<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\LiveVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The live marker an open page compares, to know when to redraw (resources/js/live-updates.js). */
class LiveVersionController extends Controller
{
    public function __invoke(Request $request, LiveVersion $live): JsonResponse
    {
        if ($eventId = $request->integer('event')) {
            $event = Event::findOrFail($eventId);
            $this->authorize('view', $event);

            return response()->json(['version' => $live->event($event->id)]);
        }

        return response()->json(['version' => $live->company((int) $request->user()->company_id)]);
    }
}
