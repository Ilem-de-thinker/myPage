<?php

namespace Database\Seeders;

use App\Domains\Billing\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Seeds plans from config so a fresh install needs no admin panel.
 *
 * Config bootstraps a plan; it does not own the price afterwards. An operator
 * sets prices from the admin panel, and the matching plan on the gateway is
 * created there too. A seeder that overwrote price_kobo on every deploy would
 * quietly undo that edit and leave the local price disagreeing with what the
 * gateway actually charges — the one failure this arrangement exists to prevent.
 *
 * So descriptive fields (name, description, limits, trial) stay config's, and
 * the three fields money depends on — price, interval, gateway plan code — are
 * written once and then only by a human. Changing a price means changing it in
 * the admin panel; that is the point of the panel.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('billing.plans', []) as $slug => $definition) {
            $plan = Plan::query()->firstOrNew(['slug' => $slug]);

            $plan->fill([
                'name' => $definition['name'],
                'description' => $definition['description'] ?? null,
                'trial_days' => $definition['trial_days'] ?? null,
                'max_products' => $definition['max_products'] ?? null,
                'max_staff' => $definition['max_staff'] ?? null,
                'is_default' => (bool) ($definition['is_default'] ?? false),
                'is_featured' => (bool) ($definition['is_featured'] ?? false),
                'is_active' => true,
            ]);

            if (! $plan->exists) {
                // First sighting only: the starting point the admin panel takes over from.
                $plan->fill([
                    'price_kobo' => $definition['price_kobo'],
                    'interval' => $definition['interval'] ?? 'monthly',
                    'gateway_plan_code' => $definition['gateway_plan_code'] ?? null,
                ]);
            }

            $plan->save();
        }
    }
}
