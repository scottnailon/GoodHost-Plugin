<?php
/**
 * Plugin Name: GoodHost - SMTP Mailer
 * Plugin URI: https://goodhost.com.au
 * Description: Configure SMTP for reliable email delivery. Works with Gmail, SendGrid, Mailgun, and any SMTP server.
 * Version: 1.0.0
 * Author: Sites By Design
 * Author URI: https://sitesbydesign.com.au
 * License: GPL v2 or later
 * Requires Plugins: goodhost
 */

if (!defined('ABSPATH')) { exit; }

class GoodHost_SMTP_Mailer {
    private $options;
    
    public function __construct() {
        $this->options = get_option('goodhost_smtp', $this->get_defaults());
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('goodhost_register_modules', [$this, 'register_module']);
        if (!empty($this->options['enabled'])) {
            add_action('phpmailer_init', [$this, 'configure_phpmailer'], 10, 1);
            add_filter('wp_mail_from', [$this, 'set_from_email']);
            add_filter('wp_mail_from_name', [$this, 'set_from_name']);
        }
        add_action('wp_ajax_goodhost_test_email', [$this, 'ajax_test_email']);
    }
    
    public function get_defaults() { return ['enabled' => 0, 'host' => '', 'port' => 587, 'encryption' => 'tls', 'auth' => 1, 'username' => '', 'password' => '', 'from_email' => get_option('admin_email'), 'from_name' => get_bloginfo('name')]; }
    public function register_module($modules) { $modules['smtp-mailer'] = ['name' => 'SMTP Mailer', 'description' => 'Configure SMTP email delivery', 'active' => true, 'settings_url' => admin_url('admin.php?page=goodhost-smtp')]; return $modules; }
    public function add_admin_menu() { add_submenu_page('goodhost', 'SMTP Mailer', 'SMTP Mailer', 'manage_options', 'goodhost-smtp', [$this, 'settings_page']); }
    public function register_settings() { register_setting('goodhost_smtp', 'goodhost_smtp', ['sanitize_callback' => [$this, 'sanitize_options']]); }
    
    public function sanitize_options($input) {
        $s = ['enabled' => isset($input['enabled']) ? 1 : 0, 'host' => sanitize_text_field($input['host'] ?? ''), 'port' => intval($input['port'] ?? 587), 'encryption' => in_array($input['encryption'] ?? '', ['none', 'ssl', 'tls']) ? $input['encryption'] : 'tls', 'auth' => isset($input['auth']) ? 1 : 0, 'username' => sanitize_text_field($input['username'] ?? ''), 'from_email' => sanitize_email($input['from_email'] ?? ''), 'from_name' => sanitize_text_field($input['from_name'] ?? '')];
        $s['password'] = !empty($input['password']) ? $input['password'] : (get_option('goodhost_smtp', [])['password'] ?? '');
        return $s;
    }
    
    public function configure_phpmailer($phpmailer) {
        $phpmailer->isSMTP();
        $phpmailer->Host = $this->options['host'];
        $phpmailer->Port = $this->options['port'];
        if ($this->options['encryption'] !== 'none') { $phpmailer->SMTPSecure = $this->options['encryption']; } else { $phpmailer->SMTPSecure = ''; $phpmailer->SMTPAutoTLS = false; }
        if (!empty($this->options['auth'])) { $phpmailer->SMTPAuth = true; $phpmailer->Username = $this->options['username']; $phpmailer->Password = $this->options['password']; } else { $phpmailer->SMTPAuth = false; }
    }
    
    public function set_from_email($email) { return !empty($this->options['from_email']) ? $this->options['from_email'] : $email; }
    public function set_from_name($name) { return !empty($this->options['from_name']) ? $this->options['from_name'] : $name; }
    
