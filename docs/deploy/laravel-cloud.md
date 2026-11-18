# Deploying to Laravel Cloud

[Laravel Cloud](https://cloud.laravel.com) runs the app with no server to manage: it builds from your Git repository, gives you a database, a cache, buckets and queue workers, and switches traffic to each new release without downtime. This page is what the kit needs on top of Cloud's [Laravel deploy guide](https://cloud.laravel.com/docs/deploy-guides/laravel). Prefer a server of your own? See [the server guide](server.md).

What the app needs in production, whatever the host:

| Piece | Why |
| --- | --- |
| A MySQL or PostgreSQL database | Cloud resets the disk on every deploy, so SQLite is not an option there. |
| A queue worker | The verification, password reset and invitation e-mails, and the Stripe syncs of the seat count (`app/Jobs/SyncSeatQuantity.php`) and the billing contact (`app/Jobs/SyncStripeCustomer.php`) are queued. |
| The scheduler | Daily housekeeping in `routes/console.php`: expired invitations, feature overrides and API tokens, and the audit log past `AUDIT_RETENTION_DAYS`. |
| A bucket, once people upload files | The disk of a Cloud instance is temporary and not shared between replicas. |
| A mail provider | Sign-up waits for the verification e-mail. |
| A Stripe webhook | Plans change only when Stripe says so. |

## 1. Prepare the repository

1. Raise the PHP platform to what Cloud will run, or remove it (see Requirements in the README):

   ```bash
   composer config platform.php 8.4.0   # or: composer config --unset platform.php
   composer update --lock
   ```

2. Add the packages Cloud's resources need:

   ```bash
   composer require league/flysystem-aws-s3-v3 "^3.0" --with-all-dependencies   # buckets
   composer require aws/aws-sdk-php                                             # managed queues
   ```

3. Remove the demo accounts if you have not yet (README, Install). The seeder skips them in production anyway.

4. Push to GitHub, GitLab or Bitbucket.

## 2. Create the application

In Cloud, create an application from the repository and a `production` environment (add a `staging` one the same way; `robots.txt` keeps search engines out of anything that is not `APP_ENV=production`).

**Build commands** (environment settings):

```bash
composer install --no-dev --optimize-autoloader
npm ci --audit false
npm run build
php artisan optimize
```

`php artisan optimize` caches the config, routes, views and events, and Filament's components, into the image.

**Deploy commands**:

```bash
php artisan migrate --force
```

Do not add `queue:restart`, `storage:link` or `optimize:clear`: Cloud restarts the workers itself, and deploy commands cannot change the disk.

## 3. Attach the resources

On the environment's canvas:

1. **Database**: Laravel MySQL or Laravel Serverless Postgres, in the environment's region. Cloud injects `DB_CONNECTION`, `DB_HOST` and the rest.
2. **Cache** (optional): the `database` cache and sessions are fine to start with. With a Laravel Valkey cache attached, Cloud sets `CACHE_STORE`; set `SESSION_DRIVER=redis` yourself if sessions should go there too.
3. **Queue**: add a **managed queue**. Cloud sets `QUEUE_CONNECTION=cloud` and scales its workers with the jobs waiting. On a small app you can instead add a `queue:work` background process to the App cluster, with `QUEUE_CONNECTION=database`.
4. **Scheduler**: switch on **Scheduler** on the App cluster. Cloud runs `schedule:run` every minute. The kit's tasks are daily clean-ups that are safe to run twice, so they need no `onOneServer()` when the App cluster has several replicas; a task of your own that must run once does.
5. **Bucket** (when people upload files): a **public** bucket with the disk name `public`, for files anyone may see through a link, such as logos and avatars. Cloud wires the disk into Laravel; do not set `visibility: 'public'` in `config/filesystems.php`, the bucket decides. Files only the app should hand out go in a second, **private** bucket, made the default disk, and are served through `Storage::temporaryUrl()`.

Redeploy after attaching anything.

## 4. Environment variables

Cloud injects what its resources need, and `APP_URL` for its own domain. Set the rest under the environment's **Environment variables** ([every variable](environment.md)):

```ini
APP_NAME="Your product"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # php artisan key:generate --show, once
APP_URL=https://your-domain.com

MAIL_MAILER=resend            # or postmark, ses, smtp
MAIL_FROM_ADDRESS=hello@your-domain.com

STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_PRO_MONTHLY=price_...
STRIPE_PRICE_PRO_YEARLY=price_...
STRIPE_PRICE_BUSINESS_MONTHLY=price_...
STRIPE_PRICE_BUSINESS_YEARLY=price_...

SESSION_SECURE_COOKIE=true
SITE_LEGAL_PLACEHOLDERS=false # once Terms and Privacy are yours
```

Keep the Stripe secrets and the mail key in Cloud's **Secrets**, attached to the environment, rather than as plain variables. `APP_KEY` stays the same for the life of the environment: a new one signs everyone out and makes the two-factor secrets unreadable.

For e-mail, attaching Resend from Cloud sets `MAIL_MAILER`, `RESEND_API_KEY` and the sender for you; run `composer require resend/resend-php` first.

## 5. Domain

Add your domain under **Domains** and create the DNS records Cloud shows; it issues the certificate. Then set `APP_URL` to it and redeploy: every link in an e-mail, the canonical URLs, the sitemap and Stripe's return URLs are built on `APP_URL`.

If redirects or links come out as `http://` behind Cloud's edge, trust the proxy in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
    // ...
})
```

## 6. Stripe webhook

Once the app answers on its domain, create the live webhook endpoint from your machine with the live secret:

```bash
STRIPE_SECRET=sk_live_... APP_URL=https://your-domain.com php artisan cashier:webhook
```

It points at `https://your-domain.com/stripe/webhook` with the events Cashier handles. Copy the endpoint's signing secret (Stripe dashboard, Developers, Webhooks) into `STRIPE_WEBHOOK_SECRET` and redeploy. In production the app refuses any webhook without a valid signature, so a missing secret means plans never change.

In Stripe, also turn on the customer portal (Settings, Billing, Customer portal) with the plans customers may switch between.

## 7. The first admin

There is no admin until you make one. After signing up on `/app/register` with your own address, run on the environment (Cloud, **Commands**):

```bash
php artisan tinker --execute="App\Models\User::where('email', 'you@your-domain.com')->update(['is_admin' => true])"
```

`/admin` then asks you to set up two-factor authentication before it opens.

## After a deploy

- `/up` answers 200 when the app boots.
- Sign up with a new address and follow the verification e-mail: mail and the queue work.
- Invite someone: the invitation e-mail arrives (through the worker).
- Subscribe to a plan with a [Stripe test card](https://docs.stripe.com/testing) on staging, or a real one you refund in production: the Billing page shows the plan once the webhook arrives.
- The **Logs** and **Queue** views in Cloud show no failures.

Go through [the production checklist](production-checklist.md) before you announce anything.
