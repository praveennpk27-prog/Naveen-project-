# Hostinger Database Setup Guide — Anaesthesiologist Logbook (Sign Up & Sign In)

This package connects your **Anaesthesiologist Logbook** Sign Up (`doctor_name`, `mobile_number`, `password`) and Sign In (`mobile_number`, `password`) directly to your **Hostinger MySQL Database**.

---

## Step 1: Create Your MySQL Database in Hostinger hPanel

1. Log in to **Hostinger hPanel** (`hpanel.hostinger.com`).
2. Go to **Websites** -> click **Manage** next to your domain.
3. In the left sidebar, open **Databases** -> **Management**.
4. Under **Create a New MySQL Database And Database User**, enter:
   - **MySQL Database Name**: e.g. `logbook` (Hostinger prefixes it like `u123456789_logbook`)
   - **MySQL Username**: e.g. `admin` (Hostinger prefixes it like `u123456789_admin`)
   - **Password**: Create a strong password
5. Click **Create** and copy the exact prefixed **Database Name**, **Username**, and **Password**.

---

## Step 2: Import `database.sql` (Optional — `api.php` also auto-creates tables)

1. In **Databases -> Management**, click **Enter phpMyAdmin** next to your new database.
2. Click the **Import** tab at the top.
3. Choose the included `database.sql` file and click **Import / Go**.
4. You will see two tables created:
   - `doctors` — stores `id`, `doctor_name`, `mobile_number` (Unique), `password_hash` (Bcrypt), `profile_json`, `cases_json`, `created_at`, `updated_at`
   - `logbook_cases` — stores individual case rows linked by `doctor_id`

---

## Step 3: Update Credentials in `api.php` and Upload to `public_html`

1. Open `api.php` and edit lines 28–31 with your Hostinger database credentials:
   ```php
   $DB_HOST = "localhost";
   $DB_NAME = "u123456789_logbook";     // Your Hostinger Database Name
   $DB_USER = "u123456789_admin";       // Your Hostinger Username
   $DB_PASS = "YourStrongPassword123!"; // Your Hostinger Password
   ```
2. Open **Hostinger hPanel -> Files -> File Manager**.
3. Open the `public_html` folder and upload both:
   - `index.html`
   - `api.php`

---

## Step 4: Test Sign Up & Sign In

1. Visit your website URL (e.g. `https://yourdomain.com/`).
2. Click the **Sign Up** tab:
   - Enter **Doctor Name**, **Mobile Number**, and **Password**, then click **Create Doctor Account**.
3. Once registered, the screen switches to **Sign In**:
   - Enter your **Mobile Number** and **Password** to log in and access your personal Anaesthesiologist Logbook.






$DB_HOST = "localhost";                  // Usually "localhost" on Hostinger
$DB_NAME = "u499301058_DocLogDataCC";         // Your Hostinger MySQL Database Name
$DB_USER = "u499301058_Naveen";           // Your Hostinger MySQL Username
$DB_PASS = "RiverStoneGlow27";  