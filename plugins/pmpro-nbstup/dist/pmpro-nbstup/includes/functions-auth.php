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
 * Map WP signon errors to user-friendly JSON payload.
 *
 * @param WP_Error $error      Login error.
 * @param string   $login_type member|admin.
 * @return array
 */
function pmpronbstup_get_login_error_payload( $error, $login_type = 'member' ) {
    $code = (string) $error->get_error_code();

    switch ( $code ) {
        case 'incorrect_password':
            return array(
                'error_code' => 'invalid_password',
                'message'    => esc_html__( 'Invalid password. Please try again.', 'pmpro-nbstup' ),
            );

        case 'invalid_username':
            return array(
                'error_code' => $login_type === 'member' ? 'invalid_aadhar' : 'invalid_login',
                'message'    => $login_type === 'member'
                    ? esc_html__( 'Invalid Aadhar number. Please check and try again.', 'pmpro-nbstup' )
                    : esc_html__( 'Invalid username or email. Please check and try again.', 'pmpro-nbstup' ),
            );

        case 'empty_password':
            return array(
                'error_code' => 'invalid_password',
                'message'    => esc_html__( 'Password is required.', 'pmpro-nbstup' ),
            );

        case 'pmpronbstup_inactive':
        case 'pmpronbstup_contribution_required':
            return array(
                'error_code' => $code,
                'message'    => wp_strip_all_tags( $error->get_error_message( $code ) ),
            );

        default:
            return array(
                'error_code' => 'login_failed',
                'message'    => esc_html__( 'Login failed. Please verify your details and try again.', 'pmpro-nbstup' ),
            );
    }
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

        $deceased_required = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_required', true);
        $deceased_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_paid', true);
        $deceased_deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_deadline', true);

        $wedding_required = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_required', true);
        $wedding_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_paid', true);
        $wedding_deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_deadline', true);

        $requires_payment = false;
        $deadline_timestamps = array();

        if ((int) $deceased_required === 1 && (int) $deceased_paid !== 1) {
            $requires_payment = true;
            if (! empty($deceased_deadline)) {
                $deadline_timestamps[] = strtotime($deceased_deadline);
            }
        }

        if ((int) $wedding_required === 1 && (int) $wedding_paid !== 1) {
            $requires_payment = true;
            if (! empty($wedding_deadline)) {
                $deadline_timestamps[] = strtotime($wedding_deadline);
            }
        }

        if ($requires_payment) {
            $deadline_text = 'the deadline';
            if (! empty($deadline_timestamps)) {
                $next_deadline = min($deadline_timestamps);
                $deadline_text = date_i18n(get_option('date_format'), $next_deadline);
            }

            $error_msg = sprintf(
                __('<strong>Error</strong>: Your contribution payment is required by %s. Please pay the contribution to access your account.', 'pmpro-nbstup'),
                $deadline_text
            );

            return new WP_Error(
                'pmpronbstup_contribution_required',
                $error_msg
            );
        }
    }

    return $user;
}
add_filter('authenticate', 'pmpronbstup_authenticate', 30, 3);

/**
 * AJAX: Member login via Aadhar number and password.
 */
add_action( 'wp_ajax_nopriv_pmpronbstup_member_login', 'pmpronbstup_ajax_member_login' );
add_action( 'wp_ajax_pmpronbstup_member_login', 'pmpronbstup_ajax_member_login' );
function pmpronbstup_ajax_member_login() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'pmpro_nbstup_login' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Please try again.', 'pmpro-nbstup' ) ) );
    }

    if ( is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => esc_html__( 'You are already logged in.', 'pmpro-nbstup' ) ) );
    }

    $aadhar_raw = isset( $_POST['aadhar_number'] ) ? wp_unslash( $_POST['aadhar_number'] ) : '';
    $aadhar = preg_replace( '/\D+/', '', $aadhar_raw );
    $password = isset( $_POST['member_password'] ) ? wp_unslash( $_POST['member_password'] ) : '';

    if ( $aadhar === '' || ! preg_match( '/^\d{12}$/', $aadhar ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Please enter a valid 12-digit Aadhar number.', 'pmpro-nbstup' ) ) );
    }

    if ( $password === '' ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Password is required.', 'pmpro-nbstup' ) ) );
    }

    $users = get_users(
        array(
            'number'     => 2,
            'meta_key'   => 'aadhar_number',
            'meta_value' => $aadhar,
        )
    );

    if ( empty( $users ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'No account found for this Aadhar number.', 'pmpro-nbstup' ) ) );
    }

    if ( count( $users ) > 1 ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Multiple accounts found for this Aadhar number. Please contact support.', 'pmpro-nbstup' ) ) );
    }

    $user = $users[0];

    // Authenticate members using Aadhar so member-only auth filters can allow the request.
    $signon = wp_signon(
        array(
            'user_login'    => $aadhar,
            'user_password' => $password,
            'remember'      => true,
        ),
        is_ssl()
    );

    if ( is_wp_error( $signon ) ) {
        $payload = pmpronbstup_get_login_error_payload( $signon, 'member' );
        wp_send_json_error( $payload );
    }

    if ( user_can( $signon, 'manage_options' ) ) {
        wp_logout();
        wp_send_json_error( array( 'message' => esc_html__( 'Admin users must log in from the admin login tab.', 'pmpro-nbstup' ) ) );
    }

    $default_redirect = function_exists( 'pmpro_url' ) ? pmpro_url( 'account' ) : home_url( '/' );
    $redirect_raw = isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ) : '';
    $redirect_raw = $redirect_raw !== '' ? esc_url_raw( $redirect_raw ) : '';
    $redirect = $redirect_raw ? wp_validate_redirect( $redirect_raw, $default_redirect ) : $default_redirect;

    wp_send_json_success( array( 'redirect' => $redirect ) );
}

