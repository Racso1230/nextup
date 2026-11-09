<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the old dashboard url redirects signed-in users to the library', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('library'));
});
