<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return Inertia::render('enki/AdminCategories', [
            'categories' => Category::orderBy('label')
                ->get()
                ->map(fn (Category $c): array => [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'label' => $c->label,
                    'icon' => $c->icon,
                    'color' => $c->color,
                    'skillsCount' => $c->skills()->count(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:categories,slug'],
            'label' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'array'],
            'color.bg' => ['nullable', 'string', 'max:20'],
            'color.fg' => ['nullable', 'string', 'max:20'],
        ]);

        Category::create($data);

        return back();
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:categories,slug,'.$category->id],
            'label' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:10'],
            'color' => ['nullable', 'array'],
            'color.bg' => ['nullable', 'string', 'max:20'],
            'color.fg' => ['nullable', 'string', 'max:20'],
        ]);

        $category->update($data);

        return back();
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $category->delete();

        return back();
    }
}
