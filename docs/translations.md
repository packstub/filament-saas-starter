# Translations

The app speaks English and Spanish out of the box. Everything the kit itself says (the panel pages, the admin panel, the e-mails, the home page and the legal placeholders) goes through `__()` with its text in `lang/<locale>/*.php`. Filament brings its own translations, Spanish among many others.

## How a language is chosen

1. A signed-in person's **Language** on the **Profile** page (`users.locale`). Their e-mails and notifications use it too: `User::preferredLocale()`, Laravel's `HasLocalePreference`.
2. Before signing in, the language picked in the home page's footer (a form posting to `/locale/{locale}`, kept in the session for the visit; for someone signed in, it becomes their Profile's language, except during a support login).
3. Otherwise `APP_LOCALE`.

`App\Http\Middleware\SetLocale` applies it, on the web routes and in both panels. An e-mail to an address without an account, such as an invitation, is written in `APP_LOCALE` once a queue worker sends it.

## The plugins' pages

Filament Teams (Members, Invitations, the invitation e-mail), Filament Features (Your plan, the flags and overrides in `/admin`) and Filament Account Switcher ship in English only for now, so those pages stay English in another language. Laravel lets you translate a package without touching it: copy its file to `lang/vendor/<package>/<locale>/`, for example `vendor/packstub/filament-teams/resources/lang/en/teams.php` to `lang/vendor/packstub-teams/es/teams.php`, and translate the values.

## Where the text lives

| File | What |
| --- | --- |
| `lang/*/app.php` | Navigation groups, the language picker |
| `lang/*/profile.php`, `workspace.php`, `projects.php`, `dashboard.php`, `social.php` | The customer panel |
| `lang/*/billing.php`, `api_tokens.php`, `activity.php` | Billing, API tokens, the audit log and its event names |
| `lang/*/admin.php` | The admin panel and its dashboard |
| `lang/*/site.php`, `pricing.php` | The home page, the pricing section, Terms and Privacy |
| `lang/*/mail.php` | The kit's e-mails and the mail footer |

The plan names and descriptions in `config/packstub-features.php` and the tagline and description in `config/site.php` are configuration, in one language. For a multilingual site, put a translation key there and print it with `__()`.

## What stays as it was written

- Names kept in the database stay in the language they were written in: an audit log entry about a deleted person says "Deleted user" (`AuditEvent::DELETED_USER`), and the role and ability keys in an entry (`member`, `projects:read`) are shown as stored.
- The answer to an unsigned Stripe webhook in production is for Stripe, in English.

## Adding a language

French as the example:

1. Copy the folder: `cp -r lang/es lang/fr`, then translate every value. Keep the keys and the `:placeholders` (`:workspace`, `:count`…) as they are.
2. List it in `config/app.php`:

   ```php
   'locales' => [
       'en' => 'English',
       'es' => 'Español',
       'fr' => 'Français',
   ],
   ```

3. Laravel's own messages (validation, password reset): `php artisan lang:publish` publishes the English ones to `lang/en`; translations for other languages come from the community package [laravel-lang/lang](https://laravel-lang.com) (`composer require laravel-lang/common --dev`, then `php artisan lang:add fr`).
4. Run `composer test`. `tests/Feature/TranslationsTest.php` fails when a key exists in English but not in French, or the other way round, or when a translation drops or renames a placeholder.

## Adding text

Never write text a person reads straight into a page, a notification or a view: add a key to `lang/en/<file>.php` and to every other language, and call `__('file.key', ['name' => $value])`. Filament's static `$title`, `$navigationLabel` and `$modelLabel` cannot call `__()`; override `getTitle()`, `getNavigationLabel()` or `getModelLabel()` instead.
