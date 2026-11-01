<?php

namespace App\Domains\Ai\Services;

use App\Domains\Admin\Models\PlatformSetting;
use Illuminate\Support\Str;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Throwable;

class AiSettings
{
    /**
     * @var array<string, string>
     */
    private const TASK_LABELS = [
        'site_builder' => 'Store builder',
        'theme_and_color_preset' => 'Theme and colours',
        'listing_from_photo' => 'Photo listings',
        'listing_photo_grouping' => 'Photo grouping',
        'listing_copy' => 'Product copy',
        'service_listing' => 'Service copy',
        'copy_rewrite' => 'Copy rewrite',
        'store_assistant' => 'Store assistant',
        'service_booking_assistant' => 'Booking assistant',
        'order_chat_assist' => 'Order chat',
        'insights_digest' => 'Insights',
        'ask_your_data' => 'Ask your data',
        'smart_search' => 'Smart search',
        'translate' => 'Translation',
        'marketplace_intent' => 'Help me buy intent',
        'marketplace_rank' => 'Help me buy ranking',
    ];

    public function enabled(): bool
    {
        return (bool) PlatformSetting::value('ai.enabled', (bool) config('ai.enabled', true));
    }

    public function provider(): ?string
    {
        $provider = PlatformSetting::value('ai.provider');

        if (! filled($provider)) {
            $provider = config('ai.provider');
        }

        return filled($provider) ? trim((string) $provider) : null;
    }

    public function model(): ?string
    {
        $model = PlatformSetting::value('ai.model');

        if (! filled($model)) {
            $model = config('ai.model');
        }

        return filled($model) ? trim((string) $model) : null;
    }

    public function defaultProvider(): string
    {
        return $this->provider() ?? (string) config('ai.tasks.site_builder.provider', 'groq');
    }

    public function defaultModel(): string
    {
        return $this->model() ?? (string) config('ai.tasks.site_builder.model', '');
    }

    public function timeout(): ?int
    {
        $timeout = PlatformSetting::value('ai.timeout');

        if (! filled($timeout)) {
            $timeout = config('ai.timeout');
        }

        return filled($timeout) ? max(1, (int) $timeout) : null;
    }

    public function timeoutFor(?int $taskTimeout = null): int
    {
        return $this->timeout() ?? $taskTimeout ?? (int) config('ai.default_timeout', 30);
    }

    public function retries(): int
    {
        $retries = PlatformSetting::value('ai.retries');

        if (! filled($retries)) {
            $retries = config('ai.retries');
        }

        if (! filled($retries)) {
            $retries = config('ai.default_retries', 1);
        }

        return max(0, (int) $retries);
    }

    /**
     * @return array<string, int>
     */
    public function limits(): array
    {
        $stored = PlatformSetting::value('ai.feature_limits');

        if (is_array($stored)) {
            $limits = $stored;
        } else {
            $limits = (array) config('ai.feature_limits', []);
        }

        return collect($limits)
            ->filter(fn (mixed $limit, string $task): bool => array_key_exists($task, (array) config('ai.tasks', [])))
            ->map(fn (mixed $limit): int => max(0, (int) $limit))
            ->all();
    }

    public function limitFor(string $task): ?int
    {
        return $this->limits()[$task] ?? null;
    }

    public function configured(): bool
    {
        return filled($this->storedKey());
    }

    public function maskedKey(): ?string
    {
        $key = $this->storedKey();

        if (! filled($key)) {
            return null;
        }

        if (mb_strlen($key) <= 8) {
            return '••••';
        }

        return mb_substr($key, 0, 4).'…'.mb_substr($key, -4);
    }

    public function apply(): void
    {
        config([
            'ai.enabled' => $this->enabled(),
            'ai.default_retries' => $this->retries(),
        ]);

        $provider = $this->provider();
        $model = $this->model();

        if ($provider !== null && filled($model)) {
            foreach (array_keys((array) config('ai.tasks', [])) as $task) {
                config([
                    "ai.tasks.{$task}.provider" => $provider,
                    "ai.tasks.{$task}.model" => $model,
                ]);
            }
        }

        $key = $this->storedKey();

        if (filled($key)) {
            config(["prism.providers.{$this->defaultProvider()}.api_key" => $key]);
        }
    }

    /**
     * @return array{enabled: bool, provider: string, model: string, timeout: int, retries: int, limits: array<string, int>, maskedKey: ?string, configured: bool, hasStoredKey: bool}
     */
    public function formValues(): array
    {
        return [
            'enabled' => $this->enabled(),
            'provider' => $this->defaultProvider(),
            'model' => $this->defaultModel(),
            'timeout' => $this->timeoutFor(),
            'retries' => $this->retries(),
            'limits' => $this->limits(),
            'maskedKey' => $this->maskedKey(),
            'configured' => $this->configured(),
            'hasStoredKey' => filled(PlatformSetting::value('ai.api_key')),
        ];
    }

