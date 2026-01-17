<?php

/**
 * Plugin Name: PMPro NBSTUP Addon
 * Description: Custom addon for Paid Memberships Pro to control subscriber activation via bank CSV import and handle deceased members.
 * Author: WebWallah
 * Version: 0.1.0
 * Text Domain: pmpro-nbstup
 */

if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PMPRONBSTUP_VERSION', '0.1.0');
define('PMPRONBSTUP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PMPRONBSTUP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PMPRONBSTUP_INCLUDES_DIR', PMPRONBSTUP_PLUGIN_DIR . 'includes/');

/**
 * Plugin activation hook
 */
function pmpronbstup_activate()
{
    // No schema changes needed; we will use user meta:
    // pmpronbstup_active (bool), pmpronbstup_deceased (bool), pmpronbstup_deceased_date (string Y-m-d)

    // Schedule daily expiry check
    if (! wp_next_scheduled('wp_scheduled_event_pmpronbstup_check_expiry')) {
        wp_schedule_event(time(), 'daily', 'wp_scheduled_event_pmpronbstup_check_expiry');
    }

    // Migrate existing active users to have expiry dates
    pmpronbstup_migrate_existing_users();
}
register_activation_hook(__FILE__, 'pmpronbstup_activate');

/**
 * Load plugin files
 */
function pmpronbstup_load_files()
{
    $includes_dir = PMPRONBSTUP_INCLUDES_DIR;

    // Core functionality
    require_once $includes_dir . 'functions-core.php';

    // Authentication and login restrictions
    require_once $includes_dir . 'functions-auth.php';

    // Admin menu and pages
    require_once $includes_dir . 'functions-admin.php';

    // CSV import processing
    require_once $includes_dir . 'functions-csv.php';

    // User profile fields
    require_once $includes_dir . 'functions-user-profile.php';

    // Payment info fields
    require_once $includes_dir . 'payment-info-fields.php';
}
add_action('plugins_loaded', 'pmpronbstup_load_files');

