<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegaMenuSelectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $success = $this->module->selectCategory(Tools::getValue('id_category'));

        ob_end_clean();
        header('Content-Type: application/json');
        exit(json_encode([
            'success' => $success,
        ]));
    }
}
