<?php

/**
 * Admin menu and pages for PMPro NBSTUP Addon
 *
 * @package PMProNBSTUP
 * @subpackage Admin
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu page under Paid Memberships Pro.
 */
function pmpronbstup_admin_menu()
{
    $capability  = 'manage_options';
    $parent_slug = 'pmpro-dashboard'; // PMPro main menu slug

    // Always register the submenu in admin.
    add_submenu_page(
        $parent_slug,
        __('User Approval', 'pmpro-nbstup'), // Page title
        __('User Approval', 'pmpro-nbstup'), // Menu title
        $capability,
        'pmpro-nbstup-user-approval',        // Page slug
        'pmpronbstup_render_admin_page'      // Callback
    );
}
add_action('admin_menu', 'pmpronbstup_admin_menu', 20);

/**
 * Render admin page with CSV upload form
 */
function pmpronbstup_render_admin_page()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pmpro-nbstup'));
    }

    settings_errors('pmpro-nbstup');

    // Get current tab
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'user_activation';
?>
    <div class="wrap">
        <h1><?php esc_html_e('User Approval', 'pmpro-nbstup'); ?></h1>

        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper">
            <a href="?page=pmpro-nbstup-user-approval&tab=user_activation" class="nav-tab <?php echo $tab === 'user_activation' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('User Activation', 'pmpro-nbstup'); ?>
            </a>
            <a href="?page=pmpro-nbstup-user-approval&tab=contribution_verification" class="nav-tab <?php echo $tab === 'contribution_verification' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Contribution Verification', 'pmpro-nbstup'); ?>
            </a>
        </nav>

        <!-- Tab Content -->
        <div class="tab-content">
            <?php if ($tab === 'contribution_verification') : ?>
                <?php pmpronbstup_render_contribution_csv_form(); ?>
            <?php else : ?>
                <?php pmpronbstup_render_user_activation_csv_form(); ?>
            <?php endif; ?>
        </div>
    </div>
<?php
}

/**
 * Render user activation CSV form
 */
function pmpronbstup_render_user_activation_csv_form()
{
?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('pmpronbstup_csv_import', 'pmpronbstup_csv_nonce'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="pmpronbstup_csv_file"><?php esc_html_e('CSV File', 'pmpro-nbstup'); ?></label>
                </th>
                <td>
                    <input type="file" name="pmpronbstup_csv_file" id="pmpronbstup_csv_file" accept=".csv" required />
                    <p class="description">
                        <?php esc_html_e('Upload bank statement CSV. It must contain a transaction ID column that matches subscriber bank transfer transaction IDs.', 'pmpro-nbstup'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Import and Activate Matching Subscribers', 'pmpro-nbstup')); ?>
    </form>

    <hr />
    <h2><?php esc_html_e('Deceased Member Flag', 'pmpro-nbstup'); ?></h2>
    <p>
        <?php esc_html_e('You can mark a member as deceased and set a date on the user profile screen. Deceased users will never be activated or allowed to log in. When a user is marked as deceased, all active users will be required to pay a contribution.', 'pmpro-nbstup'); ?>
    </p>
<?php
}

/**
 * Render contribution verification CSV form
 */
function pmpronbstup_render_contribution_csv_form()
{
?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('pmpronbstup_contribution_csv_import', 'pmpronbstup_contribution_csv_nonce'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="pmpronbstup_contribution_csv_file"><?php esc_html_e('Contribution CSV File', 'pmpro-nbstup'); ?></label>
                </th>
                <td>
                    <input type="file" name="pmpronbstup_contribution_csv_file" id="pmpronbstup_contribution_csv_file" accept=".csv" required />
                    <p class="description">
                        <?php esc_html_e('Upload CSV containing contribution transaction IDs. The file must have a transaction ID column that matches users who have paid their contribution.', 'pmpro-nbstup'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Verify and Update Contribution Payments', 'pmpro-nbstup')); ?>
    </form>

    <hr />
    <h2><?php esc_html_e('About Contribution Verification', 'pmpro-nbstup'); ?></h2>
    <p>
        <?php esc_html_e('When a member is marked as deceased, all active members are required to pay a contribution within 1 month. Users who do not pay by the deadline will be automatically deactivated. Use this form to verify contribution payments by uploading a CSV file with transaction IDs of users who have paid.', 'pmpro-nbstup'); ?>
    </p>
<?php
}
