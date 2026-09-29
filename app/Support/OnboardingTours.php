<?php

namespace App\Support;

use Illuminate\Http\Request;

class OnboardingTours
{
    /** Every valid tour key, so the "mark as seen" endpoint only accepts real tours. */
    public const KEYS = [
        'dashboard',
        'events-index',
        'event-attendance',
        'event-attendees',
        'event-staff-checkin',
        'staff-roster',
        'event-forms',
        'event-food',
        'event-rooms',
        'event-reports',
        'event-messages',
        'event-billing',
        'event-settings',
        'billing',
        'organization',
        'team',
        'merge-duplicates',
    ];

    /**
     * Which tour, if any, belongs to the page the given request is for. One
     * place to keep this mapping so every page doesn't have to declare its
     * own key.
     */
    public static function keyFor(Request $request): ?string
    {
        $is = fn (string|array $pattern) => $request->routeIs($pattern);

        return match (true) {
            $is('dashboard') => 'dashboard',
            $is('events.index') => 'events-index',
            $is(['events.attendance', 'events.scanner', 'events.arrival*']) => 'event-attendance',
            $is(['events.registrations.*', 'events.badges', 'events.staff-badges', 'badge-exports.*']) => 'event-attendees',
            $is(['support-staff.checkin*', 'support-staff.report*']) => 'event-staff-checkin',
            $is('support-staff.index') => 'staff-roster',
            $is(['events.forms.*', 'events.registration-form.*', 'events.confirmations.*']) => 'event-forms',
            $is('events.meals.*') => 'event-food',
            $is('events.accommodation.*') => 'event-rooms',
            $is(['reports.*']) => 'event-reports',
            $is('events.messages.*') => 'event-messages',
            $is('events.billing.*') => 'event-billing',
            $is('events.edit') => 'event-settings',
            $is('billing.*') => 'billing',
            $is('organization.*') => 'organization',
            $is(['admin.users.*', 'admin.register-*']) => 'team',
            $is('participants.duplicates.*') => 'merge-duplicates',
            default => null,
        };
    }
}
