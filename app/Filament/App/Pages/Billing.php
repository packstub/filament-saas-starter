<?php

namespace App\Filament\App\Pages;

use App\Billing\StripePrices;
use App\Models\Workspace;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Packstub\Features\Facades\Features;
use Packstub\Features\Support\Plan;
use Stripe\Exception\ApiErrorException;
use UnitEnum;

/**
 * Where a workspace picks and pays for its plan, for owners and admins.
 *
 * Choosing a plan opens Stripe Checkout, switching moves the running
 * subscription to another price, and "Manage billing" opens Stripe's
 * customer portal. The webhook then puts the workspace on the plan it pays
 * for (App\Listeners\SyncWorkspacePlanFromStripe). Until every Stripe key is
 * set, the page lists the missing ones.
 *
 * canAccess() guards the page on every request, and each action asks it
 * again, so someone who lost their role while the page was open can no
 * longer start a checkout, wherever the action ends up being used.
 */
class Billing extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Workspace';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'billing';

    protected string $view = 'filament.app.pages.billing';

    /** Monthly or yearly: which prices the plan cards show. */
    public string $interval = 'monthly';

    public static function canAccess(): bool
    {
        $workspace = Filament::getTenant();

        return $workspace instanceof Workspace && (bool) Filament::auth()->user()?->canManageTeam($workspace);
    }

    public function mount(): void
    {
        // A yearly subscriber sees the yearly prices first.
        $this->interval = $this->subscribedInterval() ?? StripePrices::intervals()[0] ?? 'monthly';

        // Stripe Checkout sends people back here; the plan itself changes when the webhook arrives.
        if (request()->query('checkout') === 'success') {
            Notification::make()
                ->success()
                ->title('Thank you!')
                ->body('Your plan updates as soon as Stripe confirms the payment.')
                ->send();
        }
    }

    public function getSubheading(): ?string
    {
        return "You're on the ".Features::planLabel().' plan.';
    }

    public function isConfigured(): bool
    {
        return StripePrices::configured();
    }

    /**
     * The .env keys that still need a value before every plan can be sold.
     * Monthly prices are needed; yearly ones are optional.
     *
     * @return list<string>
     */
    public function getMissingKeys(): array
    {
        return array_keys(array_filter([
            'STRIPE_KEY' => blank(config('cashier.key')),
            'STRIPE_SECRET' => blank(config('cashier.secret')),
            'STRIPE_WEBHOOK_SECRET' => blank(config('cashier.webhook.secret')),
            ...collect(config('billing.plans'))
                ->mapWithKeys(fn (array $intervals, string $plan): array => ['STRIPE_PRICE_'.strtoupper($plan).'_MONTHLY' => StripePrices::for($plan, 'monthly') === null])
                ->all(),
        ]));
    }

    /**
     * The intervals to choose between, as interval => label; empty while
     * every plan is sold at one interval only, so the switch stays hidden.
     *
     * @return array<string, string>
     */
    public function getIntervals(): array
    {
        $intervals = StripePrices::intervals();

        return count($intervals) > 1 ? array_intersect_key(StripePrices::INTERVALS, array_flip($intervals)) : [];
    }

    /**
     * @return list<array{key: string, label: string, description: ?string, price: ?string, lines: list<string>, current: bool, sold: bool}>
     */
    public function getPlans(): array
    {
        $current = Features::plan();
        $interval = $this->selectedInterval();
        $subscribed = $this->subscribedInterval();

        return array_values(array_map(fn (Plan $plan): array => [
            'key' => $plan->key,
            'label' => $plan->label,
            'description' => $plan->description,
            'price' => StripePrices::amount($plan->key, $interval) ?? $plan->price,
            'lines' => $this->describe($plan),
            // Pro billed monthly is not the current plan on the Yearly tab: it can be switched to.
            'current' => $plan->key === $current && ($subscribed === null || $subscribed === $interval || StripePrices::for($plan->key, $interval) === null),
            'sold' => StripePrices::for($plan->key, $interval) !== null,
        ], Features::plans()));
    }

    /**
     * The interval the plan cards show: the one picked, if plans are sold at it.
     */
    public function selectedInterval(): string
    {
        return in_array($this->interval, StripePrices::intervals(), true) ? $this->interval : 'monthly';
    }

    /**
     * The interval the workspace's subscription bills at, if it has one on a known price.
     */
    public function subscribedInterval(): ?string
    {
        return StripePrices::interval($this->workspace()->currentSubscription()?->stripe_price);
    }

    /**
     * What a plan includes, one line per feature: "50 projects", "Unlimited seats", "Priority support".
     *
     * @return list<string>
     */
    protected function describe(Plan $plan): array
    {
        $lines = [];

        foreach (Features::features() as $feature) {
            $value = $feature->cast($plan->has($feature->key) ? $plan->value($feature->key) : $feature->default);

            if ($feature->isLimit()) {
                $lines[] = ($value ?? 'Unlimited').' '.($feature->unit ?? $feature->label);
            } elseif ($value) {
                $lines[] = $feature->label;
            }
        }

        return $lines;
    }

    protected function getHeaderActions(): array
    {
        return [$this->manageAction()];
    }

    /**
     * Stripe's customer portal: cards, invoices, cancelling. Also the way
     * out for a subscription whose payment failed.
     */
    public function manageAction(): Action
    {
        return Action::make('manage')
            ->label('Manage billing')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (): bool => $this->isConfigured() && $this->workspace()->hasStripeId())
            ->action(fn () => $this->toStripe(fn (): string => $this->workspace()->billingPortalUrl(static::getUrl())));
    }

    /**
     * For a subscription whose first payment was left unfinished at Checkout
     * (a 3D Secure step, say): Cashier's payment page completes it. Stripe
     * drops such a subscription after about a day.
     */
    public function completePaymentAction(): Action
    {
        return Action::make('completePayment')
            ->label('Complete payment')
            ->icon(Heroicon::OutlinedCreditCard)
            ->authorize(fn (): bool => static::canAccess())
            ->action(function () {
                $subscription = $this->workspace()->currentSubscription();

                if (! $this->isConfigured() || ! $subscription?->incomplete()) {
                    return null;
                }

                try {
                    $payment = $subscription->latestPayment();
                } catch (ApiErrorException $exception) {
                    return $this->stripeFailed($exception);
                }

                return $payment
                    ? redirect()->route('cashier.payment', [$payment->id, 'redirect' => static::getUrl()])
                    : $this->toStripe(fn (): string => $this->workspace()->billingPortalUrl(static::getUrl()));
            });
    }

    /**
     * The button on each plan card for a workspace without a subscription:
     * off to Stripe Checkout. Called with the plan's key.
     */
    public function subscribeAction(): Action
    {
        return Action::make('subscribe')
            ->label(fn (array $arguments): string => 'Choose '.Features::planDefinition($arguments['plan'])?->label)
            ->authorize(fn (): bool => static::canAccess())
            ->action(function (array $arguments) {
                $price = StripePrices::for($arguments['plan'], $arguments['interval'] ?? 'monthly');

                // Never a second subscription beside one that is running or waiting for a payment.
                if (! $this->isConfigured() || $price === null || $this->hasSubscription()) {
                    return null;
                }

                return $this->toStripe(fn (): string => $this->workspace()
                    ->newSubscription('default', $price)
                    ->allowPromotionCodes()
                    ->checkout([
                        'success_url' => static::getUrl(['checkout' => 'success']),
                        'cancel_url' => static::getUrl(),
                    ])
                    ->url);
            });
    }

    /**
     * The button on each plan card for a workspace that already pays: the
     * running subscription moves to the other price, after a confirmation.
     */
    public function changePlanAction(): Action
    {
        return Action::make('changePlan')
            ->label(fn (array $arguments): string => $arguments['plan'] === Features::plan()
                ? 'Switch to '.strtolower(StripePrices::INTERVALS[$arguments['interval'] ?? 'monthly'] ?? '').' billing'
                : 'Switch to '.Features::planDefinition($arguments['plan'])?->label)
            ->authorize(fn (): bool => static::canAccess())
            ->requiresConfirmation()
            ->modalDescription('Your subscription moves to this plan now, and Stripe prorates the difference.')
            ->action(function (array $arguments) {
                $price = StripePrices::for($arguments['plan'], $arguments['interval'] ?? 'monthly');

                if (! $this->isConfigured() || $price === null || ! $this->canSwitchPlans()) {
                    return null;
                }

                try {
                    $this->workspace()->currentSubscription()->swap($price);
                } catch (IncompletePayment $exception) {
                    // The card needs a confirmation (3D Secure) or was declined: Cashier's payment
                    // page takes it from here, and the webhook sets the plan once the payment is through.
                    return redirect()->route('cashier.payment', [$exception->payment->id, 'redirect' => static::getUrl()]);
                } catch (ApiErrorException $exception) {
                    return $this->stripeFailed($exception);
                }

                // The webhook does the same a moment later; this saves the wait.
                $this->workspace()->syncPlanFromSubscription();

                Notification::make()->success()->title("You're on the ".Features::planLabel().' plan')->send();

                return null;
            });
    }

    /**
     * Whether the workspace has a Stripe subscription that has not ended,
     * whatever its state. While it has one, no new checkout is offered.
     */
    public function hasSubscription(): bool
    {
        return $this->workspace()->currentSubscription() !== null;
    }

    /**
     * Switching plans needs a subscription in good standing.
     */
    public function canSwitchPlans(): bool
    {
        return (bool) $this->workspace()->currentSubscription()?->valid();
    }

    /**
     * A subscription whose last payment failed or was never completed: the
     * customer settles it before anything else is offered.
     */
    public function needsPayment(): bool
    {
        return $this->hasSubscription() && ! $this->canSwitchPlans();
    }

    /**
     * The first payment of a new subscription was not finished.
     */
    public function needsFirstPayment(): bool
    {
        return (bool) $this->workspace()->currentSubscription()?->incomplete();
    }

    protected function workspace(): Workspace
    {
        return Filament::getTenant();
    }

    /**
     * Send the browser to a Stripe-hosted page, or say what went wrong.
     *
     * @param  callable(): string  $url
     */
    protected function toStripe(callable $url): mixed
    {
        try {
            return redirect()->away($url());
        } catch (ApiErrorException $exception) {
            return $this->stripeFailed($exception);
        }
    }

    protected function stripeFailed(ApiErrorException $exception): null
    {
        report($exception);

        Notification::make()
            ->danger()
            ->title('Stripe could not complete that')
            ->body(app()->isProduction() ? 'Please try again in a moment.' : $exception->getMessage())
            ->send();

        return null;
    }
}
