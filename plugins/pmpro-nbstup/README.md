# PMPro NBSTUP Addon

## Overview
PMPro NBSTUP is a Paid Memberships Pro extension for yearly membership operations, contribution enforcement, and admin verification workflows.

It supports:
- Membership activation and renewal tracking.
- CSV-based transaction verification.
- Deceased and daughter wedding contribution flows.
- Custom checkout fields for member/nominee/address data.
- Admin contribution management and reactivation tracking.
- Email template configuration for all major lifecycle events.

---

## Core Functionality

### 1. Membership Lifecycle
- Tracks member activity using custom status/meta.
- Sets membership start and expiry dates.
- Handles active, renewal, expired, and contribution-overdue states.
- Auto-deactivates users when membership expires or contribution deadline is missed.

### 2. Activation and Renewal by CSV
- Admin uploads CSV containing transaction IDs.
- System matches CSV transaction IDs with user `bank_transaction_id`.
- Matching users are activated or renewed.
- Renewal updates membership date range and status.
- Success/skipped/not-found results are returned in admin.

### 3. Contribution Workflows

#### Deceased Contribution
- When admin marks a member as deceased:
  - Member is deactivated.
  - All other active members are marked as contribution-required.
  - Deadline is assigned.
  - Notification emails are sent.
- Admin verifies payments by CSV or manual paid toggle.
- Paid members can be auto-reactivated if eligible.
- Overdue unpaid members are deactivated and flagged as contribution overdue.

#### Daughter Wedding Contribution
- When admin marks a member for daughter wedding:
  - All other active members are marked as wedding-contribution-required.
  - Deadline is assigned.
  - Notification emails are sent.
- Supports repeated wedding cycles over time.
- Verification and reactivation follow the same pattern as deceased contribution.

### 4. Checkout Data Collection
Checkout captures and validates:
- Member information.
- Nominee information.
- Address details with state/district/block hierarchy.
- Declaration acceptance.

Important current behavior:
- `Bank Transaction ID` and `Bank Payment Receipt` are not checkout fields.
- They are used in contribution workflows and CSV matching context.

### 5. PMPro Member Admin Page Integration
- On `wp-admin/admin.php?page=pmpro-member&user_id=...`, plugin shows custom checkout user info under User Info.
- Displays member, nominee, and address fields from user meta.
- Bank transfer fields are intentionally excluded from this screen.

### 6. User Profile (WP Admin > Users > Edit User)

Sections:
- NBSTUP Membership Flags.
- Membership Status.
- Address Details.
- Contribution Payment Status (deceased + wedding).

Contribution bank fields behavior:
- `Bank Transaction ID` and `Bank Payment Receipt URL` appear only when user has deceased or wedding contribution requirement.
- These fields are saved only in contribution-required context.

### 7. Contributions Management Page
Admin page provides:
- Contribution summary statistics.
- Filter/search by contribution type and status.
- Bulk and single-member mark-paid actions.
- Quick links for related management actions.
- Recent auto-reactivation log display.

### 8. CSV Upload Tabs (User Approval)
Supports:
- User activation verification CSV.
- Deceased contribution verification CSV.
- Wedding contribution verification CSV.

All CSV flows use transaction ID matching.

### 9. Emails and Templates
Configurable templates for:
- Activation confirmation.
- Renewal confirmation.
- Expiry reminders.
- Expiry notifications.
- Deceased contribution required.
- Wedding contribution required.
- Contribution confirmed.
- Contribution overdue.
- Admin notification summaries.

### 10. Frontend Shortcodes
- `[pmpro_account_nbstup]` custom account layout with NBSTUP sections.
- `[pmpro_nbstup_member_login]` member login flow.
- `[pmpro_nbstup_users_list]` searchable/paginated user list.

### 11. Location Management
- State, district, and block data model.
- AJAX-based cascading dropdowns.
- Used in checkout and admin profile editing.

### 12. Authentication and Access Control
- Validates member login restrictions by status.
- Blocks inappropriate login paths.
- Supports Aadhar-based authentication flow where configured.

---

## Process Flows

### A. Online Checkout Success Flow
1. User completes checkout with required member/nominee/address fields.
2. On successful order status, user is auto-activated.
3. Membership start/expiry metadata is set.
4. Status becomes active.

### B. Offline/Bank Verification Flow (CSV)
1. User provides/has transaction ID in profile data.
2. Admin uploads CSV with transaction IDs.
3. System matches IDs to users.
4. Matched users are activated/renewed and notified.

### C. Deceased Contribution Flow
1. Admin marks a member as deceased.
2. Other active members become contribution-required.
3. Members pay and are verified via CSV/manual mark-paid.
4. Verified users are reactivated if eligible.
5. Deadline checker deactivates unpaid overdue users.

### D. Wedding Contribution Flow
1. Admin marks member for daughter wedding.
2. Other active members become wedding-contribution-required.
3. Payment verification occurs via CSV/manual mark-paid.
4. Verified users are reactivated if eligible.
5. Overdue logic applies through scheduled checks.

---

## Scheduled Automation

Daily scheduled events:
- Membership expiry check.
- Contribution deadline check (deceased + wedding).

These automate:
- Expiry enforcement.
- Overdue contribution enforcement.
- Related email notifications.

---

## Data Tracked (High Level)

Membership/meta examples:
- Active flag.
- Deceased and wedding flags/dates.
- Membership start/expiry/renewal status.

Contribution/meta examples:
- Required flags.
- Paid flags.
- Deadlines.
- Contribution transaction tracking.

Bank/meta examples:
- `bank_transaction_id`
- `bank_payment_receipt`

Current scope:
- Used for transaction verification and contribution operations.
- Not used as checkout fields.

---

## Admin Pages

- `Memberships > User Approval`
  - Activation CSV.
  - Deceased contribution CSV.
  - Wedding contribution CSV.
- `Memberships > Contributions`
  - Contribution monitoring and actions.
- `Memberships > Email Settings`
  - Template configuration.

---

## Operational Checklist

1. Ensure Paid Memberships Pro is active.
2. Verify cron runs daily on the site.
3. Confirm email sending is configured.
4. Test activation CSV with sample IDs.
5. Test deceased and wedding contribution CSV flows.
6. Confirm contribution-required users show bank detail fields in user profile.
7. Confirm PMPro member page shows custom checkout data only (no bank fields).

---

## Current Sync Notes

This README is synchronized with the current plugin state where:
- Checkout does not include bank transaction or receipt fields.
- Bank fields are handled in contribution context and CSV matching workflows.
- PMPro member User Info custom section excludes bank fields.
