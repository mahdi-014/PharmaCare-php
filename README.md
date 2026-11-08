# PharmaCare — Pharmacy Management System & Online Storefront

[![PHP 8.3](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MariaDB 10.11](https://img.shields.io/badge/MariaDB-10.11%2B-003545?style=flat&logo=mariadb&logoColor=white)](https://mariadb.org/)
[![Bootstrap 5.3](https://img.shields.io/badge/Bootstrap-5.3.3-7952B3?style=flat&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Tests Passing](https://img.shields.io/badge/Tests-319%20Passing-brightgreen?style=flat)](#automated-testing--verification-suites)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

**Academic Course Project:** CIS324 Web Engineering Semester Project (Topic 13: Pharmacy Management System)  
**Implementation Stack:** Core PHP 8.3 (Hybrid Layered MVC + Page Controller), MariaDB 10.11 / MySQL InnoDB, PDO (PHP Data Objects), Bootstrap 5.3.3, Bootstrap Icons, Vanilla JavaScript (Fetch API).  
**External Frameworks:** Strictly **Zero** (No Laravel, Symfony, React, Vue, or Composer packages required).  
**Academic Documentation:**
- [System Architecture & Technical Specification](file:///d:/PharmaCare-PHP-Complete/PharmaCare-PHP/docs/ARCHITECTURE.md)
- [CIS324 Viva Alignment & Academic Mapping (Q1–Q9)](file:///d:/PharmaCare-PHP-Complete/PharmaCare-PHP/docs/CIS324_ALIGNMENT.md)

---

## Table of Contents
1. [Project Overview](#project-overview)
2. [Default Evaluation Credentials](#default-evaluation-credentials)
3. [Quick Start & Installation Guide](#quick-start--installation-guide)
   - [Option A: Native CLI Launcher (Windows)](#option-a-native-cli-launcher-windows)
   - [Option B: XAMPP Localhost Setup](#option-b-setup-with-xampp-on-windows)
   - [Option C: InfinityFree & Cloud Hosting](#option-c-infinityfree--cpanel-cloud-hosting)
4. [System Architecture & Directory Structure](#system-architecture--directory-structure)
5. [Database Schema & Entity Relationships](#database-schema--entity-relationships)
6. [Core Functional Modules](#core-functional-modules)
7. [Enterprise Security & Defensive Hardening](#enterprise-security--defensive-hardening)
8. [Automated Testing & Verification Suites](#automated-testing--verification-suites)
9. [Academic Viva & Demonstration Checklist](#academic-viva--demonstration-checklist)

---

## Project Overview

**PharmaCare** is a production-grade, enterprise-scale Pharmacy Management System and Online Storefront engineered from the ground up in Core PHP 8.3 and MariaDB 10.11.

It bridges the gap between **in-clinic dispensary operations** (Point-of-Sale billing, batch inventory management, supplier relations, and analytical dashboarding) and **customer-facing digital healthcare** (online medicine catalog, debounced live search, session-based shopping cart, atomic checkout, prescription document verification, multi-state staff fulfillment queue, and real-time live order tracking).

### Architectural Highlights
- **Strict Separation of Concerns:** Layered MVC architecture (`app/Controllers`, `app/Models`, `app/Services`) decoupled via a native PSR-4 autoloader.
- **Unified Inventory Concurrency Engine:** POS transactions and Customer Online Orders share a single source of truth backed by MariaDB InnoDB pessimistic row-locking (`SELECT ... FOR UPDATE`), guaranteeing zero overselling and zero race conditions under concurrent checkouts.
- **First Expiry, First Out (FEFO):** Intelligent batch selector automatically allocates earliest-expiring stock first, minimizing pharmaceutical wastage.
- **Asynchronous RESTful JSON APIs:** Built with native Vanilla JavaScript Fetch (300ms debounced live search and real-time polling every 10 seconds), achieving zero external JavaScript framework bloat.
- **Enterprise Security Hardening:** 100% prepared statements with emulated prepares disabled, context-aware HTML entity sanitization (`e()`), Synchronizer Token CSRF defense across forms and JSON APIs, ownership-verified IDOR defense, and strict session lifecycles with 30-minute idle inactivity timeouts.

---

## Default Evaluation Credentials

The system includes pre-seeded accounts hashed using native Bcrypt (`PASSWORD_BCRYPT`):

| Role | Username | Password | Role Badge | Primary Permitted Operations |
| :--- | :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | <span style="color:#0f766e;font-weight:bold;">ADMIN</span> | Full administrative control: Analytics Dashboard, Inventory CRUD, Batch Management, Categories, Suppliers, Point of Sale, Staff Fulfillment Queue, User Account Management. |
| **Staff Pharmacist** | `staff` | `staff123` | <span style="color:#0284c7;font-weight:bold;">STAFF</span> | Operational access: Dashboard view, Medicine lookup, Point-of-Sale billing, Invoicing, Staff Order Fulfillment Queue, Prescription review and verification. User management is restricted (403 Forbidden). |
| **Online Customer** | `customer` | `customer123` | <span style="color:#16a34a;font-weight:bold;">CUSTOMER</span> | Public & Storefront access: Medicine catalog browsing, debounced live search, shopping cart management, checkout with prescription uploads, order tracking, and personal order history. Administrative and POS tools are restricted (403 Forbidden). |

*(New customer accounts can also be created instantly via the public registration page at `index.php?page=register`).*

---

## Quick Start & Installation Guide

### Option A: Native CLI Launcher (Windows)
If PHP 8.1+ and MariaDB are installed globally or in portable directories:
1. Double-click **`start-system.bat`** (or run `.\start-system.bat` in PowerShell / Command Prompt).
   - Starts MariaDB daemon on port `3306`.
   - Starts PHP built-in web server on `http://127.0.0.1:8000`.
   - Automatically opens your default web browser to the application portal.
2. When finished, double-click **`stop-system.bat`** to safely terminate background server tasks.

---

### Option B: Setup with XAMPP on Windows

1. Install **XAMPP** with **PHP 8.1 or later**, Apache, and MySQL/MariaDB.
2. Clone or extract the repository directly into:
   ```text
   C:\xampp\htdocs\PharmaCare-PHP\
   ```
3. Open **XAMPP Control Panel** and start both **Apache** and **MySQL**.
4. Navigate to **http://localhost/phpmyadmin**:
   - Click the **Import** tab.
   - Choose the file: `C:\xampp\htdocs\PharmaCare-PHP\database\pharmacare.sql`.
   - Click **Import** (or **Go**). This creates the `pharmacare` database, sets up all 11 relational tables with foreign keys and indexes, and seeds 120 demo medicine batches, categories, suppliers, and user accounts.
5. Verify `config.php`:
   - Default XAMPP settings are pre-configured:
     ```php
     define('DB_HOST', '127.0.0.1');
     define('DB_PORT', 3306);
     define('DB_NAME', 'pharmacare');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```
   - If your local MySQL `root` user has a password, update `DB_PASS` accordingly.
6. Open your web browser and visit:
   ```text
   http://localhost/PharmaCare-PHP/
   ```

---

### Option C: InfinityFree & cPanel Cloud Hosting
1. Upload project files via FTP to the `htdocs/` directory of your hosting account.
2. In cPanel / phpMyAdmin, create a database and import **`database/infinityfree_import.sql`**.
3. Update `config.php` with the cloud database host, database name, username, and password provided by your host.
4. Access your live website URL (e.g., `http://your-subdomain.infinityfreeapp.com/`).

---

## System Architecture & Directory Structure

```text
PharmaCare-PHP/
├── index.php                  # Primary Front Controller, session firewall & RBAC router
├── actions.php                # Centralized POST action dispatcher & CSRF validator
├── config.php                 # Database credentials, port, regional timezone configuration
├── .htaccess                  # Apache security rule disabling directory browsing
├── start-system.bat           # Self-contained launcher for MariaDB and PHP development server
├── stop-system.bat            # Safe shutdown script for server background processes
│
├── api/                       # Asynchronous RESTful JSON API Endpoints
│   ├── cart/                  # Cart operations: add.php, update.php, remove.php, view.php
│   ├── medicines/             # search.php (300ms debounced live catalog search)
│   └── orders/                # track.php (real-time order status polling)
│
├── app/                       # Layered MVC Architecture (PSR-4 Namespace 'App\')
│   ├── Controllers/           # HTTP Request Controllers
│   │   ├── CatalogController.php      # Storefront browsing, search, and medicine detail
│   │   ├── CartController.php         # Session cart mutations, item clamping & totals
│   │   ├── CheckoutController.php     # Multi-step checkout & prescription uploads
│   │   ├── OrderController.php        # Staff fulfillment queue, transitions & tracking
│   │   └── CustomerAuthController.php # Customer registration, Bcrypt hashing & routing
│   ├── Models/                # Data Access Layer & Active Record Entities
│   │   ├── Medicine.php               # Catalog querying, active batches & inventory
│   │   ├── Order.php                  # Order persistence, status lookups & line items
│   │   ├── User.php                   # Authentication credentials & uniqueness checks
│   │   └── Prescription.php           # Prescription file metadata & review audit
│   └── Services/              # Domain Business Logic & Concurrency Control
│       ├── InventoryService.php       # Row-level locking (FOR UPDATE), FEFO allocation
│       └── OrderService.php           # 7-state order lifecycle state machine & gating
│
├── assets/                    # Static Client-Side Assets
│   ├── css/
│   │   └── app.css            # Custom teal design system, responsive typography & cards
│   └── js/
│       ├── app.js             # Mobile drawer toggle & UI confirmation dialogs
│       ├── sales.js           # Interactive POS cart calculation & discount math
│       ├── catalog.js         # Debounced live search, async add-to-cart & drawer UI
│       └── order-tracker.js   # Real-time 10s status polling & timeline DOM updates
│
├── database/                  # Relational Database Schemas & Migrations
│   ├── pharmacare.sql         # Canonical DDL + 11 tables + 120 seeded batches & users
│   ├── schema.sql             # Pure DDL table definitions with foreign keys
│   ├── infinityfree_import.sql# Cloud hosting compatible single-database schema
│   └── migrations/            # Versioned migrations (e.g. v2_online_ordering.sql)
│
├── docs/                      # Comprehensive Academic Documentation
│   ├── ARCHITECTURE.md        # Technical architecture, concurrency & schema specifications
│   └── CIS324_ALIGNMENT.md    # Detailed viva mapping guide for Questions Q1 through Q9
│
├── includes/                  # Infrastructure Middleware & Layout
│   ├── bootstrap.php          # Session hardening, PDO singleton, PSR-4 autoloader
│   ├── functions.php          # Global helper functions, CSRF protection, status engine
│   ├── header.php             # Unified responsive sidebar navigation, top bar & alerts
│   └── footer.php             # Global scripts bundle, dynamic script loader & modals
│
├── pages/                     # Presentation Views & Page Controllers
│   ├── login.php              # Modern dual-pane authentication view
│   ├── register.php           # Customer self-registration form with live validations
│   ├── catalog.php            # Storefront medicine directory with search & category filters
│   ├── checkout.php           # Multi-step order checkout & prescription file uploader
│   ├── orders.php             # Staff order fulfillment queue with multi-filter bar
│   ├── order_detail.php       # Order management, prescription review & transition modal
│   ├── my_orders.php          # Customer personal order history ledger
│   ├── track_order.php        # Live customer order timeline & tracking interface
│   ├── dashboard.php          # Admin/Staff analytical dashboard & inventory alerts
│   ├── medicines.php          # Admin medicine inventory management CRUD table
│   ├── medicine_form.php      # Medicine batch creation and editing form
│   ├── categories.php         # Pharmacological category management directory
│   ├── suppliers.php          # Pharmaceutical manufacturer & distributor directory
│   ├── sale_form.php          # High-speed point-of-sale billing & dispensing interface
│   ├── sales.php              # Historical sales ledger with transaction filtering
│   ├── invoice.php            # Professional printable thermal & standard invoice
│   ├── users.php              # System user account administration & role toggling
│   ├── forbidden.php          # HTTP 403 authorization error page
│   └── not_found.php          # HTTP 404 page for unrecognized routes
│
├── uploads/prescriptions/     # Protected prescription document storage
│   └── .htaccess              # Require all denied (blocks unauthorized HTTP downloads)
│
└── tests/                     # 9 Comprehensive Automated Test Suites (319 Assertions)
    ├── smoke.php              # Unit tests for status engine, validators & URL routing
    ├── database_test.php      # Live database connectivity, foreign keys & seed data
    ├── auth_test.php          # Customer registration, Bcrypt, role routing & RBAC
    ├── cart_test.php          # Session cart calculations, max-stock clamping & Rx flags
    ├── order_test.php         # Atomic checkout, FEFO allocation & row locking
    ├── order_lifecycle_test.php # 7-state order lifecycle, Rx gates & cancellations
    ├── api_test.php           # RESTful JSON APIs, debounced search & CSRF headers
    ├── mvc_test.php           # Layered MVC classes, PSR-4 autoloader & models
    └── security_test.php      # SQLi, XSS, CSRF, IDOR, session timeout & path traversal
```

---

## Database Schema & Entity Relationships

The relational schema comprises **11 InnoDB tables** enforcing referential integrity:

```text
                  ┌──────────────┐
                  │  categories  │
                  └──────┬───────┘
                         │ (1)
                         ▼ (N)
┌──────────────┐  (N)  ┌──────────────┐  (N)  ┌──────────────┐
│  suppliers   │◄──────┤  medicines   │◄──────┤  sale_items  │
└──────────────┘  (1)  └──────┬───────┘  (1)  └──────┬───────┘
                              │                      │ (N)
                              │                      ▼ (1)
                              │ (1)           ┌──────────────┐
                              │               │    sales     │
                              ▼ (N)           └──────┬───────┘
                       ┌──────────────┐              │ (N)
                       │ order_items  │              │
                       └──────┬───────┘              │
                              │ (N)                  │
                              ▼ (1)                  │
                       ┌──────────────┐              │
                       │    orders    │              │
                       └──────┬───────┘              │
                              │ (1)                  │
                              ▼ (N)                  │
                       ┌──────────────┐  (1)         ▼ (1)
                       │  app_users   │◄─────────────┴────────
                       └──────┬───────┘
                              │ (1)
               ┌──────────────┴──────────────┐
               │ (N)                         │ (N)
               ▼                             ▼
    ┌─────────────────────┐       ┌─────────────────────┐
    │order_status_history │       │    prescriptions    │
    └─────────────────────┘       └─────────────────────┘
```

1. **`app_users`**: User accounts (Admin, Staff, Customer), Bcrypt hashed passwords, enabled flag.
2. **`categories`**: Pharmacological classifications (Antibiotic, Analgesic, etc.) with `RESTRICT` cascade protection.
3. **`suppliers`**: Pharmaceutical manufacturers & distribution contacts with `RESTRICT` cascade protection.
4. **`medicines`**: 120 inventory batches storing pricing, current stock, threshold, manufacturing/expiry dates, formulation type, and prescription flags.
5. **`sales`**: Point-of-sale invoice headers with invoice numbers (`INV-YYYYMMDDHHMMSS-XXXXXX`), financial totals, and seller foreign key reference (`sold_by_id`).
6. **`sale_items`**: Line items linked to sales and medicines with unit price and line total.
7. **`orders`**: Online customer order headers (`ORD-YYYYMMDD-XXXXXX`), shipping address, totals, payment method, order status, and prescription flag.
8. **`order_items`**: Line items linked to online orders storing point-in-time snapshots of medicine name and batch number.
9. **`order_status_history`**: Immutable append-only audit trail logging all lifecycle transitions, actors, and comments.
10. **`prescriptions`**: Uploaded prescription metadata, file paths, and pharmacist verification state.
11. **`schema_migrations`**: Database version tracking table preventing duplicate migrations.

---

## Core Functional Modules

### 1. Storefront Medicine Catalog & Search
- Displays active, non-expired medicine batches with stock > 0.
- Debounced live keyword search (300ms) across medicine trade name, generic name, and brand.
- Category dropdown filtering with instant asynchronous DOM updates.
- Prescription badge indicator (`Prescription Required`) warning customers when doctor authorization is mandated.

### 2. Session Shopping Cart & Max-Stock Clamping
- Maintained in `$_SESSION['cart']` with quantity selectors bounded between 1 and available batch stock.
- Dynamic clamping: If an item has 150 units in stock and a customer requests 200, the cart automatically clamps to 150 and alerts the user.
- Interactive slide-over cart drawer with asynchronous unit increment, decrement, and removal.

### 3. Concurrency-Safe Checkout & Atomic Order Placement
- Shared `InventoryService` locks rows (`SELECT ... FOR UPDATE`) inside an isolated transaction.
- Validates active status, expiry boundary (`expiry_date >= CURDATE()`), and sufficient stock.
- Enforces First Expiry, First Out (FEFO) stock selection when batches are unpinned.
- Mandatory prescription upload validation for orders containing prescription-only drugs.

### 4. Staff Order Fulfillment Queue & State Machine
- Restricted to `STAFF` and `ADMIN` roles.
- Multi-parameter filter bar: Order status (`PENDING`, `CONFIRMED`, `PROCESSING`, `READY`, `DISPATCHED`, `COMPLETED`, `CANCELLED`), payment status (`UNPAID`, `PAID`), and keyword search.
- **Prescription Verification Gate:** Orders requiring prescriptions cannot leave `PENDING` or `CONFIRMED` until an authorized pharmacist reviews and approves the prescription.
- **Idempotent Cancellation:** When an active order is cancelled, inventory is safely locked and restored to stock, while terminal orders (`COMPLETED` or `CANCELLED`) are protected against double-restoration.

### 5. Point-of-Sale (POS) Invoicing
- Dedicated high-speed dispensing interface for in-person pharmacy sales.
- Dynamic multi-item line additions with real-time subtotal, discount, and grand total calculations.
- Atomic stock deduction and immediate generation of printable thermal or standard itemized invoices.

---

## Enterprise Security & Defensive Hardening

PharmaCare implements comprehensive **Defense-in-Depth** across all 7 OSI / application layers:

1. **SQL Injection (SQLi) Defense:** 100% prepared statements with bound parameters (`PDO::prepare()`). Native server-side prepares enforced (`PDO::ATTR_EMULATE_PREPARES = false`).
2. **Cross-Site Scripting (XSS) Defense:** Context-aware HTML entity encoding on all dynamic view renders via the `e()` helper function (`htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`). Global security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`).
3. **Cross-Site Request Forgery (CSRF) Defense:** Synchronizer Token Pattern (`$_SESSION['csrf_token']`) validated via constant-time string comparison (`hash_equals()`). Dual support for POST form fields and `X-CSRF-Token` HTTP headers in Fetch APIs.
4. **Insecure Direct Object References (IDOR):** Customer ownership validation on all order lookup, tracking, and cancellation endpoints (`$order['customer_id'] === $_SESSION['user_id']`). Unauthorized attempts receive HTTP 403 Forbidden.
5. **Session Security & Inactivity Lifecycle:** Strict cookie attributes (`HttpOnly`, `SameSite=Lax`, `use_strict_mode=1`). Session ID regeneration (`session_regenerate_id(true)`) on login, registration, and logout. Strict 30-minute idle inactivity timeout (`SESSION_IDLE_TIMEOUT = 1800`) with automatic session destruction and redirection to `index.php?page=login&timeout=1`.
6. **Prescription Upload Protection:** Client filenames discarded; cryptographically unique UUIDs assigned; MIME-type and extension allowlisting (`jpg`, `jpeg`, `png`, `pdf`); storage in `uploads/prescriptions/` with `.htaccess` `Require all denied` preventing direct public HTTP access. Downloads streamed via authenticated controller proxy.

---

## Automated Testing & Verification Suites

PharmaCare features a comprehensive test harness comprising **9 standalone automated test suites** verifying 319 assertions across unit logic, database integrity, RBAC firewalls, concurrency, and security threats.

### Running All Test Suites
Execute the following commands from the repository root:

```bash
php tests/smoke.php
php tests/database_test.php
php tests/auth_test.php
php tests/cart_test.php
php tests/order_test.php
php tests/order_lifecycle_test.php
php tests/api_test.php
php tests/mvc_test.php
php tests/security_test.php
```

### Test Suite Summary

| # | Test Suite Script | Assertions | Focus Areas Verified | Status |
| :-: | :--- | :-: | :--- | :-: |
| 1 | `tests/smoke.php` | 16 | Inventory status priority hierarchy, price formatting, zero discount validation, formulation types. | **16/16 PASSED** |
| 2 | `tests/database_test.php` | 20 | Live MariaDB connectivity, 11 system tables, seeded Admin/Staff/Customer accounts, 120 inventory batches, 0 orphaned FKs. | **20/20 PASSED** |
| 3 | `tests/auth_test.php` | 28 | Customer registration, Bcrypt hashing, unique username/email constraints, role routing, RBAC boundary enforcement, IDOR prevention. | **28/28 PASSED** |
| 4 | `tests/cart_test.php` | 39 | In-memory session cart mechanics, unit/line counts, total calculation, max-stock clamping, prescription requirement flags. | **39/39 PASSED** |
| 5 | `tests/order_test.php` | 28 | Atomic checkout, shared InventoryService, row-locking (`FOR UPDATE`), FEFO batch allocation, transactional rollback on out-of-stock. | **28/28 PASSED** |
| 6 | `tests/order_lifecycle_test.php`| 31 | 7-state order lifecycle state machine, prescription verification gate, customer order cancellation, idempotent stock restoration. | **31/31 PASSED** |
| 7 | `tests/api_test.php` | 53 | RESTful JSON APIs (`api/*`), HTTP status codes (200, 400, 401, 403, 404, 405), debounced search, session cart endpoints, CSRF headers. | **53/53 PASSED** |
| 8 | `tests/mvc_test.php` | 53 | PSR-4 autoloader, Layered MVC Controllers, Active Record Models, Domain Services, method signatures and separation of concerns. | **53/53 PASSED** |
| 9 | `tests/security_test.php` | 51 | SQLi payloads, XSS neutralization via `e()`, HTTP security headers, CSRF form & header tokens, RBAC privilege escalation, 30m idle session timeout, file upload allowlists & directory traversal. | **51/51 PASSED** |
| **TOTAL** | **9 Suites** | **319** | **Complete Application Verification** | **319/319 PASSED (100%)** |

---

## Academic Viva & Demonstration Checklist

Evaluators and students can verify all course project requirements using the step-by-step checklist below:

1. **Role-Based Access Control (RBAC):**
   - Log in with `customer` / `customer123`. Verify the navigation shows "Medicine Storefront", "My Cart", and "My Orders". Attempt to access `index.php?page=dashboard` or `?page=users`; confirm immediate HTTP 403 Forbidden.
   - Log out and log in with `staff` / `staff123`. Verify the "Orders Queue" and "Point of Sale" are accessible, but user account administration (`?page=users`) is blocked (HTTP 403).
   - Log in with `admin` / `admin123`. Confirm full administrative access to all 11 modules.
2. **Debounced Live Catalog Search:**
   - As customer, open **Medicine Storefront**. Type `paracetamol` in the search bar. Observe the live 300ms debounced search updating the medicine card grid without reloading the page.
3. **Shopping Cart & Stock Clamping:**
   - Select a medicine with limited stock (e.g. 15 units). Attempt to add 50 units. Verify the cart clamps to available stock and notifies the user.
4. **Prescription Verification Workflow:**
   - Add a prescription-required medicine (e.g., Montair 10) to the cart.
   - Proceed to **Checkout**. Confirm that checkout requires uploading a prescription document (`.jpg`, `.png`, or `.pdf`). Complete the order.
   - Log in as `staff`. Open **Orders Queue**. Verify the order shows a warning badge **Needs Review**.
   - Attempt to advance the order to `PROCESSING`; confirm the system prevents the transition until the prescription is approved.
   - Click **Review Prescription**, inspect the uploaded document, and click **Approve Prescription**.
   - Advance the order status through `CONFIRMED` → `PROCESSING` → `READY` → `DISPATCHED` → `COMPLETED`.
5. **Real-Time Live Order Tracking:**
   - In another browser window, open the customer's **Track Order** page.
   - Observe the step-progress timeline automatically update in real-time as staff change order states, without manual browser refresh.
6. **Concurrent Stock Protection & FEFO:**
   - Inspect medicine inventory before and after an order. Verify that stock was deducted from the earliest-expiring batch first (FEFO).
   - Cancel a pending order and verify that stock is safely restored to inventory.
7. **Session Inactivity Timeout:**
   - Log into the application. Leave the session inactive for 30 minutes (or test via `tests/security_test.php`). Observe that the session is invalidated, session cookies are purged, and the user is redirected to `index.php?page=login&timeout=1` with an informative session expiry notice.

---

*PharmaCare — Engineered for Web Engineering Coursework & Academic Evaluation (CIS324 Topic 13).*
