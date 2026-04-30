<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Yaml\Yaml;

class SkillArchiveParser
{
    /**
     * Extract and validate an uploaded archive, returning parsed skill data.
     *
     * @return array{name: string, summary: string, version: string, tags: array<int,string>, readme: string, files: array<int, array{path: string, content: string}>}
     *
     * @throws \InvalidArgumentException
     */
    public function parse(UploadedFile $file): array
    {
        $tmpDir = sys_get_temp_dir().'/enki-upload-'.uniqid();
        mkdir($tmpDir, 0755, true);

        try {
            $this->extract($file, $tmpDir);
            $root = $this->findRoot($tmpDir);

            if (! file_exists($root.'/SKILL.md')) {
                throw new \InvalidArgumentException('Archive must contain SKILL.md at its root.');
            }

            $readme = file_get_contents($root.'/SKILL.md');
            $meta = $this->resolveMeta($root, $readme);
            $files = $this->collectFiles($root, $root);

            return [
                'name'    => $this->slugToTitle($meta['name'] ?? basename($root)),
                'summary' => $meta['summary'] ?? $meta['description'] ?? '',
                'version' => (string) ($meta['version'] ?? '1.0.0'),
                'tags'    => array_values(array_filter((array) ($meta['tags'] ?? []))),
                'readme'  => $readme,
                'files'   => $files,
            ];
        } finally {
            $this->deleteDir($tmpDir);
        }
    }

    private function extract(UploadedFile $file, string $dest): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->getRealPath();

        if ($ext === 'zip') {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                throw new \InvalidArgumentException('Could not open zip archive.');
            }
            $zip->extractTo($dest);
            $zip->close();

            return;
        }

        // tar, tar.gz, tgz — also catches double extension
        $clientName = $file->getClientOriginalName();
        if ($ext === 'gz' || str_ends_with($clientName, '.tar.gz') || $ext === 'tgz' || $ext === 'tar') {
            try {
                $phar = new \PharData($path);
                $phar->extractTo($dest);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Could not open tar archive: '.$e->getMessage());
            }

            return;
        }

        throw new \InvalidArgumentException('Unsupported archive type. Use .zip, .tar, or .tar.gz.');
    }

    /**
     * If all top-level entries are within a single subdirectory, return that directory (GitHub zip behaviour).
     */
    private function findRoot(string $tmpDir): string
    {
        $entries = array_values(array_filter(
            scandir($tmpDir),
            fn ($e) => $e !== '.' && $e !== '..'
        ));

        if (count($entries) === 1 && is_dir($tmpDir.'/'.$entries[0])) {
            return $tmpDir.'/'.$entries[0];
        }

        return $tmpDir;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMeta(string $root, string $readme): array
    {
        foreach (['skill.yaml', 'skill.yml'] as $candidate) {
            $path = $root.'/'.$candidate;
            if (file_exists($path)) {
                $parsed = Yaml::parse(file_get_contents($path));
                if (is_array($parsed)) {
                    return $parsed;
                }
            }
        }

        return $this->parseFrontmatter($readme);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFrontmatter(string $content): array
    {
        $stripped = ltrim($content);
        if (! str_starts_with($stripped, '---')) {
            return [];
        }
        $end = strpos($stripped, '---', 3);
        if ($end === false) {
            return [];
        }
        $yaml = substr($stripped, 3, $end - 3);
        $parsed = Yaml::parse($yaml);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Recursively collect all files relative to $root.
     *
     * @return array<int, array{path: string, content: string}>
     */
    private function collectFiles(string $dir, string $root): array
    {
        $files = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $abs = $dir.'/'.$entry;
            $rel = ltrim(substr($abs, strlen($root)), '/');
            if (is_dir($abs)) {
                foreach ($this->collectFiles($abs, $root) as $child) {
                    $files[] = $child;
                }
            } else {
                $files[] = ['path' => $rel, 'content' => file_get_contents($abs)];
            }
        }

        return $files;
    }

    private function deleteDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function slugToTitle(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
