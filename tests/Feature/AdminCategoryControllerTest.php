<?php

use App\Models\Category;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

test('non-admins cannot access admin categories page', function (): void {
    $this->actingAs(User::factory()->create())->get('/enki/admin/categories')->assertForbidden();
});

test('guests are redirected from admin categories page', function (): void {
    $this->get('/enki/admin/categories')->assertRedirect(route('login'));
});

test('admins can view the categories list', function (): void {
    $admin = User::factory()->admin()->create();
    Category::factory()->create(['label' => 'Writing']);

    $this->actingAs($admin)
        ->get('/enki/admin/categories')
        ->assertOk()
        ->assertInertia(fn (Assert $page): AssertableInertia => $page
            ->component('enki/AdminCategories')
            ->has('categories', 1)
            ->has('categories.0', fn (Assert $c): AssertableInertia => $c
                ->where('label', 'Writing')
                ->has('id')
                ->has('slug')
                ->has('icon')
                ->has('color')
                ->has('skillsCount')
            )
        );
});

test('admin can create a category', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/enki/admin/categories', [
            'slug' => 'coding',
            'label' => 'Coding',
            'icon' => '💻',
            'color' => ['bg' => '#e0f2fe', 'fg' => '#0369a1'],
        ])
        ->assertRedirect();

    expect(Category::where('slug', 'coding')->exists())->toBeTrue();
});

test('admin can update a category', function (): void {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['label' => 'Old Label']);

    $this->actingAs($admin)
        ->patch('/enki/admin/categories/'.$category->id, [
            'slug' => $category->slug,
            'label' => 'New Label',
        ])
        ->assertRedirect();

    expect($category->fresh()->label)->toBe('New Label');
});

test('admin can delete a category', function (): void {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();

    $this->actingAs($admin)
        ->delete('/enki/admin/categories/'.$category->id)
        ->assertRedirect();

    expect(Category::find($category->id))->toBeNull();
});

test('non-admins cannot create categories', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/enki/admin/categories', ['slug' => 'test', 'label' => 'Test'])
        ->assertForbidden();
});

test('non-admins cannot delete categories', function (): void {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($user)
        ->delete('/enki/admin/categories/'.$category->id)
        ->assertForbidden();
});

test('store validates required fields', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/enki/admin/categories', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug', 'label']);
});

test('slug must be unique on create', function (): void {
    $admin = User::factory()->admin()->create();
    Category::factory()->create(['slug' => 'existing']);

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/enki/admin/categories', ['slug' => 'existing', 'label' => 'Existing'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
});
