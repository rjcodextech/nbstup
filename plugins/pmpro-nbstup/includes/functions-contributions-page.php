<?php

/**
 * Contributions Management Page for PMPro NBSTUP Addon
 *
 * @package PMProNBSTUP
 * @subpackage Contributions Management
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Add contributions management menu under Paid Memberships Pro
 */
function pmpronbstup_add_contributions_menu()
{
    add_submenu_page(
        'pmpro-dashboard',
        __('Contributions', 'pmpro-nbstup'),
        __('Contributions', 'pmpro-nbstup'),
        'manage_options',
        'pmpro-nbstup-contributions',
        'pmpronbstup_render_contributions_page'
    );
}
add_action('admin_menu', 'pmpronbstup_add_contributions_menu', 20);

/**
 * Handle bulk actions and individual actions
 */
function pmpronbstup_handle_contribution_actions()
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    // Handle bulk action
    if (isset($_POST['pmpronbstup_bulk_action_nonce']) && 
        wp_verify_nonce($_POST['pmpronbstup_bulk_action_nonce'], 'pmpronbstup_bulk_action')) {
        
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '';
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : array();
        
        if (!empty($action) && !empty($user_ids)) {
            $count = 0;
            
            foreach ($user_ids as $user_id) {
                if ($action === 'mark_deceased_paid') {
                    update_user_meta($user_id, 'pmpronbstup_contribution_deceased_paid', 1);
                    pmpronbstup_send_contribution_confirmation_email($user_id, 'deceased');
                    $count++;
                } elseif ($action === 'mark_wedding_paid') {
                    update_user_meta($user_id, 'pmpronbstup_contribution_wedding_paid', 1);
                    pmpronbstup_send_contribution_confirmation_email($user_id, 'wedding');
                    $count++;
                }
            }
            
            if ($count > 0) {
                $action_label = $action === 'mark_deceased_paid' ? __('deceased', 'pmpro-nbstup') : __('wedding', 'pmpro-nbstup');
                add_settings_error(
                    'pmpro-nbstup-contributions',
                    'bulk_success',
                    sprintf(__('%d %s contribution(s) marked as paid.', 'pmpro-nbstup'), $count, $action_label),
                    'success'
                );
            }
        }
    }
    
    // Handle individual action
    if (isset($_GET['action']) && isset($_GET['user_id']) && isset($_GET['_wpnonce'])) {
        $action = sanitize_text_field($_GET['action']);
        $user_id = intval($_GET['user_id']);
        
        if (wp_verify_nonce($_GET['_wpnonce'], 'contribution_action_' . $user_id)) {
            if ($action === 'mark_deceased_paid') {
                update_user_meta($user_id, 'pmpronbstup_contribution_deceased_paid', 1);
                pmpronbstup_send_contribution_confirmation_email($user_id, 'deceased');
                add_settings_error(
                    'pmpro-nbstup-contributions',
                    'action_success',
                    __('Deceased contribution marked as paid.', 'pmpro-nbstup'),
                    'success'
                );
            } elseif ($action === 'mark_wedding_paid') {
                update_user_meta($user_id, 'pmpronbstup_contribution_wedding_paid', 1);
                pmpronbstup_send_contribution_confirmation_email($user_id, 'wedding');
                add_settings_error(
                    'pmpro-nbstup-contributions',
                    'action_success',
                    __('Wedding contribution marked as paid.', 'pmpro-nbstup'),
                    'success'
                );
            } elseif ($action === 'mark_deceased_unpaid') {
                update_user_meta($user_id, 'pmpronbstup_contribution_deceased_paid', 0);
                add_settings_error(
                    'pmpro-nbstup-contributions',
                    'action_success',
                    __('Deceased contribution marked as unpaid.', 'pmpro-nbstup'),
                    'success'
                );
            } elseif ($action === 'mark_wedding_unpaid') {
                update_user_meta($user_id, 'pmpronbstup_contribution_wedding_paid', 0);
                add_settings_error(
                    'pmpro-nbstup-contributions',
                    'action_success',
                    __('Wedding contribution marked as unpaid.', 'pmpro-nbstup'),
                    'success'
                );
            }
            
            wp_redirect(remove_query_arg(array('action', 'user_id', '_wpnonce')));
            exit;
        }
    }
}
add_action('admin_init', 'pmpronbstup_handle_contribution_actions');

