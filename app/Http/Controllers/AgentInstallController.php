<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgentInstallController extends Controller
{
    public function show(): Response
    {
        $user = auth()->user();

        return Inertia::render('help/AgentInstall', [
            'docs' => HelpController::listDocs(),
            'apiToken' => $user->api_token,
            'appUrl' => config('app.url'),
        ]);
    }

    public function regenerateToken(): JsonResponse
    {
        $user = auth()->user();
        $user->update(['api_token' => Str::random(64)]);

        return response()->json(['api_token' => $user->api_token]);
    }
}
