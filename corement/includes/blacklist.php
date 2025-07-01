<?php
/**
 * Corement - Kara Liste Yönetimi
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Kara liste kontrolü
 */
function corement_check_blacklist_content($content, $email = '', $name = '') {
    $blacklist = get_option('corement_blacklist', '');
    
    if (empty($blacklist)) {
        return true;
    }
    
    $blacklist_words = corement_parse_blacklist($blacklist);
    $check_content = strtolower($content . ' ' . $email . ' ' . $name);
    
    foreach ($blacklist_words as $word) {
        if (!empty($word) && corement_word_matches($check_content, $word)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Kara liste metnini parse et
 */
function corement_parse_blacklist($blacklist) {
    // Virgül, yeni satır veya noktalı virgülle ayrılmış kelimeleri parse et
    $separators = array(',', "\n", "\r\n", ';');
    
    foreach ($separators as $separator) {
        $blacklist = str_replace($separator, '|', $blacklist);
    }
    
    $words = explode('|', $blacklist);
    
    // Boş değerleri ve fazla boşlukları temizle
    $words = array_filter(array_map('trim', $words));
    
    return array_unique($words);
}

/**
 * Kelime eşleşme kontrolü
 */
function corement_word_matches($content, $word) {
    $word = trim(strtolower($word));
    
    if (empty($word)) {
        return false;
    }
    
    // Wildcard desteği (* karakteri)
    if (strpos($word, '*') !== false) {
        $pattern = str_replace('*', '.*', preg_quote($word, '/'));
        return preg_match('/' . $pattern . '/i', $content);
    }
    
    // Tam kelime eşleşmesi
    if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $content)) {
        return true;
    }
    
    // Kısmi eşleşme
    return strpos($content, $word) !== false;
}

/**
 * IP adresi kara liste kontrolü
 */
function corement_check_ip_blacklist($ip) {
    $ip_blacklist = get_option('corement_ip_blacklist', '');
    
    if (empty($ip_blacklist)) {
        return true;
    }
    
    $blocked_ips = corement_parse_blacklist($ip_blacklist);
    
    foreach ($blocked_ips as $blocked_ip) {
        if (corement_ip_matches($ip, $blocked_ip)) {
            return false;
        }
    }
    
    return true;
}

/**
 * IP eşleşme kontrolü
 */
function corement_ip_matches($ip, $pattern) {
    $pattern = trim($pattern);
    
    if (empty($pattern)) {
        return false;
    }
    
    // Tam eşleşme
    if ($ip === $pattern) {
        return true;
    }
    
    // CIDR notasyonu desteği (örn: 192.168.1.0/24)
    if (strpos($pattern, '/') !== false) {
        return corement_ip_in_cidr($ip, $pattern);
    }
    
    // Wildcard desteği (örn: 192.168.1.*)
    if (strpos($pattern, '*') !== false) {
        $regex_pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        return preg_match('/^' . $regex_pattern . '$/', $ip);
    }
    
    return false;
}

/**
 * IP adresinin CIDR aralığında olup olmadığını kontrol et
 */
function corement_ip_in_cidr($ip, $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || 
        !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - (int)$mask);
    
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
}

/**
 * E-posta kara liste kontrolü
 */
