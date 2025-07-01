<?php
/*
Plugin Name: Corement - Modern Comment System
Plugin URI: https://github.com/eabaci42/corement
Description: Bu eklenti Ertuğrul ABACI tarafından geliştirilmiştir. Modern, güvenli ve özellikli bir yorum sistemidir.
Version: 1.0.0
Author: Ertuğrul ABACI
Author URI: https://github.com/eabaci42/corement
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: corement
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Network: false
*/
// Bu yazılım Ertuğrul ABACI tarafından üretilmiştir. Github: https://github.com/eabaci42/corement

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Sabitler
define('COREMENT_VERSION', '1.0.0');
define('COREMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COREMENT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Gerekli dosyaları dahil et
require_once COREMENT_PLUGIN_DIR . 'includes/database.php';
require_once COREMENT_PLUGIN_DIR . 'includes/security.php';
require_once COREMENT_PLUGIN_DIR . 'includes/avatar-system.php';
require_once COREMENT_PLUGIN_DIR . 'includes/form-handler.php';
require_once COREMENT_PLUGIN_DIR . 'includes/comment-list.php';
require_once COREMENT_PLUGIN_DIR . 'includes/media-handler.php';
require_once COREMENT_PLUGIN_DIR . 'includes/vote-handler.php';
require_once COREMENT_PLUGIN_DIR . 'includes/blacklist.php';

// Admin dosyalarını dahil et
if (is_admin()) {
    require_once COREMENT_PLUGIN_DIR . 'admin/admin-settings.php';
}

// Eklenti aktivasyonu
register_activation_hook(__FILE__, 'corement_activate');
function corement_activate() {
    corement_create_tables();
    
    // Varsayılan ayarları ekle
    add_option('corement_blacklist', 'spam,reklam,viagra,casino');
    add_option('corement_media_limit', 2);
    add_option('corement_max_comments_per_hour', 10);
    add_option('corement_guest_avatar', COREMENT_PLUGIN_URL . 'assets/img/default-avatar.png');
    add_option('corement_generate_letter_avatars', true);
    add_option('corement_theme_mode', 'auto');
    add_option('corement_auto_approve', false);
    add_option('corement_auto_append', false);
    add_option('corement_disable_default', false);
    add_option('corement_show_comment_count', true);
    add_option('corement_show_reaction_counts', true);
    add_option('corement_show_vote_counts', true);
    add_option('corement_rate_limit_enabled', true);
    add_option('corement_enable_security_logging', false);
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Eklenti deaktivasyonu
register_deactivation_hook(__FILE__, 'corement_deactivate');
function corement_deactivate() {
    // Zamanlanmış işleri temizle
    wp_clear_scheduled_hook('corement_cleanup_security_logs');
    wp_clear_scheduled_hook('corement_cleanup_orphaned_media');
    wp_clear_scheduled_hook('corement_cleanup_blacklist_logs');
    wp_clear_scheduled_hook('corement_update_automatic_blacklist');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// CSS ve JS dosyalarını ekle
add_action('wp_enqueue_scripts', 'corement_enqueue_scripts');
function corement_enqueue_scripts() {
    // CSS
    wp_enqueue_style(
        'corement-style',
        COREMENT_PLUGIN_URL . 'assets/css/corement.css',
        array(),
        COREMENT_VERSION
    );
    
    // JavaScript
    wp_enqueue_script(
        'corement-script',
        COREMENT_PLUGIN_URL . 'assets/js/corement.js',
        array('jquery'),
        COREMENT_VERSION,
        true
    );
    
    // AJAX için localize
    wp_localize_script('corement-script', 'corementAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('corement_nonce'),
        'strings' => array(
            'loading' => __('Loading...', 'corement'),
            'error' => __('An error occurred. Please try again.', 'corement'),
            'success' => __('Success!', 'corement'),
            'confirm_delete' => __('Are you sure you want to delete this comment?', 'corement'),
            'reply_to' => __('Reply to', 'corement'),
            'cancel' => __('Cancel', 'corement'),
            'submit' => __('Submit', 'corement'),
            'name_required' => __('Name is required.', 'corement'),
            'email_required' => __('Email is required.', 'corement'),
            'comment_required' => __('Comment is required.', 'corement'),
            'invalid_email' => __('Please enter a valid email address.', 'corement'),
            'file_too_large' => __('File is too large.', 'corement'),
            'invalid_file_type' => __('Invalid file type.', 'corement')
        )
    ));
}

// Shortcode
add_shortcode('corement', 'corement_shortcode');
function corement_shortcode($atts) {
    $atts = shortcode_atts(array(
        'post_id' => get_the_ID()
    ), $atts);
    
    if (!$atts['post_id']) {
        return '';
    }
    
    ob_start();
    corement_display_comments($atts['post_id']);
    return ob_get_clean();
}

// Otomatik ekleme
add_filter('the_content', 'corement_auto_append_comments');
function corement_auto_append_comments($content) {
    if (!get_option('corement_auto_append', false)) {
        return $content;
    }
    
    if (!is_single() && !is_page()) {
        return $content;
    }
    
    if (get_option('corement_disable_default', false)) {
        // Varsayılan yorumları gizle
        add_filter('comments_open', '__return_false');
    }
    
    $post_id = get_the_ID();
    if (!$post_id) {
        return $content;
    }
    
    ob_start();
    corement_display_comments($post_id);
    $comments_html = ob_get_clean();
    
    return $content . $comments_html;
}

// AJAX işleyicileri
add_action('wp_ajax_corement_submit_comment', 'corement_ajax_submit_comment');
add_action('wp_ajax_nopriv_corement_submit_comment', 'corement_ajax_submit_comment');

add_action('wp_ajax_corement_vote', 'corement_ajax_vote');
add_action('wp_ajax_nopriv_corement_vote', 'corement_ajax_vote');

add_action('wp_ajax_corement_reaction', 'corement_ajax_reaction');
add_action('wp_ajax_nopriv_corement_reaction', 'corement_ajax_reaction');

add_action('wp_ajax_corement_load_more', 'corement_ajax_load_more');
add_action('wp_ajax_nopriv_corement_load_more', 'corement_ajax_load_more');

// AJAX yorum gönderimi
function corement_ajax_submit_comment() {
    check_ajax_referer('corement_nonce', 'nonce');
    
    $result = corement_handle_comment_submission();
    
    if (is_wp_error($result)) {
        wp_send_json_error(array(
            'message' => $result->get_error_message()
        ));
    } else {
        wp_send_json_success(array(
            'message' => __('Comment submitted successfully!', 'corement'),
            'comment_html' => $result['html'] ?? '',
            'comment_id' => $result['comment_id'] ?? 0
        ));
    }
}

// AJAX oylama
function corement_ajax_vote() {
    check_ajax_referer('corement_nonce', 'nonce');
    
    $comment_id = absint($_POST['comment_id'] ?? 0);
    $vote_type = intval($_POST['vote_type'] ?? 0);
    
    if (!$comment_id || !in_array($vote_type, array(1, -1))) {
        wp_send_json_error(array('message' => __('Invalid vote data.', 'corement')));
    }
    
    $user_id = get_current_user_id();
    $user_ip = corement_get_user_ip();
    
    $result = corement_process_vote($comment_id, $vote_type, $user_id ?: null, $user_ip);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        $vote_counts = corement_get_vote_counts($comment_id);
        wp_send_json_success(array(
            'action' => $result['action'],
            'vote_counts' => $vote_counts
        ));
    }
}

// AJAX tepki
function corement_ajax_reaction() {
    check_ajax_referer('corement_nonce', 'nonce');
    
    $comment_id = absint($_POST['comment_id'] ?? 0);
    $reaction_type = sanitize_text_field($_POST['reaction_type'] ?? '');
    
    if (!$comment_id || !$reaction_type) {
        wp_send_json_error(array('message' => __('Invalid reaction data.', 'corement')));
    }
    
    $user_id = get_current_user_id();
    $user_ip = corement_get_user_ip();
    
    $result = corement_process_reaction($comment_id, $reaction_type, $user_id ?: null, $user_ip);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        $reaction_counts = corement_get_reaction_counts($comment_id);
        wp_send_json_success(array(
            'action' => $result['action'],
            'reaction_counts' => $reaction_counts
        ));
    }
}

