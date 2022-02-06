<?php
namespace TrayDigita\WP\Plugin\TrayDigitaPlugin;

use IteratorAggregate;
use stdClass;
use Traversable;
use WP_Error;

class Plugins implements IteratorAggregate
{
    protected $default_headers = [
        'Name'           => 'name',
        'Title'          => 'title',
        'PluginURI'      => 'plugin_uri',
        'Version'        => 'version',
        'Description'    => 'description',
        'Author'         => 'author',
        'AuthorName'     => 'author_name',
        'AuthorURI'      => 'author_uri',
        'TextDomain'     => 'text_domain',
        'DomainPath'     => 'domain_path',
        'Network'        => 'network',
        'RequiresWP'     => 'requires_wp',
        'RequiresPHP'    => 'requires_php',
        'UpdateURI'      => 'update_uri',
        'UpdateCallback' => 'update_callback',
    ];

    /**
     * @var array<string, Plugin>
     */
    private $plugins = [];
    /**
     * @var array<string, string>
     */
    private $added_plugins = [];

    /**
     * @var array<string, string>
     */
    private $cached_slugs = [];

    public function __construct()
    {
        if ( ! function_exists('get_plugins')) {
            include_once ABSPATH . '/wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        foreach ($plugins as $key => $plugin) {
            if (is_array($plugin) && isset($plugin['slug']) || isset($plugin['Slug'])) {
                $key = isset($plugin['slug']) && is_string($plugin['slug'])
                    ? $plugin['slug']
                    : (isset($plugin['Slug']) && is_string($plugin['Slug']) ? $plugin['Slug'] : null);
            }
            if (is_string($key) && is_array($plugin)) {
                $plugin = $this->create($key, $plugin);
                $this->resolveTransientData($plugin);
                $this->plugins[$plugin->slug] = $plugin;
            }
            $this->cached_slugs[dirname($key)] = $key;
        }
    }

    /**
     * @return false|\stdClass
     * @see _maybe_update_plugins()
     *
     */
    public function maybeUpdatePlugins()
    {
        $current = get_site_transient('update_plugins');
        if ( ! isset($current->last_checked)
             || 12 * HOUR_IN_SECONDS > (time() - $current->last_checked)
        ) {
            wp_update_plugins();
            do_action('tray_digita_plugin_doing_updates', $this);
            $_new = get_site_transient('update_plugins');
            if (is_object($_new)) {
                $current = $_new;
            }
        }

        return $current;
    }

    /**
     * @return \stdClass|false
     */
    public static function updateTransients()
    {
        $transient = get_site_transient('update_plugins');
        if ( ! is_object($transient)) {
            return false;
        }

        return $transient;
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        return apply_filters('news-tray-plugin-default-headers', $this->default_headers);
    }

    public function create(string $slug, array $args = []): Plugin
    {
        return new Plugin($this, $slug, $args);
    }

    protected function resolveTransientData(Plugin $plugin)
    {
        static $transient = null;
        if ( ! is_array($transient)) {
            $transients = $this->updateTransients();
            if (is_object($transients)) {
                $plugins   = $transients->no_update ?? [];
                $transient = array_merge(($transients->response ?? []), $plugins);
            }
        }

        if (is_array($transient) && ! is_bool($plugin->wordpress)) {
            $d_trans = [];
            foreach ($transient as $key => $item) {
                if (strpos($key, '/')) {
                    $d_trans[dirname($key)] = $item;
                }
            }
            if ((isset($transient[$plugin->slug]) || (
                        is_string($plugin->package)
                        && $plugin->package
                        && preg_match('~^https?://[^.]+\.wordpress\.org/plugin/~i', $plugin->package)
                    )
                ) && isset($transient[$plugin->slug]->id) && strpos($transient[$plugin->slug]->id, 'w.org') !== false) {
                $plugin->wordpress = true;
            }

            if (isset($d_trans[$plugin->slug]) && (
                    ! $plugin->package || preg_match('~^https?://[^.]+\.wordpress\.org/plugin/~i', $plugin->package)
                )) {
                $plugin_info = $this->getPluginInformation($plugin->slug);
                if ( ! empty($plugin_info) && isset($plugin_info->response)) {
                    foreach ($plugin_info->response as $key => $item) {
                        if (property_exists($plugin, $key) && ! $plugin->$key) {
                            $plugin->$key = $item;
                        }
                    }
                    if (empty($plugin->plugin_uri) && isset($plugin_info->response->slug)) {
                        $plugin->plugin_uri = sprintf('https://wordpress.org/plugins/%s', $plugin_info->response->slug);
                    }
                }

                foreach ($d_trans[$plugin->slug] as $key => $item) {
                    if (empty($plugin->{$key})) {
                        $plugin->{$key} = $item;
                    }
                }
            }
        } elseif ( ! isset($transients[$plugin->slug])) {
            $plugin_info = $this->getPluginInformation($plugin->slug);
            if ( ! empty($plugin_info) && isset($plugin_info->response)) {
                foreach ($plugin_info->response as $key => $item) {
                    if (property_exists($plugin, $key) && ! $plugin->$key) {
                        $plugin->$key = $item;
                    }
                }
                if (empty($plugin->plugin_uri) && isset($plugin_info->response->slug)) {
                    $plugin->plugin_uri = sprintf('https://wordpress.org/plugins/%s', $plugin_info->response->slug);
                }
                if ( ! empty($plugin_info->response->download_link)) {
                    $plugin->package = $plugin_info->response->download_link;
                }
            }
        }
    }

