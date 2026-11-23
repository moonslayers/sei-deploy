import { Terminal } from '../vendor/xterm-6.0.0.mjs';
import { FitAddon } from '../vendor/addon-fit-0.11.0.mjs';

const cfg = window.__terminalConfig || {};
const container = document.getElementById('terminal');
if (!container) {
    throw new Error('terminal container not found');
}

const term = new Terminal({ cursorBlink: true });
const fit = new FitAddon();
term.loadAddon(fit);
term.open(container);
fit.fit();
if (typeof ResizeObserver !== 'undefined') {
    const ro = new ResizeObserver(() => { try { fit.fit(); } catch (e) {} });
    ro.observe(container);
}

let sessionId = null;
let pollIntervalId = null;
let pollFailures = 0;
const POLL_FAILURE_LIMIT = 10;

async function start() {
    const res = await fetch(cfg.startUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': cfg.csrfToken,
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
    });
    if (!res.ok) {
        let msg = `HTTP ${res.status}`;
        try {
            const err = await res.json();
            if (err && err.error) msg = err.error;
        } catch (e) {}
        term.write(`\r\n[error] ${msg}\r\n`);
        return;
    }
    const data = await res.json();
    if (!data || !data.session) {
        term.write('\r\n[error] no session returned\r\n');
        return;
    }
    sessionId = data.session;

    let clientOffset = 0;
    pollIntervalId = setInterval(async () => {
        if (!sessionId) return;
        try {
            const r = await fetch(cfg.outputUrl.replace('__UUID__', sessionId) + `?offset=${clientOffset}`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            const data = await r.json();
            if (data && typeof data.output === 'string' && data.output !== '') {
                term.write(data.output);
            }
            if (data && typeof data.offset === 'number') {
                clientOffset = data.offset;
            }
            pollFailures = 0;
        } catch (e) {
            console.error('terminal poll failed', e);
            pollFailures += 1;
            if (pollFailures >= POLL_FAILURE_LIMIT) {
                clearInterval(pollIntervalId);
                pollIntervalId = null;
                console.error('terminal polling stopped after repeated failures');
            }
        }
    }, 500);
}

term.onData((data) => {
    if (!sessionId) return;
    fetch(cfg.inputUrl.replace('__UUID__', sessionId), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': cfg.csrfToken,
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ input: data }),
    }).then((res) => {
        if (!res.ok) {
            console.error('terminal input failed', res.status);
        }
    }).catch((e) => console.error('terminal input error', e));
});

term.onResize(({ cols, rows }) => {
    if (!sessionId) return;
    fetch(cfg.resizeUrl.replace('__UUID__', sessionId), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': cfg.csrfToken,
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ cols, rows }),
    }).then((res) => {
        if (!res.ok) {
            console.error('terminal resize failed', res.status);
        }
    }).catch(() => {});
});

function end() {
    if (pollIntervalId !== null) {
        clearInterval(pollIntervalId);
        pollIntervalId = null;
    }
    if (!sessionId) return;
    fetch(cfg.endUrl.replace('__UUID__', sessionId), {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': cfg.csrfToken,
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        keepalive: true,
    }).catch(() => {});
}

window.addEventListener('beforeunload', end);
window.addEventListener('pagehide', end);

start();