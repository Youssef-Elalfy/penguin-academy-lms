#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
source "$project_root/.runtime/env.sh"
cd "$project_root/app"
php -v
composer --version
php artisan --version
php artisan migrate --no-interaction
php artisan migrate --env=testing --no-interaction
php artisan migrate:status --no-interaction
php artisan route:list --except-vendor --no-interaction
php vendor/bin/phpunit --testdox --log-junit "$project_root/evidence/phpunit.xml"
