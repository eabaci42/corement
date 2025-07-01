<?php
/**
 * Corement - Yorum Formu ve Gönderim İşlemleri
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Yorum formunu render et
 */
add_action('corement_render_comment_form', function($atts = array()) {
    global $corement_current_post_id;
    
    $post_id = $corement_current_post_id ?? get_the_ID();
    $current_user = wp_get_current_user();
    $is_logged_in = is_user_logged_in();
    
    // Session başlat
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    ?>
    <div class="corement-container" id="corement-container">
        <div class="corement-form-section">
            <h3 class="corement-form-title"><?php _e('Leave a Comment', 'corement'); ?></h3>
            
            <?php if (isset($_SESSION['corement_error'])): ?>
                <div class="corement-message corement-error">
                    <?php echo esc_html($_SESSION['corement_error']); ?>
                </div>
                <?php unset($_SESSION['corement_error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['corement_success'])): ?>
                <div class="corement-message corement-success">
                    <?php echo esc_html($_SESSION['corement_success']); ?>
                </div>
                <?php unset($_SESSION['corement_success']); ?>
            <?php endif; ?>
            
            <form id="corement-comment-form" class="corement-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('corement_submit_comment', 'corement_nonce'); ?>
                <input type="hidden" name="corement_action" value="submit_comment" />
                <input type="hidden" name="corement_post_id" value="<?php echo esc_attr($post_id); ?>" />
                <input type="hidden" name="corement_timestamp" value="<?php echo time(); ?>" />
                <input type="hidden" name="corement_website" value="" class="corement-honeypot" />
                
                <div class="corement-user-info">
                    <?php if ($is_logged_in): ?>
                        <input type="hidden" name="corement_name" value="<?php echo esc_attr($current_user->display_name); ?>" />
                        <input type="hidden" name="corement_email" value="<?php echo esc_attr($current_user->user_email); ?>" />
                        
                        <div class="corement-logged-user">
                            <?php echo corement_get_responsive_avatar($current_user->user_email, $current_user->display_name, $current_user->ID, 'small'); ?>
                            <span class="corement-user-name"><?php echo esc_html($current_user->display_name); ?></span>
                            <a href="<?php echo wp_logout_url(get_permalink()); ?>" class="corement-logout"><?php _e('Logout', 'corement'); ?></a>
                        </div>
                    <?php else: ?>
                        <div class="corement-guest-fields">
                            <div class="corement-field-group">
                                <input type="text" 
                                       name="corement_name" 
                                       id="corement_name"
                                       placeholder="<?php _e('Your Name *', 'corement'); ?>" 
                                       required 
                                       class="corement-input" />
                            </div>
                            <div class="corement-field-group">
                                <input type="email" 
                                       name="corement_email" 
                                       id="corement_email"
                                       placeholder="<?php _e('Your Email *', 'corement'); ?>" 
                                       required 
                                       class="corement-input" />
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="corement-comment-field">
                    <textarea name="corement_comment" 
                              id="corement_comment"
                              placeholder="<?php _e('Write your comment...', 'corement'); ?>" 
                              required 
                              class="corement-textarea"
                              rows="4"></textarea>
                </div>
                
                <div class="corement-media-field">
                    <label for="corement_media" class="corement-media-label">
                        <span class="corement-media-icon">📎</span>
                        <span class="corement-media-text"><?php _e('Attach Image/GIF', 'corement'); ?></span>
                        <input type="file" 
                               name="corement_media" 
                               id="corement_media"
                               accept="image/*,.gif" 
                               class="corement-file-input" />
                    </label>
                    <div class="corement-media-preview" id="corement-media-preview"></div>
                    <small class="corement-media-info">
                        <?php printf(__('Max size: %d MB. Formats: JPG, PNG, GIF, WebP', 'corement'), get_option('corement_media_limit', 2)); ?>
                    </small>
                </div>
                
                <div class="corement-form-actions">
                    <button type="submit" class="corement-submit-btn" id="corement-submit-btn">
                        <span class="corement-btn-text"><?php _e('Post Comment', 'corement'); ?></span>
                        <span class="corement-btn-loading" style="display: none;"><?php _e('Posting...', 'corement'); ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
});

/**
 * Yorum gönderimini işle
 */
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        isset($_POST['corement_action']) && 
        $_POST['corement_action'] === 'submit_comment') {
        
        corement_handle_comment_submission();
    }
});

/**
 * Yorum gönderim işlemini yönet
 */
