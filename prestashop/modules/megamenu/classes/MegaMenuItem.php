<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegaMenuItem extends ObjectModel
{
    use MegaMenuPositionable;

    const LINK_TYPE_CATEGORY = 'category';
    const LINK_TYPE_CUSTOM = 'custom';

    public $id_category;
    public $link_type;
    public $has_dropdown;
    public $active;
    public $position;

    public $label;
    public $custom_url;

    public static $definition = [
        'table' => 'megamenu_item',
        'primary' => 'id_item',
        'multilang' => true,
        'fields' => [
            'id_category' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'link_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'has_dropdown' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],

            'label' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 128],
            'custom_url' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 255],
        ],
    ];

    public static function getAllForLang(int $idLang, bool $onlyActive = false): array
    {
        $sql = 'SELECT i.*, il.label, il.custom_url
                FROM `' . _DB_PREFIX_ . 'megamenu_item` i
                INNER JOIN `' . _DB_PREFIX_ . 'megamenu_item_lang` il ON il.id_item = i.id_item AND il.id_lang = ' . (int) $idLang . '
                ' . ($onlyActive ? ' WHERE i.active = 1 ' : '') . '
                ORDER BY i.position ASC';

        return Db::getInstance()->executeS($sql);
    }

    public static function getNextPosition(): int
    {
        return self::nextPositionInScope('megamenu_item');
    }

    public function moveUp(): bool
    {
        return $this->moveOne('megamenu_item', 'id_item', -1);
    }

    public function moveDown(): bool
    {
        return $this->moveOne('megamenu_item', 'id_item', 1);
    }
}
