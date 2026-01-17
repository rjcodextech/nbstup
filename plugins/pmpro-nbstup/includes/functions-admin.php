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
?>
    <div class="wrap">
        <h1><?php esc_html_e('User Approval', 'pmpro-nbstup'); ?></h1>

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
                            <?php esc_html_e('Upload bank statement CSV. It must contain transaction ID columns.', 'pmpro-nbstup'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Import and Activate Matching Subscribers', 'pmpro-nbstup')); ?>
        </form>

        <hr />
        <h2><?php esc_html_e('Deceased Member Flag', 'pmpro-nbstup'); ?></h2>
        <p>
            <?php esc_html_e('You can mark a member as deceased and set a date on the user profile screen. Deceased users will never be activated or allowed to log in.', 'pmpro-nbstup'); ?>
        </p>
    </div>
<?php
}
