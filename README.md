# VisionCare Optical Shop Management CMS (`optical-mgt`)
### Complete Optical Shop Management, Clinical Dispensary & Business Intelligence Suite

A clean, robust, and lightweight Optical Shop Management CMS built in **Raw PHP 8+** and **MySQL** with a solid-color, non-gradient **Bootstrap 5** administration panel and interactive **Chart.js** analytics.

---

## 🚀 Technology Stack

- **Backend**: Raw PHP 8+ (No frameworks, no Composer dependencies, no Laravel architecture)
- **Database**: MySQL 5.7+ / 8.0+ / MariaDB via **PDO** (Prepared Statements, Transactions, UTF-8 `utf8mb4`)
- **Frontend**: HTML5, CSS3 (Solid-color clinical styling, zero gradients), Vanilla JavaScript
- **UI Framework**: Bootstrap 5.3.3 & Bootstrap Icons 1.11.3
- **Data Visualizations**: Chart.js 4.4.1 (Clean solid clinical palette, zero gradients)
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
│   ├── 05_orders_seed.sql          # Sample orders, item snapshots, advance & final payments
│   └── 06_reports_schema.sql       # Reporting indexing reference & summary verification
│
├── includes/
│   ├── header.php              # Common HTML head, metadata, and CSS links
│   ├── navbar.php              # Top navbar with user dropdown and sidebar toggle
│   ├── sidebar.php             # Responsive collapsible sidebar navigation (Active routes)
│   ├── footer.php              # Common footer, Bootstrap scripts, closing tags
│   ├── functions.php           # Security & formatting helpers (CSRF, XSS, code gen, order & report helpers)
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
│   ├── orders/                 # Order Management & Billing Module (Phase 5)
│   │   ├── index.php           # Orders list with search, status/payment/date filters, KPI metrics
│   │   ├── create.php          # Order creation (Customer, Rx, dynamic items, pricing, initial payment)
│   │   ├── view.php            # Detailed order view with snapshots, financials, payment history
│   │   ├── edit.php            # Controlled pending order editor with pricing safeguards
│   │   ├── update-status.php   # Status transition handler (Stock deduction & cancellation restoration)
│   │   ├── add-payment.php     # Record additional payments & auto-recalculate due balance
│   │   ├── delete.php          # Delete pending order handler (Administrator only)
│   │   └── print.php           # Print-friendly invoice & dispensing slip (@media print)
│   │
│   └── reports/                # Reports & Analytics Module (Phase 6)
│       ├── index.php           # Reports & Analytics Hub dashboard with time-period filters & KPIs
│       ├── sales.php           # Comprehensive sales & revenue report (Subtotal, discounts, net sales)
│       ├── payments.php        # Collections report by payment method (Cash, Card, Mobile, Bank)
│       ├── due.php             # Outstanding customer dues report with instant collection actions
│       ├── orders.php          # Order lifecycle workflow & status distribution report
│       ├── customers.php       # Patient demographics, growth trends & lifetime spenders report
│       ├── products.php        # Inventory stock health, cost & retail valuation, replenishment alerts
│       ├── prescriptions.php   # Clinical examination log & refraction records report (Admin/Optician)
│       └── print.php           # Universal clean printable view & PDF export layout for all report types
│
├── uploads/
│   └── avatars/
│       ├── .htaccess           # Security: Disallows direct script execution
│       └── .gitkeep
│
├── .htaccess                   # Root Apache security config
├── index.php                   # Executive Dashboard with live Financial, Workflow & Inventory Charts
└── README.md                   # Complete system documentation
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
   - **Step 6**: `database/06_reports_schema.sql` (Verifies performance indexing for reporting queries)

3. Import SQL seed files:
   - **Step 7**: `database/01_auth_seed.sql` (Inserts system roles and seed user accounts)
   - **Step 8**: `database/02_customers_seed.sql` (Inserts realistic sample customer records)
   - **Step 9**: `database/03_prescriptions_seed.sql` (Inserts realistic optical refraction records)
   - **Step 10**: `database/04_products_seed.sql` (Inserts sample categories & optical products)
   - **Step 11**: `database/05_orders_seed.sql` (Inserts sample orders, line item snapshots, payments)

---

## 🔑 Default Login Credentials

All passwords are encrypted with PHP's `password_hash(..., PASSWORD_BCRYPT)`:

| Role | Username | Email | Password | Customers | Prescriptions | Products | Orders & Billing | Reports & Analytics |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin@opticalmgt.com` | `Admin@123` | Full CRUD | Full CRUD | Full CRUD + Margins | Full CRUD, Status, Payments, Delete Pending, Print | Full Access (All 8 Reports + Print/PDF) |
| **Optician** | `optician` | `optician@opticalmgt.com` | `Admin@123` | View, Create, Edit | Full CRUD | View, Create, Edit | View, Create, Edit Pending, Status, Print | Full Access (Including Clinical Rx Logs) |
| **Sales Staff** | `sales` | `sales@opticalmgt.com` | `Admin@123` | View, Create, Edit | View Only | View, Create, Edit | View, Create, Edit Pending, Payments, Status, Print | Standard Reports (Sales, Payments, Due, Orders, Products, Customers) |

---

## 📊 Analytics & Reporting Hub (Phase 6)

The application provides real-time business intelligence calculated directly from MySQL transactions:

1. **Dashboard Visualizations (`index.php`)**:
   - **Revenue & Collections Trend**: 6-month comparative bar chart contrasting invoiced sales with actual realized cash receipts.
   - **Order Lifecycle Donut**: Color-coded distribution across Pending, Confirmed, Processing, Ready, Delivered, and Cancelled stages.
   - **Recent Transactions Ledger**: Side-by-side feeds for latest customer orders and latest payment receipts.

2. **Specialized Report Suites (`modules/reports/`)**:
   - **Sales Report (`sales.php`)**: Net sales calculations, discounts, gross volume, search, and date filters.
   - **Payments Report (`payments.php`)**: Collections ledger broken down by payment methods (Cash, Card, Mobile Banking, Bank Transfer).
   - **Due Tracking Report (`due.php`)**: Outstanding customer receivables with direct collection shortcuts.
   - **Order Workflow Report (`orders.php`)**: Complete lifecycle tracking and volume per workflow stage.
   - **Customer Growth Report (`customers.php`)**: Patient acquisition trends, demographic filters, and top spending clients.
   - **Inventory & Valuation Report (`products.php`)**: Stock valuation at cost and retail, stock replenishment alerts.
   - **Clinical Rx Report (`prescriptions.php`)**: Refraction examination audit log gated strictly to Admin and Opticians.
   - **Universal Print & PDF Layout (`print.php`)**: Clean A4 printable layout with verification signatures and `@media print` styling.

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