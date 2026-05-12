# M-Pesa Integration Guide

This project uses Safaricom Daraja C2B callbacks to record SACCO member contributions automatically.

## Main Flow

1. A member pays through M-Pesa PayBill.
2. Safaricom sends a C2B callback to:

   ```text
   https://your-domain.co.ke/api/callback.php
   ```

3. The callback reads `BillRefNumber`.
4. `BillRefNumber` is treated as the member `NationalID`.
5. The system searches the `members` table using `NationalID`.
6. If a member is found, the transaction is saved with that member's `MemberID`.
7. If no member is found, the transaction is still saved with `MemberID = NULL`.

## Important Callback Fields

Safaricom C2B callback data should provide:

| M-Pesa Field | System Usage |
|---|---|
| `BillRefNumber` | Used as `NationalID` |
| `TransID` | Saved as unique transaction ID |
| `TransAmount` | Saved as contribution amount |
| `MSISDN` | Payer phone number |
| `FirstName` | Payer first name |
| `LastName` | Payer last name |
| `TransTime` | Transaction time |

The system does not expect M-Pesa to send `MemberID`.

## Files Involved

| File | Purpose |
|---|---|
| `config/config.php` | Database, app, and Daraja configuration |
| `classes/Database.php` | PDO database connection |
| `classes/Mpesa.php` | C2B callback logic and Daraja helper methods |
| `api/callback.php` | Safaricom C2B confirmation callback endpoint |
| `api/register_urls.php` | Helper endpoint for registering Daraja C2B URLs |
| `database/schema.sql` | Members and transactions table structure |
| `logs/mpesa-c2b-callbacks.log` | Raw callback log file created automatically |
| `logs/mpesa-c2b-errors.log` | Callback error log file created automatically |

## Variables You Need To Customize

The system reads most settings from environment variables first. If an environment variable is missing, it uses the fallback value in `config/config.php`.

### Database Variables

Set these on your hosting control panel, `.env` loader, Apache/Nginx config, or server environment:

```text
DB_HOST=localhost
DB_NAME=mashirikianosacc_mashirikiano
DB_USER=mashirikianosacc_mashirikianosacco
DB_PASS=your_database_password
```

In `config/config.php`, these map to:

```php
'db' => [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: 'mashirikianosacc_mashirikiano',
    'user' => getenv('DB_USER') ?: 'mashirikianosacc_mashirikianosacco',
    'pass' => getenv('DB_PASS') ?: '',
]
```

You must update `DB_PASS`. The database password is intentionally not hardcoded.

### App Variables

```text
APP_BASE_URL=https://your-domain.co.ke
ADMIN_REPORT_TOKEN=choose-a-private-admin-token
```

`ADMIN_REPORT_TOKEN` is optional but recommended. When set, admin pages require the token:

```text
https://your-domain.co.ke/admin/reports.php?token=choose-a-private-admin-token
https://your-domain.co.ke/admin/members.php?token=choose-a-private-admin-token
```

After the first successful token visit, the session remembers admin access.

### M-Pesa Daraja Variables

Get these from the Safaricom Daraja portal:

```text
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_SHORTCODE=your_paybill_or_shortcode
MPESA_PASSKEY=your_passkey_if_needed
MPESA_ENV=production
```

Use `sandbox` for testing:

```text
MPESA_ENV=sandbox
```

Use `production` for the live PayBill:

```text
MPESA_ENV=production
```

## Safaricom Daraja Setup

### Confirmation URL

Set your C2B Confirmation URL to:

```text
https://your-domain.co.ke/api/callback.php
```

Replace `your-domain.co.ke` with your actual domain.

### Validation URL

If you do not need validation logic yet, you can use the same URL:

```text
https://your-domain.co.ke/api/callback.php
```

For a stricter production setup, create a separate validation endpoint later.

## Registering C2B URLs

After setting your Daraja credentials, you can register C2B URLs using:

```text
https://your-domain.co.ke/api/register_urls.php?token=YOUR_ADMIN_REPORT_TOKEN&confirmation_url=https://your-domain.co.ke/api/callback.php
```

If you want to pass a separate validation URL:

```text
https://your-domain.co.ke/api/register_urls.php?token=YOUR_ADMIN_REPORT_TOKEN&confirmation_url=https://your-domain.co.ke/api/callback.php&validation_url=https://your-domain.co.ke/api/validation.php
```

## Database Setup

Import:

```text
database/schema.sql
```

This creates:

```text
members
transactions
member_contribution_totals
```

## Member Matching Logic

The important logic is inside `classes/Mpesa.php`:

```php
$billRef = $payload['BillRefNumber'];
$nationalId = $billRef;
```

Then:

```sql
SELECT * FROM members WHERE NationalID = :national_id LIMIT 1
```

If found:

```text
transactions.MemberID = members.MemberID
```

If not found:

```text
transactions.MemberID = NULL
```

## Member Account Requirements

Members must exist in the `members` table with:

```text
NationalID
FirstName
LastName
PrimaryNumber
Password
Status = Active
```

Members log in using:

```text
NationalID + Password
```

Passwords are currently stored as plain text because that was requested for this stage. Before going live at scale, change this to `password_hash()` and `password_verify()`.

## Testing A Callback

You can test the callback by sending sample JSON to `/api/callback.php`:

```json
{
  "TransID": "TEST123456",
  "TransAmount": "500",
  "BillRefNumber": "12345678",
  "MSISDN": "254712345678",
  "FirstName": "Jane",
  "LastName": "Doe",
  "TransTime": "20260512143000"
}
```

Expected result:

```json
{
  "ResultCode": 0,
  "ResultDesc": "Transaction linked to member.",
  "TransactionStatus": "recorded"
}
```

If `NationalID = 12345678` does not exist in `members`, the transaction is still saved with:

```text
MemberID = NULL
```

## Production Checklist

- Import `database/schema.sql`.
- Set `DB_PASS`.
- Set Daraja credentials.
- Set `MPESA_ENV=production`.
- Set `ADMIN_REPORT_TOKEN`.
- Confirm your hosting supports PHP PDO MySQL and cURL.
- Configure HTTPS before registering callback URLs.
- Register the C2B Confirmation URL with Safaricom.
- Make sure members use their National ID as the PayBill account number.
- Check `logs/mpesa-c2b-callbacks.log` after the first live payment.

## What Members Should Enter In M-Pesa

When paying through PayBill:

```text
Account Number = National ID
```

That Account Number becomes `BillRefNumber`, which the system uses to find the member.
