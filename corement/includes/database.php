<?php
/**
 * Corement - Veritabanı Yapısı ve Migration Sistemi
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Eklenti aktivasyonu sırasında veritabanı tablolarını oluştur
 */
function corement_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Emoji tepkiler tablosu
    $table_reactions = $wpdb->prefix . 'corement_reactions';
    $sql_reactions = "CREATE TABLE $table_reactions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        comment_id bigint(20) NOT NULL,
        user_id bigint(20) DEFAULT NULL,
        user_ip varchar(45) NOT NULL,
        reaction_type varchar(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY comment_id (comment_id),
        KEY user_id (user_id),
        KEY user_ip (user_ip),
        UNIQUE KEY unique_reaction (comment_id, user_id, user_ip, reaction_type)
    ) $charset_collate;";
    
    // Yorum oyları tablosu
    $table_votes = $wpdb->prefix . 'corement_votes';
    $sql_votes = "CREATE TABLE $table_votes (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        comment_id bigint(20) NOT NULL,
        user_id bigint(20) DEFAULT NULL,
        user_ip varchar(45) NOT NULL,
        vote_type tinyint(1) NOT NULL COMMENT '1 for upvote, -1 for downvote',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY comment_id (comment_id),
        KEY user_id (user_id),
        KEY user_ip (user_ip),
        UNIQUE KEY unique_vote (comment_id, user_id, user_ip)
    ) $charset_collate;";
    
    // Medya dosyaları tablosu
    $table_media = $wpdb->prefix . 'corement_media';
    $sql_media = "CREATE TABLE $table_media (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        comment_id bigint(20) NOT NULL,
        file_url varchar(500) NOT NULL,
        file_type varchar(50) NOT NULL,
        file_size bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY comment_id (comment_id)
    ) $charset_collate;";
    
    // Rate limiting tablosu
    $table_rate_limit = $wpdb->prefix . 'corement_rate_limit';
    $sql_rate_limit = "CREATE TABLE $table_rate_limit (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_ip varchar(45) NOT NULL,
        action_type varchar(50) NOT NULL,
        attempt_count int(11) DEFAULT 1,
        last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
        blocked_until datetime DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_ip (user_ip),
        KEY action_type (action_type),
        UNIQUE KEY unique_ip_action (user_ip, action_type)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($sql_reactions);
    dbDelta($sql_votes);
    dbDelta($sql_media);
    dbDelta($sql_rate_limit);
    
    // Veritabanı versiyonunu kaydet
    update_option('corement_db_version', '1.0.0');
}

/**
 * Eklenti deaktivasyonu sırasında temizlik
 */
function corement_deactivation() {
    // Geçici verileri temizle ama tabloları silme
    wp_clear_scheduled_hook('corement_cleanup_rate_limit');
}

/**
 * Eklenti kaldırılması sırasında tam temizlik
 */
function corement_uninstall() {
    global $wpdb;
    
    // Tabloları sil
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}corement_reactions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}corement_votes");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}corement_media");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}corement_rate_limit");
    
    // Ayarları sil
    delete_option('corement_db_version');
    delete_option('corement_blacklist');
    delete_option('corement_media_limit');
    delete_option('corement_guest_avatar');
    delete_option('corement_rate_limit_enabled');
    delete_option('corement_max_comments_per_hour');
    delete_option('corement_auto_approve');
}

/**
 * Veritabanı versiyonunu kontrol et ve gerekirse güncelle
 */
function corement_check_db_version() {
    $current_version = get_option('corement_db_version', '0.0.0');
    $required_version = '1.0.0';
    
    if (version_compare($current_version, $required_version, '<')) {
        corement_create_tables();
    }
}

/**
 * Rate limiting temizlik işi
 */
function corement_cleanup_rate_limit() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_rate_limit';
    
    // 24 saat önceki kayıtları sil
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table WHERE last_attempt < %s",
        date('Y-m-d H:i:s', strtotime('-24 hours'))
    ));
}

/**
 * Yorum için emoji tepki sayılarını getir
 */
function corement_get_reaction_counts($comment_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_reactions';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT reaction_type, COUNT(*) as count 
         FROM $table 
         WHERE comment_id = %d 
         GROUP BY reaction_type",
        $comment_id
    ));
    
    $counts = array(
        'like' => 0,
        'laugh' => 0,
        'wow' => 0,
        'sad' => 0,
        'angry' => 0
    );
    
    foreach ($results as $result) {
        $counts[$result->reaction_type] = (int)$result->count;
    }
    
    return $counts;
}

