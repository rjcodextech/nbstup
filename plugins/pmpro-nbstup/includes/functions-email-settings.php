<?php

/**
 * Email settings page for PMPro NBSTUP Addon
 *
 * @package PMProNBSTUP
 * @subpackage Email Settings
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Add email settings submenu under Paid Memberships Pro
 */
function pmpronbstup_add_email_settings_menu()
{
    add_submenu_page(
        'pmpro-dashboard',
        __('Email Settings', 'pmpro-nbstup'),
        __('Email Settings', 'pmpro-nbstup'),
        'manage_options',
        'pmpro-nbstup-email-settings',
        'pmpronbstup_render_email_settings_page'
    );
}
add_action('admin_menu', 'pmpronbstup_add_email_settings_menu', 21);

/**
 * Register settings
 */
function pmpronbstup_register_email_settings()
{
    register_setting('pmpronbstup_email_settings', 'pmpronbstup_email_settings');
}
add_action('admin_init', 'pmpronbstup_register_email_settings');

/**
 * Get email setting with default fallback
 *
 * @param string $key Setting key
 * @param string $default Default value
 * @return string Setting value
 */
function pmpronbstup_get_email_setting($key, $default = '')
{
    $settings = get_option('pmpronbstup_email_settings', array());
    return isset($settings[$key]) && !empty($settings[$key]) ? $settings[$key] : $default;
}

/**
 * Get default email templates
 *
 * @return array Default email templates
 */
function pmpronbstup_get_default_email_templates()
{
    return array(
        'deceased_contribution_subject' => __('[{blogname}] Contribution Payment Required', 'pmpro-nbstup'),
        'deceased_contribution_body' => __("Hello {display_name},\n\nA member of our community has passed away. In their memory, all active members are requested to pay a contribution.\n\nContribution Deadline: {deadline}\n\nPlease visit your account to make your contribution:\n{account_url}\n\nThank you for your support.\n\nBest regards,\n{blogname}", 'pmpro-nbstup'),
        
        'wedding_contribution_subject' => __('[{blogname}] Daughter Wedding Contribution Required', 'pmpro-nbstup'),
        'wedding_contribution_body' => __("Hello {display_name},\n\nA member of our community is celebrating their daughter's wedding. In honor of this joyous occasion, all active members are requested to pay a contribution.\n\nContribution Deadline: {deadline}\n\nPlease visit your account to make your contribution:\n{account_url}\n\nThank you for your support.\n\nBest regards,\n{blogname}", 'pmpro-nbstup'),
        
        'deceased_contribution_confirmed_subject' => __('[{blogname}] Your Contribution Has Been Verified', 'pmpro-nbstup'),
        'deceased_contribution_confirmed_body' => __("Hello {display_name},\n\nThank you! Your contribution payment has been verified and recorded.\n\nYour account remains active. Thank you for your support.\n\nBest regards,\n{blogname}", 'pmpro-nbstup'),
        
        'wedding_contribution_confirmed_subject' => __('[{blogname}] Your Wedding Contribution Has Been Verified', 'pmpro-nbstup'),
        'wedding_contribution_confirmed_body' => __("Hello {display_name},\n\nThank you! Your daughter wedding contribution payment has been verified and recorded.\n\nYour account remains active. Thank you for your support.\n\nBest regards,\n{blogname}", 'pmpro-nbstup'),
        
        'expiry_reminder_subject' => __('[{blogname}] Your Membership Expires in {days_until_expiry} Days', 'pmpro-nbstup'),
        'expiry_reminder_body' => __("Hello {display_name},\n\nYour membership will expire on {expiry_date}.\n\nTo renew your membership and maintain access, please visit your account:\n{account_url}\n\nThank you for your continued membership.\n\nBest regards,\n{blogname}", 'pmpro-nbstup'),
        
        'renewal_required_subject' => __('[{blogname}] Your Membership Has Expired', 'pmpro-nbstup'),
        'renewal_required_body' => __("Hello {display_name},\n\nYour membership expired on {expiry_date}.\n\nYour account access has been suspended. To renew your membership and regain access, please visit your account:\n{account_url}\n\nThank you,\n{blogname}", 'pmpro-nbstup'),
        
        'renewal_confirmed_subject' => __('[{blogname}] Your Membership Has Been Renewed', 'pmpro-nbstup'),
        'renewal_confirmed_body' => __("Hello {display_name},\n\nYour membership renewal has been verified and confirmed.\n\nYour membership is now valid until {expiry_date}.\n\nThank you for your continued membership.\n\nBest regards,\n{blogname}", 'pmpro-nbstup'),
        
        'activation_subject' => __('[{blogname}] Your account has been activated', 'pmpro-nbstup'),
        'activation_body' => __("Hello {display_name},\n\nYour membership account has been activated after verifying your payment.\n\nYour membership is valid for 1 year.\n- Activated: {current_date}\n- Expires on: {expiry_date}\n\nThank you.", 'pmpro-nbstup'),
        
        'contribution_overdue_subject' => __('[{blogname}] Your Contribution Payment is Overdue', 'pmpro-nbstup'),
        'contribution_overdue_body' => __("Hello {display_name},\n\nYour contribution payment deadline has passed.\n\nYour account has been deactivated. To reactivate your account and continue your membership, please pay the contribution.\n\nVisit your account: {account_url}\n\nThank you,\n{blogname}", 'pmpro-nbstup'),
    );
}

