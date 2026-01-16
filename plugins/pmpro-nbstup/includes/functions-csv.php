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

        // Find PMPro order by transaction_id
        if (! function_exists('pmpro_getMemberOrders')) {
            $skipped++;
            continue;
        }

        global $wpdb;

        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->pmpro_membership_orders} WHERE transaction_id = %s LIMIT 1",
                $csv_transaction_id
            )
        );

        if (! $order) {
            $not_found++;
            continue;
        }

        $user_id = (int) $order->user_id;
        $user    = get_userdata($user_id);
        if (! $user || ! in_array('subscriber', (array) $user->roles, true)) {
            $skipped++;
            continue;
        }

        // Skip already active subscribers
        if (pmpronbstup_is_user_active($user_id)) {
            $skipped++;
            continue;
        }

        // Mark active if not deceased
        $deceased = get_user_meta($user_id, 'pmpronbstup_deceased', true);
        if ((int) $deceased === 1 || $deceased === '1' || $deceased === true) {
            $skipped++;
            continue;
        }

        // Activate user
        if (pmpronbstup_activate_user($user_id)) {
            $activated++;

            // Send activation email
            pmpronbstup_send_activation_email($user_id);
        }
    }

    fclose($handle);

    add_settings_error(
        'pmpro-nbstup',
        'import_result',
        sprintf(
            /* translators: 1: activated count, 2: skipped count, 3: not found count */
            __('Import finished. Activated: %1$d, Skipped: %2$d, No matching transaction: %3$d.', 'pmpro-nbstup'),
            $activated,
            $skipped,
            $not_found
        ),
        'updated'
    );
}
add_action('admin_init', 'pmpronbstup_handle_csv_upload');

/**
 * Send activation email to user
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

    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $to       = $user->user_email;
    $subject  = sprintf(__('[%s] Your account has been activated', 'pmpro-nbstup'), $blogname);
    $message  = sprintf(
        __("Hello %s,\n\nYour membership account has been activated after verifying your payment.\n\nThank you.", 'pmpro-nbstup'),
        $user->display_name
    );

    return wp_mail($to, $subject, $message);
}
