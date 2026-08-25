<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\EventAttendeeCharge;
use App\Services\EventBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EventBillingController extends Controller
{
    public function index(): View
    {
        $charges = EventAttendeeCharge::query()
            ->whereIn('status', [EventAttendeeCharge::STATUS_PENDING_PAYMENT, EventAttendeeCharge::STATUS_REFUND_DUE])
            ->with(['event', 'company'])
            ->latest('finalized_at')
            ->paginate(25);

        return view('superadmin.event-billing.index', compact('charges'));
    }

    public function markRefunded(EventAttendeeCharge $charge, EventBillingService $billing): RedirectResponse
    {
        $billing->markRefunded($charge);

        return back()->with('success', 'Marked as refunded.');
    }
}
