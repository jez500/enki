<?php

use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create(['api_token' => null]);
});

test('guests are redirected from the agent install page', function (): void {
    $this->get(route('help.agent-install'))->assertRedirect(route('login'));
});

test('authenticated users can view the agent install page', function (): void {
    $this->actingAs($this->user)
        ->get(route('help.agent-install'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('help/AgentInstall')
            ->where('apiToken', null)
            ->has('docs')
            ->has('appUrl')
            ->where('appName', 'enki')
            ->where('appSlug', 'enki')
        );
});

test('agent install page reflects the configured app name', function (): void {
    config(['app.name' => 'skillhound', 'app.machine_name' => null]);

    $this->actingAs($this->user)
        ->get(route('help.agent-install'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('appName', 'skillhound')
            ->where('appSlug', 'skillhound')
        );
});

test('users can generate an api token', function (): void {
    $this->actingAs($this->user)
        ->post(route('help.agent-install.token'))
        ->assertOk()
        ->assertJsonStructure(['api_token']);

    expect($this->user->fresh()->api_token)->not->toBeNull();
});

test('regenerating a token replaces the previous one', function (): void {
    $this->user->update(['api_token' => Str::random(64)]);
    $oldToken = $this->user->api_token;

    $this->actingAs($this->user)
        ->post(route('help.agent-install.token'));

    expect($this->user->fresh()->api_token)->not->toBe($oldToken);
});

test('api token is returned in the json response', function (): void {
    $response = $this->actingAs($this->user)
        ->post(route('help.agent-install.token'));

    $newToken = $this->user->fresh()->api_token;
    $response->assertJson(['api_token' => $newToken]);
});

test('api skill download requires a valid bearer token', function (): void {
    $this->getJson('/api/skills/enki/download')->assertUnauthorized();
});

test('api skill download rejects an invalid token', function (): void {
    $this->withToken('bad-token')
        ->getJson('/api/skills/enki/download')
        ->assertUnauthorized();
});

test('api skill download accepts a valid token', function (): void {
    $token = Str::random(64);
    User::factory()->create(['api_token' => $token]);

    // The enki skill is virtual — a valid token should get a 200, not a 401
    $this->withToken($token)
        ->get('/api/skills/enki/download')
        ->assertOk();
});
