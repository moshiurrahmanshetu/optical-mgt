# VisionCare Optical Shop Management CMS (`optical-mgt`)
### Phase 1 + Phase 2 + Phase 3 + Phase 4: Foundation, Customers, Prescriptions & Product Catalog

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
│   ├── 01_auth_schema.sql          # Authentication schema (roles, users)
│   ├── 01_auth_seed.sql            # Authentication seed data (admin, optician, sales staff)
│   ├── 02_customers_schema.sql     # Customer Management schema (customers table)
│   ├── 02_customers_seed.sql       # Sample customer records (CUS-00001 to CUS-00005)
│   ├── 03_prescriptions_schema.sql # Prescription Management schema (prescriptions table)
│   ├── 03_prescriptions_seed.sql   # Sample optical refraction records (OD/OS measurements)
│   ├── 04_products_schema.sql      # Product Catalog schema (categories, products)
│   └── 04_products_seed.sql        # Sample catalog records (Frames, Lenses, Accessories)
│
├── includes/
│   ├── header.php              # Common HTML head, metadata, and CSS links
│   ├── navbar.php              # Top navbar with user dropdown and sidebar toggle
│   ├── sidebar.php             # Responsive collapsible sidebar navigation (Active routes)
│   ├── footer.php              # Common footer, Bootstrap scripts, closing tags
│   ├── functions.php           # Security & formatting helpers (CSRF, XSS, code gen, optical/stock helpers)
│   └── flash.php               # Session-based alert notifications
│
├── modules/
│   ├── customers/              # Customer Management Module (Phase 2)
│   │   ├── index.php           # Customer list with search, status filters & pagination
│   │   ├── create.php          # Add customer form with auto-code generation
│   │   ├── edit.php            # Edit customer details & address/notes
│   │   ├── view.php            # Customer profile view with live Prescription History
│   │   ├── toggle-status.php   # POST status toggle handler (Active/Inactive)
│   │   └── delete.php          # POST delete customer handler (Administrator only)
│   │
│   ├── prescriptions/          # Prescription Management Module (Phase 3)
│   │   ├── index.php           # Prescription list with search, date filters & pagination
│   │   ├── create.php          # Add prescription form with OD/OS cards and pre-select
│   │   ├── edit.php            # Edit prescription optical refraction parameters
│   │   ├── view.php            # Medical Rx details view with optical grid & print option
│   │   └── delete.php          # POST delete prescription handler (Admin & Optician)
│   │
│   └── products/               # Product / Optical Catalog Module (Phase 4)
│       ├── index.php           # Product list with search, classification & stock filters, pagination
│       ├── create.php          # Dynamic product creation form (Frames, Lenses, Accessories)
│       ├── edit.php            # Edit product specifications, pricing, stock levels
│       ├── view.php            # Product specification sheet, margin analysis & inventory status
│       ├── categories.php      # Category management CRUD with product count guard (Admin only)
│       ├── toggle-status.php   # POST status toggle handler (Active/Inactive)
│       └── delete.php          # POST delete product handler (Administrator only)
│
├── uploads/
│   └── avatars/
│       ├── .htaccess           # Security: Disallows direct script execution
│       └── .gitkeep
│
├── .htaccess                   # Root Apache security config
├── index.php                   # Dashboard with live Customer, Rx, Catalog & Inventory KPIs
└── README.md                   # System documentation
```

---

## 🛠️ Database Setup & Import Order

Open **phpMyAdmin** (`http://localhost/phpmyadmin/`) or MySQL CLI:

1. Create the database:
   ```sql
   CREATE DATABASE `optical_mgt` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Import SQL schema files in this exact sequential dependency order:
   - **Step 1**: `database/01_auth_schema.sql` (Creates `roles` and `users` tables)
   - **Step 2**: `database/02_customers_schema.sql` (Creates `customers` table)
   - **Step 3**: `database/03_prescriptions_schema.sql` (Creates `prescriptions` table with FKs)
   - **Step 4**: `database/04_products_schema.sql` (Creates `categories` and `products` tables with FKs)

3. Import SQL seed files:
   - **Step 5**: `database/01_auth_seed.sql` (Inserts system roles and seed user accounts)
   - **Step 6**: `database/02_customers_seed.sql` (Inserts realistic sample customer records)
   - **Step 7**: `database/03_prescriptions_seed.sql` (Inserts realistic optical refraction records)
   - **Step 8**: `database/04_products_seed.sql` (Inserts sample categories & optical products)

---

## 🔑 Default Login Credentials

All passwords are encrypted with PHP's `password_hash(..., PASSWORD_BCRYPT)`:

| Role | Username | Email | Password | Customer Permissions | Prescription Permissions | Product Catalog Permissions |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@opticalmgt.com` | `Admin@123` | Full CRUD, Status, Delete | Full CRUD (View, Create, Edit, Delete) | Full CRUD, Categories, Cost Price, Delete |
| **Optician** | `optician` | `optician@opticalmgt.com` | `Admin@123` | View, Create, Edit | Full CRUD (View, Create, Edit, Delete) | View, Create, Edit (Catalog) |
| **Sales Staff** | `sales` | `sales@opticalmgt.com` | `Admin@123` | View, Create, Edit | View Only | View, Create, Edit (Catalog) |

---

## 📦 Optical Product Catalog & Inventory Features (Phase 4)

1. **Classification System**:
   - **Eyeglass Frames** (`FRM-XXXXX`): Material (Acetate, Titanium, etc.), Color/Finish, Brand, Model/SKU.
   - **Ophthalmic Lenses** (`LNS-XXXXX`): Lens Design (Single Vision, Progressive, Photochromic), Material Index (1.50, 1.67, 1.74).
   - **Optical Accessories** (`ACC-XXXXX`): Cases, microfiber cloths, anti-fog solutions, cords.
2. **Category Management**:
   - Category CRUD restricted to Administrators.
   - Product count deletion protection prevents orphaned catalog items.
3. **Inventory & Stock Health**:
   - Out of Stock alert when `stock_quantity <= 0`.
   - Low Stock warning badge when `stock_quantity <= low_stock_threshold`.
   - In Stock badge when inventory is healthy.
4. **Commercial Margin Analysis**:
   - Cost price vs. Retail price margin calculation and gross margin % for Administrators.