<?php

/**
 * Core functionality for PMPro NBSTUP Addon
 *
 * @package PMProNBSTUP
 * @subpackage Core
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue frontend styles for NBSTUP account layout.
 */
function pmpronbstup_enqueue_frontend_assets()
{
    // Only load on the front end.
    if (is_admin()) {
        return;
    }

    // Optionally, limit to logged-in users.
    if (! is_user_logged_in()) {
        return;
    }

    wp_enqueue_style(
        'pmpro-nbstup-frontend',
        PMPRONBSTUP_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        PMPRONBSTUP_VERSION
    );

    // Frontend JS (compiled via Gulp from assets/js/frontend.js).
    wp_enqueue_script(
        'pmpro-nbstup-frontend',
        PMPRONBSTUP_PLUGIN_URL . 'assets/js/dist/frontend.js',
        array('jquery'),
        PMPRONBSTUP_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'pmpronbstup_enqueue_frontend_assets');

/**
 * Check if a user (subscriber) is active according to our addon rules.
 * Includes checking membership expiration dates for yearly recurring subscriptions.
 *
 * @param int $user_id User ID to check
 * @return bool True if user is active and membership is valid, false otherwise
 */
function pmpronbstup_is_user_active($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    // Only apply our logic to subscribers; other roles remain unaffected.
    if (! in_array('subscriber', (array) $user->roles, true)) {
        return true;
    }

    // If marked deceased, always treat as inactive.
    $deceased = get_user_meta($user_id, 'pmpronbstup_deceased', true);
    if ((int) $deceased === 1 || $deceased === '1' || $deceased === true) {
        return false;
    }

    // Default is inactive unless explicitly marked active.
    $active = get_user_meta($user_id, 'pmpronbstup_active', true);
    if ((int) $active !== 1 && $active !== '1' && $active !== true) {
        return false;
    }

    // CHECK IF MEMBERSHIP HAS EXPIRED (yearly recurring)
    $expiry_date = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
    if ($expiry_date) {
        $expiry_timestamp = strtotime($expiry_date);
        if ($expiry_timestamp < time()) {
            // Membership expired - auto deactivate
            pmpronbstup_deactivate_user($user_id);
            update_user_meta($user_id, 'pmpronbstup_renewal_status', 'expired');
            return false;
        }
    }

    return true;
}

/**
 * Activate a user account
 *
 * @param int $user_id User ID to activate
 * @return bool True on success, false on failure
 */
function pmpronbstup_activate_user($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    // Only activate subscribers
    if (! in_array('subscriber', (array) $user->roles, true)) {
        return false;
    }

    // Don't activate deceased users
    $deceased = get_user_meta($user_id, 'pmpronbstup_deceased', true);
    if ((int) $deceased === 1 || $deceased === '1' || $deceased === true) {
        return false;
    }

    return update_user_meta($user_id, 'pmpronbstup_active', 1);
}

/**
 * Deactivate a user account
 *
 * @param int $user_id User ID to deactivate
 * @return bool True on success, false on failure
 */
function pmpronbstup_deactivate_user($user_id)
{
    return update_user_meta($user_id, 'pmpronbstup_active', 0);
}

/**
 * Check if a user's membership has expired and auto-deactivate if needed.
 * This is called daily via scheduled event.
 *
 * @param int $user_id User ID to check
 * @return string 'active', 'expired', 'pending_renewal', or null if no membership
 */
function pmpronbstup_check_membership_expiry($user_id)
{
    $expiry_date = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
    if (! $expiry_date) {
        return null;
    }

    $expiry_timestamp = strtotime($expiry_date);
    $current_timestamp = time();

    if ($expiry_timestamp < $current_timestamp) {
        // Membership has expired
        update_user_meta($user_id, 'pmpronbstup_renewal_status', 'expired');
        pmpronbstup_deactivate_user($user_id);

        // Send renewal reminder email (only if not already sent)
        if (! get_user_meta($user_id, 'pmpronbstup_expiry_email_sent_' . date('Y-m'), true)) {
            pmpronbstup_send_renewal_required_email($user_id);
            update_user_meta($user_id, 'pmpronbstup_expiry_email_sent_' . date('Y-m'), 1);
        }

        return 'expired';
    }

    // Days until expiry
    $days_until_expiry = ceil(($expiry_timestamp - $current_timestamp) / 86400);

    // Send reminder if expiring soon (within 30 days)
    if ($days_until_expiry <= 30 && $days_until_expiry > 0) {
        if (! get_user_meta($user_id, 'pmpronbstup_expiry_reminder_sent', true)) {
            pmpronbstup_send_expiry_reminder_email($user_id, $days_until_expiry);
            update_user_meta($user_id, 'pmpronbstup_expiry_reminder_sent', 1);
        }
    }

    return 'active';
}

/**
 * Check all user memberships for expiry daily (scheduled event)
 * Should be called via wp_scheduled_event
 */
function pmpronbstup_check_all_expired_memberships()
{
    $users = get_users(array(
        'role'       => 'subscriber',
        'meta_key'   => 'pmpronbstup_active',
        'meta_value' => '1',
    ));

    foreach ($users as $user) {
        pmpronbstup_check_membership_expiry($user->ID);
    }
}

/**
 * Send expiry reminder email to user (30 days before expiry)
 *
 * @param int $user_id User ID
 * @param int $days_until_expiry Days remaining until expiry
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_expiry_reminder_email($user_id, $days_until_expiry)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $expiry_date = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to = $user->user_email;
    $subject = sprintf(
        __('[%s] Your Membership Expires in %d Days', 'pmpro-nbstup'),
        $blogname,
        $days_until_expiry
    );

    $message = sprintf(
        __("Hello %s,\n\nYour membership will expire on %s.\n\nTo renew your membership and maintain access, please visit:\n%s\n\nThank you for your continued membership.\n\nBest regards,\n%s", 'pmpro-nbstup'),
        $user->display_name,
        $expiry_date,
        pmpro_url('checkout'),
        $blogname
    );

    return wp_mail($to, $subject, $message);
}

/**
 * Send renewal required email to user (membership expired)
 *
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_renewal_required_email($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $expiry_date = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to = $user->user_email;
    $subject = sprintf(
        __('[%s] Your Membership Has Expired', 'pmpro-nbstup'),
        $blogname
    );

    $message = sprintf(
        __("Hello %s,\n\nYour membership expired on %s.\n\nYour account access has been suspended. To renew your membership and regain access, please visit:\n%s\n\nThank you,\n%s", 'pmpro-nbstup'),
        $user->display_name,
        $expiry_date,
        pmpro_url('checkout'),
        $blogname
    );

    return wp_mail($to, $subject, $message);
}

/**
 * Send renewal confirmation email to user (renewal payment verified)
 *
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_renewal_confirmation_email($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $expiry_date = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to = $user->user_email;
    $subject = sprintf(
        __('[%s] Your Membership Has Been Renewed', 'pmpro-nbstup'),
        $blogname
    );

    $message = sprintf(
        __("Hello %s,\n\nYour membership renewal has been verified and confirmed.\n\nYour membership is now valid until %s.\n\nThank you for your continued membership.\n\nBest regards,\n%s", 'pmpro-nbstup'),
        $user->display_name,
        $expiry_date,
        $blogname
    );

    return wp_mail($to, $subject, $message);
}

/**
 * Shortcode to show the PMPro Membership Account in a
 * two-column layout: left menu, right details.
 *
 * Usage: [pmpro_account_nbstup] instead of [pmpro_account]
 *
 * Left menu links jump to the core PMPro account sections
 * rendered by the [pmpro_account] shortcode:
 *  - #pmpro_account-profile
 *  - #pmpro_account-membership
 *  - #pmpro_account-orders
 *  - #pmpro_account-links
 *  - ?view=contribution for deceased members list
 *
 * @return string
 */
function pmpronbstup_account_two_column_shortcode()
{
    if (! is_user_logged_in()) {
        // Fallback: if not logged in, just show the normal account shortcode
        // so PMPro can handle redirects/messages as usual.
        return do_shortcode('[pmpro_account]');
    }

    ob_start();
    ?>
    <div class="pmpro-nbstup-account-layout">
        <aside class="pmpro-nbstup-account-sidebar">
            <nav class="pmpro-nbstup-account-nav" aria-label="<?php esc_attr_e('Membership account navigation', 'pmpro-nbstup'); ?>">
                <ul>
                    <li><a href="#pmpro_account-profile"><?php esc_html_e('Account Overview', 'pmpro-nbstup'); ?></a></li>
                    <li><a href="#pmpro_account-membership"><?php esc_html_e('My Memberships', 'pmpro-nbstup'); ?></a></li>
                    <li><a href="#pmpro_account-orders"><?php esc_html_e('Order / Invoice History', 'pmpro-nbstup'); ?></a></li>
                    <!-- <li><a href="#pmpro_account-links"><?php //esc_html_e('Member Links', 'pmpro-nbstup'); ?></a></li> -->
                    <li><a href="?view=contribution"><?php esc_html_e('Contribution', 'pmpro-nbstup'); ?></a></li>
                </ul>
            </nav>
        </aside>

        <div class="pmpro-nbstup-account-content">
            <?php
            if (isset($_GET['view']) && $_GET['view'] === 'contribution') {
                echo pmpronbstup_render_deceased_members_list();
            } else {
                // Render the standard PMPro account shortcode inside the content pane.
                echo do_shortcode('[pmpro_account]');
            }
            ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Render the list of deceased members for contribution payments
 *
 * @return string
 */
function pmpronbstup_render_deceased_members_list()
{
    $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
    $per_page = 10;

    $query = new WP_User_Query(array(
        'meta_query' => array(
            array(
                'key' => 'pmpronbstup_deceased',
                'value' => '1',
                'compare' => '='
            )
        ),
        'number' => $per_page,
        'offset' => ($paged - 1) * $per_page,
        'orderby' => 'display_name',
        'order' => 'ASC'
    ));

    $users = $query->get_results();
    $total_users = $query->get_total();
    $total_pages = ceil($total_users / $per_page);

    ob_start();
    ?>
    <strong><?php esc_html_e('Deceased Members - Pay Contribution', 'pmpro-nbstup'); ?></strong>
    <?php if (empty($users)) : ?>
        <p><?php esc_html_e('No deceased members found.', 'pmpro-nbstup'); ?></p>
    <?php else : ?>
        <table class="pmpro-nbstup-deceased-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Avatar', 'pmpro-nbstup'); ?></th>
                    <th><?php esc_html_e('Display Name', 'pmpro-nbstup'); ?></th>
                    <th><?php esc_html_e('Date of Death', 'pmpro-nbstup'); ?></th>
                    <th><?php esc_html_e('Action', 'pmpro-nbstup'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user) : ?>
                    <tr>
                        <td><?php echo get_avatar($user->ID, 32); ?></td>
                        <td><?php echo esc_html($user->display_name); ?></td>
                        <td>
                            <?php
                            $date = get_user_meta($user->ID, 'pmpronbstup_deceased_date', true);
                            if (! empty($date)) {
                                echo esc_html(date_i18n(get_option('date_format'), strtotime($date)));
                            } else {
                                esc_html_e('N/A', 'pmpro-nbstup');
                            }
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(pmpro_url('checkout') . '?level=1&contribution_for=' . $user->ID); ?>" class="button">
                                <?php esc_html_e('Pay your contribution', 'pmpro-nbstup'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
            <div class="pmpro-nbstup-pagination">
                <?php if ($paged > 1) : ?>
                    <a href="?view=contribution&paged=<?php echo $paged - 1; ?>" class="button"><?php esc_html_e('Previous', 'pmpro-nbstup'); ?></a>
                <?php endif; ?>
                <span><?php printf(__('Page %d of %d', 'pmpro-nbstup'), $paged, $total_pages); ?></span>
                <?php if ($paged < $total_pages) : ?>
                    <a href="?view=contribution&paged=<?php echo $paged + 1; ?>" class="button"><?php esc_html_e('Next', 'pmpro-nbstup'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    return ob_get_clean();
}

add_shortcode('pmpro_account_nbstup', 'pmpronbstup_account_two_column_shortcode');

/**
 * Migrate existing active users to have membership expiry dates
 * Run this on plugin activation to set expiry dates for current active users
 */
function pmpronbstup_migrate_existing_users()
{
    $users = get_users(array(
        'role'     => 'subscriber',
        'meta_key' => 'pmpronbstup_active',
        'meta_value' => '1'
    ));

    foreach ($users as $user) {
        $expiry = get_user_meta($user->ID, 'pmpronbstup_membership_expiry_date', true);
        if (!$expiry) {
            // Set expiry to 1 year from today for existing active users
            update_user_meta($user->ID, 'pmpronbstup_membership_expiry_date',
                date('Y-m-d', strtotime('+1 year')));
            update_user_meta($user->ID, 'pmpronbstup_membership_start_date', date('Y-m-d'));
            update_user_meta($user->ID, 'pmpronbstup_renewal_status', 'active');
        }
    }
}

// Hook the daily expiry check
add_action('wp_scheduled_event_pmpronbstup_check_expiry', 'pmpronbstup_check_all_expired_memberships');

/**
 * Mark all active users as requiring contribution payment
 * Called when a user is marked as deceased
 *
 * @param int $deceased_user_id User ID of the deceased member
 * @return int Number of users marked to pay contribution
 */
function pmpronbstup_mark_contribution_required($deceased_user_id)
{
    $users = get_users(array(
        'role'       => 'subscriber',
        'meta_key'   => 'pmpronbstup_active',
        'meta_value' => '1',
    ));

    $count = 0;
    $contribution_deadline = date('Y-m-d', strtotime('+1 month'));

    foreach ($users as $user) {
        // Skip the deceased user
        if ($user->ID === $deceased_user_id) {
            continue;
        }

        // Check if already marked as requiring contribution
        $already_required = get_user_meta($user->ID, 'pmpronbstup_contribution_required', true);
        if ((int) $already_required === 1) {
            continue;
        }

        update_user_meta($user->ID, 'pmpronbstup_contribution_required', 1);
        update_user_meta($user->ID, 'pmpronbstup_contribution_deadline', $contribution_deadline);
        update_user_meta($user->ID, 'pmpronbstup_contribution_paid', 0);

        // Send notification email
        pmpronbstup_send_contribution_required_email($user->ID, $contribution_deadline);

        $count++;
    }

    return $count;
}

/**
 * Send email notifying user that they need to pay contribution
 *
 * @param int    $user_id User ID
 * @param string $deadline Deadline date (Y-m-d format)
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_contribution_required_email($user_id, $deadline)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to       = $user->user_email;
    $subject  = sprintf(__('[%s] Contribution Payment Required', 'pmpro-nbstup'), $blogname);
    $message  = sprintf(
        __("Hello %s,\n\nA member of our community has passed away. In their memory, all active members are requested to pay a contribution.\n\nContribution Deadline: %s\n\nPlease visit the following link to make your contribution:\n%s\n\nThank you for your support.\n\nBest regards,\n%s", 'pmpro-nbstup'),
        $user->display_name,
        date_i18n(get_option('date_format'), strtotime($deadline)),
        pmpro_url('checkout'),
        $blogname
    );

    return wp_mail($to, $subject, $message);
}

/**
 * Check all users for overdue contribution payments and deactivate if needed
 * Should be called via wp_scheduled_event
 */
function pmpronbstup_check_contribution_deadlines()
{
    $users = get_users(array(
        'meta_key'   => 'pmpronbstup_contribution_required',
        'meta_value' => '1',
    ));

    foreach ($users as $user) {
        // Skip if contribution already paid
        $paid = get_user_meta($user->ID, 'pmpronbstup_contribution_paid', true);
        if ((int) $paid === 1) {
            continue;
        }

        // Check deadline
        $deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_deadline', true);
        if (! $deadline) {
            continue;
        }

        $deadline_timestamp = strtotime($deadline);
        $current_timestamp  = time();

        if ($deadline_timestamp < $current_timestamp) {
            // Deadline passed, deactivate user
            pmpronbstup_deactivate_user($user->ID);
            update_user_meta($user->ID, 'pmpronbstup_renewal_status', 'contribution_overdue');

            // Send overdue notification email
            pmpronbstup_send_contribution_overdue_email($user->ID);
        }
    }
}

/**
 * Send email when contribution payment is overdue
 *
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_contribution_overdue_email($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to       = $user->user_email;
    $subject  = sprintf(__('[%s] Your Contribution Payment is Overdue', 'pmpro-nbstup'), $blogname);
    $message  = sprintf(
        __("Hello %s,\n\nYour contribution payment deadline has passed.\n\nYour account has been deactivated. To reactivate your account and continue your membership, please pay the contribution.\n\nVisit: %s\n\nThank you,\n%s", 'pmpro-nbstup'),
        $user->display_name,
        pmpro_url('checkout'),
        $blogname
    );

    return wp_mail($to, $subject, $message);
}

/**
 * Send confirmation email when contribution is verified as paid
 *
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_contribution_confirmation_email($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to       = $user->user_email;
    $subject  = sprintf(__('[%s] Your Contribution Has Been Verified', 'pmpro-nbstup'), $blogname);
    $message  = sprintf(
        __("Hello %s,\n\nThank you! Your contribution payment has been verified and recorded.\n\nYour account remains active. Thank you for your support.\n\nBest regards,\n%s", 'pmpro-nbstup'),
        $user->display_name,
        $blogname
    );

    return wp_mail($to, $subject, $message);
}

/**
 * Check if user is active (including contribution status)
 * Updated to also check contribution requirements
 *
 * @param int $user_id User ID to check
 * @return bool True if user is active and all requirements met, false otherwise
 */
function pmpronbstup_is_user_active_with_contribution($user_id)
{
    // First check basic active status
    if (! pmpronbstup_is_user_active($user_id)) {
        return false;
    }

    // Check if contribution is required
    $contribution_required = get_user_meta($user_id, 'pmpronbstup_contribution_required', true);
    if ((int) $contribution_required === 1) {
        // Check if contribution is paid
        $contribution_paid = get_user_meta($user_id, 'pmpronbstup_contribution_paid', true);
        if ((int) $contribution_paid !== 1) {
            return false;
        }
    }

    return true;
}

// Hook the daily contribution deadline check
add_action('wp_scheduled_event_pmpronbstup_check_contribution', 'pmpronbstup_check_contribution_deadlines');
