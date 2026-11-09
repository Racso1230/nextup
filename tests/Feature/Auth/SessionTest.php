<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests see the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
});

test('guests are redirected from the dashboard to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can log out', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

test('guests cannot log out', function () {
    $this->post(route('logout'))->assertRedirect(route('login'));

    $this->assertGuest();
});
