<?php

namespace App\Domains\Seller\Actions;

use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class CreateStorePage
{
    public function handle(Seller $seller, array $data): StorePage
    {
        return DB::transaction(function () use ($seller, $data) {
            $theme = Theme::from($data['theme']);

            $page = $seller->storePages()->create([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'theme' => $theme,
                'brand_color' => $data['brand_color'] ?? '#16a34a',
                'whatsapp_number' => isset($data['whatsapp']) ? PhoneNumber::normalize($data['whatsapp']) : null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'bio' => $data['bio'] ?? null,
            ]);

            foreach ($theme->pages() as $pageKey => $pageDef) {
                foreach ($pageDef['sections'] as $i => $key) {
                    $page->sections()->create(['page' => $pageKey, 'key' => $key, 'position' => $i, 'is_enabled' => true]);
                }
            }

            return $page;
        });
    }
}
