<?php
namespace TrayDigita\WP\Plugin\TrayDigitaPlugin;

/*
 [id] => w.org/plugins/jetpack
 [slug] => jetpack
 [plugin] => jetpack/jetpack.php
 [new_version] => 10.6
 [url] => https://wordpress.org/plugins/jetpack/
 [package] => https://downloads.wordpress.org/plugin/jetpack.10.6.zip
 [icons] => Array
    (
        [2x] => https://ps.w.org/jetpack/assets/icon-256x256.png?rev=2638128
        [1x] => https://ps.w.org/jetpack/assets/icon.svg?rev=2638128
        [svg] => https://ps.w.org/jetpack/assets/icon.svg?rev=2638128
    )

 [banners] => Array
    (
        [2x] => https://ps.w.org/jetpack/assets/banner-1544x500.png?rev=2653649
        [1x] => https://ps.w.org/jetpack/assets/banner-772x250.png?rev=2653649
    )

 [banners_rtl] => Array
    (
    )

 [requires] => 5.8
 [compatibility] => Array
    (
    )
 */

class PluginUpdateAPI
{
    public $id = '';
    public $slug = '';
    public $plugin = '';
    public $new_version = '';
    public $url = '';
    public $package = '';
    public $icons = [];
    public $banners = [];
    public $banners_rtl = [];
    public $requires = [];
    public $tested = '';
    public $requires_php = '';
    public $compatibility = '';
    public $translations = [];
    protected $data = [];

    /**
     * @param array $args
     */
    public function __construct(array $args = [])
    {
        foreach ($args as $key => $item) {
            if ( ! property_exists($this, $key)) {
                $this->data[$key] = $item;
                continue;
            }
            if ($key === 'translations') {
                $item = $this->normalizeTranslations($item);
            }
            $this->$key = $item;
        }
        if ( ! is_array($this->banners)) {
            if ( ! is_string($this->banners)) {
                $this->banners = [];
            } else {
                $k             = strtolower(substr($this->banners, -4)) === 'svg' ? 'svg' : '1x';
                $this->banners = [$k => $this->banners];
            }
        }
        if ( ! is_array($this->banners_rtl)) {
            if ( ! is_string($this->banners_rtl)) {
                $this->banners_rtl = [];
            } else {
                $k                 = strtolower(substr($this->banners_rtl, -4)) === 'svg' ? 'svg' : '1x';
                $this->banners_rtl = [$k => $this->banners_rtl];
            }
        }
        if ( ! is_array($this->icons)) {
            if ( ! is_string($this->icons)) {
                $this->icons = [];
            } else {
                $k             = strtolower(substr($this->icons, -4)) === 'svg' ? 'svg' : '1x';
                $this->banners = [$k => $this->banners];
            }
        }
        $this->plugin = $this->plugin ?: $this->slug;
        $this->slug   = $this->slug ?: $this->plugin;
    }

    /**
     * @param array $translation
     *
     * @return array
     */
    public function normalizeTranslations(array $translation): array
    {
        if ( ! is_array($translation)) {
            return [];
        }

        $default = [
            'type'       => 'plugin',
            'slug'       => $this->slug,
            'language'   => '',
            'version'    => '',
            'updated'    => '', //'2022-02-02 09:54:07',
            'package'    => '', //https://downloads.wordpress.org/translation/plugin/jetpack/10.6/id_ID.zip',
            'autoupdate' => false,
        ];
        // when it was single translation
        if ( ! is_array(reset($translation)) && ! is_array(next($translation))) {
            $translation = [$translation];
        }
        $trans = [];
        foreach ($translation as $translations) {
            if ( ! is_array($translations)) {
                continue;
            }
            $data = array_merge($default, $translations);
            if (empty($data['language']) || empty($data['package'])
                || ! is_string($data['language'])
                || ! is_string($data['package'])
            ) {
                continue;
            }
            if ( ! is_string($data['type']) || trim($data['type']) === '') {
                $data['type'] = 'plugin';
            }
            if ( ! is_string($data['slug']) || trim($data['slug']) === '') {
                $data['slug'] = $this->slug;
            }
            if (empty($data['slug'])) {
                continue;
            }
            if (is_string($data['updated'])) {
                $time            = @strtotime($data['updated']);
                $data['updated'] = is_int($time) ? $time : null;
            }
            if (is_numeric($data['updated'])) {
                $data['updated'] = absint($data['updated']);
                $data['updated'] = date('Y-m-d H:i:s', $data['updated']);
            } else {
                $data['updated'] = '';
            }
            $trans[] = $translations;
        }

        return $trans;
    }
}
