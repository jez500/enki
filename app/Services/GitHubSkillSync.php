<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Skill;
use FilesystemIterator;
use Illuminate\Support\Facades\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

class GitHubSkillSync
{
    /**
     * @return array{owner: string, repo: string, branch: string, path: string}
     */
    public function parseUrl(string $url): array
    {
        if (preg_match('#^https://github\.com/([^/]+)/([^/]+)/tree/([^/]+)/(.+)$#', $url, $m)) {
            return ['owner' => $m[1], 'repo' => $m[2], 'branch' => $m[3], 'path' => rtrim($m[4], '/')];
        }

        if (preg_match('#^https://github\.com/([^/]+)/([^/]+)/tree/([^/]+)/?$#', $url, $m)) {
            return ['owner' => $m[1], 'repo' => $m[2], 'branch' => $m[3], 'path' => ''];
        }

        throw new \InvalidArgumentException('URL must be a GitHub tree URL: https://github.com/{owner}/{repo}/tree/{branch}[/{path}]');
    }

    public function import(string $url, ?int $createdByUserId = null): Skill
    {
        $parts = $this->parseUrl($url);
        $data = $this->fetchSkillData($parts);

        $author = Author::firstOrCreate(
            ['slug' => $data['authorSlug']],
            ['name' => $data['authorName'], 'members' => 0]
        );

        $skill = Skill::create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'summary' => $data['summary'],
            'author_id' => $author->id,
            'version' => $data['version'],
            'installs' => 0,
            'tags' => $data['tags'],
            'readme' => $data['readme'],
            'usage' => $data['usage'],
            'github_url' => $url,
            'created_by_user_id' => $createdByUserId,
        ]);

        if ($data['updatedAt']) {
            $skill->updated_at = $data['updatedAt'];
            $skill->saveQuietly();
        }

        $this->syncFiles($skill, $data['files']);

        return $skill->load(['author', 'categories', 'changelogEntries']);
    }

    public function sync(Skill $skill): void
    {
        $parts = $this->parseUrl($skill->github_url);
        $data = $this->fetchSkillData($parts);

        $author = Author::firstOrCreate(
            ['slug' => $data['authorSlug']],
            ['name' => $data['authorName'], 'members' => 0]
        );

        $skill->update([
            'name' => $data['name'],
            'summary' => $data['summary'],
            'author_id' => $author->id,
            'version' => $data['version'],
            'tags' => $data['tags'],
            'readme' => $data['readme'],
            'usage' => $data['usage'],
        ]);

        if ($data['updatedAt']) {
            $skill->updated_at = $data['updatedAt'];
            $skill->saveQuietly();
        }

        $this->syncFiles($skill, $data['files']);

        $skill->load(['author', 'categories', 'changelogEntries']);
    }

    /**
     * @param  array{owner: string, repo: string, branch: string, path: string}  $parts
     * @return array<string, mixed>
     */
    private function fetchSkillData(array $parts): array
    {
        ['owner' => $owner, 'repo' => $repo, 'branch' => $branch, 'path' => $path] = $parts;

        $tmpDir = $this->downloadAndExtract($owner, $repo, $branch);

        try {
            $skillDir = $this->resolveSkillDir($tmpDir, $path);
            $fileNames = array_map('basename', glob($skillDir.'/*') ?: []);

            $readme = $this->readTextFile($skillDir, ['SKILL.md', 'README.md'], $fileNames);
            $meta = $this->parseMeta($skillDir, $fileNames, $readme);
            $usage = $this->readTextFile($skillDir, ['USAGE.md'], $fileNames);
            $updatedAt = $this->fetchLastCommitDate($owner, $repo, $branch, $path);

            $slug = $meta['slug'] ?? ($path ? basename($path) : $repo);
            $authorSlug = strtolower($meta['author'] ?? $owner);
            $authorName = $meta['author'] ?? $owner;
            $files = $this->collectFiles($skillDir);

            return [
                'slug' => $slug,
                'name' => $this->slugToTitle($meta['name'] ?? $slug),
                'summary' => $meta['summary'] ?? $meta['description'] ?? '',
                'version' => $meta['version'] ?? '1.0.0',
                'tags' => $meta['tags'] ?? [],
                'authorSlug' => $authorSlug,
                'authorName' => $authorName,
                'readme' => $readme,
                'usage' => $usage,
                'updatedAt' => $updatedAt,
                'files' => $files,
            ];
        } finally {
            $this->cleanupDir($tmpDir);
        }
    }

    private function downloadAndExtract(string $owner, string $repo, string $branch): string
    {
        $zipUrl = "https://github.com/{$owner}/{$repo}/archive/refs/heads/{$branch}.zip";

        $request = Http::timeout(120)->withHeaders(['Accept' => 'application/octet-stream']);
        if ($token = config('services.github.token')) {
            $request = $request->withToken($token);
        }

        $response = $request->get($zipUrl);

        if ($response->status() === 404) {
            throw new RuntimeException("Repository or branch not found: {$owner}/{$repo}@{$branch}");
        }
        if ($response->status() === 403) {
            throw new RuntimeException('GitHub access denied. Set GITHUB_TOKEN if the repository is private.');
        }
        if (! $response->successful()) {
            throw new RuntimeException("Failed to download repository zip: HTTP {$response->status()}");
        }

        $tmpZip = sys_get_temp_dir().'/enki-'.uniqid().'.zip';
        $tmpDir = sys_get_temp_dir().'/enki-'.uniqid();

        file_put_contents($tmpZip, $response->body());

        try {
            $zip = new ZipArchive;
            if ($zip->open($tmpZip) !== true) {
                throw new RuntimeException('Failed to open downloaded zip.');
            }
            mkdir($tmpDir, 0755, true);
            $zip->extractTo($tmpDir);
            $zip->close();
        } finally {
            @unlink($tmpZip);
        }

        return $tmpDir;
    }

    private function resolveSkillDir(string $tmpDir, string $path): string
    {
        $topLevel = glob($tmpDir.'/*', GLOB_ONLYDIR);

        if (empty($topLevel)) {
            throw new RuntimeException('Unexpected zip structure: no top-level directory found.');
        }

        $repoRoot = $topLevel[0];

        if ($path === '') {
            return $repoRoot;
        }

        $skillDir = $repoRoot.'/'.$path;

        if (! is_dir($skillDir)) {
            throw new RuntimeException("Path '{$path}' not found in repository.");
        }

        return $skillDir;
    }

    /**
     * @param  string[]  $candidates
     * @param  string[]  $fileNames
     */
    private function readTextFile(string $dir, array $candidates, array $fileNames): string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $fileNames, true)) {
                return file_get_contents($dir.'/'.$candidate) ?: '';
            }
        }

        return '';
    }

    /**
     * @param  string[]  $fileNames
     * @return array<string, mixed>
     */
    private function parseMeta(string $dir, array $fileNames, string $readme): array
    {
        foreach (['skill.yaml', 'skill.yml'] as $candidate) {
            if (in_array($candidate, $fileNames, true)) {
                $content = file_get_contents($dir.'/'.$candidate);
                if ($content) {
                    $parsed = Yaml::parse($content);
                    if (is_array($parsed)) {
                        return $parsed;
                    }
                }
            }
        }

        return $this->parseFrontmatter($readme);
    }

    /**
     * Recursively collect all files under $skillDir with paths relative to $skillDir.
     *
     * @return array<int, array{path: string, size: int, content: string|null}>
     */
    private function collectFiles(string $skillDir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($skillDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $size = $file->getSize();
            $relativePath = ltrim(str_replace($skillDir, '', $file->getPathname()), '/\\');
            $content = $size <= 51200 ? (file_get_contents($file->getPathname()) ?: null) : null;
            $files[] = ['path' => $relativePath, 'size' => $size, 'content' => $content];
        }

        return $files;
    }

    private function cleanupDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }

    private function fetchLastCommitDate(string $owner, string $repo, string $branch, string $path): ?string
    {
        try {
            $query = ['per_page' => 1, 'sha' => $branch];
            if ($path) {
                $query['path'] = $path;
            }
            $commits = $this->apiGet("/repos/{$owner}/{$repo}/commits", $query);

            return $commits[0]['commit']['committer']['date'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function syncFiles(Skill $skill, array $files): void
    {
        $content = $skill->getContent();
        $content->deleteAll();

        foreach ($files as $file) {
            if ($file['content'] !== null) {
                $content->put($file['path'], $file['content']);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFrontmatter(string $content): array
    {
        if (! str_starts_with(ltrim($content), '---')) {
            return [];
        }

        $stripped = ltrim($content);
        $end = strpos($stripped, '---', 3);
        if ($end === false) {
            return [];
        }

        $yaml = substr($stripped, 3, $end - 3);
        $parsed = Yaml::parse($yaml);

        return is_array($parsed) ? $parsed : [];
    }

    private function slugToTitle(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function apiGet(string $endpoint, array $query = []): mixed
    {
        $request = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28']);

        $token = config('services.github.token');
        if ($token) {
            $request = $request->withToken($token);
        }

        $response = $request->get('https://api.github.com'.$endpoint, $query);

        if ($response->status() === 404) {
            throw new RuntimeException("GitHub path not found: {$endpoint}");
        }

        if ($response->status() === 403) {
            throw new RuntimeException('GitHub API rate limit exceeded. Set GITHUB_TOKEN to increase limits.');
        }

        if (! $response->successful()) {
            throw new RuntimeException("GitHub API error {$response->status()}: {$response->body()}");
        }

        return $response->json();
    }
}
