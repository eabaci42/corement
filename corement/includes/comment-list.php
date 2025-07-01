<?php
/**
 * Corement - Yorum Listeleme ve Etkileşim Sistemi
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Yorum listesini render et
 */
add_action('corement_render_comment_list', function($atts = array()) {
    global $corement_current_post_id;
    
    $post_id = $corement_current_post_id ?? get_the_ID();
    
    // Yorumları getir
    $comments = get_comments(array(
        'post_id' => $post_id,
        'status' => 'approve',
        'type' => 'corement',
        'order' => 'ASC',
        'orderby' => 'comment_date'
    ));
    
    if (empty($comments)) {
        echo '<div class="corement-no-comments">';
        echo '<p>' . __('No comments yet. Be the first to comment!', 'corement') . '</p>';
        echo '</div>';
        return;
    }
    
    echo '<div class="corement-comments-section">';
    echo '<h3 class="corement-comments-title">' . sprintf(_n('%d Comment', '%d Comments', count($comments), 'corement'), count($comments)) . '</h3>';
    echo '<div class="corement-comments-list">';
    
    corement_render_comments_tree($comments);
    
    echo '</div>';
    echo '</div>';
});

/**
 * Yorumları ağaç yapısında render et
 */
function corement_render_comments_tree($comments, $parent_id = 0, $depth = 0) {
    $max_depth = 3; // Maksimum 3 seviye
    
    foreach ($comments as $comment) {
        if ((int)$comment->comment_parent === (int)$parent_id) {
            corement_render_single_comment($comment, $depth);
            
            // Alt yorumları render et
            if ($depth < $max_depth) {
                echo '<div class="corement-replies">';
                corement_render_comments_tree($comments, $comment->comment_ID, $depth + 1);
                echo '</div>';
            }
        }
    }
}

/**
 * Tek bir yorumu render et
 */
