<?php

/**
 * User profile fields for PMPro NBSTUP Addon
 *
 * @package PMProNBSTUP
 * @subpackage User Profile
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Add deceased checkbox and date picker to user profile
 *
 * @param WP_User $user User object
 */
function pmpronbstup_user_profile_fields($user)
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $deceased       = get_user_meta($user->ID, 'pmpronbstup_deceased', true);
    $deceased_date  = get_user_meta($user->ID, 'pmpronbstup_deceased_date', true);
?>
    <h2><?php esc_html_e('NBSTUP Membership Flags', 'pmpro-nbstup'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Passed Away', 'pmpro-nbstup'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="pmpronbstup_deceased" value="1" <?php checked((int) $deceased, 1); ?> />
                    <?php esc_html_e('Mark this member as deceased', 'pmpro-nbstup'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="pmpronbstup_deceased_date"><?php esc_html_e('Date of Death', 'pmpro-nbstup'); ?></label>
            </th>
            <td>
                <input type="date" name="pmpronbstup_deceased_date" id="pmpronbstup_deceased_date" value="<?php echo esc_attr($deceased_date); ?>" />
            </td>
        </tr>
    </table>
<?php
}
add_action('show_user_profile', 'pmpronbstup_user_profile_fields');
add_action('edit_user_profile', 'pmpronbstup_user_profile_fields');

/**
 * Save deceased fields from user profile
 *
 * @param int $user_id User ID
 */
function pmpronbstup_save_user_profile_fields($user_id)
{
    if (! current_user_can('manage_options')) {
        return;
    }

    $deceased = isset($_POST['pmpronbstup_deceased']) ? 1 : 0;
    update_user_meta($user_id, 'pmpronbstup_deceased', $deceased);

    if (! empty($_POST['pmpronbstup_deceased_date'])) {
        $date = sanitize_text_field(wp_unslash($_POST['pmpronbstup_deceased_date']));
        update_user_meta($user_id, 'pmpronbstup_deceased_date', $date);
    } else {
        delete_user_meta($user_id, 'pmpronbstup_deceased_date');
    }

    // If user is marked deceased, also ensure they are not active
    if ($deceased) {
        pmpronbstup_deactivate_user($user_id);
    }
}
add_action('personal_options_update', 'pmpronbstup_save_user_profile_fields');
add_action('edit_user_profile_update', 'pmpronbstup_save_user_profile_fields');
