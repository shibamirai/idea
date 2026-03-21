<?php

use App\Models\Idea;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('requires authentication', function () {
    $idea = Idea::factory()->create();

    $this->get(route('idea.show', $idea))->assertRedirectToRoute('login');
});

it('disallows accessing an idea you did not create', function () {
    $user = User::factory()->create();

    actingAs($user);

    $idea = Idea::factory()->create();

    $this->get(route('idea.show', $idea))->assertForbidden();
});
