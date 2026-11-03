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
            'version' => ['required', 'date_format:Y-m-d'],
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
        $isDraft = $request->boolean('is_draft');

        // A published version must never move backwards: consent is recorded
        // against the version string, and an older number would make sellers
        // who accepted the newer document look out of date forever.
        if ($published && ! $isDraft) {
            $publishedVersion = Policy::query()
                ->where('slug', $policy->slug)
                ->where('is_draft', false)
                ->whereNotNull('published_at')
                ->pluck('version')
                ->filter(fn ($version): bool => is_string($version) && $version !== '')
                ->max();

            if (is_string($publishedVersion) && $publishedVersion !== '' && $data['version'] < $publishedVersion) {
                throw ValidationException::withMessages([
                    'version' => "The published version is {$publishedVersion}; a new version cannot be older.",
                ]);
            }
        }

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
            'is_draft' => $isDraft,
        ])->save();

        $audit->handle($request->user(), 'policy.updated', $policy, "Version {$policy->version}");

        return redirect()
            ->route('admin.policies.edit', $policy)
            ->with('status', 'Policy saved.');
    }
}
