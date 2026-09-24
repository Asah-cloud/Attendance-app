// Live search for plain GET forms.
//
//   <form method="GET" data-live-search="#results"> ...one search input... </form>
//   <div id="results"> ...whatever the server renders for the current query... </div>
//
// Typing (after a short pause) or pressing Enter re-fetches the same page and swaps only the
// results element. A request still in flight is cancelled when a newer one starts, so slow
// answers can never overwrite fresh ones. Pagination links inside the results are handled the
// same way. If anything goes wrong it falls back to an ordinary page load, so the form keeps
// working without JavaScript and when the network hiccups.

const PAUSE_MS = 250;

function setupLiveSearch(form) {
    const selector = form.dataset.liveSearch;
    const findResults = () => document.querySelector(selector);
    let timer = null;
    let controller = null;
    let lastUrl = window.location.href;

    const buildUrl = () => {
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        url.search = '';
        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '') url.searchParams.set(key, value.trim());
        });

        return url;
    };

    const load = async (url, { resetScroll = false } = {}) => {
        const results = findResults();
        if (!results) {
            window.location.assign(url);
            return;
        }

        controller?.abort();
        const mine = new AbortController();
        controller = mine;
        results.setAttribute('aria-busy', 'true');
        results.classList.add('opacity-60', 'transition-opacity');

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                credentials: 'same-origin',
                signal: mine.signal,
            });
            if (!response.ok) throw new Error(`Search failed (${response.status})`);

            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = page.querySelector(selector);
            if (!fresh) throw new Error('Results missing from the response');

            results.replaceChildren(...fresh.childNodes);
            if (resetScroll) results.scrollTop = 0;
            history.replaceState(history.state, '', url);
            lastUrl = String(url);
        } catch (error) {
            if (error.name === 'AbortError') return;
            window.location.assign(url);
        } finally {
            if (controller === mine) {
                results.removeAttribute('aria-busy');
                results.classList.remove('opacity-60', 'transition-opacity');
            }
        }
    };

    const search = () => {
        const url = buildUrl();
        if (url.href !== lastUrl) load(url);
    };

    form.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(search, PAUSE_MS);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        search();
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[href]');
        const results = findResults();
        if (!link || !results || !results.contains(link)) return;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target) return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || !url.searchParams.has('page')) return;

        event.preventDefault();
        load(url, { resetScroll: true });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-live-search]').forEach(setupLiveSearch);
});
