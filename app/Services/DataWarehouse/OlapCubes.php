<?php

namespace App\Services\DataWarehouse;

/**
 * Metadata kubus OLAP (setara skema Mondrian di database/dw/schema_mitra_jalan.xml).
 *
 * Setiap dimensi punya hierarki level dari yang paling umum ke paling detail.
 * Level = kolom yang dikelompokkan (cols) + cara menampilkannya (format).
 * Dimensi role-playing (waktu, staf) dipakai ulang dengan foreign key berbeda per kubus.
 */
class OlapCubes
{
    /** @return array<string, array<string, mixed>> */
    public static function dimensions(): array
    {
        return [
            'waktu' => [
                'table' => 'dim_waktu', 'pk' => 'waktu_key',
                'hierarchy' => ['tahun', 'kuartal', 'bulan', 'tanggal'],
                'levels' => [
                    'tahun' => ['label' => 'Tahun', 'cols' => ['tahun'], 'format' => fn ($v) => (string) $v[0]],
                    'kuartal' => ['label' => 'Kuartal', 'cols' => ['tahun', 'kuartal'], 'format' => fn ($v) => "{$v[1]} {$v[0]}"],
                    'bulan' => ['label' => 'Bulan', 'cols' => ['tahun', 'bulan', 'nama_bulan'], 'format' => fn ($v) => "{$v[2]} {$v[0]}"],
                    'tanggal' => ['label' => 'Tanggal', 'cols' => ['tanggal'], 'format' => fn ($v) => date('d M Y', strtotime((string) $v[0]))],
                    'nama_hari' => ['label' => 'Hari dalam minggu', 'cols' => ['hari_ke', 'nama_hari'], 'format' => fn ($v) => $v[1]],
                    'akhir_pekan' => ['label' => 'Akhir pekan?', 'cols' => ['is_akhir_pekan'], 'format' => fn ($v) => $v[0] ? 'Akhir pekan' : 'Hari kerja'],
                    'libur' => ['label' => 'Hari libur?', 'cols' => ['is_hari_libur'], 'format' => fn ($v) => $v[0] ? 'Hari libur' : 'Bukan libur'],
                ],
            ],
            'motor' => [
                'table' => 'dim_motor', 'pk' => 'motor_key',
                'hierarchy' => ['kategori', 'merk', 'motor'],
                'levels' => [
                    'kategori' => ['label' => 'Kategori', 'cols' => ['kategori'], 'format' => fn ($v) => ucfirst((string) $v[0])],
                    'merk' => ['label' => 'Merk', 'cols' => ['merk'], 'format' => fn ($v) => (string) $v[0]],
                    'motor' => ['label' => 'Motor', 'cols' => ['nama_motor', 'plat_nomor'], 'format' => fn ($v) => "{$v[0]} ({$v[1]})"],
                    'kelas_cc' => ['label' => 'Kelas cc', 'cols' => ['kelas_cc'], 'format' => fn ($v) => (string) $v[0]],
                ],
            ],
            'pelanggan' => [
                'table' => 'dim_pelanggan', 'pk' => 'pelanggan_key',
                'hierarchy' => ['segmen', 'pelanggan'],
                'levels' => [
                    'segmen' => ['label' => 'Segmen', 'cols' => ['segmen'], 'format' => fn ($v) => (string) $v[0]],
                    'pelanggan' => ['label' => 'Pelanggan', 'cols' => ['nama'], 'format' => fn ($v) => (string) $v[0]],
                ],
            ],
            'status' => [
                'table' => 'dim_status_rental', 'pk' => 'status_key',
                'hierarchy' => ['kelompok', 'status'],
                'levels' => [
                    'kelompok' => ['label' => 'Kelompok status', 'cols' => ['kelompok_status'], 'format' => fn ($v) => (string) $v[0]],
                    'status' => ['label' => 'Status', 'cols' => ['label_status'], 'format' => fn ($v) => (string) $v[0]],
                ],
            ],
            'jenis' => [
                'table' => 'dim_jenis_pembayaran', 'pk' => 'jenis_key',
                'hierarchy' => ['tipe', 'metode', 'status_bayar'],
                'levels' => [
                    'tipe' => ['label' => 'Tipe pembayaran', 'cols' => ['label_tipe'], 'format' => fn ($v) => (string) $v[0]],
                    'metode' => ['label' => 'Metode', 'cols' => ['label_metode'], 'format' => fn ($v) => (string) $v[0]],
                    'status_bayar' => ['label' => 'Status pembayaran', 'cols' => ['label_status'], 'format' => fn ($v) => (string) $v[0]],
                    'kanal' => ['label' => 'Kanal', 'cols' => ['kanal'], 'format' => fn ($v) => (string) $v[0]],
                ],
            ],
            'staf' => [
                'table' => 'dim_staf', 'pk' => 'staf_key',
                'hierarchy' => ['role', 'staf'],
                'levels' => [
                    'role' => ['label' => 'Role', 'cols' => ['role'], 'format' => fn ($v) => (string) $v[0]],
                    'staf' => ['label' => 'Staf', 'cols' => ['nama'], 'format' => fn ($v) => (string) $v[0]],
                ],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function cubes(): array
    {
        return [
            'penyewaan' => [
                'label' => 'Penyewaan',
                'fact' => 'fact_penyewaan',
                'grain' => '1 baris = 1 pesanan (booking)',
                // usage alias => [dimensi, foreign key, label]
                'dimensions' => [
                    'waktu' => ['waktu', 'waktu_mulai_key', 'Waktu mulai sewa'],
                    'waktu_booking' => ['waktu', 'waktu_booking_key', 'Waktu booking'],
                    'motor' => ['motor', 'motor_key', 'Motor'],
                    'pelanggan' => ['pelanggan', 'pelanggan_key', 'Pelanggan'],
                    'status' => ['status', 'status_key', 'Status rental'],
                    'staf' => ['staf', 'staf_serah_terima_key', 'Staf serah terima'],
                ],
                'measures' => [
                    'jumlah_sewa' => ['Jumlah sewa', 'SUM(f.jumlah_sewa)', 'angka'],
                    'total_harga' => ['Total harga', 'SUM(f.total_harga)', 'rupiah'],
                    'nominal_dp' => ['Nominal DP', 'SUM(f.nominal_dp)', 'rupiah'],
                    'sisa_pelunasan' => ['Sisa pelunasan', 'SUM(f.sisa_pelunasan)', 'rupiah'],
                    'durasi_hari' => ['Durasi (hari)', 'SUM(f.durasi_hari)', 'angka'],
                    'total_jam' => ['Total jam', 'SUM(f.total_jam)', 'angka'],
                    'rata_harga' => ['Rata-rata harga per sewa', 'AVG(f.total_harga)', 'rupiah'],
                ],
                'default' => ['measure' => 'jumlah_sewa', 'rows' => 'waktu.kuartal', 'cols' => 'motor.kategori'],
            ],
            'pembayaran' => [
                'label' => 'Pembayaran',
                'fact' => 'fact_pembayaran',
                'grain' => '1 baris = 1 transaksi pembayaran (DP, pelunasan, atau denda)',
                'dimensions' => [
                    'waktu' => ['waktu', 'waktu_bayar_key', 'Waktu bayar'],
                    'motor' => ['motor', 'motor_key', 'Motor'],
                    'pelanggan' => ['pelanggan', 'pelanggan_key', 'Pelanggan'],
                    'jenis' => ['jenis', 'jenis_key', 'Jenis pembayaran'],
                    'staf' => ['staf', 'staf_penerima_key', 'Staf penerima'],
                ],
                'measures' => [
                    'pendapatan_bersih' => ['Pendapatan bersih', 'SUM(f.pendapatan_bersih)', 'rupiah'],
                    'nominal_bayar' => ['Nominal bayar', 'SUM(f.nominal_bayar)', 'rupiah'],
                    'nominal_refund' => ['Nominal refund', 'SUM(f.nominal_refund)', 'rupiah'],
                    'jumlah_transaksi' => ['Jumlah transaksi', 'SUM(f.jumlah_transaksi)', 'angka'],
                ],
                'default' => ['measure' => 'pendapatan_bersih', 'rows' => 'waktu.kuartal', 'cols' => 'jenis.tipe'],
            ],
            'pengembalian' => [
                'label' => 'Pengembalian',
                'fact' => 'fact_pengembalian',
                'grain' => '1 baris = 1 proses pengembalian motor',
                'dimensions' => [
                    'waktu' => ['waktu', 'waktu_kembali_aktual_key', 'Waktu kembali aktual'],
                    'motor' => ['motor', 'motor_key', 'Motor'],
                    'pelanggan' => ['pelanggan', 'pelanggan_key', 'Pelanggan'],
                    'staf' => ['staf', 'staf_pemeriksa_key', 'Staf pemeriksa'],
                ],
                'measures' => [
                    'total_denda' => ['Total denda', 'SUM(f.total_denda)', 'rupiah'],
                    'denda_telat' => ['Denda telat', 'SUM(f.denda_telat)', 'rupiah'],
                    'denda_kerusakan' => ['Denda kerusakan', 'SUM(f.denda_kerusakan)', 'rupiah'],
                    'denda_bensin' => ['Denda bensin', 'SUM(f.denda_bensin)', 'rupiah'],
                    'jumlah_pengembalian' => ['Jumlah pengembalian', 'SUM(f.jumlah_pengembalian)', 'angka'],
                    'jumlah_terlambat' => ['Jumlah terlambat', 'SUM(CASE WHEN f.is_terlambat THEN 1 ELSE 0 END)', 'angka'],
                    'jam_terlambat' => ['Jam terlambat', 'SUM(f.jam_terlambat)', 'angka'],
                    'rata_jam_telat' => ['Rata-rata jam telat', 'AVG(f.jam_terlambat)', 'desimal'],
                ],
                'default' => ['measure' => 'total_denda', 'rows' => 'waktu.kuartal', 'cols' => 'motor.kategori'],
            ],
        ];
    }

    /** Level yang dipakai sebagai slicer (filter) per kubus: semua level di hierarki kecuali yang paling detail. */
    public static function slicers(string $cube): array
    {
        $slicers = [];
        foreach (self::cubes()[$cube]['dimensions'] as $usage => [$dimension]) {
            $hierarchy = self::dimensions()[$dimension]['hierarchy'];
            foreach (array_slice($hierarchy, 0, -1) as $level) {
                $slicers[] = "{$usage}.{$level}";
            }
        }

        return $slicers;
    }
}
