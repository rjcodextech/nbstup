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

    $should_load = is_user_logged_in();
    $has_login_shortcode = false;

    if ( is_singular() ) {
        $post = get_post();
        if ( $post && has_shortcode( $post->post_content, 'pmpro_nbstup_member_login' ) ) {
            $has_login_shortcode = true;
            if ( ! $should_load ) {
                $should_load = true;
            }
        }
    }

    if ( ! $should_load ) {
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

    if ( $has_login_shortcode ) {
        wp_localize_script(
            'pmpro-nbstup-frontend',
            'pmpro_nbstup_login',
            array(
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'pmpro_nbstup_login' ),
                'generic_error' => esc_html__( 'Login failed. Please try again.', 'pmpro-nbstup' ),
                'validation_message'       => esc_html__( 'Please fix the highlighted fields and try again.', 'pmpro-nbstup' ),
                'validation_login'         => esc_html__( 'Please enter your username or email.', 'pmpro-nbstup' ),
                'validation_password'      => esc_html__( 'Please enter your password.', 'pmpro-nbstup' ),
                'validation_aadhar'        => esc_html__( 'Please enter your Aadhar number.', 'pmpro-nbstup' ),
                'validation_aadhar_format' => esc_html__( 'Enter a valid 12-digit Aadhar number.', 'pmpro-nbstup' ),
            )
        );
    }
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

    $updated = update_user_meta($user_id, 'pmpronbstup_active', 1);
    if ($updated) {
        pmpronbstup_assign_unique_id($user_id);
    }

    return $updated;
}

/**
 * Get the first non-empty user meta value from a list of keys.
 *
 * @param int   $user_id User ID
 * @param array $keys    Meta keys to check in order
 * @return string
 */
function pmpronbstup_get_first_user_meta($user_id, $keys)
{
    foreach ($keys as $key) {
        $value = get_user_meta($user_id, $key, true);
        if (! empty($value)) {
            return $value;
        }
    }

    return '';
}

/**
 * Assign a 6-digit unique serial ID to a user (only once).
 * Sequential without gaps when assigned.
 *
 * @param int $user_id User ID
 * @return string|false Unique ID or false on failure
 */
function pmpronbstup_assign_unique_id($user_id)
{
    $existing = get_user_meta($user_id, 'pmpronbstup_unique_id', true);
    if (! empty($existing)) {
        return $existing;
    }

    $last = (int) get_option('pmpronbstup_unique_id_last', 0);
    $tries = 0;

    global $wpdb;

    do {
        $next = $last + 1;
        $candidate = sprintf('%06d', $next);

        $existing_user = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'pmpronbstup_unique_id',
                $candidate
            )
        );

        if (empty($existing_user)) {
            break;
        }

        $last = $next;
        $tries++;
    } while ($tries < 1000);

    if ($tries >= 1000) {
        return false;
    }

    update_user_meta($user_id, 'pmpronbstup_unique_id', $candidate);
    update_option('pmpronbstup_unique_id_last', $next);

    return $candidate;
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
    
    $defaults = pmpronbstup_get_default_email_templates();
    $subject_template = pmpronbstup_get_email_setting('expiry_reminder_subject', $defaults['expiry_reminder_subject']);
    $body_template = pmpronbstup_get_email_setting('expiry_reminder_body', $defaults['expiry_reminder_body']);
    
    // Replace placeholders
    $replacements = array(
        '{blogname}' => $blogname,
        '{display_name}' => $user->display_name,
        '{expiry_date}' => $expiry_date,
        '{days_until_expiry}' => $days_until_expiry,
        '{account_url}' => pmpro_url('account'),
    );
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $message = str_replace(array_keys($replacements), array_values($replacements), $body_template);

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
    
    $defaults = pmpronbstup_get_default_email_templates();
    $subject_template = pmpronbstup_get_email_setting('renewal_required_subject', $defaults['renewal_required_subject']);
    $body_template = pmpronbstup_get_email_setting('renewal_required_body', $defaults['renewal_required_body']);
    
    // Replace placeholders
    $replacements = array(
        '{blogname}' => $blogname,
        '{display_name}' => $user->display_name,
        '{expiry_date}' => $expiry_date,
        '{account_url}' => pmpro_url('account'),
    );
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $message = str_replace(array_keys($replacements), array_values($replacements), $body_template);

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
    
    $defaults = pmpronbstup_get_default_email_templates();
    $subject_template = pmpronbstup_get_email_setting('renewal_confirmed_subject', $defaults['renewal_confirmed_subject']);
    $body_template = pmpronbstup_get_email_setting('renewal_confirmed_body', $defaults['renewal_confirmed_body']);
    
    // Replace placeholders
    $replacements = array(
        '{blogname}' => $blogname,
        '{display_name}' => $user->display_name,
        '{expiry_date}' => $expiry_date,
    );
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $message = str_replace(array_keys($replacements), array_values($replacements), $body_template);

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
 * Shortcode: Member login with Aadhar + Password
 * Usage: [pmpro_nbstup_member_login redirect="/account/"]
 */
