<?php

namespace App\Domains\Auth\Listeners;

use App\Domains\Auth\Events\SellerRegistered;
use App\Domains\Auth\Notifications\WelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued, and only after the registration transaction commits.
 *
 * The event fires inside RegisterSeller's transaction. A listener that runs
 * before the commit would mail a link to a seller row that does not exist yet,
 * so it waits for the commit rather than racing it.
 */
class SendWelcomeEmail implements ShouldQueue
{
    /** The seller row has to exist before the mail names it. */
    public bool $afterCommit = true;

    public function handle(SellerRegistered $event): void
    {
        $event->seller->notify(new WelcomeNotification(route('onboarding.show', 'business')));
    }
}
