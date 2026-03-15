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

if (! function_exists('pmpronbstup_user_activation_csv_enabled')) {
    function pmpronbstup_user_activation_csv_enabled()
    {
        /**
         * Filter whether user activation CSV import is enabled.
         *
         * @param bool $enabled Default false to disable activation from CSV.
         */
        return (bool) apply_filters('pmpronbstup_enable_user_activation_csv', false);
    }
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

    $user_activation_enabled = pmpronbstup_user_activation_csv_enabled();

    // Get current tab
    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : ($user_activation_enabled ? 'user_activation' : 'contribution_deceased');

    if (! $user_activation_enabled && $tab === 'user_activation') {
        $tab = 'contribution_deceased';
    }
?>
    <div class="wrap">
        <h1><?php esc_html_e('User Approval', 'pmpro-nbstup'); ?></h1>

        <!-- Tab Navigation -->
        <nav class="nav-tab-wrapper">
            <?php if ($user_activation_enabled) : ?>
                <a href="?page=pmpro-nbstup-user-approval&tab=user_activation" class="nav-tab <?php echo $tab === 'user_activation' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('User Activation', 'pmpro-nbstup'); ?>
                </a>
            <?php endif; ?>
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
                <?php if ($user_activation_enabled) : ?>
                    <?php pmpronbstup_render_user_activation_csv_form(); ?>
                <?php else : ?>
                    <?php pmpronbstup_render_contribution_deceased_csv_form(); ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <hr />
        <?php pmpronbstup_render_members_vcard_export_form(); ?>
    </div>
<?php
}

/**
 * Render admin notice for vCard export feedback.
 *
 * @return void
 */
function pmpronbstup_render_vcard_export_notice()
{
    if (! is_admin() || ! current_user_can('manage_options')) {
        return;
    }

    if (empty($_GET['page']) || empty($_GET['pmpronbstup_export_notice'])) {
        return;
    }

    $page = sanitize_text_field(wp_unslash($_GET['page']));
    if (! in_array($page, array('pmpro-nbstup-user-approval', 'pmpro-memberslist'), true)) {
        return;
    }

    $notice_code = sanitize_text_field(wp_unslash($_GET['pmpronbstup_export_notice']));
    $message = '';

    if ($notice_code === 'no_members') {
        $message = __('No members found for the selected registration date range.', 'pmpro-nbstup');
    } elseif ($notice_code === 'no_vcards') {
        $message = __('No members could be exported as vCard.', 'pmpro-nbstup');
    }

    if ($message === '') {
        return;
    }
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
}
add_action('admin_notices', 'pmpronbstup_render_vcard_export_notice');

/**
 * Add "Export to vCard" button on PMPro Members List page near "Export to CSV".
 *
 * @return void
 */
