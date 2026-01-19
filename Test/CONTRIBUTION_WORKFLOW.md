# Complete Workflow - Contribution Verification

## Overview
This document shows the complete workflow for the contribution verification feature with real-world examples.

---

## Scenario: A Member Passes Away

### Day 1: Member Marked as Deceased

**Admin Action:**
1. Go to WordPress Dashboard > Users
2. Find the deceased member (e.g., "Rajesh Kumar")
3. Click to edit their profile
4. Under "NBSTUP Membership Flags" section:
   - Check "Passed Away"
   - Set "Date of Death" to today (or the actual date)
5. Click "Update Profile"

**System Response (Automatic):**
- Rajesh Kumar's account is deactivated
- All active subscribers get a new user meta:
  - `pmpronbstup_contribution_required` = 1
  - `pmpronbstup_contribution_deadline` = 1 month from today
- All active subscribers receive email:
  ```
  Subject: [Your Site Name] Contribution Payment Required
  
  Body:
  Hello John,
  
  A member of our community has passed away. In their memory, 
  all active members are requested to pay a contribution.
  
  Contribution Deadline: [Date 1 month from today]
  
  Please visit the following link to make your contribution:
  [Checkout Link]
  
  Thank you for your support.
  ```

---

## Days 2-30: Members Pay Contribution

### What Members See

**When They Try to Log In:**
```
Error: Your contribution payment is required by Feb 19, 2026. 
Please pay the contribution to access your account.
```

**Options:**
1. Click checkout link from email
2. Go to member dashboard and pay
3. Bank transfer with specific transaction ID

### Member Payment Example

**Member: "Priya Sharma"**
- Receives notification email on Day 1
- Makes bank transfer on Day 5
- Transfer Amount: ₹5000
- Transaction ID: **BANK2026-001**

**Member: "Amit Patel"**
- Receives notification email on Day 1
- Makes bank transfer on Day 15
- Transfer Amount: ₹5000
- Transaction ID: **BANK2026-002**

**Member: "Sunita Singh"**
- Receives notification email on Day 1
- Does NOT pay (deadline passes on Day 30)
- Gets auto-deactivated on Day 31

---

## Day 31: Admin Verifies Contributions

### Admin Action: Upload Contribution CSV

**Step 1:** Download bank statement from bank
- Contains transaction IDs and amounts

**Step 2:** Create or export CSV with transaction IDs
```csv
transaction_id,amount,date,payer_name
BANK2026-001,5000,2026-01-24,Priya Sharma
BANK2026-002,5000,2026-02-04,Amit Patel
BANK2026-004,5000,2026-02-10,Neha Gupta
```

**Step 3:** Go to WordPress Admin
- Navigate to Paid Memberships Pro > User Approval
- Click "Contribution Verification" tab
- Upload CSV file
- Click "Verify and Update Contribution Payments"

### System Processing

**For each row in CSV:**
1. Extract transaction ID (e.g., BANK2026-001)
2. Look for user with `bank_transaction_id` = BANK2026-001
3. If found and has contribution requirement:
   - Update: `pmpronbstup_contribution_paid` = 1
   - Save: `pmpronbstup_contribution_transaction_id` = BANK2026-001
   - Send confirmation email

**Result Message:**
```
Contribution verification finished. 
Verified: 2, Skipped: 1, No matching transaction: 0.
```

### Emails Sent to Members

**Priya Sharma receives:**
```
Subject: [Your Site Name] Your Contribution Has Been Verified

Body:
Hello Priya,

Thank you! Your contribution payment has been verified and recorded.

Your account remains active. Thank you for your support.

Best regards,
[Your Site Name]
```

**Amit Patel receives:**
```
Subject: [Your Site Name] Your Contribution Has Been Verified

Body:
Hello Amit,

Thank you! Your contribution payment has been verified and recorded.

Your account remains active. Thank you for your support.

Best regards,
[Your Site Name]
```

---

## Day 31: Auto-Deactivation (If Not Paid)

### Sunita Singh: Missed Deadline

**What Happened:**
- Day 1: Marked for contribution, deadline = Feb 19
- Day 30: Did NOT pay
- Day 31: System runs scheduled event `wp_scheduled_event_pmpronbstup_check_contribution`

**Automatic Actions:**
1. Account deactivated: `pmpronbstup_active` = 0
2. Renewal status: `pmpronbstup_renewal_status` = 'contribution_overdue'
3. Email sent:

```
Subject: [Your Site Name] Your Contribution Payment is Overdue

Body:
Hello Sunita,

Your contribution payment deadline has passed.

Your account has been deactivated. To reactivate your account 
and continue your membership, please pay the contribution.

Visit: [Checkout Link]

Thank you,
[Your Site Name]
```

---

## After Verification: Login Behavior

### Priya Sharma (Paid Contribution)
**Try to log in with username & password:**
✅ Login succeeds
- Account active
- Contribution marked as paid
- Can access full member dashboard

