<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\Produksi;
use App\Models\ProdukCustomResep;
use App\Models\ProjectMpDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProduksiStatusService
{
    /** Stok keluar sekali saat detail meninggalkan grup awal, termasuk bahan resep. */
    public function apply(OrderDetail|ProjectMpDetail $detail, int $produksiId): void
    {
        DB::transaction(function () use ($detail, $produksiId) {
            $detail = $detail instanceof OrderDetail
                ? OrderDetail::whereKey($detail->id)->lockForUpdate()->firstOrFail()
                : ProjectMpDetail::whereKey($detail->id)->lockForUpdate()->firstOrFail();
            $tujuan = Produksi::findOrFail($produksiId);
            $asal = Produksi::find($detail->produksi_id);
            $sumber = $detail instanceof OrderDetail ? 'order' : 'project';
            $detail->loadMissing(['produk.produkModel', 'bahanCustom', $sumber === 'order' ? 'order' : 'projectMp']);

            if (Produksi::shouldDeductStock($asal, $tujuan)) {
                $this->potongStok($detail, $sumber);
            }

            $detail->update([
                'produksi_id' => $produksiId,
                'hpp' => app(ProdukCustomService::class)->isCustom((int) $detail->produk_id)
                    ? $detail->hpp : $detail->produk?->hpp,
            ]);
        });
    }

    private function potongStok(OrderDetail|ProjectMpDetail $detail, string $sumber): void
    {
        $parent = $sumber === 'order' ? $detail->order : $detail->projectMp;
        $cabangId = resolve_cabang_id($parent->cabang_id);
        $parentId = (int) $parent->id;
        $stok = app(StokService::class);

        if ($detail->bahanCustom->isNotEmpty()) {
            foreach ($detail->bahanCustom as $bahan) {
                // Order lama yang sudah melalui Ambil Barang tidak boleh dipotong ulang.
                if ($bahan->ambil_barang_id) continue;
                $label = "status bahan {$sumber} #{$bahan->id}";
                if ($this->sudahDipotong($bahan->produk_id, $cabangId, $label)) continue;
                $stok->kurang((int) $bahan->produk_id, $cabangId, (int) $bahan->jumlah,
                    'jual', $label . ' (' . $parent->nota . ')', $parentId, [], false);
            }
            return;
        }

        // Jangan membuat resep baru untuk detail lama yang tidak punya snapshot.
        if ($detail->material_ids !== null && ! $detail->custom_resep_disalin_at
            && $detail->produk && app(ProdukCustomService::class)->isCustom((int) $detail->produk_id)
            && ProdukCustomResep::where('produk_id', $detail->produk_id)->where('jenis', 'bahan')->exists()) {
            throw ValidationException::withMessages(['produksi_id' => 'Bahan resep belum disiapkan. Periksa resep dan SKU pesanan.']);
        }

        if (! Produksi::produkTracksStock($detail) || $detail->pengambilan()->exists()) return;
        $label = "status produk {$sumber} #{$detail->id}";
        if ($this->sudahDipotong($detail->produk_id, $cabangId, $label)) return;

        // Order Shopee lama telah dipotong ketika buffer diproses (sebelum alur status ini).
        if ($sumber === 'project' && DB::table('produk_stoks')->where('detail_id', $parentId)
            ->where('produk_id', $detail->produk_id)->where('cabang_id', $cabangId)
            ->where('kode', 'shp')->whereNull('deleted_at')->exists()) return;
        if ($sumber === 'order' && DB::table('produk_stoks')->where('detail_id', $parentId)
            ->where('produk_id', $detail->produk_id)->where('cabang_id', $cabangId)
            ->where('kode', 'jual')->where('keterangan', 'like', 'barang dijual ke%')
            ->whereNull('deleted_at')->exists()) return;

        $stok->kurang((int) $detail->produk_id, $cabangId, (int) $detail->jumlah,
            'jual', $label . ' (' . $parent->nota . ')', $parentId, [], false);
    }

    private function sudahDipotong(int $produkId, int $cabangId, string $label): bool
    {
        return DB::table('produk_stoks')->where('produk_id', $produkId)->where('cabang_id', $cabangId)
            ->where('keterangan', 'like', $label . ' (%)')->whereNull('deleted_at')->exists();
    }
}
