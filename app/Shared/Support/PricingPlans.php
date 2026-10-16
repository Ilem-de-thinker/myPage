<?php

namespace App\Shared\Support;

use App\Domains\Billing\Models\Plan;
use Illuminate\Support\Collection;

/**
 * The pricing cards on the landing page.
 *
 * The design draws three cards with the copy, the order and the featured state
 * fixed; only the numbers and the names come from the platform. That split is
 * deliberate — a landing page that quotes prices nobody can buy from is worse
 * than one that quotes none, so the figures are read from the same rows the
 * checkout does rather than typed into the template.
 *
 * The database wins so a price edited in admin shows here. Config is the
 * fallback: a deploy that forgot to run PlanSeeder must still show prices,
 * because a blank pricing section is the worst possible first impression. When
 * config is what is on screen the section says so, rather than implying the
 * numbers are administered when they are not.
 */
final class PricingPlans
{
    /**
     * The three cards, in display order, with the middle one featured.
     *
     * @return list<array{name: string, tagline: string, price: string, description: string, featured: bool, features: list<array{label: string, code?: string, highlight?: bool, muted?: bool}>}>
     */
    public static function cards(): array
    {
        [$plans, $fromDatabase] = self::plans();

        // The design's featured card sits in the middle. With one or two plans
        // there is no middle, so the first card takes it.
        $featuredIndex = $plans->count() >= 3 ? 1 : 0;

        return $plans->values()
            ->map(fn ($plan, int $index): array => self::card($plan, $index === $featuredIndex))
            ->all();
    }

    /**
     * Whether the prices on screen are administered rather than configured.
     */
    public static function fromDatabase(): bool
    {
        [, $fromDatabase] = self::plans();

        return $fromDatabase;
    }

    /**
     * The plan rows, and whether they came from the database or from config.
     *
     * @return array{0: Collection<int, \stdClass>, 1: bool}
     */
    private static function plans(): array
    {
        $stored = Plan::active()->orderBy('price_kobo')->get();

        if ($stored->isNotEmpty()) {
            return [$stored, true];
        }

        return [collect(config('billing.plans', []))
            ->map(fn (array $plan, string $slug) => (object) [
                'slug' => $slug,
                'name' => $plan['name'],
                'description' => $plan['description'] ?? null,
                'price_kobo' => $plan['price_kobo'],
                'interval' => $plan['interval'] ?? 'monthly',
                'trial_days' => $plan['trial_days'] ?? null,
                'max_products' => $plan['max_products'] ?? null,
                'is_featured' => (bool) ($plan['is_featured'] ?? false),
            ])
            ->sortBy('price_kobo')
            ->values(), false];
    }

    /**
     * One card, in the shape the pricing-plan component takes.
     *
     * @return array{name: string, tagline: string, price: string, description: string, featured: bool, features: list<array{label: string, code?: string, highlight?: bool, muted?: bool}>}
     */
    private static function card(object $plan, bool $featured): array
    {
        $unlimited = $plan->max_products === null;

        $features = [
            [
                'label' => $unlimited
                    ? 'Unlimited products & appointment slots'
                    : 'Up to '.number_format((int) $plan->max_products).' products or services',
                'highlight' => $featured && $unlimited,
            ],
            ['label' => 'Direct WhatsApp order forwarding'],
            ['label' => 'Escrow-protected payments'],
            [
                'label' => 'Connect custom domain',
                'code' => 'yourbrand.ng',
                'muted' => ! in_array($plan->slug ?? null, config('billing.custom_domain_plans', []), true),
            ],
        ];

        if ($plan->trial_days) {
            $features[] = ['label' => $plan->trial_days.'-day free trial'];
        }

        return [
            'name' => $plan->name,
            'tagline' => $featured ? 'Full Growth Suite' : ($plan->trial_days ? 'Free to try' : 'Monthly'),
            'price' => Money::format((int) $plan->price_kobo),
            'description' => $plan->description ?? 'Everything you need to start selling on mypage.ng.',
            'featured' => $featured,
            'features' => $features,
        ];
    }
}
