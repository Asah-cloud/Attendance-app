<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventBillingController extends Controller
{
    public function show(Event $event, EventBillingService $billing): View
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        $estimate = $charge ? null : $billing->estimate($event);

        return view('events.billing.show', compact('event', 'charge', 'estimate'));
    }

    public function finalize(Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        $billing->finalize($event);

        return redirect()->route('events.billing.show', $event)->with('success', 'Attendee bill finalized. Review it below and complete payment.');
    }

    public function pay(Event $event, EventBillingService $billing): RedirectResponse
    {
        $this->authorize('update', $event);
        $charge = $event->attendeeCharge;
        abort_unless($charge, 404);
        $billing->pay($charge);

        return redirect()->route('events.billing.show', $event)->with('success', 'Test payment approved. This event\'s attendee bill is now paid.');
    }
}
