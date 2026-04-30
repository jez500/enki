<?php

namespace App\Services;

use App\Models\Skill;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class SkillContent
{
    public function __construct(private readonly Skill $skill) {}

    private function disk(): Filesystem
    {
        return Storage::disk('skill_data');
    }

    private function root(): string
    {
        return (string) $this->skill->id;
    }

    public function exists(): bool
    {
        return $this->disk()->exists($this->root());
    }

    /**
     * Write a file into this skill's directory.
     */
    public function put(string $relativePath, string $content): void
    {
        $this->disk()->put($this->root().'/'.$relativePath, $content);
    }

    /**
     * Delete the entire skill directory.
     */
    public function deleteAll(): void
    {
        $this->disk()->deleteDirectory($this->root());
    }

    /**
     * Read a single file by its relative path, or null if it doesn't exist.
     */
    public function read(string $relativePath): ?string
    {
        $path = $this->root().'/'.$relativePath;

        return $this->disk()->exists($path) ? $this->disk()->get($path) : null;
    }

    /**
     * Flat list of every file, sorted alphabetically by path.
     *
     * @return array<int, array{path: string, size: string, kind: string, content: string|null}>
     */
    public function files(): array
    {
        if (! $this->exists()) {
            return [];
        }

        $prefix = $this->root().'/';
        $result = [];

        foreach ($this->disk()->allFiles($this->root()) as $fullPath) {
            $relative = substr($fullPath, strlen($prefix));
            $bytes = $this->disk()->size($fullPath);
            $raw = $this->disk()->get($fullPath);
            $result[] = [
                'path' => $relative,
                'size' => $this->formatBytes($bytes),
                'kind' => pathinfo($relative, PATHINFO_EXTENSION),
                'content' => ($raw !== null && mb_check_encoding($raw, 'UTF-8')) ? $raw : null,
            ];
        }

        usort($result, fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $result;
    }

    public function count(): int
    {
        return $this->exists() ? count($this->disk()->allFiles($this->root())) : 0;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1_048_576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1_048_576, 1).' MB';
    }

    /** @return array<string, string> */
    public static function extractFrontmatter(string $markdown): array
    {
        $omit = ['name', 'description'];
        $trimmed = ltrim($markdown);
        if (! str_starts_with($trimmed, '---')) {
            return [];
        }

        $end = strpos($trimmed, "\n---", 3);
        if ($end === false) {
            return [];
        }

        $fields = [];
        foreach (explode("\n", trim(substr($trimmed, 3, $end - 3))) as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) {
                continue;
            }

            $key = trim(substr($line, 0, $colon));
            $val = trim(substr($line, $colon + 1));
            if ($key && $val && ! in_array($key, $omit, true)) {
                $fields[$key] = $val;
            }
        }

        return $fields;
    }

    public static function stripFrontmatter(string $markdown): string
    {
        $trimmed = ltrim($markdown);
        if (! str_starts_with($trimmed, '---')) {
            return $markdown;
        }

        $end = strpos($trimmed, "\n---", 3);
        if ($end === false) {
            return $markdown;
        }

        return ltrim(substr($trimmed, $end + 4));
    }
}
