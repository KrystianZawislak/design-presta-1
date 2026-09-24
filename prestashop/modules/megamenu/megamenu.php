<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/MegaMenuPositionable.php';
require_once __DIR__ . '/classes/MegaMenuItem.php';
require_once __DIR__ . '/classes/MegaMenuColumn.php';
require_once __DIR__ . '/classes/MegaMenuLink.php';

class MegaMenu extends Module
{
    const ENABLED = 'MEGAMENU_ENABLED';
    const CATEGORIES = 'MEGAMENU_CATEGORIES';
    const COOKIE_KEY = 'id_category_megamenu';

    public function __construct()
    {
        $this->name = 'megamenu';
        $this->tab = 'front_office_features';
        $this->author = 'Design Presta';
        $this->version = '1.3.0';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mega Menu');
        $this->description = $this->l('Lets you build the header main menu (items, columns, links) and choose the header switcher categories.');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('displayMegaMenuSwitcher')
            || !$this->registerHook('displayMegaMenuNav')
            || !$this->registerHook('actionFrontControllerSetMedia')
            || !$this->installDb()
        ) {
            return false;
        }

        $this->seedDefaultMenu();

        return Configuration::updateValue(self::ENABLED, 1)
            && Configuration::updateValue(self::CATEGORIES, json_encode([]));
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::ENABLED)
            && Configuration::deleteByName(self::CATEGORIES)
            && $this->uninstallDb();
    }

    public function installDb()
    {
        return Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megamenu_item` (
                `id_item` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `id_category` INT UNSIGNED NULL,
                `id_top_category` INT UNSIGNED NOT NULL DEFAULT 0,
                `link_type` VARCHAR(16) NOT NULL DEFAULT "custom",
                `has_dropdown` TINYINT(1) NOT NULL DEFAULT 1,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `position` INT NOT NULL DEFAULT 0
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;'
        ) && Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megamenu_item_lang` (
                `id_item` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `label` VARCHAR(128) NOT NULL DEFAULT "",
                `custom_url` VARCHAR(255) NOT NULL DEFAULT "",
                PRIMARY KEY (`id_item`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;'
        ) && Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megamenu_column` (
                `id_column` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `id_item` INT UNSIGNED NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `see_all_type` VARCHAR(16) NOT NULL DEFAULT "none",
                `id_category` INT UNSIGNED NULL,
                `id_manufacturer` INT UNSIGNED NULL,
                INDEX (`id_item`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;'
        ) && Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megamenu_column_lang` (
                `id_column` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `title` VARCHAR(128) NOT NULL DEFAULT "",
                `see_all_label` VARCHAR(128) NOT NULL DEFAULT "",
                `see_all_custom_url` VARCHAR(255) NOT NULL DEFAULT "",
                PRIMARY KEY (`id_column`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;'
        ) && Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megamenu_link` (
                `id_link` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `id_column` INT UNSIGNED NOT NULL,
                `position` INT NOT NULL DEFAULT 0,
                `link_type` VARCHAR(16) NOT NULL DEFAULT "custom",
                `id_category` INT UNSIGNED NULL,
                `id_manufacturer` INT UNSIGNED NULL,
                INDEX (`id_column`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;'
        ) && Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megamenu_link_lang` (
                `id_link` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `label` VARCHAR(128) NOT NULL DEFAULT "",
                `custom_url` VARCHAR(255) NOT NULL DEFAULT "",
                PRIMARY KEY (`id_link`, `id_lang`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;'
        );
    }

    public function uninstallDb()
    {
        foreach (['megamenu_link_lang', 'megamenu_link', 'megamenu_column_lang', 'megamenu_column', 'megamenu_item_lang', 'megamenu_item'] as $table) {
            if (!Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`')) {
                return false;
            }
        }

        return true;
    }

    protected function seedDefaultMenu()
    {
        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megamenu_item`')) {
            return;
        }

        $langIds = array_map('intval', array_column(Language::getLanguages(false), 'id_lang'));
        $ml = function (string $pl, ?string $en = null) use ($langIds) {
            $values = [];
            foreach ($langIds as $idLang) {
                $values[$idLang] = $idLang === 2 && $en !== null ? $en : $pl;
            }

            return $values;
        };

        $custom = ['type' => 'custom', 'id_category' => 0, 'url' => '#'];
        $cat = fn (int $id) => ['type' => 'category', 'id_category' => $id, 'url' => ''];

        $newProductsUrl = $this->context->link->getPageLink('new-products');
        $pricesDropUrl = $this->context->link->getPageLink('prices-drop');
        $manufacturerUrl = $this->context->link->getPageLink('manufacturer');

        $items = [
            [
                'label' => $ml('Nowości', 'New in'),
                'has_dropdown' => false,
                'link' => ['type' => 'custom', 'url' => $newProductsUrl],
                'columns' => [],
            ],
            [
                'label' => $ml('Odzież', 'Clothing'),
                'has_dropdown' => true,
                'link' => $custom,
                'columns' => [
                    $this->col($ml('Kobiety', 'Women'), $cat(13), [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Sport', 'Sport'), $custom),
                        $this->lnk($ml('Premium', 'Premium'), $custom),
                    ]),
                    $this->col($ml('Mężczyźni', 'Men'), $cat(25), [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Sport', 'Sport'), $custom),
                        $this->lnk($ml('Premium', 'Premium'), $custom),
                    ]),
                    $this->col($ml('Dzieci', 'Kids'), $cat(37), [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Sport', 'Sport'), $custom),
                        $this->lnk($ml('Premium', 'Premium'), $custom),
                    ]),
                ],
            ],
            [
                'label' => $ml('Obuwie', 'Shoes'),
                'has_dropdown' => true,
                'link' => $custom,
                'columns' => [
                    $this->col($ml('Kobiety', 'Women'), $cat(94), [
                        $this->lnk($ml('Sneakersy', 'Sneakers'), $cat(95)),
                        $this->lnk($ml('Botki', 'Ankle boots'), $cat(97)),
                        $this->lnk($ml('Płaskie buty', 'Flat shoes'), $custom),
                        $this->lnk($ml('Szpilki', 'Heels'), $custom),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $custom),
                    ]),
                    $this->col($ml('Mężczyźni', 'Men'), $cat(106), [
                        $this->lnk($ml('Sneakersy', 'Sneakers'), $cat(107)),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $custom),
                        $this->lnk($ml('Obuwie biznesowe', 'Business shoes'), $custom),
                        $this->lnk($ml('Obuwie zimowe', 'Winter shoes'), $custom),
                        $this->lnk($ml('Obuwie outdoorowe', 'Outdoor shoes'), $custom),
                    ]),
                    $this->col($ml('Dzieci', 'Kids'), $cat(118), [
                        $this->lnk($ml('Dla dziewczynek', 'For girls'), $custom),
                        $this->lnk($ml('Dla chłopców', 'For boys'), $custom),
                        $this->lnk($ml('Dla niemowląt', 'For babies'), $custom),
                    ]),
                ],
            ],
            [
                'label' => $ml('Sport', 'Sport'),
                'has_dropdown' => true,
                'link' => $custom,
                'columns' => [
                    $this->col($ml('Kobiety', 'Women'), $custom, [
                        $this->lnk($ml('Odzież sportowa', 'Sportswear'), $custom),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $custom),
                        $this->lnk($ml('Plecaki i torby', 'Backpacks and bags'), $custom),
                        $this->lnk($ml('Akcesoria sportowe', 'Sport accessories'), $custom),
                        $this->lnk($ml('Nowości', 'New in'), $custom),
                    ]),
                    $this->col($ml('Mężczyźni', 'Men'), $custom, [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Plecaki i torby', 'Backpacks and bags'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Nowości', 'New in'), $custom),
                    ]),
                    $this->col($ml('Dzieci', 'Kids'), $custom, [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Plecaki i torby', 'Backpacks and bags'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Nowości', 'New in'), $custom),
                    ]),
                ],
            ],
            [
                'label' => $ml('Akcesoria', 'Accessories'),
                'has_dropdown' => true,
                'link' => $custom,
                'columns' => [
                    $this->col($ml('Kobiety', 'Women'), $cat(24), [
                        $this->lnk($ml('Torby i plecaki', 'Bags and backpacks'), $cat(59)),
                        $this->lnk($ml('Zegarki', 'Watches'), $cat(63)),
                        $this->lnk($ml('Okulary', 'Sunglasses'), $custom),
                        $this->lnk($ml('Czapki', 'Hats'), $custom),
                        $this->lnk($ml('Szaliki', 'Scarves'), $custom),
                    ]),
                    $this->col($ml('Mężczyźni', 'Men'), $cat(36), [
                        $this->lnk($ml('Portfele', 'Wallets'), $custom),
                        $this->lnk($ml('Zegarki', 'Watches'), $cat(77)),
                        $this->lnk($ml('Okulary', 'Sunglasses'), $custom),
                        $this->lnk($ml('Paski', 'Belts'), $cat(75)),
                        $this->lnk($ml('Czapki', 'Hats'), $custom),
                    ]),
                    $this->col($ml('Dzieci', 'Kids'), $cat(48), [
                        $this->lnk($ml('Paski', 'Belts'), $cat(92)),
                        $this->lnk($ml('Torby i plecaki', 'Bags and backpacks'), $custom),
                        $this->lnk($ml('Chusty i szaliki', 'Scarves'), $custom),
                        $this->lnk($ml('Czapki i kapelusze', 'Hats and caps'), $custom),
                        $this->lnk($ml('Akcesoria dla niemowląt', 'Baby accessories'), $custom),
                    ]),
                ],
            ],
            [
                'label' => $ml('Premium', 'Premium'),
                'has_dropdown' => true,
                'link' => $custom,
                'columns' => [
                    $this->col($ml('Kobiety', 'Women'), $custom, [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Moda Designer', 'Designer fashion'), $custom),
                        $this->lnk($ml('Nowości', 'New in'), $custom),
                    ]),
                    $this->col($ml('Mężczyźni', 'Men'), $custom, [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Moda Designer', 'Designer fashion'), $custom),
                        $this->lnk($ml('Nowości', 'New in'), $custom),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Lista marek', 'Brand list'), $custom, [
                        $this->lnk($ml('MOSCHINO'), $custom),
                        $this->lnk($ml('Versace'), $custom),
                        $this->lnk($ml('Marni'), $custom),
                        $this->lnk($ml('Missoni'), $custom),
                        $this->lnk($ml('KOCHÉ'), $custom),
                    ]),
                ],
            ],
            [
                'label' => $ml('Kosmetyki', 'Beauty'),
                'has_dropdown' => true,
                'link' => $custom,
                'columns' => [
                    $this->col($ml('Kobiety', 'Women'), $custom, [
                        $this->lnk($ml('Nowości', 'New in'), $custom),
                        $this->lnk($ml('Perfumy', 'Perfume'), $custom),
                        $this->lnk($ml('Makijaż', 'Makeup'), $custom),
                        $this->lnk($ml('Pielęgnacja', 'Skincare'), $custom),
                        $this->lnk($ml('Włosy', 'Hair'), $custom),
                    ]),
                    $this->col($ml('Mężczyźni', 'Men'), $custom, [
                        $this->lnk($ml('Nowości', 'New in'), $custom),
                        $this->lnk($ml('Perfumy', 'Perfume'), $custom),
                        $this->lnk($ml('Golenie', 'Shaving'), $custom),
                        $this->lnk($ml('Włosy', 'Hair'), $custom),
                        $this->lnk($ml('Zestawy', 'Sets'), $custom),
                    ]),
                ],
            ],
            [
                'label' => $ml('Wyprzedaż %', 'Sale %'),
                'has_dropdown' => true,
                'link' => ['type' => 'custom', 'url' => $pricesDropUrl],
                'columns' => [
                    $this->col($ml('Kobiety', 'Women'), $custom, [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Sport', 'Sport'), $custom),
                        $this->lnk($ml('Premium', 'Premium'), $custom),
                    ]),
                    $this->col($ml('Mężczyźni', 'Men'), $custom, [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Sport', 'Sport'), $custom),
                        $this->lnk($ml('Premium', 'Premium'), $custom),
                    ]),
                    $this->col($ml('Dzieci', 'Kids'), $custom, [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $custom),
                        $this->lnk($ml('Sport', 'Sport'), $custom),
                        $this->lnk($ml('Designer', 'Designer'), $custom),
                    ]),
                ],
            ],
            [
                'label' => $ml('Marki', 'Brands'),
                'has_dropdown' => true,
                'link' => ['type' => 'custom', 'url' => $manufacturerUrl],
                'columns' => [
                    $this->colWithSeeAllLabel($ml('Kobiety', 'Women'), $ml('Wszystkie marki', 'All brands'), $custom, [
                        $this->lnk($ml('Marki sportowe', 'Sports brands'), $custom),
                        $this->lnk($ml('Designer'), $custom),
                        $this->lnk($ml('Nike'), $custom),
                        $this->lnk($ml('Next'), $custom),
                    ]),
                    $this->colWithSeeAllLabel($ml('Mężczyźni', 'Men'), $ml('Wszystkie marki', 'All brands'), $custom, [
                        $this->lnk($ml('Marki sportowe', 'Sports brands'), $custom),
                        $this->lnk($ml('Designer'), $custom),
                        $this->lnk($ml('Nike'), $custom),
                        $this->lnk($ml('Tommy Hilfiger'), $custom),
                    ]),
                    $this->colWithSeeAllLabel($ml('Dzieci', 'Kids'), $ml('Wszystkie marki', 'All brands'), $custom, [
                        $this->lnk($ml('Marki sportowe', 'Sports brands'), $custom),
                        $this->lnk($ml('Designer'), $custom),
                        $this->lnk($ml('Nike'), $custom),
                        $this->lnk($ml('adidas Originals'), $custom),
                    ]),
                ],
            ],
        ];

        $this->persistMenuItems($items, $langIds);
    }

    protected function persistMenuItems(array $items, array $langIds): void
    {
        foreach ($items as $position => $itemData) {
            $item = new MegaMenuItem();
            $item->link_type = $itemData['link']['type'];
            $item->id_category = (int) ($itemData['link']['id_category'] ?? 0);
            $item->id_top_category = (int) ($itemData['id_top_category'] ?? 0);
            $item->has_dropdown = $itemData['has_dropdown'];
            $item->active = true;
            $item->position = $position;
            $item->label = $itemData['label'];
            $item->custom_url = $this->fillLang($langIds, $itemData['link']['type'] === 'custom' ? $itemData['link']['url'] : '');
            $item->add();

            foreach ($itemData['columns'] as $columnPosition => $columnData) {
                $column = new MegaMenuColumn();
                $column->id_item = (int) $item->id;
                $column->position = $columnPosition;
                $column->see_all_type = $columnData['see_all']['type'];
                $column->id_category = (int) ($columnData['see_all']['id_category'] ?? 0);
                $column->id_manufacturer = (int) ($columnData['see_all']['id_manufacturer'] ?? 0);
                $column->title = $columnData['title'];
                $column->see_all_label = $columnData['see_all_label'];
                $column->see_all_custom_url = $this->fillLang($langIds, $columnData['see_all']['type'] === 'custom' ? $columnData['see_all']['url'] : '');
                $column->add();

                foreach ($columnData['links'] as $linkPosition => $linkData) {
                    $link = new MegaMenuLink();
                    $link->id_column = (int) $column->id;
                    $link->position = $linkPosition;
                    $link->link_type = $linkData['target']['type'];
                    $link->id_category = (int) ($linkData['target']['id_category'] ?? 0);
                    $link->id_manufacturer = (int) ($linkData['target']['id_manufacturer'] ?? 0);
                    $link->label = $linkData['label'];
                    $link->custom_url = $this->fillLang($langIds, $linkData['target']['type'] === 'custom' ? $linkData['target']['url'] : '');
                    $link->add();
                }
            }
        }
    }

    public function findTopCategoryIdByName(string $name): int
    {
        foreach ($this->getTopCategories() as $category) {
            if (Tools::strtolower(trim($category['name'])) === Tools::strtolower(trim($name))) {
                return (int) $category['id_category'];
            }
        }

        return 0;
    }

    protected function findOrCreateCategory(array $nameByLang, int $idParent): int
    {
        $idLang = (int) Configuration::get('PS_LANG_DEFAULT');
        $name = $nameByLang[$idLang] ?? reset($nameByLang);

        $existingId = (int) Db::getInstance()->getValue('
            SELECT c.id_category
            FROM `' . _DB_PREFIX_ . 'category` c
            INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category AND cl.id_lang = ' . $idLang . '
            WHERE c.id_parent = ' . (int) $idParent . ' AND cl.name = "' . pSQL($name) . '"
        ');

        if ($existingId) {
            return $existingId;
        }

        $category = new Category();
        $category->id_parent = $idParent;
        $category->active = true;
        $category->name = $nameByLang;
        $category->link_rewrite = array_map(fn ($langName) => Tools::str2url($langName), $nameByLang);
        $category->add();

        return (int) $category->id;
    }

    protected function findOrCreateManufacturer(string $name): int
    {
        $existingId = (int) Db::getInstance()->getValue('
            SELECT id_manufacturer FROM `' . _DB_PREFIX_ . 'manufacturer` WHERE name = "' . pSQL($name) . '"
        ');

        if ($existingId) {
            return $existingId;
        }

        $manufacturer = new Manufacturer();
        $manufacturer->name = $name;
        $manufacturer->active = true;
        $manufacturer->add();

        return (int) $manufacturer->id;
    }

    public function seedWomenMenu(): bool
    {
        $idTopCategory = $this->findTopCategoryIdByName('Kobieta');

        if (!$idTopCategory || Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megamenu_item` WHERE id_top_category = ' . (int) $idTopCategory
        )) {
            return false;
        }

        $langIds = array_map('intval', array_column(Language::getLanguages(false), 'id_lang'));
        $ml = function (string $pl, ?string $en = null) use ($langIds) {
            $values = [];
            foreach ($langIds as $idLang) {
                $values[$idLang] = $idLang === 2 && $en !== null ? $en : $pl;
            }

            return $values;
        };

        $none = ['type' => MegaMenuColumn::SEE_ALL_NONE, 'id_category' => 0, 'id_manufacturer' => 0, 'url' => ''];
        $custom = fn (string $url = '#') => ['type' => 'custom', 'id_category' => 0, 'id_manufacturer' => 0, 'url' => $url];
        $cat = fn (int $id) => ['type' => 'category', 'id_category' => $id, 'id_manufacturer' => 0, 'url' => ''];
        $mnf = fn (int $id) => ['type' => 'manufacturer', 'id_category' => 0, 'id_manufacturer' => $id, 'url' => ''];

        $newProductsUrl = $this->context->link->getPageLink('new-products');
        $pricesDropUrl = $this->context->link->getPageLink('prices-drop');
        $manufacturerUrl = $this->context->link->getPageLink('manufacturer');

        $items = [
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Nowości', 'New in'),
                'has_dropdown' => true,
                'link' => ['type' => 'custom', 'url' => $newProductsUrl],
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $custom($newProductsUrl), [
                        $this->lnk($ml('Wszystkie nowości', 'All new in'), $custom($newProductsUrl)),
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(13)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(94)),
                        $this->lnk($ml('Sport', 'Sport'), $cat(130)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(24)),
                        $this->lnk($ml('Premium', 'Premium'), $cat(133)),
                    ]),
                    $this->col($ml('Trendy', 'Trends'), $none, [
                        $this->lnk($ml('Nowe', 'New'), $custom()),
                        $this->lnk($ml("Jesień/Zima '26", "Fall/Winter '26"), $custom()),
                        $this->lnk($ml('Krata', 'Check print'), $custom()),
                        $this->lnk($ml('Topy w paski', 'Striped tops'), $custom()),
                        $this->lnk($ml('Bomberki', 'Bomber jackets'), $custom()),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Polskie marki', 'Polish brands'), $custom()),
                        $this->lnk($ml('Moda w przystępnych cenach', 'Affordable fashion'), $custom()),
                        $this->lnk($ml('Wyłącznie u nas!', 'Exclusive to us!'), $custom()),
                        $this->lnk($ml('lululemon - daj się ponieść', 'lululemon - let go'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Odzież', 'Clothing'),
                'has_dropdown' => true,
                'link' => $cat(13),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(13), [
                        $this->lnk($ml('Sukienki', 'Dresses'), $cat(49)),
                        $this->lnk($ml('Koszulki i topy', 'T-shirts and tops'), $cat(51)),
                        $this->lnk($ml('Swetry i bluzy', 'Sweaters and hoodies'), $cat(50)),
                        $this->lnk($ml('Jeansy', 'Jeans'), $cat(53)),
                        $this->lnk($ml('Spodnie', 'Trousers'), $cat(52)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('Tommy Hilfiger'), $mnf(10)),
                        $this->lnk($ml('Next'), $mnf(9)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Polskie marki', 'Polish brands'), $custom()),
                        $this->lnk($ml('Pre-Owned'), $custom()),
                        $this->lnk($ml('Wyłącznie u nas!', 'Exclusive to us!'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Obuwie', 'Shoes'),
                'has_dropdown' => true,
                'link' => $cat(94),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(94), [
                        $this->lnk($ml('Sneakersy', 'Sneakers'), $cat(95)),
                        $this->lnk($ml('Sandały', 'Sandals'), $cat(96)),
                        $this->lnk($ml('Botki', 'Ankle boots'), $cat(97)),
                        $this->lnk($ml('Szpilki i czółenka', 'Heels and pumps'), $cat(98)),
                        $this->lnk($ml('Płaskie buty', 'Flat shoes'), $cat(137)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('Next'), $mnf(9)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Nowe sneakersy - Hot Drop', 'New sneakers - Hot Drop'), $custom()),
                        $this->lnk($ml('Buty na lato', 'Summer shoes'), $custom()),
                        $this->lnk($ml('Na szeroką stopę', 'Wide fit'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Sport', 'Sport'),
                'has_dropdown' => true,
                'link' => $cat(130),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(130), [
                        $this->lnk($ml('Odzież sportowa', 'Sportswear'), $cat(157)),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $cat(158)),
                        $this->lnk($ml('Plecaki i torby', 'Backpacks and bags'), $cat(159)),
                        $this->lnk($ml('Akcesoria sportowe', 'Sport accessories'), $cat(160)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Nike'), $mnf(8)),
                    ]),
                    $this->col($ml('Dyscypliny', 'Disciplines'), $none, [
                        $this->lnk($ml('Trening', 'Training'), $custom()),
                        $this->lnk($ml('Bieganie', 'Running'), $custom()),
                        $this->lnk($ml('Joga i pilates', 'Yoga and pilates'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Streetwear'),
                'has_dropdown' => true,
                'link' => $custom(),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $none, [
                        $this->lnk($ml('Bluzy', 'Hoodies'), $cat(50)),
                        $this->lnk($ml('Koszulki i topy', 'T-shirts and tops'), $cat(51)),
                        $this->lnk($ml('Spodnie', 'Trousers'), $cat(52)),
                        $this->lnk($ml('Sneakersy', 'Sneakers'), $cat(95)),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $cat(139)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Nike'), $mnf(8)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Streetwear Corner'), $custom()),
                        $this->lnk($ml('Tylko u nas', 'Only here'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Akcesoria', 'Accessories'),
                'has_dropdown' => true,
                'link' => $cat(24),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(24), [
                        $this->lnk($ml('Torby i plecaki', 'Bags and backpacks'), $cat(59)),
                        $this->lnk($ml('Okulary', 'Sunglasses'), $cat(147)),
                        $this->lnk($ml('Paski', 'Belts'), $cat(62)),
                        $this->lnk($ml('Czapki', 'Hats'), $cat(148)),
                        $this->lnk($ml('Biżuteria', 'Jewellery'), $cat(60)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Tommy Hilfiger'), $mnf(10)),
                        $this->lnk($ml('Next'), $mnf(9)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Akcesoria Pre-owned', 'Pre-owned accessories'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Beauty'),
                'has_dropdown' => true,
                'link' => $custom($this->context->link->getCategoryLink(135)),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(135), [
                        $this->lnk($ml('Perfumy', 'Perfume'), $cat(163)),
                        $this->lnk($ml('Makijaż', 'Makeup'), $cat(164)),
                        $this->lnk($ml('Pielęgnacja', 'Skincare'), $cat(165)),
                        $this->lnk($ml('Włosy', 'Hair'), $cat(166)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Luxury Beauty'), $custom()),
                        $this->lnk($ml('Koreańskie', 'Korean beauty'), $custom()),
                        $this->lnk($ml('Zestawy', 'Gift sets'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Premium'),
                'has_dropdown' => true,
                'link' => $cat(133),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(133), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(13)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(94)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(24)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Versace'), $mnf(4)),
                        $this->lnk($ml('Marni'), $mnf(5)),
                        $this->lnk($ml('Missoni'), $mnf(6)),
                        $this->lnk($ml('MOSCHINO'), $mnf(3)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Designer'),
                'has_dropdown' => true,
                'link' => $custom($this->context->link->getCategoryLink(171)),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(171), [
                        $this->lnk($ml('Odzież', 'Clothing'), $custom()),
                        $this->lnk($ml('Obuwie', 'Shoes'), $custom()),
                        $this->lnk($ml('Torby', 'Bags'), $custom()),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('MOSCHINO'), $mnf(3)),
                        $this->lnk($ml('Versace'), $mnf(4)),
                        $this->lnk($ml('KOCHÉ'), $mnf(7)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Wyprzedaż %', 'Sale %'),
                'has_dropdown' => true,
                'link' => ['type' => 'custom', 'url' => $pricesDropUrl],
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $custom($pricesDropUrl), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(13)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(94)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(24)),
                        $this->lnk($ml('Sport', 'Sport'), $cat(130)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Nike'), $mnf(8)),
                    ]),
                ],
            ],
        ];

        $this->persistMenuItems($items, $langIds);

        return true;
    }

    public function seedMenMenu(): bool
    {
        $idTopCategory = $this->findTopCategoryIdByName('Mężczyzna');

        if (!$idTopCategory || Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megamenu_item` WHERE id_top_category = ' . (int) $idTopCategory
        )) {
            return false;
        }

        $langIds = array_map('intval', array_column(Language::getLanguages(false), 'id_lang'));
        $ml = function (string $pl, ?string $en = null) use ($langIds) {
            $values = [];
            foreach ($langIds as $idLang) {
                $values[$idLang] = $idLang === 2 && $en !== null ? $en : $pl;
            }

            return $values;
        };

        $none = ['type' => MegaMenuColumn::SEE_ALL_NONE, 'id_category' => 0, 'id_manufacturer' => 0, 'url' => ''];
        $custom = fn (string $url = '#') => ['type' => 'custom', 'id_category' => 0, 'id_manufacturer' => 0, 'url' => $url];
        $cat = fn (int $id) => ['type' => 'category', 'id_category' => $id, 'id_manufacturer' => 0, 'url' => ''];
        $mnf = fn (int $id) => ['type' => 'manufacturer', 'id_category' => 0, 'id_manufacturer' => $id, 'url' => ''];

        $newProductsUrl = $this->context->link->getPageLink('new-products');
        $pricesDropUrl = $this->context->link->getPageLink('prices-drop');
        $manufacturerUrl = $this->context->link->getPageLink('manufacturer');

        $idStreetwear = $this->findOrCreateCategory($ml('Streetwear'), $idTopCategory);
        $idSportOdziez = $this->findOrCreateCategory($ml('Odzież sportowa', 'Sportswear'), 131);
        $idSportObuwie = $this->findOrCreateCategory($ml('Obuwie sportowe', 'Sports shoes'), 131);
        $idSportAkcesoria = $this->findOrCreateCategory($ml('Akcesoria sportowe', 'Sport accessories'), 131);
        $idCialo = $this->findOrCreateCategory($ml('Ciało', 'Body care'), 136);
        $idTwarz = $this->findOrCreateCategory($ml('Twarz', 'Face care'), 136);
        $idDesignerOdziez = $this->findOrCreateCategory($ml('Odzież', 'Clothing'), 172);
        $idDesignerObuwie = $this->findOrCreateCategory($ml('Obuwie', 'Shoes'), 172);
        $idDesignerTorby = $this->findOrCreateCategory($ml('Torby', 'Bags'), 172);
        $idDesignerAkcesoria = $this->findOrCreateCategory($ml('Akcesoria', 'Accessories'), 172);
        $idDesignerOkulary = $this->findOrCreateCategory($ml('Okulary przeciwsłoneczne', 'Sunglasses'), 172);

        $mNewBalance = $this->findOrCreateManufacturer('New Balance');
        $mTimberland = $this->findOrCreateManufacturer('Timberland');
        $mJordan = $this->findOrCreateManufacturer('Jordan');
        $mOn = $this->findOrCreateManufacturer('On');
        $mSalomon = $this->findOrCreateManufacturer('Salomon');
        $mAsics = $this->findOrCreateManufacturer('ASICS');
        $mLacoste = $this->findOrCreateManufacturer('Lacoste');
        $mNorthFace = $this->findOrCreateManufacturer('The North Face');
        $mUrbanClassics = $this->findOrCreateManufacturer('Urban Classics');
        $mVans = $this->findOrCreateManufacturer('Vans');
        $mLevis = $this->findOrCreateManufacturer("Levi's");
        $mPoloRalphLauren = $this->findOrCreateManufacturer('Polo Ralph Lauren');
        $mCalvinKlein = $this->findOrCreateManufacturer('Calvin Klein');
        $mHugo = $this->findOrCreateManufacturer('HUGO');
        $mPaulSmith = $this->findOrCreateManufacturer('Paul Smith');
        $mGucci = $this->findOrCreateManufacturer('Gucci');
        $mArmaniBeauty = $this->findOrCreateManufacturer('Armani Beauty');
        $mBiotherm = $this->findOrCreateManufacturer('Biotherm');
        $mAzzaro = $this->findOrCreateManufacturer('Azzaro Parfums');
        $mRituals = $this->findOrCreateManufacturer('Rituals');
        $mGuessFragrances = $this->findOrCreateManufacturer('Guess Fragrances');
        $mNapapijri = $this->findOrCreateManufacturer('Napapijri');
        $mGap = $this->findOrCreateManufacturer('GAP');
        $mRayBan = $this->findOrCreateManufacturer('Ray-Ban');
        $mBoss = $this->findOrCreateManufacturer('BOSS');

        $items = [
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Nowości', 'New in'),
                'has_dropdown' => true,
                'link' => ['type' => 'custom', 'url' => $newProductsUrl],
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $custom($newProductsUrl), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(25)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(106)),
                        $this->lnk($ml('Sport', 'Sport'), $cat(131)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(36)),
                        $this->lnk($ml('Kosmetyki', 'Beauty'), $cat(136)),
                    ]),
                    $this->col($ml('Trendy', 'Trends'), $none, [
                        $this->lnk($ml('Nowe', 'New'), $custom()),
                        $this->lnk($ml("Jesień/Zima '26", "Fall/Winter '26"), $custom()),
                        $this->lnk($ml('Najnowsze trendy', 'Latest trends'), $custom()),
                        $this->lnk($ml('Na specjalne okazje', 'For special occasions'), $custom()),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Polskie marki na Zalando', 'Polish brands'), $custom()),
                        $this->lnk($ml('Hot Dropy już dostępne!', 'Hot Drops now available!'), $custom()),
                        $this->lnk($ml('Moda w przystępnych cenach', 'Affordable fashion'), $custom()),
                        $this->lnk($ml('Wyłącznie u nas!', 'Exclusive to us!'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Odzież', 'Clothing'),
                'has_dropdown' => true,
                'link' => $cat(25),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(25), [
                        $this->lnk($ml('T-shirty i koszulki polo', 'T-shirts and polos'), $cat(64)),
                        $this->lnk($ml('Koszule', 'Shirts'), $cat(65)),
                        $this->lnk($ml('Bluzy', 'Hoodies'), $cat(66)),
                        $this->lnk($ml('Spodnie', 'Trousers'), $cat(67)),
                        $this->lnk($ml('Jeansy', 'Jeans'), $cat(68)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Polo Ralph Lauren'), $mnf($mPoloRalphLauren)),
                        $this->lnk($ml('Tommy Hilfiger'), $mnf(10)),
                        $this->lnk($ml("Levi's"), $mnf($mLevis)),
                        $this->lnk($ml('Lacoste'), $mnf($mLacoste)),
                        $this->lnk($ml('GAP'), $mnf($mGap)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Polskie marki', 'Polish brands'), $custom()),
                        $this->lnk($ml('Marki własne Zalando', 'Zalando own brands'), $custom()),
                        $this->lnk($ml('Wyłącznie u nas!', 'Exclusive to us!'), $custom()),
                        $this->lnk($ml('Pre-Owned'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Obuwie', 'Shoes'),
                'has_dropdown' => true,
                'link' => $cat(106),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(106), [
                        $this->lnk($ml('Sneakersy', 'Sneakers'), $cat(107)),
                        $this->lnk($ml('Buty sportowe', 'Sports shoes'), $cat(108)),
                        $this->lnk($ml('Mokasyny i buty wsuwane', 'Loafers and slip-ons'), $cat(109)),
                        $this->lnk($ml('Botki', 'Boots'), $cat(110)),
                        $this->lnk($ml('Buty eleganckie', 'Formal shoes'), $cat(111)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('New Balance'), $mnf($mNewBalance)),
                        $this->lnk($ml('Timberland'), $mnf($mTimberland)),
                        $this->lnk($ml('Jordan'), $mnf($mJordan)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Nowe sneakersy - Hot Drops', 'New sneakers - Hot Drops'), $custom()),
                        $this->lnk($ml('Polskie marki', 'Polish brands'), $custom()),
                        $this->lnk($ml('Wyłącznie u nas!', 'Exclusive to us!'), $custom()),
                        $this->lnk($ml('Na szeroką stopę', 'Wide fit'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Sport', 'Sport'),
                'has_dropdown' => true,
                'link' => $cat(131),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(131), [
                        $this->lnk($ml('Odzież sportowa', 'Sportswear'), $cat($idSportOdziez)),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $cat($idSportObuwie)),
                        $this->lnk($ml('Plecaki i torby', 'Backpacks and bags'), $cat(161)),
                        $this->lnk($ml('Akcesoria sportowe', 'Sport accessories'), $cat($idSportAkcesoria)),
                    ]),
                    $this->col($ml('Dyscypliny', 'Disciplines'), $none, [
                        $this->lnk($ml('Trening', 'Training'), $custom()),
                        $this->lnk($ml('Bieganie', 'Running'), $custom()),
                        $this->lnk($ml('Piłka nożna', 'Football'), $custom()),
                        $this->lnk($ml('Koszykówka', 'Basketball'), $custom()),
                        $this->lnk($ml('Outdoor'), $custom()),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('On'), $mnf($mOn)),
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Salomon'), $mnf($mSalomon)),
                        $this->lnk($ml('ASICS'), $mnf($mAsics)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Streetwear'),
                'has_dropdown' => true,
                'link' => $cat($idStreetwear),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat($idStreetwear), [
                        $this->lnk($ml('Bluzy', 'Hoodies'), $cat(66)),
                        $this->lnk($ml('Spodnie', 'Trousers'), $cat(67)),
                        $this->lnk($ml('Jeansy', 'Jeans'), $cat(68)),
                        $this->lnk($ml('Sneakersy', 'Sneakers'), $cat(107)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Vans'), $mnf($mVans)),
                        $this->lnk($ml('Urban Classics'), $mnf($mUrbanClassics)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Tylko u nas', 'Only here'), $custom()),
                        $this->lnk($ml('Streetwear Trends'), $custom()),
                        $this->lnk($ml('Skate Hub'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Akcesoria', 'Accessories'),
                'has_dropdown' => true,
                'link' => $cat(36),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(36), [
                        $this->lnk($ml('Torby i plecaki', 'Bags and backpacks'), $cat(74)),
                        $this->lnk($ml('Czapki i kapelusze', 'Hats and caps'), $cat(76)),
                        $this->lnk($ml('Zegarki', 'Watches'), $cat(77)),
                        $this->lnk($ml('Okulary', 'Sunglasses'), $cat(151)),
                        $this->lnk($ml('Paski', 'Belts'), $cat(75)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Tommy Hilfiger'), $mnf(10)),
                        $this->lnk($ml('Polo Ralph Lauren'), $mnf($mPoloRalphLauren)),
                        $this->lnk($ml('BOSS'), $mnf($mBoss)),
                        $this->lnk($ml('Lacoste'), $mnf($mLacoste)),
                        $this->lnk($ml('Ray-Ban'), $mnf($mRayBan)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Premium'),
                'has_dropdown' => true,
                'link' => $cat(134),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(134), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(25)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(106)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(36)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Polo Ralph Lauren'), $mnf($mPoloRalphLauren)),
                        $this->lnk($ml('Tommy Hilfiger'), $mnf(10)),
                        $this->lnk($ml('Calvin Klein'), $mnf($mCalvinKlein)),
                        $this->lnk($ml('Lacoste'), $mnf($mLacoste)),
                        $this->lnk($ml('HUGO'), $mnf($mHugo)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Designer'),
                'has_dropdown' => true,
                'link' => $custom($this->context->link->getCategoryLink(172)),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(172), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat($idDesignerOdziez)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat($idDesignerObuwie)),
                        $this->lnk($ml('Torby', 'Bags'), $cat($idDesignerTorby)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat($idDesignerAkcesoria)),
                        $this->lnk($ml('Okulary przeciwsłoneczne', 'Sunglasses'), $cat($idDesignerOkulary)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Polo Ralph Lauren'), $mnf($mPoloRalphLauren)),
                        $this->lnk($ml('MOSCHINO'), $mnf(3)),
                        $this->lnk($ml('Versace'), $mnf(4)),
                        $this->lnk($ml('Paul Smith'), $mnf($mPaulSmith)),
                        $this->lnk($ml('Gucci'), $mnf($mGucci)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Kosmetyki', 'Beauty'),
                'has_dropdown' => true,
                'link' => $cat(136),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(136), [
                        $this->lnk($ml('Perfumy', 'Perfume'), $cat(167)),
                        $this->lnk($ml('Ciało', 'Body care'), $cat($idCialo)),
                        $this->lnk($ml('Włosy', 'Hair'), $cat(169)),
                        $this->lnk($ml('Twarz', 'Face care'), $cat($idTwarz)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Armani Beauty'), $mnf($mArmaniBeauty)),
                        $this->lnk($ml('Biotherm'), $mnf($mBiotherm)),
                        $this->lnk($ml('Azzaro Parfums'), $mnf($mAzzaro)),
                        $this->lnk($ml('Rituals'), $mnf($mRituals)),
                        $this->lnk($ml('Guess Fragrances'), $mnf($mGuessFragrances)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Tylko u nas', 'Only here'), $custom()),
                        $this->lnk($ml('Profesjonalna pielęgnacja włosów', 'Professional hair care'), $custom()),
                        $this->lnk($ml('Zestawy', 'Gift sets'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Wyprzedaż %', 'Sale %'),
                'has_dropdown' => true,
                'link' => ['type' => 'custom', 'url' => $pricesDropUrl],
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $custom($pricesDropUrl), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(25)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(106)),
                        $this->lnk($ml('Sport', 'Sport'), $cat(131)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(36)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Napapijri'), $mnf($mNapapijri)),
                        $this->lnk($ml('The North Face'), $mnf($mNorthFace)),
                    ]),
                ],
            ],
        ];

        $this->persistMenuItems($items, $langIds);

        return true;
    }

    public function seedKidsMenu(): bool
    {
        $idTopCategory = $this->findTopCategoryIdByName('Dziecko');

        if (!$idTopCategory || Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megamenu_item` WHERE id_top_category = ' . (int) $idTopCategory
        )) {
            return false;
        }

        $langIds = array_map('intval', array_column(Language::getLanguages(false), 'id_lang'));
        $ml = function (string $pl, ?string $en = null) use ($langIds) {
            $values = [];
            foreach ($langIds as $idLang) {
                $values[$idLang] = $idLang === 2 && $en !== null ? $en : $pl;
            }

            return $values;
        };

        $none = ['type' => MegaMenuColumn::SEE_ALL_NONE, 'id_category' => 0, 'id_manufacturer' => 0, 'url' => ''];
        $custom = fn (string $url = '#') => ['type' => 'custom', 'id_category' => 0, 'id_manufacturer' => 0, 'url' => $url];
        $cat = fn (int $id) => ['type' => 'category', 'id_category' => $id, 'id_manufacturer' => 0, 'url' => ''];
        $mnf = fn (int $id) => ['type' => 'manufacturer', 'id_category' => 0, 'id_manufacturer' => $id, 'url' => ''];

        $newProductsUrl = $this->context->link->getPageLink('new-products');
        $pricesDropUrl = $this->context->link->getPageLink('prices-drop');
        $manufacturerUrl = $this->context->link->getPageLink('manufacturer');

        $idPremium = $this->findOrCreateCategory($ml('Premium'), $idTopCategory);
        $idDesigner = $this->findOrCreateCategory($ml('Moda Designer', 'Designer fashion'), $idPremium);
        $idDesignerOdziez = $this->findOrCreateCategory($ml('Odzież', 'Clothing'), $idDesigner);
        $idDesignerObuwie = $this->findOrCreateCategory($ml('Obuwie', 'Shoes'), $idDesigner);
        $idDesignerAkcesoria = $this->findOrCreateCategory($ml('Akcesoria', 'Accessories'), $idDesigner);
        $idNiemowleta = $this->findOrCreateCategory($ml('Niemowlęta (0-2 lat)', 'Babies (0-2 years)'), $idTopCategory);
        $idDzieci = $this->findOrCreateCategory($ml('Dzieci (2-9 lat)', 'Kids (2-9 years)'), $idTopCategory);
        $idMlodziez = $this->findOrCreateCategory($ml('Młodzież (9-16 lat)', 'Teens (9-16 years)'), $idTopCategory);
        $idSportOdziez = $this->findOrCreateCategory($ml('Odzież sportowa', 'Sportswear'), 132);
        $idSportObuwie = $this->findOrCreateCategory($ml('Obuwie sportowe', 'Sports shoes'), 132);

        $mNewBalance = $this->findOrCreateManufacturer('New Balance');
        $mJordan = $this->findOrCreateManufacturer('Jordan');
        $mCrocs = $this->findOrCreateManufacturer('Crocs');
        $mLiewood = $this->findOrCreateManufacturer('Liewood');
        $mKappa = $this->findOrCreateManufacturer('Kappa');
        $mNorthFace = $this->findOrCreateManufacturer('The North Face');
        $mLindex = $this->findOrCreateManufacturer('Lindex');
        $mMarksSpencer = $this->findOrCreateManufacturer('Marks & Spencer');
        $mNameIt = $this->findOrCreateManufacturer('Name it');
        $mPoloRalphLauren = $this->findOrCreateManufacturer('Polo Ralph Lauren');
        $mJackJones = $this->findOrCreateManufacturer('Jack & Jones');
        $mGap = $this->findOrCreateManufacturer('GAP');
        $mTommyHilfiger = 10;
        $mLacoste = $this->findOrCreateManufacturer('Lacoste');
        $mCalvinKlein = $this->findOrCreateManufacturer('Calvin Klein');
        $mEmporioArmani = $this->findOrCreateManufacturer('Emporio Armani');
        $mDsquared2 = $this->findOrCreateManufacturer('Dsquared2');

        $items = [
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Nowości', 'New in'),
                'has_dropdown' => true,
                'link' => ['type' => 'custom', 'url' => $newProductsUrl],
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $custom($newProductsUrl), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(37)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(118)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(48)),
                        $this->lnk($ml('Sport', 'Sport'), $cat(132)),
                    ]),
                    $this->col($ml('Powrót do szkoły!', 'Back to school!'), $none, [
                        $this->lnk($ml('Plecaki i akcesoria', 'Backpacks and accessories'), $custom()),
                        $this->lnk($ml('Artykuły szkolne', 'School supplies'), $cat(128)),
                        $this->lnk($ml('Bluzy i dresy', 'Hoodies and tracksuits'), $custom()),
                        $this->lnk($ml('Wielopaki', 'Multipacks'), $custom()),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Tylko u nas', 'Only here'), $custom()),
                        $this->lnk($ml('Sportowe nowości', 'New in sports'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Odzież', 'Clothing'),
                'has_dropdown' => true,
                'link' => $cat(37),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(37), [
                        $this->lnk($ml('Bluzy', 'Hoodies'), $cat(79)),
                        $this->lnk($ml('Koszulki i topy', 'T-shirts and tops'), $cat(80)),
                        $this->lnk($ml('Spodnie', 'Trousers'), $cat(81)),
                        $this->lnk($ml('Kurtki', 'Jackets'), $cat(82)),
                        $this->lnk($ml('Swetry', 'Sweaters'), $cat(83)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Next'), $mnf(9)),
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Polo Ralph Lauren'), $mnf($mPoloRalphLauren)),
                        $this->lnk($ml('GAP'), $mnf($mGap)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Nowości', 'New in'), $custom($newProductsUrl)),
                        $this->lnk($ml('Przeceny %', 'Sale %'), $custom($pricesDropUrl)),
                        $this->lnk($ml('Wielopaki', 'Multipacks'), $custom()),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Obuwie', 'Shoes'),
                'has_dropdown' => true,
                'link' => $cat(118),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(118), [
                        $this->lnk($ml('Buty sportowe', 'Sports shoes'), $cat(119)),
                        $this->lnk($ml('Sandały', 'Sandals'), $cat(120)),
                        $this->lnk($ml('Kalosze', 'Wellies'), $cat(121)),
                        $this->lnk($ml('Botki', 'Ankle boots'), $cat(122)),
                        $this->lnk($ml('Kapcie', 'Slippers'), $cat(123)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('New Balance'), $mnf($mNewBalance)),
                        $this->lnk($ml('Jordan'), $mnf($mJordan)),
                        $this->lnk($ml('Crocs'), $mnf($mCrocs)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Modne Sneakersy', 'Trendy sneakers'), $custom()),
                        $this->lnk($ml('Nowości', 'New in'), $custom($newProductsUrl)),
                        $this->lnk($ml('Przeceny %', 'Sale %'), $custom($pricesDropUrl)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Akcesoria', 'Accessories'),
                'has_dropdown' => true,
                'link' => $cat(48),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(48), [
                        $this->lnk($ml('Czapki', 'Hats'), $cat(89)),
                        $this->lnk($ml('Rękawiczki', 'Gloves'), $cat(90)),
                        $this->lnk($ml('Szaliki', 'Scarves'), $cat(91)),
                        $this->lnk($ml('Paski', 'Belts'), $cat(92)),
                        $this->lnk($ml('Plecaki', 'Backpacks'), $cat(93)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Next'), $mnf(9)),
                        $this->lnk($ml('Liewood'), $mnf($mLiewood)),
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                    ]),
                    $this->col($ml('Nie przegap', "Don't miss"), $none, [
                        $this->lnk($ml('Akcesoria Premium', 'Premium accessories'), $custom()),
                        $this->lnk($ml('Akcesoria Sportowe', 'Sport accessories'), $custom()),
                        $this->lnk($ml('Przeceny %', 'Sale %'), $custom($pricesDropUrl)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Sport', 'Sport'),
                'has_dropdown' => true,
                'link' => $cat(132),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat(132), [
                        $this->lnk($ml('Odzież sportowa', 'Sportswear'), $cat($idSportOdziez)),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $cat($idSportObuwie)),
                        $this->lnk($ml('Plecaki i torby', 'Backpacks and bags'), $cat(162)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('The North Face'), $mnf($mNorthFace)),
                        $this->lnk($ml('Kappa'), $mnf($mKappa)),
                        $this->lnk($ml('Jordan'), $mnf($mJordan)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Premium'),
                'has_dropdown' => true,
                'link' => $cat($idPremium),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat($idPremium), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(37)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(118)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(48)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Tommy Hilfiger'), $mnf($mTommyHilfiger)),
                        $this->lnk($ml('Lacoste'), $mnf($mLacoste)),
                        $this->lnk($ml('Polo Ralph Lauren'), $mnf($mPoloRalphLauren)),
                        $this->lnk($ml('Calvin Klein'), $mnf($mCalvinKlein)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Designer'),
                'has_dropdown' => true,
                'link' => $custom($this->context->link->getCategoryLink($idDesigner)),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $cat($idDesigner), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat($idDesignerOdziez)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat($idDesignerObuwie)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat($idDesignerAkcesoria)),
                        $this->lnk($ml('Zabawki', 'Toys'), $cat(125)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Polo Ralph Lauren'), $mnf($mPoloRalphLauren)),
                        $this->lnk($ml('MOSCHINO'), $mnf(3)),
                        $this->lnk($ml('Versace'), $mnf(4)),
                        $this->lnk($ml('Emporio Armani'), $mnf($mEmporioArmani)),
                        $this->lnk($ml('Dsquared2'), $mnf($mDsquared2)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Bielizna i piżamy', 'Underwear and pyjamas'),
                'has_dropdown' => true,
                'link' => $custom($this->context->link->getCategoryLink(44)),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $custom($this->context->link->getCategoryLink(44)), [
                        $this->lnk($ml('Majtki', 'Underwear'), $cat(84)),
                        $this->lnk($ml('Podkoszulki', 'Undershirts'), $cat(85)),
                        $this->lnk($ml('Piżamy', 'Pyjamas'), $cat(87)),
                        $this->lnk($ml('Body', 'Bodysuits'), $cat(88)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Next'), $mnf(9)),
                        $this->lnk($ml('Lindex'), $mnf($mLindex)),
                        $this->lnk($ml('Marks & Spencer'), $mnf($mMarksSpencer)),
                        $this->lnk($ml('Name it'), $mnf($mNameIt)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Dziewczynki', 'Girls'),
                'has_dropdown' => true,
                'link' => $custom($this->context->link->getCategoryLink($idTopCategory)),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $none, [
                        $this->lnk($ml('Odzież sportowa', 'Sportswear'), $cat($idSportOdziez)),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $cat($idSportObuwie)),
                        $this->lnk($ml('Niemowlęta (0-2 lat)', 'Babies (0-2 years)'), $cat($idNiemowleta)),
                        $this->lnk($ml('Dzieci (2-9 lat)', 'Kids (2-9 years)'), $cat($idDzieci)),
                        $this->lnk($ml('Młodzież (9-16 lat)', 'Teens (9-16 years)'), $cat($idMlodziez)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('The North Face'), $mnf($mNorthFace)),
                        $this->lnk($ml('Kappa'), $mnf($mKappa)),
                        $this->lnk($ml('Jordan'), $mnf($mJordan)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Chłopcy', 'Boys'),
                'has_dropdown' => true,
                'link' => $custom($this->context->link->getCategoryLink($idTopCategory)),
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $none, [
                        $this->lnk($ml('Odzież sportowa', 'Sportswear'), $cat($idSportOdziez)),
                        $this->lnk($ml('Obuwie sportowe', 'Sports shoes'), $cat($idSportObuwie)),
                        $this->lnk($ml('Niemowlęta (0-2 lat)', 'Babies (0-2 years)'), $cat($idNiemowleta)),
                        $this->lnk($ml('Dzieci (2-9 lat)', 'Kids (2-9 years)'), $cat($idDzieci)),
                        $this->lnk($ml('Młodzież (9-16 lat)', 'Teens (9-16 years)'), $cat($idMlodziez)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki Premium', 'All Premium brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Tommy Hilfiger'), $mnf($mTommyHilfiger)),
                        $this->lnk($ml('Lacoste'), $mnf($mLacoste)),
                        $this->lnk($ml('Polo Ralph Lauren'), $mnf($mPoloRalphLauren)),
                        $this->lnk($ml('Jack & Jones'), $mnf($mJackJones)),
                    ]),
                ],
            ],
            [
                'id_top_category' => $idTopCategory,
                'label' => $ml('Wyprzedaż %', 'Sale %'),
                'has_dropdown' => true,
                'link' => ['type' => 'custom', 'url' => $pricesDropUrl],
                'columns' => [
                    $this->col($ml('Kategorie', 'Categories'), $custom($pricesDropUrl), [
                        $this->lnk($ml('Odzież', 'Clothing'), $cat(37)),
                        $this->lnk($ml('Obuwie', 'Shoes'), $cat(118)),
                        $this->lnk($ml('Akcesoria', 'Accessories'), $cat(48)),
                        $this->lnk($ml('Sport', 'Sport'), $cat(132)),
                    ]),
                    $this->colWithSeeAllLabel($ml('Marki', 'Brands'), $ml('Wszystkie marki', 'All brands'), $custom($manufacturerUrl), [
                        $this->lnk($ml('Nike'), $mnf(8)),
                        $this->lnk($ml('adidas Originals'), $mnf(11)),
                        $this->lnk($ml('Next'), $mnf(9)),
                    ]),
                ],
            ],
        ];

        $this->persistMenuItems($items, $langIds);

        return true;
    }

    public function fixWomenMenuLinks(): bool
    {
        $idTopCategory = $this->findTopCategoryIdByName('Kobieta');

        if (!$idTopCategory) {
            return false;
        }

        $langIds = array_map('intval', array_column(Language::getLanguages(false), 'id_lang'));
        $ml = function (string $pl, ?string $en = null) use ($langIds) {
            $values = [];
            foreach ($langIds as $idLang) {
                $values[$idLang] = $idLang === 2 && $en !== null ? $en : $pl;
            }

            return $values;
        };

        $idLangDefault = (int) Configuration::get('PS_LANG_DEFAULT');
        $idStreetwear = $this->findOrCreateCategory($ml('Streetwear'), $idTopCategory);

        $idStreetwearItem = (int) Db::getInstance()->getValue('
            SELECT il.id_item FROM `' . _DB_PREFIX_ . 'megamenu_item_lang` il
            INNER JOIN `' . _DB_PREFIX_ . 'megamenu_item` i ON i.id_item = il.id_item
            WHERE i.id_top_category = ' . (int) $idTopCategory . ' AND il.label = "Streetwear" AND il.id_lang = ' . $idLangDefault . '
        ');

        if ($idStreetwearItem) {
            $item = new MegaMenuItem($idStreetwearItem);
            $item->link_type = MegaMenuItem::LINK_TYPE_CATEGORY;
            $item->id_category = $idStreetwear;
            $item->update();

            $idStreetwearColumn = (int) Db::getInstance()->getValue('
                SELECT id_column FROM `' . _DB_PREFIX_ . 'megamenu_column` WHERE id_item = ' . $idStreetwearItem . ' ORDER BY position ASC LIMIT 1
            ');

            if ($idStreetwearColumn) {
                $column = new MegaMenuColumn($idStreetwearColumn);
                $column->see_all_type = MegaMenuColumn::SEE_ALL_CATEGORY;
                $column->id_category = $idStreetwear;
                $column->update();
            }
        }

        $idDesignerItem = (int) Db::getInstance()->getValue('
            SELECT id_item FROM `' . _DB_PREFIX_ . 'megamenu_item`
            WHERE id_top_category = ' . (int) $idTopCategory . ' AND id_category = 171
        ');

        if ($idDesignerItem) {
            $categoryMap = [
                'Odzież' => $this->findOrCreateCategory($ml('Odzież', 'Clothing'), 171),
                'Obuwie' => $this->findOrCreateCategory($ml('Obuwie', 'Shoes'), 171),
                'Torby' => $this->findOrCreateCategory($ml('Torby', 'Bags'), 171),
            ];

            $links = Db::getInstance()->executeS('
                SELECT l.id_link, ll.label
                FROM `' . _DB_PREFIX_ . 'megamenu_link` l
                INNER JOIN `' . _DB_PREFIX_ . 'megamenu_link_lang` ll ON ll.id_link = l.id_link AND ll.id_lang = ' . $idLangDefault . '
                WHERE l.id_column IN (
                    SELECT id_column FROM `' . _DB_PREFIX_ . 'megamenu_column` WHERE id_item = ' . $idDesignerItem . '
                )
            ');

            foreach ($links as $linkRow) {
                if (!isset($categoryMap[$linkRow['label']])) {
                    continue;
                }

                $link = new MegaMenuLink((int) $linkRow['id_link']);
                $link->link_type = MegaMenuLink::LINK_TYPE_CATEGORY;
                $link->id_category = $categoryMap[$linkRow['label']];
                $link->update();
            }
        }

        return true;
    }

    protected function col(array $title, array $seeAllTarget, array $links): array
    {
        return [
            'title' => $title,
            'see_all' => $seeAllTarget,
            'see_all_label' => $this->fillLangFromArray($title, 'Zobacz pełny katalog', 'View full catalog'),
            'links' => $links,
        ];
    }

    protected function colWithSeeAllLabel(array $title, array $seeAllLabel, array $seeAllTarget, array $links): array
    {
        return [
            'title' => $title,
            'see_all' => $seeAllTarget,
            'see_all_label' => $seeAllLabel,
            'links' => $links,
        ];
    }

    protected function lnk(array $label, array $target): array
    {
        return [
            'label' => $label,
            'target' => $target,
        ];
    }

    protected function fillLang(array $langIds, string $value): array
    {
        $values = [];
        foreach ($langIds as $idLang) {
            $values[$idLang] = $value;
        }

        return $values;
    }

    protected function fillLangFromArray(array $reference, string $pl, string $en): array
    {
        $values = [];
        foreach (array_keys($reference) as $idLang) {
            $values[$idLang] = $idLang === 2 ? $en : $pl;
        }

        return $values;
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitMegaMenu')) {
            $this->postProcess();
        }

        $output .= $this->processMenuActions();
        $output .= $this->renderForm();
        $output .= $this->renderMenuBuilder();

        return $output;
    }

    protected function postProcess()
    {
        $selectedCategories = [];

        foreach ($this->getTopCategories() as $category) {
            if (Tools::getValue(self::CATEGORIES . '_' . $category['id_category'])) {
                $selectedCategories[] = (int) $category['id_category'];
            }
        }

        Configuration::updateValue(self::CATEGORIES, json_encode($selectedCategories));
        Configuration::updateValue(self::ENABLED, (int) Tools::getValue(self::ENABLED));
    }

    protected function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Header switcher'),
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Active'),
                        'name' => self::ENABLED,
                        'values' => [
                            ['id' => 'enabled_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'enabled_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Top-level categories'),
                        'name' => self::CATEGORIES,
                        'values' => [
                            'query' => $this->getTopCategories(),
                            'id' => 'id_category',
                            'name' => 'name',
                        ],
                        'hint' => $this->l('Select the top-level categories (direct children of the shop\'s home category) that should appear in the header switcher.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'name' => 'submitMegaMenu',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitMegaMenu';
        $helper->fields_value = $this->getConfigFieldsValues();

        return $helper->generateForm([$fields_form]);
    }

    protected function getConfigFieldsValues()
    {
        $selectedCategories = $this->getSelectedCategoryIds();

        $values = [
            self::ENABLED => Configuration::get(self::ENABLED),
        ];

        foreach ($this->getTopCategories() as $category) {
            $values[self::CATEGORIES . '_' . $category['id_category']] = in_array((int) $category['id_category'], $selectedCategories, true);
        }

        return $values;
    }

    public function hookDisplayMegaMenuSwitcher()
    {
        if (!Configuration::get(self::ENABLED)) {
            return '';
        }

        $categories = $this->getSwitcherCategories();

        if (empty($categories)) {
            return '';
        }

        $this->context->smarty->assign([
            'megaMenuCategories' => $categories,
            'megaMenuSelectUrl' => $this->context->link->getModuleLink($this->name, 'select'),
        ]);

        return $this->fetch('module:megamenu/views/templates/hook/switcher.tpl');
    }

    public function hookDisplayMegaMenuNav()
    {
        if (!Configuration::get(self::ENABLED)) {
            return '';
        }

        $menu = $this->buildMenu();

        if (empty($menu['children'])) {
            return '';
        }

        $this->context->smarty->assign([
            'menu' => $menu,
            'megaMenuCategories' => $this->getSwitcherCategories(),
            'megaMenuSelectUrl' => $this->context->link->getModuleLink($this->name, 'select'),
        ]);

        return $this->fetch('module:megamenu/views/templates/hook/nav.tpl');
    }

    public function renderMobileMenuFragment(): string
    {
        $this->context->smarty->assign(['menu' => $this->buildMenu()]);

        return $this->fetch('module:megamenu/views/templates/hook/_mobile_menu.tpl');
    }

    protected function buildMenu()
    {
        $idLang = (int) $this->context->language->id;
        $root = ['children' => []];

        $currentController = Dispatcher::getInstance()->getController();
        $currentIdCategory = (int) Tools::getValue('id_category');
        $selectedGenderId = $this->getSelectedTopCategoryId();

        foreach ($this->getMenuItemsForGender($idLang, $selectedGenderId) as $itemRow) {
            $idItem = (int) $itemRow['id_item'];

            $itemCategoryId = $itemRow['link_type'] === MegaMenuItem::LINK_TYPE_CATEGORY
                ? $this->resolveGenderAwareCategoryId((int) $itemRow['id_category'], $selectedGenderId, $idLang)
                : (int) $itemRow['id_category'];

            $columns = [];

            if ($itemRow['has_dropdown']) {
                foreach (MegaMenuColumn::getForItem($idItem, $idLang) as $columnRow) {
                    $idColumn = (int) $columnRow['id_column'];

                    $seeAll = null;
                    if ($columnRow['see_all_type'] !== MegaMenuColumn::SEE_ALL_NONE && $columnRow['see_all_label'] !== '') {
                        $seeAll = [
                            'label' => $columnRow['see_all_label'],
                            'url' => $this->resolveUrl($columnRow['see_all_type'], $columnRow['id_category'], $columnRow['id_manufacturer'], $columnRow['see_all_custom_url'], $idLang),
                        ];
                    }

                    $links = [];
                    foreach (MegaMenuLink::getForColumn($idColumn, $idLang) as $linkRow) {
                        $links[] = [
                            'label' => $this->resolveLabel($linkRow['link_type'], $linkRow['id_category'], $linkRow['id_manufacturer'], $linkRow['label'], $idLang),
                            'url' => $this->resolveUrl($linkRow['link_type'], $linkRow['id_category'], $linkRow['id_manufacturer'], $linkRow['custom_url'], $idLang),
                            'open_in_new_window' => false,
                        ];
                    }

                    $columns[] = [
                        'title' => $columnRow['title'],
                        'see_all' => $seeAll,
                        'links' => $links,
                        'slot' => (int) $columnRow['position'] + 1,
                    ];
                }
            }

            $root['children'][] = [
                'type' => 'megamenu-item',
                'page_identifier' => 'megamenu-item-' . $idItem,
                'label' => $this->resolveLabel($itemRow['link_type'], $itemCategoryId, null, $itemRow['label'], $idLang),
                'url' => $this->resolveUrl($itemRow['link_type'], $itemCategoryId, null, $itemRow['custom_url'], $idLang),
                'current' => $currentController === 'category' && $itemRow['link_type'] === MegaMenuItem::LINK_TYPE_CATEGORY
                    && $currentIdCategory === $itemCategoryId,
                'open_in_new_window' => false,
                'has_dropdown' => (bool) $itemRow['has_dropdown'] && !empty($columns),
                'columns' => $columns,
            ];
        }

        return $root;
    }

    protected function getMenuItemsForGender(int $idLang, int $selectedGenderId): array
    {
        if ($selectedGenderId) {
            $genderItems = MegaMenuItem::getAllForLang($idLang, true, $selectedGenderId);

            if (!empty($genderItems)) {
                return $genderItems;
            }
        }

        return MegaMenuItem::getAllForLang($idLang, true, 0);
    }

    protected function resolveGenderAwareCategoryId(int $idCategory, int $selectedGenderId, int $idLang): int
    {
        if (!$idCategory || !$selectedGenderId) {
            return $idCategory;
        }

        $category = new Category($idCategory, $idLang);
        if (!Validate::isLoadedObject($category) || (int) $category->id_parent === $selectedGenderId) {
            return $idCategory;
        }

        $siblingId = (int) Db::getInstance()->getValue('
            SELECT c.id_category
            FROM `' . _DB_PREFIX_ . 'category` c
            INNER JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON cl.id_category = c.id_category AND cl.id_lang = ' . (int) $idLang . '
            WHERE c.id_parent = ' . (int) $selectedGenderId . ' AND cl.name = "' . pSQL($category->name) . '"
        ');

        return $siblingId ?: $idCategory;
    }

    protected function resolveLabel(string $type, $idCategory, $idManufacturer, string $customLabel, int $idLang): string
    {
        if ($type === MegaMenuColumn::SEE_ALL_CATEGORY && $idCategory) {
            $category = new Category((int) $idCategory, $idLang);
            if (Validate::isLoadedObject($category)) {
                return $category->name;
            }
        }

        if ($type === MegaMenuColumn::SEE_ALL_MANUFACTURER && $idManufacturer) {
            $manufacturer = new Manufacturer((int) $idManufacturer, $idLang);
            if (Validate::isLoadedObject($manufacturer)) {
                return $manufacturer->name;
            }
        }

        return $customLabel;
    }

    protected function resolveUrl(string $type, $idCategory, $idManufacturer, string $customUrl, int $idLang): string
    {
        if ($type === MegaMenuColumn::SEE_ALL_CATEGORY && $idCategory) {
            $category = new Category((int) $idCategory, $idLang);
            if (Validate::isLoadedObject($category)) {
                return $category->getLink();
            }
        }

        if ($type === MegaMenuColumn::SEE_ALL_MANUFACTURER && $idManufacturer) {
            $manufacturer = new Manufacturer((int) $idManufacturer, $idLang);
            if (Validate::isLoadedObject($manufacturer)) {
                return $this->context->link->getManufacturerLink($manufacturer, null, $idLang);
            }
        }

        return $customUrl !== '' ? $customUrl : '#';
    }

    public function hookActionFrontControllerSetMedia()
    {
        if (!Configuration::get(self::ENABLED)) {
            return;
        }

        $this->context->controller->registerJavascript(
            'module-megamenu',
            'modules/' . $this->name . '/views/js/megamenu.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }

    public function selectCategory($idCategory)
    {
        $idCategory = (int) $idCategory;

        if (!$idCategory) {
            unset($this->context->cookie->{self::COOKIE_KEY});
            $this->context->cookie->write();

            return true;
        }

        if (!in_array($idCategory, $this->getSelectedCategoryIds(), true)) {
            return false;
        }

        $this->context->cookie->{self::COOKIE_KEY} = $idCategory;
        $this->context->cookie->write();

        return true;
    }

    protected function getSwitcherCategories()
    {
        $selectedIds = $this->getSelectedCategoryIds();

        if (empty($selectedIds)) {
            return [];
        }

        $activeId = $this->getSelectedTopCategoryId();
        $categories = [];

        foreach ($this->getTopCategories() as $category) {
            $idCategory = (int) $category['id_category'];

            if (!in_array($idCategory, $selectedIds, true)) {
                continue;
            }

            $categories[] = [
                'id_category' => $idCategory,
                'name' => $category['name'],
                'active' => $idCategory === $activeId,
            ];
        }

        return $categories;
    }

    protected function getSelectedTopCategoryId()
    {
        $idCategory = (int) $this->context->cookie->{self::COOKIE_KEY};

        if (!$idCategory) {
            return 0;
        }

        return in_array($idCategory, $this->getSelectedCategoryIds(), true) ? $idCategory : 0;
    }

    protected function getSelectedCategoryIds()
    {
        $raw = Configuration::get(self::CATEGORIES);

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    protected function getTopCategories()
    {
        $idLang = (int) $this->context->language->id;
        $homeCategory = new Category($this->context->shop->getCategory(), $idLang);

        return $homeCategory->getSubCategories($idLang, true);
    }

    protected function menuUrl(array $params = []): string
    {
        $base = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
        ]);

        return $base . ($params ? '&' . http_build_query($params) : '');
    }

    protected function processMenuActions(): string
    {
        if (Tools::isSubmit('submitMegaMenuItem')) {
            return $this->saveItem();
        }
        if (Tools::isSubmit('deleteMegaMenuItem')) {
            return $this->deleteEntity(new MegaMenuItem((int) Tools::getValue('id_item')));
        }
        if (Tools::isSubmit('moveMegaMenuItem')) {
            return $this->moveEntity(new MegaMenuItem((int) Tools::getValue('id_item')), Tools::getValue('way'));
        }
        if (Tools::isSubmit('submitMegaMenuColumn')) {
            return $this->saveColumn();
        }
        if (Tools::isSubmit('deleteMegaMenuColumn')) {
            return $this->deleteEntity(new MegaMenuColumn((int) Tools::getValue('id_column')));
        }
        if (Tools::isSubmit('submitMegaMenuLink')) {
            return $this->saveLink();
        }
        if (Tools::isSubmit('deleteMegaMenuLink')) {
            return $this->deleteEntity(new MegaMenuLink((int) Tools::getValue('id_link')));
        }
        if (Tools::isSubmit('moveMegaMenuLink')) {
            return $this->moveEntity(new MegaMenuLink((int) Tools::getValue('id_link')), Tools::getValue('way'));
        }
        if (Tools::isSubmit('submitMegaMenuCopyScope')) {
            return $this->copyScope((int) Tools::getValue('mm_copy_from'), (int) Tools::getValue('mm_copy_to'));
        }

        return '';
    }

    protected function deleteEntity(ObjectModel $entity): string
    {
        if (!Validate::isLoadedObject($entity)) {
            return $this->displayError($this->l('Item not found.'));
        }

        return $entity->delete()
            ? $this->displayConfirmation($this->l('Deleted.'))
            : $this->displayError($this->l('Could not delete.'));
    }

    protected function moveEntity(ObjectModel $entity, $way): string
    {
        if (!Validate::isLoadedObject($entity)) {
            return $this->displayError($this->l('Item not found.'));
        }

        $moved = $way === 'up' ? $entity->moveUp() : $entity->moveDown();

        return $moved ? '' : '';
    }

    protected function saveItem(): string
    {
        $idItem = (int) Tools::getValue('id_item');
        $item = $idItem ? new MegaMenuItem($idItem) : new MegaMenuItem();

        $item->link_type = Tools::getValue('link_type') === MegaMenuItem::LINK_TYPE_CATEGORY
            ? MegaMenuItem::LINK_TYPE_CATEGORY
            : MegaMenuItem::LINK_TYPE_CUSTOM;
        $item->id_category = (int) Tools::getValue('id_category');
        $item->id_top_category = (int) Tools::getValue('id_top_category');
        $item->has_dropdown = (bool) Tools::getValue('has_dropdown');
        $item->active = (bool) Tools::getValue('active');

        foreach (Language::getLanguages(false) as $lang) {
            $item->label[$lang['id_lang']] = Tools::getValue('label_' . $lang['id_lang']);
            $item->custom_url[$lang['id_lang']] = Tools::getValue('custom_url_' . $lang['id_lang']);
        }

        if (!$idItem) {
            $item->position = MegaMenuItem::getNextPosition($item->id_top_category);
        }

        $success = $idItem ? $item->update() : $item->add();

        return $success
            ? $this->displayConfirmation($this->l('Menu item saved.'))
            : $this->displayError($this->l('Could not save the menu item.'));
    }

    protected function saveColumn(): string
    {
        $idColumn = (int) Tools::getValue('id_column');
        $idItem = (int) Tools::getValue('id_item');
        $slot = (int) Tools::getValue('slot');

        if ($slot < 1 || $slot > MegaMenuColumn::MAX_SLOTS) {
            return $this->displayError($this->l('Please choose a valid slot.'));
        }

        if (MegaMenuColumn::isSlotTaken($idItem, $slot, $idColumn)) {
            return $this->displayError($this->l('This slot is already used by another column.'));
        }

        $column = $idColumn ? new MegaMenuColumn($idColumn) : new MegaMenuColumn();

        $column->id_item = $idItem;
        $column->position = $slot - 1;
        $column->see_all_type = Tools::getValue('see_all_type', MegaMenuColumn::SEE_ALL_NONE);
        $column->id_category = (int) Tools::getValue('see_all_id_category');
        $column->id_manufacturer = (int) Tools::getValue('see_all_id_manufacturer');

        foreach (Language::getLanguages(false) as $lang) {
            $column->title[$lang['id_lang']] = Tools::getValue('title_' . $lang['id_lang']);
            $column->see_all_label[$lang['id_lang']] = Tools::getValue('see_all_label_' . $lang['id_lang']);
            $column->see_all_custom_url[$lang['id_lang']] = Tools::getValue('see_all_custom_url_' . $lang['id_lang']);
        }

        $success = $idColumn ? $column->update() : $column->add();

        return $success
            ? $this->displayConfirmation($this->l('Column saved.'))
            : $this->displayError($this->l('Could not save the column.'));
    }

    protected function saveLink(): string
    {
        $idLink = (int) Tools::getValue('id_link');
        $idColumn = (int) Tools::getValue('id_column');
        $link = $idLink ? new MegaMenuLink($idLink) : new MegaMenuLink();

        $link->id_column = $idColumn;
        $link->link_type = Tools::getValue('link_type', MegaMenuLink::LINK_TYPE_CUSTOM);
        $link->id_category = (int) Tools::getValue('id_category');
        $link->id_manufacturer = (int) Tools::getValue('id_manufacturer');

        foreach (Language::getLanguages(false) as $lang) {
            $link->label[$lang['id_lang']] = Tools::getValue('label_' . $lang['id_lang']);
            $link->custom_url[$lang['id_lang']] = Tools::getValue('custom_url_' . $lang['id_lang']);
        }

        if (!$idLink) {
            $link->position = MegaMenuLink::getNextPosition($idColumn);
        }

        $success = $idLink ? $link->update() : $link->add();

        return $success
            ? $this->displayConfirmation($this->l('Link saved.'))
            : $this->displayError($this->l('Could not save the link.'));
    }

    protected function copyScope(int $fromScope, int $toScope): string
    {
        if ($fromScope === $toScope) {
            return $this->displayError($this->l('Source and target category must be different.'));
        }

        if (Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megamenu_item` WHERE id_top_category = ' . (int) $toScope)) {
            return $this->displayError($this->l('The target category already has menu items. Delete them first if you want to copy over them.'));
        }

        $idLang = (int) $this->context->language->id;

        foreach (MegaMenuItem::getAllForLang($idLang, false, $fromScope) as $itemRow) {
            $sourceItem = new MegaMenuItem((int) $itemRow['id_item']);
            $newItem = new MegaMenuItem();
            $newItem->link_type = $sourceItem->link_type;
            $newItem->id_category = $sourceItem->id_category;
            $newItem->id_top_category = $toScope;
            $newItem->has_dropdown = $sourceItem->has_dropdown;
            $newItem->active = $sourceItem->active;
            $newItem->position = $sourceItem->position;
            $newItem->label = $sourceItem->label;
            $newItem->custom_url = $sourceItem->custom_url;
            $newItem->add();

            foreach (MegaMenuColumn::getForItem((int) $sourceItem->id, $idLang) as $columnRow) {
                $sourceColumn = new MegaMenuColumn((int) $columnRow['id_column']);
                $newColumn = new MegaMenuColumn();
                $newColumn->id_item = (int) $newItem->id;
                $newColumn->position = $sourceColumn->position;
                $newColumn->see_all_type = $sourceColumn->see_all_type;
                $newColumn->id_category = $sourceColumn->id_category;
                $newColumn->id_manufacturer = $sourceColumn->id_manufacturer;
                $newColumn->title = $sourceColumn->title;
                $newColumn->see_all_label = $sourceColumn->see_all_label;
                $newColumn->see_all_custom_url = $sourceColumn->see_all_custom_url;
                $newColumn->add();

                foreach (MegaMenuLink::getForColumn((int) $sourceColumn->id, $idLang) as $linkRow) {
                    $sourceLink = new MegaMenuLink((int) $linkRow['id_link']);
                    $newLink = new MegaMenuLink();
                    $newLink->id_column = (int) $newColumn->id;
                    $newLink->position = $sourceLink->position;
                    $newLink->link_type = $sourceLink->link_type;
                    $newLink->id_category = $sourceLink->id_category;
                    $newLink->id_manufacturer = $sourceLink->id_manufacturer;
                    $newLink->label = $sourceLink->label;
                    $newLink->custom_url = $sourceLink->custom_url;
                    $newLink->add();
                }
            }
        }

        return $this->displayConfirmation($this->l('Menu copied. You can now fine-tune it for the new category.'));
    }

    protected function getCategoryOptions(): array
    {
        $idLang = (int) $this->context->language->id;
        $idHome = (int) $this->context->shop->getCategory();
        $tree = Category::getNestedCategories($idHome, $idLang, true);

        $rows = [['id' => 0, 'name' => '-- ' . $this->l('None') . ' --']];

        if (isset($tree[$idHome]['children'])) {
            $this->flattenCategoryOptions($tree[$idHome]['children'], 0, $rows);
        }

        return $rows;
    }

    protected function flattenCategoryOptions(array $nodes, int $depth, array &$rows): void
    {
        foreach ($nodes as $node) {
            $rows[] = [
                'id' => (int) $node['id_category'],
                'name' => str_repeat('— ', $depth) . $node['name'],
            ];

            if (!empty($node['children'])) {
                $this->flattenCategoryOptions($node['children'], $depth + 1, $rows);
            }
        }
    }

    protected function getTopCategoryScopeOptions(): array
    {
        $rows = [['id' => 0, 'name' => $this->l('Neutral (no switcher category selected)')]];

        foreach ($this->getTopCategories() as $category) {
            $rows[] = ['id' => (int) $category['id_category'], 'name' => $category['name']];
        }

        return $rows;
    }

    protected function getManufacturerOptions(): array
    {
        $rows = [['id' => 0, 'name' => '-- ' . $this->l('None') . ' --']];

        foreach (Manufacturer::getManufacturers(false, (int) $this->context->language->id) as $manufacturer) {
            $rows[] = ['id' => (int) $manufacturer['id_manufacturer'], 'name' => $manufacturer['name']];
        }

        return $rows;
    }

    protected function renderMenuBuilder(): string
    {
        $view = Tools::getValue('mm_view', 'items');
        $idItem = (int) Tools::getValue('id_item');
        $idColumn = (int) Tools::getValue('id_column');

        if ($view === 'item_form') {
            return $this->renderItemForm($idItem);
        }
        if ($view === 'columns' && $idItem) {
            return $this->renderColumnsList($idItem);
        }
        if ($view === 'column_form' && $idItem) {
            return $this->renderColumnForm($idItem, $idColumn);
        }
        if ($view === 'links' && $idColumn) {
            return $this->renderLinksList($idColumn);
        }
        if ($view === 'link_form' && $idColumn) {
            return $this->renderLinkForm($idColumn, (int) Tools::getValue('id_link'));
        }

        return $this->renderItemsList();
    }

    protected function panelTitle(string $title): string
    {
        return '<div class="panel-heading"><h3>' . $title . '</h3></div>';
    }

    protected function getItemCountsByScope(): array
    {
        $rows = Db::getInstance()->executeS('
            SELECT id_top_category, COUNT(*) AS cnt
            FROM `' . _DB_PREFIX_ . 'megamenu_item`
            GROUP BY id_top_category
        ');

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id_top_category']] = (int) $row['cnt'];
        }

        return $counts;
    }

    protected function renderScopeTabs(array $scopeOptions, int $activeScope): string
    {
        $counts = $this->getItemCountsByScope();

        $html = '<ul class="nav nav-tabs" style="margin-bottom:20px;">';

        foreach ($scopeOptions as $option) {
            $idScope = (int) $option['id'];
            $count = $counts[$idScope] ?? 0;

            $html .= '<li class="' . ($idScope === $activeScope ? 'active' : '') . '">'
                . '<a href="' . $this->menuUrl(['mm_scope' => $idScope]) . '">'
                . Tools::safeOutput($option['name'])
                . ' <span class="badge">' . $count . '</span>'
                . '</a></li>';
        }

        $html .= '</ul>';

        return $html;
    }

    protected function renderCopyScopeForm(array $scopeOptions, int $activeScope, bool $hasItems): string
    {
        if (!$hasItems) {
            return '';
        }

        $targets = array_filter($scopeOptions, fn ($option) => (int) $option['id'] !== $activeScope);

        if (!$targets) {
            return '';
        }

        $html = '<form method="post" action="' . $this->menuUrl() . '" style="margin-top:20px;display:flex;align-items:center;gap:10px;">';
        $html .= '<label class="control-label" style="margin:0;">' . $this->l('Copy the menu items shown here to another switcher category') . '</label>';
        $html .= '<select name="mm_copy_to" class="form-control" style="width:auto;">';

        foreach ($targets as $option) {
            $html .= '<option value="' . (int) $option['id'] . '">' . Tools::safeOutput($option['name']) . '</option>';
        }

        $html .= '</select>';
        $html .= '<input type="hidden" name="mm_copy_from" value="' . $activeScope . '">';
        $html .= '<button type="submit" name="submitMegaMenuCopyScope" class="btn btn-default">' . $this->l('Copy') . '</button>';
        $html .= '</form>';

        return $html;
    }

    protected function renderItemsList(): string
    {
        $idLang = (int) $this->context->language->id;
        $scope = (int) Tools::getValue('mm_scope', 0);
        $scopeOptions = $this->getTopCategoryScopeOptions();
        $items = MegaMenuItem::getAllForLang($idLang, false, $scope);

        $html = '<div class="panel">' . $this->panelTitle($this->l('Main menu items'));
        $html .= '<div class="panel-body">';
        $html .= $this->renderScopeTabs($scopeOptions, $scope);
        $html .= '<table class="table"><thead><tr>'
            . '<th style="width:80px">' . $this->l('Position') . '</th>'
            . '<th>' . $this->l('Label') . '</th>'
            . '<th>' . $this->l('Type') . '</th>'
            . '<th>' . $this->l('Dropdown') . '</th>'
            . '<th>' . $this->l('Active') . '</th>'
            . '<th style="width:260px">' . $this->l('Actions') . '</th>'
            . '</tr></thead><tbody>';

        if (!$items) {
            $html .= '<tr><td colspan="6" class="text-center text-muted">' . $this->l('No menu items in this scope yet.') . '</td></tr>';
        }

        foreach ($items as $item) {
            $idItem = (int) $item['id_item'];
            $label = $item['link_type'] === MegaMenuItem::LINK_TYPE_CATEGORY
                ? $this->resolveLabel(MegaMenuColumn::SEE_ALL_CATEGORY, $item['id_category'], null, $item['label'], $idLang)
                : $item['label'];

            $html .= '<tr>';
            $html .= '<td>' . (int) $item['position']
                . ' <a href="' . $this->menuUrl(['moveMegaMenuItem' => 1, 'id_item' => $idItem, 'way' => 'up', 'mm_scope' => $scope]) . '" title="' . $this->l('Move up') . '"><i class="material-icons">arrow_upward</i></a>'
                . ' <a href="' . $this->menuUrl(['moveMegaMenuItem' => 1, 'id_item' => $idItem, 'way' => 'down', 'mm_scope' => $scope]) . '" title="' . $this->l('Move down') . '"><i class="material-icons">arrow_downward</i></a>'
                . '</td>';
            $html .= '<td>' . Tools::safeOutput($label) . '</td>';
            $html .= '<td>' . ($item['link_type'] === MegaMenuItem::LINK_TYPE_CATEGORY ? $this->l('Category') : $this->l('Custom link')) . '</td>';
            $html .= '<td>' . ((int) $item['has_dropdown'] ? $this->l('Yes') : $this->l('No')) . '</td>';
            $html .= '<td>' . ((int) $item['active'] ? $this->l('Yes') : $this->l('No')) . '</td>';
            $html .= '<td>'
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'columns', 'id_item' => $idItem, 'mm_scope' => $scope]) . '">' . $this->l('Columns') . '</a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'item_form', 'id_item' => $idItem, 'mm_scope' => $scope]) . '"><i class="material-icons">edit</i></a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['deleteMegaMenuItem' => 1, 'id_item' => $idItem, 'mm_scope' => $scope]) . '" onclick="return confirm(\'' . $this->l('Delete this item and everything inside it?') . '\')"><i class="material-icons">delete</i></a>'
                . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<a class="btn btn-primary" href="' . $this->menuUrl(['mm_view' => 'item_form', 'mm_scope' => $scope]) . '">' . $this->l('Add new menu item') . '</a>';
        $html .= $this->renderCopyScopeForm($scopeOptions, $scope, (bool) $items);
        $html .= '</div></div>';

        return $html;
    }

    protected function renderItemForm(int $idItem): string
    {
        $item = $idItem ? new MegaMenuItem($idItem) : null;
        $scope = $item ? (int) $item->id_top_category : (int) Tools::getValue('mm_scope', 0);

        $fieldsValue = [
            'id_item' => $idItem,
            'link_type' => $item ? $item->link_type : MegaMenuItem::LINK_TYPE_CUSTOM,
            'id_category' => $item ? (int) $item->id_category : 0,
            'id_top_category' => $scope,
            'has_dropdown' => $item ? (bool) $item->has_dropdown : true,
            'active' => $item ? (bool) $item->active : true,
            'label' => $item ? $item->label : [],
            'custom_url' => $item ? $item->custom_url : [],
        ];

        $fieldsForm = [
            'form' => [
                'legend' => ['title' => $idItem ? $this->l('Edit menu item') : $this->l('Add menu item')],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Link type'),
                        'name' => 'link_type',
                        'options' => [
                            'query' => [
                                ['id' => MegaMenuItem::LINK_TYPE_CATEGORY, 'name' => $this->l('Category')],
                                ['id' => MegaMenuItem::LINK_TYPE_CUSTOM, 'name' => $this->l('Custom link')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Category'),
                        'name' => 'id_category',
                        'hint' => $this->l('Used only when link type = Category.'),
                        'options' => ['query' => $this->getCategoryOptions(), 'id' => 'id', 'name' => 'name'],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Header switcher scope'),
                        'name' => 'id_top_category',
                        'hint' => $this->l('Show this menu item only when this category is selected in the header switcher. Choose "Neutral" for the menu shown before any switcher category is picked (or after it is deselected).'),
                        'options' => ['query' => $this->getTopCategoryScopeOptions(), 'id' => 'id', 'name' => 'name'],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Label'),
                        'name' => 'label',
                        'lang' => true,
                        'hint' => $this->l('Used only when link type = Custom link (category name is used otherwise).'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('URL'),
                        'name' => 'custom_url',
                        'lang' => true,
                        'hint' => $this->l('Used only when link type = Custom link.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Has dropdown'),
                        'name' => 'has_dropdown',
                        'values' => [
                            ['id' => 'has_dropdown_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'has_dropdown_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Active'),
                        'name' => 'active',
                        'values' => [
                            ['id' => 'item_active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'item_active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    ['type' => 'hidden', 'name' => 'id_item'],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->menuUrl(['mm_scope' => $scope]);
        $helper->submit_action = 'submitMegaMenuItem';
        $helper->fields_value = $fieldsValue;
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $backUrl = $this->menuUrl(['mm_scope' => $scope]);

        return '<a class="btn btn-default" href="' . $backUrl . '">&laquo; ' . $this->l('Back to menu items') . '</a>'
            . $helper->generateForm([$fieldsForm]);
    }

    protected function renderColumnsList(int $idItem): string
    {
        $idLang = (int) $this->context->language->id;
        $item = new MegaMenuItem($idItem, $idLang);
        $columns = MegaMenuColumn::getForItem($idItem, $idLang);
        $scope = (int) $item->id_top_category;

        $html = '<a class="btn btn-default" href="' . $this->menuUrl(['mm_scope' => $scope]) . '">&laquo; ' . $this->l('Back to menu items') . '</a>';
        $html .= '<div class="panel">' . $this->panelTitle(sprintf($this->l('Columns for "%s"'), Tools::safeOutput($item->label)));
        $html .= '<div class="panel-body">';
        $html .= '<table class="table"><thead><tr>'
            . '<th style="width:80px">' . $this->l('Slot') . '</th>'
            . '<th>' . $this->l('Title') . '</th>'
            . '<th style="width:280px">' . $this->l('Actions') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($columns as $column) {
            $idColumn = (int) $column['id_column'];
            $html .= '<tr>';
            $html .= '<td>' . ((int) $column['position'] + 1) . '</td>';
            $html .= '<td>' . Tools::safeOutput($column['title']) . '</td>';
            $html .= '<td>'
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'links', 'id_column' => $idColumn, 'id_item' => $idItem]) . '">' . $this->l('Links') . '</a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'column_form', 'id_item' => $idItem, 'id_column' => $idColumn]) . '"><i class="material-icons">edit</i></a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['deleteMegaMenuColumn' => 1, 'id_column' => $idColumn, 'id_item' => $idItem]) . '" onclick="return confirm(\'' . $this->l('Delete this column and its links?') . '\')"><i class="material-icons">delete</i></a>'
                . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        if (MegaMenuColumn::getNextFreeSlot($idItem) !== null) {
            $html .= '<a class="btn btn-primary" href="' . $this->menuUrl(['mm_view' => 'column_form', 'id_item' => $idItem]) . '">' . $this->l('Add new column') . '</a>';
        } else {
            $html .= '<p>' . $this->l('All 5 slots are used for this menu item.') . '</p>';
        }

        $html .= '</div></div>';

        return $html;
    }

    protected function renderColumnForm(int $idItem, int $idColumn): string
    {
        $column = $idColumn ? new MegaMenuColumn($idColumn) : null;
        $backUrl = $this->menuUrl(['mm_view' => 'columns', 'id_item' => $idItem]);

        $usedSlots = MegaMenuColumn::getUsedSlots($idItem, $idColumn);
        $slotOptions = [];
        for ($slot = 1; $slot <= MegaMenuColumn::MAX_SLOTS; ++$slot) {
            if (!in_array($slot - 1, $usedSlots, true)) {
                $slotOptions[] = ['id' => $slot, 'name' => (string) $slot];
            }
        }

        if (!$column && empty($slotOptions)) {
            return '<a class="btn btn-default" href="' . $backUrl . '">&laquo; ' . $this->l('Back to columns') . '</a>'
                . $this->displayError($this->l('All 5 slots are already used for this menu item. Delete a column first if you want to add a new one.'));
        }

        $fieldsValue = [
            'id_item' => $idItem,
            'id_column' => $idColumn,
            'slot' => $column ? ((int) $column->position + 1) : $slotOptions[0]['id'],
            'see_all_type' => $column ? $column->see_all_type : MegaMenuColumn::SEE_ALL_NONE,
            'see_all_id_category' => $column ? (int) $column->id_category : 0,
            'see_all_id_manufacturer' => $column ? (int) $column->id_manufacturer : 0,
            'title' => $column ? $column->title : [],
            'see_all_label' => $column ? $column->see_all_label : [],
            'see_all_custom_url' => $column ? $column->see_all_custom_url : [],
        ];

        $fieldsForm = [
            'form' => [
                'legend' => ['title' => $idColumn ? $this->l('Edit column') : $this->l('Add column')],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Slot'),
                        'name' => 'slot',
                        'hint' => $this->l('Each slot always renders in the same physical position in the dropdown, no matter how many other columns are configured for this item.'),
                        'options' => [
                            'query' => $slotOptions,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    ['type' => 'text', 'label' => $this->l('Title'), 'name' => 'title', 'lang' => true],
                    [
                        'type' => 'select',
                        'label' => $this->l('"See all" link type'),
                        'name' => 'see_all_type',
                        'options' => [
                            'query' => [
                                ['id' => MegaMenuColumn::SEE_ALL_NONE, 'name' => $this->l('None')],
                                ['id' => MegaMenuColumn::SEE_ALL_CATEGORY, 'name' => $this->l('Category')],
                                ['id' => MegaMenuColumn::SEE_ALL_MANUFACTURER, 'name' => $this->l('Manufacturer (brand)')],
                                ['id' => MegaMenuColumn::SEE_ALL_CUSTOM, 'name' => $this->l('Custom link')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    ['type' => 'select', 'label' => $this->l('Category'), 'name' => 'see_all_id_category', 'hint' => $this->l('Used only if type = Category.'), 'options' => ['query' => $this->getCategoryOptions(), 'id' => 'id', 'name' => 'name']],
                    ['type' => 'select', 'label' => $this->l('Manufacturer'), 'name' => 'see_all_id_manufacturer', 'hint' => $this->l('Used only if type = Manufacturer.'), 'options' => ['query' => $this->getManufacturerOptions(), 'id' => 'id', 'name' => 'name']],
                    ['type' => 'text', 'label' => $this->l('"See all" label'), 'name' => 'see_all_label', 'lang' => true, 'hint' => $this->l('Always shown as typed, e.g. "See full catalog" (only the target link uses the type above). Leave empty to hide the "see all" link.')],
                    ['type' => 'text', 'label' => $this->l('"See all" URL'), 'name' => 'see_all_custom_url', 'lang' => true, 'hint' => $this->l('Used only if type = Custom link.')],
                    ['type' => 'hidden', 'name' => 'id_item'],
                    ['type' => 'hidden', 'name' => 'id_column'],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->menuUrl(['mm_view' => 'columns', 'id_item' => $idItem]);
        $helper->submit_action = 'submitMegaMenuColumn';
        $helper->fields_value = $fieldsValue;
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        return '<a class="btn btn-default" href="' . $backUrl . '">&laquo; ' . $this->l('Back to columns') . '</a>'
            . $helper->generateForm([$fieldsForm]);
    }

    protected function renderLinksList(int $idColumn): string
    {
        $idLang = (int) $this->context->language->id;
        $column = new MegaMenuColumn($idColumn, $idLang);
        $idItem = (int) $column->id_item;
        $links = MegaMenuLink::getForColumn($idColumn, $idLang);

        $html = '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'columns', 'id_item' => $idItem]) . '">&laquo; ' . $this->l('Back to columns') . '</a>';
        $html .= '<div class="panel">' . $this->panelTitle(sprintf($this->l('Links for column "%s"'), Tools::safeOutput($column->title)));
        $html .= '<div class="panel-body">';
        $html .= '<table class="table"><thead><tr>'
            . '<th style="width:80px">' . $this->l('Position') . '</th>'
            . '<th>' . $this->l('Label') . '</th>'
            . '<th>' . $this->l('Type') . '</th>'
            . '<th style="width:180px">' . $this->l('Actions') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($links as $link) {
            $idLink = (int) $link['id_link'];
            $label = $this->resolveLabel($link['link_type'], $link['id_category'], $link['id_manufacturer'], $link['label'], $idLang);

            $typeLabel = [
                MegaMenuLink::LINK_TYPE_CATEGORY => $this->l('Category'),
                MegaMenuLink::LINK_TYPE_MANUFACTURER => $this->l('Manufacturer'),
                MegaMenuLink::LINK_TYPE_CUSTOM => $this->l('Custom link'),
            ][$link['link_type']] ?? $link['link_type'];

            $html .= '<tr>';
            $html .= '<td>' . (int) $link['position']
                . ' <a href="' . $this->menuUrl(['moveMegaMenuLink' => 1, 'id_link' => $idLink, 'id_column' => $idColumn, 'id_item' => $idItem, 'way' => 'up']) . '"><i class="material-icons">arrow_upward</i></a>'
                . ' <a href="' . $this->menuUrl(['moveMegaMenuLink' => 1, 'id_link' => $idLink, 'id_column' => $idColumn, 'id_item' => $idItem, 'way' => 'down']) . '"><i class="material-icons">arrow_downward</i></a>'
                . '</td>';
            $html .= '<td>' . Tools::safeOutput($label) . '</td>';
            $html .= '<td>' . $typeLabel . '</td>';
            $html .= '<td>'
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'link_form', 'id_column' => $idColumn, 'id_item' => $idItem, 'id_link' => $idLink]) . '"><i class="material-icons">edit</i></a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['deleteMegaMenuLink' => 1, 'id_link' => $idLink, 'id_column' => $idColumn, 'id_item' => $idItem]) . '" onclick="return confirm(\'' . $this->l('Delete this link?') . '\')"><i class="material-icons">delete</i></a>'
                . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<a class="btn btn-primary" href="' . $this->menuUrl(['mm_view' => 'link_form', 'id_column' => $idColumn, 'id_item' => $idItem]) . '">' . $this->l('Add new link') . '</a>';
        $html .= '</div></div>';

        return $html;
    }

    protected function renderLinkForm(int $idColumn, int $idLink): string
    {
        $idItem = (int) (new MegaMenuColumn($idColumn))->id_item;
        $link = $idLink ? new MegaMenuLink($idLink) : null;

        $fieldsValue = [
            'id_column' => $idColumn,
            'id_item' => $idItem,
            'id_link' => $idLink,
            'link_type' => $link ? $link->link_type : MegaMenuLink::LINK_TYPE_CUSTOM,
            'id_category' => $link ? (int) $link->id_category : 0,
            'id_manufacturer' => $link ? (int) $link->id_manufacturer : 0,
            'label' => $link ? $link->label : [],
            'custom_url' => $link ? $link->custom_url : [],
        ];

        $fieldsForm = [
            'form' => [
                'legend' => ['title' => $idLink ? $this->l('Edit link') : $this->l('Add link')],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Link type'),
                        'name' => 'link_type',
                        'options' => [
                            'query' => [
                                ['id' => MegaMenuLink::LINK_TYPE_CATEGORY, 'name' => $this->l('Category')],
                                ['id' => MegaMenuLink::LINK_TYPE_MANUFACTURER, 'name' => $this->l('Manufacturer (brand)')],
                                ['id' => MegaMenuLink::LINK_TYPE_CUSTOM, 'name' => $this->l('Custom link')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    ['type' => 'select', 'label' => $this->l('Category'), 'name' => 'id_category', 'hint' => $this->l('Used only if link type = Category.'), 'options' => ['query' => $this->getCategoryOptions(), 'id' => 'id', 'name' => 'name']],
                    ['type' => 'select', 'label' => $this->l('Manufacturer'), 'name' => 'id_manufacturer', 'hint' => $this->l('Used only if link type = Manufacturer.'), 'options' => ['query' => $this->getManufacturerOptions(), 'id' => 'id', 'name' => 'name']],
                    ['type' => 'text', 'label' => $this->l('Label'), 'name' => 'label', 'lang' => true, 'hint' => $this->l('Used only if link type = Custom link (category/brand name is used otherwise).')],
                    ['type' => 'text', 'label' => $this->l('URL'), 'name' => 'custom_url', 'lang' => true, 'hint' => $this->l('Used only if link type = Custom link.')],
                    ['type' => 'hidden', 'name' => 'id_column'],
                    ['type' => 'hidden', 'name' => 'id_item'],
                    ['type' => 'hidden', 'name' => 'id_link'],
                ],
                'submit' => ['title' => $this->l('Save')],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->menuUrl(['mm_view' => 'links', 'id_column' => $idColumn, 'id_item' => $idItem]);
        $helper->submit_action = 'submitMegaMenuLink';
        $helper->fields_value = $fieldsValue;
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $backUrl = $this->menuUrl(['mm_view' => 'links', 'id_column' => $idColumn, 'id_item' => $idItem]);

        return '<a class="btn btn-default" href="' . $backUrl . '">&laquo; ' . $this->l('Back to links') . '</a>'
            . $helper->generateForm([$fieldsForm]);
    }
}