function pmpronbstup_add_memberslist_vcard_export_button()
{
    if (! is_admin() || ! current_user_can('manage_options')) {
        return;
    }

    if (empty($_GET['page']) || sanitize_text_field(wp_unslash($_GET['page'])) !== 'pmpro-memberslist') {
        return;
    }

    $registered_from = isset($_GET['pmpronbstup_registered_from']) ? sanitize_text_field(wp_unslash($_GET['pmpronbstup_registered_from'])) : '';
    $registered_to   = isset($_GET['pmpronbstup_registered_to']) ? sanitize_text_field(wp_unslash($_GET['pmpronbstup_registered_to'])) : '';
    $export_nonce    = wp_create_nonce('pmpronbstup_export_members_vcards');
    ?>
    <script>
        (function() {
            function addVcardExportModalButton() {
                var links = document.querySelectorAll('a.page-title-action, a.button');
                var exportCsvButton = null;

                for (var i = 0; i < links.length; i++) {
                    var text = (links[i].textContent || '').toLowerCase().trim();
                    if (text.indexOf('export to csv') !== -1) {
                        exportCsvButton = links[i];
                        break;
                    }
                }

                if (!exportCsvButton || document.getElementById('pmpronbstup-export-vcard-trigger')) {
                    return;
                }

                var trigger = document.createElement('a');
                trigger.id = 'pmpronbstup-export-vcard-trigger';
                trigger.href = '#';
                trigger.className = exportCsvButton.className;
                trigger.textContent = '<?php echo esc_js(__('Export to vCard', 'pmpro-nbstup')); ?>';
                trigger.style.marginLeft = '8px';

                var overlay = document.createElement('div');
                overlay.id = 'pmpronbstup-export-vcard-modal';
                overlay.style.position = 'fixed';
                overlay.style.inset = '0';
                overlay.style.background = 'rgba(0,0,0,0.45)';
                overlay.style.display = 'none';
                overlay.style.alignItems = 'center';
                overlay.style.justifyContent = 'center';
                overlay.style.zIndex = '100000';

                var modal = document.createElement('div');
                modal.style.background = '#fff';
                modal.style.padding = '20px';
                modal.style.width = '100%';
                modal.style.maxWidth = '420px';
                modal.style.borderRadius = '8px';
                modal.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';

                var heading = document.createElement('h3');
                heading.textContent = '<?php echo esc_js(__('Export Members vCard', 'pmpro-nbstup')); ?>';
                heading.style.marginTop = '0';
                heading.style.marginBottom = '14px';

                var form = document.createElement('form');
                form.method = 'get';
                form.action = '<?php echo esc_js(admin_url('admin.php')); ?>';

                function hiddenInput(name, value) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    return input;
                }

                function fieldWrap(labelText, inputEl) {
                    var wrap = document.createElement('div');
                    wrap.style.marginBottom = '12px';

                    var label = document.createElement('label');
                    label.textContent = labelText;
                    label.style.display = 'block';
                    label.style.fontWeight = '600';
                    label.style.marginBottom = '6px';

                    inputEl.style.width = '100%';
                    inputEl.style.boxSizing = 'border-box';

                    wrap.appendChild(label);
                    wrap.appendChild(inputEl);
                    return wrap;
                }

                var fromInput = document.createElement('input');
                fromInput.type = 'date';
                fromInput.name = 'pmpronbstup_registered_from';
                fromInput.value = '<?php echo esc_js($registered_from); ?>';

                var toInput = document.createElement('input');
                toInput.type = 'date';
                toInput.name = 'pmpronbstup_registered_to';
                toInput.value = '<?php echo esc_js($registered_to); ?>';

                var actions = document.createElement('div');
                actions.style.display = 'flex';
                actions.style.justifyContent = 'flex-end';
                actions.style.gap = '8px';
                actions.style.marginTop = '16px';

                var cancelButton = document.createElement('button');
                cancelButton.type = 'button';
                cancelButton.className = 'button';
                cancelButton.textContent = '<?php echo esc_js(__('Cancel', 'pmpro-nbstup')); ?>';

                var submitButton = document.createElement('button');
                submitButton.type = 'submit';
                submitButton.className = 'button button-primary';
                submitButton.textContent = '<?php echo esc_js(__('Export Data', 'pmpro-nbstup')); ?>';

                form.appendChild(hiddenInput('page', 'pmpro-memberslist'));
                form.appendChild(hiddenInput('pmpronbstup_export_vcards', '1'));
                form.appendChild(hiddenInput('pmpronbstup_export_vcards_nonce', '<?php echo esc_js($export_nonce); ?>'));
                form.appendChild(fieldWrap('<?php echo esc_js(__('Registered From', 'pmpro-nbstup')); ?>', fromInput));
                form.appendChild(fieldWrap('<?php echo esc_js(__('Registered To', 'pmpro-nbstup')); ?>', toInput));

                actions.appendChild(cancelButton);
                actions.appendChild(submitButton);
                form.appendChild(actions);

                modal.appendChild(heading);
                modal.appendChild(form);
                overlay.appendChild(modal);
                document.body.appendChild(overlay);

                function closeModal() {
                    overlay.style.display = 'none';
                }

                function onEscapeKey(event) {
                    if (event.key === 'Escape' && overlay.style.display === 'flex') {
                        closeModal();
                    }
                }

                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    overlay.style.display = 'flex';
                });

                cancelButton.addEventListener('click', closeModal);
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        closeModal();
                    }
                });
                document.addEventListener('keydown', onEscapeKey);

                exportCsvButton.insertAdjacentElement('afterend', trigger);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', addVcardExportModalButton);
            } else {
                addVcardExportModalButton();
            }
        })();
    </script>
    <?php
}
add_action('admin_footer', 'pmpronbstup_add_memberslist_vcard_export_button', 20);

