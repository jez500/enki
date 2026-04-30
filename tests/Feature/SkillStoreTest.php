<?php

use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use App\Services\SkillArchiveParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('skill_data');
});

// ── SkillArchiveParser unit tests ─────────────────────────────────────────────

test('parser rejects archive without SKILL.md', function () {
    $zip = makeTestZip(['readme.txt' => 'hello']);

    expect(fn () => app(SkillArchiveParser::class)->parse($zip))
        ->toThrow(InvalidArgumentException::class, 'SKILL.md');
});

test('parser extracts name summary version tags and files from skill.yaml', function () {
    $zip = makeTestZip([
        'SKILL.md'   => "# Hello\n\nContent here.",
        'skill.yaml' => "name: My Skill\nsummary: Does things\nversion: 2.0.0\ntags:\n  - writing\n",
        'prompt.txt' => 'Be helpful.',
    ]);

    $result = app(SkillArchiveParser::class)->parse($zip);

    expect($result['name'])->toBe('My Skill')
        ->and($result['summary'])->toBe('Does things')
        ->and($result['version'])->toBe('2.0.0')
        ->and($result['tags'])->toBe(['writing'])
        ->and($result['readme'])->toContain('Content here.')
        ->and(array_column($result['files'], 'path'))->toContain('prompt.txt');
});

test('parser falls back to frontmatter when no skill.yaml', function () {
    $zip = makeTestZip([
        'SKILL.md' => "---\nname: Frontmatter Skill\ndescription: From frontmatter\n---\n\n# Body",
    ]);

    $result = app(SkillArchiveParser::class)->parse($zip);

    expect($result['name'])->toBe('Frontmatter Skill')
        ->and($result['summary'])->toBe('From frontmatter');
});

test('parser unwraps single top-level directory', function () {
    $zip = makeTestZip([
        'my-skill/SKILL.md'   => '# Wrapped',
        'my-skill/skill.yaml' => "name: Wrapped Skill\nsummary: inside\nversion: 1.0.0\n",
    ]);

    $result = app(SkillArchiveParser::class)->parse($zip);

    expect($result['name'])->toBe('Wrapped Skill');
});

// ── Store endpoint tests ──────────────────────────────────────────────────────

test('store endpoint requires authentication', function () {
    $this->postJson('/enki/skills', ['name' => 'Test', 'category' => 'writing', 'visibility' => 'public'])
        ->assertUnauthorized();
});

test('store creates skill with archive', function () {
    $user = User::factory()->create();
    Category::factory()->create(['slug' => 'writing', 'label' => 'Writing']);

    $zip = makeTestZip([
        'SKILL.md'   => "# My New Skill\n\nContent.",
        'skill.yaml' => "name: My New Skill\nsummary: Does great things\nversion: 1.0.0\ntags:\n  - writing\n  - tools\n",
    ]);

    $this->actingAs($user)
        ->call('POST', '/enki/skills', [
            'name'       => 'My New Skill',
            'category'   => 'writing',
            'visibility' => 'public',
        ], [], ['archive' => $zip], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('name', 'My New Skill')
        ->assertJsonPath('isPrivate', false);

    expect(Skill::where('name', 'My New Skill')->exists())->toBeTrue();
});

test('store auto-generates slug from name', function () {
    $user = User::factory()->create();
    Category::factory()->create(['slug' => 'writing', 'label' => 'Writing']);

    $zip = makeTestZip([
        'SKILL.md'   => "# Auto Slug Skill\n\nContent.",
        'skill.yaml' => "name: Auto Slug Skill\nsummary: Slugged\nversion: 1.0.0\n",
    ]);

    $response = $this->actingAs($user)
        ->call('POST', '/enki/skills', [
            'name'       => 'Auto Slug Skill',
            'category'   => 'writing',
            'visibility' => 'public',
        ], [], ['archive' => $zip], ['Accept' => 'application/json'])
        ->assertOk();

    expect($response->json('slug'))->toBe('auto-slug-skill');
});

test('store creates private skill', function () {
    $user = User::factory()->create();
    Category::factory()->create(['slug' => 'writing', 'label' => 'Writing']);

    $zip = makeTestZip([
        'SKILL.md'   => "# Secret Skill\n\nContent.",
        'skill.yaml' => "name: Secret Skill\nsummary: Private\nversion: 1.0.0\n",
    ]);

    $this->actingAs($user)
        ->call('POST', '/enki/skills', [
            'name'       => 'Secret Skill',
            'category'   => 'writing',
            'visibility' => 'private',
        ], [], ['archive' => $zip], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('isPrivate', true);
});

test('store with valid archive writes files to filesystem', function () {
    $user = User::factory()->create();
    Category::factory()->create(['slug' => 'writing', 'label' => 'Writing']);

    $zip = makeTestZip([
        'SKILL.md'   => "# Test Skill\n\nContent.",
        'skill.yaml' => "name: Archive Skill\nsummary: From zip\nversion: 1.0.0\n",
        'prompt.txt' => 'Be helpful.',
    ]);

    $this->actingAs($user)
        ->call('POST', '/enki/skills', [
            'name'       => 'Archive Skill',
            'category'   => 'writing',
            'visibility' => 'public',
        ], [], ['archive' => $zip], ['Accept' => 'application/json'])
        ->assertOk();

    $skill = Skill::where('name', 'Archive Skill')->first();
    expect($skill)->not->toBeNull();
    expect($skill->getContent()->read('prompt.txt'))->toBe('Be helpful.');
});

test('store rejects archive without SKILL.md', function () {
    $user = User::factory()->create();
    Category::factory()->create(['slug' => 'writing', 'label' => 'Writing']);

    $zip = makeTestZip(['notes.txt' => 'no skill here']);

    $this->actingAs($user)
        ->call('POST', '/enki/skills', [
            'name'       => 'Bad Skill',
            'category'   => 'writing',
            'visibility' => 'public',
        ], [], ['archive' => $zip], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($v) => str_contains($v, 'SKILL.md'));
});

// ── Helper ────────────────────────────────────────────────────────────────────

function makeTestZip(array $files): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'enki-test-').'.zip';
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::CREATE);
    foreach ($files as $path => $content) {
        $parts = explode('/', $path);
        for ($i = 1; $i < count($parts); $i++) {
            $dir = implode('/', array_slice($parts, 0, $i));
            if ($zip->locateName($dir.'/') === false) {
                $zip->addEmptyDir($dir);
            }
        }
        $zip->addFromString($path, $content);
    }
    $zip->close();

    return new UploadedFile($tmp, 'skill.zip', 'application/zip', null, true);
}
