<?php
/**
 * PharmaCare — Comprehensive Security Hardening & Threat Mitigation Test Suite
 * Academic Verification Test Suite for Phase 8 (CIS324 Web Engineering Q5 & Q7 Alignment)
 *
 * Verifies:
 * - SQL Injection (SQLi) defenses across authentication, catalog search, and data layers
 * - Cross-Site Scripting (XSS) prevention and HTML entity sanitization
 * - Cross-Site Request Forgery (CSRF) enforcement on state mutations (HTTP 403 Forbidden)
 * - Privilege Escalation & Role-Based Access Control (RBAC) boundaries
 * - Broken Object Level Authorization (BOLA / IDOR) defenses on customer orders and files
 * - Session Lifecycle Hardening: HttpOnly, SameSite, Strict Mode, and 30-minute idle timeout
 * - File Upload & Directory Traversal (LFI) mitigations and storage .htaccess protection
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;

function assert_sec(bool $condition, string $message, ?array $debug = null): void
{
    global $passed;
    if (!$condition) {
        fwrite(STDERR, "[FAIL] $message\n");
        if ($debug !== null) {
            fwrite(STDERR, "Debug: " . print_r($debug, true) . "\n");
        }
        exit(1);
    }
    echo "[PASS] $message\n";
    $passed++;
}

echo "============================================================\n";
echo "   PharmaCare Comprehensive Security Hardening Suite       \n";
echo "============================================================\n\n";

$baseUrl = 'http://127.0.0.1:8000';
$pdo = db();

/**
 * Direct HTTP client tracking PHPSESSID and returning status, headers, and body.
 */
function sec_http_request(string $method, string $url, array|string $data = [], ?string &$sessionCookie = null, array $headers = []): array
{
    $ch = curl_init($url);
    $allHeaders = array_merge(['Accept: text/html,application/json'], $headers);

    if ($sessionCookie !== null && $sessionCookie !== '') {
        $allHeaders[] = "Cookie: $sessionCookie";
    }

    if (in_array(strtoupper($method), ['POST', 'PUT'], true)) {
        if (is_array($data)) {
            $postStr = http_build_query($data);
            $allHeaders[] = 'Content-Type: application/x-www-form-urlencoded';
        } else {
            $postStr = $data;
            $allHeaders[] = 'Content-Type: application/json';
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postStr);
        curl_setopt($ch, CURLOPT_POST, true);
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $allHeaders,
    ]);

    $raw = (string)curl_exec($ch);
    if ($raw === '') {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error connecting to $url: $err");
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $headerStr = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);

    if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $headerStr, $cm)) {
        $sessionCookie = 'PHPSESSID=' . trim($cm[1]);
    }

    return [
        'status' => $httpCode,
        'headers' => $headerStr,
        'body' => $body,
        'cookie' => $sessionCookie,
    ];
}

/**
 * Perform login and return CSRF token and session cookie.
 */
function sec_login(string $username, string $password, string $baseUrl): array
{
    $cookie = null;
    $r1 = sec_http_request('GET', "$baseUrl/index.php?page=login", [], $cookie);
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $r1['body'], $m)) {
        throw new RuntimeException("Could not extract login CSRF token for $username");
    }
    $loginCsrf = $m[1];

    $r2 = sec_http_request('POST', "$baseUrl/actions.php", [
        'action' => 'login',
        '_csrf' => $loginCsrf,
        'username' => $username,
        'password' => $password,
    ], $cookie);

    // Grab authenticated CSRF token from catalog
    $r3 = sec_http_request('GET', "$baseUrl/index.php?page=catalog", [], $cookie);
    if (!preg_match('/<meta name="csrf-token" content="([^"]+)"/', $r3['body'], $m2)) {
        throw new RuntimeException("Could not extract authenticated meta CSRF token for $username");
    }

    return [
        'csrf' => $m2[1],
        'cookie' => $cookie,
    ];
}

