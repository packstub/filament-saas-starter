<?php

namespace App\Jobs;

use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stripe\Exception\ApiErrorException;

/**
 * Send the workspace's current details to its Stripe customer: the billing
 * e-mail is the first owner's (Workspace::stripeEmail()), so it follows an
 * ownership transfer. Carries the workspace's id only, like SyncSeatQuantity.
 */
class SyncStripeCustomer implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Workspace $workspace) {}

    public function handle(): void
    {
        if (! $this->workspace->hasStripeId() || blank(config('cashier.secret'))) {
            return;
        }

        try {
            $this->workspace->syncStripeCustomerDetails();
        } catch (ApiErrorException $exception) {
            report($exception);

            $this->release(60);
        }
    }
}
