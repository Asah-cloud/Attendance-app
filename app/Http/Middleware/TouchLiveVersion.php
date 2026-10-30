<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Services\LiveVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a successful save that changes people, registrations or rooms, move the live marker so
 * open pages redraw. Model events already do this for single records; this covers changes made
 * in bulk (undoing an import, clearing a list, a room plan) that skip them. Check-ins, scans,
 * meals and messages are left out: their pages update on their own, and they happen too often.
 */
class TouchLiveVersion
{
    /** Route names (prefixes) whose saves change what the people pages show. */
    private const ROUTES = [
        'events.registrations.', 'events.import.', 'events.accommodation.', 'events.registration-fields.',
        'events.update', 'events.badges.printed.', 'events.staff-badges.printed.', 'events.confirmations.import',
        'participants.', 'support-staff.import', 'support-staff.clear-all',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $name = (string) $request->route()?->getName();
        if ($request->isMethodSafe() || $response->getStatusCode() >= 400 || ! $this->changesPeople($name)) {
            return $response;
        }

        $live = app(LiveVersion::class);
        $event = $request->route('event');
        if ($event instanceof Event) {
            $live->touchEvent($event->id);
            $live->touchCompany($event->company_id);
        } elseif ($companyId = $request->user()?->company_id) {
            // A company-wide change (merging people, a staff list) can touch any of its events.
            $live->touchCompany($companyId);
            Event::where('company_id', $companyId)->pluck('id')->each(fn ($id) => $live->touchEvent($id));
        }

        return $response;
    }

    private function changesPeople(string $name): bool
    {
        foreach (self::ROUTES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
