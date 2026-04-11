#!/bin/bash
set -e

# Ensure writable directories for Apache (www-data)
mkdir -p /var/www/html/database/data
chown -R www-data:www-data /var/www/html/database/data
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Pass through commands (e.g. php artisan key:generate)
if [ $# -gt 0 ]; then
    exec gosu www-data "$@"
fi

# Run migrations as www-data so SQLite file + WAL journals
# are created with correct ownership (fixes readonly database bug)
gosu www-data php artisan migrate --force

exec apache2-foreground
