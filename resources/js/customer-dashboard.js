import Chart from 'chart.js/auto';

/*
 * Dashboard customer (/dashboard-saya).
 * Data mentah (sewa & pembayaran milik customer ini) dikirim sebagai JSON, lalu diagregasi di sini.
 * - Filter "Periode" di kanan atas mengatur kartu ringkasan dan semua grafik yang memilih "Ikuti periode".
 * - Setiap grafik punya filter tahun sendiri yang bisa berbeda dari periode halaman.
 * Warna seri sama dengan dashboard owner: biru lalu oranye (lolos uji buta warna).
 */

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const WEEKDAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
const STATUS_ORDER = ['Selesai', 'Berjalan', 'Menunggu', 'Dibatalkan', 'Kedaluwarsa', 'Tidak datang'];
const DURATION_BUCKETS = [
    ['1 hari', (d) => d <= 1],
    ['2 hari', (d) => d === 2],
    ['3–4 hari', (d) => d >= 3 && d <= 4],
    ['5–7 hari', (d) => d >= 5 && d <= 7],
    ['> 7 hari', (d) => d > 7],
];

const BLUE = '#2a78d6';
const ORANGE = '#eb6834';
const GRID = '#efeee9';
const MUTED = '#6e6b63';
const INK = '#1d1d1f';

const number = (v) => Number(v).toLocaleString('id-ID');
const rupiah = (v) => 'Rp' + Math.round(v).toLocaleString('id-ID');
const rupiahShort = (v) => {
    if (v >= 1e6) return 'Rp' + (v / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' jt';
    if (v >= 1e3) return 'Rp' + (v / 1e3).toLocaleString('id-ID') + ' rb';
    return 'Rp' + v;
};

/** Sewa yang benar-benar terjadi (motor dipakai). */
const used = (r) => r.status === 'Selesai' || r.status === 'Berjalan';

function bar(label, data, color = BLUE, extra = {}) {
    return {
        label,
        data,
        backgroundColor: color,
        hoverBackgroundColor: color,
        borderWidth: 0,
        borderRadius: 4,
        borderSkipped: 'start',
        maxBarThickness: 28,
        categoryPercentage: 0.8,
        barPercentage: 0.9,
        ...extra,
    };
}

function options({ format = number, legend = false, horizontal = false, stacked = false } = {}) {
    const value = horizontal ? 'x' : 'y';
    const category = horizontal ? 'y' : 'x';

    return {
        indexAxis: horizontal ? 'y' : 'x',
        maintainAspectRatio: false,
        animation: { duration: 250 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                display: legend,
                position: 'top',
                align: 'start',
                labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, boxHeight: 10, color: INK },
            },
            tooltip: {
                callbacks: { label: (ctx) => `${ctx.dataset.label}: ${format(ctx.parsed[value])}` },
            },
        },
        scales: {
            [category]: { stacked, grid: { display: false }, border: { color: GRID }, ticks: { color: MUTED } },
            [value]: {
                stacked,
                beginAtZero: true,
                grid: { color: GRID },
                border: { display: false },
                ticks: { color: MUTED, precision: 0, callback: (v) => (format === rupiah ? rupiahShort(v) : number(v)) },
            },
        },
    };
}

/** Label bulan: 12 bulan untuk satu tahun, atau rentang bulan berisi data untuk semua tahun. */
function monthKeys(rows, year) {
    if (year) return MONTHS.map((_, i) => [Number(year), i + 1]);
    if (!rows.length) return [];

    const keys = rows.map((r) => r.tahun * 12 + (r.bulan - 1));
    const out = [];
    for (let k = Math.min(...keys); k <= Math.max(...keys); k++) out.push([Math.floor(k / 12), (k % 12) + 1]);
    return out;
}

const monthLabel = ([y, m], year) => (year ? MONTHS[m - 1] : `${MONTHS[m - 1]} ${String(y).slice(2)}`);

