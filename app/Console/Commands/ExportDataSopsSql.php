<?php

namespace App\Console\Commands;

use App\Models\DataSop;
use App\Models\SopBagian;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportDataSopsSql extends Command
{
    protected $signature = 'sop:export-sql {file? : Lokasi file SQL hasil ekspor}';

    protected $description = 'Ekspor struktur, data SOP, bagian dan hak akses ke SQL untuk phpMyAdmin';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->error('Ekspor SQL phpMyAdmin membutuhkan koneksi MySQL.');
            return self::FAILURE;
        }

        $file = $this->argument('file') ?: database_path('sql/data_sops_phpmyadmin.sql');
        $folder = dirname($file);
        if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
            $this->error('Direktori ekspor tidak dapat dibuat.');
            return self::FAILURE;
        }

        $handle = fopen($file, 'wb');
        if (!$handle) {
            $this->error('File ekspor tidak dapat ditulis.');
            return self::FAILURE;
        }

        $quote = fn ($value) => $value === null ? 'NULL' : DB::getPdo()->quote((string) $value);
        $time = fn ($value) => $value?->format('Y-m-d H:i:s');

        try {
            fwrite($handle, "-- Ekspor Data SOP MyPrintPro (struktur + data + izin akses).\n"
                . "-- Impor ke database proyek yang sudah memiliki tabel roles, permissions, dan migrations.\n"
                . "-- Disarankan backup database tujuan terlebih dahulu. Tidak ada DROP/DELETE dalam file ini.\n"
                . "-- Jika tujuan sudah memiliki data_sops dengan struktur lama (kolom bagian), jalankan migrasi Laravel terlebih dahulu.\n"
                . "-- INSERT IGNORE mempertahankan baris SOP dan bagian yang sudah ada pada database tujuan.\n"
                . "SET NAMES utf8mb4;\n\n");

            foreach (['sop_bagians', 'data_sops'] as $table) {
                $schema = DB::selectOne('SHOW CREATE TABLE `' . $table . '`');
                $create = $schema->{'Create Table'};
                $create = preg_replace('/^CREATE TABLE /', 'CREATE TABLE IF NOT EXISTS ', $create);
                fwrite($handle, $create . ";\n\n");
            }

            $bagians = SopBagian::orderBy('id')->get();
            foreach ($bagians->chunk(50) as $batch) {
                $values = $batch->map(fn ($item) => '(' . implode(', ', [
                    $quote($item->nama), $quote($item->slug),
                    $quote($time($item->created_at)), $quote($time($item->updated_at)),
                ]) . ')')->implode(",\n");
                fwrite($handle, "INSERT IGNORE INTO `sop_bagians` (`nama`, `slug`, `created_at`, `updated_at`) VALUES\n{$values};\n\n");
            }

            $count = 0;
            $slugs = $bagians->pluck('slug', 'id');
            foreach (DataSop::orderBy('id')->get()->chunk(20) as $batch) {
                $values = $batch->map(function ($item) use ($quote, $time, $slugs) {
                    $slug = $slugs[$item->sop_bagian_id] ?? null;
                    if (!$slug) {
                        throw new \RuntimeException("SOP {$item->id} tidak memiliki bagian yang valid.");
                    }
                    $bagian = '(SELECT `id` FROM `sop_bagians` WHERE `slug` = ' . $quote($slug) . ' LIMIT 1)';
                    return '(' . implode(', ', [
                        $item->id, $quote($item->judul), $quote($item->isi), $bagian,
                        (int) $item->global, $quote($time($item->created_at)), $quote($time($item->updated_at)),
                    ]) . ')';
                })->implode(",\n");
                fwrite($handle, "INSERT IGNORE INTO `data_sops` (`id`, `judul`, `isi`, `sop_bagian_id`, `global`, `created_at`, `updated_at`) VALUES\n{$values};\n\n");
                $count += $batch->count();
            }

            fwrite($handle, <<<'SQL'
-- Berikan izin SOP kepada role yang sudah ada (tanpa membuat/mengubah role).
INSERT IGNORE INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`) VALUES
('sop_access', 'web', NOW(), NOW()),
('sop_manage', 'web', NOW(), NOW());

INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id FROM `permissions` p CROSS JOIN `roles` r
WHERE p.name = 'sop_access' AND p.guard_name = 'web'
  AND r.guard_name = 'web' AND LOWER(r.name) <> 'konten';

INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.id, r.id FROM `permissions` p CROSS JOIN `roles` r
WHERE p.name = 'sop_manage' AND p.guard_name = 'web'
  AND r.guard_name = 'web' AND LOWER(r.name) IN ('super', 'admin', 'manager');

-- Catat migrasi SOP agar php artisan migrate tidak mencoba membuat tabel yang sama.
SET @sop_batch = (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_25_000001_create_data_sops_table', @sop_batch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_25_000001_create_data_sops_table');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_25_000002_add_sop_permissions', @sop_batch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_25_000002_add_sop_permissions');
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_25_000003_create_sop_bagians_table', @sop_batch
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_11_25_000003_create_sop_bagians_table');
SQL
            );
        } finally {
            fclose($handle);
        }

        $this->info("{$count} SOP dan {$bagians->count()} bagian diekspor ke {$file}");
        return self::SUCCESS;
    }
}
