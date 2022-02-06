<?php
namespace TrayDigita\WP\Plugin\TrayDigitaPlugin;

class Plugin
{
    public $slug;
    public $package = '';
    public $name = '';
    public $title = '';
    public $plugin_uri = '';
    public $version = '';
    public $description = '';
    public $author = '';
    public $author_name = '';
    public $author_uri = '';
    public $text_domain = '';
    public $domain_path = '';
    public $new_version = '';
    public $network = null;
    public $requires_wp = '';
    public $requires_php = '';
    public $update_uri = '';
    public $required = false;
    public $update_callback = null;
    public $information_callback = null;
    public $wordpress = null;
    protected $plugins;
    protected $data = [];

    /**
     * @param Plugins $plugins
     * @param string $slug
     * @param array $args
     */
    public function __construct(Plugins $plugins, string $slug, array $args = [])
    {
        $slug            = ltrim(wp_normalize_path($slug), '/');
        $this->plugins   = $plugins;
        $default_headers = $plugins->getDefaultHeaders();
        $transient       = $plugins->updateTransients();
        if (is_object($transient)) {
            $response  = $transient->response ?? [];
            $no_update = $transient->no_update ?? [];
            if (is_array($no_update) && isset($no_update[$slug])) {
                $no_update[$slug] = (array)$no_update[$slug];
                $args             = array_merge($no_update[$slug], $args);
            }
            if (is_array($response) && isset($response[$slug])) {
                $response[$slug] = (array)$response[$slug];
                $args            = array_merge($response[$slug], $args);
            }
        }

        $this->slug = $slug;
        unset($args['slug']);
        foreach ($args as $key => $arg) {
            $key = $default_headers[$key] ?? $key;
            if ($key === 'required') {
                $this->required = (bool)$arg;
                continue;
            }
            if (($key === 'update_callback' || $key === 'information_callback') && ! is_callable($arg)) {
                continue;
            }
            if ($key === 'plugin' || $key === 'data') {
                $this->data[$key] = $arg;
                continue;
            }
            if (property_exists($this, $key)) {
                $this->{$key} = $arg;
                continue;
            }
            $this->data[$key] = $arg;
        }

        if ( ! $this->title) {
            $this->title = $this->name;
        }
        if ( ! $this->name) {
            $this->name = $this->title;
        }
        if ( ! $this->author_name) {
            $this->author_name = $this->author;
        }
        if ( ! $this->author) {
            $this->author = $this->author_name;
        }
        if ( ! $this->requires_wp && isset($args['requires'])) {
            $this->requires_wp = is_array($args['requires']) ? (string)reset($args['requires']) : (string)$args['requires'];
        }
        if ( ! $this->requires_php && isset($args['requires_php'])) {
            $this->requires_php = is_array($args['requires_php']) ? (string)reset($args['requires_php']) : (string)$args['requires_php'];
        }
        if ( ! $this->new_version) {
            $this->new_version = $this->version;
        }
        if ( ! $this->version) {
            $this->version = $this->new_version;
        }
    }

    /**
     * @return Plugins
     */
    public function getPlugins(): Plugins
    {
        return $this->plugins;
    }

    public function isNeedUpdate(): bool
    {
        return $this->version != $this->new_version && version_compare($this->new_version, $this->version, '>');
    }

    /**
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * @return string
     */
    public function getPackage(): string
    {
        return $this->package;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getPluginUri(): string
    {
        return $this->plugin_uri;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getAuthor(): string
    {
        return $this->author;
    }

    /**
     * @return string
     */
    public function getAuthorName(): string
    {
        return $this->author_name;
    }

    /**
     * @return string
     */
    public function getAuthorUri(): string
    {
        return $this->author_uri;
    }

    /**
     * @return string
     */
    public function getTextDomain(): string
    {
        return $this->text_domain;
    }

    /**
     * @return string
     */
    public function getDomainPath(): string
    {
        return $this->domain_path;
    }

    /**
     * @return string
     */
    public function getNewVersion(): string
    {
        return $this->new_version;
    }

    /**
     * @return null
     */
    public function getNetwork()
    {
        return $this->network;
    }

    /**
     * @return mixed|string
     */
    public function getRequiresWp()
    {
        return $this->requires_wp;
    }

    /**
     * @return string
     */
    public function getRequiresPhp(): string
    {
        return $this->requires_php;
    }

    /**
     * @return string
     */
    public function getUpdateUri(): string
    {
        return $this->update_uri;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return bool
     */
    public function isExists(): bool
    {
        $plugin_root = WP_PLUGIN_DIR;
        if (substr($this->slug, -4) === '.php') {
            return file_exists("$plugin_root/{$this->slug}");
        }

        return file_exists("$plugin_root/{$this->slug}");
    }

    /**
     * @return null
     */
    public function getUpdateCallback()
    {
        return $this->update_callback;
    }

    public function get(string $name, $default = null)
    {
        return property_exists($this, $name)
            ? $this->{$name}
            : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPluginData(): array
    {
        $data = $this->plugins->getDefaultHeaders();
        $data = array_map('__return_empty_string', $data);
        foreach ($this->plugins->getDefaultHeaders() as $key => $item) {
            if ( ! is_string($key) || ! is_string($item)) {
                continue;
            }
            if (property_exists($this, $item)) {
                $data[$key] = $this->{$item};
            }
        }

        return $data;
    }
}
