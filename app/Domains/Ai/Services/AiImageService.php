<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiDraft;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AiImageService
{
    /**
     * Store an uploaded image in the merchant's AI staging area.
     *
     * Staging files stay on the public disk so web and queue workers read the
     * same copy. They are copied again when a draft is applied, which keeps the
     * draft preview intact after image processing moves the product file.
     */
    public function stage(UploadedFile $file, StorePage $page, string $folder = 'staging'): string
    {
        $path = $file->store("ai/{$page->getKey()}/{$folder}", 'public');

        if (! is_string($path)) {
            throw new RuntimeException('The uploaded image could not be stored. Please try again.');
        }

        return $path;
    }

    public function exists(string $relativePath): bool
    {
        return $relativePath !== '' && Storage::disk('public')->exists($relativePath);
    }

    /**
     * Copy a staged draft image into the product's permanent directory.
     */
    public function attachToProduct(Product $product, string $relativePath, int $position): ?ProductImage
    {
        $target = $this->copyToDirectory($relativePath, "products/{$product->getKey()}");

        if ($target === null) {
            return null;
        }

        return $product->images()->create(['path' => $target, 'position' => $position]);
    }

    /**
     * Copy a staged draft image into the service's permanent directory.
     */
    public function attachToService(Service $service, string $relativePath): ?string
    {
        return $this->copyToDirectory($relativePath, "booking/{$service->store_page_id}/services");
    }

    /**
     * Delete only files this draft owns in its tenant staging directory.
     *
     * Older drafts used a shared `ai/listing-inputs` directory; those files are
     * deliberately left alone because other drafts may still reference them.
     */
    public function deleteDraftMedia(AiDraft $draft): void
    {
        $prefix = "ai/{$draft->store_page_id}/staging/";

        foreach ((array) ($draft->payload['_media_relative_paths'] ?? []) as $path) {
            if (is_string($path) && str_starts_with($path, $prefix)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * @param  list<string>  $relativePaths
     */
    public function deleteMany(array $relativePaths): void
    {
        foreach (array_unique($relativePaths) as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function copyToDirectory(string $relativePath, string $directory): ?string
    {
        if (! $this->exists($relativePath)) {
            return null;
        }

        $extension = Str::lower(pathinfo($relativePath, PATHINFO_EXTENSION)) ?: 'jpg';
        $base = Str::slug(pathinfo($relativePath, PATHINFO_FILENAME)) ?: 'image';
        $target = trim($directory, '/').'/'.$base.'-'.Str::lower(Str::random(8)).'.'.$extension;

        if (! Storage::disk('public')->copy($relativePath, $target)) {
            return null;
        }

        return $target;
    }
}