// AJAX daha fazla yorum yükle
function corement_ajax_load_more() {
    check_ajax_referer('corement_nonce', 'nonce');
    
    $post_id = absint($_POST['post_id'] ?? 0);
    $offset = absint($_POST['offset'] ?? 0);
    $limit = absint($_POST['limit'] ?? 10);
    
    if (!$post_id) {
        wp_send_json_error(array('message' => __('Invalid post ID.', 'corement')));
    }
    
    $comments = corement_get_comments($post_id, $limit, $offset);
    
    ob_start();
    foreach ($comments as $comment) {
        corement_display_single_comment($comment);
    }
    $html = ob_get_clean();
    
    wp_send_json_success(array(
        'html' => $html,
        'has_more' => count($comments) === $limit
    ));
}

// Dil dosyalarını yükle
add_action('plugins_loaded', 'corement_load_textdomain');
function corement_load_textdomain() {
    load_plugin_textdomain('corement', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Eklenti bağlantıları
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'corement_plugin_action_links');
function corement_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=corement-settings') . '">' . __('Settings', 'corement') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Widget desteği
add_action('widgets_init', 'corement_register_widgets');
function corement_register_widgets() {
    // Widget sınıfları gelecek sürümde eklenecek
}

// REST API desteği
add_action('rest_api_init', 'corement_register_rest_routes');
function corement_register_rest_routes() {
    // REST API rotaları gelecek sürümde eklenecek
}

