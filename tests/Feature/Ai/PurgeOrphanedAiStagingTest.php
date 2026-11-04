<?php

use App\Domains\Ai\Jobs\PurgeOrphanedAiStaging;
use App\Domains\Ai\Services\AiImageService;
use Illuminate\Support\Facades\Storage;

it('deletes staging originals older than the TTL and keeps fresh or permanent files', function () {
    Storage::fake('local');

    $disk = Storage::disk('local');
    $old = 'ai/1/staging/old.jpg';
    $fresh = 'ai/1/staging/fresh.jpg';
    $permanent = 'ai/1/output/keep.jpg';

    $disk->put($old, 'old');
    $disk->put($fresh, 'fresh');
    $disk->put($permanent, 'permanent');

    touch($disk->path($old), now()->subDays(8)->getTimestamp());
    touch($disk->path($permanent), now()->subDays(8)->getTimestamp());

    $deleted = (new PurgeOrphanedAiStaging)->handle(app(AiImageService::class));

    expect($deleted)->toBe(1)
        ->and($disk->exists($old))->toBeFalse()
        ->and($disk->exists($fresh))->toBeTrue()
        ->and($disk->exists($permanent))->toBeTrue();
});
