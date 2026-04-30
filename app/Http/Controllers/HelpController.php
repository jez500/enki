<?php

namespace App\Http\Controllers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    public function show(string $doc = 'README'): Response
    {
        abort_if(! preg_match('/^[\w\-]+$/', $doc), 404);

        $docsPath = base_path('docs');
        $filePath = $docsPath.'/'.$doc.'.md';

        abort_unless(File::exists($filePath), 404);

        return Inertia::render('Help', [
            'docs' => self::listDocs(),
            'currentDoc' => $doc,
            'content' => Str::markdown(File::get($filePath)),
        ]);
    }

    /** @return Collection<int, array{slug: string, title: string}> */
    public static function listDocs(): Collection
    {
        return collect(glob(base_path('docs').'/*.md'))
            ->map(function (string $path): array {
                $slug = pathinfo($path, PATHINFO_FILENAME);

                return [
                    'slug' => $slug,
                    'title' => ucwords(str_replace(['_', '-'], ' ', $slug)),
                ];
            })
            ->sort(fn (array $a, array $b): int => match (true) {
                $a['slug'] === 'README' => -1,
                $b['slug'] === 'README' => 1,
                default => strcmp($a['title'], $b['title']),
            })
            ->values();
    }
}
