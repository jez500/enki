<?php

use App\Models\Skill;
use App\Models\User;

test('authenticated user can star a skill', function (): void {
    $user = User::factory()->create();
    $skill = Skill::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('enki.skill.star', $skill->slug));

    $response->assertOk()
        ->assertJson(['starred' => true]);

    $this->assertDatabaseHas('skill_user', [
        'skill_id' => $skill->id,
        'user_id' => $user->id,
    ]);
});

test('starring again un-stars the skill', function (): void {
    $user = User::factory()->create();
    $skill = Skill::factory()->create();

    $user->starredSkills()->attach($skill->id, ['starred_at' => now()]);

    $response = $this->actingAs($user)
        ->postJson(route('enki.skill.star', $skill->slug));

    $response->assertOk()
        ->assertJson(['starred' => false]);

    $this->assertDatabaseMissing('skill_user', [
        'skill_id' => $skill->id,
        'user_id' => $user->id,
    ]);
});

test('unauthenticated request returns 401', function (): void {
    $skill = Skill::factory()->create();

    $response = $this->postJson(route('enki.skill.star', $skill->slug));

    $response->assertUnauthorized();
});
