<?php

namespace App\Models;

use App\Billing\StripePrices;
use App\Enums\AuditEventType;
use App\Events\WorkspaceDeleting;
use Database\Factories\WorkspaceFactory;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;
use Laravel\Sanctum\HasApiTokens;
use Packstub\Features\Concerns\HasFeatures;
use Packstub\Features\Facades\Features;
use Packstub\Features\Models\FeatureOverride;
use Packstub\Teams\Concerns\HasTeamMembers;
use Packstub\Teams\Facades\Teams;
use Stripe\Exception\InvalidRequestException;

/**
 * The tenant: what people sign up for, invite others into and pay for.
 */
#[Fillable(['name', 'slug'])]
class Workspace extends Model
{
    // The Stripe customer is the workspace, not the person who entered the card.
    use Billable;

    // Its API tokens: a token belongs to the workspace, not to the person who created it.
    use HasApiTokens;

    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    use HasFeatures;
    use HasTeamMembers;

    /** @var array<string, mixed> */
    protected $attributes = [
        'onboarding_dismissed_at' => null,
    ];

    public const NAME_MAX_LENGTH = 60;

    /** Counts the suffix that tells two workspaces of the same name apart. Short enough for a host name, should workspaces ever get subdomains. */
    public const SLUG_MAX_LENGTH = 50;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'onboarding_dismissed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The tables a workspace owns have no foreign key to this one
        // (database/migrations/workspace), and neither have Cashier's or the
        // feature overrides, so their rows are removed here. Members and
        // invitations go with the workspace by their own foreign keys.
        static::deleting(function (Workspace $workspace): void {
            // What a module adds (docs/modules) is removed by its own listener. First, so a
            // listener that fails stops the delete before anything has been asked of Stripe.
            WorkspaceDeleting::dispatch($workspace);

            // Nothing keeps billing a workspace that is gone. Without a Stripe secret
            // there is no way to reach Stripe, and nothing it could be charging for.
            // If Stripe refuses, the exception stops the delete and everything stays.
            if (filled(config('cashier.secret'))) {
                $workspace->cancelStripeSubscription();
            }

            $workspace->projects()->delete();

            $workspace->subscriptions()->get()->each(function (Subscription $subscription): void {
                $subscription->items()->delete();
                $subscription->delete();
            });

            FeatureOverride::query()->forScope($workspace)->delete();

            // Its API tokens stop working with it.
            $workspace->tokens()->delete();

            // Its audit log goes too; the entry written below stays for the back office.
            AuditEvent::query()->where('workspace_id', $workspace->getKey())->delete();
        });

