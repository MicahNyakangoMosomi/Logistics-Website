# SACCO Management System

This project now includes an internal SACCO member management workflow for Mashirikiano SACCO. Public users do not self-register into the system; members are created by authenticated SACCO staff/admins.

## Main Pages

| Area | Path | Purpose |
| --- | --- | --- |
| Admin login | `/auth/admin_login.php` | Staff/admin authentication using `admin_users` records |
| Admin dashboard | `/admin/members.php` | Internal member registration, search, statistics, editing, and contribution summaries |
| Reports | `/admin/reports.php` | Contribution reporting and recent M-Pesa contribution records |
| Member portal | `/auth/login.php` | Active members sign in with National ID and password |
| Member dashboard | `/member/dashboard.php` | Member profile and contribution history |

The existing admin token flow still works as a bootstrap/fallback through `ADMIN_REPORT_TOKEN`, but the scalable workflow is to create records in `admin_users` with role `admin` or `staff`.

## Internal Member Registration Workflow

Admins/staff register members from `/admin/members.php`.

The form collects:

| Field | Required | Notes |
| --- | --- | --- |
| FirstName | Yes | Used for MemberID generation |
| LastName | Yes | Used for MemberID generation |
| PrimaryNumber | Yes | Member phone number |
| Email | No | Can be left blank |
| Password | Yes | Stored with `password_hash()` |
| NationalID | Yes | Must be unique |

When a member is created:

1. `MemberID` is generated automatically.
2. `Status` is set to `Active`.
3. `CreatedAt` is set by the database timestamp.
4. Password is securely hashed before storage.
5. Duplicate `NationalID` registration is blocked.
6. Existing unmatched contributions with the same `NationalID` are linked to the new member.

## MemberID Format

`MemberID` is generated using:

1. First 4 digits of `NationalID`
2. First letter of `FirstName`
3. First letter of `LastName`
4. Incrementing count for the same prefix

Examples:

```text
1234JD1
1234JD2
1234JD3
```

The database also enforces `MemberID` uniqueness with a primary key.

## Admin Dashboard Features

The admin dashboard supports:

- Member statistics: total members, active members, total contributions, unmatched contribution groups
- Internal member registration
- Search by `MemberID`, member name, phone number, or `NationalID`
- Status filtering
- Editable member table
- Protected immutable fields: `MemberID` and `CreatedAt`
- Total contribution aggregation per member from the `contributions` table

Admins can edit:

- `FirstName`
- `LastName`
- `PrimaryNumber`
- `Email`
- `NationalID`
- `Status`
- Password, only when a new password is entered

## Database Tables

### `members`

Stores member profile and login data.

Important fields:

- `MemberID` as generated SACCO identifier
- `NationalID` with a unique constraint
- `Password` as a secure hash
- `Status`
- `CreatedAt`

### `contributions`

Stores contribution/payment records separately from member profiles.

Important fields:

- `ContributionID`
- `TranID`
- `MemberID`
- `NationalID`
- `Amount`
- `TranTime`
- `CreatedAt`

Each matched contribution links to `members.MemberID`. M-Pesa contributions that cannot be matched immediately are stored with `MemberID = NULL` and can be linked later when the member is registered with the same `NationalID`.

### `admin_users`

Stores internal staff/admin accounts.

Important fields:

- `Email`
- `PasswordHash`
- `Role`: `admin` or `staff`
- `Status`: `Active` or `Suspended`

## M-Pesa Contribution Flow

The M-Pesa callback treats `BillRefNumber` as `NationalID`.

1. Callback data is normalized.
2. The system searches `members.NationalID`.
3. If a member exists, the contribution is saved with that member's generated `MemberID`.
4. If no member exists, the contribution is saved as unmatched.
5. When a staff member later registers that `NationalID`, unmatched contributions are automatically linked.

## Setup Notes

For a new database, run:

```sql
SOURCE database/schema.sql;
```

For an existing installation, review and run:

```sql
SOURCE database/sacco_management_migration.sql;
```

After the schema is ready, create at least one `admin_users` record. Generate a password hash with PHP:

```bash
php -r "echo password_hash('YourStrongPassword', PASSWORD_DEFAULT), PHP_EOL;"
```

Then insert the admin user:

```sql
INSERT INTO admin_users (FullName, Email, PasswordHash, Role)
VALUES ('System Admin', 'admin@example.com', 'PASTE_HASH_HERE', 'admin');
```

## Security Practices Implemented

- Internal registration is protected by admin/staff authentication.
- Role values are stored for admin users.
- Member passwords are hashed with PHP `password_hash()`.
- Login verifies hashed passwords with `password_verify()`.
- Legacy plain text member passwords can still log in during transition, but newly created/updated passwords are hashed.
- Duplicate National ID registrations are blocked.
- `MemberID` and `CreatedAt` are immutable from the admin edit UI.
- Database uniqueness protects `MemberID`, `NationalID`, admin emails, and contribution transaction IDs.

## Future SACCO Modules

The system keeps members and contributions separate so future modules can be added cleanly:

- Loans
- Share capital
- Statements
- Dividends
- Guarantors
- Reports

Future tables should relate back to `members.MemberID`.
