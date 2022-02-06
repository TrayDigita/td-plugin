<?php
use TrayDigita\WP\Plugin\TrayDigitaPlugin\Handler;
use TrayDigita\WP\Plugin\TrayDigitaPlugin\Plugins;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ .'/src/Plugin.php';
require_once __DIR__ .'/src/Plugins.php';
require_once __DIR__ .'/src/Handler.php';
require_once __DIR__ .'/src/PluginInfoAPI.php';
require_once __DIR__ .'/src/PluginUpdateAPI.php';

if (!function_exists('tray_digita_plugin_instance')) {
    function tray_digita_plugin_instance(): Plugins
    {
        return Handler::instance()->getPlugins();
    }
}

add_action('admin_init', [Handler::instance(), 'init']);
