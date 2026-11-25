<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ShopeeApi;
use App\Models\Marketplace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopeeMissingBufferController extends Controller
{
    use ShopeeApi;

    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'marketplace_id' => 'nullable|integer|min:1',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $to = isset($input['to']) ? strtotime($input['to']) : time();
        $from = isset($input['from']) ? strtotime($input['from']) : $to - 86400;

        // Shopee membatasi satu rentang get_order_list maksimal 15 hari.
        if ($to > time() || $from >= $to || $to - $from > 15 * 86400) {
            return response()->json(['success' => false, 'message' => 'Rentang waktu harus di masa lalu dan maksimal 15 hari.'], 422);
        }

        $query = Marketplace::withoutGlobalScope('cabang')
            ->where('marketplace', 'shopee')
            ->whereNotNull('shop_id');
        if (isset($input['marketplace_id'])) {
            $query->whereKey($input['marketplace_id']);
        }

        $shops = $query->get();
        if ($shops->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Toko Shopee tidak ditemukan.'], 404);
        }

        $missing = [];
        $errors = [];
        $checked = 0;

        foreach ($shops as $shop) {
            $cursor = '';
            $page = 0;

            do {
                $params = [
                    'time_range_field' => 'create_time',
                    'time_from' => $from,
                    'time_to' => $to,
                    'page_size' => 100,
                ];
                if ($cursor !== '') {
                    $params['cursor'] = $cursor;
                }

                $api = $this->ambilApi($shop, 'order/get_order_list', $params);
                if (!is_array($api) || !empty($api['error']) || !isset($api['response']['order_list'])) {
                    $errors[] = ['marketplace_id' => $shop->id, 'shop_id' => $shop->shop_id, 'error' => $api['error'] ?? 'Respons Shopee tidak valid'];
                    break;
                }

                $orders = collect($api['response']['order_list'])
                    ->filter(fn ($order) => !empty($order['order_sn']) && !in_array($order['order_status'] ?? null, ['CANCELLED', 'COMPLETED'], true));
                $notas = $orders->pluck('order_sn')->unique()->values()->all();
                $checked += count($notas);

                if ($notas) {
                    $inBuffer = DB::table('marketplace_buffers')->whereIn('nota', $notas)->pluck('nota')->flip();
                    $inProject = DB::table('project_mps')->whereIn('nota', $notas)->pluck('nota')->flip();

                    foreach ($orders as $order) {
                        $nota = $order['order_sn'];
                        if (!$inBuffer->has($nota) && !$inProject->has($nota)) {
                            $missing[$nota] = [
                                'nota' => $nota,
                                'status' => $order['order_status'] ?? null,
                                'shop_id' => $shop->shop_id,
                                'marketplace_id' => $shop->id,
                                'nama_marketplace' => $shop->nama,
                            ];
                        }
                    }
                }

                $more = $api['response']['more'] ?? false;
                $next = $api['response']['next_cursor'] ?? '';
                $page++;
                if ($more && ($next === '' || $next === $cursor || $page >= 100)) {
                    $errors[] = ['marketplace_id' => $shop->id, 'shop_id' => $shop->shop_id, 'error' => 'Pagination Shopee tidak selesai; perkecil rentang waktu.'];
                    break;
                }
                $cursor = $next;
            } while ($more);
        }

        return response()->json([
            'success' => empty($errors),
            'from' => date('Y-m-d H:i:s', $from),
            'to' => date('Y-m-d H:i:s', $to),
            'checked' => $checked,
            'total_missing' => count($missing),
            'missing' => array_values($missing),
            'errors' => $errors,
        ]);
    }
}
