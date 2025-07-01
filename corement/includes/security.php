<?php
/**
 * Corement - Güvenlik Fonksiyonları
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSRF koruması için nonce doğrulama
 */
function corement_verify_nonce($action = 'corement_action') {
    if (!isset($_POST['corement_nonce']) || !wp_verify_nonce($_POST['corement_nonce'], $action)) {
        wp_die(__('Security check failed. Please try again.', 'corement'));
    }
}

/**
 * XSS koruması için veri temizleme
 */
function corement_sanitize_comment_data($data) {
    $sanitized = array();
    
    $sanitized['name'] = sanitize_text_field($data['name'] ?? '');
    $sanitized['email'] = sanitize_email($data['email'] ?? '');
    $sanitized['comment'] = wp_kses($data['comment'] ?? '', array(
        'br' => array(),
        'p' => array(),
        'strong' => array(),
        'em' => array(),
        'a' => array('href' => array(), 'title' => array()),
        'blockquote' => array(),
        'code' => array()
    ));
    $sanitized['parent_id'] = absint($data['parent_id'] ?? 0);
    
    return $sanitized;
}

/**
 * Dosya yükleme güvenliği
 */
function corement_validate_upload($file) {
    $errors = array();
    
    // Dosya boyutu kontrolü
    $max_size = get_option('corement_media_limit', 2) * 1024 * 1024; // MB to bytes
    if ($file['size'] > $max_size) {
        $errors[] = sprintf(__('File size exceeds %d MB limit.', 'corement'), $max_size / 1024 / 1024);
    }
    
    // Dosya türü kontrolü
    $allowed_types = array(
        'image/jpeg',
        'image/png', 
        'image/gif',
        'image/webp'
    );
    
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = __('Only JPEG, PNG, GIF and WebP images are allowed.', 'corement');
    }
    
    // Dosya uzantısı kontrolü
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $errors[] = __('Invalid file extension.', 'corement');
    }
    
    // MIME type ve uzantı uyumu
    $mime_to_ext = array(
        'image/jpeg' => array('jpg', 'jpeg'),
        'image/png' => array('png'),
        'image/gif' => array('gif'),
        'image/webp' => array('webp')
    );
    
    if (isset($mime_to_ext[$file['type']]) && !in_array($file_extension, $mime_to_ext[$file['type']])) {
        $errors[] = __('File type and extension do not match.', 'corement');
    }
    
    // Dosya içeriği kontrolü (basit)
    if (function_exists('getimagesize')) {
        $image_info = @getimagesize($file['tmp_name']);
        if ($image_info === false) {
            $errors[] = __('Invalid image file.', 'corement');
        }
    }
    
    return $errors;
}

/**
 * IP adresi alma (proxy arkasında da çalışır)
 */
