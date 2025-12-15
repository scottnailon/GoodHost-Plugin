<?php
/**
 * Plugin Name: GoodHost
 * Plugin URI: https://goodhost.com.au
 * Description: A lightweight plugin framework for hosting-related utilities. Provides a unified admin menu for GoodHost sub-plugins.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('GOODHOST_VERSION', '1.0.0');
define('GOODHOST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GOODHOST_PLUGIN_URL', plugin_dir_url(__FILE__));

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
        
        // Add dashboard as first submenu to avoid duplicate
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
            .goodhost-modules table { max-width: 700px; }
            .goodhost-footer { margin-top: 30px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1; max-width: 680px; }
            .goodhost-status-active { color: green; font-weight: 500; }
            .goodhost-status-inactive { color: #666; }
        </style>';
    }
    
    private function get_logo_url() {
        $local_logo = GOODHOST_PLUGIN_DIR . 'assets/goodhost-logo.png';
        if (file_exists($local_logo)) {
            return GOODHOST_PLUGIN_URL . 'assets/goodhost-logo.png';
        }
        return 'https://goodhost.com.au/wp-content/uploads/2021/10/goodhost-australian-web-hosting-2.png';
    }
    
    public function dashboard_page() {
        $logo_url = $this->get_logo_url();
        $modules = $this->get_registered_modules();
        ?>
        <div class="wrap">
            <div class="goodhost-header">
                <img src="<?php echo esc_url($logo_url); ?>" alt="GoodHost">
            </div>
            
            <h1>GoodHost Suite</h1>
            <p>Welcome to the GoodHost plugin suite. Simple tools that just work.</p>
            
            <div class="goodhost-modules">
                <h2>Installed Modules</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modules)) : ?>
                            <tr>
                                <td colspan="4">No modules installed yet. Install GoodHost sub-plugins to extend functionality.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($modules as $module) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($module['name']); ?></strong></td>
                                    <td><?php echo esc_html($module['description']); ?></td>
                                    <td>
                                        <?php if ($module['active']) : ?>
                                            <span class="goodhost-status-active">● Active</span>
                                        <?php else : ?>
                                            <span class="goodhost-status-inactive">○ Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($module['settings_url'])) : ?>
                                            <a href="<?php echo esc_url($module['settings_url']); ?>">Settings</a>
                                        <?php else : ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php do_action('goodhost_dashboard_modules'); ?>
                    </tbody>
                </table>
            </div>
            
            <div class="goodhost-footer">
                <strong>Sites By Design</strong><br>
                <a href="https://sitesbydesign.com.au" target="_blank">sitesbydesign.com.au</a> · 
                <a href="https://goodhost.com.au" target="_blank">goodhost.com.au</a>
            </div>
        </div>
        <?php
    }
    
    private function get_registered_modules() {
        return apply_filters('goodhost_register_modules', []);
    }
    
    /**
     * Helper function for sub-plugins to check if GoodHost is active
     */
    public static function is_active() {
        return true;
    }
}

// Initialize
function goodhost_init() {
    return GoodHost::get_instance();
}
add_action('plugins_loaded', 'goodhost_init');

/**
 * Helper function for sub-plugins to register with GoodHost
 * 
 * Usage in sub-plugin:
 * add_filter('goodhost_register_modules', function($modules) {
 *     $modules['news-sitemap'] = [
 *         'name' => 'News Sitemap',
 *         'description' => 'Google News sitemap generator',
 *         'active' => true,
 *         'settings_url' => admin_url('admin.php?page=goodhost-news-sitemap')
 *     ];
 *     return $modules;
 * });
 */
