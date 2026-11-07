# PharmaCare — Pharmacy Management System (PHP + MySQL)

**Academic Course Project:** Web Engineering Laboratory & Theory Semester Project  
**Implementation Stack:** Core PHP 8.1+ (Procedural & Functional Modules), MySQL / MariaDB 10+, PDO (PHP Data Objects), Bootstrap 5, Bootstrap Icons, Vanilla JavaScript, CSS3.  
**Architecture:** Page Controller / Layered Lightweight MVC with Centralized Database Gateway & Transactional Point of Sale.

---

## Table of Contents
1. [Project Overview](#1-project-overview)
2. [Quick Start & Installation Guide](#2-quick-start--installation-guide)
   - [Option A: Instant Launch with Bundled Scripts](#option-a-instant-launch-with-bundled-scripts)
   - [Option B: Setup with XAMPP on Windows](#option-b-setup-with-xampp-on-windows)
3. [User Roles & Default Demo Credentials](#3-user-roles--default-demo-credentials)
4. [System Architecture & Directory Structure](#4-system-architecture--directory-structure)
5. [Database Design & Entity Relationships](#5-database-design--entity-relationships)
6. [Core Functional Modules & Pharmacy Business Logic](#6-core-functional-modules--pharmacy-business-logic)
7. [Spring Boot to Core PHP Feature Mapping](#7-spring-boot-to-core-php-feature-mapping)
8. [Deliberate Architecture & Migration Differences](#8-deliberate-architecture--migration-differences)
9. [Comprehensive Test & Validation Report](#9-comprehensive-test--validation-report)
10. [Manual Feature Test Checklist](#10-manual-feature-test-checklist)
11. [Important Migration & Setup Notes](#11-important-migration--setup-notes)

---

## 1. Project Overview

**PharmaCare** is a full-featured Pharmacy Management System engineered as a complete, reliable, and secure web application. It retains the comprehensive pharmacy business domain, data models, and clean responsive interface of an enterprise Spring Boot application while achieving lightweight deployment through **Core PHP 8.1+ and MySQL/MariaDB**.

No external runtime dependencies, Node modules, Composer packages, or Java runtimes are required. All styling and icons are powered by responsive Bootstrap 5 and modern CSS custom properties.

### Key Highlights
- **Role-Based Access Control (RBAC):** Strict separation between `ADMIN` and `STAFF` privileges.
- **Real-Time Inventory Status Engine:** Deterministic multi-stage status priority calculation (`INACTIVE` → `EXPIRED` → `OUT_OF_STOCK` → `EXPIRING_SOON` → `LOW_STOCK` → `AVAILABLE`).
- **Atomic POS Transactions:** Point-of-sale invoices execute within isolated MySQL transactions (`BEGIN TRANSACTION ... SELECT FOR UPDATE ... COMMIT`), guaranteeing stock cannot be double-sold or over-deducted.
- **Enterprise Security:** Prepared statements for 100% of queries, CSRF token validation on all POST mutations, HTML-entity sanitization on output, and Bcrypt password hashing.

---

## 2. Quick Start & Installation Guide

### Option A: Instant Launch with Bundled Scripts
The project includes self-contained execution scripts configured for the global PHP and MariaDB environment:

1. Double-click **`start-system.bat`** (or execute `./start-system.bat` in terminal).
   - Automatically verifies and starts the MariaDB database daemon on port `3306`.
   - Starts the PHP built-in web server on `http://localhost:8000`.
   - Automatically opens your default web browser to the application login screen.
2. When finished, double-click **`stop-system.bat`** to safely terminate background server processes.

---

### Option B: Setup with XAMPP on Windows

1. Install **XAMPP** with **PHP 8.1 or later**, Apache, and MySQL/MariaDB.
2. Clone or extract the repository directly into `C:\xampp\htdocs\PharmaCare-PHP\`.
3. In **XAMPP Control Panel**, start both **Apache** and **MySQL**.
4. Open **http://localhost/phpmyadmin** in your browser:
   - Click **Import** in phpMyAdmin.
   - Select `C:\xampp\htdocs\PharmaCare-PHP\database\pharmacare.sql`.
   - Click **Go**. This creates the `pharmacare` database, sets up all 6 relational tables with foreign keys, and seeds 120 demo medicine batches, categories, suppliers, and user accounts.
5. Verify `config.php`:
   - Default XAMPP settings are pre-configured: Host `127.0.0.1`, Port `3306`, User `root`, empty password `''`, Database `pharmacare`.
   - If your MySQL user password differs, update `config.php`.
6. Navigate to **http://localhost/PharmaCare-PHP/** in your browser.

> **Note:** Do not open `index.php` directly via file browsing or VS Code Live Server. PHP scripts require a running web server with the PHP PDO MySQL extension.

---

## 3. User Roles & Default Demo Credentials

The database comes pre-seeded with two accounts hashed using PHP `password_hash(..., PASSWORD_BCRYPT)`:

| Role | Username | Password | Permitted Operations |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Full administrative access: Dashboard, Inventory CRUD, Batch Management, Categories, Suppliers, Sales POS, Invoices, User Management. |
| **Pharmacy Staff** | `staff` | `staff123` | Operational access: Dashboard view, Medicine catalog & stock status lookup, Create new POS sales, View sales history, Print invoices. Administrative management is restricted. |

*(Important: These demo credentials are for academic presentation and coursework only. Passwords should be changed prior to production hosting).*

---

## 4. System Architecture & Directory Structure

PharmaCare employs a clean, modular Page Controller pattern where requests are sanitized through a front controller routing firewall (`index.php`), write actions are routed through atomic transaction handlers (`actions.php`), and core domain logic is decoupled into reusable helper modules (`includes/`).

```text
PharmaCare-PHP/
├── index.php                  # Primary GET router, session verification & authorization firewall
├── actions.php                # Centralized POST controller, input validation & atomic transactions
├── config.php                 # Global database credentials, port & regional timezone configuration
├── .htaccess                  # Apache server security configuration (disables directory index listing)
├── .gitignore                 # Standard repository ignores (OS, IDE, temporary and session files)
├── LICENSE                    # Open Source Academic MIT License
├── README.md                  # Comprehensive academic project documentation
├── start-system.bat           # Self-contained launcher for MariaDB and PHP development web server
├── stop-system.bat            # Clean termination script for background server processes
│
├── assets/                    # Static presentation assets
│   ├── css/
│   │   └── app.css            # Responsive styling, typography, cards & badge themes
│   └── js/
│       ├── app.js             # Client-side mobile drawer toggle & confirmation dialogs
│       └── sales.js           # Interactive dynamic POS cart, live row calculation & discount math
│
├── database/                  # Relational database schemas & seed files
│   ├── .htaccess              # Security rule: blocks direct HTTP access to SQL scripts
│   ├── schema.sql             # Pure DDL table definitions with foreign keys and constraints
│   └── pharmacare.sql         # Complete DDL + 120 seeded medicine batches, categories, users
│
├── includes/                  # Core application infrastructure modules
│   ├── .htaccess              # Security rule: blocks direct browser access to include files
│   ├── bootstrap.php          # Session management, PDO singleton factory & HTTP security headers
│   ├── functions.php          # Reusable domain functions, CSRF protection, queries & validators
│   ├── header.php             # Unified sidebar navigation, top bar, breadcrumbs & flash alert renderer
│   └── footer.php             # Global scripts bundle, dynamic page script loader & HTML closure
│
├── pages/                     # View templates & page controllers
│   ├── login.php              # Modern dual-pane sign-in interface
│   ├── dashboard.php          # Analytical KPIs, expiring/low stock alerts & recent invoices
│   ├── medicines.php          # Inventory table with keyword search, category & status filters
│   ├── medicine_form.php      # Comprehensive add/edit medicine form with validation
│   ├── categories.php         # Category directory with cascade deletion protection
│   ├── suppliers.php          # Supplier directory with contact details & linked item counts
│   ├── sale_form.php          # Point-of-sale billing interface with multi-item rows
│   ├── sales.php              # Historical sales ledger with search and summary metrics
│   ├── invoice.php            # Professional printable thermal/standard sales invoice
│   ├── users.php              # System account creation, role assignment & enable/disable toggling
│   ├── forbidden.php          # HTTP 403 authorization error page
│   └── not_found.php          # HTTP 404 page for invalid or unknown route queries
│
└── tests/                     # Automated testing and verification suite
    ├── smoke.php              # Standalone unit tests for status logic, sanitization & URL routing
    └── database_test.php      # Live database connectivity, foreign keys & transaction integrity test
```

---

## 5. Database Design & Entity Relationships

The relational schema is built on **MySQL InnoDB** to enforce referential integrity and foreign key cascading rules:

```text
                  ┌──────────────┐
                  │  categories  │
                  └──────┬───────┘
                         │ (1)
                         │
                         ▼ (N)
┌──────────────┐  (N)  ┌──────────────┐  (N)  ┌──────────────┐
│  suppliers   │◄──────┤  medicines   │◄──────┤  sale_items  │
└──────────────┘  (1)  └──────────────┘  (1)  └──────┬───────┘
                                                     │ (N)
                                                     │
                                                     ▼ (1)
                       ┌──────────────┐  (1)  ┌──────────────┐
                       │  app_users   │◄──────┤    sales     │
                       └──────────────┘  (N)  └──────────────┘
```

### Table Specifications
1. **`categories`**: Medicine classifications (e.g., Antibiotic, Painkiller, Antihistamine). Unique name constraints.
2. **`suppliers`**: Pharmaceutical manufacturers & distribution contacts (phone, email, company name).
3. **`medicines`**: Inventory batches storing trade name, generic name, brand, unique batch number, formulation type, purchase price, selling price, current stock quantity, minimum threshold, manufacturing date, expiry date, prescription flag, and active status flag.
4. **`app_users`**: Administrative and operational user accounts storing bcrypt hashed passwords, role (`ADMIN` or `STAFF`), and account enabled status.
5. **`sales`**: Header records for point-of-sale transactions storing generated invoice number (`INV-YYYYMMDDHHMMSS-XXXXXX`), customer name, customer phone, subtotal, discount, total amount, sale timestamp, and seller foreign key reference (`sold_by_id`).
6. **`sale_items`**: Line items linked to sales and medicines storing quantity sold, unit price charged, and line total.

---

## 6. Core Functional Modules & Pharmacy Business Logic

### Real-Time Medicine Status Priority Engine
Medicine statuses are dynamically evaluated at runtime based on the strict hierarchy below:
```text
┌──────────────┐
│   INACTIVE   │  (active = 0)
└──────┬───────┘
       ▼
┌──────────────┐
│   EXPIRED    │  (expiry_date < today)
└──────┬───────┘
       ▼
┌──────────────┐
│ OUT OF STOCK │  (quantity = 0)
└──────┬───────┘
       ▼
┌──────────────┐
│EXPIRING SOON │  (expiry_date <= today + 30 days)
└──────┬───────┘
       ▼
┌──────────────┐
│  LOW STOCK   │  (quantity <= minimum_stock_level)
└──────┬───────┘
       ▼
┌──────────────┐
│  AVAILABLE   │  (all healthy criteria met)
└──────────────┘
```
- **Sale Prevention:** Inactive or expired batches are strictly barred from point-of-sale selection and cannot be invoiced.
- **Batches Expiring Today:** Considered saleable until the date passes, consistent with standard clinical dispensary practice.

### Transactional Point-of-Sale Checkout
When a sale is submitted:
1. `actions.php` verifies the user's active session and CSRF token.
2. A database transaction begins (`$pdo->beginTransaction()`).
3. Every requested medicine row is acquired with `SELECT ... FOR UPDATE`, row-level locking inventory against concurrent checkouts.
4. Active status, expiry boundaries, and stock quantity are strictly re-verified against fresh locked state.
5. Monetary values are converted and calculated using integer cents to prevent floating-point rounding discrepancies.
6. The sale header is inserted into `sales`, child rows are bulk-inserted into `sale_items`, and inventory is deducted in `medicines`.
7. The transaction commits atomically (`$pdo->commit()`). If any item violates business logic, the entire sale rolls back without partial updates.

---

## 7. Spring Boot to Core PHP Feature Mapping

This table outlines the exact parity achieved between the original enterprise Spring Boot architecture and this Core PHP implementation:

| Original Spring Boot Source Component | Core PHP Implementation | Parity & Technical Notes |
| :--- | :--- | :--- |
| `AuthController`, `SecurityConfig` | `pages/login.php`, `actions.php`, `includes/functions.php` | Bcrypt password verification, session hardening, secure POST logout, strict role authorization firewalls. |
| `DashboardController`, `DashboardService` | `pages/dashboard.php` | Identical analytical counters, active medicine aggregates, today's sales, stock alerts & recent invoices. |
| `MedicineController`, `MedicineService` | `pages/medicines.php`, `pages/medicine_form.php`, `actions.php` | Full CRUD, unique batch validation, multi-field search, category & computed status filters, activation toggling. |
| `Medicine.getStatus()` | `includes/functions.php` (`medicine_status()`) | Identical status precedence and 30-day expiring soon inclusive window. |
| `CategoryController`, `CategoryService` | `pages/categories.php`, `actions.php` | Add/Edit/Delete with relational foreign-key usage protection preventing accidental orphan records. |
| `SupplierController`, `SupplierService` | `pages/suppliers.php`, `actions.php` | Add/Edit/Delete with relational usage protection and email/phone format validation. |
| `SaleController`, `SaleService` | `pages/sale_form.php`, `pages/sales.php`, `pages/invoice.php`, `actions.php` | Dynamic repeatable line items, discount computation, row-locking transactions, automatic stock deduction, itemized invoice. |
| `UserController`, `UserService` | `pages/users.php`, `actions.php` | Account creation, role assignment, bcrypt hashing, self-disable prevention, last-admin protection. |
| `fragments/layout.html`, `static/css/app.css` | `includes/header.php`, `includes/footer.php`, `assets/css/app.css` | Original teal/emerald branding, responsive sidebar, cards, modern typography and clean layout preserved. |
| `static/js/app.js`, inline sale JS | `assets/js/app.js`, `assets/js/sales.js` | Mobile drawer navigation, confirmation modals, interactive cart calculation and real-time total updates. |
| `V1__create_pharmacy_schema.sql` | `database/schema.sql` | Preserved foreign keys, referential cascades, and MySQL InnoDB table engine. |
| `DataInitializer.java` | `database/pharmacare.sql` | 5 categories, 3 suppliers, 2 demo accounts, and 120 seeded medicine batches. |

---

## 8. Deliberate Architecture & Migration Differences

1. **Query-Based Routing (`index.php?page=...`):** Rather than requiring Apache URL rewriting modules (`mod_rewrite`) or custom server virtual hosts, the system uses clean query routing. This guarantees the application functions immediately when placed in standard `htdocs` subfolders under XAMPP or any standard web server without configuration modifications.
2. **Standardized Single Database Engine:** Configured exclusively for MySQL / MariaDB using the native `pdo_mysql` driver, eliminating legacy H2 in-memory binaries.
3. **Clean Decoupling from Java Framework Infrastructure:** Spring-specific infrastructure (Actuator endpoints, JPA Hibernate annotations, Flyway runners) was replaced with direct, high-performance, native PHP PDO prepared statements and explicit transaction blocks.
4. **Prescription Handling Policy:** In alignment with the original Spring Boot implementation, the prescription-required attribute serves as an informational alert flag on dispense screens; it does not mandate external prescription file uploads.

---

## 9. Comprehensive Test & Validation Report

### Automated Checks & Test Results
- **PHP Syntax Linting:** 100% of PHP files pass `php -l` without errors or deprecation warnings.
- **JavaScript Syntax Check:** Static validation verified on `assets/js/app.js` and `assets/js/sales.js`.
- **Smoke Test Suite ([`tests/smoke.php`](file:///d:/PharmaCare-PHP-Complete/PharmaCare-PHP/tests/smoke.php)):**
  - Normal inventory status calculation: **PASSED**
  - Inactive status precedence over stock/expiry: **PASSED**
  - Expired status evaluation: **PASSED**
  - Out of stock status calculation: **PASSED**
  - 30-day inclusive expiring-soon status: **PASSED**
  - Low-stock threshold status: **PASSED**
  - HTML entity escaping sanitization (`e()`): **PASSED**
  - Decimal price validator: **PASSED**
  - Zero discount parsing: **PASSED**
  - Negative price rejection: **PASSED**
  - Zero quantity validation: **PASSED**
  - Zero inventory stock acceptance: **PASSED**
  - Formulation medicine types registry: **PASSED**
  - Query parameter URL generator: **PASSED**
  - Demo batch records integrity check (120 batches): **PASSED**
  - Relational foreign keys schema verification: **PASSED**
  - *Result:* **All 16 of 16 unit checks passed.**
- **Database Verification Suite ([`tests/database_test.php`](file:///d:/PharmaCare-PHP-Complete/PharmaCare-PHP/tests/database_test.php)):**
  - PDO connectivity to MariaDB 10.11 / MySQL: **PASSED**
  - Verification of 6 system tables: **PASSED**
  - Verification of seeded admin and staff accounts: **PASSED**
  - Verification of 120 medicine inventory batches: **PASSED**
  - Verification of 5 categories & 3 suppliers: **PASSED**
- **HTTP Routing & Session Integration:**
  - Unauthenticated access to `/index.php` triggers HTTP 302 redirect to `/index.php?page=login`.
  - Login form serves HTTP 200 OK with CSRF protection token.
  - POST authentication succeeds, session regenerates, and user redirects to `/index.php?page=dashboard`.
  - Non-admin staff attempting to visit `/index.php?page=users` is blocked with HTTP 403 Forbidden.

---

## 10. Manual Feature Test Checklist

Students or evaluators can manually verify each requirement using the checklist below:

1. **Role Separation:** Log in with `admin` / `admin123`. Verify the "Administration" menu is visible (Categories, Suppliers, Users). Log out and log in with `staff` / `staff123`. Verify the Administration menu is hidden and direct URL access to `?page=users` is refused with HTTP 403.
2. **Category & Supplier Management:** As admin, add a new category and supplier. Verify they appear in dropdowns when editing or adding medicines.
3. **Cascade Deletion Protection:** Try deleting a category or supplier that has existing medicines attached. The system will cleanly block the deletion with a descriptive warning message.
4. **Inventory Search & Filter:** Navigate to **Medicines**. Search by name, brand, or batch number. Filter by category or calculated status (e.g., "Expiring soon" or "Low stock").
5. **Real-Time Status Trigger:** Edit any medicine's expiry date to yesterday's date. Notice the badge immediately flips to red **Expired**. Confirm this medicine can no longer be added to a sale.
6. **Point-of-Sale Transaction:** Navigate to **New Sale**. Add items, enter quantities, apply a discount, and submit. Confirm immediate redirection to a clean, itemized invoice with correct totals.
7. **Inventory Deduction:** Return to **Medicines** and verify that the quantity in stock for the sold items decreased by the exact quantity sold.
8. **Over-Sale Protection:** Attempt to sell a quantity greater than currently available stock. The transaction will roll back with an explicit error message and zero inventory changes.
9. **Account Security:** Try creating a new user with a password shorter than 8 characters; confirm validation rejection. Disable a test staff account and verify that login is refused.

---

## 11. Important Migration & Setup Notes

- **Database Seed Design:** `database/pharmacare.sql` is structured for initial setup into a clean database. It automatically creates the `pharmacare` database if not present and imports all schemas and initial records.
- **Empty Database Setup:** If an instructor requests starting from a blank system without demo inventory, simply import `database/schema.sql` alone and register an administrator account.
- **Font & Icon Assets:** The application references Bootstrap 5, Bootstrap Icons, and Plus Jakarta Sans from trusted public CDNs. For local offline environments without internet, the core PHP logic, forms, transactions, and layout continue functioning properly.

---

*PharmaCare — Developed for Web Engineering Coursework & Academic Evaluation.*
