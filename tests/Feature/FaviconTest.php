<?php

test('the app layout links the favicon files', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="/favicon.ico"', false)
        ->assertSee('href="/favicon.svg"', false)
        ->assertSee('href="/apple-touch-icon.png"', false);
});

test('the svg favicon uses the enki logo on an accent badge', function (): void {
    $svg = file_get_contents(public_path('favicon.svg'));

    expect($svg)
        ->toContain('M4 7l8-4 8 4M4 7v10l8 4 8-4V7M4 7l8 4M20 7l-8 4M12 11v10')
        ->toContain('fill="#3d6b4a"');
});

test('the raster favicons exist', function (string $file): void {
    expect(public_path($file))->toBeFile();
})->with(['favicon.ico', 'apple-touch-icon.png']);
