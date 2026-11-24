import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js', // rental.js menambah/menghapus kelas (hidden, text-rust)
    ],

    theme: {
        extend: {
            fontFamily: {
                // Font sistem (SF Pro di Apple, Segoe UI di Windows): sama dengan panel admin.
                sans: ['system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', ...defaultTheme.fontFamily.sans],
                display: ['system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', ...defaultTheme.fontFamily.sans],
            },

            // Semua warna membaca CSS variable di resources/css/app.css (palet putih kuning).
            colors: {
                // 60% dominan
                paper: 'var(--paper)',
                surface: 'var(--surface)',
                line: 'var(--line)',
                muted: 'var(--muted)',
                // 30% support
                ink: 'var(--ink)',
                night: 'var(--night)',
                'night-2': 'var(--night-2)',
                'night-line': 'var(--night-line)',
                'night-muted': 'var(--night-muted)',
                // 10% aksen
                volt: 'var(--volt)',
                'volt-ink': 'var(--volt-ink)',
                'volt-soft': 'var(--volt-soft)',
                // status
                ok: 'var(--ok)',
                'ok-bg': 'var(--ok-bg)',
                warn: 'var(--warn)',
                'warn-bg': 'var(--warn-bg)',
                rust: 'var(--rust)',
                'rust-bg': 'var(--rust-bg)',
            },

            keyframes: {
                'fade-up': {
                    from: { opacity: '0', transform: 'translateY(8px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'fade-up': 'fade-up .3s ease both',
            },
        },
    },

    plugins: [forms],
};
