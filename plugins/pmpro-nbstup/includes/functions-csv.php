<?php

/**
 * CSV import processing for PMPro NBSTUP Addon
 *
 * @package PMProNBSTUP
 * @subpackage CSV Import
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle CSV upload and processing when admin form is submitted
 */
function pmpronbstup_handle_csv_upload()
{
    if (! function_exists('pmpronbstup_user_activation_csv_enabled') || ! pmpronbstup_user_activation_csv_enabled()) {
        return;
    }
    if (! is_admin()) {
        return;
    }

    if (empty($_POST['pmpronbstup_csv_nonce']) || ! wp_verify_nonce($_POST['pmpronbstup_csv_nonce'], 'pmpronbstup_csv_import')) {
        return;
    }

    if (! current_user_can('manage_options')) {
        return;
    }

    if (empty($_FILES['pmpronbstup_csv_file']['tmp_name'])) {
        add_settings_error('pmpro-nbstup', 'no_file', __('No CSV file uploaded.', 'pmpro-nbstup'), 'error');
        return;
    }

    $file = $_FILES['pmpronbstup_csv_file']['tmp_name'];

    $handle = fopen($file, 'r');
    if (! $handle) {
        add_settings_error('pmpro-nbstup', 'cannot_open', __('Unable to open uploaded file.', 'pmpro-nbstup'), 'error');
        return;
    }

    $row        = 0;
    $activated  = 0;
    $skipped    = 0;
    $not_found  = 0;
    $header_map = array();

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        $row++;

        // Assume first row is header
        if (1 === $row) {
            $header_map = array();
            foreach ($data as $index => $col) {
                $key                 = strtolower(trim($col));
                $header_map[$key]  = $index;
            }
            continue;
        }

        // Normalise column names – expecting something like "transaction_id"
        $transaction_id_col = null;

        foreach ($header_map as $name => $index) {
            if (null === $transaction_id_col && false !== strpos($name, 'transaction')) {
                $transaction_id_col = $index;
            }
        }

        if (null === $transaction_id_col) {
            $skipped++;
            continue;
        }

        if (! isset($data[$transaction_id_col])) {
            $skipped++;
            continue;
        }

        $csv_transaction_id = trim($data[$transaction_id_col]);

        if ('' === $csv_transaction_id) {
            $skipped++;
            continue;
        }

        // Find user by bank_transaction_id user meta
        global $wpdb;

        $user_meta = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'bank_transaction_id',
                $csv_transaction_id
            )
        );

        if (! $user_meta) {
            $not_found++;
            continue;
        }

        $user_id = (int) $user_meta->user_id;
        $user    = get_userdata($user_id);
        if (! $user || ! in_array('subscriber', (array) $user->roles, true)) {
            $skipped++;
            continue;
        }

        // Check if user is deceased
        $deceased = get_user_meta($user_id, 'pmpronbstup_deceased', true);
        if ((int) $deceased === 1 || $deceased === '1' || $deceased === true) {
            $skipped++;
            continue;
        }

        // Determine if this is a RENEWAL or INITIAL ACTIVATION
        $existing_expiry = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
        $is_renewal = (! empty($existing_expiry));
        $was_already_active = pmpronbstup_is_user_active($user_id);

        // Skip if already active (already processed) - only if not a renewal
        if (! $is_renewal && $was_already_active) {
            $skipped++;
            continue;
        }

        // Calculate expiry date (1 year from today)
        $new_expiry_date = date('Y-m-d', strtotime('+1 year'));

        if ($is_renewal) {
            // RENEWAL: Update expiry and send renewal confirmation
            update_user_meta($user_id, 'pmpronbstup_membership_expiry_date', $new_expiry_date);
            update_user_meta($user_id, 'pmpronbstup_last_renewal_date', date('Y-m-d'));
            update_user_meta($user_id, 'pmpronbstup_renewal_status', 'active');
            update_user_meta($user_id, 'pmpronbstup_active', 1);
            pmpronbstup_assign_unique_id($user_id);

            // Clear reminder flags for new year
            delete_user_meta($user_id, 'pmpronbstup_expiry_reminder_sent');

            $activated++;
            pmpronbstup_send_renewal_confirmation_email($user_id);
        } else {
            // INITIAL ACTIVATION: Set start date, expiry, and activation
            update_user_meta($user_id, 'pmpronbstup_membership_start_date', date('Y-m-d'));
            update_user_meta($user_id, 'pmpronbstup_membership_expiry_date', $new_expiry_date);
            update_user_meta($user_id, 'pmpronbstup_renewal_status', 'active');
            update_user_meta($user_id, 'pmpronbstup_active', 1);
            pmpronbstup_assign_unique_id($user_id);

            $activated++;
            pmpronbstup_send_activation_email($user_id);
        }
    }

    fclose($handle);

    add_settings_error(
        'pmpro-nbstup',
        'import_result',
        sprintf(
            /* translators: 1: activated count, 2: skipped count, 3: not found count */
            __('Import finished. Activated/Renewed: %1$d, Skipped: %2$d, No matching transaction: %3$d.', 'pmpro-nbstup'),
            $activated,
            $skipped,
            $not_found
        ),
        'updated'
    );
}
add_action('admin_init', 'pmpronbstup_handle_csv_upload');