/** Definisi tiap grafik: menerima data yang sudah difilter tahun, mengembalikan config + tabel. */
const CHARTS = {
    spending({ payments }, year) {
        const keys = monthKeys(payments, year);
        const sum = (jenis) =>
            keys.map(([y, m]) =>
                payments.filter((p) => p.tahun === y && p.bulan === m && p.jenis === jenis).reduce((t, p) => t + p.nominal, 0),
            );
        const sewa = sum('Sewa');
        const denda = sum('Denda');
        const labels = keys.map((k) => monthLabel(k, year));

        return {
            empty: !payments.length,
            config: {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        bar('Sewa', sewa, BLUE, { borderRadius: 0 }),
                        bar('Denda', denda, ORANGE, { borderRadius: { topLeft: 4, topRight: 4 } }),
                    ],
                },
                options: options({ format: rupiah, legend: true, stacked: true }),
            },
            table: {
                head: ['Bulan', 'Sewa', 'Denda'],
                rows: labels.map((l, i) => [l, rupiah(sewa[i]), rupiah(denda[i])]).filter((_, i) => sewa[i] || denda[i]),
            },
        };
    },

    bikes({ rentals }) {
        const counts = {};
        rentals.filter(used).forEach((r) => (counts[r.motor] = (counts[r.motor] || 0) + 1));
        const top = Object.entries(counts).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).slice(0, 5);

        return {
            empty: !top.length,
            config: {
                type: 'bar',
                data: { labels: top.map((t) => t[0]), datasets: [bar('Disewa', top.map((t) => t[1]))] },
                options: options({ format: (v) => `${number(v)} kali`, horizontal: true }),
            },
            table: { head: ['Motor', 'Disewa'], rows: top.map(([m, c]) => [m, `${c} kali`]) },
        };
    },

    status({ rentals }) {
        const rows = STATUS_ORDER.map((s) => [s, rentals.filter((r) => r.status === s).length]).filter(([, c]) => c > 0);

        return {
            empty: !rows.length,
            config: {
                type: 'bar',
                data: { labels: rows.map((r) => r[0]), datasets: [bar('Pesanan', rows.map((r) => r[1]))] },
                options: options({ format: (v) => `${number(v)} pesanan`, horizontal: true }),
            },
            table: { head: ['Status', 'Pesanan'], rows: rows.map(([s, c]) => [s, number(c)]) },
        };
    },

    duration({ rentals }) {
        const usedRows = rentals.filter(used);
        const rows = DURATION_BUCKETS.map(([label, test]) => [label, usedRows.filter((r) => test(r.hari)).length]);

        return {
            empty: !usedRows.length,
            config: {
                type: 'bar',
                data: { labels: rows.map((r) => r[0]), datasets: [bar('Sewa', rows.map((r) => r[1]))] },
                options: options({ format: (v) => `${number(v)} sewa` }),
            },
            table: { head: ['Lama sewa', 'Jumlah'], rows: rows.map(([l, c]) => [l, number(c)]) },
        };
    },

    weekday({ rentals }) {
        const usedRows = rentals.filter(used);
        const rows = WEEKDAYS.map((d, i) => [d, usedRows.filter((r) => r.hari_ke === i + 1).length]);

        return {
            empty: !usedRows.length,
            config: {
                type: 'bar',
                data: { labels: rows.map((r) => r[0]), datasets: [bar('Sewa', rows.map((r) => r[1]))] },
                options: options({ format: (v) => `${number(v)} sewa` }),
            },
            table: { head: ['Hari', 'Jumlah'], rows: rows.map(([d, c]) => [d, number(c)]) },
        };
    },
};

function fillTable(table, { head, rows }) {
    table.replaceChildren();
    const thead = table.createTHead().insertRow();
    head.forEach((h) => {
        const th = document.createElement('th');
        th.textContent = h;
        thead.appendChild(th);
    });
    const tbody = table.createTBody();
    rows.forEach((row) => {
        const tr = tbody.insertRow();
        row.forEach((cell) => (tr.insertCell().textContent = cell));
    });
}

export function initCustomerDashboard(root) {
    const source = root.querySelector('#dash-data');
    if (!source) return;

    const data = JSON.parse(source.textContent);
    const period = root.querySelector('[data-period]');

    // "semua" = semua tahun (null); string kosong di filter grafik = ikut periode halaman.
    const yearOf = (value) => (value && value !== 'semua' ? Number(value) : null);
    const slice = (year) => ({
        rentals: year ? data.rentals.filter((r) => r.tahun === year) : data.rentals,
        payments: year ? data.payments.filter((p) => p.tahun === year) : data.payments,
    });

    const instances = new Map();

    function renderChart(card) {
        const key = card.dataset.chart;
        const filter = card.querySelector('[data-chart-filter]').value;
        const year = yearOf(filter === '' ? period.value : filter);
        const result = CHARTS[key](slice(year), year);

        card.querySelector('.dash-canvas').hidden = result.empty;
        card.querySelector('.dash-empty').hidden = !result.empty;
        card.querySelector('.dash-table').hidden = result.empty;
        fillTable(card.querySelector('.dash-table table'), result.table);

        instances.get(key)?.destroy();
        if (!result.empty) {
            instances.set(key, new Chart(card.querySelector('canvas'), result.config));
        }
    }

    function renderKpis() {
        const { rentals, payments } = slice(yearOf(period.value));
        const set = (key, value, sub) => {
            root.querySelector(`[data-kpi="${key}"]`).textContent = value;
            root.querySelector(`[data-kpi-sub="${key}"]`).textContent = sub;
        };

        const done = rentals.filter((r) => r.status === 'Selesai');
        set('selesai', number(done.length), `dari ${number(rentals.length)} pesanan`);

        const paid = payments.reduce((t, p) => t + p.nominal, 0);
        const fines = payments.filter((p) => p.jenis === 'Denda').reduce((t, p) => t + p.nominal, 0);
        set('dibayar', rupiahShort(paid), fines > 0 ? `termasuk denda ${rupiah(fines)}` : 'tanpa denda');

        const days = done.reduce((t, r) => t + r.hari, 0);
        set('hari', `${number(days)} hari`, done.length ? `di ${number(done.length)} sewa selesai` : 'belum ada sewa selesai');

        const returned = rentals.filter((r) => r.telat !== null);
        const onTime = returned.filter((r) => !r.telat).length;
        set(
            'tepat',
            returned.length ? `${Math.round((onTime / returned.length) * 100)}%` : '–',
            returned.length ? `${onTime} dari ${returned.length} pengembalian` : 'belum ada pengembalian',
        );
    }

    const cards = [...root.querySelectorAll('[data-chart]')];

    cards.forEach((card) => {
        card.querySelector('[data-chart-filter]').addEventListener('change', () => renderChart(card));
    });

    period.addEventListener('change', () => {
        renderKpis();
        cards.filter((c) => c.querySelector('[data-chart-filter]').value === '').forEach(renderChart);
    });

    renderKpis();
    cards.forEach(renderChart);
}