/**
 * AJAX: Admin login via username/email and password.
 */
add_action( 'wp_ajax_nopriv_pmpronbstup_admin_login', 'pmpronbstup_ajax_admin_login' );
add_action( 'wp_ajax_pmpronbstup_admin_login', 'pmpronbstup_ajax_admin_login' );
function pmpronbstup_ajax_admin_login() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'pmpro_nbstup_login' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Please try again.', 'pmpro-nbstup' ) ) );
    }

    if ( is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => esc_html__( 'You are already logged in.', 'pmpro-nbstup' ) ) );
    }

    $user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
    $password = isset( $_POST['user_password'] ) ? wp_unslash( $_POST['user_password'] ) : '';
    $remember = ! empty( $_POST['remember'] );

    if ( $user_login === '' ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Username or email is required.', 'pmpro-nbstup' ) ) );
    }

    if ( $password === '' ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Password is required.', 'pmpro-nbstup' ) ) );
    }

    $signon = wp_signon(
        array(
            'user_login'    => $user_login,
            'user_password' => $password,
            'remember'      => $remember,
        ),
        is_ssl()
    );

    if ( is_wp_error( $signon ) ) {
        $payload = pmpronbstup_get_login_error_payload( $signon, 'admin' );
        wp_send_json_error( $payload );
    }

    $default_redirect = admin_url();
    $redirect_raw = isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ) : '';
    $redirect_raw = $redirect_raw !== '' ? esc_url_raw( $redirect_raw ) : '';
    $redirect = $redirect_raw ? wp_validate_redirect( $redirect_raw, $default_redirect ) : $default_redirect;

    wp_send_json_success( array( 'redirect' => $redirect ) );
}

/**
 * Mark subscriber users for one-time logout after confirmation page display.
 *
 * @param int      $user_id Checked out user ID.
 * @param stdClass $morder  Membership order object.
 * @return void
 */
function pmpronbstup_mark_user_for_post_confirmation_logout( $user_id, $morder ) {
    if ( empty( $user_id ) ) {
        return;
    }

    $user = get_userdata( $user_id );
    if ( ! $user instanceof WP_User ) {
        return;
    }

    if ( in_array( 'administrator', (array) $user->roles, true ) || user_can( $user, 'manage_options' ) ) {
        return;
    }

    update_user_meta( $user_id, 'pmpronbstup_logout_after_confirmation', time() );
}
add_action( 'pmpro_after_checkout', 'pmpronbstup_mark_user_for_post_confirmation_logout', 99, 2 );

/**
 * Determine if the current request is PMPro confirmation page.
 *
 * @return bool
 */
function pmpronbstup_is_confirmation_request() {
    if ( function_exists( 'pmpro_is_confirmation_page' ) && pmpro_is_confirmation_page() ) {
        return true;
    }

    if ( function_exists( 'pmpro_getOption' ) ) {
        $confirmation_page_id = (int) pmpro_getOption( 'confirmation_page_id' );
        if ( $confirmation_page_id > 0 && is_page( $confirmation_page_id ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Log users out after their first confirmation page request post-checkout.
 *
 * @return void
 */
function pmpronbstup_logout_user_after_confirmation_page() {
    if ( ! is_user_logged_in() || ! pmpronbstup_is_confirmation_request() ) {
        return;
    }

    if ( isset( $_GET['pmpronbstup_logged_out'] ) ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( empty( $user_id ) || current_user_can( 'manage_options' ) ) {
        return;
    }

    $logout_marker = (int) get_user_meta( $user_id, 'pmpronbstup_logout_after_confirmation', true );
    if ( empty( $logout_marker ) ) {
        return;
    }

    delete_user_meta( $user_id, 'pmpronbstup_logout_after_confirmation' );
    wp_logout();

    $redirect_url = add_query_arg( 'pmpronbstup_logged_out', '1' );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'template_redirect', 'pmpronbstup_logout_user_after_confirmation_page', 20 );
