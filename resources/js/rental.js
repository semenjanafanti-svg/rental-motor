import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import 'flatpickr/dist/flatpickr.min.css';
import '../css/rental.css';

flatpickr.localize(Indonesian);

// API rental memakai waktu Asia/Jakarta. Offset eksplisit mencegah tanggal bergeser di browser zona lain.
const toDate = (value) => new Date(`${value.replace(' ', 'T')}+07:00`);
const rupiah = (n) => 'Rp' + new Intl.NumberFormat('id-ID').format(n);
const formatDateTime = (date) => date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' });

/* ---------------------------------------------------------------
 * Halaman detail motor: kalender ketersediaan
 * Menandai hari yang punya jadwal terisi + daftar rentang waktunya.
 * ------------------------------------------------------------- */
function initAvailabilityCalendar() {
    const input = document.getElementById('availability-calendar');
    if (!input) return;

    const list = document.getElementById('booked-list');
    let booked = [];

    const dayCoverage = (day) => {
        const dayStart = Date.UTC(day.getFullYear(), day.getMonth(), day.getDate()) - (7 * 60 * 60 * 1000);
        const dayEnd = dayStart + (24 * 60 * 60 * 1000);
        const intervals = booked
            .map((range) => [Math.max(range.start.getTime(), dayStart), Math.min(range.end.getTime(), dayEnd)])
            .filter(([start, end]) => start < end)
            .sort((first, second) => first[0] - second[0]);

        if (intervals.length === 0) return null;

        let coveredUntil = dayStart;
        for (const [start, end] of intervals) {
            if (start > coveredUntil) break;
            coveredUntil = Math.max(coveredUntil, end);
            if (coveredUntil >= dayEnd) return 'fully-booked';
        }

        return 'partially-booked';
    };

    const calendar = flatpickr(input, {
        inline: true,
        minDate: input.dataset.today,
        onDayCreate: (_selected, _str, _instance, dayElem) => {
            const coverage = dayCoverage(dayElem.dateObj);
            if (coverage) {
                dayElem.classList.add(coverage);
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
    const form = document.getElementById('schedule-form');
    if (!form) return;

    const timeInput = document.getElementById('start_time');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const loadingEl = document.getElementById('availability-loading');
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
    let quoteController = null;
    let quoteTimer = null;

    async function refreshQuote() {
        const startDate = startDateInput.value;
        const startTime = timeInput.value;
        const endDate = endDateInput.value;

        if (!startDate || !startTime || !endDate) {
            showState('placeholder');
            submitButton.disabled = true;
            return;
        }

        const currentRequest = ++quoteCounter;
        quoteController?.abort();
        quoteController = new AbortController();

        try {
            const url = new URL(quoteUrl, window.location.origin);
            url.searchParams.set('start_date', startDate);
            url.searchParams.set('start_time', startTime);
            url.searchParams.set('end_date', endDate);

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: quoteController.signal,
            });
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
            if (error.name === 'AbortError') return;
            showState('placeholder');
            submitButton.disabled = true;
        }
    }

    // Flatpickr dapat memicu beberapa perubahan beruntun. Menunggu sebentar
    // mencegah server menghitung quote untuk setiap perubahan perantara.
    function scheduleQuote() {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(refreshQuote, 150);
    }

    // Batas tanggal pengembalian mengikuti tanggal mulai dan jadwal orang lain
    function updateEndBounds() {
        const start = startPicker.selectedDates[0];

        if (!start) {
            endPicker.clear(false);
            return;
        }

        const freeDays = availableDates?.[ymd(start)];
        if (!freeDays) {
            endPicker.clear(false);
            return;
        }
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
    let datesController = null;

    async function loadDates() {
        const currentRequest = ++datesCounter;
        datesController?.abort();
        datesController = new AbortController();
        availableDates = null;
        startPicker.set('disable', [() => true]);
        startPicker.set('clickOpens', false);
        startDateInput.disabled = true;
        loadingEl.textContent = 'Memuat tanggal tersedia...';
        loadingEl.classList.remove('text-rust');
        loadingEl.hidden = false;
        submitButton.disabled = true;

        try {
            const url = new URL(datesUrl, window.location.origin);
            url.searchParams.set('start_time', timeInput.value);

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: datesController.signal,
            });
            const data = await response.json();

            if (currentRequest !== datesCounter) return;

            availableDates = data.ok ? data.dates : null;
        } catch (error) {
            if (currentRequest !== datesCounter) return;
            if (error.name === 'AbortError') return;
            availableDates = null;
        }

        if (availableDates === null) {
            loadingEl.textContent = 'Jadwal belum bisa dimuat. Muat ulang halaman untuk mencoba lagi.';
            loadingEl.classList.add('text-rust');
            startPicker.set('disable', [() => true]);
            return;
        }

        startPicker.set('disable', [(date) => !(ymd(date) in availableDates)]);
        startPicker.set('clickOpens', true);
        startDateInput.disabled = false;
        loadingEl.hidden = true;

        // Pilihan tanggal mulai yang tidak tersedia lagi untuk jam ini dibersihkan
        const selected = startPicker.selectedDates[0];
        if (selected && availableDates !== null && !(ymd(selected) in availableDates)) {
            startPicker.clear(false);
            endPicker.clear(false);
        }

        startPicker.redraw();
        updateEndBounds();
        scheduleQuote();
    }

    const today = new Date(`${form.dataset.today}T00:00:00`);

    const endPicker = flatpickr(endDateInput, {
        dateFormat: 'Y-m-d',
        minDate: today,
        maxDate: addDays(today, 90 + maxDays),
        onChange: scheduleQuote,
    });

    const startPicker = flatpickr(startDateInput, {
        dateFormat: 'Y-m-d',
        minDate: form.dataset.today,
        disable: [() => true],
        clickOpens: false,
        onChange: () => {
            updateEndBounds();
            scheduleQuote();
        },
    });

    const pad2 = (n) => String(n).padStart(2, '0');

    flatpickr(timeInput, {
        noCalendar: true,
        enableTime: true,
        time_24hr: true,
        dateFormat: 'H:i',
        minuteIncrement: 60,
        minTime: `${pad2(form.dataset.openHour)}:00`,
        maxTime: `${pad2(form.dataset.closeHour)}:00`,
        defaultDate: timeInput.value || '08:00',
        onChange: () => {
            quoteCounter++;
            quoteController?.abort();
            submitButton.disabled = true;
            startPicker.clear(false);
            endPicker.clear(false);
            loadDates();
        },
    });

    loadDates();
}

function initCountdown() {
    const el = document.getElementById('countdown');
    if (!el) return;

    const expires = Number(el.dataset.expires);
    const tick = () => {
        const s = Math.max(0, Math.ceil((expires - Date.now()) / 1000));
        el.textContent = `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`;
        el.classList.toggle('text-rust', s < 120);
        if (s === 0) { clearInterval(timer); window.location.reload(); }
    };
    const timer = setInterval(tick, 1000);
    tick();
}

function init() {
    initAvailabilityCalendar();
    initCheckout();
    initCountdown();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