/**
 * Render bulk members vCard export form with registration date filters.
 *
 * @return void
 */
function pmpronbstup_render_members_vcard_export_form()
{
    $registered_from = isset($_GET['pmpronbstup_registered_from']) ? sanitize_text_field(wp_unslash($_GET['pmpronbstup_registered_from'])) : '';
    $registered_to   = isset($_GET['pmpronbstup_registered_to']) ? sanitize_text_field(wp_unslash($_GET['pmpronbstup_registered_to'])) : '';
?>
    <h2><?php esc_html_e('Bulk Members vCard Export', 'pmpro-nbstup'); ?></h2>
    <p>
        <?php esc_html_e('Export all subscriber members as a single .vcf file. Optional registration date filters allow exporting only members registered within a selected duration.', 'pmpro-nbstup'); ?>
    </p>

    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="pmpro-nbstup-user-approval" />
        <input type="hidden" name="tab" value="<?php echo isset($_GET['tab']) ? esc_attr(sanitize_text_field(wp_unslash($_GET['tab']))) : ''; ?>" />
        <input type="hidden" name="pmpronbstup_export_vcards" value="1" />
        <?php wp_nonce_field('pmpronbstup_export_members_vcards', 'pmpronbstup_export_vcards_nonce'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="pmpronbstup_registered_from"><?php esc_html_e('Registered From', 'pmpro-nbstup'); ?></label>
                </th>
                <td>
                    <input type="date" id="pmpronbstup_registered_from" name="pmpronbstup_registered_from" value="<?php echo esc_attr($registered_from); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="pmpronbstup_registered_to"><?php esc_html_e('Registered To', 'pmpro-nbstup'); ?></label>
                </th>
                <td>
                    <input type="date" id="pmpronbstup_registered_to" name="pmpronbstup_registered_to" value="<?php echo esc_attr($registered_to); ?>" />
                    <p class="description"><?php esc_html_e('Leave both dates empty to export all members.', 'pmpro-nbstup'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Export Members vCard (.vcf)', 'pmpro-nbstup')); ?>
    </form>
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

    $import_state_id = isset($_GET['import_state_id']) ? intval($_GET['import_state_id']) : 0;
    if ($import_state_id <= 0 && !empty($states)) {
        $import_state_id = (int) $states[0]->id;
    }
?>
    <div class="pmpro-nbstup-contrib-filters">
        <h2><?php esc_html_e('Import Districts and Blocks from CSV', 'pmpro-nbstup'); ?></h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('pmpronbstup_location_import_action', 'pmpronbstup_location_import_nonce'); ?>
            <input type="hidden" name="action" value="import_district_block_csv" />

            <table class="form-table">
                <tr>
                    <th><label for="import_state_id"><?php esc_html_e('State', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <select id="import_state_id" name="import_state_id" required>
                            <option value=""><?php esc_html_e('Select State', 'pmpro-nbstup'); ?></option>
                            <?php foreach ($states as $state) : ?>
                                <option value="<?php echo esc_attr($state->id); ?>" <?php selected($import_state_id, (int) $state->id); ?>>
                                    <?php echo esc_html($state->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="pmpronbstup_location_csv_file"><?php esc_html_e('CSV File', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="file" name="pmpronbstup_location_csv_file" id="pmpronbstup_location_csv_file" accept=".csv,text/csv" required />
                        <p class="description"><?php esc_html_e('Upload CSV with columns: District (or Disctrict) and Block.', 'pmpro-nbstup'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Import CSV', 'pmpro-nbstup')); ?>
        </form>
    </div>

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
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    $is_location_page = isset($_REQUEST['page']) && $_REQUEST['page'] === 'pmpro-nbstup-location-management';
    if (!$is_location_page) {
        return;
    }

    // Handle District/Block CSV import.
    if (isset($_POST['action']) && $_POST['action'] === 'import_district_block_csv') {
        if (!isset($_POST['pmpronbstup_location_import_nonce']) || !wp_verify_nonce($_POST['pmpronbstup_location_import_nonce'], 'pmpronbstup_location_import_action')) {
            return;
        }

        $state_id = isset($_POST['import_state_id']) ? intval($_POST['import_state_id']) : 0;
        if ($state_id <= 0 || !pmpro_nbstup_get_state($state_id)) {
            add_settings_error('pmpro-nbstup-location', 'import_invalid_state', __('Please select a valid state for import.', 'pmpro-nbstup'), 'error');
            return;
        }

        if (empty($_FILES['pmpronbstup_location_csv_file']['tmp_name']) || !is_uploaded_file($_FILES['pmpronbstup_location_csv_file']['tmp_name'])) {
            add_settings_error('pmpro-nbstup-location', 'import_missing_file', __('Please upload a valid CSV file.', 'pmpro-nbstup'), 'error');
            return;
        }

        $result = pmpronbstup_import_district_block_csv($_FILES['pmpronbstup_location_csv_file']['tmp_name'], $state_id);
        if (is_wp_error($result)) {
            add_settings_error('pmpro-nbstup-location', 'import_failed', $result->get_error_message(), 'error');
            return;
        }

        add_settings_error(
            'pmpro-nbstup-location',
            'import_success',
            sprintf(
                __('Import complete. Rows: %1$d, Districts inserted: %2$d, Districts existing: %3$d, Blocks inserted: %4$d, Blocks existing: %5$d, Skipped: %6$d.', 'pmpro-nbstup'),
                (int) $result['rows'],
                (int) $result['district_inserted'],
                (int) $result['district_existing'],
                (int) $result['block_inserted'],
                (int) $result['block_existing'],
                (int) $result['skipped']
            ),
            'success'
        );

        pmpronbstup_location_redirect_with_messages(
            admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=districts&import_state_id=' . $state_id)
        );
    }

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
                pmpronbstup_location_redirect_with_messages(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=states'));
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
                pmpronbstup_location_redirect_with_messages(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=districts'));
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
                pmpronbstup_location_redirect_with_messages(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=blocks'));
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
            pmpronbstup_location_redirect_with_messages(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=states'));
        }
    }

    if (isset($_GET['delete_district']) && isset($_GET['_wpnonce'])) {
        $district_id = intval($_GET['delete_district']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_district_' . $district_id)) {
            if (pmpro_nbstup_delete_district($district_id)) {
                add_settings_error('pmpro-nbstup-location', 'district_deleted', __('District deleted successfully.', 'pmpro-nbstup'), 'success');
            }
            pmpronbstup_location_redirect_with_messages(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=districts'));
        }
    }

    if (isset($_GET['delete_block']) && isset($_GET['_wpnonce'])) {
        $block_id = intval($_GET['delete_block']);
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_block_' . $block_id)) {
            if (pmpro_nbstup_delete_block($block_id)) {
                add_settings_error('pmpro-nbstup-location', 'block_deleted', __('Block deleted successfully.', 'pmpro-nbstup'), 'success');
            }
            pmpronbstup_location_redirect_with_messages(admin_url('admin.php?page=pmpro-nbstup-location-management&loc_tab=blocks'));
        }
    }
}

/**
 * Persist location settings errors and redirect safely.
 *
 * @param string $url Redirect URL.
 * @return void
 */
function pmpronbstup_location_redirect_with_messages($url)
{
    set_transient('settings_errors', get_settings_errors(), 30);
    wp_safe_redirect($url);
    exit;
}

add_action('admin_init', 'pmpronbstup_handle_location_forms');

/**
 * Import District + Block CSV rows into location tables for a specific state.
 *
 * @param string $csv_path Uploaded CSV temporary file path.
 * @param int    $state_id Target state ID.
 * @return array|WP_Error
 */
function pmpronbstup_import_district_block_csv($csv_path, $state_id)
{
    global $wpdb;

    if (empty($csv_path) || !file_exists($csv_path)) {
        return new WP_Error('import_file_missing', __('CSV file is missing.', 'pmpro-nbstup'));
    }

    $districts_table = $wpdb->prefix . 'pmpro_nbstup_districts';
    $blocks_table = $wpdb->prefix . 'pmpro_nbstup_blocks';

    $handle = fopen($csv_path, 'r');
    if (!$handle) {
        return new WP_Error('import_file_open_failed', __('Could not open CSV file.', 'pmpro-nbstup'));
    }

    $header = fgetcsv($handle);
    if (!$header || !is_array($header)) {
        fclose($handle);
        return new WP_Error('import_header_missing', __('CSV header row is missing.', 'pmpro-nbstup'));
    }

    $normalized_headers = array_map(
        static function ($value) {
            $value = is_string($value) ? strtolower(trim($value)) : '';
            return preg_replace('/[^a-z]/', '', $value);
        },
        $header
    );

    $district_idx = false;
    $block_idx = false;
    foreach ($normalized_headers as $idx => $column_name) {
        if ($district_idx === false && in_array($column_name, array('district', 'disctrict'), true)) {
            $district_idx = $idx;
        }
        if ($block_idx === false && $column_name === 'block') {
            $block_idx = $idx;
        }
    }

    if ($district_idx === false || $block_idx === false) {
        fclose($handle);
        return new WP_Error('import_header_invalid', __('CSV must include District (or Disctrict) and Block columns.', 'pmpro-nbstup'));
    }

    $stats = array(
        'rows' => 0,
        'district_inserted' => 0,
        'district_existing' => 0,
        'block_inserted' => 0,
        'block_existing' => 0,
        'skipped' => 0,
    );

    $district_cache = array();

    while (($row = fgetcsv($handle)) !== false) {
        $stats['rows']++;

        $district_name = isset($row[$district_idx]) ? pmpro_nbstup_normalize_location_name((string) $row[$district_idx]) : '';
        $block_name = isset($row[$block_idx]) ? pmpro_nbstup_normalize_location_name((string) $row[$block_idx]) : '';

        if ($district_name === '' || $block_name === '') {
            $stats['skipped']++;
            continue;
        }

        $district_key = strtolower($district_name);

        if (!isset($district_cache[$district_key])) {
            $district_id = (int) pmpro_nbstup_find_district_id_by_name($state_id, $district_name);

            if ($district_id > 0) {
                $stats['district_existing']++;
                $district_cache[$district_key] = $district_id;
            } else {
                $inserted = $wpdb->insert(
                    $districts_table,
                    array(
                        'state_id' => $state_id,
                        'name' => $district_name,
                    ),
                    array('%d', '%s')
                );

                if ($inserted === false) {
                    $stats['skipped']++;
                    continue;
                }

                $district_id = (int) $wpdb->insert_id;
                $district_cache[$district_key] = $district_id;
                $stats['district_inserted']++;
            }
        } else {
            $district_id = (int) $district_cache[$district_key];
        }

        if ($district_id <= 0) {
            $stats['skipped']++;
            continue;
        }

        $block_exists = (int) pmpro_nbstup_find_block_id_by_name($district_id, $block_name);

        if ($block_exists > 0) {
            $stats['block_existing']++;
            continue;
        }

        $block_inserted = $wpdb->insert(
            $blocks_table,
            array(
                'district_id' => $district_id,
                'name' => $block_name,
            ),
            array('%d', '%s')
        );

        if ($block_inserted === false) {
            $stats['skipped']++;
            continue;
        }

        $stats['block_inserted']++;
    }

    fclose($handle);

    return $stats;
}

