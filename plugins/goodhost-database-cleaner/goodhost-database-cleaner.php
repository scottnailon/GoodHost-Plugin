<?php
/**
 * Plugin Name: GoodHost - Database Cleaner
 * Plugin URI: https://goodhost.com.au
 * Description: Clean up your WordPress database. Remove revisions, drafts, spam, transients, and orphaned data.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Database_Cleaner {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        add_action('wp_ajax_goodhost_db_clean', [$this, 'ajax_clean']);
        add_action('wp_ajax_goodhost_db_optimize', [$this, 'ajax_optimize']);
    }
    
    public function register_module($modules) { $modules['database-cleaner'] = ['name' => 'Database Cleaner', 'description' => 'Clean revisions, spam, transients', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-database')]; return $modules; }
    
    public function add_admin_menu() {
        $parent = menu_page_url('goodhost', false) ? 'goodhost' : 'tools.php';
        add_submenu_page($parent, 'Database Cleaner', 'Database Cleaner', 'manage_options', 'goodhost-database', [$this, 'settings_page']);
    }
    
    private function get_counts() {
        global $wpdb;
        return [
            'revisions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"),
            'auto_drafts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"),
            'trashed_posts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'"),
            'spam_comments' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'"),
            'trashed_comments' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'"),
            'expired_transients' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()"),
            'all_transients' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"),
            'orphaned_postmeta' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL"),
            'orphaned_commentmeta' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL"),
        ];
    }
    
    private function format_bytes($bytes, $precision = 2) { $units = ['B', 'KB', 'MB', 'GB']; $bytes = max($bytes, 0); $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); $pow = min($pow, count($units) - 1); return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow]; }
    
    public function ajax_clean() {
        check_ajax_referer('goodhost_db_clean', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $type = sanitize_text_field($_POST['type']);
        $deleted = 0;
        switch ($type) {
            case 'revisions': $deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'"); break;
            case 'auto_drafts': $deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'"); break;
            case 'trashed_posts': $deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'"); break;
            case 'spam_comments': $deleted = $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'"); break;
            case 'trashed_comments': $deleted = $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'"); break;
            case 'expired_transients': $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()"); break;
            case 'all_transients': $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"); break;
            case 'orphaned_postmeta': $deleted = $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL"); break;
            case 'orphaned_commentmeta': $deleted = $wpdb->query("DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL"); break;
            case 'all':
                $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
                $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
                $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'");
                $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
                $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'");
                $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'");
                $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL");
                $wpdb->query("DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL");
                $deleted = 'all'; break;
        }
        wp_send_json_success(['deleted' => $deleted, 'counts' => $this->get_counts()]);
    }
    
    public function ajax_optimize() {
        check_ajax_referer('goodhost_db_clean', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $optimized = 0;
        foreach ($tables as $table) { if ($table['Data_free'] > 0) { $wpdb->query("OPTIMIZE TABLE `{$table['Name']}`"); $optimized++; } }
        wp_send_json_success(['optimized' => $optimized]);
    }
    
    public function settings_page() {
        $counts = $this->get_counts();
        global $wpdb;
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $total_size = 0; $total_overhead = 0;
        foreach ($tables as $t) { $total_size += $t['Data_length'] + $t['Index_length']; $total_overhead += $t['Data_free']; }
        ?>
        <div class="wrap"><h1>Database Cleaner</h1>
        <style>.gh-sum{background:#f0f0f1;padding:20px;margin-bottom:20px;max-width:600px}.gh-sum h3{margin-top:0}.gh-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;max-width:1000px}.gh-card{background:#fff;border:1px solid #ccd0d4;padding:20px}.gh-card h3{margin-top:0}.gh-item{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eee}.gh-item:last-child{border:0}.gh-item .num{font-weight:bold;min-width:50px;text-align:right;margin-right:15px}.gh-item .num.zero{color:#46b450}</style>
        <div class="gh-sum"><h3>Database Summary</h3><p><strong>Total Size:</strong> <?php echo $this->format_bytes($total_size); ?></p><p><strong>Overhead:</strong> <?php echo $this->format_bytes($total_overhead); ?></p><p><button class="button" onclick="optimizeTables()">Optimize All Tables</button> <span id="opt-status"></span></p></div>
        <div class="gh-grid">
            <div class="gh-card"><h3>Posts</h3>
                <div class="gh-item"><span>Revisions</span><span class="num <?php echo $counts['revisions']===0?'zero':''; ?>" id="c-revisions"><?php echo number_format($counts['revisions']); ?></span><button class="button" onclick="cleanItem('revisions')" <?php echo $counts['revisions']===0?'disabled':''; ?>>Clean</button></div>
                <div class="gh-item"><span>Auto Drafts</span><span class="num <?php echo $counts['auto_drafts']===0?'zero':''; ?>" id="c-auto_drafts"><?php echo number_format($counts['auto_drafts']); ?></span><button class="button" onclick="cleanItem('auto_drafts')" <?php echo $counts['auto_drafts']===0?'disabled':''; ?>>Clean</button></div>
                <div class="gh-item"><span>Trashed Posts</span><span class="num <?php echo $counts['trashed_posts']===0?'zero':''; ?>" id="c-trashed_posts"><?php echo number_format($counts['trashed_posts']); ?></span><button class="button" onclick="cleanItem('trashed_posts')" <?php echo $counts['trashed_posts']===0?'disabled':''; ?>>Clean</button></div>
            </div>
            <div class="gh-card"><h3>Comments</h3>
                <div class="gh-item"><span>Spam</span><span class="num <?php echo $counts['spam_comments']===0?'zero':''; ?>" id="c-spam_comments"><?php echo number_format($counts['spam_comments']); ?></span><button class="button" onclick="cleanItem('spam_comments')" <?php echo $counts['spam_comments']===0?'disabled':''; ?>>Clean</button></div>
                <div class="gh-item"><span>Trashed</span><span class="num <?php echo $counts['trashed_comments']===0?'zero':''; ?>" id="c-trashed_comments"><?php echo number_format($counts['trashed_comments']); ?></span><button class="button" onclick="cleanItem('trashed_comments')" <?php echo $counts['trashed_comments']===0?'disabled':''; ?>>Clean</button></div>
            </div>
            <div class="gh-card"><h3>Transients</h3>
                <div class="gh-item"><span>Expired</span><span class="num <?php echo $counts['expired_transients']===0?'zero':''; ?>" id="c-expired_transients"><?php echo number_format($counts['expired_transients']); ?></span><button class="button" onclick="cleanItem('expired_transients')" <?php echo $counts['expired_transients']===0?'disabled':''; ?>>Clean</button></div>
                <div class="gh-item"><span>All Transients</span><span class="num" id="c-all_transients"><?php echo number_format($counts['all_transients']); ?></span><button class="button" onclick="cleanItem('all_transients')">Clean All</button></div>
            </div>
            <div class="gh-card"><h3>Orphaned Data</h3>
                <div class="gh-item"><span>Post Meta</span><span class="num <?php echo $counts['orphaned_postmeta']===0?'zero':''; ?>" id="c-orphaned_postmeta"><?php echo number_format($counts['orphaned_postmeta']); ?></span><button class="button" onclick="cleanItem('orphaned_postmeta')" <?php echo $counts['orphaned_postmeta']===0?'disabled':''; ?>>Clean</button></div>
                <div class="gh-item"><span>Comment Meta</span><span class="num <?php echo $counts['orphaned_commentmeta']===0?'zero':''; ?>" id="c-orphaned_commentmeta"><?php echo number_format($counts['orphaned_commentmeta']); ?></span><button class="button" onclick="cleanItem('orphaned_commentmeta')" <?php echo $counts['orphaned_commentmeta']===0?'disabled':''; ?>>Clean</button></div>
            </div>
        </div>
        <p style="margin-top:20px"><button class="button button-primary button-hero" onclick="cleanAll()">Clean All</button></p>
        </div>
        <script>
        function cleanItem(type){var btn=event.target;btn.disabled=true;btn.textContent='...';fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_db_clean&type='+type+'&_wpnonce=<?php echo wp_create_nonce('goodhost_db_clean'); ?>'}).then(r=>r.json()).then(res=>{if(res.success){btn.textContent='Done!';var c=res.data.counts;for(var k in c){var el=document.getElementById('c-'+k);if(el){el.textContent=c[k].toLocaleString();if(c[k]===0)el.classList.add('zero');}}}setTimeout(function(){btn.textContent='Clean';btn.disabled=false;},1500);});}
        function cleanAll(){if(!confirm('Clean all?'))return;var btn=event.target;btn.disabled=true;btn.textContent='Cleaning...';fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_db_clean&type=all&_wpnonce=<?php echo wp_create_nonce('goodhost_db_clean'); ?>'}).then(r=>r.json()).then(res=>{if(res.success){btn.textContent='Done!';setTimeout(function(){location.reload();},1000);}});}
        function optimizeTables(){var btn=event.target;var st=document.getElementById('opt-status');btn.disabled=true;st.textContent='Optimizing...';fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_db_optimize&_wpnonce=<?php echo wp_create_nonce('goodhost_db_clean'); ?>'}).then(r=>r.json()).then(res=>{st.textContent=res.success?'Optimized '+res.data.optimized+' tables':'Error';btn.disabled=false;});}
        </script>
    <?php }
}
new GoodHost_Database_Cleaner();
