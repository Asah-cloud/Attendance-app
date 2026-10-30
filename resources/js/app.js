import './bootstrap';
import './live-search';
import './onboarding-tour';

import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;
window.loadHtml5Qrcode = () => import('html5-qrcode').then(({ Html5Qrcode }) => Html5Qrcode);

// Only the room board page needs drag and drop, so it loads only there.
if (document.querySelector('[data-room-board]')) {
    import('./room-board');
}

Livewire.start();

// Open pages redraw themselves when people or rooms change (needs Alpine and Livewire above).
import('./live-updates');