function corement_handle_comment_submission() {
    // Session başlat
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    try {
        // Nonce kontrolü
        if (!wp_verify_nonce($_POST['corement_nonce'] ?? '', 'corement_submit_comment')) {
            throw new Exception(__('Security check failed. Please try again.', 'corement'));
        }
        
        // Veri temizleme
        $data = corement_sanitize_comment_data($_POST);
        
        // Güvenlik kontrolleri
        $security_errors = corement_security_check($data);
        if (!empty($security_errors)) {
            throw new Exception(implode(' ', $security_errors));
        }
        
        // Post ID kontrolü
        $post_id = absint($_POST['corement_post_id'] ?? 0);
        if (!$post_id || !get_post($post_id)) {
            throw new Exception(__('Invalid post.', 'corement'));
        }
        
        // Medya yükleme
        $media_url = '';
        if (!empty($_FILES['corement_media']['name'])) {
            $media_url = corement_handle_media_upload($_FILES['corement_media']);
        }
        
        // Yorum içeriğini hazırla
        $comment_content = $data['comment'];
        if ($media_url) {
            $comment_content .= "\n\n" . corement_generate_media_html($media_url);
        }
        
        // WordPress yorum verisi
        $commentdata = array(
            'comment_post_ID' => $post_id,
            'comment_author' => $data['name'],
            'comment_author_email' => $data['email'],
            'comment_content' => $comment_content,
            'comment_parent' => $data['parent_id'],
            'comment_approved' => get_option('corement_auto_approve', false) ? 1 : 0,
            'comment_type' => 'corement',
            'comment_meta' => array(
                'corement_version' => COREMENT_VERSION,
                'corement_ip' => corement_get_user_ip()
            )
        );
        
        // Kayıtlı kullanıcı ise user_id ekle
        if (is_user_logged_in()) {
            $commentdata['user_id'] = get_current_user_id();
        }
        
        // Yorumu kaydet
        $comment_id = wp_new_comment($commentdata);
        
        if (!$comment_id) {
            throw new Exception(__('Failed to save comment. Please try again.', 'corement'));
        }
        
        // Medya bilgisini kaydet
        if ($media_url) {
            corement_save_media_info($comment_id, $media_url, $_FILES['corement_media']);
        }
        
        // Başarı mesajı
        $message = get_option('corement_auto_approve', false) 
            ? __('Your comment has been posted successfully!', 'corement')
            : __('Your comment has been submitted and is awaiting moderation.', 'corement');
            
        $_SESSION['corement_success'] = $message;
        
    } catch (Exception $e) {
        $_SESSION['corement_error'] = $e->getMessage();
    }
    
    // Sayfayı yenile
    wp_safe_redirect($_SERVER['HTTP_REFERER'] . '#corement-container');
    exit;
}

/**
 * Medya yükleme işlemi
 */
function corement_handle_media_upload($file) {
    // Dosya doğrulama
    $validation_errors = corement_validate_upload($file);
    if (!empty($validation_errors)) {
        throw new Exception(implode(' ', $validation_errors));
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
        throw new Exception($uploaded_file['error']);
    }
    
    return $uploaded_file['url'];
}

/**
 * Medya HTML'i oluştur
 */
function corement_generate_media_html($media_url) {
    $file_extension = strtolower(pathinfo($media_url, PATHINFO_EXTENSION));
    
    $html = '<div class="corement-media-attachment">';
    
    if (in_array($file_extension, array('gif'))) {
        $html .= '<img src="' . esc_url($media_url) . '" alt="' . __('Attached GIF', 'corement') . '" class="corement-media-gif" />';
    } else {
        $html .= '<a href="' . esc_url($media_url) . '" target="_blank" class="corement-media-link">';
        $html .= '<img src="' . esc_url($media_url) . '" alt="' . __('Attached Image', 'corement') . '" class="corement-media-image" />';
        $html .= '</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Medya bilgisini veritabanına kaydet
 */
function corement_save_media_info($comment_id, $media_url, $file_info) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_media';
    
    $wpdb->insert(
        $table,
        array(
            'comment_id' => $comment_id,
            'file_url' => $media_url,
            'file_type' => $file_info['type'],
            'file_size' => $file_info['size'],
            'created_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%d', '%s')
    );
}

/**
 * AJAX ile yorum gönderimi (gelecek sürüm için)
 */
add_action('wp_ajax_corement_submit_comment', 'corement_ajax_submit_comment');
add_action('wp_ajax_nopriv_corement_submit_comment', 'corement_ajax_submit_comment');

function corement_ajax_submit_comment() {
    try {
        // Nonce kontrolü
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'corement_nonce')) {
            throw new Exception(__('Security check failed.', 'corement'));
        }
        
        // Normal form işlemi ile aynı
        // Bu kısım gelecek sürümde AJAX desteği için kullanılacak
        
        wp_send_json_success(array(
            'message' => __('Comment submitted successfully!', 'corement')
        ));
        
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}

