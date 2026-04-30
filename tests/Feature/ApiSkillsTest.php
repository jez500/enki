<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->token = Str::random(64);
    $this->user = User::factory()->create(['api_token' => $this->token]);
});

// GET /api/skills

test('api skills index requires a valid token', function () {
    $this->getJson('/api/skills')->assertUnauthorized();
});

test('api skills index returns paginated skills', function () {
    $author = Author::factory()->create();
    Skill::factory()->for($author)->create(['slug' => 'coding/skill-a', 'name' => 'Skill A']);

    $this->withToken($this->token)
        ->getJson('/api/skills')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['slug', 'name', 'summary', 'categories', 'author', 'version', 'updatedAt', 'ratings', 'tags', 'installs', 'isExternal']],
            'current_page', 'last_page', 'per_page', 'total',
        ])
        // Virtual enki skill is appended after real skills
        ->assertJsonPath('data.0.slug', 'coding/skill-a')
        ->assertJsonPath('data.1.slug', 'enki');
});

test('api skills index filters by search query', function () {
    $author = Author::factory()->create();
    Skill::factory()->for($author)->create(['slug' => 'coding/match', 'name' => 'Matching Skill']);
    Skill::factory()->for($author)->create(['slug' => 'coding/other', 'name' => 'Other Skill']);

    $this->withToken($this->token)
        ->getJson('/api/skills?q=matching')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.slug', 'coding/match');
});

test('api skills index filters by category', function () {
    $this->seed(CategorySeeder::class);
    $author = Author::factory()->create();
    $coding = Category::where('slug', 'coding')->first();

    $inCategory = Skill::factory()->for($author)->create(['slug' => 'coding/in-cat']);
    $inCategory->categories()->attach($coding);
    Skill::factory()->for($author)->create(['slug' => 'research/out-cat']);

    $this->withToken($this->token)
        ->getJson('/api/skills?category=coding')
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.slug', 'coding/in-cat');
});

test('api skills index does not expose private skills from other users', function () {
    $author = Author::factory()->create();
    $otherUser = User::factory()->create(['api_token' => null]);
    Skill::factory()->for($author)->create([
        'slug' => 'private/secret',
        'visibility' => 'private',
        'created_by_user_id' => $otherUser->id,
    ]);

    $response = $this->withToken($this->token)->getJson('/api/skills')->assertOk();

    $slugs = collect($response->json('data'))->pluck('slug');
    expect($slugs)->not->toContain('private/secret');
});

// GET /api/skills/{slug}

test('api skills show requires a valid token', function () {
    $this->getJson('/api/skills/coding/some-skill')->assertUnauthorized();
});

test('api skills show returns skill details with readme and usage', function () {
    $author = Author::factory()->create();
    Skill::factory()->for($author)->create([
        'slug' => 'coding/detail-skill',
        'readme' => "---\nname: detail-skill\n---\n# Hello",
        'usage' => 'Use it like this.',
    ]);

    $this->withToken($this->token)
        ->getJson('/api/skills/coding/detail-skill')
        ->assertOk()
        ->assertJsonStructure(['slug', 'name', 'readme', 'usage'])
        ->assertJsonPath('slug', 'coding/detail-skill')
        ->assertJsonPath('readme', '# Hello')
        ->assertJsonPath('usage', 'Use it like this.');
});

test('api skills show returns 404 for unknown slug', function () {
    $this->withToken($this->token)
        ->getJson('/api/skills/does/not/exist')
        ->assertNotFound();
});

test('api skills show does not expose private skills from other users', function () {
    $author = Author::factory()->create();
    $otherUser = User::factory()->create(['api_token' => null]);
    Skill::factory()->for($author)->create([
        'slug' => 'private/owned-by-other',
        'visibility' => 'private',
        'created_by_user_id' => $otherUser->id,
    ]);

    $this->withToken($this->token)
        ->getJson('/api/skills/private/owned-by-other')
        ->assertNotFound();
});

// GET /api/categories

test('api categories requires a valid token', function () {
    $this->getJson('/api/categories')->assertUnauthorized();
});

test('api categories returns all categories', function () {
    $this->seed(CategorySeeder::class);

    $this->withToken($this->token)
        ->getJson('/api/categories')
        ->assertOk()
        ->assertJsonStructure([['id', 'label']]);
});
