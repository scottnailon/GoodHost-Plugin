<?php
/**
 * Plugin Name: GoodHost - Lazy Load
 * Plugin URI: https://goodhost.com.au
 * Description: Lazy load images, iframes, and videos to improve page load speed.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Lazy_Load {
    private $options;
    
    public function __construct() {
        $this->options = get_option('goodhost_lazy_load', $this->get_defaults());
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        if (!is_admin() && !empty($this->options['enabled'])) {
            add_filter('the_content', [$this, 'add_lazy_load'], 99);
            add_filter('post_thumbnail_html', [$this, 'add_lazy_load'], 99);
            add_filter('get_avatar', [$this, 'add_lazy_load'], 99);
            add_action('wp_head', [$this, 'add_styles']);
        }
    }
    
    public function get_defaults() {
        return ['enabled' => 1, 'images' => 1, 'iframes' => 1, 'videos' => 1, 'use_native' => 1, 'placeholder' => 'color', 'exclude_classes' => 'no-lazy, skip-lazy', 'exclude_first' => 2];
    }
    
    public function register_module($modules) {
        $modules['lazy-load'] = ['name' => 'Lazy Load', 'description' => 'Defer images/iframes', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-lazy-load')];
        return $modules;
    }
    
    public function add_admin_menu() { add_submenu_page('goodhost', 'Lazy Load', 'Lazy Load', 'manage_options', 'goodhost-lazy-load', [$this, 'settings_page']); }
    public function register_settings() { register_setting('goodhost_lazy_load', 'goodhost_lazy_load'); }
    
    public function add_styles() { echo '<style>img[loading="lazy"],iframe[loading="lazy"]{opacity:1;transition:opacity .3s}img.goodhost-lazy-placeholder{background:#f0f0f0}</style>'; }
    
    public function add_lazy_load($content) {
        if (empty($content) || is_feed()) return $content;
        $exclude_classes = array_map('trim', explode(',', $this->options['exclude_classes']));
        $exclude_first = intval($this->options['exclude_first']);
        if (!empty($this->options['images'])) $content = $this->process_images($content, $exclude_classes, $exclude_first);
        if (!empty($this->options['iframes'])) $content = $this->process_iframes($content, $exclude_classes);
        if (!empty($this->options['videos'])) $content = $this->process_videos($content, $exclude_classes);
        return $content;
    }
    
    private function process_images($content, $exclude_classes, $exclude_first) {
        static $image_count = 0;
        preg_match_all('/<img[^>]+>/i', $content, $matches);
        if (empty($matches[0])) return $content;
        foreach ($matches[0] as $img) {
            if (strpos($img, 'loading=') !== false) continue;
            $skip = false;
            foreach ($exclude_classes as $class) { if (!empty($class) && strpos($img, $class) !== false) { $skip = true; break; } }
            if ($skip) continue;
            $image_count++;
            if ($image_count <= $exclude_first) continue;
            $new_img = str_replace('<img', '<img loading="lazy"', $img);
            if ($this->options['placeholder'] === 'color') {
                $new_img = strpos($new_img, 'class=') !== false ? preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 goodhost-lazy-placeholder"', $new_img) : str_replace('<img', '<img class="goodhost-lazy-placeholder"', $new_img);
            }
            $content = str_replace($img, $new_img, $content);
        }
        return $content;
    }
    
    private function process_iframes($content, $exclude_classes) {
        preg_match_all('/<iframe[^>]+>/i', $content, $matches);
        if (empty($matches[0])) return $content;
        foreach ($matches[0] as $iframe) {
            if (strpos($iframe, 'loading=') !== false) continue;
            $skip = false;
            foreach ($exclude_classes as $class) { if (!empty($class) && strpos($iframe, $class) !== false) { $skip = true; break; } }
            if ($skip) continue;
            $content = str_replace($iframe, str_replace('<iframe', '<iframe loading="lazy"', $iframe), $content);
        }
        return $content;
    }
    
    private function process_videos($content, $exclude_classes) {
        preg_match_all('/<video[^>]+>/i', $content, $matches);
        if (empty($matches[0])) return $content;
        foreach ($matches[0] as $video) {
            if (strpos($video, 'preload="none"') !== false) continue;
            $skip = false;
            foreach ($exclude_classes as $class) { if (!empty($class) && strpos($video, $class) !== false) { $skip = true; break; } }
            if ($skip) continue;
            $new_video = strpos($video, 'preload=') !== false ? preg_replace('/preload=["\'][^"\']*["\']/', 'preload="none"', $video) : str_replace('<video', '<video preload="none"', $video);
            $content = str_replace($video, $new_video, $content);
        }
        return $content;
    }
    
    public function settings_page() { ?>
        <div class="wrap"><h1>Lazy Load</h1>
        <form method="post" action="options.php" style="max-width:600px"><?php settings_fields('goodhost_lazy_load'); ?>
        <style>.gh-card{background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px}.gh-card h3{margin-top:0}.gh-card label{display:block;margin:10px 0}</style>
        <div class="gh-card"><h3>Enable</h3><label><input type="checkbox" name="goodhost_lazy_load[enabled]" value="1" <?php checked(!empty($this->options['enabled'])); ?>> <strong>Enable Lazy Load</strong></label></div>
        <div class="gh-card"><h3>What to Lazy Load</h3>
            <label><input type="checkbox" name="goodhost_lazy_load[images]" value="1" <?php checked(!empty($this->options['images'])); ?>> Images</label>
            <label><input type="checkbox" name="goodhost_lazy_load[iframes]" value="1" <?php checked(!empty($this->options['iframes'])); ?>> Iframes</label>
            <label><input type="checkbox" name="goodhost_lazy_load[videos]" value="1" <?php checked(!empty($this->options['videos'])); ?>> Videos</label>
        </div>
        <div class="gh-card"><h3>Options</h3>
            <p><label>Skip First N Images: <input type="number" name="goodhost_lazy_load[exclude_first]" value="<?php echo esc_attr($this->options['exclude_first']); ?>" min="0" max="10" style="width:60px"></label><br><small>Don't lazy load above-the-fold images</small></p>
            <p><label>Exclude Classes: <input type="text" name="goodhost_lazy_load[exclude_classes]" value="<?php echo esc_attr($this->options['exclude_classes']); ?>" class="regular-text"></label><br><small>Comma-separated CSS classes to exclude</small></p>
            <p><label>Placeholder: <select name="goodhost_lazy_load[placeholder]"><option value="color" <?php selected($this->options['placeholder'], 'color'); ?>>Gray background</option><option value="none" <?php selected($this->options['placeholder'], 'none'); ?>>None</option></select></label></p>
        </div>
        <?php submit_button(); ?></form></div>
    <?php }
}
new GoodHost_Lazy_Load();
