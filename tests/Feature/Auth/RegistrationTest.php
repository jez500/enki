<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::registration());
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
});

test('registration screen can be rendered', function (): void {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function (): void {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('enki', absolute: false));
});

test('first registered user is assigned the admin role', function (): void {
    $this->post(route('register.store'), [
        'name' => 'First User',
        'email' => 'first@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'first@example.com')->first()->role)->toBe(UserRole::Admin);
});

test('subsequent registered users are assigned the member role', function (): void {
    User::factory()->admin()->create();

    $this->post(route('register.store'), [
        'name' => 'Second User',
        'email' => 'second@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(User::where('email', 'second@example.com')->first()->role)->toBe(UserRole::Member);
});
