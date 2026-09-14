<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class FreeShippingBar extends Module
{
    const TEXT = 'FREESHIPPINGBAR_TEXT';
    const TAX_MODE = 'FREESHIPPINGBAR_TAX_MODE';
    const VAT_PERCENT = 'FREESHIPPINGBAR_VAT_PERCENT';
    const ENABLED = 'FREESHIPPINGBAR_ENABLED';

    const PER_LANGUAGE_KEYS = [self::TEXT, self::TAX_MODE, self::VAT_PERCENT];

    public function __construct()
    {
        $this->name = 'freeshippingbar';
        $this->tab = 'front_office_features';
        $this->author = 'Design Presta';
        $this->version = '1.0.0';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Free Shipping Bar');
        $this->description = $this->l('Displays how much is left to reach free shipping, in the header.');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('displayFreeShippingBar')
            || !$this->registerHook('actionFrontControllerSetMedia')
        ) {
            return false;
        }

        $taxModeDefaults = [];
        $vatPercentDefaults = [];

        foreach (Language::getLanguages(false) as $lang) {
            $taxModeDefaults[(int) $lang['id_lang']] = 'brutto';
            $vatPercentDefaults[(int) $lang['id_lang']] = 23;
        }

        return Configuration::updateValue(self::TAX_MODE, $taxModeDefaults)
            && Configuration::updateValue(self::VAT_PERCENT, $vatPercentDefaults)
            && Configuration::updateValue(self::ENABLED, 1);
    }

    public function uninstall()
    {
        foreach (self::PER_LANGUAGE_KEYS as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall() && Configuration::deleteByName(self::ENABLED);
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitFreeShippingBar')) {
            $this->postProcess();
        }

        return $this->renderForm();
    }

    protected function postProcess()
    {
        foreach (self::PER_LANGUAGE_KEYS as $key) {
            $values = [];

            foreach (Language::getLanguages(false) as $lang) {
                $values[(int) $lang['id_lang']] = Tools::getValue($key . '_' . $lang['id_lang']);
            }

            Configuration::updateValue($key, $values);
        }

        Configuration::updateValue(self::ENABLED, (int) Tools::getValue(self::ENABLED));
    }

    protected function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Free Shipping Bar'),
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
                        'type' => 'text',
                        'label' => $this->l('Message'),
                        'name' => self::TEXT,
                        'lang' => true,
                        'desc' => $this->l('Use %amount% where the missing amount should appear.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Display amount as'),
                        'name' => self::TAX_MODE,
                        'lang' => true,
                        'values' => [
                            [
                                'id' => 'tax_mode_brutto',
                                'value' => 'brutto',
                                'label' => $this->l('Brutto'),
                            ],
                            [
                                'id' => 'tax_mode_netto',
                                'value' => 'netto',
                                'label' => $this->l('Netto'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('VAT percentage'),
                        'name' => self::VAT_PERCENT,
                        'lang' => true,
                        'suffix' => '%',
                        'desc' => $this->l('Used only when the amount is displayed as Netto, since the native PrestaShop shipping threshold is stored as Brutto.'),
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
        $helper->languages = Language::getLanguages(false);
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->submit_action = 'submitFreeShippingBar';

        $helper->fields_value = $this->getConfigFieldsValues();

        return $helper->generateForm([$fields_form]);
    }

    protected function getConfigFieldsValues()
    {
        $values = [
            self::ENABLED => Configuration::get(self::ENABLED),
        ];

        foreach (self::PER_LANGUAGE_KEYS as $key) {
            $values[$key] = [];

            foreach (Language::getLanguages(false) as $lang) {
                $values[$key][(int) $lang['id_lang']] = Configuration::get($key, (int) $lang['id_lang']);
            }
        }

        return $values;
    }

    public function hookDisplayFreeShippingBar()
    {
        return $this->renderBar();
    }

    public function hookActionFrontControllerSetMedia()
    {
        if (!$this->isActive()) {
            return;
        }

        $this->context->controller->registerJavascript(
            'module-freeshippingbar',
            'modules/' . $this->name . '/views/js/freeshippingbar.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }

    public function renderBar()
    {
        if (!$this->isActive()) {
            return '';
        }

        $message = $this->getMessage();

        $this->context->smarty->assign([
            'freeShippingBarMessage' => $message,
            'freeShippingBarRefreshUrl' => $this->context->link->getModuleLink($this->name, 'refresh'),
        ]);

        return $this->fetch('module:freeshippingbar/views/templates/hook/freeshippingbar.tpl');
    }

    protected function isActive()
    {
        return Configuration::get(self::ENABLED) && (float) Configuration::get('PS_SHIPPING_FREE_PRICE') > 0;
    }

    protected function getMessage()
    {
        $threshold = (float) Configuration::get('PS_SHIPPING_FREE_PRICE');
        $cart = $this->context->cart;
        $cartTotal = $cart ? (float) $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, null, null, false) : 0.0;
        $missing = $threshold - $cartTotal;

        if ($missing <= 0) {
            return '';
        }

        $idLang = (int) $this->context->language->id;
        $template = Configuration::get(self::TEXT, $idLang);

        if (empty($template)) {
            return '';
        }

        $taxMode = Configuration::get(self::TAX_MODE, $idLang);
        $vatPercent = (float) Configuration::get(self::VAT_PERCENT, $idLang);

        if ($taxMode === 'netto' && $vatPercent > 0) {
            $missing = $missing / (1 + $vatPercent / 100);
        }

        $locale = Tools::getContextLocale($this->context);
        $formattedAmount = $locale->formatPrice($missing, $this->context->currency->iso_code);

        return str_replace('%amount%', $formattedAmount, $template);
    }
}
