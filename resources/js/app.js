import Alpine from 'alpinejs';
import './rental';

window.Alpine = Alpine;

Alpine.start();

// Chart.js cukup dimuat di halaman dashboard customer saja.
const dashboards = document.querySelectorAll('[data-customer-dashboard]');
if (dashboards.length) {
    import('./customer-dashboard').then(({ initCustomerDashboard }) => dashboards.forEach(initCustomerDashboard));
}
