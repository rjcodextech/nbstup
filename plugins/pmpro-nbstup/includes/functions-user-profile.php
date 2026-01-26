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

    $deceased           = get_user_meta($user->ID, 'pmpronbstup_deceased', true);
    $deceased_date      = get_user_meta($user->ID, 'pmpronbstup_deceased_date', true);
    $daughter_wedding   = get_user_meta($user->ID, 'pmpronbstup_daughter_wedding', true);
    $wedding_date       = get_user_meta($user->ID, 'pmpronbstup_wedding_date', true);
    $active             = get_user_meta($user->ID, 'pmpronbstup_active', true);
    $renewal_status     = get_user_meta($user->ID, 'pmpronbstup_renewal_status', true);
    $membership_start   = get_user_meta($user->ID, 'pmpronbstup_membership_start_date', true);
    $membership_expiry  = get_user_meta($user->ID, 'pmpronbstup_membership_expiry_date', true);
    $last_renewal       = get_user_meta($user->ID, 'pmpronbstup_last_renewal_date', true);
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
        <tr>
            <th scope="row"><?php esc_html_e('Daughter Wedding', 'pmpro-nbstup'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="pmpronbstup_daughter_wedding" value="1" <?php checked((int) $daughter_wedding, 1); ?> />
                    <?php esc_html_e('Mark this member as having daughter wedding', 'pmpro-nbstup'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="pmpronbstup_wedding_date"><?php esc_html_e('Wedding Date', 'pmpro-nbstup'); ?></label>
            </th>
            <td>
                <input type="date" name="pmpronbstup_wedding_date" id="pmpronbstup_wedding_date" value="<?php echo esc_attr($wedding_date); ?>" />
            </td>
        </tr>
    </table>

    <h2><?php esc_html_e('Membership Status', 'pmpro-nbstup'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Active Status', 'pmpro-nbstup'); ?></th>
            <td>
                <p><strong><?php echo esc_html($active ? __('Active', 'pmpro-nbstup') : __('Inactive', 'pmpro-nbstup')); ?></strong></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Renewal Status', 'pmpro-nbstup'); ?></th>
            <td>
                <p><strong><?php echo esc_html(ucfirst($renewal_status ?: 'none')); ?></strong></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Membership Start Date', 'pmpro-nbstup'); ?></th>
            <td>
                <p><?php echo esc_html($membership_start ?: __('Not set', 'pmpro-nbstup')); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Membership Expiry Date', 'pmpro-nbstup'); ?></th>
            <td>
                <p><?php echo esc_html($membership_expiry ?: __('Not set', 'pmpro-nbstup')); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Last Renewal Date', 'pmpro-nbstup'); ?></th>
            <td>
                <p><?php echo esc_html($last_renewal ?: __('Not set', 'pmpro-nbstup')); ?></p>
            </td>
        </tr>
    </table>

    <h2><?php esc_html_e('Contribution Payment Status', 'pmpro-nbstup'); ?></h2>
    
    <!-- Deceased Contribution Section -->
    <h3><?php esc_html_e('Deceased Member Contribution', 'pmpro-nbstup'); ?></h3>
    <table class="form-table" role="presentation">
        <?php
        $contribution_deceased_required = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_required', true);
        $contribution_deceased_paid     = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_paid', true);
        $contribution_deceased_deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_deadline', true);
        ?>
        <tr>
            <th scope="row"><?php esc_html_e('Contribution Required', 'pmpro-nbstup'); ?></th>
            <td>
                <p><strong><?php echo esc_html((int) $contribution_deceased_required === 1 ? __('Yes', 'pmpro-nbstup') : __('No', 'pmpro-nbstup')); ?></strong></p>
            </td>
        </tr>
        <?php if ((int) $contribution_deceased_required === 1) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Contribution Paid', 'pmpro-nbstup'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pmpronbstup_contribution_deceased_paid" value="1" <?php checked((int) $contribution_deceased_paid, 1); ?> />
                        <?php esc_html_e('Mark contribution as paid', 'pmpro-nbstup'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Contribution Deadline', 'pmpro-nbstup'); ?></th>
                <td>
                    <p><?php echo esc_html($contribution_deceased_deadline ?: __('Not set', 'pmpro-nbstup')); ?></p>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <!-- Wedding Contribution Section -->
    <h3><?php esc_html_e('Daughter Wedding Contribution', 'pmpro-nbstup'); ?></h3>
    <table class="form-table" role="presentation">
        <?php
        $contribution_wedding_required = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_required', true);
        $contribution_wedding_paid     = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_paid', true);
        $contribution_wedding_deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_deadline', true);
        ?>
        <tr>
            <th scope="row"><?php esc_html_e('Contribution Required', 'pmpro-nbstup'); ?></th>
            <td>
                <p><strong><?php echo esc_html((int) $contribution_wedding_required === 1 ? __('Yes', 'pmpro-nbstup') : __('No', 'pmpro-nbstup')); ?></strong></p>
            </td>
        </tr>
        <?php if ((int) $contribution_wedding_required === 1) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Contribution Paid', 'pmpro-nbstup'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="pmpronbstup_contribution_wedding_paid" value="1" <?php checked((int) $contribution_wedding_paid, 1); ?> />
                        <?php esc_html_e('Mark contribution as paid', 'pmpro-nbstup'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Contribution Deadline', 'pmpro-nbstup'); ?></th>
                <td>
                    <p><?php echo esc_html($contribution_wedding_deadline ?: __('Not set', 'pmpro-nbstup')); ?></p>
                </td>
            </tr>
        <?php endif; ?>
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

    $current_deceased = get_user_meta($user_id, 'pmpronbstup_deceased', true);
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
        
        // Mark all other active users to pay contribution
        pmpronbstup_mark_contribution_required_deceased($user_id);
    }

    // Handle daughter wedding fields
    $current_daughter_wedding = get_user_meta($user_id, 'pmpronbstup_daughter_wedding', true);
    $daughter_wedding = isset($_POST['pmpronbstup_daughter_wedding']) ? 1 : 0;
    update_user_meta($user_id, 'pmpronbstup_daughter_wedding', $daughter_wedding);

    if (! empty($_POST['pmpronbstup_wedding_date'])) {
        $wedding_date = sanitize_text_field(wp_unslash($_POST['pmpronbstup_wedding_date']));
        update_user_meta($user_id, 'pmpronbstup_wedding_date', $wedding_date);
    } else {
        delete_user_meta($user_id, 'pmpronbstup_wedding_date');
    }

    // If user is marked for daughter wedding, mark all other active users to pay contribution
    if ($daughter_wedding) {
        // Mark all other active users to pay wedding contribution
        pmpronbstup_mark_contribution_required_wedding($user_id);
    }

    // Save deceased contribution paid status if contribution is required
    $contribution_deceased_required = get_user_meta($user_id, 'pmpronbstup_contribution_deceased_required', true);
    if ((int) $contribution_deceased_required === 1) {
        $contribution_deceased_paid = isset($_POST['pmpronbstup_contribution_deceased_paid']) ? 1 : 0;
        update_user_meta($user_id, 'pmpronbstup_contribution_deceased_paid', $contribution_deceased_paid);
    }

    // Save wedding contribution paid status if contribution is required
    $contribution_wedding_required = get_user_meta($user_id, 'pmpronbstup_contribution_wedding_required', true);
    if ((int) $contribution_wedding_required === 1) {
        $contribution_wedding_paid = isset($_POST['pmpronbstup_contribution_wedding_paid']) ? 1 : 0;
        update_user_meta($user_id, 'pmpronbstup_contribution_wedding_paid', $contribution_wedding_paid);
    }

    // Send notification if deceased status changed from not deceased to deceased
    if ($current_deceased != '1' && $deceased == 1) {
        pmpronbstup_send_deceased_notification($user_id);
    }

    // Send notification if wedding status changed
    if ($current_daughter_wedding != '1' && $daughter_wedding == 1) {
        pmpronbstup_send_wedding_notification($user_id);
    }
}

/**
 * Send notification email when a member is marked as deceased
 *
 * @param int $user_id User ID
 */
function pmpronbstup_send_deceased_notification($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return;
    }

    $admin_email = get_option('admin_email');
    $subject = __('Member Marked as Deceased', 'pmpro-nbstup');
    $message = sprintf(__('Member %s has been marked as deceased.', 'pmpro-nbstup'), $user->display_name);

    wp_mail($admin_email, $subject, $message);
}

/**
 * Send notification email when a member is marked for daughter wedding
 *
 * @param int $user_id User ID
 */
function pmpronbstup_send_wedding_notification($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return;
    }

    $admin_email = get_option('admin_email');
    $subject = __('Member Marked for Daughter Wedding', 'pmpro-nbstup');
    $message = sprintf(__('Member %s has been marked for daughter wedding contribution.', 'pmpro-nbstup'), $user->display_name);

    wp_mail($admin_email, $subject, $message);
}

add_action('personal_options_update', 'pmpronbstup_save_user_profile_fields');
add_action('edit_user_profile_update', 'pmpronbstup_save_user_profile_fields');
