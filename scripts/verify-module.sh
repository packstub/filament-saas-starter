#!/usr/bin/env bash
#
# Checks that a module recipe still works: copies the kit to a temporary
# directory, installs and migrates it as a new project would, applies
# docs/modules/<name>.md with scripts/apply-recipe.php (composer require, the
# files and lines it adds, its migrations), then runs `composer test` (Pint and
# the whole suite, the recipe's own test included) and a fresh
# `migrate:fresh --seed`. Maintainers only; a new project does not get it.
#
#   scripts/verify-module.sh flow
#   scripts/verify-module.sh ai --path packstub/agents=../../plugins/agents@1.12.0
#   scripts/verify-module.sh nitpick --keep        # leave the copy behind to look at
#
# --path <package>=<directory>@<version> installs a package from a local
# checkout (a release that is not on Packagist yet), as that version.

set -euo pipefail

usage() {
    echo "Usage: scripts/verify-module.sh <module> [--path <package>=<directory>@<version>]... [--keep]" >&2
    exit 2
}

[ $# -ge 1 ] || usage

kit="$(cd "$(dirname "$0")/.." && pwd)"
module="$1"
shift
recipe="$kit/docs/modules/$module.md"
keep=0
paths=()

while [ $# -gt 0 ]; do
    case "$1" in
        --keep) keep=1 ;;
        --path) [ $# -ge 2 ] || usage; paths+=("$2"); shift ;;
        *) usage ;;
    esac
    shift
done

[ -f "$recipe" ] || { echo "No recipe at docs/modules/$module.md" >&2; exit 2; }

copy="$(mktemp -d "${TMPDIR:-/tmp}/kit-$module.XXXXXX")"
cleanup() {
    if [ "$keep" = 1 ]; then echo "The copy stays at $copy"; else rm -rf "$copy"; fi
}
trap cleanup EXIT

step() { printf '\n==> %s\n' "$*"; }

step "Copying the kit (tracked and new files, as in the working tree) to $copy"
(cd "$kit" && git ls-files -z --cached --others --exclude-standard | rsync -a --from0 --files-from=- ./ "$copy/")
# Reuse the installed packages when there are some; composer install brings them in line with the lock.
[ -d "$kit/vendor" ] && cp -R "$kit/vendor" "$copy/vendor"

cd "$copy"
export COMPOSER_NO_INTERACTION=1

step "Installing and migrating, as a new project"
composer install --no-progress --quiet
cp .env.example .env
php artisan key:generate --quiet
touch database/database.sqlite
php artisan migrate --seed --force --quiet

for path in "${paths[@]+"${paths[@]}"}"; do
    package="${path%%=*}"
    rest="${path#*=}"
    directory="$(cd "$kit" && cd "${rest%@*}" && pwd)"
    version="${rest##*@}"
    step "Taking $package $version from $directory"
    composer config "repositories.${package//\//-}" \
        "{\"type\":\"path\",\"url\":\"$directory\",\"options\":{\"symlink\":false,\"versions\":{\"$package\":\"$version\"}}}"
done

step "Applying docs/modules/$module.md"
php "$kit/scripts/apply-recipe.php" "$recipe" "$copy"

step "Running composer test"
composer test

step "Installing from scratch with the module"
php artisan migrate:fresh --seed --force --quiet

step "docs/modules/$module.md works"
