<?php

namespace App\Http\Controllers;

use App\Imports\UsersImport;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Helper to get the current event day logic.
     * Prioritizes form input, then URL query, then calendar date.
     */
    private function getEventDay(Request $request, Event $event)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // 1. Prioritize 'day' sent via hidden form input or URL
        if ($request->has('day')) {
            return (int) $request->input('day');
        }

        // 2. Admin fallback for URL override (?day=2)
        if ($user && $user->hasRole('admin')) {
            return (int) $request->query('day', 1);
        }

        // 3. Others are locked to calendar date calculation
        $startDate = Carbon::parse($event->event_date)->startOfDay();
        $today = now()->startOfDay();
        
        return max(1, $startDate->diffInDays($today) + 1);
    }

    public function show(Request $request, Event $event)
    {
        $currentDay = $this->getEventDay($request, $event);

        $totalMembers = $event->users()->count(); 
        
        $presentCount = Attendance::where('event_id', $event->id)
                                    ->where('day', $currentDay)
                                    ->count();

        return view('events.attendance', compact(
            'event', 'totalMembers', 'presentCount', 'currentDay'
        ));
    }

    public function store(Request $request, Event $event)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $currentDay = $this->getEventDay($request, $event);

        // Prevent duplicates
        $alreadyMarked = Attendance::where('event_id', $event->id)
            ->where('user_id', $request->user_id)
            ->where('day', $currentDay)
            ->exists();

        if ($alreadyMarked) {
            return back()->with('info', "Member already marked present for Day $currentDay.");
        }

        Attendance::create([
            'event_id'   => $event->id,
            'user_id'    => $request->user_id,
            'status'     => 'present',
            'day'        => $currentDay, 
            'day_number' => $currentDay, 
            'marked_at'  => now(),
        ]);

        return back()->with('success', "Attendance marked for Day $currentDay!");
    }

    public function destroy(Request $request, Event $event, $user_id)
    {
        $currentDay = $this->getEventDay($request, $event);

        Attendance::where('event_id', $event->id)
            ->where('user_id', $user_id)
            ->where('day', $currentDay)
            ->delete();

        return back()->with('success', "Attendance removed for Day $currentDay.");
    }

    public function processPublicScan(Request $request, Event $event)
    {
        $request->validate(['phone' => 'required']);

        $selectedDay = $this->getEventDay($request, $event);

        // Clean phone input
        $inputPhone = preg_replace('/[^0-9]/', '', $request->phone);
        $lastNine = substr($inputPhone, -9);

        $targetUser = User::where('phone', 'LIKE', '%' . $lastNine)->first();

        if (!$targetUser) {
            return back()->with('error', "Number ending in ...$lastNine not found.");
        }

        Attendance::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $targetUser->id, 'day' => $selectedDay],
            ['status' => 'present', 'day_number' => $selectedDay, 'marked_at' => now()]
        );

        return back()->with('success', "Welcome, {$targetUser->name}! Marked for Day $selectedDay");
    }

    public function import(Request $request, Event $event)
    {
        set_time_limit(300);
        $request->validate(['file' => 'required|mimes:xlsx,csv,xls']);
        try {
            Excel::import(new UsersImport($event), $request->file('file'));
            return back()->with('success', 'Members added successfully!');
        } catch (\Exception $e) {
            return back()->withErrors('Import failed: '.$e->getMessage());
        }
    }

    public function publicScan(Event $event)
    {
        return view('attendance.public-scan', compact('event'));
    }
}