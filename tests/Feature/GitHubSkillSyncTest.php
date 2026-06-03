<?php

use App\Models\Author;
use App\Models\Skill;
use App\Models\User;
use App\Services\GitHubSkillSync;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('skill_data');
});

function fakeCommits(): array
{
    return [
        ['commit' => ['committer' => ['date' => '2026-04-15T10:00:00Z']]],
    ];
}

/**
 * Build a zip in memory containing the given $files (path => content) under $topDir.
 * Returns the raw zip bytes.
 *
 * @param  array<string, string>  $files
 */
function fakeRepoZip(string $topDir, array $files): string
{
    $tmpZip = tempnam(sys_get_temp_dir(), 'enki-test-');
    $zip = new ZipArchive;
    $zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $path => $content) {
        $zip->addFromString(sprintf('%s/%s', $topDir, $path), $content);
    }

    $zip->close();

    $bytes = file_get_contents($tmpZip);
    unlink($tmpZip);

    return $bytes;
}

function fakeSkillFiles(): array
{
    return [
        'skills/my-skill/SKILL.md' => "# My Skill\n\nThis is the readme content.",
        'skills/my-skill/skill.yaml' => "name: My Skill\nsummary: Does cool things\nversion: 2.1.0\ntags:\n  - writing\n  - tools\n",
        'skills/my-skill/prompt.txt' => 'file content',
        'skills/my-skill/agents/researcher.md' => 'agent file',
        'skills/my-skill/agents/writer.md' => 'agent file',
    ];
}

function stubGitHubApis(): void
{
    Http::fake([
        'https://github.com/anthropics/skills/archive/refs/heads/main.zip' => Http::response(
            fakeRepoZip('skills-main', fakeSkillFiles()),
            200,
            ['Content-Type' => 'application/zip'],
        ),
        'https://api.github.com/repos/anthropics/skills/commits*' => Http::response(fakeCommits()),
    ]);
}

test('parseUrl extracts owner repo branch path', function (): void {
    $service = app(GitHubSkillSync::class);
    $parts = $service->parseUrl('https://github.com/anthropics/skills/tree/main/skills/my-skill');

    expect($parts)->toBe([
        'owner' => 'anthropics',
        'repo' => 'skills',
        'branch' => 'main',
        'path' => 'skills/my-skill',
    ]);
});

test('parseUrl extracts owner repo branch with empty path for repo root url', function (): void {
    $service = app(GitHubSkillSync::class);
    $parts = $service->parseUrl('https://github.com/alchaincyf/huashu-design/tree/master');

    expect($parts)->toBe([
        'owner' => 'alchaincyf',
        'repo' => 'huashu-design',
        'branch' => 'master',
        'path' => '',
    ]);
});

test('parseUrl accepts repo root url with trailing slash', function (): void {
    $service = app(GitHubSkillSync::class);
    $parts = $service->parseUrl('https://github.com/alchaincyf/huashu-design/tree/master/');

    expect($parts['path'])->toBe('');
});

test('parseUrl extracts owner repo from bare repo url with empty branch and path', function (): void {
    $service = app(GitHubSkillSync::class);
    $parts = $service->parseUrl('https://github.com/henricook/claude-glab-skill');

    expect($parts)->toBe([
        'owner' => 'henricook',
        'repo' => 'claude-glab-skill',
        'branch' => '',
        'path' => '',
    ]);
});

test('parseUrl strips .git suffix and trailing slash from bare repo url', function (string $url): void {
    $parts = app(GitHubSkillSync::class)->parseUrl($url);

    expect($parts['repo'])->toBe('claude-glab-skill')
        ->and($parts['branch'])->toBe('')
        ->and($parts['path'])->toBe('');
})->with([
    'https://github.com/henricook/claude-glab-skill.git',
    'https://github.com/henricook/claude-glab-skill/',
]);

test('parseUrl rejects invalid url', function (): void {
    expect(fn () => app(GitHubSkillSync::class)->parseUrl('https://github.com/anthropics'))
        ->toThrow(InvalidArgumentException::class);
});

test('import creates skill and files from github', function (): void {
    stubGitHubApis();

    $url = 'https://github.com/anthropics/skills/tree/main/skills/my-skill';
    $skill = app(GitHubSkillSync::class)->import($url);

    expect($skill->name)->toBe('My Skill')
        ->and($skill->summary)->toBe('Does cool things')
        ->and($skill->version)->toBe('2.1.0')
        ->and($skill->tags)->toBe(['writing', 'tools'])
        ->and($skill->github_url)->toBe($url)
        ->and($skill->readme)->toContain('This is the readme content.');

    expect(Author::where('slug', 'anthropics')->exists())->toBeTrue();

    $content = $skill->getContent();
    $files = $content->files();
    expect($files)->toHaveCount(5); // 3 root files + 2 files in agents/
    $paths = array_column($files, 'path');
    expect($paths)->toContain('SKILL.md')
        ->toContain('agents/researcher.md')
        ->toContain('agents/writer.md');
    expect($content->read('prompt.txt'))->toBe('file content');
    expect($content->read('agents/researcher.md'))->toBe('agent file');
});

