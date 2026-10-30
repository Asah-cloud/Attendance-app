// Keeps open pages current without reloading. The page names a live marker
// (<meta name="live-version">, see components/live-version.blade.php) that the server moves
// whenever the event's people, registrations or rooms change. Every few seconds, while the tab
// is in view, the page asks for the marker; when it has moved, the page fetches itself again
// and redraws each [data-live-root] in place (Alpine morph), and Livewire parts refresh.
//
// What the user is doing is never thrown away: a field being typed in, a form with unsaved
// changes, an open window (modal) or a drag in progress (anything marked [data-live-busy])
// holds the redraw until they finish. Open <details> stay open. Pages that set state with
// script listen for "live:before" and "live:updated" on the root to put it back.

const CHECK_MS = 5000;
const RETRY_MS = 1500;

const fields = 'input, textarea, select';
const typing = (el) => el?.matches?.('input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]), textarea, select');

/** Whether a field holds something the user changed and hasn't saved. */
function changed(field) {
    if (field.type === 'hidden' || field.type === 'button' || field.type === 'submit') return false;
    if (field.type === 'checkbox' || field.type === 'radio') return field.checked !== field.defaultChecked;
    if (field.type === 'file') return field.files?.length > 0;
    if (field.tagName === 'SELECT') return [...field.options].some((option) => option.selected !== option.defaultSelected);
    return field.value !== field.defaultValue;
}

const unsaved = (form) => [...form.querySelectorAll(fields)].some(changed);

/** A modal or drawer open over the page. */
function overlayOpen(root) {
    return [...root.querySelectorAll('.fixed')].some((el) => {
        const style = getComputedStyle(el);
        return style.display !== 'none' && style.visibility !== 'hidden' && el.getClientRects().length > 0 && el.offsetWidth > window.innerWidth / 2;
    });
}

function busy(roots) {
    if (document.querySelector('[data-live-busy]')) return true;
    return roots.some((root) => (typing(document.activeElement) && root.contains(document.activeElement)) || overlayOpen(root));
}

function redraw(root, fresh) {
    root.dispatchEvent(new CustomEvent('live:before', { bubbles: true }));
    window.Alpine.morph(root, fresh, {
        key: (el) => el.dataset?.registrationId ?? (el.id || undefined),
        updating(el, toEl, childrenOnly, skip) {
            if (el.nodeType !== Node.ELEMENT_NODE) return;
            // Livewire parts refresh themselves; scripts and opted-out parts are left alone.
            if (el.hasAttribute('wire:id') || el.hasAttribute('data-live-ignore') || el.tagName === 'SCRIPT') return skip();
            if (el.tagName === 'FORM' && unsaved(el)) return skip();
            if (el.matches(fields) && (el === document.activeElement || changed(el))) return skip();
            // Whatever the user opened stays open: <details>, and rows or panels shown by script.
            if (el.tagName === 'DETAILS' && el.open) toEl.setAttribute('open', '');
            if (!el.classList.contains('hidden') && toEl.classList?.contains('hidden')) toEl.classList.remove('hidden');
            if (!el.hidden && toEl.hidden) toEl.hidden = false;
        },
    });
    root.dispatchEvent(new CustomEvent('live:updated', { bubbles: true }));
}

async function refreshPage() {
    window.Livewire?.all?.().forEach((component) => component.$wire?.$refresh?.());

    const roots = [...document.querySelectorAll('[data-live-root]')];
    if (!roots.length) return true;
    const response = await fetch(window.location.href, {
        headers: { Accept: 'text/html', 'X-Live-Update': '1' },
        credentials: 'same-origin',
    }).catch(() => null);
    // Signed out, or the page has gone: leave it as it is.
    if (!response?.ok || response.redirected) return false;
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const fresh = [...page.querySelectorAll('[data-live-root]')];
    if (fresh.length !== roots.length || busy(roots)) return false;
    roots.forEach((root, index) => redraw(root, fresh[index].outerHTML));

    return true;
}

function start(meta) {
    let seen = meta.dataset.version;
    let waiting = null;
    let checking = false;
    let retry = null;

    const apply = async () => {
        clearTimeout(retry);
        if (!waiting) return;
        const roots = [...document.querySelectorAll('[data-live-root]')];
        if (busy(roots)) {
            retry = setTimeout(apply, RETRY_MS);
            return;
        }
        const target = waiting;
        if (await refreshPage()) {
            seen = target;
            if (waiting === target) waiting = null;
        } else {
            retry = setTimeout(apply, RETRY_MS * 2);
        }
    };

    const check = async () => {
        if (document.hidden || checking) return;
        checking = true;
        try {
            const response = await fetch(meta.content, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const version = response.ok ? (await response.json()).version : null;
            if (version && version !== seen) waiting = version;
        } catch {
            // Offline for a moment: try again next time.
        } finally {
            checking = false;
        }
        if (waiting) apply();
    };

    setInterval(check, CHECK_MS);
    document.addEventListener('visibilitychange', check);
    window.addEventListener('focus', check);
}

const meta = document.querySelector('meta[name="live-version"]');
if (meta) start(meta);
