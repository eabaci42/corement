<?php
/**
 * Corement - Oylama ve Tepki Sistemi
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Oy verme işlemi
 */
function corement_process_vote($comment_id, $vote_type, $user_id = null, $user_ip = null) {
    global $wpdb;
    
    if (!$user_id && !$user_ip) {
        return new WP_Error('invalid_user', __('Invalid user data.', 'corement'));
    }
    
    if (!in_array($vote_type, array(1, -1))) {
        return new WP_Error('invalid_vote', __('Invalid vote type.', 'corement'));
    }
    
    $table = $wpdb->prefix . 'corement_votes';
    
    // Mevcut oyu kontrol et
    $existing_vote = corement_get_user_vote($comment_id, $user_id, $user_ip);
    
    if ($existing_vote === $vote_type) {
        // Aynı oy varsa kaldır
        $where = array('comment_id' => $comment_id);
        if ($user_id) {
            $where['user_id'] = $user_id;
        } else {
            $where['user_ip'] = $user_ip;
            $where['user_id'] = null;
        }
        
        $result = $wpdb->delete($table, $where);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Database error occurred.', 'corement'));
        }
        
        return array('action' => 'removed', 'vote' => $vote_type);
    } else {
        // Önceki oyu güncelle veya yeni oy ekle
        $data = array(
            'comment_id' => $comment_id,
            'user_id' => $user_id,
            'user_ip' => $user_ip,
            'vote_type' => $vote_type,
            'created_at' => current_time('mysql')
        );
        
        if ($existing_vote !== null) {
            // Güncelle
            $where = array('comment_id' => $comment_id);
            if ($user_id) {
                $where['user_id'] = $user_id;
            } else {
                $where['user_ip'] = $user_ip;
                $where['user_id'] = null;
            }
            
            $result = $wpdb->update($table, array('vote_type' => $vote_type), $where);
        } else {
            // Yeni ekle
            $result = $wpdb->insert($table, $data);
        }
        
        if ($result === false) {
            return new WP_Error('db_error', __('Database error occurred.', 'corement'));
        }
        
        return array('action' => 'added', 'vote' => $vote_type);
    }
}

/**
 * Tepki verme işlemi
 */
function corement_process_reaction($comment_id, $reaction_type, $user_id = null, $user_ip = null) {
    global $wpdb;
    
    if (!$user_id && !$user_ip) {
        return new WP_Error('invalid_user', __('Invalid user data.', 'corement'));
    }
    
    $allowed_reactions = array('like', 'laugh', 'wow', 'sad', 'angry');
    if (!in_array($reaction_type, $allowed_reactions)) {
        return new WP_Error('invalid_reaction', __('Invalid reaction type.', 'corement'));
    }
    
    $table = $wpdb->prefix . 'corement_reactions';
    
    // Mevcut tepkiyi kontrol et
    $existing_reaction = corement_get_user_reaction($comment_id, $user_id, $user_ip);
    
    if ($existing_reaction === $reaction_type) {
        // Aynı tepki varsa kaldır
        $where = array('comment_id' => $comment_id, 'reaction_type' => $reaction_type);
        if ($user_id) {
            $where['user_id'] = $user_id;
        } else {
            $where['user_ip'] = $user_ip;
            $where['user_id'] = null;
        }
        
        $result = $wpdb->delete($table, $where);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Database error occurred.', 'corement'));
        }
        
        return array('action' => 'removed', 'reaction' => $reaction_type);
    } else {
        // Önceki tepkiyi sil
        if ($existing_reaction) {
            $where = array('comment_id' => $comment_id);
            if ($user_id) {
                $where['user_id'] = $user_id;
            } else {
                $where['user_ip'] = $user_ip;
                $where['user_id'] = null;
            }
            
            $wpdb->delete($table, $where);
        }
        
        // Yeni tepki ekle
        $data = array(
            'comment_id' => $comment_id,
            'user_id' => $user_id,
            'user_ip' => $user_ip,
            'reaction_type' => $reaction_type,
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table, $data);
        
        if ($result === false) {
            return new WP_Error('db_error', __('Database error occurred.', 'corement'));
        }
        
        return array('action' => 'added', 'reaction' => $reaction_type);
    }
}

/**
 * Popüler yorumları getir (oy sayısına göre)
 */
function corement_get_popular_comments($post_id = null, $limit = 10) {
    global $wpdb;
    
    $votes_table = $wpdb->prefix . 'corement_votes';
    $comments_table = $wpdb->comments;
    
    $where_clause = "WHERE c.comment_type = 'corement' AND c.comment_approved = '1'";
    if ($post_id) {
        $where_clause .= $wpdb->prepare(" AND c.comment_post_ID = %d", $post_id);
    }
    
    $sql = "
        SELECT c.*, 
               COALESCE(SUM(v.vote_type), 0) as vote_score,
               COUNT(v.id) as vote_count
        FROM $comments_table c
        LEFT JOIN $votes_table v ON c.comment_ID = v.comment_id
        $where_clause
        GROUP BY c.comment_ID
        HAVING vote_score > 0
        ORDER BY vote_score DESC, vote_count DESC
        LIMIT %d
    ";
    
    return $wpdb->get_results($wpdb->prepare($sql, $limit));
}

/**
 * En çok tepki alan yorumları getir
 */
