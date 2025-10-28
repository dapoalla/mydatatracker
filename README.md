# My Data Tracker

My Data Tracker is a personal information tracking app designed to help you stay on top of vehicle particulars and household utilities. It’s a lightweight PHP/MySQL application with a simple dashboard for logging, reviewing, and planning renewals.

## Overview
- Track vehicle documents: Road Worthiness, Vehicle Licence, Insurance, Driver’s Licence, and Ownership Certificate.
- Log odometer readings and maintenance: mileage, service notes, dates, and costs.
- Track other essentials: Electricity meter purchases/readings, Cooking gas purchases, refills, and usage.
- Review reports to see recent activity and upcoming renewals at a glance.

## Key Features
- Vehicle particulars: store renewal dates and costs for key documents.
- Odometer & maintenance: capture mileage logs and maintenance events for each vehicle.
- Utilities tracking: record electricity meter history and cooking gas usage/purchases.
- Multi-vehicle support: switch between vehicles and view their specific records.
- Simple UI: dashboard-driven workflow with clear actions for logging and review.

## Requirements
- PHP 8.1+ (tested locally on PHP 8.2)
- MySQL/MariaDB
- Web server (Apache on cPanel or XAMPP locally)

## Quick Start (cPanel)
1. Log in to cPanel and open File Manager.
2. Upload the project ZIP to `public_html/` (or a subfolder like `public_html/datatracker/`).
3. Extract the ZIP.
4. Open `config.php` and set your database credentials:
   - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
   - Ensure the MySQL user has appropriate privileges on the database.
5. Create the database and tables if you haven’t already. If you have a schema dump, import it via phpMyAdmin.
6. Ensure PHP version matches the app requirements.
   - The included `.htaccess` pins PHP to cPanel’s `ea-php81`. Adjust as needed in cPanel > MultiPHP Manager.
7. Permissions (typical cPanel defaults):
   - Directories: `755`
   - Files: `644`
   - Keep `error_log` readable only to you; do not commit logs to Git.
8. Visit your site URL to confirm it loads. Log in/register as needed.

## Local Development (XAMPP)
1. Place the project under `C:\xampp\htdocs\datatracker\datatracker` (as in this repo).
2. Start Apache and MySQL from XAMPP.
3. Update `config.php` with local DB credentials.
4. Navigate to `http://localhost/datatracker/datatracker/`.

## Publishing to GitHub
1. Create a new GitHub repository named `mydatatracker`.
2. Initialize and push from this folder:
   ```powershell
   git init
   git add .
   git commit -m "Initial import of My Data Tracker"
   git branch -M main
   git remote add origin https://github.com/<YOUR_GITHUB_USERNAME>/mydatatracker.git
   git push -u origin main
   ```
   If using SSH, replace the remote with `git@github.com:<YOUR_GITHUB_USERNAME>/mydatatracker.git`.

## Deployment Notes and Permissions
- Make sure `display_errors` is disabled in production (set in `php.ini` or via `.htaccess`) to avoid leaking sensitive info.
- Sessions: avoid calling `session_start()` multiple times on the same request; ensure it’s included once per page load.
- Input Validation: sanitize and validate `$_GET`/`$_POST` values; the app uses prepared statements, keep that pattern for new queries.
- Unique Constraints: DB constraints are enforced (e.g., vehicles plate number, item names). Handle duplicates gracefully in UI.

## Troubleshooting
- "Access denied for user ..." from MySQL: verify the MySQL user exists, has the correct password, and privileges on the target DB from the cPanel MySQL interface.
- `session_start()` notice about existing session: ensure only one `session_start()` runs per request.
- `mysqli_stmt_bind_param(): Argument #2 ($types) cannot be empty`: ensure your bind types string (e.g., `"ssi"`) matches the parameters you pass.

## License
This project is proprietary unless stated otherwise by the repository owner.