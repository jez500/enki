<?php

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

test('non-admins cannot access admin users page', function (): void {
    $this->actingAs(User::factory()->create())->get('/enki/admin/users')->assertForbidden();
});

test('guests are redirected from admin users page', function (): void {
    $this->get('/enki/admin/users')->assertRedirect(route('login'));
});

test('admins can view the users list', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Alice Example', 'email' => 'alice@example.com']);

    $this->actingAs($admin)
        ->get('/enki/admin/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page): AssertableInertia => $page
            ->component('enki/Admin')
            ->has('users', 2)
            ->has('roles')
        );
});

test('admin can update a user role', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['role' => UserRole::Member]);

    $this->actingAs($admin)
        ->patch(sprintf('/enki/admin/users/%s/role', $user->id), ['role' => 'admin'])
        ->assertRedirect();

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

test('non-admins cannot update user roles', function (): void {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->patch(sprintf('/enki/admin/users/%s/role', $target->id), ['role' => 'admin'])
        ->assertForbidden();
});

test('admin can delete a user', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete('/enki/admin/users/'.$user->id)
        ->assertRedirect();

    expect(User::find($user->id))->toBeNull();
});

test('admin cannot delete themselves', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->delete('/enki/admin/users/'.$admin->id)
        ->assertForbidden();
});

test('non-admins cannot delete users', function (): void {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->delete('/enki/admin/users/'.$target->id)
        ->assertForbidden();
});

test('role update validates input', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->patch(sprintf('/enki/admin/users/%s/role', $user->id), ['role' => 'superuser'])
        ->assertStatus(422);
});
