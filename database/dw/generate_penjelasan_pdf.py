"""
Membuat PDF "Penjelasan Dimensi & Fakta - Data Warehouse Mitra Jalan".

Pemakaian:
    python database/dw/generate_penjelasan_pdf.py "D:/UNAIR/SEMESTER 5/BASIS DATA/Penjelasan Dimensi dan Fakta - DW Mitra Jalan.pdf"

Angka contoh diambil dari hasil ETL (php artisan dw:etl) pada data dummy 100 rental.
"""
import sys

from reportlab.graphics.shapes import Drawing, Line, Rect, String
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    KeepTogether, PageBreak, Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle,
)

OUT = sys.argv[1] if len(sys.argv) > 1 else "Penjelasan Dimensi dan Fakta - DW Mitra Jalan.pdf"

# Palet sama dengan web (putih + kuning amber)
INK = colors.HexColor("#1d1d1f")
MUTED = colors.HexColor("#6e6b63")
LINE = colors.HexColor("#e8e6df")
PAPER = colors.HexColor("#f7f7f4")
AMBER = colors.HexColor("#f5a623")
AMBER_INK = colors.HexColor("#3b2600")
CREAM = colors.HexColor("#fff6db")
CREAM_LINE = colors.HexColor("#f2dc9a")
SOFT = colors.HexColor("#fff1cf")
BLUE_BG = colors.HexColor("#eef4fb")
BLUE = colors.HexColor("#2a78d6")

base = dict(fontName="Helvetica", textColor=INK, alignment=TA_LEFT)
H1 = ParagraphStyle("H1", **{**base, "fontName": "Helvetica-Bold"}, fontSize=22, leading=26, spaceAfter=6)
H2 = ParagraphStyle("H2", **{**base, "fontName": "Helvetica-Bold"}, fontSize=15, leading=19, spaceBefore=10, spaceAfter=6)
H3 = ParagraphStyle("H3", **{**base, "fontName": "Helvetica-Bold"}, fontSize=11.5, leading=15, spaceBefore=8, spaceAfter=3)
BODY = ParagraphStyle("Body", **base, fontSize=10, leading=14.5, spaceAfter=5)
SMALL = ParagraphStyle("Small", **{**base, "textColor": MUTED}, fontSize=8.5, leading=11.5)
CELL = ParagraphStyle("Cell", **base, fontSize=8.6, leading=11)
CELLB = ParagraphStyle("CellB", **{**base, "fontName": "Helvetica-Bold"}, fontSize=8.6, leading=11)
EYEBROW = ParagraphStyle("Eyebrow", **{**base, "fontName": "Helvetica-Bold", "textColor": MUTED}, fontSize=8.5, leading=11, spaceAfter=4)


def p(text, style=BODY):
    return Paragraph(text, style)


def table(rows, widths, header=True, zebra=True):
    data = [[c if not isinstance(c, str) else Paragraph(c, CELLB if (header and i == 0) else CELL) for c in r]
            for i, r in enumerate(rows)]
    t = Table(data, colWidths=widths, repeatRows=1 if header else 0)
    style = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LINEBELOW", (0, 0), (-1, -1), 0.5, LINE),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ]
    if header:
        style += [("BACKGROUND", (0, 0), (-1, 0), PAPER)]
    t.setStyle(TableStyle(style))
    return t


def callout(text, bg=CREAM, border=CREAM_LINE):
    t = Table([[Paragraph(text, BODY)]], colWidths=[170 * mm])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), bg),
        ("BOX", (0, 0), (-1, -1), 0.8, border),
        ("LEFTPADDING", (0, 0), (-1, -1), 9), ("RIGHTPADDING", (0, 0), (-1, -1), 9),
        ("TOPPADDING", (0, 0), (-1, -1), 7), ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
    ]))
    return t


