<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('skill_changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->string('version', 32);
            $table->date('released_on');
            $table->text('notes');
            $table->index(['skill_id', 'released_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_changelog_entries');
    }
};
