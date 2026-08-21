<?php

define('PLUGIN_ITASSETDASHBOARD_VERSION', '1.1.4');
define('PLUGIN_ITASSETDASHBOARD_MIN_GLPI', '10.0.0');
define('PLUGIN_ITASSETDASHBOARD_MAX_GLPI', '11.0.99');

function plugin_version_itassetdashboard() {
    return [
        'name'           => 'IT Asset Dashboard',
        'version'        => PLUGIN_ITASSETDASHBOARD_VERSION,
        'author'         => 'Piseth SEANG',
        'license'        => 'GPLv2+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_ITASSETDASHBOARD_MIN_GLPI,
                'max' => PLUGIN_ITASSETDASHBOARD_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_itassetdashboard_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_ITASSETDASHBOARD_MIN_GLPI, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_ITASSETDASHBOARD_MAX_GLPI, 'gt')) {
        echo "This plugin requires GLPI >= " . PLUGIN_ITASSETDASHBOARD_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_itassetdashboard_check_config() {
    return true;
}

function plugin_init_itassetdashboard() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['itassetdashboard'] = true;

    Plugin::registerClass('PluginItassetdashboardDashboard');

    if (Session::haveRight('computer', READ)) {
        $PLUGIN_HOOKS['menu_toadd']['itassetdashboard'] = [
            'tools' => 'PluginItassetdashboardDashboard'
        ];
    }
}
