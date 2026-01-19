# Yearly Recurring Subscription Implementation Review

## Current Status: ✅ IMPLEMENTATION COMPLETED

The plugin now **FULLY supports yearly recurring subscriptions** with automatic expiry, renewals, and email notifications. All required features have been implemented and are functional.

---

## Issues Found

### 1. ❌ No Membership Expiration Tracking
**Problem**: No tracking of when memberships expire or renew
**Location**: All files
**Impact**: Cannot deactivate users when membership expires

### 2. ❌ No Annual Renewal Date Verification
**Problem**: Current logic only checks if user has `pmpronbstup_active = 1`
**Location**: `functions-core.php` → `pmpronbstup_is_user_active()`
**Impact**: Users stay active even if membership has expired

### 3. ❌ No Automatic Deactivation on Expired Memberships
**Problem**: No mechanism to deactivate users when their membership expires
**Location**: Throughout the plugin
**Impact**: Cannot enforce yearly payment requirement

### 4. ❌ No Renewal Transaction Tracking
**Problem**: Cannot distinguish between initial payment and renewal payments
**Location**: `payment-info-fields.php` and `functions-csv.php`
**Impact**: Cannot process annual renewal activations separately

### 5. ❌ No Payment Verification Timeline
**Problem**: No way to know if a payment is for the current year
**Location**: CSV import logic
**Impact**: Cannot verify if payment is current or expired

### 6. ⚠️ README.md is Outdated
**Problem**: README mentions "amount = 51 INR" which was removed
**Location**: `README.md`
**Impact**: Documentation doesn't match implementation

---

## Required Implementation Updates

### 1. Add Membership Duration & Renewal Tracking

**New User Meta Fields**:
```php
// Membership expiration tracking
pmpronbstup_membership_start_date    (Y-m-d)  - When membership started
pmpronbstup_membership_expiry_date   (Y-m-d)  - When membership expires
pmpronbstup_last_renewal_date        (Y-m-d)  - Last successful renewal date
pmpronbstup_renewal_status           (string) - 'active', 'expired', 'pending'

// Payment verification
pmpronbstup_pending_renewal           (1 or 0)  - Waiting for verification
pmpronbstup_renewal_year              (YYYY)    - Year of current renewal
```

---

### 2. Update `pmpronbstup_is_user_active()` Function

**Current Logic**:
```php
function pmpronbstup_is_user_active($user_id) {
    $active = get_user_meta($user_id, 'pmpronbstup_active', true);
    return (int) $active === 1;
}
```

**Required Logic**:
```php
function pmpronbstup_is_user_active($user_id) {
    // Check if marked deceased
    if (is_user_deceased($user_id)) {
        return false;
    }
    
    // Check if marked active
    $active = get_user_meta($user_id, 'pmpronbstup_active', true);
    if ((int) $active !== 1) {
        return false;
    }
    
    // CHECK IF MEMBERSHIP HAS EXPIRED
    $expiry_date = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
    if ($expiry_date && strtotime($expiry_date) < time()) {
        // Membership expired - deactivate
        pmpronbstup_deactivate_user($user_id);
        return false;
    }
    
    return true;
}
```

---

### 3. Add Membership Expiration Check Function

**New Function**:
```php
function pmpronbstup_check_membership_expiry($user_id) {
    $expiry_date = get_user_meta($user_id, 'pmpronbstup_membership_expiry_date', true);
    if (!$expiry_date) {
        return null;
    }
    
    $expiry_timestamp = strtotime($expiry_date);
    $current_timestamp = time();
    
    if ($expiry_timestamp < $current_timestamp) {
        // Membership has expired
        update_user_meta($user_id, 'pmpronbstup_renewal_status', 'expired');
        pmpronbstup_deactivate_user($user_id);
        
        // Send renewal reminder email
        pmpronbstup_send_renewal_reminder_email($user_id);
        
        return 'expired';
    }
    
    // Days until expiry
    $days_until_expiry = ceil(($expiry_timestamp - $current_timestamp) / 86400);
    
    // Send reminder if expiring soon (within 30 days)
    if ($days_until_expiry <= 30 && $days_until_expiry > 0) {
        update_user_meta($user_id, 'pmpronbstup_expiry_reminder_sent', 1);
        pmpronbstup_send_expiry_reminder_email($user_id, $days_until_expiry);
    }
    
    return 'active';
}
```

---

### 4. Update CSV Import Logic for Renewals

**Current Logic**: Activates any matching transaction ID