function corement_check_email_blacklist($email) {
    $email_blacklist = get_option('corement_email_blacklist', '');
    
    if (empty($email_blacklist)) {
        return true;
    }
    
    $blocked_emails = corement_parse_blacklist($email_blacklist);
    $email = strtolower(trim($email));
    
    foreach ($blocked_emails as $blocked_email) {
        $blocked_email = strtolower(trim($blocked_email));
        
        // Domain kontrolü (@domain.com formatı)
        if (strpos($blocked_email, '@') === 0) {
            $domain = substr($blocked_email, 1);
            if (strpos($email, '@' . $domain) !== false) {
                return false;
            }
        }
        // Tam e-posta kontrolü
        elseif ($email === $blocked_email) {
            return false;
        }
        // Wildcard kontrolü
        elseif (strpos($blocked_email, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($blocked_email, '/'));
            if (preg_match('/^' . $pattern . '$/', $email)) {
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Kara liste istatistikleri
 */
function corement_get_blacklist_stats() {
    $stats = array();
    
    // Kelime kara listesi
    $word_blacklist = get_option('corement_blacklist', '');
    $stats['blocked_words'] = count(corement_parse_blacklist($word_blacklist));
    
    // IP kara listesi
    $ip_blacklist = get_option('corement_ip_blacklist', '');
    $stats['blocked_ips'] = count(corement_parse_blacklist($ip_blacklist));
    
    // E-posta kara listesi
    $email_blacklist = get_option('corement_email_blacklist', '');
    $stats['blocked_emails'] = count(corement_parse_blacklist($email_blacklist));
    
    return $stats;
}

/**
 * Otomatik kara liste güncelleme (spam veritabanlarından)
 */
function corement_update_automatic_blacklist() {
    if (!get_option('corement_auto_blacklist_update', false)) {
        return;
    }
    
    // Bilinen spam kelimelerini ekle
    $spam_words = corement_get_common_spam_words();
    $current_blacklist = get_option('corement_blacklist', '');
    $current_words = corement_parse_blacklist($current_blacklist);
    
    $new_words = array_diff($spam_words, $current_words);
    
    if (!empty($new_words)) {
        $updated_blacklist = $current_blacklist . ',' . implode(',', $new_words);
        update_option('corement_blacklist', $updated_blacklist);
    }
}

/**
 * Yaygın spam kelimelerini getir
 */
function corement_get_common_spam_words() {
    return array(
        'viagra', 'cialis', 'casino', 'poker', 'gambling',
        'loan', 'mortgage', 'insurance', 'pharmacy', 'replica',
        'rolex', 'weight loss', 'make money', 'work from home',
        'click here', 'buy now', 'limited time', 'act now',
        'congratulations', 'winner', 'prize', 'lottery',
        'free money', 'earn cash', 'get rich', 'investment',
        'bitcoin', 'cryptocurrency', 'trading', 'forex'
    );
}

/**
 * Kara liste logları
 */
function corement_log_blacklist_hit($type, $value, $content = '') {
    if (!get_option('corement_log_blacklist_hits', false)) {
        return;
    }
    
    $logs = get_option('corement_blacklist_logs', array());
    
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'type' => $type, // 'word', 'ip', 'email'
        'value' => $value,
        'content' => substr($content, 0, 100), // İlk 100 karakter
        'ip' => corement_get_user_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    $logs[] = $log_entry;
    
    // Son 500 log'u tut
    if (count($logs) > 500) {
        $logs = array_slice($logs, -500);
    }
    
    update_option('corement_blacklist_logs', $logs);
}

/**
 * Kara liste loglarını temizle
 */
function corement_cleanup_blacklist_logs() {
    $logs = get_option('corement_blacklist_logs', array());
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    $filtered_logs = array_filter($logs, function($log) use ($cutoff_date) {
        return $log['timestamp'] > $cutoff_date;
    });
    
    update_option('corement_blacklist_logs', array_values($filtered_logs));
}

/**
 * Kara liste export/import
 */
function corement_export_blacklist() {
    $data = array(
        'words' => get_option('corement_blacklist', ''),
        'ips' => get_option('corement_ip_blacklist', ''),
        'emails' => get_option('corement_email_blacklist', ''),
        'exported_at' => current_time('mysql'),
        'version' => COREMENT_VERSION
    );
    
    return json_encode($data, JSON_PRETTY_PRINT);
}

/**
 * Kara liste import
 */
function corement_import_blacklist($json_data) {
    $data = json_decode($json_data, true);
    
    if (!$data || !is_array($data)) {
        return new WP_Error('invalid_data', __('Invalid blacklist data.', 'corement'));
    }
    
    if (isset($data['words'])) {
        update_option('corement_blacklist', sanitize_textarea_field($data['words']));
    }
    
    if (isset($data['ips'])) {
        update_option('corement_ip_blacklist', sanitize_textarea_field($data['ips']));
    }
    
    if (isset($data['emails'])) {
        update_option('corement_email_blacklist', sanitize_textarea_field($data['emails']));
    }
    
    return true;
}

/**
 * Günlük kara liste temizleme işi
 */
add_action('corement_cleanup_blacklist_logs', 'corement_cleanup_blacklist_logs');

// Kara liste temizleme işini zamanla
if (!wp_next_scheduled('corement_cleanup_blacklist_logs')) {
    wp_schedule_event(time(), 'daily', 'corement_cleanup_blacklist_logs');
}

/**
 * Haftalık otomatik kara liste güncelleme
 */
add_action('corement_update_automatic_blacklist', 'corement_update_automatic_blacklist');

// Otomatik güncelleme işini zamanla
if (!wp_next_scheduled('corement_update_automatic_blacklist')) {
    wp_schedule_event(time(), 'weekly', 'corement_update_automatic_blacklist');
}

