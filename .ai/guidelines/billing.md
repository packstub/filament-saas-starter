# Billing

Laravel Cashier with Stripe. The workspace, not the person, is the Stripe customer (`Billable` on `App\Models\Workspace`), and Cashier's tables point at `workspaces` and `workspace_id`.

## How a plan is set

1. The Billing page (`app/Filament/App/Pages/Billing.php`) sends an owner to Stripe Checkout for a price from `config/billing.php` (`App\Billing\StripePrices::for($plan, $interval)`), or to the customer portal.
2. Stripe calls the webhook (`/stripe/webhook`, Cashier's controller). After Cashier has stored the subscription, `App\Listeners\SyncWorkspacePlanFromStripe` calls `Workspace::syncPlanFromSubscription()`, which reads the price back into a plan key (`StripePrices::plan()`) and calls `Workspace::changePlan()`.
3. Filament Features resolves every limit from the workspace's `plan` column.

So the plan is written from what Stripe holds (the webhook, and the Billing page right after it changed or resumed a subscription through the API), from Change plan in `/admin`, and by the seeder. Never set `plan` from the Checkout success URL: anyone can open it without paying.

## Rules

- Prices live in `config/billing.php` (plan key, `monthly`/`yearly`, `price`, `amount`, `unit_amount`, `per_seat`), read through `StripePrices`. Add a plan there and in `config/packstub-features.php` together.
- A job that talks to Stripe holds the workspace's id, never a model with people in it (`app/Jobs/SyncSeatQuantity.php`, `app/Jobs/SyncStripeCustomer.php`). Never queue a listener of Filament Teams' events: they carry whole `User` models.
- A Stripe call inside a database transaction cannot be rolled back; `Workspace::delete()` explains how the kit lives with that.
- The admin dashboard's figures (`app/Billing/RevenueMetrics.php`) come from the app's own tables and `unit_amount`, never from the Stripe API.
- Without `STRIPE_SECRET` the app runs without billing: the Billing page says what to set, and nothing calls Stripe.
- In production, unsigned webhooks are refused (`app/Listeners/RefuseUnsignedStripeWebhooks.php`): `STRIPE_WEBHOOK_SECRET` is required.

## Tests

Never reach Stripe from a test. `fakeStripe()` in `tests/Pest.php` answers Stripe's API from memory (its `->requests` and `->params` record the calls), `stripeSubscriptionEvent()` builds a webhook payload, `postSignedStripeEvent()` signs and posts one. See `tests/Feature/BillingTest.php` and `tests/Feature/SeatBillingTest.php`.
