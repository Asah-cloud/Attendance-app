<?php

use App\Exports\FormResponsesExport;
use App\Models\AttendeePayment;
use App\Models\Company;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\EventRegistrationSubmitted;
use App\Services\AttendeePaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'manager', 'usher'] as $role) {
        Role::findOrCreate($role);
    }
    Notification::fake();
    config()->set('services.paystack', ['secret_key' => 'sk_test_123', 'public_key' => 'pk_test_123', 'base_url' => 'https://paystack.test']);
    $this->company = Company::create(['name' => 'Grace Chapel', 'email' => 'office@grace.test', 'payout_subaccount_code' => 'ACCT_grace']);
    $this->manager = User::factory()->create(['company_id' => $this->company->id, 'role' => 'manager']);
    $this->manager->assignRole('manager');
    $this->event = Event::create(['company_id' => $this->company->id, 'title' => 'Youth Camp', 'slug' => 'youth-camp', 'event_date' => now()->addWeek(), 'registration_enabled' => true]);
    $this->form = Form::create(['event_id' => $this->event->id, 'title' => 'Camp T-shirt', 'is_open' => true,
        'payment_enabled' => true, 'payment_label' => 'T-shirt', 'payment_amount_minor' => 5000]);
    $this->paystackStatus = 'success';
});

/** Fake Paystack: checkout links, and verify answers with the stored amount (or $test->paystackStatus). */
function fakePaystack($test): void
{
    Http::fake([
        'paystack.test/transaction/initialize' => fn (Request $request) => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://paystack.test/pay/'.$request['reference']]]),
        'paystack.test/transaction/verify/*' => function (Request $request) use ($test) {
            $payment = AttendeePayment::where('reference', Str::afterLast($request->url(), '/'))->first();

            return Http::response(['status' => true, 'data' => ['status' => $test->paystackStatus, 'amount' => $payment?->amount_minor, 'currency' => 'GHS', 'channel' => 'mobile_money']]);
        },
    ]);
}

function initializeRequest(): Request
{
    return Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/transaction/initialize'))->last()[0];
}

it('takes people to Paystack after a paid form and marks the response paid when they come back', function () {
    fakePaystack($this);

    $this->get(route('forms.show', ['youth-camp', $this->form->slug]))->assertOk()->assertSee('GH₵ 50.00')->assertSee('Submit &amp; pay', false);
    $response = $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com']);

    $payment = AttendeePayment::sole();
    $response->assertRedirect('https://paystack.test/pay/'.$payment->reference);
    expect(FormResponse::sole()->payment_status)->toBe('unpaid')
        ->and($payment->amount_minor)->toBe(5000)->and($payment->platform_fee_minor)->toBe(0);
    $sent = initializeRequest();
    expect($sent['subaccount'])->toBe('ACCT_grace')->and($sent['bearer'])->toBe('subaccount')
        ->and($sent['amount'])->toBe(5000)->and($sent['email'])->toBe('kwame@example.com')
        ->and(isset($sent['transaction_charge']))->toBeFalse()->and($sent['channels'])->toContain('mobile_money');

    $this->get(route('payments.return', $payment->reference))
        ->assertRedirect(route('forms.thank-you', ['youth-camp', $this->form->slug]))
        ->assertSessionHas('payment_success');
    expect(FormResponse::sole()->payment_status)->toBe('paid')->and(FormResponse::sole()->amount_paid_minor)->toBe(5000)
        ->and($payment->fresh()->status)->toBe('paid')->and($payment->fresh()->channel)->toBe('mobile_money');
});

it('takes the platform fee from each payment only while it is switched on', function () {
    fakePaystack($this);
    PlatformSetting::set(AttendeePaymentService::FEE_ENABLED, '1');
    PlatformSetting::set(AttendeePaymentService::FEE_PERCENT, '3');

    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com']);
    expect(initializeRequest()['transaction_charge'])->toBe(150)->and(AttendeePayment::sole()->platform_fee_minor)->toBe(150);

    PlatformSetting::set(AttendeePaymentService::FEE_ENABLED, '0');
    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'ama@example.com']);
    expect(isset(initializeRequest()['transaction_charge']))->toBeFalse();
});

