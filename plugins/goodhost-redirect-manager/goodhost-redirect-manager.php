<?php
/**
 * Plugin Name: GoodHost - Redirect Manager
 * Plugin URI: https://goodhost.com.au
 * Description: Simple 301/302 redirect manager with 404 logging. Defers to RankMath if installed.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoodHost_Redirect_Manager {
    
    private $table_name;
    private $log_table;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'goodhost_redirects';
        $this->log_table = $wpdb->prefix . 'goodhost_404_log';
        
        // Check if RankMath is handling redirects
        if ($this->rankmath_redirects_active()) {
            add_action('admin_menu', [$this, 'add_admin_menu_rankmath'], 20);
            add_filter('goodhost_register_modules', [$this, 'register_module_rankmath']);
            return;
        }
        
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('template_redirect', [$this, 'handle_redirects'], 1);
        add_action('template_redirect', [$this, 'log_404'], 99);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        add_action('wp_ajax_goodhost_add_redirect', [$this, 'ajax_add_redirect']);
        add_action('wp_ajax_goodhost_delete_redirect', [$this, 'ajax_delete_redirect']);
        add_action('wp_ajax_goodhost_clear_404_log', [$this, 'ajax_clear_404_log']);
        add_action('wp_ajax_goodhost_redirect_404', [$this, 'ajax_redirect_404']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    
    private function rankmath_redirects_active() {
        if (!class_exists('RankMath')) {
            return false;
        }
        $modules = get_option('rank_math_modules', []);
        return in_array('redirections', $modules);
    }
    
    public function register_module($modules) {
        $modules['redirect-manager'] = [
            'name' => 'Redirect Manager',
            'description' => '301/302 redirects with 404 logging',
            'active' => true,
            'settings_url' => admin_url('admin.php?page=goodhost-redirects')
        ];
        return $modules;
    }
    
    public function register_module_rankmath($modules) {
        $modules['redirect-manager'] = [
            'name' => 'Redirect Manager',
            'description' => 'Using RankMath Redirections',
            'active' => true,
            'settings_url' => admin_url('admin.php?page=rank-math-redirections')
        ];
        return $modules;
    }
    
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            source_url varchar(500) NOT NULL,
            target_url varchar(500) NOT NULL,
            redirect_type int(3) NOT NULL DEFAULT 301,
            hits bigint(20) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY source_url (source_url(191))
        ) $charset_collate;";
        
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(500) NOT NULL,
            referrer varchar(500) DEFAULT '',
            user_agent varchar(500) DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            hits int(11) NOT NULL DEFAULT 1,
            last_hit datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY url (url(191))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
    }
    
    public function add_admin_menu() {
        add_submenu_page('goodhost', 'Redirect Manager', 'Redirects', 'manage_options', 'goodhost-redirects', [$this, 'settings_page']);
    }
    
    public function add_admin_menu_rankmath() {
        add_submenu_page('goodhost', 'Redirect Manager', 'Redirects', 'manage_options', 'goodhost-redirects', [$this, 'rankmath_notice_page']);
    }
    
    public function register_settings() {}
    
    public function handle_redirects() {
        global $wpdb;
        $current_url = trim($_SERVER['REQUEST_URI'], '/');
        $redirect = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE source_url = %s OR source_url = %s LIMIT 1",
            $current_url, '/' . $current_url
        ));
        if ($redirect) {
            $wpdb->update($this->table_name, ['hits' => $redirect->hits + 1], ['id' => $redirect->id]);
            wp_redirect($redirect->target_url, $redirect->redirect_type);
            exit;
        }
    }
    
    public function log_404() {
        if (!is_404()) return;
        global $wpdb;
        $url = $_SERVER['REQUEST_URI'];
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '';
        $ip = $this->get_client_ip();
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, hits FROM {$this->log_table} WHERE url = %s", $url));
        if ($existing) {
            $wpdb->update($this->log_table, ['hits' => $existing->hits + 1, 'last_hit' => current_time('mysql'), 'referrer' => $referrer, 'user_agent' => $user_agent, 'ip_address' => $ip], ['id' => $existing->id]);
        } else {
            $wpdb->insert($this->log_table, ['url' => $url, 'referrer' => $referrer, 'user_agent' => $user_agent, 'ip_address' => $ip, 'hits' => 1, 'last_hit' => current_time('mysql')]);
        }
    }
    
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key]);
                return trim($ip[0]);
            }
        }
        return '0.0.0.0';
    }
    
    public function rankmath_notice_page() {
        ?>
        <div class="wrap">
            <h1>Redirect Manager</h1>
            <div style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #2271b1;padding:15px;margin:20px 0;max-width:700px;">
                <h3 style="margin-top:0;">✓ RankMath Redirections Active</h3>
                <p>RankMath is handling your redirects. Use RankMath's built-in redirect manager for the best integration with your SEO setup.</p>
                <p><a href="<?php echo admin_url('admin.php?page=rank-math-redirections'); ?>" class="button button-primary">Open RankMath Redirections →</a></p>
            </div>
        </div>
        <?php
    }
    
    public function settings_page() {
        global $wpdb;
        $redirects = $wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY id DESC");
        $logs = $wpdb->get_results("SELECT * FROM {$this->log_table} ORDER BY hits DESC, last_hit DESC LIMIT 100");
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'redirects';
        ?>
        <div class="wrap">
            <h1>Redirect Manager</h1>
            <style>
                .goodhost-tabs { margin: 20px 0; border-bottom: 1px solid #ccc; }
                .goodhost-tabs a { display: inline-block; padding: 10px 20px; text-decoration: none; border: 1px solid #ccc; border-bottom: none; margin-right: 5px; background: #f0f0f1; }
                .goodhost-tabs a.active { background: #fff; border-bottom: 1px solid #fff; margin-bottom: -1px; }
                .goodhost-form { background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px; max-width: 800px; }
                .goodhost-form input[type="text"], .goodhost-form select { width: 100%; padding: 8px; margin: 5px 0 15px; }
                .goodhost-table { max-width: 900px; }
                .goodhost-btn-delete { color: #d63638; cursor: pointer; }
                .goodhost-btn-redirect { color: #2271b1; cursor: pointer; }
            </style>
            <div class="goodhost-tabs">
                <a href="?page=goodhost-redirects&tab=redirects" class="<?php echo $active_tab === 'redirects' ? 'active' : ''; ?>">Redirects</a>
                <a href="?page=goodhost-redirects&tab=404-log" class="<?php echo $active_tab === '404-log' ? 'active' : ''; ?>">404 Log (<?php echo count($logs); ?>)</a>
            </div>
            <?php if ($active_tab === 'redirects') : ?>
                <div class="goodhost-form">
                    <h3>Add New Redirect</h3>
                    <form id="add-redirect-form">
                        <label>Source URL (relative path):</label>
                        <input type="text" name="source_url" id="source_url" placeholder="/old-page/" required>
                        <label>Target URL:</label>
                        <input type="text" name="target_url" id="target_url" placeholder="https://example.com/new-page/" required>
                        <label>Redirect Type:</label>
                        <select name="redirect_type" id="redirect_type">
                            <option value="301">301 - Permanent</option>
                            <option value="302">302 - Temporary</option>
                            <option value="307">307 - Temporary (Strict)</option>
                        </select>
                        <button type="submit" class="button button-primary">Add Redirect</button>
                        <span id="redirect-message" style="margin-left:10px;"></span>
                    </form>
                </div>
                <h3>Active Redirects</h3>
                <?php if (empty($redirects)) : ?><p>No redirects configured yet.</p>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped goodhost-table">
                    <thead><tr><th>Source</th><th>Target</th><th>Type</th><th>Hits</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($redirects as $r) : ?>
                        <tr id="redirect-row-<?php echo $r->id; ?>">
                            <td><code><?php echo esc_html($r->source_url); ?></code></td>
                            <td><a href="<?php echo esc_url($r->target_url); ?>" target="_blank"><?php echo esc_html($r->target_url); ?></a></td>
                            <td><?php echo esc_html($r->redirect_type); ?></td>
                            <td><?php echo number_format($r->hits); ?></td>
                            <td><span class="goodhost-btn-delete" onclick="deleteRedirect(<?php echo $r->id; ?>)">Delete</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php else : ?>
                <h3>404 Error Log</h3>
                <p><button type="button" class="button" onclick="clear404Log()">Clear All Logs</button></p>
                <?php if (empty($logs)) : ?><p>No 404 errors logged yet. 🎉</p>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped goodhost-table">
                    <thead><tr><th>URL</th><th>Hits</th><th>Last Hit</th><th>Referrer</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($logs as $log) : ?>
                        <tr id="log-row-<?php echo $log->id; ?>">
                            <td><code><?php echo esc_html($log->url); ?></code></td>
                            <td><?php echo number_format($log->hits); ?></td>
                            <td><?php echo esc_html($log->last_hit); ?></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?php echo esc_html($log->referrer ?: '—'); ?></td>
                            <td><span class="goodhost-btn-redirect" onclick="create404Redirect('<?php echo esc_js($log->url); ?>', <?php echo $log->id; ?>)">Create Redirect</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <script>
        document.getElementById('add-redirect-form')?.addEventListener('submit', function(e) {
            e.preventDefault();
            var msg = document.getElementById('redirect-message');
            msg.textContent = 'Adding...';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                var res = JSON.parse(xhr.responseText);
                if (res.success) { msg.style.color = 'green'; msg.textContent = 'Redirect added!'; setTimeout(function() { location.reload(); }, 500); }
                else { msg.style.color = 'red'; msg.textContent = res.data || 'Error'; }
            };
            xhr.send('action=goodhost_add_redirect&source_url=' + encodeURIComponent(document.getElementById('source_url').value) + '&target_url=' + encodeURIComponent(document.getElementById('target_url').value) + '&redirect_type=' + document.getElementById('redirect_type').value + '&_wpnonce=<?php echo wp_create_nonce('goodhost_redirects'); ?>');
        });
        function deleteRedirect(id) {
            if (!confirm('Delete this redirect?')) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() { var res = JSON.parse(xhr.responseText); if (res.success) document.getElementById('redirect-row-' + id).remove(); };
            xhr.send('action=goodhost_delete_redirect&id=' + id + '&_wpnonce=<?php echo wp_create_nonce('goodhost_redirects'); ?>');
        }
        function clear404Log() {
            if (!confirm('Clear all 404 logs?')) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() { location.reload(); };
            xhr.send('action=goodhost_clear_404_log&_wpnonce=<?php echo wp_create_nonce('goodhost_redirects'); ?>');
        }
        function create404Redirect(url, logId) {
            var target = prompt('Enter target URL for redirect:', '<?php echo home_url(); ?>');
            if (!target) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() { var res = JSON.parse(xhr.responseText); if (res.success) { document.getElementById('log-row-' + logId).remove(); alert('Redirect created!'); } };
            xhr.send('action=goodhost_redirect_404&source_url=' + encodeURIComponent(url) + '&target_url=' + encodeURIComponent(target) + '&log_id=' + logId + '&_wpnonce=<?php echo wp_create_nonce('goodhost_redirects'); ?>');
        }
        </script>
        <?php
    }
    
    public function ajax_add_redirect() {
        check_ajax_referer('goodhost_redirects', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $source = sanitize_text_field($_POST['source_url']);
        $target = esc_url_raw($_POST['target_url']);
        $type = intval($_POST['redirect_type']);
        if (empty($source) || empty($target)) wp_send_json_error('Source and target URLs are required');
        if (strpos($source, '/') !== 0) $source = '/' . $source;
        $wpdb->insert($this->table_name, ['source_url' => $source, 'target_url' => $target, 'redirect_type' => $type]);
        wp_send_json_success();
    }
    
    public function ajax_delete_redirect() {
        check_ajax_referer('goodhost_redirects', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $wpdb->delete($this->table_name, ['id' => intval($_POST['id'])]);
        wp_send_json_success();
    }
    
    public function ajax_clear_404_log() {
        check_ajax_referer('goodhost_redirects', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->log_table}");
        wp_send_json_success();
    }
    
    public function ajax_redirect_404() {
        check_ajax_referer('goodhost_redirects', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $source = sanitize_text_field($_POST['source_url']);
        $target = esc_url_raw($_POST['target_url']);
        $log_id = intval($_POST['log_id']);
        $wpdb->insert($this->table_name, ['source_url' => $source, 'target_url' => $target, 'redirect_type' => 301]);
        $wpdb->delete($this->log_table, ['id' => $log_id]);
        wp_send_json_success();
    }
}

new GoodHost_Redirect_Manager();
