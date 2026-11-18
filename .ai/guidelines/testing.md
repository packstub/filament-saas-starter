# Testing

- `composer test` runs Pint in check mode and the whole Pest suite; it must pass before a change is done. `php artisan test --filter=Billing` runs part of it, `composer lint` fixes the style.
- Every change comes with a test in `tests/Feature` (or `tests/Unit` for a class without the framework). Architecture rules are in `tests/Unit/ArchTest.php`.
- The suite runs on SQLite in memory (`phpunit.xml`), with the `sync` queue and the `array` mailer.

## Helpers (`tests/Pest.php`)

- `workspace($plan = 'free', $owner = null)` creates a workspace with an owner; `memberOf($workspace, 'admin')` adds someone with a role.
- `inWorkspace($workspace, $user = null)` signs in and makes the workspace Filament's current tenant, ready for `Livewire::test()` of a page in the customer panel.
- `admin()` is a back-office user with two-factor authentication; `inAdminPanel($user = null)` signs in and opens the `admin` panel.
- `fakeStripe()`, `stripeSubscriptionEvent()`, `stripeSubscription()`, `postSignedStripeEvent()`: Stripe without the network (see the billing guideline).
- `countQueries(fn () => …)` counts the queries a callback runs, for N+1 tests.

## Style

- Use datasets instead of loops, and `expect(fn () => …)->toThrow()` or an assertion on the outcome instead of `try`/`catch`.
- Use factory states (`admin()`, `withAppAuthentication()`, `unverified()`, `paused()`, `onPlan()`) instead of mocked models.
- A user signed in to `/admin` in a test needs `admin()`: the panel requires two-factor authentication.
- `actingAs()` users are never re-read from the database, so give `UserFactory` a value for every new `users` column (models are strict).
- Fake what leaves the app: `Mail::fake()`, `Notification::fake()`, `Queue::fake()`, `Storage::fake()`, `Http::fake()`.
