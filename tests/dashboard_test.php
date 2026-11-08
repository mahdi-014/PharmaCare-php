<?php
/**
 * PharmaCare — Unified Dashboard Analytics & Real-Time Reporting Test Suite
 * Academic Verification Test Suite for Dashboard Upgrade (CIS324 Web Engineering)
 *
 * Verifies:
 * - DashboardService query aggregation across POS sales, online orders, inventory, and prescriptions
 * - Financial accuracy: today sales, channel sub-totals (POS & Online), and all-time revenue
 * - Channel split integrity: pos_sales + online_sales == total_sales
 * - Inventory health: active batches, active units, low stock (<= 10), expired, and expiring soon (60d)
 * - Operations queue: pending online orders and prescriptions awaiting review
 * - Top-selling medicines ranking and recent unified transactions feed
 * - Strict RBAC boundary: Unauthenticated guest redirected (302), Customer blocked (403 Forbidden), Staff/Admin access permitted (200 OK)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

use App\Services\DashboardService;

$passed = 0;

function assert_dash(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) {
        fwrite(STDERR, "[FAIL] $message\n");
        exit(1);
    }
    echo "[PASS] $message\n";
    $passed++;
}

echo "============================================================\n";
echo "   PharmaCare Unified Dashboard Analytics Verification     \n";
echo "============================================================\n\n";

try {
    $pdo = db();

    // -------------------------------------------------------------
    // SECTION 1: Service Structure & Autoloading
    // -------------------------------------------------------------
    echo "--- Section 1: Service Initialization & Autoloading ---\n";
    assert_dash(class_exists(DashboardService::class), "DashboardService class autoloaded via PSR-4");

    $summary = DashboardService::getSummary($pdo);
    assert_dash(is_array($summary), "DashboardService::getSummary returns associative array");
    assert_dash(isset($summary['financial']), "Summary payload contains 'financial' section");
    assert_dash(isset($summary['inventory']), "Summary payload contains 'inventory' section");
    assert_dash(isset($summary['operations']), "Summary payload contains 'operations' section");
    assert_dash(isset($summary['top_selling']), "Summary payload contains 'top_selling' section");
    assert_dash(isset($summary['recent_transactions']), "Summary payload contains 'recent_transactions' section");
    assert_dash(isset($summary['inventory_alerts']), "Summary payload contains 'inventory_alerts' section");

    // -------------------------------------------------------------
    // SECTION 2: Financial & Channel Sales Accuracy
    // -------------------------------------------------------------
    echo "\n--- Section 2: Financial & Channel KPIs ---\n";
    $fin = DashboardService::getFinancialKpis($pdo);

    $dbSales = $pdo->query("
        SELECT 
            COUNT(*) AS total_invoices_all,
            COALESCE(SUM(total_amount), 0.00) AS total_revenue,
            COUNT(CASE WHEN DATE(sale_date) = CURDATE() THEN 1 END) AS today_invoices_count,
            COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() THEN total_amount ELSE 0 END), 0.00) AS today_total_sales,
            COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() AND channel = 'POS' THEN total_amount ELSE 0 END), 0.00) AS today_pos_sales,
            COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() AND channel = 'ONLINE' THEN total_amount ELSE 0 END), 0.00) AS today_online_sales,
            COALESCE(SUM(CASE WHEN channel = 'POS' THEN total_amount ELSE 0 END), 0.00) AS all_pos_sales,
            COALESCE(SUM(CASE WHEN channel = 'ONLINE' THEN total_amount ELSE 0 END), 0.00) AS all_online_sales,
            COUNT(CASE WHEN channel = 'POS' THEN 1 END) AS all_pos_count,
            COUNT(CASE WHEN channel = 'ONLINE' THEN 1 END) AS all_online_count
        FROM sales
    ")->fetch(PDO::FETCH_ASSOC);

    assert_dash((float)$fin['total_revenue'] === (float)$dbSales['total_revenue'], "Dashboard total revenue matches database ledger exactly (" . $fin['total_revenue'] . ")");
    assert_dash((float)$fin['today_total_sales'] === (float)$dbSales['today_total_sales'], "Today total sales matches database ledger (" . $fin['today_total_sales'] . ")");
    assert_dash((float)$fin['today_pos_sales'] === (float)$dbSales['today_pos_sales'], "Today POS sales matches database (" . $fin['today_pos_sales'] . ")");
    assert_dash((float)$fin['today_online_sales'] === (float)$dbSales['today_online_sales'], "Today Online sales matches database (" . $fin['today_online_sales'] . ")");
    assert_dash((int)$fin['today_invoices_count'] === (int)$dbSales['today_invoices_count'], "Today invoice count matches database (" . $fin['today_invoices_count'] . ")");
    assert_dash((float)$fin['all_pos_sales'] === (float)$dbSales['all_pos_sales'], "All-time POS sales volume matches database (" . $fin['all_pos_sales'] . ")");
    assert_dash((float)$fin['all_online_sales'] === (float)$dbSales['all_online_sales'], "All-time Online sales volume matches database (" . $fin['all_online_sales'] . ")");

    // Channel split math assertion
    $calcAllSum = round($fin['all_pos_sales'] + $fin['all_online_sales'], 2);
    assert_dash($calcAllSum === round($fin['total_revenue'], 2), "Channel sales sum matches total revenue: all_pos + all_online == total_revenue ($calcAllSum === {$fin['total_revenue']})");

    $calcTodaySum = round($fin['today_pos_sales'] + $fin['today_online_sales'], 2);
    assert_dash($calcTodaySum === round($fin['today_total_sales'], 2), "Today channel sales sum matches today total: today_pos + today_online == today_total ($calcTodaySum === {$fin['today_total_sales']})");

    if ($fin['total_revenue'] > 0.0) {
        $percentSum = round($fin['pos_percentage'] + $fin['online_percentage'], 0);
        assert_dash((int)$percentSum === 100, "Channel split percentages sum to 100% ({$fin['pos_percentage']}% + {$fin['online_percentage']}%)");
    }

    // -------------------------------------------------------------
    // SECTION 3: Inventory Health & Stock KPIs
    // -------------------------------------------------------------
    echo "\n--- Section 3: Inventory & Stock Health KPIs ---\n";
    $inv = DashboardService::getInventoryKpis($pdo);

    $dbInv = $pdo->query("
        SELECT 
            COUNT(CASE WHEN active = 1 THEN 1 END) AS active_batches,
            COALESCE(SUM(CASE WHEN active = 1 THEN quantity ELSE 0 END), 0) AS active_stock_units,
            COUNT(CASE WHEN active = 1 AND expiry_date >= CURDATE() AND quantity <= 10 THEN 1 END) AS low_stock_count,
            COUNT(CASE WHEN expiry_date < CURDATE() THEN 1 END) AS expired_count,
            COUNT(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 END) AS expiring_soon_count
        FROM medicines
    ")->fetch(PDO::FETCH_ASSOC);

    assert_dash((int)$inv['active_batches'] === (int)$dbInv['active_batches'], "Active medicine batches count matches database (" . $inv['active_batches'] . ")");
    assert_dash((int)$inv['active_stock_units'] === (int)$dbInv['active_stock_units'], "Active physical stock units match database (" . $inv['active_stock_units'] . ")");
    assert_dash((int)$inv['low_stock_count'] === (int)$dbInv['low_stock_count'], "Low stock batch count (<= 10 units) matches database (" . $inv['low_stock_count'] . ")");
    assert_dash((int)$inv['expired_count'] === (int)$dbInv['expired_count'], "Expired medicine batches count matches database (" . $inv['expired_count'] . ")");
    assert_dash((int)$inv['expiring_soon_count'] === (int)$dbInv['expiring_soon_count'], "Expiring soon batch count (within 60 days) matches database (" . $inv['expiring_soon_count'] . ")");

    // -------------------------------------------------------------
    // SECTION 4: Fulfillment & Operations KPIs
    // -------------------------------------------------------------
    echo "\n--- Section 4: Operations & Fulfillment Queue KPIs ---\n";
    $ops = DashboardService::getOperationsKpis($pdo);

    $dbPendingOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('PENDING', 'CONFIRMED')")->fetchColumn();
    $dbPendingRx = (int)$pdo->query("SELECT COUNT(*) FROM prescriptions WHERE verification_status = 'PENDING'")->fetchColumn();

    assert_dash((int)$ops['pending_orders'] === $dbPendingOrders, "Pending online orders count matches database (" . $ops['pending_orders'] . ")");
    assert_dash((int)$ops['pending_prescriptions'] === $dbPendingRx, "Pending prescriptions count matches database (" . $ops['pending_prescriptions'] . ")");

    // -------------------------------------------------------------
    // SECTION 5: Top-Selling & Recent Transactions Feeds
    // -------------------------------------------------------------
    echo "\n--- Section 5: Top-Selling & Unified Transactions Feeds ---\n";
    $topSelling = DashboardService::getTopSellingMedicines(5, $pdo);
    assert_dash(is_array($topSelling), "Top selling medicines returned as array");
    assert_dash(count($topSelling) <= 5, "Top selling list bounded to requested limit of 5");

    if (!empty($topSelling)) {
        $firstItem = $topSelling[0];
        assert_dash(isset($firstItem['name']), "Top selling item contains 'name'");
        assert_dash(isset($firstItem['category_name']), "Top selling item contains 'category_name'");
        assert_dash(isset($firstItem['total_units_sold']), "Top selling item contains 'total_units_sold'");
        assert_dash(isset($firstItem['total_revenue_generated']), "Top selling item contains 'total_revenue_generated'");
        if (count($topSelling) > 1) {
            assert_dash((int)$topSelling[0]['total_units_sold'] >= (int)$topSelling[1]['total_units_sold'], "Top selling list ordered descending by sold units");
        }
    }

    $recentTx = DashboardService::getRecentTransactions(10, $pdo);
    assert_dash(is_array($recentTx), "Recent transactions returned as array");
    assert_dash(count($recentTx) <= 10, "Recent transactions bounded to limit of 10");

    if (!empty($recentTx)) {
        $tx = $recentTx[0];
        assert_dash(isset($tx['invoice_number']), "Recent transaction contains 'invoice_number'");
        assert_dash(isset($tx['channel']), "Recent transaction contains 'channel'");
        assert_dash(in_array($tx['channel'], ['POS', 'ONLINE'], true), "Transaction channel is either POS or ONLINE ('" . $tx['channel'] . "')");
        assert_dash(isset($tx['total_amount']), "Recent transaction contains 'total_amount'");
    }

    // -------------------------------------------------------------
    // SECTION 6: Role Accessibility & Security Guard
    // -------------------------------------------------------------
    echo "\n--- Section 6: Role-Based Access Control on Dashboard ---\n";

    $baseUrl = 'http://127.0.0.1:8000';

    // Robust HTTP helper matching sec_http_request
    $httpRequest = function (string $method, string $url, array $data = [], ?string &$sessionCookie = null): array {
        $ch = curl_init($url);
        $headers = ['Accept: text/html,application/json'];
        if ($sessionCookie !== null && $sessionCookie !== '') {
            $headers[] = "Cookie: $sessionCookie";
        }
        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $raw = (string)curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headerStr = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        if (preg_match('/Set-Cookie:\s*PHPSESSID=([^;]+)/i', $headerStr, $cm)) {
            $sessionCookie = 'PHPSESSID=' . trim($cm[1]);
        }
        return ['status' => $httpCode, 'headers' => $headerStr, 'body' => $body, 'cookie' => $sessionCookie];
    };

    $loginUser = function (string $username, string $password) use ($baseUrl, $httpRequest): string {
        $cookie = null;
        $r1 = $httpRequest('GET', "$baseUrl/index.php?page=login", [], $cookie);
        if (!preg_match('/name="_csrf" value="([^"]+)"/', $r1['body'], $m)) {
            throw new RuntimeException("Could not extract login CSRF token for $username");
        }
        $r2 = $httpRequest('POST', "$baseUrl/actions.php", [
            'action' => 'login',
            '_csrf' => $m[1],
            'username' => $username,
            'password' => $password,
        ], $cookie);
        return $cookie ?? '';
    };

    // 6.1 Unauthenticated guest accessing dashboard redirected to login
    $guestCookie = null;
    $guestRes = $httpRequest('GET', "$baseUrl/index.php?page=dashboard", [], $guestCookie);
    assert_dash($guestRes['status'] === 302, "Unauthenticated guest receives HTTP 302 redirect");
    assert_dash(str_contains($guestRes['headers'], 'page=login'), "Guest redirected to login page");

    // 6.2 Customer login and attempt to access dashboard -> 403 Forbidden
    $custCookie = $loginUser('customer', 'customer123');
    $custDash = $httpRequest('GET', "$baseUrl/index.php?page=dashboard", [], $custCookie);
    assert_dash($custDash['status'] === 403, "Customer role barred from dashboard with HTTP 403 Forbidden");
    assert_dash(str_contains($custDash['body'], '403') || str_contains($custDash['body'], 'Access Denied'), "403 Forbidden template rendered to customer");

    // 6.3 Staff login and attempt to access dashboard -> 200 OK with analytics elements
    $staffCookie = $loginUser('staff', 'staff123');
    $staffDash = $httpRequest('GET', "$baseUrl/index.php?page=dashboard", [], $staffCookie);
    assert_dash($staffDash['status'] === 200, "Staff role accesses dashboard with HTTP 200 OK");
    assert_dash(str_contains($staffDash['body'], "Today's Sales"), "Dashboard view contains 'Today\'s Sales' metric card");
    assert_dash(str_contains($staffDash['body'], "Total Revenue"), "Dashboard view contains 'Total Revenue' metric card");
    assert_dash(str_contains($staffDash['body'], "Sales by Channel"), "Dashboard view contains 'Sales by Channel' analytics component");
    assert_dash(str_contains($staffDash['body'], "Top-Selling Medicines"), "Dashboard view contains 'Top-Selling Medicines' component");
    assert_dash(str_contains($staffDash['body'], "Recent Unified Transactions"), "Dashboard view contains 'Recent Unified Transactions' table");

} catch (Throwable $e) {
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

echo "\n============================================================\n";
echo "  DASHBOARD ANALYTICS TEST SUITE: $passed/$passed ASSERTIONS PASSED\n";
echo "============================================================\n";
