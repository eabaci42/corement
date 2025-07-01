<?php
/*
Plugin Name: Corement
Description: Core Comment System - Çekirdek Yorum Sistemi
Version: 0.0.1
Author: Ertuğrul ABACI
Author URI: https://github.com/eabaci42
*/

// Güvenlik önlemi: Dosya doğrudan çağrılırsa çıkış yap
if (!defined('ABSPATH')) {
    exit;
}

// Gerekli dosyaları dahil et
require_once plugin_dir_path(__FILE__) . 'includes/form-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/comment-list.php';
require_once plugin_dir_path(__FILE__) . 'includes/media-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/vote-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/blacklist.php';

// Admin paneli
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'admin/admin-settings.php';
}

// CSS ve JS dosyalarını ekle
function corement_enqueue_assets() {
    wp_enqueue_style('corement-css', plugin_dir_url(__FILE__) . 'assets/css/corement.css');
    wp_enqueue_script('corement-js', plugin_dir_url(__FILE__) . 'assets/js/corement.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'corement_enqueue_assets');

// Kısa kod ile yorum formu ve listeyi ekle
function corement_shortcode($atts) {
    ob_start();
    do_action('corement_render_comment_form');
    do_action('corement_render_comment_list');
    return ob_get_clean();
}
add_shortcode('corement', 'corement_shortcode');

// Otomatik olarak yazı ve sayfa altına ekle (isteğe bağlı)
function corement_auto_append($content) {
    if (is_single() || is_page()) {
        $content .= do_shortcode('[corement]');
    }
    return $content;
}
add_filter('the_content', 'corement_auto_append');
