# Writing profile: Filament SaaS Starter

Read by the writing-kit agents (editor, reader) and by `/writing-kit:review-docs README.md`. Project-specific; the craft and voice live in the writing-kit plugin (skills `docs` and `voice`).

## Audience

A Laravel developer who sees Packstub for the first time and wants to start a SaaS this week. They know Laravel well and have at least tried Filament. They decide in a minute whether to run `laravel new --using`, and after installing they read the tour to find the file to change. Explain what the kit wires together and where; never the framework basics.

## The kit, without overstating

- **What it is:** a starter kit, a Laravel app people own after creating it, not a package they update. MIT, free.
- **What it is made of:** Filament's own single-database tenancy, Filament Teams, Filament Features and Filament Account Switcher (Packstub, free, MIT), Laravel Cashier for Stripe. Facts about a plugin come from its repo under `../../plugins/<name>` or its docs on packstub.dev, facts about the kit from this repo's code and tests; never from memory.
- **Numbers** (plan limits, test counts, versions) only as the config files, the suite and `composer.json` have them.
- **Customers and adoption:** nothing to cite. No "production-ready", no "battle-tested", no "used by".
- **Other tools** (Laravel's starter kits, Jetstream, Spark, other SaaS kits, stancl/tenancy) get credit by name when they come up, and are never the problem this kit solves.
- **Paid products** are named once, in "Going further": Filament Tenancy (database per workspace) and XLITE's SaaS MVP package. No other calls to action. The Tenancy steps there are checked against `../../plugins/filament-tenancy/docs` (`adopting-an-existing-app.md`, `database-strategies.md`), never written from memory.

## README

- The README is the only documentation; there is no docs site for the kit, so it must read on its own on GitHub and on Packagist.
- Order, on purpose different from a package README: what you get in the first hour, requirements, install, the tour (workspaces, members, plans and limits, billing, support logins, the Projects pattern, tests), going further, security, license.
- The tour names the file for every piece, as a path from the project root. UI labels are bold and spelled as the app shows them (**Invite member**, **Your plan**, **Manage billing**, **Switch back**).
- The demo logins in the README match `database/seeders/DatabaseSeeder.php`; the plan table matches `config/packstub-features.php`; the `.env` keys match `.env.example`.
- Maintainer notes (dependencies, publishing) live in `RELEASING.md`, never in the README.

## Checks

- `composer test` must pass.
- Links to packstub.dev, xlite.dev, laravel.com and filamentphp.com are checked by opening them.
