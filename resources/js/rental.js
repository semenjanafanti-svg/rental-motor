import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/rental.css';

flatpickr.localize(Indonesian);

// "2026-09-20 08:00" -> Date (waktu lokal)
const toDate = (value) => new Date(value.replace(' ', 'T'));
const rupiah = (n) => 'Rp' + new Intl.NumberFormat('id-ID').format(n);
const formatDateTime = (date) => date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

/* ---------------------------------------------------------------
 * Halaman detail motor: kalender ketersediaan
 * Menandai hari yang punya jadwal terisi + daftar rentang waktunya.
 * ------------------------------------------------------------- */
function initAvailabilityCalendar() {
    const input = document.getElementById('availability-calendar');
    if (!input) return;

    const list = document.getElementById('booked-list');
    let booked = [];

    const overlapsDay = (day) => {
        const dayStart = new Date(day.getFullYear(), day.getMonth(), day.getDate());
        const dayEnd = new Date(day.getFullYear(), day.getMonth(), day.getDate() + 1);
        return booked.some((range) => range.start < dayEnd && range.end > dayStart);
    };

    const calendar = flatpickr(input, {
        inline: true,
        minDate: 'today',
        onDayCreate: (_selected, _str, _instance, dayElem) => {
            if (overlapsDay(dayElem.dateObj)) {
                dayElem.classList.add('has-booking');
            }
        },
    });

    const renderList = (message = null) => {
        list.innerHTML = '';

        if (message || booked.length === 0) {
            const li = document.createElement('li');
            li.textContent = message ?? 'Belum ada jadwal terisi dalam 90 hari ke depan.';
            list.appendChild(li);
            return;
        }

        booked.forEach((range) => {
            const li = document.createElement('li');
            li.textContent = `${formatDateTime(range.start)} \u2013 ${formatDateTime(range.end)}`;
            list.appendChild(li);
        });
    };

    fetch(input.dataset.url, { headers: { Accept: 'application/json' } })
        .then((response) => response.json())
        .then(({ ranges }) => {
            booked = ranges.map((range) => ({ start: toDate(range.start), end: toDate(range.end) }));
            calendar.redraw();
            renderList();
        })
        .catch(() => renderList('Gagal memuat jadwal.'));
}

/* ---------------------------------------------------------------
 * Halaman checkout: jam mulai, tanggal mulai, tanggal pengembalian.
 * - Tanggal mulai yang bisa dipilih dan jumlah hari maksimal datang dari server
 *   (endpoint dates), bergantung pada jam mulai yang dipilih.
 * - Jam pengembalian otomatis = jam mulai; ringkasan harga dari server (endpoint quote).
 * ------------------------------------------------------------- */
