<?php

namespace App\Domains\Catalog\Jobs;

use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $imageId) {}

    public function handle(): void
    {
        $image = ProductImage::query()->find($this->imageId);

        if (! $image || ! Storage::disk('public')->exists($image->path)) {
            return;
        }

        $contents = Storage::disk('public')->get($image->path);
        $source = @imagecreatefromstring($contents);

        if (! $source) {
            return;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $processed = $this->resize($source, $width, $height, 1600);
        $thumbnail = $this->resize($source, $width, $height, 480);
        $originalPath = $image->path;
        $directory = 'products/'.$image->product_id;
        $base = pathinfo($image->path, PATHINFO_FILENAME);
        $processedPath = $directory.'/'.$base.'.webp';
        $thumbnailPath = $directory.'/'.$base.'-thumb.webp';
        $this->storeWebp($processed, $processedPath);
        $this->storeWebp($thumbnail, $thumbnailPath);

        $image->update([
            'path' => $processedPath,
            'thumbnail_path' => $thumbnailPath,
            'width' => imagesx($processed),
            'height' => imagesy($processed),
            'processed_at' => now(),
        ]);

        imagedestroy($source);
        imagedestroy($processed);
        imagedestroy($thumbnail);

        if ($originalPath !== $processedPath) {
            Storage::disk('public')->delete($originalPath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }

    private function resize(\GdImage $source, int $width, int $height, int $max): \GdImage
    {
        $scale = min(1, $max / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function storeWebp(\GdImage $image, string $path): void
    {
        ob_start();
        imagewebp($image, null, 82);
        $contents = ob_get_clean();

        Storage::disk('public')->put($path, $contents);
    }
}
