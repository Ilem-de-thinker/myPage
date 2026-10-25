<?php

namespace App\Domains\Auth\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PolicyController extends Controller
{
    /**
     * The policy pages are intentionally versioned in config so a future change
     * can require fresh consent without changing the route contract.
     *
     * @return array<string, array{title: string, summary: string, sections: array<int, array{heading: string, body: string}>}>
     */
    private static function policies(): array
    {
        return [
            'terms' => [
                'title' => 'Terms of Service',
                'summary' => 'The rules for sellers using mypage.ng to publish a storefront and subscription services.',
                'sections' => [
                    ['heading' => 'What mypage.ng provides', 'body' => 'mypage.ng provides storefront, catalogue, booking, order tracking and subscription software. It does not buy, sell, receive, hold, escrow, refund or pay out buyer money.'],
                    ['heading' => 'Seller responsibility', 'body' => 'You are responsible for your business, listings, prices, availability, fulfilment, customer support, taxes and compliance with applicable law. You must connect only a gateway account you control.'],
                    ['heading' => 'Subscriptions', 'body' => 'Sellers and service providers pay mypage.ng for the subscription plan they select. Subscription charges are separate from buyer payments, which go directly to the seller through the seller’s own gateway account.'],
                    ['heading' => 'Suspension', 'body' => 'We may suspend or remove a storefront for fraud, unlawful content, abuse, misleading listings, unresolved reports or non-payment of a subscription. Suspension does not transfer or adjudicate buyer funds.'],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'summary' => 'How mypage.ng collects, uses, protects and deletes personal information.',
                'sections' => [
                    ['heading' => 'Controller and contact', 'body' => 'The data controller is '.config('legal.controller_name').'. Contact '.config('legal.contact_email').' or '.config('legal.contact_address').' for privacy questions or data requests.'],
                    ['heading' => 'What we collect', 'body' => 'We collect seller account details, storefront content, subscription records, security logs and support or moderation reports. Buyer contact details are collected by the relevant seller’s storefront to fulfil an order or booking and to provide updates.'],
                    ['heading' => 'What we do not control', 'body' => 'Buyer payment credentials and buyer funds are handled by the seller’s selected payment gateway. mypage.ng is not the payment recipient, does not hold buyer money and does not operate the seller’s gateway account.'],
                    ['heading' => 'Your rights', 'body' => 'You may ask for access, correction, deletion, restriction or a copy of personal information held by mypage.ng. Use the request form below or contact '.config('legal.contact_email').'.'],
                ],
            ],
            'refunds' => [
                'title' => 'Refunds and Returns',
                'summary' => 'Refunds are handled by the seller who received the buyer’s payment.',
                'sections' => [
                    ['heading' => 'Buyer purchases', 'body' => 'A buyer must contact the seller using the seller’s published support details. The seller is responsible for its return, cancellation and refund policy and processes any refund through its own payment gateway.'],
                    ['heading' => 'mypage.ng subscriptions', 'body' => 'Subscription billing questions can be sent to '.config('legal.contact_email').'. A subscription refund, if offered, concerns the seller’s subscription to mypage.ng and is separate from any buyer purchase.'],
                ],
            ],
            'merchant-agreement' => [
                'title' => 'Seller and Merchant Agreement',
                'summary' => 'The additional obligations that apply when you publish a seller or service-provider storefront.',
                'sections' => [
                    ['heading' => 'Accurate business information', 'body' => 'Keep your business identity, contact details, prices, inventory, services, availability and delivery or cancellation terms accurate and current.'],
                    ['heading' => 'Gateway ownership', 'body' => 'Every seller must use their own approved gateway credentials. Buyer payments settle into that seller’s gateway account. Never enter another person’s credentials or represent mypage.ng as the payment recipient.'],
                    ['heading' => 'Customer support', 'body' => 'Maintain a working support route and respond to buyers. Reports may lead to moderation or suspension, but mypage.ng does not decide private payment disputes.'],
                ],
            ],
            'cookies' => [
                'title' => 'Cookie Policy',
                'summary' => 'The cookies and browser storage used to keep the service working.',
                'sections' => [
                    ['heading' => 'Essential storage', 'body' => 'We use session cookies for sign-in and security, and temporary preview cookies for private storefront previews. These are required for requested functionality.'],
                    ['heading' => 'Preferences', 'body' => 'The interface may store a theme preference in the browser. We do not currently use advertising cookies or third-party analytics cookies.'],
                ],
            ],
            'acceptable-use' => [
                'title' => 'Acceptable Use Policy',
                'summary' => 'Content and conduct that are not allowed on mypage.ng.',
                'sections' => [
                    ['heading' => 'Not allowed', 'body' => 'Do not use mypage.ng for fraud, impersonation, illegal goods or services, malware, harassment, hate, exploitation, deceptive listings, credential theft or attempts to bypass payment-provider controls.'],
                    ['heading' => 'Enforcement', 'body' => 'We may remove content, limit features, suspend a storefront or preserve information needed to investigate abuse or comply with law.'],
                ],
            ],
            'disputes' => [
                'title' => 'Dispute Resolution',
                'summary' => 'How to raise a concern without confusing platform moderation with payment adjudication.',
                'sections' => [
                    ['heading' => 'Seller first', 'body' => 'For an order or booking, contact the seller first. The seller received the buyer payment and controls any cancellation, fulfilment or refund decision.'],
                    ['heading' => 'Report a store', 'body' => 'You may report fraud, non-delivery, misleading listings or payment problems to mypage.ng. A report is a moderation input; it does not authorise mypage.ng to move, freeze or refund buyer money.'],
                ],
            ],
            'ip' => [
                'title' => 'Intellectual Property Policy',
                'summary' => 'How to report content that infringes your rights.',
                'sections' => [
                    ['heading' => 'Notice', 'body' => 'Send a notice to '.config('legal.contact_email').' identifying the work, the allegedly infringing storefront URL, your contact details and your good-faith basis for the request.'],
                    ['heading' => 'Review', 'body' => 'We may remove or restrict content while we review a credible notice and may contact the storefront owner for a response.'],
                ],
            ],
            'contact' => [
                'title' => 'Contact and Imprint',
                'summary' => 'Contact mypage.ng about the platform, privacy, subscriptions or moderation.',
                'sections' => [
                    ['heading' => 'Platform contact', 'body' => 'Email '.config('legal.contact_email').' for platform support, privacy requests, subscription questions and legal notices.'],
                    ['heading' => 'Buyer support', 'body' => 'For a purchase or booking, use the seller’s WhatsApp, email or contact form shown on that storefront. Buyer payments go directly to that seller’s gateway account.'],
                    ['heading' => 'Business address', 'body' => config('legal.contact_address')],
                ],
            ],
        ];
    }

    public function show(string $policy): View
    {
        $policies = self::policies();

        abort_unless(isset($policies[$policy]), 404);

        return view('themes.shared.pages.policy', [
            'policy' => $policies[$policy],
            'slug' => $policy,
            'termsVersion' => config('legal.terms_version'),
            'privacyVersion' => config('legal.privacy_version'),
        ]);
    }

    public function requestData(): View
    {
        return view('themes.shared.pages.data-request');
    }
}
