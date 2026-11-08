# PharmaCare — Pharmacy Management & Online Medicine Ordering System

**Coursework Project:** CIS324 Web Engineering Semester Project  
**Topic:** Topic 13 — Pharmacy Inventory and Online Medicine Ordering System  
**Implementation Stack:** Core PHP 8.1+ / 8.3, MySQL / MariaDB 10.11+ (InnoDB), PDO (PHP Data Objects), Bootstrap 5.3.3, Vanilla JavaScript (ES6+ Fetch API).  
**External Frameworks:** Strictly **Zero** — Native PHP and JavaScript without Laravel, Symfony, React, Vue, or Composer dependencies.

---

## 1. Project Overview & Functional Modules

PharmaCare is a comprehensive, modular pharmacy web application that combines in-clinic dispensary operations with a customer-facing digital medicine storefront.

### Core Modules
1. **Pharmacy Inventory Management:**
   - Real-time batch-level tracking (Batch Number, Expiry Date, Purchase Price, Selling Price, Minimum Stock Level).
   - Automated stock status classification: Available, Low Stock, Expiring Soon (within 30 days), Expired, and Inactive.
   - Categorization, supplier management, and pessimistic row locking (`SELECT ... FOR UPDATE`) to prevent overselling.

2. **Customer Storefront & Online Medicine Ordering:**
   - Public and authenticated storefront catalog with real-time category filtering.
   - Asynchronous debounced live search (Vanilla JS Fetch).
   - Session-backed shopping cart with stock clamping and prescription requirement detection.
   - Multi-step checkout with delivery address, phone validation, payment method (Cash on Delivery / Store Pickup), and digital prescription document upload.
   - Live order tracking timeline with automatic 10-second status polling.

3. **Staff Order Fulfillment & Prescription Verification:**
   - Dedicated staff queue with multi-factor filters (Order Status, Payment Status, Keyword Search).
   - Order detail inspection with line-item price snapshots.
   - Prescription verification module allowing pharmacists to view, approve, or reject uploaded medical prescriptions.
   - Streamlined order lifecycle state machine with fast-track one-click order completion.
   - Automated synchronization: completed online orders bridge directly into financial sales records.

4. **Point of Sale (POS) Billing & Invoicing:**
   - Fast counter sales billing interface for walk-in pharmacy customers.
   - Multi-item line entry with instant price calculation and optional discount handling.
   - Atomic inventory stock deduction within a single database transaction.
   - Clean, professional, printable sales invoice generation (`index.php?page=invoice&id=...`).

5. **Unified Analytical Dashboard:**
   - Unified financial metrics: Today's sales, all-time gross revenue, POS vs. Online revenue split.
   - Inventory health cards: Active batches, low stock warnings, expiring/expired alert counts.
   - Real-time operations queue: Pending customer orders and unverified prescriptions needing review.
   - Top-selling medicines table and recent multi-channel sales transaction log.

---

## 2. Default Evaluation Credentials

The database is pre-seeded with three academic evaluation accounts (passwords hashed via `PASSWORD_BCRYPT`):

| Role | Username | Password | Role Description & Access Scope |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | Full system access: Analytical Dashboard, Inventory CRUD, Categories, Suppliers, POS Counter Sales, Staff Fulfillment Queue, and User Management. |
| **Staff Pharmacist** | `staff` | `staff123` | Operational access: Dashboard, Inventory lookup, POS Counter Sales, Staff Fulfillment Queue, and Prescription Verification. User administration is restricted (403 Forbidden). |
| **Online Customer** | `customer` | `customer123` | Storefront access: Medicine catalog, live search, shopping cart, checkout, prescription upload, live order tracking, and personal order history. Administrative tools are restricted (403 Forbidden). |

*(New customer accounts can also be created freely via the public registration form at `index.php?page=register`).*

---

## 3. Quick Start & Installation Guide

