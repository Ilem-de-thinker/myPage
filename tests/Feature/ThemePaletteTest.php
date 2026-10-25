<?php

use App\Shared\Support\Color;

it('keeps merchant palette contrast safe across representative brand colors', function (string $brand) {
    $ink = Color::contrast($brand);
    $text = Color::textSafe($brand);
    $palette = Color::palette($brand);

    expect(Color::contrastRatio($brand, $ink))->toBeGreaterThanOrEqual(4.5)
        ->and(Color::contrastRatio($text, '#ffffff'))->toBeGreaterThanOrEqual(4.5)
        ->and(array_keys($palette))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900]);
})->with([
    'deep green' => '#166534',
    'bright yellow' => '#facc15',
    'navy' => '#172554',
    'hot pink' => '#ec4899',
    'black' => '#000000',
    'orange' => '#f97316',
]);
