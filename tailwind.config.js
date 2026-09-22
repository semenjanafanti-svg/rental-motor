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
                sans: ['"Work Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Oswald', '"Arial Narrow"', ...defaultTheme.fontFamily.sans],
            },

            // Semua warna membaca CSS variable di resources/css/app.css,
            // jadi mode terang/gelap berganti otomatis tanpa awalan dark:
            colors: {
                ink: 'var(--ink)',
                paper: 'var(--paper)',
                surface: 'var(--surface)',
                line: 'var(--line)',
                muted: 'var(--muted)',
                sun: 'var(--amber)',
                'sun-ink': 'var(--amber-ink)',
                'sun-soft': 'var(--sun-soft)',
                'sun-text': 'var(--sun-text)',
                moss: 'var(--teal)',
                'moss-bg': 'var(--teal-bg)',
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
