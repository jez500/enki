<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $slugToIcon = [
        'comms' => 'MessageSquare',
        'coding' => 'Code2',
        'data' => 'Database',
        'design' => 'Pencil',
        'ops' => 'Settings',
        'research' => 'Search',
        'support' => 'Headphones',
        'writing' => 'PenLine',
    ];

    public function up(): void
    {
        foreach ($this->slugToIcon as $slug => $icon) {
            DB::table('categories')->where('slug', $slug)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        $pathBySlug = [
            'comms' => 'M2.5 2.5h11v8h-4.5l-2 2.5-2-2.5h-2.5v-8z',
            'coding' => 'M5.5 5l-3 3 3 3M10.5 5l3 3-3 3M9 3.5l-2 9',
            'data' => 'M8 3c-3.3 0-5.5 1.1-5.5 2.5v5C2.5 11.9 4.7 13 8 13s5.5-1.1 5.5-2.5v-5C13.5 4.1 11.3 3 8 3zM2.5 5.5C2.5 6.9 4.7 8 8 8s5.5-1.1 5.5-2.5M2.5 9C2.5 10.4 4.7 11.5 8 11.5S13.5 10.4 13.5 9',
            'design' => 'M10.5 2l3.5 3.5-7.5 7.5H3v-3.5L10.5 2zM8.5 4l3 3M3 12.5l-.5 1',
            'ops' => 'M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM8 1.5v2M8 12.5v2M1.5 8h2M12.5 8h2M3.6 3.6l1.4 1.4M11 11l1.4 1.4M11 5l1.4-1.4M3.6 12.4l1.4-1.4',
            'research' => 'M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM14 14l-3.5-3.5',
            'support' => 'M2 9V7a6 6 0 0 1 12 0v2M2.5 9h1A1.5 1.5 0 0 1 5 10.5v1A1.5 1.5 0 0 1 3.5 13H3M13.5 9h-1A1.5 1.5 0 0 0 11 10.5v1A1.5 1.5 0 0 0 12.5 13H13',
            'writing' => 'M11.5 2.5l2 2-8.5 8.5H3v-2l8.5-8.5zM9.5 4.5l2 2',
        ];

        foreach ($pathBySlug as $slug => $path) {
            DB::table('categories')->where('slug', $slug)->update(['icon' => $path]);
        }
    }
};
