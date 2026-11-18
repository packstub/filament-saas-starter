<?php

namespace App\Jobs;

use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Stripe\Exception\ApiErrorException;

/**
 * Bring a per-seat subscription's quantity in Stripe in line with the
 * workspace's members (Workspace::syncSeatQuantity()).
 *
 * The job carries the workspace's id only (SerializesModels, through
 * Queueable) and reads the members when it runs, so no user's columns ever
 * reach the queue or `failed_jobs`. A workspace deleted in the meantime
 * drops the job. A call Stripe refuses is reported and tried again a
 * minute later; the next subscription webhook also puts it right.
 */
class SyncSeatQuantity implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Workspace $workspace) {}

    public function handle(): void
    {
        try {
            $this->workspace->syncSeatQuantity();
        } catch (ApiErrorException $exception) {
            report($exception);

            $this->release(60);
        }
    }
}
