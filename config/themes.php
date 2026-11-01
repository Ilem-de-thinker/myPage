<?php

/*
|--------------------------------------------------------------------------
| Theme registry
|--------------------------------------------------------------------------
|
| Two themes, each a fixed multi-page website. A seller does not choose a
| layout, reorder blocks or pick a font: the only design setting is
| store_pages.brand_color. Everything else a seller edits is content.
|
| What this file does hold is the *order* of blocks on each page, and the fields
| each block actually accepts. That second part is the important one. The old
| page builder gave every block the same heading/body/image triple, which meant
| a textarea labelled "body" under Categories where no body would ever render,
| and a seller filling in fields that had no effect on their shop.
|
| A block declares only the fields it uses. Anything absent from `fields` has no
| input, and a block with no content left does not render at all.
|
*/

return [
    'presets' => [
        'editorial-luxe' => ['label' => 'Editorial Luxe', 'description' => 'Magazine-led layouts for fashion, beauty and premium goods.', 'business_types' => ['store', 'service'], 'composition' => ['hero' => 'editorial', 'navigation' => 'editorial', 'collection' => 'editorial', 'detail' => 'editorial', 'footer' => 'editorial'], 'font_label' => 'Newsreader + Inter', 'font' => ['heading' => "'Newsreader', Georgia, serif", 'body' => "'Inter', system-ui, sans-serif"], 'fonts_link' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600;6..72,700&display=swap'],
        'minimal-swiss' => ['label' => 'Minimal Swiss', 'description' => 'Grid-first clarity for consultants, agencies and studios.', 'business_types' => ['store', 'service'], 'composition' => ['hero' => 'structured', 'navigation' => 'minimal', 'collection' => 'structured', 'detail' => 'structured', 'footer' => 'minimal'], 'font_label' => 'Plus Jakarta Sans + Inter', 'font' => ['heading' => "'Plus Jakarta Sans', Inter, sans-serif", 'body' => "'Inter', system-ui, sans-serif"], 'fonts_link' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap'],
        'brutalist-soft' => ['label' => 'Bold / Soft', 'description' => 'Oversized type and confident blocks for creative businesses and food.', 'business_types' => ['store', 'service'], 'composition' => ['hero' => 'brutalist', 'navigation' => 'brutalist', 'collection' => 'brutalist', 'detail' => 'brutalist', 'footer' => 'brutalist'], 'font_label' => 'Archivo + Inter', 'font' => ['heading' => "'Archivo', system-ui, sans-serif", 'body' => "'Inter', system-ui, sans-serif"], 'fonts_link' => 'https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap'],
        'catalog' => ['label' => 'Marketplace Catalog', 'description' => 'Fast browsing, filters and dense product discovery.', 'business_types' => ['store'], 'composition' => ['hero' => 'catalog', 'navigation' => 'catalog', 'collection' => 'catalog', 'detail' => 'catalog', 'footer' => 'catalog'], 'font_label' => 'Archivo + Inter', 'font' => ['heading' => "'Archivo', system-ui, sans-serif", 'body' => "'Inter', system-ui, sans-serif"], 'fonts_link' => 'https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap'],
        'boutique-warm' => ['label' => 'Boutique Warm', 'description' => 'Image-led, tactile and welcoming for artisans and bakeries.', 'business_types' => ['store', 'service'], 'composition' => ['hero' => 'boutique', 'navigation' => 'boutique', 'collection' => 'boutique', 'detail' => 'boutique', 'footer' => 'boutique'], 'font_label' => 'Newsreader + Inter', 'font' => ['heading' => "'Newsreader', Georgia, serif", 'body' => "'Inter', system-ui, sans-serif"], 'fonts_link' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600;6..72,700&display=swap'],
        'tech-pro' => ['label' => 'Tech / Pro', 'description' => 'Structured trust signals for B2B and technical providers.', 'business_types' => ['store', 'service'], 'composition' => ['hero' => 'tech', 'navigation' => 'tech', 'collection' => 'tech', 'detail' => 'tech', 'footer' => 'tech'], 'font_label' => 'Plus Jakarta Sans + Inter', 'font' => ['heading' => "'Plus Jakarta Sans', Inter, sans-serif", 'body' => "'Inter', system-ui, sans-serif"], 'fonts_link' => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap'],
    ],

    'store' => [
        'label' => 'Store',
        'view_namespace' => 'themes.store',

        /*
         | Archivo: drawn for editorial at speed, and it ships tabular lining
         | figures, which on a shop page is not a nicety — the price is the
         | loudest element and must not shift when a cart updates. The variable
         | width axis lets the hero compress without a second typeface.
         */
        'font' => [
            'heading' => "'Archivo', system-ui, sans-serif",
            'body' => "'Archivo', system-ui, sans-serif",
        ],
        'fonts_link' => 'https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800&display=swap',

        'pages' => [
            'home' => [
                'label' => 'Home',
                'blocks' => ['hero', 'delivery_band', 'featured', 'categories', 'products', 'reviews', 'about', 'faq', 'contact'],
            ],
            'about' => ['label' => 'About', 'blocks' => ['story']],
            'contact' => ['label' => 'Contact', 'blocks' => ['intro']],
        ],

        /*
         | Editable content, per block. `type` drives the input in the seller UI;
         | `fields` is deliberately short and specific.
         */
        'blocks' => [
            'home_hero' => [
                'label' => 'Hero',
                'help' => 'The first thing a visitor sees. Leave it blank to keep the default.',
                'fields' => [
                    'headline' => ['type' => 'text', 'label' => 'Headline', 'default' => 'Made for you, made to last'],
                    'subtext' => ['type' => 'textarea', 'label' => 'One line under it'],
                    'image' => ['type' => 'image', 'label' => 'Hero photo'],
                    'button_label' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Shop now'],
                ],
            ],

            'home_featured' => [
                'label' => 'Featured products',
                'always' => true,
                'help' => 'Shows your newest products. Add a heading if you want one.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'New arrivals'],
                ],
            ],

            /*
             | Data, not words: the band reads the seller's delivery profile and
             | renders nothing when they have not set one up. `always` keeps it in
             | the render pipeline; the component decides whether there is a fact
             | worth showing. No fields means it never appears in the editor.
             */
            'home_delivery_band' => [
                'label' => 'Delivery band',
                'always' => true,
                'help' => 'Shown automatically once you set up delivery or pickup.',
                'fields' => [],
            ],

            'home_categories' => [
                'label' => 'Categories',
                'always' => true,
                'help' => 'Your product categories, in the order you arrange them.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Shop by category'],
                ],
            ],

            'home_products' => [
                'label' => 'All products',
                'always' => true,
                'help' => 'A grid of everything you sell. Hidden until you add a product.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Everything we make'],
                ],
            ],

            'home_reviews' => [
                'label' => 'Reviews',
                'help' => 'What buyers say about you.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'What buyers say'],
                    'items' => ['type' => 'repeater', 'label' => 'Reviews', 'fields' => [
                        // A row with a name and no quote is not a review, so the
                        // quote is the field that decides whether the row counts.
                        'quote' => ['type' => 'textarea', 'label' => 'What they said', 'required' => true],
                        'author' => ['type' => 'text', 'label' => 'Their name'],
                    ]],
                ],
            ],

            'home_about' => [
                'label' => 'About you',
                'help' => 'A short story about the business.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'About us'],
                    'body' => ['type' => 'richtext', 'label' => 'Your story'],
                    'image' => ['type' => 'image', 'label' => 'Photo of you or your work'],
                ],
            ],

            'home_faq' => [
                'label' => 'Frequently asked questions',
                'help' => 'Answer the questions buyers ask before they place an order.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Questions, answered'],
                    'items' => ['type' => 'repeater', 'label' => 'Questions', 'fields' => [
                        'question' => ['type' => 'text', 'label' => 'Question', 'required' => true],
                        'answer' => ['type' => 'textarea', 'label' => 'Answer', 'required' => true],
                    ]],
                ],
            ],

            'home_contact' => [
                'label' => 'Contact',
                'always' => true,
                'help' => 'How buyers reach you. Your WhatsApp number is added automatically.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Get in touch'],
                ],
            ],

            'about_story' => [
                'label' => 'Your story',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Page heading'],
                    'body' => ['type' => 'richtext', 'label' => 'Story'],
                    'image' => ['type' => 'image', 'label' => 'Photo'],
                ],
            ],

            'contact_intro' => [
                'label' => 'Contact page copy',
                // The address and email are page settings now, shown on every
                // page's footer as well as here. Only the page's own words remain
                // in this block, so the two places cannot disagree.
                'fields' => [
                    'intro' => ['type' => 'textarea', 'label' => 'A line above your details'],
                    'hours' => ['type' => 'textarea', 'label' => 'Opening hours', 'placeholder' => "Monday to Friday, 9am - 6pm\nSaturday, 10am - 4pm"],
                ],
            ],
        ],

        'required' => ['products'],
    ],

    'service' => [
        'label' => 'Business',
        'view_namespace' => 'themes.service',

        /*
         | Newsreader: a text serif with a variable optical size. A booking is not
         | a purchase, and the same grotesque that makes a price list feel like a
         | ledger makes an appointment feel like a checkout. One family, and the
         | whole page changes voice with it.
         */
        'font' => [
            'heading' => "'Newsreader', Georgia, serif",
            'body' => "'Newsreader', Georgia, serif",
        ],
        'fonts_link' => 'https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,400;6..72,500;6..72,600;6..72,700&display=swap',

        'pages' => [
            'home' => [
                'label' => 'Home',
                'blocks' => ['hero', 'delivery_band', 'services', 'team', 'booking', 'gallery', 'reviews', 'about', 'faq', 'contact'],
            ],
            'about' => ['label' => 'About', 'blocks' => ['story']],
            'contact' => ['label' => 'Contact', 'blocks' => ['intro']],
            'about' => ['label' => 'About', 'blocks' => ['story']],
            'contact' => ['label' => 'Contact', 'blocks' => ['intro']],
            'booking' => ['label' => 'Booking', 'blocks' => ['booking_summary']],
            'services' => ['label' => 'Services', 'blocks' => ['services_intro']],
            'service-show' => ['label' => 'Service', 'blocks' => ['service_intro']],
        ],

        /*
         | The booking widget and the service listings are rendered from real
         | rows, not from stored text, so they are layout rather than blocks.
         | What a seller writes on those pages is the introduction above them.
         */
        'blocks' => [
            'home_hero' => [
                'label' => 'Hero',
                'fields' => [
                    'headline' => ['type' => 'text', 'label' => 'Headline'],
                    'subtext' => ['type' => 'textarea', 'label' => 'One line under it'],
                    'image' => ['type' => 'image', 'label' => 'Hero photo'],
                    'button_label' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Book now'],
                ],
            ],
            'home_services' => [
                'label' => 'Services',
                'always' => true,
                'help' => 'Your services. Hidden until you add one.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'What we do'],
                ],
            ],

            /*
             | Reads the store's delivery profile: home service, coverage and
             | travel radius. Renders nothing until the seller turns one on.
             */
            'home_delivery_band' => [
                'label' => 'Service area band',
                'always' => true,
                'help' => 'Shown automatically once you offer home service.',
                'fields' => [],
            ],
            'home_team' => [
                'label' => 'Team',
                'always' => true,
                'fields' => ['heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'The team']],
            ],
            'booking_summary' => [
                'label' => 'Introduction',
                'help' => 'A line above the booking form. Optional.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Book a time'],
                    'body' => ['type' => 'textarea', 'label' => 'Anything to know first'],
                ],
            ],
            'services_intro' => [
                'label' => 'Introduction',
                'help' => 'A line above your service list. Optional.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'What we offer'],
                    'body' => ['type' => 'textarea', 'label' => 'Anything to know first'],
                ],
            ],
            'service_intro' => [
                'label' => 'Introduction',
                'help' => 'A line above this service. Optional.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading'],
                    'body' => ['type' => 'textarea', 'label' => 'A note about this service'],
                ],
            ],
            'home_booking' => [
                'label' => 'Booking',
                'help' => 'Points people at your booking page. Hidden until you add a service.',
                'always' => true,
                'fields' => ['heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Book an appointment']],
            ],
            'home_gallery' => [
                'label' => 'Gallery',
                'always' => true,
                'fields' => ['heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Our work']],
            ],
            'home_reviews' => [
                'label' => 'Reviews',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'What clients say'],
                    'items' => ['type' => 'repeater', 'label' => 'Reviews', 'fields' => [
                        // A row with a name and no quote is not a review, so the
                        // quote is the field that decides whether the row counts.
                        'quote' => ['type' => 'textarea', 'label' => 'What they said', 'required' => true],
                        'author' => ['type' => 'text', 'label' => 'Their name'],
                    ]],
                ],
            ],
            'home_about' => [
                'label' => 'About you',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'About us'],
                    'body' => ['type' => 'richtext', 'label' => 'Your story'],
                    'image' => ['type' => 'image', 'label' => 'Photo'],
                ],
            ],

            'home_faq' => [
                'label' => 'Frequently asked questions',
                'help' => 'Answer the questions clients ask before they book.',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Before you book'],
                    'items' => ['type' => 'repeater', 'label' => 'Questions', 'fields' => [
                        'question' => ['type' => 'text', 'label' => 'Question', 'required' => true],
                        'answer' => ['type' => 'textarea', 'label' => 'Answer', 'required' => true],
                    ]],
                ],
            ],
            'home_contact' => [
                'label' => 'Contact',
                'fields' => ['heading' => ['type' => 'text', 'label' => 'Heading', 'default' => 'Find us']],
            ],
            'about_story' => [
                'label' => 'Your story',
                'fields' => [
                    'heading' => ['type' => 'text', 'label' => 'Page heading'],
                    'body' => ['type' => 'richtext', 'label' => 'Story'],
                    'image' => ['type' => 'image', 'label' => 'Photo'],
                ],
            ],
            'contact_intro' => [
                'label' => 'Contact page copy',
                'fields' => [
                    'intro' => ['type' => 'textarea', 'label' => 'A line above your details'],
                    'hours' => ['type' => 'textarea', 'label' => 'Opening hours'],
                ],
            ],
        ],

        'required' => ['services', 'booking'],
    ],
];
