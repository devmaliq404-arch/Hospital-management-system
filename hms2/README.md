# CarePoint Hospital Management System

A starter hospital management application built with PHP 8+, MySQL/MariaDB, HTML, and CSS. It includes public demo account registration, staff login, dashboard, patient registration, doctor directory, appointments, medical records, and billing.

## Requirements
- PHP 8.1+ with PDO MySQL enabled
- MySQL 8+ or a compatible MariaDB version
- XAMPP, WAMP, MAMP, or another PHP web server

## Install with XAMPP
1. Copy the `hospital_management` folder into XAMPP's `htdocs` directory.
2. Start Apache and MySQL from the XAMPP control panel.
3. Open `http://localhost/phpmyadmin` and import `database.sql`.
4. Open `config.php`. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` to match your MySQL setup. The defaults are for a typical local XAMPP installation.
5. Visit `http://localhost/hospital_management/register.php` to create a demo account. Use a strong password of at least 12 characters. Successful registration signs you in automatically.
6. You can also visit `http://localhost/hospital_management/login.php` to sign in later.
7. Public self-registration creates a basic `staff` account; it does not allow users to choose the `admin` role. The `setup_admin.php` page remains available only if you specifically need to initialise an administrator for development.

## Included sections
- Dashboard with patient, doctor, appointment, and outstanding-bill counts
- Patient profiles and registration numbers
- Doctor directory and specialties
- Appointment scheduling and status tracking
- Medical visit records
- Billing and payment status
- Password hashing, prepared SQL statements, HTML output escaping, CSRF tokens, and session ID regeneration on login

## Important limitations and safety
This is a starter/demo system, not a certified clinical system. Before any real use, have a qualified developer perform a security and privacy review. Add role-based permissions, audit logs, automated backups, encryption and HTTPS, account recovery and rate limiting, secure hosting, retention policies, and jurisdiction-specific compliance controls. Medical records and billing are sensitive; use only synthetic data while testing. The current prototype allows any authenticated user to manage all modules, and deletion of related records is restricted by database foreign keys. It does not implement prescriptions, pharmacy inventory, lab integrations, insurance claims, or inpatient/ward workflows.
