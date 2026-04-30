<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AgentInstallController;
use App\Http\Controllers\Auth\GoogleSocialiteController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EnkiController;
use App\Http\Controllers\HelpController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/welcome', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('welcome');

Route::redirect('/', '/enki')->name('home');

Route::middleware('auth.token')->group(function () {
    Route::get('/api/skills', [EnkiController::class, 'apiIndex'])->name('api.skills.index');
    Route::get('/api/categories', [EnkiController::class, 'apiCategories'])->name('api.categories.index');
    Route::get('/api/skills/{slug}/download', [EnkiController::class, 'download'])
        ->where('slug', '.+')
        ->name('api.skills.download');
    Route::get('/api/skills/{slug}', [EnkiController::class, 'apiShow'])
        ->where('slug', '.+')
        ->name('api.skills.show');
});

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleSocialiteController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleSocialiteController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('/enki', [EnkiController::class, 'index'])->name('enki');
    Route::post('/enki/skills', [EnkiController::class, 'store'])->name('enki.skills.store');
    Route::post('/enki/skills/import', [EnkiController::class, 'import'])->name('enki.import');
    Route::get('/enki/skills/{slug}/download', [EnkiController::class, 'download'])
        ->where('slug', '.+')
        ->name('enki.skill.download');
    Route::get('/enki/skills/{slug}', [EnkiController::class, 'skill'])
        ->where('slug', '.+')
        ->name('enki.skill');
    Route::post('/enki/skills/{slug}/sync', [EnkiController::class, 'sync'])
        ->where('slug', '.+')
        ->name('enki.skill.sync');
    Route::post('/enki/skills/{slug}/star', [EnkiController::class, 'star'])
        ->where('slug', '.+')
        ->name('enki.skill.star');
    Route::post('/enki/skills/{slug}', [EnkiController::class, 'update'])
        ->where('slug', '.+')
        ->name('enki.skill.update');
    Route::delete('/enki/skills/{slug}', [EnkiController::class, 'destroy'])
        ->where('slug', '.+')
        ->name('enki.skill.destroy');
    Route::apiResource('categories', CategoryController::class)->except('show');

    Route::get('/help/agent-install', [AgentInstallController::class, 'show'])->name('help.agent-install');
    Route::post('/help/agent-install/token', [AgentInstallController::class, 'regenerateToken'])->name('help.agent-install.token');
    Route::get('/help/{doc?}', [HelpController::class, 'show'])->name('help');

    Route::prefix('enki/admin')->name('enki.admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.updateRole');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/settings.php';
