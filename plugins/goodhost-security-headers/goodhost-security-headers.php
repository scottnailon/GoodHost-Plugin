<?php
/**
 * Plugin Name: GoodHost - Security Headers
 * Plugin URI: https://goodhost.com.au
 * Description: Add essential security headers to protect your site from common attacks.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Security_Headers {
    private $options;
    
    public function __construct() {
        $this->options = get_option('goodhost_security_headers', $this->get_defaults());
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        if (!empty($this->options['enabled'])) add_action('send_headers', [$this, 'send_security_headers']);
    }
    
    public function get_defaults() { return ['enabled' => 1, 'x_frame_options' => 'SAMEORIGIN', 'x_content_type' => 1, 'x_xss_protection' => 1, 'referrer_policy' => 'strict-origin-when-cross-origin', 'permissions_policy' => 1, 'hsts' => 0, 'hsts_max_age' => 31536000, 'hsts_subdomains' => 0, 'hsts_preload' => 0]; }
    public function register_module($modules) { $modules['security-headers'] = ['name' => 'Security Headers', 'description' => 'HTTP security headers', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-security-headers')]; return $modules; }
    public function add_admin_menu() { add_submenu_page('goodhost', 'Security Headers', 'Security Headers', 'manage_options', 'goodhost-security-headers', [$this, 'settings_page']); }
    public function register_settings() { register_setting('goodhost_security_headers', 'goodhost_security_headers'); }
    
    public function send_security_headers() {
        if (is_admin()) return;
        if (!empty($this->options['x_frame_options'])) header('X-Frame-Options: ' . $this->options['x_frame_options']);
        if (!empty($this->options['x_content_type'])) header('X-Content-Type-Options: nosniff');
        if (!empty($this->options['x_xss_protection'])) header('X-XSS-Protection: 1; mode=block');
        if (!empty($this->options['referrer_policy'])) header('Referrer-Policy: ' . $this->options['referrer_policy']);
        if (!empty($this->options['permissions_policy'])) header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
        if (!empty($this->options['hsts']) && is_ssl()) {
            $hsts = 'max-age=' . intval($this->options['hsts_max_age']);
            if (!empty($this->options['hsts_subdomains'])) $hsts .= '; includeSubDomains';
            if (!empty($this->options['hsts_preload'])) $hsts .= '; preload';
            header('Strict-Transport-Security: ' . $hsts);
        }
    }
    
    public function settings_page() { ?>
        <div class="wrap"><h1>Security Headers</h1>
        <form method="post" action="options.php"><?php settings_fields('goodhost_security_headers'); ?>
        <style>.gh-card{background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px;max-width:700px}.gh-card h3{margin-top:0}.gh-info{background:#f0f0f1;padding:10px 15px;margin:10px 0;border-left:3px solid #2271b1}</style>
        <div class="gh-card"><h3>Enable</h3><label><input type="checkbox" name="goodhost_security_headers[enabled]" value="1" <?php checked(!empty($this->options['enabled'])); ?>> <strong>Enable Security Headers</strong></label></div>
        <div class="gh-card"><h3>X-Frame-Options</h3><p>Prevents clickjacking attacks.</p>
            <select name="goodhost_security_headers[x_frame_options]"><option value="" <?php selected($this->options['x_frame_options'], ''); ?>>Disabled</option><option value="SAMEORIGIN" <?php selected($this->options['x_frame_options'], 'SAMEORIGIN'); ?>>SAMEORIGIN</option><option value="DENY" <?php selected($this->options['x_frame_options'], 'DENY'); ?>>DENY</option></select>
        </div>
        <div class="gh-card"><h3>Content Security</h3>
            <label style="display:block;margin:8px 0"><input type="checkbox" name="goodhost_security_headers[x_content_type]" value="1" <?php checked(!empty($this->options['x_content_type'])); ?>> X-Content-Type-Options: nosniff</label>
            <label style="display:block;margin:8px 0"><input type="checkbox" name="goodhost_security_headers[x_xss_protection]" value="1" <?php checked(!empty($this->options['x_xss_protection'])); ?>> X-XSS-Protection: 1; mode=block</label>
            <label style="display:block;margin:8px 0"><input type="checkbox" name="goodhost_security_headers[permissions_policy]" value="1" <?php checked(!empty($this->options['permissions_policy'])); ?>> Permissions-Policy (restrictive)</label>
        </div>
        <div class="gh-card"><h3>Referrer-Policy</h3>
            <select name="goodhost_security_headers[referrer_policy]"><option value="" <?php selected($this->options['referrer_policy'], ''); ?>>Disabled</option><option value="no-referrer" <?php selected($this->options['referrer_policy'], 'no-referrer'); ?>>no-referrer</option><option value="same-origin" <?php selected($this->options['referrer_policy'], 'same-origin'); ?>>same-origin</option><option value="strict-origin" <?php selected($this->options['referrer_policy'], 'strict-origin'); ?>>strict-origin</option><option value="strict-origin-when-cross-origin" <?php selected($this->options['referrer_policy'], 'strict-origin-when-cross-origin'); ?>>strict-origin-when-cross-origin</option></select>
        </div>
        <div class="gh-card"><h3>HSTS</h3><?php if (!is_ssl()) echo '<div class="gh-info" style="border-color:#d63638">Your site is not using HTTPS. HSTS requires SSL.</div>'; ?>
            <label style="display:block;margin:8px 0"><input type="checkbox" name="goodhost_security_headers[hsts]" value="1" <?php checked(!empty($this->options['hsts'])); ?>> Enable HSTS</label>
            <p>Max Age: <select name="goodhost_security_headers[hsts_max_age]"><option value="86400" <?php selected($this->options['hsts_max_age'], 86400); ?>>1 day</option><option value="604800" <?php selected($this->options['hsts_max_age'], 604800); ?>>1 week</option><option value="2592000" <?php selected($this->options['hsts_max_age'], 2592000); ?>>1 month</option><option value="31536000" <?php selected($this->options['hsts_max_age'], 31536000); ?>>1 year</option></select></p>
            <label style="display:block;margin:8px 0"><input type="checkbox" name="goodhost_security_headers[hsts_subdomains]" value="1" <?php checked(!empty($this->options['hsts_subdomains'])); ?>> Include Subdomains</label>
            <label style="display:block;margin:8px 0"><input type="checkbox" name="goodhost_security_headers[hsts_preload]" value="1" <?php checked(!empty($this->options['hsts_preload'])); ?>> Preload</label>
        </div>
        <?php submit_button(); ?></form>
        <div class="gh-card"><h3>Test Headers</h3><ul><li><a href="https://securityheaders.com/?q=<?php echo urlencode(home_url()); ?>" target="_blank">SecurityHeaders.com</a></li><li><a href="https://observatory.mozilla.org/analyze/<?php echo parse_url(home_url(), PHP_URL_HOST); ?>" target="_blank">Mozilla Observatory</a></li></ul></div>
        </div>
    <?php }
}
new GoodHost_Security_Headers();
