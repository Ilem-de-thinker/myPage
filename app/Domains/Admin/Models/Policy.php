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

    public static function current(string $slug): ?self
    {
        return self::query()
            ->where('slug', $slug)
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
