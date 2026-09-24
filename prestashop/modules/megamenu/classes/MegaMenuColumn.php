<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegaMenuColumn extends ObjectModel
{
    const MAX_SLOTS = 5;

    const SEE_ALL_NONE = 'none';
    const SEE_ALL_CATEGORY = 'category';
    const SEE_ALL_MANUFACTURER = 'manufacturer';
    const SEE_ALL_CUSTOM = 'custom';

    public $id_item;
    public $position;
    public $see_all_type;
    public $id_category;
    public $id_manufacturer;

    public $title;
    public $see_all_label;
    public $see_all_custom_url;

    public static $definition = [
        'table' => 'megamenu_column',
        'primary' => 'id_column',
        'multilang' => true,
        'fields' => [
            'id_item' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'see_all_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'id_category' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_manufacturer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],

            'title' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 128],
            'see_all_label' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 128],
            'see_all_custom_url' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 255],
        ],
    ];

    public static function getForItem(int $idItem, int $idLang): array
    {
        $sql = 'SELECT c.*, cl.title, cl.see_all_label, cl.see_all_custom_url
                FROM `' . _DB_PREFIX_ . 'megamenu_column` c
                INNER JOIN `' . _DB_PREFIX_ . 'megamenu_column_lang` cl ON cl.id_column = c.id_column AND cl.id_lang = ' . (int) $idLang . '
                WHERE c.id_item = ' . (int) $idItem . '
                ORDER BY c.position ASC';

        return Db::getInstance()->executeS($sql);
    }

    public static function getUsedSlots(int $idItem, int $excludeIdColumn = 0): array
    {
        $sql = 'SELECT position FROM `' . _DB_PREFIX_ . 'megamenu_column` WHERE id_item = ' . (int) $idItem;

        if ($excludeIdColumn) {
            $sql .= ' AND id_column != ' . (int) $excludeIdColumn;
        }

        return array_map('intval', array_column(Db::getInstance()->executeS($sql), 'position'));
    }

    public static function getNextFreeSlot(int $idItem): ?int
    {
        $usedSlots = self::getUsedSlots($idItem);

        for ($slot = 1; $slot <= self::MAX_SLOTS; ++$slot) {
            if (!in_array($slot - 1, $usedSlots, true)) {
                return $slot;
            }
        }

        return null;
    }

    public static function isSlotTaken(int $idItem, int $slot, int $excludeIdColumn = 0): bool
    {
        return in_array($slot - 1, self::getUsedSlots($idItem, $excludeIdColumn), true);
    }
}
