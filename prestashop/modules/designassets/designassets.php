<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DesignAssets extends Module
{
    public function __construct()
    {
        $this->name = 'designassets';
        $this->tab = 'front_office_features';
        $this->author = 'Design Presta';
        $this->version = '1.0.0';
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Design Assets');
        $this->description = $this->l('Registers the theme layout CSS files.');

        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install() && $this->registerHook('actionFrontControllerSetMedia');
    }

    public function hookActionFrontControllerSetMedia()
    {
        $fontsFile = _PS_THEME_DIR_ . 'assets/css/fonts.css';

        if (is_file($fontsFile)) {
            $this->context->controller->registerStylesheet(
                'designassets-fonts',
                '/assets/css/fonts.css',
                ['media' => 'all', 'priority' => 35]
            );
        }

        $tokensFile = _PS_THEME_DIR_ . 'assets/css/tokens.css';

        if (is_file($tokensFile)) {
            $this->context->controller->registerStylesheet(
                'designassets-tokens',
                '/assets/css/tokens.css',
                ['media' => 'all', 'priority' => 40]
            );
        }

        $overridesFile = _PS_THEME_DIR_ . 'assets/css/overrides.css';

        if (is_file($overridesFile)) {
            $this->context->controller->registerStylesheet(
                'designassets-overrides',
                '/assets/css/overrides.css',
                ['media' => 'all', 'priority' => 55]
            );
        }

        $layoutDir = _PS_THEME_DIR_ . 'assets/css/layout/';

        if (!is_dir($layoutDir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($layoutDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'css') {
                continue;
            }

            $relativePath = '/assets/css/layout/' . substr($file->getPathname(), strlen($layoutDir));
            $id = 'designassets-' . str_replace(['/', '\\'], '-', substr($relativePath, strlen('/assets/css/layout/')));

            $this->context->controller->registerStylesheet(
                $id,
                $relativePath,
                ['media' => 'all', 'priority' => 60]
            );
        }
    }
}
