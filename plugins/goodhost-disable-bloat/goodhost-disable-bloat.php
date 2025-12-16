<?php
/**
 * Plugin Name: GoodHost - Disable Bloat
 * Plugin URI: https://goodhost.com.au
 * Description: Remove WordPress bloat for better performance. Disable emojis, embeds, dashicons, RSS feeds, and more.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Disable_Bloat {
    private $options;
    
    public function __construct() {
        $this->options = get_option('goodhost_disable_bloat', $this->get_defaults());
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        add_action('init', [$this, 'apply_settings'], 1);
        add_action('wp_enqueue_scripts', [$this, 'dequeue_scripts'], 999);
    }
    
    public function get_defaults() {
        return ['disable_emojis' => 1, 'disable_embeds' => 1, 'disable_dashicons' => 1, 'disable_jquery_migrate' => 1, 'disable_xmlrpc' => 1, 'disable_self_pingback' => 1, 'remove_query_strings' => 1, 'remove_shortlink' => 1, 'remove_rsd_link' => 1, 'remove_wlwmanifest' => 1, 'remove_wp_version' => 1, 'disable_rss' => 0, 'disable_rest_api' => 0, 'disable_heartbeat' => 0, 'heartbeat_frequency' => 60, 'disable_comments' => 0, 'remove_feed_links' => 0, 'disable_gutenberg' => 0, 'limit_revisions' => 0, 'revision_count' => 5];
    }
    
    public function register_module($modules) {
        $modules['disable-bloat'] = ['name' => 'Disable Bloat', 'description' => 'Remove WordPress bloat for performance', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-disable-bloat')];
        return $modules;
    }
    
    public function add_admin_menu() {
        add_submenu_page('goodhost', 'Disable Bloat', 'Disable Bloat', 'manage_options', 'goodhost-disable-bloat', [$this, 'settings_page']);
    }
    
    public function register_settings() {
        register_setting('goodhost_disable_bloat', 'goodhost_disable_bloat', ['sanitize_callback' => [$this, 'sanitize_options']]);
    }
    
    public function sanitize_options($input) {
        $sanitized = [];
        foreach ($this->get_defaults() as $key => $default) {
            $sanitized[$key] = ($key === 'heartbeat_frequency' || $key === 'revision_count') ? intval($input[$key] ?? $default) : (isset($input[$key]) ? 1 : 0);
        }
        return $sanitized;
    }
    
    public function apply_settings() {
        if (!empty($this->options['disable_emojis'])) {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('admin_print_scripts', 'print_emoji_detection_script');
            remove_action('wp_print_styles', 'print_emoji_styles');
            remove_action('admin_print_styles', 'print_emoji_styles');
            add_filter('emoji_svg_url', '__return_false');
        }
        if (!empty($this->options['disable_embeds'])) {
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            remove_action('wp_head', 'wp_oembed_add_host_js');
            add_filter('embed_oembed_discover', '__return_false');
        }
        if (!empty($this->options['disable_xmlrpc'])) {
            add_filter('xmlrpc_enabled', '__return_false');
            remove_action('wp_head', 'rsd_link');
        }
        if (!empty($this->options['remove_shortlink'])) remove_action('wp_head', 'wp_shortlink_wp_head', 10);
        if (!empty($this->options['remove_rsd_link'])) remove_action('wp_head', 'rsd_link');
        if (!empty($this->options['remove_wlwmanifest'])) remove_action('wp_head', 'wlwmanifest_link');
        if (!empty($this->options['remove_wp_version'])) { remove_action('wp_head', 'wp_generator'); add_filter('the_generator', '__return_empty_string'); }
        if (!empty($this->options['remove_feed_links'])) { remove_action('wp_head', 'feed_links', 2); remove_action('wp_head', 'feed_links_extra', 3); }
        if (!empty($this->options['disable_gutenberg'])) { add_filter('use_block_editor_for_post', '__return_false', 10); add_filter('use_block_editor_for_post_type', '__return_false', 10); }
        if (!empty($this->options['remove_query_strings'])) { add_filter('script_loader_src', [$this, 'remove_query_strings'], 15); add_filter('style_loader_src', [$this, 'remove_query_strings'], 15); }
        if (!empty($this->options['disable_self_pingback'])) add_action('pre_ping', [$this, 'disable_self_pingback']);
        if (!empty($this->options['disable_heartbeat'])) add_action('init', function() { wp_deregister_script('heartbeat'); }, 1);
        if (!empty($this->options['disable_rest_api'])) add_filter('rest_authentication_errors', function($result) { return is_user_logged_in() ? $result : new WP_Error('rest_disabled', 'REST API restricted.', ['status' => 401]); });
        if (!empty($this->options['disable_rss'])) { add_action('do_feed', [$this, 'disable_feed'], 1); add_action('do_feed_rss2', [$this, 'disable_feed'], 1); }
        if (!empty($this->options['disable_comments'])) { add_filter('comments_open', '__return_false', 20); add_filter('pings_open', '__return_false', 20); }
        if (!empty($this->options['limit_revisions'])) add_filter('wp_revisions_to_keep', function() { return intval($this->options['revision_count']); });
    }
    
    public function dequeue_scripts() {
        if (!empty($this->options['disable_dashicons']) && !is_user_logged_in()) wp_deregister_style('dashicons');
        if (!empty($this->options['disable_jquery_migrate']) && !is_admin()) { wp_deregister_script('jquery'); wp_register_script('jquery', includes_url('/js/jquery/jquery.min.js'), [], null, true); }
        if (!empty($this->options['disable_embeds'])) wp_deregister_script('wp-embed');
    }
    
    public function disable_self_pingback(&$links) { $home = get_option('home'); foreach ($links as $l => $link) { if (strpos($link, $home) === 0) unset($links[$l]); } }
    public function remove_query_strings($src) { return strpos($src, '?ver=') !== false ? remove_query_arg('ver', $src) : $src; }
    public function disable_feed() { wp_die(__('RSS feeds are disabled.', 'goodhost'), '', ['response' => 403]); }
    
    public function settings_page() { ?>
        <div class="wrap"><h1>Disable Bloat</h1>
        <form method="post" action="options.php"><?php settings_fields('goodhost_disable_bloat'); ?>
        <style>.gh-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;max-width:1000px}.gh-card{background:#fff;border:1px solid #ccd0d4;padding:20px}.gh-card h3{margin-top:0}.gh-card label{display:block;margin:8px 0;cursor:pointer}</style>
        <div class="gh-grid">
            <div class="gh-card"><h3>Frontend</h3>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_emojis]" value="1" <?php checked(!empty($this->options['disable_emojis'])); ?>> Disable Emojis</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_embeds]" value="1" <?php checked(!empty($this->options['disable_embeds'])); ?>> Disable Embeds</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_dashicons]" value="1" <?php checked(!empty($this->options['disable_dashicons'])); ?>> Disable Dashicons (Frontend)</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_jquery_migrate]" value="1" <?php checked(!empty($this->options['disable_jquery_migrate'])); ?>> Disable jQuery Migrate</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[remove_query_strings]" value="1" <?php checked(!empty($this->options['remove_query_strings'])); ?>> Remove Query Strings</label>
            </div>
            <div class="gh-card"><h3>Header</h3>
                <label><input type="checkbox" name="goodhost_disable_bloat[remove_wp_version]" value="1" <?php checked(!empty($this->options['remove_wp_version'])); ?>> Remove WP Version</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[remove_shortlink]" value="1" <?php checked(!empty($this->options['remove_shortlink'])); ?>> Remove Shortlink</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[remove_rsd_link]" value="1" <?php checked(!empty($this->options['remove_rsd_link'])); ?>> Remove RSD Link</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[remove_wlwmanifest]" value="1" <?php checked(!empty($this->options['remove_wlwmanifest'])); ?>> Remove WLW Manifest</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[remove_feed_links]" value="1" <?php checked(!empty($this->options['remove_feed_links'])); ?>> Remove Feed Links</label>
            </div>
            <div class="gh-card"><h3>Security</h3>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_xmlrpc]" value="1" <?php checked(!empty($this->options['disable_xmlrpc'])); ?>> Disable XML-RPC</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_rest_api]" value="1" <?php checked(!empty($this->options['disable_rest_api'])); ?>> Restrict REST API</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_self_pingback]" value="1" <?php checked(!empty($this->options['disable_self_pingback'])); ?>> Disable Self Pingbacks</label>
            </div>
            <div class="gh-card"><h3>Features</h3>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_rss]" value="1" <?php checked(!empty($this->options['disable_rss'])); ?>> Disable RSS Feeds</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_comments]" value="1" <?php checked(!empty($this->options['disable_comments'])); ?>> Disable Comments</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_gutenberg]" value="1" <?php checked(!empty($this->options['disable_gutenberg'])); ?>> Disable Gutenberg</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[disable_heartbeat]" value="1" <?php checked(!empty($this->options['disable_heartbeat'])); ?>> Disable Heartbeat</label>
                <label><input type="checkbox" name="goodhost_disable_bloat[limit_revisions]" value="1" <?php checked(!empty($this->options['limit_revisions'])); ?>> Limit Revisions to <input type="number" name="goodhost_disable_bloat[revision_count]" value="<?php echo esc_attr($this->options['revision_count']); ?>" min="0" max="100" style="width:60px"></label>
            </div>
        </div>
        <?php submit_button(); ?></form></div>
    <?php }
}
new GoodHost_Disable_Bloat();
