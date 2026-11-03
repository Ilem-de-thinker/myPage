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
        $seller = DB::transaction(function () use ($data) {
            // Account state is not mass-assignable, so the fields this action
            // owns are written with forceFill after the fillable core.
            $seller = new Seller([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => PhoneNumber::normalize($data['phone']),
                'password' => $data['password'],
            ]);

            $seller->forceFill([
                'status' => 'active',
                'password_set_at' => now(),
                'terms_accepted_at' => now(),
                'terms_version' => Policy::version('terms'),
                'privacy_accepted_at' => now(),
                'privacy_version' => Policy::version('privacy'),
            ])->save();

            $this->startTrial($seller);

            event(new SellerRegistered($seller));

            return $seller;
        });

        // Send after the commit: a mailer failure must not roll back the
        // account, and the OTP is only useful once the seller exists.
        $seller->sendEmailVerificationNotification();

        return $seller;
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
