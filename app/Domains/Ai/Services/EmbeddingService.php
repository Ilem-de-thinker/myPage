<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Models\AiEmbedding;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EmbeddingService
{
    public function configured(): bool
    {
        return (bool) config('ai.embeddings.enabled') && filled(config('ai.embeddings.api_key')) && filled(config('ai.embeddings.model')) && filled(config('ai.embeddings.url'));
    }

    public function index(Model $model, string $content, ?int $sellerId = null): ?AiEmbedding
    {
        if (! $this->configured() || trim($content) === '') {
            return null;
        }

        $content = Str::limit(trim($content), 12000, '');
        $hash = hash('sha256', $content);
        $embedding = AiEmbedding::query()->where('embeddable_type', $model->getMorphClass())->where('embeddable_id', $model->getKey())->where('content_hash', $hash)->first();
        if ($embedding !== null) {
            return $embedding;
        }

        $response = Http::withToken((string) config('ai.embeddings.api_key'))
            ->timeout((int) config('ai.embeddings.timeout', 20))
            ->retry(2, 200)
            ->post((string) config('ai.embeddings.url'), ['model' => config('ai.embeddings.model'), 'input' => $content]);
        $response->throw();

        return AiEmbedding::updateOrCreate(
            ['embeddable_type' => $model->getMorphClass(), 'embeddable_id' => $model->getKey(), 'content_hash' => $hash],
            ['seller_id' => $sellerId, 'provider' => config('ai.embeddings.provider'), 'model' => config('ai.embeddings.model'), 'content' => $content, 'vector' => $response->json('data.0.embedding', []), 'metadata' => ['indexed_at' => now()->toIso8601String()]],
        );
    }

    /** @return array<int, array{id:int, score:float}> */
    public function search(StorePage $page, string $query, int $limit = 20): array
    {
        if (! $this->configured()) {
            return [];
        }

        $response = Http::withToken((string) config('ai.embeddings.api_key'))->timeout((int) config('ai.embeddings.timeout', 20))->retry(2, 200)->post((string) config('ai.embeddings.url'), ['model' => config('ai.embeddings.model'), 'input' => $query]);
        $response->throw();
        $needle = $response->json('data.0.embedding', []);

        return AiEmbedding::query()->where('seller_id', $page->seller_id)->get(['embeddable_id', 'vector'])->map(fn (AiEmbedding $row): array => ['id' => (int) $row->embeddable_id, 'score' => $this->cosine($needle, $row->vector)])->sortByDesc('score')->take($limit)->values()->all();
    }

    /** @param Collection<int, StorePage> $pages */
    /** @return array<int, int> */
    public function searchPages(Collection $pages, string $query, int $limit = 24): array
    {
        if (! $this->configured()) {
            return [];
        }

        try {
            foreach ($pages as $page) {
                $content = implode("\n", array_filter([$page->name, $page->tagline, $page->bio, $page->city, $page->state]));
                $this->index($page, $content, $page->seller_id);
            }

            $response = Http::withToken((string) config('ai.embeddings.api_key'))->timeout((int) config('ai.embeddings.timeout', 20))->retry(2, 200)->post((string) config('ai.embeddings.url'), ['model' => config('ai.embeddings.model'), 'input' => $query]);
            $response->throw();
            $needle = $response->json('data.0.embedding', []);
            $type = (new StorePage)->getMorphClass();
            $pageIds = $pages->modelKeys();

            return AiEmbedding::query()->where('embeddable_type', $type)->whereIn('embeddable_id', $pageIds)->get(['embeddable_id', 'vector'])->map(fn (AiEmbedding $row): array => ['id' => (int) $row->embeddable_id, 'score' => $this->cosine($needle, $row->vector)])->sortByDesc('score')->take($limit)->pluck('id')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array<int, float|int> $left */
    /** @param array<int, float|int> $right */
    private function cosine(array $left, array $right): float
    {
        $dot = $leftNorm = $rightNorm = 0.0;
        foreach ($left as $index => $value) {
            $other = (float) ($right[$index] ?? 0);
            $dot += (float) $value * $other;
            $leftNorm += (float) $value ** 2;
            $rightNorm += $other ** 2;
        }

        return $leftNorm && $rightNorm ? $dot / (sqrt($leftNorm) * sqrt($rightNorm)) : 0.0;
    }
}
