# Deploying to your own server

A virtual server you manage yourself, or one that [Laravel Forge](https://forge.laravel.com) or [Ploi](https://ploi.io) provisions and manages for you. Both set up Nginx, PHP, a database, Redis and SSL, run the deploy script below on every push, and have screens for the queue worker and the scheduler. On Laravel Cloud instead? See [the Laravel Cloud guide](laravel-cloud.md).

## What the server runs

| Piece | Setup |
| --- | --- |
| PHP 8.3 or newer | With the extensions Laravel needs, plus `intl` (Filament) and `gd` or `imagick` if you resize images. |
| Nginx (or Caddy) | The site's web root is `public/`. |
| MySQL, MariaDB or PostgreSQL | SQLite works on one server you back up, but a real database is easier to back up and grow. |
| Redis | Optional, and recommended for the cache, sessions and queue once you have more than a handful of customers. |
| A queue worker | Always. The verification, password reset and invitation e-mails and the Stripe syncs (`app/Jobs`) are queued. |
| The scheduler | Always. The daily clean-ups in `routes/console.php`. |
| Node 20.19 or newer | At build time only, for `npm run build`. |

## 1. Prepare the repository

Raise `config.platform.php` in `composer.json` to the server's PHP version, or remove it (README, Requirements), and remove the demo accounts (README, Install).

## 2. Environment

Create `.env` on the server from `.env.example` and set at least ([every variable](environment.md)):

```ini
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # php artisan key:generate --show, once
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis             # or database
SESSION_DRIVER=redis          # or database
QUEUE_CONNECTION=redis        # or database, never sync
SESSION_SECURE_COOKIE=true

LOG_STACK=daily
LOG_LEVEL=warning

MAIL_MAILER=smtp              # or postmark, ses, resend
MAIL_FROM_ADDRESS=hello@your-domain.com

STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

plus the Stripe price IDs, as in `.env.example`.

## 3. The deploy script

Forge and Ploi run this on every push (adapt their default):

```bash
cd /home/forge/your-domain.com
git pull origin main

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci --audit false
npm run build

php artisan migrate --force
php artisan optimize
php artisan storage:link        # once is enough; harmless again

php artisan queue:restart       # workers pick up the new code
```

`php artisan optimize` caches the config, routes, views and events. Anything that reads `.env` after that reads the cache, so run it again after changing `.env` (the next deploy does).

## 4. The queue worker

One long-running `queue:work` process, kept alive by Supervisor (Forge: **Queue**, Ploi: **Queue workers**):

```ini
[program:saas-worker]
command=php /home/forge/your-domain.com/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=forge
numprocs=1
redirect_stderr=true
stdout_logfile=/home/forge/your-domain.com/storage/logs/worker.log
stopwaitsecs=3600
```

One process handles a young SaaS easily: the kit's jobs are e-mails and single Stripe calls. Raise `numprocs` when the jobs you add take longer.

### Horizon, and when to use it

[Laravel Horizon](https://laravel.com/docs/horizon) replaces the plain worker with a supervisor that balances processes across queues and a dashboard of throughput, wait times and failed jobs. It needs Redis (`QUEUE_CONNECTION=redis`).

- **Stay with `queue:work`** while the queue is e-mails and Stripe calls, there is one queue, and `php artisan queue:failed` tells you enough.
- **Move to Horizon** when you add slow jobs of your own (imports, reports, AI calls) next to the e-mails and want them on separate queues with their own process counts, or when you need to see wait times and retry failures from a dashboard.

To switch: `composer require laravel/horizon`, `php artisan horizon:install`, restrict the dashboard to admins in `app/Providers/HorizonServiceProvider.php` (`Gate::define('viewHorizon', fn (User $user) => $user->is_admin)`), run `php artisan horizon` under Supervisor instead of `queue:work`, and replace `queue:restart` with `php artisan horizon:terminate` in the deploy script.

## 5. The scheduler

One cron entry (Forge: **Scheduler**, Ploi: **Cron jobs**):

```cron
* * * * * cd /home/forge/your-domain.com && php artisan schedule:run >> /dev/null 2>&1
```

`php artisan schedule:list` shows what runs: pruning invitations, feature overrides, expired API tokens and old audit log entries, once a day. With the app on several servers, run the cron on one of them, or add `->onOneServer()` to tasks that must run once.

## 6. Files

Files on the `public` disk live in `storage/app/public`, served through the `public/storage` link that `storage:link` creates. On one server, back that folder up with the database. On several servers, or to keep the disk small, use S3 or an S3-compatible bucket (R2, DigitalOcean Spaces, MinIO): `composer require league/flysystem-aws-s3-v3 "^3.0"`, set the `AWS_*` variables and use the `s3` disk.

## 7. HTTPS and proxies

Forge and Ploi issue Let's Encrypt certificates. Behind a load balancer or Cloudflare's proxy, trust it, so Laravel sees HTTPS and the visitor's address (the rate limits use it):

```php
// bootstrap/app.php, in withMiddleware()
$middleware->trustProxies(at: '*');   // or the load balancer's addresses
```

## 8. Stripe and the first admin

Create the live webhook with `php artisan cashier:webhook` on the server (it uses `APP_URL` and `STRIPE_SECRET`), put its signing secret in `STRIPE_WEBHOOK_SECRET`, run `php artisan optimize` again. Make yourself an admin after signing up:

```bash
php artisan tinker --execute="App\Models\User::where('email', 'you@your-domain.com')->update(['is_admin' => true])"
```

## 9. Backups

Back up the database every day, off the server, and try a restore once. Forge and Ploi both schedule database backups to S3-compatible storage; [spatie/laravel-backup](https://github.com/spatie/laravel-backup) does it from the app, files included. Stripe keeps the billing history, but the link between a Stripe customer and a workspace lives only in your database.

Then go through [the production checklist](production-checklist.md).