/**
 * Render contributions management page
 */
function pmpronbstup_render_contributions_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pmpro-nbstup'));
    }

    settings_errors('pmpro-nbstup-contributions');

    // Get filter parameters
    $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : 'all';
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Build user query
    $meta_query = array('relation' => 'OR');
    
    if ($filter_type === 'deceased' || $filter_type === 'all') {
        $meta_query[] = array(
            'key' => 'pmpronbstup_contribution_deceased_required',
            'value' => '1',
            'compare' => '='
        );
    }
    
    if ($filter_type === 'wedding' || $filter_type === 'all') {
        $meta_query[] = array(
            'key' => 'pmpronbstup_contribution_wedding_required',
            'value' => '1',
            'compare' => '='
        );
    }

    $args = array(
        'role' => 'subscriber',
        'meta_query' => $meta_query,
        'orderby' => 'display_name',
        'order' => 'ASC',
    );

    if (!empty($search)) {
        $args['search'] = '*' . $search . '*';
        $args['search_columns'] = array('user_login', 'user_email', 'display_name');
    }

    $users = get_users($args);

    // Filter by status
    if ($filter_status !== 'all') {
        $users = array_filter($users, function($user) use ($filter_status, $filter_type) {
            if ($filter_type === 'deceased' || $filter_type === 'all') {
                $deceased_required = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_required', true);
                $deceased_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_paid', true);
                if ((int)$deceased_required === 1) {
                    if ($filter_status === 'paid' && (int)$deceased_paid === 1) return true;
                    if ($filter_status === 'unpaid' && (int)$deceased_paid !== 1) return true;
                }
            }
            
            if ($filter_type === 'wedding' || $filter_type === 'all') {
                $wedding_required = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_required', true);
                $wedding_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_paid', true);
                if ((int)$wedding_required === 1) {
                    if ($filter_status === 'paid' && (int)$wedding_paid === 1) return true;
                    if ($filter_status === 'unpaid' && (int)$wedding_paid !== 1) return true;
                }
            }
            
            return false;
        });
    }

    // Calculate statistics
    $stats = array(
        'total' => count($users),
        'deceased_required' => 0,
        'deceased_paid' => 0,
        'deceased_unpaid' => 0,
        'wedding_required' => 0,
        'wedding_paid' => 0,
        'wedding_unpaid' => 0,
    );

    foreach ($users as $user) {
        $deceased_required = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_required', true);
        $deceased_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_paid', true);
        $wedding_required = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_required', true);
        $wedding_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_paid', true);

        if ((int)$deceased_required === 1) {
            $stats['deceased_required']++;
            if ((int)$deceased_paid === 1) {
                $stats['deceased_paid']++;
            } else {
                $stats['deceased_unpaid']++;
            }
        }

        if ((int)$wedding_required === 1) {
            $stats['wedding_required']++;
            if ((int)$wedding_paid === 1) {
                $stats['wedding_paid']++;
            } else {
                $stats['wedding_unpaid']++;
            }
        }
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Contributions Management', 'pmpro-nbstup'); ?></h1>

        <!-- Statistics Dashboard -->
        <div class="pmpro-nbstup-contrib-stats">
            <div class="pmpro-nbstup-contrib-stat is-primary">
                <div class="pmpro-nbstup-contrib-stat-value is-primary"><?php echo esc_html($stats['total']); ?></div>
                <div class="pmpro-nbstup-contrib-stat-label"><?php esc_html_e('Total Members', 'pmpro-nbstup'); ?></div>
            </div>
            <div class="pmpro-nbstup-contrib-stat is-danger">
                <div class="pmpro-nbstup-contrib-stat-value is-danger"><?php echo esc_html($stats['deceased_required']); ?></div>
                <div class="pmpro-nbstup-contrib-stat-label"><?php esc_html_e('Deceased Contributions', 'pmpro-nbstup'); ?></div>
                <small><?php printf(__('%d paid, %d unpaid', 'pmpro-nbstup'), $stats['deceased_paid'], $stats['deceased_unpaid']); ?></small>
            </div>
            <div class="pmpro-nbstup-contrib-stat is-success">
                <div class="pmpro-nbstup-contrib-stat-value is-success"><?php echo esc_html($stats['wedding_required']); ?></div>
                <div class="pmpro-nbstup-contrib-stat-label"><?php esc_html_e('Wedding Contributions', 'pmpro-nbstup'); ?></div>
                <small><?php printf(__('%d paid, %d unpaid', 'pmpro-nbstup'), $stats['wedding_paid'], $stats['wedding_unpaid']); ?></small>
            </div>
        </div>

        <!-- Filters and Search -->
        <form method="get" class="pmpro-nbstup-contrib-filters">
            <input type="hidden" name="page" value="pmpro-nbstup-contributions" />
            
            <div class="pmpro-nbstup-contrib-filters-row">
                <select name="filter_type" class="pmpro-nbstup-contrib-filter-select">
                    <option value="all" <?php selected($filter_type, 'all'); ?>><?php esc_html_e('All Types', 'pmpro-nbstup'); ?></option>
                    <option value="deceased" <?php selected($filter_type, 'deceased'); ?>><?php esc_html_e('Deceased Only', 'pmpro-nbstup'); ?></option>
                    <option value="wedding" <?php selected($filter_type, 'wedding'); ?>><?php esc_html_e('Wedding Only', 'pmpro-nbstup'); ?></option>
                </select>

                <select name="filter_status" class="pmpro-nbstup-contrib-filter-select">
                    <option value="all" <?php selected($filter_status, 'all'); ?>><?php esc_html_e('All Statuses', 'pmpro-nbstup'); ?></option>
                    <option value="paid" <?php selected($filter_status, 'paid'); ?>><?php esc_html_e('Paid', 'pmpro-nbstup'); ?></option>
                    <option value="unpaid" <?php selected($filter_status, 'unpaid'); ?>><?php esc_html_e('Unpaid', 'pmpro-nbstup'); ?></option>
                </select>

                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" 
                       placeholder="<?php esc_attr_e('Search users...', 'pmpro-nbstup'); ?>" 
                       class="pmpro-nbstup-contrib-filter-search" />

                <button type="submit" class="button"><?php esc_html_e('Filter', 'pmpro-nbstup'); ?></button>
                <a href="?page=pmpro-nbstup-contributions" class="button"><?php esc_html_e('Reset', 'pmpro-nbstup'); ?></a>
            </div>
        </form>

        <?php if (empty($users)) : ?>
            <div class="notice notice-info">
                <p><?php esc_html_e('No members with contribution requirements found.', 'pmpro-nbstup'); ?></p>
            </div>
        <?php else : ?>
            <!-- Bulk Actions Form -->
            <form method="post">
                <?php wp_nonce_field('pmpronbstup_bulk_action', 'pmpronbstup_bulk_action_nonce'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action">
                            <option value=""><?php esc_html_e('Bulk Actions', 'pmpro-nbstup'); ?></option>
                            <option value="mark_deceased_paid"><?php esc_html_e('Mark Deceased as Paid', 'pmpro-nbstup'); ?></option>
                            <option value="mark_wedding_paid"><?php esc_html_e('Mark Wedding as Paid', 'pmpro-nbstup'); ?></option>
                        </select>
                        <button type="submit" class="button action"><?php esc_html_e('Apply', 'pmpro-nbstup'); ?></button>
                    </div>
                </div>

                <!-- Contributions Table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column"><input type="checkbox" id="select-all" /></td>
                            <th><?php esc_html_e('User', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Email', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Deceased Contribution', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Wedding Contribution', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Actions', 'pmpro-nbstup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user) :
                            $deceased_required = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_required', true);
                            $deceased_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_paid', true);
                            $deceased_deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_deceased_deadline', true);
                            
                            $wedding_required = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_required', true);
                            $wedding_paid = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_paid', true);
                            $wedding_deadline = get_user_meta($user->ID, 'pmpronbstup_contribution_wedding_deadline', true);
                            
                            $has_contribution = ((int)$deceased_required === 1) || ((int)$wedding_required === 1);
                            
                            if (!$has_contribution) continue;
                        ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="user_ids[]" value="<?php echo esc_attr($user->ID); ?>" />
                                </th>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user->ID)); ?>">
                                            <?php echo esc_html($user->display_name); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <?php if ((int)$deceased_required === 1) : ?>
                                        <?php if ((int)$deceased_paid === 1) : ?>
                                            <span class="dashicons dashicons-yes-alt pmpro-nbstup-status-icon pmpro-nbstup-status-paid"></span>
                                            <strong class="pmpro-nbstup-status-paid"><?php esc_html_e('Paid', 'pmpro-nbstup'); ?></strong>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-warning pmpro-nbstup-status-icon pmpro-nbstup-status-unpaid"></span>
                                            <strong class="pmpro-nbstup-status-unpaid"><?php esc_html_e('Unpaid', 'pmpro-nbstup'); ?></strong>
                                            <?php if ($deceased_deadline) : ?>
                                                <br><small><?php printf(__('Deadline: %s', 'pmpro-nbstup'), esc_html($deceased_deadline)); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="pmpro-nbstup-status-empty">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$wedding_required === 1) : ?>
                                        <?php if ((int)$wedding_paid === 1) : ?>
                                            <span class="dashicons dashicons-yes-alt pmpro-nbstup-status-icon pmpro-nbstup-status-paid"></span>
                                            <strong class="pmpro-nbstup-status-paid"><?php esc_html_e('Paid', 'pmpro-nbstup'); ?></strong>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-warning pmpro-nbstup-status-icon pmpro-nbstup-status-unpaid"></span>
                                            <strong class="pmpro-nbstup-status-unpaid"><?php esc_html_e('Unpaid', 'pmpro-nbstup'); ?></strong>
                                            <?php if ($wedding_deadline) : ?>
                                                <br><small><?php printf(__('Deadline: %s', 'pmpro-nbstup'), esc_html($wedding_deadline)); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="pmpro-nbstup-status-empty">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$deceased_required === 1 && (int)$deceased_paid !== 1) : ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(
                                            add_query_arg(array('action' => 'mark_deceased_paid', 'user_id' => $user->ID)),
                                            'contribution_action_' . $user->ID
                                        )); ?>" class="button button-small">
                                            <?php esc_html_e('Mark Deceased Paid', 'pmpro-nbstup'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ((int)$wedding_required === 1 && (int)$wedding_paid !== 1) : ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(
                                            add_query_arg(array('action' => 'mark_wedding_paid', 'user_id' => $user->ID)),
                                            'contribution_action_' . $user->ID
                                        )); ?>" class="button button-small">
                                            <?php esc_html_e('Mark Wedding Paid', 'pmpro-nbstup'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $user->ID)); ?>" class="button button-small">
                                        <?php esc_html_e('Edit User', 'pmpro-nbstup'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

        <?php endif; ?>

        <div class="pmpro-nbstup-contrib-quick-actions">
            <h3><?php esc_html_e('Quick Actions', 'pmpro-nbstup'); ?></h3>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=pmpro-nbstup-user-approval&tab=contribution_deceased')); ?>" class="button">
                    <?php esc_html_e('Upload Deceased CSV', 'pmpro-nbstup'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=pmpro-nbstup-user-approval&tab=contribution_wedding')); ?>" class="button">
                    <?php esc_html_e('Upload Wedding CSV', 'pmpro-nbstup'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=pmpro-nbstup-email-settings')); ?>" class="button">
                    <?php esc_html_e('Email Settings', 'pmpro-nbstup'); ?>
                </a>
            </p>
        </div>
    </div>
    <?php
}
