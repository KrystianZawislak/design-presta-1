<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegaMenuColumn extends ObjectModel
{
    use MegaMenuPositionable;

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

    public static function getNextPosition(int $idItem): int
    {
        return self::nextPositionInScope('megamenu_column', 'id_item', $idItem);
    }

    public function moveUp(): bool
    {
        return $this->moveOne('megamenu_column', 'id_column', -1, 'id_item', (int) $this->id_item);
    }

    public function moveDown(): bool
    {
        return $this->moveOne('megamenu_column', 'id_column', 1, 'id_item', (int) $this->id_item);
    }
}
