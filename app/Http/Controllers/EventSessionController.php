<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventSessionController extends Controller
{
    public function index(Event $event)
    {
        $this->authorize('update', $event);

        $stored = $event->eventSessions()->orderBy('session')->get();

        $days = collect(range(1, $event->totalDays()))->map(function (int $day) use ($stored) {
            $sessions = collect([['id' => null, 'session' => 1, 'label' => null]])
                ->concat($stored->where('day', $day)->map(fn (EventSession $s) => ['id' => $s->id, 'session' => $s->session, 'label' => $s->label]))
                ->sortBy('session')
                ->values();

            return ['day' => $day, 'sessions' => $sessions];
        });

        return view('events.sessions', compact('event', 'days'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $validated = $request->validate([
            'day' => ['required', 'integer', 'min:1', 'max:'.$event->totalDays()],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $day = (int) $validated['day'];
        $nextSession = $event->sessionCountForDay($day) + 1;

        EventSession::create([
            'event_id' => $event->id,
            'day' => $day,
            'session' => $nextSession,
            'label' => $validated['label'] ?? null,
        ]);

        return back()->with('success', $event->attendanceSessionLabel($day, $nextSession).' added.');
    }

    public function destroy(Event $event, EventSession $eventSession): RedirectResponse
    {
        $this->authorize('update', $event);
        abort_unless($eventSession->event_id === $event->id, 404);

        if ($eventSession->session !== $event->sessionCountForDay($eventSession->day)) {
            return back()->with('error', 'Only the last session on a day can be removed.');
        }

        $hasAttendance = Attendance::query()
            ->where('event_id', $event->id)
            ->where('day', $eventSession->day)
            ->where('session', $eventSession->session)
            ->exists();

        if ($hasAttendance) {
            return back()->with('error', 'This session already has recorded attendance and cannot be removed.');
        }

        $label = $event->attendanceSessionLabel($eventSession->day, $eventSession->session);
        $eventSession->delete();

        return back()->with('success', $label.' removed.');
    }
}
