<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A per-seller override of a platform feature flag. The platform default comes
 * from config; this only ever narrows or widens it for one seller.
 */
class FeatureFlag extends Model
{
    public $timestamps = false;

    protected $fillable = ['seller_id', 'feature', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