    public function ajax_test_email() {
        check_ajax_referer('goodhost_smtp', '_wpnonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
        $to = sanitize_email($_POST['to']);
        if (!is_email($to)) wp_send_json_error('Invalid email');
        $subject = 'GoodHost SMTP Test - ' . get_bloginfo('name');
        $message = "This is a test email from GoodHost SMTP Mailer.\n\nIf you're reading this, your SMTP settings are working!\n\nSent from: " . home_url();
        $result = wp_mail($to, $subject, $message);
        if ($result) wp_send_json_success('Test email sent to ' . $to);
        else { global $phpmailer; wp_send_json_error('Failed. ' . (isset($phpmailer) && $phpmailer->ErrorInfo ? $phpmailer->ErrorInfo : '')); }
    }
    
    public function settings_page() {
        $presets = ['gmail' => ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls'], 'outlook' => ['host' => 'smtp-mail.outlook.com', 'port' => 587, 'encryption' => 'tls'], 'sendgrid' => ['host' => 'smtp.sendgrid.net', 'port' => 587, 'encryption' => 'tls'], 'mailgun' => ['host' => 'smtp.mailgun.org', 'port' => 587, 'encryption' => 'tls']];
        ?>
        <div class="wrap"><h1>SMTP Mailer</h1>
        <form method="post" action="options.php" style="max-width:700px"><?php settings_fields('goodhost_smtp'); ?>
        <style>.gh-card{background:#fff;border:1px solid #ccd0d4;padding:20px;margin-bottom:20px}.gh-card h3{margin-top:0}.gh-presets{margin-bottom:15px}.gh-test{background:#f0f0f1;padding:20px;margin-top:20px}.gh-status{padding:10px;margin-top:10px;display:none}.gh-status.success{background:#d4edda;border:1px solid #28a745}.gh-status.error{background:#f8d7da;border:1px solid #dc3545}</style>
        <div class="gh-card"><h3>Quick Setup</h3><div class="gh-presets"><select id="smtp-preset"><option value="">-- Select Provider --</option><option value="gmail">Gmail</option><option value="outlook">Outlook/Office 365</option><option value="sendgrid">SendGrid</option><option value="mailgun">Mailgun</option></select> <button type="button" class="button" onclick="applyPreset()">Apply</button></div></div>
        <div class="gh-card"><h3>SMTP Settings</h3>
            <p><label><input type="checkbox" name="goodhost_smtp[enabled]" value="1" <?php checked(!empty($this->options['enabled'])); ?>> <strong>Enable SMTP</strong></label></p>
            <table class="form-table"><tr><th>Host</th><td><input type="text" name="goodhost_smtp[host]" id="host" value="<?php echo esc_attr($this->options['host']); ?>" class="regular-text"></td></tr>
            <tr><th>Port</th><td><input type="number" name="goodhost_smtp[port]" id="port" value="<?php echo esc_attr($this->options['port']); ?>" style="width:80px"></td></tr>
            <tr><th>Encryption</th><td><select name="goodhost_smtp[encryption]" id="encryption"><option value="tls" <?php selected($this->options['encryption'], 'tls'); ?>>TLS</option><option value="ssl" <?php selected($this->options['encryption'], 'ssl'); ?>>SSL</option><option value="none" <?php selected($this->options['encryption'], 'none'); ?>>None</option></select></td></tr>
            <tr><th>Authentication</th><td><label><input type="checkbox" name="goodhost_smtp[auth]" value="1" <?php checked(!empty($this->options['auth'])); ?>> Use authentication</label></td></tr>
            <tr><th>Username</th><td><input type="text" name="goodhost_smtp[username]" value="<?php echo esc_attr($this->options['username']); ?>" class="regular-text"></td></tr>
            <tr><th>Password</th><td><input type="password" name="goodhost_smtp[password]" value="" class="regular-text" placeholder="<?php echo !empty($this->options['password']) ? '********' : ''; ?>"><br><small>Leave blank to keep existing</small></td></tr></table>
        </div>
        <div class="gh-card"><h3>From Address</h3>
            <table class="form-table"><tr><th>From Email</th><td><input type="email" name="goodhost_smtp[from_email]" value="<?php echo esc_attr($this->options['from_email']); ?>" class="regular-text"></td></tr>
            <tr><th>From Name</th><td><input type="text" name="goodhost_smtp[from_name]" value="<?php echo esc_attr($this->options['from_name']); ?>" class="regular-text"></td></tr></table>
        </div>
        <?php submit_button(); ?></form>
        <div class="gh-test"><h3>Send Test Email</h3><p><input type="email" id="test-email" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text"> <button class="button button-primary" onclick="sendTestEmail()">Send Test</button></p><div id="test-status" class="gh-status"></div></div>
        </div>
        <script>
        var presets=<?php echo json_encode($presets); ?>;
        function applyPreset(){var p=document.getElementById('smtp-preset').value;if(p&&presets[p]){document.getElementById('host').value=presets[p].host;document.getElementById('port').value=presets[p].port;document.getElementById('encryption').value=presets[p].encryption;}}
        function sendTestEmail(){var email=document.getElementById('test-email').value;var st=document.getElementById('test-status');st.style.display='block';st.className='gh-status';st.textContent='Sending...';fetch('<?php echo admin_url('admin-ajax.php'); ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=goodhost_test_email&to='+encodeURIComponent(email)+'&_wpnonce=<?php echo wp_create_nonce('goodhost_smtp'); ?>'}).then(r=>r.json()).then(res=>{st.className='gh-status '+(res.success?'success':'error');st.textContent=res.data;});}
        </script>
    <?php }
}
new GoodHost_SMTP_Mailer();
