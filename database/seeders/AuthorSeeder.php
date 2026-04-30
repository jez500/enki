<?php

namespace Database\Seeders;

use App\Models\Author;
use Illuminate\Database\Seeder;

class AuthorSeeder extends Seeder
{
    public function run(): void
    {
        $authors = [
            ['slug' => 'platform',   'name' => 'Platform Team',  'members' => 12],
            ['slug' => 'growth',     'name' => 'Growth Eng',      'members' => 6],
            ['slug' => 'research',   'name' => 'Research Lab',    'members' => 9],
            ['slug' => 'design-sys', 'name' => 'Design Systems',  'members' => 4],
            ['slug' => 'data-eng',   'name' => 'Data Eng',        'members' => 8],
            ['slug' => 'support-ai', 'name' => 'Support AI',      'members' => 5],
            ['slug' => 'infra',      'name' => 'Infra Guild',     'members' => 7],
            ['slug' => 'ml-core',    'name' => 'ML Core',         'members' => 11],
        ];

        foreach ($authors as $author) {
            Author::updateOrCreate(['slug' => $author['slug']], $author);
        }
    }
}
