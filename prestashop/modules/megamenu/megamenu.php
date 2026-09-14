<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

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
        $this->version = '1.0.0';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mega Menu');
        $this->description = $this->l('Lets you choose which top-level categories appear as a switcher in the header.');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('displayMegaMenuSwitcher')
            || !$this->registerHook('actionFrontControllerSetMedia')
        ) {
            return false;
        }

        return Configuration::updateValue(self::ENABLED, 1)
            && Configuration::updateValue(self::CATEGORIES, json_encode([]));
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::ENABLED)
            && Configuration::deleteByName(self::CATEGORIES);
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitMegaMenu')) {
            $this->postProcess();
        }

        return $this->renderForm();
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
                    'title' => $this->l('Mega Menu'),
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Active'),
                        'name' => self::ENABLED,
                        'values' => [
                            [
                                'id' => 'enabled_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'enabled_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
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
}
