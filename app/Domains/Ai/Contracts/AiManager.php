<?php

namespace App\Domains\Ai\Contracts;

use App\Domains\Seller\Models\StorePage;

interface AiManager
{
    public function run(string $task, array $input, ?StorePage $page = null): string|array;

    /**
     * Run a buyer-facing task. The platform pays for these calls, so they never
     * consume the merchant's monthly Mercho credits; the caller caps them per
     * store instead.
     */
    public function runPlatform(string $task, array $input, StorePage $page): string|array;
}