# ------------------------------------------------------------------ diagram skema
def schema_diagram():
    W, H = 170 * mm, 96 * mm
    d = Drawing(W, H)

    def box(x, y, w, h, title, lines, fact=False):
        d.add(Rect(x, y, w, h, rx=6, ry=6,
                   fillColor=AMBER if fact else colors.white,
                   strokeColor=AMBER if fact else LINE, strokeWidth=1))
        d.add(String(x + 6, y + h - 12, title, fontName="Helvetica-Bold", fontSize=8.3,
                     fillColor=AMBER_INK if fact else INK))
        for i, ln in enumerate(lines):
            d.add(String(x + 6, y + h - 23 - i * 9, ln, fontName="Helvetica", fontSize=6.8,
                         fillColor=AMBER_INK if fact else MUTED))
        return (x + w / 2, y + h / 2)

    fw, fh = 50 * mm, 22 * mm
    cx = (W - fw) / 2
    f1 = box(cx, H - 28 * mm, fw, fh, "fact_penyewaan", ["1 baris = 1 pesanan", "101 baris", "total_harga, durasi_hari"], True)
    f2 = box(cx, H - 55 * mm, fw, fh, "fact_pembayaran", ["1 baris = 1 transaksi bayar", "193 baris", "pendapatan_bersih"], True)
    f3 = box(cx, H - 82 * mm, fw, fh, "fact_pengembalian", ["1 baris = 1 pengembalian", "66 baris", "total_denda, jam_terlambat"], True)

    dw_, dh = 42 * mm, 19 * mm
    L, R = 2 * mm, W - dw_ - 2 * mm
    dims = {
        "waktu": box(L, H - 22 * mm, dw_, dh, "dim_waktu", ["tanggal > bulan > kuartal", "> tahun (730 baris)"]),
        "motor": box(R, H - 22 * mm, dw_, dh, "dim_motor (SCD 2)", ["kategori > merk > motor", "tarif berversi"]),
        "pelanggan": box(L, H - 52 * mm, dw_, dh, "dim_pelanggan", ["segmen Baru / Berulang", "28 pelanggan"]),
        "staf": box(R, H - 52 * mm, dw_, dh, "dim_staf", ["owner, admin,", "0 = tanpa petugas"]),
        "status": box(L, H - 82 * mm, dw_, dh, "dim_status_rental", ["8 status, 4 kelompok", "Berhasil/Gagal/..."]),
        "jenis": box(R, H - 82 * mm, dw_, dh, "dim_jenis_pembayaran", ["junk: tipe x metode", "x status = 30 baris"]),
    }
    links = [
        ("waktu", f1), ("waktu", f2), ("waktu", f3),
        ("motor", f1), ("motor", f2), ("motor", f3),
        ("pelanggan", f1), ("pelanggan", f2), ("pelanggan", f3),
        ("staf", f1), ("staf", f2), ("staf", f3),
        ("status", f1), ("jenis", f2),
    ]
    for name, (fx, fy) in links:
        dx, dy = dims[name]
        left = dx < fx
        x1 = (L + dw_) if left else R
        x2 = cx if left else cx + fw
        d.add(Line(x1, dy, x2, fy, strokeColor=colors.HexColor("#c9c6bd"), strokeWidth=0.6))
    d.add(String(0, 2, "Kotak kuning = tabel fakta, kotak putih = tabel dimensi. Garis = foreign key.",
                 fontName="Helvetica", fontSize=7, fillColor=MUTED))
    return d