    /**
     * @param  array{enabled: bool, provider: string, model: string, api_key?: ?string, timeout: int, retries: int, feature_limits?: array<string, int|string|null>}  $data
     * @return list<string>
     */
    public function save(array $data): array
    {
        $limits = collect($data['feature_limits'] ?? [])
            ->filter(fn (mixed $limit, string $task): bool => $limit !== null && $limit !== '' && array_key_exists($task, (array) config('ai.tasks', [])))
            ->map(fn (mixed $limit): int => max(0, (int) $limit))
            ->all();

        $storedProvider = PlatformSetting::value('ai.provider');
        $storedModel = PlatformSetting::value('ai.model');
        $changed = [];

        if ((bool) $data['enabled'] !== $this->enabled()) {
            $changed[] = 'ai.enabled';
        }
        if (trim((string) $data['provider']) !== trim((string) $storedProvider)) {
            $changed[] = 'ai.provider';
        }
        if (trim((string) $data['model']) !== trim((string) $storedModel)) {
            $changed[] = 'ai.model';
        }
        if ((int) $data['timeout'] !== $this->timeoutFor()) {
            $changed[] = 'ai.timeout';
        }
        if ((int) $data['retries'] !== $this->retries()) {
            $changed[] = 'ai.retries';
        }
        if ($limits !== $this->limits()) {
            $changed[] = 'ai.feature_limits';
        }

        PlatformSetting::put('ai.enabled', (bool) $data['enabled'], 'boolean');
        PlatformSetting::put('ai.provider', trim((string) $data['provider']));
        PlatformSetting::put('ai.model', trim((string) $data['model']));
        PlatformSetting::put('ai.timeout', (int) $data['timeout'], 'integer');
        PlatformSetting::put('ai.retries', (int) $data['retries'], 'integer');
        PlatformSetting::put('ai.feature_limits', $limits, 'json');

        if (filled($data['api_key'] ?? null)) {
            PlatformSetting::put('ai.api_key', (string) $data['api_key'], 'encrypted');
            $changed[] = 'ai.api_key';
        }

        return $changed;
    }

    /**
     * @return array{ok: bool, provider: string, model: string, message: string}
     */
    public function testConnection(): array
    {
        $this->apply();

        $provider = $this->defaultProvider();
        $model = $this->defaultModel();

        if (! $this->configured()) {
            return ['ok' => false, 'provider' => $provider, 'model' => $model, 'message' => 'No API key is configured.'];
        }

        try {
            $response = Prism::text()
                ->using($provider, $model)
                ->withPrompt('Reply with OK.')
                ->withMaxTokens(8)
                ->withClientOptions(['timeout' => $this->timeoutFor()])
                ->withClientRetry($this->retries(), 200)
                ->asText();

            if ($response->finishReason !== FinishReason::Stop) {
                return ['ok' => false, 'provider' => $provider, 'model' => $model, 'message' => 'The provider stopped with '.$response->finishReason->value.'.'];
            }

            return ['ok' => true, 'provider' => $provider, 'model' => $model, 'message' => 'Connection successful.'];
        } catch (Throwable $exception) {
            return ['ok' => false, 'provider' => $provider, 'model' => $model, 'message' => $this->safeError($exception)];
        }
    }

    /**
     * @return array<string, string>
     */
    public static function providers(): array
    {
        return collect(array_keys((array) config('prism.providers', [])))
            ->mapWithKeys(fn (string $provider): array => [$provider => Str::headline($provider)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function tasks(): array
    {
        return collect(array_keys((array) config('ai.tasks', [])))
            ->mapWithKeys(fn (string $task): array => [$task => self::TASK_LABELS[$task] ?? Str::headline($task)])
            ->all();
    }

    public static function taskLabel(string $task): string
    {
        return self::TASK_LABELS[$task] ?? Str::headline($task);
    }

    private function storedKey(): ?string
    {
        $key = PlatformSetting::value('ai.api_key');

        if (! filled($key)) {
            $key = config('ai.api_key');
        }

        if (! filled($key)) {
            $key = config("prism.providers.{$this->defaultProvider()}.api_key");
        }

        return filled($key) ? (string) $key : null;
    }

    private function safeError(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        $key = $this->storedKey();

        if (filled($key)) {
            $message = str_replace($key, '[redacted]', $message);
        }

        return Str::limit($message !== '' ? $message : 'The provider did not return a usable response.', 300);
    }
}
