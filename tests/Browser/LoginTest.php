<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;

it('logs in a user', function () {
    $user = User::factory()->create(['password' => 'password123!@#']);

    visit('/login')
        ->fill('email', $user->email)
        ->fill('password', 'password123!@#')
        ->click('@login-button')
        ->assertRoute('idea.index');

    assertAuthenticated();
});

it('logs out a user', function () {
    /** @var User */
    $user = User::factory()->create();

    actingAs($user);

    visit('/')->click('Log Out');

    assertGuest();
});
