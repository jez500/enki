<?php

namespace App\Services;

use Illuminate\Support\Str;

class VirtualEnkiSkill
{
    private readonly string $readme;

    private readonly string $usage;

    /** @var array<string, mixed> */
    private array $meta;

    public function __construct(private readonly AppService $appService)
    {
        $vars = ['appName' => $appService->getAppName(), 'appSlug' => $appService->getAppSlug()];
        $this->readme = view('skills.enki.readme', $vars)->render();
        $this->usage = view('skills.enki.usage', $vars)->render();
        $this->meta = $this->parseFrontmatter($this->readme);
    }

    public function slug(): string
    {
        return $this->appService->getAppSlug();
    }

    /**
     * Whether the virtual skill should appear given the full filter set.
     *
     * @param  array{q: string, category: string, sort: string, starred: bool, mySkills: bool, source: string}  $filters
     */
    public function matchesFilters(array $filters): bool
    {
        return $this->matchesBaseFilters($filters) && $filters['category'] === 'all';
    }

    /**
     * Whether the virtual skill matches filters that don't include category
     * (used for skill counts, which tally across all categories).
     *
     * @param  array{q: string, category: string, sort: string, starred: bool, mySkills: bool, source: string}  $filters
     */
    public function matchesBaseFilters(array $filters): bool
    {
        if ($filters['starred'] || $filters['mySkills']) {
            return false;
        }

        if ($filters['source'] === 'external') {
            return false;
        }

        if ($filters['q']) {
            $q = strtolower($filters['q']);
            $haystack = strtolower(implode(' ', array_filter([
                $this->slug(),
                $this->appService->getAppName(),
                $this->meta['description'] ?? '',
                implode(' ', (array) ($this->meta['tags'] ?? [])),
            ])));
            if (! str_contains($haystack, $q)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function toWebSummary(): array
    {
        return [
            'slug' => $this->slug(),
            'name' => $this->appService->getAppName(),
            'summary' => $this->meta['description'] ?? '',
            'categories' => [],
            'categoryIcon' => null,
            'categoryColor' => null,
            'author' => 'laravel-boost',
            'version' => (string) ($this->meta['version'] ?? '1.0.0'),
            'updated' => 'always current',
            'ratings' => 0,
            'tags' => (array) ($this->meta['tags'] ?? []),
            'starred' => false,
            'monogramTint' => 0,
            'installs' => 0,
            'isExternal' => false,
            'githubUrl' => null,
            'importedBy' => null,
            'isPrivate' => false,
            'canEdit' => false,
            'canStar' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function toWebFull(): array
    {
        return array_merge($this->toWebSummary(), [
            'readmeHtml' => Str::markdown(SkillContent::stripFrontmatter($this->readme)),
            'readmeFrontmatter' => SkillContent::extractFrontmatter($this->readme),
            'usageHtml' => Str::markdown($this->usage),
            'changelog' => [],
            'activityLog' => [],
            'files' => [
                [
                    'path' => 'SKILL.md',
                    'size' => strlen($this->readme).' B',
                    'kind' => 'md',
                    'content' => $this->readme,
                ],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function toApiSummary(): array
    {
        return [
            'slug' => $this->slug(),
            'name' => $this->appService->getAppName(),
            'summary' => $this->meta['description'] ?? '',
            'categories' => [],
            'author' => 'laravel-boost',
            'version' => (string) ($this->meta['version'] ?? '1.0.0'),
            'updatedAt' => now()->toDateString(),
            'ratings' => 0,
            'tags' => (array) ($this->meta['tags'] ?? []),
            'installs' => 0,
            'isExternal' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function toApiFull(): array
    {
        return array_merge($this->toApiSummary(), [
            'readme' => SkillContent::stripFrontmatter($this->readme),
            'usage' => $this->usage,
        ]);
    }

    /** @return array{name: string, members: int, skills: int} */
    public function authorEntry(): array
    {
        return ['name' => 'Laravel Boost', 'members' => 1, 'skills' => 1];
    }

    public function buildZipPath(): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'enki-virtual-zip-');
        $zip = new \ZipArchive;
        $zip->open($tmpPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('SKILL.md', $this->readme);
        $zip->close();

        return $tmpPath;
    }

    /** @return array<string, mixed> */
    private function parseFrontmatter(string $content): array
    {
        $trimmed = ltrim($content);
        if (! str_starts_with($trimmed, '---')) {
            return [];
        }

        $end = strpos($trimmed, "\n---", 3);
        if ($end === false) {
            return [];
        }

        $result = [];
        foreach (explode("\n", trim(substr($trimmed, 3, $end - 3))) as $line) {
            if (preg_match('/^(\w[\w-]*):\s*(.+)$/', trim($line), $m)) {
                $result[$m[1]] = trim($m[2], '"\'');
            } elseif (preg_match('/^(\w[\w-]*):\s*\[([^\]]*)\]$/', trim($line), $m)) {
                $result[$m[1]] = array_map(fn ($v): string => trim($v, ' "\''), array_filter(explode(',', $m[2])));
            }
        }

        return $result;
    }
}
