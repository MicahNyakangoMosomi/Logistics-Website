# Admin Login Setup

This guide shows how to create an admin/staff account and log in to the internal SACCO admin panel.

## Why The HTTP 500 Happened

The admin login page uses the `admin_users` table.

The current code expects this column:

```sql
admin_users.Password
```

If the live database does not have the `admin_users` table, or if it still has the older `PasswordHash` column instead of `Password`, PHP can throw a database error and the server may show:

```text
HTTP ERROR 500
```

The login page has now been updated to show a setup message instead of a blank 500 when this happens.

## Required Admin Table

Run this in phpMyAdmin if `admin_users` does not exist:

```sql
CREATE TABLE IF NOT EXISTS admin_users (
  AdminUserID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  FullName VARCHAR(150) NOT NULL,
  Email VARCHAR(150) NOT NULL,
  Password VARCHAR(255) NOT NULL,
  Role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  Status ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
  CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (AdminUserID),
  UNIQUE KEY uniq_admin_users_email (Email),
  KEY idx_admin_users_role (Role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

If `admin_users` already exists but does not have the raw password column, run:

```sql
ALTER TABLE admin_users
ADD COLUMN Password VARCHAR(255) NOT NULL DEFAULT '' AFTER Email;
```

## Add An Admin User

The admin password is stored raw in the database as requested.

Example:

```sql
INSERT INTO admin_users (FullName, Email, Password, Role, Status)
VALUES ('System Admin', 'admin@mashirikianosacco.co.ke', 'Admin12345', 'admin', 'Active');
```

If the email already exists and you only want to reset the password:

```sql
UPDATE admin_users
SET Password = 'Admin12345',
    Role = 'admin',
    Status = 'Active'
WHERE Email = 'admin@mashirikianosacco.co.ke';
```

## Login

Open:

```text
https://mashirikianosacco.co.ke/auth/admin_login.php
```

Use:

```text
Email: admin@mashirikianosacco.co.ke
Password: Admin12345
```

After successful login, the system redirects to:

```text
https://mashirikianosacco.co.ke/admin/members.php
```

## If You Still See HTTP 500

Open this page after logging in:

```text
https://mashirikianosacco.co.ke/admin/health.php
```

It checks:

- Database connection
- `members` table
- `contributions` table
- `admin_users` table
- `member_contribution_totals` view

If `/admin/members.php` was crashing because a table is missing, the dashboard now shows a setup message instead of a blank 500.

## Minimum SQL To Fix Admin Login Only

If you only need admin login working first, run this:

```sql
CREATE TABLE IF NOT EXISTS admin_users (
  AdminUserID INT UNSIGNED NOT NULL AUTO_INCREMENT,
  FullName VARCHAR(150) NOT NULL,
  Email VARCHAR(150) NOT NULL,
  Password VARCHAR(255) NOT NULL,
  Role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  Status ENUM('Active','Suspended') NOT NULL DEFAULT 'Active',
  CreatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (AdminUserID),
  UNIQUE KEY uniq_admin_users_email (Email)
);

INSERT INTO admin_users (FullName, Email, Password, Role, Status)
VALUES ('System Admin', 'admin@mashirikianosacco.co.ke', 'Admin12345', 'admin', 'Active')
ON DUPLICATE KEY UPDATE
  Password = VALUES(Password),
  Role = VALUES(Role),
  Status = VALUES(Status);
```

Then login again. After login works, run the full SACCO migration for members and contributions.

## Quick Database Check

Use this to confirm the admin account exists:

```sql
SELECT AdminUserID, FullName, Email, Password, Role, Status, CreatedAt
FROM admin_users;
```

The account must have:

- `Status = Active`
- `Role = admin` or `staff`
- `Password` matching the exact password typed in the login form

## Common Problems

If login says invalid credentials:

- Confirm the email is typed exactly.
- Confirm the password value in `admin_users.Password` is the same raw text.
- Confirm `Status` is `Active`.

If the page says admin login is not fully configured:

- Confirm `admin_users` exists.
- Confirm `admin_users.Password` exists.
- Re-run the relevant SQL above.
