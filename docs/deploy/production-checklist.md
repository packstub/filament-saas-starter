# Production checklist

Go through it before the first real customer signs up, and again after a big change. Each line says how to check it.

## The app

- [ ] `APP_ENV=production`: `php artisan about` shows **Environment: production**. Anything else shows one-click demo logins (`local`) or keeps the site out of search engines.
- [ ] `APP_DEBUG=false`: an unknown URL shows the plain 404 page, without a stack trace.
- [ ] `APP_KEY` is set, generated once for this environment, and kept somewhere safe besides the server. Losing it signs everyone out and locks the two-factor secrets.
- [ ] `APP_URL` is the public `https://` address: the link in a verification e-mail opens the site.
- [ ] The config is cached on every deploy (`php artisan optimize`).
- [ ] `config.platform.php` in `composer.json` matches the server's PHP version, or is gone.
- [ ] The demo accounts are gone: `demo@example.com`, `member@example.com` and `admin@example.com` cannot sign in, and their addresses are out of `app/Support/SupportLogins.php`.

## Accounts and security

- [ ] You have an admin account (`is_admin`), and `/admin` asked you to set up two-factor authentication. Every admin has it; the panel refuses to open without it outside `local`.
- [ ] Your recovery codes for that account are stored outside your phone.
- [ ] `SESSION_SECURE_COOKIE=true`, and the site redirects `http://` to `https://`.
- [ ] Google and GitHub sign-in: the callback URLs in each provider point at `APP_URL`, or the keys are empty and the buttons are gone.
- [ ] `.env` is not in Git and not readable from the web (`https://your-domain.com/.env` is a 404).

## E-mail

- [ ] `MAIL_MAILER` is a real provider, not `log`, and `MAIL_FROM_ADDRESS` is on a domain you control.
- [ ] That domain has SPF, DKIM and DMARC records for the provider (it shows how), so e-mails do not land in spam.
- [ ] A sign-up with a fresh address receives the verification e-mail within a minute.
- [ ] An invitation arrives, and its link opens the sign-up page with the address filled in.

## Queue and scheduler

- [ ] `QUEUE_CONNECTION` is not `sync`, and a worker runs: `php artisan queue:monitor default` (or Cloud's queue view) shows it draining.
- [ ] `php artisan queue:failed` is empty after the e-mail checks above.
- [ ] The scheduler runs every minute: `php artisan schedule:list` shows the daily tasks with a next run time, and they ran yesterday (Cloud: the scheduler toggle is on).

## Stripe

- [ ] `STRIPE_KEY` and `STRIPE_SECRET` are live keys (`pk_live_`, `sk_live_`), and the `STRIPE_PRICE_*` IDs are the live prices: test prices do not exist in live mode.
- [ ] The amounts in `config/billing.php` (`amount`, `unit_amount`) match the live prices: the Billing page and the revenue metrics read them from there.
- [ ] The live webhook endpoint exists (`php artisan cashier:webhook`) at `https://your-domain.com/stripe/webhook`, and `STRIPE_WEBHOOK_SECRET` is its signing secret. In Stripe's dashboard its recent deliveries answer 200.
- [ ] The customer portal is enabled in Stripe, with the plans customers may switch between.
- [ ] One real subscription, refunded afterwards: the workspace moves to the plan, the receipt reaches the owner, cancelling in the portal moves it back to Free at the period's end.
- [ ] Stripe's own e-mails (receipts, failed payments) are switched on in its customer e-mail settings.

## Files

- [ ] Uploaded files live somewhere that survives a deploy: an S3-compatible bucket (always on Laravel Cloud), or `storage/app/public` on a single server that is backed up.
- [ ] With more than one replica or server, the default disk is a shared bucket, and a logo uploaded and saved several times in a row is saved every time.
- [ ] A logo's URL opens in a private browser window (the bucket serves the files publicly).
- [ ] `php artisan storage:link` has run on a server with local files (`public/storage` exists).

## Data and backups

- [ ] The database is backed up every day, to another place than the server, and you have restored a backup once.
- [ ] Uploaded files are backed up too, or the bucket has versioning.
- [ ] `AUDIT_RETENTION_DAYS` matches what your privacy policy says.
- [ ] Terms and Privacy hold your own text, and `SITE_LEGAL_PLACEHOLDERS=false`.

## Watching it

- [ ] `/up` is checked by an uptime monitor.
- [ ] Errors reach you: an error tracker (Laravel Nightwatch, Sentry, Flare, Bugsnag), or at least `LOG_LEVEL=warning` and a look at the logs each day.
- [ ] Someone gets an e-mail when the queue stops or jobs fail.