/**
 * Handle contribution CSV upload and processing for deceased members
 */
function pmpronbstup_handle_contribution_csv_upload()
{
    if (! is_admin()) {
        return;
    }

    if (empty($_POST['pmpronbstup_contribution_deceased_csv_nonce']) || ! wp_verify_nonce($_POST['pmpronbstup_contribution_deceased_csv_nonce'], 'pmpronbstup_contribution_deceased_csv_import')) {
        return;
    }

    if (! current_user_can('manage_options')) {
        return;
    }

    if (empty($_FILES['pmpronbstup_contribution_deceased_csv_file']['tmp_name'])) {
        add_settings_error('pmpro-nbstup', 'no_contribution_file', __('No CSV file uploaded.', 'pmpro-nbstup'), 'error');
        return;
    }

    $file = $_FILES['pmpronbstup_contribution_deceased_csv_file']['tmp_name'];

    $handle = fopen($file, 'r');
    if (! $handle) {
        add_settings_error('pmpro-nbstup', 'cannot_open_contribution', __('Unable to open uploaded file.', 'pmpro-nbstup'), 'error');
        return;
    }

    $row        = 0;
    $verified   = 0;
    $skipped    = 0;
    $not_found  = 0;
    $header_map = array();

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        $row++;

        // Assume first row is header
        if (1 === $row) {
            $header_map = array();
            foreach ($data as $index => $col) {
                $key                 = strtolower(trim($col));
                $header_map[$key]  = $index;
            }
            continue;
        }

        // Find transaction ID column
        $transaction_id_col = null;

        foreach ($header_map as $name => $index) {
            if (null === $transaction_id_col && false !== strpos($name, 'transaction')) {
                $transaction_id_col = $index;
            }
        }

        if (null === $transaction_id_col) {
            $skipped++;
            continue;
        }

        if (! isset($data[$transaction_id_col])) {
            $skipped++;
            continue;
        }

        $csv_transaction_id = trim($data[$transaction_id_col]);

        if ('' === $csv_transaction_id) {
            $skipped++;
            continue;
        }

        // Find user by contribution_transaction_id user meta
        global $wpdb;

        $user_meta = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'bank_transaction_id',
                $csv_transaction_id
            )
        );

        if (! $user_meta) {
            $not_found++;
            continue;
        }

        $user_id = (int) $user_meta->user_id;
        $user    = get_userdata($user_id);
        if (! $user || ! in_array('subscriber', (array) $user->roles, true)) {
            $skipped++;
            continue;
        }

        // Check if user has deceased contribution requirement
        $contribution_required = get_user_meta($user_id, 'pmpronbstup_contribution_deceased_required', true);
        if ((int) $contribution_required !== 1) {
            $skipped++;
            continue;
        }

        // Mark contribution as paid
        update_user_meta($user_id, 'pmpronbstup_contribution_deceased_paid', 1);
        update_user_meta($user_id, 'pmpronbstup_contribution_deceased_transaction_id', $csv_transaction_id);
        pmpronbstup_reactivate_user_if_eligible($user_id, __('Deceased contribution verified via CSV import', 'pmpro-nbstup'));

        // Send confirmation email
        pmpronbstup_send_contribution_confirmation_email($user_id, 'deceased');

        $verified++;
    }

    fclose($handle);

    add_settings_error(
        'pmpro-nbstup',
        'contribution_deceased_import_result',
        sprintf(
            /* translators: 1: verified count, 2: skipped count, 3: not found count */
            __('Deceased contribution verification finished. Verified: %1$d, Skipped: %2$d, No matching transaction: %3$d.', 'pmpro-nbstup'),
            $verified,
            $skipped,
            $not_found
        ),
        'updated'
    );
}
add_action('admin_init', 'pmpronbstup_handle_contribution_csv_upload');

