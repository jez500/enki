<?php

use App\Models\Author;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

// --- Web listing ---

test('virtual enki skill appears in the web listing', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('skills.data', 1)
            ->where('skills.data.0.slug', 'enki')
            ->where('skills.data.0.author', 'laravel-boost')
        );
});

test('virtual enki skill is counted in skill counts', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skillCounts.all', 1)
        );
});

test('virtual enki skill appears in search results when query matches', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['q' => 'enki']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.total', 1)
            ->where('skills.data.0.slug', 'enki')
        );
});

test('virtual enki skill is excluded when query does not match', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['q' => 'xyzzy-no-match']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.total', 0)
        );
});

test('virtual enki skill is excluded when filtered by a category', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['category' => 'coding']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.total', 0)
        );
});

test('virtual enki skill is excluded when starred filter is active', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['starred' => true]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.total', 0)
        );
});

test('virtual enki skill is excluded when mySkills filter is active', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['mySkills' => true]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.total', 0)
        );
});

test('virtual enki skill is excluded when source is external', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['source' => 'external']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.total', 0)
        );
});

test('virtual enki skill appears when source is internal', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['source' => 'internal']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.data.0.slug', 'enki')
        );
});

test('laravel-boost author is injected into authors prop', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('authors.laravel-boost')
            ->where('authors.laravel-boost.name', 'Laravel Boost')
        );
});

// --- Web skill page ---

test('enki skill page resolves without a database record', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki.skill', ['slug' => 'enki']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedSkill.slug', 'enki')
            ->has('selectedSkill.readmeHtml')
            ->has('selectedSkill.usageHtml')
        );
});

test('enki skill page includes a SKILL.md in the files list', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('enki.skill', ['slug' => 'enki']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedSkill.files.0.path', 'SKILL.md')
        );
});

// --- Web download ---

test('enki skill download returns a zip without a database record', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('enki.skill.download', ['slug' => 'enki']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/zip');
    expect($response->headers->get('content-disposition'))->toContain('enki.zip');
});

test('enki skill download zip contains SKILL.md', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('enki.skill.download', ['slug' => 'enki']));
    $response->assertOk();

    $tmp = tempnam(sys_get_temp_dir(), 'enki-test-');
    file_put_contents($tmp, $response->streamedContent() ?: $response->getContent());

    $zip = new \ZipArchive;
    expect($zip->open($tmp))->toBeTrue();
    expect($zip->locateName('SKILL.md'))->not->toBeFalse();
    $zip->close();
    @unlink($tmp);
});

// --- API ---

test('api skills index includes virtual enki skill', function () {
    $token = Str::random(64);
    User::factory()->create(['api_token' => $token]);

    $response = $this->withToken($token)->getJson('/api/skills')->assertOk();

    $slugs = collect($response->json('data'))->pluck('slug');
    expect($slugs)->toContain('enki');
});

test('api skills show returns virtual enki skill', function () {
    $token = Str::random(64);
    User::factory()->create(['api_token' => $token]);

    $this->withToken($token)
        ->getJson('/api/skills/enki')
        ->assertOk()
        ->assertJsonPath('slug', 'enki')
        ->assertJsonStructure(['readme', 'usage']);
});

test('api skills download returns zip for virtual enki skill', function () {
    $token = Str::random(64);
    User::factory()->create(['api_token' => $token]);

    $this->withToken($token)
        ->get('/api/skills/enki/download')
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

test('api skills index total is incremented for virtual enki skill', function () {
    $token = Str::random(64);
    User::factory()->create(['api_token' => $token]);

    $author = Author::factory()->create();
    Skill::factory()->for($author)->count(2)->create();

    $this->withToken($token)
        ->getJson('/api/skills')
        ->assertOk()
        ->assertJsonPath('total', 3); // 2 real + 1 virtual
});
