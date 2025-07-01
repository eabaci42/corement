<?php
/**
 * Corement - Admin Paneli ve Ayarlar
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin menüsünü oluştur
 */
function corement_admin_menu() {
    // Ana menü
    add_menu_page(
        __('Corement Settings', 'corement'),
        'Corement',
        'manage_options',
        'corement-settings',
        'corement_settings_page',
        'dashicons-admin-comments',
        60
    );
    
    // Alt menüler
    add_submenu_page(
        'corement-settings',
        __('General Settings', 'corement'),
        __('General', 'corement'),
        'manage_options',
        'corement-settings',
        'corement_settings_page'
    );
    
    add_submenu_page(
        'corement-settings',
        __('Moderation', 'corement'),
        __('Moderation', 'corement'),
        'manage_options',
        'corement-moderation',
        'corement_moderation_page'
    );
    
    add_submenu_page(
        'corement-settings',
        __('Security', 'corement'),
        __('Security', 'corement'),
        'manage_options',
        'corement-security',
        'corement_security_page'
    );
    
    add_submenu_page(
        'corement-settings',
        __('Statistics', 'corement'),
        __('Statistics', 'corement'),
        'manage_options',
        'corement-stats',
        'corement_stats_page'
    );
}
add_action('admin_menu', 'corement_admin_menu');

/**
 * Ana ayarlar sayfası
 */
function corement_settings_page() {
    // Ayarları kaydet
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['corement_admin_nonce'], 'corement_admin_action')) {
        corement_save_general_settings();
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'corement') . '</p></div>';
    }
    
    $current_tab = $_GET['tab'] ?? 'general';
    ?>
    <div class="wrap">
        <h1><?php _e('Corement Settings', 'corement'); ?></h1>
        
        <nav class="nav-tab-wrapper">
            <a href="?page=corement-settings&tab=general" class="nav-tab <?php echo $current_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                <?php _e('General', 'corement'); ?>
            </a>
            <a href="?page=corement-settings&tab=appearance" class="nav-tab <?php echo $current_tab === 'appearance' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Appearance', 'corement'); ?>
            </a>
            <a href="?page=corement-settings&tab=notifications" class="nav-tab <?php echo $current_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Notifications', 'corement'); ?>
            </a>
        </nav>
        
        <form method="post" action="">
            <?php wp_nonce_field('corement_admin_action', 'corement_admin_nonce'); ?>
            
            <?php if ($current_tab === 'general'): ?>
                <?php corement_render_general_settings(); ?>
            <?php elseif ($current_tab === 'appearance'): ?>
                <?php corement_render_appearance_settings(); ?>
            <?php elseif ($current_tab === 'notifications'): ?>
                <?php corement_render_notification_settings(); ?>
            <?php endif; ?>
            
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Genel ayarları render et
 */