/**
 * Handle contribution CSV upload and processing for wedding contributions
 */
function pmpronbstup_handle_contribution_wedding_csv_upload()
{
    if (! is_admin()) {
        return;
    }

    if (empty($_POST['pmpronbstup_contribution_wedding_csv_nonce']) || ! wp_verify_nonce($_POST['pmpronbstup_contribution_wedding_csv_nonce'], 'pmpronbstup_contribution_wedding_csv_import')) {
        return;
    }

    if (! current_user_can('manage_options')) {
        return;
    }

    if (empty($_FILES['pmpronbstup_contribution_wedding_csv_file']['tmp_name'])) {
        add_settings_error('pmpro-nbstup', 'no_wedding_contribution_file', __('No CSV file uploaded.', 'pmpro-nbstup'), 'error');
        return;
    }

    $file = $_FILES['pmpronbstup_contribution_wedding_csv_file']['tmp_name'];

    $handle = fopen($file, 'r');
    if (! $handle) {
        add_settings_error('pmpro-nbstup', 'cannot_open_wedding_contribution', __('Unable to open uploaded file.', 'pmpro-nbstup'), 'error');
        return;
    }

    $row        = 0;
    $verified   = 0;
    $skipped    = 0;
    $not_found  = 0;
    $header_map = array();

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        $row++;

        // Assume first row is header
        if (1 === $row) {
            $header_map = array();
            foreach ($data as $index => $col) {
                $key                 = strtolower(trim($col));
                $header_map[$key]  = $index;
            }
            continue;
        }

        // Find transaction ID column
        $transaction_id_col = null;

        foreach ($header_map as $name => $index) {
            if (null === $transaction_id_col && false !== strpos($name, 'transaction')) {
                $transaction_id_col = $index;
            }
        }

        if (null === $transaction_id_col) {
            $skipped++;
            continue;
        }

        if (! isset($data[$transaction_id_col])) {
            $skipped++;
            continue;
        }

        $csv_transaction_id = trim($data[$transaction_id_col]);

        if ('' === $csv_transaction_id) {
            $skipped++;
            continue;
        }

        // Find user by contribution_transaction_id user meta
        global $wpdb;

        $user_meta = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                'bank_transaction_id',
                $csv_transaction_id
            )
        );

        if (! $user_meta) {
            $not_found++;
            continue;
        }

        $user_id = (int) $user_meta->user_id;
        $user    = get_userdata($user_id);
        if (! $user || ! in_array('subscriber', (array) $user->roles, true)) {
            $skipped++;
            continue;
        }

        // Check if user has wedding contribution requirement
        $contribution_required = get_user_meta($user_id, 'pmpronbstup_contribution_wedding_required', true);
        if ((int) $contribution_required !== 1) {
            $skipped++;
            continue;
        }

        // Mark contribution as paid
        update_user_meta($user_id, 'pmpronbstup_contribution_wedding_paid', 1);
        update_user_meta($user_id, 'pmpronbstup_contribution_wedding_transaction_id', $csv_transaction_id);
        pmpronbstup_reactivate_user_if_eligible($user_id, __('Wedding contribution verified via CSV import', 'pmpro-nbstup'));

        // Send confirmation email
        pmpronbstup_send_contribution_confirmation_email($user_id, 'wedding');

        $verified++;
    }

    fclose($handle);

    add_settings_error(
        'pmpro-nbstup',
        'contribution_wedding_import_result',
        sprintf(
            /* translators: 1: verified count, 2: skipped count, 3: not found count */
            __('Wedding contribution verification finished. Verified: %1$d, Skipped: %2$d, No matching transaction: %3$d.', 'pmpro-nbstup'),
            $verified,
            $skipped,
            $not_found
        ),
        'updated'
    );
}
add_action('admin_init', 'pmpronbstup_handle_contribution_wedding_csv_upload');

/**
 * Validate YYYY-MM-DD date format.
 *
 * @param string $date Raw date string.
 * @return bool
 */
