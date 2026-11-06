<?php

use App\Jobs\SendStaffMessagePush;
use App\Models\Company;
use App\Models\PushSubscription;
use App\Models\StaffMessage;
use App\Models\StaffMessageRecipient;
use App\Models\User;
use App\Services\WebPushSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    config(['services.webpush.public_key' => 'BPublicKeyForTests', 'services.webpush.private_key' => 'private-key-for-tests']);
    $this->company = Company::create(['name' => 'Push Org']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->usher = User::factory()->create(['name' => 'Kwame Asante', 'company_id' => $this->company->id, 'role' => 'usher']);
    $this->usher->assignRole('usher');
    $this->subscribe = fn (User $user, string $endpoint = 'https://push.example.com/abc') => $this->actingAs($user)
        ->postJson(route('push-subscriptions.store'), ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token']]);
});

it('saves a phone that turns notifications on, and hands a shared phone to whoever turned them on last', function () {
    ($this->subscribe)($this->usher)->assertOk();
    expect(PushSubscription::sole()->user_id)->toBe($this->usher->id);

    $other = User::factory()->create(['company_id' => $this->company->id, 'role' => 'usher']);
    $other->assignRole('usher');
    ($this->subscribe)($other)->assertOk();
    expect(PushSubscription::sole()->user_id)->toBe($other->id);

    $this->actingAs($other)->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://push.example.com/abc'])->assertOk();
    expect(PushSubscription::count())->toBe(0);
});

it('only accepts secure push addresses', function () {
    $this->actingAs($this->usher)->postJson(route('push-subscriptions.store'), ['endpoint' => 'http://push.example.com/abc', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])
        ->assertUnprocessable();
});

it('pushes a new message to the recipients\' phones', function () {
    Queue::fake();
    ($this->subscribe)($this->usher);

    $this->actingAs($this->manager)->post(route('staff-messages.store'), ['audience' => 'all', 'body' => 'Gate B is closed.', 'urgent' => '1'])
        ->assertSessionHas('success', 'Message sent to 1 staff member, and pushed to the phones of 1 of them.');

    Queue::assertPushed(SendStaffMessagePush::class, fn ($job) => $job->messageId === StaffMessage::sole()->id);
});

it('sends the message to phones of staff who have not read it yet', function () {
    $reader = User::factory()->create(['company_id' => $this->company->id, 'role' => 'usher']);
    $reader->assignRole('usher');
    ($this->subscribe)($this->usher, 'https://push.example.com/unread');
    ($this->subscribe)($reader, 'https://push.example.com/read');
    $message = StaffMessage::create(['company_id' => $this->company->id, 'sender_id' => $this->manager->id, 'audience' => 'all', 'body' => 'Gate B is closed.', 'urgent' => true]);
    StaffMessageRecipient::create(['staff_message_id' => $message->id, 'user_id' => $this->usher->id]);
    StaffMessageRecipient::create(['staff_message_id' => $message->id, 'user_id' => $reader->id, 'read_at' => now()]);

    $sender = new class extends WebPushSender
    {
        public array $sent = [];

        public function send(Collection $subscriptions, array $payload, bool $urgent = false): int
        {
            $this->sent[] = [$subscriptions->pluck('endpoint')->all(), $payload, $urgent];

            return $subscriptions->count();
        }
    };
    (new SendStaffMessagePush($message->id))->handle($sender);

    [$endpoints, $payload, $urgent] = $sender->sent[0];
    expect($endpoints)->toBe(['https://push.example.com/unread'])
        ->and($payload['title'])->toBe('URGENT · Message from '.$this->manager->name)
        ->and($payload['body'])->toBe('Gate B is closed.')
        ->and($urgent)->toBeTrue();
});

it('asks staff to turn notifications on, and shows managers how many have', function () {
    $this->actingAs($this->usher)->get(route('dashboard'))->assertOk()->assertSee('Get messages on your phone')->assertSee('manifest.webmanifest');
    $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()->assertDontSee('Get messages on your phone');

    ($this->subscribe)($this->usher);
    $this->actingAs($this->manager)->get(route('staff-messages.index'))->assertOk()
        ->assertSee('Phone notifications on for 1 of 1 staff')
        ->assertSee('Kwame Asante (phone notifications on)');
});

it('stays quiet when push keys are not set up', function () {
    config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);

    $this->actingAs($this->usher)->get(route('dashboard'))->assertOk()->assertDontSee('Get messages on your phone');
    expect(app(WebPushSender::class)->send(collect([new PushSubscription]), ['title' => 'x']))->toBe(0);
});
