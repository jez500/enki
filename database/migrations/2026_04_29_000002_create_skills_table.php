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
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('name', 128);
            $table->string('summary', 255);
            $table->string('category', 64)->index();
            $table->foreignId('author_id')->constrained('authors')->cascadeOnDelete()->index();
            $table->string('version', 32);
            $table->decimal('rating', 3, 1);
            $table->unsignedInteger('ratings')->default(0);
            $table->unsignedInteger('installs')->default(0);
            $table->unsignedTinyInteger('monogram_tint')->default(0);
            $table->json('tags');
            $table->longText('readme');
            $table->longText('usage');
            $table->softDeletes();
            $table->timestamps();
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
