<?php
/**
 * Corement - Avatar Sistemi
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Corement için özel avatar getir
 */
function corement_get_avatar($email, $size = 48, $name = '', $user_id = null) {
    // Kayıtlı kullanıcı ise WordPress avatar'ını kullan
    if ($user_id && $user_id > 0) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            return get_avatar($user->user_email, $size, '', $user->display_name, array(
                'class' => 'corement-avatar corement-avatar-user',
                'loading' => 'lazy'
            ));
        }
    }
    
    // E-posta varsa WordPress avatar sistemini kullan
    if (!empty($email)) {
        // Gravatar kontrolü
        if (corement_has_gravatar($email)) {
            return get_avatar($email, $size, '', $name, array(
                'class' => 'corement-avatar corement-avatar-gravatar',
                'loading' => 'lazy'
            ));
        }
    }
    
    // Varsayılan avatar
    return corement_get_default_avatar($size, $name);
}

/**
 * Gravatar varlığını kontrol et
 */
function corement_has_gravatar($email) {
    $hash = md5(strtolower(trim($email)));
    $uri = 'https://www.gravatar.com/avatar/' . $hash . '?d=404';
    
    // Cache kontrolü
    $cache_key = 'corement_gravatar_' . $hash;
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
        return $cached === 'exists';
    }
    
    // HTTP isteği ile kontrol et
    $response = wp_remote_head($uri, array(
        'timeout' => 5,
        'sslverify' => false
    ));
    
    $exists = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    
    // 1 saat cache'le
    set_transient($cache_key, $exists ? 'exists' : 'not_exists', HOUR_IN_SECONDS);
    
    return $exists;
}

/**
 * Varsayılan avatar getir
 */
function corement_get_default_avatar($size = 48, $name = '') {
    $default_avatar_url = get_option('corement_guest_avatar', COREMENT_PLUGIN_URL . 'assets/img/default-avatar.png');
    
    // İsim varsa ilk harflerden avatar oluştur
    if (!empty($name) && get_option('corement_generate_letter_avatars', true)) {
        return corement_generate_letter_avatar($name, $size);
    }
    
    // Varsayılan resim
    return sprintf(
        '<img src="%s" alt="%s" class="corement-avatar corement-avatar-default" width="%d" height="%d" loading="lazy" />',
        esc_url($default_avatar_url),
        esc_attr($name ?: __('Guest User', 'corement')),
        $size,
        $size
    );
}

/**
 * İsimden harf avatarı oluştur
 */
function corement_generate_letter_avatar($name, $size = 48) {
    // İsimden ilk harfleri al
    $words = explode(' ', trim($name));
    $initials = '';
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(mb_substr($word, 0, 1));
            if (strlen($initials) >= 2) break;
        }
    }
    
    if (empty($initials)) {
        $initials = '?';
    }
    
    // Renk oluştur (isimden hash)
    $colors = array(
        '#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6',
        '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#16a085',
        '#27ae60', '#2980b9', '#8e44ad', '#f1c40f', '#d35400'
    );
    
    $hash = crc32($name);
    $color = $colors[abs($hash) % count($colors)];
    
    // SVG avatar oluştur
    $svg = sprintf(
        '<svg width="%d" height="%d" class="corement-avatar corement-avatar-letter" viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg">
            <circle cx="%d" cy="%d" r="%d" fill="%s"/>
            <text x="%d" y="%d" font-family="Arial, sans-serif" font-size="%d" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="central">%s</text>
        </svg>',
        $size, $size, $size, $size,
        $size/2, $size/2, $size/2, $color,
        $size/2, $size/2, $size * 0.4, esc_html($initials)
    );
    
    return $svg;
}

/**
 * Avatar boyutlarını optimize et
 */
function corement_get_avatar_sizes() {
    return array(
        'small' => 32,
        'medium' => 48,
        'large' => 64,
        'xlarge' => 96
    );
}

/**
 * Responsive avatar HTML'i oluştur
 */
function corement_get_responsive_avatar($email, $name = '', $user_id = null, $size_class = 'medium') {
    $sizes = corement_get_avatar_sizes();
    $size = $sizes[$size_class] ?? $sizes['medium'];
    
    $avatar_html = corement_get_avatar($email, $size, $name, $user_id);
    
    // Responsive sınıfları ekle
    $avatar_html = str_replace(
        'class="corement-avatar',
        'class="corement-avatar corement-avatar-' . $size_class,
        $avatar_html
    );
    
    return $avatar_html;
}

/**
 * Avatar cache temizleme
 */
function corement_clear_avatar_cache($email = null) {
    if ($email) {
        $hash = md5(strtolower(trim($email)));
        delete_transient('corement_gravatar_' . $hash);
    } else {
        // Tüm gravatar cache'lerini temizle
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_corement_gravatar_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_corement_gravatar_%'");
    }
}

/**
 * Avatar yükleme işlemi (gelecek sürüm için)
 */
