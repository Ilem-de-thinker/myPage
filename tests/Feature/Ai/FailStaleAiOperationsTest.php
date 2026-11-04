<?php

use App\Domains\Ai\Jobs\FailStaleAiOperations;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Ai\Services\AiUsageReservations;

it('fails an operation whose worker never came back and releases its credit', function () {
    [$seller, $page] = sellerWithPage();

    $operation = AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'status' => 'running',
        'input' => ['feature' => 'products'],
        'started_at' => now()->subHour(),
    ]);

    app(AiUsageReservations::class)->reserve($operation);

    expect(AiUsageLedger::query()->where('operation_id', $operation->id)->count())->toBe(1);

    (new FailStaleAiOperations)->handle(app(AiUsageReservations::class));

    expect($operation->fresh()->status)->toBe('failed')
        ->and($operation->fresh()->completed_at)->not->toBeNull()
        ->and(AiUsageLedger::query()->where('operation_id', $operation->id)->count())->toBe(0);
});

it('leaves a genuinely running operation and recent failures alone', function () {
    [$seller, $page] = sellerWithPage();

    $running = AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'status' => 'running',
        'input' => ['feature' => 'products'],
        'started_at' => now()->subMinutes(2),
    ]);

    $failed = AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'status' => 'failed',
        'input' => ['feature' => 'products'],
        'started_at' => now()->subHours(2),
    ]);

    (new FailStaleAiOperations)->handle(app(AiUsageReservations::class));

    expect($running->fresh()->status)->toBe('running')
        ->and($failed->fresh()->status)->toBe('failed');
});
