<?php

use App\Http\Controllers\CustomMessageController;
use App\Jobs\SendCustomAttendeeMessageJob;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\CustomAttendeeMessage;
use App\Support\MergeFields;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

function roomMessageSetup(): array
{
    $company = Company::create(['name' => 'TAC']);
    $event = Event::create(['company_id' => $company->id, 'title' => 'Retreat', 'event_date' => now()->addWeek(), 'accommodation_enabled' => true]);
    unlockAllEventFeatures($event);
    Role::findOrCreate('manager');
    $manager = User::factory()->create(['company_id' => $company->id, 'role' => 'manager']);
    $manager->assignRole('manager');
    $site = $event->accommodationSites()->create(['name' => 'Main Campus', 'address' => 'Gomoa Fetteh']);
    $floor = $site->blocks()->create(['name' => 'New Block'])->floors()->create(['name' => 'First Floor']);
    $room = $floor->rooms()->create(['name' => 'NB-101', 'capacity' => 4]);
    $people = collect(['Kofi Mensah', 'Ama Owusu', 'No Room Yet'])->map(function ($name) use ($company, $event) {
        $person = Participant::create(['company_id' => $company->id, 'name' => $name, 'email' => str($name)->slug().'@example.com']);
        $event->registrations()->create(['participant_id' => $person->id, 'status' => EventRegistration::STATUS_CONFIRMED, 'accommodation_required' => true]);

        return $person;
    });
    $told = null;
    foreach ([0, 1] as $i) {
        $assignment = $event->registrations()->where('participant_id', $people[$i]->id)->first()
            ->roomAssignment()->create(['accommodation_room_id' => $room->id, 'status' => 'assigned', 'method' => 'manual']);
        $told ??= $assignment;
    }
    $told->update(['notification_sent_at' => now()]);

    return [$event, $manager, $room, $people];
}

it('opens Messages with the people not yet told and a room message to edit', function () {
    [$event, $manager, , $people] = roomMessageSetup();

    $this->actingAs($manager)->get(route('events.messages.create', ['event' => $event, 'rooms' => 'new']))
        ->assertOk()
        ->assertViewHas('selectedParticipantIds', [$people[1]->id])
        ->assertViewHas('preset', CustomMessageController::ROOM_MESSAGE)
        ->assertSee('{room_link}', false);

    $this->actingAs($manager)->get(route('events.messages.create', ['event' => $event, 'rooms' => 'all']))
        ->assertViewHas('selectedParticipantIds', fn ($ids) => collect($ids)->sort()->values()->all() === collect([$people[0]->id, $people[1]->id])->sort()->values()->all());
});

it('fills each person\'s own room into a message and marks them as told', function () {
    [$event, , , $people] = roomMessageSetup();
    $message = $event->customMessages()->create([
        'company_id' => $event->company_id,
        'subject' => 'Your room for {event}',
        'email_body' => "Room: {room}\nBuilding: {building}, {floor}\nLocation: {location}",
        'mode' => 'smart',
        'recipient_count' => 2,
    ]);
    Notification::fake();
    $recipients = collect([$people[1], $people[2]])->map(fn ($person) => $message->recipients()->create([
        'participant_id' => $person->id, 'name' => $person->name, 'email' => $person->email, 'channel' => 'mail', 'status' => 'pending',
    ]));

    $recipients->each(fn ($recipient) => (new SendCustomAttendeeMessageJob($recipient->id))->handle());

    Notification::assertSentTo($recipients[0], CustomAttendeeMessage::class, function ($notification) use ($recipients) {
        $lines = implode(' ', $notification->toMail($recipients[0])->viewData['lines']);

        return str_contains($lines, 'Room: NB-101') && str_contains($lines, 'New Block, First Floor') && str_contains($lines, 'Main Campus, Gomoa Fetteh');
    });
    Notification::assertSentTo($recipients[1], CustomAttendeeMessage::class, fn ($notification) => str_contains(implode(' ', $notification->toMail($recipients[1])->viewData['lines']), 'Room: '.MergeFields::NO_ROOM));

    $assignment = $event->registrations()->where('participant_id', $people[1]->id)->first()->roomAssignment;
    expect($assignment->notification_sent_at)->not->toBeNull();
});

it('does not mark people as told by a message that does not mention their room', function () {
    [$event, , , $people] = roomMessageSetup();
    $message = $event->customMessages()->create(['company_id' => $event->company_id, 'subject' => 'Hello', 'email_body' => 'See you soon, {first_name}', 'mode' => 'smart', 'recipient_count' => 1]);
    Notification::fake();
    $recipient = $message->recipients()->create(['participant_id' => $people[1]->id, 'name' => $people[1]->name, 'email' => $people[1]->email, 'channel' => 'mail', 'status' => 'pending']);

    (new SendCustomAttendeeMessageJob($recipient->id))->handle();

    expect($event->registrations()->where('participant_id', $people[1]->id)->first()->roomAssignment->notification_sent_at)->toBeNull();
});

it('offers the room message from the room board', function () {
    [$event, $manager] = roomMessageSetup();

    $this->actingAs($manager)->get(route('events.accommodation.board', $event))
        ->assertOk()
        ->assertSee('Tell people their rooms')
        ->assertSee(route('events.messages.create', ['event' => $event, 'rooms' => 'new']), false);
});

it('recognises the room placeholders even when the event has accommodation switched off', function () {
    [$event, $manager] = roomMessageSetup();
    $event->update(['accommodation_enabled' => false]);

    $this->actingAs($manager)->get(route('events.messages.create', ['event' => $event, 'rooms' => 'all']))
        ->assertOk()
        // It has rooms, so the room buttons still show, and the tokens are never flagged as unknown.
        ->assertViewHas('composeConfig', fn ($config) => array_key_exists('room', $config['tokens']) && array_key_exists('room_link', $config['knownTokens']));
});