### Prerequisites
- PHP 8.1 or higher (with `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `session` extensions enabled).
- MySQL 5.7+ or MariaDB 10.4+.
- Modern web browser (Chrome, Firefox, Edge, Safari).

### Option A: Localhost Setup with XAMPP (Recommended)
1. Install **XAMPP** and start the **Apache** and **MySQL** modules from the XAMPP Control Panel.
2. Clone or place this repository into your XAMPP web root:
   ```text
   C:\xampp\htdocs\PharmaCare-PHP\
   ```
3. Open your browser and navigate to **phpMyAdmin** (`http://localhost/phpmyadmin`).
4. Create a database named `pharmacare` (or import directly):
   - Click the **Import** tab.
   - Browse to: `C:\xampp\htdocs\PharmaCare-PHP\database\pharmacare.sql`.
   - Click **Import**.
5. Check `config.php` (defaults match standard XAMPP):
   ```php
   return [
       'db_host'   => '127.0.0.1',
       'db_port'   => 3306,
       'db_name'   => 'pharmacare',
       'db_user'   => 'root',
       'db_pass'   => '',
       'timezone'  => 'Asia/Dhaka',
       'app_name'  => 'PharmaCare',
   ];
   ```
6. Visit the application in your browser:
   ```text
   http://localhost/PharmaCare-PHP/
   ```

### Option B: PHP Built-in Web Server CLI
1. Import `database/pharmacare.sql` into your local MySQL / MariaDB instance:
   ```bash
   mysql -u root -p pharmacare < database/pharmacare.sql
   ```
2. Navigate to the project root directory and start the built-in server:
   ```bash
   php -S 127.0.0.1:8000
   ```
3. Open `http://127.0.0.1:8000` in your web browser.

---

## 4. Modular System Architecture & Directory Layout

PharmaCare employs a clean, modular structure combining a Front Controller router, Layered MVC modules for online ordering, and procedural helpers for clean maintainability.

```text
PharmaCare-PHP/
├── config/
│   └── database.php           # PDO connection singleton & connection parameters (Q4 & Q5)
├── includes/
│   ├── auth.php               # Session lifecycle, 30-min idle timeout, role guards (Q7)
│   ├── bootstrap.php          # PSR-4 autoloader, environment setup, security headers (Q8)
│   ├── functions.php          # Procedural helpers (formatting, CSRF tokens, cart logic)
│   ├── inventory_service.php  # Stock deduction & restoration legacy bridge
│   ├── header.php             # Responsive Bootstrap 5 navigation & flash alerts
│   └── footer.php             # Page footer & global script includes
├── app/                       # Object-Oriented Layered MVC Architecture (Q8)
│   ├── Controllers/
│   │   ├── CatalogController.php   # Storefront catalog view & live search API
│   │   ├── CartController.php      # Cart session state & mutation APIs
│   │   └── OrderController.php     # Checkout, order lifecycle, queue & status API
│   ├── Models/
│   │   ├── Medicine.php            # Medicine inventory model & batch queries
│   │   ├── Order.php               # Order persistence, items & status history model
│   │   └── User.php                # User credentials & customer registration model
│   └── Services/
│       ├── InventoryService.php    # Concurrency-safe stock deduction (FOR UPDATE)
│       ├── OrderService.php        # Atomic order creation & state machine engine
│       └── DashboardService.php    # Single-pass SQL analytics aggregator
├── pages/                     # View Templates & Procedural Workflows
│   ├── dashboard.php          # Executive analytics dashboard
│   ├── catalog.php            # Storefront medicine browsing
│   ├── cart.php               # Shopping cart table & controls
│   ├── checkout.php           # Shipping & prescription upload checkout form
│   ├── order_confirmation.php # Post-order success screen
│   ├── orders.php             # Staff order fulfillment queue
│   ├── order_detail.php       # Order lifecycle transition & prescription review
│   ├── my_orders.php          # Customer order history & live tracker
│   ├── medicines.php          # Inventory management table
│   ├── medicine_form.php      # Medicine add/edit form
│   ├── categories.php         # Medicine category CRUD
│   ├── suppliers.php          # Supplier relations CRUD
│   ├── sales.php              # POS sales transaction history
│   ├── sale_form.php          # Point of Sale billing counter
│   ├── invoice.php            # Printable sales receipt
│   ├── users.php              # Admin user account management
│   ├── profile.php            # Account profile settings
│   ├── login.php              # User authentication screen
│   ├── register.php           # Customer registration form
│   ├── download_prescription.php # Protected prescription streaming endpoint
│   ├── forbidden.php          # 403 Access Denied template
│   └── not_found.php          # 404 Not Found template
├── api/                       # Asynchronous RESTful JSON API Endpoints (Q6)
│   ├── search_medicines.php   # Live medicine search (GET)
│   ├── cart.php               # Cart add / update / remove operations (POST)
│   ├── order_status.php       # Live order tracking polling (GET)
│   ├── medicines/search.php   # Standard compatibility alias
│   ├── cart/add.php           # Standard compatibility alias
│   ├── cart/update.php        # Standard compatibility alias
│   └── orders/status.php      # Standard compatibility alias
├── assets/                    # Static Assets
│   ├── css/app.css            # Custom CSS styles, badge tokens, print layout
│   └── js/
│       ├── app.js             # Global UI helpers, toast triggers
│       ├── catalog.js         # Debounced live search & async add-to-cart
│       ├── order-tracker.js   # Live order status polling engine
│       └── sales.js           # POS dynamic multi-item row calculator
├── database/
│   └── pharmacare.sql         # Canonical 10-table schema & 15 realistic medicines (Q4)
├── storage/
│   └── prescriptions/         # Uploaded Rx files (.htaccess protected from direct web access)
├── actions.php                # Procedural POST action dispatcher with CSRF verification
├── index.php                  # Central Front Controller & Route Dispatcher (Q1)
├── config.php                 # Application configuration settings
└── README.md                  # Comprehensive academic report & CIS324 defense manual
```

---

## 5. Coursework Viva & Oral Defense Guide (CIS324 Q1–Q9)

This section maps the requirements of questions **Q1 through Q9** in the CIS324 Web Engineering semester assignment directly to the architectural solutions implemented in PharmaCare.

```
                           CIS324 Viva Architectural Map
┌─────────────────────────────────────────────────────────────────────────────┐
│ Q1: Client-Server & Front Controller  ──► index.php                         │
│ Q2: Semantic HTML5 & Responsive UI    ──► includes/header.php, pages/       │
│ Q3: Dynamic UI & Client Validation    ──► assets/js/sales.js, catalog.js    │
│ Q4: Relational DB & PDO Connection    ──► config/database.php, pharmacare.sql│
│ Q5: Security, CSRF & Input Sanitizing ──► includes/functions.php, e(), csrf │
│ Q6: Asynchronous JSON APIs & Fetch    ──► api/*.php, assets/js/             │
│ Q7: Sessions, Inactivity & RBAC       ──► includes/auth.php, bootstrap.php  │
│ Q8: Layered MVC Pattern & Autoloader  ──► app/ (Controllers, Models, Serv)  │
│ Q9: Deployment & Server Security      ──► .htaccess, storage/prescriptions/ │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

### Q1: Web Concepts & Client-Server Architecture
- **Concept:** Explaining the HTTP request-response cycle, statelessness, and Front Controller routing.
- **Codebase Implementation:**
  - `index.php` acts as the single entry point (Front Controller) for all GET page views. It intercepts incoming requests, boots the application environment via `includes/bootstrap.php`, checks authentication state, and routes to the appropriate Controller or view template.
  - Form submissions are routed to `actions.php`, which validates the HTTP method (strictly `POST`), verifies CSRF tokens, and dispatches actions inside safe `try...catch` blocks.
  - The stateless nature of HTTP is bridged using secure server-side PHP sessions (`$_SESSION`), maintaining user login state and shopping cart items between requests.

---

### Q2: HTML5 Semantic Layout & Responsive UI Design
- **Concept:** Semantic markup, structure, accessibility, and fluid responsive design across mobile, tablet, and desktop viewports.
- **Codebase Implementation:**
  - Standardized semantic HTML5 elements are employed throughout: `<header>`, `<nav class="navbar">`, `<main class="container py-4">`, `<section>`, `<article>`, and `<footer>`.
  - Layout is built on **Bootstrap 5.3.3** using mobile-first grid containers (`col-12 col-md-6 col-lg-4`), responsive flexbox utilities, and Bootstrap Icons (`bi-*`).
  - Print layout is handled cleanly in `pages/invoice.php` and `assets/css/app.css` using `@media print` directives to hide navigation bars and action buttons when printing receipts.

---

### Q3: Client-Side Scripting & Dynamic Form Validation
- **Concept:** Enhancing user experience with Vanilla JavaScript without reloading the page, form constraint validation, and dynamic DOM manipulation.
- **Codebase Implementation:**
  - `assets/js/sales.js`: Powers the POS billing counter. Automatically calculates line totals, dynamic discounts, and grand totals instantly in the browser as the cashier changes quantities or adds medicine rows.
  - `assets/js/catalog.js`: Intercepts catalog search inputs with a 300ms debounce timer, preventing excessive server requests while updating the medicine card grid dynamically.
  - Client-side validation is complemented with HTML5 attributes (`required`, `min`, `max`, `pattern` for Bangladesh phone numbers `^01[3-9]\d{8}$`), backed by rigorous server-side re-validation.

---

### Q4: Relational Database Design, Normalization & PDO Connection
- **Concept:** Third Normal Form (3NF) relational database design, foreign key referential integrity, indexed search columns, and robust database access via PDO.
- **Codebase Implementation:**
  - The database (`database/pharmacare.sql`) consists of 10 normalized InnoDB tables:
    1. `app_users`: User credentials, contact info, and roles (`ADMIN`, `STAFF`, `CUSTOMER`).
    2. `categories`: Pharmaceutical classifications (e.g., Analgesic, Antibiotic, Antihistamine).
    3. `suppliers`: Drug manufacturers and contact details.
    4. `medicines`: Medicine batches with expiry date, unit price, stock, and prescription flag.
    5. `sales`: POS and bridged online sales transactions.
    6. `sale_items`: Itemized line entries for each sale.
    7. `orders`: Online customer orders with delivery addresses and order status.
    8. `order_items`: Line items capturing historical price and batch snapshots.
    9. `order_status_history`: Audit trail recording status changes, actor ID, and timestamps.
    10. `prescriptions`: Uploaded customer prescription files and verification statuses.
  - Database access is abstracted into `config/database.php` through a singleton `db(): PDO` function. It configures:
    - `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` (throws exceptions on database errors).
    - `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC` (returns clean associative arrays).
    - `PDO::ATTR_EMULATE_PREPARES => false` (enforces native MySQL prepared statements).

---

### Q5: Security Vulnerability Mitigations
- **Concept:** Defending against common web vulnerabilities: Cross-Site Scripting (XSS), SQL Injection (SQLi), Cross-Site Request Forgery (CSRF), and Insecure File Uploads.
- **Codebase Implementation:**
  - **SQL Injection Defense:** All database queries utilize parameterized prepared statements (`$stmt->execute([$param])`). Raw string concatenation in SQL queries is strictly prohibited.
  - **XSS Defense:** All user-supplied data output in HTML templates is sanitized via the global helper `e($string)`, which wraps `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
  - **CSRF Defense:** `csrf()` generates cryptographically secure 64-character hex tokens stored in `$_SESSION['_csrf']`. Form submissions include `csrf_field()`, validated in `actions.php` via `hash_equals()`. Asynchronous JSON APIs pass the token via `X-CSRF-Token` headers.
  - **Secure File Uploads:** Uploaded prescriptions (`OrderService::savePrescriptionFile`) validate MIME type (`image/jpeg`, `image/png`, `application/pdf`), file extension, maximum file size (5MB), and rename files to random hex hashes stored outside the public document root.
  - **Password Security:** Stored using native PHP `password_hash($password, PASSWORD_BCRYPT)` and verified using timing-safe `password_verify()`.

---

### Q6: Asynchronous JSON APIs & Vanilla JS Fetch Integration
- **Concept:** Single-page dynamic interactivity via RESTful JSON endpoints using Vanilla JS Fetch API.
- **Codebase Implementation:**
  - Clean API endpoints in `api/`:
    - `api/search_medicines.php` (GET): Accepts `q` (keyword) and `category_id`. Returns matching medicines, inventory status, and cart counts.
    - `api/cart.php` (POST): Accepts JSON payloads to dynamically add, update, or remove cart items without reloading the page.
    - `api/order_status.php` (GET): Accepts `id` (Order ID). Returns current order status, prescription status, and complete transition history.
  - Standard JSON response envelope:
    ```json
    {
      "success": true,
      "message": "Medicines retrieved successfully.",
      "data": { ... }
    }
    ```
  - Standard HTTP status codes (200 OK, 400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 405 Method Not Allowed).
  - Client-side integration (`assets/js/order-tracker.js`) polls `api/order_status.php` every 10 seconds on active orders, updating the timeline badges in real-time.

---

### Q7: State Management, Session Lifecycle & Role-Based Access Control
- **Concept:** Session security flags, idle inactivity timeouts, session fixation defense, and strict role guards.
- **Codebase Implementation:**
  - `includes/bootstrap.php` configures hardened session cookie parameters before session startup:
    ```php
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax'
    ]);
    ```
  - **Inactivity Idle Timeout:** `includes/auth.php` enforces a 30-minute idle session timeout (`SESSION_IDLE_TIMEOUT = 1800` seconds). If inactive for > 1800 seconds, the session is cleared, cookies destroyed, and the user redirected to `login&timeout=1`.
  - **Session Fixation Prevention:** Upon successful login in `actions.php`, `session_regenerate_id(true)` issues a fresh session identifier and invalidates the old one.
  - **Role Guards:** `require_login()`, `require_admin()`, `require_staff_or_admin()`, and `require_customer()` in `includes/auth.php` enforce RBAC at both the route controller level and the form action dispatcher level.

---

### Q8: Layered MVC Architectural Pattern & PSR-4 Autoloading
- **Concept:** Separation of concerns using Model-View-Controller architecture and native autoloading.
- **Codebase Implementation:**
  - **Autoloader:** `includes/bootstrap.php` registers an autoloader mapping the `App\` namespace to the `app/` directory without Composer:
    ```php
    spl_autoload_register(function (string $class): void {
        $prefix = 'App\\';
        $baseDir = dirname(__DIR__) . '/app/';
        // ... resolves namespace to directory path and requires file
    });
    ```
  - **Controllers (`app/Controllers/`):**
    - `CatalogController`: Orchestrates catalog browsing and live search API queries.
    - `CartController`: Handles cart calculations, stock validation, and API updates.
    - `OrderController`: Coordinates checkout, order placement, staff queues, and status polling.
  - **Models (`app/Models/`):**
    - `Medicine`: Encapsulates batch queries and category lookups.
    - `Order`: Encapsulates order persistence, line items, and audit history queries.
    - `User`: Encapsulates user lookups and customer account creation.
  - **Domain Services (`app/Services/`):**
    - `InventoryService`: Centralized concurrency-safe stock deduction and restoration.
    - `OrderService`: Multi-table atomic transactions, state machine transitions, and prescription handling.
    - `DashboardService`: High-performance analytical aggregator for the executive dashboard.

---

### Q9: Deployment Architecture & Server Configuration
- **Concept:** Production deployment readiness, web server configuration, directory security, and environment portability.
- **Codebase Implementation:**
  - **Apache Security via `.htaccess`:**
    - Root `.htaccess` enables `FollowSymLinks`, configures default index to `index.php`, and sends HTTP security headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`).
    - Sensitive directories (`database/`, `includes/`, `storage/prescriptions/`) include individual `.htaccess` files containing `Require all denied` and `Options -Indexes`, preventing direct URL access to SQL dumps, backend scripts, and patient prescription uploads.
  - **Prescription File Streaming:** Uploaded prescription documents are accessed exclusively through an authenticated streaming controller (`pages/download_prescription.php`), verifying that only the owning customer or authorized staff/admin can view medical documents.
  - **Zero-Dependency Portability:** Runs seamlessly on local XAMPP, native CLI PHP, or standard Linux Apache shared hosting (e.g., InfinityFree, cPanel).