function initCheckout() {
    const form = document.getElementById('checkout-form');
    if (!form) return;

    const timeInput = document.getElementById('start_time');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const submitButton = form.querySelector('button[type="submit"]');

    const datesUrl = form.dataset.datesUrl;
    const quoteUrl = form.dataset.quoteUrl;
    const minDays = Number(form.dataset.minDays);
    const maxDays = Number(form.dataset.maxDays);

    const pad = (n) => String(n).padStart(2, '0');
    const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);

    // { 'YYYY-MM-DD': jumlah hari maksimal }. null = belum diketahui (izinkan semua, server yang memvalidasi)
    let availableDates = null;

    const placeholderEl = document.getElementById('quote-placeholder');
    const errorEl = document.getElementById('quote-error');
    const okEl = document.getElementById('quote-ok');

    const showState = (state) => {
        placeholderEl.classList.toggle('hidden', state !== 'placeholder');
        errorEl.classList.toggle('hidden', state !== 'error');
        okEl.classList.toggle('hidden', state !== 'ok');
    };

    let quoteCounter = 0;

    async function refreshQuote() {
        const startDate = startDateInput.value;
        const startTime = timeInput.value;
        const endDate = endDateInput.value;

        if (!startDate || !startTime || !endDate) {
            showState('placeholder');
            return;
        }

        const currentRequest = ++quoteCounter;

        try {
            const url = new URL(quoteUrl, window.location.origin);
            url.searchParams.set('start_date', startDate);
            url.searchParams.set('start_time', startTime);
            url.searchParams.set('end_date', endDate);

            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();

            if (currentRequest !== quoteCounter) return; // ada permintaan yang lebih baru

            if (data.ok) {
                document.getElementById('q-start').textContent = formatDateTime(toDate(data.start_at));
                document.getElementById('q-end').textContent = formatDateTime(toDate(data.end_at));
                document.getElementById('q-days').textContent = `${data.days} hari (${data.total_hours} jam)`;
                document.getElementById('q-total').textContent = rupiah(data.total_price);
                document.getElementById('q-dp').textContent = rupiah(data.dp_amount);
                document.getElementById('q-balance').textContent = rupiah(data.balance_amount);
                showState('ok');
                submitButton.disabled = false;
            } else {
                errorEl.textContent = data.message;
                showState('error');
                submitButton.disabled = true;
            }
        } catch (error) {
            if (currentRequest !== quoteCounter) return;
            // Jika ringkasan gagal dimuat, biarkan server yang memvalidasi saat submit
            showState('placeholder');
            submitButton.disabled = false;
        }
    }

    // Batas tanggal pengembalian mengikuti tanggal mulai dan jadwal orang lain
    function updateEndBounds() {
        const start = startPicker.selectedDates[0];

        if (!start) {
            endPicker.clear(false);
            return;
        }

        const freeDays = availableDates && availableDates[ymd(start)] ? availableDates[ymd(start)] : maxDays;
        const earliest = addDays(start, minDays);
        const latest = addDays(start, Math.min(maxDays, freeDays));

        endPicker.set('minDate', earliest);
        endPicker.set('maxDate', latest);

        const end = endPicker.selectedDates[0];
        if (!end || end < earliest || end > latest) {
            endPicker.setDate(earliest, false); // usulan awal: sewa minimum
        }
    }

    let datesCounter = 0;

    async function loadDates() {
        const currentRequest = ++datesCounter;

        try {
            const url = new URL(datesUrl, window.location.origin);
            url.searchParams.set('start_time', timeInput.value);

            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json();

            if (currentRequest !== datesCounter) return;

            availableDates = data.ok ? data.dates : null;
        } catch (error) {
            if (currentRequest !== datesCounter) return;
            availableDates = null;
        }

        // Pilihan tanggal mulai yang tidak tersedia lagi untuk jam ini dibersihkan
        const selected = startPicker.selectedDates[0];
        if (selected && availableDates !== null && !(ymd(selected) in availableDates)) {
            startPicker.clear(false);
            endPicker.clear(false);
        }

        startPicker.redraw();
        updateEndBounds();
        refreshQuote();
    }

    const today = new Date();

    const endPicker = flatpickr(endDateInput, {
        dateFormat: 'Y-m-d',
        minDate: today,
        maxDate: addDays(today, 90 + maxDays),
        onChange: refreshQuote,
    });

    const startPicker = flatpickr(startDateInput, {
        dateFormat: 'Y-m-d',
        minDate: 'today',
        enable: [(date) => availableDates === null || ymd(date) in availableDates],
        onChange: () => {
            updateEndBounds();
            refreshQuote();
        },
    });

    flatpickr(timeInput, {
        noCalendar: true,
        enableTime: true,
        time_24hr: true,
        dateFormat: 'H:i',
        minuteIncrement: 30,
        defaultDate: timeInput.value || '08:00',
        onChange: loadDates,
    });

    loadDates();
}

/* ---------------------------------------------------------------
 * Halaman pesanan: tombol Bayar DP (Midtrans Snap)
 * Snap token dibuat server; Server Key tidak pernah sampai ke browser.
 * ------------------------------------------------------------- */
function initPayment() {
    const button = document.getElementById('pay-button');
    if (!button) return;

    const errorEl = document.getElementById('pay-error');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    const showError = (message) => {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
        button.disabled = false;
    };

    const reload = () => window.location.reload();

    button.addEventListener('click', async () => {
        errorEl.classList.add('hidden');
        button.disabled = true;

        if (!window.snap) {
            showError('Modul pembayaran Midtrans belum termuat. Muat ulang halaman.');
            return;
        }

        try {
            const response = await fetch(button.dataset.payUrl, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const data = await response.json();

            if (!response.ok) {
                showError(data.message || 'Gagal memulai pembayaran.');
                return;
            }

            window.snap.pay(data.token, {
                onSuccess: reload,
                onPending: reload,
                onError: () => showError('Pembayaran gagal diproses. Silakan coba lagi.'),
                onClose: () => {
                    button.disabled = false;
                },
            });
        } catch (error) {
            showError('Tidak dapat menghubungi server. Coba lagi.');
        }
    });
}

/* ---------------------------------------------------------------
 * Halaman pesanan: polling status sambil menunggu pembayaran.
 * Server menanyakan status ke Midtrans, jadi jalan juga di localhost tanpa webhook.
 * ------------------------------------------------------------- */
function initStatusPolling() {
    const box = document.getElementById('status-poll');
    if (!box) return;

    let attempts = 0;

    const timer = setInterval(async () => {
        attempts += 1;

        if (attempts > 360) { // berhenti setelah sekitar 30 menit
            clearInterval(timer);
            return;
        }

        if (document.hidden) return;

        try {
            const response = await fetch(box.dataset.url, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;

            const data = await response.json();

            if (data.status !== box.dataset.status) {
                clearInterval(timer);
                window.location.reload();
            }
        } catch (error) {
            // abaikan, coba lagi pada putaran berikutnya
        }
    }, 5000);
}

function init() {
    initAvailabilityCalendar();
    initCheckout();
    initPayment();
    initStatusPolling();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
