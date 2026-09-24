<?php

use App\Services\ResendDomainService;
use Resend\Laravel\Facades\Resend;

beforeEach(function () {
    config(['services.resend.key' => 're_test_key']);
});

it('adopts a domain that already exists in the Resend account instead of failing', function () {
    $domains = Mockery::mock();
    $domains->shouldReceive('create')->once()->andThrow(new RuntimeException('The tacmail.org domain has been registered already.'));
    $domains->shouldReceive('list')->once()->andReturn((object) ['data' => [
        (object) ['id' => 'other-id', 'name' => 'other.org'],
        (object) ['id' => 'dom_123', 'name' => 'TacMail.org'],
    ]]);
    $domains->shouldReceive('get')->with('dom_123')->once()->andReturn((object) [
        'id' => 'dom_123',
        'name' => 'tacmail.org',
        'status' => 'pending',
        'records' => [['record' => 'DKIM', 'type' => 'TXT']],
    ]);
    Resend::shouldReceive('domains')->andReturn($domains);

    $result = app(ResendDomainService::class)->create('tacmail.org');

    expect($result['id'])->toBe('dom_123')
        ->and($result['status'])->toBe('pending')
        ->and($result['records'])->toHaveCount(1);
});

it('does not restart verification while Resend is still checking a pending domain', function () {
    $domains = Mockery::mock();
    $domains->shouldReceive('get')->with('dom_123')->once()->andReturn((object) ['id' => 'dom_123', 'name' => 'tacmail.org', 'status' => 'pending', 'records' => []]);
    $domains->shouldReceive('verify')->never();
    Resend::shouldReceive('domains')->andReturn($domains);

    expect(app(ResendDomainService::class)->checkVerification('dom_123')['status'])->toBe('pending');
});

it('restarts verification once Resend reports a failed check', function () {
    $domains = Mockery::mock();
    $domains->shouldReceive('get')->with('dom_123')->twice()->andReturn((object) ['id' => 'dom_123', 'name' => 'tacmail.org', 'status' => 'failed', 'records' => []]);
    $domains->shouldReceive('verify')->with('dom_123')->once();
    Resend::shouldReceive('domains')->andReturn($domains);

    expect(app(ResendDomainService::class)->checkVerification('dom_123')['status'])->toBe('failed');
});

it('still fails when creation fails and the domain is not in the account', function () {
    $domains = Mockery::mock();
    $domains->shouldReceive('create')->once()->andThrow(new RuntimeException('Resend is down'));
    $domains->shouldReceive('list')->once()->andReturn((object) ['data' => [(object) ['id' => 'other-id', 'name' => 'other.org']]]);
    Resend::shouldReceive('domains')->andReturn($domains);

    app(ResendDomainService::class)->create('tacmail.org');
})->throws(RuntimeException::class, 'Resend is down');
