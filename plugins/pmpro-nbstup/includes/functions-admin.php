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

    // Add Location Management submenu
    add_submenu_page(
        $parent_slug,
        __('Location Management', 'pmpro-nbstup'), // Page title
        __('Location Management', 'pmpro-nbstup'), // Menu title
        $capability,
        'pmpro-nbstup-location-management',         // Page slug
        'pmpronbstup_render_location_admin_page'    // Callback
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
            <a href="?page=pmpro-nbstup-user-approval&tab=contribution_deceased" class="nav-tab <?php echo $tab === 'contribution_deceased' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Deceased Contribution', 'pmpro-nbstup'); ?>
            </a>
            <a href="?page=pmpro-nbstup-user-approval&tab=contribution_wedding" class="nav-tab <?php echo $tab === 'contribution_wedding' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Wedding Contribution', 'pmpro-nbstup'); ?>
            </a>
        </nav>

        <!-- Tab Content -->
        <div class="tab-content">
            <?php if ($tab === 'contribution_deceased') : ?>
                <?php pmpronbstup_render_contribution_deceased_csv_form(); ?>
            <?php elseif ($tab === 'contribution_wedding') : ?>
                <?php pmpronbstup_render_contribution_wedding_csv_form(); ?>
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
 * Render contribution verification CSV form for deceased members
 */
function pmpronbstup_render_contribution_deceased_csv_form()
{
?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('pmpronbstup_contribution_deceased_csv_import', 'pmpronbstup_contribution_deceased_csv_nonce'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="pmpronbstup_contribution_deceased_csv_file"><?php esc_html_e('Deceased Contribution CSV File', 'pmpro-nbstup'); ?></label>
                </th>
                <td>
                    <input type="file" name="pmpronbstup_contribution_deceased_csv_file" id="pmpronbstup_contribution_deceased_csv_file" accept=".csv" required />
                    <p class="description">
                        <?php esc_html_e('Upload CSV containing deceased contribution transaction IDs. The file must have a transaction ID column that matches users who have paid their contribution.', 'pmpro-nbstup'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Verify and Update Deceased Contribution Payments', 'pmpro-nbstup')); ?>
    </form>

    <hr />
    <h2><?php esc_html_e('About Deceased Contribution Verification', 'pmpro-nbstup'); ?></h2>
    <p>
        <?php esc_html_e('When a member is marked as deceased, all active members are required to pay a contribution within 1 month. Users who do not pay by the deadline will be automatically deactivated. Use this form to verify contribution payments by uploading a CSV file with transaction IDs of users who have paid.', 'pmpro-nbstup'); ?>
    </p>
<?php
}

/**
 * Render contribution verification CSV form for wedding contributions
 */
function pmpronbstup_render_contribution_wedding_csv_form()
{
?>
    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('pmpronbstup_contribution_wedding_csv_import', 'pmpronbstup_contribution_wedding_csv_nonce'); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="pmpronbstup_contribution_wedding_csv_file"><?php esc_html_e('Wedding Contribution CSV File', 'pmpro-nbstup'); ?></label>
                </th>
                <td>
                    <input type="file" name="pmpronbstup_contribution_wedding_csv_file" id="pmpronbstup_contribution_wedding_csv_file" accept=".csv" required />
                    <p class="description">
                        <?php esc_html_e('Upload CSV containing wedding contribution transaction IDs. The file must have a transaction ID column that matches users who have paid their contribution.', 'pmpro-nbstup'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php submit_button(__('Verify and Update Wedding Contribution Payments', 'pmpro-nbstup')); ?>
    </form>

    <hr />
    <h2><?php esc_html_e('About Wedding Contribution Verification', 'pmpro-nbstup'); ?></h2>
    <p>
        <?php esc_html_e('When a member is marked for daughter wedding, all active members are requested to pay a contribution within 1 month. Users who do not pay by the deadline will be automatically deactivated. Use this form to verify contribution payments by uploading a CSV file with transaction IDs of users who have paid.', 'pmpro-nbstup'); ?>
    </p>
<?php
}

/**
 * Render Location Management admin page
 */
function pmpronbstup_render_location_admin_page()
{
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pmpro-nbstup'));
    }

    // Handle form submissions
    pmpronbstup_handle_location_forms();

    settings_errors('pmpro-nbstup-location');

    // Get current tab
    $tab = isset($_GET['loc_tab']) ? sanitize_text_field($_GET['loc_tab']) : 'states';
?>
    <div class="wrap">
        <h1><?php esc_html_e('Location Management', 'pmpro-nbstup'); ?></h1>

        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper">
            <a href="?page=pmpro-nbstup-location-management&loc_tab=states" class="nav-tab <?php echo $tab === 'states' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('States', 'pmpro-nbstup'); ?>
            </a>
            <a href="?page=pmpro-nbstup-location-management&loc_tab=districts" class="nav-tab <?php echo $tab === 'districts' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Districts', 'pmpro-nbstup'); ?>
            </a>
            <a href="?page=pmpro-nbstup-location-management&loc_tab=blocks" class="nav-tab <?php echo $tab === 'blocks' ? 'nav-tab-active' : ''; ?>">
                <?php esc_html_e('Blocks', 'pmpro-nbstup'); ?>
            </a>
        </nav>

        <!-- Tab Content -->
        <div class="tab-content pmpro-nbstup-tab-content">
            <?php if ($tab === 'districts') : ?>
                <?php pmpronbstup_render_districts_tab(); ?>
            <?php elseif ($tab === 'blocks') : ?>
                <?php pmpronbstup_render_blocks_tab(); ?>
            <?php else : ?>
                <?php pmpronbstup_render_states_tab(); ?>
            <?php endif; ?>
        </div>
    </div>
<?php
}

/**
 * Render States tab
 */
function pmpronbstup_render_states_tab()
{
    $states = pmpro_nbstup_get_all_states();
    $edit_state = null;

    if (isset($_GET['edit_state'])) {
        $edit_state = pmpro_nbstup_get_state(intval($_GET['edit_state']));
    }
?>
    <div class="pmpro-nbstup-admin-split">
        <!-- Add/Edit Form -->
        <div class="pmpro-nbstup-admin-col-fixed">
            <h2><?php echo $edit_state ? esc_html__('Edit State', 'pmpro-nbstup') : esc_html__('Add New State', 'pmpro-nbstup'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('pmpronbstup_state_action', 'pmpronbstup_state_nonce'); ?>
                <?php if ($edit_state) : ?>
                    <input type="hidden" name="state_id" value="<?php echo esc_attr($edit_state->id); ?>" />
                    <input type="hidden" name="action" value="update_state" />
                <?php else : ?>
                    <input type="hidden" name="action" value="add_state" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="state_name"><?php esc_html_e('State Name', 'pmpro-nbstup'); ?></label></th>
                        <td>
                            <input type="text" id="state_name" name="state_name" class="regular-text" 
                                   value="<?php echo $edit_state ? esc_attr($edit_state->name) : ''; ?>" required />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($edit_state ? __('Update State', 'pmpro-nbstup') : __('Add State', 'pmpro-nbstup')); ?>
                
                <?php if ($edit_state) : ?>
                    <a href="?page=pmpro-nbstup-location-management&loc_tab=states" class="button">
                        <?php esc_html_e('Cancel', 'pmpro-nbstup'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- States List -->
        <div class="pmpro-nbstup-admin-col-fluid">
            <h2><?php esc_html_e('All States', 'pmpro-nbstup'); ?></h2>
            <?php if (empty($states)) : ?>
                <p><?php esc_html_e('No states found. Add your first state.', 'pmpro-nbstup'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Name', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Actions', 'pmpro-nbstup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($states as $state) : ?>
                            <tr>
                                <td><?php echo esc_html($state->id); ?></td>
                                <td><?php echo esc_html($state->name); ?></td>
                                <td>
                                    <a href="?page=pmpro-nbstup-location-management&loc_tab=states&edit_state=<?php echo esc_attr($state->id); ?>">
                                        <?php esc_html_e('Edit', 'pmpro-nbstup'); ?>
                                    </a>
                                    |
                                    <a href="?page=pmpro-nbstup-location-management&loc_tab=states&delete_state=<?php echo esc_attr($state->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_state_' . $state->id); ?>" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure? This will delete all districts and blocks under this state.', 'pmpro-nbstup'); ?>');">
                                        <?php esc_html_e('Delete', 'pmpro-nbstup'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php
}

/**
 * Render Districts tab
 */
function pmpronbstup_render_districts_tab()
{
    $states = pmpro_nbstup_get_all_states();
    $districts = pmpro_nbstup_get_districts();
    $edit_district = null;

    if (isset($_GET['edit_district'])) {
        $edit_district = pmpro_nbstup_get_district(intval($_GET['edit_district']));
    }
?>
    <div class="pmpro-nbstup-admin-split">
        <!-- Add/Edit Form -->
        <div class="pmpro-nbstup-admin-col-fixed">
            <h2><?php echo $edit_district ? esc_html__('Edit District', 'pmpro-nbstup') : esc_html__('Add New District', 'pmpro-nbstup'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('pmpronbstup_district_action', 'pmpronbstup_district_nonce'); ?>
                <?php if ($edit_district) : ?>
                    <input type="hidden" name="district_id" value="<?php echo esc_attr($edit_district->id); ?>" />
                    <input type="hidden" name="action" value="update_district" />
                <?php else : ?>
                    <input type="hidden" name="action" value="add_district" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="district_state"><?php esc_html_e('State', 'pmpro-nbstup'); ?></label></th>
                        <td>
                            <select id="district_state" name="district_state" required>
                                <option value=""><?php esc_html_e('Select State', 'pmpro-nbstup'); ?></option>
                                <?php foreach ($states as $state) : ?>
                                    <option value="<?php echo esc_attr($state->id); ?>" 
                                            <?php echo ($edit_district && $edit_district->state_id == $state->id) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($state->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="district_name"><?php esc_html_e('District Name', 'pmpro-nbstup'); ?></label></th>
                        <td>
                            <input type="text" id="district_name" name="district_name" class="regular-text" 
                                   value="<?php echo $edit_district ? esc_attr($edit_district->name) : ''; ?>" required />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($edit_district ? __('Update District', 'pmpro-nbstup') : __('Add District', 'pmpro-nbstup')); ?>
                
                <?php if ($edit_district) : ?>
                    <a href="?page=pmpro-nbstup-location-management&loc_tab=districts" class="button">
                        <?php esc_html_e('Cancel', 'pmpro-nbstup'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Districts List -->
        <div class="pmpro-nbstup-admin-col-fluid">
            <h2><?php esc_html_e('All Districts', 'pmpro-nbstup'); ?></h2>
            <?php if (empty($districts)) : ?>
                <p><?php esc_html_e('No districts found. Add your first district.', 'pmpro-nbstup'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('District Name', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('State', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Actions', 'pmpro-nbstup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($districts as $district) : ?>
                            <tr>
                                <td><?php echo esc_html($district->id); ?></td>
                                <td><?php echo esc_html($district->name); ?></td>
                                <td><?php echo esc_html(pmpro_nbstup_get_state_name($district->state_id)); ?></td>
                                <td>
                                    <a href="?page=pmpro-nbstup-location-management&loc_tab=districts&edit_district=<?php echo esc_attr($district->id); ?>">
                                        <?php esc_html_e('Edit', 'pmpro-nbstup'); ?>
                                    </a>
                                    |
                                    <a href="?page=pmpro-nbstup-location-management&loc_tab=districts&delete_district=<?php echo esc_attr($district->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_district_' . $district->id); ?>" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure? This will delete all blocks under this district.', 'pmpro-nbstup'); ?>');">
                                        <?php esc_html_e('Delete', 'pmpro-nbstup'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php
}

/**
 * Render Blocks tab
 */
function pmpronbstup_render_blocks_tab()
{
    $districts = pmpro_nbstup_get_districts();
    $blocks = pmpro_nbstup_get_blocks();
    $edit_block = null;

    if (isset($_GET['edit_block'])) {
        $edit_block = pmpro_nbstup_get_block(intval($_GET['edit_block']));
    }
?>
    <div class="pmpro-nbstup-admin-split">
        <!-- Add/Edit Form -->
        <div class="pmpro-nbstup-admin-col-fixed">
            <h2><?php echo $edit_block ? esc_html__('Edit Block', 'pmpro-nbstup') : esc_html__('Add New Block', 'pmpro-nbstup'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('pmpronbstup_block_action', 'pmpronbstup_block_nonce'); ?>
                <?php if ($edit_block) : ?>
                    <input type="hidden" name="block_id" value="<?php echo esc_attr($edit_block->id); ?>" />
                    <input type="hidden" name="action" value="update_block" />
                <?php else : ?>
                    <input type="hidden" name="action" value="add_block" />
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="block_district"><?php esc_html_e('District', 'pmpro-nbstup'); ?></label></th>
                        <td>
                            <select id="block_district" name="block_district" required>
                                <option value=""><?php esc_html_e('Select District', 'pmpro-nbstup'); ?></option>
                                <?php foreach ($districts as $district) : ?>
                                    <option value="<?php echo esc_attr($district->id); ?>" 
                                            <?php echo ($edit_block && $edit_block->district_id == $district->id) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($district->name); ?> (<?php echo esc_html(pmpro_nbstup_get_state_name($district->state_id)); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="block_name"><?php esc_html_e('Block Name', 'pmpro-nbstup'); ?></label></th>
                        <td>
                            <input type="text" id="block_name" name="block_name" class="regular-text" 
                                   value="<?php echo $edit_block ? esc_attr($edit_block->name) : ''; ?>" required />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button($edit_block ? __('Update Block', 'pmpro-nbstup') : __('Add Block', 'pmpro-nbstup')); ?>
                
                <?php if ($edit_block) : ?>
                    <a href="?page=pmpro-nbstup-location-management&loc_tab=blocks" class="button">
                        <?php esc_html_e('Cancel', 'pmpro-nbstup'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Blocks List -->
        <div class="pmpro-nbstup-admin-col-fluid">
            <h2><?php esc_html_e('All Blocks', 'pmpro-nbstup'); ?></h2>
            <?php if (empty($blocks)) : ?>
                <p><?php esc_html_e('No blocks found. Add your first block.', 'pmpro-nbstup'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Block Name', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('District', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('State', 'pmpro-nbstup'); ?></th>
                            <th><?php esc_html_e('Actions', 'pmpro-nbstup'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($blocks as $block) : ?>
                            <?php 
                            $district = pmpro_nbstup_get_district($block->district_id);
                            $state_id = $district ? $district->state_id : 0;
                            ?>
                            <tr>
                                <td><?php echo esc_html($block->id); ?></td>
                                <td><?php echo esc_html($block->name); ?></td>
                                <td><?php echo esc_html(pmpro_nbstup_get_district_name($block->district_id)); ?></td>
                                <td><?php echo esc_html(pmpro_nbstup_get_state_name($state_id)); ?></td>
                                <td>
                                    <a href="?page=pmpro-nbstup-location-management&loc_tab=blocks&edit_block=<?php echo esc_attr($block->id); ?>">
                                        <?php esc_html_e('Edit', 'pmpro-nbstup'); ?>
                                    </a>
                                    |
                                    <a href="?page=pmpro-nbstup-location-management&loc_tab=blocks&delete_block=<?php echo esc_attr($block->id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_block_' . $block->id); ?>" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure?', 'pmpro-nbstup'); ?>');">
                                        <?php esc_html_e('Delete', 'pmpro-nbstup'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php
}

/**
 * Handle location form submissions
 */
function pmpronbstup_handle_location_forms()
{
    // Handle State actions
    if (isset($_POST['action']) && in_array($_POST['action'], ['add_state', 'update_state'])) {
        if (!isset($_POST['pmpronbstup_state_nonce']) || !wp_verify_nonce($_POST['pmpronbstup_state_nonce'], 'pmpronbstup_state_action')) {
            return;
        }

        $state_name = isset($_POST['state_name']) ? sanitize_text_field($_POST['state_name']) : '';
        
        if (empty($state_name)) {
            add_settings_error('pmpro-nbstup-location', 'empty_name', __('State name is required.', 'pmpro-nbstup'), 'error');
            return;
        }

        if ($_POST['action'] === 'add_state') {
            if (pmpro_nbstup_add_state($state_name)) {
                add_settings_error('pmpro-nbstup-location', 'state_added', __('State added successfully.', 'pmpro-nbstup'), 'success');
            } else {
                add_settings_error('pmpro-nbstup-location', 'state_add_failed', __('Failed to add state.', 'pmpro-nbstup'), 'error');
            }
        } elseif ($_POST['action'] === 'update_state') {
            $state_id = isset($_POST['state_id']) ? intval($_POST['state_id']) : 0;
            if (pmpro_nbstup_update_state($state_id, $state_name)) {
                add_settings_error('pmpro-nbstup-location', 'state_updated', __('State updated successfully.', 'pmpro-nbstup'), 'success');
                wp_redirect(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=states'));
                exit;
            } else {
                add_settings_error('pmpro-nbstup-location', 'state_update_failed', __('Failed to update state.', 'pmpro-nbstup'), 'error');
            }
        }
    }

    // Handle District actions
    if (isset($_POST['action']) && in_array($_POST['action'], ['add_district', 'update_district'])) {
        if (!isset($_POST['pmpronbstup_district_nonce']) || !wp_verify_nonce($_POST['pmpronbstup_district_nonce'], 'pmpronbstup_district_action')) {
            return;
        }

        $district_name = isset($_POST['district_name']) ? sanitize_text_field($_POST['district_name']) : '';
        $state_id = isset($_POST['district_state']) ? intval($_POST['district_state']) : 0;
        
        if (empty($district_name) || empty($state_id)) {
            add_settings_error('pmpro-nbstup-location', 'empty_name', __('District name and state are required.', 'pmpro-nbstup'), 'error');
            return;
        }

        if ($_POST['action'] === 'add_district') {
            if (pmpro_nbstup_add_district($state_id, $district_name)) {
                add_settings_error('pmpro-nbstup-location', 'district_added', __('District added successfully.', 'pmpro-nbstup'), 'success');
            } else {
                add_settings_error('pmpro-nbstup-location', 'district_add_failed', __('Failed to add district.', 'pmpro-nbstup'), 'error');
            }
        } elseif ($_POST['action'] === 'update_district') {
            $district_id = isset($_POST['district_id']) ? intval($_POST['district_id']) : 0;
            if (pmpro_nbstup_update_district($district_id, $state_id, $district_name)) {
                add_settings_error('pmpro-nbstup-location', 'district_updated', __('District updated successfully.', 'pmpro-nbstup'), 'success');
                wp_redirect(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=districts'));
                exit;
            } else {
                add_settings_error('pmpro-nbstup-location', 'district_update_failed', __('Failed to update district.', 'pmpro-nbstup'), 'error');
            }
        }
    }

    // Handle Block actions
    if (isset($_POST['action']) && in_array($_POST['action'], ['add_block', 'update_block'])) {
        if (!isset($_POST['pmpronbstup_block_nonce']) || !wp_verify_nonce($_POST['pmpronbstup_block_nonce'], 'pmpronbstup_block_action')) {
            return;
        }

        $block_name = isset($_POST['block_name']) ? sanitize_text_field($_POST['block_name']) : '';
        $district_id = isset($_POST['block_district']) ? intval($_POST['block_district']) : 0;
        
        if (empty($block_name) || empty($district_id)) {
            add_settings_error('pmpro-nbstup-location', 'empty_name', __('Block name and district are required.', 'pmpro-nbstup'), 'error');
            return;
        }

        if ($_POST['action'] === 'add_block') {
            if (pmpro_nbstup_add_block($district_id, $block_name)) {
                add_settings_error('pmpro-nbstup-location', 'block_added', __('Block added successfully.', 'pmpro-nbstup'), 'success');
            } else {
                add_settings_error('pmpro-nbstup-location', 'block_add_failed', __('Failed to add block.', 'pmpro-nbstup'), 'error');
            }
        } elseif ($_POST['action'] === 'update_block') {
            $block_id = isset($_POST['block_id']) ? intval($_POST['block_id']) : 0;
            if (pmpro_nbstup_update_block($block_id, $district_id, $block_name)) {
                add_settings_error('pmpro-nbstup-location', 'block_updated', __('Block updated successfully.', 'pmpro-nbstup'), 'success');
                wp_redirect(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=blocks'));
                exit;
            } else {
                add_settings_error('pmpro-nbstup-location', 'block_update_failed', __('Failed to update block.', 'pmpro-nbstup'), 'error');
            }
        }
    }

    // Handle deletions
    if (isset($_GET['delete_state']) && isset($_GET['_wpnonce'])) {
        $state_id = intval($_GET['delete_state']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_state_' . $state_id)) {
            if (pmpro_nbstup_delete_state($state_id)) {
                add_settings_error('pmpro-nbstup-location', 'state_deleted', __('State deleted successfully.', 'pmpro-nbstup'), 'success');
            }
            wp_redirect(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=states'));
            exit;
        }
    }

    if (isset($_GET['delete_district']) && isset($_GET['_wpnonce'])) {
        $district_id = intval($_GET['delete_district']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_district_' . $district_id)) {
            if (pmpro_nbstup_delete_district($district_id)) {
                add_settings_error('pmpro-nbstup-location', 'district_deleted', __('District deleted successfully.', 'pmpro-nbstup'), 'success');
            }
            wp_redirect(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=districts'));
            exit;
        }
    }

    if (isset($_GET['delete_block']) && isset($_GET['_wpnonce'])) {
        $block_id = intval($_GET['delete_block']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_block_' . $block_id)) {
            if (pmpro_nbstup_delete_block($block_id)) {
                add_settings_error('pmpro-nbstup-location', 'block_deleted', __('Block deleted successfully.', 'pmpro-nbstup'), 'success');
            }
            wp_redirect(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=blocks'));
            exit;
        }
    }
}