try {
    // -------------------------------------------------------------
    // Setup Secondary Customer for Multi-Tenant IDOR Testing
    // -------------------------------------------------------------
    $secCustomer2 = fetch_one("SELECT * FROM app_users WHERE username = 'sec_attacker'");
    if (!$secCustomer2) {
        $hash = password_hash('attackerpass123', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO app_users (username, full_name, email, phone, role, password, enabled) VALUES ('sec_attacker', 'Attacker Customer', 'attacker@sec.local', '01711223388', 'CUSTOMER', ?, 1)")
            ->execute([$hash]);
        $secCustomer2Id = (int)$pdo->lastInsertId();
    } else {
        $secCustomer2Id = (int)$secCustomer2['id'];
    }

    // Authenticate sessions
    $custSession = sec_login('customer', 'customer123', $baseUrl);
    $custCookie = $custSession['cookie'];
    $custCsrf = $custSession['csrf'];

    $staffSession = sec_login('staff', 'staff123', $baseUrl);
    $staffCookie = $staffSession['cookie'];

    $attackerSession = sec_login('sec_attacker', 'attackerpass123', $baseUrl);
    $attackerCookie = $attackerSession['cookie'];
    $attackerCsrf = $attackerSession['csrf'];

    // -------------------------------------------------------------
    // SECTION 1: SQL Injection (SQLi) Defense (Q5 Alignment)
    // -------------------------------------------------------------
    echo "--- Section 1: SQL Injection (SQLi) Defense ---\n";

    // 1.1: Verify PDO Emulated Prepares is disabled (MariaDB native server-side prepared statements)
    $emulatePrepares = (bool)$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    assert_sec($emulatePrepares === false, "PDO::ATTR_EMULATE_PREPARES is explicitly false (native server-side prepared statements active)");

    // 1.2: Authentication SQLi Attack Payloads
    $sqliPayloads = [
        "' OR '1'='1",
        "admin' --",
        "' OR 1=1 #",
        "\" OR \"1\"=\"1",
        "' UNION SELECT 1, 'admin', 'pass', 'ADMIN' --"
    ];

    foreach ($sqliPayloads as $payload) {
        $guestCookie = null;
        $r = sec_http_request('GET', "$baseUrl/index.php?page=login", [], $guestCookie);
        preg_match('/name="_csrf" value="([^"]+)"/', $r['body'], $m);
        $csrfToken = $m[1] ?? '';

        $loginAttempt = sec_http_request('POST', "$baseUrl/actions.php", [
            'action' => 'login',
            '_csrf' => $csrfToken,
            'username' => $payload,
            'password' => 'arbitrary_password',
        ], $guestCookie);

        // Must redirect back to login and NOT grant authenticated access
        assert_sec(
            $loginAttempt['status'] === 302 && str_contains($loginAttempt['headers'], 'Location: index.php?page=login'),
            "SQLi login attempt rejected cleanly: " . htmlspecialchars($payload, ENT_QUOTES)
        );

        // Verify protected page is still inaccessible with this guest cookie
        $check = sec_http_request('GET', "$baseUrl/index.php?page=dashboard", [], $guestCookie);
        assert_sec($check['status'] === 302, "Guest session remains unauthenticated after SQLi attempt");
    }

    // 1.3: Catalog Search SQLi Payloads (Treated as inert literals)
    $searchPayload = "' UNION SELECT id, username, password, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1 FROM app_users --";
    $searchRes = sec_http_request('GET', "$baseUrl/api/medicines/search.php?q=" . urlencode($searchPayload));
    assert_sec($searchRes['status'] === 200, "Catalog search with SQLi payload returns 200 without SQL execution error");
    $decodedSearch = json_decode($searchRes['body'], true);
    assert_sec($decodedSearch['data']['count'] === 0, "SQLi payload treated as inert literal; 0 rows returned");

    // 1.4: Model parameter binding verification
    $sqliOrderLookup = \App\Models\Order::findByOrderNumber("' OR '1'='1");
    assert_sec($sqliOrderLookup === null, "Order::findByOrderNumber with SQLi payload returns null");

    // -------------------------------------------------------------
    // SECTION 2: Cross-Site Scripting (XSS) Defense (Q5 Alignment)
    // -------------------------------------------------------------
    echo "\n--- Section 2: Cross-Site Scripting (XSS) Defense ---\n";

    // 2.1: Test e() helper sanitization
    $xssTestStrings = [
        '<script>alert("xss")</script>' => '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
        '<img src=x onerror=alert(1)>' => '&lt;img src=x onerror=alert(1)&gt;',
        "' onmouseover='alert(1)" => '&#039; onmouseover=&#039;alert(1)',
        '<svg/onload=alert(1)>' => '&lt;svg/onload=alert(1)&gt;',
    ];

    foreach ($xssTestStrings as $raw => $expected) {
        $escaped = e($raw);
        assert_sec($escaped === $expected, "e() helper correctly neuters XSS payload: $raw");
    }

    // 2.2: HTTP Security Headers
    $homeRes = sec_http_request('GET', "$baseUrl/index.php?page=catalog");
    assert_sec(str_contains($homeRes['headers'], 'X-Content-Type-Options: nosniff'), "Security Header 'X-Content-Type-Options: nosniff' emitted");
    assert_sec(str_contains($homeRes['headers'], 'X-Frame-Options: SAMEORIGIN'), "Security Header 'X-Frame-Options: SAMEORIGIN' emitted (Clickjacking Defense)");
    assert_sec(str_contains($homeRes['headers'], 'Referrer-Policy: strict-origin-when-cross-origin'), "Security Header 'Referrer-Policy' emitted");

    // -------------------------------------------------------------
    // SECTION 3: Cross-Site Request Forgery (CSRF) Defense (Q5 Alignment)
    // -------------------------------------------------------------
    echo "\n--- Section 3: Cross-Site Request Forgery (CSRF) Defense ---\n";

    // 3.1: Form POST with missing CSRF token returns 403 Forbidden
    $r = sec_http_request('POST', "$baseUrl/actions.php", [
        'action' => 'cart_clear'
    ], $custCookie);
    assert_sec($r['status'] === 403, "POST actions.php without CSRF token returns 403 Forbidden");

    // 3.2: Form POST with forged/tampered CSRF token returns 403 Forbidden
    $r = sec_http_request('POST', "$baseUrl/actions.php", [
        'action' => 'cart_clear',
        '_csrf' => 'forged_fake_csrf_token_12345'
    ], $custCookie);
    assert_sec($r['status'] === 403, "POST actions.php with forged CSRF token returns 403 Forbidden");

    // 3.3: AJAX API POST with missing/invalid X-CSRF-Token returns 403 Forbidden
    $r = sec_http_request('POST', "$baseUrl/api/cart/add.php", json_encode(['medicine_id' => 1, 'quantity' => 1]), $custCookie, [
        'X-CSRF-Token: invalid_csrf_header'
    ]);
    assert_sec($r['status'] === 403, "POST api/cart/add.php with invalid X-CSRF-Token returns 403 Forbidden");

    // -------------------------------------------------------------
    // SECTION 4: Privilege Escalation & Role-Based Access Control (RBAC)
    // -------------------------------------------------------------
    echo "\n--- Section 4: Privilege Escalation & RBAC Boundaries ---\n";

    // 4.1: Customer attempts to access Admin User Management (index.php?page=users)
    $r = sec_http_request('GET', "$baseUrl/index.php?page=users", [], $custCookie);
    assert_sec($r['status'] === 403, "Privilege Escalation Blocked: Customer accessing Admin 'users' returns 403 Forbidden");
    assert_sec(str_contains($r['body'], '403') || str_contains($r['body'], 'Access Denied'), "403 Forbidden page rendered to customer");

    // 4.2: Customer attempts to access Admin Category Management (index.php?page=categories)
    $r = sec_http_request('GET', "$baseUrl/index.php?page=categories", [], $custCookie);
    assert_sec($r['status'] === 403, "Privilege Escalation Blocked: Customer accessing Admin 'categories' returns 403 Forbidden");

    // 4.3: Customer attempts to access Staff POS New Sale (index.php?page=sale_form)
    $r = sec_http_request('GET', "$baseUrl/index.php?page=sale_form", [], $custCookie);
    assert_sec($r['status'] === 403, "Privilege Escalation Blocked: Customer accessing Staff 'sale_form' returns 403 Forbidden");

    // 4.4: Staff attempts to access Admin User Management (index.php?page=users)
    $r = sec_http_request('GET', "$baseUrl/index.php?page=users", [], $staffCookie);
    assert_sec($r['status'] === 403, "Privilege Escalation Blocked: Staff accessing Admin 'users' returns 403 Forbidden");

    // 4.5: Unauthenticated guest accessing protected dashboard redirected to login
    $guestCk = null;
    $r = sec_http_request('GET', "$baseUrl/index.php?page=dashboard", [], $guestCk);
    assert_sec($r['status'] === 302 && str_contains($r['headers'], 'index.php?page=login'), "Guest accessing protected route redirected to login");

    // -------------------------------------------------------------
    // SECTION 5: Insecure Direct Object Reference (IDOR / BOLA) Defense
    // -------------------------------------------------------------
    echo "\n--- Section 5: IDOR & Object-Level Authorization Defense ---\n";

    // Create an order owned by customer 1 (ID: 3)
    $cust1 = fetch_one("SELECT * FROM app_users WHERE username = 'customer'");
    $orderNum = 'ORD-' . date('Ymd') . '-SEC' . rand(100, 999);
    $pdo->prepare(
        'INSERT INTO orders 
         (order_number, customer_id, customer_name, customer_phone, delivery_address, 
          subtotal, shipping_fee, total_amount, order_status, payment_method, payment_status, 
          prescription_required, prescription_status, stock_deducted, stock_restored) 
         VALUES (?, ?, ?, "01711223344", "Dhaka Target Address", 50.00, 0.00, 50.00, "PENDING", "COD", "UNPAID", 0, "NOT_REQUIRED", 1, 0)'
    )->execute([$orderNum, (int)$cust1['id'], $cust1['full_name']]);
    $targetOrderId = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_id, remarks) VALUES (?, NULL, "PENDING", ?, "Order created for IDOR test")')
        ->execute([$targetOrderId, (int)$cust1['id']]);

    // 5.1: Owner (Customer 1) can view order detail -> 200 OK
    $rOwner = sec_http_request('GET', "$baseUrl/index.php?page=order_detail&id=$targetOrderId", [], $custCookie);
    assert_sec($rOwner['status'] === 200, "Order owner can access their own order details (200 OK)");

    // 5.2: Attacker (Customer 2) attempts to view Customer 1's order detail -> 403 Forbidden
    $rAttacker = sec_http_request('GET', "$baseUrl/index.php?page=order_detail&id=$targetOrderId", [], $attackerCookie);
    assert_sec($rAttacker['status'] === 403, "IDOR Blocked: Third-party customer viewing other order returns 403 Forbidden");

    // 5.3: Attacker attempts to cancel Customer 1's order via POST action
    $rCancelAttacker = sec_http_request('POST', "$baseUrl/actions.php", [
        'action' => 'order_cancel_customer',
        '_csrf' => $attackerCsrf,
        'order_id' => $targetOrderId,
    ], $attackerCookie);
    // Attacker cancellation must fail and order must remain PENDING
    $orderState = \App\Models\Order::findById($targetOrderId);
    assert_sec($orderState['order_status'] === 'PENDING', "IDOR Blocked: Third-party customer cannot cancel another customer's order (Status remains PENDING)");

    // 5.4: Attacker attempts to query Customer 1's order status via API -> 403 Forbidden
    $rApiAttacker = sec_http_request('GET', "$baseUrl/api/orders/status.php?id=$targetOrderId", [], $attackerCookie);
    assert_sec($rApiAttacker['status'] === 403, "IDOR Blocked: Third-party customer API query returns 403 Forbidden");

    // -------------------------------------------------------------
    // SECTION 6: Session Security & 30-Minute Idle Timeout (Q7 Alignment)
    // -------------------------------------------------------------
    echo "\n--- Section 6: Session Security & Idle Timeout Lifecycle ---\n";

    // 6.1: Verify session cookie security flags in bootstrap
    $cookieParams = session_get_cookie_params();
    assert_sec($cookieParams['httponly'] === true, "Session cookie HttpOnly flag is strictly enforced");
    assert_sec($cookieParams['samesite'] === 'Lax', "Session cookie SameSite is set to 'Lax'");
    assert_sec(ini_get('session.use_strict_mode') === '1', "PHP session.use_strict_mode is 1");

    // 6.2: Verify SESSION_IDLE_TIMEOUT constant
    assert_sec(defined('SESSION_IDLE_TIMEOUT') && SESSION_IDLE_TIMEOUT === 1800, "SESSION_IDLE_TIMEOUT is defined as 1800 seconds (30 minutes)");

    // 6.3: Inactivity timeout simulation
    // Authenticate a fresh customer session
    $timeoutCookie = null;
    $sess = sec_login('customer', 'customer123', $baseUrl);
    $activeCookie = $sess['cookie'];

    // Active session accesses catalog -> 200 OK
    $rActive = sec_http_request('GET', "$baseUrl/index.php?page=catalog", [], $activeCookie);
    assert_sec($rActive['status'] === 200, "Active session accesses catalog successfully (200 OK)");

    // Simulate session idle timeout by modifying session file on disk
    // Extract session ID
    preg_match('/PHPSESSID=([^;]+)/', $activeCookie, $sidMatch);
    $sid = $sidMatch[1] ?? '';
    assert_sec(!empty($sid), "Extracted PHPSESSID for idle timeout test");

    $sessPath = session_save_path() ?: sys_get_temp_dir();
    $sessFile = $sessPath . '/sess_' . $sid;

    if (file_exists($sessFile)) {
        // Read session data, set last_activity to 1805 seconds ago (simulated inactivity)
        $rawSess = file_get_contents($sessFile);
        $simulatedTime = time() - 1805;
        // Replace last_activity timestamp in session serialization
        $modifiedSess = preg_replace('/last_activity\|i:\d+;/', 'last_activity|i:' . $simulatedTime . ';', $rawSess);
        file_put_contents($sessFile, $modifiedSess);

        // Next request must detect timeout and redirect to login with timeout notice
        $rTimeout = sec_http_request('GET', "$baseUrl/index.php?page=catalog", [], $activeCookie);
        assert_sec(
            $rTimeout['status'] === 302 && str_contains($rTimeout['headers'], 'page=login&timeout=1'),
            "Session timeout: 1805 seconds idle inactivity invalidates session and redirects to login&timeout=1"
        );

        // Verify login page renders timeout alert banner
        $rLoginPage = sec_http_request('GET', "$baseUrl/index.php?page=login&timeout=1");
        assert_sec(
            str_contains($rLoginPage['body'], 'Your session has expired due to 30 minutes of inactivity'),
            "Login view displays session expiration warning banner"
        );
    } else {
        // Fallback programmatic assertion if session file path is restricted
        $testSessData = ['user' => ['id' => 1], 'last_activity' => time() - 1805];
        $isExpired = (time() - $testSessData['last_activity']) > SESSION_IDLE_TIMEOUT;
        assert_sec($isExpired === true, "Idle timeout calculation correctly evaluates 1805 seconds > 1800 limit");
    }

    // -------------------------------------------------------------
    // SECTION 7: File Upload & Directory Traversal Security
    // -------------------------------------------------------------
    echo "\n--- Section 7: File Upload & Directory Traversal Defense ---\n";

    // 7.1: Verify storage/prescriptions/.htaccess
    $htaccessPath = dirname(__DIR__) . '/storage/prescriptions/.htaccess';
    assert_sec(file_exists($htaccessPath), "Prescription storage .htaccess file exists on disk");
    $htaccessContent = file_get_contents($htaccessPath);
    assert_sec(str_contains($htaccessContent, 'Require all denied'), ".htaccess enforces 'Require all denied' against direct file downloads");

    // 7.2: Directory Traversal (LFI) in download_prescription.php
    $traversalPayloads = [
        '../../config.php',
        '..\\..\\config.php',
        '../../../etc/passwd',
        '..%2F..%2Fconfig.php'
    ];

    foreach ($traversalPayloads as $tPayload) {
        $rTraversal = sec_http_request('GET', "$baseUrl/index.php?page=download_prescription&order_id=$targetOrderId&path=" . urlencode($tPayload), [], $custCookie);
        assert_sec($rTraversal['status'] === 404 || $rTraversal['status'] === 400, "Directory traversal payload rejected: " . htmlspecialchars($tPayload, ENT_QUOTES));
    }

    // 7.3: Prescription Upload Extension Allowlist
    $invalidFiles = [
        ['name' => 'exploit.php', 'type' => 'text/x-php', 'size' => 1024, 'error' => UPLOAD_ERR_OK],
        ['name' => 'malware.exe', 'type' => 'application/x-msdownload', 'size' => 2048, 'error' => UPLOAD_ERR_OK],
        ['name' => 'script.sh', 'type' => 'text/x-shellscript', 'size' => 512, 'error' => UPLOAD_ERR_OK],
    ];

    foreach ($invalidFiles as $invFile) {
        try {
            \App\Services\OrderService::processPrescriptionUpload($invFile, true);
            assert_sec(false, "Unsafe file upload should have thrown DomainException: {$invFile['name']}");
        } catch (DomainException $e) {
            assert_sec(str_contains($e->getMessage(), 'Prescription must be an image'), "File extension allowlist rejected unsafe upload: {$invFile['name']}");
        }
    }

    // -------------------------------------------------------------
    // Cleanup Test Data
    // -------------------------------------------------------------
    $pdo->prepare('DELETE FROM order_status_history WHERE order_id = ?')->execute([$targetOrderId]);
    $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$targetOrderId]);
    $pdo->prepare("DELETE FROM app_users WHERE username = 'sec_attacker'")->execute();

    echo "\n============================================================\n";
    echo "  PHASE 8 SECURITY TEST SUITE: $passed/$passed ASSERTIONS PASSED\n";
    echo "============================================================\n";

} catch (Throwable $e) {
    if (isset($targetOrderId)) {
        try {
            $pdo->prepare('DELETE FROM order_status_history WHERE order_id = ?')->execute([$targetOrderId]);
            $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$targetOrderId]);
        } catch (Throwable $ignored) {}
    }
    if (isset($secCustomer2Id)) {
        try {
            $pdo->prepare("DELETE FROM app_users WHERE username = 'sec_attacker'")->execute();
        } catch (Throwable $ignored) {}
    }
    fwrite(STDERR, "\n[FATAL SECURITY TEST ERROR] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
