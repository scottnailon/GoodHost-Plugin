<?php
/**
 * Plugin Name: GoodHost - Login Protection
 * Plugin URI: https://goodhost.com.au
 * Description: Protect your login page with brute force prevention, login limits, and lockouts.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Login_Protection {
    private $options, $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'goodhost_login_attempts';
        $this->options = get_option('goodhost_login_protection', $this->get_defaults());
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        if (!empty($this->options['enabled'])) {
            add_filter('authenticate', [$this, 'check_attempts'], 30, 3);
            add_action('wp_login_failed', [$this, 'log_failed_attempt']);
            add_action('wp_login', [$this, 'clear_attempts'], 10, 2);
            add_filter('login_errors', [$this, 'mask_login_errors']);
        }
        add_action('wp_ajax_goodhost_unlock_ip', [$this, 'ajax_unlock_ip']);
        add_action('wp_ajax_goodhost_clear_lockouts', [$this, 'ajax_clear_lockouts']);
    }
    
    public function get_defaults() { return ['enabled' => 1, 'max_attempts' => 5, 'lockout_duration' => 30, 'lockout_multiplier' => 2, 'max_lockout' => 1440, 'mask_errors' => 1]; }
    
    public function activate() {
        global $wpdb;
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->table_name} (id bigint(20) AUTO_INCREMENT, ip_address varchar(45) NOT NULL, username varchar(100) DEFAULT '', attempts int(11) DEFAULT 1, lockout_count int(11) DEFAULT 0, locked_until datetime DEFAULT NULL, last_attempt datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY ip_address (ip_address)) " . $wpdb->get_charset_collate());
    }
    
    public function register_module($modules) { $modules['login-protection'] = ['name' => 'Login Protection', 'description' => 'Brute force prevention', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-login-protection')]; return $modules; }
    public function add_admin_menu() { add_submenu_page('goodhost', 'Login Protection', 'Login Protection', 'manage_options', 'goodhost-login-protection', [$this, 'settings_page']); }
    public function register_settings() { register_setting('goodhost_login_protection', 'goodhost_login_protection'); }
    
    private function get_ip() {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) { $ip = explode(',', $_SERVER[$key]); return trim($ip[0]); }
        }
        return '0.0.0.0';
    }
    
    private function get_record($ip) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE ip_address = %s", $ip)); }
    private function is_locked($record) { return $record && $record->locked_until && strtotime($record->locked_until) > time(); }
    
    public function check_attempts($user, $username, $password) {
        if (empty($username)) return $user;
        $record = $this->get_record($this->get_ip());
        if ($this->is_locked($record)) {
            $minutes = ceil((strtotime($record->locked_until) - time()) / 60);
            return new WP_Error('goodhost_locked', sprintf('Too many failed login attempts. Try again in %d %s.', $minutes, $minutes === 1 ? 'minute' : 'minutes'));
        }
        return $user;
    }
    
    public function log_failed_attempt($username) {
        global $wpdb;
        $ip = $this->get_ip();
        $record = $this->get_record($ip);
        $max = intval($this->options['max_attempts']);
        $duration = intval($this->options['lockout_duration']);
        $mult = floatval($this->options['lockout_multiplier']);
        $max_lockout = intval($this->options['max_lockout']);
        if ($record) {
            $attempts = $record->attempts + 1;
            $lockout_count = $record->lockout_count;
            $locked_until = null;
            if ($attempts >= $max) {
                $lockout_count++;
                $dur = min($duration * pow($mult, $lockout_count - 1), $max_lockout);
                $locked_until = date('Y-m-d H:i:s', time() + ($dur * 60));
                $attempts = 0;
            }
            $wpdb->update($this->table_name, ['username' => $username, 'attempts' => $attempts, 'lockout_count' => $lockout_count, 'locked_until' => $locked_until, 'last_attempt' => current_time('mysql')], ['ip_address' => $ip]);
        } else {
            $wpdb->insert($this->table_name, ['ip_address' => $ip, 'username' => $username, 'attempts' => 1, 'lockout_count' => 0, 'last_attempt' => current_time('mysql')]);
        }
    }
    
    public function clear_attempts($user_login, $user) { global $wpdb; $wpdb->update($this->table_name, ['attempts' => 0, 'locked_until' => null], ['ip_address' => $this->get_ip()]); }
    public function mask_login_errors($error) { return (empty($this->options['mask_errors']) || strpos($error, 'Too many failed') !== false) ? $error : '<strong>Error:</strong> Invalid username or password.'; }
    
    public function ajax_unlock_ip() {
        check_ajax_referer('goodhost_login_protection', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $wpdb->update($this->table_name, ['attempts' => 0, 'locked_until' => null], ['ip_address' => sanitize_text_field($_POST['ip'])]);
        wp_send_json_success();
    }
    
    public function ajax_clear_lockouts() {
        check_ajax_referer('goodhost_login_protection', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->table_name}");
        wp_send_json_success();
    }
    
    public function settings_page() {
        global $wpdb;
        $lockouts = $wpdb->get_results("SELECT * FROM {$this->table_name} WHERE locked_until IS NOT NULL AND locked_until > NOW() ORDER BY locked_until DESC LIMIT 50");
        $stats = ['total' => $wpdb->get_var("SELECT SUM(lockout_count) FROM {$this->table_name}"), 'active' => count($lockouts), 'ips' => $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM {$this->table_name}")];
        ?>
        <div class="wrap"><h1>Login Protection</h1>
        <style>.gh-stats{display:flex;gap:20px;margin:20px 0}.gh-stat{background:#fff;border:1px solid #ccd0d4;padding:15px 25px;text-align:center}.gh-stat .num{font-size:2em;font-weight:bold}.gh-stat.warn .num{color:#d63638}.gh-card{background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px;max-width:700px}.gh-card h3{margin-top:0}</style>
        <div class="gh-stats">
            <div class="gh-stat <?php echo $stats['active'] > 0 ? 'warn' : ''; ?>"><div class="num"><?php echo intval($stats['active']); ?></div><div>Active Lockouts</div></div>
            <div class="gh-stat"><div class="num"><?php echo intval($stats['total']); ?></div><div>Total Lockouts</div></div>
            <div class="gh-stat"><div class="num"><?php echo intval($stats['ips']); ?></div><div>Unique IPs</div></div>
        </div>
        <form method="post" action="options.php"><?php settings_fields('goodhost_login_protection'); ?>
        <div class="gh-card"><h3>Enable</h3><label><input type="checkbox" name="goodhost_login_protection[enabled]" value="1" <?php checked(!empty($this->options['enabled'])); ?>> <strong>Enable Login Protection</strong></label></div>
        <div class="gh-card"><h3>Lockout Settings</h3>
            <p>Max Attempts: <input type="number" name="goodhost_login_protection[max_attempts]" value="<?php echo esc_attr($this->options['max_attempts']); ?>" min="1" max="20" style="width:60px"> before lockout</p>
            <p>Lockout Duration: <input type="number" name="goodhost_login_protection[lockout_duration]" value="<?php echo esc_attr($this->options['lockout_duration']); ?>" min="1" max="1440" style="width:60px"> minutes</p>
            <p>Multiplier: <input type="number" name="goodhost_login_protection[lockout_multiplier]" value="<?php echo esc_attr($this->options['lockout_multiplier']); ?>" min="1" max="10" step="0.5" style="width:60px">x for each lockout</p>
            <p>Max Lockout: <input type="number" name="goodhost_login_protection[max_lockout]" value="<?php echo esc_attr($this->options['max_lockout']); ?>" min="60" max="10080" style="width:80px"> minutes</p>
        </div>
        <div class="gh-card"><h3>Security</h3><label><input type="checkbox" name="goodhost_login_protection[mask_errors]" value="1" <?php checked(!empty($this->options['mask_errors'])); ?>> Mask login errors</label></div>
        <?php submit_button(); ?></form>
        <?php if (!empty($lockouts)) : ?>
        <div class="gh-card" style="max-width:900px"><h3>Currently Locked Out</h3>
        <table class="wp-list-table widefat fixed striped"><thead><tr><th>IP</th><th>Username</th><th>Until</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($lockouts as $l) : ?>
            <tr id="lockout-<?php echo esc_attr($l->ip_address); ?>"><td><code><?php echo esc_html($l->ip_address); ?></code></td><td><?php echo esc_html($l->username); ?></td><td><?php echo esc_html($l->locked_until); ?></td><td><button class="button button-small" onclick="unlockIP('<?php echo esc_js($l->ip_address); ?>')">Unlock</button></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <p style="margin-top:15px"><button class="button" onclick="clearAllLockouts()">Clear All</button></p></div>
        <script>
        function unlockIP(ip){if(!confirm('Unlock?'))return;fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_unlock_ip&ip='+encodeURIComponent(ip)+'&_wpnonce=<?php echo wp_create_nonce('goodhost_login_protection'); ?>'}).then(()=>location.reload());}
        function clearAllLockouts(){if(!confirm('Clear all?'))return;fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_clear_lockouts&_wpnonce=<?php echo wp_create_nonce('goodhost_login_protection'); ?>'}).then(()=>location.reload());}
        </script>
        <?php endif; ?>
        </div>
    <?php }
}
new GoodHost_Login_Protection();
