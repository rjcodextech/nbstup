# Implementation Changes - PMPro NBSTUP Plugin

## Summary
Updated the plugin code to remove the ₹51 INR amount restriction and match CSV transaction IDs directly with subscriber `bank_transaction_id` user meta instead of PMPro orders.

---

## Files Modified

### 1. `includes/functions-csv.php`

#### Change: Database Query Logic
**Location**: Lines 77-100 (approximately)

**Before**:
```php
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
```

**After**:
```php
// Find user by bank_transaction_id user meta
global $wpdb;

$user_meta = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        'bank_transaction_id',
        $csv_transaction_id
    )
);

if (! $user_meta) {
    $not_found++;
    continue;
}

$user_id = (int) $user_meta->user_id;
```

**What Changed**:
- ✅ Removed dependency check for `pmpro_getMemberOrders()` function
- ✅ Changed query from `pmpro_membership_orders` table to `usermeta` table
- ✅ Now searches for users where `meta_key = 'bank_transaction_id'` and `meta_value` matches CSV transaction ID
- ✅ Removed amount column extraction and amount != 51 validation (no longer needed)

**Why**:
- The plugin now matches against bank transfer data stored during checkout, not PMPro orders
- This gives more flexibility and doesn't require a specific amount value

---

### 2. `includes/functions-admin.php`

#### Change: CSV Upload Help Text
**Location**: Lines 53-56 (approximately)

**Before**:
```php
<p class="description">
    <?php esc_html_e('Upload bank statement CSV. It must contain transaction ID columns.', 'pmpro-nbstup'); ?>
</p>
```

**After**:
```php
<p class="description">
    <?php esc_html_e('Upload bank statement CSV. It must contain a transaction ID column that matches subscriber bank transfer transaction IDs.', 'pmpro-nbstup'); ?>
</p>
```

**What Changed**:
- ✅ Updated help text to clarify that the transaction ID must match `bank_transaction_id` from checkout
- ✅ Removed mention of amount requirement
- ✅ More user-friendly instruction

---

## Processing Flow Changes

### Old Flow:
```
CSV Row
  ↓
Extract transaction_id AND amount
  ↓
Skip if amount ≠ 51
  ↓
Query: pmpro_membership_orders WHERE transaction_id = CSV value
  ↓
Get user_id from order
  ↓
Activate user
```

### New Flow:
```
CSV Row
  ↓
Extract transaction_id
  ↓
Query: usermeta WHERE meta_key = 'bank_transaction_id' AND meta_value = CSV value
  ↓
Get user_id from meta
  ↓
Activate user
```

---

## Database Query Changes

### Old Query:
```sql
SELECT * FROM wp_pmpro_membership_orders 
WHERE transaction_id = %s LIMIT 1
```

### New Query:
```sql
SELECT user_id FROM wp_usermeta 
WHERE meta_key = 'bank_transaction_id' AND meta_value = %s LIMIT 1
```

---

## CSV Requirements (Updated)

| Requirement | Old | New |
|------------|-----|-----|
| File format | `.csv` | `.csv` |
| Transaction ID column | Required | Required |
| Amount column | Required | ❌ Removed |
| Amount = 51 check | Required | ❌ Removed |
| Source data | PMPro orders | User meta (bank transfers) |

---

## Backward Compatibility

⚠️ **Important**: This change is **NOT backward compatible** with existing CSV import data that relied on PMPro transaction IDs from orders.

**New requirement**: CSV files must contain transaction IDs that match the `bank_transaction_id` values stored in user meta (from the checkout bank transfer form).

---

## Testing Checklist

After deploying these changes, verify:

- [ ] CSV upload form still appears in "User Approval" admin page
- [ ] CSV file accepts `.csv` files only
- [ ] Users with matching `bank_transaction_id` are activated
- [ ] Users without matching transaction ID are counted as "not found"
- [ ] Already-active users are counted as "skipped"
- [ ] Deceased users are not activated
- [ ] Activation emails are sent correctly
- [ ] Admin receives import summary with correct counts
- [ ] Non-subscriber roles are not affected

---

## No Breaking Changes For Users

This change only affects the admin CSV import process. Other plugin functionality remains unchanged:

- ✅ Subscriber login authentication (still blocked if not active)
- ✅ Deceased member management (still working)
- ✅ Bank transfer fields at checkout (still working)
- ✅ Custom account layout shortcode (still working)
- ✅ Contribution payment for deceased members (still working)

---

## Function Signatures

All function signatures remain **unchanged**:

```php
pmpronbstup_handle_csv_upload()           // No parameter changes
pmpronbstup_send_activation_email($user_id)  // No parameter changes
```

---

## Code Quality

All changes maintain WordPress security standards:

- ✅ Prepared statements with `$wpdb->prepare()`
- ✅ Proper table prefixes with `$wpdb->` variables
- ✅ No additional SQL injection vulnerabilities introduced
- ✅ Nonce verification still in place
- ✅ Capability checks still enforced
- ✅ Input sanitization maintained