test('import from repo root url uses repo name as slug', function (): void {
    Http::fake([
        'https://github.com/acme/my-repo/archive/refs/heads/main.zip' => Http::response(
            fakeRepoZip('my-repo-main', [
                'SKILL.md' => "# Root Skill\n\nRoot readme.",
                'skill.yaml' => "name: Root Skill\nsummary: A root skill\nversion: 1.0.0\ntags: []\n",
            ]),
            200,
            ['Content-Type' => 'application/zip'],
        ),
        'https://api.github.com/repos/acme/my-repo/commits*' => Http::response(fakeCommits()),
    ]);

    $skill = app(GitHubSkillSync::class)->import('https://github.com/acme/my-repo/tree/main');

    expect($skill->slug)->toBe('my-repo')
        ->and($skill->name)->toBe('Root Skill');
});

test('import from bare repo url resolves the default branch', function (): void {
    Http::fake([
        'https://api.github.com/repos/henricook/claude-glab-skill' => Http::response(['default_branch' => 'main']),
        'https://github.com/henricook/claude-glab-skill/archive/refs/heads/main.zip' => Http::response(
            fakeRepoZip('claude-glab-skill-main', [
                'SKILL.md' => "---\nname: Glab Skill\ndescription: GitLab helpers\n---\n\nReadme body.",
            ]),
            200,
            ['Content-Type' => 'application/zip'],
        ),
        'https://api.github.com/repos/henricook/claude-glab-skill/commits*' => Http::response(fakeCommits()),
    ]);

    $skill = app(GitHubSkillSync::class)->import('https://github.com/henricook/claude-glab-skill');

    expect($skill->slug)->toBe('claude-glab-skill')
        ->and($skill->name)->toBe('Glab Skill')
        ->and($skill->summary)->toBe('GitLab helpers')
        ->and($skill->github_url)->toBe('https://github.com/henricook/claude-glab-skill');
});

test('sync updates existing external skill', function (): void {
    stubGitHubApis();

    $url = 'https://github.com/anthropics/skills/tree/main/skills/my-skill';
    $skill = Skill::factory()->create(['github_url' => $url, 'name' => 'Old Name', 'version' => '1.0.0']);

    app(GitHubSkillSync::class)->sync($skill);

    $skill->refresh();
    expect($skill->name)->toBe('My Skill')
        ->and($skill->version)->toBe('2.1.0');
});

test('import endpoint requires authentication', function (): void {
    $this->postJson('/enki/skills/import', ['github_url' => 'https://github.com/a/b/tree/main/c'])
        ->assertUnauthorized();
});

test('import endpoint validates github url format', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/enki/skills/import', ['github_url' => 'https://example.com/not-github'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('github_url');
});

test('import endpoint calls sync service and returns skill', function (): void {
    stubGitHubApis();

    $user = User::factory()->create();
    $url = 'https://github.com/anthropics/skills/tree/main/skills/my-skill';

    $response = $this->actingAs($user)
        ->postJson('/enki/skills/import', ['github_url' => $url]);

    $response->assertOk()
        ->assertJsonPath('name', 'My Skill')
        ->assertJsonPath('version', '2.1.0')
        ->assertJsonPath('isExternal', true)
        ->assertJsonPath('githubUrl', $url);
});

test('sync endpoint updates and returns skill', function (): void {
    stubGitHubApis();

    $user = User::factory()->create();
    $url = 'https://github.com/anthropics/skills/tree/main/skills/my-skill';
    $skill = Skill::factory()->create([
        'github_url' => $url,
        'slug' => 'my-skill',
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson(sprintf('/enki/skills/%s/sync', $skill->slug))
        ->assertOk()
        ->assertJsonPath('name', 'My Skill')
        ->assertJsonPath('isExternal', true);
});

test('sync endpoint rejects internal skills', function (): void {
    $user = User::factory()->create();
    $skill = Skill::factory()->create([
        'github_url' => null,
        'slug' => 'internal-skill',
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->postJson(sprintf('/enki/skills/%s/sync', $skill->slug))
        ->assertStatus(422)
        ->assertJsonPath('message', 'This skill is not linked to a GitHub repository.');
});

test('member cannot sync a skill they did not create', function (): void {
    $url = 'https://github.com/anthropics/skills/tree/main/skills/my-skill';
    $owner = User::factory()->create();
    $skill = Skill::factory()->create([
        'github_url' => $url,
        'slug' => 'my-skill',
        'created_by_user_id' => $owner->id,
    ]);
    $other = User::factory()->create();

    $this->actingAs($other)
        ->postJson(sprintf('/enki/skills/%s/sync', $skill->slug))
        ->assertForbidden();
});

test('admin can sync any skill', function (): void {
    stubGitHubApis();

    $url = 'https://github.com/anthropics/skills/tree/main/skills/my-skill';
    $owner = User::factory()->create();
    $skill = Skill::factory()->create([
        'github_url' => $url,
        'slug' => 'my-skill',
        'created_by_user_id' => $owner->id,
    ]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->postJson(sprintf('/enki/skills/%s/sync', $skill->slug))
        ->assertOk()
        ->assertJsonPath('name', 'My Skill');
});
