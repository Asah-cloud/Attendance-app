// Drag-and-drop room board (resources/views/accommodation/board.blade.php): each room and the
// "Needs a room" list is a Sortable list; dropping a person saves the move through
// RoomBoardController@move, and anything the server refuses is put back where it came from.
import Sortable from 'sortablejs';

const board = document.querySelector('[data-room-board]');
const csrf = document.querySelector('meta[name=csrf-token]').content;
const notify = (message, type = 'success') => window.dispatchEvent(new CustomEvent('notify', { detail: { message, type } }));

// Per-viewer display choices; storage can be unavailable (private windows), so never rely on it.
const remember = (key, value) => { try { localStorage.setItem(`room-board:${key}`, value); } catch { /* not saved */ } };
const recall = (key, fallback) => { try { return localStorage.getItem(`room-board:${key}`) || fallback; } catch { return fallback; } };

/** Hide floors whose rooms are all filtered out, and keep the room filter's counts current. */
function applyFilter() {
    board.querySelectorAll('[data-show-button]').forEach((button) => button.classList.toggle('is-active', button.dataset.showButton === board.dataset.show));
    board.querySelectorAll('[data-floor]').forEach((floor) => {
        // A card's own display is "none" only when the room filter hides it.
        const visible = [...floor.querySelectorAll('[data-room-card]')].some((card) => getComputedStyle(card).display !== 'none');
        floor.classList.toggle('is-empty', !visible);
    });
}

function setShow(value) {
    board.dataset.show = value;
    remember('show', value);
    board.querySelectorAll('[data-floor]').forEach((floor) => floor.classList.remove('is-empty'));
    applyFilter();
}

function setDensity(value) {
    board.dataset.density = value;
    remember('density', value);
    const toggle = board.querySelector('[data-density-toggle]');
    if (toggle) toggle.textContent = value === 'compact' ? 'Show names' : 'Compact view';
    board.querySelectorAll('[data-room-card]').forEach((card) => {
        card.classList.toggle('is-compact', value === 'compact');
        setOpen(card, false);
    });
}

/** A folded room (full, or any room in compact view) shows initials; opening it shows names. */
function setOpen(card, open) {
    card.classList.toggle('is-open', open);
    const button = card.querySelector('[data-room-toggle]');
    if (button) {
        button.textContent = open ? 'Fold' : 'Show names';
        button.setAttribute('aria-expanded', String(open));
    }
}

function refreshCounts() {
    let placed = 0;
    let beds = 0;
    let full = 0;
    let rooms = 0;
    board.querySelectorAll('[data-room-list]').forEach((list) => {
        const used = list.querySelectorAll('[data-registration-id]').length;
        const counter = board.querySelector(`[data-count-for="${list.dataset.roomId}"]`);
        if (list.dataset.roomId === '') {
            if (counter) counter.textContent = used;
            return;
        }
        const capacity = Number(list.dataset.capacity);
        placed += used;
        beds += capacity;
        if (counter) counter.textContent = `${used} / ${capacity} beds`;
        const bar = board.querySelector(`[data-bar-for="${list.dataset.roomId}"]`);
        if (bar) bar.style.width = `${capacity ? Math.min(100, Math.round((used / capacity) * 100)) : 0}%`;
        const card = list.closest('[data-room-card]');
        const nowFull = used >= capacity;
        // Nothing to fold or show in an empty room.
        const toggle = card.querySelector('[data-room-toggle]');
        if (toggle) toggle.style.display = used ? '' : 'none';
        // A room that has just filled up folds itself, so the board stays short.
        if (nowFull && !card.classList.contains('is-full')) setOpen(card, false);
        card.classList.toggle('is-full', nowFull);
        rooms += 1;
        full += nowFull ? 1 : 0;
    });
    const counts = { all: rooms, space: rooms - full, full };
    board.querySelectorAll('[data-show-count]').forEach((el) => { el.textContent = `(${counts[el.dataset.showCount]})`; });
    const placedTotal = board.querySelector('[data-total-placed]');
    const freeTotal = board.querySelector('[data-total-free]');
    if (placedTotal) placedTotal.textContent = placed;
    if (freeTotal) freeTotal.textContent = Math.max(0, beds - placed);
    applyFilter();
}

function putBack(evt) {
    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
    refreshCounts();
}

async function save(evt, confirmed = false) {
    const roomId = evt.to.dataset.roomId || null;
    const response = await fetch(board.dataset.moveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ registration_id: Number(evt.item.dataset.registrationId), room_id: roomId ? Number(roomId) : null, confirmed }),
    }).catch(() => null);
    const data = response ? await response.json().catch(() => ({})) : {};

    if (response?.status === 409 && data.confirm) {
        return window.confirm(data.confirm) ? save(evt, true) : putBack(evt);
    }
    if (!response?.ok || !data.ok) {
        notify(data.message || 'That move could not be saved. Please try again.', 'error');
        return putBack(evt);
    }
    notify(roomId ? `${evt.item.dataset.name} → ${evt.to.dataset.roomName}` : `${evt.item.dataset.name} now needs a room`);
}

if (board) {
    board.querySelectorAll('[data-room-list]').forEach((list) => Sortable.create(list, {
        group: {
            name: 'rooms',
            // A full room accepts nobody new; "Needs a room" always does.
            put: (to) => to.el.dataset.roomId === '' || to.el.querySelectorAll('[data-registration-id]').length < Number(to.el.dataset.capacity),
        },
        animation: 150,
        // Pointer-based dragging behaves the same with a mouse, a finger or a pen.
        forceFallback: true,
        fallbackOnBody: true,
        filter: '.is-locked',
        preventOnFilter: false,
        ghostClass: 'opacity-40',
        onAdd: (evt) => { refreshCounts(); save(evt); },
    }));

    board.querySelectorAll('[data-show-button]').forEach((button) => button.addEventListener('click', () => setShow(button.dataset.showButton)));
    board.querySelector('[data-density-toggle]')?.addEventListener('click', () => setDensity(board.dataset.density === 'compact' ? 'names' : 'compact'));
    board.querySelectorAll('[data-room-toggle]').forEach((button) => button.addEventListener('click', () => {
        const card = button.closest('[data-room-card]');
        setOpen(card, !card.classList.contains('is-open'));
    }));
    // The building pills (Alpine) change which sections show; re-check empty floors afterwards.
    window.addEventListener('room-board:filter', applyFilter);

    setDensity(recall('density', 'names'));
    setShow(recall('show', 'all'));
    refreshCounts();

    board.querySelector('[data-board-search]')?.addEventListener('input', (event) => {
        const term = event.target.value.trim().toLowerCase();
        board.querySelectorAll('[data-registration-id]').forEach((chip) => {
            const hit = term && chip.dataset.search.includes(term);
            chip.classList.toggle('ring-2', hit);
            chip.classList.toggle('ring-amber-400', hit);
            chip.classList.toggle('opacity-30', Boolean(term) && !hit);
        });
    });
}