        // Inside the same transaction as the delete (see delete() below).
        static::deleted(fn (Workspace $workspace) => AuditEvent::record($workspace, AuditEventType::WorkspaceDeleted));
    }

    /**
     * All or nothing: the workspace's row and everything the `deleting` hook
     * removes go in one transaction, wherever delete() is called from.
     *
     * The Stripe cancellation happens inside it, so the rows stay locked for
     * that call, and a later failure rolls back the rows but not the
     * cancellation. That case puts itself right: Stripe then sends
     * `customer.subscription.deleted`, and the webhook moves the workspace
     * that is still here to the default plan.
     */
    public function delete(): ?bool
    {
        return DB::transaction(fn (): ?bool => parent::delete());
    }

    /**
     * Cancel the subscription in Stripe now. One that Stripe no longer has
     * (deleted in the dashboard, or with its customer) is as cancelled as it
     * gets; any other error is thrown.
     */
    protected function cancelStripeSubscription(): void
    {
        try {
            $this->currentSubscription()?->cancelNow();
        } catch (InvalidRequestException $exception) {
            if ($exception->getStripeCode() !== 'resource_missing') {
                throw $exception;
            }
        }
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * A new workspace with a name cut to length and a slug of its own.
     */
    public static function named(string $name): static
    {
        $name = Str::limit(trim($name), static::NAME_MAX_LENGTH, '');

        return static::create(['name' => $name, 'slug' => static::uniqueSlug($name)]);
    }

    /**
     * A URL slug for a new workspace: the name, plus a short suffix when the name is taken.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = trim(Str::limit(Str::slug($name), static::SLUG_MAX_LENGTH - 5, ''), '-') ?: 'workspace';
        $slug = $base;
        $reserved = static::reservedSlugs();

        while (in_array($slug, $reserved, true) || static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    /**
     * Slugs no workspace may have: the first URL segment of every page the
     * app panel serves outside a workspace (`login`, `register`, `new`,
     * `profile`, `invitations`…). Those routes come before `/app/{workspace}`,
     * so a workspace called "New" or "Login" could never be opened. Read from
     * the routes, so a page or plugin added later is covered too. With the
     * panel at the root of the site (`->path('')`), that is the first segment
     * of every route the app has: `admin`, `stripe`, `up`…
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        $path = trim(Filament::getPanel('app')->getPath(), '/');
        $prefix = $path === '' ? '' : $path.'/';

        return collect(Route::getRoutes()->getRoutes())
            ->map(fn (RoutingRoute $route): string => $route->uri())
            ->filter(fn (string $uri): bool => str_starts_with($uri, $prefix))
            ->map(fn (string $uri): string => Str::before(Str::after($uri, $prefix), '/'))
            ->reject(fn (string $segment): bool => $segment === '' || str_starts_with($segment, '{'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Put the workspace on a plan. Filament Features reads the `plan`
     * column, so the new limits apply from the next check.
     */
    public function changePlan(string $plan): void
    {
        // A workspace just created has no plan attribute yet: the column's default applies.
        $from = $this->getAttributes()['plan'] ?? config('packstub-features.default_plan');

        $this->forceFill(['plan' => $plan])->save();

        Features::flush($this);

        if ($from !== $plan) {
            AuditEvent::record($this, AuditEventType::PlanChanged, [
                'from' => Features::planDefinition($from)?->label ?? $from,
                'to' => Features::planDefinition($plan)?->label ?? $plan,
            ]);
        }
    }

    /**
     * The Stripe subscription this workspace has now, if any: the latest one
     * that has not ended. It may be past due or waiting for a first payment.
     */
    public function currentSubscription(): ?Subscription
    {
        // The admin table loads the subscriptions of every row at once; elsewhere one query finds it.
        $subscription = $this->relationLoaded('subscriptions')
            ? $this->subscriptions->where('type', 'default')->sortByDesc('id')->first()
            : $this->subscriptions()->where('type', 'default')->latest('id')->first();

        return $subscription && ! $subscription->ended() ? $subscription : null;
    }

    /**
     * The free trial a new subscription would start with, in days: the
     * configured trial for a workspace that has never had a subscription,
     * none for one that has (one trial per workspace).
     */
    public function trialDaysOnOffer(): int
    {
        $days = (int) config('billing.trial_days');

        return $days > 0 && ! $this->subscriptions()->exists() ? $days : 0;
    }

    /**
     * Match the plan to the Stripe subscription.
     *
     * The plan of its price while the subscription is active, on trial, in
     * its grace period after a cancellation, or past due: Stripe retries a
     * failed payment for days, and the customer keeps what they had while it
     * does. The default plan once it has ended, was never paid, Stripe gave
     * up collecting (`unpaid`), or Stripe paused it because a trial ended
     * without a card (`paused`, which Cashier counts as active).
     */
    public function syncPlanFromSubscription(): void
    {
        $subscription = $this->currentSubscription();

        if (! $subscription || $subscription->stripe_status === 'paused' || ! ($subscription->valid() || $subscription->pastDue())) {
            $this->changePlan(config('packstub-features.default_plan'));

            return;
        }

        $plan = StripePrices::plan($subscription->stripe_price);

        if ($plan === null) {
            // A price created in Stripe and not added to config/billing.php (a
            // new price tried out in the dashboard, say). The customer pays, so
            // the plan stays as it is.
            Log::warning('A Stripe subscription has a price that config/billing.php does not map to a plan; the workspace keeps its current plan.', [
                'workspace' => $this->getKey(),
                'subscription' => $subscription->stripe_id,
                'price' => $subscription->stripe_price,
            ]);

            return;
        }

        $this->changePlan($plan);
    }

    /**
     * How many seats a per-seat plan bills this workspace for: its members.
     * Pending invitations hold a seat against the plan's limit but are not
     * billed until someone accepts.
     */
    public function billableSeats(): int
    {
        return max(1, Teams::membersOf($this)->count());
    }

    /**
     * The quantity a subscription on this price should have: the billable
     * seats on a per-seat plan, one on a flat one.
     */
    public function quantityFor(string $price): int
    {
        return StripePrices::perSeat(StripePrices::plan($price)) ? $this->billableSeats() : 1;
    }

    /**
     * Bring the quantity of a per-seat subscription in Stripe in line with
     * the members, after someone joined or left. Nothing to do on a flat
     * plan, without a Stripe secret, or for a subscription that is not in
     * good standing (Stripe refuses to change an incomplete one).
     */
    public function syncSeatQuantity(): void
    {
        $subscription = $this->currentSubscription();

        if (blank(config('cashier.secret')) || ! $subscription?->valid() || $subscription->incomplete()
            || ! StripePrices::perSeat(StripePrices::plan($subscription->stripe_price))) {
            return;
        }

        $seats = $this->billableSeats();

        if ($subscription->quantity !== $seats) {
            $subscription->updateQuantity($seats);
        }
    }

    /**
     * The address Stripe sends receipts to: the first owner's.
     */
    public function stripeEmail(): ?string
    {
        return Teams::ownersOf($this)->oldest('workspace_user.created_at')->value('email');
    }
}
