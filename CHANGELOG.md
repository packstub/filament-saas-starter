# Changelog

All notable changes to `packstub/filament-saas-starter` are documented in this file. A project created from the kit is your own code and does not update itself: read a release's notes to see what you may want to copy over.

## Unreleased

## 1.0.0

**Upgrading:** from a copy made before monthly and yearly prices, rename `STRIPE_PRICE_PRO` and `STRIPE_PRICE_BUSINESS` in `.env` to `STRIPE_PRICE_PRO_MONTHLY` and `STRIPE_PRICE_BUSINESS_MONTHLY` (the old names are still read as the monthly prices), and run `php artisan migrate`.

First release of Filament SaaS Starter: a multi-tenant SaaS on Laravel 13 and Filament 5 to start your product from, built on Filament Teams, Filament Features, Filament Account Switcher and Laravel Cashier.

- Sign-up, log-in, password reset and a profile page, with e-mail verification before the panel opens
- Two-factor authentication with an authenticator app: optional for customers, required for the admin panel
- Password rules set in one place, longer and checked against known leaks in production
- Workspaces as tenants: one per sign-up, as many as a person likes, everything in the panel scoped to the current one
- Workspace names that match a page of the panel (New, Login, Profile…) get a slug of their own
- Members, invitations by e-mail or link, and the roles Owner, Admin and Member, with invitation e-mails on the queue
- Plans (Free, Pro, Business) with limits on projects and seats, a Your plan page, and overrides per workspace
- Stripe billing with Checkout and the customer portal; the workspace is the customer and the webhook sets its plan
- Monthly and yearly prices per plan, with a switch on the Billing page and a move to yearly billing on the same plan
- A free trial on a workspace's first subscription (14 days by default), shown on the Billing page and the dashboard
- Optional per-seat pricing: the Stripe quantity follows the members as people join and leave
- Past-due and unfinished payments handled on the Billing page, and a customer deleted in Stripe returns the workspace to the Free plan
- Unsigned Stripe webhooks refused in production
- An admin panel with users, workspaces, feature flags and Change plan
- Support logins: sign in as a customer from the admin panel and switch back, with every switch logged
- Projects as the example resource: a status enum, a policy by role, a plan limit checked under a lock
- Deleting a workspace cancels its Stripe subscription and removes its projects, subscriptions and overrides
- Delete workspace in Workspace settings, for owners, after typing the workspace's name
- Delete account on the Profile page, behind the password; workspaces only that person used go with it
- Download your data (Profile page) and Export workspace data (Workspace settings, owners), each as one JSON file
- After Transfer ownership, Stripe's receipts and billing e-mails go to the new owner
- A workspace audit log of members, roles, invitations, ownership, plan changes and deletion, on an Activity page for owners and admins
- Activity in the admin panel: every workspace's audit log, deleted workspaces and support logins included
- Demo accounts with one-click logins in the local environment, never seeded in production
- Strict Eloquent models outside production, so an N+1 query fails while you develop
- A Pest suite with architecture tests, and GitHub Actions for PHP 8.3, 8.4 and 8.5
- Laid out for a database per workspace later, with Filament Tenancy
