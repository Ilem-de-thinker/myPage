<?php

namespace App\Domains\Seller\Support;

use App\Domains\Seller\Models\StorePage;

class StorePolicies
{
    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return ['terms', 'privacy', 'refunds', 'delivery', 'cancellation'];
    }

    /**
     * @return array{title: string, summary: string, sections: array<int, array{heading: string, body: string}>}
     */
    public function forPage(StorePage $page, string $policy): array
    {
        $custom = $page->settings['policies'][$policy] ?? null;

        if (is_array($custom) && isset($custom['sections'])) {
            return [
                'title' => (string) ($custom['title'] ?? ucfirst($policy)),
                'summary' => (string) ($custom['summary'] ?? ''),
                'sections' => $custom['sections'],
            ];
        }

        if (is_string($custom) && trim($custom) !== '') {
            return [
                'title' => ucfirst($policy),
                'summary' => 'How '.$page->name.' handles '.$policy.'.',
                'sections' => [
                    ['heading' => ucfirst($policy), 'body' => $custom],
                ],
            ];
        }

        return $this->defaults($page)[$policy];
    }

    /**
     * @return array<string, array{title: string, summary: string, sections: array<int, array{heading: string, body: string}>}>
     */
    private function defaults(StorePage $page): array
    {
        $contact = $page->public_email ?: $page->whatsapp_number ?: 'the contact details on this page';
        $platform = 'mypage.ng provides the storefront software only. It does not buy, sell, receive, hold or refund buyer money.';

        return [
            'terms' => [
                'title' => 'Store Terms',
                'summary' => 'The terms that apply when you buy from '.$page->name.'.',
                'sections' => [
                    ['heading' => 'Who you are buying from', 'body' => $page->name.' is the merchant for every order or booking placed through this storefront. '.$platform],
                    ['heading' => 'Orders and bookings', 'body' => 'Prices, availability and fulfilment are set by '.$page->name.'. Contact '.$contact.' with any question about an order, booking, delivery or return.'],
                    ['heading' => 'Platform role', 'body' => $platform],
                ],
            ],
            'privacy' => [
                'title' => 'Store Privacy Notice',
                'summary' => 'How '.$page->name.' uses the details you share through this storefront.',
                'sections' => [
                    ['heading' => 'What this store collects', 'body' => $page->name.' collects the name, phone number, email, delivery address and order details you submit so it can fulfil your order or booking and contact you about it.'],
                    ['heading' => 'Payments', 'body' => 'Payment details are handled by the payment gateway that '.$page->name.' has connected. '.$platform],
                    ['heading' => 'Contact', 'body' => 'Contact '.$contact.' to ask for access, correction or deletion of the details this store holds about you.'],
                ],
            ],
            'refunds' => [
                'title' => 'Refunds and Returns',
                'summary' => 'How '.$page->name.' handles cancellations, returns and refunds.',
                'sections' => [
                    ['heading' => 'Ask the seller', 'body' => 'Contact '.$contact.' before returning an item or cancelling an order. Refunds are decided and processed by '.$page->name.'.'],
                    ['heading' => 'Payment route', 'body' => 'Any refund is sent back through the same payment gateway account that received the original payment. '.$platform],
                ],
            ],
            'delivery' => [
                'title' => 'Delivery and Pickup',
                'summary' => 'How orders from '.$page->name.' are delivered or collected.',
                'sections' => [
                    ['heading' => 'Options', 'body' => $this->deliverySummary($page)],
                    ['heading' => 'Timing', 'body' => 'Delivery times are agreed with '.$page->name.'. Contact '.$contact.' if an order is late or arrives damaged.'],
                ],
            ],
            'cancellation' => [
                'title' => 'Cancellation Policy',
                'summary' => 'How to change or cancel a booking with '.$page->name.'.',
                'sections' => [
                    ['heading' => 'Changing a booking', 'body' => 'Manage your booking from the booking reference page. Contact '.$contact.' if you need help.'],
                    ['heading' => 'Deposits and payments', 'body' => 'Any deposit or cancellation charge is set by '.$page->name.'. '.$platform],
                ],
            ],
        ];
    }

    private function deliverySummary(StorePage $page): string
    {
        $profile = $page->deliveryProfile()->with('zones')->first();

        if ($profile === null) {
            return 'Contact '.$page->name.' to confirm delivery or pickup options.';
        }

        $options = array_filter([
            $profile->offers_delivery ? 'delivery' : null,
            $profile->offers_pickup ? 'pickup' : null,
            $profile->offers_home_service ? 'home service' : null,
        ]);

        if ($options === []) {
            return 'Contact '.$page->name.' to confirm delivery or pickup options.';
        }

        $summary = 'Available: '.implode(', ', $options).'.';

        if ($profile->zones->isNotEmpty()) {
            $summary .= ' Zones: '.$profile->zones->pluck('name')->filter()->implode(', ').'.';
        }

        if ($profile->notes) {
            $summary .= ' '.$profile->notes;
        }

        return $summary;
    }
}
