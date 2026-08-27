<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\ApplicationCache;
use App\Services\ParticipantRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly ApplicationCache $cache,
        private readonly ParticipantRegistrationService $registrations,
    ) {}

    private function getEventDay(Request $request, Event $event)
    {
        if ($request->has('day')) {
            $day = (int) $request->input('day');
            $this->ensureValidDay($event, $day);

            return $day;
        }

        return $event->activeAttendanceSession();
    }

    public function show(Request $request, Event $event)
    {
        $this->authorize('view', $event);

        $currentDay = $this->getEventDay($request, $event);
        $stats = $this->cache->rememberEvent($event->id, "attendance-stats:v2:day:{$currentDay}", fn () => [
            'totalMembers' => $event->attendanceEligibleParticipants()->count(),
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

    public function arrival(Event $event)
    {
        $this->authorize('view', $event);
        abort_unless($event->has_arrival_session, 404);

        $confirmedCount = $event->confirmedParticipants()->count();
        $arrivedCount = $event->arrivedParticipants()->count();

        return view('events.arrival', compact('event', 'confirmedCount', 'arrivedCount'));
    }

    public function store(Request $request, Event $event)
    {
        $this->authorize('update', $event);

        $request->validate(['participant_id' => 'required|integer']);
        $targetParticipant = $event->attendanceEligibleParticipants()->findOrFail($request->integer('participant_id'));
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

        if ($attendance->wasRecentlyCreated) {
            $this->cache->invalidateEvent($event->id, $event->company_id);
        }

        if (! $attendance->wasRecentlyCreated) {
            return back()->with('info', 'Member already marked present for '.$event->attendanceSessionLabel($currentDay).'.');
        }

        return back()->with('success', 'Attendance marked for '.$event->attendanceSessionLabel($currentDay).'!');
    }

    public function destroy(Request $request, Event $event, int $participant_id)
    {
        $this->authorize('update', $event);
        $event->attendanceEligibleParticipants()->findOrFail($participant_id);

        $currentDay = $this->getEventDay($request, $event);
        $this->ensureAttendanceCanBeMarked($event, $currentDay);

        Attendance::query()
            ->where('event_id', '=', $event->id)
            ->where('participant_id', '=', $participant_id)
            ->where('day', '=', $currentDay)
            ->delete();

        return back()->with('success', 'Attendance removed for '.$event->attendanceSessionLabel($currentDay).'.');
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

        return view('attendance.public-scan', [
            'event' => $event,
            'registration' => $registration,
            'session' => $event->has_arrival_session ? 0 : $event->activeAttendanceSession(),
        ]);
    }

    public function publicCheckIn(Event $event)
    {
        return view('attendance.public-scan', [
            'event' => $event,
            'registration' => null,
            'session' => $event->activeAttendanceSession(),
        ]);
    }

    public function publicArrivalCheckIn(Event $event)
    {
        abort_unless($event->has_arrival_session, 404);

        return view('attendance.public-scan', [
            'event' => $event,
            'registration' => null,
            'session' => 0,
        ]);
    }

    public function checkInByPhone(Request $request, Event $event)
    {
        return $this->checkInByPhoneForSession($request, $event, $event->activeAttendanceSession());
    }

    public function checkInArrivalByPhone(Request $request, Event $event)
    {
        abort_unless($event->has_arrival_session, 404);

        return $this->checkInByPhoneForSession($request, $event, 0);
    }

    private function checkInByPhoneForSession(Request $request, Event $event, int $day)
    {
        $validated = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $phone = $this->registrations->normalizePhone($validated['phone']);

        if (! $phone) {
            return back()->withInput()->with('error', 'Enter a valid registered phone number.');
        }

        $registration = $event->registrations()
            ->with('participant')
            ->where('status', EventRegistration::STATUS_CONFIRMED)
            ->whereHas('participant', fn ($query) => $query->whereIn('phone', [
                $phone, '0'.$phone, '233'.$phone, '+233'.$phone,
            ]))
            ->first();

        if (! $registration) {
            return back()->withInput()->with('error', 'We could not find a confirmed attendee with that phone number. Please check the number or ask an usher for help.');
        }

        if ($day > 0 && ! $event->participantHasArrived($registration->participant_id)) {
            return back()->withInput()->with('error', 'Arrival check-in must be completed before daily attendance can be marked.');
        }

        if (! $event->canMarkAttendanceForDay($day)) {
            return back()->with('error', 'Attendance is not open for this event right now. Please ask an event manager for help.');
        }

        $marker = $request->user();
        $attendance = Attendance::query()->createOrFirst([
            'event_id' => $event->id,
            'participant_id' => $registration->participant_id,
            'day' => $day,
        ], [
            'status' => 'present',
            'marked_by' => $marker?->can('scanAttendance', $event) ? $marker->id : null,
        ]);

        $this->cache->invalidateEvent($event->id, $event->company_id);

        $session = $event->attendanceSessionLabel($day);
        $message = $attendance->wasRecentlyCreated
            ? "Welcome, {$registration->participant->name}! Your {$session} check-in is complete."
            : "{$registration->participant->name} is already checked in for {$session}.";

        return back()->with('success', $message);
    }

    public function scanner(Event $event)
    {
        $this->authorize('scanAttendance', $event);

        return view('attendance.scanner', [
            'event' => $event,
            'session' => $event->activeAttendanceSession(),
            'checkInUrl' => route('events.scanner.check-in', $event),
        ]);
    }

    public function arrivalScanner(Event $event)
    {
        $this->authorize('scanAttendance', $event);
        abort_unless($event->has_arrival_session, 404);

        return view('attendance.scanner', [
            'event' => $event,
            'session' => 0,
            'checkInUrl' => route('events.arrival.scanner.check-in', $event),
        ]);
    }

    public function scan(Request $request, Event $event)
    {
        $this->authorize('scanAttendance', $event);

        return $this->scanForSession($request, $event, $event->activeAttendanceSession());
    }

    public function scanArrival(Request $request, Event $event)
    {
        $this->authorize('scanAttendance', $event);
        abort_unless($event->has_arrival_session, 404);

        return $this->scanForSession($request, $event, 0);
    }

    private function scanForSession(Request $request, Event $event, int $day)
    {

        $validated = $request->validate([
            'registration_code' => ['required', 'string', 'max:500'],
        ]);

        $code = $this->registrationCodeFromScan($validated['registration_code']);

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

        if ($day > 0 && ! $event->participantHasArrived($registration->participant_id)) {
            return response()->json(['message' => "{$registration->participant->name} has not completed Arrival check-in yet."], 422);
        }

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
                ? "Welcome, {$registration->participant->name}! Your {$event->attendanceSessionLabel($day)} check-in is complete. We are happy to have you here."
                : "Welcome back, {$registration->participant->name}! You are already checked in for {$event->attendanceSessionLabel($day)}. Enjoy the event.",
        ]);
    }

    private function totalDays(Event $event): int
    {
        return $event->totalDays();
    }

    private function registrationCodeFromScan(string $value): string
    {
        $value = trim($value);

        if (str_starts_with($value, 'ASAH-ATTENDANCE:')) {
            return substr($value, strlen('ASAH-ATTENDANCE:'));
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/check-in/([^/]+)$#', $path, $matches)) {
            return rawurldecode($matches[1]);
        }

        return $value;
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
