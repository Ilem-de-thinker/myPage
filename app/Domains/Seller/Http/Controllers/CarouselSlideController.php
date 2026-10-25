<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Seller\Models\CarouselSlide;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class CarouselSlideController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $page = $this->page($request);
        $data = $request->validate($this->rules());

        $slide = new CarouselSlide($this->attributes($data));
        $slide->store_page_id = $page->getKey();
        $slide->position = (int) $page->carouselSlides()->max('position') + 1;
        $slide->image_path = $request->file('image')->store("storefront/{$page->getKey()}/carousel", 'public');
        $slide->save();

        return back()->with('status', 'Carousel slide added.');
    }

    public function update(Request $request, CarouselSlide $slide): RedirectResponse
    {
        $page = $this->page($request);
        abort_unless($slide->store_page_id === $page->getKey(), 404);

        if ($request->boolean('remove')) {
            return $this->destroy($request, $slide);
        }

        $data = $request->validate($this->rules(false));
        $slide->fill($this->attributes($data));

        if ($request->hasFile('image')) {
            if ($slide->image_path) {
                Storage::disk('public')->delete($slide->image_path);
            }

            $slide->image_path = $request->file('image')->store("storefront/{$page->getKey()}/carousel", 'public');
        }

        $slide->is_active = $request->boolean('is_active');
        $slide->save();

        return back()->with('status', 'Carousel slide updated.');
    }

    public function destroy(Request $request, CarouselSlide $slide): RedirectResponse
    {
        $page = $this->page($request);
        abort_unless($slide->store_page_id === $page->getKey(), 404);

        if ($slide->image_path) {
            Storage::disk('public')->delete($slide->image_path);
        }

        $slide->delete();

        return back()->with('status', 'Carousel slide removed.');
    }

    public function move(Request $request, CarouselSlide $slide, string $direction): RedirectResponse
    {
        $page = $this->page($request);
        abort_unless($slide->store_page_id === $page->getKey(), 404);
        abort_unless(in_array($direction, ['up', 'down'], true), 404);

        $slides = $page->carouselSlides()->get()->values();
        $index = $slides->search(fn (CarouselSlide $candidate): bool => $candidate->is($slide));
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $slides->has($target)) {
            return back();
        }

        $other = $slides->get($target);
        [$slide->position, $other->position] = [$other->position, $slide->position];
        $slide->save();
        $other->save();

        return back()->with('status', 'Carousel order updated.');
    }

    /** @return array<string, list<string>> */
    private function rules(bool $imageRequired = true): array
    {
        return [
            'headline' => ['required', 'string', 'max:160'],
            'subtext' => ['nullable', 'string', 'max:300'],
            'button_label' => ['nullable', 'string', 'max:80'],
            'button_url' => ['nullable', 'url', 'max:500'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $data */
    private function attributes(array $data): array
    {
        return [
            'headline' => trim($data['headline']),
            'subtext' => trim((string) ($data['subtext'] ?? '')) ?: null,
            'button_label' => trim((string) ($data['button_label'] ?? '')) ?: null,
            'button_url' => trim((string) ($data['button_url'] ?? '')) ?: null,
        ];
    }

    private function page(Request $request): StorePage
    {
        return $request->user()->storePages()->firstOrFail();
    }
}
