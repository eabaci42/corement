<?php
// Corement - Yorum Formu ve Gönderim İşlemleri
if (!defined('ABSPATH')) exit;

// Yorum formunu render et
add_action('corement_render_comment_form', function() {
    $current_user = wp_get_current_user();
    $is_logged_in = is_user_logged_in();
    $guest_avatar = get_option('corement_guest_avatar', plugins_url('assets/img/default-avatar.png', __FILE__));
    ?>
    <div class="corement-container">
        <?php if (isset($_SESSION['corement_error'])): ?>
            <div class="corement-error" style="color:red;"> <?php echo esc_html($_SESSION['corement_error']); unset($_SESSION['corement_error']); ?> </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['corement_success'])): ?>
            <div class="corement-success" style="color:green;"> <?php echo esc_html($_SESSION['corement_success']); unset($_SESSION['corement_success']); ?> </div>
        <?php endif; ?>
        <form id="corement-comment-form" enctype="multipart/form-data" method="post">
            <?php if ($is_logged_in): ?>
                <input type="hidden" name="corement_name" value="<?php echo esc_attr($current_user->display_name); ?>" />
                <input type="hidden" name="corement_email" value="<?php echo esc_attr($current_user->user_email); ?>" />
                <div><strong><?php echo esc_html($current_user->display_name); ?></strong> olarak yorum yapıyorsunuz.</div>
            <?php else: ?>
                <input type="text" name="corement_name" placeholder="Adınız" required />
                <input type="email" name="corement_email" placeholder="E-posta" required />
            <?php endif; ?>
            <textarea name="corement_comment" placeholder="Yorumunuz..." required></textarea>
            <div>
                <label>Medya (resim/gif): <input type="file" name="corement_media" accept="image/*,image/gif" /></label>
            </div>
            <input type="hidden" name="corement_action" value="submit_comment" />
            <button type="submit">Yorumu Gönder</button>
        </form>
    </div>
    <?php
});

// Yorum gönderimini işle
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['corement_action']) && $_POST['corement_action'] === 'submit_comment') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $name = sanitize_text_field($_POST['corement_name'] ?? '');
        $email = sanitize_email($_POST['corement_email'] ?? '');
        $comment = sanitize_textarea_field($_POST['corement_comment'] ?? '');
        $post_id = get_the_ID();
        // Kara liste kontrolü
        $blacklist = get_option('corement_blacklist', '');
        $blacklist_words = array_filter(array_map('trim', explode(',', $blacklist)));
        foreach ($blacklist_words as $word) {
            if (stripos($comment, $word) !== false) {
                $_SESSION['corement_error'] = 'Yorumunuzda yasaklı kelime ("' . esc_html($word) . '") bulundu!';
                wp_safe_redirect($_SERVER['HTTP_REFERER']);
                exit;
            }
        }
        // Medya yükleme kontrolü
        $media_url = '';
        if (!empty($_FILES['corement_media']['name'])) {
            $file = $_FILES['corement_media'];
            $allowed_types = array('image/jpeg','image/png','image/gif','image/webp');
            $allowed_exts = array('jpg','jpeg','png','gif','webp');
            $max_size = (int)get_option('corement_media_limit', 2) * 1024 * 1024;
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($file['type'], $allowed_types) || !in_array($file_ext, $allowed_exts) || $file['size'] > $max_size) {
                $_SESSION['corement_error'] = 'Yalnızca resim/gif ve maksimum ' . ($max_size/1024/1024) . 'MB dosya yükleyebilirsiniz!';
                wp_safe_redirect($_SERVER['HTTP_REFERER']);
                exit;
            }
            $upload = wp_handle_upload($file, array('test_form' => false));
            if (isset($upload['url'])) {
                $media_url = $upload['url'];
            }
        }
        // Yorum verisini hazırla
        $media_html = '';
        if ($media_url) {
            $media_html = "\n<div class='corement-media'><a href='".esc_url($media_url)."' target='_blank'><img src='".esc_url($media_url)."' style='max-width:200px;max-height:200px;border-radius:6px;' /></a></div>";
        }
        $allowed_tags = array(
            'a' => array('href'=>array(),'target'=>array(),'rel'=>array()),
            'img' => array('src'=>array(),'style'=>array(),'alt'=>array()),
            'div' => array('class'=>array()),
            'br' => array(),
            'strong' => array(),
            'em' => array(),
            'p' => array(),
        );
        $commentdata = array(
            'comment_post_ID' => get_the_ID(),
            'comment_author' => $name,
            'comment_author_email' => $email,
            'comment_content' => wp_kses($comment, $allowed_tags) . $media_html,
            'comment_approved' => 0, // Moderasyon için
        );
        $comment_id = wp_new_comment($commentdata);
        if ($comment_id) {
            $_SESSION['corement_success'] = 'Yorumunuz başarıyla gönderildi, onay bekliyor!';
        } else {
            $_SESSION['corement_error'] = 'Yorum gönderilemedi!';
        }
        wp_safe_redirect($_SERVER['HTTP_REFERER']);
        exit;
    }
});
