<?php
/**
 * Plugin Name: GoodHost - News Sitemap
 * Plugin URI: https://goodhost.com.au
 * Description: Google News sitemap generator. Requires the GoodHost plugin.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoodHost_News_Sitemap {
    
    private $options;
    
    public function __construct() {
        $this->options = get_option('goodhost_news_sitemap_options', $this->get_defaults());
        
        add_action('init', [$this, 'add_rewrite_rules']);
        add_action('template_redirect', [$this, 'render_sitemap']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function get_defaults() {
        return [
            'publication_name' => get_bloginfo('name'),
            'publication_language' => substr(get_locale(), 0, 2),
            'post_types' => ['post'],
            'categories' => [],
            'hours_limit' => 48,
        ];
    }
    
    public function register_module($modules) {
        $modules['news-sitemap'] = [
            'name' => 'News Sitemap',
            'description' => 'Google News sitemap generator',
            'active' => true,
            'settings_url' => admin_url('admin.php?page=goodhost-news-sitemap')
        ];
        return $modules;
    }
    
    public function activate() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function add_rewrite_rules() {
        add_rewrite_rule(
            'news-sitemap\.xml$',
            'index.php?goodhost_news_sitemap=1',
            'top'
        );
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'goodhost_news_sitemap';
        return $vars;
    }
    
    public function render_sitemap() {
        if (!get_query_var('goodhost_news_sitemap')) {
            return;
        }
        
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');
        
        echo $this->generate_sitemap();
        exit;
    }
    
    public function generate_sitemap() {
        $hours = intval($this->options['hours_limit']);
        $date_query = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $args = [
            'post_type' => $this->options['post_types'],
            'post_status' => 'publish',
            'posts_per_page' => 1000,
            'date_query' => [
                [
                    'after' => $date_query,
                    'inclusive' => true,
                ],
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        
        if (!empty($this->options['categories'])) {
            $args['category__in'] = $this->options['categories'];
        }
        
        $posts = get_posts($args);
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
        
        foreach ($posts as $post) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . esc_url(get_permalink($post)) . "</loc>\n";
            $xml .= "    <news:news>\n";
            $xml .= "      <news:publication>\n";
            $xml .= "        <news:name>" . esc_xml($this->options['publication_name']) . "</news:name>\n";
            $xml .= "        <news:language>" . esc_xml($this->options['publication_language']) . "</news:language>\n";
            $xml .= "      </news:publication>\n";
            $xml .= "      <news:publication_date>" . get_the_date('c', $post) . "</news:publication_date>\n";
            $xml .= "      <news:title>" . esc_xml($post->post_title) . "</news:title>\n";
            $xml .= "    </news:news>\n";
            $xml .= "  </url>\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'goodhost',
            'News Sitemap',
            'News Sitemap',
            'manage_options',
            'goodhost-news-sitemap',
            [$this, 'settings_page']
        );
    }
    
    public function register_settings() {
        register_setting('goodhost_news_sitemap', 'goodhost_news_sitemap_options', [
            'sanitize_callback' => [$this, 'sanitize_options']
        ]);
    }
    
    public function sanitize_options($input) {
        $sanitized = [];
        $sanitized['publication_name'] = sanitize_text_field($input['publication_name'] ?? '');
        $sanitized['publication_language'] = sanitize_text_field($input['publication_language'] ?? 'en');
        $sanitized['post_types'] = array_map('sanitize_text_field', (array)($input['post_types'] ?? ['post']));
        $sanitized['categories'] = array_map('intval', (array)($input['categories'] ?? []));
        $sanitized['hours_limit'] = max(1, intval($input['hours_limit'] ?? 48));
        
        // Flush rewrite rules on save
        flush_rewrite_rules();
        
        return $sanitized;
    }
    
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $post_types = get_post_types(['public' => true], 'objects');
        $categories = get_categories(['hide_empty' => false]);
        $sitemap_url = home_url('/news-sitemap.xml');
        ?>
        <div class="wrap">
            <h1>News Sitemap Settings</h1>
            
            <div style="background:#fff;border:1px solid #ccd0d4;padding:15px;margin:20px 0;max-width:700px;">
                <strong>Your News Sitemap URL:</strong><br>
                <code style="display:block;margin-top:5px;padding:10px;background:#f0f0f1;">
                    <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank"><?php echo esc_html($sitemap_url); ?></a>
                </code>
                <p style="margin-top:10px;color:#666;">Submit this URL to <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a> under Sitemaps.</p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('goodhost_news_sitemap'); ?>
                
                <table class="form-table" style="max-width:700px;">
                    <tr>
                        <th scope="row"><label for="publication_name">Publication Name</label></th>
                        <td>
                            <input type="text" id="publication_name" name="goodhost_news_sitemap_options[publication_name]" 
                                   value="<?php echo esc_attr($this->options['publication_name']); ?>" class="regular-text">
                            <p class="description">Your publication name as registered with Google News.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="publication_language">Language</label></th>
                        <td>
                            <input type="text" id="publication_language" name="goodhost_news_sitemap_options[publication_language]" 
                                   value="<?php echo esc_attr($this->options['publication_language']); ?>" class="small-text" maxlength="5">
                            <p class="description">2-letter ISO 639 language code (e.g., en, de, fr).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Post Types</th>
                        <td>
                            <?php foreach ($post_types as $pt) : ?>
                                <label style="display:block;margin-bottom:5px;">
                                    <input type="checkbox" name="goodhost_news_sitemap_options[post_types][]" 
                                           value="<?php echo esc_attr($pt->name); ?>"
                                           <?php checked(in_array($pt->name, $this->options['post_types'])); ?>>
                                    <?php echo esc_html($pt->label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">Select which post types to include in the sitemap.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Categories</th>
                        <td>
                            <select name="goodhost_news_sitemap_options[categories][]" multiple style="min-width:300px;height:150px;">
                                <option value="" <?php selected(empty($this->options['categories'])); ?>>All Categories</option>
                                <?php foreach ($categories as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>"
                                            <?php selected(in_array($cat->term_id, $this->options['categories'])); ?>>
                                        <?php echo esc_html($cat->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Limit to specific categories (hold Ctrl/Cmd to select multiple), or leave empty for all.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hours_limit">Time Limit</label></th>
                        <td>
                            <input type="number" id="hours_limit" name="goodhost_news_sitemap_options[hours_limit]" 
                                   value="<?php echo esc_attr($this->options['hours_limit']); ?>" class="small-text" min="1" max="168">
                            <span>hours</span>
                            <p class="description">Include posts from the last X hours. Google News requires articles be less than 48 hours old.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}

// Helper function for XML escaping
if (!function_exists('esc_xml')) {
    function esc_xml($string) {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

// Initialize
new GoodHost_News_Sitemap();
