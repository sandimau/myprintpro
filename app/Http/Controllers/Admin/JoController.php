<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Produksi;
use App\Models\ProjectMp;
use App\Services\JoBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JoController extends Controller
{
    public function index(Request $request, JoBoard $board)
    {
        $statusIds = $board->statusIds();
        $orders = $board->orders($statusIds);
        $counts = [
            'semua' => $orders->count(),
            'offline' => $orders->where('sumber', 'offline')->count(),
            'online' => $orders->where('sumber', 'online')->count(),
        ];

        $sumber = $request->query('sumber', 'all');
        if (in_array($sumber, ['offline', 'online'], true)) {
            $orders = $orders->where('sumber', $sumber);
        }
        $cari = trim((string) $request->query('cari', ''));
        if ($cari !== '') {
            $orders = $orders->filter(fn ($order) => str_contains(
                mb_strtolower(($order['nota'] ?? '').' '.($order['konsumen'] ?? '')),
                mb_strtolower($cari)
            ));
        }

        return view('admin.jo.index', [
            'orders' => $orders,
            'counts' => $counts,
            'statuses' => Produksi::whereIn('id', $statusIds)->orderBy('urutan')->pluck('nama'),
            'sumber' => $sumber,
            'cari' => $cari,
        ]);
    }

    public function offline(Order $order)
    {
        abort_if(DB::table('project_mps')->where('order_id', $order->id)->exists(), 404);
        $order->load(['kontak', 'orderDetail.produk.produkModel', 'orderDetail.produksi', 'orderDetail.proses']);

        return view('admin.jo.detail', [
            'nota' => $order->nota,
            'konsumen' => $order->kontak?->nama ?? $order->konsumen_detail,
            'total' => $order->total,
            'deadline' => $order->deathline,
            'tanggal' => $order->created_at,
            'marketplace' => null,
            'sumber' => 'offline',
            'items' => $order->orderDetail,
        ]);
    }

    public function online(ProjectMp $projectMp)
    {
        $projectMp->load(['marketplace', 'details.produk.produkModel', 'details.produksi']);

        return view('admin.jo.detail', [
            'nota' => $projectMp->nota,
            'konsumen' => $projectMp->konsumen,
            'total' => $projectMp->total,
            'deadline' => $projectMp->deadline,
            'tanggal' => $projectMp->created_at,
            'marketplace' => $projectMp->marketplace?->nama,
            'sumber' => 'online',
            'items' => $projectMp->details,
        ]);
    }

    public function settings(JoBoard $board)
    {
        return view('admin.jo.settings', [
            'statuses' => Produksi::orderedForStatusSelect(),
            'selected' => $board->statusIds(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'status_ids' => ['sometimes', 'array'],
            'status_ids.*' => ['required', 'integer', 'distinct', Rule::exists('produksis', 'id')->whereNull('deleted_at')],
        ]);

        DB::transaction(function () use ($data) {
            DB::table('jo_statuses')->delete();
            foreach ($data['status_ids'] ?? [] as $id) {
                DB::table('jo_statuses')->insert([
                    'produksi_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('jo.settings')->with('success', 'Status JO berhasil disimpan.');
    }
}
