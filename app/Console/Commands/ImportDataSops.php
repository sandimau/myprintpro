<?php

namespace App\Console\Commands;

use App\Services\DataSopOrganizer;
use App\Models\SopBagian;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDataSops extends Command
{
    protected $signature = 'sop:import {file? : Lokasi dump SQL SOP}';

    protected $description = 'Impor SOP dari dump lama tanpa menghapus atau menimpa data yang sudah ada';

    public function handle(DataSopOrganizer $organizer): int
    {
        $file = $this->argument('file') ?: database_path('seeders/data_sops.sql');
        if (!is_file($file)) {
            $this->error("File SQL tidak ditemukan: {$file}");
            return self::FAILURE;
        }
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Impor dump SQL ini membutuhkan koneksi MySQL.');
            return self::FAILURE;
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            $this->error('File SQL tidak dapat dibaca.');
            return self::FAILURE;
        }

        // Dump lama memakai kolom company_id/kategori_id yang tidak dipakai proyek ini.
        // Tabel sementara menerima format lama, lalu hanya kolom yang relevan disalin.
        DB::statement('CREATE TEMPORARY TABLE sop_import_legacy (
            id BIGINT UNSIGNED PRIMARY KEY, judul TEXT, isi LONGTEXT, bagian VARCHAR(100),
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL, company_id BIGINT NULL,
            `global` TINYINT NULL, kategori_id BIGINT NULL
        )');

        try {
            $statement = '';
            $quoted = false;
            $escaped = false;
            $count = 0;
            foreach (str_split($sql) as $char) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($quoted && $char === '\\') {
                    $escaped = true;
                } elseif ($char === "'") {
                    $quoted = !$quoted;
                }

                $statement .= $char;
                if ($char === ';' && !$quoted) {
                    if (preg_match('/^\s*INSERT INTO `data_sops`/i', $statement)) {
                        DB::statement(str_replace('INSERT INTO `data_sops`', 'INSERT INTO `sop_import_legacy`', trim($statement)));
                        $count++;
                    }
                    $statement = '';
                }
            }

            if ($count === 0) {
                $this->error('Tidak ada INSERT data_sops dalam file.');
                return self::FAILURE;
            }

            DB::transaction(function () {
                $alias = ['depan' => 'umum', 'pricelist' => 'daftar-harga'];
                $bagians = SopBagian::pluck('id', 'slug');
                foreach (DB::table('sop_import_legacy')->orderBy('id')->get()->chunk(50) as $batch) {
                    $rows = [];
                    foreach ($batch as $item) {
                        $slug = \Illuminate\Support\Str::slug(trim((string) $item->bagian));
                        if ($slug === '') $slug = in_array($item->id, [275, 276, 277], true) ? 'cetak' : 'peraturan';
                        $slug = $alias[$slug] ?? $slug;
                        $rows[] = [
                            'id' => $item->id, 'judul' => $item->judul, 'isi' => $item->isi,
                            'sop_bagian_id' => $bagians[$slug] ?? $bagians['umum'],
                            'global' => $item->global ?? 0,
                            'created_at' => $item->created_at ?? now(),
                            'updated_at' => $item->updated_at ?? now(),
                        ];
                    }
                    DB::table('data_sops')->insertOrIgnore($rows);
                }
            });
            $hasil = $organizer->run();
            $this->info("SOP berhasil diimpor dan dirapikan ({$hasil['hapus']} duplikat dihapus). Data dengan ID yang sudah ada tidak ditimpa.");
            return self::SUCCESS;
        } finally {
            DB::statement('DROP TEMPORARY TABLE IF EXISTS sop_import_legacy');
        }
    }
}
