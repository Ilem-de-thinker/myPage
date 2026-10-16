<?php

namespace App\Domains\Admin\Models;

use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Every decision an admin makes is written here before it takes effect. Admin
 * actions move other people's money and take their pages down, so "who did what,
 * when, and why" has to be answerable without trusting anybody's memory.
 *
 * Append only: there is no update path and no delete.
 */
class AdminAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'admin_seller_id',
        'action',
        'subject_type',
        'subject_id',
        'note',
        'ip',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'admin_seller_id');
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Admin audit entries are immutable.');
        });

        static::deleting(function () {
            throw new \LogicException('Admin audit entries are immutable.');
        });
    }

    public function subjectLabel(): string
    {
        return $this->subject_type
            ? class_basename($this->subject_type).' #'.$this->subject_id
            : '—';
    }
}