function pmpronbstup_member_login_shortcode( $atts )
{
    if ( is_user_logged_in() ) {
        $account_url = function_exists( 'pmpro_url' ) ? pmpro_url( 'account' ) : home_url( '/' );
        return '<p>' . sprintf( esc_html__( 'You are already logged in. Go to %s.', 'pmpro-nbstup' ), '<a href="' . esc_url( $account_url ) . '">' . esc_html__( 'your account', 'pmpro-nbstup' ) . '</a>' ) . '</p>';
    }

    $atts = shortcode_atts(
        array(
            'redirect'       => '',
            'admin_redirect' => '',
        ),
        $atts,
        'pmpro_nbstup_member_login'
    );

    $member_redirect = ! empty( $atts['redirect'] ) ? $atts['redirect'] : '';
    $admin_redirect = ! empty( $atts['admin_redirect'] ) ? $atts['admin_redirect'] : '';

    ob_start();
    ?>
    <div class="pmpro-nbstup-member-login pmpro-nbstup-login-tabs" data-login-tabs>
        <div class="pmpro-nbstup-login-tabs__header" role="tablist" aria-label="<?php esc_attr_e( 'Login tabs', 'pmpro-nbstup' ); ?>">
            <button type="button" class="pmpro-nbstup-login-tab is-active" data-tab="member" role="tab" aria-selected="true" aria-controls="pmpro-nbstup-login-panel-member" id="pmpro-nbstup-login-tab-member">
                <?php esc_html_e( 'Member Login', 'pmpro-nbstup' ); ?>
            </button>
            <button type="button" class="pmpro-nbstup-login-tab" data-tab="admin" role="tab" aria-selected="false" aria-controls="pmpro-nbstup-login-panel-admin" id="pmpro-nbstup-login-tab-admin">
                <?php esc_html_e( 'Admin Login', 'pmpro-nbstup' ); ?>
            </button>
        </div>

        <div class="pmpro-nbstup-login-panel is-active" data-panel="member" role="tabpanel" id="pmpro-nbstup-login-panel-member" aria-labelledby="pmpro-nbstup-login-tab-member">
            <div class="pmpro_message pmpro_error pmpro-nbstup-login-message" role="alert" hidden></div>

            <form method="post" class="pmpro-nbstup-member-login__form pmpro-nbstup-login-form" data-login-type="member" data-redirect="<?php echo esc_attr( $member_redirect ); ?>">
                <div class="pmpro-nbstup-member-login__header">
                    <h2 class="pmpro-nbstup-member-login__title">
                        <?php esc_html_e( 'Member Login', 'pmpro-nbstup' ); ?>
                    </h2>
                    <p class="pmpro-nbstup-member-login__subtitle">
                        <?php esc_html_e( 'Login using your Aadhar number and password.', 'pmpro-nbstup' ); ?>
                    </p>
                </div>

                <div class="pmpro-nbstup-member-login__field">
                    <label for="pmpro_nbstup_aadhar_number" class="pmpro-nbstup-member-login__label">
                        <?php esc_html_e( 'आधार कार्ड नंबर', 'pmpro-nbstup' ); ?>
                    </label>
                    <input
                        type="text"
                        id="pmpro_nbstup_aadhar_number"
                        name="aadhar_number"
                        class="pmpro-nbstup-member-login__input"
                        inputmode="numeric"
                        autocomplete="username"
                        required
                    />
                </div>

                <div class="pmpro-nbstup-member-login__field">
                    <label for="pmpro_nbstup_member_password" class="pmpro-nbstup-member-login__label">
                        <?php esc_html_e( 'Password', 'pmpro-nbstup' ); ?>
                    </label>
                    <input
                        type="password"
                        id="pmpro_nbstup_member_password"
                        name="member_password"
                        class="pmpro-nbstup-member-login__input"
                        autocomplete="current-password"
                        required
                    />
                </div>

                <div class="pmpro-nbstup-member-login__actions">
                    <button type="submit" class="pmpro_btn pmpro_btn-submit pmpro-nbstup-member-login__submit">
                        <?php esc_html_e( 'Log In', 'pmpro-nbstup' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="pmpro-nbstup-login-panel" data-panel="admin" role="tabpanel" id="pmpro-nbstup-login-panel-admin" aria-labelledby="pmpro-nbstup-login-tab-admin">
            <div class="pmpro_message pmpro_error pmpro-nbstup-login-message" role="alert" hidden></div>

            <form method="post" class="pmpro-nbstup-member-login__form pmpro-nbstup-login-form" data-login-type="admin" data-redirect="<?php echo esc_attr( $admin_redirect ); ?>">
                <div class="pmpro-nbstup-member-login__header">
                    <h2 class="pmpro-nbstup-member-login__title">
                        <?php esc_html_e( 'Admin Login', 'pmpro-nbstup' ); ?>
                    </h2>
                    <p class="pmpro-nbstup-member-login__subtitle">
                        <?php esc_html_e( 'Login with your WordPress username or email.', 'pmpro-nbstup' ); ?>
                    </p>
                </div>

                <div class="pmpro-nbstup-member-login__field">
                    <label for="pmpro_nbstup_admin_login" class="pmpro-nbstup-member-login__label">
                        <?php esc_html_e( 'Username or Email', 'pmpro-nbstup' ); ?>
                    </label>
                    <input
                        type="text"
                        id="pmpro_nbstup_admin_login"
                        name="user_login"
                        class="pmpro-nbstup-member-login__input"
                        autocomplete="username"
                        required
                    />
                </div>

                <div class="pmpro-nbstup-member-login__field">
                    <label for="pmpro_nbstup_admin_password" class="pmpro-nbstup-member-login__label">
                        <?php esc_html_e( 'Password', 'pmpro-nbstup' ); ?>
                    </label>
                    <input
                        type="password"
                        id="pmpro_nbstup_admin_password"
                        name="user_password"
                        class="pmpro-nbstup-member-login__input"
                        autocomplete="current-password"
                        required
                    />
                </div>

                <div class="pmpro-nbstup-member-login__field pmpro-nbstup-login__checkbox">
                    <label for="pmpro_nbstup_admin_remember">
                        <input type="checkbox" id="pmpro_nbstup_admin_remember" name="remember" value="1" />
                        <?php esc_html_e( 'Remember me', 'pmpro-nbstup' ); ?>
                    </label>
                </div>

                <div class="pmpro-nbstup-member-login__actions">
                    <button type="submit" class="pmpro_btn pmpro_btn-submit pmpro-nbstup-member-login__submit">
                        <?php esc_html_e( 'Log In', 'pmpro-nbstup' ); ?>
                    </button>
                </div>
            </form>
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
add_shortcode('pmpro_nbstup_member_login', 'pmpronbstup_member_login_shortcode');

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
 * Mark all active users as requiring contribution payment for deceased member
 * Called when a user is marked as deceased
 *
 * @param int $deceased_user_id User ID of the deceased member
 * @return int Number of users marked to pay contribution
 */
function pmpronbstup_mark_contribution_required_deceased($deceased_user_id)
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
        $already_required = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_required', true);
        if ((int) $already_required === 1) {
            continue;
        }

        update_user_meta($user->ID, 'pmpronbstup_contribution_deceased_required', 1);
        update_user_meta($user->ID, 'pmpronbstup_contribution_deceased_deadline', $contribution_deadline);
        update_user_meta($user->ID, 'pmpronbstup_contribution_deceased_paid', 0);

        // Send notification email
        pmpronbstup_send_contribution_required_email($user->ID, $contribution_deadline, 'deceased');

        $count++;
    }

    return $count;
}

