<?php

namespace App\Domains\Ai\Models;

use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiEmbedding extends Model
{
    protected $fillable = ['seller_id', 'embeddable_type', 'embeddable_id', 'provider', 'model', 'content_hash', 'content', 'vector', 'metadata'];

    protected function casts(): array
    {
        return ['vector' => 'array', 'metadata' => 'array'];
    }

    public function embeddable(): MorphTo
    {
        return $this->morphTo();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