function corement_get_most_reacted_comments($post_id = null, $limit = 10) {
    global $wpdb;
    
    $reactions_table = $wpdb->prefix . 'corement_reactions';
    $comments_table = $wpdb->comments;
    
    $where_clause = "WHERE c.comment_type = 'corement' AND c.comment_approved = '1'";
    if ($post_id) {
        $where_clause .= $wpdb->prepare(" AND c.comment_post_ID = %d", $post_id);
    }
    
    $sql = "
        SELECT c.*, COUNT(r.id) as reaction_count
        FROM $comments_table c
        LEFT JOIN $reactions_table r ON c.comment_ID = r.comment_id
        $where_clause
        GROUP BY c.comment_ID
        HAVING reaction_count > 0
        ORDER BY reaction_count DESC
        LIMIT %d
    ";
    
    return $wpdb->get_results($wpdb->prepare($sql, $limit));
}

/**
 * Oy istatistikleri
 */
function corement_get_vote_statistics($comment_id = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_votes';
    
    if ($comment_id) {
        // Belirli bir yorum için
        return corement_get_vote_counts($comment_id);
    } else {
        // Genel istatistikler
        $stats = array();
        
        $stats['total_votes'] = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $stats['total_upvotes'] = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE vote_type = 1");
        $stats['total_downvotes'] = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE vote_type = -1");
        
        return $stats;
    }
}

/**
 * Tepki istatistikleri
 */
function corement_get_reaction_statistics($comment_id = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'corement_reactions';
    
    if ($comment_id) {
        // Belirli bir yorum için
        return corement_get_reaction_counts($comment_id);
    } else {
        // Genel istatistikler
        $stats = array();
        
        $stats['total_reactions'] = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        
        $reaction_breakdown = $wpdb->get_results(
            "SELECT reaction_type, COUNT(*) as count 
             FROM $table 
             GROUP BY reaction_type 
             ORDER BY count DESC"
        );
        
        $stats['by_type'] = array();
        foreach ($reaction_breakdown as $reaction) {
            $stats['by_type'][$reaction->reaction_type] = (int)$reaction->count;
        }
        
        return $stats;
    }
}

/**
 * Kullanıcının oy verme geçmişi
 */
function corement_get_user_vote_history($user_id = null, $user_ip = null, $limit = 50) {
    global $wpdb;
    
    if (!$user_id && !$user_ip) {
        return array();
    }
    
    $table = $wpdb->prefix . 'corement_votes';
    
    $where_conditions = array();
    $where_values = array();
    
    if ($user_id) {
        $where_conditions[] = "user_id = %d";
        $where_values[] = $user_id;
    } else {
        $where_conditions[] = "user_ip = %s AND user_id IS NULL";
        $where_values[] = $user_ip;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    $where_values[] = $limit;
    
    $sql = "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d";
    
    return $wpdb->get_results($wpdb->prepare($sql, $where_values));
}

/**
 * Kullanıcının tepki geçmişi
 */
function corement_get_user_reaction_history($user_id = null, $user_ip = null, $limit = 50) {
    global $wpdb;
    
    if (!$user_id && !$user_ip) {
        return array();
    }
    
    $table = $wpdb->prefix . 'corement_reactions';
    
    $where_conditions = array();
    $where_values = array();
    
    if ($user_id) {
        $where_conditions[] = "user_id = %d";
        $where_values[] = $user_id;
    } else {
        $where_conditions[] = "user_ip = %s AND user_id IS NULL";
        $where_values[] = $user_ip;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    $where_values[] = $limit;
    
    $sql = "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d";
    
    return $wpdb->get_results($wpdb->prepare($sql, $where_values));
}

/**
 * Oy ve tepki verilerini temizle (yorum silindiğinde)
 */
function corement_cleanup_vote_data($comment_id) {
    global $wpdb;
    
    $votes_table = $wpdb->prefix . 'corement_votes';
    $reactions_table = $wpdb->prefix . 'corement_reactions';
    
    $wpdb->delete($votes_table, array('comment_id' => $comment_id));
    $wpdb->delete($reactions_table, array('comment_id' => $comment_id));
}

/**
 * Trending yorumları getir (son 24 saatte en çok etkileşim alan)
 */
function corement_get_trending_comments($post_id = null, $limit = 10) {
    global $wpdb;
    
    $votes_table = $wpdb->prefix . 'corement_votes';
    $reactions_table = $wpdb->prefix . 'corement_reactions';
    $comments_table = $wpdb->comments;
    
    $where_clause = "WHERE c.comment_type = 'corement' AND c.comment_approved = '1'";
    if ($post_id) {
        $where_clause .= $wpdb->prepare(" AND c.comment_post_ID = %d", $post_id);
    }
    
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-24 hours'));
    
    $sql = "
        SELECT c.*, 
               (COALESCE(vote_score, 0) + COALESCE(reaction_count, 0)) as engagement_score
        FROM $comments_table c
        LEFT JOIN (
            SELECT comment_id, SUM(vote_type) as vote_score
            FROM $votes_table 
            WHERE created_at > %s
            GROUP BY comment_id
        ) v ON c.comment_ID = v.comment_id
        LEFT JOIN (
            SELECT comment_id, COUNT(*) as reaction_count
            FROM $reactions_table 
            WHERE created_at > %s
            GROUP BY comment_id
        ) r ON c.comment_ID = r.comment_id
        $where_clause
        HAVING engagement_score > 0
        ORDER BY engagement_score DESC
        LIMIT %d
    ";
    
    return $wpdb->get_results($wpdb->prepare($sql, $cutoff_date, $cutoff_date, $limit));
}

/**
 * Yorum silindiğinde oy ve tepki verilerini temizle
 */
add_action('delete_comment', 'corement_cleanup_vote_data');

