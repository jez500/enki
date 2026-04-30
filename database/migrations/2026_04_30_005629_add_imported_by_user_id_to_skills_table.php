<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->foreignId('imported_by_user_id')->nullable()->after('github_url')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\User::class, 'imported_by_user_id');
            $table->dropColumn('imported_by_user_id');
        });
    }
};
