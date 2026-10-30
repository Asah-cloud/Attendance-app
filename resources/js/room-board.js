// Drag-and-drop room board (resources/views/accommodation/board.blade.php): each room and the
// "Needs a room" list is a Sortable list. A person can be dropped anywhere on a room card, or
// tapped and then a room tapped. Moves save through RoomBoardController@move, and anything the
// server refuses is put back where it came from.
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
    // A move can bring someone into or out of a searched room.
    if (board.hasAttribute('data-searching')) return runSearch();
    applyFilter();
}

function putBack(evt) {
    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
    refreshCounts();
}

async function save(evt) {
    const roomId = evt.to.dataset.roomId || null;
    const response = await fetch(board.dataset.moveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ registration_id: Number(evt.item.dataset.registrationId), room_id: roomId ? Number(roomId) : null }),
    }).catch(() => null);
    const data = response ? await response.json().catch(() => ({})) : {};

    if (!response?.ok || !data.ok) {
        notify(data.message || 'That move could not be saved. Please try again.', 'error');
        return putBack(evt);
    }
    if (data.room_gender) {
        // An empty room kept for the other gender has switched to this person's.
        const badge = board.querySelector(`[data-room-gender="${roomId}"]`);
        if (badge) { badge.textContent = data.room_gender; badge.classList.remove('hidden'); }
        return notify(`${evt.item.dataset.name} → ${evt.to.dataset.roomName}. It is now for ${data.room_gender.toLowerCase()} guests.`);
    }
    notify(roomId ? `${evt.item.dataset.name} → ${evt.to.dataset.roomName}` : `${evt.item.dataset.name} now needs a room`);
}

/** The drop area (a room card, or the "Needs a room" panel) under a screen point. */
function zoneAt(x, y) {
    return document.elementFromPoint(x, y)?.closest('[data-drop-zone]') ?? null;
}

const listOf = (zone) => zone?.querySelector('[data-room-list]') ?? null;
const hasSpace = (list) => list.dataset.roomId === '' || list.querySelectorAll('[data-registration-id]').length < Number(list.dataset.capacity);

function markZone(zone) {
    board.querySelectorAll('.is-drop-target, .is-drop-blocked').forEach((el) => el.classList.remove('is-drop-target', 'is-drop-blocked'));
    const list = listOf(zone);
    if (list) zone.classList.add(hasSpace(list) ? 'is-drop-target' : 'is-drop-blocked');
}

/** Move a person into a list (after a drop or a tap) and save it; the server can still refuse. */
function place(item, from, oldIndex, to) {
    if (to === from) return;
    if (!hasSpace(to) && item.parentElement !== to) {
        notify(`${to.dataset.roomName} is full.`, 'error');
        from.insertBefore(item, from.children[oldIndex] || null);
        refreshCounts();
        return;
    }
    if (item.parentElement !== to) to.appendChild(item);
    refreshCounts();
    save({ item, from, to, oldIndex });
}

// --- Tap to place: tap a person, then tap a room (or "Needs a room"). ---
let selected = null;

function select(chip) {
    if (selected) selected.classList.remove('is-selected');
    selected = chip === selected ? null : chip;
    selected?.classList.add('is-selected');
    board.classList.toggle('is-placing', Boolean(selected));
    // Hold live redraws (live-updates.js) while someone is being placed.
    board.toggleAttribute('data-live-busy', Boolean(selected));
    const banner = board.querySelector('[data-placing-banner]');
    if (banner) {
        banner.style.display = selected ? '' : 'none';
        if (selected) banner.querySelector('[data-placing-name]').textContent = selected.dataset.name;
    }
}

/** Words compared loosely: case ignored, and a phone number's leading zeros dropped. */
const searchWords = (text) => text.toLowerCase().split(/\s+/).filter(Boolean).map((word) => (/^\d+$/.test(word) ? word.replace(/^0+/, '') : word)).filter(Boolean);
const roomKey = (text) => text.toLowerCase().replace(/[^a-z0-9]/g, '');

/**
 * Filter the board to a search, like the live search: only the people who match stay in
 * "Needs a room", and only rooms holding a match (or named like the search) stay on the board,
 * opened so names show, across every building and whatever the room filter. When the only
 * matches are waiting, the rooms stay so they can be placed. Clearing restores the board.
 */
function runSearch() {
    const input = board.querySelector('[data-board-search]');
    const words = searchWords(input?.value ?? '');
    const searching = words.length > 0;
    board.toggleAttribute('data-searching', searching);
    let people = 0;
    let waiting = 0;
    let rooms = 0;

    board.querySelectorAll('[data-registration-id]').forEach((chip) => {
        const hit = searching && words.every((word) => chip.dataset.search.includes(word));
        chip.classList.toggle('is-hit', hit);
        if (hit) {
            people += 1;
            if (chip.closest('[data-room-list]')?.dataset.roomId === '') waiting += 1;
        }
    });
    board.querySelectorAll('[data-room-card]').forEach((card) => {
        const name = roomKey(card.querySelector('[data-room-list]').dataset.roomName ?? '');
        const match = searching && (Boolean(card.querySelector('.is-hit')) || (roomKey(input.value) !== '' && name.includes(roomKey(input.value))));
        card.classList.toggle('is-match', match);
        rooms += match ? 1 : 0;
        // Open folded rooms that match, so the person's name shows; fold them again afterwards.
        if (match && !card.classList.contains('is-open')) { setOpen(card, true); card.dataset.searchOpened = '1'; }
        if (!match && card.dataset.searchOpened) { setOpen(card, false); delete card.dataset.searchOpened; }
    });
    board.querySelectorAll('[data-block-section]').forEach((section) => section.classList.toggle('has-match', Boolean(section.querySelector('[data-room-card].is-match'))));
    board.toggleAttribute('data-searching-rooms', rooms > 0);

    const summary = board.querySelector('[data-search-summary]');
    if (summary) {
        summary.hidden = !searching;
        const placedPeople = people - waiting;
        summary.textContent = !people && !rooms ? 'No one matches.'
            : [people ? `${people} ${people === 1 ? 'person' : 'people'} found` : `${rooms} ${rooms === 1 ? 'room' : 'rooms'} found`,
                waiting ? `${waiting} waiting for a room` : '',
                placedPeople ? `${placedPeople} in a room` : ''].filter(Boolean).join(' · ');
    }
    board.querySelectorAll('[data-floor]').forEach((floor) => floor.classList.remove('is-empty'));
    applyFilter();
}

