<x-public-layout
    title="Asah Apex Attendance — Attendance, made effortless"
    description="QR check-in, live attendance tracking, and clear reporting for every event. Built for organisations that need a faster, more accountable headcount."
>
    <section class="relative overflow-hidden bg-[#071426] pt-20 text-white">
        <div class="absolute inset-0 opacity-40" style="background-image: radial-gradient(circle at 75% 20%, rgba(36,107,253,.4), transparent 28%), radial-gradient(circle at 20% 70%, rgba(244,184,96,.15), transparent 24%)"></div>
        <div class="relative mx-auto grid min-h-[760px] max-w-7xl items-center gap-14 px-5 py-20 lg:grid-cols-[1.05fr_.95fr] lg:px-8">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-blue-400/25 bg-blue-400/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.2em] text-blue-200">
                    <span class="h-2 w-2 rounded-full bg-amber-300"></span> An Asah Apex system
                </div>
                <h1 class="mt-8 max-w-3xl text-5xl font-extrabold leading-[1.02] tracking-[-0.055em] sm:text-6xl lg:text-7xl">Know who showed up. <span class="text-blue-400">Act on what matters.</span></h1>
                <p class="mt-7 max-w-xl text-lg leading-8 text-slate-300">A faster, cleaner way to manage event attendance—from QR check-in and live headcounts to reports your team can actually use.</p>
                <div class="mt-10 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('pricing') }}" class="rounded-2xl bg-blue-600 px-7 py-4 text-center text-sm font-extrabold shadow-2xl shadow-blue-950 transition hover:-translate-y-0.5 hover:bg-blue-500">Choose a plan</a>
                    <a href="#how-it-works" class="rounded-2xl border border-white/15 bg-white/5 px-7 py-4 text-center text-sm font-extrabold transition hover:bg-white/10">See how it works</a>
                </div>
                <div class="mt-10 flex flex-wrap gap-x-8 gap-y-3 text-sm font-semibold text-slate-400">
                    <span>✓ No special hardware</span><span>✓ Mobile-ready</span><span>✓ Secure company access</span>
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-xl">
                <div class="absolute -inset-8 rounded-full bg-blue-600/20 blur-3xl"></div>
                <img src="{{ asset('images/asah-apex-event-checkin.webp') }}" alt="Guests using QR check-in at a professional event" width="1400" height="713" fetchpriority="high" class="relative h-[520px] w-full rounded-[2rem] border border-white/15 object-cover object-[62%_center] shadow-2xl shadow-black/40">
                <div class="absolute -bottom-10 -left-5 hidden w-[340px] overflow-hidden rounded-[2rem] border border-white/15 bg-white p-2 shadow-2xl shadow-black/40 sm:block">
                    <div class="rounded-[1.5rem] bg-slate-50 p-5 text-slate-900 sm:p-7">
                        <div class="flex items-center justify-between">
                            <div><p class="text-xs font-bold uppercase tracking-widest text-blue-600">Live event</p><p class="mt-1 text-lg font-extrabold">Annual Leadership Summit</p></div>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Active</span>
                        </div>
                        <div class="mt-7 grid grid-cols-3 gap-3">
                            <div class="rounded-2xl bg-[#071426] p-4 text-white"><p class="text-[10px] uppercase tracking-wider text-slate-400">Invited</p><p class="mt-2 text-2xl font-extrabold">1,240</p></div>
                            <div class="rounded-2xl bg-blue-600 p-4 text-white"><p class="text-[10px] uppercase tracking-wider text-blue-100">Present</p><p class="mt-2 text-2xl font-extrabold">986</p></div>
                            <div class="rounded-2xl bg-amber-100 p-4 text-amber-950"><p class="text-[10px] uppercase tracking-wider text-amber-700">Rate</p><p class="mt-2 text-2xl font-extrabold">79%</p></div>
                        </div>
                        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
                            <div class="flex items-end gap-2" aria-label="Attendance activity chart">
                                @foreach([28, 42, 34, 62, 48, 76, 58, 86, 72, 94, 80, 100] as $height)
                                    <span class="flex-1 rounded-t-md bg-gradient-to-t from-blue-700 to-blue-400" style="height: {{ $height }}px"></span>
                                @endforeach
                            </div>
                            <div class="mt-4 flex justify-between text-xs font-semibold text-slate-400"><span>Doors opened</span><span>Check-ins now</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-white py-8">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-12 gap-y-4 px-5 text-xs font-extrabold uppercase tracking-[0.22em] text-slate-400 lg:px-8">
            <span>Churches</span><span>Conferences</span><span>Associations</span><span>Corporate teams</span><span>Training events</span>
        </div>
    </section>

    <section id="features" class="mx-auto max-w-7xl px-5 py-24 lg:px-8">
        <div class="max-w-2xl">
            <p class="text-xs font-extrabold uppercase tracking-[0.25em] text-blue-600">Everything in one place</p>
            <h2 class="mt-4 text-4xl font-extrabold tracking-[-0.04em] text-[#071426] sm:text-5xl">Less queueing. Less guesswork. Better events.</h2>
        </div>
        <div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach([
                ['01', 'QR check-in', 'Turn any phone into a fast check-in point with secure, event-specific QR links.', 'feature-qr.webp', 'An attendee scanning an event QR code'],
                ['02', 'Live attendance', 'Watch numbers update as people arrive and move confidently with a real-time headcount.', 'feature-live.webp', 'A live attendance dashboard on a tablet'],
                ['03', 'Participant imports', 'Bring existing lists in from Excel and keep every attendee connected to the right event.', 'feature-import.webp', 'A participant spreadsheet being imported'],
                ['04', 'Multi-day tracking', 'Track attendance day by day without mixing sessions or losing the full event picture.', 'feature-days.webp', 'Multi-day attendance tracking on a tablet'],
                ['05', 'Clear reporting', 'See present and absent participants, attendance rates, and downloadable summaries.', 'feature-reports.webp', 'An event coordinator reviewing attendance reports'],
                ['06', 'Company controls', 'Keep teams and data separated with role-based access, multi-manager accounts, and subscription or pay-per-event billing controls.', 'feature-controls.webp', 'A company administrator managing permissions'],
                ['07', 'Everything around the event', 'Custom registration forms, food distribution tracking, printable A5/A6 badges, and Excel/CSV/PDF exports — plus your own logo, flyer, SMS sender ID and email domain.', 'feature-event-operations.webp', 'An event coordinator managing badges and meal collection'],
            ] as [$number, $heading, $copy, $image, $alt])
                <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-xl hover:shadow-blue-950/5">
                    @if($image)
                        <img src="{{ asset('images/'.$image) }}" alt="{{ $alt }}" width="720" height="540" loading="lazy" decoding="async" class="h-52 w-full object-cover transition duration-500 group-hover:scale-[1.03]">
                    @else
                        <div class="grid h-52 w-full place-items-center bg-gradient-to-br from-blue-600 to-blue-800 text-white">
                            <svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2Z" /></svg>
                        </div>
                    @endif
                    <div class="p-7">
                        <span class="text-xs font-extrabold text-blue-600">{{ $number }}</span>
                        <h3 class="mt-5 text-xl font-extrabold text-[#071426]">{{ $heading }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $copy }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="overflow-hidden bg-white py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-14 px-5 lg:grid-cols-2 lg:px-8">
            <div class="relative">
                <div class="absolute -inset-5 rounded-[2.5rem] bg-gradient-to-br from-blue-100 to-amber-50"></div>
                <img src="{{ asset('images/asah-apex-live-insights.webp') }}" alt="Event coordinators reviewing live attendance insights" width="1200" height="612" loading="lazy" decoding="async" class="relative h-[500px] w-full rounded-[2rem] object-cover object-center shadow-2xl shadow-blue-950/10">
                <div class="absolute bottom-5 right-5 rounded-2xl bg-white/95 p-4 shadow-xl backdrop-blur">
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-blue-600">Live visibility</p>
                    <p class="mt-1 text-lg font-extrabold text-[#071426]">Decisions without delay.</p>
                </div>
            </div>
            <div class="lg:pl-8">
                <p class="text-xs font-extrabold uppercase tracking-[0.25em] text-blue-600">Built around your team</p>
                <h2 class="mt-4 text-4xl font-extrabold tracking-[-0.04em] text-[#071426] sm:text-5xl">See the event clearly while it is happening.</h2>
                <p class="mt-6 text-lg leading-8 text-slate-600">Give coordinators a reliable view of arrivals, attendance rates, and daily participation—without chasing paper lists or waiting for someone to update a spreadsheet.</p>
                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-5"><p class="font-extrabold text-[#071426]">One shared picture</p><p class="mt-2 text-sm leading-6 text-slate-600">Authorised teams work from the same live information.</p></div>
                    <div class="rounded-2xl bg-slate-50 p-5"><p class="font-extrabold text-[#071426]">Useful after the event</p><p class="mt-2 text-sm leading-6 text-slate-600">Turn check-ins into clean summaries and exports.</p></div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="bg-[#eaf0ff] py-24">
        <div class="mx-auto max-w-7xl px-5 lg:px-8">
            <div class="grid gap-14 lg:grid-cols-[.8fr_1.2fr]">
                <div>
                    <p class="text-xs font-extrabold uppercase tracking-[0.25em] text-blue-600">Simple by design</p>
                    <h2 class="mt-4 text-4xl font-extrabold tracking-[-0.04em] text-[#071426]">From setup to insight in three steps.</h2>
                    <p class="mt-5 leading-8 text-slate-600">Your team should be focused on the event—not fighting the attendance tool.</p>
                </div>
                <div class="grid gap-5 md:grid-cols-3 lg:grid-cols-1">
                    @foreach([
                        ['Create your event', 'Set the dates, location, company, and expected participant list.', 'images/how-create-event.webp', 'An organizer creating an event on a laptop'],
                        ['Share the check-in', 'Print or display the secure QR code and let attendees check in from their phones.', 'images/asah-apex-event-checkin.webp', 'An attendee scanning an event QR code'],
                        ['Review and report', 'Follow live numbers, make authorised corrections, and export clear reports.', 'images/how-review-report.webp', 'Event leaders reviewing an attendance report'],
                    ] as $index => [$heading, $copy, $image, $alt])
                        <div class="overflow-hidden rounded-3xl bg-white shadow-sm lg:grid lg:grid-cols-[180px_1fr]">
                            <img src="{{ asset($image) }}" alt="{{ $alt }}" width="900" height="675" loading="lazy" decoding="async" class="h-44 w-full object-cover lg:h-full">
                            <div class="flex gap-4 p-5">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-[#071426] text-sm font-extrabold text-white">{{ $index + 1 }}</span>
                                <div><h3 class="font-extrabold text-[#071426]">{{ $heading }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $copy }}</p></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-24 lg:px-8">
        <div class="overflow-hidden rounded-[2.5rem] bg-[#071426] px-7 py-14 text-center text-white sm:px-14">
            <p class="text-xs font-extrabold uppercase tracking-[0.25em] text-amber-300">Ready when you are</p>
            <h2 class="mx-auto mt-5 max-w-3xl text-4xl font-extrabold tracking-[-0.04em] sm:text-5xl">Give every arrival a smoother start.</h2>
            <p class="mx-auto mt-5 max-w-2xl leading-8 text-slate-300">Choose a plan that fits your events today and scales with your organisation tomorrow.</p>
            <a href="{{ route('pricing') }}" class="mt-9 inline-flex rounded-2xl bg-white px-7 py-4 text-sm font-extrabold text-[#071426] transition hover:bg-blue-50">Explore pricing</a>
        </div>
    </section>
</x-public-layout>
