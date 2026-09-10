# VisionCare Optical Shop Management CMS (`optical-mgt`)
### Phase 1: Project Foundation + Authentication

A clean, robust, and lightweight Optical Shop Management CMS built in **Raw PHP 8+** and **MySQL** with a solid-color, non-gradient **Bootstrap 5** administration panel.

---

## 🚀 Technology Stack

- **Backend**: Raw PHP 8+ (No heavy frameworks or Composer dependencies)
- **Database**: MySQL 5.7+ / 8.0+ / MariaDB via **PDO** (Prepared Statements, UTF-8 `utf8mb4`)
- **Frontend**: HTML5, CSS3 (Solid-color clinical styling, zero gradients), Vanilla JavaScript
- **UI Framework**: Bootstrap 5.3.3 & Bootstrap Icons 1.11.3
- **Web Server**: Apache / XAMPP Compatible

---

## 📁 Project Structure

```text
optical-mgt/
├── assets/
│   ├── css/
│   │   └── style.css            # Custom solid-color UI styling (No gradients)
│   ├── js/
│   │   └── app.js              # Sidebar toggle, localStorage persistence, tooltips, avatar preview
│   └── images/
│       └── logo.svg            # Optical CMS vector logo
│
├── auth/
│   ├── login.php               # Login form & authentication processor
│   ├── logout.php              # Secure session termination handler
│   ├── profile.php             # User profile view and update UI
│   ├── update-profile.php      # Profile update & avatar upload processor
│   └── change-password.php     # Password change form & verification processor
│
├── config/
│   ├── config.php              # Global constants, dynamic base URL, secure session init
│   ├── database.php            # Singleton PDO database connection handler
│   └── auth.php                # Authentication & RBAC helper functions
│
├── database/
│   ├── 01_auth_schema.sql      # Independent SQL schema (roles, users tables)
│   └── 01_auth_seed.sql        # Seed data (roles + default bcrypt admin & staff)
│
├── includes/
│   ├── header.php              # Common HTML head, metadata, and CSS links
│   ├── navbar.php              # Top navbar with user dropdown and sidebar toggle
│   ├── sidebar.php             # Responsive collapsible sidebar navigation
│   ├── footer.php              # Common footer, Bootstrap scripts, closing tags
│   ├── functions.php           # Security helpers (CSRF, XSS escaping, redirects, avatar utils)
│   └── flash.php               # Session-based alert notifications
│
├── modules/
│   └── .gitkeep                # Future business modules (Phase 2)
│
├── uploads/
│   └── avatars/
│       ├── .htaccess           # Security: Disallows direct script execution
│       └── .gitkeep
│
├── .htaccess                   # Root Apache security config
├── index.php                   # Dashboard shell with real DB metrics
└── README.md                   # System documentation
```

---

## 🛠️ XAMPP Installation & Setup Guide

### 1. Place Project in `htdocs`
Ensure the project is located at:
```text
C:\xampp\htdocs\optical-mgt\
```

### 2. Start Apache and MySQL in XAMPP
Open the **XAMPP Control Panel** and start both **Apache** and **MySQL** services.

### 3. Database Setup & Import Order
Open **phpMyAdmin** (`http://localhost/phpmyadmin/`) or use the MySQL command line:

1. Create the database:
   ```sql
   CREATE DATABASE `optical_mgt` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import SQL files in this exact sequential order:
   - **Step 1**: `database/01_auth_schema.sql` (Creates `roles` and `users` tables)
   - **Step 2**: `database/01_auth_seed.sql` (Inserts system roles and seed user accounts)

### 4. Database Configuration
Edit [`config/database.php`](file:///c:/xampp/htdocs/optical-mgt/config/database.php) if your local MySQL settings differ from standard defaults:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'optical_mgt');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 5. Access the Application
Open your browser and navigate to:
```text
http://localhost/optical-mgt/
```
Unauthenticated requests will automatically redirect to the login page (`auth/login.php`).

---

## 🔑 Default Login Credentials

All passwords are encrypted with PHP's `password_hash(..., PASSWORD_BCRYPT)`:

| Role | Username | Email | Password |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@opticalmgt.com` | `Admin@123` |
| **Optician** | `optician` | `optician@opticalmgt.com` | `Admin@123` |
| **Sales Staff** | `sales` | `sales@opticalmgt.com` | `Admin@123` |

---

## 🔐 Security Features Implemented

1. **Prepared Statements**: All database operations use PDO prepared statements to eliminate SQL Injection risks.
2. **Password Security**: Passwords are saved using modern `PASSWORD_BCRYPT` hashing; verification uses `password_verify()`.
3. **Session Hardening**:
   - `session.use_strict_mode = 1`
   - `session.use_only_cookies = 1`
   - Secure cookie attributes: `httponly = true`, `samesite = Lax`.
   - `session_regenerate_id(true)` called upon successful authentication.
4. **CSRF Protection**: Form submissions require a valid `csrf_token` checked using `hash_equals()`.
5. **XSS Prevention**: Safe output escaping with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` via the `e()` helper function.
6. **Avatar Upload Hardening**:
   - Extension whitelist: `.jpg`, `.jpeg`, `.png`, `.webp`.
   - MIME verification via PHP `finfo` (`image/jpeg`, `image/png`, `image/webp`).
   - File size restricted to 2MB.
   - Unique randomized filenames (`avatar_...`) to prevent directory traversal.
   - Upload directory protected by `.htaccess` denying PHP and script execution.
   - Fallback SVG avatar generated with user initials to prevent broken image icons.

---

## 🖥️ UI & Sidebar Features

- **Collapsible Sidebar**: Toggle between expanded (`260px`) and collapsed (`72px`) mode.
- **State Persistence**: Collapsed/expanded state persists across page refreshes via `localStorage`.
- **Tooltips**: Bootstrap 5 tooltips display menu names in collapsed sidebar mode.
- **Responsive Offcanvas**: Seamless mobile navigation drawer with backdrop overlay.
- **Solid Color Aesthetic**: Clinical, medical-grade solid palette (`#0f172a`, `#0284c7`, `#f8fafc`) without color gradients.

---

## 📌 Development Notes for Subsequent Phases

- Future business modules (Customers, Prescriptions, Frames/Lenses Products, Orders & Invoicing) will reside in the `modules/` directory without requiring modifications to the core authentication architecture.
- Reusable authentication helpers (`isLoggedIn()`, `requireLogin()`, `currentUser()`, `requireRole()`) are globally accessible across all pages.