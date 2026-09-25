#!/bin/sh
set -e

cd "$(dirname "$0")/.."

KEEP_FROM_FRESH_INSTALL="employee employee_account employee_session employee_shop"

STRUCTURE_ONLY="address admin_filter api_client blockwishlist_statistics cart cart_cart_rule cart_product
connections connections_page connections_source customer customer_group customer_message
customer_message_sync_imap customer_session customer_thread customization customized_data date_range
emailsubscription eventbus_incremental_sync eventbus_job eventbus_live_sync eventbus_type_sync
ganalytics ganalytics_data guest log mail mailalert_customer_oos mbo_api_config message message_readed
module_history module_preference mutation order_carrier order_cart_rule order_detail order_detail_tax
order_history order_invoice order_invoice_payment order_invoice_tax order_payment order_return
order_return_detail order_slip order_slip_detail orders page_viewed pagenotfound product_comment
product_comment_grade product_comment_report product_comment_usefulness product_sale
pscheckout_authorization pscheckout_capture pscheckout_cart pscheckout_customer pscheckout_order
pscheckout_order_matrice pscheckout_payment_token pscheckout_purchase_unit pscheckout_refund
pscheckout_tracking psgdpr_log psshipping_address psshipping_address_orders shipment shipment_product
smarty_cache smarty_last_flush smarty_lazy_cache statssearch stock_mvt supply_order supply_order_detail
supply_order_history supply_order_receipt_history tab_module_preference webservice_account
webservice_account_shop webservice_permission wishlist wishlist_product wishlist_product_cart"

SECRET_CONFIGURATION=$(cat docker/db/fresh-install-configuration.list)

IGNORED=""
for table in $KEEP_FROM_FRESH_INSTALL $STRUCTURE_ONLY configuration; do
    IGNORED="$IGNORED --ignore-table=\$MARIADB_DATABASE.ps_$table"
done

STRUCTURE_TABLES=""
for table in $STRUCTURE_ONLY; do
    STRUCTURE_TABLES="$STRUCTURE_TABLES ps_$table"
done

DUMP='mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --skip-dump-date --single-transaction "$MARIADB_DATABASE"'

{
    docker compose exec -T db sh -c "$DUMP $IGNORED"
    docker compose exec -T db sh -c "$DUMP --no-data $STRUCTURE_TABLES"
    docker compose exec -T db sh -c "$DUMP ps_configuration --where=\"name NOT IN ($SECRET_CONFIGURATION)\""
} > docker/db/prestashop.sql

docker compose exec -T db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -N -e "SELECT iso_code FROM ps_lang ORDER BY id_lang"' > docker/db/languages.list

SNAPSHOT_PATHS="img translations mails $(cd prestashop && ls -d modules/*/mails)"

rm -rf docker/files
mkdir -p docker/files
(cd prestashop && rsync -aR --exclude '.DS_Store' --exclude 'img/tmp/*.jpg' --exclude 'img/tmp/*.png' $SNAPSHOT_PATHS ../docker/files/)
echo "$SNAPSHOT_PATHS" | tr ' ' '\n' > docker/files.list

echo "Saved docker/db/prestashop.sql, docker/db/languages.list, docker/files and docker/files.list"
