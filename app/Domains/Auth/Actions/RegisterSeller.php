<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Admin\Models\Policy;
use App\Domains\Auth\Events\SellerRegistered;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Models\Plan;
use App\Domains\Seller\Models\Seller;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

class RegisterSeller
{
    public function __construct(private readonly SubscriptionManager $subscriptions) {}

    /**
     * @param  array{name: string, email: string, phone: string, password: string, terms_accepted: true, privacy_accepted: true}  $data
     */
    public function handle(array $data): Seller
    {
        return DB::transaction(function () use ($data) {
            $seller = Seller::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => PhoneNumber::normalize($data['phone']),
                'password' => $data['password'],
                'status' => 'active',
                'terms_accepted_at' => now(),
                'terms_version' => Policy::currentVersion('terms') ?? config('legal.terms_version'),
                'privacy_accepted_at' => now(),
                'privacy_version' => Policy::currentVersion('privacy') ?? config('legal.privacy_version'),
            ]);

            $this->startTrial($seller);

            event(new SellerRegistered($seller));

            $seller->sendEmailVerificationNotification();

            return $seller;
        });
    }

    /**
     * Every seller starts on the trial plan.
     *
     * Plans are seeded separately, so a deploy that has not run the seeder must
     * still be able to take a registration rather than failing on a missing row.
     * The seller simply has no subscription and the existing fail-open rules
     * apply until plans exist.
     */
    private function startTrial(Seller $seller): void
    {
        $plan = Plan::query()
            ->where('slug', config('billing.trial_plan'))
            ->first();

        if ($plan === null) {
            return;
        }

        $this->subscriptions->start($seller, $plan, 'trial');
    }
}
