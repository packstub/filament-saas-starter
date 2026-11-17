# Releasing

Notes for maintainers. A project created from the kit does not get this file.

## Dependencies

Everything comes from Packagist: `packstub/filament-teams` `^1.1` (1.1.0 is the first release that creates a workspace for someone who signs up on Filament's own registration page), `packstub/filament-features` `^1.1.1` (the first MIT release) and `packstub/filament-account-switcher` `^4.4`.

`config.platform.php` is `8.3.0`, so `composer update` on a newer PHP still writes a lock file that installs on PHP 8.3, the kit's minimum. Keep it there for as long as `"php": "^8.3"` stands. A new project keeps the setting, and the README's Requirements section tells its owner how to raise or remove it. Removing it in `post-create-project-cmd` was considered and left out: `config.platform` is part of the lock file's content hash, so the new project would start with a lock file Composer calls out of date, and refreshing it needs a second trip to Packagist during `laravel new`.

To work on the kit and a plugin together, add a path repository locally and leave it out of the commit:

```bash
composer config repositories.teams '{"type":"path","url":"../../plugins/filament-teams","options":{"symlink":true,"versions":{"packstub/filament-teams":"1.1.0"}}}'
composer update packstub/filament-teams
```

## Publishing

1. Create `packstub/filament-saas-starter` on GitHub, push, and wait for a green **Tests** run (the suite on PHP 8.3, 8.4 and 8.5, a fresh install, and the final **Tests passed** job).
2. Submit the repository to Packagist. `laravel new --using=packstub/filament-saas-starter` runs `composer create-project`, which needs the package there with `"type": "project"`.
3. Mark it as a template: `gh repo edit packstub/filament-saas-starter --template`.
4. Set the project secret for `add-to-project.yml`: `workspace/set-secret.sh PROJECTS_TOKEN filament-saas-starter`.
5. Copy the ruleset `main: tests must pass` from another public repo (it requires the check `Tests passed`) and switch on `allow_auto_merge`, so Dependabot pull requests merge only when CI is green. A new job in `tests.yml` goes into the `needs` of `tests-passed`.
6. Turn on Discussions: the issue templates in `.github/ISSUE_TEMPLATE/config.yml` send questions and ideas there.
7. Tag `v1.0.0` (see Versions).
8. Try it once for real: `laravel new my-app --using=packstub/filament-saas-starter`, sign in with the demo accounts, sign up as a new customer.

## What a new project runs

- `composer create-project` runs `post-root-package-install` (copies `.env.example`) and `post-create-project-cmd` (key, SQLite file, `migrate --seed`). The seeder prints the demo logins with paths (`/app`, `/admin`), because `APP_URL` is still `http://localhost` at that point.
- The Laravel installer then replaces `APP_URL=http://localhost` in `.env`; keep that exact value in `.env.example`. It also offers `npm install` and `npm run build`.
- There is no `extra.laravel.installer.post-create-project` hook: everything a new project needs has run by then.
- The installer rewrites `php-version: 'x.y'` in a starter kit's workflows. `tests.yml` uses a matrix, so it stays as it is.
- `post-autoload-dump` runs `filament:upgrade`, which publishes Filament's assets into `public/` (ignored by Git).

`.gitattributes` keeps the maintainer files out of the archive Composer installs: this file, `CHANGELOG.md`, `SECURITY.md`, `.claude`, and from `.github` the contributing guide, the issue templates, the funding file, `release.yml` and the `add-to-project`, `dependabot-auto-merge` and `github-release` workflows. A project made with `laravel new` or `composer create-project` starts without them and keeps `tests.yml` and `dependabot.yml`. **Use this template** on GitHub copies every file. A new maintainer-only file gets its own `export-ignore` line.

To try a fresh project from a local commit, export it and create a project from the export:

```bash
mkdir ../kit-export && git archive HEAD | tar -x -C ../kit-export
cd .. && composer create-project packstub/filament-saas-starter my-app \
    --repository='{"type":"path","url":"./kit-export","options":{"symlink":false}}' --stability=dev
```

## Versions

Tag releases as `v<major>.<minor>.<patch>`. `laravel new` passes `--stability=dev`, so Composer may install `main`: keep `main` installable at all times.

Changelog first, then the tag: move the lines under `## Unreleased` in `CHANGELOG.md` to a `## <version>` section, one short line per change a user of the kit would notice, and push the tag. `github-release.yml` creates the GitHub release from that section and appends the merged pull requests (`.github/release.yml` leaves Dependabot out). Never create a release by hand. Batch changes and tag about once a week at most; a security fix goes out within a day or two and says so.

People do not update a project made from the kit, so write each line for someone deciding whether to copy the change into their own app.
