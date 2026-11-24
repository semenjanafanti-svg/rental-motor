-- =====================================================================
-- Contoh operasi OLAP (ROLAP) di atas data warehouse dw_mitra_jalan.
-- Jalankan di MySQL:  USE dw_mitra_jalan;  lalu salin query per blok.
-- Query yang sama dijalankan oleh halaman Admin > OLAP Explorer.
-- =====================================================================

USE dw_mitra_jalan;

-- ---------------------------------------------------------------------
-- 1. ROLL-UP: pendapatan bersih dari bulan naik ke kuartal lalu ke tahun.
--    WITH ROLLUP menambah baris subtotal per tahun dan grand total (NULL).
-- ---------------------------------------------------------------------
SELECT w.tahun,
       w.kuartal,
       SUM(f.pendapatan_bersih) AS pendapatan_bersih
FROM fact_pembayaran f
JOIN dim_waktu w ON w.waktu_key = f.waktu_bayar_key
GROUP BY w.tahun, w.kuartal WITH ROLLUP;

-- ---------------------------------------------------------------------
-- 2. DRILL-DOWN: jumlah sewa per kuartal 2026, lalu dibuka ke bulan Q1 2026.
-- ---------------------------------------------------------------------
-- 2a. level kuartal
SELECT w.kuartal, SUM(f.jumlah_sewa) AS jumlah_sewa
FROM fact_penyewaan f
JOIN dim_waktu w ON w.waktu_key = f.waktu_booking_key
WHERE w.tahun = 2026
GROUP BY w.kuartal
ORDER BY w.kuartal;

-- 2b. drill-down Q1 2026 ke level bulan
SELECT w.bulan, w.nama_bulan, SUM(f.jumlah_sewa) AS jumlah_sewa
FROM fact_penyewaan f
JOIN dim_waktu w ON w.waktu_key = f.waktu_booking_key
WHERE w.tahun = 2026 AND w.kuartal = 'Q1'
GROUP BY w.bulan, w.nama_bulan
ORDER BY w.bulan;

-- ---------------------------------------------------------------------
-- 3. SLICE: satu dimensi dikunci (kategori motor = matic).
-- ---------------------------------------------------------------------
SELECT m.nama_motor,
       SUM(f.jumlah_sewa) AS jumlah_sewa,
       SUM(f.total_harga) AS total_harga
FROM fact_penyewaan f
JOIN dim_motor m ON m.motor_key = f.motor_key
WHERE m.kategori = 'matic'
GROUP BY m.nama_motor
ORDER BY total_harga DESC;

-- ---------------------------------------------------------------------
-- 4. DICE: beberapa dimensi dikunci sekaligus
--    (tahun 2026, status Berhasil, pelanggan segmen Berulang).
-- ---------------------------------------------------------------------
SELECT m.kategori,
       SUM(f.jumlah_sewa) AS jumlah_sewa,
       SUM(f.total_harga) AS total_harga
FROM fact_penyewaan f
JOIN dim_waktu w          ON w.waktu_key = f.waktu_booking_key
JOIN dim_status_rental s  ON s.status_key = f.status_key
JOIN dim_pelanggan p      ON p.pelanggan_key = f.pelanggan_key
JOIN dim_motor m          ON m.motor_key = f.motor_key
WHERE w.tahun = 2026
  AND s.kelompok_status = 'Berhasil'
  AND p.segmen = 'Berulang'
GROUP BY m.kategori;

-- ---------------------------------------------------------------------
-- 5. PIVOT: kategori motor sebagai baris, kelompok status sebagai kolom.
--    Menukar baris dan kolom = menukar posisi kedua dimensi.
-- ---------------------------------------------------------------------
SELECT m.kategori,
       SUM(CASE WHEN s.kelompok_status = 'Berhasil' THEN f.jumlah_sewa ELSE 0 END) AS berhasil,
       SUM(CASE WHEN s.kelompok_status = 'Berjalan' THEN f.jumlah_sewa ELSE 0 END) AS berjalan,
       SUM(CASE WHEN s.kelompok_status = 'Menunggu' THEN f.jumlah_sewa ELSE 0 END) AS menunggu,
       SUM(CASE WHEN s.kelompok_status = 'Gagal'    THEN f.jumlah_sewa ELSE 0 END) AS gagal,
       SUM(f.jumlah_sewa) AS total
FROM fact_penyewaan f
JOIN dim_motor m         ON m.motor_key = f.motor_key
JOIN dim_status_rental s ON s.status_key = f.status_key
GROUP BY m.kategori WITH ROLLUP;

-- ---------------------------------------------------------------------
-- 6. Contoh lintas fakta (fact constellation): sewa, pendapatan, dan denda
--    per motor. Tiap fakta diagregasi dulu, baru digabung lewat dimensi
--    bersama (conformed dimension) supaya tidak terjadi double counting.
-- ---------------------------------------------------------------------
SELECT m.nama_motor,
       COALESCE(s.jumlah_sewa, 0)       AS jumlah_sewa,
       COALESCE(b.pendapatan_bersih, 0) AS pendapatan_bersih,
       COALESCE(k.total_denda, 0)       AS total_denda
FROM (SELECT DISTINCT bike_id, nama_motor FROM dim_motor WHERE is_versi_aktif = 1) m
LEFT JOIN (SELECT dm.bike_id, SUM(f.jumlah_sewa) AS jumlah_sewa
           FROM fact_penyewaan f JOIN dim_motor dm ON dm.motor_key = f.motor_key
           GROUP BY dm.bike_id) s ON s.bike_id = m.bike_id
LEFT JOIN (SELECT dm.bike_id, SUM(f.pendapatan_bersih) AS pendapatan_bersih
           FROM fact_pembayaran f JOIN dim_motor dm ON dm.motor_key = f.motor_key
           GROUP BY dm.bike_id) b ON b.bike_id = m.bike_id
LEFT JOIN (SELECT dm.bike_id, SUM(f.total_denda) AS total_denda
           FROM fact_pengembalian f JOIN dim_motor dm ON dm.motor_key = f.motor_key
           GROUP BY dm.bike_id) k ON k.bike_id = m.bike_id
ORDER BY pendapatan_bersih DESC;

-- ---------------------------------------------------------------------
-- 7. Role-playing dimension: dim_waktu dipakai dua kali dalam satu query
--    (tanggal booking vs tanggal mulai) untuk melihat jarak pemesanan.
-- ---------------------------------------------------------------------
SELECT CASE
         WHEN DATEDIFF(wm.tanggal, wb.tanggal) = 0 THEN 'Hari yang sama'
         WHEN DATEDIFF(wm.tanggal, wb.tanggal) <= 2 THEN '1-2 hari sebelumnya'
         ELSE '3+ hari sebelumnya'
       END AS jarak_pesan,
       SUM(f.jumlah_sewa) AS jumlah_sewa
FROM fact_penyewaan f
JOIN dim_waktu wb ON wb.waktu_key = f.waktu_booking_key
JOIN dim_waktu wm ON wm.waktu_key = f.waktu_mulai_key
GROUP BY jarak_pesan
ORDER BY MIN(DATEDIFF(wm.tanggal, wb.tanggal));
