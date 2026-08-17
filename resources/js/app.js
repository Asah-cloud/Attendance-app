import './bootstrap';

import { Alpine, Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;
window.loadHtml5Qrcode = () => import('html5-qrcode').then(({ Html5Qrcode }) => Html5Qrcode);

Livewire.start();
