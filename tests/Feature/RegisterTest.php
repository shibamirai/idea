<?php

use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\assertAuthenticated;

it('registers a user', function () {
    visit('/register')
        ->fill('name', 'John Doe')
        ->fill('email', 'john@example.com')
        ->fill('password', 'password123!@#')
        ->click('Create Account')
        ->assertPathIs('/');

    assertAuthenticated();

    expect(Auth::user())->toMatchArray([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

it('requires a valid email', function () {
    visit('/register')
        ->fill('name', 'John Doe')
        ->fill('email', 'john123')
        ->fill('password', 'password123!@#')
        ->click('Create Account')
        ->assertPathIs('/register');
});
