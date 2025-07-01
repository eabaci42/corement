<?php
// Corement - Basit Admin Paneli Taslağı
if (!defined('ABSPATH')) exit;

function corement_admin_menu() {
    add_menu_page(
        'Corement Ayarları',
        'Corement',
        'manage_options',
        'corement-settings',
        'corement_settings_page',
        'dashicons-admin-comments',
        60
    );
}
add_action('admin_menu', 'corement_admin_menu');

function corement_settings_page() {
    ?>
    <div class="wrap">
        <h1>Corement Ayarları</h1>
        <form method="post" action="options.php">
            <?php settings_fields('corement_settings_group'); ?>
            <?php do_settings_sections('corement-settings'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Kara Liste (virgül ile ayırın)</th>
                    <td><input type="text" name="corement_blacklist" value="<?php echo esc_attr(get_option('corement_blacklist', '')); ?>" style="width: 400px;" />
                    <br><small>Her kelimeyi virgül ile ayırın. Yorumda bu kelimelerden biri geçerse yorum engellenir.</small></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Maksimum Medya Boyutu (MB)</th>
                    <td><input type="number" name="corement_media_limit" value="<?php echo esc_attr(get_option('corement_media_limit', 2)); ?>" min="1" max="10" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Varsayılan Misafir Avatarı</th>
                    <td><input type="text" name="corement_guest_avatar" value="<?php echo esc_attr(get_option('corement_guest_avatar', plugins_url('assets/img/default-avatar.png', __FILE__))); ?>" style="width: 400px;" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function corement_register_settings() {
    register_setting('corement_settings_group', 'corement_blacklist');
    register_setting('corement_settings_group', 'corement_media_limit');
    register_setting('corement_settings_group', 'corement_guest_avatar');
}
add_action('admin_init', 'corement_register_settings');
