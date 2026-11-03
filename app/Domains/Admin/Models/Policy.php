<?php

namespace App\Domains\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Policy extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'summary',
        'sections',
        'version',
        'effective_at',
        'published_at',
        'is_draft',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'effective_at' => 'date',
            'published_at' => 'datetime',
            'is_draft' => 'boolean',
        ];
    }

    /**
     * The copy a visitor is allowed to see.
     *
     * A draft is a lawyer-review document, not a published one: it stays out of
     * the public site even when an admin has already given it a publish date.
     * Only a non-draft row whose effective date has arrived is current.
     */
    public static function current(string $slug): ?self
    {
        return self::query()
            ->where('slug', $slug)
            ->where('is_draft', false)
            ->whereNotNull('published_at')
            ->where(fn ($query) => $query
                ->whereNull('effective_at')
                ->orWhereDate('effective_at', '<=', today()))
            ->latest('effective_at')
            ->latest('id')
            ->first();
    }

    public static function currentVersion(string $slug): ?string
    {
        return self::current($slug)?->version;
    }

    /**
     * The single version string the whole app records consent against.
     *
     * Registration, the consent screen and the pending check must agree; when
     * they read different sources a seller can accept one version and still be
     * told they accepted another. The published row wins, then the shipped
     * config value.
     */
    public static function version(string $slug): string
    {
        return self::currentVersion($slug)
            ?? (string) config("legal.{$slug}_version", '2026-10-23');
    }

    /**
     * @return array{title: string, summary: string, sections: array<int, array{heading: string, body: string}>, is_draft: bool}
     */
    public function toPolicyArray(): array
    {
        return [
            'title' => $this->title,
            'summary' => $this->summary,
            'sections' => $this->sections,
            'is_draft' => $this->is_draft,
        ];
    }
}
