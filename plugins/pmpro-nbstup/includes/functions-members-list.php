<?php

/**
 * Members List admin page for PMPro NBSTUP Addon
 *
 * Displays a paginated list of all subscriber members with their details,
 * sorted by latest registration first.
 *
 * @package PMProNBSTUP
 * @subpackage Admin
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue admin assets for the Members List page.
 *
 * @param string $hook Current admin page hook suffix.
 * @return void
 */
function pmpronbstup_enqueue_members_list_assets($hook)
{
    if (! is_admin()) {
        return;
    }

    if (strpos((string) $hook, 'pmpro-nbstup-members-list') === false && (! isset($_GET['page']) || $_GET['page'] !== 'pmpro-nbstup-members-list')) {
        return;
    }

    wp_enqueue_style(
        'pmpro-nbstup-admin',
        PMPRONBSTUP_PLUGIN_URL . 'assets/css/admin.css',
        array(),
        PMPRONBSTUP_VERSION
    );
}
add_action('admin_enqueue_scripts', 'pmpronbstup_enqueue_members_list_assets');

/**
 * Render the Members List admin page.
 *
 * @return void
 */
function pmpronbstup_render_members_list_page()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pmpro-nbstup'));
    }

    $per_page    = 20;
    $current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $offset       = ($current_page - 1) * $per_page;

    // Search filter
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

    // Status filter
    $status_filter = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';

    // Build WP_User_Query args — subscribers only, newest first.
    $args = array(
        'role'    => 'subscriber',
        'orderby' => 'registered',
        'order'   => 'DESC',
        'number'  => $per_page,
        'offset'  => $offset,
    );

    if ($search !== '') {
        $args['search']         = '*' . $search . '*';
        $args['search_columns'] = array('user_login', 'user_email', 'display_name', 'user_nicename');
    }

    // Meta query for status filter
    if ($status_filter !== '') {
        switch ($status_filter) {
            case 'active':
                $args['meta_query'] = array(
                    array(
                        'key'   => 'pmpronbstup_active',
                        'value' => '1',
                    ),
                );
                break;
            case 'inactive':
                $args['meta_query'] = array(
                    'relation' => 'OR',
                    array(
                        'key'     => 'pmpronbstup_active',
                        'value'   => '0',
                    ),
                    array(
                        'key'     => 'pmpronbstup_active',
                        'compare' => 'NOT EXISTS',
                    ),
                );
                break;
            case 'deceased':
                $args['meta_query'] = array(
                    array(
                        'key'   => 'pmpronbstup_deceased',
                        'value' => '1',
                    ),
                );
                break;
        }
    }

    $user_query  = new WP_User_Query($args);
    $total_users = $user_query->get_total();
    $members     = $user_query->get_results();
    $total_pages = ceil($total_users / $per_page);

    $page_url = admin_url('admin.php?page=pmpro-nbstup-members-list');
