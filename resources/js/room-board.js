// Drag-and-drop room board (resources/views/accommodation/board.blade.php): each room and the
// "Needs a room" list is a Sortable list; dropping a person saves the move through
// RoomBoardController@move, and anything the server refuses is put back where it came from.
import Sortable from 'sortablejs';

const board = document.querySelector('[data-room-board]');
const csrf = document.querySelector('meta[name=csrf-token]').content;
const notify = (message, type = 'success') => window.dispatchEvent(new CustomEvent('notify', { detail: { message, type } }));

function refreshCounts() {
    board.querySelectorAll('[data-room-list]').forEach((list) => {
        const used = list.querySelectorAll('[data-registration-id]').length;
        const counter = board.querySelector(`[data-count-for="${list.dataset.roomId}"]`);
        if (!counter) return;
        if (list.dataset.roomId === '') {
            counter.textContent = used;
            return;
        }
        const capacity = Number(list.dataset.capacity);
        counter.textContent = `${used} / ${capacity} beds`;
        list.closest('[data-room-card]').classList.toggle('is-full', used >= capacity);
    });
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
