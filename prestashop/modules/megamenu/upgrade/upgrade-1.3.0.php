<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_0($module)
{
    $module->seedMenMenu();
    $module->seedKidsMenu();
    $module->fixWomenMenuLinks();

    return true;
}
