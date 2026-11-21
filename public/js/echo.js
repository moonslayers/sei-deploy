import Echo from '../vendor/laravel-echo-2.6.3.js';

const cfg = (window.__terminalConfig && window.__terminalConfig.echo) || {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: cfg.key,
    wsHost: cfg.wsHost,
    wsPort: cfg.wsPort ?? 80,
    wssPort: cfg.wssPort ?? 443,
    forceTLS: cfg.forceTLS ?? false,
    enabledTransports: ['ws', 'wss'],
});