function corement_get_user_ip() {
    $ip_keys = array(
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Standard
    );
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            
            // Virgülle ayrılmış IP listesi varsa ilkini al
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            
            $ip = trim($ip);
            
            // IP geçerliliğini kontrol et
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Kara liste kontrolü
 */
function corement_check_blacklist($content, $email = '', $name = '') {
    $blacklist = get_option('corement_blacklist', '');
    
    if (empty($blacklist)) {
        return true;
    }
    
    $blacklist_words = array_filter(array_map('trim', explode(',', strtolower($blacklist))));
    $check_content = strtolower($content . ' ' . $email . ' ' . $name);
    
    foreach ($blacklist_words as $word) {
        if (!empty($word) && strpos($check_content, $word) !== false) {
            return false;
        }
    }
    
    return true;
}

/**
 * Spam kontrolü (basit heuristic)
 */
function corement_check_spam($data) {
    $spam_indicators = 0;
    $content = strtolower($data['comment']);
    
    // Çok fazla link
    if (substr_count($content, 'http') > 3) {
        $spam_indicators += 2;
    }
    
    // Çok fazla büyük harf
    $uppercase_ratio = strlen(preg_replace('/[^A-Z]/', '', $data['comment'])) / strlen($data['comment']);
    if ($uppercase_ratio > 0.5) {
        $spam_indicators += 1;
    }
    
    // Tekrarlayan karakterler
    if (preg_match('/(.)\1{4,}/', $content)) {
        $spam_indicators += 1;
    }
    
    // Çok kısa veya çok uzun
    $length = strlen(trim($data['comment']));
    if ($length < 3 || $length > 5000) {
        $spam_indicators += 1;
    }
    
    // Sadece sayı ve sembol
    if (preg_match('/^[0-9\s\W]+$/', trim($data['comment']))) {
        $spam_indicators += 2;
    }
    
    // Spam kelimeleri
    $spam_words = array('viagra', 'casino', 'poker', 'loan', 'mortgage', 'pharmacy', 'replica');
    foreach ($spam_words as $word) {
        if (strpos($content, $word) !== false) {
            $spam_indicators += 2;
        }
    }
    
    return $spam_indicators >= 3;
}

/**
 * Honeypot alanı kontrolü
 */
function corement_check_honeypot() {
    // Gizli alan doldurulmuşsa bot
    if (!empty($_POST['corement_website'])) {
        return false;
    }
    
    return true;
}

/**
 * Zaman bazlı spam kontrolü
 */
function corement_check_timing() {
    // Form çok hızlı gönderilmişse bot olabilir
    if (isset($_POST['corement_timestamp'])) {
        $form_time = intval($_POST['corement_timestamp']);
        $current_time = time();
        
        // 3 saniyeden az sürede gönderilmişse şüpheli
        if (($current_time - $form_time) < 3) {
            return false;
        }
    }
    
    return true;
}

/**
 * Genel güvenlik kontrolü
 */
function corement_security_check($data) {
    $errors = array();
    
    // Rate limiting
    $user_ip = corement_get_user_ip();
    if (!corement_check_rate_limit($user_ip, 'comment', get_option('corement_max_comments_per_hour', 10))) {
        $errors[] = __('Too many comments. Please wait before commenting again.', 'corement');
    }
    
    // Kara liste
    if (!corement_check_blacklist($data['comment'], $data['email'], $data['name'])) {
        $errors[] = __('Your comment contains prohibited content.', 'corement');
    }
    
    // Spam kontrolü
    if (corement_check_spam($data)) {
        $errors[] = __('Your comment appears to be spam.', 'corement');
    }
    
    // Honeypot
    if (!corement_check_honeypot()) {
        $errors[] = __('Bot detected.', 'corement');
    }
    
    // Timing
    if (!corement_check_timing()) {
        $errors[] = __('Comment submitted too quickly.', 'corement');
    }
    
    // Duplicate content check
    if (corement_check_duplicate_content($data)) {
        $errors[] = __('Duplicate comment detected.', 'corement');
    }
    
    return $errors;
}

/**
 * Duplicate content check
 */
function corement_check_duplicate_content($data) {
    global $wpdb;
    
    $post_id = absint($_POST['corement_post_id'] ?? 0);
    if (!$post_id) return false;
    
    // Son 24 saat içinde aynı içerikle yorum var mı kontrol et
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT comment_ID FROM {$wpdb->comments} 
         WHERE comment_post_ID = %d 
         AND comment_content = %s 
         AND comment_date > %s
         AND comment_type = 'corement'
         LIMIT 1",
        $post_id,
        $data['comment'],
        date('Y-m-d H:i:s', strtotime('-24 hours'))
    ));
    
    return !empty($existing);
}

/**
 * SQL injection koruması için hazırlanmış sorgu kullanımını zorla
 */
function corement_prepare_query($query, $args = array()) {
    global $wpdb;
    
    if (empty($args)) {
        return $query;
    }
    
    return $wpdb->prepare($query, $args);
}

/**
 * Admin yetkisi kontrolü
 */
function corement_check_admin_permission() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'corement'));
    }
}



/**
 * Gelişmiş XSS koruması
 */
function corement_advanced_xss_protection($content) {
    // Tehlikeli HTML etiketlerini kaldır
    $dangerous_tags = array(
        'script', 'iframe', 'object', 'embed', 'form', 'input', 
        'button', 'textarea', 'select', 'option', 'meta', 'link',
        'style', 'base', 'frame', 'frameset', 'applet'
    );
    
    foreach ($dangerous_tags as $tag) {
        $content = preg_replace('/<' . $tag . '[^>]*>.*?<\/' . $tag . '>/is', '', $content);
        $content = preg_replace('/<' . $tag . '[^>]*\/?>/is', '', $content);
    }
    
    // Tehlikeli attributeları kaldır
    $dangerous_attrs = array(
        'onload', 'onerror', 'onclick', 'onmouseover', 'onmouseout',
        'onfocus', 'onblur', 'onchange', 'onsubmit', 'onreset',
        'onselect', 'onkeydown', 'onkeyup', 'onkeypress'
    );
    
    foreach ($dangerous_attrs as $attr) {
        $content = preg_replace('/' . $attr . '\s*=\s*["\'][^"\']*["\']/i', '', $content);
    }
    
    // JavaScript protokollerini kaldır
    $content = preg_replace('/javascript\s*:/i', '', $content);
    $content = preg_replace('/vbscript\s*:/i', '', $content);
    $content = preg_replace('/data\s*:/i', '', $content);
    
    return $content;
}

/**
 * CSRF token oluştur
 */
