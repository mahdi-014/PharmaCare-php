<?php
/**
 * PharmaCare — Asynchronous JSON APIs & Vanilla JS Integration Test Suite
 * Academic Verification Test Suite for Phase 6 (CIS324 Web Engineering Q6 Alignment)
 *
 * Verifies:
 * - api/medicines/search.php (GET, 200, query filtering, 405 on non-GET, JSON headers)
 * - api/cart/add.php (POST, 401 unauthenticated, 403 staff, 403 CSRF rejection, 200 customer, 400 stock boundary)
 * - api/cart/update.php (POST, 401 unauthenticated, 200 update qty, 200 removal at qty=0, 404 not in cart)
 * - api/orders/status.php (GET, 401 unauthenticated, 403 IDOR prevention, 200 owner, 200 staff, timeline history)
 * - Strict HTTP status codes and standard JSON envelope format
 * - Database and session isolation and complete post-test teardown
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$passed = 0;

function assert_api(bool $condition, string $message, ?array $debug = null): void
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
echo "   PharmaCare Asynchronous JSON APIs Verification Suite    \n";
echo "============================================================\n\n";

$baseUrl = 'http://127.0.0.1:8000';
$pdo = db();

/**
 * Robust HTTP client with direct header-based session tracking and JSON decoding.
 */
function http_request(string $method, string $url, array $data = [], ?string &$sessionCookie = null, array $headers = []): array
{
    $ch = curl_init($url);
    $allHeaders = array_merge(['Accept: application/json'], $headers);

    if ($sessionCookie !== null && $sessionCookie !== '') {
        $allHeaders[] = "Cookie: $sessionCookie";
    }

    if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
        $jsonPayload = json_encode($data);
        $allHeaders[] = 'Content-Type: application/json';
        $allHeaders[] = 'Content-Length: ' . strlen($jsonPayload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
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
    $decoded = json_decode($body, true);

    // Track any updated PHPSESSID from Set-Cookie header
    if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $headerStr, $cm)) {
        $sessionCookie = 'PHPSESSID=' . trim($cm[1]);
    }

    return [
        'status' => $httpCode,
        'headers' => $headerStr,
        'body' => $body,
        'json' => is_array($decoded) ? $decoded : null,
        'cookie' => $sessionCookie,
    ];
}

/**
 * Authenticate session via actions.php and retrieve meta CSRF token.
 */
function authenticate_session(string $username, string $password, string $baseUrl): array
{
    $sessCookie = null;

    // Step 1: GET login page to obtain initial CSRF token
    $r1 = http_request('GET', "$baseUrl/index.php?page=login", [], $sessCookie);
    if (!preg_match('/name="_csrf" value="([^"]+)"/', $r1['body'], $matches)) {
        throw new RuntimeException("Could not extract login CSRF token for $username");
    }
    $loginCsrf = $matches[1];

    // Step 2: POST credentials to actions.php (form-urlencoded)
    $ch = curl_init("$baseUrl/actions.php");
    $postData = http_build_query([
        'action' => 'login',
        '_csrf' => $loginCsrf,
        'username' => $username,
        'password' => $password,
    ]);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            "Cookie: $sessCookie",
        ],
    ]);
    $raw2 = (string)curl_exec($ch);
    $hSize2 = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $hStr2 = substr($raw2, 0, $hSize2);

    if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $hStr2, $cm)) {
        $sessCookie = 'PHPSESSID=' . trim($cm[1]);
    }

    // Step 3: GET catalog page to obtain authenticated CSRF token from <meta name="csrf-token">
    $r3 = http_request('GET', "$baseUrl/index.php?page=catalog", [], $sessCookie);
    if (!preg_match('/<meta name="csrf-token" content="([^"]+)"/', $r3['body'], $matches2)) {
        throw new RuntimeException("Could not extract authenticated meta CSRF token for $username");
    }

    return [
        'csrf' => $matches2[1],
        'cookie' => $sessCookie,
    ];
}

