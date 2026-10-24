#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd -- "$(dirname -- "$0")/.." && pwd)"
source "$project_root/.runtime/env.sh"
mkdir -p "$project_root/evidence/m2"
cd "$project_root/app"
php artisan migrate --no-interaction
php artisan migrate --env=testing --no-interaction
php artisan migrate:status --no-interaction
php artisan migrate:status --env=testing --no-interaction
php vendor/bin/phpunit --testdox --log-junit "$project_root/evidence/m2/phpunit.xml"