function corement_generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['corement_csrf_token'])) {
        $_SESSION['corement_csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['corement_csrf_token'];
}

/**
 * CSRF token doğrula
 */
function corement_verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['corement_csrf_token']) && 
           hash_equals($_SESSION['corement_csrf_token'], $token);
}

/**
 * IP tabanlı güvenlik kontrolü
 */
function corement_check_ip_security($ip) {
    // Bilinen kötü IP'leri kontrol et (basit blacklist)
    $blocked_ips = get_option('corement_blocked_ips', array());
    
    if (in_array($ip, $blocked_ips)) {
        return false;
    }
    
    // IP formatını kontrol et
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    
    // Özel IP aralıklarını kontrol et (isteğe bağlı)
    if (get_option('corement_block_private_ips', false)) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
    }
    
    return true;
}

/**
 * User-Agent tabanlı bot algılama
 */
function corement_detect_bot_by_user_agent() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (empty($user_agent)) {
        return true; // Boş user agent şüpheli
    }
    
    // Bilinen bot user agent'ları
    $bot_patterns = array(
        '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
        '/curl/i', '/wget/i', '/python/i', '/java/i',
        '/perl/i', '/ruby/i', '/php/i'
    );
    
    foreach ($bot_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Referrer kontrolü
 */
function corement_check_referrer() {
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    // Referrer yoksa şüpheli
    if (empty($referrer)) {
        return false;
    }
    
    // Referrer aynı domain'den gelmiyorsa şüpheli
    $referrer_host = parse_url($referrer, PHP_URL_HOST);
    if ($referrer_host !== $host) {
        return false;
    }
    
    return true;
}

/**
 * Gelişmiş spam algılama
 */
function corement_advanced_spam_detection($data) {
    $spam_score = 0;
    $content = strtolower($data['comment']);
    
    // URL sayısı
    $url_count = substr_count($content, 'http');
    if ($url_count > 2) $spam_score += $url_count * 2;
    
    // Tekrarlayan kelimeler
    $words = str_word_count($content, 1);
    $word_counts = array_count_values($words);
    foreach ($word_counts as $count) {
        if ($count > 3) $spam_score += $count;
    }
    
    // Büyük harf oranı
    $uppercase_ratio = strlen(preg_replace('/[^A-Z]/', '', $data['comment'])) / strlen($data['comment']);
    if ($uppercase_ratio > 0.3) $spam_score += 5;
    
    // Sayı oranı
    $number_ratio = strlen(preg_replace('/[^0-9]/', '', $content)) / strlen($content);
    if ($number_ratio > 0.3) $spam_score += 3;
    
    // Özel karakter oranı
    $special_ratio = strlen(preg_replace('/[a-zA-Z0-9\s]/', '', $content)) / strlen($content);
    if ($special_ratio > 0.2) $spam_score += 3;
    
    // Spam kelimeleri (gelişmiş)
    $spam_keywords = array(
        'viagra', 'cialis', 'casino', 'poker', 'loan', 'mortgage',
        'pharmacy', 'replica', 'rolex', 'weight loss', 'make money',
        'work from home', 'click here', 'buy now', 'limited time',
        'act now', 'congratulations', 'winner', 'prize', 'lottery'
    );
    
    foreach ($spam_keywords as $keyword) {
        if (strpos($content, $keyword) !== false) {
            $spam_score += 10;
        }
    }
    
    return $spam_score > 15;
}

/**
 * Güvenlik logları
 */
function corement_log_security_event($event_type, $details = array()) {
    if (!get_option('corement_enable_security_logging', false)) {
        return;
    }
    
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'ip' => corement_get_user_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'event_type' => $event_type,
        'details' => $details,
        'user_id' => get_current_user_id() ?: null
    );
    
    $logs = get_option('corement_security_logs', array());
    $logs[] = $log_entry;
    
    // Son 1000 log'u tut
    if (count($logs) > 1000) {
        $logs = array_slice($logs, -1000);
    }
    
    update_option('corement_security_logs', $logs);
}

/**
 * Güvenlik loglarını temizle
 */
function corement_cleanup_security_logs() {
    $logs = get_option('corement_security_logs', array());
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    $filtered_logs = array_filter($logs, function($log) use ($cutoff_date) {
        return $log['timestamp'] > $cutoff_date;
    });
    
    update_option('corement_security_logs', array_values($filtered_logs));
}

/**
 * Güvenlik başlıkları ekle
 */
function corement_add_security_headers() {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

// Güvenlik başlıklarını ekle
add_action('send_headers', 'corement_add_security_headers');

// Güvenlik loglarını temizleme işi
add_action('corement_cleanup_security_logs', 'corement_cleanup_security_logs');

// Günlük log temizleme işini zamanla
if (!wp_next_scheduled('corement_cleanup_security_logs')) {
    wp_schedule_event(time(), 'daily', 'corement_cleanup_security_logs');
}

