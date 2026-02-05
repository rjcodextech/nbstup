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
