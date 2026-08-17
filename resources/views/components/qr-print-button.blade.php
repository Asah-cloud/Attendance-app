@props(['event', 'currentDay'])

<div class="bg-white p-8 rounded-3xl shadow-sm border border-gray-100 text-center">
    <h3 class="font-black text-gray-400 mb-6 uppercase text-[10px] tracking-widest">Attendance QR</h3>
    
    <div class="inline-block p-6 bg-white border border-gray-100 rounded-3xl mb-6 shadow-sm">
        {!! QrCode::size(250)->color(30, 58, 138)->generate(URL::signedRoute('scan.events', ['event' => $event->id, 'day' => $currentDay])) !!}
    </div>

    <div class="mb-6">
        <a href="{{ route('events.print-qr', [$event->id, $currentDay]) }}" 
           target="_blank"
           class="w-full inline-flex justify-center items-center px-6 py-3 bg-blue-900 text-white rounded-xl font-black text-xs uppercase tracking-widest shadow-lg hover:bg-blue-800 transition-all active:scale-95">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
            </svg>
            Open QR for Printing
        </a>
    </div>
</div>
