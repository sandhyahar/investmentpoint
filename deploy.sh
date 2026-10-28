#!/bin/bash
# Update the live site from GitHub. On the server (SSH):
#   cd ~/domains/investmentpoint.in/laravel && bash deploy.sh
set -e
cd "$(dirname "$0")"

# Hostinger blocks proc_open/exec/symlink for PHP; command-line PHP may lift that per run
PHP="php -d disable_functions="

$PHP artisan down --retry=15 || true
trap '$PHP artisan up' EXIT          # the site comes back even if a step fails

git pull --ff-only
$PHP "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction
$PHP artisan migrate --force
[ -L public/storage ] || ln -s ../storage/app/public public/storage   # PHP's symlink() is blocked
$PHP artisan optimize:clear
$PHP artisan optimize

echo "Live site updated to: $(git log -1 --format='%h %s (%cd)' --date=short)"
