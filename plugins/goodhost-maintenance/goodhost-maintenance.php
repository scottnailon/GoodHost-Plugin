<?php
/**
 * Plugin Name: GoodHost - Maintenance Mode
 * Plugin URI: https://goodhost.com.au
 * Description: Put your site in maintenance mode with a customizable coming soon or under construction page.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Maintenance_Mode {
    private $options;
    
    public function __construct() {
        $this->options = get_option('goodhost_maintenance', $this->get_defaults());
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        add_action('admin_bar_menu', [$this, 'admin_bar_indicator'], 100);
        if (!empty($this->options['enabled'])) add_action('template_redirect', [$this, 'show_maintenance_page'], 1);
    }
    
    public function get_defaults() { return ['enabled' => 0, 'mode' => 'maintenance', 'title' => 'Under Maintenance', 'headline' => "We'll Be Back Soon!", 'message' => "Our website is currently undergoing scheduled maintenance. We'll be back shortly.", 'background_color' => '#1e3a5f', 'text_color' => '#ffffff', 'accent_color' => '#4a9eff', 'show_logo' => 1, 'logo_url' => '', 'show_countdown' => 0, 'countdown_date' => '', 'allow_logged_in' => 1, 'allowed_ips' => '', 'response_code' => 503]; }
    public function register_module($modules) { $s = !empty($this->options['enabled']) ? 'Active' : 'Inactive'; $modules['maintenance-mode'] = ['name' => 'Maintenance Mode', 'description' => $s, 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-maintenance')]; return $modules; }
    public function add_admin_menu() { add_submenu_page('goodhost', 'Maintenance Mode', 'Maintenance Mode', 'manage_options', 'goodhost-maintenance', [$this, 'settings_page']); }
    public function register_settings() { register_setting('goodhost_maintenance', 'goodhost_maintenance', ['sanitize_callback' => [$this, 'sanitize_options']]); }
    
    public function sanitize_options($input) {
        return ['enabled' => isset($input['enabled']) ? 1 : 0, 'mode' => in_array($input['mode'] ?? '', ['maintenance', 'coming_soon']) ? $input['mode'] : 'maintenance', 'title' => sanitize_text_field($input['title'] ?? ''), 'headline' => sanitize_text_field($input['headline'] ?? ''), 'message' => wp_kses_post($input['message'] ?? ''), 'background_color' => sanitize_hex_color($input['background_color'] ?? '#1e3a5f'), 'text_color' => sanitize_hex_color($input['text_color'] ?? '#ffffff'), 'accent_color' => sanitize_hex_color($input['accent_color'] ?? '#4a9eff'), 'show_logo' => isset($input['show_logo']) ? 1 : 0, 'logo_url' => esc_url_raw($input['logo_url'] ?? ''), 'show_countdown' => isset($input['show_countdown']) ? 1 : 0, 'countdown_date' => sanitize_text_field($input['countdown_date'] ?? ''), 'allow_logged_in' => isset($input['allow_logged_in']) ? 1 : 0, 'allowed_ips' => sanitize_textarea_field($input['allowed_ips'] ?? ''), 'response_code' => in_array(intval($input['response_code'] ?? 503), [200, 503]) ? intval($input['response_code']) : 503];
    }
    
    public function admin_bar_indicator($wp_admin_bar) {
        if (!empty($this->options['enabled']) && current_user_can('manage_options')) {
            $wp_admin_bar->add_node(['id' => 'goodhost-maintenance', 'title' => 'Maintenance Mode ON', 'href' => admin_url('admin.php?page=goodhost-maintenance'), 'meta' => ['class' => 'goodhost-maintenance-indicator']]);
        }
    }
    
    private function is_allowed() {
        if (!empty($this->options['allow_logged_in']) && is_user_logged_in()) return true;
        if (!empty($this->options['allowed_ips'])) {
            $allowed = array_map('trim', explode("\n", $this->options['allowed_ips']));
            $ip = $this->get_ip();
            if (in_array($ip, $allowed)) return true;
        }
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false || is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) return true;
        return false;
    }
    
    private function get_ip() { foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) { if (!empty($_SERVER[$key])) { $ip = explode(',', $_SERVER[$key]); return trim($ip[0]); } } return '0.0.0.0'; }
    
    public function show_maintenance_page() {
        if ($this->is_allowed()) return;
        if ($this->options['response_code'] === 503) { header('HTTP/1.1 503 Service Temporarily Unavailable'); header('Retry-After: 3600'); }
        $o = $this->options;
        $logo = $o['logo_url'] ?: (($id = get_theme_mod('custom_logo')) ? wp_get_attachment_image_url($id, 'medium') : '');
        ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php echo esc_html($o['title']); ?></title>
<style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:<?php echo esc_attr($o['background_color']); ?>;color:<?php echo esc_attr($o['text_color']); ?>;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:20px}.container{max-width:600px}.logo{margin-bottom:40px}.logo img{max-width:200px;max-height:80px}h1{font-size:2.5em;margin-bottom:20px}.message{font-size:1.2em;line-height:1.6;opacity:.9;margin-bottom:40px}.countdown{display:flex;justify-content:center;gap:20px;margin-bottom:40px}.cd-item{background:rgba(255,255,255,.1);padding:20px;border-radius:10px;min-width:80px}.cd-item .num{font-size:2.5em;font-weight:bold;color:<?php echo esc_attr($o['accent_color']); ?>}.cd-item .lbl{font-size:.8em;text-transform:uppercase;opacity:.7}.icon{font-size:4em;margin-bottom:20px}</style></head>
<body><div class="container">
<?php if ($o['show_logo'] && $logo) : ?><div class="logo"><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>"></div><?php else : ?><div class="icon">&#128295;</div><?php endif; ?>
<h1><?php echo esc_html($o['headline']); ?></h1>
<div class="message"><?php echo wp_kses_post($o['message']); ?></div>
<?php if ($o['show_countdown'] && $o['countdown_date']) : ?>
<div class="countdown" id="countdown"><div class="cd-item"><div class="num" id="days">00</div><div class="lbl">Days</div></div><div class="cd-item"><div class="num" id="hours">00</div><div class="lbl">Hours</div></div><div class="cd-item"><div class="num" id="minutes">00</div><div class="lbl">Minutes</div></div><div class="cd-item"><div class="num" id="seconds">00</div><div class="lbl">Seconds</div></div></div>
<script>var cd=new Date("<?php echo esc_js($o['countdown_date']); ?>").getTime();var x=setInterval(function(){var n=new Date().getTime();var d=cd-n;if(d<0){clearInterval(x);document.getElementById("countdown").innerHTML="<p>We're back!</p>";return;}document.getElementById("days").textContent=Math.floor(d/(1000*60*60*24));document.getElementById("hours").textContent=Math.floor((d%(1000*60*60*24))/(1000*60*60));document.getElementById("minutes").textContent=Math.floor((d%(1000*60*60))/(1000*60));document.getElementById("seconds").textContent=Math.floor((d%(1000*60))/1000);},1000);</script>
<?php endif; ?>
</div></body></html>
        <?php exit;
    }
    
    public function settings_page() { ?>
        <div class="wrap"><h1>Maintenance Mode</h1>
        <?php if (!empty($this->options['enabled'])) : ?><div style="background:#d63638;color:#fff;padding:15px;margin:20px 0;max-width:700px"><strong>Maintenance Mode is ACTIVE</strong> - Visitors see the maintenance page.</div><?php endif; ?>
        <form method="post" action="options.php" style="max-width:700px"><?php settings_fields('goodhost_maintenance'); ?>
        <style>.gh-card{background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px}.gh-card h3{margin-top:0}.gh-color{display:flex;align-items:center;gap:10px}.gh-color input[type="color"]{width:50px;height:35px;border:1px solid #ccc;cursor:pointer}</style>
        <div class="gh-card"><h3>Enable</h3><label style="font-size:1.2em"><input type="checkbox" name="goodhost_maintenance[enabled]" value="1" <?php checked(!empty($this->options['enabled'])); ?> style="width:20px;height:20px"> <strong>Enable Maintenance Mode</strong></label></div>
        <div class="gh-card"><h3>Content</h3>
            <p><label>Mode: <select name="goodhost_maintenance[mode]"><option value="maintenance" <?php selected($this->options['mode'], 'maintenance'); ?>>Maintenance (503)</option><option value="coming_soon" <?php selected($this->options['mode'], 'coming_soon'); ?>>Coming Soon (200)</option></select></label></p>
            <p><label>Title: <input type="text" name="goodhost_maintenance[title]" value="<?php echo esc_attr($this->options['title']); ?>" class="regular-text"></label></p>
            <p><label>Headline: <input type="text" name="goodhost_maintenance[headline]" value="<?php echo esc_attr($this->options['headline']); ?>" class="regular-text"></label></p>
            <p><label>Message:<br><textarea name="goodhost_maintenance[message]" rows="4" class="large-text"><?php echo esc_textarea($this->options['message']); ?></textarea></label></p>
        </div>
        <div class="gh-card"><h3>Design</h3>
            <p class="gh-color">Background: <input type="color" name="goodhost_maintenance[background_color]" value="<?php echo esc_attr($this->options['background_color']); ?>"></p>
            <p class="gh-color">Text: <input type="color" name="goodhost_maintenance[text_color]" value="<?php echo esc_attr($this->options['text_color']); ?>"></p>
            <p class="gh-color">Accent: <input type="color" name="goodhost_maintenance[accent_color]" value="<?php echo esc_attr($this->options['accent_color']); ?>"></p>
            <p><label><input type="checkbox" name="goodhost_maintenance[show_logo]" value="1" <?php checked(!empty($this->options['show_logo'])); ?>> Show logo</label></p>
            <p><input type="url" name="goodhost_maintenance[logo_url]" value="<?php echo esc_url($this->options['logo_url']); ?>" class="regular-text" placeholder="Leave blank to use site logo"></p>
        </div>
        <div class="gh-card"><h3>Countdown</h3>
            <p><label><input type="checkbox" name="goodhost_maintenance[show_countdown]" value="1" <?php checked(!empty($this->options['show_countdown'])); ?>> Show countdown</label></p>
            <p><label>End Date: <input type="datetime-local" name="goodhost_maintenance[countdown_date]" value="<?php echo esc_attr($this->options['countdown_date']); ?>"></label></p>
        </div>
        <div class="gh-card"><h3>Access</h3>
            <p><label><input type="checkbox" name="goodhost_maintenance[allow_logged_in]" value="1" <?php checked(!empty($this->options['allow_logged_in'])); ?>> Allow logged-in users to view site</label></p>
            <p><label>Allowed IPs (one per line):<br><textarea name="goodhost_maintenance[allowed_ips]" rows="4" class="regular-text"><?php echo esc_textarea($this->options['allowed_ips']); ?></textarea></label><br><small>Your IP: <code><?php echo esc_html($this->get_ip()); ?></code></small></p>
        </div>
        <?php submit_button(); ?></form></div>
    <?php }
}
new GoodHost_Maintenance_Mode();
