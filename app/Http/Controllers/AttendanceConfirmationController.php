<?php

namespace App\Http\Controllers;

use App\Imports\HardCopyContactsImport;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationField;
use App\Notifications\AttendanceConfirmationRequest;
use App\Notifications\Concerns\NotifiesPerChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class AttendanceConfirmationController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorize('manageWhenOpen', $event);
        $event->ensureSystemRegistrationFields();
        $registrations = $event->registrations()
            ->with('participant')
            ->where('status', EventRegistration::STATUS_AWAITING_CONFIRMATION)
            ->latest('registered_at')
            ->paginate(50);

        return view('events.confirmations', [
            'event' => $event,
            'registrations' => $registrations,
            'defaultMessage' => AttendanceConfirmationRequest::DEFAULT_MESSAGE,
            'customFields' => $event->registrationFields()->where('is_system', false)->get(),
            'fieldTypes' => EventRegistrationField::CUSTOM_TYPES,
            'previewCode' => $registrations->first()?->registration_code,
        ]);
    }

    public function updateMessage(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        $validated = $request->validate(['confirmation_message' => ['required', 'string', 'max:2000']]);
        $event->update($validated);

        return back()->with('success', 'Welcome message updated.');
    }

    public function import(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:2048']);

        try {
            Excel::import(new HardCopyContactsImport($event), $request->file('file'));

            return back()->with('success', 'Contacts imported. Review the list below, then send confirmation requests.');
        } catch (Throwable $exception) {
            Log::error('Hard-copy contact import failed.', ['event_id' => $event->id, 'exception' => $exception]);

            return back()->with('error', 'The import could not be completed. Check the file format and try again.');
        }
    }

    public function remove(Event $event, EventRegistration $registration): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        abort_unless($registration->event_id === $event->id, 404);
        abort_unless($registration->status === EventRegistration::STATUS_AWAITING_CONFIRMATION, 422);
        $registration->delete();

        return back()->with('success', 'Contact removed.');
    }

    public function send(Event $event): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        $registrations = $event->registrations()
            ->with('participant')
            ->where('status', EventRegistration::STATUS_AWAITING_CONFIRMATION)
            ->get();

        $sent = 0;
        foreach ($registrations as $registration) {
            if (! $registration->participant->email && ! $registration->participant->phone) {
                continue;
            }
            NotifiesPerChannel::send($registration->participant, new AttendanceConfirmationRequest($registration));
            $registration->update(['confirmation_sent_at' => now(), 'confirmation_reminder_sent_at' => null]);
            $sent++;
        }

        return back()->with('success', "Confirmation requests sent to {$sent} attendee(s).");
    }
}
