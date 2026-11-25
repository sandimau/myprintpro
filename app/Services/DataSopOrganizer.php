<?php

namespace App\Services;

use App\Models\DataSop;
use App\Models\SopBagian;
use App\Support\SopHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataSopOrganizer
{
    public function run(): array
    {
        return DB::transaction(function () {
            $sops = DataSop::orderBy('id')->get();
            $backup = 'sop-backups/data-sops-' . now()->format('Ymd-His-u') . '.json';
            if (!Storage::disk('local')->put($backup, $sops->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                throw new \RuntimeException('Gagal menyimpan cadangan data SOP.');
            }

            $kelompok = $sops->groupBy(function (DataSop $sop) {
                $judul = mb_strtolower(html_entity_decode(trim($sop->judul), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $judul = preg_replace('/\s*&\s*/u', ' & ', $judul);
                $judul = preg_replace('/\s+/u', ' ', trim($judul));

                return $sop->sop_bagian_id . '|' . $judul;
            });

            $hapus = [];
            foreach ($kelompok as $items) {
                if ($items->count() < 2) {
                    continue;
                }
                // Konten terlengkap dipertahankan, jika sama pilih ID tertua.
                $urut = $items->sort(function ($a, $b) {
                    return mb_strlen($b->isi) <=> mb_strlen($a->isi) ?: $a->id <=> $b->id;
                })->values();
                foreach ($urut->skip(1) as $sop) {
                    $hapus[] = $sop->id;
                }
            }

            if ($hapus) {
                DataSop::whereIn('id', $hapus)->delete();
            }
            $dirapikan = 0;
            $umumId = SopBagian::where('slug', 'umum')->value('id');
            foreach ($sops as $sop) {
                if (in_array($sop->id, $hapus, true)) {
                    continue;
                }
                $judul = trim(preg_replace('/\s+/u', ' ', html_entity_decode($sop->judul, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $isi = preg_match('/<\s*[a-z][^>]*>/i', $sop->isi)
                    ? SopHtml::clean($sop->isi)
                    : nl2br(e($sop->isi));
                $perubahan = [];
                if ($judul !== $sop->judul) $perubahan['judul'] = $judul;
                if ($isi !== $sop->isi) $perubahan['isi'] = $isi;
                if (!$sop->sop_bagian_id && $umumId) $perubahan['sop_bagian_id'] = $umumId;
                if ($perubahan) {
                    $sop->update($perubahan);
                    $dirapikan++;
                }
            }

            return ['hapus' => count($hapus), 'dirapikan' => $dirapikan, 'backup' => storage_path('app/' . $backup)];
        });
    }
}