function corement_handle_avatar_upload($user_id, $file) {
    // Bu fonksiyon gelecek sürümde kullanıcıların kendi avatar'larını yüklemesi için kullanılacak
    
    if (!$user_id || !current_user_can('edit_user', $user_id)) {
        return new WP_Error('permission_denied', __('Permission denied.', 'corement'));
    }
    
    // Dosya doğrulama
    $validation_errors = corement_validate_upload($file);
    if (!empty($validation_errors)) {
        return new WP_Error('validation_failed', implode(' ', $validation_errors));
    }
    
    // Yükleme işlemi
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $upload_overrides = array(
        'test_form' => false,
        'mimes' => array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp'
        )
    );
    
    $uploaded_file = wp_handle_upload($file, $upload_overrides);
    
    if (isset($uploaded_file['error'])) {
        return new WP_Error('upload_failed', $uploaded_file['error']);
    }
    
    // Resmi yeniden boyutlandır
    $resized = corement_resize_avatar($uploaded_file['file']);
    if (is_wp_error($resized)) {
        return $resized;
    }
    
    // Kullanıcı meta'sına kaydet
    update_user_meta($user_id, 'corement_custom_avatar', $uploaded_file['url']);
    
    // Gravatar cache'ini temizle
    $user = get_user_by('id', $user_id);
    if ($user) {
        corement_clear_avatar_cache($user->user_email);
    }
    
    return $uploaded_file['url'];
}

/**
 * Avatar resmi yeniden boyutlandır
 */
function corement_resize_avatar($file_path, $max_size = 200) {
    if (!function_exists('wp_get_image_editor')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
    
    $editor = wp_get_image_editor($file_path);
    
    if (is_wp_error($editor)) {
        return $editor;
    }
    
    // Kare olarak kırp
    $size = $editor->get_size();
    $min_dimension = min($size['width'], $size['height']);
    
    $crop_x = ($size['width'] - $min_dimension) / 2;
    $crop_y = ($size['height'] - $min_dimension) / 2;
    
    $editor->crop($crop_x, $crop_y, $min_dimension, $min_dimension);
    
    // Boyutlandır
    if ($min_dimension > $max_size) {
        $editor->resize($max_size, $max_size);
    }
    
    // Kaydet
    $saved = $editor->save();
    
    if (is_wp_error($saved)) {
        return $saved;
    }
    
    return $saved['path'];
}

/**
 * Özel avatar URL'i getir
 */
function corement_get_custom_avatar_url($user_id) {
    return get_user_meta($user_id, 'corement_custom_avatar', true);
}

/**
 * Avatar sistemini WordPress'e entegre et
 */
add_filter('get_avatar', 'corement_filter_get_avatar', 10, 5);

function corement_filter_get_avatar($avatar, $id_or_email, $size, $default, $alt) {
    // Sadece Corement yorumları için özel avatar kullan
    if (!is_admin() && (is_single() || is_page())) {
        // Eğer bu bir Corement yorumu ise özel avatar sistemini kullan
        global $comment;
        if ($comment && get_comment_meta($comment->comment_ID, 'corement_version', true)) {
            $user_id = null;
            $email = '';
            $name = $alt;
            
            if (is_numeric($id_or_email)) {
                $user_id = $id_or_email;
                $user = get_user_by('id', $user_id);
                if ($user) {
                    $email = $user->user_email;
                    $name = $user->display_name;
                }
            } elseif (is_object($id_or_email)) {
                if (isset($id_or_email->user_id) && $id_or_email->user_id) {
                    $user_id = $id_or_email->user_id;
                }
                $email = $id_or_email->comment_author_email ?? '';
                $name = $id_or_email->comment_author ?? '';
            } elseif (is_string($id_or_email)) {
                $email = $id_or_email;
            }
            
            return corement_get_avatar($email, $size, $name, $user_id);
        }
    }
    
    return $avatar;
}

/**
 * Admin panelinde avatar önizlemesi
 */
function corement_admin_avatar_preview($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) return '';
    
    $avatar = corement_get_avatar($user->user_email, 64, $user->display_name, $user_id);
    
    return '<div class="corement-admin-avatar-preview">' . $avatar . '</div>';
}

/**
 * Avatar cache temizleme hook'u
 */
add_action('profile_update', function($user_id) {
    $user = get_user_by('id', $user_id);
    if ($user) {
        corement_clear_avatar_cache($user->user_email);
    }
});

/**
 * Avatar CSS sınıfları
 */
function corement_avatar_css_classes($email, $user_id = null) {
    $classes = array('corement-avatar');
    
    if ($user_id) {
        $classes[] = 'corement-avatar-user';
        $classes[] = 'corement-avatar-user-' . $user_id;
    } else {
        $classes[] = 'corement-avatar-guest';
    }
    
    if (corement_has_gravatar($email)) {
        $classes[] = 'corement-avatar-gravatar';
    } else {
        $classes[] = 'corement-avatar-default';
    }
    
    return implode(' ', $classes);
}

