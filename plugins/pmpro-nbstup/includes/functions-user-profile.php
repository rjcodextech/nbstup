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

    <h2><?php esc_html_e('Address Details', 'pmpro-nbstup'); ?></h2>
    <?php
    $user_state_id    = get_user_meta($user->ID, 'user_state', true);
    $user_district_id = get_user_meta($user->ID, 'user_district', true);
    $user_block_id    = get_user_meta($user->ID, 'user_block', true);
    $user_address     = get_user_meta($user->ID, 'user_address', true);
    
    $state_name    = $user_state_id ? pmpro_nbstup_get_state_name($user_state_id) : '';
    $district_name = $user_district_id ? pmpro_nbstup_get_district_name($user_district_id) : '';
    $block_name    = $user_block_id ? pmpro_nbstup_get_block_name($user_block_id) : '';
    
    $all_states    = pmpro_nbstup_get_all_states();
    $districts     = $user_state_id ? pmpro_nbstup_get_districts($user_state_id) : array();
    $blocks        = $user_district_id ? pmpro_nbstup_get_blocks($user_district_id) : array();
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="user_state"><?php esc_html_e('State', 'pmpro-nbstup'); ?></label>
            </th>
            <td>
                <select name="user_state" id="user_state" class="regular-text">
                    <option value=""><?php esc_html_e('Select State', 'pmpro-nbstup'); ?></option>
                    <?php foreach ($all_states as $state) : ?>
                        <option value="<?php echo esc_attr($state->id); ?>" <?php selected($user_state_id, $state->id); ?>>
                            <?php echo esc_html($state->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="user_district"><?php esc_html_e('District', 'pmpro-nbstup'); ?></label>
            </th>
            <td>
                <select name="user_district" id="user_district" class="regular-text">
                    <option value=""><?php esc_html_e('Select District', 'pmpro-nbstup'); ?></option>
                    <?php foreach ($districts as $district) : ?>
                        <option value="<?php echo esc_attr($district->id); ?>" <?php selected($user_district_id, $district->id); ?>>
                            <?php echo esc_html($district->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="user_block"><?php esc_html_e('Block', 'pmpro-nbstup'); ?></label>
            </th>
            <td>
                <select name="user_block" id="user_block" class="regular-text">
                    <option value=""><?php esc_html_e('Select Block', 'pmpro-nbstup'); ?></option>
                    <?php foreach ($blocks as $block) : ?>
                        <option value="<?php echo esc_attr($block->id); ?>" <?php selected($user_block_id, $block->id); ?>>
                            <?php echo esc_html($block->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="user_address"><?php esc_html_e('Address', 'pmpro-nbstup'); ?></label>
            </th>
            <td>
                <textarea name="user_address" id="user_address" rows="3" cols="30" class="regular-text"><?php echo esc_textarea($user_address); ?></textarea>
            </td>
        </tr>
    </table>

    <h2><?php esc_html_e('Bank Transfer Details', 'pmpro-nbstup'); ?></h2>
    <?php
    $bank_transaction_id = get_user_meta($user->ID, 'bank_transaction_id', true);
    $bank_payment_receipt = get_user_meta($user->ID, 'bank_payment_receipt', true);
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="bank_transaction_id"><?php esc_html_e('Transaction ID', 'pmpro-nbstup'); ?></label>
            </th>
            <td>
                <input type="text" name="bank_transaction_id" id="bank_transaction_id" value="<?php echo esc_attr($bank_transaction_id); ?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Payment Receipt', 'pmpro-nbstup'); ?></th>
            <td>
                <?php if ($bank_payment_receipt) : ?>
                    <p>
                        <a href="<?php echo esc_url($bank_payment_receipt); ?>" target="_blank">
                            <?php esc_html_e('View Receipt', 'pmpro-nbstup'); ?>
                        </a>
                    </p>
                    <label>
                        <input type="text" name="bank_payment_receipt" id="bank_payment_receipt" value="<?php echo esc_attr($bank_payment_receipt); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Enter receipt URL or leave blank to keep current', 'pmpro-nbstup'); ?></p>
                    </label>
                <?php else : ?>
                    <input type="text" name="bank_payment_receipt" id="bank_payment_receipt" value="" class="regular-text" placeholder="<?php esc_attr_e('Enter receipt URL', 'pmpro-nbstup'); ?>" />
                    <p class="description"><?php esc_html_e('No receipt uploaded yet', 'pmpro-nbstup'); ?></p>
                <?php endif; ?>
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

    // Save Address fields
    if (isset($_POST['user_state'])) {
        $state_id = intval($_POST['user_state']);
        update_user_meta($user_id, 'user_state', $state_id);
    }

    if (isset($_POST['user_district'])) {
        $district_id = intval($_POST['user_district']);
        update_user_meta($user_id, 'user_district', $district_id);
    }

    if (isset($_POST['user_block'])) {
        $block_id = intval($_POST['user_block']);
        update_user_meta($user_id, 'user_block', $block_id);
    }

    if (isset($_POST['user_address'])) {
        $address = sanitize_textarea_field($_POST['user_address']);
        update_user_meta($user_id, 'user_address', $address);
    }

    // Save Bank Transfer fields
    if (isset($_POST['bank_transaction_id'])) {
        $transaction_id = sanitize_text_field($_POST['bank_transaction_id']);
        update_user_meta($user_id, 'bank_transaction_id', $transaction_id);
    }

    if (isset($_POST['bank_payment_receipt'])) {
        $receipt_url = esc_url_raw($_POST['bank_payment_receipt']);
        if (!empty($receipt_url)) {
            update_user_meta($user_id, 'bank_payment_receipt', $receipt_url);
        }
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

/**
 * Enqueue admin scripts for user profile cascading dropdowns
 */
function pmpronbstup_admin_user_profile_scripts($hook) {
    if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
        return;
    }
    
    wp_enqueue_script('jquery');
    
    wp_add_inline_script('jquery', "
        jQuery(document).ready(function($) {
            var stateSelect = $('#user_state');
            var districtSelect = $('#user_district');
            var blockSelect = $('#user_block');
            
            // When state changes, load districts
            stateSelect.on('change', function() {
                var stateId = $(this).val();
                
                // Reset district and block
                districtSelect.html('<option value=\"\">Loading...</option>');
                blockSelect.html('<option value=\"\">Select District First</option>');
                
                if (!stateId) {
                    districtSelect.html('<option value=\"\">Select State First</option>');
                    return;
                }
                
                // Load districts
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pmpro_nbstup_get_districts',
                        state_id: stateId
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=\"\">Select District</option>';
                            $.each(response.data, function(index, district) {
                                options += '<option value=\"' + district.id + '\">' + district.name + '</option>';
                            });
                            districtSelect.html(options);
                        } else {
                            districtSelect.html('<option value=\"\">No districts available</option>');
                        }
                    },
                    error: function() {
                        districtSelect.html('<option value=\"\">Error loading districts</option>');
                    }
                });
            });
            
            // When district changes, load blocks
            districtSelect.on('change', function() {
                var districtId = $(this).val();
                
                // Reset block
                blockSelect.html('<option value=\"\">Loading...</option>');
                
                if (!districtId) {
                    blockSelect.html('<option value=\"\">Select District First</option>');
                    return;
                }
                
                // Load blocks
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pmpro_nbstup_get_blocks',
                        district_id: districtId
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=\"\">Select Block</option>';
                            $.each(response.data, function(index, block) {
                                options += '<option value=\"' + block.id + '\">' + block.name + '</option>';
                            });
                            blockSelect.html(options);
                        } else {
                            blockSelect.html('<option value=\"\">No blocks available</option>');
                        }
                    },
                    error: function() {
                        blockSelect.html('<option value=\"\">Error loading blocks</option>');
                    }
                });
            });
        });
    ");
}
add_action('admin_enqueue_scripts', 'pmpronbstup_admin_user_profile_scripts');
