# Changelog

All notable changes to the PMPro NBSTUP Bank Import Addon are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [0.1.0] - 2026-01-19

### Initial Release

This is the complete initial release of PMPro NBSTUP including core functionality and contribution verification system.

#### Added

**Core Features:**
- Default deactivation of all subscriber accounts
- Bank transfer verification via CSV import
- Yearly membership duration (1 year from activation/renewal)
- Automatic membership expiry checking
- User role-based access control (subscribers only)

**User Activation Feature:**
- CSV upload interface for user activation
- Transaction ID matching with bank transfer data
- New member activation (1 year membership)
- Existing member renewal (extend by 1 year)
- Activation and renewal email notifications
- CSV processing with results reporting (activated count, skipped, not found)

**Membership Management:**
- Membership start date tracking
- Membership expiry date tracking
- Renewal status tracking (active, renewal, expired, contribution_overdue)
- Last renewal date tracking
- Automatic deactivation on expiry
- 30-day expiry reminder emails
- Expiry notification emails
- Daily scheduled event for expiry checks

**Deceased Member Handling:**
- Passed Away checkbox on user profile
- Date of Death date picker
- Deceased users cannot be activated
- Deceased users cannot log in
- Automatic account deactivation when marked deceased
- Deceased member notification email to admin
- Deceased members listed on contribution page
- Contribution required for all active members when deceased marked

**Contribution Verification Feature:**
- Automatic contribution requirement when member marked deceased
- 1-month contribution payment deadline
- Contribution required email notifications
- CSV upload for contribution verification
- Transaction ID matching for contribution payments
- Contribution payment confirmation emails
- Automatic deactivation for missed contribution deadlines
- Contribution overdue email notifications
- Manual contribution payment marking
- Contribution status display on user profile
- Contribution deadline tracking

**Checkout Fields:**
- Transaction ID field on checkout
- Payment Receipt file upload field
- Field display for bank transfer gateway only
- Transaction ID saved to user meta and order meta
- Payment receipt saved to user meta and order meta (URL)
- Receipt validation (PNG, JPG, PDF formats)

**Email Notifications:**
- Activation email (new members)
- Renewal confirmation email (existing members)
- Expiry reminder email (30 days before expiry)
- Membership expired email (on expiry)
- Contribution required email (all active members when deceased marked)
- Contribution overdue email (missed deadline)
- Contribution confirmed email (verified payment)
- Deceased notification email (admin)

**Admin Interface:**
- User Approval submenu under Paid Memberships Pro
- User Activation tab with CSV upload
- Contribution Verification tab with CSV upload
- Tab navigation between features
- Results display with statistics
- Error and success messages

**User Profile Fields:**
- NBSTUP Membership Flags section:
  - Passed Away checkbox
  - Date of Death field
- Membership Status section (read-only):
  - Active Status display
  - Renewal Status display
  - Membership Start Date
  - Membership Expiry Date
  - Last Renewal Date
- Contribution Payment Status section:
  - Contribution Required display
  - Contribution Paid checkbox
  - Contribution Deadline display

**Frontend Account Page:**
- Custom shortcode: [pmpro_account_nbstup]
- Two-column layout (sidebar + content)
- Navigation sidebar with links:
  - Account Overview
  - My Memberships
  - Order / Invoice History
  - Contribution page
- Deceased members list with avatars
- Deceased members contribution payment button
- Pagination for deceased members list

**Scheduled Events:**
- Daily membership expiry check event
- Daily contribution deadline check event
- Automatic user deactivation on expiry
- Automatic user deactivation on contribution deadline
- Email notifications via scheduled events

**Security Features:**
- Nonce verification on all forms
- Capability checks (manage_options for admin)
- Input sanitization (sanitize_text_field)
- Output escaping (esc_html, esc_attr, esc_url)
- Prepared database statements ($wpdb->prepare)
- Type validation on all meta values
- File upload validation
- CSV file validation

**User Meta Fields - Membership:**
- pmpronbstup_active (1/0 - can login)
- pmpronbstup_deceased (1/0 - marked as deceased)
- pmpronbstup_deceased_date (Y-m-d format)
- pmpronbstup_membership_start_date (Y-m-d format)
- pmpronbstup_membership_expiry_date (Y-m-d format)
- pmpronbstup_renewal_status (string)
- pmpronbstup_last_renewal_date (Y-m-d format)
- pmpronbstup_expiry_reminder_sent (1/0 - flag)
- pmpronbstup_expiry_email_sent_[YM] (1/0 - per month)