/**
 * Mark all active users as requiring contribution payment (legacy function for backward compatibility)
 * Called when a user is marked as deceased
 *
 * @param int $deceased_user_id User ID of the deceased member
 * @return int Number of users marked to pay contribution
 */
function pmpronbstup_mark_contribution_required($deceased_user_id)
{
    return pmpronbstup_mark_contribution_required_deceased($deceased_user_id);
}

/**
 * Mark all active users as requiring contribution payment for daughter wedding
 * Called when a user is marked for daughter wedding
 * Note: This can be called multiple times for different weddings
 *
 * @param int $wedding_user_id User ID of the member with daughter wedding
 * @return int Number of users marked to pay contribution
 */
function pmpronbstup_mark_contribution_required_wedding($wedding_user_id)
{
    $users = get_users(array(
        'role'       => 'subscriber',
        'meta_key'   => 'pmpronbstup_active',
        'meta_value' => '1',
    ));

    $count = 0;
    $contribution_deadline = date('Y-m-d', strtotime('+1 month'));

    foreach ($users as $user) {
        // Skip the wedding user themselves
        if ($user->ID === $wedding_user_id) {
            continue;
        }

        // Always mark as requiring contribution (allows multiple weddings)
        // If already required and unpaid, this updates the deadline
        update_user_meta($user->ID, 'pmpronbstup_contribution_wedding_required', 1);
        update_user_meta($user->ID, 'pmpronbstup_contribution_wedding_deadline', $contribution_deadline);
        
        // Only reset paid status if not already paid for current wedding cycle
        $already_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_paid', true);
        if ((int) $already_paid !== 1) {
            update_user_meta($user->ID, 'pmpronbstup_contribution_wedding_paid', 0);
        }

        // Send notification email
        pmpronbstup_send_contribution_required_email($user->ID, $contribution_deadline, 'wedding');

        $count++;
    }

    return $count;
}

