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
                    <li><a href="#pmpro_account-links"><?php esc_html_e('Member Links', 'pmpro-nbstup'); ?></a></li>
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
    <h2><?php esc_html_e('Deceased Members - Pay Contribution', 'pmpro-nbstup'); ?></h2>
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
