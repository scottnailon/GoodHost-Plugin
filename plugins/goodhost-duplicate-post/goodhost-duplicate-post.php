<?php
/**
 * Plugin Name: GoodHost - Duplicate Post
 * Plugin URI: https://goodhost.com.au
 * Description: Clone posts, pages, and custom post types with one click.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) {
    exit;
}

class GoodHost_Duplicate_Post {
    
    public function __construct() {
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        add_filter('post_row_actions', [$this, 'add_duplicate_link'], 10, 2);
        add_filter('page_row_actions', [$this, 'add_duplicate_link'], 10, 2);
        add_action('admin_action_goodhost_duplicate', [$this, 'duplicate_post']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('admin_bar_menu', [$this, 'admin_bar_link'], 100);
    }
    
    public function register_module($modules) {
        $modules['duplicate-post'] = [
            'name' => 'Duplicate Post',
            'description' => 'Clone posts and pages',
            'active' => true,
            'settings_url' => ''
        ];
        return $modules;
    }
    
    public function add_duplicate_link($actions, $post) {
        if (!current_user_can('edit_posts')) return $actions;
        $url = wp_nonce_url(admin_url('admin.php?action=goodhost_duplicate&post=' . $post->ID), 'goodhost_duplicate_' . $post->ID);
        $actions['duplicate'] = sprintf('<a href="%s" title="%s">%s</a>', esc_url($url), esc_attr__('Duplicate this item', 'goodhost'), __('Duplicate', 'goodhost'));
        return $actions;
    }
    
    public function admin_bar_link($wp_admin_bar) {
        if (!is_admin() && !is_singular()) return;
        if (!current_user_can('edit_posts')) return;
        $post_id = get_the_ID();
        if (!$post_id && is_admin()) { global $post; if ($post) $post_id = $post->ID; }
        if (!$post_id) return;
        if (is_admin()) { $screen = get_current_screen(); if (!$screen || $screen->base !== 'post') return; }
        $url = wp_nonce_url(admin_url('admin.php?action=goodhost_duplicate&post=' . $post_id), 'goodhost_duplicate_' . $post_id);
        $wp_admin_bar->add_node(['id' => 'goodhost-duplicate', 'title' => '📋 Duplicate', 'href' => $url]);
    }
    
    public function duplicate_post() {
        if (!isset($_GET['post']) || !isset($_GET['_wpnonce'])) wp_die('Invalid request.');
        $post_id = intval($_GET['post']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'goodhost_duplicate_' . $post_id)) wp_die('Security check failed.');
        if (!current_user_can('edit_posts')) wp_die('Permission denied.');
        $post = get_post($post_id);
        if (!$post) wp_die('Post not found.');
        $new_post_id = $this->create_duplicate($post);
        if (is_wp_error($new_post_id)) wp_die('Error creating duplicate: ' . $new_post_id->get_error_message());
        wp_redirect(admin_url('post.php?action=edit&post=' . $new_post_id . '&duplicated=1'));
        exit;
    }
    
    private function create_duplicate($post) {
        $current_user = wp_get_current_user();
        $new_post = [
            'post_title' => $post->post_title . ' (Copy)',
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => 'draft',
            'post_type' => $post->post_type,
            'post_author' => $current_user->ID,
            'post_parent' => $post->post_parent,
            'menu_order' => $post->menu_order,
            'post_password' => $post->post_password,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
        ];
        $new_post_id = wp_insert_post($new_post);
        if (is_wp_error($new_post_id)) return $new_post_id;
        $taxonomies = get_object_taxonomies($post->post_type);
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'slugs']);
            wp_set_object_terms($new_post_id, $terms, $taxonomy);
        }
        $post_meta = get_post_meta($post->ID);
        foreach ($post_meta as $meta_key => $meta_values) {
            if (in_array($meta_key, ['_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date'])) continue;
            foreach ($meta_values as $meta_value) {
                add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) set_post_thumbnail($new_post_id, $thumbnail_id);
        return $new_post_id;
    }
    
    public function admin_notices() {
        if (isset($_GET['duplicated']) && $_GET['duplicated'] == 1) {
            echo '<div class="notice notice-success is-dismissible"><p>Post duplicated successfully! You are now editing the copy.</p></div>';
        }
    }
}

new GoodHost_Duplicate_Post();