/**
 * Render email settings page
 */
function pmpronbstup_render_email_settings_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'pmpro-nbstup'));
    }

    $defaults = pmpronbstup_get_default_email_templates();
    $settings = get_option('pmpronbstup_email_settings', array());
    
    // Handle form submission
    if (isset($_POST['pmpronbstup_email_settings_nonce']) && 
        wp_verify_nonce($_POST['pmpronbstup_email_settings_nonce'], 'pmpronbstup_save_email_settings')) {
        
        $new_settings = array();
        foreach ($defaults as $key => $default_value) {
            if (isset($_POST['pmpronbstup_email_settings'][$key])) {
                $new_settings[$key] = sanitize_textarea_field(wp_unslash($_POST['pmpronbstup_email_settings'][$key]));
            }
        }
        
        update_option('pmpronbstup_email_settings', $new_settings);
        echo '<div class="notice notice-success"><p>' . esc_html__('Email settings saved successfully.', 'pmpro-nbstup') . '</p></div>';
        $settings = $new_settings;
    }
    
    // Handle reset to defaults
    if (isset($_POST['pmpronbstup_reset_defaults_nonce']) && 
        wp_verify_nonce($_POST['pmpronbstup_reset_defaults_nonce'], 'pmpronbstup_reset_email_defaults')) {
        
        delete_option('pmpronbstup_email_settings');
        echo '<div class="notice notice-success"><p>' . esc_html__('Email templates reset to defaults.', 'pmpro-nbstup') . '</p></div>';
        $settings = array();
    }