it('lets people choose the amount when the manager allows it, down to the minimum', function () {
    fakePaystack($this);
    $this->form->update(['payment_payer_sets_amount' => true, 'payment_minimum_minor' => 2000]);

    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com', 'payment_amount' => '10'])
        ->assertSessionHasErrors('payment_amount');
    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com', 'payment_amount' => '75.50'])
        ->assertRedirect();

    expect(AttendeePayment::sole()->amount_minor)->toBe(7550);
});

it('keeps a response unpaid when the payment does not go through, and lets them try again', function () {
    fakePaystack($this);
    $this->paystackStatus = 'abandoned';
    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com']);
    $first = AttendeePayment::sole();

    $this->get(route('payments.return', $first->reference))->assertOk()->assertSee('hasn\'t gone through', false)->assertSee('Pay now');
    expect(FormResponse::sole()->payment_status)->toBe('unpaid')->and($first->fresh()->status)->toBe('failed');

    $retry = $this->post(route('payments.retry', $first->reference));
    $second = AttendeePayment::latest('id')->first();
    expect($second->reference)->not->toBe($first->reference)->and($second->payable_id)->toBe(FormResponse::sole()->id);
    $retry->assertRedirect('https://paystack.test/pay/'.$second->reference);
});

it('does not accept a payment for a different amount than asked', function () {
    Http::fake([
        'paystack.test/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://paystack.test/pay/x']]),
        'paystack.test/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => 'success', 'amount' => 100, 'currency' => 'GHS']]),
    ]);
    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com']);

    $this->get(route('payments.return', AttendeePayment::sole()->reference))->assertOk();
    expect(FormResponse::sole()->payment_status)->toBe('unpaid');
});

it('holds a paid registration\'s confirmation message until the webhook says it is paid', function () {
    fakePaystack($this);
    $this->event->update(['registration_payment_enabled' => true, 'registration_payment_amount_minor' => 20000, 'registration_payment_label' => 'Camp fee', 'automatic_attendee_email' => true]);

    $this->get(route('events.register', $this->event))->assertOk()->assertSee('Camp fee')->assertSee('GH₵ 200.00');
    $this->post(route('events.register.store', $this->event), [
        'name' => 'Kwame Asante', 'email' => 'kwame@example.com', 'phone' => '0241234567', 'gender' => 'Male', 'category' => 'Youth', 'consent' => 1,
    ])->assertRedirect();

    $registration = EventRegistration::sole();
    expect($registration->payment_status)->toBe('unpaid');
    Notification::assertNothingSent();
    $this->get(route('registrations.confirmation', $registration->management_token))->assertSee('Payment needed')->assertSee('Pay now');

    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => AttendeePayment::sole()->reference, 'status' => 'success', 'amount' => 20000, 'currency' => 'GHS']]);
    $this->call('POST', route('paystack.webhook'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_123')], content: $body)->assertOk();

    expect($registration->fresh()->payment_status)->toBe('paid')->and($registration->fresh()->amount_paid_minor)->toBe(20000);
    Notification::assertSentTo($registration->participant, EventRegistrationSubmitted::class);
    $this->get(route('registrations.confirmation', $registration->management_token))->assertSee('Paid · GH₵ 200.00');

    // A second confirmation for the same payment changes nothing.
    $this->call('POST', route('paystack.webhook'), server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, 'sk_test_123')], content: $body)->assertOk();
    Notification::assertSentToTimes($registration->participant, EventRegistrationSubmitted::class, 1);
});

