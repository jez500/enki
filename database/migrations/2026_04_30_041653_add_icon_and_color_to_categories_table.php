<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @return array<string, array{icon: string, color: array{bg: string, fg: string}}> */
    private function categoryData(): array
    {
        return [
            'comms' => [
                'icon' => 'M2.5 2.5h11v8h-4.5l-2 2.5-2-2.5h-2.5v-8z',
                'color' => ['bg' => '#d4e2f0', 'fg' => '#1e3d6b'],
            ],
            'coding' => [
                'icon' => 'M5.5 5l-3 3 3 3M10.5 5l3 3-3 3M9 3.5l-2 9',
                'color' => ['bg' => '#cde8d4', 'fg' => '#1a4a28'],
            ],
            'data' => [
                'icon' => 'M8 3c-3.3 0-5.5 1.1-5.5 2.5v5C2.5 11.9 4.7 13 8 13s5.5-1.1 5.5-2.5v-5C13.5 4.1 11.3 3 8 3zM2.5 5.5C2.5 6.9 4.7 8 8 8s5.5-1.1 5.5-2.5M2.5 9C2.5 10.4 4.7 11.5 8 11.5S13.5 10.4 13.5 9',
                'color' => ['bg' => '#f0e0a0', 'fg' => '#5a3c00'],
            ],
            'design' => [
                'icon' => 'M10.5 2l3.5 3.5-7.5 7.5H3v-3.5L10.5 2zM8.5 4l3 3M3 12.5l-.5 1',
                'color' => ['bg' => '#dfd4f0', 'fg' => '#3a1e6b'],
            ],
            'ops' => [
                'icon' => 'M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM8 1.5v2M8 12.5v2M1.5 8h2M12.5 8h2M3.6 3.6l1.4 1.4M11 11l1.4 1.4M11 5l1.4-1.4M3.6 12.4l1.4-1.4',
                'color' => ['bg' => '#cdd8e8', 'fg' => '#1e304a'],
            ],
            'research' => [
                'icon' => 'M7 12A5 5 0 1 0 7 2a5 5 0 0 0 0 10zM14 14l-3.5-3.5',
                'color' => ['bg' => '#c4e8e4', 'fg' => '#1a4540'],
            ],
            'support' => [
                'icon' => 'M2 9V7a6 6 0 0 1 12 0v2M2.5 9h1A1.5 1.5 0 0 1 5 10.5v1A1.5 1.5 0 0 1 3.5 13H3M13.5 9h-1A1.5 1.5 0 0 0 11 10.5v1A1.5 1.5 0 0 0 12.5 13H13',
                'color' => ['bg' => '#f0d4c8', 'fg' => '#6b2a1e'],
            ],
            'writing' => [
                'icon' => 'M11.5 2.5l2 2-8.5 8.5H3v-2l8.5-8.5zM9.5 4.5l2 2',
                'color' => ['bg' => '#f0d0d8', 'fg' => '#6b1a2a'],
            ],
        ];
    }

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('icon')->nullable()->after('label');
            $table->json('color')->nullable()->after('icon');
        });

        foreach ($this->categoryData() as $slug => $data) {
            DB::table('categories')->where('slug', $slug)->update([
                'icon' => $data['icon'],
                'color' => json_encode($data['color']),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};
