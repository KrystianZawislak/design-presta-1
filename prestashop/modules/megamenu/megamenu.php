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
        $this->version = '1.1.0';
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

        foreach ($items as $position => $itemData) {
            $item = new MegaMenuItem();
            $item->link_type = $itemData['link']['type'];
            $item->id_category = (int) ($itemData['link']['id_category'] ?? 0);
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
                $column->id_manufacturer = 0;
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
                    $link->id_manufacturer = 0;
                    $link->label = $linkData['label'];
                    $link->custom_url = $this->fillLang($langIds, $linkData['target']['type'] === 'custom' ? $linkData['target']['url'] : '');
                    $link->add();
                }
            }
        }
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

        $this->context->smarty->assign(['menu' => $menu]);

        return $this->fetch('module:megamenu/views/templates/hook/nav.tpl');
    }

    protected function buildMenu()
    {
        $idLang = (int) $this->context->language->id;
        $root = ['children' => []];

        $currentController = Dispatcher::getInstance()->getController();
        $currentIdCategory = (int) Tools::getValue('id_category');
        $selectedGenderId = $this->getSelectedTopCategoryId();

        foreach (MegaMenuItem::getAllForLang($idLang, true) as $itemRow) {
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
        if (Tools::isSubmit('moveMegaMenuColumn')) {
            return $this->moveEntity(new MegaMenuColumn((int) Tools::getValue('id_column')), Tools::getValue('way'));
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
        $item->has_dropdown = (bool) Tools::getValue('has_dropdown');
        $item->active = (bool) Tools::getValue('active');

        foreach (Language::getLanguages(false) as $lang) {
            $item->label[$lang['id_lang']] = Tools::getValue('label_' . $lang['id_lang']);
            $item->custom_url[$lang['id_lang']] = Tools::getValue('custom_url_' . $lang['id_lang']);
        }

        if (!$idItem) {
            $item->position = MegaMenuItem::getNextPosition();
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
        $column = $idColumn ? new MegaMenuColumn($idColumn) : new MegaMenuColumn();

        $column->id_item = $idItem;
        $column->see_all_type = Tools::getValue('see_all_type', MegaMenuColumn::SEE_ALL_NONE);
        $column->id_category = (int) Tools::getValue('see_all_id_category');
        $column->id_manufacturer = (int) Tools::getValue('see_all_id_manufacturer');

        foreach (Language::getLanguages(false) as $lang) {
            $column->title[$lang['id_lang']] = Tools::getValue('title_' . $lang['id_lang']);
            $column->see_all_label[$lang['id_lang']] = Tools::getValue('see_all_label_' . $lang['id_lang']);
            $column->see_all_custom_url[$lang['id_lang']] = Tools::getValue('see_all_custom_url_' . $lang['id_lang']);
        }

        if (!$idColumn) {
            $column->position = MegaMenuColumn::getNextPosition($idItem);
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

    protected function renderItemsList(): string
    {
        $idLang = (int) $this->context->language->id;
        $items = MegaMenuItem::getAllForLang($idLang, false);

        $html = '<div class="panel">' . $this->panelTitle($this->l('Main menu items'));
        $html .= '<div class="panel-body">';
        $html .= '<table class="table"><thead><tr>'
            . '<th style="width:80px">' . $this->l('Position') . '</th>'
            . '<th>' . $this->l('Label') . '</th>'
            . '<th>' . $this->l('Type') . '</th>'
            . '<th>' . $this->l('Dropdown') . '</th>'
            . '<th>' . $this->l('Active') . '</th>'
            . '<th style="width:260px">' . $this->l('Actions') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($items as $item) {
            $idItem = (int) $item['id_item'];
            $label = $item['link_type'] === MegaMenuItem::LINK_TYPE_CATEGORY
                ? $this->resolveLabel(MegaMenuColumn::SEE_ALL_CATEGORY, $item['id_category'], null, $item['label'], $idLang)
                : $item['label'];

            $html .= '<tr>';
            $html .= '<td>' . (int) $item['position']
                . ' <a href="' . $this->menuUrl(['moveMegaMenuItem' => 1, 'id_item' => $idItem, 'way' => 'up']) . '" title="' . $this->l('Move up') . '"><i class="material-icons">arrow_upward</i></a>'
                . ' <a href="' . $this->menuUrl(['moveMegaMenuItem' => 1, 'id_item' => $idItem, 'way' => 'down']) . '" title="' . $this->l('Move down') . '"><i class="material-icons">arrow_downward</i></a>'
                . '</td>';
            $html .= '<td>' . Tools::safeOutput($label) . '</td>';
            $html .= '<td>' . ($item['link_type'] === MegaMenuItem::LINK_TYPE_CATEGORY ? $this->l('Category') : $this->l('Custom link')) . '</td>';
            $html .= '<td>' . ((int) $item['has_dropdown'] ? $this->l('Yes') : $this->l('No')) . '</td>';
            $html .= '<td>' . ((int) $item['active'] ? $this->l('Yes') : $this->l('No')) . '</td>';
            $html .= '<td>'
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'columns', 'id_item' => $idItem]) . '">' . $this->l('Columns') . '</a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'item_form', 'id_item' => $idItem]) . '"><i class="material-icons">edit</i></a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['deleteMegaMenuItem' => 1, 'id_item' => $idItem]) . '" onclick="return confirm(\'' . $this->l('Delete this item and everything inside it?') . '\')"><i class="material-icons">delete</i></a>'
                . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<a class="btn btn-primary" href="' . $this->menuUrl(['mm_view' => 'item_form']) . '">' . $this->l('Add new menu item') . '</a>';
        $html .= '</div></div>';

        return $html;
    }

    protected function renderItemForm(int $idItem): string
    {
        $item = $idItem ? new MegaMenuItem($idItem) : null;

        $fieldsValue = [
            'id_item' => $idItem,
            'link_type' => $item ? $item->link_type : MegaMenuItem::LINK_TYPE_CUSTOM,
            'id_category' => $item ? (int) $item->id_category : 0,
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
        $helper->currentIndex = $this->menuUrl();
        $helper->submit_action = 'submitMegaMenuItem';
        $helper->fields_value = $fieldsValue;
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $backUrl = $this->menuUrl();

        return '<a class="btn btn-default" href="' . $backUrl . '">&laquo; ' . $this->l('Back to menu items') . '</a>'
            . $helper->generateForm([$fieldsForm]);
    }

    protected function renderColumnsList(int $idItem): string
    {
        $idLang = (int) $this->context->language->id;
        $item = new MegaMenuItem($idItem, $idLang);
        $columns = MegaMenuColumn::getForItem($idItem, $idLang);

        $html = '<a class="btn btn-default" href="' . $this->menuUrl() . '">&laquo; ' . $this->l('Back to menu items') . '</a>';
        $html .= '<div class="panel">' . $this->panelTitle(sprintf($this->l('Columns for "%s"'), Tools::safeOutput($item->label)));
        $html .= '<div class="panel-body">';
        $html .= '<table class="table"><thead><tr>'
            . '<th style="width:80px">' . $this->l('Position') . '</th>'
            . '<th>' . $this->l('Title') . '</th>'
            . '<th style="width:280px">' . $this->l('Actions') . '</th>'
            . '</tr></thead><tbody>';

        foreach ($columns as $column) {
            $idColumn = (int) $column['id_column'];
            $html .= '<tr>';
            $html .= '<td>' . (int) $column['position']
                . ' <a href="' . $this->menuUrl(['moveMegaMenuColumn' => 1, 'id_column' => $idColumn, 'id_item' => $idItem, 'way' => 'up']) . '"><i class="material-icons">arrow_upward</i></a>'
                . ' <a href="' . $this->menuUrl(['moveMegaMenuColumn' => 1, 'id_column' => $idColumn, 'id_item' => $idItem, 'way' => 'down']) . '"><i class="material-icons">arrow_downward</i></a>'
                . '</td>';
            $html .= '<td>' . Tools::safeOutput($column['title']) . '</td>';
            $html .= '<td>'
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'links', 'id_column' => $idColumn, 'id_item' => $idItem]) . '">' . $this->l('Links') . '</a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['mm_view' => 'column_form', 'id_item' => $idItem, 'id_column' => $idColumn]) . '"><i class="material-icons">edit</i></a> '
                . '<a class="btn btn-default" href="' . $this->menuUrl(['deleteMegaMenuColumn' => 1, 'id_column' => $idColumn, 'id_item' => $idItem]) . '" onclick="return confirm(\'' . $this->l('Delete this column and its links?') . '\')"><i class="material-icons">delete</i></a>'
                . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $html .= '<a class="btn btn-primary" href="' . $this->menuUrl(['mm_view' => 'column_form', 'id_item' => $idItem]) . '">' . $this->l('Add new column') . '</a>';
        $html .= '</div></div>';

        return $html;
    }

    protected function renderColumnForm(int $idItem, int $idColumn): string
    {
        $column = $idColumn ? new MegaMenuColumn($idColumn) : null;

        $fieldsValue = [
            'id_item' => $idItem,
            'id_column' => $idColumn,
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
        $helper->currentIndex = $this->menuUrl();
        $helper->submit_action = 'submitMegaMenuColumn';
        $helper->fields_value = $fieldsValue;
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $backUrl = $this->menuUrl(['mm_view' => 'columns', 'id_item' => $idItem]);

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
        $helper->currentIndex = $this->menuUrl();
        $helper->submit_action = 'submitMegaMenuLink';
        $helper->fields_value = $fieldsValue;
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $backUrl = $this->menuUrl(['mm_view' => 'links', 'id_column' => $idColumn, 'id_item' => $idItem]);

        return '<a class="btn btn-default" href="' . $backUrl . '">&laquo; ' . $this->l('Back to links') . '</a>'
            . $helper->generateForm([$fieldsForm]);
    }
}
