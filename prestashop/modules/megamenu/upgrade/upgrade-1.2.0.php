<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module)
{
    $columnExists = Db::getInstance()->executeS('
        SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'megamenu_item` LIKE "id_top_category"
    ');

    if (empty($columnExists)) {
        if (!Db::getInstance()->execute('
            ALTER TABLE `' . _DB_PREFIX_ . 'megamenu_item`
            ADD COLUMN `id_top_category` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id_category`
        ')) {
            return false;
        }
    }

    $module->seedWomenMenu();

    return true;
}