/**
 * Yorum için oy sayılarını getir
 */
function corement_get_vote_counts($comment_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_votes';
    
    $upvotes = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE comment_id = %d AND vote_type = 1",
        $comment_id
    ));
    
    $downvotes = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE comment_id = %d AND vote_type = -1",
        $comment_id
    ));
    
    return array(
        'upvotes' => (int)$upvotes,
        'downvotes' => (int)$downvotes,
        'total' => (int)$upvotes - (int)$downvotes
    );
}

/**
 * Kullanıcının bir yoruma verdiği tepkiyi kontrol et
 */
function corement_get_user_reaction($comment_id, $user_id = null, $user_ip = null) {
    global $wpdb;
    
    if (!$user_id && !$user_ip) {
        return null;
    }
    
    $table = $wpdb->prefix . 'corement_reactions';
    
    if ($user_id) {
        $reaction = $wpdb->get_var($wpdb->prepare(
            "SELECT reaction_type FROM $table WHERE comment_id = %d AND user_id = %d",
            $comment_id, $user_id
        ));
    } else {
        $reaction = $wpdb->get_var($wpdb->prepare(
            "SELECT reaction_type FROM $table WHERE comment_id = %d AND user_ip = %s AND user_id IS NULL",
            $comment_id, $user_ip
        ));
    }
    
    return $reaction;
}

/**
 * Kullanıcının bir yoruma verdiği oyu kontrol et
 */
function corement_get_user_vote($comment_id, $user_id = null, $user_ip = null) {
    global $wpdb;
    
    if (!$user_id && !$user_ip) {
        return null;
    }
    
    $table = $wpdb->prefix . 'corement_votes';
    
    if ($user_id) {
        $vote = $wpdb->get_var($wpdb->prepare(
            "SELECT vote_type FROM $table WHERE comment_id = %d AND user_id = %d",
            $comment_id, $user_id
        ));
    } else {
        $vote = $wpdb->get_var($wpdb->prepare(
            "SELECT vote_type FROM $table WHERE comment_id = %d AND user_ip = %s AND user_id IS NULL",
            $comment_id, $user_ip
        ));
    }
    
    return $vote ? (int)$vote : null;
}

/**
 * Rate limiting kontrolü
 */
function corement_check_rate_limit($user_ip, $action_type = 'comment', $max_attempts = 10) {
    global $wpdb;
    
    if (!get_option('corement_rate_limit_enabled', true)) {
        return true;
    }
    
    $table = $wpdb->prefix . 'corement_rate_limit';
    
    // Mevcut kaydı kontrol et
    $record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_ip = %s AND action_type = %s",
        $user_ip, $action_type
    ));
    
    $now = current_time('mysql');
    
    if ($record) {
        // Eğer bloklanmışsa ve süre dolmamışsa
        if ($record->blocked_until && $record->blocked_until > $now) {
            return false;
        }
        
        // Son deneme 1 saat içindeyse sayacı artır
        if (strtotime($record->last_attempt) > strtotime('-1 hour')) {
            $new_count = $record->attempt_count + 1;
            
            if ($new_count > $max_attempts) {
                // 1 saat blokla
                $blocked_until = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $wpdb->update(
                    $table,
                    array(
                        'attempt_count' => $new_count,
                        'last_attempt' => $now,
                        'blocked_until' => $blocked_until
                    ),
                    array('id' => $record->id)
                );
                
                return false;
            } else {
                $wpdb->update(
                    $table,
                    array(
                        'attempt_count' => $new_count,
                        'last_attempt' => $now,
                        'blocked_until' => null
                    ),
                    array('id' => $record->id)
                );
            }
        } else {
            // 1 saat geçmişse sayacı sıfırla
            $wpdb->update(
                $table,
                array(
                    'attempt_count' => 1,
                    'last_attempt' => $now,
                    'blocked_until' => null
                ),
                array('id' => $record->id)
            );
        }
    } else {
        // Yeni kayıt oluştur
        $wpdb->insert(
            $table,
            array(
                'user_ip' => $user_ip,
                'action_type' => $action_type,
                'attempt_count' => 1,
                'last_attempt' => $now
            )
        );
    }
    
    return true;
}