/**
 * Send email notifying user that they need to pay contribution
 *
 * @param int    $user_id User ID
 * @param string $deadline Deadline date (Y-m-d format)
 * @param string $type Contribution type: 'deceased' or 'wedding'
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_contribution_required_email($user_id, $deadline, $type = 'deceased')
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to       = $user->user_email;
    
    $defaults = pmpronbstup_get_default_email_templates();
    
    if ($type === 'wedding') {
        $subject_template = pmpronbstup_get_email_setting('wedding_contribution_subject', $defaults['wedding_contribution_subject']);
        $body_template = pmpronbstup_get_email_setting('wedding_contribution_body', $defaults['wedding_contribution_body']);
    } else {
        $subject_template = pmpronbstup_get_email_setting('deceased_contribution_subject', $defaults['deceased_contribution_subject']);
        $body_template = pmpronbstup_get_email_setting('deceased_contribution_body', $defaults['deceased_contribution_body']);
    }
    
    // Replace placeholders
    $replacements = array(
        '{blogname}' => $blogname,
        '{display_name}' => $user->display_name,
        '{deadline}' => date_i18n(get_option('date_format'), strtotime($deadline)),
        '{account_url}' => pmpro_url('account'),
    );
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $message = str_replace(array_keys($replacements), array_values($replacements), $body_template);

    return wp_mail($to, $subject, $message);
}

/**
 * Check all users for overdue contribution payments and deactivate if needed
 * Should be called via wp_scheduled_event
 */