### Sunita Singh (Didn't Pay)
**Try to log in with username & password:**
❌ Login fails with error:
```
Error: Your account is not active. Please contact support 
or renew your membership.
```

**Why?** Account was auto-deactivated when deadline passed

---

## Manual Admin Override

### If Payment Received by Other Means

**Scenario:** Sunita pays contribution on Day 35 via different method (e.g., cash at event)

**Admin Action:**
1. Go to Users > Sunita Singh
2. Under "Contribution Payment Status":
   - Check "Mark contribution as paid"
3. Click "Update Profile"
4. Sunita receives confirmation email
5. On next login, Sunita can access account

---

## User Profile Views - After Implementation

### Profile: Rajesh Kumar (Deceased)
```
NBSTUP Membership Flags
├─ Passed Away: ☑ Checked
├─ Date of Death: Jan 19, 2026

Membership Status
├─ Active Status: Inactive
├─ Renewal Status: None
├─ Membership Start Date: Not set
├─ Membership Expiry Date: Not set
└─ Last Renewal Date: Not set

Contribution Payment Status
└─ Contribution Required: No
```

### Profile: Priya Sharma (Paid Contribution)
```
NBSTUP Membership Flags
├─ Passed Away: ☐ Unchecked
└─ Date of Death: [Empty]

Membership Status
├─ Active Status: Active
├─ Renewal Status: Active
├─ Membership Start Date: Jan 1, 2026
├─ Membership Expiry Date: Jan 1, 2027
└─ Last Renewal Date: Jan 31, 2026

Contribution Payment Status
├─ Contribution Required: Yes
├─ Contribution Paid: ☑ Checked
└─ Contribution Deadline: Feb 19, 2026
```

### Profile: Sunita Singh (Missed Deadline)
```
NBSTUP Membership Flags
├─ Passed Away: ☐ Unchecked
└─ Date of Death: [Empty]

Membership Status
├─ Active Status: Inactive
├─ Renewal Status: contribution_overdue
├─ Membership Start Date: Jan 1, 2026
├─ Membership Expiry Date: Jan 1, 2027
└─ Last Renewal Date: Jan 31, 2026

Contribution Payment Status
├─ Contribution Required: Yes
├─ Contribution Paid: ☐ Unchecked
└─ Contribution Deadline: Feb 19, 2026
```

---

## Email Flow Diagram

```
Day 1: Deceased Marked
    ↓
    Notification Email sent to All Active Members
    "Contribution Required - Deadline: Feb 19, 2026"
    ↓
Days 2-29: Members Pay
    ├─ Priya pays on Day 5 (TXN: BANK2026-001)
    ├─ Amit pays on Day 15 (TXN: BANK2026-002)
    └─ Sunita does NOT pay
    ↓
Day 30: Admin uploads contribution CSV
    ├─ Priya → Marked as paid → Receives Confirmation Email
    ├─ Amit → Marked as paid → Receives Confirmation Email
    └─ Sunita → NOT in CSV → Still waiting
    ↓
Day 31: Scheduled event runs
    ├─ Sunita deadline passed → Auto-deactivated
    └─ Receives "Payment Overdue" Email
    ↓
Day 35: Sunita pays late
    ├─ Admin manually marks as paid
    └─ Receives Confirmation Email
    ↓
Day 36+: Sunita can log in again
```

---

## CSV Examples

### Minimal CSV
```csv
transaction_id
BANK2026-001
BANK2026-002
BANK2026-004
```

### CSV with Additional Info
```csv
transaction_id,amount,date,bank,payer_name
BANK2026-001,5000,2026-01-24,HDFC,Priya Sharma
BANK2026-002,5000,2026-02-04,ICICI,Amit Patel
BANK2026-004,5000,2026-02-10,Axis,Neha Gupta
```

### CSV with Reference Number
```csv
reference_number,transaction_id,status
1,BANK2026-001,completed
2,BANK2026-002,completed
3,BANK2026-004,completed
```

**Key:** The plugin automatically finds the column containing "transaction" and extracts those values.

---

## Summary

**Timeline for Complete Contribution Cycle:**

| Day | Event | Auto/Manual | Result |
|-----|-------|-------------|--------|
| 1 | Member marked deceased | Auto | Contribution required for all |
| 1 | Email sent to all | Auto | Deadline set for 30 days |
| 2-29 | Members pay contributions | Manual | Payment recorded |
| 31 | Admin uploads CSV | Manual | Payments verified |
| 31 | Confirmation emails sent | Auto | Members marked as paid |
| 31 | Auto-deactivation runs | Auto | Non-payers deactivated |
| 31+ | Members can log in | N/A | If contribution paid |

This complete workflow ensures:
- ✅ All members are notified
- ✅ Fair 1-month deadline
- ✅ Auto-enforcement of payment
- ✅ Easy verification via CSV
- ✅ Proper email notifications
