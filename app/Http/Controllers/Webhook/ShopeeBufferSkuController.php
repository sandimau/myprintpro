<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ShopeeApi;
use App\Models\Marketplace;
use App\Services\ProdukCustomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopeeBufferSkuController extends Controller
{
    use ShopeeApi;

    public function __invoke(Request $request, ProdukCustomService $produkCustom): JsonResponse
    {
        $input = $request->validate([
            'marketplace_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $query = DB::table('marketplace_buffers')
            ->where('mp', 'shopee')->whereNull('project_id')
            ->whereNotIn('status', ['CANCELLED', 'COMPLETED'])
            ->whereNotNull('nota')->orderBy('id');
        if (isset($input['marketplace_id'])) {
            $shopId = Marketplace::withoutGlobalScope('cabang')
                ->where('marketplace', 'shopee')->whereKey($input['marketplace_id'])->value('shop_id');
            if (!$shopId) {
                return response()->json(['success' => false, 'message' => 'Toko Shopee tidak ditemukan.'], 404);
            }
            $query->where('shop_id', $shopId);
        }

        $buffers = $query->limit($input['limit'] ?? 100)->get();
        $invalid = [];
        $errors = [];
        $checked = 0;

        foreach ($buffers->groupBy('shop_id') as $shopId => $rows) {
            $shop = Marketplace::withoutGlobalScope('cabang')
                ->where('marketplace', 'shopee')->where('shop_id', $shopId)->first();
            if (!$shop) {
                $errors[] = ['shop_id' => $shopId, 'message' => 'Toko Shopee tidak ditemukan.'];
                continue;
            }

            foreach ($rows->chunk(50) as $batch) {
                $notas = $batch->pluck('nota')->unique()->values()->all();
                $api = $this->ambilApi($shop, 'order/get_order_detail', [
                    'order_sn_list' => implode(',', $notas),
                    'response_optional_fields' => 'item_list',
                ]);
                if (!is_array($api) || !empty($api['error']) || !isset($api['response']['order_list'])) {
                    $errors[] = ['shop_id' => $shopId, 'notas' => $notas,
                        'message' => is_array($api) ? ($api['error'] ?? 'Respons Shopee tidak valid.') : 'Respons Shopee tidak valid.'];
                    continue;
                }

                $orders = collect($api['response']['order_list'])->keyBy('order_sn');
                foreach ($batch as $buffer) {
                    $order = $orders->get($buffer->nota);
                    if (!$order || !isset($order['item_list']) || !is_array($order['item_list'])) {
                        $errors[] = ['shop_id' => $shopId, 'nota' => $buffer->nota,
                            'message' => 'Detail item pesanan tidak tersedia dari Shopee.'];
                        continue;
                    }

                    $checked++;
                    foreach ($order['item_list'] as $item) {
                        $sku = trim((string) (($item['model_sku'] ?? null) ?: ($item['item_sku'] ?? '')));
                        if ($sku === 'NON_PRODUK') {
                            continue;
                        }

                        try {
                            $parsed = $produkCustom->parseSku($sku);
                            $reason = DB::table('produks')->where('id', $parsed['produk_id'])->exists()
                                ? null : 'Produk ID ' . $parsed['produk_id'] . ' tidak ada di sistem.';
                        } catch (ValidationException $e) {
                            $reason = $e->errors()['produk_id'][0] ?? $e->getMessage();
                        }

                        if ($reason !== null) {
                            $invalid[] = [
                                'nota' => $buffer->nota,
                                'shop_id' => $shopId,
                                'marketplace_id' => $shop->id,
                                'nama_marketplace' => $shop->nama,
                                'sku' => $sku,
                                'reason' => $reason,
                            ];
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => empty($errors),
            'total_buffer' => $buffers->count(),
            'checked' => $checked,
            'total_invalid' => count($invalid),
            'invalid' => $invalid,
            'errors' => $errors,
        ]);
    }
}
