<?php
/**
 * Corement - Medya İşleme Sistemi
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Medya dosyası yükleme işlemi
 */
function corement_process_media_upload($file) {
    // Dosya doğrulama
    $validation_errors = corement_validate_upload($file);
    if (!empty($validation_errors)) {
        return new WP_Error('validation_failed', implode(' ', $validation_errors));
    }
    
    // WordPress upload handler
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $upload_overrides = array(
        'test_form' => false,
        'mimes' => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'webp' => 'image/webp'
        )
    );
    
    $uploaded_file = wp_handle_upload($file, $upload_overrides);
    
    if (isset($uploaded_file['error'])) {
        return new WP_Error('upload_failed', $uploaded_file['error']);
    }
    
    // Resmi optimize et
    $optimized = corement_optimize_image($uploaded_file['file']);
    if (!is_wp_error($optimized)) {
        $uploaded_file['file'] = $optimized;
        $uploaded_file['url'] = str_replace(basename($uploaded_file['url']), basename($optimized), $uploaded_file['url']);
    }
    
    return $uploaded_file;
}

/**
 * Resim optimizasyonu
 */
function corement_optimize_image($file_path) {
    if (!function_exists('wp_get_image_editor')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
    
    $editor = wp_get_image_editor($file_path);
    
    if (is_wp_error($editor)) {
        return $file_path; // Hata varsa orijinal dosyayı döndür
    }
    
    $size = $editor->get_size();
    $max_width = get_option('corement_max_image_width', 800);
    $max_height = get_option('corement_max_image_height', 600);
    
    // Boyut kontrolü
    if ($size['width'] > $max_width || $size['height'] > $max_height) {
        $editor->resize($max_width, $max_height);
    }
    
    // Kalite ayarı
    $quality = get_option('corement_image_quality', 85);
    $editor->set_quality($quality);
    
    // Kaydet
    $saved = $editor->save();
    
    if (is_wp_error($saved)) {
        return $file_path;
    }
    
    // Orijinal dosyayı sil
    if ($saved['path'] !== $file_path) {
        @unlink($file_path);
    }
    
    return $saved['path'];
}

/**
 * Medya dosyası silme
 */
function corement_delete_media($comment_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_media';
    
    // Medya dosyalarını getir
    $media_files = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE comment_id = %d",
        $comment_id
    ));
    
    foreach ($media_files as $media) {
        // Dosyayı sil
        $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $media->file_url);
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        
        // Veritabanı kaydını sil
        $wpdb->delete($table, array('id' => $media->id));
    }
}

/**
 * Medya dosyası bilgilerini getir
 */
function corement_get_media_info($comment_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_media';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE comment_id = %d",
        $comment_id
    ));
}

/**
 * Medya dosyası boyutunu kontrol et
 */
function corement_check_media_size($file_size) {
    $max_size = get_option('corement_media_limit', 2) * 1024 * 1024; // MB to bytes
    return $file_size <= $max_size;
}

/**
 * Desteklenen medya türlerini getir
 */
function corement_get_supported_media_types() {
    return array(
        'image/jpeg' => array('jpg', 'jpeg'),
        'image/png' => array('png'),
        'image/gif' => array('gif'),
        'image/webp' => array('webp')
    );
}

/**
 * Medya türü kontrolü
 */
function corement_is_supported_media_type($file_type, $file_extension) {
    $supported_types = corement_get_supported_media_types();
    
    if (!isset($supported_types[$file_type])) {
        return false;
    }
    
    return in_array(strtolower($file_extension), $supported_types[$file_type]);
}

/**
 * Medya URL'sinden HTML oluştur
 */
function corement_generate_media_html($media_url, $media_type = '') {
    $file_extension = strtolower(pathinfo($media_url, PATHINFO_EXTENSION));
    
    $html = '<div class="corement-media-attachment">';
    
    if ($file_extension === 'gif') {
        $html .= sprintf(
            '<img src="%s" alt="%s" class="corement-media-gif" loading="lazy" />',
            esc_url($media_url),
            esc_attr__('Attached GIF', 'corement')
        );
    } else {
        $html .= sprintf(
            '<a href="%s" target="_blank" class="corement-media-link" rel="noopener">',
            esc_url($media_url)
        );
        $html .= sprintf(
            '<img src="%s" alt="%s" class="corement-media-image" loading="lazy" />',
            esc_url($media_url),
            esc_attr__('Attached Image', 'corement')
        );
        $html .= '</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Medya dosyası temizleme (cron job)
 */
function corement_cleanup_orphaned_media() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_media';
    
    // Yorumu silinmiş medya dosyalarını bul
    $orphaned_media = $wpdb->get_results(
        "SELECT m.* FROM $table m 
         LEFT JOIN {$wpdb->comments} c ON m.comment_id = c.comment_ID 
         WHERE c.comment_ID IS NULL"
    );
    
    foreach ($orphaned_media as $media) {
        // Dosyayı sil
        $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $media->file_url);
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        
        // Veritabanı kaydını sil
        $wpdb->delete($table, array('id' => $media->id));
    }
}

/**
 * Medya istatistikleri
 */
function corement_get_media_stats() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_media';
    
    $stats = array();
    
    // Toplam medya sayısı
    $stats['total_media'] = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    
    // Toplam medya boyutu
    $stats['total_size'] = $wpdb->get_var("SELECT SUM(file_size) FROM $table");
    
    // Medya türlerine göre dağılım
    $stats['by_type'] = $wpdb->get_results(
        "SELECT file_type, COUNT(*) as count, SUM(file_size) as total_size 
         FROM $table 
         GROUP BY file_type"
    );
    
    return $stats;
}

/**
 * Yorum silindiğinde medya dosyalarını da sil
 */
add_action('delete_comment', 'corement_delete_media');

/**
 * Günlük medya temizleme işi
 */
add_action('corement_cleanup_orphaned_media', 'corement_cleanup_orphaned_media');

// Medya temizleme işini zamanla
if (!wp_next_scheduled('corement_cleanup_orphaned_media')) {
    wp_schedule_event(time(), 'daily', 'corement_cleanup_orphaned_media');
}