**User Meta Fields - Contribution:**
- pmpronbstup_contribution_required (1/0)
- pmpronbstup_contribution_deadline (Y-m-d format)
- pmpronbstup_contribution_paid (1/0)
- pmpronbstup_contribution_transaction_id (string)

**User Meta Fields - Bank Transfer:**
- bank_transaction_id (string)
- bank_payment_receipt (string - URL)

**CSV Import Features:**
- User Activation CSV:
  - Transaction ID extraction
  - User lookup by bank_transaction_id
  - New member activation
  - Member renewal
  - Email notifications
  - Results reporting

- Contribution CSV:
  - Transaction ID extraction
  - User lookup by bank_transaction_id
  - Contribution verification
  - Payment confirmation
  - Status updating
  - Results reporting

**Documentation:**
- Comprehensive README.md (6,000+ words)
- Table of contents with navigation
- Feature descriptions
- Process workflows
- CSV format examples
- User meta field documentation
- Security features list
- Installation and deployment guide
- Troubleshooting guide
- Quick reference section

**Localization:**
- Text domain: 'pmpro-nbstup'
- All strings wrapped with __() or esc functions
- Ready for translation

#### Technical Details

**Plugin Structure:**
- Main plugin file: pmpro-nbstup.php
- Core functions: includes/functions-core.php
- Authentication: includes/functions-auth.php
- Admin interface: includes/functions-admin.php
- CSV processing: includes/functions-csv.php
- User profile: includes/functions-user-profile.php
- Checkout fields: includes/payment-info-fields.php

**File Structure:**
```
pmpro-nbstup/
├── pmpro-nbstup.php
├── README.md
├── CHANGELOG.md
├── includes/
│   ├── functions-core.php
│   ├── functions-auth.php
│   ├── functions-admin.php
│   ├── functions-csv.php
│   ├── functions-user-profile.php
│   └── payment-info-fields.php
├── assets/
│   ├── css/
│   │   ├── frontend.css
│   │   └── frontend.css.map
│   ├── js/
│   │   ├── frontend.js
│   │   └── dist/
│   │       ├── frontend.js
│   │       └── frontend.js.map
│   └── scss/
│       └── frontend.scss
├── package.json
├── gulpfile.js
└── [other config files]
```

**Dependencies:**
- WordPress 5.0+
- Paid Memberships Pro (PMPro)
- PHP 7.2+

**CSS & JavaScript:**
- Two-column account layout styling
- Responsive design (mobile, tablet, desktop)
- Navigation sidebar styling
- Table styling for deceased members
- Pagination styling
- Button styling
- JavaScript for smooth scrolling
- Active link highlighting on scroll
- Compiled and minified assets

**Gulp Build System:**
- SCSS compilation with Autoprefixer
- CSS minification with CSSNano
- JavaScript minification with Uglify
- Source maps for debugging
- Watch mode for development

#### Functions Added

**Core Functions (functions-core.php):**
1. `pmpronbstup_enqueue_frontend_assets()` - Enqueue CSS/JS
2. `pmpronbstup_is_user_active()` - Check if user active
3. `pmpronbstup_activate_user()` - Activate user account
4. `pmpronbstup_deactivate_user()` - Deactivate user account
5. `pmpronbstup_check_membership_expiry()` - Check expiry
6. `pmpronbstup_check_all_expired_memberships()` - Check all users
7. `pmpronbstup_send_expiry_reminder_email()` - Send reminder
8. `pmpronbstup_send_renewal_required_email()` - Send expiry notice
9. `pmpronbstup_send_renewal_confirmation_email()` - Send renewal confirm
10. `pmpronbstup_account_two_column_shortcode()` - Account page layout
11. `pmpronbstup_render_deceased_members_list()` - Deceased list display
12. `pmpronbstup_migrate_existing_users()` - Migration on activation
13. `pmpronbstup_mark_contribution_required()` - Mark for contribution
14. `pmpronbstup_send_contribution_required_email()` - Send notification
15. `pmpronbstup_check_contribution_deadlines()` - Check deadlines
16. `pmpronbstup_send_contribution_overdue_email()` - Send overdue
17. `pmpronbstup_send_contribution_confirmation_email()` - Send confirm
18. `pmpronbstup_is_user_active_with_contribution()` - Check with contrib

**Authentication Functions (functions-auth.php):**
1. `pmpronbstup_authenticate()` - Login restriction filter

