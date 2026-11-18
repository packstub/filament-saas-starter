# Environment variables

Every value the app reads from `.env`, grouped by what it is for. Laravel's own settings that the kit does not change (Redis tuning, Memcached, DynamoDB, log channels other than the default) are left out; the config file named in each section has them all. `.env.example` is the starting point for local development; this page says what each value should be in production.

Application code never calls `env()`; it reads `config()`, so after changing a value in production run `php artisan config:cache` again (Laravel Cloud and the deploy script in [the server guide](server.md) do it for you on every deploy).

## Application (`config/app.php`)

| Variable | Production value | What it does |
| --- | --- | --- |
| `APP_NAME` | Your product's name | The name in the panels, the e-mails, the page titles and the mail sender's name. |
| `APP_ENV` | `production` | Turns on the production password rules, refuses unsigned Stripe webhooks, hides the demo logins and skips the demo seeder. Never `local` on a server: `local` shows one-click logins. |
| `APP_KEY` | `base64:…` from `php artisan key:generate --show` | Encrypts sessions, cookies and two-factor secrets. Generate it once per environment and keep it: a new key signs everyone out and makes two-factor secrets unreadable. |
| `APP_PREVIOUS_KEYS` | Empty, or old keys separated by commas | Old keys still accepted for decryption while you rotate `APP_KEY`. |
| `APP_DEBUG` | `false` | `true` shows stack traces, with your environment's secrets, to anyone who triggers an error. |
| `APP_URL` | `https://your-domain.com` (Laravel Cloud sets one for its own domain; set yours once a custom domain is attached) | The base of every link in e-mails (verification, invitations, password reset), the canonical and sitemap URLs, and the Stripe redirect URLs. |
| `APP_LOCALE` | `en` | The language the app starts in. |
| `APP_FALLBACK_LOCALE` | `en` | The language used for a text missing from the current one. |
| `APP_FAKER_LOCALE` | `en_US` | Only used by factories, in tests and the seeder. |
| `APP_MAINTENANCE_DRIVER` | `file`, or `cache` with several servers | Where `php artisan down` is remembered. |
| `APP_MAINTENANCE_STORE` | `database` | The cache store, with `APP_MAINTENANCE_DRIVER=cache`. |
| `BCRYPT_ROUNDS` | `12` | The cost of password hashes (`config/hashing.php` in the framework). |
| `PHP_CLI_SERVER_WORKERS` | Unused | Only for `php artisan serve` in development: how many requests it handles at once. |

## Database (`config/database.php`)

| Variable | Production value | What it does |
| --- | --- | --- |
| `DB_CONNECTION` | `mysql`, `mariadb` or `pgsql` | The database. SQLite is fine on one server you back up, but not on Laravel Cloud, whose disk is reset on every deploy. Filament Teams and Filament Features follow it (`config/packstub-teams.php`, `config/packstub-features.php`). |
| `DB_URL` | Empty, or a full connection URL | Replaces the separate values below when set. |
| `DB_HOST` | Your database host | |
| `DB_PORT` | `3306` (MySQL) or `5432` (PostgreSQL) | |
| `DB_DATABASE` | Your database name | |
| `DB_USERNAME` | Your database user | |
| `DB_PASSWORD` | Your database password | |

## Sessions, cache and queue

| Variable | Production value | What it does |
| --- | --- | --- |
| `SESSION_DRIVER` | `database` (or `redis`) | Where sessions live. Never `file` with more than one server. |
| `SESSION_LIFETIME` | `120` | Minutes of inactivity before a sign-in expires. |
| `SESSION_ENCRYPT` | `false` | Encrypts the session payload at rest. |
| `SESSION_PATH` | `/` | |
| `SESSION_DOMAIN` | Empty | Set it (`.your-domain.com`) only to share sign-ins across subdomains. |
| `SESSION_SECURE_COOKIE` | `true` | Sends the session cookie over HTTPS only. |
| `CACHE_STORE` | `database` (or `redis`) | The cache, also used for the plan-limit locks (`app/Actions/CreateProject.php`) and the revenue metrics. Never `array` or `file` with more than one server: the locks must be shared. |
| `CACHE_PREFIX` | Empty | |
| `QUEUE_CONNECTION` | `database`, `redis`, or `cloud` on Laravel Cloud | Never `sync` in production: invitation e-mails, the notifications and the Stripe seat and contact syncs would run during the request and fail it with Stripe. A worker must run, see the deploy guides. |
| `REDIS_CLIENT` | `phpredis` | |
| `REDIS_HOST` | Your Redis host | Only with Redis for the cache, sessions or queue. |
| `REDIS_PASSWORD` | Your Redis password | |
| `REDIS_PORT` | `6379` | |
| `MEMCACHED_HOST` | Unused | Only with `CACHE_STORE=memcached`. |
| `BROADCAST_CONNECTION` | `log` | The kit broadcasts nothing. |

## Files (`config/filesystems.php`)

