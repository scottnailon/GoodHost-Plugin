<?php
/**
 * Plugin Name: GoodHost - Code Snippets
 * Plugin URI: https://goodhost.com.au
 * Description: Add custom PHP, CSS, and JavaScript code without editing theme files.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_Code_Snippets {
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'goodhost_snippets';
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('init', [$this, 'execute_php_snippets'], 1);
        add_action('wp_head', [$this, 'output_header_snippets'], 99);
        add_action('wp_footer', [$this, 'output_footer_snippets'], 99);
        add_action('admin_head', [$this, 'output_admin_header_snippets']);
        add_action('wp_ajax_goodhost_save_snippet', [$this, 'ajax_save_snippet']);
        add_action('wp_ajax_goodhost_delete_snippet', [$this, 'ajax_delete_snippet']);
        add_action('wp_ajax_goodhost_toggle_snippet', [$this, 'ajax_toggle_snippet']);
    }
    
    public function activate() {
        global $wpdb;
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$this->table_name} (id bigint(20) AUTO_INCREMENT, title varchar(200) NOT NULL, description text DEFAULT '', code longtext NOT NULL, type varchar(20) NOT NULL DEFAULT 'php', location varchar(20) NOT NULL DEFAULT 'everywhere', priority int(11) NOT NULL DEFAULT 10, active tinyint(1) NOT NULL DEFAULT 0, created_at datetime DEFAULT CURRENT_TIMESTAMP, updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY active (active), KEY type (type)) " . $wpdb->get_charset_collate());
    }
    
    public function register_module($modules) { $modules['code-snippets'] = ['name' => 'Code Snippets', 'description' => 'Custom PHP/CSS/JS', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-snippets')]; return $modules; }
    
    public function add_admin_menu() {
        $parent = menu_page_url('goodhost', false) ? 'goodhost' : 'tools.php';
        add_submenu_page($parent, 'Code Snippets', 'Code Snippets', 'manage_options', 'goodhost-snippets', [$this, 'admin_page']);
    }
    
    private function get_snippets($type = null, $active_only = false) {
        global $wpdb;
        $where = [];
        if ($type) $where[] = $wpdb->prepare("type = %s", $type);
        if ($active_only) $where[] = "active = 1";
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        return $wpdb->get_results("SELECT * FROM {$this->table_name} $where_sql ORDER BY priority ASC, id ASC");
    }
    
    public function execute_php_snippets() {
        if (is_admin()) return;
        $snippets = $this->get_snippets('php', true);
        foreach ($snippets as $snippet) {
            if ($snippet->location === 'admin_only') continue;
            try { eval($snippet->code); } catch (Throwable $e) { error_log('GoodHost Snippet Error (' . $snippet->title . '): ' . $e->getMessage()); }
        }
    }
    
    public function output_header_snippets() {
        foreach ($this->get_snippets('css', true) as $snippet) { if (in_array($snippet->location, ['everywhere', 'frontend', 'header'])) echo "<style>\n" . $snippet->code . "\n</style>\n"; }
        foreach ($this->get_snippets('js', true) as $snippet) { if ($snippet->location === 'header') echo "<script>\n" . $snippet->code . "\n</script>\n"; }
        foreach ($this->get_snippets('html', true) as $snippet) { if ($snippet->location === 'header') echo $snippet->code . "\n"; }
    }
    
    public function output_footer_snippets() {
        foreach ($this->get_snippets('js', true) as $snippet) { if (in_array($snippet->location, ['everywhere', 'frontend', 'footer'])) echo "<script>\n" . $snippet->code . "\n</script>\n"; }
        foreach ($this->get_snippets('html', true) as $snippet) { if (in_array($snippet->location, ['everywhere', 'frontend', 'footer'])) echo $snippet->code . "\n"; }
    }
    
    public function output_admin_header_snippets() {
        foreach ($this->get_snippets('css', true) as $snippet) { if (in_array($snippet->location, ['everywhere', 'admin_only'])) echo "<style>\n" . $snippet->code . "\n</style>\n"; }
    }
    
    public function ajax_save_snippet() {
        check_ajax_referer('goodhost_snippets', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $id = intval($_POST['id'] ?? 0);
        $data = ['title' => sanitize_text_field($_POST['title']), 'description' => sanitize_textarea_field($_POST['description'] ?? ''), 'code' => wp_unslash($_POST['code']), 'type' => in_array($_POST['type'], ['php', 'css', 'js', 'html']) ? $_POST['type'] : 'php', 'location' => sanitize_text_field($_POST['location'] ?? 'everywhere'), 'priority' => intval($_POST['priority'] ?? 10), 'active' => isset($_POST['active']) ? 1 : 0];
        if ($data['type'] === 'php' && $data['active']) { $check = @eval('return true; ' . $data['code']); if ($check === false) wp_send_json_error('PHP syntax error detected.'); }
        if ($id > 0) $wpdb->update($this->table_name, $data, ['id' => $id]); else { $wpdb->insert($this->table_name, $data); $id = $wpdb->insert_id; }
        wp_send_json_success(['id' => $id]);
    }
    
    public function ajax_delete_snippet() {
        check_ajax_referer('goodhost_snippets', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $wpdb->delete($this->table_name, ['id' => intval($_POST['id'])]);
        wp_send_json_success();
    }
    
    public function ajax_toggle_snippet() {
        check_ajax_referer('goodhost_snippets', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        global $wpdb;
        $id = intval($_POST['id']);
        $active = intval($_POST['active']);
        if ($active) {
            $snippet = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id));
            if ($snippet && $snippet->type === 'php') { $check = @eval('return true; ' . $snippet->code); if ($check === false) wp_send_json_error('PHP syntax error. Cannot activate.'); }
        }
        $wpdb->update($this->table_name, ['active' => $active], ['id' => $id]);
        wp_send_json_success();
    }
    
    public function admin_page() {
        $snippets = $this->get_snippets();
        $editing = null;
        if (isset($_GET['edit'])) { global $wpdb; $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", intval($_GET['edit']))); }
        ?>
        <div class="wrap"><h1>Code Snippets</h1>
        <style>.gh-container{display:flex;gap:30px;flex-wrap:wrap}.gh-list{flex:1;min-width:400px}.gh-editor{flex:1;min-width:500px;max-width:800px}.gh-card{background:#fff;border:1px solid #ccd0d4;padding:15px;margin-bottom:10px;display:flex;align-items:center;gap:15px}.gh-card.inactive{opacity:.6}.gh-card .info{flex:1}.gh-card .info h4{margin:0 0 5px}.gh-card .meta{color:#666;font-size:12px}.gh-type{background:#f0f0f1;padding:3px 8px;border-radius:3px;font-size:11px;text-transform:uppercase}.gh-type.php{background:#8892bf;color:#fff}.gh-type.css{background:#264de4;color:#fff}.gh-type.js{background:#f7df1e;color:#000}.gh-type.html{background:#e34c26;color:#fff}.gh-editor-card{background:#fff;border:1px solid #ccd0d4;padding:20px}.gh-editor-card h3{margin-top:0}.gh-code{width:100%;min-height:300px;font-family:monospace;font-size:13px;padding:10px;border:1px solid #ccc;background:#1e1e1e;color:#d4d4d4}.gh-toggle{position:relative;width:44px;height:24px}.gh-toggle input{opacity:0;width:0;height:0}.gh-toggle .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px;transition:.3s}.gh-toggle .slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s}.gh-toggle input:checked+.slider{background:#2271b1}.gh-toggle input:checked+.slider:before{transform:translateX(20px)}</style>
        <div class="gh-container">
            <div class="gh-list"><h2>All Snippets</h2><p><a href="?page=goodhost-snippets" class="button button-primary">+ Add New Snippet</a></p>
            <?php if (empty($snippets)) : ?><p>No snippets yet. Create your first one!</p>
            <?php else : foreach ($snippets as $snippet) : ?>
                <div class="gh-card <?php echo $snippet->active ? '' : 'inactive'; ?>" id="snippet-<?php echo $snippet->id; ?>">
                    <label class="gh-toggle"><input type="checkbox" <?php checked($snippet->active); ?> onchange="toggleSnippet(<?php echo $snippet->id; ?>,this.checked)"><span class="slider"></span></label>
                    <div class="info"><h4><?php echo esc_html($snippet->title); ?></h4><div class="meta"><span class="gh-type <?php echo esc_attr($snippet->type); ?>"><?php echo strtoupper($snippet->type); ?></span> <?php echo esc_html($snippet->location); ?></div></div>
                    <div class="actions"><a href="?page=goodhost-snippets&edit=<?php echo $snippet->id; ?>" class="button button-small">Edit</a> <button type="button" class="button button-small" onclick="deleteSnippet(<?php echo $snippet->id; ?>)" style="color:#d63638">Delete</button></div>
                </div>
            <?php endforeach; endif; ?></div>
            <div class="gh-editor"><div class="gh-editor-card"><h3><?php echo $editing ? 'Edit Snippet' : 'New Snippet'; ?></h3>
                <form id="snippet-form"><input type="hidden" name="id" value="<?php echo $editing ? $editing->id : 0; ?>">
                <table class="form-table">
                    <tr><th><label for="title">Title</label></th><td><input type="text" name="title" id="title" class="regular-text" value="<?php echo $editing ? esc_attr($editing->title) : ''; ?>" required></td></tr>
                    <tr><th><label for="description">Description</label></th><td><input type="text" name="description" id="description" class="regular-text" value="<?php echo $editing ? esc_attr($editing->description) : ''; ?>" placeholder="Optional"></td></tr>
                    <tr><th><label for="type">Type</label></th><td><select name="type" id="type"><option value="php" <?php echo ($editing && $editing->type === 'php') ? 'selected' : ''; ?>>PHP</option><option value="css" <?php echo ($editing && $editing->type === 'css') ? 'selected' : ''; ?>>CSS</option><option value="js" <?php echo ($editing && $editing->type === 'js') ? 'selected' : ''; ?>>JavaScript</option><option value="html" <?php echo ($editing && $editing->type === 'html') ? 'selected' : ''; ?>>HTML</option></select></td></tr>
                    <tr><th><label for="location">Location</label></th><td><select name="location" id="location"><option value="everywhere" <?php echo ($editing && $editing->location === 'everywhere') ? 'selected' : ''; ?>>Everywhere</option><option value="frontend" <?php echo ($editing && $editing->location === 'frontend') ? 'selected' : ''; ?>>Frontend Only</option><option value="admin_only" <?php echo ($editing && $editing->location === 'admin_only') ? 'selected' : ''; ?>>Admin Only</option><option value="header" <?php echo ($editing && $editing->location === 'header') ? 'selected' : ''; ?>>Header</option><option value="footer" <?php echo ($editing && $editing->location === 'footer') ? 'selected' : ''; ?>>Footer</option></select></td></tr>
                    <tr><th><label for="priority">Priority</label></th><td><input type="number" name="priority" id="priority" class="small-text" value="<?php echo $editing ? intval($editing->priority) : 10; ?>" min="1" max="100"></td></tr>
                    <tr><th><label for="code">Code</label></th><td><p class="description" id="code-hint">Don't include &lt;?php tags for PHP snippets.</p><textarea name="code" id="code" class="gh-code" required><?php echo $editing ? esc_textarea($editing->code) : ''; ?></textarea></td></tr>
                    <tr><th><label>Status</label></th><td><label><input type="checkbox" name="active" value="1" <?php echo ($editing && $editing->active) ? 'checked' : ''; ?>> Active</label></td></tr>
                </table>
                <p><button type="submit" class="button button-primary">Save Snippet</button><?php if ($editing) : ?> <a href="?page=goodhost-snippets" class="button">Cancel</a><?php endif; ?></p><div id="save-status"></div>
                </form>
            </div></div>
        </div></div>
        <script>
        document.getElementById('snippet-form').addEventListener('submit',function(e){e.preventDefault();var st=document.getElementById('save-status');st.textContent='Saving...';var fd=new FormData(this);fd.append('action','goodhost_save_snippet');fd.append('_wpnonce','<?php echo wp_create_nonce('goodhost_snippets'); ?>');fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{if(res.success){st.style.color='green';st.textContent='Saved!';setTimeout(function(){window.location.href='?page=goodhost-snippets';},500);}else{st.style.color='red';st.textContent=res.data||'Error';}});});
        function toggleSnippet(id,active){fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_toggle_snippet&id='+id+'&active='+(active?1:0)+'&_wpnonce=<?php echo wp_create_nonce('goodhost_snippets'); ?>'}).then(r=>r.json()).then(res=>{if(!res.success){alert(res.data||'Error');location.reload();}else{document.getElementById('snippet-'+id).classList.toggle('inactive',!active);}});}
        function deleteSnippet(id){if(!confirm('Delete this snippet?'))return;fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_delete_snippet&id='+id+'&_wpnonce=<?php echo wp_create_nonce('goodhost_snippets'); ?>'}).then(r=>r.json()).then(res=>{if(res.success)document.getElementById('snippet-'+id).remove();});}
        document.getElementById('type').addEventListener('change',function(){var hint=document.getElementById('code-hint');switch(this.value){case 'php':hint.textContent="Don't include <?php tags.";break;case 'css':hint.textContent="CSS wrapped in style tags.";break;case 'js':hint.textContent="JS wrapped in script tags.";break;case 'html':hint.textContent="HTML output as-is.";break;}});
        </script>
    <?php }
}
new GoodHost_Code_Snippets();
