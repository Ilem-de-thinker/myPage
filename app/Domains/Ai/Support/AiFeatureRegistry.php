<?php

namespace App\Domains\Ai\Support;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AiFeatureRegistry
{
    /**
     * @var array<string, array{label: string, task: string, business_types: list<string>, plan: string}>
     */
    private const FEATURES = [
        'store-creation' => ['label' => 'Create with Mercho', 'task' => 'site_builder', 'business_types' => ['store', 'service', 'both'], 'plan' => 'ai'],
        'products' => ['label' => 'Use Mercho for products', 'task' => 'listing_copy', 'business_types' => ['store', 'both'], 'plan' => 'ai'],
        'services' => ['label' => 'Use Mercho for services', 'task' => 'service_listing', 'business_types' => ['service', 'both'], 'plan' => 'ai'],
        'appearance' => ['label' => 'Use Mercho for appearance', 'task' => 'storefront_direction', 'business_types' => ['store', 'service', 'both'], 'plan' => 'ai'],
        'content' => ['label' => 'Improve with Mercho', 'task' => 'copy_rewrite', 'business_types' => ['store', 'service', 'both'], 'plan' => 'ai'],
        'analytics' => ['label' => 'Use Mercho for analytics', 'task' => 'insights_digest', 'business_types' => ['store', 'service', 'both'], 'plan' => 'ai'],
        'order-chat' => ['label' => 'Use Mercho in chat', 'task' => 'order_chat_assist', 'business_types' => ['store', 'service', 'both'], 'plan' => 'ai'],
        'store-assistant' => ['label' => 'Use Mercho store assistant', 'task' => 'store_assistant', 'business_types' => ['store', 'both'], 'plan' => 'ai'],
    ];

    public static function get(string $feature): array
    {
        abort_unless(isset(self::FEATURES[$feature]), 404);

        return self::FEATURES[$feature];
    }

    public static function forRequest(Request $request): ?string
    {
        $route = (string) $request->route()?->getName();

        return match (true) {
            $route === 'dashboard' => 'analytics',
            str_starts_with($route, 'products.') => 'products',
            str_starts_with($route, 'services.') => 'services',
            str_starts_with($route, 'dashboard.analytics.') => 'analytics',
            $route === 'dashboard.chat.show' => 'order-chat',
            str_starts_with($route, 'storefront.') && $request->string('tab')->toString() === 'brand' => 'appearance',
            str_starts_with($route, 'storefront.') => 'content',
            default => null,
        };
    }

    public static function allowed(StorePage $page, string $feature): bool
    {
        $definition = self::get($feature);

        return in_array($page->businessType(), $definition['business_types'], true);
    }

    public static function authorize(StorePage $page, string $feature): void
    {
        if (! self::allowed($page, $feature)) {
            throw new HttpException(403, 'This Mercho feature is not available for this business type.');
        }
    }

    public static function featuresFor(StorePage $page): array
    {
        return collect(self::FEATURES)
            ->filter(fn (array $definition, string $feature): bool => self::allowed($page, $feature))
            ->map(fn (array $definition, string $feature): array => ['key' => $feature, ...$definition])
            ->values()
            ->all();
    }
}
