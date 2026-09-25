<?php

use App\Models\Event;
use App\Models\EventAttendeeCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Grants an event full access to every advanced feature for tests that exercise
 * functionality unrelated to billing (messages, badges, rooms, food, exports),
 * without going through the real finalize/pay flow or its notifications.
 */
function unlockAllEventFeatures(Event $event): void
{
    $charge = $event->attendeeCharge;

    if ($charge) {
        $charge->update(['status' => EventAttendeeCharge::STATUS_PAID, 'grandfathered' => true]);

        return;
    }

    EventAttendeeCharge::create([
        'event_id' => $event->id,
        'company_id' => $event->company_id,
        'status' => EventAttendeeCharge::STATUS_PAID,
        'registered_count' => 0,
        'tier_breakdown' => [],
        'amount_minor' => 0,
        'features_amount_minor' => 0,
        'feature_breakdown' => [],
        'grandfathered' => true,
        'currency' => config('plans.currency'),
        'finalized_at' => now(),
        'paid_at' => now(),
    ]);

    $event->unsetRelation('attendeeCharge');
}
