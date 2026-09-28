<x-app-layout>
    <x-slot name="header">Billing</x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
                <div class="rounded-3xl bg-[#071426] p-8 text-white shadow-xl">
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-300">Pay per event</p>
                    <p class="mt-2 text-sm font-bold text-slate-300">{{ $company->name }}</p>
                    <h3 class="mt-3 text-3xl font-black">No subscription, pay only for the events you run</h3>
                    <p class="mt-2 max-w-2xl text-slate-300">Each event is billed per confirmed attendee, plus any advanced features you choose for it. Open an event's Billing tab to pick features, finalize and pay. Your reports stay available after the event.</p>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-7 shadow-sm">
                    <h3 class="font-black text-gray-900">Billing contact</h3>
                    <form method="POST" action="{{ route('billing.contact.update') }}" class="mt-5 space-y-4">
                        @csrf @method('PATCH')
                        <input type="email" name="email" value="{{ old('email', $company->email) }}" required class="w-full rounded-xl border-gray-200">
                        <x-input-error :messages="$errors->get('email')" />
                        <button class="w-full rounded-xl bg-gray-900 px-4 py-3 text-sm font-bold text-white">Update billing contact</button>
                    </form>
                </div>
            </section>

            <section class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 p-6"><h3 class="font-black text-gray-900">Event bills</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500"><tr><th class="p-4">Event</th><th class="p-4">Status</th><th class="p-4">Attendees</th><th class="p-4 text-right">Amount</th><th class="p-4"></th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($attendeeCharges as $charge)
                                <tr>
                                    <td class="p-4 font-bold">{{ $charge->event->title }}</td>
                                    <td class="p-4"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold uppercase">{{ str_replace('_', ' ', $charge->status) }}</span></td>
                                    <td class="p-4">{{ $charge->registered_count }}</td>
                                    <td class="p-4 text-right font-black">{{ $charge->currency }} {{ number_format($charge->amount_minor / 100, 2) }}</td>
                                    <td class="p-4 text-right"><a href="{{ route('events.billing.show', $charge->event) }}" class="text-xs font-bold text-blue-700">Open</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-8 text-center text-gray-500">No event bills yet. Open an event's Billing tab to finalize its bill.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($attendeeCharges->hasPages())<div class="border-t border-gray-100 p-4">{{ $attendeeCharges->links() }}</div>@endif
            </section>
        </div>
    </div>
</x-app-layout>