try {
    // -------------------------------------------------------------
    // Setup Test Accounts
    // -------------------------------------------------------------
    $testCustomer2 = fetch_one("SELECT * FROM app_users WHERE username = 'test_cust2'");
    if (!$testCustomer2) {
        $hash = password_hash('cust2pass123', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO app_users (username, full_name, email, phone, role, password, enabled) VALUES ('test_cust2', 'Second Customer', 'cust2@example.com', '01711223399', 'CUSTOMER', ?, 1)")
            ->execute([$hash]);
        $testCustomer2Id = (int)$pdo->lastInsertId();
    } else {
        $testCustomer2Id = (int)$testCustomer2['id'];
    }

    // Authenticate sessions
    $custSession = authenticate_session('customer', 'customer123', $baseUrl);
    $custCsrf = $custSession['csrf'];
    $custCookie = $custSession['cookie'];

    $staffSession = authenticate_session('staff', 'staff123', $baseUrl);
    $staffCsrf = $staffSession['csrf'];
    $staffCookie = $staffSession['cookie'];

    $cust2Session = authenticate_session('test_cust2', 'cust2pass123', $baseUrl);
    $cust2Csrf = $cust2Session['csrf'];
    $cust2Cookie = $cust2Session['cookie'];

    assert_api(!empty($custCsrf) && !empty($custCookie), "Customer 1 authenticated and session CSRF token acquired");
    assert_api(!empty($staffCsrf) && !empty($staffCookie), "Staff authenticated and session CSRF token acquired");
    assert_api(!empty($cust2Csrf) && !empty($cust2Cookie), "Customer 2 authenticated for strict IDOR testing");

    // -------------------------------------------------------------
    // SECTION 1: api/medicines/search.php
    // -------------------------------------------------------------
    echo "\n--- Section 1: Medicine Search API ---\n";

    // 1.1: Reject non-GET
    $r = http_request('POST', "$baseUrl/api/medicines/search.php", ['q' => 'Paracetamol']);
    assert_api($r['status'] === 405, "POST request to search.php returns 405 Method Not Allowed");
    assert_api($r['json']['success'] === false, "405 response envelope contains success=false");

    // 1.2: Public GET all available medicines
    $r = http_request('GET', "$baseUrl/api/medicines/search.php");
    assert_api($r['status'] === 200, "Public GET request to search.php returns 200 OK");
    assert_api(str_contains($r['headers'], 'application/json'), "Response Content-Type is application/json");
    assert_api(str_contains($r['headers'], 'X-Content-Type-Options: nosniff'), "Response contains nosniff security header");
    assert_api($r['json']['success'] === true, "JSON envelope contains success=true");
    assert_api(isset($r['json']['data']['medicines']), "JSON payload data contains medicines array");
    assert_api($r['json']['data']['count'] > 0, "Available medicine inventory returned ({$r['json']['data']['count']} items)");

    // 1.3: Keyword filtering
    $r = http_request('GET', "$baseUrl/api/medicines/search.php?q=Paracetamol");
    assert_api($r['status'] === 200, "Keyword search returns 200 OK");
    assert_api($r['json']['data']['count'] > 0, "Keyword 'Paracetamol' returned {$r['json']['data']['count']} matching medicines");
    foreach ($r['json']['data']['medicines'] as $m) {
        $matches = (stripos($m['name'], 'Paracetamol') !== false) ||
                   (stripos($m['generic_name'], 'Paracetamol') !== false) ||
                   (stripos($m['brand_name'], 'Paracetamol') !== false);
        assert_api($matches, "Search result '{$m['name']}' matches generic/brand 'Paracetamol'");
        break;
    }

    // 1.4: Category filtering
    $cat = fetch_one("SELECT id, name FROM categories WHERE name = 'Antibiotic' LIMIT 1");
    if ($cat) {
        $r = http_request('GET', "$baseUrl/api/medicines/search.php?category_id={$cat['id']}");
        assert_api($r['status'] === 200, "Category filter returns 200 OK");
        foreach ($r['json']['data']['medicines'] as $m) {
            assert_api((int)$m['category_id'] === (int)$cat['id'], "Medicine {$m['name']} belongs to category {$cat['name']}");
            break;
        }
    }

    // -------------------------------------------------------------
    // SECTION 2: api/cart/add.php
    // -------------------------------------------------------------
    echo "\n--- Section 2: Asynchronous Add-to-Cart API ---\n";

    $activeMed = fetch_one("SELECT id, name, quantity FROM medicines WHERE active = 1 AND expiry_date >= CURDATE() AND quantity >= 10 LIMIT 1");
    assert_api($activeMed !== null, "Test active medicine batch selected (ID: {$activeMed['id']}, Stock: {$activeMed['quantity']})");
    $medId = (int)$activeMed['id'];
    $medStock = (int)$activeMed['quantity'];

    // 2.1: Reject non-POST
    $r = http_request('GET', "$baseUrl/api/cart/add.php");
    assert_api($r['status'] === 405, "GET request to cart/add.php returns 405 Method Not Allowed");

    // 2.2: Reject unauthenticated guest
    $nullCookie = null;
    $r = http_request('POST', "$baseUrl/api/cart/add.php", ['medicine_id' => $medId, 'quantity' => 1], $nullCookie);
    assert_api($r['status'] === 401, "Unauthenticated request returns 401 Unauthorized");
    assert_api(str_contains($r['json']['message'], 'Authentication required'), "401 error message prompts user to sign in");

    // 2.3: Reject Staff user (Staff role guard)
    $r = http_request('POST', "$baseUrl/api/cart/add.php", ['medicine_id' => $medId, 'quantity' => 1], $staffCookie, ["X-CSRF-Token: $staffCsrf"]);
    assert_api($r['status'] === 403, "Staff user request returns 403 Forbidden");
    assert_api(str_contains($r['json']['message'], 'Staff and admin accounts cannot use customer cart'), "Staff guard message confirmed");

    // 2.4: Reject Customer with invalid CSRF token
    $r = http_request('POST', "$baseUrl/api/cart/add.php", ['medicine_id' => $medId, 'quantity' => 1], $custCookie, ["X-CSRF-Token: invalid_csrf_token_123"]);
    assert_api($r['status'] === 403, "Invalid CSRF token returns 403 Forbidden");
    assert_api(str_contains($r['json']['message'], 'CSRF'), "Error message confirms CSRF token rejection");

    // 2.5: Successful Add-to-Cart for Customer with valid CSRF
    $r = http_request('POST', "$baseUrl/api/cart/add.php", ['medicine_id' => $medId, 'quantity' => 2], $custCookie, ["X-CSRF-Token: $custCsrf"]);
    assert_api($r['status'] === 200, "Valid Customer POST adds item to cart with 200 OK", $r);
    assert_api($r['json']['success'] === true, "Add-to-cart returns success=true");
    assert_api($r['json']['data']['item']['id'] === $medId, "Response contains added medicine ID");
    assert_api($r['json']['data']['cart_count'] >= 2, "Cart total unit count updated correctly");

    // 2.6: Stock boundary clamp: adding huge quantity clamps to batch stock
    $r = http_request('POST', "$baseUrl/api/cart/add.php", ['medicine_id' => $medId, 'quantity' => 99999], $custCookie, ["X-CSRF-Token: $custCsrf"]);
    assert_api($r['status'] === 200, "Adding quantity exceeding stock clamps to available batch stock ({$medStock}) with 200 OK");
    assert_api($r['json']['data']['item']['quantity'] === $medStock, "Clamped quantity equals max stock ($medStock)");

    // 2.7: Adding further units when already at max stock returns 400 DomainException
    $r = http_request('POST', "$baseUrl/api/cart/add.php", ['medicine_id' => $medId, 'quantity' => 1], $custCookie, ["X-CSRF-Token: $custCsrf"]);
    assert_api($r['status'] === 400, "Adding more units when already at max stock returns 400 Bad Request");
    assert_api($r['json']['success'] === false, "Rejection contains success=false");

    // 2.8: Invalid medicine ID (<= 0)
    $r = http_request('POST', "$baseUrl/api/cart/add.php", ['medicine_id' => 0, 'quantity' => 1], $custCookie, ["X-CSRF-Token: $custCsrf"]);
    assert_api($r['status'] === 400, "Medicine ID 0 returns 400 Bad Request");

    // 2.9: Non-existent medicine ID
    $r = http_request('POST', "$baseUrl/api/cart/add.php", ['medicine_id' => 9999999, 'quantity' => 1], $custCookie, ["X-CSRF-Token: $custCsrf"]);
    assert_api($r['status'] === 404, "Non-existent medicine ID returns 404 Not Found");

    // -------------------------------------------------------------
    // SECTION 3: api/cart/update.php
    // -------------------------------------------------------------
    echo "\n--- Section 3: Asynchronous Cart Update API ---\n";

    // 3.1: Reject non-POST
    $r = http_request('GET', "$baseUrl/api/cart/update.php");
    assert_api($r['status'] === 405, "GET request to cart/update.php returns 405 Method Not Allowed");

    // 3.2: Reject unauthenticated
    $nullCookie2 = null;
    $r = http_request('POST', "$baseUrl/api/cart/update.php", ['medicine_id' => $medId, 'quantity' => 3], $nullCookie2);
    assert_api($r['status'] === 401, "Unauthenticated update returns 401 Unauthorized");

    // 3.3: Customer updates quantity
    $r = http_request('POST', "$baseUrl/api/cart/update.php", ['medicine_id' => $medId, 'quantity' => 4], $custCookie, ["X-CSRF-Token: $custCsrf"]);
    assert_api($r['status'] === 200, "Customer updates item quantity to 4 with 200 OK");
    assert_api($r['json']['data']['item']['quantity'] === 4, "Updated quantity in session cart is 4");

    // 3.4: Customer sets quantity to 0 (item removal)
    $r = http_request('POST', "$baseUrl/api/cart/update.php", ['medicine_id' => $medId, 'quantity' => 0], $custCookie, ["X-CSRF-Token: $custCsrf"]);
    assert_api($r['status'] === 200, "Setting quantity to 0 returns 200 OK");
    assert_api($r['json']['data']['item_removed'] === true, "Response indicates item_removed=true");

    // 3.5: Updating item no longer in cart
    $r = http_request('POST', "$baseUrl/api/cart/update.php", ['medicine_id' => $medId, 'quantity' => 2], $custCookie, ["X-CSRF-Token: $custCsrf"]);
    assert_api($r['status'] === 404, "Updating item not in cart returns 404 Not Found");

    // -------------------------------------------------------------
    // SECTION 4: api/orders/status.php & Strict IDOR Protection
    // -------------------------------------------------------------
    echo "\n--- Section 4: Real-Time Order Status & IDOR Protection ---\n";

    // Setup an order for Customer 1 (ID: 3)
    $customer1 = fetch_one("SELECT * FROM app_users WHERE username = 'customer'");
    $orderNum = 'ORD-' . date('Ymd') . '-API' . rand(100, 999);
    $ins = $pdo->prepare(
        'INSERT INTO orders 
         (order_number, customer_id, customer_name, customer_phone, delivery_address, 
          subtotal, shipping_fee, total_amount, order_status, payment_method, payment_status, 
          prescription_required, prescription_status, stock_deducted, stock_restored) 
         VALUES (?, ?, ?, "01711223344", "API Test Address, Dhaka", 100.00, 0.00, 100.00, "PENDING", "COD", "UNPAID", 0, "NOT_REQUIRED", 1, 0)'
    );
    $ins->execute([$orderNum, (int)$customer1['id'], $customer1['full_name']]);
    $testOrderId = (int)$pdo->lastInsertId();

    $histStmt = $pdo->prepare('INSERT INTO order_status_history (order_id, previous_status, new_status, changed_by_id, remarks) VALUES (?, NULL, "PENDING", ?, "Order placed via API test")');
    $histStmt->execute([$testOrderId, (int)$customer1['id']]);

    // 4.1: Reject non-GET
    $r = http_request('POST', "$baseUrl/api/orders/status.php", ['id' => $testOrderId]);
    assert_api($r['status'] === 405, "POST request to orders/status.php returns 405 Method Not Allowed");

    // 4.2: Unauthenticated returns 401
    $nullCookie3 = null;
    $r = http_request('GET', "$baseUrl/api/orders/status.php?id=$testOrderId", [], $nullCookie3);
    assert_api($r['status'] === 401, "Unauthenticated request to orders/status.php returns 401 Unauthorized");

    // 4.3: Missing order parameters returns 400
    $r = http_request('GET', "$baseUrl/api/orders/status.php", [], $custCookie);
    assert_api($r['status'] === 400, "Missing order ID returns 400 Bad Request");

    // 4.4: Non-existent order returns 404
    $r = http_request('GET', "$baseUrl/api/orders/status.php?id=9999999", [], $custCookie);
    assert_api($r['status'] === 404, "Non-existent order returns 404 Not Found");

    // 4.5: Customer 1 queries their OWN order -> 200 OK
    $r = http_request('GET', "$baseUrl/api/orders/status.php?id=$testOrderId", [], $custCookie);
    assert_api($r['status'] === 200, "Customer 1 successfully queries their own order (200 OK)");
    assert_api($r['json']['data']['order_status'] === 'PENDING', "Returned order status is PENDING");
    assert_api(count($r['json']['data']['timeline']) >= 1, "Returned chronological audit timeline has >= 1 events");
    assert_api($r['json']['data']['is_terminal'] === false, "Pending order marked as is_terminal=false");

    // 4.6: STRICT IDOR PROTECTION: Customer 2 attempts to query Customer 1's order -> 403 Forbidden!
    $r = http_request('GET', "$baseUrl/api/orders/status.php?id=$testOrderId", [], $cust2Cookie);
    assert_api($r['status'] === 403, "IDOR Guard: Customer 2 querying Customer 1's order blocked with 403 Forbidden");
    assert_api(str_contains($r['json']['message'], 'Access denied'), "IDOR access denied message confirmed");

    // 4.7: Staff user queries Customer 1's order -> 200 OK (Staff authorization)
    $r = http_request('GET', "$baseUrl/api/orders/status.php?id=$testOrderId", [], $staffCookie);
    assert_api($r['status'] === 200, "Staff user querying customer order returns 200 OK");
    assert_api($r['json']['data']['order_number'] === $orderNum, "Staff received correct order data");

    // -------------------------------------------------------------
    // Cleanup Test Data
    // -------------------------------------------------------------
    $pdo->prepare('DELETE FROM order_status_history WHERE order_id = ?')->execute([$testOrderId]);
    $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$testOrderId]);
    $pdo->prepare("DELETE FROM app_users WHERE username = 'test_cust2'")->execute();

    echo "\n============================================================\n";
    echo "  PHASE 6 API TEST SUITE COMPLETE: $passed/$passed ASSERTIONS PASSED\n";
    echo "============================================================\n";

} catch (Throwable $e) {
    // Clean up on failure as well
    if (isset($testOrderId)) {
        try {
            $pdo->prepare('DELETE FROM order_status_history WHERE order_id = ?')->execute([$testOrderId]);
            $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$testOrderId]);
        } catch (Throwable $ignored) {}
    }
    if (isset($testCustomer2Id)) {
        try {
            $pdo->prepare("DELETE FROM app_users WHERE username = 'test_cust2'")->execute();
        } catch (Throwable $ignored) {}
    }
    fwrite(STDERR, "\n[FATAL ERROR] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}