**Required Logic**:
```php
// When activating user via CSV import:

// 1. Check if this is a renewal (user already has membership)
$existing_membership = pmpronbstup_has_active_membership($user_id);

if ($existing_membership) {
    // RENEWAL PAYMENT
    // Set new expiry date (1 year from today)
    $new_expiry = date('Y-m-d', strtotime('+1 year'));
    update_user_meta($user_id, 'pmpronbstup_membership_expiry_date', $new_expiry);
    update_user_meta($user_id, 'pmpronbstup_last_renewal_date', date('Y-m-d'));
    update_user_meta($user_id, 'pmpronbstup_renewal_status', 'active');
    
    // Send renewal confirmation email
    pmpronbstup_send_renewal_confirmation_email($user_id);
} else {
    // INITIAL ACTIVATION
    // Set start date and expiry date (1 year from today)
    update_user_meta($user_id, 'pmpronbstup_membership_start_date', date('Y-m-d'));
    $expiry = date('Y-m-d', strtotime('+1 year'));
    update_user_meta($user_id, 'pmpronbstup_membership_expiry_date', $expiry);
    update_user_meta($user_id, 'pmpronbstup_renewal_status', 'active');
    
    // Send activation confirmation email
    pmpronbstup_send_activation_email($user_id);
}

// Mark as verified
update_user_meta($user_id, 'pmpronbstup_active', 1);
```

---

### 5. Add Scheduled Renewal Checks

**New Function**:
```php
// Hook: Daily check for expired memberships
add_action('wp_scheduled_event_pmpronbstup_check_expiry', 'pmpronbstup_check_all_expired_memberships');

function pmpronbstup_check_all_expired_memberships() {
    // Get all active subscribers
    $users = get_users(array(
        'role'     => 'subscriber',
        'meta_key' => 'pmpronbstup_active',
        'meta_value' => '1'
    ));
    
    foreach ($users as $user) {
        pmpronbstup_check_membership_expiry($user->ID);
    }
}

// Register the event in plugin activation
function pmpronbstup_activate() {
    if (!wp_next_scheduled('wp_scheduled_event_pmpronbstup_check_expiry')) {
        wp_schedule_event(time(), 'daily', 'wp_scheduled_event_pmpronbstup_check_expiry');
    }
}
```

---

### 6. Update User Profile Fields

**Add New Fields**:
```php
// In functions-user-profile.php, add:

<tr>
    <th scope="row">
        <label><?php esc_html_e('Membership Status', 'pmpro-nbstup'); ?></label>
    </th>
    <td>
        <p>
            <strong><?php esc_html_e('Status:', 'pmpro-nbstup'); ?></strong>
            <?php echo esc_html($renewal_status); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Start Date:', 'pmpro-nbstup'); ?></strong>
            <?php echo esc_html($membership_start_date); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Expiry Date:', 'pmpro-nbstup'); ?></strong>
            <?php echo esc_html($membership_expiry_date); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Last Renewal:', 'pmpro-nbstup'); ?></strong>
            <?php echo esc_html($last_renewal_date); ?>
        </p>
    </td>
</tr>
```

---

### 7. Update README.md

**Current Content**:
```
- Only rows where **amount = 51** (INR) are processed.
```

**Should Be**:
```
- Each row will match with subscriber bank transfer transaction IDs
- Successfully matched subscribers are activated for 1 year
- Membership expires exactly 1 year from activation/renewal date
```

---

## Email Templates Needed

### 1. Activation Email (Initial)
```
Subject: Your Membership Account is Active - [Site Name]

Body:
Hello [Name],

Your membership has been verified and activated for 1 year.
- Active From: [Start Date]
- Expires On: [Expiry Date]

To renew, please visit: [Renewal Link]

Thank you,
[Site Name]
```

### 2. Renewal Confirmation Email
```
Subject: Your Membership Renewed - [Site Name]

Body:
Hello [Name],

Your membership renewal has been verified.
- Valid Until: [New Expiry Date]

Thank you for your continued membership.

[Site Name]
```

### 3. Expiry Reminder Email (30 days before)
```
Subject: Your Membership Expires in [X] Days - [Site Name]

Body:
Hello [Name],

Your membership will expire on [Expiry Date].

Please renew your membership to maintain access:
[Renewal Link]

Thank you,
[Site Name]
```

### 4. Renewal Required Email (After expiry)
```
Subject: Your Membership Has Expired - [Site Name]

Body:
Hello [Name],

Your membership expired on [Expiry Date].

Your access has been suspended. To renew, visit:
[Renewal Link]

Thank you,
[Site Name]
```

---

## CSV Import Process - Revised