?>
    <div class="wrap pmpro-nbstup-members-list-wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e('Members List', 'pmpro-nbstup'); ?></h1>

        <!-- Filters -->
        <div class="pmpro-nbstup-members-list-filters">
            <form method="get" action="<?php echo esc_url($page_url); ?>">
                <input type="hidden" name="page" value="pmpro-nbstup-members-list" />

                <div class="pmpro-nbstup-members-list-filters-row">
                    <select name="status" class="pmpro-nbstup-members-list-filter-select">
                        <option value=""><?php esc_html_e('All Statuses', 'pmpro-nbstup'); ?></option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'pmpro-nbstup'); ?></option>
                        <option value="inactive" <?php selected($status_filter, 'inactive'); ?>><?php esc_html_e('Inactive', 'pmpro-nbstup'); ?></option>
                        <option value="deceased" <?php selected($status_filter, 'deceased'); ?>><?php esc_html_e('Deceased', 'pmpro-nbstup'); ?></option>
                    </select>

                    <input type="search" name="s" class="pmpro-nbstup-members-list-filter-search"
                        placeholder="<?php esc_attr_e('Search by name, email, or login...', 'pmpro-nbstup'); ?>"
                        value="<?php echo esc_attr($search); ?>" />

                    <?php submit_button(__('Filter', 'pmpro-nbstup'), 'secondary', 'submit', false); ?>

                    <?php if ($search !== '' || $status_filter !== '') : ?>
                        <a href="<?php echo esc_url($page_url); ?>" class="button"><?php esc_html_e('Reset', 'pmpro-nbstup'); ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Results count -->
        <p class="pmpro-nbstup-members-list-count">
            <?php
            printf(
                /* translators: %s: number of members */
                esc_html(_n('%s member found', '%s members found', $total_users, 'pmpro-nbstup')),
                '<strong>' . number_format_i18n($total_users) . '</strong>'
            );
            ?>
        </p>

        <!-- Table -->
        <div class="pmpro-nbstup-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="column-id"><?php esc_html_e('#', 'pmpro-nbstup'); ?></th>
                        <th class="column-unique-id"><?php esc_html_e('Unique ID', 'pmpro-nbstup'); ?></th>
                        <th class="column-name"><?php esc_html_e('Name', 'pmpro-nbstup'); ?></th>
                        <th class="column-email"><?php esc_html_e('Email', 'pmpro-nbstup'); ?></th>
                        <th class="column-phone"><?php esc_html_e('Phone', 'pmpro-nbstup'); ?></th>
                        <th class="column-aadhar"><?php esc_html_e('Aadhar', 'pmpro-nbstup'); ?></th>
                        <th class="column-location"><?php esc_html_e('Location', 'pmpro-nbstup'); ?></th>
                        <th class="column-status"><?php esc_html_e('Status', 'pmpro-nbstup'); ?></th>
                        <th class="column-membership"><?php esc_html_e('Membership', 'pmpro-nbstup'); ?></th>
                        <th class="column-registered"><?php esc_html_e('Registered', 'pmpro-nbstup'); ?></th>
                        <th class="column-actions"><?php esc_html_e('Actions', 'pmpro-nbstup'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)) : ?>
                        <tr>
                            <td colspan="11"><?php esc_html_e('No members found.', 'pmpro-nbstup'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php
                        $row_num = $offset;
                        foreach ($members as $member) :
                            $row_num++;
                            $user_id = $member->ID;

                            // Gather all user meta
                            $unique_id       = get_user_meta($user_id, 'pmpronbstup_unique_id', true);
                            $active          = get_user_meta($user_id, 'pmpronbstup_active', true);
                            $deceased        = get_user_meta($user_id, 'pmpronbstup_deceased', true);
                            $renewal_status  = get_user_meta($user_id, 'pmpronbstup_renewal_status', true);
                            $expiry_date     = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
                            $start_date      = get_user_meta($user_id, 'pmpronbstup_membership_start_date', true);
                            $aadhar          = get_user_meta($user_id, 'aadhar_number', true);
                            $phone           = get_user_meta($user_id, 'phone_no', true);

                            // Location
                            $state_id    = get_user_meta($user_id, 'user_state', true);
                            $district_id = get_user_meta($user_id, 'user_district', true);
                            $block_id    = get_user_meta($user_id, 'user_block', true);

                            $state_name    = $state_id ? pmpro_nbstup_get_state_name($state_id) : '';
                            $district_name = $district_id ? pmpro_nbstup_get_district_name($district_id) : '';
                            $block_name    = $block_id ? pmpro_nbstup_get_block_name($block_id) : '';

                            $location_parts = array_filter(array($block_name, $district_name, $state_name));
                            $location       = ! empty($location_parts) ? implode(', ', $location_parts) : '—';

                            // Status badge
                            if ((int) $deceased === 1) {
                                $status_class = 'deceased';
                                $status_label = __('Deceased', 'pmpro-nbstup');
                            } elseif ((int) $active === 1) {
                                $status_class = 'active';
                                $status_label = __('Active', 'pmpro-nbstup');
                            } else {
                                $status_class = 'inactive';
                                $status_label = __('Inactive', 'pmpro-nbstup');
                            }

                            // Membership info
                            $membership_info = '';
                            if ($expiry_date) {
                                $membership_info = sprintf(
                                    /* translators: %s: expiry date */
                                    __('Expires: %s', 'pmpro-nbstup'),
                                    date_i18n(get_option('date_format'), strtotime($expiry_date))
                                );
                            } elseif ($start_date) {
                                $membership_info = sprintf(
                                    /* translators: %s: start date */
                                    __('Started: %s', 'pmpro-nbstup'),
                                    date_i18n(get_option('date_format'), strtotime($start_date))
                                );
                            }
                            if ($renewal_status) {
                                $membership_info .= $membership_info ? '<br>' : '';
                                $membership_info .= esc_html(ucfirst($renewal_status));
                            }
                        ?>
                            <tr>
                                <td class="column-id"><?php echo esc_html($row_num); ?></td>
                                <td class="column-unique-id"><?php echo esc_html($unique_id ?: '—'); ?></td>
                                <td class="column-name">
                                    <strong>
                                        <a href="<?php echo esc_url(get_edit_user_link($user_id)); ?>">
                                            <?php echo esc_html($member->display_name); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td class="column-email"><?php echo esc_html($member->user_email); ?></td>
                                <td class="column-phone"><?php echo esc_html($phone ?: '—'); ?></td>
                                <td class="column-aadhar"><?php echo esc_html($aadhar ?: '—'); ?></td>
                                <td class="column-location"><?php echo esc_html($location); ?></td>
                                <td class="column-status">
                                    <span class="pmpro-nbstup-member-status pmpro-nbstup-member-status--<?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td class="column-membership"><?php echo wp_kses($membership_info ?: '—', array('br' => array())); ?></td>
                                <td class="column-registered">
                                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($member->user_registered))); ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo esc_url(get_edit_user_link($user_id)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit', 'pmpro-nbstup'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1) :
            // Build base URL preserving filters.
            $base_args = array('page' => 'pmpro-nbstup-members-list');
            if ($search !== '') {
                $base_args['s'] = $search;
            }
            if ($status_filter !== '') {
                $base_args['status'] = $status_filter;
            }
            $first_url = esc_url(add_query_arg(array_merge($base_args, array('paged' => 1)), admin_url('admin.php')));
            $last_url  = esc_url(add_query_arg(array_merge($base_args, array('paged' => $total_pages)), admin_url('admin.php')));
            $prev_url  = $current_page > 1 ? esc_url(add_query_arg(array_merge($base_args, array('paged' => $current_page - 1)), admin_url('admin.php'))) : '';
            $next_url  = $current_page < $total_pages ? esc_url(add_query_arg(array_merge($base_args, array('paged' => $current_page + 1)), admin_url('admin.php'))) : '';
        ?>
            <div class="pmpro-nbstup-pagination">
                <span class="pmpro-nbstup-pagination__total">
                    <?php
                    printf(
                        /* translators: %s: number of items */
                        esc_html(_n('%s item', '%s items', $total_users, 'pmpro-nbstup')),
                        '<strong>' . number_format_i18n($total_users) . '</strong>'
                    );
                    ?>
                </span>

                <span class="pmpro-nbstup-pagination__links">
                    <?php // First 
                    ?>
                    <a class="pmpro-nbstup-pagination__btn<?php echo $current_page <= 1 ? ' is-disabled' : ''; ?>"
                        <?php echo $current_page > 1 ? 'href="' . $first_url . '"' : ''; ?>
                        title="<?php esc_attr_e('First page', 'pmpro-nbstup'); ?>">&laquo;</a>

                    <?php // Previous 
                    ?>
                    <a class="pmpro-nbstup-pagination__btn<?php echo $current_page <= 1 ? ' is-disabled' : ''; ?>"
                        <?php echo $prev_url ? 'href="' . $prev_url . '"' : ''; ?>
                        title="<?php esc_attr_e('Previous page', 'pmpro-nbstup'); ?>">&lsaquo;</a>

                    <?php // Page indicator + jumper 
                    ?>
                    <span class="pmpro-nbstup-pagination__indicator">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="pmpro-nbstup-pagination__jump-form">
                            <?php foreach ($base_args as $k => $v) : ?>
                                <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>" />
                            <?php endforeach; ?>
                            <input type="number" name="paged" class="pmpro-nbstup-pagination__input"
                                min="1" max="<?php echo esc_attr($total_pages); ?>"
                                value="<?php echo esc_attr($current_page); ?>" />
                        </form>
                        <?php
                        printf(
                            /* translators: %s: total pages */
                            esc_html__('of %s', 'pmpro-nbstup'),
                            '<strong>' . number_format_i18n($total_pages) . '</strong>'
                        );
                        ?>
                    </span>

                    <?php // Next 
                    ?>
                    <a class="pmpro-nbstup-pagination__btn<?php echo $current_page >= $total_pages ? ' is-disabled' : ''; ?>"
                        <?php echo $next_url ? 'href="' . $next_url . '"' : ''; ?>
                        title="<?php esc_attr_e('Next page', 'pmpro-nbstup'); ?>">&rsaquo;</a>

                    <?php // Last 
                    ?>
                    <a class="pmpro-nbstup-pagination__btn<?php echo $current_page >= $total_pages ? ' is-disabled' : ''; ?>"
                        <?php echo $current_page < $total_pages ? 'href="' . $last_url . '"' : ''; ?>
                        title="<?php esc_attr_e('Last page', 'pmpro-nbstup'); ?>">&raquo;</a>
                </span>
            </div>
        <?php endif; ?>
    </div>
<?php
}
