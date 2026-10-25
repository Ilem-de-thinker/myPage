<?php

namespace App\Domains\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class DataSubjectRequest extends Model
{
    protected $fillable = [
        'name',
        'email',
        'request_type',
        'details',
        'status',
        'requested_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
