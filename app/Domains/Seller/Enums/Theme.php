<?php

namespace App\Domains\Seller\Enums;

enum Theme: string
{
    case Store = 'store';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Store',
            self::Service => 'Service',
        };
    }

    public function sections(): array
    {
        $pages = config("themes.{$this->value}.pages", []);

        return collect($pages)->flatMap(fn ($p) => $p['sections'])->values()->all();
    }

    public function pages(): array
    {
        return config("themes.{$this->value}.pages", []);
    }

    public function pageSections(string $page): array
    {
        return config("themes.{$this->value}.pages.{$page}.sections", []);
    }

    public function requiredSections(): array
    {
        return config("themes.{$this->value}.required", []);
    }
}
