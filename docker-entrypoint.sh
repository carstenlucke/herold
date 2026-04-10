#!/bin/bash
set -e

# Ensure writable directories for Apache (www-data)
mkdir -p /var/www/html/database/data
chown -R www-data:www-data /var/www/html/database/data
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Run migrations and seeds as www-data so SQLite file + WAL journals
# are created with correct ownership (fixes readonly database bug)
gosu www-data php artisan migrate --force
gosu www-data php artisan db:seed --class=UserSeeder --force

exec apache2-foreground
