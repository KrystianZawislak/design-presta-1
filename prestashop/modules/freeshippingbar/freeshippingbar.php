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
        return parent::install()
            && $this->registerHook('displayFreeShippingBar')
            && Configuration::updateValue(self::TAX_MODE, 'brutto')
            && Configuration::updateValue(self::VAT_PERCENT, 23)
            && Configuration::updateValue(self::ENABLED, 1);
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::TEXT)
            && Configuration::deleteByName(self::TAX_MODE)
            && Configuration::deleteByName(self::VAT_PERCENT)
            && Configuration::deleteByName(self::ENABLED);
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
        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            Configuration::updateValue(
                self::TEXT,
                Tools::getValue(self::TEXT . '_' . $lang['id_lang']),
                false,
                null,
                (int) $lang['id_lang']
            );
        }

        Configuration::updateValue(self::TAX_MODE, Tools::getValue(self::TAX_MODE));
        Configuration::updateValue(self::VAT_PERCENT, (float) Tools::getValue(self::VAT_PERCENT));
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
            self::TAX_MODE => Configuration::get(self::TAX_MODE),
            self::VAT_PERCENT => Configuration::get(self::VAT_PERCENT),
            self::ENABLED => Configuration::get(self::ENABLED),
            self::TEXT => [],
        ];

        foreach (Language::getLanguages(false) as $lang) {
            $values[self::TEXT][(int) $lang['id_lang']] = Configuration::get(self::TEXT, (int) $lang['id_lang']);
        }

        return $values;
    }

    public function hookDisplayFreeShippingBar()
    {
        if (!Configuration::get(self::ENABLED)) {
            return '';
        }

        $threshold = (float) Configuration::get('PS_SHIPPING_FREE_PRICE');

        if ($threshold <= 0) {
            return '';
        }

        $cart = $this->context->cart;
        $cartTotal = $cart ? (float) $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING, null, null, false) : 0.0;

        $missing = $threshold - $cartTotal;

        if ($missing <= 0) {
            return '';
        }

        $template = Configuration::get(self::TEXT, (int) $this->context->language->id);

        if (empty($template)) {
            return '';
        }

        $taxMode = Configuration::get(self::TAX_MODE);
        $vatPercent = (float) Configuration::get(self::VAT_PERCENT);

        if ($taxMode === 'netto' && $vatPercent > 0) {
            $missing = $missing / (1 + $vatPercent / 100);
        }

        $locale = Tools::getContextLocale($this->context);
        $formattedAmount = $locale->formatPrice($missing, $this->context->currency->iso_code);
        $message = str_replace('%amount%', $formattedAmount, $template);

        $this->context->smarty->assign('freeShippingBarMessage', $message);

        return $this->fetch('module:freeshippingbar/views/templates/hook/freeshippingbar.tpl');
    }
}
