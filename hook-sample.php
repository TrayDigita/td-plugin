<?php

use TrayDigita\WP\Plugin\TrayDigitaPlugin\Handler;
use TrayDigita\WP\Plugin\TrayDigitaPlugin\Plugin;
use TrayDigita\WP\Plugin\TrayDigitaPlugin\PluginInfoAPI;
use TrayDigita\WP\Plugin\TrayDigitaPlugin\PluginUpdateAPI;

if (!defined('ABSPATH')) {
    return;
}

if (!function_exists('tray_digita_add_plugin_update_callback')) {
    function tray_digita_add_plugin_update_callback(Plugin $plugin)
    {
        $api              = new PluginUpdateAPI();
        $api->slug        = dirname($plugin->slug) ?: $plugin->slug;
        $api->plugin      = $plugin->slug;
        $api->new_version = '2.0.0';
        $api->package     = 'https://example.com/plugin.zip';
        $api->url = $plugin->plugin_uri;
        $api->id = preg_replace('~(?:(?:https?:)?//)?([?]+)(?:\?.*)?~', '$1', $plugin->plugin_uri?:$plugin->slug);
        return $api;
    }
}

if (!function_exists('tray_digita_add_plugin_info_callback')) {
    function tray_digita_add_plugin_info_callback(Plugin $plugin)
    {
        $api                 = new PluginInfoAPI();
        $api->version        = '2.0.0';
        $api->download_link  = 'https://example.com/plugin.zip';
        $api->rating         = 100;
        $api->ratings        = [
            5 => 10000,
            4 => 0,
            3 => 0,
            2 => 0,
            1 => 0,
        ];
        $api->slug           = dirname($plugin->slug) ?: $plugin->slug;
        $api->author_profile = $plugin->author_uri;
        $api->author         = $plugin->author;
        $api->homepage       = $plugin->plugin_uri;
        $api->requires       = $plugin->requires_wp;
        $api->requires_php   = $plugin->requires_php;
        global $wp_version;
        $api->tested                   = $wp_version;
        $api->name                     = $plugin->name;
        $api->active_installs          = 10000000;
        $api->support_threads          = 100;
        $api->support_threads_resolved = 100;
        $api->added                    = gmdate('Y-m-d');
        $api->last_updated             = gmdate('Y-m-d H:ia \G\M\T');

        return $api;
    }
}

function tray_digita_add_plugin(Handler $admin)
{
    $plugins = $admin->getPlugins();
    $plugin_list = [
        'plugin-sample' => [
            'name' => __('Tray Digita Plugin', 'tray-digita'),
            'version' => '1.0.0',
            'description' => __('Tray Digita Plugin Description', 'tray-digita'),
            'required'=> true,
            'package' => '/path/to/plugin.zip',
            'update_callback' => 'tray_digita_add_plugin_update_callback',
            'information_callback' => 'tray_digita_add_plugin_info_callback',
        ],
        'amp' => [
            'name' => __('AMP', 'tray-digita'),
            'description' => __('AMP PLUGIN', 'tray-digita'),
            'required' => true,
            'wordpress' => true
        ]
    ];

    foreach ($plugin_list as $slug => $args) {
        $plugins->add($plugins->create($slug, $args));
    }
}

add_action('tray_digita_plugin_doing_init', 'tray_digita_add_plugin');