it('saves a payout account as a Paystack sub-account', function () {
    $this->company->update(['payout_subaccount_code' => null]);
    Http::fake([
        'paystack.test/bank*' => Http::response(['status' => true, 'data' => [['name' => 'MTN', 'code' => 'MTN', 'active' => true], ['name' => 'Telecel', 'code' => 'VOD', 'active' => true]]]),
        'paystack.test/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_new']]),
    ]);

    $this->actingAs($this->manager)->get(route('organization.payouts.edit'))->assertOk()->assertSee('Telecel');
    $this->put(route('organization.payouts.update'), [
        'payout_business_name' => 'Grace Chapel', 'payout_type' => 'mobile_money', 'payout_bank_code' => 'MTN', 'payout_account_number' => '024 123 4567',
    ])->assertSessionHasNoErrors();

    expect($this->company->fresh())->payout_subaccount_code->toBe('ACCT_new')->payout_account_number->toBe('0241234567')->payout_bank_name->toBe('MTN');
    $sent = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/subaccount'))->last()[0];
    expect($sent['settlement_bank'])->toBe('MTN')->and($sent['percentage_charge'])->toBe(0);
});

it('needs a payout account before a form can collect payments', function () {
    $this->company->update(['payout_subaccount_code' => null]);
    $form = Form::create(['event_id' => $this->event->id, 'title' => 'Donations', 'is_open' => true]);

    $this->actingAs($this->manager)->get(route('events.forms.edit', [$this->event, $form]))->assertOk()->assertSee('Add your payout account first');
    $this->patch(route('events.forms.payment', [$this->event, $form]), ['payment_enabled' => 1, 'payment_amount' => '20'])
        ->assertSessionHasErrors('payment_enabled');
    expect($form->fresh()->payment_enabled)->toBeFalse();
    $this->get(route('forms.show', ['youth-camp', $form->slug]))->assertDontSee('Submit &amp; pay', false);
});

it('lets managers switch payment on, change the amount and switch it off', function () {
    $form = Form::create(['event_id' => $this->event->id, 'title' => 'Donations', 'is_open' => true]);

    $this->actingAs($this->manager)->patch(route('events.forms.payment', [$this->event, $form]), ['payment_enabled' => 1, 'payment_label' => 'Donation', 'payment_amount' => '20', 'payment_payer_sets_amount' => 1, 'payment_minimum' => '5'])
        ->assertSessionHasNoErrors();
    expect($form->fresh())->collectsPayment()->toBeTrue()->payment_amount_minor->toBe(2000)->payment_minimum_minor->toBe(500);

    $this->patch(route('events.forms.payment', [$this->event, $form]), ['payment_enabled' => 1, 'payment_amount' => '35'])->assertSessionHasNoErrors();
    expect($form->fresh()->payment_amount_minor)->toBe(3500);

    $this->patch(route('events.forms.payment', [$this->event, $form]), ['payment_enabled' => 0])->assertSessionHasNoErrors();
    expect($form->fresh()->collectsPayment())->toBeFalse();

    $this->patch(route('events.registration-form.payment', $this->event), ['payment_enabled' => 1, 'payment_amount' => '150'])->assertSessionHasNoErrors();
    expect($this->event->fresh()->registration_payment_amount_minor)->toBe(15000);
});

it('shows managers who has paid on the responses page and in the export', function () {
    $paid = FormResponse::create(['form_id' => $this->form->id, 'answers' => [], 'payment_status' => 'paid', 'amount_paid_minor' => 5000]);
    FormResponse::create(['form_id' => $this->form->id, 'answers' => [], 'payment_status' => 'unpaid']);

    $this->actingAs($this->manager)->get(route('events.forms.responses', [$this->event, $this->form]))->assertOk()
        ->assertSee('Collected')->assertSee('Paid · GH₵ 50.00')->assertSee('Not paid');
    $export = new FormResponsesExport($this->form);
    expect($export->headings())->toContain('Payment')->and($export->map($paid))->toContain('Paid', '50.00');
});

