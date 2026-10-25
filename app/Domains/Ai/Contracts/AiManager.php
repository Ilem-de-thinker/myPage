<?php

namespace App\Domains\Ai\Contracts;

use App\Domains\Seller\Models\StorePage;

interface AiManager
{
    public function run(string $task, array $input, ?StorePage $page = null): string|array;
}
