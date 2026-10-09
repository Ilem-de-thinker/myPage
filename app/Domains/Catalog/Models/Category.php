<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Seller\Models\StorePage;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Catalog\Models\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<CategoryFactory> */
class Category extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = ['seller_id', 'store_page_id', 'name', 'slug', 'position'];

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
