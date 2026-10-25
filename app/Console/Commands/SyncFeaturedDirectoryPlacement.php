<?php

namespace App\Console\Commands;

use App\Domains\Directory\Services\DirectoryPlacement;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Console\Command;

final class SyncFeaturedDirectoryPlacement extends Command
{
    protected $signature = 'directory:sync-featured';

    protected $description = 'Remove featured placement from stores without an eligible subscription';

    public function handle(DirectoryPlacement $placement): int
    {
        StorePage::query()->where('is_featured', true)->with('seller')->chunkById(100, function ($pages) use ($placement): void {
            foreach ($pages as $page) {
                if (! $placement->canFeature($page)) {
                    $page->updateQuietly(['is_featured' => false]);
                }
            }
        });

        return self::SUCCESS;
    }
}
