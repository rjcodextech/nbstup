<?php

/**
 * Plugin Name: PMPro NBSTUP Addon
 * Description: Custom addon for Paid Memberships Pro to control subscriber activation via bank CSV import and handle deceased members.
 * Author: WebWallah
 * Version: 1.0.8
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
 * - Checkout fields for member, nominee, and address collection
 * - User listing shortcode with search and pagination
 *
 * @package PMProNBSTUP
 * @version 1.0.8
 */

if (! defined('ABSPATH')) {
    exit; // Prevent direct access to this file.
}

// Define plugin constants for easy reference throughout the codebase.
define('PMPRONBSTUP_VERSION', '1.0.8');
define('PMPRONBSTUP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PMPRONBSTUP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PMPRONBSTUP_INCLUDES_DIR', PMPRONBSTUP_PLUGIN_DIR . 'includes/');

// Check if Paid Memberships Pro is active
if (!defined('PMPRO_VERSION')) {
    add_action('admin_notices', function () {
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
    // Add rewrite rules first before flushing
    pmpronbstup_add_rewrite_rules();

    // Flush rewrite rules after adding new ones
    flush_rewrite_rules();

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
 * Plugin deactivation hook
 *
 * Runs when the plugin is deactivated to clean up scheduled events and rewrite rules
 *
 * @return void
 */
function pmpronbstup_deactivate()
{
    // Unschedule daily events
    wp_clear_scheduled_hook('wp_scheduled_event_pmpronbstup_check_expiry');
    wp_clear_scheduled_hook('wp_scheduled_event_pmpronbstup_check_contribution');

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'pmpronbstup_deactivate');

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
 * - Checkout and PMPro member data display fields
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

    // Checkout fields (member/nominee/address) and PMPro member-page display.
    require_once $includes_dir . 'payment-info-fields.php';

    // Location management (States, Districts, Blocks).
    require_once $includes_dir . 'functions-location.php';

    // Members List admin page.
    require_once $includes_dir . 'functions-members-list.php';

    // Email settings page and template management.
    require_once $includes_dir . 'functions-email-settings.php';

    // Contributions management page.
    require_once $includes_dir . 'functions-contributions-page.php';

    // Now that all functions are loaded, run migration if needed.
    pmpronbstup_run_migration();
}
add_action('plugins_loaded', 'pmpronbstup_load_files');

/**
 * Add rewrite rules for /adminpanel path
 * This allows the admin login form to be accessible at siteurl.com/adminpanel
 *
 * @return void
 */
function pmpronbstup_add_rewrite_rules()
{
    // Add rewrite rule for /adminpanel
    add_rewrite_rule('^adminpanel/?$', 'index.php?pmpro_nbstup_admin_page=true', 'top');
}
add_action('init', 'pmpronbstup_add_rewrite_rules', 10);

/**
 * Register the pmpro_nbstup_admin_page query variable
 *
 * @param array $vars Query variables.
 * @return array
 */
function pmpronbstup_register_query_var($vars)
{
    $vars[] = 'pmpro_nbstup_admin_page';
    return $vars;
}
add_filter('query_vars', 'pmpronbstup_register_query_var');

/**
 * Template redirect for admin login page
 * Loads a custom template when the /adminpanel URL is accessed
 *
 * @return void
 */
function pmpronbstup_template_redirect()
{
    if (get_query_var('pmpro_nbstup_admin_page')) {
        // Prevent direct access if already logged in, redirect to admin
        if (is_user_logged_in() && current_user_can('manage_options')) {
            wp_redirect(admin_url());
            exit;
        }

        // Load the admin login page template
        pmpronbstup_load_admin_login_template();
        exit;
    }
}
add_action('template_redirect', 'pmpronbstup_template_redirect');

/**
 * Redirect default WordPress login page to home page
 * Disables access to wp-login.php and forces users to use custom login forms
 *
 * @return void
 */
function pmpronbstup_redirect_default_login()
{
    // Redirect any access to wp-login.php to home page, except when WordPress
    // itself needs to handle an action such as logout.  Without this check the
    // logout link (which hits wp-login.php?action=logout) is immediately
    // redirected and the user never actually gets logged out.
    if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false) {
        // Allow the logout action to proceed to core.  We also passthrough for
        // other actions like "lostpassword" or "resetpass" just in case but the
        // primary problem reported by the user was inability to log out.
        if (isset($_REQUEST['action']) && in_array($_REQUEST['action'], array('logout', 'lostpassword', 'resetpass', 'rp', 'retrievepassword'), true)) {
            return;
        }

        wp_redirect(home_url());
        exit;
    }
}
add_action('login_init', 'pmpronbstup_redirect_default_login');

/**
 * Load and display the admin login page template
 *
 * @return void
 */
function pmpronbstup_load_admin_login_template()
{
?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>

    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo esc_html(bloginfo('name')) . ' - ' . esc_html__('Admin Login', 'pmpro-nbstup'); ?></title>
        <?php wp_head(); ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            html,
            body {
                height: 100%;
                width: 100%;
            }

            body {
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                padding: 20px;
            }

            .pmpro-nbstup-admin-login-wrapper {
                width: 100%;
                max-width: 1000px;
                height: auto;
                min-height: 500px;
            }

            .pmpro-nbstup-admin-login-container {
                background: white;
                border-radius: 24px;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
                display: grid;
                grid-template-columns: 1fr 1fr;
                height: auto;
                min-height: 420px;
            }

            /* Left Column - Form */
            .admin-login-form-column {
                padding: 35px 32px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                background: white;
            }

            .admin-login-logo {
                display: flex;
                align-items: center;
                gap: 10px;
                margin-bottom: 24px;
            }

            .admin-login-logo-icon {
                width: 32px;
                height: 32px;
                background: #7B61FF;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }

            .admin-login-logo-text {
                font-size: 20px;
                font-weight: 700;
                color: #1a1a1a;
                font-family: 'Inter', sans-serif;
            }

            .admin-login-header {
                margin-bottom: 24px;
                text-align: left;
            }

            .admin-login-header h1 {
                color: #1a1a1a;
                font-size: 36px;
                font-weight: 700;
                margin-bottom: 8px;
                line-height: 1.2;
            }

            .admin-login-header p {
                color: #9ca3af;
                font-size: 16px;
                font-weight: 400;
                line-height: 1.5;
            }

            .admin-login-form .pmpro-nbstup-member-login__field {
                margin-bottom: 14px;
            }

            .admin-login-form .pmpro-nbstup-member-login__label {
                display: none;
            }

            .admin-login-form .pmpro-nbstup-member-login__input {
                width: 100%;
                padding: 14px 16px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                font-size: 15px;
                font-family: 'Inter', inherit;
                transition: all 0.3s ease;
                background: #f9fafb;
                color: #1a1a1a;
            }

            .admin-login-form .pmpro-nbstup-member-login__input::placeholder {
                color: #9ca3af;
            }

            .admin-login-form .pmpro-nbstup-member-login__input:focus {
                outline: none;
                border-color: #7B61FF;
                background: white;
                box-shadow: 0 0 0 3px rgba(123, 97, 255, 0.1);
            }

            .admin-login-actions-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 16px 0;
                gap: 15px;
            }

            .admin-login-remember {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .admin-login-remember input[type="checkbox"] {
                width: 18px;
                height: 18px;
                cursor: pointer;
                accent-color: #7B61FF;
                border-radius: 4px;
            }

            .admin-login-remember label {
                color: #374151;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                margin: 0;
            }

            .admin-login-forgot {
                color: #7B61FF;
                font-size: 14px;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.3s ease;
            }

            .admin-login-forgot:hover {
                color: #6b52dd;
                text-decoration: underline;
            }

            .admin-login-form .pmpro-nbstup-member-login__actions {
                margin-top: 28px;
                margin-bottom: 24px;
            }

            .admin-login-form .pmpro-nbstup-member-login__submit {
                width: 100%;
                padding: 14px 24px;
                background: #7B61FF;
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                font-family: 'Inter', inherit;
            }

            .admin-login-form .pmpro-nbstup-member-login__submit:hover {
                background: #6b52dd;
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(123, 97, 255, 0.3);
            }

            .admin-login-form .pmpro-nbstup-member-login__submit:active {
                transform: translateY(0);
            }

            .admin-login-form .pmpro-nbstup-member-login__submit:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
            }

            .admin-login-footer-text {
                text-align: center;
                color: #6b7280;
                font-size: 14px;
                margin-top: 24px;
            }

            .admin-login-footer-text a {
                color: #7B61FF;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.3s ease;
            }

            .admin-login-footer-text a:hover {
                color: #6b52dd;
                text-decoration: underline;
            }

            /* Message styling */
            .admin-login-form .pmpro-nbstup-login-message {
                margin-bottom: 20px;
                padding: 12px 16px;
                border-radius: 8px;
                font-size: 14px;
                border-left: 4px solid #ef4444;
                background: rgba(239, 68, 68, 0.08);
                color: #7f1d1d;
                display: none;
            }

            .admin-login-form .pmpro-nbstup-login-message:not([hidden]) {
                display: block;
                animation: slideDown 0.3s ease;
            }

            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Right Column - Visual */
            .admin-login-visual-column {
                background: linear-gradient(135deg, rgba(167, 139, 250, 0.9) 0%, rgba(99, 102, 241, 0.9) 100%);
                background-size: cover;
                background-position: center;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
            }



            /* Responsive Design */
            @media (max-width: 768px) {
                .pmpro-nbstup-admin-login-container {
                    grid-template-columns: 1fr;
                    min-height: auto;
                }

                .admin-login-form-column {
                    padding: 40px 28px;
                }

                .admin-login-visual-column {
                    display: none;
                }

                .admin-login-header h1 {
                    font-size: 28px;
                }

                .admin-login-form-column {
                    min-height: 100vh;
                    justify-content: flex-start;
                    padding-top: 60px;
                }

                .admin-login-logo {
                    margin-bottom: 30px;
                }

                .admin-login-phone {
                    width: 140px;
                    height: 280px;
                    border-width: 8px;
                }

                .admin-login-phone::before {
                    width: 120px;
                    height: 20px;
                }
            }

            @media (max-width: 480px) {
                .admin-login-form-column {
                    padding: 30px 20px;
                }

                .admin-login-header h1 {
                    font-size: 24px;
                }

                .admin-login-header p {
                    font-size: 14px;
                }

                .admin-login-form .pmpro-nbstup-member-login__input {
                    padding: 12px 14px;
                    font-size: 14px;
                }

                .admin-login-actions-row {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .admin-login-forgot {
                    width: 100%;
                    text-align: center;
                }
            }

            /* Loading state */
            .is-loading .pmpro-nbstup-member-login__submit {
                position: relative;
                color: transparent;
            }

            .is-loading .pmpro-nbstup-member-login__submit::after {
                content: '';
                position: absolute;
                width: 16px;
                height: 16px;
                top: 50%;
                left: 50%;
                margin-left: -8px;
                margin-top: -8px;
                border: 2px solid rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                border-top-color: white;
                animation: spin 0.8s linear infinite;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }
        </style>
    </head>

    <body>
        <div class="pmpro-nbstup-admin-login-wrapper">
            <div class="pmpro-nbstup-admin-login-container">
                <!-- Left Column - Form -->
                <div class="admin-login-form-column">
                    <div class="admin-login-logo">
                        <div class="admin-login-logo-icon">🔐</div>
                        <div class="admin-login-logo-text"><?php echo esc_html(bloginfo('name')); ?></div>
                    </div>

                    <div class="admin-login-header">
                        <h1><?php esc_html_e('Holla, Welcome Back', 'pmpro-nbstup'); ?></h1>
                        <p><?php esc_html_e('Hey, welcome back to your special place', 'pmpro-nbstup'); ?></p>
                    </div>

                    <div class="admin-login-form">
                        <?php echo do_shortcode('[pmpro_nbstup_admin_login redirect="' . esc_url(admin_url()) . '"]'); ?>

                        <div class="admin-login-footer-text">
                            <?php esc_html_e('Don\'t have an account?', 'pmpro-nbstup'); ?>
                            <a href="<?php echo esc_url(home_url('/registration')); ?>"><?php esc_html_e('Sign Up', 'pmpro-nbstup'); ?></a>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Visual -->
                <div class="admin-login-visual-column" style="background-image: url('<?php echo esc_url(PMPRONBSTUP_PLUGIN_URL . 'assets/img/nbstup-cover.webp'); ?>');"></div>
            </div>
        </div>
        <?php wp_footer(); ?>
    </body>

    </html>
<?php
}
