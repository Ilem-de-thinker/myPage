<?php

return [
    'store' => [
        'label' => 'Store',
        'view_namespace' => 'themes.store',
        'pages' => [
            'home' => ['label' => 'Home', 'sections' => ['hero', 'featured', 'categories', 'products', 'reviews', 'about', 'contact']],
            'products' => ['label' => 'Products', 'sections' => ['filters', 'grid']],
            'product-show' => ['label' => 'Product', 'sections' => ['gallery', 'details', 'related']],
            'about' => ['label' => 'About', 'sections' => ['story', 'values']],
            'contact' => ['label' => 'Contact', 'sections' => ['details', 'hours']],
            'cart' => ['label' => 'Cart', 'sections' => ['items']],
            'checkout' => ['label' => 'Checkout', 'sections' => ['form', 'summary']],
            'order-tracking' => ['label' => 'Track order', 'sections' => ['form']],
        ],
        'required' => ['products'],
    ],
    'service' => [
        'label' => 'Service',
        'view_namespace' => 'themes.service',
        'pages' => [
            'home' => ['label' => 'Home', 'sections' => ['hero', 'services', 'team', 'booking', 'gallery', 'reviews', 'about', 'contact']],
            'services' => ['label' => 'Services', 'sections' => ['filters', 'grid']],
            'service-show' => ['label' => 'Service', 'sections' => ['details', 'booking-cta', 'related']],
            'about' => ['label' => 'About', 'sections' => ['story', 'values', 'team']],
            'contact' => ['label' => 'Contact', 'sections' => ['details', 'hours']],
            'booking' => ['label' => 'Booking', 'sections' => ['widget', 'summary']],
            'booking-confirmation' => ['label' => 'Confirmation', 'sections' => ['details']],
        ],
        'required' => ['services', 'booking'],
    ],
    'font_pairs' => [
        'modern' => ['heading' => "'Inter', sans-serif", 'body' => "'Inter', sans-serif"],
        'elegant' => ['heading' => "'Playfair Display', serif", 'body' => "'Lato', sans-serif"],
        'bold' => ['heading' => "'Poppins', sans-serif", 'body' => "'Open Sans', sans-serif"],
        'friendly' => ['heading' => "'Nunito', sans-serif", 'body' => "'Nunito', sans-serif"],
    ],
];