function corement_render_single_comment($comment, $depth = 0) {
    $user_id = get_current_user_id();
    $user_ip = corement_get_user_ip();
    
    // Emoji tepki sayıları
    $reaction_counts = corement_get_reaction_counts($comment->comment_ID);
    $user_reaction = corement_get_user_reaction($comment->comment_ID, $user_id ?: null, $user_ip);
    
    // Oy sayıları
    $vote_counts = corement_get_vote_counts($comment->comment_ID);
    $user_vote = corement_get_user_vote($comment->comment_ID, $user_id ?: null, $user_ip);
    
    // Avatar
    $avatar = corement_get_responsive_avatar(
        $comment->comment_author_email, 
        $comment->comment_author, 
        $comment->user_id ?: null,
        'medium'
    );
    
    ?>
    <div class="corement-comment" data-comment-id="<?php echo $comment->comment_ID; ?>" data-depth="<?php echo $depth; ?>">
        <div class="corement-comment-header">
            <div class="corement-comment-avatar">
                <?php echo $avatar; ?>
            </div>
            <div class="corement-comment-meta">
                <span class="corement-comment-author"><?php echo esc_html($comment->comment_author); ?></span>
                <span class="corement-comment-date" title="<?php echo esc_attr($comment->comment_date); ?>">
                    <?php echo human_time_diff(strtotime($comment->comment_date), current_time('timestamp')) . ' ' . __('ago', 'corement'); ?>
                </span>
            </div>
        </div>
        
        <div class="corement-comment-content">
            <?php echo wp_kses_post($comment->comment_content); ?>
        </div>
        
        <div class="corement-comment-actions">
            <!-- Emoji Tepkiler -->
            <div class="corement-reactions">
                <?php
                $reactions = array(
                    'like' => '👍',
                    'laugh' => '😂', 
                    'wow' => '😮',
                    'sad' => '😢',
                    'angry' => '😡'
                );
                
                foreach ($reactions as $type => $emoji) {
                    $count = $reaction_counts[$type];
                    $is_active = ($user_reaction === $type);
                    $class = 'corement-reaction' . ($is_active ? ' active' : '');
                    ?>
                    <button class="<?php echo $class; ?>" 
                            data-comment-id="<?php echo $comment->comment_ID; ?>"
                            data-reaction="<?php echo $type; ?>"
                            title="<?php echo ucfirst($type); ?>">
                        <span class="corement-reaction-emoji"><?php echo $emoji; ?></span>
                        <?php if ($count > 0): ?>
                            <span class="corement-reaction-count"><?php echo $count; ?></span>
                        <?php endif; ?>
                    </button>
                    <?php
                }
                ?>
            </div>
            
            <!-- Oylama -->
            <div class="corement-voting">
                <button class="corement-vote corement-upvote<?php echo ($user_vote === 1) ? ' active' : ''; ?>"
                        data-comment-id="<?php echo $comment->comment_ID; ?>"
                        data-vote="1"
                        title="<?php _e('Upvote', 'corement'); ?>">
                    <span class="corement-vote-icon">▲</span>
                </button>
                
                <span class="corement-vote-count <?php echo ($vote_counts['total'] > 0) ? 'positive' : (($vote_counts['total'] < 0) ? 'negative' : ''); ?>">
                    <?php echo $vote_counts['total']; ?>
                </span>
                
                <button class="corement-vote corement-downvote<?php echo ($user_vote === -1) ? ' active' : ''; ?>"
                        data-comment-id="<?php echo $comment->comment_ID; ?>"
                        data-vote="-1"
                        title="<?php _e('Downvote', 'corement'); ?>">
                    <span class="corement-vote-icon">▼</span>
                </button>
            </div>
            
            <!-- Yanıtla Butonu -->
            <?php if ($depth < 2): // Maksimum 3 seviye için ?>
                <button class="corement-reply-btn" 
                        data-comment-id="<?php echo $comment->comment_ID; ?>"
                        data-author="<?php echo esc_attr($comment->comment_author); ?>">
                    <?php _e('Reply', 'corement'); ?>
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Yanıt Formu (Gizli) -->
        <?php if ($depth < 2): ?>
            <div class="corement-reply-form" id="corement-reply-form-<?php echo $comment->comment_ID; ?>" style="display: none;">
                <?php corement_render_reply_form($comment->comment_ID, $comment->comment_author); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Yanıt formunu render et
 */
function corement_render_reply_form($parent_id, $parent_author) {
    $current_user = wp_get_current_user();
    $is_logged_in = is_user_logged_in();
    
    ?>
    <form class="corement-reply-form-inner" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('corement_submit_comment', 'corement_nonce'); ?>
        <input type="hidden" name="corement_action" value="submit_comment" />
        <input type="hidden" name="corement_post_id" value="<?php echo get_the_ID(); ?>" />
        <input type="hidden" name="corement_parent_id" value="<?php echo $parent_id; ?>" />
        <input type="hidden" name="corement_timestamp" value="<?php echo time(); ?>" />
        <input type="hidden" name="corement_website" value="" class="corement-honeypot" />
        
        <div class="corement-reply-header">
            <span class="corement-reply-to"><?php printf(__('Replying to %s', 'corement'), '<strong>' . esc_html($parent_author) . '</strong>'); ?></span>
            <button type="button" class="corement-reply-cancel"><?php _e('Cancel', 'corement'); ?></button>
        </div>
        
        <?php if (!$is_logged_in): ?>
            <div class="corement-reply-user-fields">
                <input type="text" 
                       name="corement_name" 
                       placeholder="<?php _e('Your Name *', 'corement'); ?>" 
                       required 
                       class="corement-input corement-input-small" />
                <input type="email" 
                       name="corement_email" 
                       placeholder="<?php _e('Your Email *', 'corement'); ?>" 
                       required 
                       class="corement-input corement-input-small" />
            </div>
        <?php else: ?>
            <input type="hidden" name="corement_name" value="<?php echo esc_attr($current_user->display_name); ?>" />
            <input type="hidden" name="corement_email" value="<?php echo esc_attr($current_user->user_email); ?>" />
        <?php endif; ?>
        
        <textarea name="corement_comment" 
                  placeholder="<?php _e('Write your reply...', 'corement'); ?>" 
                  required 
                  class="corement-textarea corement-textarea-small"
                  rows="3"></textarea>
        
        <div class="corement-reply-media">
            <label for="corement_reply_media_<?php echo $parent_id; ?>" class="corement-media-label-small">
                <span class="corement-media-icon">📎</span>
                <span class="corement-media-text"><?php _e('Attach Image', 'corement'); ?></span>
                <input type="file" 
                       name="corement_media" 
                       id="corement_reply_media_<?php echo $parent_id; ?>"
                       accept="image/*,.gif" 
                       class="corement-file-input" />
            </label>
        </div>
        
        <div class="corement-reply-actions">
            <button type="submit" class="corement-reply-submit"><?php _e('Post Reply', 'corement'); ?></button>
        </div>
    </form>
    <?php
}

/**
 * AJAX - Emoji tepki verme
 */
add_action('wp_ajax_corement_react', 'corement_ajax_react');
add_action('wp_ajax_nopriv_corement_react', 'corement_ajax_react');

function corement_ajax_react() {
    try {
        // Nonce kontrolü
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'corement_nonce')) {
            throw new Exception(__('Security check failed.', 'corement'));
        }
        
        $comment_id = absint($_POST['comment_id'] ?? 0);
        $reaction_type = sanitize_text_field($_POST['reaction'] ?? '');
        
        if (!$comment_id || !in_array($reaction_type, array('like', 'laugh', 'wow', 'sad', 'angry'))) {
            throw new Exception(__('Invalid data.', 'corement'));
        }
        
        $user_id = get_current_user_id();
        $user_ip = corement_get_user_ip();
        
        // Rate limiting
        if (!corement_check_rate_limit($user_ip, 'reaction', 20)) {
            throw new Exception(__('Too many reactions. Please wait.', 'corement'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'corement_reactions';
        
        // Mevcut tepkiyi kontrol et
        $existing = corement_get_user_reaction($comment_id, $user_id ?: null, $user_ip);
        
        if ($existing === $reaction_type) {
            // Aynı tepki varsa kaldır
            $where = array('comment_id' => $comment_id, 'reaction_type' => $reaction_type);
            if ($user_id) {
                $where['user_id'] = $user_id;
            } else {
                $where['user_ip'] = $user_ip;
                $where['user_id'] = null;
            }
            
            $wpdb->delete($table, $where);
            $action = 'removed';
        } else {
            // Önceki tepkiyi sil
            if ($existing) {
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
            $wpdb->insert(
                $table,
                array(
                    'comment_id' => $comment_id,
                    'user_id' => $user_id ?: null,
                    'user_ip' => $user_ip,
                    'reaction_type' => $reaction_type,
                    'created_at' => current_time('mysql')
                ),
                array('%d', $user_id ? '%d' : null, '%s', '%s', '%s')
            );
            
            $action = 'added';
        }
        
        // Güncel sayıları getir
        $reaction_counts = corement_get_reaction_counts($comment_id);
        
        wp_send_json_success(array(
            'action' => $action,
            'reaction' => $reaction_type,
            'counts' => $reaction_counts
        ));
        
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}

/**
 * AJAX - Oy verme
 */
add_action('wp_ajax_corement_vote', 'corement_ajax_vote');
add_action('wp_ajax_nopriv_corement_vote', 'corement_ajax_vote');

function corement_ajax_vote() {
    try {
        // Nonce kontrolü
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'corement_nonce')) {
            throw new Exception(__('Security check failed.', 'corement'));
        }
        
        $comment_id = absint($_POST['comment_id'] ?? 0);
        $vote_type = intval($_POST['vote'] ?? 0);
        
        if (!$comment_id || !in_array($vote_type, array(1, -1))) {
            throw new Exception(__('Invalid data.', 'corement'));
        }
        
        $user_id = get_current_user_id();
        $user_ip = corement_get_user_ip();
        
        // Rate limiting
        if (!corement_check_rate_limit($user_ip, 'vote', 30)) {
            throw new Exception(__('Too many votes. Please wait.', 'corement'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'corement_votes';
        
        // Mevcut oyu kontrol et
        $existing = corement_get_user_vote($comment_id, $user_id ?: null, $user_ip);
        
        if ($existing === $vote_type) {
            // Aynı oy varsa kaldır
            $where = array('comment_id' => $comment_id);
            if ($user_id) {
                $where['user_id'] = $user_id;
            } else {
                $where['user_ip'] = $user_ip;
                $where['user_id'] = null;
            }
            
            $wpdb->delete($table, $where);
            $action = 'removed';
        } else {
            // Önceki oyu güncelle veya yeni oy ekle
            $data = array(
                'comment_id' => $comment_id,
                'user_id' => $user_id ?: null,
                'user_ip' => $user_ip,
                'vote_type' => $vote_type,
                'created_at' => current_time('mysql')
            );
            
            if ($existing !== null) {
                // Güncelle
                $where = array('comment_id' => $comment_id);
                if ($user_id) {
                    $where['user_id'] = $user_id;
                } else {
                    $where['user_ip'] = $user_ip;
                    $where['user_id'] = null;
                }
                
                $wpdb->update($table, array('vote_type' => $vote_type), $where);
            } else {
                // Yeni ekle
                $wpdb->insert($table, $data);
            }
            
            $action = 'added';
        }
        
        // Güncel sayıları getir
        $vote_counts = corement_get_vote_counts($comment_id);
        
        wp_send_json_success(array(
            'action' => $action,
            'vote' => $vote_type,
            'counts' => $vote_counts
        ));
        
    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}

