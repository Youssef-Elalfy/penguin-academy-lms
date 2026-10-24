#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd -- "$(dirname -- "$0")/.." && pwd)"
source "$project_root/.runtime/env.sh"
evidence_dir="$project_root/evidence/m3/latest"
mkdir -p "$evidence_dir"
cd "$project_root/app"
php artisan migrate --no-interaction | tee "$evidence_dir/migrate-development.log"
php artisan migrate --env=testing --no-interaction | tee "$evidence_dir/migrate-test.log"
php artisan migrate:status --no-interaction | tee "$evidence_dir/status-development.log"
php artisan migrate:status --env=testing --no-interaction | tee "$evidence_dir/status-test.log"
php vendor/bin/phpunit --testdox --log-junit "$evidence_dir/phpunit.xml" | tee "$evidence_dir/phpunit.log"
composer validate --strict 2>&1 | tee "$evidence_dir/composer.log"
npm run build 2>&1 | tee "$evidence_dir/build.log"
php artisan view:cache | tee "$evidence_dir/blade.log"
php artisan view:clear
php artisan route:list --json > "$evidence_dir/routes.json"
php "$project_root/tools/m3-integrity.php" > "$evidence_dir/integrity-development.json"
APP_ENV=testing php "$project_root/tools/m3-integrity.php" > "$evidence_dir/integrity-test.json"
