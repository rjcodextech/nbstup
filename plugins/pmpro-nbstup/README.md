## PMPro NBSTUP Bank Import Addon

**Description**

Custom addon for **Paid Memberships Pro** that:

- **Deactivates all subscriber accounts by default** so they cannot log in.
- **Adds an admin page** to upload a **bank statement CSV**.
- On import, **matches CSV `transaction_id` and amount = 51 INR** with PMPro orders and:
  - Marks the matching **subscriber** as active.
  - Sends an **account activation email** to the user.
- Adds **“Passed Away” checkbox + date** on the user profile page:
  - Deceased members are **never activated** and **cannot log in**.

**CSV Requirements**

- File type: `.csv`
- Must contain at least:
  - A column with a header containing the word `transaction` (e.g. `transaction_id`).
  - A column named `amount` or containing the word `amount`.
- Only rows where **amount = 51** (INR) are processed.

**How the Matching Works**

1. For each valid CSV row:
   - Read `transaction_id` and `amount`.
   - If `amount != 51`, the row is skipped.
2. The plugin looks up a **PMPro order** where `transaction_id` matches.
3. If found and the associated user:
   - Has the **subscriber** role,
   - Is **not already active** according to this addon,
   - Is **not marked deceased**,
   then the addon:
   - Sets user meta `pmpronbstup_active = 1`.
   - Sends a simple activation email.

**Login Behavior**

- For **subscribers only**:
  - If `pmpronbstup_active` is **not set** or is **0**, login is blocked.
  - If `pmpronbstup_deceased` is **1**, login is blocked.
- Other user roles (admin, editor, etc.) are **not affected**.

**Deceased Member Fields**

- On the user profile (in wp-admin) for admins:
  - **Passed Away** checkbox → `pmpronbstup_deceased` user meta.
  - **Date of Death** date field → `pmpronbstup_deceased_date` user meta.
- If a user is marked as **deceased**, the addon also forces `pmpronbstup_active = 0`.

**Admin Menu Location**

- The page **NBSTUP Bank Import** is added as a submenu under **Paid Memberships Pro** in the admin.

