#!/bin/sh
set -e

db() {
    mysql -h "$DB_SERVER" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWD" "$DB_NAME" "$@"
}

SECRET_CONFIGURATION=$(cat /tmp/project/db/fresh-install-configuration.list)

for iso in $(cat /tmp/project/db/languages.list); do
    echo "\n* Installing language pack: $iso"
    runuser -g www-data -u www-data -- php -r "require '/var/www/html/config/config.inc.php'; \$kernel = new AdminKernel('prod', false); \$kernel->boot(); if (Language::getIdByIso('$iso')) { exit(0); } \$result = Language::downloadAndInstallLanguagePack('$iso'); if (\$result !== true) { var_export(\$result); exit(1); }"
done

echo "\n* Importing project database..."

db -N -r -e "SELECT CONCAT('INSERT INTO ps_configuration (id_shop_group, id_shop, name, value, date_add, date_upd) VALUES (', QUOTE(id_shop_group), ', ', QUOTE(id_shop), ', ', QUOTE(name), ', ', QUOTE(value), ', NOW(), NOW());') FROM ps_configuration WHERE name IN ($SECRET_CONFIGURATION)" > /tmp/fresh-install-configuration.sql

db < /tmp/project/db/prestashop.sql
db < /tmp/fresh-install-configuration.sql
db -e "UPDATE ps_shop_url SET domain = '$PS_DOMAIN', domain_ssl = '$PS_DOMAIN'; UPDATE ps_contact SET email = '$ADMIN_MAIL'"

rm /tmp/fresh-install-configuration.sql

echo "\n* Restoring project files..."

for path in $(cat /tmp/project/files.list); do
    rm -rf "/var/www/html/$path"
    cp -R "/tmp/project/files/$path" "/var/www/html/$path"
    chown -R www-data:www-data "/var/www/html/$path"
done

rm -rf /var/www/html/var/cache/*

echo "\n* Project imported"
