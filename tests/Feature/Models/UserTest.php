<?php

use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('steam_id is a string in arrays and json', function () {
    $user = User::factory()->create();

    expect($user->toArray()['steam_id'])->toBeString()
        ->and(json_decode($user->toJson(), true)['steam_id'])->toBeString();
});

test('a 17-digit steam id survives a save and reload exactly', function () {
    $user = User::factory()->create(['steam_id' => '76561197960287930']);

    expect($user->fresh()->steam_id)->toBe('76561197960287930')
        ->and(User::query()->where('steam_id', '76561197960287930')->exists())->toBeTrue();
});

test('steam_id is unique', function () {
    User::factory()->create(['steam_id' => '76561197960287930']);

    expect(fn () => User::factory()->create(['steam_id' => '76561197960287930']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('enums default to unknown and idle', function () {
    $user = User::query()->create(['steam_id' => '76561197960287931', 'persona_name' => 'Someone'])->fresh();

    expect($user->library_visibility)->toBe(LibraryVisibility::Unknown)
        ->and($user->import_status)->toBe(ImportStatus::Idle);
});

test('the private library state sets visibility', function () {
    expect(User::factory()->privateLibrary()->create()->library_visibility)->toBe(LibraryVisibility::Private);
});

test('the remember token is hidden', function () {
    expect(User::factory()->create()->toArray())->not->toHaveKey('remember_token');
});
