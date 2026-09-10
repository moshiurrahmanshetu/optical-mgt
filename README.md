# VisionCare Optical Shop Management CMS (`optical-mgt`)
### Phase 1 + Phase 2: Project Foundation, Authentication & Customer Management

A clean, robust, and lightweight Optical Shop Management CMS built in **Raw PHP 8+** and **MySQL** with a solid-color, non-gradient **Bootstrap 5** administration panel.

---

## 🚀 Technology Stack

- **Backend**: Raw PHP 8+ (No frameworks, no Composer dependencies, no Laravel architecture)
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
│   ├── 01_auth_schema.sql      # Authentication schema (roles, users)
│   ├── 01_auth_seed.sql        # Authentication seed data (admin, optician, sales staff)
│   ├── 02_customers_schema.sql # Customer Management schema (customers table)
│   └── 02_customers_seed.sql   # Realistic sample customer records (CUS-00001 to CUS-00005)
│
├── includes/
│   ├── header.php              # Common HTML head, metadata, and CSS links
│   ├── navbar.php              # Top navbar with user dropdown and sidebar toggle
│   ├── sidebar.php             # Responsive collapsible sidebar navigation (Active routes)
│   ├── footer.php              # Common footer, Bootstrap scripts, closing tags
│   ├── functions.php           # Security helpers (CSRF, XSS escaping, code generator, avatar utils)
│   └── flash.php               # Session-based alert notifications
│
├── modules/
│   └── customers/              # Customer Management Module
│       ├── index.php           # Customer list with search, status filters & pagination
│       ├── create.php          # Add customer form with auto-code generation
│       ├── edit.php            # Edit customer details & address/notes
│       ├── view.php            # Customer profile view with future module tabs
│       ├── toggle-status.php   # POST status toggle handler (Active/Inactive)
│       └── delete.php          # POST delete customer handler (Administrator only)
│
├── uploads/
│   └── avatars/
│       ├── .htaccess           # Security: Disallows direct script execution
│       └── .gitkeep
│
├── .htaccess                   # Root Apache security config
├── index.php                   # Dashboard with live Customer & Staff metrics
└── README.md                   # System documentation
```

---

## 🛠️ Database Setup & Import Order

Open **phpMyAdmin** (`http://localhost/phpmyadmin/`) or MySQL CLI:

1. Create the database:
   ```sql
   CREATE DATABASE `optical_mgt` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import SQL files in this exact sequential order:
   - **Step 1**: `database/01_auth_schema.sql` (Creates `roles` and `users` tables)
   - **Step 2**: `database/01_auth_seed.sql` (Inserts system roles and seed user accounts)
   - **Step 3**: `database/02_customers_schema.sql` (Creates `customers` table)
   - **Step 4**: `database/02_customers_seed.sql` (Inserts realistic sample customer records)

---

## 🔑 Default Login Credentials

All passwords are encrypted with PHP's `password_hash(..., PASSWORD_BCRYPT)`:

| Role | Username | Email | Password | Permissions |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@opticalmgt.com` | `Admin@123` | Full CRUD, Status toggle, Delete customers |
| **Optician** | `optician` | `optician@opticalmgt.com` | `Admin@123` | View, Create, Edit customers |
| **Sales Staff** | `sales` | `sales@opticalmgt.com` | `Admin@123` | View, Create, Edit customers (No Delete) |

---

## 👥 Customer Management Module Features

1. **Auto-Generated Sequential Customer Code**:
   - Format: `CUS-00001`, `CUS-00002`, `CUS-00003`...
   - Calculated server-side by detecting the highest numeric suffix in the database and checking for collisions.
2. **Search & Status Filtering**:
   - Real-time search by customer code, full name, phone number, and email.
   - Status filtering by `All`, `Active`, or `Inactive`.
3. **Optimized Pagination**:
   - Efficient pagination using `LIMIT` and `OFFSET` with a single `COUNT(*)` query.
   - Preserves search and filter parameters across page links.
4. **Role-Based Access Control**:
   - All authenticated roles can view, register, and update customer profiles.
   - Only the **Administrator** role can permanently delete customer records.
5. **Customer Profile & Clinical Notes**:
   - Full contact details, age calculation, address, and optical preferences/notes.
   - Dedicated placeholder cards for upcoming **Prescription Management (Phase 3)** and **Orders & Billing (Phase 4)**.