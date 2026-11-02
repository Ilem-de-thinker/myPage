<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Actions\RecordAdminAction;
use App\Domains\Admin\Models\Policy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PolicyAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.policies.index', [
            'policies' => Policy::orderBy('slug')->get(),
        ]);
    }

    public function edit(Policy $policy): View
    {
        return view('admin.policies.edit', [
            'policy' => $policy,
            'sectionsJson' => json_encode($policy->sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(Request $request, Policy $policy, RecordAdminAction $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:500'],
            'version' => ['required', 'string', 'max:32'],
            'effective_at' => ['nullable', 'date'],
            'sections' => ['required', 'string'],
            'published' => ['sometimes', 'boolean'],
            'is_draft' => ['sometimes', 'boolean'],
        ]);

        $sections = json_decode($data['sections'], true);

        if (! is_array($sections) || $sections === []) {
            throw ValidationException::withMessages([
                'sections' => 'Sections must be a non-empty JSON array of heading/body objects.',
            ]);
        }

        foreach ($sections as $index => $section) {
            if (! is_array($section) || ! filled($section['heading'] ?? null) || ! filled($section['body'] ?? null)) {
                throw ValidationException::withMessages([
                    'sections' => "Section {$index} needs a heading and a body.",
                ]);
            }
        }

        $published = $request->boolean('published');

        $policy->fill([
            'title' => $data['title'],
            'summary' => $data['summary'],
            'version' => $data['version'],
            'effective_at' => $data['effective_at'] ?? null,
            'sections' => array_map(fn (array $section): array => [
                'heading' => (string) $section['heading'],
                'body' => (string) $section['body'],
            ], $sections),
            'published_at' => $published ? ($policy->published_at ?? now()) : null,
            'is_draft' => $request->boolean('is_draft'),
        ])->save();

        $audit->handle($request->user(), 'policy.updated', $policy, "Version {$policy->version}");

        return redirect()
            ->route('admin.policies.edit', $policy)
            ->with('status', 'Policy saved.');
    }
}
