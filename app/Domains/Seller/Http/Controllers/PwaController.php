<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\StoreUrl;
use App\Shared\Support\ThemeTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PwaController extends Controller
{
    public function merchantManifest(StorePage $page): JsonResponse
    {
        $tokens = ThemeTokens::forPage($page)['vars'];

        return $this->json([
            'id' => '/',
            'name' => $page->name,
            'short_name' => Str::limit($page->name, 12, ''),
            'description' => $page->tagline ?: 'Visit '.$page->name.' on mypage.ng.',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => $tokens['--brand'],
            'background_color' => $tokens['--paper'],
            'icons' => $this->icons('storefront.pwa.icon', $page->slug),
        ]);
    }

    public function platformManifest(): JsonResponse
    {
        return $this->json([
            'id' => '/',
            'name' => 'mypage.ng',
            'short_name' => 'mypage.ng',
            'description' => 'Sovereign storefronts for Nigerian businesses.',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => '#15803d',
            'background_color' => '#ffffff',
            'icons' => $this->icons('pwa.icon'),
        ]);
    }

    public function merchantServiceWorker(StorePage $page): Response
    {
        return $this->worker($page->slug, StoreUrl::path('storefront.pwa.offline', ['slug' => $page->slug]));
    }

    public function platformServiceWorker(): Response
    {
        return $this->worker('platform', route('pwa.offline', [], false));
    }

    public function merchantOffline(StorePage $page): View
    {
        return view('pwa.offline', ['page' => $page]);
    }

    public function platformOffline(): View
    {
        return view('pwa.offline');
    }

    public function merchantIcon(StorePage $page, int $size = 512): Response
    {
        return $this->icon($page, $size);
    }

    public function platformIcon(int $size = 512): Response
    {
        return $this->icon(null, $size);
    }

    private function icon(?StorePage $page, int $size): Response
    {
        $size = in_array($size, [192, 512], true) ? $size : 512;
        $name = $page?->name ?? 'mypage.ng';
        $tokens = $page ? ThemeTokens::forPage($page)['vars'] : ['--brand' => '#15803d', '--brand-ink' => '#ffffff'];
        $initials = Str::upper(Str::substr(collect(preg_split('/\s+/', trim($name)))->filter()->map(fn (string $word): string => Str::substr($word, 0, 1))->take(2)->implode(''), 0, 2));
        $logo = $page?->logo_path ? $this->logo($page->logo_path) : null;
        $canvas = imagecreatetruecolor($size, $size);
        $background = imagecolorallocate($canvas, ...$this->rgb($tokens['--brand']));

        imagealphablending($canvas, true);
        imagefill($canvas, 0, 0, $background);

        if ($logo instanceof \GdImage) {
            $this->drawLogo($canvas, $logo, $size);
            imagedestroy($logo);
        } else {
            $ink = imagecolorallocate($canvas, ...$this->rgb($tokens['--brand-ink']));
            $font = 5;
            $width = imagefontwidth($font) * strlen($initials);
            $height = imagefontheight($font);
            imagestring($canvas, $font, (int) (($size - $width) / 2), (int) (($size - $height) / 2), $initials, $ink);
        }

        ob_start();
        imagepng($canvas);
        $png = ob_get_clean();
        imagedestroy($canvas);

        return response((string) $png)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    private function logo(string $path): ?\GdImage
    {
        $storage = Storage::disk('public');

        if (! $storage->exists($path)) {
            return null;
        }

        $image = @imagecreatefromstring($storage->get($path));

        return $image instanceof \GdImage ? $image : null;
    }

    private function drawLogo(\GdImage $canvas, \GdImage $logo, int $size): void
    {
        $width = imagesx($logo);
        $height = imagesy($logo);
        $scale = min(($size * 0.7) / $width, ($size * 0.7) / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $x = (int) (($size - $targetWidth) / 2);
        $y = (int) (($size - $targetHeight) / 2);

        imagealphablending($canvas, true);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $logo, $x, $y, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagealphablending($canvas, true);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /** @return list<array{src: string, sizes: string, type: string, purpose?: string}> */
    private function icons(string $route, ?string $slug = null): array
    {
        $params = $slug === null ? [] : ['slug' => $slug];

        return collect([192, 512])->map(fn (int $size): array => [
            'src' => route($route, [...$params, 'size' => $size], false),
            'sizes' => $size.'x'.$size,
            'type' => 'image/png',
            'purpose' => $size === 512 ? 'any maskable' : 'any',
        ])->all();
    }

    private function json(array $manifest): JsonResponse
    {
        return response()->json($manifest)->header('Content-Type', 'application/manifest+json');
    }

    private function worker(string $key, string $offlineUrl): Response
    {
        $version = 'mypage-pwa-v2-'.md5($key);
        $script = view('pwa.service-worker', compact('version', 'offlineUrl'))->render();

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Service-Worker-Allowed', '/')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}
