<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return Inertia::render('enki/Admin', [
            'users' => User::orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role?->value ?? UserRole::Member->value,
                    'createdAt' => $u->created_at->toDateString(),
                ]),
            'roles' => collect(UserRole::cases())->map(fn ($r) => $r->value)->all(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $request->validate(['role' => ['required', 'in:admin,member']]);

        $user->update(['role' => $request->role]);

        return back();
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($user->is(auth()->user()), 403);

        $user->delete();

        return back();
    }
}
