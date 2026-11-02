<?php

namespace App\Domains\Admin\Support;

class LegalPolicies
{
    /**
     * The shipped policy copy. It seeds the database and is the fallback when a
     * deploy has not run the seeder yet, so the legal pages never 500.
     *
     * @return array<string, array{title: string, summary: string, sections: array<int, array{heading: string, body: string}>, is_draft?: bool}>
     */
    public static function all(): array
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
            'kyc-verification' => [
                'is_draft' => true,
                'title' => 'KYC and Verification Policy',
                'summary' => 'How merchant verification works on mypage.ng. Draft for review by a qualified Nigerian lawyer.',
                'sections' => [
                    ['heading' => 'What verification means', 'body' => 'The verified tick is a trust signal shown on a store page. It is not a payment licence, a guarantee of quality, or a promise that mypage.ng will resolve a buyer dispute.'],
                    ['heading' => 'Tiers', 'body' => 'Tier 1 is an account with a confirmed email address and a reachable phone number. Tier 2 is a reviewed identity document such as a national ID, passport, driver’s licence or NIN. Tier 3 is business verification using CAC registration details. A higher tier adds trust; it never changes where buyer money settles.'],
                    ['heading' => 'Statuses', 'body' => 'An application can be pending, approved, rejected, marked needs more information, expired or revoked. A rejection or information request states the reason and lets the seller send a corrected document. An expired or revoked verification stops displaying the tick.'],
                    ['heading' => 'Document handling', 'body' => 'Documents are stored privately and encrypted at rest. Reviewers open them through short-lived signed links. Registration numbers are masked in the review queue, and documents are never written to application logs.'],
                    ['heading' => 'Duplicates and retention', 'body' => 'A registration number already linked to another active or approved account is refused so one business cannot claim several identities. Documents are retained for '.config('legal.kyc_retention_years').' years after submission, then purged, subject to the Nigeria Data Protection Act 2023 and any legal hold.'],
                    ['heading' => 'Seller obligations', 'body' => 'Submit documents you are authorised to use, keep them current, and tell us promptly if the business changes name, ownership or registration status. Misuse may lead to revocation or suspension.'],
                ],
            ],
            'ai-usage' => [
                'is_draft' => true,
                'title' => 'AI Usage Policy',
                'summary' => 'Terms for using Mercho AI features. Draft for review by a qualified Nigerian lawyer.',
                'sections' => [
                    ['heading' => 'Merchant responsibility', 'body' => 'The merchant is responsible for reviewing and publishing AI output. AI-generated content is a draft until the merchant approves it.'],
                    ['heading' => 'No invention', 'body' => 'AI features describe only information present in the merchant data. The merchant must verify descriptions, categories and prices before publishing.'],
                    ['heading' => 'Background processing', 'body' => 'Long-running AI work runs in the background and is visible in the task manager. One failed item does not fail the whole task.'],
                ],
            ],
            'prohibited-items' => [
                'is_draft' => true,
                'title' => 'Prohibited Items and Services',
                'summary' => 'Items and services that may not be listed on mypage.ng. Draft for review by a qualified Nigerian lawyer.',
                'sections' => [
                    ['heading' => 'Not allowed', 'body' => 'Fraudulent, illegal, stolen, counterfeit, exploitative, hateful, deceptive or harmful items and services are not allowed.'],
                    ['heading' => 'Enforcement', 'body' => 'We may remove listings, suspend a storefront or preserve information needed to investigate abuse or comply with law.'],
                ],
            ],
            'faq' => [
                'is_draft' => true,
                'title' => 'Frequently Asked Questions',
                'summary' => 'Common questions about mypage.ng. Draft for review.',
                'sections' => [
                    ['heading' => 'What is mypage.ng?', 'body' => 'mypage.ng is a discovery and online-presence platform for merchants. It has no cart, orders or buyer payments; transactions happen directly between buyers and merchants.'],
                    ['heading' => 'How do I get paid?', 'body' => 'Buyers pay you directly through your own payment gateway account. mypage.ng does not hold buyer money.'],
                ],
            ],
            'about' => [
                'is_draft' => true,
                'title' => 'About mypage.ng',
                'summary' => 'About the platform. Draft for review.',
                'sections' => [
                    ['heading' => 'Mission', 'body' => 'mypage.ng helps merchants build an online presence, be discovered and manage their business.'],
                ],
            ],
        ];
    }
}
