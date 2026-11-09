<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the library page shares the persona name and avatar with the shell', function () {
    $user = User::factory()->create([
        'persona_name' => 'Ada',
        'avatar_url' => 'https://avatars.example.com/ada.jpg',
    ]);

    $this->actingAs($user)
        ->get(route('library'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Library')
            ->where('auth.user.persona_name', 'Ada')
            ->where('auth.user.avatar_url', 'https://avatars.example.com/ada.jpg'));
});

test('the SteamID64 is not shared with the front end', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('library'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('auth.user.steam_id')
            ->missing('sidebarOpen'));
});
