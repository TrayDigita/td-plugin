<?php
namespace TrayDigita\WP\Plugin\TrayDigitaPlugin;

class PluginInfoAPI
{
    /**
     * @var string Plugin Name (Title)
     */
    public $name = '';
    /**
     * @var string Plugin slug
     */
    public $slug = '';
    /**
     * @var string Plugin Current Version
     */
    public $version = '';
    /**
     * @var string Author
     */
    public $author = '';
    /**
     * @var string URL Profile
     */
    public $author_profile = '';
    /**
     * @var array<string, array<string, array>>
     */
    public $contributors = [];
    public $requires = ''; // eg 5.6
    public $tested = ''; // eg : 5.9
    public $requires_php = ''; // eg : 7.2
    /**
     * @var int integer by 100 max
     */
    public $rating = 0; // eg : 7.2
    /**
     * @var int<int, int>
     */
    public $ratings = [
        5 => 0,
        4 => 0,
        3 => 0,
        2 => 0,
        1 => 0,
    ];
    /**
     * @var int total rating
     */
    public $num_ratings = 0;
    /**
     * @var int Threads case count
     */
    public $support_threads = 0;
    /**
     * @var int Thread case closed count
     */
    public $support_threads_resolved = 0;
    /**
     * @var int total active install
     */
    public $active_installs = 0;

    /**
     * @var int gmdate('Y-m-d H:ia \G\M\T');
     */
    public $last_updated = '';
    /**
     * @var string gmdate('Y-m-d')
     */
    public $added = '';
    /**
     * @var string Homepage URL
     */
    public $homepage = '';
    /**
     * @var array<string, string>
     */
    public $sections = [];
    public $reviews = '';
    public $download_link = '';
    public $screenshots = [];
    public $tags = [];
    public $versions = [];
    public $donate_link = [];
    public $banners = [];
    protected $data = [];

    public function __construct(array $args = [])
    {
        foreach ($this->fromArrayMetaData($args) as $key => $item) {
            if (property_exists($this, $key) && $key !== 'data') {
                $this->{$key} = $item;
                continue;
            }
            $this->data[$key] = $item;
        }
    }

    private function fromArrayMetaData(array $meta)
    {
        $obj = [];
        foreach ($meta as $key => $v) {
            if ( ! is_string($key)) {
                continue;
            }
            $obj[$key] = $v;
        }

        if (isset($meta['version']) && empty($obj->versions)) {
            $obj[$meta['version']] = $meta['package'] ?? $meta['download_link'] ?? '';
        }

        if (empty($this->download_link)) {
            if ( ! empty($meta['download_link'])) {
                $obj['download_link'] = $meta['download_link'];
            } elseif ( ! empty($meta['package'])) {
                $obj['download_link'] = $meta['package'];
            }
        }
        if ( ! isset($meta['banners'])) {
            $meta['banners'] = [];
        }
        if (empty($this->banners['low'])) {
            $obj['banners']['low'] = $meta['banners']['low'] ?? '';
        }
        if (empty($obj->banners['high'])) {
            $obj['banners']['high'] = $meta['banners']['high'] ?? '';
        }
        if (empty($obj->banners['high'])) {
            $obj['banners']['high'] = $plugin->banners['2x'] ?? ($plugin->banners['1x'] ?? '');
        }
        if (empty($obj->banners['low'])) {
            $obj['banners']['low'] = $plugin->banners['1x'] ?? ($plugin->banners['2x'] ?? '');
        }
        if (empty($obj['banners']['low'])) {
            unset($obj['banners']['low']);
        }
        if (empty($obj['banners']['high'])) {
            unset($obj['banners']['high']);
        }
        $obj['rating']                   = ! is_numeric($obj['rating'] ?? '') ? $this->rating : $obj['rating'];
        $obj['num_ratings']              = ! is_numeric($obj['num_ratings'] ?? '') ? $this->num_ratings : $obj['num_ratings'];
        $obj['support_threads_resolved'] = ! is_numeric($obj['support_threads_resolved'] ?? '') ? $this->support_threads_resolved : $obj['support_threads_resolved'];
        $obj['support_threads']          = ! is_numeric($obj['support_threads'] ?? '') ? $this->support_threads : $obj['support_threads'];
        $obj['active_installs']          = ! is_numeric($obj['active_installs'] ?? '') ? $this->active_installs : $obj['active_installs'];
        $obj['rating']                   = absint($obj['rating']);
        $obj['num_ratings']              = absint($obj['num_ratings']);
        $obj['support_threads_resolved'] = absint($obj['support_threads_resolved']);
        $obj['support_threads']          = absint($obj['support_threads']);
        $obj['active_installs']          = absint($obj['active_installs']);

        return $obj;
    }
}
