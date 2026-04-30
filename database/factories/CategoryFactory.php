<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->word();

        return [
            'slug' => $slug,
            'label' => ucfirst($slug),
            'icon' => 'M8 2a6 6 0 1 0 0 12A6 6 0 0 0 8 2z',
            'color' => ['bg' => '#e6e3da', 'fg' => '#3f3a2c'],
        ];
    }
}