function corement_render_general_settings() {
    ?>
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('Auto Approve Comments', 'corement'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="corement_auto_approve" value="1" <?php checked(get_option('corement_auto_approve', false)); ?> />
                    <?php _e('Automatically approve new comments', 'corement'); ?>
                </label>
                <p class="description"><?php _e('If disabled, comments will require manual approval.', 'corement'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Auto Append to Content', 'corement'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="corement_auto_append" value="1" <?php checked(get_option('corement_auto_append', false)); ?> />
                    <?php _e('Automatically show comments on posts and pages', 'corement'); ?>
                </label>
                <p class="description"><?php _e('If disabled, you need to use [corement] shortcode manually.', 'corement'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Disable Default Comments', 'corement'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="corement_disable_default" value="1" <?php checked(get_option('corement_disable_default', false)); ?> />
                    <?php _e('Disable WordPress default comment system', 'corement'); ?>
                </label>
                <p class="description"><?php _e('This will hide the default WordPress comment form and use only Corement.', 'corement'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Maximum Media Size (MB)', 'corement'); ?></th>
            <td>
                <input type="number" name="corement_media_limit" value="<?php echo esc_attr(get_option('corement_media_limit', 2)); ?>" min="1" max="10" class="small-text" />
                <p class="description"><?php _e('Maximum file size for image uploads in megabytes.', 'corement'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Comments Per Hour Limit', 'corement'); ?></th>
            <td>
                <input type="number" name="corement_max_comments_per_hour" value="<?php echo esc_attr(get_option('corement_max_comments_per_hour', 10)); ?>" min="1" max="100" class="small-text" />
                <p class="description"><?php _e('Maximum number of comments a user can post per hour.', 'corement'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Default Guest Avatar', 'corement'); ?></th>
            <td>
                <input type="url" name="corement_guest_avatar" value="<?php echo esc_attr(get_option('corement_guest_avatar', COREMENT_PLUGIN_URL . 'assets/img/default-avatar.png')); ?>" class="regular-text" />
                <p class="description"><?php _e('URL of the default avatar image for guest users.', 'corement'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Generate Letter Avatars', 'corement'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="corement_generate_letter_avatars" value="1" <?php checked(get_option('corement_generate_letter_avatars', true)); ?> />
                    <?php _e('Generate colorful letter avatars from user names', 'corement'); ?>
                </label>
                <p class="description"><?php _e('Creates unique avatars using the first letters of user names.', 'corement'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Görünüm ayarları
 */
function corement_render_appearance_settings() {
    ?>
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('Theme Compatibility', 'corement'); ?></th>
            <td>
                <select name="corement_theme_mode">
                    <option value="auto" <?php selected(get_option('corement_theme_mode', 'auto'), 'auto'); ?>><?php _e('Auto Detect', 'corement'); ?></option>
                    <option value="light" <?php selected(get_option('corement_theme_mode', 'auto'), 'light'); ?>><?php _e('Light Mode', 'corement'); ?></option>
                    <option value="dark" <?php selected(get_option('corement_theme_mode', 'auto'), 'dark'); ?>><?php _e('Dark Mode', 'corement'); ?></option>
                </select>
                <p class="description"><?php _e('Choose the color scheme for the comment system.', 'corement'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Custom CSS', 'corement'); ?></th>
            <td>
                <textarea name="corement_custom_css" rows="10" cols="50" class="large-text code"><?php echo esc_textarea(get_option('corement_custom_css', '')); ?></textarea>
                <p class="description"><?php _e('Add custom CSS to customize the appearance of comments.', 'corement'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Show Comment Count', 'corement'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="corement_show_comment_count" value="1" <?php checked(get_option('corement_show_comment_count', true)); ?> />
                    <?php _e('Display comment count in the header', 'corement'); ?>
                </label>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Show Reaction Counts', 'corement'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="corement_show_reaction_counts" value="1" <?php checked(get_option('corement_show_reaction_counts', true)); ?> />
                    <?php _e('Display emoji reaction counts', 'corement'); ?>
                </label>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Show Vote Counts', 'corement'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="corement_show_vote_counts" value="1" <?php checked(get_option('corement_show_vote_counts', true)); ?> />
                    <?php _e('Display upvote/downvote counts', 'corement'); ?>
                </label>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Bildirim ayarları
 */
function corement_render_notification_settings() {
    ?>
    <table class="form-table">
        <tr>
            <th scope="row"><?php _e('Email Notifications', 'corement'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="corement_email_notifications" value="1" <?php checked(get_option('corement_email_notifications', false)); ?> />
                    <?php _e('Send email notifications for new comments', 'corement'); ?>
                </label>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><?php _e('Notification Email', 'corement'); ?></th>
            <td>
                <input type="email" name="corement_notification_email" value="<?php echo esc_attr(get_option('corement_notification_email', get_option('admin_email'))); ?>" class="regular-text" />
                <p class="description"><?php _e('Email address to receive notifications.', 'corement'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Moderasyon sayfası
 */
function corement_moderation_page() {
    // Toplu işlemler
    if (isset($_POST['action']) && $_POST['action'] !== '-1') {
        corement_handle_bulk_moderation();
    }
    
    // Tek işlemler
    if (isset($_GET['action']) && isset($_GET['comment_id'])) {
        corement_handle_single_moderation();
    }
    
    $status = $_GET['status'] ?? 'pending';
    $comments = corement_get_comments_for_moderation($status);
    ?>
    <div class="wrap">
        <h1><?php _e('Comment Moderation', 'corement'); ?></h1>
        
        <ul class="subsubsub">
            <li><a href="?page=corement-moderation&status=pending" class="<?php echo $status === 'pending' ? 'current' : ''; ?>"><?php _e('Pending', 'corement'); ?></a> |</li>
            <li><a href="?page=corement-moderation&status=approved" class="<?php echo $status === 'approved' ? 'current' : ''; ?>"><?php _e('Approved', 'corement'); ?></a> |</li>
            <li><a href="?page=corement-moderation&status=spam" class="<?php echo $status === 'spam' ? 'current' : ''; ?>"><?php _e('Spam', 'corement'); ?></a></li>
        </ul>
        
        <form method="post">
            <?php wp_nonce_field('corement_moderation', 'corement_moderation_nonce'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1"><?php _e('Bulk Actions', 'corement'); ?></option>
                        <option value="approve"><?php _e('Approve', 'corement'); ?></option>
                        <option value="spam"><?php _e('Mark as Spam', 'corement'); ?></option>
                        <option value="delete"><?php _e('Delete', 'corement'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'corement'); ?>" />
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped comments">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" />
                        </td>
                        <th><?php _e('Author', 'corement'); ?></th>
                        <th><?php _e('Comment', 'corement'); ?></th>
                        <th><?php _e('Post', 'corement'); ?></th>
                        <th><?php _e('Date', 'corement'); ?></th>
                        <th><?php _e('Actions', 'corement'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($comments)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No comments found.', 'corement'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="comment_ids[]" value="<?php echo $comment->comment_ID; ?>" />
                                </th>
                                <td>
                                    <strong><?php echo esc_html($comment->comment_author); ?></strong><br>
                                    <small><?php echo esc_html($comment->comment_author_email); ?></small>
                                </td>
                                <td>
                                    <?php echo wp_trim_words(strip_tags($comment->comment_content), 20); ?>
                                </td>
                                <td>
                                    <a href="<?php echo get_permalink($comment->comment_post_ID); ?>" target="_blank">
                                        <?php echo get_the_title($comment->comment_post_ID); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($comment->comment_date)); ?>
                                </td>
                                <td>
                                    <?php if ($comment->comment_approved === '0'): ?>
                                        <a href="?page=corement-moderation&action=approve&comment_id=<?php echo $comment->comment_ID; ?>&_wpnonce=<?php echo wp_create_nonce('corement_moderate_' . $comment->comment_ID); ?>" class="button button-small">
                                            <?php _e('Approve', 'corement'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?page=corement-moderation&action=spam&comment_id=<?php echo $comment->comment_ID; ?>&_wpnonce=<?php echo wp_create_nonce('corement_moderate_' . $comment->comment_ID); ?>" class="button button-small">
                                        <?php _e('Spam', 'corement'); ?>
                                    </a>
                                    
                                    <a href="?page=corement-moderation&action=delete&comment_id=<?php echo $comment->comment_ID; ?>&_wpnonce=<?php echo wp_create_nonce('corement_moderate_' . $comment->comment_ID); ?>" class="button button-small" onclick="return confirm('<?php _e('Are you sure?', 'corement'); ?>')">
                                        <?php _e('Delete', 'corement'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>
    <?php
}

/**
 * Güvenlik sayfası
 */
function corement_security_page() {
    if (isset($_POST['submit'])) {
        corement_save_security_settings();
        echo '<div class="notice notice-success"><p>' . __('Security settings saved!', 'corement') . '</p></div>';
    }
    
    if (isset($_POST['clear_logs'])) {
        delete_option('corement_security_logs');
        echo '<div class="notice notice-success"><p>' . __('Security logs cleared!', 'corement') . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Security Settings', 'corement'); ?></h1>
        
        <form method="post">
            <?php wp_nonce_field('corement_security', 'corement_security_nonce'); ?>
            
            <h2><?php _e('Blacklist Management', 'corement'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Blacklisted Words', 'corement'); ?></th>
                    <td>
                        <textarea name="corement_blacklist" rows="5" cols="50" class="large-text"><?php echo esc_textarea(get_option('corement_blacklist', 'spam,reklam,viagra,casino')); ?></textarea>
                        <p class="description"><?php _e('Separate words with commas. Comments containing these words will be blocked.', 'corement'); ?></p>
                    </td>
                </tr>
            </table>
            
            <h2><?php _e('Rate Limiting', 'corement'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Rate Limiting', 'corement'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="corement_rate_limit_enabled" value="1" <?php checked(get_option('corement_rate_limit_enabled', true)); ?> />
                            <?php _e('Enable rate limiting for comments and reactions', 'corement'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <h2><?php _e('Security Logging', 'corement'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Security Logging', 'corement'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="corement_enable_security_logging" value="1" <?php checked(get_option('corement_enable_security_logging', false)); ?> />
                            <?php _e('Log security events for analysis', 'corement'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <hr>
        
        <h2><?php _e('Security Logs', 'corement'); ?></h2>
        <?php
        $logs = get_option('corement_security_logs', array());
        if (!empty($logs)):
            $recent_logs = array_slice(array_reverse($logs), 0, 20);
        ?>
            <form method="post" style="margin-bottom: 20px;">
                <input type="submit" name="clear_logs" class="button" value="<?php _e('Clear Logs', 'corement'); ?>" onclick="return confirm('<?php _e('Are you sure?', 'corement'); ?>')" />
            </form>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'corement'); ?></th>
                        <th><?php _e('IP Address', 'corement'); ?></th>
                        <th><?php _e('Event Type', 'corement'); ?></th>
                        <th><?php _e('Details', 'corement'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['timestamp']); ?></td>
                            <td><?php echo esc_html($log['ip']); ?></td>
                            <td><?php echo esc_html($log['event_type']); ?></td>
                            <td><?php echo esc_html(json_encode($log['details'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No security logs found.', 'corement'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * İstatistikler sayfası
 */
function corement_stats_page() {
    global $wpdb;
    
    // İstatistikleri hesapla
    $total_comments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'corement'");
    $pending_comments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'corement' AND comment_approved = '0'");
    $approved_comments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'corement' AND comment_approved = '1'");
    $spam_comments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'corement' AND comment_approved = 'spam'");
    
    $total_reactions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}corement_reactions");
    $total_votes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}corement_votes");
    
    ?>
    <div class="wrap">
        <h1><?php _e('Corement Statistics', 'corement'); ?></h1>
        
        <div class="corement-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div class="corement-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php _e('Total Comments', 'corement'); ?></h3>
                <p style="font-size: 2em; margin: 0; color: #0073aa;"><?php echo number_format($total_comments); ?></p>
            </div>
            
            <div class="corement-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php _e('Pending', 'corement'); ?></h3>
                <p style="font-size: 2em; margin: 0; color: #f39c12;"><?php echo number_format($pending_comments); ?></p>
            </div>
            
            <div class="corement-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php _e('Approved', 'corement'); ?></h3>
                <p style="font-size: 2em; margin: 0; color: #27ae60;"><?php echo number_format($approved_comments); ?></p>
            </div>
            
            <div class="corement-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php _e('Spam', 'corement'); ?></h3>
                <p style="font-size: 2em; margin: 0; color: #e74c3c;"><?php echo number_format($spam_comments); ?></p>
            </div>
            
            <div class="corement-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php _e('Total Reactions', 'corement'); ?></h3>
                <p style="font-size: 2em; margin: 0; color: #9b59b6;"><?php echo number_format($total_reactions); ?></p>
            </div>
            
            <div class="corement-stat-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3><?php _e('Total Votes', 'corement'); ?></h3>
                <p style="font-size: 2em; margin: 0; color: #1abc9c;"><?php echo number_format($total_votes); ?></p>
            </div>
        </div>
        
        <h2><?php _e('Recent Activity', 'corement'); ?></h2>
        <?php
        $recent_comments = $wpdb->get_results(
            "SELECT * FROM {$wpdb->comments} 
             WHERE comment_type = 'corement' 
             ORDER BY comment_date DESC 
             LIMIT 10"
        );
        
        if ($recent_comments):
        ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Author', 'corement'); ?></th>
                        <th><?php _e('Comment', 'corement'); ?></th>
                        <th><?php _e('Post', 'corement'); ?></th>
                        <th><?php _e('Date', 'corement'); ?></th>
                        <th><?php _e('Status', 'corement'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_comments as $comment): ?>
                        <tr>
                            <td><?php echo esc_html($comment->comment_author); ?></td>
                            <td><?php echo wp_trim_words(strip_tags($comment->comment_content), 10); ?></td>
                            <td>
                                <a href="<?php echo get_permalink($comment->comment_post_ID); ?>" target="_blank">
                                    <?php echo get_the_title($comment->comment_post_ID); ?>
                                </a>
                            </td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($comment->comment_date)); ?></td>
                            <td>
                                <?php
                                if ($comment->comment_approved === '1') {
                                    echo '<span style="color: #27ae60;">' . __('Approved', 'corement') . '</span>';
                                } elseif ($comment->comment_approved === '0') {
                                    echo '<span style="color: #f39c12;">' . __('Pending', 'corement') . '</span>';
                                } else {
                                    echo '<span style="color: #e74c3c;">' . __('Spam', 'corement') . '</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No comments found.', 'corement'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Genel ayarları kaydet
 */
function corement_save_general_settings() {
    $settings = array(
        'corement_auto_approve',
        'corement_auto_append',
        'corement_disable_default',
        'corement_media_limit',
        'corement_max_comments_per_hour',
        'corement_guest_avatar',
        'corement_generate_letter_avatars',
        'corement_theme_mode',
        'corement_custom_css',
        'corement_show_comment_count',
        'corement_show_reaction_counts',
        'corement_show_vote_counts',
        'corement_email_notifications',
        'corement_notification_email'
    );
    
    foreach ($settings as $setting) {
        $value = $_POST[$setting] ?? '';
        
        if (in_array($setting, array('corement_auto_approve', 'corement_auto_append', 'corement_disable_default', 'corement_generate_letter_avatars', 'corement_show_comment_count', 'corement_show_reaction_counts', 'corement_show_vote_counts', 'corement_email_notifications'))) {
            $value = $value ? true : false;
        } elseif (in_array($setting, array('corement_media_limit', 'corement_max_comments_per_hour'))) {
            $value = absint($value);
        } else {
            $value = sanitize_text_field($value);
        }
        
        update_option($setting, $value);
    }
}

/**
 * Güvenlik ayarlarını kaydet
 */
function corement_save_security_settings() {
    if (!wp_verify_nonce($_POST['corement_security_nonce'], 'corement_security')) {
        return;
    }
    
    update_option('corement_blacklist', sanitize_textarea_field($_POST['corement_blacklist'] ?? ''));
    update_option('corement_rate_limit_enabled', !empty($_POST['corement_rate_limit_enabled']));
    update_option('corement_enable_security_logging', !empty($_POST['corement_enable_security_logging']));
}

/**
 * Moderasyon için yorumları getir
 */
function corement_get_comments_for_moderation($status = 'pending') {
    $args = array(
        'type' => 'corement',
        'number' => 50,
        'order' => 'DESC'
    );
    
    if ($status === 'pending') {
        $args['status'] = 'hold';
    } elseif ($status === 'approved') {
        $args['status'] => 'approve';
    } elseif ($status === 'spam') {
        $args['status'] = 'spam';
    }
    
    return get_comments($args);
}

/**
 * Toplu moderasyon işlemleri
 */
function corement_handle_bulk_moderation() {
    if (!wp_verify_nonce($_POST['corement_moderation_nonce'], 'corement_moderation')) {
        return;
    }
    
    $action = $_POST['action'];
    $comment_ids = $_POST['comment_ids'] ?? array();
    
    if (empty($comment_ids)) {
        return;
    }
    
    foreach ($comment_ids as $comment_id) {
        corement_moderate_comment($comment_id, $action);
    }
}

/**
 * Tek moderasyon işlemi
 */
function corement_handle_single_moderation() {
    $comment_id = absint($_GET['comment_id']);
    $action = $_GET['action'];
    
    if (!wp_verify_nonce($_GET['_wpnonce'], 'corement_moderate_' . $comment_id)) {
        return;
    }
    
    corement_moderate_comment($comment_id, $action);
    
    wp_redirect(admin_url('admin.php?page=corement-moderation'));
    exit;
}

/**
 * Yorum moderasyon işlemi
 */
function corement_moderate_comment($comment_id, $action) {
    switch ($action) {
        case 'approve':
            wp_set_comment_status($comment_id, 'approve');
            break;
        case 'spam':
            wp_set_comment_status($comment_id, 'spam');
            break;
        case 'delete':
            wp_delete_comment($comment_id, true);
            break;
    }
}

/**
 * Admin CSS ve JS
 */
function corement_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'corement') === false) {
        return;
    }
    
    wp_enqueue_style('corement-admin-css', COREMENT_PLUGIN_URL . 'admin/admin.css', array(), COREMENT_VERSION);
    wp_enqueue_script('corement-admin-js', COREMENT_PLUGIN_URL . 'admin/admin.js', array('jquery'), COREMENT_VERSION, true);
}
add_action('admin_enqueue_scripts', 'corement_admin_enqueue_scripts');

/**
 * Ayarları kaydet
 */
function corement_register_settings() {
    // Bu fonksiyon WordPress Settings API ile entegrasyon için kullanılabilir
    // Şu an manuel kaydetme kullanıyoruz
}
add_action('admin_init', 'corement_register_settings');