# ------------------------------------------------------------------ halaman
def on_page(canvas, doc):
    canvas.saveState()
    canvas.setFillColor(AMBER)
    canvas.rect(0, A4[1] - 5, A4[0], 5, stroke=0, fill=1)
    canvas.setFont("Helvetica", 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(20 * mm, 10 * mm, "Data Warehouse Mitra Jalan  -  Penjelasan Dimensi & Fakta")
    canvas.drawRightString(A4[0] - 20 * mm, 10 * mm, f"Halaman {doc.page}")
    canvas.restoreState()


story = []
S = story.append

# ---------- 1. Pembuka
S(p("BASIS DATA  -  DATA WAREHOUSE, ETL, DAN OLAP", EYEBROW))
S(p("Penjelasan Dimensi &amp; Fakta<br/>Data Warehouse Mitra Jalan", H1))
S(p("Dokumen ini menjelaskan isi data warehouse <b>dw_mitra_jalan</b> yang dibangun dari web rental motor "
    "Mitra Jalan: tabel mana yang menjadi <b>fakta</b>, mana yang menjadi <b>dimensi</b>, mengapa begitu, "
    "dan bagaimana keduanya dipakai untuk analisis OLAP. Semua contoh angka berasal dari hasil ETL nyata "
    "atas 100 data rental dummy.", BODY))
S(Spacer(1, 4))
S(callout(
    "<b>Cara paling gampang membedakannya.</b> Bayangkan sebuah <b>struk</b> rental. "
    "Angka yang bisa dijumlahkan (total harga, DP, denda, lama sewa) adalah <b>fakta</b>. "
    "Keterangan yang menjelaskan angka itu (<i>kapan</i>, <i>motor apa</i>, <i>siapa penyewanya</i>, "
    "<i>siapa petugasnya</i>, <i>statusnya apa</i>) adalah <b>dimensi</b>. "
    "Fakta menjawab \"berapa?\", dimensi menjawab \"menurut apa?\"."))
S(Spacer(1, 8))
S(table([
    ["", "Tabel FAKTA", "Tabel DIMENSI"],
    ["Isinya", "Kejadian bisnis + angka (measure)", "Deskripsi / konteks (atribut teks)"],
    ["Pertanyaan", "Berapa banyak? Berapa rupiah?", "Kapan? Apa? Siapa? Bagaimana?"],
    ["Ukuran tabel", "Banyak baris, terus bertambah", "Sedikit baris, jarang berubah"],
    ["Kolom", "Foreign key ke dimensi + measure", "Surrogate key + atribut + hierarki"],
    ["Contoh di sini", "fact_penyewaan, fact_pembayaran, fact_pengembalian",
     "dim_waktu, dim_motor, dim_pelanggan, dim_staf, dim_status_rental, dim_jenis_pembayaran"],
], [28 * mm, 70 * mm, 72 * mm]))

S(p("Skema yang dipakai: Fact Constellation (Galaxy)", H2))
S(p("Karena ada <b>tiga proses bisnis</b> berbeda (menyewa, membayar, mengembalikan), dibuat tiga tabel fakta. "
    "Ketiganya <b>berbagi dimensi yang sama</b> (conformed dimension), sehingga angka antar fakta bisa dibandingkan "
    "dengan aman, misalnya \"jumlah sewa vs pendapatan vs denda per motor\". Gabungan beberapa star schema yang "
    "berbagi dimensi inilah yang disebut <b>fact constellation</b> atau <b>galaxy schema</b>."))
S(schema_diagram())

S(PageBreak())

# ---------- 2. Ringkasan tabel
S(p("Ringkasan semua tabel", H2))
S(table([
    ["Tabel", "Jenis", "Grain (1 baris =)", "Baris", "Sumber di web (OLTP)"],
    ["fact_penyewaan", "Fakta", "1 pesanan rental (semua status)", "101", "rentals"],
    ["fact_pembayaran", "Fakta", "1 transaksi bayar (DP / pelunasan / denda)", "193", "payments"],
    ["fact_pengembalian", "Fakta", "1 pengembalian motor", "66", "rental_returns"],
    ["dim_waktu", "Dimensi", "1 tanggal kalender", "730", "dibangkitkan (2025-2026) + config libur"],
    ["dim_motor", "Dimensi (SCD 2)", "1 versi motor", "6", "bikes"],
    ["dim_pelanggan", "Dimensi (SCD 1)", "1 pelanggan", "28", "users (role customer)"],
    ["dim_staf", "Dimensi", "1 petugas", "3", "users (admin, super_admin)"],
    ["dim_status_rental", "Dimensi", "1 status pesanan", "8", "config/dw.php"],
    ["dim_jenis_pembayaran", "Dimensi (junk)", "1 kombinasi tipe x metode x status", "30", "config/dw.php"],
], [36 * mm, 24 * mm, 50 * mm, 13 * mm, 47 * mm]))
S(Spacer(1, 6))
S(p("<b>Grain</b> adalah keputusan terpenting saat merancang fakta: \"satu baris mewakili apa?\". Grain yang jelas "
    "mencegah angka terhitung dua kali. Contoh: satu pesanan bisa punya dua pembayaran (DP lalu pelunasan), "
    "karena itu pembayaran dibuat menjadi fakta tersendiri, bukan kolom di fact_penyewaan."))

# ---------- 3. Dimensi
S(p("Bagian 1. Tabel Dimensi", H2))

dims = [
    ("dim_waktu  -  KAPAN?",
     "Kalender lengkap 1 Jan 2025 s.d. 31 Des 2026. Kuncinya <b>smart key</b> YYYYMMDD (contoh 20260817) supaya mudah dibaca "
     "dan otomatis urut. Dimensi ini <b>role-playing</b>: satu tabel dipakai berkali-kali dengan peran berbeda, yaitu "
     "waktu booking, waktu mulai, waktu kembali, waktu bayar, dan waktu kembali aktual.",
     "Tahun > Kuartal > Bulan > Tanggal; juga Hari kerja / Akhir pekan dan Hari libur",
     "20260817 | 2026-08-17 | Senin | minggu ke-34 | Agustus | Q3 | 2026 | akhir pekan: tidak | libur: ya (Hari Kemerdekaan RI)",
     "Pertanyaan: \"Apakah permintaan naik di akhir pekan / hari libur?\" \"Kuartal mana paling ramai?\""),
    ("dim_motor  -  MOTOR APA?",
     "Data unit motor: nama, merk, plat, kategori (matic/manual/sport), cc, kelas cc, tarif. Memakai <b>SCD Type 2</b>: "
     "jika tarif harian, kategori, atau status fisik berubah, baris lama ditutup (berlaku_sampai diisi, is_versi_aktif = 0) "
     "dan dibuat baris versi baru. Hasilnya, sewa lama tetap tercatat dengan tarif lama. Versi pertama berlaku sejak 1900-01-01.",
     "Kategori > Merk > Motor; juga Kelas CC",
     "motor_key 1 | bike_id 1 | Beat 110 | Honda | L 1101 AB | matic | 110 cc | Rp75.000/hari | versi aktif",
     "Pertanyaan: \"Motor/kategori mana paling laku dan paling menghasilkan?\" \"Bagaimana utilisasi armada?\""),
    ("dim_pelanggan  -  SIAPA PENYEWANYA?",
     "Pelanggan dari tabel users ber-role customer. Memakai <b>SCD Type 1</b> (ditimpa, tanpa riwayat) karena perubahan nama/HP "
     "tidak penting untuk analisis. Ada atribut turunan <b>segmen</b>: Berulang jika sudah 2x atau lebih sewa berhasil, selain itu Baru.",
     "Segmen > Pelanggan",
     "pelanggan_key 5 | Lili Maryadi | daftar 2025-11-14 | segmen Berulang | 12 sewa berhasil | Aktif",
     "Pertanyaan: \"Berapa pelanggan yang kembali menyewa?\" (11 Berulang, 17 Baru) \"Siapa pelanggan paling bernilai?\""),
    ("dim_staf  -  SIAPA PETUGASNYA?",
     "Petugas admin dan owner. Ada baris khusus <b>staf_key 0 = \"Tanpa petugas\"</b> (unknown member) untuk transaksi yang "
     "memang tidak ditangani petugas, misalnya pesanan yang batal sebelum serah terima. Dengan begitu foreign key di fakta "
     "tidak pernah NULL. Juga role-playing: serah terima, penerima pembayaran, pemeriksa pengembalian.",
     "Peran > Staf",
     "0 Tanpa petugas | 1 Owner Rental (Super Admin) | 2 Admin Rental (Admin)",
     "Pertanyaan: \"Beban kerja tiap petugas berapa?\""),
    ("dim_status_rental  -  BAGAIMANA HASILNYA?",
     "8 status pesanan di web dikelompokkan menjadi 4 kelompok agar mudah dianalisis.",
     "Kelompok > Status",
     "Menunggu: pending_payment, pending_verification | Berjalan: approved, active | Berhasil: completed | Gagal: cancelled, expired, no_show",
     "Pertanyaan: \"Berapa persen pesanan gagal, dan gagal karena apa?\""),
    ("dim_jenis_pembayaran  -  BAYAR APA DAN BAGAIMANA?",
     "<b>Junk dimension</b>: beberapa atribut kecil berkardinalitas rendah digabung jadi satu dimensi supaya fakta tidak penuh "
     "kolom flag. Isinya semua kombinasi tipe (DP, Pelunasan, Denda) x metode (Tunai, Transfer manual/QRIS) x status (5) = 30 baris.",
     "Tipe; Metode > Kanal; Status",
     "jenis_key 7 | DP | Transfer manual | QRIS statis | Lunas",
     "Pertanyaan: \"Pelanggan lebih banyak bayar tunai atau QRIS?\""),
]
for title, desc, hier, example, question in dims:
    S(KeepTogether([
        p(title, H3),
        p(desc),
        table([["Hierarki", hier], ["Contoh baris", example], ["Dipakai untuk", question]],
              [30 * mm, 140 * mm], header=False),
        Spacer(1, 4),
    ]))

S(PageBreak())

# ---------- 4. Fakta
S(p("Bagian 2. Tabel Fakta", H2))
S(p("Setiap tabel fakta berisi <b>foreign key</b> ke dimensi dan <b>measure</b> (angka). Semua measure di sini "
    "<b>additive</b>, artinya aman dijumlahkan menurut dimensi apa pun. Rata-rata (misalnya rata-rata durasi) "
    "dihitung saat query, bukan disimpan."))

facts = [
    ("fact_penyewaan  -  proses MENYEWA",
     "1 baris = 1 pesanan rental, termasuk yang batal atau kedaluwarsa (supaya rasio gagal bisa dihitung).",
     "waktu_booking_key, waktu_mulai_key, waktu_kembali_key (dim_waktu); motor_key; pelanggan_key; status_key; staf_serah_terima_key",
     "jumlah_sewa (selalu 1, untuk dihitung), durasi_hari, total_jam, total_harga, nominal_dp, sisa_pelunasan",
     "Total seluruh total_harga = Rp35.680.000"),
    ("fact_pembayaran  -  proses MEMBAYAR",
     "1 baris = 1 transaksi pembayaran: DP, pelunasan, atau denda.",
     "waktu_bayar_key; motor_key; pelanggan_key; jenis_key (junk); staf_penerima_key",
     "jumlah_transaksi, nominal_bayar, nominal_refund, pendapatan_bersih (= bayar - refund, hanya yang lunas)",
     "Total pendapatan_bersih = Rp25.548.000 (sama persis dengan database web)"),
    ("fact_pengembalian  -  proses MENGEMBALIKAN",
     "1 baris = 1 pengembalian motor (hanya pesanan yang selesai).",
     "waktu_kembali_aktual_key; motor_key; pelanggan_key; staf_pemeriksa_key",
     "jumlah_pengembalian, jam_terlambat, denda_telat, denda_kerusakan, denda_bensin, total_denda, is_terlambat, is_rusak",
     "Total denda = Rp1.229.000"),
]
for title, grain, fks, measures, total in facts:
    S(KeepTogether([
        p(title, H3),
        table([["Grain", grain], ["Foreign key", fks], ["Measure", measures], ["Angka nyata", total]],
              [30 * mm, 140 * mm], header=False),
        Spacer(1, 4),
    ]))

S(p("Contoh: satu pesanan dilacak di ketiga fakta", H3))
S(p("Pesanan <b>BK-20251125-0001</b> (dipesan 25 Nov 2025, sewa 1-3 Des 2025, 2 hari) muncul seperti ini:"))
S(table([
    ["Tabel", "Baris", "Isi singkat"],
    ["fact_penyewaan", "1", "waktu_booking 20251125, mulai 20251201, kembali 20251203, status Selesai, durasi 2 hari, "
                            "total_harga Rp450.000, DP Rp135.000 (30%), sisa Rp315.000"],
    ["fact_pembayaran", "2", "DP Rp135.000 via QRIS tanggal 20251125 + pelunasan Rp315.000 tunai tanggal 20251201. "
                             "Jumlahnya Rp450.000 = total_harga di fact_penyewaan"],
    ["fact_pengembalian", "1", "waktu kembali aktual, petugas pemeriksa, jam terlambat dan denda (jika ada)"],
], [34 * mm, 14 * mm, 122 * mm]))
S(Spacer(1, 5))
S(callout("<b>Kenapa tidak satu tabel fakta saja?</b> Kalau DP dan pelunasan digabung ke fact_penyewaan, pesanan dengan "
          "dua pembayaran harus dibuat dua baris, lalu total_harga ikut terhitung dua kali. Memisahkan fakta per proses "
          "bisnis menjaga grain tetap bersih.", bg=BLUE_BG, border=colors.HexColor("#cfe0f5")))

# ---------- 5. ETL
S(p("Bagian 3. Alur ETL", H2))
S(table([
    ["Tahap", "Yang dilakukan"],
    ["Extract", "Membaca tabel operasional web: rentals, payments, rental_returns, bikes, users."],
    ["Transform", "Membuat smart key tanggal, menandai akhir pekan & libur nasional, mengelompokkan status, menghitung "
                  "segmen pelanggan, durasi, sisa pelunasan (total - DP), pendapatan bersih (bayar - refund), dan "
                  "mencari versi motor yang berlaku saat pesanan dibuat (lookup SCD 2)."],
    ["Load", "Dimensi di-upsert (motor diberi versi baru bila berubah), fakta dimuat ulang penuh di dalam satu "
             "transaksi. Setiap run dicatat di etl_log."],
    ["Jadwal", "Otomatis setiap hari jam 01:00 (scheduler Laravel), atau manual: <b>php artisan dw:etl</b> / "
               "tombol \"Jalankan ETL\" di Dashboard admin."],
], [26 * mm, 144 * mm]))

# ---------- 6. OLAP
S(p("Bagian 4. Operasi OLAP dengan angka nyata", H2))
S(p("Operasi ini bisa dicoba langsung di menu <b>Admin &gt; OLAP Explorer</b> atau lewat file "
    "<b>database/dw/olap_queries.sql</b>."))
S(table([
    ["Operasi", "Arti", "Contoh hasil"],
    ["Roll-up", "Naik level hierarki (lebih ringkas)", "Pendapatan per kuartal 2026: Q1 Rp4.170.000, Q2 Rp7.578.000, "
                                                       "Q3 Rp3.906.000, Q4 Rp7.272.000 -> tahun 2026 Rp22.926.000 -> "
                                                       "semua tahun Rp25.548.000"],
    ["Drill-down", "Turun level (lebih rinci)", "Sewa Q1 2026 = 20, dibuka per bulan: Januari 7, Februari 7, Maret 6"],
    ["Slice", "Mengunci satu dimensi", "Hanya kategori matic: Vario 160 Rp6.500.000, NMAX 155 Rp4.725.000, "
                                       "Beat 110 Rp4.125.000, Scoopy 110 Rp3.230.000"],
    ["Dice", "Mengunci beberapa dimensi", "Tahun 2026 + status Berhasil + segmen Berulang, dikelompokkan per kategori: "
                                          "matic 33 sewa, manual 10, sport 5"],
    ["Pivot", "Menukar baris dan kolom", "Kategori x kelompok status: matic 46 berhasil / 14 gagal, manual 12 / 8, "
                                         "sport 8 / 2; total 101 = 66 + 8 + 3 + 24"],
], [22 * mm, 42 * mm, 106 * mm]))

# ---------- 6b. 5W 1H
S(PageBreak())
S(p("Bagian 5. Pertanyaan 5W 1H", H2))
S(p("Cara cepat menguji apakah data warehouse sudah lengkap adalah mengajukan pertanyaan <b>5W 1H</b>: "
    "What, Who, When, Where, Why, How. Setiap \"W\" biasanya dijawab oleh sebuah <b>dimensi</b>, sedangkan "
    "\"berapa banyak / berapa rupiah\" dijawab oleh <b>measure</b> di tabel fakta. Tabel di bawah memetakan "
    "keenam pertanyaan ke tabel di dw_mitra_jalan."))
S(table([
    ["Pertanyaan", "Artinya", "Dijawab oleh", "Kolom kunci"],
    ["<b>What</b> (Apa)", "Apa yang disewa / dibayar?", "dim_motor, dim_jenis_pembayaran",
     "kategori, merk, nama_motor, tipe pembayaran"],
    ["<b>Who</b> (Siapa)", "Siapa yang menyewa dan siapa yang melayani?", "dim_pelanggan, dim_staf",
     "segmen, nama pelanggan, nama & peran staf"],
    ["<b>When</b> (Kapan)", "Kapan dipesan, dimulai, dikembalikan, dibayar?", "dim_waktu (role-playing) + jam_mulai",
     "tahun, kuartal, bulan, nama_hari, akhir pekan, hari libur"],
    ["<b>Where</b> (Di mana)", "Di mana / lewat kanal apa transaksi terjadi?", "dim_jenis_pembayaran (kanal)",
     "kanal: Tunai di lokasi / QRIS statis"],
    ["<b>Why</b> (Mengapa)", "Mengapa pesanan gagal atau kena denda?", "dim_status_rental + measure denda",
     "kelompok & label status, denda_telat / kerusakan / bensin"],
    ["<b>How</b> (Bagaimana)", "Bagaimana proses sewa & bayar berjalan?", "dim_jenis_pembayaran + measure fakta",
     "metode bayar, durasi_hari, jam_terlambat"],
    ["<b>How much / many</b>", "Berapa nilainya?", "measure di ketiga fakta",
     "jumlah_sewa, total_harga, pendapatan_bersih, total_denda"],
], [28 * mm, 46 * mm, 46 * mm, 50 * mm]))
S(Spacer(1, 6))
S(callout("<b>Catatan untuk \"Where\".</b> Mitra Jalan hanya punya satu lokasi dan tabel users tidak menyimpan alamat, "
          "jadi tidak ada dimensi lokasi (misalnya kota atau cabang). Pertanyaan \"di mana\" dijawab lewat <b>kanal "
          "transaksi</b>: tunai di lokasi atau QRIS. Jika nanti ada cabang baru, cukup tambahkan <b>dim_lokasi</b> dan "
          "kolom lokasi_key di ketiga fakta."))

S(p("Contoh pertanyaan bisnis dan jawabannya", H3))
S(p("Semua jawaban di bawah dihitung dari data warehouse (100 rental dummy + 1 data awal) dan bisa diulang di "
    "OLAP Explorer."))

w5h1 = [
    ("WHAT - Apa", [
        ("Motor apa yang paling sering dipesan?", "fact_penyewaan x dim_motor",
         "Beat 110 (22 pesanan), disusul Supra X 125 (21) dan Scoopy 110 (17)."),
        ("Kategori apa yang paling diminati?", "fact_penyewaan x dim_motor.kategori",
         "Matic: 68 dari 101 pesanan (67%). Manual 21, sport 12."),
        ("Motor apa yang paling menghasilkan uang?", "fact_penyewaan (status Berhasil) x dim_motor",
         "R15 (Rp6.525.000 dari 8 sewa) walau jarang dipesan, karena tarifnya paling tinggi."),
    ]),
    ("WHO - Siapa", [
        ("Siapa pelanggan paling bernilai?", "fact_pembayaran x dim_pelanggan",
         "Lili Maryadi (Rp7.190.000), Victoria Mandasari (Rp2.932.000), Daruna Tampubolon (Rp2.500.000)."),
        ("Seberapa penting pelanggan berulang?", "fact_pembayaran x dim_pelanggan.segmen",
         "11 pelanggan Berulang menyumbang Rp22.177.000 (87% pendapatan). 9 pelanggan Baru: Rp3.371.000."),
        ("Siapa petugas yang paling sibuk?", "fact_penyewaan x dim_staf (serah terima)",
         "Admin Rental 53 serah terima, Owner 17. Ada 31 pesanan \"Tanpa petugas\" (batal / belum diambil)."),
    ]),
    ("WHEN - Kapan", [
        ("Hari apa paling ramai motor diambil?", "fact_penyewaan x dim_waktu (waktu_mulai).nama_hari",
         "Sabtu (24 sewa). Hari lain 11-15 sewa. Minggu justru sepi (11)."),
        ("Bulan apa puncak permintaan?", "fact_penyewaan x dim_waktu (bulan)",
         "November 2026 (15 sewa), lalu Desember 2025 (11) dan Januari 2026 (10)."),
        ("Jam berapa pelanggan paling sering mulai sewa?", "fact_penyewaan.jam_mulai",
         "Pukul 13.00 dan 14.00 (masing-masing 10 sewa), lalu pukul 07.00 (9)."),
    ]),
    ("WHERE - Di mana", [
        ("Lewat kanal mana uang masuk?", "fact_pembayaran x dim_jenis_pembayaran.kanal",
         "QRIS statis 107 transaksi (Rp16.094.000, 63%); tunai di lokasi 63 transaksi (Rp9.454.000, 37%)."),
        ("Di mana pelunasan biasanya dibayar?", "fact_pembayaran (tipe Pelunasan) x kanal",
         "Kebanyakan tunai di lokasi saat serah terima (46 transaksi) dibanding QRIS (24)."),
    ]),
    ("WHY - Mengapa", [
        ("Mengapa pesanan gagal?", "fact_penyewaan x dim_status_rental (kelompok Gagal)",
         "24 dari 101 pesanan gagal (24%): dibatalkan 11, kedaluwarsa karena DP tidak dibayar 9, tidak datang 4."),
        ("Mengapa pelanggan kena denda?", "fact_pengembalian (denda_telat, denda_kerusakan, denda_bensin)",
         "Kerusakan terbesar nilainya (Rp700.000, 5 kasus); telat Rp349.000 (7 kasus); bensin Rp180.000 (11 kasus)."),
    ]),
    ("HOW - Bagaimana", [
        ("Bagaimana pelanggan membayar?", "fact_pembayaran x dim_jenis_pembayaran.metode",
         "Transfer manual/QRIS 107 transaksi vs tunai 63; DP selalu lewat QRIS (78 transaksi)."),
        ("Bagaimana pola sewanya?", "fact_penyewaan (durasi_hari, total_harga) status Berhasil",
         "Rata-rata 2,5 hari per sewa dengan nilai rata-rata Rp320.758."),
        ("Bagaimana kebiasaan memesan?", "fact_penyewaan x dim_waktu (booking vs mulai)",
         "Rata-rata dipesan 4,1 hari sebelum motor diambil; 53 pesanan dibuat 3 hari atau lebih sebelumnya."),
    ]),
]
for title, rows in w5h1:
    S(KeepTogether([
        p(title, H3),
        table([["Pertanyaan bisnis", "Tabel yang dipakai", "Jawaban dari data"]] + [list(r) for r in rows],
              [50 * mm, 50 * mm, 70 * mm]),
        Spacer(1, 3),
    ]))

code_title = p("Contoh query: <i>\"Hari apa paling ramai?\"</i> (When)", H3)
code = Table([[Paragraph(
    "SELECT w.nama_hari, SUM(f.jumlah_sewa) AS jumlah_sewa<br/>"
    "FROM fact_penyewaan f<br/>"
    "JOIN dim_waktu w ON w.waktu_key = f.waktu_mulai_key<br/>"
    "GROUP BY w.hari_ke, w.nama_hari<br/>"
    "ORDER BY w.hari_ke;",
    ParagraphStyle("Code", fontName="Courier", fontSize=8.5, leading=11.5, textColor=INK))]],
    colWidths=[170 * mm])
code.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), PAPER), ("BOX", (0, 0), (-1, -1), 0.6, LINE),
    ("LEFTPADDING", (0, 0), (-1, -1), 9), ("TOPPADDING", (0, 0), (-1, -1), 7), ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
]))
S(KeepTogether([
    code_title,
    code,
    p("Polanya selalu sama: <b>W</b> menentukan dimensi yang di-JOIN dan di-GROUP BY, <b>How much</b> menentukan "
      "measure yang di-SUM.", SMALL),
]))

