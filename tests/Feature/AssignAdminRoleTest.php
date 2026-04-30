<?php

use App\Enums\UserRole;
use App\Models\User;

test('assigns admin role to a user by email argument', function () {
    $user = User::factory()->create(['role' => UserRole::Member]);

    $this->artisan('user:assign-admin', ['email' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

test('reports success if user is already an admin', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    $this->artisan('user:assign-admin', ['email' => $user->email])
        ->expectsOutputToContain('already an admin')
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

test('fails with an error if email does not match any user', function () {
    $this->artisan('user:assign-admin', ['email' => 'nobody@example.com'])
        ->expectsOutputToContain('No user found')
        ->assertFailed();
});