| Variable | Production value | What it does |
| --- | --- | --- |
| `FILESYSTEM_DISK` | `local`, or your bucket's disk | The default disk. |
| `AWS_ACCESS_KEY_ID` | Your bucket key | For an S3 or S3-compatible bucket (`s3` disk). Laravel Cloud fills these in when you attach a bucket. |
| `AWS_SECRET_ACCESS_KEY` | Your bucket secret | |
| `AWS_DEFAULT_REGION` | Your bucket's region | |
| `AWS_BUCKET` | Your bucket's name | |
| `AWS_URL` | Your bucket's public URL, when it has one | |
| `AWS_ENDPOINT` | Empty for AWS; the endpoint of R2, MinIO or another S3-compatible service | |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` (`true` for MinIO) | |

## Mail (`config/mail.php`)

| Variable | Production value | What it does |
| --- | --- | --- |
| `MAIL_MAILER` | `smtp`, `ses`, `postmark` or `resend` | `log` writes e-mails to the log instead of sending them: fine locally, never in production, where sign-up needs the verification e-mail. |
| `MAIL_SCHEME` | Empty, or `smtps` | |
| `MAIL_HOST` | Your SMTP host | |
| `MAIL_PORT` | `587` (or `465` with `smtps`) | |
| `MAIL_USERNAME` | Your SMTP user | |
| `MAIL_PASSWORD` | Your SMTP password | |
| `MAIL_FROM_ADDRESS` | An address on your own, verified domain | The sender of every e-mail, and the default contact address on the legal pages. |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | |
| `POSTMARK_API_KEY` | With `MAIL_MAILER=postmark` | (`config/services.php`) |
| `RESEND_API_KEY` | With `MAIL_MAILER=resend` | (`config/services.php`; Laravel Cloud sets it when you attach Resend) |

## Logs (`config/logging.php`)

| Variable | Production value | What it does |
| --- | --- | --- |
| `LOG_CHANNEL` | `stack` | |
| `LOG_STACK` | `daily` on a server; on Laravel Cloud, what Cloud sets (`laravel-cloud-socket`, so the logs show in its dashboard) | Where log lines go. `single` grows one file forever. |
| `LOG_DAILY_DAYS` | `14` | Days of `daily` log files kept. |
| `LOG_DEPRECATIONS_CHANNEL` | `null` | |
| `LOG_LEVEL` | `info` or `warning` | `debug` in production writes much more than you will read. |

## Stripe billing (`config/billing.php`, Laravel Cashier)

| Variable | Production value | What it does |
| --- | --- | --- |
| `STRIPE_KEY` | `pk_live_…` | The publishable key. Leave all Stripe keys empty to run without billing. |
| `STRIPE_SECRET` | `sk_live_…` (or a restricted `rk_live_…` key) | The secret key. Without it the Billing page explains what to set and nothing calls Stripe. |
| `STRIPE_WEBHOOK_SECRET` | `whsec_…` of the live endpoint | Required in production: without it every webhook is refused, and plans never change. |
| `STRIPE_PRICE_PRO_MONTHLY` | `price_…` (live) | The recurring prices of each paid plan. Test-mode and live-mode prices have different IDs. |
| `STRIPE_PRICE_PRO_YEARLY` | `price_…` or empty | Empty: the plan is sold monthly only. |
| `STRIPE_PRICE_BUSINESS_MONTHLY` | `price_…` (live) | |
| `STRIPE_PRICE_BUSINESS_YEARLY` | `price_…` or empty | |
| `STRIPE_PRICE_PRO` | Unset | The name of `STRIPE_PRICE_PRO_MONTHLY` before yearly prices, still read. |
| `STRIPE_PRICE_BUSINESS` | Unset | The same for `STRIPE_PRICE_BUSINESS_MONTHLY`. |
| `CASHIER_CURRENCY` | `usd`, `eur`… | The currency of the amounts the admin dashboard adds up. |
| `CASHIER_CURRENCY_LOCALE` | `en` | How amounts are formatted. |
| `CASHIER_PATH` | `stripe` | The webhook lives at `<APP_URL>/<CASHIER_PATH>/webhook`. |
| `STRIPE_WEBHOOK_TOLERANCE` | `300` | Seconds a signed webhook stays valid. |
| `BILLING_TRIAL_DAYS` | `14`, or `0` for no trials | The free trial on a workspace's first subscription. |

## Sign in with Google and GitHub (`config/services.php`)

| Variable | Production value | What it does |
| --- | --- | --- |
| `GOOGLE_CLIENT_ID` | From Google Cloud's OAuth client | A provider turns on once both of its values are set. Callback: `<APP_URL>/auth/google/callback`. |
| `GOOGLE_CLIENT_SECRET` | | |
| `GITHUB_CLIENT_ID` | From a GitHub OAuth app | Callback: `<APP_URL>/auth/github/callback`. |
| `GITHUB_CLIENT_SECRET` | | |

## The public site and the audit log

| Variable | Production value | What it does |
| --- | --- | --- |
| `SITE_TAGLINE` | Your headline | The home page's headline and page title (`config/site.php`). |
| `SITE_DESCRIPTION` | About 150 characters | The meta description and link previews. |
| `SITE_COMPANY` | Your company's legal name | The footer and the legal pages. Defaults to `APP_NAME`. |
| `SITE_CONTACT_EMAIL` | Your support address | The footer and the legal pages. Defaults to `MAIL_FROM_ADDRESS`. |
| `SITE_TWITTER` | Your X handle without the @, or unset | Link previews. |
| `SITE_LEGAL_PLACEHOLDERS` | `false` once Terms and Privacy are your own | While `true`, those pages carry a notice and stay out of search engines. |
| `AUDIT_RETENTION_DAYS` | `365`, or what your privacy policy says | Days an audit log entry is kept (`config/audit.php`). |

## Plugins

| Variable | Production value | What it does |
| --- | --- | --- |
| `PACKSTUB_FEATURES_DEFAULT_PLAN` | `free` | The plan of a new workspace, and of one whose subscription ended (`config/packstub-features.php`). |
| `PACKSTUB_FEATURES_CACHE_STORE` | Empty (the default cache) | Where resolved limits are cached. |
| `SANCTUM_TOKEN_PREFIX` | Empty, or a short prefix such as `myapp_` | Put in front of new API tokens, so secret scanners (GitHub's among them) recognise a leaked one. |

## Front end

| Variable | Production value | What it does |
| --- | --- | --- |
| `VITE_APP_NAME` | `"${APP_NAME}"` | Read by Vite at build time, so set it before `npm run build`. |
