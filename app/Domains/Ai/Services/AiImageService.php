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
     * Staging files live on the private ('local') disk until the product or
     * service goes live: unpublished photos must not be reachable by URL.
     * When a draft is applied the files are copied into the public disk and
     * the permanent directory; only those copies are public.
     */
    public function stage(UploadedFile $file, StorePage $page, string $folder = 'staging'): string
    {
        $path = $file->store("ai/{$page->getKey()}/{$folder}", 'local');

        if (! is_string($path)) {
            throw new RuntimeException('The uploaded image could not be stored. Please try again.');
        }

        return $path;
    }

    public function exists(string $relativePath): bool
    {
        return $relativePath !== '' && Storage::disk('local')->exists($relativePath);
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
                Storage::disk('local')->delete($path);
            }
        }
    }

    /**
     * Delete staging originals no draft has claimed for a while.
     *
     * Apply and delete clean their own files, but a merchant who abandons a
     * draft leaves its originals on the private disk forever. The TTL is
     * deliberately generous (a week by default) so a slow merchant does not
     * lose the images behind a draft they still intend to use.
     */
    public function purgeOrphanedStaging(?int $hours = null): int
    {
        $hours = max(1, $hours ?? (int) config('ai.limits.staging_ttl_hours', 168));
        $cutoff = now()->subHours($hours)->getTimestamp();
        $disk = Storage::disk('local');
        $deleted = 0;

        foreach ($disk->allFiles('ai') as $path) {
            if (! str_contains($path, '/staging/')) {
                continue;
            }

            if ($disk->lastModified($path) > $cutoff) {
                continue;
            }

            $disk->delete($path);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * @param  list<string>  $relativePaths
     */
    public function deleteMany(array $relativePaths): void
    {
        foreach (array_unique($relativePaths) as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private function copyToDirectory(string $relativePath, string $directory): ?string
    {
        if (! Storage::disk('local')->exists($relativePath)) {
            return null;
        }

        $extension = Str::lower(pathinfo($relativePath, PATHINFO_EXTENSION)) ?: 'jpg';
        $base = Str::slug(pathinfo($relativePath, PATHINFO_FILENAME)) ?: 'image';
        $target = trim($directory, '/').'/'.$base.'-'.Str::lower(Str::random(8)).'.'.$extension;

        // Private staging → public catalogue: the permanent copy lands in the
        // public disk; the staging original stays private until deleted.
        if (! Storage::disk('public')->put($target, Storage::disk('local')->get($relativePath))) {
            return null;
        }

        return $target;
    }
}
