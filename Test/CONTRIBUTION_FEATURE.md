# Contribution Verification Feature

## Overview
This document describes the new contribution verification feature added to the PMPro NBSTUP plugin. This feature allows the admin to manage contribution payments when a member is marked as deceased.

## Feature Description

### What Happens When a Member is Marked as Deceased

1. **All active members are notified** - Each active subscriber receives an email notifying them that a member has passed away and they are required to pay a contribution in their memory.

2. **1-month payment deadline** - Users have 1 month from the notification date to pay the contribution.

3. **Auto-deactivation after deadline** - If a user does not pay the contribution within 1 month, their account is automatically deactivated and they cannot log in.

4. **Login restriction** - Users who have a contribution requirement but haven't paid will see a specific error message with the deadline when they try to log in.

### User Meta Fields Added

```
pmpronbstup_contribution_required (bool)           - Whether user needs to pay contribution
pmpronbstup_contribution_deadline (string Y-m-d)   - Deadline for contribution payment
pmpronbstup_contribution_paid (bool)                - Whether contribution is paid
pmpronbstup_contribution_transaction_id (string)   - Transaction ID of contribution payment
```

---

## Admin Features

### Tab 1: User Activation
- Upload CSV with bank transfer transaction IDs to activate/renew subscriber memberships
- Same functionality as before

### Tab 2: Contribution Verification
- Upload CSV file containing transaction IDs of members who have paid their contribution
- Admin matches the transaction IDs against user `bank_transaction_id` meta
- When a match is found and user has a contribution requirement, the user is marked as having paid
- User receives a confirmation email

### User Profile Updates
- New "Contribution Payment Status" section shows:
  - Whether contribution is required (Yes/No)
  - If required, shows checkbox to manually mark contribution as paid
  - Shows the contribution payment deadline
- Admin can manually mark contribution as paid if needed

---

## Workflow

### Step 1: Mark Member as Deceased
1. Go to Users > [Member Name]
2. Check "Passed Away" checkbox
3. Set "Date of Death"
4. Save

**Result**: All active members are automatically:
- Marked as requiring contribution
- Notified via email with deadline (1 month from today)
- Cannot log in until contribution is marked as paid

### Step 2: Verify Contribution Payments
1. Collect bank statements with transaction IDs from members who paid contribution
2. Go to "User Approval" > "Contribution Verification" tab
3. Upload CSV file with transaction IDs
4. System matches transaction IDs with users who have contribution requirements
5. Matching users are marked as having paid contribution

**Result**: Users who paid contribution:
- Can log in again
- Receive confirmation email
- Contribution deadline is cleared

### Step 3: Auto-Deactivation (Automatic)
- Daily scheduled event checks all users with contribution requirements
- If deadline has passed and contribution not paid:
  - User account is automatically deactivated
  - User receives notification email
  - User cannot log in until contribution is paid

---

## CSV Format for Contribution Verification

### Required Format
The CSV file must contain a column with a header containing the word "transaction" (e.g., `transaction_id`, `transaction`, `txn_id`, etc.)

### Example CSV
```csv
transaction_id,amount,date
TXN001,5000,2026-01-19
TXN002,5000,2026-01-19
TXN003,5000,2026-01-20
```

### What Happens During Import
1. System reads each row (skips header)
2. Extracts transaction ID
3. Looks up user with matching `bank_transaction_id` user meta
4. If user has contribution requirement, marks contribution as paid
5. Sends confirmation email to user
6. Reports import statistics (verified, skipped, not found)

---

## Email Notifications

### 1. Contribution Required Email
Sent to all active members when someone is marked deceased:
- Informs about the deceased member
- States contribution is required
- Provides deadline
- Links to checkout/payment

### 2. Contribution Overdue Email
Sent when deadline passes and user hasn't paid:
- Informs that deadline has passed
- States account has been deactivated
- Requires payment to reactivate
- Links to checkout

### 3. Contribution Confirmation Email
Sent when contribution payment is verified via CSV import:
- Confirms payment received
- Thanks user for contribution
- Confirms account is active

---

## Login Behavior

### For Subscribers with Contribution Requirement

If contribution required but not paid:
```
Error: Your contribution payment is required by [date]. 
Please pay the contribution to access your account.
```

If deadline has passed:
Account is deactivated automatically and user sees:
```
Error: Your account is not active. Please contact support 
or renew your membership.
```

---

## Scheduled Events

Two daily cron events run:

1. **wp_scheduled_event_pmpronbstup_check_expiry**
   - Checks membership expiry dates
   - Deactivates expired memberships
   - Sends expiry reminders (30 days before)

2. **wp_scheduled_event_pmpronbstup_check_contribution**
   - Checks contribution payment deadlines
   - Deactivates users with overdue contribution
   - Sends overdue notification emails

---

## Manual Admin Actions

### Mark Contribution as Paid Manually
1. Go to Users > [Member Name]
2. Scroll to "Contribution Payment Status"
3. Check "Mark contribution as paid"
4. Save

### Remove Contribution Requirement Manually
1. Edit user profile
2. Update contribution requirement meta (requires code/database)

---

## Plugin Updates

### Files Modified
1. **pmpro-nbstup.php**
   - Added scheduled event for contribution checks

2. **includes/functions-core.php**
   - Added `pmpronbstup_mark_contribution_required($user_id)` - Mark all active users to pay contribution
   - Added `pmpronbstup_send_contribution_required_email($user_id, $deadline)` - Send notification
   - Added `pmpronbstup_check_contribution_deadlines()` - Check for overdue contributions
   - Added `pmpronbstup_send_contribution_overdue_email($user_id)` - Overdue notification
   - Added `pmpronbstup_send_contribution_confirmation_email($user_id)` - Confirmation email
   - Added `pmpronbstup_is_user_active_with_contribution($user_id)` - Check with contribution status

3. **includes/functions-auth.php**
   - Updated `pmpronbstup_authenticate()` - Added contribution requirement check on login

4. **includes/functions-admin.php**
   - Updated `pmpronbstup_render_admin_page()` - Added tabs
   - Added `pmpronbstup_render_user_activation_csv_form()` - User activation tab
   - Added `pmpronbstup_render_contribution_csv_form()` - Contribution verification tab

5. **includes/functions-csv.php**
   - Added `pmpronbstup_handle_contribution_csv_upload()` - Process contribution CSV uploads

6. **includes/functions-user-profile.php**
   - Updated `pmpronbstup_user_profile_fields()` - Added contribution status section
   - Updated `pmpronbstup_save_user_profile_fields()` - Added contribution payment save logic

---

## Testing Checklist

- [ ] Mark a user as deceased - check if all active users are marked for contribution
- [ ] Verify contribution requirement email is sent
- [ ] Upload contribution CSV - check if users are marked as paid
- [ ] Verify confirmation email is sent
- [ ] Test login for user with unpaid contribution - should show deadline error
- [ ] Wait for deadline to pass - check if auto-deactivated
- [ ] Verify overdue email is sent
- [ ] Manually mark contribution as paid - verify login works
- [ ] Check scheduled events are running (WordPress Cron)

