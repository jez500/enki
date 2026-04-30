<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->numerify('category/skill-####'),
            'name' => ucwords(fake()->words(2, true)),
            'summary' => fake()->sentence(),
            'author_id' => Author::factory(),
            'version' => fake()->numerify('#.#.#'),
            'installs' => fake()->numberBetween(800, 8000),
            'monogram_tint' => fake()->numberBetween(0, 5),
            'tags' => fake()->words(3),
            'readme' => '# Skill'."\n\nPlaceholder readme.",
            'usage' => '## Quick start'."\n\nPlaceholder usage.",
            'updated_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /** @param array<array{version: string, released_on: string, notes: string}> $entries */
    public function withChangelog(array $entries): static
    {
        return $this->afterCreating(function (Skill $skill) use ($entries): void {
            foreach ($entries as $entry) {
                $skill->changelogEntries()->create($entry);
            }
        });
    }

    /** @param array<array{path: string, size: string, kind: string}> $files */
    public function withFiles(array $files): static
    {
        return $this->afterCreating(function (Skill $skill) use ($files): void {
            foreach ($files as $i => $file) {
                $skill->files()->create(array_merge($file, ['sort_order' => $i]));
            }
        });
    }
}
