<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleSocialiteController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $allowedDomains = config('services.google.allowed_domains', []);

        if (! empty($allowedDomains)) {
            $domain = Str::after($googleUser->getEmail(), '@');

            if (! in_array($domain, $allowedDomains)) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Your email domain is not permitted to access this application.',
                ]);
            }
        }

        $user = User::firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
            ]
        );

        if (! $user->google_id) {
            $user->update(['google_id' => $googleUser->getId()]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'));
    }
}
