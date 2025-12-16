<?php
/**
 * Plugin Name: GoodHost - Broken Link Checker
 * Plugin URI: https://goodhost.com.au
 * Description: Scan your content for broken links. Find and fix dead URLs in posts, pages, and comments.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Broken_Link_Checker {
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'goodhost_links';
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        add_action('wp_ajax_goodhost_scan_links', [$this, 'ajax_scan_links']);
        add_action('wp_ajax_goodhost_check_link', [$this, 'ajax_check_link']);
        add_action('wp_ajax_goodhost_delete_link', [$this, 'ajax_delete_link']);
        add_action('wp_ajax_goodhost_recheck_link', [$this, 'ajax_recheck_link']);
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    
    public function register_module($modules) { $modules['broken-links'] = ['name' => 'Broken Link Checker', 'description' => 'Find and fix dead links', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-broken-links')]; return $modules; }
    
    public function activate() {
        global $wpdb;
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->table_name} (id bigint(20) AUTO_INCREMENT, url varchar(2000) NOT NULL, url_hash varchar(32) NOT NULL, status_code int(3) DEFAULT NULL, status_text varchar(100) DEFAULT '', source_id bigint(20) NOT NULL, source_type varchar(20) NOT NULL, source_field varchar(50) NOT NULL, link_text varchar(500) DEFAULT '', is_broken tinyint(1) DEFAULT 0, last_checked datetime DEFAULT NULL, created_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY url_source (url_hash, source_id, source_type), KEY is_broken (is_broken)) " . $wpdb->get_charset_collate());
    }
    
    public function add_admin_menu() {
        $parent = menu_page_url('goodhost', false) ? 'goodhost' : 'tools.php';
        add_submenu_page($parent, 'Broken Link Checker', 'Broken Links', 'manage_options', 'goodhost-broken-links', [$this, 'settings_page']);
    }
    
    private function extract_links($content) {
        $links = [];
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) { if (!preg_match('/^(#|javascript:|mailto:|tel:)/i', $m[1])) $links[] = ['url' => $m[1], 'text' => substr(strip_tags($m[2]), 0, 500)]; }
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/is', $content, $img);
        foreach ($img[1] as $url) { if (!preg_match('/^(data:)/i', $url)) $links[] = ['url' => $url, 'text' => '[Image]']; }
        return $links;
    }
    
    private function check_url($url) {
        if (strpos($url, '//') === 0) $url = 'https:' . $url;
        elseif (strpos($url, '/') === 0) $url = home_url($url);
        $response = wp_remote_head($url, ['timeout' => 10, 'redirection' => 5, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (compatible; GoodHost Link Checker)']);
        if (is_wp_error($response)) return ['status_code' => 0, 'status_text' => $response->get_error_message(), 'is_broken' => 1];
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 405 || $code === 0) {
            $response = wp_remote_get($url, ['timeout' => 10, 'redirection' => 5, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (compatible; GoodHost Link Checker)']);
            if (!is_wp_error($response)) $code = wp_remote_retrieve_response_code($response);
        }
        return ['status_code' => $code, 'status_text' => wp_remote_retrieve_response_message($response), 'is_broken' => ($code >= 400 || $code === 0) ? 1 : 0];
    }
    
    public function ajax_scan_links() {
        check_ajax_referer('goodhost_links', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $offset = intval($_POST['offset'] ?? 0);
        $posts = $wpdb->get_results($wpdb->prepare("SELECT ID, post_content, post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post', 'page') ORDER BY ID ASC LIMIT 10 OFFSET %d", $offset));
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post', 'page')");
        $found = 0;
        foreach ($posts as $post) {
            foreach ($this->extract_links($post->post_content) as $link) {
                $hash = md5($link['url']);
                if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE url_hash = %s AND source_id = %d AND source_type = %s", $hash, $post->ID, $post->post_type))) {
                    $wpdb->insert($this->table_name, ['url' => $link['url'], 'url_hash' => $hash, 'source_id' => $post->ID, 'source_type' => $post->post_type, 'source_field' => 'post_content', 'link_text' => $link['text']]);
                    $found++;
                }
            }
        }
        wp_send_json_success(['offset' => $offset + 10, 'total' => $total, 'links_found' => $found, 'done' => ($offset + 10 >= $total)]);
    }
    
    public function ajax_check_link() {
        check_ajax_referer('goodhost_links', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $link = $wpdb->get_row("SELECT * FROM {$this->table_name} WHERE last_checked IS NULL OR last_checked < DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY last_checked ASC LIMIT 1");
        if (!$link) wp_send_json_success(['done' => true]);
        $result = $this->check_url($link->url);
        $wpdb->update($this->table_name, ['status_code' => $result['status_code'], 'status_text' => $result['status_text'], 'is_broken' => $result['is_broken'], 'last_checked' => current_time('mysql')], ['id' => $link->id]);
        $remaining = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE last_checked IS NULL OR last_checked < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $broken = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE is_broken = 1");
        wp_send_json_success(['done' => false, 'remaining' => $remaining, 'total' => $total, 'broken' => $broken, 'last_url' => $link->url, 'last_status' => $result['status_code']]);
    }
    
    public function ajax_recheck_link() {
        check_ajax_referer('goodhost_links', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $link = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", intval($_POST['id'])));
        if (!$link) wp_send_json_error('Link not found');
        $result = $this->check_url($link->url);
        $wpdb->update($this->table_name, ['status_code' => $result['status_code'], 'status_text' => $result['status_text'], 'is_broken' => $result['is_broken'], 'last_checked' => current_time('mysql')], ['id' => $link->id]);
        wp_send_json_success($result);
    }
    
    public function ajax_delete_link() {
        check_ajax_referer('goodhost_links', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $wpdb->delete($this->table_name, ['id' => intval($_POST['id'])]);
        wp_send_json_success();
    }
    
    public function settings_page() {
        global $wpdb;
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'broken';
        $where = $filter === 'broken' ? 'WHERE is_broken = 1' : ($filter === 'unchecked' ? 'WHERE last_checked IS NULL' : '');
        $links = $wpdb->get_results("SELECT l.*, p.post_title FROM {$this->table_name} l LEFT JOIN {$wpdb->posts} p ON l.source_id = p.ID $where ORDER BY l.is_broken DESC, l.last_checked DESC LIMIT 200");
        $stats = ['total' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}"), 'broken' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE is_broken = 1"), 'unchecked' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE last_checked IS NULL")];
        ?>
        <div class="wrap"><h1>Broken Link Checker</h1>
        <style>.gh-stats{display:flex;gap:20px;margin:20px 0}.gh-stat{background:#fff;border:1px solid #ccd0d4;padding:20px;min-width:120px;text-align:center}.gh-stat .num{font-size:32px;font-weight:bold}.gh-stat.broken .num{color:#d63638}.gh-controls{margin:20px 0}.gh-controls button{margin-right:10px}.gh-progress{display:none;margin:20px 0;padding:15px;background:#f0f0f1;max-width:600px}.gh-progress-bar{background:#ccc;height:20px;border-radius:3px;overflow:hidden;margin:10px 0}.gh-progress-fill{background:#2271b1;height:100%;width:0%;transition:width .3s}.gh-filters{margin:20px 0}.gh-filters a{margin-right:15px;text-decoration:none}.gh-filters a.active{font-weight:bold}.gh-table{max-width:1200px}.gh-table .url{max-width:400px;word-break:break-all}.gh-table .status-ok{color:#46b450}.gh-table .status-broken{color:#d63638;font-weight:bold}</style>
        <div class="gh-stats">
            <div class="gh-stat"><div class="num"><?php echo number_format($stats['total']); ?></div><div>Total Links</div></div>
            <div class="gh-stat broken"><div class="num"><?php echo number_format($stats['broken']); ?></div><div>Broken</div></div>
            <div class="gh-stat"><div class="num"><?php echo number_format($stats['unchecked']); ?></div><div>Unchecked</div></div>
        </div>
        <div class="gh-controls"><button class="button button-primary" onclick="startScan()">Scan for Links</button><button class="button" onclick="startCheck()">Check All Links</button></div>
        <div class="gh-progress" id="progress-box"><div id="progress-text">Scanning...</div><div class="gh-progress-bar"><div class="gh-progress-fill" id="progress-fill"></div></div><div id="progress-detail"></div></div>
        <div class="gh-filters"><strong>Filter:</strong>
            <a href="?page=goodhost-broken-links&filter=broken" class="<?php echo $filter === 'broken' ? 'active' : ''; ?>">Broken (<?php echo $stats['broken']; ?>)</a>
            <a href="?page=goodhost-broken-links&filter=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">All (<?php echo $stats['total']; ?>)</a>
            <a href="?page=goodhost-broken-links&filter=unchecked" class="<?php echo $filter === 'unchecked' ? 'active' : ''; ?>">Unchecked (<?php echo $stats['unchecked']; ?>)</a>
        </div>
        <?php if (empty($links)) : ?><p><?php echo $filter === 'broken' ? 'No broken links found!' : 'No links found. Click "Scan for Links" to start.'; ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat fixed striped gh-table"><thead><tr><th style="width:35%">URL</th><th style="width:15%">Status</th><th style="width:20%">Found In</th><th style="width:15%">Link Text</th><th style="width:15%">Actions</th></tr></thead><tbody>
        <?php foreach ($links as $link) : ?>
            <tr id="link-row-<?php echo $link->id; ?>">
                <td class="url"><a href="<?php echo esc_url($link->url); ?>" target="_blank" rel="noopener"><?php echo esc_html(substr($link->url, 0, 80)); ?><?php echo strlen($link->url) > 80 ? '...' : ''; ?></a></td>
                <td><?php if ($link->last_checked) : ?><span class="<?php echo $link->is_broken ? 'status-broken' : 'status-ok'; ?>"><?php echo $link->status_code; ?> <?php echo esc_html($link->status_text); ?></span><?php else : ?><span style="color:#666">Not checked</span><?php endif; ?></td>
                <td><?php if ($link->post_title) : ?><a href="<?php echo get_edit_post_link($link->source_id); ?>" target="_blank"><?php echo esc_html(substr($link->post_title, 0, 40)); ?></a><?php else : ?><?php echo ucfirst($link->source_type); ?> #<?php echo $link->source_id; ?><?php endif; ?></td>
                <td><?php echo esc_html(substr($link->link_text, 0, 30)); ?></td>
                <td><button class="button button-small" onclick="recheckLink(<?php echo $link->id; ?>)">Recheck</button> <button class="button button-small" onclick="dismissLink(<?php echo $link->id; ?>)" style="color:#d63638">Dismiss</button></td>
            </tr>
        <?php endforeach; ?></tbody></table>
        <?php endif; ?></div>
        <script>
        var scanning=false,checking=false;
        function startScan(){if(scanning)return;scanning=true;document.getElementById('progress-box').style.display='block';document.getElementById('progress-text').textContent='Scanning posts for links...';scanBatch(0);}
        function scanBatch(offset){fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_scan_links&offset='+offset+'&_wpnonce=<?php echo wp_create_nonce('goodhost_links'); ?>'}).then(r=>r.json()).then(res=>{if(res.success){var pct=Math.round((res.data.offset/res.data.total)*100);document.getElementById('progress-fill').style.width=pct+'%';document.getElementById('progress-detail').textContent='Scanned '+res.data.offset+' of '+res.data.total+' posts. Found '+res.data.links_found+' new links.';if(!res.data.done)scanBatch(res.data.offset);else{document.getElementById('progress-text').textContent='Scan complete!';scanning=false;setTimeout(function(){location.reload();},1000);}}});}
        function startCheck(){if(checking)return;checking=true;document.getElementById('progress-box').style.display='block';document.getElementById('progress-text').textContent='Checking links...';checkNext();}
        function checkNext(){fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_check_link&_wpnonce=<?php echo wp_create_nonce('goodhost_links'); ?>'}).then(r=>r.json()).then(res=>{if(res.success){if(res.data.done){document.getElementById('progress-text').textContent='Check complete!';document.getElementById('progress-fill').style.width='100%';checking=false;setTimeout(function(){location.reload();},1000);}else{var checked=res.data.total-res.data.remaining;var pct=Math.round((checked/res.data.total)*100);document.getElementById('progress-fill').style.width=pct+'%';document.getElementById('progress-detail').textContent='Checked '+checked+' of '+res.data.total+'. Broken: '+res.data.broken+'. Last: '+res.data.last_status;checkNext();}}});}
        function recheckLink(id){var btn=event.target;btn.disabled=true;btn.textContent='...';fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_recheck_link&id='+id+'&_wpnonce=<?php echo wp_create_nonce('goodhost_links'); ?>'}).then(r=>r.json()).then(res=>{if(res.success)location.reload();else{btn.disabled=false;btn.textContent='Recheck';}});}
        function dismissLink(id){if(!confirm('Remove this link from tracking?'))return;fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_delete_link&id='+id+'&_wpnonce=<?php echo wp_create_nonce('goodhost_links'); ?>'}).then(function(){document.getElementById('link-row-'+id).remove();});}
        </script>
    <?php }
}
new GoodHost_Broken_Link_Checker();