if (board) {
    let lastPoint = null;
    let dragging = false;
    let justDragged = false;
    const track = (x, y) => {
        lastPoint = { x, y };
        if (dragging) markZone(zoneAt(x, y));
    };
    document.addEventListener('pointermove', (e) => track(e.clientX, e.clientY), { passive: true });
    document.addEventListener('touchmove', (e) => e.touches[0] && track(e.touches[0].clientX, e.touches[0].clientY), { passive: true });

    // Lists get drag and drop once; a live redraw can add rooms, which get it then.
    const sortable = new WeakSet();
    const makeSortable = (list) => sortable.has(list) || (sortable.add(list), Sortable.create(list, {
        group: {
            name: 'rooms',
            // A full room accepts nobody new; "Needs a room" always does.
            put: (to) => hasSpace(to.el),
        },
        animation: 150,
        // Pointer-based dragging behaves the same with a mouse, a finger or a pen.
        forceFallback: true,
        fallbackOnBody: true,
        // A small wobble is still a tap, not a drag.
        fallbackTolerance: 4,
        // On touch screens, hold briefly to pick someone up, so swiping still scrolls the page.
        delay: 180,
        delayOnTouchOnly: true,
        touchStartThreshold: 6,
        // Scroll sooner and faster when dragging near the top or bottom of the screen.
        scrollSensitivity: 120,
        scrollSpeed: 22,
        bubbleScroll: true,
        emptyInsertThreshold: 30,
        filter: '.is-locked',
        preventOnFilter: false,
        ghostClass: 'opacity-40',
        onStart: () => {
            dragging = true;
            select(null);
            board.classList.add('is-dragging');
            board.toggleAttribute('data-live-busy', true);
        },
        // Saving happens on drop, below, so a drop anywhere on a room card counts.
        onEnd: (evt) => {
            dragging = false;
            justDragged = true;
            setTimeout(() => { justDragged = false; }, 0);
            board.classList.remove('is-dragging');
            board.toggleAttribute('data-live-busy', false);
            markZone(null);
            const pointed = lastPoint ? listOf(zoneAt(lastPoint.x, lastPoint.y)) : null;
            const target = pointed && pointed !== evt.to && hasSpace(pointed) ? pointed : evt.to;
            if (target === evt.from) {
                refreshCounts();
                return;
            }
            place(evt.item, evt.from, evt.oldIndex, target);
        },
    }));
    board.querySelectorAll('[data-room-list]').forEach(makeSortable);

    board.addEventListener('click', (event) => {
        if (justDragged || event.target.closest('button, a, input')) return;
        // Tapping a person picks them (tap again to let go); tapping a room then places them.
        const chip = event.target.closest('[data-registration-id]');
        if (chip) {
            if (!chip.classList.contains('is-locked')) select(chip);
            return;
        }
        const zone = event.target.closest('[data-drop-zone]');
        if (selected && zone) {
            const from = selected.parentElement;
            const item = selected;
            select(null);
            place(item, from, [...from.children].indexOf(item), listOf(zone));
        }
    });
    board.querySelector('[data-placing-cancel]')?.addEventListener('click', () => select(null));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') select(null); });

    board.querySelectorAll('[data-show-button]').forEach((button) => button.addEventListener('click', () => setShow(button.dataset.showButton)));
    board.querySelector('[data-density-toggle]')?.addEventListener('click', () => setDensity(board.dataset.density === 'compact' ? 'names' : 'compact'));
    board.addEventListener('click', (event) => {
        const button = event.target.closest('[data-room-toggle]');
        if (!button) return;
        const card = button.closest('[data-room-card]');
        setOpen(card, !card.classList.contains('is-open'));
    });

    // Someone changed people or rooms elsewhere: the board was redrawn (live-updates.js). Put
    // back drag and drop, the view, rooms that were opened, counts and the search.
    let opened = [];
    board.addEventListener('live:before', () => {
        opened = [...board.querySelectorAll('[data-room-card].is-open:not([data-search-opened]) [data-room-list]')].map((list) => list.dataset.roomId);
    });
    board.addEventListener('live:updated', () => {
        board.querySelectorAll('[data-room-list]').forEach(makeSortable);
        setDensity(board.dataset.density);
        opened.forEach((id) => {
            const card = board.querySelector(`[data-room-list][data-room-id="${id}"]`)?.closest('[data-room-card]');
            if (card) setOpen(card, true);
        });
        refreshCounts();
    });
    // The building pills (Alpine) change which sections show; re-check empty floors afterwards.
    window.addEventListener('room-board:filter', applyFilter);

    setDensity(recall('density', 'names'));
    setShow(recall('show', 'all'));
    refreshCounts();

    const search = board.querySelector('[data-board-search]');
    search?.addEventListener('input', runSearch);
    search?.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && search.value) { search.value = ''; runSearch(); }
    });
}
