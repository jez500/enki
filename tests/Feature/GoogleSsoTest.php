<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

function mockSocialiteUser(string $email, string $name, string $id = '12345'): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);
    $socialiteUser->shouldReceive('getId')->andReturn($id);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('google')->andReturn($provider);

    app()->instance(SocialiteFactory::class, $socialite);
}

test('google redirect route redirects to google', function (): void {
    $response = $this->get(route('auth.google'));

    $response->assertRedirect();

    expect($response->headers->get('Location'))->toContain('accounts.google.com');
})->skip('requires real Google credentials');

test('callback creates a new user and logs them in', function (): void {
    config(['services.google.allowed_domains' => []]);
    mockSocialiteUser('jane@example.com', 'Jane Doe');

    $response = $this->get(route('auth.google.callback'));

    $this->assertAuthenticated();
    $response->assertRedirect(config('fortify.home'));

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Jane Doe');
    expect($user->google_id)->toBe('12345');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->password)->toBeNull();
});

test('callback logs in an existing user and links their google id', function (): void {
    config(['services.google.allowed_domains' => []]);
    $user = User::factory()->create(['email' => 'existing@example.com', 'google_id' => null]);

    mockSocialiteUser('existing@example.com', 'Existing User', 'google-99');

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-99');
});

test('callback does not overwrite existing email_verified_at', function (): void {
    $verifiedAt = now()->subDay();
    User::factory()->create([
        'email' => 'verified@example.com',
        'email_verified_at' => $verifiedAt,
    ]);

    mockSocialiteUser('verified@example.com', 'Verified User');

    $this->get(route('auth.google.callback'));

    $user = User::where('email', 'verified@example.com')->first();
    expect($user->email_verified_at->toDateString())->toBe($verifiedAt->toDateString());
});

test('callback rejects emails from non-allowed domains', function (): void {
    config(['services.google.allowed_domains' => ['allowed.com']]);

    mockSocialiteUser('user@blocked.com', 'Blocked User');

    $response = $this->get(route('auth.google.callback'));

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['email']);
});

test('callback allows emails from allowed domains', function (): void {
    config(['services.google.allowed_domains' => ['allowed.com']]);

    mockSocialiteUser('user@allowed.com', 'Allowed User');

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticated();
});

test('callback allows all domains when allowed_domains is empty', function (): void {
    config(['services.google.allowed_domains' => []]);

    mockSocialiteUser('user@anydomain.org', 'Any User');

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticated();
});

test('login page shows sso button when google credentials are configured', function (): void {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-secret',
    ]);

    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/Login')
        ->where('ssoEnabled', true)
        ->where('canRegister', false)
        ->where('canResetPassword', false)
    );
});

test('login page shows traditional form when google credentials are not configured', function (): void {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    $response = $this->get(route('login'));

    $response->assertInertia(fn ($page) => $page
        ->where('ssoEnabled', false)
        ->where('canRegister', true)
    );
});

test('register route redirects to google when sso is enabled', function (): void {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-secret',
    ]);

    $response = $this->get(route('register'));

    $response->assertRedirect(route('auth.google'));
});

test('forgot password route redirects to google when sso is enabled', function (): void {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-secret',
    ]);

    $response = $this->get(route('password.request'));

    $response->assertRedirect(route('auth.google'));
});

test('authenticated users cannot access google sso routes', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('auth.google'));

    $response->assertRedirect();

    expect($response->headers->get('Location'))->not->toBe(route('auth.google'));
});
