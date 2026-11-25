-- Backfill bahan resep order lama yang sudah melewati grup "awal" sebelum
-- pemotongan stok dipindahkan ke perubahan status. MySQL 8 / MariaDB.
-- Jalankan pada database yang sudah dibackup, saat proses order/cron dihentikan.
-- Tidak menyentuh order di grup awal (akan dipotong saat status pindah), batal,
-- status kosong, order terhapus, maupun bahan yang sudah diambil/dipotong.
-- Seluruh perintah dalam file ini harus dijalankan pada SATU koneksi SQL.

CREATE TEMPORARY TABLE backfill_bahan_lewat_awal AS
SELECT b.id AS bahan_id, b.sumber, b.detail_id, b.produk_id, b.jumlah,
       pr.hpp, c.id AS cabang_id,
       CASE WHEN b.sumber = 'order' THEN o.id ELSE p.id END AS dokumen_id,
       CASE WHEN b.sumber = 'order' THEN o.nota ELSE p.nota END AS nota,
       status_produksi.grup AS grup
FROM order_custom_bahans b
JOIN produks pr ON pr.id = b.produk_id
LEFT JOIN order_details od ON b.sumber = 'order' AND od.id = b.detail_id
    AND od.deleted_at IS NULL
LEFT JOIN orders o ON o.id = od.order_id AND o.deleted_at IS NULL
LEFT JOIN project_mp_details pd ON b.sumber = 'project' AND pd.id = b.detail_id
LEFT JOIN project_mps p ON p.id = pd.project_id
JOIN cabangs c ON c.id = CASE WHEN b.sumber = 'order' THEN o.cabang_id ELSE p.cabang_id END
JOIN produksis status_produksi ON status_produksi.id =
    CASE WHEN b.sumber = 'order' THEN od.produksi_id ELSE pd.produksi_id END
LEFT JOIN ambil_barangs a ON a.sumber = 'bahan' AND a.detail_id = b.id
WHERE b.sumber IN ('order', 'project')
  AND b.jumlah > 0
  AND b.ambil_barang_id IS NULL AND a.id IS NULL
  AND LOWER(TRIM(status_produksi.grup)) NOT IN ('awal', 'batal')
  AND LOWER(TRIM(status_produksi.nama)) <> 'batal'
  AND NOT EXISTS (
      SELECT 1 FROM produk_stoks s
      WHERE s.produk_id = b.produk_id AND s.cabang_id = c.id
        AND s.deleted_at IS NULL
        AND s.keterangan LIKE CONCAT('status bahan ', b.sumber, ' #', b.id, ' (%)')
  )
  -- Riwayat gudanglanyard/ambil manual yang sudah memotong stok jangan dipotong ulang.
  AND NOT EXISTS (
      SELECT 1 FROM produk_stoks s
      WHERE s.produk_id = b.produk_id AND s.cabang_id = c.id
        AND s.detail_id = CASE WHEN b.sumber = 'order' THEN o.id ELSE p.id END
        AND s.deleted_at IS NULL AND s.kurang > 0
        AND (s.keterangan LIKE 'bahan custom %'
          OR s.keterangan LIKE 'ambil bahan resep order %')
  );

-- Pratinjau sebelum mutasi; pada dump 25 Nov 2026 sekitar 135 baris.
SELECT sumber, grup, COUNT(*) AS jumlah_bahan, SUM(jumlah) AS total_unit
FROM backfill_bahan_lewat_awal GROUP BY sumber, grup ORDER BY sumber, grup;
SELECT bahan_id, sumber, detail_id, produk_id, cabang_id, jumlah, nota, grup
FROM backfill_bahan_lewat_awal ORDER BY sumber, detail_id, bahan_id LIMIT 100;

START TRANSACTION;

-- Format keterangan sama dengan ProduksiStatusService agar perubahan status
-- berikutnya dan pengulangan skrip tidak mendebit bahan yang sama lagi.
INSERT INTO produk_stoks
    (produk_id, cabang_id, tambah, kurang, hpp, kode, keterangan, detail_id, created_at, updated_at)
SELECT x.produk_id, x.cabang_id, 0, x.jumlah, COALESCE(x.hpp, 0), 'jual',
       CONCAT('status bahan ', x.sumber, ' #', x.bahan_id, ' (', COALESCE(x.nota, ''), ')'),
       x.dokumen_id, NOW(), NOW()
FROM backfill_bahan_lewat_awal x;

-- INSERT SQL tidak menjalankan event Eloquent ProdukStok::saved. Hitung ulang
-- cache saldo tahun ini dengan rumus yang sama seperti StokService::updateLastStok.
INSERT INTO produk_last_stoks (produk_id, cabang_id, tahun, saldo, created_at, updated_at)
SELECT x.produk_id, x.cabang_id, YEAR(CURDATE()),
       COALESCE((SELECT lama.saldo FROM produk_last_stoks lama
                 WHERE lama.produk_id = x.produk_id AND lama.cabang_id = x.cabang_id
                   AND lama.tahun < YEAR(CURDATE())
                 ORDER BY lama.tahun DESC LIMIT 1), 0)
       + COALESCE((SELECT SUM(COALESCE(s.tambah, 0) - COALESCE(s.kurang, 0))
                   FROM produk_stoks s
                   WHERE s.produk_id = x.produk_id AND s.cabang_id = x.cabang_id
                     AND s.deleted_at IS NULL
                     AND s.created_at >= MAKEDATE(YEAR(CURDATE()), 1)
                     AND s.created_at < MAKEDATE(YEAR(CURDATE()) + 1, 1)), 0),
       NOW(), NOW()
FROM (SELECT DISTINCT produk_id, cabang_id FROM backfill_bahan_lewat_awal) x
ON DUPLICATE KEY UPDATE saldo = VALUES(saldo), updated_at = NOW();

-- Tandai produk bahan untuk sinkronisasi ulang ke Shopee (setara markDirty).
INSERT INTO shopee_stock_syncs
    (produk_id, dirty_at, last_error, synced_marketplaces, created_at, updated_at)
SELECT DISTINCT produk_id, NOW(), NULL, NULL, NOW(), NOW()
FROM backfill_bahan_lewat_awal
ON DUPLICATE KEY UPDATE dirty_at = VALUES(dirty_at), last_error = NULL,
    synced_marketplaces = NULL, updated_at = NOW();

SELECT COUNT(*) AS mutasi_dibuat FROM backfill_bahan_lewat_awal;
COMMIT;

DROP TEMPORARY TABLE backfill_bahan_lewat_awal;
