<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceReset;
use App\Models\Event;
use App\Models\User;
use App\Notifications\AttendanceWasReset;
use App\Services\ApplicationCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Removes an event's check-ins (every day, or one day) and can put them back. Guarded by the
 * event title and the person's password, only while the event is open (closing reconciles the
 * bill from the check-ins), and the company's managers are emailed each time.
 */
class AttendanceResetController extends Controller
{
    public function __construct(private ApplicationCache $cache) {}

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);

        $request->validate([
            'day' => ['required', 'regex:/^(all|\d{1,3})$/'],
            'confirm_title' => ['required', 'string'],
            'password' => ['required', 'current_password'],
        ], ['password.current_password' => 'That password is wrong.']);

        if (trim($request->string('confirm_title')) !== $event->title) {
            throw ValidationException::withMessages(['confirm_title' => 'Type the exact event title to confirm.']);
        }

        $day = $request->input('day') === 'all' ? null : (int) $request->input('day');
        $query = Attendance::query()->where('event_id', $event->id)->when($day !== null, fn ($q) => $q->where('day', $day));

        $reset = DB::transaction(function () use ($request, $event, $day, $query) {
            $rows = $query->clone()->orderBy('id')->get()->map(fn (Attendance $attendance) => Arr::except($attendance->getAttributes(), ['id']));
            if ($rows->isEmpty()) {
                return null;
            }
            $query->clone()->delete();

            return $event->attendanceResets()->create([
                'user_id' => $request->user()->id,
                'day' => $day,
                'removed_count' => $rows->count(),
                'rows' => $rows->values()->all(),
                'ip_address' => $request->ip(),
            ]);
        });

        if (! $reset) {
            return back()->with('error', 'There were no check-ins to reset.');
        }

        $this->cache->invalidateEvent($event->id, $event->company_id);
        $this->tellManagers($event, $reset);

        return back()->with('success', "Attendance reset: removed {$reset->removed_count} check-in".($reset->removed_count === 1 ? '' : 's')." ({$reset->label()}). You can undo this from Reset attendance.");
    }

    public function undo(Request $request, Event $event, AttendanceReset $reset): RedirectResponse
    {
        $this->authorize('manageWhenOpen', $event);
        abort_unless($reset->event_id === $event->id, 404);
        if ($reset->undone_at) {
            return back()->with('error', 'That reset has already been undone.');
        }

        $restored = DB::transaction(function () use ($request, $reset) {
            // People deleted since the reset can't get their check-ins back; a check-in taken
            // again since then is kept as it is.
            $rows = collect($reset->rows);
            $people = DB::table('participants')->whereIn('id', $rows->pluck('participant_id')->unique())->pluck('id')->flip();
            $restored = 0;
            foreach ($rows->filter(fn ($row) => $people->has($row['participant_id']))->chunk(500) as $chunk) {
                $restored += DB::table('attendances')->insertOrIgnore($chunk->values()->all());
            }
            $reset->update(['undone_at' => now(), 'undone_by' => $request->user()->id]);

            return $restored;
        });

        $this->cache->invalidateEvent($event->id, $event->company_id);
        $this->tellManagers($event, $reset, undone: true);

        return back()->with('success', "Reset undone: {$restored} check-in".($restored === 1 ? '' : 's').' put back.');
    }

    private function tellManagers(Event $event, AttendanceReset $reset, bool $undone = false): void
    {
        $managers = User::role('manager')->where('company_id', $event->company_id)->get();
        Notification::send($managers, new AttendanceWasReset($reset, $undone));
    }
}
