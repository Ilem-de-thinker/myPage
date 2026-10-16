<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\FeatureFlag;
use App\Domains\Billing\Models\PlatformFeatureFlag;
use Illuminate\Support\Facades\Cache;

/**
 * One place to ask whether a feature is on.
 *
 * Three inputs, in order of authority:
 *
 * 1. A feature missing from config is off. Never on by omission.
 * 2. `mypage.features_locked` kills every feature at once. This is the panic
 *    switch, and it is deliberately separate from the defaults: turning a
 *    feature off in config is a decision about that feature, not about all of
 *    them, and conflating the two makes "switch everything off in an emergency"
 *    impossible without editing every line.
 * 3. A platform-wide row wins over config, in both directions. This is the
 *    switch an admin reaches for during an incident; without it, turning a
 *    feature off for everyone means a deploy.
 * 4. The per-seller row wins over both, in both directions.
 *
 * That last one matters more than it looks. Features ship dark, so if a row
 * could only ever turn things off then no seller could ever be opted in, and an
 * admin toggle built on top of it would be a control that does nothing.
 */
final class Feature
{
    private const CACHE_KEY = 'feature-flags';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * @param  int|null  $sellerId  null asks about the platform as a whole.
     */
    public static function enabled(string $feature, ?int $sellerId = null): bool
    {
        $defaults = config('mypage.features', []);

        // A feature missing from config is off, never on by omission.
        if (! array_key_exists($feature, $defaults)) {
            return false;
        }

        // The panic switch outranks everything, including a row that says on.
        if (config('mypage.features_locked', false)) {
            return false;
        }

        [$platformOverrides, $sellerOverrides] = self::overrides();

        $platform = array_key_exists($feature, $platformOverrides)
            ? (bool) $platformOverrides[$feature]
            : (bool) $defaults[$feature];

        if ($sellerId === null) {
            return $platform;
        }

        // No override means whatever the platform decided.
        return array_key_exists($feature, $sellerOverrides[$sellerId] ?? [])
            ? (bool) $sellerOverrides[$sellerId][$feature]
            : $platform;
    }

    /**
     * Whether a seller has an explicit opinion, as opposed to following the
     * platform default. The admin panel uses this to tell "on for everyone"
     * apart from "on for this one seller".
     */
    public static function hasOverride(string $feature, int $sellerId): bool
    {
        return array_key_exists($feature, self::overrides()[1][$sellerId] ?? []);
    }

    /**
     * The platform-wide state of a feature: forced on, forced off, or left to
     * config. The admin panel needs the third case as much as the first two, so
     * a decision to stop overriding is expressible.
     */
    public static function platform(string $feature): ?bool
    {
        $overrides = self::overrides()[0];

        return array_key_exists($feature, $overrides) ? (bool) $overrides[$feature] : null;
    }

    /**
     * Stop overriding a feature platform-wide, so config decides again.
     */
    public static function followConfig(string $feature): void
    {
        PlatformFeatureFlag::query()->where('feature', $feature)->delete();

        self::flush();
    }

    /**
     * Force a feature on or off for every seller, whatever config says.
     */
    public static function force(string $feature, bool $enabled): void
    {
        PlatformFeatureFlag::updateOrCreate(
            ['feature' => $feature],
            ['enabled' => $enabled],
        );

        self::flush();
    }

    /**
     * The features a seller can be opted in or out of, and what they resolve to.
     *
     * @return array<string, bool>
     */
    public static function allFor(int $sellerId): array
    {
        return collect(config('mypage.features', []))
            ->map(fn (mixed $default, string $feature) => self::enabled($feature, $sellerId))
            ->all();
    }

    /**
     * Platform-wide overrides and per-seller overrides, in one cache read.
     *
     * Every row, not just the enabled ones: a flag can only narrow a feature if
     * turning it OFF is readable too.
     *
     * @return array{0: array<string, bool>, 1: array<int, array<string, bool>>}
     */
    private static function overrides(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => [
            PlatformFeatureFlag::query()->pluck('enabled', 'feature')->map(fn ($v) => (bool) $v)->all(),
            FeatureFlag::query()
                ->get(['seller_id', 'feature', 'enabled'])
                ->groupBy('seller_id')
                ->map(fn ($rows) => $rows->pluck('enabled', 'feature')->all())
                ->all(),
        ]);
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