it('lets only admins switch the platform fee on and off', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $admin->assignRole('admin');

    $this->actingAs($this->manager)->put(route('integrations.attendee-fee'), ['fee_enabled' => 1, 'fee_percent' => 3])->assertForbidden();
    $this->actingAs($admin)->put(route('integrations.attendee-fee'), ['fee_enabled' => 1, 'fee_percent' => 2.5])->assertSessionHasNoErrors();
    expect(AttendeePaymentService::feeEnabled())->toBeTrue()->and(AttendeePaymentService::feePercent())->toBe(2.5)
        ->and(AttendeePaymentService::feeFor(10000))->toBe(250);

    $this->put(route('integrations.attendee-fee'), ['fee_enabled' => 0])->assertSessionHasNoErrors();
    expect(AttendeePaymentService::feeEnabled())->toBeFalse()->and(AttendeePaymentService::feeFor(10000))->toBe(0);
    $this->get(route('integrations.edit'))->assertOk()->assertSee('Platform fee on attendee payments');
});

it('lets people pick what they are paying for and charges that choice\'s price', function () {
    fakePaystack($this);
    $this->actingAs($this->manager)->patch(route('events.forms.payment', [$this->event, $this->form]), [
        'payment_enabled' => 1, 'payment_label' => 'Camp ticket', 'payment_options' => "Adult: 200\nStudent - 100\nChild: GH₵ 50.50",
    ])->assertSessionHasNoErrors();
    expect($this->form->fresh()->paymentOptions())->toBe([
        ['label' => 'Adult', 'amount_minor' => 20000], ['label' => 'Student', 'amount_minor' => 10000], ['label' => 'Child', 'amount_minor' => 5050],
    ]);
    auth()->logout();

    $this->get(route('forms.show', ['youth-camp', $this->form->slug]))->assertOk()->assertSee('Choose what you\'re paying for', false)->assertSee('Student')->assertSee('GH₵ 100.00');
    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com'])->assertSessionHasErrors('payment_option');
    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com', 'payment_option' => 'VIP'])->assertSessionHasErrors('payment_option');
    $this->post(route('forms.store', ['youth-camp', $this->form->slug]), ['payer_email' => 'kwame@example.com', 'payment_option' => 'Student'])->assertRedirect();

    $payment = AttendeePayment::sole();
    expect($payment->amount_minor)->toBe(10000)->and($payment->payment_option)->toBe('Student')
        ->and(initializeRequest()['amount'])->toBe(10000)->and(FormResponse::sole()->payment_option)->toBe('Student');

    $this->get(route('payments.return', $payment->reference));
    $this->actingAs($this->manager)->get(route('events.forms.responses', [$this->event, $this->form]))
        ->assertSee('Student: <span class="font-black">1</span> paid', false);
    expect((new FormResponsesExport($this->form))->map(FormResponse::sole()))->toContain('Student', '100.00');
});

it('rejects choices that are not written as a name and a price', function () {
    $this->actingAs($this->manager)->patch(route('events.forms.payment', [$this->event, $this->form]), ['payment_enabled' => 1, 'payment_options' => "Adult: 200\nStudent"])
        ->assertSessionHasErrors('payment_options');
    expect($this->form->fresh()->paymentOptions())->toBe([]);
});

it('charges a registration the price of the choice made, also when paying later', function () {
    fakePaystack($this);
    $this->event->update(['registration_payment_enabled' => true, 'registration_payment_options' => [['label' => 'Adult', 'amount_minor' => 20000], ['label' => 'Student', 'amount_minor' => 10000]]]);

    $this->post(route('events.register.store', $this->event), [
        'name' => 'Ama Mensah', 'email' => 'ama@example.com', 'phone' => '0201234567', 'gender' => 'Female', 'category' => 'Youth', 'consent' => 1, 'payment_option' => 'Student',
    ])->assertRedirect();
    $registration = EventRegistration::sole();
    expect($registration->payment_option)->toBe('Student')->and(AttendeePayment::sole()->amount_minor)->toBe(10000);

    AttendeePayment::query()->delete();
    $this->post(route('registrations.pay', $registration->management_token))->assertRedirect();
    expect(AttendeePayment::sole()->amount_minor)->toBe(10000)->and(AttendeePayment::sole()->payment_option)->toBe('Student');
});
