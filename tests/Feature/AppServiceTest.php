<?php

use App\Services\AppService;

test('app name comes from config and falls back to enki', function (): void {
    config(['app.name' => 'skillhound']);
    expect(app(AppService::class)->getAppName())->toBe('skillhound');

    config(['app.name' => null]);
    expect(app(AppService::class)->getAppName())->toBe('enki');
});

test('app slug defaults to the slug of the app name', function (): void {
    config(['app.name' => 'Skill Hound', 'app.machine_name' => null]);
    expect(app(AppService::class)->getAppSlug())->toBe('skill-hound');
});

test('app slug can be overridden independently of the name', function (): void {
    config(['app.name' => 'Skill Hound', 'app.machine_name' => 'sh']);
    expect(app(AppService::class)->getAppSlug())->toBe('sh');
});