    /**
     * @param string $plugin
     * @param false $force
     *
     * @return array|mixed|stdClass|WP_Error
     */
    public function getPluginInformation(string $plugin, $force = false)
    {
        $name      = "tray-digita-plugin-info-$plugin";
        $transient = get_site_transient($name);
        if ( ! is_object($transient)
             || ! isset($transient->last_checked)
             || 12 * HOUR_IN_SECONDS <= (time() - $transient->last_checked)
             || ! isset($transient->response)
        ) {
            delete_site_transient($name);
            $transient = null;
        }
        if ($transient && ! $force) {
            return $transient;
        }

        $current               = new \stdClass();
        $current->last_checked = time();
        $current->response     = time();
        $url                   = 'http://api.wordpress.org/plugins/info/1.2/';
        $url                   = add_query_arg(
            [
                'action'  => 'plugin_information',
                'request' => [
                    'slug'   => $plugin,
                    'fields' => [
                        'sections'    => false,
                        'description' => false,
                    ],
                ],
            ],
            $url
        );
        $ssl                   = wp_http_supports(['ssl']);
        if ($ssl) {
            $url = set_url_scheme($url, 'https');
        }
        global $wp_version;
        $http_args = [
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url('/'),
        ];
        $request   = wp_remote_get($url, $http_args);
        if (is_wp_error($request)) {
            return $transient ?: $request;
        }
        $current->response = json_decode(wp_remote_retrieve_body($request));
        set_site_transient($name, $current);

        return $current;
    }

    public function add(Plugin $plugin): bool
    {
        $plugins = $this->get($plugin->slug);
        if (isset($this->cached_slugs[$plugin->slug])) {
            $this->added_plugins[$this->cached_slugs[$plugin->slug]] = $plugin->version;
        } else {
            $this->added_plugins[$plugin->slug] = $plugin->version;
        }
        if ($plugins) {
            $to_check = [
                'required',
                'update_callback',
                'information_callback',
            ];
            $this->resolveTransientData($plugin);
            foreach ($to_check as $item) {
                $data    = $plugin->{$item};
                $current = $plugins->{$item};
                if (is_array($current)) {
                    $data             = (array)$data;
                    $plugins->{$item} = array_merge($current, $data);
                    continue;
                }
                $plugins->{$item} = is_bool($data) ? (bool)$plugin->{$item} : $plugin->{$item};
            }

            return false;
        }
        $this->set($plugin);

        return true;
    }

    public function set(Plugin $plugin)
    {
        $this->added_plugins[$plugin->slug] = $plugin->version;
        $this->plugins[$plugin->slug]       = $plugin;
        if (substr($plugin->slug, -4) === '.php') {
            $this->cached_slugs[dirname($plugin->slug)] = $plugin->slug;
        } elseif (isset($this->cached_slugs[$plugin->slug])) {
            $plugin->old_slug = $plugin->slug;
            $plugin->slug     = $this->cached_slugs[$plugin->slug];
        }
        $this->resolveTransientData($plugin);
    }

    /**
     * @param string $slug
     *
     * @return false|Plugin
     */
    public function get(string $slug)
    {
        $key = isset($this->plugins[$slug])
            ? $slug
            : ($this->cached_slugs[$slug] ?? null);

        return $key ? $this->plugins[$key] : false;
    }

    /**
     * @return array<int, string>
     */
    public function getSlugs(): array
    {
        return array_keys($this->plugins);
    }

    /**
     * @return Traversable|array<string, Plugin>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->plugins);
    }

    /**
     * @return Plugin[]
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * @return array
     */
    public function getAddedPlugins(): array
    {
        return $this->added_plugins;
    }
}
