# SocityApp — Society Management Portal

Society management portal built with Laravel 10, Blade, HTML5, CSS3, jQuery, JavaScript and MySQL/MariaDB. It includes isolated society workspaces, role-based resident, committee and maintenance-staff access, and platform-level super-admin controls.

## Requirements

- PHP 8.1 or newer (PHP 8.2 is supported)
- Composer 2
- MySQL or MariaDB, with PHP's `pdo_mysql` extension enabled
- Apache/XAMPP is optional; Laravel's local development server can be used instead

## Run locally

1. Create a new empty MySQL database named `society_portal` in phpMyAdmin. The Laravel migrations create all application tables; do not import the old `database/schema.sql`.
2. Install PHP dependencies from the project root:

   ```powershell
   composer install
   ```

3. Copy `.env.example` to `.env` if `.env` does not already exist. Set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to match your local MySQL account.
4. Generate the application key and create the tables with demo data:

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   ```

5. Start the app:

   ```powershell
   php artisan serve
   ```

6. Open <http://127.0.0.1:8000>.

To activate platform administration, promote an existing society administrator after the database has been seeded:

```powershell
php artisan society:promote-super-admin admin@your-society.example
```

Replace the example address with the email of an existing, active society administrator. Confirm the prompt only when the displayed account is the intended platform administrator. The administrator then signs in using the **Super admin** account type. This command does not create a public super-admin signup.

For Apache/XAMPP, configure the site's document root to this project's `public` folder. Do not expose the project root or `.env` through Apache.

## Demo accounts

All seeded accounts use the password `Secret@123`.

| Role | Email |
| --- | --- |
| Resident | `ramesh.patel@gmail.com` |
| Administrator | `admin@societymanager.org` |
| Maintenance staff | `maintenance.lead@societymanager.org` |

## Features

- Session-based login, registration, password resets, logout, password hashing, CSRF protection and role-gated actions
- Resident dashboard and society-wide administrator metrics
- Monthly and single-flat bill generation with duplicate protection, bill history, payment proof review and downloadable PDF invoices
- Administrator tools to add residents manually or import residents and monthly charges/payments from CSV
- Resident owners can add, update, or remove tenant contact details and upload private PDF rent agreements for their own flat; administrators can review tenant records and download agreements, while tenants do not receive portal accounts
- Resident maintenance requests, staff assignment, status changes and complaint audit history
- Published notices with category/search filters and administrator publishing
- Society resident and flat directory
- Society registration with pending approval, isolated society data, and a first administrator account
- Super-admin society approval/suspension, workspace switching, and an all-society activity log
- Audit records for sign-ins, password-reset requests, bill/proof/agreement downloads, and society data changes (passwords and private file paths are excluded)
- Responsive HTML/CSS views with jQuery-powered navigation and search
- Site-wide light and dark themes with a saved browser preference

Owners can submit a transaction reference and/or a JPG, PNG, or WebP payment screenshot (up to 5 MB) for each bill. The bill remains unpaid until an administrator verifies the submission; rejected proof can be corrected and resubmitted. Payment recording stores proof for review only; no payment gateway is connected.
Bill PDFs can be downloaded from the billing page by the flat owner or an administrator.
Tenant agreement PDFs are stored in private application storage and can be downloaded only by the assigned owner or an administrator; uploads are limited to 10 MB.
Administrators can download blank CSV templates and separate sample CSV files from the directory and billing pages. The samples contain example values only; replace all resident sample data, including its password, and the sample flat/payment details before importing. The CSV files open in Excel; save completed sheets as CSV before uploading. Resident imports include the initial password in each row. Imports accept up to 500 rows, validate the whole file first, and save all rows in one database transaction; any invalid row rejects the entire import and reports its row number.
Password-reset delivery uses Laravel's mail configuration. The default local `MAIL_MAILER=log` setting writes reset messages to the Laravel log; configure SMTP in `.env` to deliver email.
Existing society data is retained and assigned to the initial `Lotus Orchid` workspace by the multi-society migration. New society registrations remain pending until a super admin approves them; the first administrator can sign in after approval. A super admin can open an active society workspace to use its regular administrative tools, then leave that workspace to return to platform-wide management. Activity history is recorded from the migration onward; past actions are not reconstructed.

## Database and testing

Migrations are the source of truth for the Laravel schema. Demo content can be re-created in a disposable local database with `php artisan migrate:fresh --seed`; this command drops existing tables, so do not run it against a database containing data you need.

Run the automated feature tests with:

```powershell
php artisan test
```
