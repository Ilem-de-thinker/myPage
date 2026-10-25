<?php

namespace App\Domains\Ai\Console;

use App\Domains\Ai\Contracts\AiManager;
use Illuminate\Console\Command;

class AiTestTask extends Command
{
    protected $signature = 'ai:test-task {task} {--input=}';

    protected $description = 'Run a configured AI task smoke test.';

    public function handle(AiManager $ai): int
    {
        $task = (string) $this->argument('task');
        $input = $this->option('input') ? json_decode((string) $this->option('input'), true, 512, JSON_THROW_ON_ERROR) : ['message' => 'Smoke test. Return a concise safe response.'];

        try {
            $result = $ai->run($task, is_array($input) ? $input : []);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