---

## 6. Minimal Realistic Academic Dataset

The canonical database seed (`database/pharmacare.sql`) contains exactly 15 realistic pharmaceutical items designed to demonstrate all inventory states during oral evaluation:

| ID | Medicine Trade Name | Generic / Category | Type | Batch No. | Stock | Price | Expiry Date | Status / Evaluation Role |
| :-: | :--- | :--- | :--- | :--- | :-: | :-: | :-: | :--- |
| **1** | Napa 500mg | Paracetamol (Analgesics) | Tablet | BX-2024-001 | 100 | ৳2.50 | 2027-12-31 | Available (In-Stock OTC) |
| **2** | Napa Extra | Paracetamol + Caffeine | Tablet | BX-2024-002 | 8 | ৳3.50 | 2027-08-31 | **Low Stock Alert** (<= 10 units) |
| **3** | Ace 500mg | Paracetamol (Analgesics) | Tablet | SQ-2024-003 | 150 | ৳2.00 | 2028-01-31 | Available (In-Stock OTC) |
| **4** | Ace Plus | Paracetamol + Caffeine | Tablet | SQ-2024-004 | 5 | ৳3.00 | 2027-11-30 | **Low Stock Alert** (<= 10 units) |
| **5** | Moxacil 500mg | Amoxicillin (Antibiotics) | Capsule | SQ-2024-005 | 40 | ৳8.00 | 2027-05-31 | Available (**Prescription Required**) |
| **6** | Fimoxyl 500mg | Amoxicillin (Antibiotics) | Capsule | BX-2024-006 | 60 | ৳7.50 | 2027-06-30 | Available (**Prescription Required**) |
| **7** | Azithrocin 500mg | Azithromycin (Antibiotics) | Tablet | BX-2024-007 | 30 | ৳35.00 | 2027-03-31 | Available (**Prescription Required**) |
| **8** | Zimax 500mg | Azithromycin (Antibiotics) | Tablet | IN-2024-008 | 25 | ৳35.00 | 2027-04-30 | Available (**Prescription Required**) |
| **9** | Histacin 4mg | Chlorpheniramine (Antihistamines) | Tablet | SQ-2024-009 | 200 | ৳0.50 | 2028-06-30 | Available (In-Stock OTC) |
| **10** | Alatrol 10mg | Cetirizine (Antihistamines) | Tablet | SQ-2024-010 | 80 | ৳3.00 | 2027-09-30 | Available (In-Stock OTC) |
| **11** | Entacyd 200ml | Antacid (Gastric Care) | Syrup | SQ-2024-011 | 20 | ৳85.00 | 2027-02-28 | Available (In-Stock OTC) |
| **12** | Seclo 20mg | Omeprazole (Gastric Care) | Capsule | SQ-2024-012 | 120 | ৳6.00 | 2027-10-31 | Available (In-Stock OTC) |
| **13** | Sergel 20mg | Esomeprazole (Gastric Care) | Capsule | IN-2024-013 | 90 | ৳7.00 | 2027-12-31 | Available (In-Stock OTC) |
| **14** | Dextromethorphan Syr | Cough Formula | Syrup | BX-2023-014 | 15 | ৳55.00 | *+20 days* | **Expiring Soon Alert** (< 30 days) |
| **15** | Old Amoxicillin Batch | Amoxicillin | Capsule | EX-2023-015 | 10 | ৳6.00 | *Past Date* | **Expired Batch** (Blocked from sale) |

---

## 7. License & Academic Integrity Notice

This project was developed for the **CIS324 Web Engineering** semester curriculum. It is released under the **MIT License**. Code structure, database normalization, and documentation are designed for academic submission, project viva defense, and educational demonstration.
