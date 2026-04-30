<?php

use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guests are redirected from the help page', function () {
    $this->get(route('help'))->assertRedirect(route('login'));
});

test('authenticated users can view the help page with readme by default', function () {
    $this->actingAs($this->user)
        ->get(route('help'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Help')
            ->where('currentDoc', 'README')
            ->has('docs')
            ->has('content')
        );
});

test('it renders a specific doc', function () {
    $this->actingAs($this->user)
        ->get(route('help', ['doc' => 'SSO_SETUP']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentDoc', 'SSO_SETUP')
        );
});

test('it returns 404 for a missing doc', function () {
    $this->actingAs($this->user)
        ->get(route('help', ['doc' => 'NonExistent']))
        ->assertNotFound();
});

test('it returns 404 for path traversal attempts', function () {
    $this->actingAs($this->user)
        ->get('/help/..%2FREADME')
        ->assertNotFound();
});

test('doc list includes all md files with formatted titles', function () {
    $this->actingAs($this->user)
        ->get(route('help'))
        ->assertInertia(fn ($page) => $page
            ->has('docs')
            ->where('docs.0.slug', 'README')
            ->where('docs.0.title', 'README')
        );
});

test('readme appears first in the doc list', function () {
    $this->actingAs($this->user)
        ->get(route('help'))
        ->assertInertia(fn ($page) => $page
            ->where('docs.0.slug', 'README')
        );
});

test('content is rendered as html', function () {
    $this->actingAs($this->user)
        ->get(route('help'))
        ->assertInertia(fn ($page) => $page
            ->where('content', fn ($content) => str_contains($content, '<'))
        );
});
