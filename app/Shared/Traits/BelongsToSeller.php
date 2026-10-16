<?php

namespace App\Shared\Traits;

use App\Domains\Seller\Models\StorePage;
use App\Shared\Tenancy\CrossTenant;
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

        // An admin reviews other people's rows, and a seller_id filter would
        // hide them from someone whose job is to moderate the whole platform.
        if (CrossTenant::enabled()) {
            return null;
        }

        return auth()->user()?->id;
    }
}
