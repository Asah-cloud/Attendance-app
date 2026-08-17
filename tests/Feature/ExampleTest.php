<?php

it('shows the public landing page', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Asah Apex')
        ->assertSee('Know who showed up');
});

it('shows the public pricing page', function () {
    $this->get('/pricing')
        ->assertOk()
        ->assertSee('Straightforward pricing')
        ->assertSee('Business');
});
