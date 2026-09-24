<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class FreeShippingBarRefreshModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        ob_end_clean();
        header('Content-Type: application/json');
        exit(json_encode([
            'preview' => $this->module->renderBar(),
        ]));
    }
}
