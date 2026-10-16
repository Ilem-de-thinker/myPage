<?php

namespace App\Domains\Customers\Models;

use App\Shared\Traits\BelongsToSeller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNote extends Model
{
    use BelongsToSeller;

    protected $table = 'customer_notes';

    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'seller_id', 'body'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
