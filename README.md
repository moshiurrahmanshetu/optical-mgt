# VisionCare Optical Shop Management CMS (`optical-mgt`)
### Phase 1 + Phase 2 + Phase 3 + Phase 4 + Phase 5: Complete Optical Shop Management CMS

A clean, robust, and lightweight Optical Shop Management CMS built in **Raw PHP 8+** and **MySQL** with a solid-color, non-gradient **Bootstrap 5** administration panel.

---

## 🚀 Technology Stack

- **Backend**: Raw PHP 8+ (No frameworks, no Composer dependencies, no Laravel architecture)
- **Database**: MySQL 5.7+ / 8.0+ / MariaDB via **PDO** (Prepared Statements, Transactions, UTF-8 `utf8mb4`)
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
│   ├── 04_products_seed.sql        # Sample catalog records (Frames, Lenses, Accessories)
│   ├── 05_orders_schema.sql        # Order Management schema (orders, order_items, payments)
│   └── 05_orders_seed.sql          # Sample orders, item snapshots, advance & final payments
│
├── includes/
│   ├── header.php              # Common HTML head, metadata, and CSS links
│   ├── navbar.php              # Top navbar with user dropdown and sidebar toggle
│   ├── sidebar.php             # Responsive collapsible sidebar navigation (Active routes)
│   ├── footer.php              # Common footer, Bootstrap scripts, closing tags
│   ├── functions.php           # Security & formatting helpers (CSRF, XSS, code gen, order helpers)
│   └── flash.php               # Session-based alert notifications
│
├── modules/
│   ├── customers/              # Customer Management Module (Phase 2)
│   │   ├── index.php           # Customer list with search, status filters & pagination
│   │   ├── create.php          # Add customer form with auto-code generation
│   │   ├── edit.php            # Edit customer details & address/notes
│   │   ├── view.php            # Customer profile with live Rx & Order History
│   │   ├── toggle-status.php   # POST status toggle handler (Active/Inactive)
│   │   └── delete.php          # POST delete customer handler (Administrator only)
│   │
│   ├── prescriptions/          # Prescription Management Module (Phase 3)
│   │   ├── index.php           # Prescription list with search, date filters & pagination
│   │   ├── create.php          # Add prescription form with OD/OS cards and pre-select
│   │   ├── edit.php            # Edit prescription optical refraction parameters
│   │   ├── view.php            # Medical Rx details view with optical grid & related orders
│   │   └── delete.php          # POST delete prescription handler (Admin & Optician)
│   │
│   ├── products/               # Product / Optical Catalog Module (Phase 4)
│   │   ├── index.php           # Product list with search, classification & stock filters, pagination
│   │   ├── create.php          # Dynamic product creation form (Frames, Lenses, Accessories)
│   │   ├── edit.php            # Edit product specifications, pricing, stock levels
│   │   ├── view.php            # Product specification sheet, margin analysis & inventory status
│   │   ├── categories.php      # Category management CRUD with product count guard (Admin only)
│   │   ├── toggle-status.php   # POST status toggle handler (Active/Inactive)
│   │   └── delete.php          # POST delete product handler (Administrator only)
│   │
│   └── orders/                 # Order Management & Billing Module (Phase 5)
│       ├── index.php           # Orders list with search, status/payment/date filters, KPI metrics
│       ├── create.php          # Order creation (Customer, Rx, dynamic items, pricing, initial payment)
│       ├── view.php            # Detailed order view with snapshots, financials, payment history
│       ├── edit.php            # Controlled pending order editor with pricing safeguards
│       ├── update-status.php   # Status transition handler (Stock deduction & cancellation restoration)
│       ├── add-payment.php     # Record additional payments & auto-recalculate due balance
│       ├── delete.php          # Delete pending order handler (Administrator only)
│       └── print.php           # Print-friendly invoice & dispensing slip (@media print)
│
├── uploads/
│   └── avatars/
│       ├── .htaccess           # Security: Disallows direct script execution
│       └── .gitkeep
│
├── .htaccess                   # Root Apache security config
├── index.php                   # Dashboard with live Financial, Order, Customer & Catalog KPIs
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
   - **Step 5**: `database/05_orders_schema.sql` (Creates `orders`, `order_items`, and `payments` tables with FKs)

