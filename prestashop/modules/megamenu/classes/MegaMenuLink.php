<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegaMenuLink extends ObjectModel
{
    use MegaMenuPositionable;

    const LINK_TYPE_CATEGORY = 'category';
    const LINK_TYPE_MANUFACTURER = 'manufacturer';
    const LINK_TYPE_CUSTOM = 'custom';

    public $id_column;
    public $position;
    public $link_type;
    public $id_category;
    public $id_manufacturer;

    public $label;
    public $custom_url;

    public static $definition = [
        'table' => 'megamenu_link',
        'primary' => 'id_link',
        'multilang' => true,
        'fields' => [
            'id_column' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'link_type' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'id_category' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
            'id_manufacturer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],

            'label' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 128],
            'custom_url' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'size' => 255],
        ],
    ];

    public static function getForColumn(int $idColumn, int $idLang): array
    {
        $sql = 'SELECT l.*, ll.label, ll.custom_url
                FROM `' . _DB_PREFIX_ . 'megamenu_link` l
                INNER JOIN `' . _DB_PREFIX_ . 'megamenu_link_lang` ll ON ll.id_link = l.id_link AND ll.id_lang = ' . (int) $idLang . '
                WHERE l.id_column = ' . (int) $idColumn . '
                ORDER BY l.position ASC';

        return Db::getInstance()->executeS($sql);
    }

    public static function getNextPosition(int $idColumn): int
    {
        return self::nextPositionInScope('megamenu_link', 'id_column', $idColumn);
    }

    public function moveUp(): bool
    {
        return $this->moveOne('megamenu_link', 'id_link', -1, 'id_column', (int) $this->id_column);
    }

    public function moveDown(): bool
    {
        return $this->moveOne('megamenu_link', 'id_link', 1, 'id_column', (int) $this->id_column);
    }
}
