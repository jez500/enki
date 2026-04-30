<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Category;
use App\Models\Skill;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $changelog = [
            ['version' => '2.4.1', 'released_on' => '2026-04-22', 'notes' => 'Patch — fix stale cache when context exceeds 8k tokens.'],
            ['version' => '2.4.0', 'released_on' => '2026-04-08', 'notes' => 'Adds streaming mode and per-call telemetry hooks.'],
            ['version' => '2.3.0', 'released_on' => '2026-03-14', 'notes' => 'New schema validator. Breaking: `output.meta` renamed to `output.trace`.'],
            ['version' => '2.2.2', 'released_on' => '2026-02-27', 'notes' => 'Reliability fixes for retry storms on rate-limit.'],
            ['version' => '2.2.0', 'released_on' => '2026-02-02', 'notes' => 'Multi-turn planning. Memory window doubled.'],
            ['version' => '2.1.0', 'released_on' => '2025-12-19', 'notes' => 'Initial public release inside the org.'],
        ];

        $files = [
            ['path' => 'skill.yaml',          'size' => '1.2 KB',  'kind' => 'yaml'],
            ['path' => 'prompts/system.md',   'size' => '4.8 KB',  'kind' => 'md'],
            ['path' => 'prompts/planner.md',  'size' => '2.1 KB',  'kind' => 'md'],
            ['path' => 'prompts/critic.md',   'size' => '1.6 KB',  'kind' => 'md'],
            ['path' => 'schema.json',         'size' => '0.9 KB',  'kind' => 'json'],
            ['path' => 'examples/basic.json', 'size' => '2.3 KB',  'kind' => 'json'],
            ['path' => 'examples/edge.json',  'size' => '3.7 KB',  'kind' => 'json'],
            ['path' => 'tests/golden.jsonl',  'size' => '11.4 KB', 'kind' => 'jsonl'],
            ['path' => 'README.md',           'size' => '5.0 KB',  'kind' => 'md'],
            ['path' => 'CHANGELOG.md',        'size' => '2.4 KB',  'kind' => 'md'],
        ];

        $readme = fn (string $name, string $summary): string => "# {$name}\n\n{$summary}\n\n## When to use\n\nReach for this skill when an agent needs to ".strtolower(rtrim($summary, '.'))." as part of a longer task. It's been hardened against the edge cases the team hits most often.\n\n## How it works\n\nThe skill exposes a single `run` entrypoint. You pass it a goal, optional context, and an output schema. Internally it plans, gathers, executes, and self-checks.\n\nOutputs are deterministic with `temperature: 0` by default. Override per-call if you want exploration.\n\n## Inputs\n\n- **goal** — Plain-language description of what to produce.\n- **context** — Array of strings or file refs the skill should consider.\n- **schema** — Optional JSON schema for structured output.\n\n## Outputs\n\nA structured object matching your schema, plus a `trace` array of intermediate reasoning steps.\n\n## Notes\n\nThis skill is internal-only. Do not bundle it into externally-shipped agents without sign-off from the owning team.";

        $usage = fn (string $slug): string => "## Quick start\n\nDrop into your agent runtime:\n\n```bash\nenki add {$slug}\n```\n\nThen call it from any agent definition:\n\n```yaml\nskills:\n  - {$slug}@latest\n```\n\n## Programmatic\n\n```ts\nimport { runSkill } from \"@enki/runtime\";\n\nconst result = await runSkill(\"{$slug}\", {\n  goal: \"your goal here\",\n  context: [\"doc-ref-1\", \"doc-ref-2\"],\n});\n```\n\n## Pinning a version\n\nLock to a specific version when you need reproducibility:\n\n```yaml\nskills:\n  - {$slug}@2.4.1\n```\n";

        $updatedAt = fn (string $relative): CarbonInterface => match ($relative) {
            'today' => now()->startOfDay(),
            'this morning' => now()->setTime(9, 0),
            'yesterday' => now()->subDay()->startOfDay(),
            '2 days ago' => now()->subDays(2),
            '3 days ago' => now()->subDays(3),
            '4 days ago' => now()->subDays(4),
            '5 days ago' => now()->subDays(5),
            '6 days ago' => now()->subDays(6),
            '1 week ago' => now()->subWeek(),
            '2 weeks ago' => now()->subWeeks(2),
            '3 weeks ago' => now()->subWeeks(3),
            default => now()->subDays(1),
        };

        $raw = [
            ['slug' => 'research/literature-survey', 'name' => 'Literature Survey',   'summary' => 'Synthesize technical papers into a structured brief.',               'category' => 'research', 'author' => 'research',   'version' => '3.1.0', 'updated' => '2 days ago',   'tags' => ['arxiv', 'synthesis', 'citations', 'long-context'],  'monogram_tint' => 0],
            ['slug' => 'writing/changelog-author',   'name' => 'Changelog Author',    'summary' => 'Turn a list of merged PRs into a release-ready changelog.',          'category' => 'writing',  'author' => 'platform',   'version' => '1.7.2', 'updated' => '5 days ago',   'tags' => ['release', 'git', 'markdown'],                       'monogram_tint' => 1],
            ['slug' => 'coding/code-reviewer',       'name' => 'Code Reviewer',       'summary' => "Review diffs against the team's style guide and surface risks.",      'category' => 'coding',   'author' => 'platform',   'version' => '4.0.0', 'updated' => 'yesterday',    'tags' => ['review', 'quality', 'diff', 'ts', 'py'],            'monogram_tint' => 2],
            ['slug' => 'coding/test-author',         'name' => 'Test Author',         'summary' => 'Generate unit tests with explanations from a function or class.',    'category' => 'coding',   'author' => 'platform',   'version' => '2.4.1', 'updated' => '1 week ago',   'tags' => ['testing', 'jest', 'pytest'],                        'monogram_tint' => 3],
            ['slug' => 'data/sql-shaper',            'name' => 'SQL Shaper',          'summary' => 'Translate plain-language asks into validated SQL against our warehouse.', 'category' => 'data', 'author' => 'data-eng', 'version' => '2.4.1', 'updated' => '1 week ago',   'tags' => ['sql', 'warehouse', 'snowflake'],                    'monogram_tint' => 4],
            ['slug' => 'data/csv-cleaner',           'name' => 'CSV Cleaner',         'summary' => 'Detect and fix malformed CSV rows, types, and encodings.',            'category' => 'data',     'author' => 'data-eng',   'version' => '1.5.0', 'updated' => '3 weeks ago',  'tags' => ['csv', 'etl', 'cleanup'],                           'monogram_tint' => 5],
            ['slug' => 'ops/incident-summarizer',    'name' => 'Incident Summarizer', 'summary' => 'Distill an on-call channel into a postmortem-ready timeline.',        'category' => 'ops',      'author' => 'infra',      'version' => '1.2.4', 'updated' => 'today',        'tags' => ['oncall', 'postmortem', 'slack'],                    'monogram_tint' => 0],
            ['slug' => 'ops/runbook-runner',         'name' => 'Runbook Runner',      'summary' => 'Execute a runbook step-by-step with confirmations and rollbacks.',    'category' => 'ops',      'author' => 'infra',      'version' => '0.9.3', 'updated' => '4 days ago',   'tags' => ['runbook', 'ops', 'interactive'],                    'monogram_tint' => 1],
            ['slug' => 'design/spec-writer',         'name' => 'Spec Writer',         'summary' => 'Turn a Figma frame and notes into an engineering spec.',              'category' => 'design',   'author' => 'design-sys', 'version' => '2.0.1', 'updated' => '6 days ago',   'tags' => ['figma', 'spec', 'handoff'],                         'monogram_tint' => 2],
            ['slug' => 'design/copy-critic',         'name' => 'Copy Critic',         'summary' => 'Audit UI copy for tone, clarity, and brand voice consistency.',       'category' => 'design',   'author' => 'design-sys', 'version' => '1.3.0', 'updated' => '2 weeks ago',  'tags' => ['voice', 'ux-writing', 'audit'],                    'monogram_tint' => 3],
            ['slug' => 'support/ticket-triage',      'name' => 'Ticket Triage',       'summary' => 'Classify, tag, and route inbound support tickets.',                   'category' => 'support',  'author' => 'support-ai', 'version' => '3.0.4', 'updated' => 'yesterday',    'tags' => ['zendesk', 'routing', 'classification'],             'monogram_tint' => 4],
            ['slug' => 'support/draft-reply',        'name' => 'Draft Reply',         'summary' => 'Compose first-pass support replies grounded in the help center.',     'category' => 'support',  'author' => 'support-ai', 'version' => '2.1.0', 'updated' => '1 week ago',   'tags' => ['rag', 'support', 'drafting'],                       'monogram_tint' => 5],
            ['slug' => 'comms/exec-brief',           'name' => 'Exec Brief',          'summary' => 'Compress a long thread into a 5-bullet brief for leadership.',        'category' => 'comms',    'author' => 'platform',   'version' => '1.4.0', 'updated' => '3 days ago',   'tags' => ['brief', 'exec', 'summarize'],                       'monogram_tint' => 0],
            ['slug' => 'comms/meeting-notes',        'name' => 'Meeting Notes',       'summary' => 'Take a transcript, return decisions, owners, and follow-ups.',        'category' => 'comms',    'author' => 'platform',   'version' => '2.2.0', 'updated' => 'this morning', 'tags' => ['transcript', 'actions', 'owners'],                  'monogram_tint' => 1],
            ['slug' => 'research/competitive-scan',  'name' => 'Competitive Scan',    'summary' => 'Pull, dedupe, and compare competitor product changes weekly.',        'category' => 'research', 'author' => 'growth',     'version' => '1.1.2', 'updated' => '1 week ago',   'tags' => ['competitive', 'weekly', 'compare'],                'monogram_tint' => 2],
            ['slug' => 'data/metric-explainer',      'name' => 'Metric Explainer',    'summary' => 'Explain a metric movement using upstream cuts and segments.',         'category' => 'data',     'author' => 'data-eng',   'version' => '0.8.0', 'updated' => 'yesterday',    'tags' => ['analytics', 'rca', 'experimental'],                'monogram_tint' => 3],
            ['slug' => 'writing/blog-outliner',      'name' => 'Blog Outliner',       'summary' => 'Produce an editor-ready outline from a thesis and audience.',         'category' => 'writing',  'author' => 'growth',     'version' => '1.6.1', 'updated' => '5 days ago',   'tags' => ['outline', 'blog', 'marketing'],                    'monogram_tint' => 4],
            ['slug' => 'ml-core/eval-runner',        'name' => 'Eval Runner',         'summary' => 'Run an evaluation suite against any model and stream results.',       'category' => 'coding',   'author' => 'ml-core',    'version' => '5.2.0', 'updated' => 'today',        'tags' => ['evals', 'ml', 'regression'],                        'monogram_tint' => 5],
        ];

        $authors = Author::pluck('id', 'slug');
        $categories = Category::pluck('id', 'slug');

        foreach ($raw as $s) {
            $skill = Skill::updateOrCreate(
                ['slug' => $s['slug']],
                [
                    'name' => $s['name'],
                    'summary' => $s['summary'],
                    'author_id' => $authors[$s['author']],
                    'version' => $s['version'],
                    'installs' => 1000,
                    'monogram_tint' => $s['monogram_tint'],
                    'tags' => $s['tags'],
                    'readme' => $readme($s['name'], $s['summary']),
                    'usage' => $usage($s['slug']),
                    'updated_at' => $updatedAt($s['updated']),
                ]
            );
            $skill->categories()->sync([$categories[$s['category']]]);

            $skill->changelogEntries()->delete();
            foreach ($changelog as $entry) {
                $skill->changelogEntries()->create($entry);
            }

            $skill->files()->delete();
            foreach ($files as $i => $file) {
                $skill->files()->create(array_merge($file, ['sort_order' => $i]));
            }
        }

        // ── Enki skill (real downloadable skill, not demo data) ──────────────

        $enkiSkillMd = view('skills.enki.readme')->render();
        $enkiUsage = view('skills.enki.usage')->render();

        $enkiSkill = Skill::updateOrCreate(
            ['slug' => 'enki'],
            [
                'name'          => 'Enki',
                'summary'       => 'Install skills from the library by name. Gives your agent the `/enki add` command.',
                'author_id'     => $authors['platform'],
                'version'       => '1.0.0',
                'installs'      => 0,
                'monogram_tint' => 0,
                'tags'          => ['skills', 'install', 'library'],
                'readme'        => $enkiSkillMd,
                'usage'         => $enkiUsage,
                'updated_at'    => now(),
            ]
        );
        $enkiSkill->categories()->sync([$categories['ops']]);

        $content = $enkiSkill->getContent();
        $content->deleteAll();
        $content->put('SKILL.md', $enkiSkillMd);

        $enkiSkill->files()->delete();
        $enkiSkill->files()->create(['path' => 'SKILL.md', 'size' => strlen($enkiSkillMd).' B', 'kind' => 'md', 'sort_order' => 0]);
    }
}
