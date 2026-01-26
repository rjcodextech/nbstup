<?php

/**
 * Plugin Name: PMPro NBSTUP Addon
 * Description: Custom addon for Paid Memberships Pro to control subscriber activation via bank CSV import and handle deceased members.
 * Author: WebWallah
 * Version: 0.1.0
 * Text Domain: pmpro-nbstup
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires: Paid Memberships Pro
 * Requires WP: 5.0
 * Requires PHP: 7.2
 */

/**
 * PMPro NBSTUP - Subscription Management Addon
 *
 * This plugin extends Paid Memberships Pro with:
 * - CSV-based user activation via bank transfer verification
 * - Yearly membership renewal with automatic expiration
 * - Deceased member management
 * - Daughter wedding contribution system
 * - Contribution payment system when members pass away or have daughter weddings
 * - Automated email notifications
 * - Checkout fields for transaction ID and payment receipt collection
 * - User listing shortcode with search and pagination
 *
 * @package PMProNBSTUP
 * @version 0.1.0
 */

if (! defined('ABSPATH')) {
    exit; // Prevent direct access to this file.
}

// Define plugin constants for easy reference throughout the codebase.
define('PMPRONBSTUP_VERSION', '0.1.0');
define('PMPRONBSTUP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PMPRONBSTUP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PMPRONBSTUP_INCLUDES_DIR', PMPRONBSTUP_PLUGIN_DIR . 'includes/');

// Check if Paid Memberships Pro is active
if (!defined('PMPRO_VERSION')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>PMPro NBSTUP Addon requires Paid Memberships Pro to be installed and active.</p></div>';
    });
    return;
}

/**
 * Plugin activation hook
 *
 * Runs when the plugin is activated. Sets up:
 * - Scheduled events for daily membership expiry checks
 * - Scheduled events for daily contribution deadline enforcement
 *
 * User meta fields used (stored in wp_usermeta):
 *   Membership:
 *   - pmpronbstup_active: 0/1 flag for user account access
 *   - pmpronbstup_deceased: 0/1 flag for deceased members
 *   - pmpronbstup_deceased_date: Y-m-d format date of death
 *   - pmpronbstup_daughter_wedding: 0/1 flag for daughter wedding
 *   - pmpronbstup_wedding_date: Y-m-d format wedding date
 *   - pmpronbstup_membership_start_date: Y-m-d when membership started
 *   - pmpronbstup_membership_expiry_date: Y-m-d when membership expires
 *   - pmpronbstup_renewal_status: active|renewal|expired|contribution_overdue
 *   - pmpronbstup_last_renewal_date: Y-m-d date of last renewal
 *
 *   Deceased Contribution:
 *   - pmpronbstup_contribution_deceased_required: 0/1 whether member must pay
 *   - pmpronbstup_contribution_deceased_deadline: Y-m-d payment deadline
 *   - pmpronbstup_contribution_deceased_paid: 0/1 whether payment verified
 *   - pmpronbstup_contribution_deceased_transaction_id: Payment transaction ID
 *
 *   Wedding Contribution:
 *   - pmpronbstup_contribution_wedding_required: 0/1 whether member must pay
 *   - pmpronbstup_contribution_wedding_deadline: Y-m-d payment deadline
 *   - pmpronbstup_contribution_wedding_paid: 0/1 whether payment verified
 *   - pmpronbstup_contribution_wedding_transaction_id: Payment transaction ID
 *
 * @return void
 */
function pmpronbstup_activate()
{
    // Schedule daily expiry check - runs via WordPress Cron system.
    if (! wp_next_scheduled('wp_scheduled_event_pmpronbstup_check_expiry')) {
        wp_schedule_event(time(), 'daily', 'wp_scheduled_event_pmpronbstup_check_expiry');
    }

    // Schedule daily contribution deadline check - runs via WordPress Cron system.
    if (! wp_next_scheduled('wp_scheduled_event_pmpronbstup_check_contribution')) {
        wp_schedule_event(time(), 'daily', 'wp_scheduled_event_pmpronbstup_check_contribution');
    }
}
register_activation_hook(__FILE__, 'pmpronbstup_activate');

/**
 * Run migration on plugins_loaded hook
 *
 * This migrates existing active users to have membership expiry dates.
 * It runs after all plugin files are loaded, so all functions are available.
 *
 * @return void
 */
function pmpronbstup_run_migration()
{
    // Only run if migration hasn't been done yet.
    if (! get_option('pmpronbstup_migration_completed')) {
        pmpronbstup_migrate_existing_users();
        update_option('pmpronbstup_migration_completed', true);
    }
}

/**
 * Load all plugin include files
 *
 * Runs on 'plugins_loaded' hook to load all required plugin functionality:
 * - Core membership and activation logic
 * - Authentication and login restrictions
 * - Admin interface and CSV import
 * - User profile fields
 * - Checkout payment fields
 *
 * @return void
 */
function pmpronbstup_load_files()
{
    $includes_dir = PMPRONBSTUP_INCLUDES_DIR;

    // Core membership, activation, and expiry logic.
    require_once $includes_dir . 'functions-core.php';

    // Authentication filters - prevent login for inactive/expired members.
    require_once $includes_dir . 'functions-auth.php';

    // Admin menu pages and CSV import forms.
    require_once $includes_dir . 'functions-admin.php';

    // CSV processing for both user activation and contribution verification.
    require_once $includes_dir . 'functions-csv.php';

    // User profile fields for membership and contribution status.
    require_once $includes_dir . 'functions-user-profile.php';

    // Checkout form fields for transaction ID and payment receipt collection.
    require_once $includes_dir . 'payment-info-fields.php';

    // Email settings page and template management.
    require_once $includes_dir . 'functions-email-settings.php';

    // Contributions management page.
    require_once $includes_dir . 'functions-contributions-page.php';

    // Now that all functions are loaded, run migration if needed.
    pmpronbstup_run_migration();
}
add_action('plugins_loaded', 'pmpronbstup_load_files');

