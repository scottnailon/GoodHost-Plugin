<?php
/**
 * Plugin Name: GoodHost
 * Plugin URI: https://goodhost.com.au
 * Description: A lightweight plugin framework for hosting-related utilities. Provides a unified admin menu for GoodHost sub-plugins.
 * Version: 1.1.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('GOODHOST_VERSION', '1.1.0');
define('GOODHOST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GOODHOST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GOODHOST_MANIFEST_URL', 'https://raw.githubusercontent.com/scottnailon/GoodHost-Plugin/main/manifest.json');
define('GOODHOST_GITHUB_RAW', 'https://raw.githubusercontent.com/scottnailon/GoodHost-Plugin/main/');

class GoodHost {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu'], 5);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
        add_action('wp_ajax_goodhost_install_plugin', [$this, 'ajax_install_plugin']);
        add_action('wp_ajax_goodhost_remove_plugin', [$this, 'ajax_remove_plugin']);
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'GoodHost',
            'GoodHost',
            'manage_options',
            'goodhost',
            [$this, 'dashboard_page'],
            'dashicons-superhero-alt',
            30
        );
        
        add_submenu_page(
            'goodhost',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'goodhost',
            [$this, 'dashboard_page']
        );
    }
    
    public function admin_styles($hook) {
        if (strpos($hook, 'goodhost') === false) {
            return;
        }
        
        echo '<style>
            .goodhost-header { margin-bottom: 20px; }
            .goodhost-header img { max-width: 300px; height: auto; }
            .goodhost-modules { margin-top: 20px; }
            .goodhost-modules table { max-width: 800px; }
            .goodhost-footer { margin-top: 30px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1; max-width: 780px; }
            .goodhost-status-active { color: green; font-weight: 500; }
            .goodhost-status-available { color: #2271b1; }
            .goodhost-status-inactive { color: #666; }
            .goodhost-btn { padding: 4px 12px; text-decoration: none; border-radius: 3px; font-size: 13px; cursor: pointer; border: none; }
            .goodhost-btn-install { background: #2271b1; color: #fff; }
            .goodhost-btn-install:hover { background: #135e96; color: #fff; }
            .goodhost-btn-remove { background: #d63638; color: #fff; }
            .goodhost-btn-remove:hover { background: #b32d2e; color: #fff; }
            .goodhost-btn-settings { background: #f0f0f1; color: #2271b1; border: 1px solid #2271b1; }
            .goodhost-btn:disabled { opacity: 0.6; cursor: not-allowed; }
            .goodhost-message { padding: 10px 15px; margin: 10px 0; border-left: 4px solid; max-width: 780px; }
            .goodhost-message-success { background: #d4edda; border-color: #28a745; }
            .goodhost-message-error { background: #f8d7da; border-color: #dc3545; }
            .goodhost-spinner { display: none; margin-left: 10px; }
        </style>';
    }
    
    private function get_logo_url() {
        $local_logo = GOODHOST_PLUGIN_DIR . 'assets/goodhost-logo.png';
        if (file_exists($local_logo)) {
            return GOODHOST_PLUGIN_URL . 'assets/goodhost-logo.png';
        }
        return 'https://goodhost.com.au/wp-content/uploads/2021/10/goodhost-australian-web-hosting-2.png';
    }
    
    private function get_manifest() {
        $cached = get_transient('goodhost_manifest');
        if ($cached !== false) {
            return $cached;
        }
        
        $response = wp_remote_get(GOODHOST_MANIFEST_URL, [
            'timeout' => 15,
            'headers' => ['Accept' => 'application/json']
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $manifest = json_decode($body, true);
        
        if (!$manifest) {
            return false;
        }
        
        set_transient('goodhost_manifest', $manifest, HOUR_IN_SECONDS);
        return $manifest;
    }
    
    private function is_plugin_installed($folder) {
        return is_dir(WP_PLUGIN_DIR . '/' . $folder);
    }
    
    private function is_plugin_active($folder, $main_file) {
        return is_plugin_active($folder . '/' . $main_file);
    }
    
    public function dashboard_page() {
        $logo_url = $this->get_logo_url();
        $installed_modules = apply_filters('goodhost_register_modules', []);
        $manifest = $this->get_manifest();
        
        // Handle messages
        $message = '';
        $message_type = '';
        if (isset($_GET['gh_installed'])) {
            $message = 'Plugin installed and activated successfully!';
            $message_type = 'success';
        } elseif (isset($_GET['gh_removed'])) {
            $message = 'Plugin removed successfully!';
            $message_type = 'success';
        } elseif (isset($_GET['gh_error'])) {
            $message = 'An error occurred: ' . esc_html($_GET['gh_error']);
            $message_type = 'error';
        }
        ?>
        <div class="wrap">
            <div class="goodhost-header">
                <img src="<?php echo esc_url($logo_url); ?>" alt="GoodHost">
            </div>
            
            <h1>GoodHost Suite</h1>
            <p>Welcome to the GoodHost plugin suite. Simple tools that just work.</p>
            
            <?php if ($message) : ?>
                <div class="goodhost-message goodhost-message-<?php echo esc_attr($message_type); ?>">
                    <?php echo esc_html($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="goodhost-modules">
                <h2>Available Modules</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:25%;">Module</th>
                            <th style="width:40%;">Description</th>
                            <th style="width:15%;">Version</th>
                            <th style="width:20%;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$manifest || empty($manifest['plugins'])) : ?>
                            <tr>
                                <td colspan="4">Unable to fetch available modules. <a href="<?php echo esc_url(admin_url('admin.php?page=goodhost')); ?>">Try refreshing</a>.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($manifest['plugins'] as $slug => $plugin) : 
                                $is_installed = $this->is_plugin_installed($plugin['folder']);
                                $is_active = $is_installed && $this->is_plugin_active($plugin['folder'], $plugin['main_file']);
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($plugin['name']); ?></strong></td>
                                    <td><?php echo esc_html($plugin['description']); ?></td>
                                    <td><?php echo esc_html($plugin['version']); ?></td>
                                    <td>
                                        <?php if ($is_active) : ?>
                                            <span class="goodhost-status-active">● Active</span>
                                            <?php if (!empty($installed_modules[$slug]['settings_url'])) : ?>
                                                <a href="<?php echo esc_url($installed_modules[$slug]['settings_url']); ?>" class="goodhost-btn goodhost-btn-settings" style="margin-left:5px;">Settings</a>
                                            <?php endif; ?>
                                        <?php elseif ($is_installed) : ?>
                                            <span class="goodhost-status-inactive">○ Installed</span>
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('plugins.php?action=activate&plugin=' . urlencode($plugin['folder'] . '/' . $plugin['main_file'])), 'activate-plugin_' . $plugin['folder'] . '/' . $plugin['main_file'])); ?>" class="goodhost-btn goodhost-btn-install">Activate</a>
                                        <?php else : ?>
                                            <button type="button" class="goodhost-btn goodhost-btn-install" onclick="goodhostInstall('<?php echo esc_js($slug); ?>')">
                                                Install
                                            </button>
                                            <span class="goodhost-spinner spinner" id="spinner-<?php echo esc_attr($slug); ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p style="margin-top:15px;">
                    <a href="<?php echo esc_url(add_query_arg('gh_refresh', '1', admin_url('admin.php?page=goodhost'))); ?>" class="button">↻ Refresh List</a>
                </p>
            </div>
            
            <div class="goodhost-footer">
                <strong>Sites By Design</strong><br>
                <a href="https://sitesbydesign.com.au" target="_blank">sitesbydesign.com.au</a> · 
                <a href="https://goodhost.com.au" target="_blank">goodhost.com.au</a>
            </div>
        </div>
        
        <script>
        function goodhostInstall(slug) {
            if (!confirm('Install this plugin?')) return;
            
            var btn = event.target;
            var spinner = document.getElementById('spinner-' + slug);
            
            btn.disabled = true;
            btn.textContent = 'Installing...';
            spinner.style.display = 'inline-block';
            spinner.classList.add('is-active');
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>');
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        window.location.href = '<?php echo admin_url('admin.php?page=goodhost'); ?>&gh_installed=1';
                    } else {
                        window.location.href = '<?php echo admin_url('admin.php?page=goodhost'); ?>&gh_error=' + encodeURIComponent(response.data);
                    }
                } else {
                    window.location.href = '<?php echo admin_url('admin.php?page=goodhost'); ?>&gh_error=Request%20failed';
                }
            };
            xhr.send('action=goodhost_install_plugin&slug=' + slug + '&_wpnonce=<?php echo wp_create_nonce('goodhost_install'); ?>');
        }
        </script>
        <?php
        
        // Clear cache if refresh requested
        if (isset($_GET['gh_refresh'])) {
            delete_transient('goodhost_manifest');
            wp_redirect(admin_url('admin.php?page=goodhost'));
            exit;
        }
    }
    
    public function ajax_install_plugin() {
        check_ajax_referer('goodhost_install', '_wpnonce');
        
        if (!current_user_can('install_plugins')) {
            wp_send_json_error('Permission denied');
        }
        
        $slug = sanitize_text_field($_POST['slug']);
        $manifest = $this->get_manifest();
        
        if (!$manifest || !isset($manifest['plugins'][$slug])) {
            wp_send_json_error('Plugin not found in manifest');
        }
        
        $plugin = $manifest['plugins'][$slug];
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin['folder'];
        
        // Create plugin directory
        if (!wp_mkdir_p($plugin_dir)) {
            wp_send_json_error('Could not create plugin directory');
        }
        
        // Download main plugin file
        $file_url = GOODHOST_GITHUB_RAW . $plugin['folder'] . '/' . $plugin['main_file'];
        $response = wp_remote_get($file_url, ['timeout' => 30]);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Could not download plugin: ' . $response->get_error_message());
        }
        
        $file_content = wp_remote_retrieve_body($response);
        if (empty($file_content)) {
            wp_send_json_error('Downloaded file is empty');
        }
        
        // Save main file
        $result = file_put_contents($plugin_dir . '/' . $plugin['main_file'], $file_content);
        if ($result === false) {
            wp_send_json_error('Could not save plugin file');
        }
        
        // Download readme.txt if exists
        $readme_url = GOODHOST_GITHUB_RAW . $plugin['folder'] . '/readme.txt';
        $readme_response = wp_remote_get($readme_url, ['timeout' => 15]);
        if (!is_wp_error($readme_response)) {
            $readme_content = wp_remote_retrieve_body($readme_response);
            if (!empty($readme_content)) {
                file_put_contents($plugin_dir . '/readme.txt', $readme_content);
            }
        }
        
        // Activate the plugin
        $activate = activate_plugin($plugin['folder'] . '/' . $plugin['main_file']);
        if (is_wp_error($activate)) {
            wp_send_json_error('Installed but could not activate: ' . $activate->get_error_message());
        }
        
        wp_send_json_success('Plugin installed and activated');
    }
    
    public function ajax_remove_plugin() {
        check_ajax_referer('goodhost_remove', '_wpnonce');
        
        if (!current_user_can('delete_plugins')) {
            wp_send_json_error('Permission denied');
        }
        
        $slug = sanitize_text_field($_POST['slug']);
        $manifest = $this->get_manifest();
        
        if (!$manifest || !isset($manifest['plugins'][$slug])) {
            wp_send_json_error('Plugin not found');
        }
        
        $plugin = $manifest['plugins'][$slug];
        $plugin_file = $plugin['folder'] . '/' . $plugin['main_file'];
        
        // Deactivate first
        deactivate_plugins($plugin_file);
        
        // Delete
        $deleted = delete_plugins([$plugin_file]);
        
        if (is_wp_error($deleted)) {
            wp_send_json_error($deleted->get_error_message());
        }
        
        wp_send_json_success('Plugin removed');
    }
    
    public static function is_active() {
        return true;
    }
}

// Initialize
function goodhost_init() {
    return GoodHost::get_instance();
}
add_action('plugins_loaded', 'goodhost_init');
