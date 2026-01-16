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
 * Prevent login for inactive subscriber users.
 *
 * @param WP_User|WP_Error $user User object or error
 * @param string           $username Username
 * @param string           $password Password
 * @return WP_User|WP_Error User object or error
 */
function pmpronbstup_authenticate($user, $username, $password)
{
    if ($user instanceof WP_User) {
        if (! pmpronbstup_is_user_active($user->ID)) {
            return new WP_Error(
                'pmpronbstup_inactive',
                __('<strong>Error</strong>: Your account is not active. Please contact support.', 'pmpro-nbstup')
            );
        }
    }

    return $user;
}
add_filter('authenticate', 'pmpronbstup_authenticate', 30, 3);
