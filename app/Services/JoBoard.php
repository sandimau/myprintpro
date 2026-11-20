<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\ProjectMpDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Antrean produksi JO, hanya membaca order yang masih aktif. */
class JoBoard
{
    public function statusIds(): array
    {
        return DB::table('jo_statuses')->pluck('produksi_id')->map(fn ($id) => (int) $id)->all();
    }

    public function orders(array $statusIds): Collection
    {
        if (! $statusIds) {
            return collect();
        }

        $offline = OrderDetail::query()
            ->whereIn('produksi_id', $statusIds)
            ->whereHas('order')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('project_mps')
                ->whereColumn('project_mps.order_id', 'order_details.order_id'))
            ->with(['order.kontak', 'produk.produkModel', 'produksi', 'proses'])
            ->get()
            ->groupBy('order_id')
            ->map(function ($details) {
                $order = $details->first()->order;

                return [
                    'sumber' => 'offline',
                    'id' => $order->id,
                    'nota' => $order->nota,
                    'konsumen' => $order->kontak?->nama ?? $order->konsumen_detail ?? '',
                    'total' => $order->total,
                    'tanggal' => $order->created_at,
                    'label' => 'Offline',
                    'deadline' => $order->deathline,
                    'items' => $details,
                ];
            });

        // Cek buffer TERAKHIR, bukan sekadar pernah berstatus aktif: detail lama
        // tidak boleh muncul kembali setelah pesanan marketplace selesai.
        $online = ProjectMpDetail::query()
            ->whereIn('produksi_id', $statusIds)
            ->whereHas('projectMp', function ($q) {
                $q->where(function ($active) {
                    $active->whereHas('buffer', fn ($b) => $b
                        ->whereIn('status', ['PROCESSED', 'READY_TO_SHIP', 'UNPAID']))
                        // myprintpro belum punya penanda ada_proses; buffer custom
                        // menjadi penanda order batal yang sudah masuk produksi.
                        ->orWhereHas('buffer', fn ($b) => $b
                            ->where('status', 'IN_CANCEL')->where('custom', 1));
                });
            })
            ->with(['projectMp.marketplace', 'produk.produkModel', 'produksi'])
            ->get()
            ->groupBy('project_id')
            ->map(function ($details) {
                $project = $details->first()->projectMp;

                return [
                    'sumber' => 'online',
                    'id' => $project->id,
                    'nota' => $project->nota,
                    'konsumen' => $project->konsumen ?? '',
                    'total' => $project->total,
                    'tanggal' => $project->created_at,
                    'label' => $project->marketplace?->nama ?? 'Online',
                    'deadline' => $project->deadline,
                    'items' => $details,
                ];
            });

        return $offline->values()->concat($online->values())
            ->sortBy('tanggal')->values();
    }
}
