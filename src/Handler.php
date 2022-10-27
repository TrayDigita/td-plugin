<?php
namespace TrayDigita\WP\Plugin\TrayDigitaPlugin;

class Handler
{
    /**
     * @var Handler|null
     */
    private static $instance = null;
    /**
     * @var Plugins
     */
    protected $plugins = null;
    /**
     * @var null|array<string, array>
     */
    protected $plugins_data = null;

    /**
     * @var array<string, array>
     */
    protected $not_installed = [];

    /**
     * @var array
     */
    protected $inactive = [];

    /**
     * @var array[]
     */
    protected $currents = [
        'required'    => [],
        'recommended' => [],
    ];

    private function __construct()
    {
        if ( ! function_exists('is_plugin_active')) {
            include_once ABSPATH . '/wp-admin/includes/plugin.php';
        }

        self::$instance = $this;
    }

    public static function instance()
    {
        if ( ! self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * @return Plugins
     */
    public function getPlugins(): Plugins
    {
        if ( ! $this->plugins) {
            $this->plugins = new Plugins();
        }

        return $this->plugins;
    }

    /**
     * @param array $actions
     * @param string $plugin_file
     * @param array $plugin_data
     * @param mixed $context
     *
     * @return array
     */
    public function hookActionLinks($actions, $plugin_file, $plugin_data, $context): array
    {
        $plugin = $this->plugins->get($plugin_file);
        if ( ! $plugin) {
            return $actions;
        }
        $exists = $plugin->isExists();
        $id     = sanitize_html_class(dirname($plugin_file));
        $link   = sprintf(
            '<a href="%s" id="install-%s" class="edit" aria-label="%s">%s</a>',
            wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=' . $plugin_file),
                'install-plugin_' . $plugin_file
            ),
            esc_attr($id),
            /* translators: %s: Plugin name. */
            esc_attr(sprintf(_x('Network Activate %s', 'plugin'), $plugin_data['Name'])),
            __('Install')
        );

        if ( ! $exists && current_user_can('install_plugins')) {
            if ($plugin->package || $plugin->update_callback) {
                $actions = [
                    'install' => $link,
                ];
            } else {
                $actions = ['unavailable' => __('Package not available', 'tray-digita')];
            }
        }

        if (apply_filters('tray_digita_add_info_text', true)) {
            if ($plugin->required) {
                $actions['required'] = apply_filters(
                    'tray_digita_required_text',
                    sprintf('<span style="padding:3px 5px;background:#b32d2e;color:#fff;">%s</span>',
                        __('Required', 'tray-digita'))
                );
            } else {
                $actions['recommended'] = apply_filters(
                    'tray_digita_recommended_text',
                    sprintf('<span style="padding:3px 5px;background:#38c750;color:#fff;">%s</span>',
                        __('Recommended', 'tray-digita'))
                );
            }
        }

        return $actions;
    }

    /**
     * @param array $plugins
     *
     * @return array
     */
    public function hookAllPlugins($plugins): array
    {
        $active_plugins = array_flip((array)get_option('active_plugins', []));
        $added          = $this->getPlugins()->getAddedPlugins();
        foreach ($this->getPlugins() as $plugin) {
            if ( ! isset($plugins[$plugin->slug]) || isset($added[$plugin->slug])) {
                if ($plugin->required) {
                    $this->currents['required'][$plugin->slug] = $plugin->toPluginData();
                } else {
                    $this->currents['recommended'][$plugin->slug] = $plugin->toPluginData();
                }

                if ( ! $plugin->isExists()) {
                    $this->not_installed[$plugin->slug] = $plugin->toPluginData();
                } elseif ( ! isset($active_plugins[$plugin->slug])) {
                    $this->inactive[$plugin->slug] = $plugin->toPluginData();
                }
                $plugins[$plugin->slug] = $plugin->toPluginData();
                add_action(
                    "plugin_action_links_{$plugin->slug}",
                    [$this, 'hookActionLinks'],
                    10,
                    4
                );
            }
        }

        return $plugins;
    }

    public function hookSetStatus()
    {
        global $status, $wp_list_table;
        if ($wp_list_table instanceof \WP_Plugins_List_Table) {
            $new_status = $_REQUEST['plugin_status'] ?? null;
            if ($new_status === 'td-install' && count($this->not_installed)) {
                $status               = $new_status;
                $wp_list_table->items = $this->not_installed;
            }
            if ($new_status === 'td-activate' && count($this->inactive)) {
                $status               = $new_status;
                $wp_list_table->items = $this->inactive;
            }
        }
    }

    public function hookViews($views)
    {
        global $status;

        if ( ! doing_action('views_plugins')) {
            return $views;
        }

        $install_count = count($this->not_installed);
        if ($install_count) {
            $install_text = _nx(
                'Install <span class="count">(%s)</span>',
                'Install <span class="count">(%s)</span>',
                $install_count,
                'plugins',
                'tray-digita'
            );

            $views['td-install'] = sprintf(
                "<a href='%s'%s>%s</a>",
                add_query_arg('plugin_status', 'td-install', 'plugins.php'),
                ($status === 'td-install') ? ' class="current" aria-current="page"' : '',
                sprintf($install_text, number_format_i18n($install_count))
            );
        }

        if ( ! empty($this->inactive)) {
            $inactive_text = _nx(
                'Activate <span class="count">(%s)</span>',
                'Activate <span class="count">(%s)</span>',
                count($this->inactive),
                'plugins',
                'tray-digita'
            );

            $views['td-activate'] = sprintf(
                "<a href='%s'%s>%s</a>",
                add_query_arg('plugin_status', 'td-activate', 'plugins.php'),
                ($status === 'td-activate') ? ' class="current" aria-current="page"' : '',
                sprintf($inactive_text, number_format_i18n(count($this->inactive)))
            );
        }

        return $views;
    }

    public function hookPluginsApi($res, $action, $args)
    {
        $new_args    = (array)$args;
        $plugin_slug = $new_args['slug'] ?? null;

        if ( ! $plugin_slug) {
            return $res;
        }
        $plugin = $this->getPlugins()->get($plugin_slug);
        if ( ! $plugin) {
            return $res;
        }

        $action = $_REQUEST['action'] ?? ($_REQUEST['tab'] ?? '');
        switch ($action) {
            case 'install-plugin':
            case 'upgrade-plugin':
                if (is_callable($plugin->update_callback)) {
                    $new_result = call_user_func(
                        $plugin->update_callback,
                        $plugin,
                        $this,
                        $res,
                        $action,
                        $args
                    );
                    if ($new_result instanceof PluginUpdateAPI) {
                        $new_result = get_object_vars($new_result);
                        unset($new_result['*data']);
                        $res = $new_result;
                    }
                }

                // remove filter
                // global $wp_filter;
                // unset($wp_filter['upgrader_process_complete']);
                do_action('tray_digita_plugin_doing_install', $res);
                add_filter('install_plugin_complete_actions', function ($actions) {
                    $link = self_admin_url('plugins.php');
                    if (count($this->not_installed) > 1) {
                        $link = add_query_arg('plugin_status', 'install', $link);
                    }
                    $actions['plugins_page'] = sprintf(
                        '<a href="%s" target="_parent">%s</a>',
                        $link,
                        __('Go to Plugins page')
                    );

                    return $actions;
                });
                break;
            case 'plugin-information':
                if (is_callable($plugin->information_callback)) {
                    $new_result = call_user_func(
                        $plugin->information_callback,
                        $plugin,
                        $this,
                        $res,
                        $action,
                        $args
                    );
                    if ($new_result instanceof PluginInfoAPI) {
                        $res = $new_result;
                    }
                }
                break;
        }

        return $res;
    }

    /**
     * @param $response
     * @param $parsed_args
     * @param $url
     *
     * @return false
     */
    public function hookHandlePluginUpdate($response, $parsed_args, $url)
    {
        if (strpos($url, 'api.wordpress.org/plugins/update-check/1.1/') === false
            || ($parsed_args['method'] ?? "GET") !== 'POST'
        ) {
            return $response;
        }

        $body  = wp_remote_retrieve_body($response);
        $body  = json_decode($body, true) ?: [];
        $slugs = array_merge(array_keys($body['no_update']), array_keys($body['plugins']));
        $slugs = array_flip($slugs);
        foreach ($this->getPlugins() as $plugin) {
            $slug = $plugin->slug;
            if (isset($slugs[$slug])) {
                continue;
            }
            if ( ! is_callable($plugin->update_callback)) {
                continue;
            }
            $update[$slug] = $plugin;
        }
        if ( ! empty($update)) {
            $default_body = [
                'plugins'      => [],
                'translations' => [],
                'no_update'    => [],
            ];

            $body = is_array($body) ? array_merge($default_body, $body) : [];
            foreach ($update as $plugin) {
                $new_result = call_user_func(
                    $plugin->update_callback,
                    $plugin,
                    $this,
                    $response,
                    'update-check',
                    $body
                );
                if ( ! $new_result instanceof PluginUpdateAPI) {
                    continue;
                }
                $plugin->new_version     = $new_result->new_version ?: $plugin->new_version;
                $k                       = $plugin->isNeedUpdate() ? 'plugins' : 'no_update';
                $body[$k][$plugin->slug] = get_object_vars($new_result);
                $translation             = $body[$k][$plugin->slug]['translations'] ?: [];
                unset(
                    $body[$k][$plugin->slug]['*data'],
                    $body[$k][$plugin->slug]['translations']
                );
                $body['translations'] = array_merge($body['translations'], $translation);
            }
            $response['body'] = json_encode($body);
        }

        return $response;
    }

    public function init()
    {
        if ( ! current_user_can('update_plugins') && current_user_can('install_plugins')) {
            return;
        }
        if ( ! is_admin() && ! (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }

        do_action('tray_digita_plugin_doing_init', $this);
        add_action('plugins_api', [$this, 'hookPluginsApi'], 10, 3);
        add_filter('all_plugins', [$this, 'hookAllPlugins']);
        add_action('pre_current_active_plugins', [$this, 'hookSetStatus']);
        add_filter("views_plugins", [$this, 'hookViews']);
        if (current_user_can('update_plugins')) {
            add_action('http_response', [$this, 'hookHandlePluginUpdate'], 10, 3);
        }
    }
}