function pmpronbstup_is_valid_iso_date($date)
{
    if (! is_string($date) || $date === '') {
        return false;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $date;
}

/**
 * Build a vCard 3.0 entry for a member.
 *
 * @param WP_User $user User object.
 * @return string
 */
function pmpronbstup_build_member_vcard_entry($user)
{
    if (! ($user instanceof WP_User)) {
        return '';
    }

    $user_id = (int) $user->ID;
    if ($user_id <= 0) {
        return '';
    }

    $values = function_exists('pmpronbstup_get_pmpro_member_custom_field_values')
        ? pmpronbstup_get_pmpro_member_custom_field_values($user_id)
        : array();

    $full_name = isset($values['member_name']) && $values['member_name'] !== ''
        ? $values['member_name']
        : $user->display_name;
    $full_name = trim((string) $full_name);

    $name_parts = preg_split('/\s+/', $full_name);
    $first_name = ! empty($name_parts) ? array_shift($name_parts) : '';
    $last_name  = ! empty($name_parts) ? implode(' ', $name_parts) : '';

    $district = isset($values['district_name']) ? (string) $values['district_name'] : '';
    if ($district === '') {
        $district_id = (int) get_user_meta($user_id, 'user_district', true);
        if ($district_id > 0 && function_exists('pmpro_nbstup_get_district_name')) {
            $district = (string) pmpro_nbstup_get_district_name($district_id);
        }
    }

    $block = isset($values['block_name']) ? (string) $values['block_name'] : '';
    if ($block === '') {
        $block_id = (int) get_user_meta($user_id, 'user_block', true);
        if ($block_id > 0 && function_exists('pmpro_nbstup_get_block_name')) {
            $block = (string) pmpro_nbstup_get_block_name($block_id);
        }
    }

    $mobile = isset($values['phone_no']) ? (string) $values['phone_no'] : (string) get_user_meta($user_id, 'phone_no', true);
    $unique_id = (string) get_user_meta($user_id, 'pmpronbstup_unique_id', true);
    if ($unique_id === '' && function_exists('pmpronbstup_assign_unique_id')) {
        $maybe_unique_id = pmpronbstup_assign_unique_id($user_id);
        if (is_string($maybe_unique_id) && $maybe_unique_id !== '') {
            $unique_id = $maybe_unique_id;
        }
    }

    $escape = function_exists('pmpronbstup_escape_vcard_text')
        ? 'pmpronbstup_escape_vcard_text'
        : static function ($value) {
            $value = (string) $value;
            $value = str_replace('\\', '\\\\', $value);
            $value = str_replace(';', '\\;', $value);
            $value = str_replace(',', '\\,', $value);
            $value = preg_replace("/\r\n|\r|\n/", '\\n', $value);
            return trim($value);
        };

    $display_name_base = $full_name !== '' ? $full_name : ('Member ' . $user_id);
    $fn_parts = array('NBST', $display_name_base);
    if ($block !== '') {
        $fn_parts[] = $block;
    }
    if ($district !== '') {
        $fn_parts[] = $district;
    }
    $fn = implode(' ', $fn_parts);

    $vcard = array(
        'BEGIN:VCARD',
        'VERSION:3.0',
        'FN:' . $escape($fn),
        'N:;' . $escape($fn) . ';;;',
    );

    if ($mobile !== '') {
        $vcard[] = 'TEL;TYPE=CELL:' . $escape($mobile);
    }

    $vcard[] = 'X-DISTRICT:' . $escape($district);
    $vcard[] = 'X-BLOCK:' . $escape($block);
    $vcard[] = 'X-UNIQUE-ID:' . $escape($unique_id);
    $vcard[] = 'END:VCARD';

    return implode("\r\n", $vcard);
}

/**
 * Export subscriber members as a single vCard file with optional registration date filters.
 *
 * @return void
 */
function pmpronbstup_handle_members_vcard_export()
{
    if (! is_admin()) {
        return;
    }

    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
    if (empty($_GET['pmpronbstup_export_vcards']) || $page === '' || ! in_array($page, array('pmpro-nbstup-user-approval', 'pmpro-memberslist'), true)) {
        return;
    }

    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to export members.', 'pmpro-nbstup'));
    }

    $nonce = isset($_GET['pmpronbstup_export_vcards_nonce']) ? sanitize_text_field(wp_unslash($_GET['pmpronbstup_export_vcards_nonce'])) : '';
    if ($nonce === '' || ! wp_verify_nonce($nonce, 'pmpronbstup_export_members_vcards')) {
        wp_die(esc_html__('Security check failed while exporting members vCard file.', 'pmpro-nbstup'));
    }

    $registered_from = isset($_GET['pmpronbstup_registered_from']) ? sanitize_text_field(wp_unslash($_GET['pmpronbstup_registered_from'])) : '';
    $registered_to   = isset($_GET['pmpronbstup_registered_to']) ? sanitize_text_field(wp_unslash($_GET['pmpronbstup_registered_to'])) : '';

    if ($registered_from !== '' && ! pmpronbstup_is_valid_iso_date($registered_from)) {
        wp_die(esc_html__('Invalid "Registered From" date. Please use YYYY-MM-DD format.', 'pmpro-nbstup'));
    }

    if ($registered_to !== '' && ! pmpronbstup_is_valid_iso_date($registered_to)) {
        wp_die(esc_html__('Invalid "Registered To" date. Please use YYYY-MM-DD format.', 'pmpro-nbstup'));
    }

    if ($registered_from !== '' && $registered_to !== '' && strtotime($registered_from) > strtotime($registered_to)) {
        wp_die(esc_html__('"Registered From" date cannot be after "Registered To" date.', 'pmpro-nbstup'));
    }

    $query_args = array(
        'role'    => 'subscriber',
        'fields'  => 'all',
        'orderby' => 'registered',
        'order'   => 'ASC',
        'number'  => -1,
    );

    if ($registered_from !== '' || $registered_to !== '') {
        $date_clause = array(
            'column'    => 'user_registered',
            'inclusive' => true,
        );

        if ($registered_from !== '') {
            $date_clause['after'] = $registered_from;
        }
        if ($registered_to !== '') {
            $date_clause['before'] = $registered_to;
        }

        $query_args['date_query'] = array($date_clause);
    }

    $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
    $redirect_args = array(
        'page' => $page,
    );
    if ($page === 'pmpro-nbstup-user-approval' && $tab !== '') {
        $redirect_args['tab'] = $tab;
    }
    if ($registered_from !== '') {
        $redirect_args['pmpronbstup_registered_from'] = $registered_from;
    }
    if ($registered_to !== '') {
        $redirect_args['pmpronbstup_registered_to'] = $registered_to;
    }

    $users = get_users($query_args);
    if (empty($users)) {
        $redirect_args['pmpronbstup_export_notice'] = 'no_members';
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    $entries = array();
    foreach ($users as $user) {
        $entry = pmpronbstup_build_member_vcard_entry($user);
        if ($entry !== '') {
            $entries[] = $entry;
        }
    }

    if (empty($entries)) {
        $redirect_args['pmpronbstup_export_notice'] = 'no_vcards';
        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    $filename_parts = array('nbstup-members');
    if ($registered_from !== '') {
        $filename_parts[] = 'from-' . $registered_from;
    }
    if ($registered_to !== '') {
        $filename_parts[] = 'to-' . $registered_to;
    }
    $filename = sanitize_file_name(implode('-', $filename_parts) . '.vcf');

    nocache_headers();
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    echo implode("\r\n", $entries) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}
add_action('admin_init', 'pmpronbstup_handle_members_vcard_export', 7);

/**
 * Send activation email to user (initial membership)
 *
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
function pmpronbstup_send_activation_email($user_id)
{
    $user = get_userdata($user_id);
    if (! $user) {
        return false;
    }

    $expiry_date = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to       = $user->user_email;
    
    $defaults = pmpronbstup_get_default_email_templates();
    $subject_template = pmpronbstup_get_email_setting('activation_subject', $defaults['activation_subject']);
    $body_template = pmpronbstup_get_email_setting('activation_body', $defaults['activation_body']);
    
    // Replace placeholders
    $replacements = array(
        '{blogname}' => $blogname,
        '{display_name}' => $user->display_name,
        '{current_date}' => date('Y-m-d'),
        '{expiry_date}' => $expiry_date,
    );
    
    $subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
    $message = str_replace(array_keys($replacements), array_values($replacements), $body_template);

    return wp_mail($to, $subject, $message);
}
