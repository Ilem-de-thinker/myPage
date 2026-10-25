<?php

namespace App\Domains\Directory\Jobs;

use App\Domains\Ai\Models\AiEmbedding;
use App\Domains\Ai\Services\EmbeddingService;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncDirectorySearchIndex implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $modelClass, public int $modelId) {}

    public function handle(EmbeddingService $embeddings): void
    {
        /** @var Model|null $model */
        $model = $this->modelClass::query()->find($this->modelId);
        if (! $model || ! $this->isPublic($model)) {
            $morphClass = $model?->getMorphClass() ?? (new $this->modelClass)->getMorphClass();
            AiEmbedding::query()->where('embeddable_type', $morphClass)->where('embeddable_id', $this->modelId)->delete();

            return;
        }

        $content = match (true) {
            $model instanceof StorePage => implode("\n", array_filter([$model->name, $model->tagline, $model->bio, $model->city, $model->state])),
            $model instanceof Product => implode("\n", array_filter([$model->name, $model->description, $model->category?->name, $model->storePage?->name, $model->storePage?->city])),
            $model instanceof Service => implode("\n", array_filter([$model->name, $model->description, $model->storePage?->name, $model->storePage?->city])),
            default => '',
        };

        $embeddings->index($model, $content, $model->seller_id ?? $model->storePage?->seller_id);
    }

    private function isPublic(Model $model): bool
    {
        return match (true) {
            $model instanceof StorePage => $model->isDiscoverable(),
            $model instanceof Product => $model->is_directory_listed && $model->status === 'active' && $model->storePage?->isDiscoverable(),
            $model instanceof Service => $model->is_directory_listed && $model->is_active && $model->storePage?->isDiscoverable(),
            default => false,
        };
    }
}
