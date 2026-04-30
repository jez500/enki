<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'comms',    'label' => 'Comms'],
            ['slug' => 'coding',   'label' => 'Coding'],
            ['slug' => 'data',     'label' => 'Data'],
            ['slug' => 'design',   'label' => 'Design'],
            ['slug' => 'ops',      'label' => 'Ops'],
            ['slug' => 'research', 'label' => 'Research'],
            ['slug' => 'support',  'label' => 'Support'],
            ['slug' => 'writing',  'label' => 'Writing'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