?>
    <div class="wrap">
        <h1><?php esc_html_e('NBSTUP Email Settings', 'pmpro-nbstup'); ?></h1>
        <p><?php esc_html_e('Configure email templates sent by the NBSTUP addon. Available placeholders:', 'pmpro-nbstup'); ?></p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><code>{blogname}</code> - <?php esc_html_e('Site name', 'pmpro-nbstup'); ?></li>
            <li><code>{display_name}</code> - <?php esc_html_e('User display name', 'pmpro-nbstup'); ?></li>
            <li><code>{deadline}</code> - <?php esc_html_e('Contribution deadline', 'pmpro-nbstup'); ?></li>
            <li><code>{expiry_date}</code> - <?php esc_html_e('Membership expiry date', 'pmpro-nbstup'); ?></li>
            <li><code>{current_date}</code> - <?php esc_html_e('Current date', 'pmpro-nbstup'); ?></li>
            <li><code>{account_url}</code> - <?php esc_html_e('Account URL', 'pmpro-nbstup'); ?></li>
            <li><code>{days_until_expiry}</code> - <?php esc_html_e('Days until expiry', 'pmpro-nbstup'); ?></li>
        </ul>

        <div style="background: #f0f0f1; padding: 15px; margin: 20px 0; border-left: 4px solid #2271b1;">
            <h3 style="margin-top: 0;"><?php esc_html_e('Available Shortcodes', 'pmpro-nbstup'); ?></h3>
            <p><?php esc_html_e('Use these shortcodes in your pages and posts:', 'pmpro-nbstup'); ?></p>
            
            <table class="widefat" style="background: #fff; margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="padding: 10px;"><strong><?php esc_html_e('Shortcode', 'pmpro-nbstup'); ?></strong></th>
                        <th style="padding: 10px;"><strong><?php esc_html_e('Description', 'pmpro-nbstup'); ?></strong></th>
                        <th style="padding: 10px;"><strong><?php esc_html_e('Attributes', 'pmpro-nbstup'); ?></strong></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 10px;"><code>[pmpro_nbstup_users_list]</code></td>
                        <td style="padding: 10px;"><?php esc_html_e('Display a list of all users with membership details, status, and contribution information. Includes search and pagination.', 'pmpro-nbstup'); ?></td>
                        <td style="padding: 10px;">
                            <code>per_page</code> - <?php esc_html_e('Number of users per page (default: 20)', 'pmpro-nbstup'); ?><br>
                            <strong><?php esc_html_e('Example:', 'pmpro-nbstup'); ?></strong> <code>[pmpro_nbstup_users_list per_page="30"]</code>
                        </td>
                    </tr>
                    <tr style="background: #f9f9f9;">
                        <td style="padding: 10px;"><code>[pmpro_account_nbstup]</code></td>
                        <td style="padding: 10px;"><?php esc_html_e('Enhanced member account page with two-column layout. Shows account overview, membership details, order history, and contribution list.', 'pmpro-nbstup'); ?></td>
                        <td style="padding: 10px;">
                            <?php esc_html_e('No attributes', 'pmpro-nbstup'); ?><br>
                            <strong><?php esc_html_e('Example:', 'pmpro-nbstup'); ?></strong> <code>[pmpro_account_nbstup]</code>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <p style="margin-top: 15px;">
                <strong><?php esc_html_e('Features:', 'pmpro-nbstup'); ?></strong>
            </p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><strong>[pmpro_nbstup_users_list]</strong> - <?php esc_html_e('Shows user ID, name, email, username, active status, deceased status, wedding status, membership status, expiry date, and contribution payment status (both deceased and wedding)', 'pmpro-nbstup'); ?></li>
                <li><strong>[pmpro_account_nbstup]</strong> - <?php esc_html_e('Replaces standard PMPro account page with sidebar navigation and content area. Includes link to view deceased members list for contribution payments', 'pmpro-nbstup'); ?></li>
            </ul>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('pmpronbstup_save_email_settings', 'pmpronbstup_email_settings_nonce'); ?>
            
            <h2><?php esc_html_e('Contribution Emails', 'pmpro-nbstup'); ?></h2>
            
            <h3><?php esc_html_e('Deceased Member Contribution Required', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="deceased_contribution_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[deceased_contribution_subject]" id="deceased_contribution_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('deceased_contribution_subject', $defaults['deceased_contribution_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="deceased_contribution_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[deceased_contribution_body]" id="deceased_contribution_body" 
                                  rows="10" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('deceased_contribution_body', $defaults['deceased_contribution_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Wedding Contribution Required', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wedding_contribution_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[wedding_contribution_subject]" id="wedding_contribution_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('wedding_contribution_subject', $defaults['wedding_contribution_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wedding_contribution_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[wedding_contribution_body]" id="wedding_contribution_body" 
                                  rows="10" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('wedding_contribution_body', $defaults['wedding_contribution_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Deceased Contribution Confirmed', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="deceased_contribution_confirmed_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[deceased_contribution_confirmed_subject]" id="deceased_contribution_confirmed_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('deceased_contribution_confirmed_subject', $defaults['deceased_contribution_confirmed_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="deceased_contribution_confirmed_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[deceased_contribution_confirmed_body]" id="deceased_contribution_confirmed_body" 
                                  rows="6" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('deceased_contribution_confirmed_body', $defaults['deceased_contribution_confirmed_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Wedding Contribution Confirmed', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wedding_contribution_confirmed_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[wedding_contribution_confirmed_subject]" id="wedding_contribution_confirmed_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('wedding_contribution_confirmed_subject', $defaults['wedding_contribution_confirmed_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wedding_contribution_confirmed_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[wedding_contribution_confirmed_body]" id="wedding_contribution_confirmed_body" 
                                  rows="6" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('wedding_contribution_confirmed_body', $defaults['wedding_contribution_confirmed_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Membership Emails', 'pmpro-nbstup'); ?></h2>

            <h3><?php esc_html_e('Expiry Reminder (30 days before)', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="expiry_reminder_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[expiry_reminder_subject]" id="expiry_reminder_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('expiry_reminder_subject', $defaults['expiry_reminder_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="expiry_reminder_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[expiry_reminder_body]" id="expiry_reminder_body" 
                                  rows="8" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('expiry_reminder_body', $defaults['expiry_reminder_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Membership Expired', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="renewal_required_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[renewal_required_subject]" id="renewal_required_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('renewal_required_subject', $defaults['renewal_required_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="renewal_required_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[renewal_required_body]" id="renewal_required_body" 
                                  rows="8" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('renewal_required_body', $defaults['renewal_required_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Membership Renewed', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="renewal_confirmed_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[renewal_confirmed_subject]" id="renewal_confirmed_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('renewal_confirmed_subject', $defaults['renewal_confirmed_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="renewal_confirmed_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[renewal_confirmed_body]" id="renewal_confirmed_body" 
                                  rows="8" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('renewal_confirmed_body', $defaults['renewal_confirmed_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Account Activated', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="activation_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[activation_subject]" id="activation_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('activation_subject', $defaults['activation_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="activation_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[activation_body]" id="activation_body" 
                                  rows="8" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('activation_body', $defaults['activation_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Contribution Overdue', 'pmpro-nbstup'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="contribution_overdue_subject"><?php esc_html_e('Subject', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <input type="text" name="pmpronbstup_email_settings[contribution_overdue_subject]" id="contribution_overdue_subject" 
                               value="<?php echo esc_attr(pmpronbstup_get_email_setting('contribution_overdue_subject', $defaults['contribution_overdue_subject'])); ?>" 
                               class="large-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="contribution_overdue_body"><?php esc_html_e('Body', 'pmpro-nbstup'); ?></label></th>
                    <td>
                        <textarea name="pmpronbstup_email_settings[contribution_overdue_body]" id="contribution_overdue_body" 
                                  rows="8" class="large-text"><?php echo esc_textarea(pmpronbstup_get_email_setting('contribution_overdue_body', $defaults['contribution_overdue_body'])); ?></textarea>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save Email Settings', 'pmpro-nbstup')); ?>
        </form>

        <hr style="margin: 40px 0;" />
        
        <form method="post" action="" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to reset all email templates to defaults? This cannot be undone.', 'pmpro-nbstup')); ?>');">
            <?php wp_nonce_field('pmpronbstup_reset_email_defaults', 'pmpronbstup_reset_defaults_nonce'); ?>
            <p>
                <input type="submit" name="reset_defaults" class="button button-secondary" value="<?php esc_attr_e('Reset All to Defaults', 'pmpro-nbstup'); ?>" />
            </p>
        </form>
    </div>
<?php
}
