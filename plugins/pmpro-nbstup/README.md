## PMPro NBSTUP Bank Import Addon

**Description**

Custom addon for **Paid Memberships Pro** that manages **yearly recurring subscriptions** with bank transfer payments:

- **Deactivates all subscriber accounts by default** so they cannot log in.
- **Adds an admin page** to upload a **bank statement CSV**.
- On import, **matches CSV `transaction_id`** with subscriber bank transfer data and:
  - **Activates new members** for 1 year or **renews existing members** for another year.
  - Sends appropriate **activation or renewal confirmation emails**.
- **Automatically deactivates** members when their yearly membership expires.
- **Sends reminder emails** 30 days before expiry and notification when expired.
- Adds **"Passed Away" checkbox + date** on the user profile page:
  - Deceased members are **never activated** and **cannot log in**.

**Yearly Recurring Subscription Features**

- **1-year membership duration** from activation/renewal date
- **Automatic deactivation** on expiry (no manual intervention needed)
- **Bank transfer verification** via CSV import for renewals
- **Email notifications** for expiry reminders and renewals
- **Daily automated checks** for expired memberships

**CSV Requirements**

- File type: `.csv`
- Must contain at least:
  - A column with a header containing the word `transaction` (e.g. `transaction_id`).
- The `transaction_id` must match values stored in subscriber `bank_transaction_id` user meta from checkout.

**How the Matching Works**

1. For each valid CSV row:
    - Extract `transaction_id`.
2. The plugin looks up a **subscriber user** where `bank_transaction_id` user meta matches.
3. If found and the user:
    - Has the **subscriber** role,
    - Is **not marked deceased**,
    then the addon:
    - **For new members**: Sets membership start date, expiry date (+1 year), and activates account.
    - **For renewals**: Updates expiry date (+1 year from today), sets renewal status, and sends renewal confirmation.
    - Sends appropriate email notification.
    - **Note**: No amount validation is performed - any matching transaction ID activates/renews the membership.

**Login Behavior**

- For **subscribers only**:
  - If `pmpronbstup_active` is **not set** or is **0**, login is blocked.
  - If `pmpronbstup_deceased` is **1**, login is blocked.
  - If membership has **expired** (expiry date < today), login is blocked with specific expiry message.
- Other user roles (admin, editor, etc.) are **not affected**.

**Membership Management**

- **Activation**: New members are activated for 1 year from activation date.
- **Renewal**: Existing members can renew via bank transfer, extending expiry by 1 year.
- **Expiry**: Members are automatically deactivated when membership expires.
- **Reminders**: Email sent 30 days before expiry.
- **Notifications**: Email sent when membership expires.

**Deceased Member Fields**

- On the user profile (in wp-admin) for admins:
  - **Passed Away** checkbox → `pmpronbstup_deceased` user meta.
  - **Date of Death** date field → `pmpronbstup_deceased_date` user meta.
  - **Membership Status** display (active/inactive, renewal status, dates).
- If a user is marked as **deceased**, the addon also forces `pmpronbstup_active = 0`.

**Admin Menu Location**

- The page **NBSTUP Bank Import** is added as a submenu under **Paid Memberships Pro** in the admin.

