/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './bootstrap.js';

// Désactive Turbo Drive sur les routes front office pour éviter les conflits JavaScript
document.addEventListener('turbo:before-visit', function(event) {
    const url = event.detail.url;
    const frontOfficeRoutes = ['/events', '/my-tickets', '/feedback', '/landing', '/login', '/register', '/forgot-password', '/reset-password', '/demo-video', '/quiz'];
    const isFrontOfficeRoute = frontOfficeRoutes.some(route => url.includes(route));

    if (isFrontOfficeRoute) {
        event.preventDefault();
        window.location.href = url; // Force un vrai rechargement natif
    }
});

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
