<?php

use App\Models\User;

test('an authenticated user can view the skills library', function (): void {
    $this->actingAs(User::factory()->create());

    visit('/enki')
        ->assertSee(config('app.name'))
        ->assertSee('skills');
});

test('the skills library loads without JavaScript errors', function (): void {
    $this->actingAs(User::factory()->create());

    visit('/enki')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
