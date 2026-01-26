<?php

/**
 * Authentication and login restrictions for PMPro NBSTUP Addon
 *
 * @package PMProNBSTUP
 * @subpackage Authentication
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Prevent login for inactive subscriber users or those with expired memberships.
 * Also checks if contribution is required and paid.
 *
 * @param WP_User|WP_Error $user User object or error
 * @param string           $username Username
 * @param string           $password Password
 * @return WP_User|WP_Error User object or error
 */
function pmpronbstup_authenticate($user, $username, $password)
{
    if ($user instanceof WP_User) {
        if (! in_array('subscriber', (array) $user->roles, true)) {
            return $user;
        }

        // Check if user is active
        if (! pmpronbstup_is_user_active($user->ID)) {
            // Provide specific error message based on why they're inactive
            $deceased = get_user_meta($user->ID, 'pmpronbstup_deceased', true);
            if ((int) $deceased === 1) {
                $error_msg = __('<strong>Error</strong>: This account has been marked as deceased and cannot access the system.', 'pmpro-nbstup');
            } else {
                // Check if membership expired
                $expiry_date = get_user_meta($user->ID, 'pmpronbstup_membership_expiry_date', true);
                if ($expiry_date && strtotime($expiry_date) < time()) {
                    $error_msg = __('<strong>Error</strong>: Your membership has expired. Please renew your membership to regain access.', 'pmpro-nbstup');
                } else {
                    $error_msg = __('<strong>Error</strong>: Your account is not active. Please contact support or renew your membership.', 'pmpro-nbstup');
                }
            }

            return new WP_Error(
                'pmpronbstup_inactive',
                $error_msg
            );
        }

        // Check if contribution is required but not paid
        $contribution_required = get_user_meta($user->ID, 'pmpronbstup_contribution_required', true);
        if ((int) $contribution_required === 1) {
            $contribution_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_paid', true);
            if ((int) $contribution_paid !== 1) {
                $deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_deadline', true);
                $error_msg = sprintf(
                    __('<strong>Error</strong>: Your contribution payment is required by %s. Please pay the contribution to access your account.', 'pmpro-nbstup'),
                    $deadline ? date_i18n(get_option('date_format'), strtotime($deadline)) : 'the deadline'
                );

                return new WP_Error(
                    'pmpronbstup_contribution_required',
                    $error_msg
                );
            }
        }
    }

    return $user;
}
add_filter('authenticate', 'pmpronbstup_authenticate', 30, 3);
