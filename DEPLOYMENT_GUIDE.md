# 🚀 TORVO SPAIR - cPanel Deployment Guide

This guide provides step-by-step instructions to move your **TORVO SPAIR B2B Portal** from your local XAMPP environment to a live cPanel hosting account.

---

## 📋 Pre-Deployment Checklist
1. **PHP Version**: Ensure your cPanel is set to **PHP 7.4 or 8.x** (PHP 8.2 is recommended).
2. **Database**: Prepare a new MySQL database name, username, and password via the "MySQL® Database Wizard" in cPanel.
3. **Domain/Path**: Decide if you are deploying to the root (e.g., `torvotools.com`) or a subdirectory.

---

## 🛠️ Deployment Steps

### 1. Upload Files
- Compress all project files into a `.zip` archive (everything inside `c:\xampp\htdocs\torvo_spair`).
- Use **cPanel File Manager** to upload the zip to your `public_html` (or desired folder).
- Extract the files.

### 2. Configure Database Credentials
- Open `config/db_config.php` in the cPanel File Editor.
- Update the credentials with your live details:
  ```php
  <?php
  // config/db_config.php
  define('DB_HOST', 'localhost'); // Usually localhost on cPanel
  define('DB_USER', 'your_cpanel_db_user');
  define('DB_PASS', 'your_secure_password');
  define('DB_NAME', 'your_cpanel_db_name');
  ?>
  ```
- **Note**: Most cPanel hosts prefix the DB name and user with your account username (e.g., `hotelsun_torvo`).

### 3. Run One-Click Setup
- Visit `https://yourdomain.com/setup.php` in your browser.
- Click **"Run Database Setup"**.
- This will automatically create all tables, insert sample data, and create the necessary `assets/uploads/` directory with correct permissions.
- **IMPORTANT**: Once you see the "Setup Complete" message, **delete `setup.php`** from your server for security.

### 4. Adjust `.cpanel.yml` (Optional)
If you use **Git Version Control** in cPanel for automatic deployments, update `.cpanel.yml` with your account's home directory path:
- Change `/home/hotelsunplaza/torvotools.com/` to your actual home directory path (found in cPanel sidebar).

---

## 🔐 Post-Deployment Security
1. **Delete Installation Files**:
   - `setup.php` (Mandatory)
   - `database.sql`
   - `migrate_schema.sql`
2. **Permissions**:
   - Ensure `assets/uploads/` is writable (usually `755`).
   - `config/` files should be `644`.

## 🌐 Dynamic URL Handling
The system automatically detects your URL. Whether you use `http` or `https`, or if you move the site to a different sub-folder, the `APP_URL` will adjust itself without manual configuration.

---
*Support: If you encounter a "Database Connection Failed" screen, double-check your credentials in `config/db_config.php`.*
