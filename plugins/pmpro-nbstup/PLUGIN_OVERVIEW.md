# PMPro NBSTUP Plugin - Comprehensive Overview

## Plugin Information
- **Plugin Name**: PMPro NBSTUP Addon
- **Version**: 0.1.0
- **Author**: WebWallah
- **Text Domain**: pmpro-nbstup
- **Description**: Custom addon for Paid Memberships Pro to control subscriber activation via bank CSV import and handle deceased members.

---

## Table of Contents
1. [Plugin Purpose](#plugin-purpose)
2. [Core Features](#core-features)
3. [File Structure](#file-structure)
4. [User Meta Data](#user-meta-data)
5. [Key Workflows](#key-workflows)
6. [Security & Best Practices](#security--best-practices)
7. [Build & Asset Processing](#build--asset-processing)
8. [Technical Details](#technical-details)

---

## Plugin Purpose

The **PMPro NBSTUP** is a specialized add-on for **Paid Memberships Pro** that manages subscriber activation through bank statement CSV imports and handles deceased member scenarios. It's specifically designed for the NBSTUP (National Bank Settlement) use case.

**Key Concept**: All subscribers start as **inactive** by default and cannot log in until activated via verified bank payment confirmation.

---

## Core Features

### 1. Yearly Recurring Subscription Management

#### Membership Lifecycle
- **1-year membership duration** from activation/renewal date
- **Automatic expiry** on membership end date
- **Bank transfer verification** for renewals via CSV import
- **Email notifications** for expiry reminders and renewals

#### Activation & Renewal Process
- **Initial Activation**: New members activated for 1 year from activation date
- **Annual Renewal**: Existing members extend membership by 1 year upon verified payment
- **Auto-Deactivation**: Members automatically deactivated when membership expires
- **Expiry Reminders**: Email sent 30 days before expiry
- **Renewal Required**: Email sent when membership expires

#### Activation Status Check
- Function: `pmpronbstup_is_user_active($user_id)`
- Returns `true` only if:
  - User has subscriber role
  - User meta `pmpronbstup_active = 1`
  - User is NOT marked deceased
  - **Membership has NOT expired** (expiry_date > today)

---

### 2. CSV Bank Import System

#### Admin Interface
- **Menu Location**: Submenu under "Paid Memberships Pro" → "User Approval"
- **Page Slug**: `pmpro-nbstup-user-approval`
- **Capability Required**: `manage_options`

#### CSV Requirements
- **File Type**: `.csv` only
- **Required Columns**:
  - Column containing "transaction" (e.g., `transaction_id`) - must match `bank_transaction_id` from checkout form

#### Import Processing Logic
```
1. Admin uploads CSV file
2. Plugin reads first row as header
3. Normalizes column names (case-insensitive)
4. For each data row:
    - Extract transaction_id
    - Look up user by matching bank_transaction_id user meta
    - Check if user is subscriber
    - Check if user is not marked deceased
    - Determine if RENEWAL (existing membership) or INITIAL ACTIVATION
    - If RENEWAL:
      * Extend expiry_date by 1 year
      * Update last_renewal_date
      * Send renewal confirmation email
    - If INITIAL ACTIVATION:
      * Set membership_start_date
      * Set expiry_date (1 year from today)
      * Set pmpronbstup_active = 1
      * Send activation email
5. Display summary: activated/renewed count, skipped count, not found count
```

#### Import Result Summary
- Displays: "Activated: X, Skipped: Y, No matching transaction: Z"
- Uses WordPress settings error API

---

### 3. Deceased Member Management

#### Profile Fields (Admin Only)
Available on user profile edit page (both own and edit user profile):
- **"Passed Away"** checkbox → stores `pmpronbstup_deceased` (1 or 0)
- **"Date of Death"** date input → stores `pmpronbstup_deceased_date` (Y-m-d format)

#### Deceased Member Behavior
- **Cannot be activated** even via CSV import
- **Cannot log in** - login attempt blocked with error message
- **Auto-deactivation**: When marked deceased, `pmpronbstup_active` is forced to 0
- **Admin Notification**: Email sent to admin when member marked deceased

#### Deceased Member Display
- **Deceased Members List**: `[pmpro_account_nbstup]` shortcode shows separate view
- **List Features**:
  - Shows all members marked deceased
  - Displays: Avatar, name, date of death
  - "Pay your contribution" button per member
  - Paginated (10 per page)
  - Links to checkout for contribution payments

---

### 4. Authentication Filter

#### Login Interception
- **Hook**: `authenticate` filter (priority 30)
- **Applies To**: Subscribers only (other roles unaffected)

#### Login Blocking Rules
Subscriber login is blocked if:
1. `pmpronbstup_active` is not set OR equals 0, OR
2. `pmpronbstup_deceased` equals 1

#### Error Message
```
<strong>Error</strong>: Your account is not active. Please contact support.
```

#### Non-Subscriber Users
- Admins, editors, and other roles bypass this filter
- Only subscribers are subject to activation requirement

---

### 5. Custom Account Layout Shortcode

#### Shortcode
```
[pmpro_account_nbstup]
```

Replaces the default `[pmpro_account]` shortcode with a two-column layout.

#### Layout Structure
```
┌─────────────────────────────────┐
│  Sidebar    │    Content Area   │
│             │                   │
│ - Overview  │ (Selected view)   │
│ - Members   │                   │
│ - Orders    │                   │
│ - Links     │                   │
│ - Contrib.  │                   │
└─────────────────────────────────┘
```

#### Sidebar Navigation Menu
- **Account Overview** → links to `#pmpro_account-profile`
- **My Memberships** → links to `#pmpro_account-membership`
- **Order / Invoice History** → links to `#pmpro_account-orders`
- **Member Links** → links to `#pmpro_account-links`
- **Contribution** → `?view=contribution` (deceased members list)

#### Content Area Behavior
- If `?view=contribution` parameter present: shows deceased members list
- Otherwise: renders standard `[pmpro_account]` shortcode

#### Responsive Design
- Desktop: Side-by-side layout (sidebar 260px fixed, content flex)
- Mobile (< 768px): Stacked vertical layout (sidebar full width)

---

### 6. Bank Transfer Payment Fields

#### Checkout Integration
- Adds fields after payment information section
- Only visible when "Check" (bank transfer) gateway is selected

#### Form Fields
1. **Transaction ID**
   - Type: Text input
   - Required: Yes
   - ID: `bank_transaction_id`

2. **Payment Receipt**
   - Type: File upload
   - Required: Yes
   - Accepts: `.png, .jpg, .jpeg, .pdf`
   - ID: `bank_payment_receipt`

#### Data Storage
After checkout, saves to both user meta and order meta:
- `bank_transaction_id` → user meta & order meta
- `bank_payment_receipt` → user meta & order meta (URL to uploaded file)

#### Display Locations
1. **Member Dashboard** (order history)
   - Shows in order details section
   - Displays transaction ID and clickable receipt link

2. **WP-Admin Order Screen**
   - Shows in order details table
   - Admin can view transaction ID and receipt

#### File Upload Handling
- Uses `wp_handle_upload()` for secure file handling
- Validates file types on input (client-side)
- Stores URL to uploaded file
- Files stored in WordPress uploads directory

---

## File Structure

### Main Plugin File
**`pmpro-nbstup.php`** (100 lines)
- Defines plugin header and text domain
- Sets up 4 constants:
  - `PMPRONBSTUP_VERSION` = "0.1.0"
  - `PMPRONBSTUP_PLUGIN_DIR` = plugin directory path
  - `PMPRONBSTUP_PLUGIN_URL` = plugin directory URL
  - `PMPRONBSTUP_INCLUDES_DIR` = includes directory path
- `pmpronbstup_activate()` - activation hook (no schema needed)
- `pmpronbstup_load_files()` - requires all include files on `plugins_loaded` hook

### Include Files

#### `includes/functions-core.php` (~450 lines)
**Core functionality, frontend assets, and membership management**

Functions:
- `pmpronbstup_enqueue_frontend_assets()` - loads frontend CSS/JS (logged-in users only)
- `pmpronbstup_is_user_active($user_id)` - checks if user is activated and membership valid
- `pmpronbstup_activate_user($user_id)` - activates a subscriber user
- `pmpronbstup_deactivate_user($user_id)` - deactivates a user
- `pmpronbstup_check_membership_expiry($user_id)` - checks and handles membership expiry
- `pmpronbstup_check_all_expired_memberships()` - daily cron job for expiry checks
- `pmpronbstup_send_expiry_reminder_email($user_id, $days)` - sends 30-day expiry reminder
- `pmpronbstup_send_renewal_required_email($user_id)` - sends expired membership notification
- `pmpronbstup_send_renewal_confirmation_email($user_id)` - sends renewal confirmation
- `pmpronbstup_migrate_existing_users()` - migrates existing users on plugin activation
- `pmpronbstup_account_two_column_shortcode()` - renders `[pmpro_account_nbstup]`
- `pmpronbstup_render_deceased_members_list()` - renders deceased members table with pagination

Hooks:
- `wp_enqueue_scripts` - enqueue frontend assets
- `shortcode` - register `pmpro_account_nbstup` shortcode
- `wp_scheduled_event_pmpronbstup_check_expiry` - daily expiry check

---

#### `includes/functions-auth.php` (~40 lines)
**Authentication and login restrictions**

Functions:
- `pmpronbstup_authenticate($user, $username, $password)` - authenticate filter

Hooks:
- `authenticate` (priority 30) - block inactive subscriber logins

---

#### `includes/functions-admin.php` (~100 lines)
**Admin menu and pages**

Functions:
- `pmpronbstup_admin_menu()` - registers admin submenu page
- `pmpronbstup_render_admin_page()` - renders CSV upload form and description

Hooks:
- `admin_menu` (priority 20) - register submenu
- HTML form with nonce field and file input

---

#### `includes/functions-csv.php` (~200 lines)
**CSV import processing for activation and renewals**

Functions:
- `pmpronbstup_handle_csv_upload()` - main CSV processing function
  - Validates nonce and permissions
  - Opens CSV file
  - Parses header row
  - Processes data rows:
    * Extracts transaction_id
    * Looks up user by bank_transaction_id user meta
    * Validates user is subscriber, not deceased
    * Determines if renewal or initial activation
    * For renewals: extends membership by 1 year, sends renewal email
    * For initial: sets 1-year membership, sends activation email
  - Returns summary message

- `pmpronbstup_send_activation_email($user_id)` - sends initial account activation email

Hooks:
- `admin_init` - handle CSV upload on form submission

Database Query:
```php
SELECT user_id FROM {$wpdb->usermeta}
WHERE meta_key = 'bank_transaction_id' AND meta_value = %s LIMIT 1
```

---

#### `includes/functions-user-profile.php` (~130 lines)
**User profile fields**

Functions:
- `pmpronbstup_user_profile_fields($user)` - displays deceased checkbox and date picker
- `pmpronbstup_save_user_profile_fields($user_id)` - saves deceased meta
  - Auto-deactivates user if marked deceased
  - Sends notification email if status changed to deceased
  
- `pmpronbstup_send_deceased_notification($user_id)` - sends admin notification

Hooks:
- `show_user_profile` - show on own profile
- `edit_user_profile` - show on edit user page
- `personal_options_update` - save on own profile
- `edit_user_profile_update` - save on edit user page

---

#### `includes/payment-info-fields.php` (~200 lines)
**Bank transfer payment fields**

Functions:
- `pmpro_add_bank_transfer_fields()` - adds fieldset to checkout form
- `pmpro_save_bank_transfer_data($user_id, $order)` - saves transaction ID and receipt
- `pmpro_show_bank_details_to_member($order)` - displays in member dashboard
- `pmpro_show_bank_details_in_admin($order)` - displays in WP-Admin order

Hooks:
- `pmpro_checkout_form_enctype` - set form to multipart/form-data
- `pmpro_checkout_after_payment_information_fields` - add form fields
- `pmpro_after_checkout` - save transaction data
- `pmpro_member_order_details_after` - show in member dashboard
- `pmpro_order_details_after` - show in admin order screen

---

### Asset Files

#### `assets/scss/frontend.scss` (~60 lines)
**SCSS source for styling**
- Two-column layout with flexbox
- Sidebar: 260px fixed width, light gray background
- Navigation links with hover states
- Responsive design (stacks on mobile < 768px)
- Box shadow and border radius for modern look

#### `assets/css/frontend.css` (~50 lines)
**Compiled CSS** (generated from SCSS)
- Same as SCSS but in CSS format
- Used directly in frontend

#### `assets/js/frontend.js` (~40 lines)
**Frontend JavaScript**
- Smooth scroll for sidebar navigation links
- Smooth scrolling with 80px offset
- Only runs if sidebar element exists

#### `gulpfile.js`
**Gulp build configuration**
- SCSS compilation with Autoprefixer
- JS minification and concatenation
- Source maps generation

#### `package.json`
**NPM dependencies**
- Gulp 4
- SCSS compiler (Sass)
- Autoprefixer
- CSSnano
- Uglify for JS minification
- Gulp plugins for various tasks

Scripts:
- `npm run dev` - watch mode
- `npm run build` - production build

---

## User Meta Data

### Stored User Metadata

#### Core Activation
```php
// User meta: pmpronbstup_active
Value: 1 or 0 (bool)
Meaning: Whether subscriber is activated and allowed to log in
Set by: CSV import, profile save, deceased logic
```

#### Membership Tracking
```php
// User meta: pmpronbstup_membership_start_date
Value: Date string (Y-m-d format)
Meaning: Date when membership was first activated
Set by: CSV import (initial activation)

// User meta: pmpronbstup_membership_expiry_date
Value: Date string (Y-m-d format)
Meaning: Date when current membership expires
Set by: CSV import, daily expiry check

// User meta: pmpronbstup_last_renewal_date
Value: Date string (Y-m-d format)
Meaning: Date of last successful renewal payment
Set by: CSV import (renewals)

// User meta: pmpronbstup_renewal_status
Value: 'active', 'expired', 'pending_renewal'
Meaning: Current status of membership renewal
Set by: CSV import, expiry check
```

#### Email Notification Flags
```php
// User meta: pmpronbstup_expiry_reminder_sent
Value: 1 or 0 (bool)
Meaning: Whether 30-day expiry reminder has been sent
Set by: Daily expiry check

// User meta: pmpronbstup_expiry_email_sent_[Y-m]
Value: 1 or 0 (bool)
Meaning: Whether expiry notification sent for specific month
Set by: Daily expiry check
```

#### Deceased Status
```php
// User meta: pmpronbstup_deceased
Value: 1 or 0 (bool)
Meaning: Whether member is marked as deceased
Set by: User profile edit
```

#### Deceased Date
```php
// User meta: pmpronbstup_deceased_date
Value: Date string (Y-m-d format)
Meaning: Date of member's death
Set by: User profile edit (optional)
```

#### Bank Transfer - Transaction ID
```php
// User meta: bank_transaction_id
Value: String (transaction ID)
Meaning: Bank transfer transaction ID provided at checkout
Set by: pmpro_after_checkout hook
Scope: User level
```

#### Bank Transfer - Payment Receipt
```php
// User meta: bank_payment_receipt
Value: URL string
Meaning: URL to uploaded payment receipt file
Set by: pmpro_after_checkout hook
Scope: User level
```

### Order Meta Data

```php
// Order meta: bank_transaction_id
// Order meta: bank_payment_receipt
// Identical to user meta but stored at order level for order history
```

---

## Key Workflows

### Workflow 1: Subscriber Activation/Renewal via CSV Import

```
┌─────────────────────────────────────┐
│ Admin uploads bank statement CSV    │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ Plugin parses CSV header            │
│ Maps transaction_id column          │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ For each CSV row:                   │
│ - Extract transaction_id            │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ Query: Find user by bank_txn_id     │
│ If not found: increment not_found   │
│ If found: continue                  │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ Validate user:                      │
│ - Has subscriber role? ✓            │
│ - Not marked deceased? ✓            │
│ If any fail: increment skipped      │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ Check membership status:            │
│ - Has existing expiry_date?         │
└────────────────┬────────────────────┘
                  │
         ┌────────┴────────┐
         │                 │
         ▼                 ▼
    RENEWAL          INITIAL ACTIVATION
    PAYMENT             PAYMENT
         │                 │
         ▼                 ▼
┌─────────────────────────────────────┐  ┌─────────────────────────────────────┐
│ Extend expiry_date +1 year          │  │ Set start_date = today            │
│ Set last_renewal_date = today       │  │ Set expiry_date = today +1 year   │
│ Set renewal_status = 'active'       │  │ Set renewal_status = 'active'     │
│ Set pmpronbstup_active = 1          │  │ Set pmpronbstup_active = 1        │
│ Send renewal confirmation email     │  │ Send activation email             │
└─────────────────────────────────────┘  └─────────────────────────────────────┘
         │                 │
         └────────┬────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ Display summary:                    │
│ "Activated/Renewed: X, Skipped: Y,  │
│  No matching transaction: Z"        │
└─────────────────────────────────────┘
```

### Workflow 2: Member Deceased Process

```
┌─────────────────────────────────────┐
│ Admin edits user profile            │
│ Checks "Passed Away" checkbox       │
│ (Optionally sets Date of Death)     │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ edit_user_profile_update hook fires │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Save to user meta:                  │
│ - pmpronbstup_deceased = 1          │
│ - pmpronbstup_deceased_date = date  │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Force deactivation:                 │
│ Set pmpronbstup_active = 0          │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Send admin notification email       │
│ "Member X has been marked deceased" │
└─────────────────────────────────────┘
```

### Workflow 3: Member Contribution Payment

```
┌─────────────────────────────────────┐
│ Member views account page:          │
│ [pmpro_account_nbstup] shortcode    │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Clicks "Contribution" link          │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Query deceased members               │
│ Paginate results (10 per page)      │
│ Display table with members          │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Member selects deceased person      │
│ Clicks "Pay your contribution"      │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Redirects to checkout:              │
│ ?level=1                            │
│ &contribution_for=<deceased_id>     │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Checkout form displays:             │
│ - Transaction ID field (required)   │
│ - Payment Receipt upload (required) │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ Member provides bank transfer info: │
│ - Transaction ID                    │
│ - Receipt file (PNG/JPG/PDF)        │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│ pmpro_after_checkout hook fires     │
│ Save data to user & order meta      │
└─────────────────────────────────────┘
```

### Workflow 4: Membership Expiry Management

```
┌─────────────────────────────────────┐
│ Daily cron job runs                 │
│ wp_scheduled_event_pmpronbstup_check_expiry │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ Get all active subscribers          │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ For each subscriber:                │
│ Check membership_expiry_date        │
└────────────────┬────────────────────┘
                  │
         ┌────────┴────────┐
         │                 │
         ▼                 ▼
    EXPIRED           NOT EXPIRED
 (expiry < today)    (expiry > today)
         │                 │
         ▼                 ▼
┌─────────────────────────────────────┐  ┌─────────────────────────────────────┐
│ Set renewal_status = 'expired'      │  │ Check days until expiry           │
│ Deactivate user                     │  │ If <= 30 days:                    │
│ Send "renewal required" email       │  │   Send expiry reminder email      │
└─────────────────────────────────────┘  └─────────────────────────────────────┘
```

### Workflow 5: Login Attempt

```
┌─────────────────────────────────────┐
│ User enters username + password     │
│ on WordPress login form             │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ authenticate filter fires           │
│ (priority 30)                       │
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ Check user has subscriber role?     │
│ If NO: allow login (bypass filter)  │
│ If YES: continue to activation check│
└────────────────┬────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────┐
│ pmpronbstup_is_user_active($id)?    │
│ Checks:                             │
│ - pmpronbstup_deceased = 1? → BLOCK │
│ - pmpronbstup_active ≠ 1? → BLOCK   │
│ - membership_expiry_date < today? → BLOCK │
└────────────────┬────────────────────┘
                  │
         ┌────────┴────────┐
         │                 │
         ▼                 ▼
      ALLOW              BLOCK
      LOGIN          (Show error)
```

---

## Security & Best Practices

### ✅ Security Measures Implemented

#### Authentication & Authorization
- [x] `wp_nonce_field()` & `wp_verify_nonce()` on CSV upload form
- [x] `current_user_can('manage_options')` capability checks
- [x] Role-based filtering (only affects subscribers)
- [x] Proper admin action hooks with permission validation

#### Input Sanitization
- [x] `sanitize_text_field()` for text inputs
- [x] `wp_unslash()` for POST data
- [x] `wp_handle_upload()` for file uploads with validation
- [x] Accept-only attribute on file inputs (`.csv`, `.png`, `.jpg`, `.jpeg`, `.pdf`)

#### Data Output
- [x] `esc_html()` for text content
- [x] `esc_attr()` for HTML attributes
- [x] `esc_url()` for URL outputs
- [x] `esc_html_e()` for translatable text

#### Database Queries
- [x] Prepared statements with `$wpdb->prepare()` for transaction_id lookup
- [x] Proper table references with `$wpdb->` prefix

#### File Security
- [x] `wp_handle_upload()` for secure file handling
- [x] File type validation at upload
- [x] File stored in WordPress uploads directory

#### Other
- [x] `if (!defined('ABSPATH'))` exit check in all files
- [x] Proper use of WordPress hooks and filters
- [x] No direct form submissions without nonces
- [x] No SQL injection vulnerable queries

---

## Build & Asset Processing

### Gulp Build System

#### Installation
```bash
cd wp-content/plugins/pmpro-nbstup
npm install
```

#### Build Commands
```bash
# Development (watch mode)
npm run dev

# Production build
npm run build
```

#### Processing Pipeline

**SCSS → CSS**
- Source: `assets/scss/frontend.scss`
- Output: `assets/css/frontend.css`
- Processors:
  - SCSS compilation via Sass
  - Autoprefixer (adds vendor prefixes)
  - CSSnano (minification)
  - Source maps for debugging

**JavaScript**
- Source: `assets/js/frontend.js`
- Output: `assets/js/dist/frontend.js`
- Processors:
  - Uglify (minification)
  - Concatenation (if multiple files)
  - Source maps

#### Dependencies
```json
{
  "gulp": "^4.0.2",
  "gulp-sass": "^5.1.0",
  "sass": "^1.79.4",
  "gulp-sourcemaps": "^3.0.0",
  "gulp-postcss": "^9.0.1",
  "autoprefixer": "^10.4.20",
  "cssnano": "^6.1.2",
  "gulp-uglify": "^3.0.2",
  "gulp-concat": "^2.6.1",
  "gulp-rename": "^2.0.0"
}
```

---

## Technical Details

### WordPress Hooks Used

#### Action Hooks
```php
plugins_loaded            → pmpronbstup_load_files
register_activation_hook  → pmpronbstup_activate
wp_enqueue_scripts        → pmpronbstup_enqueue_frontend_assets
admin_menu                → pmpronbstup_admin_menu
admin_init                → pmpronbstup_handle_csv_upload
show_user_profile         → pmpronbstup_user_profile_fields
edit_user_profile         → pmpronbstup_user_profile_fields
personal_options_update   → pmpronbstup_save_user_profile_fields
edit_user_profile_update  → pmpronbstup_save_user_profile_fields
wp_scheduled_event_pmpronbstup_check_expiry → pmpronbstup_check_all_expired_memberships
pmpro_checkout_form_enctype
pmpro_checkout_after_payment_information_fields → pmpro_add_bank_transfer_fields
pmpro_after_checkout      → pmpro_save_bank_transfer_data
pmpro_member_order_details_after → pmpro_show_bank_details_to_member
pmpro_order_details_after → pmpro_show_bank_details_in_admin
```

#### Filter Hooks
```php
authenticate              → pmpronbstup_authenticate (priority 30)
add_shortcode             → pmpro_account_nbstup
```

### Database Tables Used
```php
{$wpdb->pmpro_membership_orders}  // PMPro orders table
{$wpdb->usermeta}                 // WordPress user meta
{$wpdb->postmeta}                 // WordPress post meta (for order data)
{$wpdb->users}                    // WordPress users (via WP_User_Query)
```

### PMPro Dependencies
- `pmpro_url()` function - generates PMPro page URLs
- PMPro orders table structure
- PMPro gateway system (specifically "check" gateway for bank transfer)

### Constants Defined
```php
PMPRONBSTUP_VERSION       = '0.1.0'
PMPRONBSTUP_PLUGIN_DIR    = plugin directory path
PMPRONBSTUP_PLUGIN_URL    = plugin directory URL
PMPRONBSTUP_INCLUDES_DIR  = includes directory path
```

### Localization
- Text Domain: `pmpro-nbstup`
- All user-facing strings wrapped with `__()`, `esc_html_e()`, `_e()`
- Ready for translation

---

## Summary

The **PMPro NBSTUP** plugin is a production-ready, security-conscious addon that:

1. **Manages yearly recurring subscriptions** with automatic expiry and renewal tracking
2. **Processes CSV imports** for both initial activations and annual renewals
3. **Handles deceased members** with dedicated UI and blocked access
4. **Provides custom account layout** with navigation sidebar
5. **Captures bank transfer details** at checkout and displays them throughout
6. **Blocks inactive/expired subscribers** from logging in with proper error messages
7. **Sends automated email notifications** for activations, renewals, expiry reminders, and expired memberships
8. **Runs daily automated checks** for membership expiry and sends renewal reminders

The code is well-organized, properly sanitized, uses WordPress best practices, and is ready for production use. All functionality is isolated to subscribers, leaving admins and other user roles unaffected.
