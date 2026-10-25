<?php

return [
    'tokens' => [
        'ink' => '#17211b',
        'ink_muted' => '#64736a',
        'paper' => '#f8f9f4',
        'surface' => '#eef2eb',
        'card' => '#ffffff',
        'brand' => '#1f6b4d',
        'accent' => '#c9784c',
        'line' => '#d9e2d8',
        'shadow' => '0 24px 70px -42px rgba(23,33,27,.65)',
        'display_font' => 'Fraunces',
        'text_font' => 'DM Sans',
        'radius' => '1.5rem',
        'space_section' => 'clamp(4rem, 8vw, 8rem)',
        'motion_curve' => 'cubic-bezier(.22, 1, .36, 1)',
    ],
    'animation' => [
        'default_personality' => 'editorial',
        'low_data_query' => '(prefers-reduced-data: reduce)',
    ],
    'directory' => [
        'assistant_daily_limit' => (int) env('DIRECTORY_ASSISTANT_DAILY_LIMIT', 20),
    ],
];
