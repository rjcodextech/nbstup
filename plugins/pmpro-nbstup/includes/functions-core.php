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
 *
 * @param int $user_id User ID to check
 * @return bool True if user is active, false otherwise
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

    return (int) $active === 1 || $active === '1' || $active === true;
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
                    <li><a href="#pmpro_account-links"><?php esc_html_e('Member Links', 'pmpro-nbstup'); ?></a></li>
                </ul>
            </nav>
        </aside>

        <div class="pmpro-nbstup-account-content">
            <?php
            // Render the standard PMPro account shortcode inside the content pane.
            echo do_shortcode('[pmpro_account]');
            ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('pmpro_account_nbstup', 'pmpronbstup_account_two_column_shortcode');
