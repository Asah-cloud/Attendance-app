<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\ApplicationCache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(private readonly ApplicationCache $cache) {}

    private function getEventDay(Request $request, Event $event)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($request->has('day')) {
            $day = (int) $request->input('day');
            $this->ensureValidDay($event, $day);

            return $day;
        }

        if ($user && $user->hasRole('admin')) {
            return (int) $request->query('day', 1);
        }

        $startDate = Carbon::parse($event->event_date)->startOfDay();
        $today = now()->startOfDay();

        return (int) min($this->totalDays($event), max(1, $startDate->diffInDays($today) + 1));
    }

    public function show(Request $request, Event $event)
    {
        $this->authorize('view', $event);

        $currentDay = $this->getEventDay($request, $event);
        $stats = $this->cache->rememberEvent($event->id, "attendance-stats:day:{$currentDay}", fn () => [
            'totalMembers' => $event->confirmedParticipants()->count(),
            'presentCount' => Attendance::query()
                ->where('event_id', '=', $event->id)
                ->where('day', '=', $currentDay)
                ->count('id'),
        ]);

        ['totalMembers' => $totalMembers, 'presentCount' => $presentCount] = $stats;

        return view('events.attendance', compact(
            'event', 'totalMembers', 'presentCount', 'currentDay'
        ));
    }

    public function store(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $request->validate(['participant_id' => 'required|integer']);
        $targetParticipant = $event->confirmedParticipants()->findOrFail($request->integer('participant_id'));
        $currentDay = $this->getEventDay($request, $event);
        $this->ensureAttendanceCanBeMarked($event, $currentDay);

        $attendance = Attendance::query()->createOrFirst([
            'event_id' => $event->id,
            'participant_id' => $targetParticipant->id,
            'day' => $currentDay,
        ], [
            'status' => 'present',
            'marked_by' => Auth::id(),
        ]);

        if (! $attendance->wasRecentlyCreated) {
            return back()->with('info', "Member already marked present for Day $currentDay.");
        }

        return back()->with('success', "Attendance marked for Day $currentDay!");
    }

    public function destroy(Request $request, Event $event, int $participant_id)
    {
        $this->authorize('update', $event);
        $event->confirmedParticipants()->findOrFail($participant_id);

        $currentDay = $this->getEventDay($request, $event);
        $this->ensureAttendanceCanBeMarked($event, $currentDay);

        Attendance::query()
            ->where('event_id', '=', $event->id)
            ->where('participant_id', '=', $participant_id)
            ->where('day', '=', $currentDay)
            ->delete();

        return back()->with('success', "Attendance removed for Day $currentDay.");
    }

    public function personalCheckIn(string $code)
    {
        $registration = EventRegistration::query()
            ->with(['event', 'participant'])
            ->where('registration_code', $code)
            ->firstOrFail();

        $event = $registration->event;
        $participant = $registration->participant;

        if (! $event || ! $participant) {
            abort(404);
        }

        return view('attendance.personal-result', [
            'registration' => $registration,
            'successful' => false,
            'message' => 'You are all set! Please show this QR code to an event manager or usher when you arrive. They will help you check in.',
        ]);
    }

    public function scanner(Event $event)
    {
        $this->authorize('scanAttendance', $event);

        return view('attendance.scanner', ['event' => $event]);
    }

    public function scan(Request $request, Event $event)
    {
        $this->authorize('scanAttendance', $event);

        $validated = $request->validate([
            'registration_code' => ['required', 'string', 'max:100'],
        ]);

        $code = str_starts_with($validated['registration_code'], 'ASAH-ATTENDANCE:')
            ? substr($validated['registration_code'], strlen('ASAH-ATTENDANCE:'))
            : $validated['registration_code'];

        $registration = $event->registrations()
            ->with('participant')
            ->where('registration_code', $code)
            ->first();

        if (! $registration) {
            return response()->json(['message' => 'We could not match this QR code to this event. Please check the code and try again, or ask a manager for help.'], 422);
        }

        if ($registration->status !== EventRegistration::STATUS_CONFIRMED) {
            return response()->json(['message' => "Welcome, {$registration->participant->name}. Your registration is not confirmed yet. Please speak with an event manager for assistance."], 422);
        }

        $day = $event->currentDay();

        if (! $event->canMarkAttendanceForDay($day)) {
            return response()->json(['message' => 'Thanks for checking in! Attendance is not open for this event right now. Please confirm the event date or ask a manager for help.'], 422);
        }

        $attendance = Attendance::query()->createOrFirst([
            'event_id' => $event->id,
            'participant_id' => $registration->participant_id,
            'day' => $day,
        ], [
            'status' => 'present',
            'marked_by' => Auth::id(),
        ]);

        return response()->json([
            'successful' => true,
            'already_present' => ! $attendance->wasRecentlyCreated,
            'name' => $registration->participant->name,
            'day' => $day,
            'message' => $attendance->wasRecentlyCreated
                ? "Welcome, {$registration->participant->name}! Your check-in for Day {$day} is complete. We are happy to have you here."
                : "Welcome back, {$registration->participant->name}! You are already checked in for Day {$day}. Enjoy the event.",
        ]);
    }

    private function totalDays(Event $event): int
    {
        return $event->totalDays();
    }

    private function ensureValidDay(Event $event, int $day): void
    {
        if ($day < 1 || $day > $this->totalDays($event)) {
            throw ValidationException::withMessages(['day' => 'The selected event day is invalid.']);
        }
    }

    private function ensureAttendanceCanBeMarked(Event $event, int $day): void
    {
        if (! $event->canMarkAttendanceForDay($day)) {
            throw ValidationException::withMessages([
                'day' => 'Attendance can only be changed for a day that has started while the event is active.',
            ]);
        }
    }
}