**Admin Functions (functions-admin.php):**
1. `pmpronbstup_admin_menu()` - Register admin menu
2. `pmpronbstup_render_admin_page()` - Render main page
3. `pmpronbstup_render_user_activation_csv_form()` - Activation form
4. `pmpronbstup_render_contribution_csv_form()` - Contribution form

**CSV Functions (functions-csv.php):**
1. `pmpronbstup_handle_csv_upload()` - Process user activation CSV
2. `pmpronbstup_send_activation_email()` - Send activation email
3. `pmpronbstup_handle_contribution_csv_upload()` - Process contribution CSV

**User Profile Functions (functions-user-profile.php):**
1. `pmpronbstup_user_profile_fields()` - Display profile fields
2. `pmpronbstup_save_user_profile_fields()` - Save profile fields
3. `pmpronbstup_send_deceased_notification()` - Send deceased notice

**Checkout Functions (payment-info-fields.php):**
1. `pmpro_add_bank_transfer_fields()` - Add checkout fields
2. `pmpro_save_bank_transfer_data()` - Save transaction ID and receipt
3. `pmpro_show_bank_details_to_member()` - Show in member dashboard
4. `pmpro_show_bank_details_in_admin()` - Show in admin order details

#### Hooks & Filters

**Actions:**
- `plugins_loaded` - Load plugin files
- `register_activation_hook` - Register activation
- `wp_enqueue_scripts` - Enqueue frontend assets
- `admin_menu` - Register admin menu
- `admin_init` - CSV import processing
- `add_shortcode` - Register shortcodes
- `show_user_profile` - Display profile fields (frontend)
- `edit_user_profile` - Display profile fields (admin)
- `personal_options_update` - Save profile (frontend)
- `edit_user_profile_update` - Save profile (admin)
- `pmpro_checkout_after_payment_information_fields` - Add checkout fields
- `pmpro_after_checkout` - Save bank transfer data
- `pmpro_member_order_details_after` - Show details in dashboard
- `pmpro_order_details_after` - Show details in admin
- `wp_scheduled_event_pmpronbstup_check_expiry` - Daily expiry check
- `wp_scheduled_event_pmpronbstup_check_contribution` - Daily contrib check

**Filters:**
- `pmpro_checkout_form_enctype` - Enable file uploads
- `authenticate` - Login restrictions

#### Testing & QA

**Security Testing:**
- ✅ Nonce verification on forms
- ✅ Capability checks on admin
- ✅ Input sanitization
- ✅ Output escaping
- ✅ SQL injection protection
- ✅ File upload validation
- ✅ Type validation

**Functionality Testing:**
- ✅ User activation via CSV
- ✅ Member renewal via CSV
- ✅ Membership expiry
- ✅ Deceased member handling
- ✅ Contribution requirement
- ✅ Contribution verification
- ✅ Auto-deactivation
- ✅ Email notifications
- ✅ Login restrictions
- ✅ Profile fields

**Compatibility Testing:**
- ✅ WordPress 5.0+
- ✅ Paid Memberships Pro
- ✅ PHP 7.2+
- ✅ MySQL/MariaDB

#### Known Limitations

- Amount validation not performed on CSV import (by design)
- WordPress Cron must be enabled for scheduled events
- Contribution feature triggers for all active members (not selective)
- Deceased users cannot be undeleted (manual database edit required)

#### Browser Compatibility

- ✅ Chrome (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Edge (latest)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

---

## Version History Summary

| Version | Date | Status | Major Changes |
|---------|------|--------|---|
| 0.1.0 | 2026-01-19 | Stable | Initial release with all features |

---

## Migration Guide

### From Previous Versions

This is the first release (0.1.0), so no migration needed.

### Fresh Installation

1. Install plugin
2. Activate plugin
3. Configure Paid Memberships Pro checkout
4. Set up CSV imports
5. Begin using features

### Upgrading (Future Versions)

Always backup WordPress database before upgrading.

---

## Support & Reporting

**For Issues:**
- Check troubleshooting section in README.md
- Review error logs

**For Feature Requests:**
- Document current functionality in README.md
- Contact development team

---

## License & Credits

**License:** Custom (Proprietary)  
**Author:** WebWallah  
**Copyright:** 2026 WebWallah

---

## Format Legend

- **Added** - New features
- **Changed** - Changes to existing functionality
- **Deprecated** - Features to be removed
- **Removed** - Removed features
- **Fixed** - Bug fixes
- **Security** - Security improvements

---

**Latest Update:** January 19, 2026  
**Current Version:** 0.1.0  
**Status:** Production Ready