# ---------- 7. Catatan & glosarium
S(p("Catatan kesesuaian dengan web", H2))
S(p("Rancangan mengikuti data yang benar-benar ada di web. Dua hal di rancangan awal kelompok tidak dipakai karena "
    "tidak ada di aplikasi: (1) metode pembayaran <b>Midtrans</b> - web hanya mendukung Tunai dan Transfer manual/QRIS "
    "statis; (2) atribut <b>alamat</b> pelanggan - tabel users tidak memiliki kolom alamat."))

S(p("Glosarium singkat", H2))
S(table([
    ["Istilah", "Arti"],
    ["Surrogate key", "Kunci buatan DW (motor_key, pelanggan_key) yang terpisah dari id di web, supaya riwayat bisa disimpan."],
    ["Grain", "Tingkat detail satu baris fakta."],
    ["Measure", "Kolom angka di fakta yang dijumlahkan atau dirata-rata."],
    ["Conformed dimension", "Dimensi yang sama dipakai oleh beberapa fakta."],
    ["Role-playing dimension", "Satu dimensi dipakai dengan beberapa peran (waktu booking, waktu mulai, dll.)."],
    ["SCD Type 1 / Type 2", "Cara menangani perubahan data dimensi: Type 1 menimpa, Type 2 menyimpan versi lama."],
    ["Junk dimension", "Gabungan beberapa atribut kecil (flag/kode) menjadi satu dimensi."],
    ["Unknown member", "Baris khusus (key 0) untuk nilai yang tidak ada, agar foreign key tidak NULL."],
    ["ROLAP", "OLAP yang langsung memakai tabel relasional (SQL GROUP BY), seperti OLAP Explorer di web ini."],
], [42 * mm, 128 * mm]))
S(Spacer(1, 6))
S(p("File terkait di proyek: database/dw/ddl_dw_mitra_jalan.sql (DDL), database/dw/schema_mitra_jalan.xml "
    "(skema Mondrian 3 kubus), database/dw/olap_queries.sql (contoh query OLAP), app/Services/DataWarehouse/ (ETL & OLAP).",
    SMALL))

doc = SimpleDocTemplate(OUT, pagesize=A4, leftMargin=20 * mm, rightMargin=20 * mm, topMargin=18 * mm,
                        bottomMargin=18 * mm, title="Penjelasan Dimensi & Fakta - DW Mitra Jalan",
                        author="Mitra Jalan", subject="Data warehouse, ETL, OLAP")
doc.build(story, onFirstPage=on_page, onLaterPages=on_page)
print("PDF dibuat:", OUT)