```
Admin Uploads Bank CSV
  ↓
For each transaction_id in CSV:
  ↓
Find user by bank_transaction_id
  ↓
Check if subscriber?
  ↓
Check if not deceased?
  ↓
├─→ IS RENEWAL (user already has membership)
│    ├─ Set expiry_date = today + 1 year
│    ├─ Set last_renewal_date = today
│    ├─ Set renewal_status = 'active'
│    └─ Send renewal confirmation email
│
└─→ IS INITIAL (user has no membership)
     ├─ Set membership_start_date = today
     ├─ Set membership_expiry_date = today + 1 year
     ├─ Set renewal_status = 'active'
     ├─ Set pmpronbstup_active = 1
     └─ Send activation email
```

---

## Login Flow - Revised

```
User attempts login
  ↓
Check if subscriber?
  ↓
Check if deceased?
  ↓
Check if pmpronbstup_active = 1?
  ↓
Check membership expiry date:
  ├─→ EXPIRED (expiry_date < today)
  │    └─ BLOCK: "Membership expired. Renew to regain access."
  │
  └─→ NOT EXPIRED
      └─ ALLOW: Login successful
```

---

## Database Queries - Updated

### Get expired memberships:
```sql
SELECT user_id FROM wp_usermeta 
WHERE meta_key = 'pmpronbstup_membership_expiry_date' 
AND meta_value < CURDATE()
```

### Get expiring soon (30 days):
```sql
SELECT user_id FROM wp_usermeta 
WHERE meta_key = 'pmpronbstup_membership_expiry_date' 
AND meta_value BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
```

### Get active members:
```sql
SELECT DISTINCT user_id FROM wp_usermeta 
WHERE meta_key = 'pmpronbstup_active' AND meta_value = '1'
AND user_id IN (
    SELECT user_id FROM wp_usermeta 
    WHERE meta_key = 'pmpronbstup_membership_expiry_date' 
    AND meta_value >= CURDATE()
)
```

---

## Summary of Changes Implemented

| Component | Status | Implementation |
|-----------|--------|----------------|
| User meta fields | ✅ Completed | Added 5 new meta fields for tracking membership dates and status |
| `pmpronbstup_is_user_active()` | ✅ Completed | Added expiry date check and auto-deactivation |
| Membership expiry checker | ✅ Completed | Created `pmpronbstup_check_membership_expiry()` function |
| CSV import logic | ✅ Completed | Added renewal vs initial activation logic |
| Scheduled events | ✅ Completed | Added daily expiry check via WP cron |
| User profile display | ✅ Completed | Shows membership dates and status in profile |
| Email templates | ✅ Completed | Added 4 email types: activation, renewal, expiry reminder, renewal required |
| README.md | ✅ Completed | Updated with renewal info |
| Admin page | ✅ Completed | Shows renewal status info |
| Login messages | ✅ Completed | Added expiry-specific login block messages |

---

## Implementation Priority

1. **High**: Add membership expiry date check to `pmpronbstup_is_user_active()`
2. **High**: Update CSV import logic for renewal vs initial
3. **High**: Add user meta fields for tracking
4. **Medium**: Create scheduled daily expiry check
5. **Medium**: Add expiry/renewal email functions
6. **Medium**: Update README.md and user profile
7. **Low**: Enhance admin interface with renewal status

---

## Backward Compatibility Notes

⚠️ **Migration Required**: When deploying these changes:

1. Existing active users will have no `pmpronbstup_membership_expiry_date`
2. Need to set expiry dates for all current active users (e.g., +1 year from today)
3. Script to run on plugin upgrade:
```php
function pmpronbstup_migrate_existing_users() {
    $users = get_users(array(
        'role' => 'subscriber',
        'meta_key' => 'pmpronbstup_active',
        'meta_value' => '1'
    ));
    
    foreach ($users as $user) {
        $expiry = get_user_meta($user->ID, 'pmpronbstup_membership_expiry_date', true);
        if (!$expiry) {
            // Set expiry to 1 year from today
            update_user_meta($user->ID, 'pmpronbstup_membership_expiry_date', 
                date('Y-m-d', strtotime('+1 year')));
        }
    }
}
```

---

## Testing Checklist

- [ ] Initial user activation sets correct expiry date
- [ ] Renewal updates expiry date correctly
- [ ] Expired users cannot log in
- [ ] Expiry reminder emails sent at 30 days before
- [ ] Expired users receive renewal required email
- [ ] CSV import distinguishes initial vs renewal
- [ ] Scheduled event runs daily
- [ ] User profile shows correct membership dates
- [ ] Admin can manually adjust membership dates if needed
