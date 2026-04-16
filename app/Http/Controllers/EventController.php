<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $events = Event::latest()->get();

        return view('events.index', compact('events'));
    }

        public function show(Event $event)
    {
        // If someone accidentally lands on /events/1, send them to the attendance page
        return redirect()->route('events.attendance', $event->id);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('events.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date|after_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'description' => 'nullable|string',
            'day' => 'nullable|integer|min:1',
        ]);

        Event::create($validated);

        return redirect()->route('events.index')->with('success', 'Event created!');
    }
    public function updateDay(Request $request, Event $event)
{
    $start = \Carbon\Carbon::parse($event->event_date)->startOfDay();
    $end = $event->end_date ? \Carbon\Carbon::parse($event->end_date)->startOfDay() : $start;
    $maxDays = $start->diffInDays($end) + 1;

    $validated = $request->validate([
        'day' => "required|integer|min:1|max:{$maxDays}"
    ]);

    $event->update(['day' => $validated['day']]);

    return back()->with('success', "Switched to Day {$validated['day']} successfully.");
}


// Show the edit form
public function edit(Event $event)
{
    return view('events.edit', compact('event'));
}

// Save the updated data
public function update(Request $request, Event $event)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'event_date' => 'required|date',
        'end_date' => 'nullable|date|after_or_equal:event_date',
        'description' => 'nullable|string',
        'location' => 'nullable|string',
    ]);

    $event->update($validated);

    return redirect()->route('events.index')->with('success', 'Event updated successfully!');
}

    // Delete the event
public function destroy(Event $event)
{
    $event->delete();
    return redirect()->route('events.index')->with('success', 'Event and its records deleted.');
}
}
