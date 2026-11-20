-- Instalasi fitur JO untuk MySQL/MariaDB melalui phpMyAdmin.
-- Setara dengan migrasi 2026_11_19_000001_create_jo_statuses_table.php.
-- Pilih database myprintpro yang dituju, lalu impor file ini.
-- Prasyarat: tabel produksis dan migrations sudah ada.
-- Aman diimpor ulang; tidak menghapus pilihan status JO yang sudah tersimpan.
-- Tidak perlu diimpor ke database lokal yang sudah menjalankan migrasi JO.

CREATE TABLE IF NOT EXISTS `jo_statuses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `produksi_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `jo_statuses_produksi_id_unique` (`produksi_id`),
    CONSTRAINT `jo_statuses_produksi_id_foreign`
        FOREIGN KEY (`produksi_id`) REFERENCES `produksis` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tandai migrasi Laravel agar artisan migrate tidak mencoba membuat ulang tabel.
SET @jo_batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_19_000001_create_jo_statuses_table', @jo_batch
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_11_19_000001_create_jo_statuses_table'
);
