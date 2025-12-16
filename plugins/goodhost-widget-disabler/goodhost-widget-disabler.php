<?php
/**
 * Plugin Name: GoodHost - Widget Disabler
 * Plugin URI: https://goodhost.com.au
 * Description: Disable unused widgets in Elementor, Essential Addons, and Premium Addons to improve performance.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Widget_Disabler {
    private $options;
    
    public function __construct() {
        $this->options = get_option('goodhost_widget_disabler', []);
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        add_action('elementor/widgets/register', [$this, 'disable_elementor_widgets'], 999);
        add_filter('eael/active_widgets', [$this, 'disable_essential_addons'], 999);
    }
    
    public function register_module($modules) { $modules['widget-disabler'] = ['name' => 'Widget Disabler', 'description' => 'Disable unused Elementor widgets', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-widgets')]; return $modules; }
    public function add_admin_menu() { add_submenu_page('goodhost', 'Widget Disabler', 'Widget Disabler', 'manage_options', 'goodhost-widgets', [$this, 'settings_page']); }
    public function register_settings() { register_setting('goodhost_widget_disabler', 'goodhost_widget_disabler'); }
    
    private function get_elementor_widgets() {
        return ['heading' => 'Heading', 'image' => 'Image', 'text-editor' => 'Text Editor', 'video' => 'Video', 'button' => 'Button', 'divider' => 'Divider', 'spacer' => 'Spacer', 'google-maps' => 'Google Maps', 'icon' => 'Icon', 'image-box' => 'Image Box', 'icon-box' => 'Icon Box', 'star-rating' => 'Star Rating', 'image-carousel' => 'Image Carousel', 'image-gallery' => 'Image Gallery', 'icon-list' => 'Icon List', 'counter' => 'Counter', 'progress' => 'Progress Bar', 'testimonial' => 'Testimonial', 'tabs' => 'Tabs', 'accordion' => 'Accordion', 'toggle' => 'Toggle', 'social-icons' => 'Social Icons', 'alert' => 'Alert', 'audio' => 'Audio', 'shortcode' => 'Shortcode', 'html' => 'HTML', 'posts' => 'Posts (Pro)', 'portfolio' => 'Portfolio (Pro)', 'slides' => 'Slides (Pro)', 'form' => 'Form (Pro)', 'nav-menu' => 'Nav Menu (Pro)', 'animated-headline' => 'Animated Headline (Pro)', 'price-table' => 'Price Table (Pro)', 'flip-box' => 'Flip Box (Pro)', 'call-to-action' => 'Call to Action (Pro)', 'countdown' => 'Countdown (Pro)', 'share-buttons' => 'Share Buttons (Pro)'];
    }
    
    private function get_essential_addons_widgets() {
        return ['post-grid' => 'Post Grid', 'post-timeline' => 'Post Timeline', 'fancy-text' => 'Fancy Text', 'creative-btn' => 'Creative Button', 'count-down' => 'Countdown', 'team-members' => 'Team Members', 'testimonials' => 'Testimonials', 'info-box' => 'Info Box', 'flip-box' => 'Flip Box', 'dual-header' => 'Dual Color Heading', 'price-table' => 'Pricing Table', 'ninja-forms' => 'Ninja Forms', 'gravity-forms' => 'Gravity Forms', 'wpforms' => 'WPForms', 'contact-form-7' => 'Contact Form 7', 'call-to-action' => 'Call to Action', 'logo-carousel' => 'Logo Carousel', 'twitter-feed' => 'Twitter Feed', 'data-table' => 'Data Table', 'filter-gallery' => 'Filterable Gallery', 'image-accordion' => 'Image Accordion', 'content-ticker' => 'Content Ticker', 'tooltip' => 'Tooltip', 'adv-tabs' => 'Advanced Tabs', 'adv-accordion' => 'Advanced Accordion', 'feature-list' => 'Feature List', 'progress-bar' => 'Progress Bar', 'content-timeline' => 'Content Timeline', 'image-hotspots' => 'Image Hotspots', 'sticky-video' => 'Sticky Video', 'simple-menu' => 'Simple Menu', 'post-list' => 'Post List'];
    }
    
    public function disable_elementor_widgets($widgets_manager) {
        $disabled = isset($this->options['elementor']) ? $this->options['elementor'] : [];
        foreach ($disabled as $widget_name => $is_disabled) { if ($is_disabled) $widgets_manager->unregister($widget_name); }
    }
    
    public function disable_essential_addons($active_widgets) {
        $disabled = isset($this->options['essential_addons']) ? $this->options['essential_addons'] : [];
        foreach ($disabled as $widget_name => $is_disabled) { if ($is_disabled && isset($active_widgets[$widget_name])) $active_widgets[$widget_name] = false; }
        return $active_widgets;
    }
    
    public function settings_page() {
        $elementor_active = did_action('elementor/loaded');
        $ea_active = class_exists('Essential_Addons_Elementor\\Classes\\Bootstrap');
        $elementor_widgets = $this->get_elementor_widgets();
        $ea_widgets = $this->get_essential_addons_widgets();
        ?>
        <div class="wrap"><h1>Widget Disabler</h1><p>Disable unused widgets to reduce page load time and improve editor performance.</p>
        <style>.gh-tabs{margin:20px 0}.gh-tabs button{padding:10px 20px;border:1px solid #ccc;background:#f0f0f1;cursor:pointer;margin-right:-1px}.gh-tabs button.active{background:#fff;border-bottom:1px solid #fff}.gh-tabs button:disabled{opacity:.5;cursor:not-allowed}.gh-panel{display:none;background:#fff;border:1px solid #ccc;padding:20px;margin-top:-1px}.gh-panel.active{display:block}.gh-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;max-height:500px;overflow-y:auto}.gh-item{padding:8px;background:#f9f9f9;border:1px solid #eee}.gh-item label{display:flex;align-items:center;cursor:pointer}.gh-item input{margin-right:8px}.gh-actions{margin:15px 0;padding:15px;background:#f0f0f1}.gh-inactive{padding:20px;background:#fff3cd;border:1px solid #ffc107;margin:10px 0}</style>
        <form method="post" action="options.php"><?php settings_fields('goodhost_widget_disabler'); ?>
        <div class="gh-tabs">
            <button type="button" class="active" onclick="showPanel('elementor',this)" <?php echo !$elementor_active ? 'disabled' : ''; ?>>Elementor <?php echo !$elementor_active ? '(Not Active)' : ''; ?></button>
            <button type="button" onclick="showPanel('essential',this)" <?php echo !$ea_active ? 'disabled' : ''; ?>>Essential Addons <?php echo !$ea_active ? '(Not Active)' : ''; ?></button>
        </div>
        <div id="panel-elementor" class="gh-panel active">
            <?php if (!$elementor_active) : ?><div class="gh-inactive">Elementor is not active on this site.</div>
            <?php else : ?>
            <div class="gh-actions"><button type="button" class="button" onclick="toggleAll('elementor',true)">Disable All</button> <button type="button" class="button" onclick="toggleAll('elementor',false)">Enable All</button> <span style="margin-left:20px;color:#666">Check widgets you want to DISABLE</span></div>
            <div class="gh-grid" id="grid-elementor">
            <?php foreach ($elementor_widgets as $key => $label) : $checked = !empty($this->options['elementor'][$key]); ?>
                <div class="gh-item"><label><input type="checkbox" name="goodhost_widget_disabler[elementor][<?php echo esc_attr($key); ?>]" value="1" <?php checked($checked); ?>> <?php echo esc_html($label); ?></label></div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <div id="panel-essential" class="gh-panel">
            <?php if (!$ea_active) : ?><div class="gh-inactive">Essential Addons for Elementor is not active on this site.</div>
            <?php else : ?>
            <div class="gh-actions"><button type="button" class="button" onclick="toggleAll('essential',true)">Disable All</button> <button type="button" class="button" onclick="toggleAll('essential',false)">Enable All</button> <span style="margin-left:20px;color:#666">Check widgets you want to DISABLE</span></div>
            <div class="gh-grid" id="grid-essential">
            <?php foreach ($ea_widgets as $key => $label) : $checked = !empty($this->options['essential_addons'][$key]); ?>
                <div class="gh-item"><label><input type="checkbox" name="goodhost_widget_disabler[essential_addons][<?php echo esc_attr($key); ?>]" value="1" <?php checked($checked); ?>> <?php echo esc_html($label); ?></label></div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <p style="margin-top:20px"><?php submit_button('Save Changes', 'primary', 'submit', false); ?></p>
        </form></div>
        <script>
        function showPanel(panel,btn){document.querySelectorAll('.gh-panel').forEach(function(p){p.classList.remove('active');});document.querySelectorAll('.gh-tabs button').forEach(function(b){b.classList.remove('active');});document.getElementById('panel-'+panel).classList.add('active');btn.classList.add('active');}
        function toggleAll(grid,checked){document.querySelectorAll('#grid-'+grid+' input[type="checkbox"]').forEach(function(cb){cb.checked=checked;});}
        </script>
    <?php }
}
new GoodHost_Widget_Disabler();
