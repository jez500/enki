<?php

use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('skill_data');
});

test('owner can update their skill metadata', function () {
    $user = User::factory()->create();
    Category::factory()->create(['slug' => 'writing', 'label' => 'Writing']);
    $skill = Skill::factory()->create([
        'created_by_user_id' => $user->id,
        'name' => 'Old Name',
        'summary' => 'Old summary',
        'visibility' => 'public',
    ]);

    $this->actingAs($user)
        ->postJson("/enki/skills/{$skill->slug}", [
            'name' => 'New Name',
            'category' => 'writing',
            'summary' => 'New summary',
            'tags' => 'a, b',
            'visibility' => 'private',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'New Name')
        ->assertJsonPath('summary', 'New summary')
        ->assertJsonPath('isPrivate', true);

    expect($skill->fresh()->name)->toBe('New Name')
        ->and($skill->fresh()->visibility)->toBe('private');
});

test('member cannot update someone else\'s skill', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Category::factory()->create(['slug' => 'writing', 'label' => 'Writing']);
    $skill = Skill::factory()->create(['created_by_user_id' => $owner->id]);

    $this->actingAs($other)
        ->postJson("/enki/skills/{$skill->slug}", [
            'name' => 'Hacked',
            'category' => 'writing',
            'visibility' => 'public',
        ])
        ->assertForbidden();

    expect($skill->fresh()->name)->not->toBe('Hacked');
});

test('admin can update any skill', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    Category::factory()->create(['slug' => 'writing', 'label' => 'Writing']);
    $skill = Skill::factory()->create(['created_by_user_id' => $owner->id, 'name' => 'Old']);

    $this->actingAs($admin)
        ->postJson("/enki/skills/{$skill->slug}", [
            'name' => 'Admin Edited',
            'category' => 'writing',
            'visibility' => 'public',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Admin Edited');
});

test('owner can delete their skill', function () {
    $user = User::factory()->create();
    $skill = Skill::factory()->create(['created_by_user_id' => $user->id]);

    $this->actingAs($user)
        ->deleteJson("/enki/skills/{$skill->slug}")
        ->assertOk()
        ->assertJsonPath('deleted', true);

    expect(Skill::withTrashed()->where('id', $skill->id)->exists())->toBeFalse();
});

test('member cannot delete someone else\'s skill', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $skill = Skill::factory()->create(['created_by_user_id' => $owner->id]);

    $this->actingAs($other)
        ->deleteJson("/enki/skills/{$skill->slug}")
        ->assertForbidden();

    expect(Skill::where('id', $skill->id)->exists())->toBeTrue();
});

test('admin can delete any skill', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $skill = Skill::factory()->create(['created_by_user_id' => $owner->id]);

    $this->actingAs($admin)
        ->deleteJson("/enki/skills/{$skill->slug}")
        ->assertOk();

    expect(Skill::where('id', $skill->id)->exists())->toBeFalse();
});

test('canEdit flag is true for skill owner', function () {
    $user = User::factory()->create();
    Skill::factory()->create(['created_by_user_id' => $user->id, 'visibility' => 'public']);

    $this->actingAs($user)
        ->get('/enki')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedSkill.canEdit', true));
});

test('canEdit flag is false for non-owner non-admin', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    Skill::factory()->create(['created_by_user_id' => $owner->id, 'visibility' => 'public']);

    $this->actingAs($other)
        ->get('/enki')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedSkill.canEdit', false));
});

test('canEdit flag is true for admin viewing other user\'s skill', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    Skill::factory()->create(['created_by_user_id' => $owner->id, 'visibility' => 'public']);

    $this->actingAs($admin)
        ->get('/enki')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedSkill.canEdit', true));
});
