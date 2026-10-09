<?php

namespace App\Shared\Traits;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToSeller
{
    protected static function bootBelongsToSeller(): void
    {
        static::addGlobalScope('seller', function (Builder $query) {
            if ($id = static::currentSellerId()) {
                $query->where($query->getModel()->getTable().'.seller_id', $id);
            }
        });

        static::creating(function (Model $model) {
            $model->seller_id ??= static::currentSellerId();
        });
    }

    protected static function currentSellerId(): ?int
    {
        if (app()->bound(StorePage::class)) {
            return app(StorePage::class)->seller_id;
        }

        return auth()->user()?->id;
    }
}