function pmpronbstup_check_contribution_deadlines()
{
    $overdue_deceased = array();
    $overdue_wedding = array();
    
    // Check deceased contributions
    $users_deceased = get_users(array(
        'meta_key'   => 'pmpronbstup_contribution_deceased_required',
        'meta_value' => '1',
    ));

    foreach ($users_deceased as $user) {
        // Skip if contribution already paid
        $paid = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_paid', true);
        if ((int) $paid === 1) {
            continue;
        }

        // Check deadline
        $deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_deadline', true);
        if (! $deadline) {
            continue;
        }

        $deadline_timestamp = strtotime($deadline);
        $current_timestamp  = time();

        if ($deadline_timestamp < $current_timestamp) {
            // Deadline passed, deactivate user
            pmpronbstup_deactivate_user($user->ID);
            update_user_meta($user->ID, 'pmpronbstup_renewal_status', 'contribution_overdue');

            // Send overdue notification email to user
            pmpronbstup_send_contribution_overdue_email($user->ID);
            
            // Add to admin notification list
            $overdue_deceased[] = array(
                'user' => $user,
                'deadline' => $deadline,
                'type' => 'deceased'
            );
        }
    }
    
    // Check wedding contributions
    $users_wedding = get_users(array(
        'meta_key'   => 'pmpronbstup_contribution_wedding_required',
        'meta_value' => '1',
    ));

    foreach ($users_wedding as $user) {
        // Skip if contribution already paid
        $paid = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_paid', true);
        if ((int) $paid === 1) {
            continue;
        }

        // Check deadline
        $deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_deadline', true);
        if (! $deadline) {
            continue;
        }

        $deadline_timestamp = strtotime($deadline);
        $current_timestamp  = time();

        if ($deadline_timestamp < $current_timestamp) {
            // Deadline passed, deactivate user
            pmpronbstup_deactivate_user($user->ID);
            update_user_meta($user->ID, 'pmpronbstup_renewal_status', 'contribution_overdue');

            // Send overdue notification email to user
            pmpronbstup_send_contribution_overdue_email($user->ID);
            
            // Add to admin notification list
            $overdue_wedding[] = array(
                'user' => $user,
                'deadline' => $deadline,
                'type' => 'wedding'
            );
        }
    }
    
    // Send admin summary email if there are overdue contributions
    if (!empty($overdue_deceased) || !empty($overdue_wedding)) {
        pmpronbstup_send_admin_overdue_summary($overdue_deceased, $overdue_wedding);
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
    
    $defaults = pmpronbstup_get_default_email_templates();
    $subject_template = pmpronbstup_get_email_setting('contribution_overdue_subject', $defaults['contribution_overdue_subject']);
    $body_template = pmpronbstup_get_email_setting('contribution_overdue_body', $defaults['contribution_overdue_body']);
    
    // Replace placeholders
    $replacements = array(
        '{blogname}' => $blogname,
        '{display_name}' => $user->display_name,
        '{account_url}' => pmpro_url('account'),
    );
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $message = str_replace(array_keys($replacements), array_values($replacements), $body_template);

    return wp_mail($to, $subject, $message);
}

/**
 * Send admin summary email of all overdue contributions
 *
 * @param array $overdue_deceased Array of deceased contribution overdue users
 * @param array $overdue_wedding Array of wedding contribution overdue users
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_admin_overdue_summary($overdue_deceased, $overdue_wedding)
{
    $admin_email = get_option('admin_email');
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    
    $total_overdue = count($overdue_deceased) + count($overdue_wedding);
    
    $subject = sprintf(
        __('[%s] %d Members with Overdue Contributions - Accounts Deactivated', 'pmpro-nbstup'),
        $blogname,
        $total_overdue
    );
    
    $message = sprintf(
        __("Admin Notification - Overdue Contributions\n\n%d member(s) have been automatically deactivated due to overdue contribution payments.\n\n", 'pmpro-nbstup'),
        $total_overdue
    );
    
    if (!empty($overdue_deceased)) {
        $message .= sprintf(
            __("DECEASED MEMBER CONTRIBUTIONS (%d overdue):\n", 'pmpro-nbstup'),
            count($overdue_deceased)
        );
        $message .= str_repeat('-', 50) . "\n";
        foreach ($overdue_deceased as $item) {
            $message .= sprintf(
                "- %s (%s) - Deadline: %s\n",
                $item['user']->display_name,
                $item['user']->user_email,
                date_i18n(get_option('date_format'), strtotime($item['deadline']))
            );
        }
        $message .= "\n";
    }
    
    if (!empty($overdue_wedding)) {
        $message .= sprintf(
            __("WEDDING CONTRIBUTIONS (%d overdue):\n", 'pmpro-nbstup'),
            count($overdue_wedding)
        );
        $message .= str_repeat('-', 50) . "\n";
        foreach ($overdue_wedding as $item) {
            $message .= sprintf(
                "- %s (%s) - Deadline: %s\n",
                $item['user']->display_name,
                $item['user']->user_email,
                date_i18n(get_option('date_format'), strtotime($item['deadline']))
            );
        }
        $message .= "\n";
    }
    
    $message .= __("\nThese members have been notified via email and their accounts have been deactivated.\n\n", 'pmpro-nbstup');
    $message .= sprintf(
        __("To manage contributions, visit: %s/wp-admin/admin.php?page=pmpro-nbstup-contributions\n\n", 'pmpro-nbstup'),
        home_url()
    );
    $message .= sprintf(__("Best regards,\n%s", 'pmpro-nbstup'), $blogname);
    
    return wp_mail($admin_email, $subject, $message);
}

/**
 * Send confirmation email when contribution is verified as paid
 *
 * @param int $user_id User ID
 * @param string $type Contribution type: 'deceased' or 'wedding'
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_contribution_confirmation_email($user_id, $type = 'deceased')
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to       = $user->user_email;
    
    $defaults = pmpronbstup_get_default_email_templates();
    
    if ($type === 'wedding') {
        $subject_template = pmpronbstup_get_email_setting('wedding_contribution_confirmed_subject', $defaults['wedding_contribution_confirmed_subject']);
        $body_template = pmpronbstup_get_email_setting('wedding_contribution_confirmed_body', $defaults['wedding_contribution_confirmed_body']);
    } else {
        $subject_template = pmpronbstup_get_email_setting('deceased_contribution_confirmed_subject', $defaults['deceased_contribution_confirmed_subject']);
        $body_template = pmpronbstup_get_email_setting('deceased_contribution_confirmed_body', $defaults['deceased_contribution_confirmed_body']);
    }
    
    // Replace placeholders
    $replacements = array(
        '{blogname}' => $blogname,
        '{display_name}' => $user->display_name,
    );
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $message = str_replace(array_keys($replacements), array_values($replacements), $body_template);

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

/**
 * Shortcode to display a list of all users with details, pagination, and search
 * Usage: [pmpro_nbstup_users_list]
 * Optional attributes: per_page (default: 20)
 *
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function pmpronbstup_users_list_shortcode($atts)
{
    // Parse shortcode attributes
    $atts = shortcode_atts(array(
        'per_page' => 20,
    ), $atts, 'pmpro_nbstup_users_list');

    $per_page = intval($atts['per_page']);
    if ($per_page < 1) {
        $per_page = 20;
    }

    // Get current page
    $paged = isset($_GET['user_page']) ? max(1, intval($_GET['user_page'])) : 1;

    // Get search query
    $search = isset($_GET['user_search']) ? sanitize_text_field($_GET['user_search']) : '';

    // Build user query arguments
    $args = array(
        'role'    => 'subscriber',
        'orderby' => 'display_name',
        'order'   => 'ASC',
        'number'  => $per_page,
        'offset'  => ($paged - 1) * $per_page,
    );

    // Add search if provided
    if (!empty($search)) {
        $args['search'] = '*' . $search . '*';
        $args['search_columns'] = array('user_login', 'user_email', 'display_name');
    }

    // Get users
    $user_query = new WP_User_Query($args);
    $users = $user_query->get_results();
    $total_users = $user_query->get_total();

    // Calculate pagination
    $total_pages = ceil($total_users / $per_page);
    $serial_start = ($paged - 1) * $per_page;

    // Start output buffering
    ob_start();
    ?>
    <div class="pmpro-nbstup-users-list">
        <!-- Search Form -->
        <form method="get" class="pmpro-nbstup-search-form">
            <?php
            // Preserve all GET parameters except user_page and user_search
            foreach ($_GET as $key => $value) {
                if ($key !== 'user_page' && $key !== 'user_search') {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
                }
            }
            ?>
            <input type="text" name="user_search" placeholder="<?php esc_attr_e('Search users by name, email, or username...', 'pmpro-nbstup'); ?>" value="<?php echo esc_attr($search); ?>" />
            <button type="submit"><?php esc_html_e('Search', 'pmpro-nbstup'); ?></button>
            <?php if (!empty($search)) : ?>
                <a href="<?php echo esc_url(remove_query_arg(array('user_search', 'user_page'))); ?>" class="button"><?php esc_html_e('Clear', 'pmpro-nbstup'); ?></a>
            <?php endif; ?>
        </form>

        <?php if (!empty($search)) : ?>
            <p><strong><?php printf(__('Search results for: %s', 'pmpro-nbstup'), esc_html($search)); ?></strong> (<?php printf(__('%d users found', 'pmpro-nbstup'), $total_users); ?>)</p>
        <?php endif; ?>

        <?php if (empty($users)) : ?>
            <p><?php esc_html_e('No users found.', 'pmpro-nbstup'); ?></p>
        <?php else : ?>
            <table class="pmpro-nbstup-users-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('S No', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('Unique ID', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('नाम', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('पिता/पति का नाम', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('व्यवसाय', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('स्थाई निवासी जिला', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('ब्लॉक', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('Status', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('स्थाई पता', 'pmpro-nbstup'); ?></th>
                        <th><?php esc_html_e('Submission Date', 'pmpro-nbstup'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $index => $user) :
                        $serial = $serial_start + $index + 1;

                        $unique_id = get_user_meta($user->ID, 'pmpronbstup_unique_id', true);
                        $active = get_user_meta($user->ID, 'pmpronbstup_active', true);

                        $father_husband = pmpronbstup_get_first_user_meta($user->ID, array(
                            'pmpronbstup_father_husband_name',
                            'father_husband_name',
                            'father_name',
                            'husband_name',
                            'pmpronbstup_father_name',
                            'pmpronbstup_husband_name',
                        ));

                        $occupation = pmpronbstup_get_first_user_meta($user->ID, array(
                            'Occupation',
                            'pmpronbstup_occupation',
                            'occupation',
                            'business',
                            'profession',
                        ));

                        $user_district_id = get_user_meta($user->ID, 'user_district', true);
                        $user_block_id = get_user_meta($user->ID, 'user_block', true);
                        $user_address = get_user_meta($user->ID, 'user_address', true);

                        $district_name = $user_district_id ? pmpro_nbstup_get_district_name($user_district_id) : '';
                        $block_name = $user_block_id ? pmpro_nbstup_get_block_name($user_block_id) : '';

                        $submission_date = $user->user_registered ? date_i18n(get_option('date_format'), strtotime($user->user_registered)) : '';
                    ?>
                        <tr>
                            <td><?php echo esc_html($serial); ?></td>
                            <td><?php echo esc_html($unique_id ?: '-'); ?></td>
                            <td>
                                <?php
                                $display_name = pmpronbstup_get_first_user_meta($user->ID, array('name'));
                                echo esc_html($display_name ? $display_name : ($user->display_name ?: '-'));
                                ?>
                            </td>
                            <td><?php echo esc_html($father_husband ?: '-'); ?></td>
                            <td><?php echo esc_html($occupation ?: '-'); ?></td>
                            <td><?php echo esc_html($district_name ?: '-'); ?></td>
                            <td><?php echo esc_html($block_name ?: '-'); ?></td>
                            <td>
                                <?php if ((int) $active === 1) : ?>
                                    <span class="status-badge status-active"><?php esc_html_e('Active', 'pmpro-nbstup'); ?></span>
                                <?php else : ?>
                                    <span class="status-badge status-inactive"><?php esc_html_e('Inactive', 'pmpro-nbstup'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($user_address ?: '-'); ?></td>
                            <td><?php echo esc_html($submission_date ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="pmpro-nbstup-pagination">
                    <?php if ($paged > 1) : ?>
                        <a href="<?php echo esc_url(add_query_arg('user_page', $paged - 1)); ?>">&laquo; <?php esc_html_e('Previous', 'pmpro-nbstup'); ?></a>
                    <?php endif; ?>

                    <?php
                    // Show page numbers
                    $range = 2; // Number of pages to show on each side
                    for ($i = 1; $i <= $total_pages; $i++) {
                        if ($i == 1 || $i == $total_pages || ($i >= $paged - $range && $i <= $paged + $range)) {
                            if ($i == $paged) {
                                echo '<span class="current">' . $i . '</span>';
                            } else {
                                echo '<a href="' . esc_url(add_query_arg('user_page', $i)) . '">' . $i . '</a>';
                            }
                        } elseif ($i == $paged - $range - 1 || $i == $paged + $range + 1) {
                            echo '<span>...</span>';
                        }
                    }
                    ?>

                    <?php if ($paged < $total_pages) : ?>
                        <a href="<?php echo esc_url(add_query_arg('user_page', $paged + 1)); ?>"><?php esc_html_e('Next', 'pmpro-nbstup'); ?> &raquo;</a>
                    <?php endif; ?>
                </div>

                <p class="pmpro-nbstup-pagination-summary">
                    <?php printf(__('Page %d of %d | Total users: %d', 'pmpro-nbstup'), $paged, $total_pages, $total_users); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('pmpro_nbstup_users_list', 'pmpronbstup_users_list_shortcode');
