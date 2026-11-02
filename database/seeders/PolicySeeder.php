<?php

namespace Database\Seeders;

use App\Domains\Admin\Models\Policy;
use App\Domains\Admin\Support\LegalPolicies;
use Illuminate\Database\Seeder;

class PolicySeeder extends Seeder
{
    public function run(): void
    {
        foreach (LegalPolicies::all() as $slug => $policy) {
            Policy::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $policy['title'],
                    'summary' => $policy['summary'],
                    'sections' => $policy['sections'],
                    'version' => $slug === 'terms'
                        ? (string) config('legal.terms_version')
                        : ($slug === 'privacy'
                            ? (string) config('legal.privacy_version')
                            : now()->toDateString()),
                    'effective_at' => now()->toDateString(),
                    'published_at' => now(),
                    'is_draft' => (bool) ($policy['is_draft'] ?? false),
                ],
            );
        }
    }
}
