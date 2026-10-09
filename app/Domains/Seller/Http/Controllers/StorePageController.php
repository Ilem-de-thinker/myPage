<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Seller\Models\StoreSection;
use App\Domains\Seller\Services\SlugResolver;
use App\Shared\Support\Html;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class StorePageController extends Controller
{
    public function edit(Request $request): View
    {
        $page = $request->user()->storePages()->with('sections')->firstOrFail();

        return view('dashboard.page-editor', ['page' => $page]);
    }

    public function update(Request $request, SlugResolver $resolver): RedirectResponse
    {
        $page = $request->user()->storePages()->firstOrFail();

        $validated = $request->validate([
            'sections' => ['array'],
            'sections.*.id' => ['required', 'integer'],
            'sections.*.enabled' => ['sometimes', 'boolean'],
            'sections.*.heading' => ['nullable', 'string', 'max:255'],
            'sections.*.body' => ['nullable', 'string'],
            'sections.*.image' => ['nullable', 'file', 'image', 'max:4096'],
        ]);

        $pagePositions = [];

        foreach ($validated['sections'] ?? [] as $index => $row) {
            $section = StoreSection::where('store_page_id', $page->id)->find($row['id']);
            if (! $section) {
                continue;
            }

            $pagePositions[$section->page] = ($pagePositions[$section->page] ?? 0) + 1;

            $content = $section->content ?? [];
            $content['heading'] = $row['heading'] ?? ($content['heading'] ?? null);
            $content['body'] = Html::sanitize($row['body'] ?? ($content['body'] ?? ''));

            if ($request->hasFile("sections.{$index}.image")) {
                $content['image'] = $request->file("sections.{$index}.image")->store("sections/{$section->id}", 'public');
            }

            $section->update([
                'is_enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'position' => $pagePositions[$section->page] - 1,
                'content' => $content,
            ]);
        }

        $resolver->flush($page->slug);

        return back()->with('status', 'Page updated.');
    }
}
