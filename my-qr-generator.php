<?php
/**
 * Plugin Name: My QR Code Generator
 * Description: Creates a QR code and a shortened link from any URL. Use the [my_qr_generator] shortcode on any page.
 * Version: 1.7
 * Author: Gemini & Rồng Con HG
 * Author URI: https://rongcon.net 
 * License: GPL2+
 * Text Domain: my-qr-generator
 * Copyright (c) 2021-2024 Gemini & Rồng Con HG (https://rongcon.net)
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html  
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants for easier path management
define('MQR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MQR_PLUGIN_PATH', plugin_dir_path(__FILE__));

class My_QR_Generator {

    /**
     * Constructor to hook into WordPress.
     */
    public function __construct() {
        // Add the admin menu page for management
        add_action('admin_menu', [$this, 'add_admin_menu']);
        // Register plugin settings for logo/watermark
        add_action('admin_init', [$this, 'register_settings']);
        // Register the custom post type for short links
        add_action('init', [$this, 'register_shortlink_cpt']);
        // Handle the redirection for short links
        add_action('template_redirect', [$this, 'shortlink_redirect']);
        
        // Handle the AJAX request for generating the link (for logged-in and non-logged-in users)
        add_action('wp_ajax_mqrg_generate_link', [$this, 'handle_ajax_generate_link']);
        add_action('wp_ajax_nopriv_mqrg_generate_link', [$this, 'handle_ajax_generate_link']);
        // AJAX to fetch current effective URL + remaining time for dynamic QR display
        add_action('wp_ajax_mqrg_get_effective', [$this, 'ajax_get_effective']);
        add_action('wp_ajax_nopriv_mqrg_get_effective', [$this, 'ajax_get_effective']);
        
        // Enqueue scripts and styles for the admin page and frontend
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Register the shortcode to display the generator on the frontend
        add_shortcode('my_qr_generator', [$this, 'render_shortcode']);
        // Shortcode for dynamic QR display with countdown
        add_shortcode('my_dynamic_qr', [$this, 'render_dynamic_shortcode']);

        // Admin post handler for saving shortlink updates (base URL and dynamic rules)
        add_action('admin_post_mqrg_save_shortlink', [$this, 'handle_admin_save_shortlink']);
        // Admin actions: export CSV and reset stats
        add_action('admin_post_mqrg_export_csv', [$this, 'handle_export_csv']);
        add_action('admin_post_mqrg_reset_stats', [$this, 'handle_reset_stats']);
        add_action('admin_post_mqrg_delete_shortlink', [$this, 'handle_delete_shortlink']);
        add_action('admin_post_mqrg_export_csv_all', [$this, 'handle_export_csv_all']);
        // Public endpoint for server-side QR PNG
        add_action('template_redirect', [$this, 'handle_qr_png_endpoint'], 5);
    }

    /**
     * Adds the plugin page to the WordPress admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            'Tạo mã QR',
            'Tạo mã QR',
            'manage_options',
            'my-qr-generator',
            [$this, 'render_admin_page'],
            'dashicons-camera-alt',
            25
        );
        // Submenu: Overview of shortlinks
        add_submenu_page(
            'my-qr-generator',
            'Danh sách link',
            'Danh sách link',
            'manage_options',
            'mqrg-links',
            [$this, 'render_links_overview']
        );
        // Submenu: Manage Dynamic Rules
        add_submenu_page(
            'my-qr-generator',
            'Quản lý link',
            'Quản lý link',
            'manage_options',
            'mqrg-manage',
            [$this, 'render_manage_dynamic']
        );
        // Submenu: Stats
        add_submenu_page(
            'my-qr-generator',
            'Thống kê',
            'Thống kê',
            'manage_options',
            'mqrg-stats',
            [$this, 'render_stats_page']
        );
        // Submenu: Settings
        add_submenu_page(
            'my-qr-generator',
            'Cài đặt',
            'Cài đặt',
            'manage_options',
            'mqrg-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Register plugin settings for logo/watermark.
     */
    public function register_settings() {
        register_setting('mqrg_settings_group', 'mqrg_logo_attachment_id', ['type' => 'integer', 'default' => 0]);
    }
    
    /**
     * Enqueues assets for the admin page.
     */
    public function enqueue_admin_assets($hook) {
        if ($hook === 'toplevel_page_my-qr-generator') {
            $this->enqueue_generator_assets();
            return;
        }
        // Load QR lib and styles on subpages that show QR previews
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
        if (
            in_array($hook, ['my-qr-generator_page_mqrg-manage','my-qr-generator_page_mqrg-links'], true)
            || in_array($page, ['mqrg-manage','mqrg-links'], true)
        ) {
            // Ensure the QRCode lib is present on our admin pages
            if (!wp_script_is('qrcode-js', 'registered')) {
                wp_register_script('qrcode-js', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js', [], '1.0.0', true);
            }
            wp_enqueue_script('qrcode-js');
            wp_enqueue_style('mqrg-style', MQR_PLUGIN_URL . 'assets/style.css', [], '1.4');
        }
        // Enqueue media uploader for Settings page
        if (
            in_array($hook, ['my-qr-generator_page_mqrg-settings'], true)
            || $page === 'mqrg-settings'
        ) {
            wp_enqueue_media();
            wp_enqueue_script('jquery');
        }
    }
    
    /**
     * Enqueues assets for the frontend if the shortcode is present.
     */
    public function enqueue_frontend_assets() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'my_qr_generator')) {
            $this->enqueue_generator_assets();
        }
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'my_dynamic_qr')) {
            $this->enqueue_dynamic_assets();
        }
    }
    
    /**
     * A unified function to enqueue all necessary scripts and styles.
     */
    private function enqueue_generator_assets() {
        // Enqueue the QR code library from CDN
        wp_enqueue_script('qrcode-js', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js', [], '1.0.0', true);

        // Enqueue our new external JS file, dependent on qrcode-js
        wp_enqueue_script('mqrg-script', MQR_PLUGIN_URL . 'assets/script.js', ['qrcode-js'], '1.4', true);
        
        // Enqueue our external CSS file
        wp_enqueue_style('mqrg-style', MQR_PLUGIN_URL . 'assets/style.css', [], '1.4');

        // Pass AJAX URL and nonce to our new external script
        wp_localize_script('mqrg-script', 'mqrg_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mqrg_nonce')
        ]);
    }

    /**
     * Enqueue assets for the dynamic QR shortcode
     */
    private function enqueue_dynamic_assets() {
        wp_enqueue_script('qrcode-js', 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js', [], '1.0.0', true);
        wp_enqueue_script('mqrg-dynamic', MQR_PLUGIN_URL . 'assets/dynamic.js', ['qrcode-js'], '1.0', true);
        wp_enqueue_style('mqrg-style', MQR_PLUGIN_URL . 'assets/style.css', [], '1.4');
        wp_localize_script('mqrg-dynamic', 'mqrg_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mqrg_nonce')
        ]);
    }

    /**
     * Registers the 'shortlink' Custom Post Type.
     * Changed the slug to 'url' and query_var to 'url'.
     */
    public function register_shortlink_cpt() {
        $args = [
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'query_var'           => 'url', // Changed query var for non-pretty permalinks
            'rewrite'             => ['slug' => 'url', 'with_front' => false], // Changed slug to 'url'
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'supports'            => ['title'],
            'show_in_rest'        => false,
        ];
        register_post_type('shortlink', $args);
    }

    /**
     * Redirects from the short URL to the original URL.
     */
    public function shortlink_redirect() {
        // Check for our custom query var 'url'
        $shortlink_slug = get_query_var('url');

        if ($shortlink_slug) {
            $args = [
                'name'        => $shortlink_slug,
                'post_type'   => 'shortlink',
                'post_status' => 'publish',
                'numberposts' => 1
            ];
            $posts = get_posts($args);
            if ($posts) {
                $post_id = $posts[0]->ID;
                // Chỉ kiểm tra token khi có tham số qr=1 (từ QR scan)
                // Link thường không có qr=1 nên luôn hợp lệ vĩnh viễn
                $is_qr_scan = isset($_GET['qr']) && $_GET['qr'] === '1';
                $rotate_interval = intval(get_post_meta($post_id, '_mqrg_rotate_interval', true));
                
                if ($is_qr_scan && $rotate_interval > 0) {
                    $token = isset($_GET['t']) ? sanitize_text_field(wp_unslash($_GET['t'])) : '';
                    $expected = $this->compute_rotation_token($post_id, null, $rotate_interval);
                    if (!$token || !hash_equals($expected, $token)) {
                        // Expired or invalid QR token
                        status_header(410);
                        nocache_headers();
                        echo '<!doctype html><meta charset="utf-8"><title>QR đã hết hạn</title><div style="font:16px/1.6 -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:24px;max-width:720px;margin:40px auto;">'
                           . '<h1 style="margin:0 0 8px;">Mã QR đã hết hiệu lực</h1>'
                           . '<p>Vui lòng quét lại mã QR mới nhất. Mã cũ đã hết hạn theo cấu hình thay đổi định kỳ.</p>'
                           . '</div>';
                        exit;
                    }
                }
                $destination = $this->get_effective_destination($post_id);
                if (!$destination) {
                    $destination = get_post_meta($post_id, '_original_url', true);
                }
                if ($destination) {
                    $this->increment_scan_count($post_id);
                    wp_redirect(esc_url_raw($destination), 301);
                    exit;
                }
            }
        }
        // Fallback for pretty permalinks
        elseif (is_singular('shortlink')) {
            global $post;
            $destination = $this->get_effective_destination($post->ID);
            if (!$destination) {
                $destination = get_post_meta($post->ID, '_original_url', true);
            }
            if ($destination) {
                $this->increment_scan_count($post->ID);
                wp_redirect(esc_url_raw($destination), 301);
                exit;
            }
        }
    }

    /**
     * Handles the AJAX request to generate a short link and QR code.
     */
    public function handle_ajax_generate_link() {
        check_ajax_referer('mqrg_nonce', 'nonce');
        $long_url = isset($_POST['long_url']) ? esc_url_raw(trim($_POST['long_url'])) : '';

        if (empty($long_url) || !filter_var($long_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => 'Vui lòng nhập một URL hợp lệ.']);
            return;
        }
        
        $existing_posts = get_posts([
            'post_type' => 'shortlink',
            'meta_key' => '_original_url',
            'meta_value' => $long_url,
            'posts_per_page' => 1,
            'post_status' => 'publish',
        ]);

        if (!empty($existing_posts)) {
            $post_id = $existing_posts[0]->ID;
        } else {
            $short_slug = wp_generate_password(6, false);
            $post_data = [
                'post_title'  => $long_url,
                'post_name'   => $short_slug,
                'post_type'   => 'shortlink',
                'post_status' => 'publish',
            ];
            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => 'Không thể tạo link rút gọn.']);
                return;
            }
            update_post_meta($post_id, '_original_url', $long_url);
        }
        
        // Use the post slug to construct the URL for both pretty and non-pretty permalinks
        $short_url = get_permalink($post_id);

        if (!get_option('mqrg_flushed')) {
            flush_rewrite_rules();
            update_option('mqrg_flushed', true);
        }

        wp_send_json_success(['short_url' => $short_url]);
    }
    
    /**
     * Renders the shortcode output on the frontend.
     */
    public function render_shortcode() {
        ob_start();
        echo '<h2 style="text-align: center;">Tạo mã QR Code và Liên kết rút gọn</h2>';
        echo '<p style="text-align: center;">Dán một đường dẫn website để tạo mã QR và link chia sẻ rút gọn.</p>';
        echo $this->get_generator_html();
        return ob_get_clean();
    }

    /**
     * Renders the dynamic QR display shortcode.
     * Usage: [my_dynamic_qr id="123"]
     */
    public function render_dynamic_shortcode($atts = []) {
        $atts = shortcode_atts([
            'id' => 0,
            'mode' => 'effective', // 'effective' or 'short'
            'refresh' => 0, // auto-refresh interval in seconds (0 = disabled)
        ], $atts, 'my_dynamic_qr');
        $post_id = intval($atts['id']);
        if (!$post_id || get_post_type($post_id) !== 'shortlink') {
            return '<p>Thiếu hoặc sai ID của mã QR.</p>';
        }
        $short_url = get_permalink($post_id);
        $mode = in_array($atts['mode'], ['effective','short'], true) ? $atts['mode'] : 'effective';
        $refresh = max(0, intval($atts['refresh']));
        ob_start();
        ?>
        <div class="mqrg-wrap mqrg-dynamic-wrap" data-post-id="<?php echo esc_attr($post_id); ?>" data-mode="<?php echo esc_attr($mode); ?>" data-short-url="<?php echo esc_attr($short_url); ?>" data-refresh="<?php echo esc_attr($refresh); ?>">
            <div class="mqrg-container">
                <div class="mqrg-result-section" style="margin: 0 auto;">
                    <div class="mqrg-qrcode-container">
                        <p class="mqrg-placeholder-text">Mã QR sẽ xuất hiện ở đây</p>
                    </div>
                    <div class="mqrg-result-actions" style="display:block; margin-top:16px; text-align:center;">
                        <div class="mqrg-countdown" style="font-size:16px; color:#333;">
                            <div style="margin-bottom:4px; font-weight:normal;">Thời gian cập nhật mã QR Code còn lại:</div>
                            <div style="font-size:32px; font-weight:bold; color:#0073aa;"><span class="mqrg-remaining">--:--</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Returns current effective destination URL and remaining seconds for a given shortlink post.
     */
    public function ajax_get_effective() {
        check_ajax_referer('mqrg_nonce', 'nonce');
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'shortlink') {
            wp_send_json_error(['message' => 'Invalid shortlink ID']);
        }
        $now = $this->now();
        $rotate_interval = intval(get_post_meta($post_id, '_mqrg_rotate_interval', true));
        $current_rule = null;
        $effective = '';
        $base_url = get_post_meta($post_id, '_original_url', true);
        $remaining = 0;
        $next_change = 0;
        $tokenized_short_url = '';
        $short_url = get_permalink($post_id);
        
        // Rotation-first: if interval is set, compute remaining time and tokenized URL
        if ($rotate_interval > 0) {
            $remaining = max(1, $rotate_interval - ($now % $rotate_interval));
            $next_change = $now + $remaining;
            $token = $this->compute_rotation_token($post_id, $now, $rotate_interval);
            $tokenized_short_url = add_query_arg(['qr' => '1', 't' => $token], $short_url);
        } else {
            // Không còn quy tắc động, QR vĩnh viễn
            $remaining = 0;
            $next_change = 0;
            $tokenized_short_url = '';
        }
        
        wp_send_json_success([
            'effective_url'     => $base_url ?: '',
            'remaining_seconds' => $remaining,
            'base_url'          => $base_url ?: '',
            'now'               => $now,
            'next_change'       => $next_change,
            'has_active_rule'   => !empty($current_rule),
            'rotation_interval' => $rotate_interval,
            'tokenized_short_url' => $tokenized_short_url,
            'has_rotation'      => $rotate_interval > 0,
        ]);
    }

    /**
     * Renders the HTML for the admin page.
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Tạo mã QR Code và Liên kết rút gọn</h1>
            <p>Dán một đường dẫn website để tạo mã QR và link chia sẻ rút gọn.</p>
            <?php echo $this->get_generator_html(); ?>
        </div>
        <?php
    }
    
    /**
     * Admin: Overview listing of shortlinks, destinations and stats.
     */
    public function render_links_overview() {
        if (!current_user_can('manage_options')) return;
        $posts = get_posts([
            'post_type'   => 'shortlink',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);
        ?>
        <div class="wrap">
            <h1>Danh sách link</h1>
            <p>Danh sách tất cả mã QR/shortlink đã tạo. Bạn có thể xem thống kê và cập nhật URL nguồn.</p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th>Slug</th>
                        <th>Shortcode</th>
                        <th>Link rút gọn</th>
                        <th>URL nguồn</th>
                        <th>Đích hiện tại</th>
                        <th style="width:80px;">Lượt quét</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($posts) : foreach ($posts as $p) :
                    $base = get_post_meta($p->ID, '_original_url', true);
                    $effective = $this->get_effective_destination($p->ID);
                    $count = intval(get_post_meta($p->ID, '_mqrg_scan_count', true));
                    $short = get_permalink($p->ID);
                    $count7 = $this->get_last_days_count($p->ID, 7);
                    $shortcode = '[my_dynamic_qr id="' . $p->ID . '" refresh="60"]';
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($p->ID); ?></strong></td>
                        <td><?php echo esc_html($p->post_name); ?></td>
                        <td>
                            <input type="text" readonly value="<?php echo esc_attr($shortcode); ?>" class="regular-text code" style="font-size:11px;" onclick="this.select();" />
                            <button type="button" class="button button-small mqrg-copy-shortcode" data-text="<?php echo esc_attr($shortcode); ?>" style="margin-left:4px;" title="Sao chép shortcode">
                                <span class="dashicons dashicons-clipboard" style="margin-top:2px;"></span>
                            </button>
                        </td>
                        <td><a href="<?php echo esc_url($short); ?>" target="_blank"><?php echo esc_html($short); ?></a></td>
                        <td style="max-width:360px; word-break:break-all;">
                            <div>&nbsp;<?php echo esc_html($base); ?></div>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:6px;">
                                <?php wp_nonce_field('mqrg_save_shortlink', 'mqrg_nonce_field'); ?>
                                <input type="hidden" name="action" value="mqrg_save_shortlink" />
                                <input type="hidden" name="post_id" value="<?php echo esc_attr($p->ID); ?>" />
                                <input name="base_url" type="url" value="<?php echo esc_attr($base); ?>" class="regular-text" style="max-width:100%;" placeholder="https://example.com" />
                                <button class="button button-small" type="submit">Cập nhật</button>
                            </form>
                        </td>
                        <td style="max-width:300px; word-break:break-all;">&nbsp;<?php echo esc_html($effective ?: $base); ?></td>
                        <td><?php echo esc_html($count); ?><?php echo $count7 ? ' (7d: '.intval($count7).')' : ''; ?></td>
                        <td>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mqrg-manage&post_id=' . $p->ID)); ?>"><span class="dashicons dashicons-admin-generic" style="margin-top:3px;"></span> Quản lý động</a>
                            <a class="button" href="<?php echo esc_url(get_permalink($p->ID)); ?>" target="_blank"><span class="dashicons dashicons-external" style="margin-top:3px;"></span> Đi tới</a>
                            <a class="button" href="<?php echo esc_url(add_query_arg(['page'=>'mqrg-stats','post_id'=>$p->ID], admin_url('admin.php'))); ?>"><span class="dashicons dashicons-chart-line" style="margin-top:3px;"></span> Thống kê</a>
                            <a class="button button-link-delete" onclick="return confirm('Reset thống kê cho mã này?');" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mqrg_reset_stats&post_id='.$p->ID), 'mqrg_reset_stats_'.$p->ID)); ?>"><span class="dashicons dashicons-update" style="margin-top:3px;"></span> Reset thống kê</a>
                            <a class="button button-link-delete" onclick="return confirm('Xóa hoàn toàn shortlink này?');" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mqrg_delete_shortlink&post_id='.$p->ID), 'mqrg_delete_shortlink_'.$p->ID)); ?>"><span class="dashicons dashicons-trash" style="margin-top:3px;"></span> Xóa</a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6">Chưa có shortlink nào.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <script>
        (function(){
            document.addEventListener('click', function(e){
                var btn = e.target.closest('.mqrg-copy-shortcode');
                if (btn) {
                    e.preventDefault();
                    var text = btn.getAttribute('data-text');
                    if (!text) return;
                    
                    // Try modern clipboard API first
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function() {
                            var icon = btn.querySelector('.dashicons');
                            if (icon) {
                                icon.classList.remove('dashicons-clipboard');
                                icon.classList.add('dashicons-yes');
                                setTimeout(function(){
                                    icon.classList.remove('dashicons-yes');
                                    icon.classList.add('dashicons-clipboard');
                                }, 1500);
                            }
                        }).catch(function(err) {
                            console.error('Copy failed:', err);
                        });
                    } else {
                        // Fallback to old method
                        var tmp = document.createElement('textarea');
                        tmp.value = text;
                        tmp.style.position = 'fixed';
                        tmp.style.opacity = '0';
                        document.body.appendChild(tmp);
                        tmp.select();
                        document.execCommand('copy');
                        document.body.removeChild(tmp);
                        var icon = btn.querySelector('.dashicons');
                        if (icon) {
                            icon.classList.remove('dashicons-clipboard');
                            icon.classList.add('dashicons-yes');
                            setTimeout(function(){
                                icon.classList.remove('dashicons-yes');
                                icon.classList.add('dashicons-clipboard');
                            }, 1500);
                        }
                    }
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Admin: Manage a shortlink's base URL and dynamic rules.
     */
    public function render_manage_dynamic() {
        if (!current_user_can('manage_options')) return;
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'shortlink') {
            echo '<div class="notice notice-error"><p>Thiếu hoặc sai shortlink.</p></div>';
            return;
        }
        $base = get_post_meta($post_id, '_original_url', true);
        $rotate_interval = intval(get_post_meta($post_id, '_mqrg_rotate_interval', true));
        $tz_string = get_option('timezone_string');
        if (!$tz_string || !is_string($tz_string)) {
            $offset = get_option('gmt_offset');
            $tz_string = $offset ? 'UTC'.($offset>=0?'+':'').$offset : 'UTC';
        }
        ?>
        <div class="wrap">
            <h1>Quản lý QR động</h1>
            <p><strong>Shortlink:</strong> <a href="<?php echo esc_url(get_permalink($post_id)); ?>" target="_blank"><?php echo esc_html(get_permalink($post_id)); ?></a></p>
            <p><strong>Múi giờ site:</strong> <?php echo esc_html($tz_string); ?> (thời gian nhập bên dưới hiểu theo múi giờ site)</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('mqrg_save_shortlink', 'mqrg_nonce_field'); ?>
                <input type="hidden" name="action" value="mqrg_save_shortlink" />
                <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>" />
                <input type="hidden" name="rules_timezone" value="<?php echo esc_attr($tz_string); ?>" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mqrg_base_url">URL nguồn (mặc định)</label></th>
                        <td><input name="base_url" id="mqrg_base_url" type="url" class="regular-text" value="<?php echo esc_attr($base); ?>" placeholder="https://example.com"/></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mqrg_rotate_interval">Giây đổi mã QR</label></th>
                        <td>
                            <input name="rotate_interval" id="mqrg_rotate_interval" type="number" min="0" step="1" class="small-text" value="<?php echo esc_attr($rotate_interval); ?>" />
                            <p class="description">Khi > 0, mã QR sẽ thay đổi sau mỗi số giây này. Mã cũ sẽ hết hiệu lực.</p>
                        </td>
                    </tr>
                    <!-- Đã bỏ phần quy tắc động -->
                </table>
                <?php submit_button('Lưu thay đổi'); ?>
            </form>

            <h2>QR xem nhanh</h2>
            <?php $short_url = get_permalink($post_id); ?>
            <div style="display:flex; gap:16px; align-items:flex-start;">
                <div class="mqrg-qrcode-container" id="mqrg-admin-preview"><p class="mqrg-placeholder-text">Mã QR sẽ xuất hiện ở đây</p></div>
                <div>
                    <input type="text" class="regular-text code" style="width:360px;" readonly value="<?php echo esc_attr($short_url); ?>" />
                    <p style="margin-top:8px;">
                        <label><strong>Màu QR:</strong></label><br/>
                        <span class="mqrg-color-dot" data-color="#000000" style="display:inline-block;width:32px;height:32px;background:#000000;cursor:pointer;border:2px solid #ddd;margin:2px;" title="Đen"></span>
                        <span class="mqrg-color-dot" data-color="#0a68ff" style="display:inline-block;width:32px;height:32px;background:#0a68ff;cursor:pointer;border:2px solid #ddd;margin:2px;" title="Xanh"></span>
                        <span class="mqrg-color-dot" data-color="#d63638" style="display:inline-block;width:32px;height:32px;background:#d63638;cursor:pointer;border:2px solid #ddd;margin:2px;" title="Đỏ"></span>
                        <span class="mqrg-color-dot" data-color="#18b24b" style="display:inline-block;width:32px;height:32px;background:#18b24b;cursor:pointer;border:2px solid #ddd;margin:2px;" title="Xanh lá"></span>
                        <span class="mqrg-color-dot" data-color="#f56e28" style="display:inline-block;width:32px;height:32px;background:#f56e28;cursor:pointer;border:2px solid #ddd;margin:2px;" title="Cam"></span>
                        <span class="mqrg-color-dot" data-color="#6c2eb9" style="display:inline-block;width:32px;height:32px;background:#6c2eb9;cursor:pointer;border:2px solid #ddd;margin:2px;" title="Tím"></span>
                    </p>
                    <p style="margin-top:8px;">
                        <button id="mqrg-admin-copy" class="button">Sao chép Short URL</button>
                        <button id="mqrg-admin-download" class="button button-primary">Tải QR PNG</button>
                    </p>
                    <p class="description">QR này encode Short URL cố định để thuận tiện in ấn. Endpoint PNG: <code><?php echo esc_html(home_url('/?mqrg_qr_png='.$post_id)); ?></code></p>
                </div>
            </div>
        </div>
        <script>
        (function(){
            function whenQRCodeReady(cb){
                if (typeof window.QRCode !== 'undefined') { cb(); return; }
                var tries = 0; var iv = setInterval(function(){
                    if (typeof window.QRCode !== 'undefined') { clearInterval(iv); cb(); }
                    else if (++tries > 200) { clearInterval(iv); console.error('QRCode library not loaded'); }
                }, 50);
            }
            function onReady(cb){
                if (document.readyState !== 'loading') cb();
                else document.addEventListener('DOMContentLoaded', cb);
            }

            // Admin QR preview (Short URL)
            onReady(function(){ 
                whenQRCodeReady(function(){
                    var box = document.getElementById('mqrg-admin-preview');
                    if (!box) {
                        console.error('QR preview box not found');
                        return;
                    }
                    var url = <?php echo wp_json_encode(get_permalink($post_id)); ?>;
                    var currentColor = '#000000';
                    
                    function renderQR(color) {
                        box.innerHTML = '';
                        new QRCode(box, { 
                            text: url, 
                            width: 256, 
                            height: 256, 
                            colorDark: color, 
                            colorLight: '#ffffff', 
                            correctLevel: QRCode.CorrectLevel.H 
                        });
                        currentColor = color;
                    }
                    
                    // Render initial QR
                    renderQR(currentColor);
                    
                    // Color picker
                    document.querySelectorAll('.mqrg-color-dot').forEach(function(dot){
                        dot.addEventListener('click', function(){
                            var col = this.getAttribute('data-color');
                            renderQR(col);
                        });
                    });
                    
                    // Copy button
                    var copyBtn = document.getElementById('mqrg-admin-copy');
                    if (copyBtn) {
                        copyBtn.addEventListener('click', function(e){
                            e.preventDefault();
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(url).then(function() {
                                    copyBtn.textContent = 'Đã sao chép!';
                                    setTimeout(function(){ copyBtn.textContent = 'Sao chép Short URL'; }, 1500);
                                }).catch(function(err) {
                                    console.error('Copy failed:', err);
                                });
                            } else {
                                var tmp = document.createElement('input'); 
                                tmp.value = url; 
                                document.body.appendChild(tmp); 
                                tmp.select(); 
                                document.execCommand('copy'); 
                                document.body.removeChild(tmp);
                                copyBtn.textContent = 'Đã sao chép!';
                                setTimeout(function(){ copyBtn.textContent = 'Sao chép Short URL'; }, 1500);
                            }
                        });
                    }
                    
                    // Download button
                    var dlBtn = document.getElementById('mqrg-admin-download');
                    if (dlBtn) {
                        dlBtn.addEventListener('click', function(e){
                            e.preventDefault();
                            var canvas = box.querySelector('canvas');
                            var img = box.querySelector('img');
                            var sourceCanvas = canvas;
                            
                            // If only img exists, convert to canvas first
                            if (!canvas && img) {
                                sourceCanvas = document.createElement('canvas');
                                sourceCanvas.width = img.width || img.naturalWidth;
                                sourceCanvas.height = img.height || img.naturalHeight;
                                var tmpCtx = sourceCanvas.getContext('2d');
                                tmpCtx.drawImage(img, 0, 0);
                            }
                            
                            if (!sourceCanvas) {
                                console.error('No canvas or image found');
                                return;
                            }
                            
                            var padding = 15; 
                            var newCanvas = document.createElement('canvas'); 
                            var ctx = newCanvas.getContext('2d');
                            newCanvas.width = sourceCanvas.width + padding*2; 
                            newCanvas.height = sourceCanvas.height + padding*2;
                            ctx.fillStyle = '#ffffff'; 
                            ctx.fillRect(0, 0, newCanvas.width, newCanvas.height);
                            ctx.drawImage(sourceCanvas, padding, padding);
                            
                            var a = document.createElement('a'); 
                            a.download = 'qrcode.png'; 
                            a.href = newCanvas.toDataURL('image/png'); 
                            a.click();
                        });
                    }
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Handle admin POST to save base URL and dynamic rules.
     */
    public function handle_admin_save_shortlink() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        check_admin_referer('mqrg_save_shortlink', 'mqrg_nonce_field');
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'shortlink') wp_die('Invalid post');
        $base_url = isset($_POST['base_url']) ? esc_url_raw(trim($_POST['base_url'])) : '';
        if ($base_url) {
            update_post_meta($post_id, '_original_url', $base_url);
        }
        // Save rotation interval (seconds). 0 disables rotation.
        $rotate_interval = isset($_POST['rotate_interval']) ? intval($_POST['rotate_interval']) : 0;
        if ($rotate_interval < 0) $rotate_interval = 0;
        update_post_meta($post_id, '_mqrg_rotate_interval', $rotate_interval);
        // Đã bỏ lưu quy tắc động
        wp_redirect(add_query_arg(['page' => 'mqrg-manage', 'post_id' => $post_id, 'updated' => 1], admin_url('admin.php')));
        exit;
    }
    
    /**
     * Returns the generator's HTML content.
     */
    private function get_generator_html() {
        ob_start();
        ?>
        <div class="mqrg-wrap">
            <div class="mqrg-container">
                <div class="mqrg-form-section">
                    <div class="mqrg-input-group">
                        <label for="mqrg-url-input">Đường dẫn website</label>
                        <input type="url" id="mqrg-url-input" placeholder="https://namsaigon.edu.vn" class="mqrg-input">
                    </div>

                    <div class="mqrg-options">
                        <h3>Tùy chỉnh</h3>
                        <div class="mqrg-color-palette">
                            <span class="mqrg-color-dot active" data-color="#000000" style="background-color:#000000;"></span>
                            <span class="mqrg-color-dot" data-color="#0a68ff" style="background-color:#0a68ff;"></span>
                            <span class="mqrg-color-dot" data-color="#d63638" style="background-color:#d63638;"></span>
                            <span class="mqrg-color-dot" data-color="#18b24b" style="background-color:#18b24b;"></span>
                            <span class="mqrg-color-dot" data-color="#f56e28" style="background-color:#f56e28;"></span>
                            <span class="mqrg-color-dot" data-color="#6c2eb9" style="background-color:#6c2eb9;"></span>
                        </div>
                    </div>
                    
                    <button class="mqrg-generate-btn">Tạo mã QR</button>
                    <span class="spinner"></span>
                </div>

                <div class="mqrg-result-section">
                    <div class="mqrg-qrcode-container">
                        <p class="mqrg-placeholder-text">Mã QR sẽ xuất hiện ở đây</p>
                    </div>
                    <div class="mqrg-result-actions" style="display: none;">
                        <div class="mqrg-shortlink-wrapper">
                            <input type="text" class="mqrg-shortlink-output" readonly>
                        </div>
                        <div class="mqrg-buttons-wrapper">
                            <button class="mqrg-copy-btn">Sao chép</button>
                            <button class="mqrg-download-btn">Tải về</button>
                        </div>
                        <div class="mqrg-copy-feedback mqrg-feedback">Đã sao chép!</div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Returns effective destination URL for a shortlink based on dynamic rules.
     * Optionally returns the matched rule by reference.
     */
    private function get_effective_destination($post_id, $now = null, &$matched_rule = null) {
        // Đã bỏ quy tắc động, luôn trả về null
        $matched_rule = null;
        return '';
    }

    /**
     * Compute rotating token for a shortlink based on interval and current time bucket.
     */
    private function compute_rotation_token($post_id, $now = null, $interval = null) {
        $post_id = intval($post_id);
        if ($now === null) $now = $this->now();
        if ($interval === null) $interval = intval(get_post_meta($post_id, '_mqrg_rotate_interval', true));
        $interval = intval($interval);
        if ($interval <= 0) return '';
        $bucket = floor($now / $interval);
        $salt = wp_salt('auth');
        return substr(hash('sha256', $post_id . '|' . $bucket . '|' . $salt), 0, 10);
    }

    /**
     * Increment scan count for a shortlink.
     */
    private function increment_scan_count($post_id) {
        $count = intval(get_post_meta($post_id, '_mqrg_scan_count', true));
        update_post_meta($post_id, '_mqrg_scan_count', $count + 1);
        update_post_meta($post_id, '_mqrg_last_scan', $this->now());
        // daily histogram
        $daily = get_post_meta($post_id, '_mqrg_daily_counts', true);
        if (!is_array($daily)) $daily = [];
        $day = date('Y-m-d', $this->now());
        $daily[$day] = isset($daily[$day]) ? (intval($daily[$day]) + 1) : 1;
        update_post_meta($post_id, '_mqrg_daily_counts', $daily);
    }

    private function get_last_days_count($post_id, $days = 7) {
        $daily = get_post_meta($post_id, '_mqrg_daily_counts', true);
        if (!is_array($daily)) return 0;
        $sum = 0;
        $now = $this->now();
        for ($i=0; $i<$days; $i++) {
            $d = date('Y-m-d', $now - $i*DAY_IN_SECONDS);
            if (isset($daily[$d])) $sum += intval($daily[$d]);
        }
        return $sum;
    }

    /**
     * Helper to get current timestamp (site local time).
     */
    private function now() {
        return intval(current_time('timestamp'));
    }

    /**
     * Admin: Stats page with filter and chart
     */
    public function render_stats_page() {
        if (!current_user_can('manage_options')) return;
        $range = isset($_GET['range']) ? intval($_GET['range']) : 30;
        if (!in_array($range, [7,30,90], true)) $range = 30;
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $now = $this->now();
        $labels = [];
        for ($i=$range-1; $i>=0; $i--) { $labels[] = date('Y-m-d', $now - $i*DAY_IN_SECONDS); }
        $series = [];
        if ($post_id && get_post_type($post_id) === 'shortlink') {
            $daily = get_post_meta($post_id, '_mqrg_daily_counts', true);
            if (!is_array($daily)) $daily = [];
            foreach ($labels as $d) { $series[] = isset($daily[$d]) ? intval($daily[$d]) : 0; }
            $title = 'Stats for #'.$post_id;
        } else {
            $posts = get_posts(['post_type'=>'shortlink','post_status'=>'publish','numberposts'=>-1]);
            $agg = array_fill_keys($labels, 0);
            foreach ($posts as $p) {
                $daily = get_post_meta($p->ID, '_mqrg_daily_counts', true);
                if (!is_array($daily)) continue;
                foreach ($labels as $d) { if (isset($daily[$d])) $agg[$d] += intval($daily[$d]); }
            }
            foreach ($labels as $d) { $series[] = $agg[$d]; }
            $title = 'All shortlinks';
        }
        ?>
        <div class="wrap">
            <h1>Stats</h1>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="mqrg-stats" />
                <label>Post ID: <input type="number" name="post_id" value="<?php echo esc_attr($post_id); ?>" style="width:120px;" /></label>
                <label style="margin-left:10px;">Range:
                    <select name="range">
                        <option value="7" <?php selected($range,7); ?>>7 ngày</option>
                        <option value="30" <?php selected($range,30); ?>>30 ngày</option>
                        <option value="90" <?php selected($range,90); ?>>90 ngày</option>
                    </select>
                </label>
                <button class="button">Lọc</button>
            </form>
            <p>
                <?php if ($post_id) : ?>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mqrg_export_csv&post_id='.$post_id.'&days='.$range), 'mqrg_export_csv_'.$post_id)); ?>">Xuất CSV (<?php echo intval($range); ?> ngày)</a>
                <?php else: ?>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=mqrg_export_csv_all&days='.$range), 'mqrg_export_csv_all')); ?>">Xuất CSV tổng hợp (<?php echo intval($range); ?> ngày)</a>
                <?php endif; ?>
            </p>
            <canvas id="mqrgChart" height="100"></canvas>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function(){
            const ctx = document.getElementById('mqrgChart');
            const data = {
                labels: <?php echo wp_json_encode($labels); ?>,
                datasets: [{
                    label: 'Lượt quét',
                    data: <?php echo wp_json_encode($series); ?>,
                    borderColor: '#0a68ff',
                    backgroundColor: 'rgba(10,104,255,0.2)',
                    fill: true,
                    tension: 0.3
                }]
            };
            new Chart(ctx, { type: 'line', data: data, options: { plugins: { title: { display: true, text: <?php echo wp_json_encode($title); ?> } }, scales: { x: { title: { display: true, text: 'Ngày' } }, y: { beginAtZero:true, title: { display: true, text: 'Lượt quét' } } } }});
        })();
        </script>
        <?php
    }

    /** Export CSV of daily stats for a post id over N days */
    public function handle_export_csv() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        if (!$post_id || get_post_type($post_id) !== 'shortlink') wp_die('Invalid post');
        if (!wp_verify_nonce(isset($_GET['_wpnonce'])?$_GET['_wpnonce']:'', 'mqrg_export_csv_'.$post_id)) wp_die('Bad nonce');
        $now = $this->now();
        $daily = get_post_meta($post_id, '_mqrg_daily_counts', true);
        if (!is_array($daily)) $daily = [];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mqrg-stats-'.$post_id.'-'.$days.'d.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['date','count']);
        for ($i=$days-1; $i>=0; $i--) {
            $d = date('Y-m-d', $now - $i*DAY_IN_SECONDS);
            $c = isset($daily[$d]) ? intval($daily[$d]) : 0;
            fputcsv($out, [$d, $c]);
        }
        fclose($out);
        exit;
    }

    /** Reset stats for a post */
    public function handle_reset_stats() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'shortlink') wp_die('Invalid post');
        if (!wp_verify_nonce(isset($_GET['_wpnonce'])?$_GET['_wpnonce']:'', 'mqrg_reset_stats_'.$post_id)) wp_die('Bad nonce');
        delete_post_meta($post_id, '_mqrg_daily_counts');
        delete_post_meta($post_id, '_mqrg_last_scan');
        update_post_meta($post_id, '_mqrg_scan_count', 0);
        wp_redirect(add_query_arg(['page'=>'mqrg-links','reset'=>1], admin_url('admin.php')));
        exit;
    }

    /** Delete a shortlink permanently */
    public function handle_delete_shortlink() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        if (!$post_id || get_post_type($post_id) !== 'shortlink') wp_die('Invalid post');
        if (!wp_verify_nonce(isset($_GET['_wpnonce'])?$_GET['_wpnonce']:'', 'mqrg_delete_shortlink_'.$post_id)) wp_die('Bad nonce');
        // Delete all meta and the post itself
        delete_post_meta($post_id, '_original_url');
        delete_post_meta($post_id, '_mqrg_dynamic_rules');
        delete_post_meta($post_id, '_mqrg_scan_count');
        delete_post_meta($post_id, '_mqrg_last_scan');
        delete_post_meta($post_id, '_mqrg_daily_counts');
        wp_delete_post($post_id, true); // true = force delete, bypass trash
        wp_redirect(add_query_arg(['page'=>'mqrg-links','deleted'=>1], admin_url('admin.php')));
        exit;
    }

    /** Export aggregated CSV across all shortlinks for N days */
    public function handle_export_csv_all() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        if (!wp_verify_nonce(isset($_GET['_wpnonce'])?$_GET['_wpnonce']:'', 'mqrg_export_csv_all')) wp_die('Bad nonce');
        $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
        if (!in_array($days, [7,30,90], true)) $days = 30;
        $now = $this->now();
        $labels = [];
        for ($i=$days-1; $i>=0; $i--) { $labels[] = date('Y-m-d', $now - $i*DAY_IN_SECONDS); }
        $agg = array_fill_keys($labels, 0);
        $posts = get_posts(['post_type'=>'shortlink','post_status'=>'publish','numberposts'=>-1]);
        foreach ($posts as $p) {
            $daily = get_post_meta($p->ID, '_mqrg_daily_counts', true);
            if (!is_array($daily)) continue;
            foreach ($labels as $d) { if (isset($daily[$d])) $agg[$d] += intval($daily[$d]); }
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mqrg-stats-aggregate-'.$days.'d.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['date','count']);
        foreach ($labels as $d) {
            fputcsv($out, [$d, $agg[$d]]);
        }
        fclose($out);
        exit;
    }
    
    /**
     * Render the Settings page for logo/watermark upload.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;
        
        // Handle form submission
        if (isset($_POST['mqrg_save_settings']) && check_admin_referer('mqrg_settings_nonce')) {
            $logo_id = isset($_POST['mqrg_logo_attachment_id']) ? intval($_POST['mqrg_logo_attachment_id']) : 0;
            update_option('mqrg_logo_attachment_id', $logo_id);
            // Clear all cached QR codes when logo changes
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mqrg_qr_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mqrg_qr_%'");
            echo '<div class="notice notice-success"><p>Cài đặt đã được lưu. Cache QR đã được xóa.</p></div>';
        }
        
        $logo_id = get_option('mqrg_logo_attachment_id', 0);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
        
        ?>
        <div class="wrap">
            <h1>Cài đặt QR Generator</h1>
            <form method="post" action="">
                <?php wp_nonce_field('mqrg_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label>Logo/Watermark (QR Center)</label></th>
                        <td>
                            <div style="margin-bottom:10px;">
                                <input type="hidden" id="mqrg_logo_attachment_id" name="mqrg_logo_attachment_id" value="<?php echo esc_attr($logo_id); ?>" />
                                <button type="button" class="button" id="mqrg_upload_logo_btn">Chọn Logo</button>
                                <button type="button" class="button" id="mqrg_remove_logo_btn" <?php echo $logo_id ? '' : 'style="display:none;"'; ?>>Xóa Logo</button>
                            </div>
                            <div id="mqrg_logo_preview" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
                                <img src="<?php echo esc_url($logo_url); ?>" style="max-width:150px; max-height:150px; border:1px solid #ddd; padding:5px;" />
                            </div>
                            <p class="description">Logo sẽ được đặt ở giữa mã QR với nền trắng khi tải xuống hoặc hiển thị PNG. Khuyến nghị: ảnh vuông, tối đa 150x150px.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="mqrg_save_settings" class="button button-primary" value="Lưu cài đặt" />
                </p>
            </form>
        </div>
        <script>
        jQuery(document).ready(function($) {
            var frame;
            $('#mqrg_upload_logo_btn').on('click', function(e) {
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: 'Chọn Logo cho QR Code',
                    button: { text: 'Sử dụng Logo này' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#mqrg_logo_attachment_id').val(attachment.id);
                    $('#mqrg_logo_preview img').attr('src', attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
                    $('#mqrg_logo_preview').show();
                    $('#mqrg_remove_logo_btn').show();
                });
                frame.open();
            });
            $('#mqrg_remove_logo_btn').on('click', function(e) {
                e.preventDefault();
                $('#mqrg_logo_attachment_id').val('0');
                $('#mqrg_logo_preview').hide();
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    /**
     * Endpoint: Serve QR code PNG for a shortlink
     * URL: /?mqrg_qr_png=POST_ID&color=HEX (color optional, default #000000)
     * Supports transient caching and logo watermark overlay.
     */
    public function handle_qr_png_endpoint() {
        if (!isset($_GET['mqrg_qr_png'])) return;
        $post_id = intval($_GET['mqrg_qr_png']);
        if (!$post_id || get_post_type($post_id) !== 'shortlink') {
            status_header(404);
            exit('Invalid shortlink');
        }
        $url = get_permalink($post_id);
        if (!$url) {
            status_header(404);
            exit('Invalid permalink');
        }
        $color_hex = isset($_GET['color']) ? sanitize_text_field($_GET['color']) : '000000';
        $color_hex = ltrim($color_hex, '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $color_hex)) $color_hex = '000000';
        
        $logo_id = get_option('mqrg_logo_attachment_id', 0);
        $logo_hash = $logo_id ? md5($logo_id . get_post_modified_time('U', true, $logo_id)) : 'nologo';
        
        // Check transient cache: mqrg_qr_{post_id}_{color}_{logo_hash}
        $cache_key = "mqrg_qr_{$post_id}_{$color_hex}_{$logo_hash}";
        $cached_png = get_transient($cache_key);
        if ($cached_png !== false) {
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=3600');
            header('X-Cache: HIT');
            echo $cached_png;
            exit;
        }
        
        // Ensure phpqrcode is available (via Composer or bundled)
        if (!class_exists('QRcode')) {
            // Try Composer autoloader first
            $autoload = MQR_PLUGIN_PATH . 'vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
        }
        if (!class_exists('QRcode')) {
            // Try common library paths
            $paths = [
                MQR_PLUGIN_PATH . 'vendor/phpqrcode/qrlib.php', // some packages flatten
                MQR_PLUGIN_PATH . 'vendor/phpqrcode/phpqrcode/qrlib.php', // composer package structure
            ];
            foreach ($paths as $p) {
                if (file_exists($p)) {
                    require_once $p;
                    break;
                }
            }
        }
        if (!class_exists('QRcode')) {
            status_header(500);
            exit('QR library not available. Please run composer install in the my-qr-generator plugin directory or include phpqrcode.');
        }
        // Generate QR in memory
        ob_start();
        QRcode::png($url, false, QR_ECLEVEL_H, 8, 2);
        $raw_png = ob_get_clean();
        // Recolor the QR if not black
        if ($color_hex !== '000000') {
            $raw_png = $this->recolor_qr_png($raw_png, $color_hex);
        }
        // Overlay logo if configured
        if ($logo_id) {
            $raw_png = $this->overlay_logo_on_qr($raw_png, $logo_id);
        }
        
        // Cache for 1 hour
        set_transient($cache_key, $raw_png, HOUR_IN_SECONDS);
        
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=3600');
        header('X-Cache: MISS');
        echo $raw_png;
        exit;
    }

    /**
     * Helper: recolor black pixels in a PNG to the given hex color.
     */
    private function recolor_qr_png($png_data, $hex) {
        $im = imagecreatefromstring($png_data);
        if (!$im) return $png_data;
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $w = imagesx($im);
        $h = imagesy($im);
        for ($x=0; $x<$w; $x++) {
            for ($y=0; $y<$h; $y++) {
                $rgb = imagecolorat($im, $x, $y);
                $rr = ($rgb >> 16) & 0xFF;
                $gg = ($rgb >> 8) & 0xFF;
                $bb = $rgb & 0xFF;
                // If pixel is black or very dark, recolor
                if ($rr < 50 && $gg < 50 && $bb < 50) {
                    imagesetpixel($im, $x, $y, imagecolorallocate($im, $r, $g, $b));
                }
            }
        }
        ob_start();
        imagepng($im);
        $out = ob_get_clean();
        imagedestroy($im);
        return $out;
    }
    
    /**
     * Helper: overlay logo/watermark on center of QR code with white background padding.
     * Logo size is 20% of QR width, with 10% padding for white background.
     */
    private function overlay_logo_on_qr($qr_png_data, $logo_attachment_id) {
        $qr_im = imagecreatefromstring($qr_png_data);
        if (!$qr_im) return $qr_png_data;
        
        $logo_path = get_attached_file($logo_attachment_id);
        if (!$logo_path || !file_exists($logo_path)) {
            imagedestroy($qr_im);
            return $qr_png_data;
        }
        
        // Detect logo format and load
        $logo_info = getimagesize($logo_path);
        if (!$logo_info) {
            imagedestroy($qr_im);
            return $qr_png_data;
        }
        
        $logo_im = false;
        switch ($logo_info['mime']) {
            case 'image/png': $logo_im = imagecreatefrompng($logo_path); break;
            case 'image/jpeg': $logo_im = imagecreatefromjpeg($logo_path); break;
            case 'image/gif': $logo_im = imagecreatefromgif($logo_path); break;
            case 'image/webp': $logo_im = imagecreatefromwebp($logo_path); break;
        }
        
        if (!$logo_im) {
            imagedestroy($qr_im);
            return $qr_png_data;
        }
        
        $qr_w = imagesx($qr_im);
        $qr_h = imagesy($qr_im);
        $logo_w = imagesx($logo_im);
        $logo_h = imagesy($logo_im);
        
        // Logo target size: 20% of QR width
        $target_logo_size = intval($qr_w * 0.20);
        // White background padding: 10% larger than logo
        $bg_size = intval($target_logo_size * 1.10);
        
        // Resize logo to target size (maintain aspect ratio, fit within square)
        $scale = min($target_logo_size / $logo_w, $target_logo_size / $logo_h);
        $new_logo_w = intval($logo_w * $scale);
        $new_logo_h = intval($logo_h * $scale);
        
        $logo_resized = imagecreatetruecolor($new_logo_w, $new_logo_h);
        imagealphablending($logo_resized, false);
        imagesavealpha($logo_resized, true);
        imagecopyresampled($logo_resized, $logo_im, 0, 0, 0, 0, $new_logo_w, $new_logo_h, $logo_w, $logo_h);
        
        // Calculate center position
        $center_x = intval($qr_w / 2);
        $center_y = intval($qr_h / 2);
        
        // Draw white background square
        $white = imagecolorallocate($qr_im, 255, 255, 255);
        imagefilledrectangle(
            $qr_im,
            $center_x - intval($bg_size / 2),
            $center_y - intval($bg_size / 2),
            $center_x + intval($bg_size / 2),
            $center_y + intval($bg_size / 2),
            $white
        );
        
        // Overlay logo on center
        imagecopyresampled(
            $qr_im,
            $logo_resized,
            $center_x - intval($new_logo_w / 2),
            $center_y - intval($new_logo_h / 2),
            0, 0,
            $new_logo_w, $new_logo_h,
            $new_logo_w, $new_logo_h
        );
        
        imagedestroy($logo_im);
        imagedestroy($logo_resized);
        
        ob_start();
        imagepng($qr_im);
        $out = ob_get_clean();
        imagedestroy($qr_im);
        return $out;
    }
}

// Instantiate the plugin class
new My_QR_Generator();
