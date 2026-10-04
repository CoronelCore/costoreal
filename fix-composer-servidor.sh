#!/bin/sh
set -eu

repo_root=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
cd "$repo_root"
composer_cli=$(command -v composer || true)
if [ -z "$composer_cli" ]; then
    echo 'Instala Composer 2 antes de regenerar los archivos del servicio.' >&2
    exit 1
fi

bin/php "$composer_cli" dump-autoload --optimize
bin/php artisan optimize:clear
bin/php artisan config:cache
bin/php artisan route:cache
bin/php artisan view:cache

echo 'Autoload y cachés regenerados con PHP 8.5. Reinicia los workers de ese servicio.'
