<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Category;
use App\Models\Skill;
use App\Services\GitHubSkillSync;
use App\Services\SkillArchiveParser;
use App\Services\SkillContent;
use App\Services\VirtualEnkiSkill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnkiController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $filters = $this->parseFilters();
        $firstSkill = $this->buildSkillQuery($user, $filters)
            ->with(['changelogEntries', 'createdBy'])
            ->first();

        return Inertia::render('Enki', array_merge($this->sharedProps($user, $filters), [
            'selectedSkill' => $firstSkill ? $this->transformSkillFull($firstSkill) : null,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:128',
            'slug' => 'nullable|string|max:128|unique:skills,slug|regex:/^[a-z0-9\-\/]+$/',
            'category' => 'required|string',
            'summary' => 'nullable|string|max:500',
            'tags' => 'nullable|string',
            'visibility' => 'required|in:public,private',
            'archive' => 'required|file|max:51200',
        ]);

        $user = auth()->user();
        $parsed = null;

        if ($request->hasFile('archive')) {
            try {
                $parsed = app(SkillArchiveParser::class)->parse($request->file('archive'));
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $name = $parsed ? $parsed['name'] : $request->name;
        $base = $request->filled('slug') ? $request->slug : Str::slug($name);
        $slug = $base;
        $i = 2;
        while (Skill::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        $author = Author::firstOrCreate(
            ['slug' => Str::slug($user->name)],
            ['name' => $user->name, 'members' => 1]
        );

        $tags = array_values(array_filter(
            array_map('trim', explode(',', $request->input('tags', '')))
        ));

        $skill = Skill::create([
            'slug' => $slug,
            'name' => $name,
            'summary' => $parsed['summary'] ?? $request->input('summary', ''),
            'author_id' => $author->id,
            'version' => $parsed['version'] ?? '1.0.0',
            'installs' => 0,
            'monogram_tint' => rand(0, 5),
            'tags' => count($tags) ? $tags : ($parsed['tags'] ?? []),
            'readme' => $parsed['readme'] ?? '',
            'usage' => '',
            'visibility' => $request->visibility,
            'created_by_user_id' => $user->id,
        ]);

        $category = Category::where('slug', $request->category)->first();
        if ($category) {
            $skill->categories()->sync([$category->id]);
        }

        if ($parsed) {
            $content = $skill->getContent();
            foreach ($parsed['files'] as $file) {
                $content->put($file['path'], $file['content']);
            }
        }

        $skill->load(['author', 'categories', 'changelogEntries', 'createdBy']);

        return response()->json($this->transformSkillFull($skill));
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'github_url' => ['required', 'url', 'regex:#^https://github\.com/[^/]+/[^/]+/tree/.+#'],
        ]);

        try {
            $skill = app(GitHubSkillSync::class)->import($request->github_url, auth()->id());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->transformSkillFull($skill));
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $skill = Skill::with(['author', 'categories', 'changelogEntries', 'createdBy'])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->authorize('update', $skill);

        $request->validate([
            'name' => 'required|string|max:128',
            'category' => 'required|string',
            'summary' => 'nullable|string|max:500',
            'tags' => 'nullable|string',
            'visibility' => 'required|in:public,private',
            'archive' => 'nullable|file|max:51200',
        ]);

        $parsed = null;
        if ($request->hasFile('archive')) {
            try {
                $parsed = app(SkillArchiveParser::class)->parse($request->file('archive'));
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $tags = array_values(array_filter(
            array_map('trim', explode(',', $request->input('tags', '')))
        ));

        $skill->fill([
            'name' => $parsed['name'] ?? $request->name,
            'summary' => $parsed['summary'] ?? $request->input('summary', ''),
            'version' => $parsed['version'] ?? $skill->version,
            'tags' => count($tags) ? $tags : ($parsed['tags'] ?? $skill->tags ?? []),
            'visibility' => $request->visibility,
        ]);
        if ($parsed) {
            $skill->readme = $parsed['readme'] ?? $skill->readme;
        }
        $skill->save();

        $category = Category::where('slug', $request->category)->first();
        if ($category) {
            $skill->categories()->sync([$category->id]);
        }

        if ($parsed) {
            $content = $skill->getContent();
            $content->deleteAll();
            foreach ($parsed['files'] as $file) {
                $content->put($file['path'], $file['content']);
            }
        }

        $skill->load(['author', 'categories', 'changelogEntries', 'createdBy']);

        return response()->json($this->transformSkillFull($skill));
    }

    public function destroy(string $slug): JsonResponse
    {
        $skill = Skill::where('slug', $slug)->firstOrFail();

        $this->authorize('delete', $skill);

        $skill->getContent()->deleteAll();
        $skill->categories()->detach();
        $skill->starredByUsers()->detach();
        $skill->changelogEntries()->delete();
        $skill->files()->delete();
        $skill->forceDelete();

        return response()->json(['deleted' => true]);
    }

    public function sync(string $slug): JsonResponse
    {
        $skill = Skill::with(['author', 'categories', 'changelogEntries', 'createdBy'])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->authorize('update', $skill);

        if (! $skill->github_url) {
            return response()->json(['message' => 'This skill is not linked to a GitHub repository.'], 422);
        }

        try {
            app(GitHubSkillSync::class)->sync($skill);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->transformSkillFull($skill));
    }

    public function download(string $slug): BinaryFileResponse
    {
        if ($slug === VirtualEnkiSkill::SLUG) {
            $tmpPath = app(VirtualEnkiSkill::class)->buildZipPath();

            return response()->download($tmpPath, 'enki.zip', [
                'Content-Type' => 'application/zip',
            ])->deleteFileAfterSend();
        }

        $skill = Skill::where('slug', $slug)->firstOrFail();
        $content = $skill->getContent();
        $files = $content->files();

        if (count($files) === 0) {
            abort(404, 'No files available for this skill.');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'enki-zip-');
        $zip = new \ZipArchive;
        if ($zip->open($tmpPath, \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create zip archive.');
        }
        foreach ($files as $file) {
            $raw = $content->read($file['path']);
            if ($raw !== null) {
                $zip->addFromString($file['path'], $raw);
            }
        }
        $zip->close();

        $filename = str_replace('/', '-', $skill->slug).'.zip';

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }

    public function star(string $slug): JsonResponse
    {
        $user = auth()->user();
        $skill = Skill::where('slug', $slug)->firstOrFail();
        $result = $user->starredSkills()->toggle([$skill->id => ['starred_at' => now()]]);
        $starred = count($result['attached']) > 0;

        return response()->json(['starred' => $starred]);
    }

    public function apiIndex(): JsonResponse
    {
        $user = auth()->user();
        $filters = $this->parseFilters();

        $paginator = $this->buildSkillQuery($user, $filters)->paginate(30);
        $virtual = app(VirtualEnkiSkill::class);
        $matches = $virtual->matchesFilters($filters);

        $items = $paginator->getCollection()
            ->map(fn (Skill $s) => $this->transformApiSkillSummary($s));

        if ($matches && $paginator->currentPage() === 1) {
            $items->push($virtual->toApiSummary());
        }

        return response()->json([
            'data' => $items->values(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total() + ($matches ? 1 : 0),
        ]);
    }

    public function apiShow(string $slug): JsonResponse
    {
        if ($slug === VirtualEnkiSkill::SLUG) {
            return response()->json(app(VirtualEnkiSkill::class)->toApiFull());
        }

        $user = auth()->user();
        $skill = Skill::with(['author', 'categories'])
            ->withCount(['starredByUsers as ratings'])
            ->where('slug', $slug)
            ->where(fn ($q) => $q
                ->where('visibility', 'public')
                ->orWhere(fn ($q2) => $q2->where('visibility', 'private')->where('created_by_user_id', $user->id))
            )
            ->firstOrFail();

        return response()->json($this->transformApiSkillFull($skill));
    }

    public function apiCategories(): JsonResponse
    {
        return response()->json($this->categories());
    }

    public function skill(string $slug): Response
    {
        $user = auth()->user();
        $filters = $this->parseFilters();

        if ($slug === VirtualEnkiSkill::SLUG) {
            return Inertia::render('Enki', array_merge($this->sharedProps($user, $filters), [
                'selectedSkill' => app(VirtualEnkiSkill::class)->toWebFull(),
            ]));
        }

        $skill = Skill::with(['author', 'categories', 'changelogEntries', 'createdBy'])
            ->withExists(['starredByUsers as starred' => fn ($q) => $q->where('users.id', $user->id)])
            ->withCount(['starredByUsers as ratings'])
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('Enki', array_merge($this->sharedProps($user, $filters), [
            'selectedSkill' => $this->transformSkillFull($skill),
        ]));
    }

    /** @return array<string, mixed> */
    private function sharedProps(mixed $user, array $filters): array
    {
        return [
            'categories' => $this->categories(),
            'tints' => $this->tints(),
            'authors' => $this->authorData(),
            'filters' => $filters,
            'skillCounts' => $this->skillCounts($user, $filters),
            'skills' => Inertia::scroll(fn () => $this->buildSkillsPage($user, $filters)),
        ];
    }

    private function buildSkillsPage(mixed $user, array $filters): LengthAwarePaginator
    {
        $paginator = $this->buildSkillQuery($user, $filters)->paginate(5);
        $virtual = app(VirtualEnkiSkill::class);
        $matches = $virtual->matchesFilters($filters);

        $items = $paginator->getCollection()
            ->map(fn (Skill $s) => $this->transformSkillSummary($s));

        if ($matches && $paginator->onLastPage()) {
            $items->push($virtual->toWebSummary());
        }

        return new LengthAwarePaginator(
            $items,
            $paginator->total() + ($matches ? 1 : 0),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    /** @return array{q: string, category: string, sort: string, starred: bool, mySkills: bool, source: string} */
    private function parseFilters(): array
    {
        $source = request()->input('source', 'all');

        return [
            'q' => (string) request()->input('q', ''),
            'category' => (string) request()->input('category', 'all'),
            'sort' => in_array(request()->input('sort'), ['recent', 'name', 'stars']) ? request()->input('sort') : 'recent',
            'starred' => request()->boolean('starred'),
            'mySkills' => request()->boolean('mySkills'),
            'source' => in_array($source, ['internal', 'external']) ? $source : 'all',
        ];
    }

    private function buildSkillQuery(mixed $user, array $filters): Builder
    {
        return Skill::with(['author', 'categories', 'createdBy'])
            ->withExists(['starredByUsers as starred' => fn ($q) => $q->where('users.id', $user->id)])
            ->withCount(['starredByUsers as ratings'])
            ->where(fn ($q) => $q
                ->where('visibility', 'public')
                ->orWhere(fn ($q2) => $q2->where('visibility', 'private')->where('created_by_user_id', $user->id))
            )
            ->when($filters['q'], fn ($b, $q) => $b->where(fn ($b2) => $b2->where('name', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%")
                ->orWhere('summary', 'like', "%{$q}%")
                ->orWhere('tags', 'like', "%{$q}%")
            ))
            ->when($filters['category'] !== 'all', fn ($b) => $b->whereHas('categories', fn ($q) => $q->where('slug', $filters['category']))
            )
            ->when($filters['starred'], fn ($b) => $b->whereHas('starredByUsers', fn ($q) => $q->where('users.id', $user->id))
            )
            ->when($filters['mySkills'], fn ($b) => $b->where('created_by_user_id', $user->id))
            ->when($filters['source'] === 'internal', fn ($b) => $b->whereNull('github_url'))
            ->when($filters['source'] === 'external', fn ($b) => $b->whereNotNull('github_url'))
            ->when($filters['sort'] === 'name', fn ($b) => $b->orderBy('name'))
            ->when($filters['sort'] === 'stars', fn ($b) => $b->orderByDesc('ratings'))
            ->unless(in_array($filters['sort'], ['name', 'stars']), fn ($b) => $b->orderByDesc('updated_at'));
    }

    /** @return array<string, int> */
    private function skillCounts(mixed $user, array $filters): array
    {
        $matchingIds = Skill::query()
            ->where(fn ($q) => $q
                ->where('visibility', 'public')
                ->orWhere(fn ($q2) => $q2->where('visibility', 'private')->where('created_by_user_id', $user->id))
            )
            ->when($filters['q'], fn ($b, $q) => $b->where(fn ($b2) => $b2->where('name', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%")
                ->orWhere('summary', 'like', "%{$q}%")
                ->orWhere('tags', 'like', "%{$q}%")
            ))
            ->when($filters['starred'], fn ($b) => $b->whereHas('starredByUsers', fn ($q) => $q->where('users.id', $user->id))
            )
            ->when($filters['mySkills'], fn ($b) => $b->where('created_by_user_id', $user->id))
            ->when($filters['source'] === 'internal', fn ($b) => $b->whereNull('github_url'))
            ->when($filters['source'] === 'external', fn ($b) => $b->whereNotNull('github_url'))
            ->pluck('id');

        $perCat = DB::table('category_skill')
            ->join('categories', 'categories.id', '=', 'category_skill.category_id')
            ->whereIn('category_skill.skill_id', $matchingIds)
            ->groupBy('categories.slug')
            ->select('categories.slug', DB::raw('count(*) as cnt'))
            ->pluck('cnt', 'slug')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $counts = array_merge(['all' => $matchingIds->count()], $perCat);

        if (app(VirtualEnkiSkill::class)->matchesBaseFilters($filters)) {
            $counts['all']++;
        }

        return $counts;
    }

    private function authorData(): Collection
    {
        $virtual = app(VirtualEnkiSkill::class);

        return collect(['laravel-boost' => $virtual->authorEntry()])
            ->merge(
                Author::withCount('skills')
                    ->get()
                    ->keyBy('slug')
                    ->map(fn (Author $a) => [
                        'name' => $a->name,
                        'members' => $a->members,
                        'skills' => $a->skills_count,
                    ])
            );
    }

    /** @return array<int, array{id: string, label: string, icon: string|null, color: array{bg: string, fg: string}|null}> */
    private function categories(): array
    {
        return Category::orderBy('label')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->slug,
                'label' => $c->label,
                'icon' => $c->icon,
                'color' => $c->color,
            ])
            ->prepend(['id' => 'all', 'label' => 'All skills', 'icon' => null, 'color' => null])
            ->values()
            ->all();
    }

    /** @return array<int, array{bg: string, fg: string}> */
    private function tints(): array
    {
        return [
            ['bg' => '#efe7d8', 'fg' => '#6b4f1d'],
            ['bg' => '#e6e3da', 'fg' => '#3f3a2c'],
            ['bg' => '#e3dfd1', 'fg' => '#4a4128'],
            ['bg' => '#dcd8c9', 'fg' => '#39341f'],
            ['bg' => '#e9e4d4', 'fg' => '#564a2a'],
            ['bg' => '#ddd8c5', 'fg' => '#3a3622'],
        ];
    }

    /** @return array<string, mixed> */
    private function transformSkillSummary(Skill $skill): array
    {
        return [
            'slug' => $skill->slug,
            'name' => $skill->name,
            'summary' => $skill->summary,
            'categories' => $skill->categories->pluck('slug')->all(),
            'categoryIcon' => $skill->categories->first()?->icon,
            'categoryColor' => $skill->categories->first()?->color,
            'author' => $skill->author->slug,
            'version' => $skill->version,
            'updated' => $skill->updated,
            'ratings' => $skill->ratings,
            'tags' => $skill->tags,
            'starred' => (bool) $skill->starred,
            'monogramTint' => $skill->monogram_tint,
            'installs' => $skill->installs,
            'isExternal' => $skill->github_url !== null,
            'githubUrl' => $skill->github_url,
            'importedBy' => $skill->createdBy?->name,
            'isPrivate' => $skill->visibility === 'private',
            'canEdit' => Gate::allows('update', $skill),
            'canStar' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function transformApiSkillSummary(Skill $skill): array
    {
        return [
            'slug' => $skill->slug,
            'name' => $skill->name,
            'summary' => $skill->summary,
            'categories' => $skill->categories->pluck('slug')->all(),
            'author' => $skill->author->slug,
            'version' => $skill->version,
            'updatedAt' => $skill->updated_at->toDateString(),
            'ratings' => $skill->ratings,
            'tags' => $skill->tags ?? [],
            'installs' => $skill->installs,
            'isExternal' => $skill->github_url !== null,
        ];
    }

    /** @return array<string, mixed> */
    private function transformApiSkillFull(Skill $skill): array
    {
        return array_merge($this->transformApiSkillSummary($skill), [
            'readme' => SkillContent::stripFrontmatter($skill->readme ?? ''),
            'usage' => $skill->usage ?? '',
        ]);
    }

    /** @return array<string, mixed> */
    private function transformSkillFull(Skill $skill): array
    {
        $activityLog = Activity::forSubject($skill)
            ->with('causer')
            ->latest()
            ->get()
            ->map(fn (Activity $a) => [
                'event' => $a->event ?? 'updated',
                'causer' => $a->causer?->name,
                'at' => $a->created_at->toISOString(),
                'atHuman' => $a->created_at->diffForHumans(['parts' => 1]),
            ])
            ->all();

        return array_merge($this->transformSkillSummary($skill), [
            'readmeHtml' => Str::markdown(SkillContent::stripFrontmatter($skill->readme ?? '')),
            'readmeFrontmatter' => SkillContent::extractFrontmatter($skill->readme ?? ''),
            'usageHtml' => Str::markdown($skill->usage ?? ''),
            'changelog' => $skill->changelogEntries->map(fn ($e) => [
                'v' => $e->version,
                'd' => $e->released_on->toDateString(),
                'notes' => $e->notes,
            ])->all(),
            'activityLog' => $activityLog,
            'files' => $skill->getContent()->files(),
        ]);
    }
}