3. Import SQL seed files:
   - **Step 6**: `database/01_auth_seed.sql` (Inserts system roles and seed user accounts)
   - **Step 7**: `database/02_customers_seed.sql` (Inserts realistic sample customer records)
   - **Step 8**: `database/03_prescriptions_seed.sql` (Inserts realistic optical refraction records)
   - **Step 9**: `database/04_products_seed.sql` (Inserts sample categories & optical products)
   - **Step 10**: `database/05_orders_seed.sql` (Inserts sample orders, line item snapshots, payments)

---

## 🔑 Default Login Credentials

All passwords are encrypted with PHP's `password_hash(..., PASSWORD_BCRYPT)`:

| Role | Username | Email | Password | Customer | Prescription | Products | Orders & Billing |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@opticalmgt.com` | `Admin@123` | Full CRUD | Full CRUD | Full CRUD + Margins | Full CRUD, Status, Payments, Delete Pending, Print |
| **Optician** | `optician` | `optician@opticalmgt.com` | `Admin@123` | View, Create, Edit | Full CRUD | View, Create, Edit | View, Create, Edit Pending, Status, Print |
| **Sales Staff** | `sales` | `sales@opticalmgt.com` | `Admin@123` | View, Create, Edit | View Only | View, Create, Edit | View, Create, Edit Pending, Payments, Status, Print |

---

## 👓 Order & Billing Workflow (Phase 5)

```text
Customer Selection
       ↓
Filter / Select Prescription (OD/OS)
       ↓
Add Order Items (Frames, Lenses, Accessories)
       ↓
Server-Authoritative Price & Stock Check
       ↓
Calculate Subtotal → Apply Discount → Grand Total
       ↓
Record Initial Deposit / Payment (Optional)
       ↓
Order Status Workflow:
  Pending → Confirmed [Stock Deducted Atomically] → Processing (Workshop) → Ready (Pickup) → Delivered
       ↓
Additional Payments / Full Settlement (Due = $0.00)
```

### 🔒 Critical Business & Inventory Rules

1. **Stock Commitment Point (`Confirmed`)**:
   - Stock is **NOT** deducted while an order is in `Pending` draft status.
   - When an order transitions from `Pending` → `Confirmed`, stock quantities are locked with `SELECT ... FOR UPDATE` and deducted inside an atomic database transaction.
   - Subsequent transitions (`Confirmed` → `Processing` → `Ready` → `Delivered`) do **not** double-deduct stock (`stock_deducted` flag guarantees idempotency).

2. **Order Cancellation & Stock Restoration**:
   - If an order in `Confirmed`, `Processing`, or `Ready` status is `Cancelled`, the deducted stock is automatically restored to the `products` inventory.
   - If a `Pending` order (where stock was never deducted) is cancelled, stock is not adjusted.

3. **Editing & Deletion Guardrails**:
   - **Editing**: Orders can only be modified while in `Pending` status. Once processing has begun, modifications are locked to maintain historical and financial integrity.
   - **Deletion**: Only Administrators can delete orders, and **only** for `Pending` orders. Processed orders must be `Cancelled` instead to preserve financial audit history.

4. **Authoritative Server-Side Pricing**:
   - Product selling prices submitted by the browser are never trusted. The server queries active prices directly from the database and recalculates all line totals, subtotals, discounts, and grand totals.

5. **Historical Snapshots**:
   - Order items store `product_name` and `product_code` snapshots at the time of sale so future product renaming or model code changes do not alter historical records.

6. **Payment & Due Tracking**:
   - Payments are recorded in the `payments` table with date, amount, payment method (Cash, Card, Mobile Banking, Bank Transfer, Other), reference, and recipient staff user.
   - `paid_amount` and `due_amount` are automatically recalculated directly from database transaction records.