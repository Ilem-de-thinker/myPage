<?php

namespace App\Domains\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A platform-wide override of a feature flag, applied to every seller.
 *
 * Separate from the per-seller FeatureFlag rows because a platform decision is
 * not a seller decision. No row at all means "follow config", which is why this
 * table is empty on a normal day.
 */
class PlatformFeatureFlag extends Model
{
    protected $fillable = ['feature', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
