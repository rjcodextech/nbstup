# PMPro NBSTUP Bank Import Addon

**Complete Plugin Documentation - All Features & Processes**

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Core Features](#core-features)
3. [User Activation Feature](#user-activation-feature)
4. [Membership Management](#membership-management)
5. [Deceased Member Handling](#deceased-member-handling)
6. [Daughter Wedding Contribution](#daughter-wedding-contribution)
7. [Contribution Verification Feature](#contribution-verification-feature)
8. [Users List Shortcode](#users-list-shortcode)
9. [Email Configuration](#email-configuration)
10. [Admin Interface](#admin-interface)
11. [User Profile Fields](#user-profile-fields)
12. [Checkout Fields](#checkout-fields)
13. [Email Notifications](#email-notifications)
14. [Scheduled Events](#scheduled-events)
15. [Technical Details](#technical-details)
16. [Installation & Deployment](#installation--deployment)
17. [Troubleshooting](#troubleshooting)

---

## Overview

**PMPro NBSTUP** is a custom addon for **Paid Memberships Pro** that manages **yearly recurring subscriptions** with bank transfer payments. It includes:

✅ **User Activation** - Verify payments via CSV import  
✅ **Yearly Membership** - Auto-set 1-year membership duration  
✅ **Auto-Deactivation** - Disable accounts when membership expires  
✅ **Deceased Members** - Mark and manage deceased members  
✅ **Daughter Wedding** - Request contributions for daughter weddings (multiple times)  
✅ **Contribution System** - Require contribution when member dies or has wedding  
✅ **Contributions Management** - Dedicated admin page to monitor and manage all contributions  
✅ **Users List Shortcode** - Display all users with search & pagination  
✅ **Email Notifications** - Automated emails for all events (fully configurable)  
✅ **Admin Notifications** - Daily summary of overdue contributions  
✅ **Checkout Fields** - Collect transaction IDs and receipts  

---

## Core Features

### 1. Default Deactivation
- **All subscriber accounts are deactivated by default** so they cannot log in
- Must be activated via CSV import verification or manual admin action
- Other user roles (admin, editor, etc.) are not affected

### 2. Bank Transfer Verification
- **Matches transaction IDs** from bank statements with subscriber bank transfer data
- **No amount validation** - any matching transaction ID activates/renews membership
- Uses `bank_transaction_id` user meta from checkout

### 3. Yearly Membership Duration
- **1-year membership** from activation/renewal date
- **Automatic expiry** when date is reached
- **Auto-renewal support** via CSV import

### 4. Membership Status Tracking
- Tracks membership start date
- Tracks membership expiry date
- Tracks renewal status (active, renewal, expired, contribution_overdue)
- Updates stored in user meta

---

## User Activation Feature

### How It Works

**Admin uploads CSV file with bank statement transaction IDs:**

1. Go to **Paid Memberships Pro > User Approval > User Activation** tab
2. Upload CSV file containing transaction IDs
3. Click "Import and Activate Matching Subscribers"

**System processes CSV:**
- Reads each row (skips header)
- Extracts transaction ID
- Looks up user with matching `bank_transaction_id` meta
- Checks if user is subscriber and not deceased
- **For new members**: Activates for 1 year, sends activation email
- **For renewals**: Extends expiry by 1 year, sends renewal email
- Reports: Activated/Renewed count, Skipped count, Not found count

### CSV Format Requirements

**File type:** `.csv`

**Required:** A column header containing word "transaction" (e.g., `transaction_id`, `transaction`, `txn_id`)

**Example CSV:**
```csv
transaction_id,amount,date,payer_name
BANK-2026-001,5000,2026-01-15,John Doe
BANK-2026-002,5000,2026-01-20,Jane Smith
BANK-2026-003,5000,2026-01-25,Alice Johnson
```

**Minimal CSV:**
```csv
transaction_id
BANK-2026-001
BANK-2026-002
BANK-2026-003
```

### Matching Process

1. Extract transaction ID from CSV row
2. Query database: Find user where `bank_transaction_id` = transaction ID
3. Validate:
   - User has `subscriber` role
   - User is not marked as deceased
4. Check if existing membership:
   - **No existing expiry** → New member activation
   - **Has existing expiry** → Renewal
5. Update user meta and send appropriate email

### New Member Activation
- Sets: `pmpronbstup_membership_start_date` = today
- Sets: `pmpronbstup_membership_expiry_date` = today + 1 year
- Sets: `pmpronbstup_active` = 1
- Sets: `pmpronbstup_renewal_status` = "active"
- Sends activation email with expiry date

### Member Renewal
- Updates: `pmpronbstup_membership_expiry_date` = today + 1 year
- Updates: `pmpronbstup_last_renewal_date` = today
- Sets: `pmpronbstup_renewal_status` = "active"
- Clears: `pmpronbstup_expiry_reminder_sent` (for next cycle)
- Sends renewal confirmation email with new expiry date

---

## Membership Management

### Membership States

| State | Active? | Can Login? | Notes |
|-------|---------|-----------|-------|
| **Not Activated** | No | No | No activation record |
| **Active** | Yes | Yes | Within membership period |
| **Expired** | No | No | Expiry date passed |
| **Deceased** | No | No | Marked as passed away |
| **Contribution Overdue** | No | No | Missed contribution deadline |

### Membership Lifecycle

```
Step 1: User Signs Up
  ├─ Account created
  ├─ pmpronbstup_active = 0 (inactive by default)
  └─ Cannot log in

Step 2: Bank Transfer Made
  ├─ User makes bank transfer
  ├─ Gets transaction ID
  └─ Waits for verification

Step 3: CSV Import / Activation
  ├─ Admin uploads CSV with transaction ID
  ├─ System matches and activates
  ├─ pmpronbstup_active = 1
  ├─ Sets expiry date (+1 year)
  └─ Sends activation email

Step 4: Member Active (Days 1-365)
  ├─ Can log in and access content
  ├─ Day 30-before-expiry: Receives reminder email
  └─ Can renew anytime via bank transfer

Step 5: Renewal (Within the year or after)
  ├─ Admin uploads CSV with new transaction ID
  ├─ System extends expiry by 1 year
  ├─ Sends renewal confirmation
  └─ Membership continues

Step 6: Expiry (After 365 days)
  ├─ Scheduled event checks daily
  ├─ Detects expiry date passed
  ├─ Auto-deactivates: pmpronbstup_active = 0
  ├─ Sends expiry notification
  └─ Cannot log in until renewed
```

### Automatic Expiry Check

**Runs:** Daily via scheduled event `wp_scheduled_event_pmpronbstup_check_expiry`

**Checks:**
- All subscribers with `pmpronbstup_active = 1`
- Compare `pmpronbstup_membership_expiry_date` with today
- If expiry date < today:
  - Sets `pmpronbstup_active = 0` (deactivate)
  - Sends expiry notification email
- If 30 days until expiry and not already sent:
  - Sends reminder email with renewal link

---

## Deceased Member Handling

### Marking Member as Deceased

**Location:** WordPress Users > Edit User Profile

**Fields:**
1. **Passed Away** - Checkbox to mark as deceased
2. **Date of Death** - Date picker for date of death

### What Happens

**When marked as deceased:**
1. User account automatically deactivated
2. Cannot be activated via CSV import
3. Cannot log in under any circumstances
4. All other active members automatically required to pay contribution

### Effects

| Feature | Effect |
|---------|--------|
| **Login** | Blocked with message: "This account has been marked as deceased" |
| **CSV Import** | Skipped - cannot be activated |
| **Contribution** | All active members required to pay |
| **Visibility** | Listed on contribution members page |

### Deceased Members List

**Location:** Member Dashboard > My Account > Contribution tab

**Shows:**
- Avatar
- Display name
- Date of death
- "Pay your contribution" button

---

## Contribution Verification Feature

### When Does It Activate?

When admin marks a member as deceased, **ALL active members are automatically required to pay contribution** within 1 month.

### How Contribution Works

#### Phase 1: Notification (Day 1)
```
Admin marks user as deceased
         ↓
System automatically:
├─ Deactivates deceased user
├─ Marks all active members to pay
├─ Sets deadline = 1 month from today
└─ Sends notification email to each member

Email Content:
"A member of our community has passed away. In their memory, 
 all active members are requested to pay a contribution.
 
 Contribution Deadline: [Date]
 
 Visit [Checkout Link] to pay your contribution."
```

#### Phase 2: Payment (Days 2-30)
```
Active members choose to:
├─ Make bank transfer with transaction ID
├─ Pay via online checkout
└─ Pay at event or other means
```

#### Phase 3: Verification (Day 31+)
```
Admin collects bank statements
         ↓
Goes to: User Approval > Contribution Verification
         ↓
Uploads CSV with transaction IDs of payers
         ↓
System automatically:
├─ Matches transaction IDs with users
├─ Marks matching users as paid
├─ Sends confirmation emails
└─ Users can log in again
```

#### Phase 4: Enforcement
```
If member doesn't pay:
         ↓
Daily scheduled event checks deadline
         ↓
If deadline passed AND not paid:
├─ Auto-deactivates user
├─ Sets status = "contribution_overdue"
├─ Sends overdue notification
└─ User cannot log in
         ↓
If member pays later:
├─ Admin marks as paid manually (or via CSV)
├─ Sends confirmation email
└─ User can log in again
```

### CSV Format for Contribution

**Same as user activation - column with "transaction" in header**

```csv
transaction_id,amount,date
BANK-2026-CONTRIB-001,5000,2026-02-05
BANK-2026-CONTRIB-002,5000,2026-02-10
BANK-2026-CONTRIB-003,5000,2026-02-15
```

### Manual Contribution Override

**If member pays by other means:**

1. Go to Users > [Member Name]
2. Under "Contribution Payment Status" section
3. Check "Mark contribution as paid"
4. Click "Update Profile"
5. User receives confirmation email
6. User can log in again

---

## Daughter Wedding Contribution

**Feature:** Request contributions from all active members when a member has a daughter wedding. **This can be triggered multiple times** for different weddings.

### How It Works

1. **Admin marks member for daughter wedding:**
   - Edit user profile
   - Check "Mark this member as having daughter wedding"
   - Set wedding date (optional)
   - Save profile

2. **System automatically:**
   - Marks all active members to pay wedding contribution
   - Sets deadline = 1 month from today
   - Sends notification emails to each member
   - Sends admin notification

3. **Members make payment:**
   - Via bank transfer with transaction ID
   - Via online checkout
   - Via other means

4. **Admin verifies payments:**
   - Go to: Memberships > User Approval > Wedding Contribution tab
   - Upload CSV with transaction IDs
   - System marks contributions as paid
   - Sends confirmation emails

### Multiple Weddings Support

Unlike deceased contributions, **wedding contributions can be requested multiple times**:
- Same member can have multiple daughter weddings over time
- Each new wedding creates a new contribution requirement
- Previous wedding contributions don't block new ones
- Deadline is updated each time

### Wedding vs Deceased Contributions

**Managed Separately:**
- Deceased contribution tracked separately from wedding contribution
- User can owe both types simultaneously
- Each has its own deadline, paid status, and transaction ID
- Both types visible in user profile

### Manual Override

**If member pays by other means:**
1. Go to Users > [Member Name]
2. Under "Daughter Wedding Contribution" section
3. Check "Mark contribution as paid"
4. Click "Update Profile"

---

## Users List Shortcode

**Display all users with search and pagination on any page.**

### Usage

```
[pmpro_nbstup_users_list]
```

**With custom items per page:**
```
[pmpro_nbstup_users_list per_page="30"]
```

### Features

- **Search**: Search by username, email, or display name
- **Pagination**: Navigate through pages with customizable items per page
- **Responsive**: Mobile-friendly table design
- **Sortable**: Users ordered by display name

### Displayed Information

| Column | Description |
|--------|-------------|
| ID | User ID |
| Name | Display name |
| Email | User email |
| Username | Login username |
| Active | Active/Inactive status badge |
| Deceased | Yes/No badge |
| Wedding | Yes/No badge |
| Membership Status | active, renewal, expired, etc. |
| Expiry Date | Membership expiry date |
| Deceased Contribution | Paid/Pending/- |
| Wedding Contribution | Paid/Pending/- |

### Search Functionality

- Case-insensitive wildcard search
- Searches in: username, email, display name
- Results count displayed
- Clear search button
- Preserves pagination

### Status Badges

**Color-coded for easy identification:**
- **Green badges**: Active, Yes, Paid
- **Red badges**: Inactive, No, Pending
- **Gray**: N/A or none

### Examples

**On a members page:**
```
[pmpro_nbstup_users_list]
```

**With custom pagination:**
```
[pmpro_nbstup_users_list per_page="50"]
```

**In template files:**
```php
<?php echo do_shortcode('[pmpro_nbstup_users_list per_page="25"]'); ?>
```

---

## Email Configuration

**All email templates are fully configurable from WordPress admin.**

### Location

**Memberships > Email Settings**

### Available Email Templates

1. **Deceased Member Contribution Required**
   - Sent when a member is marked as deceased
   - Notifies all active members to pay contribution

2. **Wedding Contribution Required**
   - Sent when a member is marked for daughter wedding
   - Notifies all active members to pay wedding contribution

3. **Deceased Contribution Confirmed**
   - Sent when deceased contribution payment is verified

4. **Wedding Contribution Confirmed**
   - Sent when wedding contribution payment is verified

5. **Expiry Reminder (30 days before)**
   - Sent 30 days before membership expires

6. **Membership Expired**
   - Sent when membership expires

7. **Membership Renewed**
   - Sent when renewal payment is verified

8. **Account Activated**
   - Sent on initial account activation

9. **Contribution Overdue**
   - Sent when contribution deadline passes without payment

### Available Placeholders

Use these in subject and body:

| Placeholder | Description |
|-------------|-------------|
| `{blogname}` | Site name |
| `{display_name}` | User display name |
| `{deadline}` | Contribution deadline |
| `{expiry_date}` | Membership expiry date |
| `{current_date}` | Current date |
| `{account_url}` | Account URL |
| `{days_until_expiry}` | Days until expiry |

### Example Template

**Subject:**
```
[{blogname}] Wedding Contribution Required
```

**Body:**
```
Hello {display_name},

A member of our community is celebrating their daughter's wedding. 
In honor of this joyous occasion, all active members are requested 
to pay a contribution.

Contribution Deadline: {deadline}

Please visit your account: {account_url}

Thank you for your support.

Best regards,
{blogname}
```

### Reset to Defaults

Click **"Reset All to Defaults"** button to restore original templates.

---

## Admin Interface

### Memberships Menu

Admin pages located under **Paid Memberships Pro** menu in sidebar:

#### 1. User Approval (CSV Import)
**Location:** Memberships > User Approval

**Three Tabs:**

**Tab 1: User Activation**
- CSV upload for user activation/renewal
- Description of bank transfer matching process
- Info about deceased member flag

**Process:**
1. Upload CSV with transaction IDs from bank statements
2. System activates/renews matching subscribers
3. Sends appropriate emails
4. Shows results (activated count, skipped, not found)

**Tab 2: Deceased Contribution**
- CSV upload for deceased contribution payment verification
- Description of deceased contribution verification process
- Info about contribution feature

**Process:**
1. Upload CSV with transaction IDs of members who paid deceased contribution
2. System marks matching members as paid
3. Sends confirmation emails
4. Shows results (verified count, skipped, not found)

**Tab 3: Wedding Contribution**
- CSV upload for wedding contribution payment verification
- Description of wedding contribution verification process
- Info about multiple wedding support

**Process:**
1. Upload CSV with transaction IDs of members who paid wedding contribution
2. System marks matching members as paid
3. Sends confirmation emails
4. Shows results (verified count, skipped, not found)

#### 2. Contributions Management
**Location:** Memberships > Contributions

**Purpose:** Comprehensive contribution management and overview

**Features:**

**Statistics Dashboard**
- Total members with contributions
- Deceased contributions count (paid/unpaid)
- Wedding contributions count (paid/unpaid)
- Visual card-based dashboard

**Filters and Search**
- Filter by type: All Types | Deceased Only | Wedding Only
- Filter by status: All Statuses | Paid | Unpaid
- Search by username, email, or display name
- Reset filters button

**Contributions Table**
- User ID, Name, Email
- Deceased contribution status (Paid/Unpaid/—)
- Wedding contribution status (Paid/Unpaid/—)
- Deadline dates for unpaid contributions
- Action buttons for each user

**Bulk Actions**
- Select multiple users with checkboxes
- Mark Deceased as Paid (bulk)
- Mark Wedding as Paid (bulk)
- Automatically sends confirmation emails

**Individual Actions**
- Mark Deceased Paid button (per user)
- Mark Wedding Paid button (per user)
- Edit User link (direct to user profile)

**Quick Links**
- Upload Deceased CSV (→ User Approval tab)
- Upload Wedding CSV (→ User Approval tab)
- Email Settings (→ Email configuration)

**Use Cases:**
1. **Monitor contribution status** - See all outstanding contributions at a glance
2. **Mark manual payments** - When member pays via cash/check/other methods
3. **Bulk processing** - Process multiple payments at once
4. **Track deadlines** - View which contributions are nearing deadline
5. **Send confirmations** - Automatically email members when marked as paid

#### 3. Email Settings
**Location:** Memberships > Email Settings

**Purpose:** Configure all email templates with custom content

**See "Email Configuration" section for details**

---

## User Profile Fields

### Admin View (wp-admin > Users > Edit User)

#### NBSTUP Membership Flags Section
- **Passed Away** [Checkbox] - Mark member as deceased
- **Date of Death** [Date Field] - When they passed away
- **Daughter Wedding** [Checkbox] - Mark member for daughter wedding
- **Wedding Date** [Date Field] - Wedding date

#### Membership Status Section (Read-Only)
- **Active Status** - Active or Inactive
- **Renewal Status** - active | renewal | expired | contribution_overdue
- **Membership Start Date** - Activation date (Y-m-d)
- **Membership Expiry Date** - When membership expires (Y-m-d)
- **Last Renewal Date** - Last renewal date (Y-m-d)

#### Contribution Payment Status Section

**Deceased Member Contribution:**
- **Contribution Required** - Yes or No
- **Contribution Paid** [Checkbox] - Check to manually mark as paid (if required)
- **Contribution Deadline** - Payment deadline (Y-m-d)

**Daughter Wedding Contribution:**
- **Contribution Required** - Yes or No
- **Contribution Paid** [Checkbox] - Check to manually mark as paid (if required)
- **Contribution Deadline** - Payment deadline (Y-m-d)

### Member View (Frontend Account Dashboard)

**Custom shortcode: `[pmpro_account_nbstup]`**

**Two-column layout:**
- **Left sidebar** - Navigation menu with links to:
  - Account Overview
  - My Memberships
  - Order / Invoice History
  - Contribution (for viewing deceased members)

- **Right content** - Displays:
  - Account overview
  - Current membership status
  - Order history
  - Contribution list (if accessing contribution tab)

---

## Checkout Fields

### Transaction ID Field
- **Label:** "Transaction ID"
- **Type:** Text input
- **Required:** Yes (for bank transfer gateway)
- **Saved to:**
  - `bank_transaction_id` user meta
  - `bank_transaction_id` order meta
- **Used by:** CSV import to match payments

### Payment Receipt Field
- **Label:** "Payment Receipt"
- **Type:** File upload
- **Accepts:** .png, .jpg, .jpeg, .pdf
- **Required:** Yes (for bank transfer gateway)
- **Saved to:**
  - `bank_payment_receipt` user meta (URL)
  - `bank_payment_receipt` order meta (URL)
- **Visible:** In member order history and admin

### Field Display
- Only shows for "check" (bank transfer) gateway
- Enctype automatically set to multipart/form-data
- Fields appear in "Bank Transfer Details" section
- Proper validation and error messages

### File Storage
- Files uploaded to WordPress media library
- URL stored in user/order meta
- Admin can view receipt in order details
- Member can view receipt in dashboard

---

## Email Notifications

### 1. User Activation Email
**Sent to:** New members after CSV verification  
**Subject:** `[Site Name] Your account has been activated`  
**Contains:**
- User greeting
- Confirmation of activation
- Membership duration (1 year)
- Activation date
- Expiry date
- Thank you message

### 2. Renewal Confirmation Email
**Sent to:** Existing members after renewal CSV import  
**Subject:** `[Site Name] Your Membership Has Been Renewed`  
**Contains:**
- User greeting
- Confirmation of renewal
- New expiry date
- Thank you message

### 3. Expiry Reminder Email
**Sent to:** Members 30 days before expiry  
**Subject:** `[Site Name] Your Membership Expires in 30 Days`  
**Contains:**
- User greeting
- Expiry date warning
- Number of days remaining
- Link to checkout/renewal
- Thank you message

### 4. Membership Expired Email
**Sent to:** Members when membership expires  
**Subject:** `[Site Name] Your Membership Has Expired`  
**Contains:**
- User greeting
- Notification of expiry
- Membership suspension notice
- Link to renew membership
- Thank you message

### 5. Contribution Required Email
**Sent to:** All active members when someone dies  
**Subject:** `[Site Name] Contribution Payment Required`  
**Contains:**
- User greeting
- Notification of deceased member
- Contribution requirement
- Payment deadline
- Link to checkout
- Thank you message

### 6. Contribution Overdue Email
**Sent to:** Members when contribution deadline passed  
**Subject:** `[Site Name] Your Contribution Payment is Overdue`  
**Contains:**
- User greeting
- Deadline passed notification
- Account deactivation notice
- Link to pay contribution
- Thank you message

### 7. Contribution Confirmed Email
**Sent to:** Members after contribution CSV verification  
**Subject:** `[Site Name] Your Contribution Has Been Verified`  
**Contains:**
- User greeting
- Payment confirmation
- Account reactivation notice
- Thank you message

### 8. Deceased Notification Email
**Sent to:** Admin when member marked as deceased  
**Subject:** `Member Marked as Deceased`  
**Contains:**
- Member name
- Admin notification

### 9. Admin Overdue Contributions Summary
**Sent to:** Admin when daily cron detects overdue contributions  
**Subject:** `[Site Name] Overdue Contributions Summary`  
**Contains:**
- Count of overdue deceased contributions
- List of members with overdue deceased payments (name, ID, deadline)
- Count of overdue wedding contributions
- List of members with overdue wedding payments (name, ID, deadline)
- Direct link to Contributions Management page

**Frequency:** Once per day (when cron runs and finds overdue contributions)

**Purpose:** 
- Keep admin informed of payment issues
- Consolidate multiple overdue notifications into single email
- Enable quick action via management page link

---

## Scheduled Events

### Scheduled Event 1: Membership Expiry Check
**Hook:** `wp_scheduled_event_pmpronbstup_check_expiry`  
**Frequency:** Daily (midnight)  
**Purpose:** Check and process membership expirations

**What it does:**
1. Gets all subscribers with `pmpronbstup_active = 1`
2. For each user:
   - Check if `pmpronbstup_membership_expiry_date` < today
   - If expired:
     - Deactivate user (`pmpronbstup_active = 0`)
     - Set renewal_status = "expired"
     - Send expiry email
   - If 30 days until expiry:
     - Send reminder email
     - Mark reminder as sent (per month)

### Scheduled Event 2: Contribution Deadline Check
**Hook:** `wp_scheduled_event_pmpronbstup_check_contribution`  
**Frequency:** Daily (midnight)  
**Purpose:** Check and process contribution payment deadlines

**What it does:**

**For Deceased Contributions:**
1. Gets all users with `pmpronbstup_contribution_deceased_required = 1`
2. For each user:
   - Check if `pmpronbstup_contribution_deceased_deadline` < today
   - Skip if `pmpronbstup_contribution_deceased_paid = 1`
   - If overdue:
     - Deactivate user (`pmpronbstup_active = 0`)
     - Set renewal_status = "contribution_overdue"
     - Send overdue email to user
     - Add to overdue list for admin summary

**For Wedding Contributions:**
1. Gets all users with `pmpronbstup_contribution_wedding_required = 1`
2. For each user:
   - Check if `pmpronbstup_contribution_wedding_deadline` < today
   - Skip if `pmpronbstup_contribution_wedding_paid = 1`
   - If overdue:
     - Deactivate user (`pmpronbstup_active = 0`)
     - Set renewal_status = "contribution_overdue"
     - Send overdue email to user
     - Add to overdue list for admin summary

**Admin Notification:**
- If any contributions are overdue, sends single summary email to admin
- Lists all members with overdue deceased contributions
- Lists all members with overdue wedding contributions
- Includes member names, IDs, deadlines
- Provides link to Contributions Management page

**Why Admin Notifications:**
- Monitor contribution payment issues at a glance
- Quickly identify members who need follow-up
- Single daily digest instead of multiple emails
- Direct link to manage contributions

### WordPress Cron Requirement

These scheduled events use **WordPress Cron** (not system cron).

**To enable WordPress Cron:**
```php
// In wp-config.php, ensure this is NOT disabled:
// define('DISABLE_WP_CRON', true);

// Should be either false or not defined:
define('DISABLE_WP_CRON', false); // Or remove this line
```

**Automatic triggering:**
- Runs when WordPress page is visited after scheduled time
- Approximately accurate (within a page load of scheduled time)
- Does not require system cron setup

---

## Technical Details

### User Meta Fields - Membership

| Meta Key | Data Type | Example | Purpose |
|----------|-----------|---------|---------|
| `pmpronbstup_active` | Integer (0 or 1) | 1 | User is active/can login |
| `pmpronbstup_deceased` | Integer (0 or 1) | 0 | User marked as deceased |
| `pmpronbstup_deceased_date` | String (Y-m-d) | 2026-01-19 | Date of death |
| `pmpronbstup_daughter_wedding` | Integer (0 or 1) | 1 | User marked for daughter wedding |
| `pmpronbstup_wedding_date` | String (Y-m-d) | 2026-06-15 | Wedding date |
| `pmpronbstup_membership_start_date` | String (Y-m-d) | 2026-01-19 | When membership started |
| `pmpronbstup_membership_expiry_date` | String (Y-m-d) | 2027-01-19 | When membership expires |
| `pmpronbstup_renewal_status` | String | active | Status: active, renewal, expired, contribution_overdue |
| `pmpronbstup_last_renewal_date` | String (Y-m-d) | 2026-01-19 | Last time renewed |
| `pmpronbstup_expiry_reminder_sent` | Integer (0 or 1) | 1 | Reminder email sent |
| `pmpronbstup_expiry_email_sent_[YM]` | Integer (0 or 1) | 1 | Monthly tracking flag |

### User Meta Fields - Deceased Contribution

| Meta Key | Data Type | Example | Purpose |
|----------|-----------|---------|---------|
| `pmpronbstup_contribution_deceased_required` | Integer (0 or 1) | 1 | User must pay deceased contribution |
| `pmpronbstup_contribution_deceased_deadline` | String (Y-m-d) | 2026-02-19 | When deceased payment due |
| `pmpronbstup_contribution_deceased_paid` | Integer (0 or 1) | 1 | Deceased contribution paid |
| `pmpronbstup_contribution_deceased_transaction_id` | String | BANK-001 | Deceased payment transaction ID |

### User Meta Fields - Wedding Contribution

| Meta Key | Data Type | Example | Purpose |
|----------|-----------|---------|---------|
| `pmpronbstup_contribution_wedding_required` | Integer (0 or 1) | 1 | User must pay wedding contribution |
| `pmpronbstup_contribution_wedding_deadline` | String (Y-m-d) | 2026-03-15 | When wedding payment due |
| `pmpronbstup_contribution_wedding_paid` | Integer (0 or 1) | 1 | Wedding contribution paid |
| `pmpronbstup_contribution_wedding_transaction_id` | String | BANK-002 | Wedding payment transaction ID |

### User Meta Fields - Bank Transfer

| Meta Key | Data Type | Example | Purpose |
|----------|-----------|---------|---------|
| `bank_transaction_id` | String | BANK-2026-001 | Bank transfer transaction ID |
| `bank_payment_receipt` | String (URL) | https://.../receipt.pdf | Payment receipt file URL |

### Security Features

✅ **Nonce Verification** - All forms protected with WordPress nonces  
✅ **Capability Checks** - Only users with `manage_options` can access admin  
✅ **Input Sanitization** - All user inputs sanitized with `sanitize_text_field()`  
✅ **Output Escaping** - All data escaped: `esc_html()`, `esc_attr()`, `esc_url()`  
✅ **Database Protection** - All queries use `$wpdb->prepare()` (prepared statements)  
✅ **Type Validation** - All meta values type-checked before use  
✅ **File Upload** - Validated file types (.csv only for imports, .png/.jpg/.pdf for receipts)  

---

## Installation & Deployment

### Requirements

✅ **WordPress** 5.0 or higher  
✅ **Paid Memberships Pro** (PMPro) plugin installed and activated  
✅ **PHP** 7.2 or higher  
✅ **WordPress Cron** enabled (default)  

### Installation Steps

1. **Upload Plugin Files**
   - Upload `pmpro-nbstup` folder to `/wp-content/plugins/`

2. **Activate Plugin**
   - Go to WordPress Dashboard > Plugins
   - Find "PMPro NBSTUP Addon"
   - Click "Activate"
   - Activation hook automatically registers scheduled events

3. **Verify Checkout Fields**
   - Go to PMPro > Settings > Checkout
   - Verify "Transaction ID" and "Payment Receipt" fields appear for bank transfer gateway

4. **Add Account Page**
   - Create or edit page for member account
   - Use shortcode: `[pmpro_account_nbstup]` (or `[pmpro_account]` for standard layout)

5. **Test**
   - Create test user with "subscriber" role
   - Go to Users > Edit and verify new fields appear
   - Test CSV import with sample data

### First-Time Setup

**1. Create Test Member**
- Add new subscriber user
- Note the user ID

**2. Set Up Bank Transfer Gateway** (in PMPro settings)
- Choose "Check" gateway
- Configure payment details

**3. Test Transaction ID Collection**
- Go to checkout
- Verify transaction ID field appears
- Upload test receipt
- Verify fields saved to user meta

**4. Test User Activation**
- Create CSV with test transaction ID
- Upload to User Approval > User Activation tab
- Verify user activated
- Verify activation email sent

**5. Test Contribution Feature**
- Mark a user as deceased
- Verify all other active users marked for contribution
- Create contribution CSV
- Upload to User Approval > Contribution Verification tab
- Verify users marked as paid

### Post-Deployment Checklist

- [ ] Plugin activated successfully
- [ ] No PHP errors in debug log
- [ ] Scheduled events registered
- [ ] User profile fields show correctly
- [ ] Checkout fields appear for bank transfer
- [ ] CSV import works with test data
- [ ] Emails send successfully
- [ ] Scheduled deadline checks run daily

---

## Troubleshooting

### Issue: CSV Import Shows "No Matching Transaction"

**Cause:** Transaction IDs don't match between CSV and user meta

**Solution:**
1. Verify user has `bank_transaction_id` meta:
   - Edit user profile in wp-admin
   - Check that they made a bank transfer with transaction ID
   - Transaction ID should match exactly (case-sensitive)

2. Check CSV format:
   - Ensure column header contains word "transaction"
   - Example: `transaction_id`, `transaction`, `txn_id`
   - Remove extra spaces or special characters from IDs

3. Verify data:
   - Open CSV in text editor (not Excel - can corrupt data)
   - Check for extra spaces before/after transaction IDs
   - Ensure column index is correct

**Example correct CSV:**
```
transaction_id
BANK-2026-001
BANK-2026-002
```

### Issue: Emails Not Sending

**Cause:** WordPress mail configuration issue

**Solution:**
1. Test WordPress email:
   - Install "Check & Log Emails" plugin
   - Verify emails are being sent
   - Check email log

2. Check mail configuration:
   - Most hosts require SMTP configuration
   - Use "WP Mail SMTP" plugin for proper configuration
   - Set up with Gmail, SendGrid, or other SMTP service

3. Verify email addresses:
   - Check user email addresses are valid
   - Test email sending to admin first
   - Check spam folder

### Issue: Scheduled Events Not Running

**Cause:** WordPress Cron disabled

**Solution:**
1. Check wp-config.php:
   ```php
   // WRONG (disables WordPress Cron):
   define('DISABLE_WP_CRON', true);
   
   // CORRECT (enables WordPress Cron):
   define('DISABLE_WP_CRON', false);
   // OR just remove the line
   ```

2. If using system cron instead:
   - Add to system crontab:
   ```
   */15 * * * * wget -q -O - https://yoursite.com/wp-cron.php >/dev/null 2>&1
   ```

3. Manually trigger for testing:
   - Visit: `https://yoursite.com/wp-cron.php`
   - Or use WP CLI: `wp cron event list`

### Issue: User Cannot Log In

**Possible Causes:**

1. **User not activated**
   - Check: `pmpronbstup_active` meta = 1
   - Solution: Upload CSV or activate manually on user profile

2. **Membership expired**
   - Check: `pmpronbstup_membership_expiry_date` < today
   - Solution: Renew via CSV import

3. **Marked as deceased**
   - Check: `pmpronbstup_deceased` meta = 1
   - Solution: Only site admin can uncheck this

4. **Contribution overdue**
   - Check: `pmpronbstup_contribution_required` = 1 AND `pmpronbstup_contribution_paid` = 0
   - Solution: Upload contribution CSV or mark as paid manually

**How to check meta values:**
- WordPress Admin > Users > Edit User
- Scroll to respective section (Membership Status, Contribution Payment Status)
- Values display in read-only format

### Issue: CSV Import Shows All Users Skipped

**Possible Causes:**

1. **No transaction ID column found**
   - Solution: Ensure header contains "transaction" word
   - Example: `transaction_id` OR `transaction` OR `txn_id`

2. **User doesn't exist or has wrong role**
   - Solution: Verify user exists and has "subscriber" role

3. **User marked as deceased**
   - Solution: Deceased users cannot be activated via CSV

4. **User already active (not renewal)**
   - Solution: Already activated users are skipped unless renewing

### Issue: Contribution Feature Not Triggering

**Cause:** User not properly marked as deceased

**Solution:**
1. Go to Users > Edit User
2. Under "NBSTUP Membership Flags"
3. Check "Passed Away" checkbox
4. Set "Date of Death"
5. Click "Update Profile"
6. Verify all other active users marked for contribution

**How to verify:**
- Edit another active user
- Check if "Contribution Required" section shows "Yes"
- Check if deadline is set to 1 month from deceased user mark date

---

## Quick Reference

### Common Tasks

**Activate a New Member**
1. Member makes bank transfer with transaction ID: BANK-001
2. Admin goes to User Approval > User Activation
3. Admin uploads CSV with transaction ID
4. System activates member, sends email

**Renew Existing Member**
1. Member makes renewal bank transfer with new transaction ID: BANK-002
2. Admin uploads CSV with transaction ID
3. System extends membership by 1 year, sends confirmation

**Mark Member as Deceased**
1. Go to Users > Edit Member
2. Check "Passed Away"
3. Set "Date of Death"
4. Click "Update Profile"
5. All active members auto-marked for contribution

**Verify Contribution Payments**
1. Collect bank statements with transaction IDs
2. Go to User Approval > Contribution Verification
3. Upload CSV with transaction IDs
4. System marks matching members as paid

**Manually Mark Contribution as Paid**
1. Go to Users > Edit Member
2. Scroll to "Contribution Payment Status"
3. Check "Mark contribution as paid"
4. Click "Update Profile"

### Important Reminders

⚠️ **Transaction IDs must match exactly** (case-sensitive)  
⚠️ **CSV must have "transaction" in header** (any variation works)  
⚠️ **Only subscribers can be activated** (not other roles)  
⚠️ **Deceased users cannot be activated** (must uncheck flag first)  
⚠️ **Contribution auto-deactivates after deadline** (if not paid)  
⚠️ **Scheduled events need WordPress Cron enabled**  
⚠️ **Email sending requires proper mail configuration**  

---

## Support & Documentation

For detailed information, refer to plugin settings and user profiles.

**Plugin Features:**
- ✅ User activation via CSV import
- ✅ Membership renewal support
- ✅ Auto-deactivation on expiry
- ✅ Email notifications for all events
- ✅ Deceased member handling
- ✅ Contribution verification system
- ✅ Contribution auto-enforcement
- ✅ Checkout transaction ID collection
- ✅ Payment receipt storage
- ✅ Member dashboard
- ✅ Admin controls
- ✅ Security features

**Version:** 0.1.0  
**Last Updated:** January 19, 2026  
**Status:** Production Ready

