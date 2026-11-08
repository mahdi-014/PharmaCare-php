<?php
/**
 * PharmaCare — Unified Dashboard Analytics Service
 * High-performance query aggregator extracting unified operational analytics
 * across POS counter sales, online customer orders, inventory health, and prescription workflows.
 */

declare(strict_types=1);

namespace App\Services;

use PDO;

class DashboardService
{
    /**
     * Financial & Sales KPIs
     * - Today's Total Sales: SUM(total_amount) from sales where DATE(sale_date) = CURDATE()
     * - Channel Breakdown for Today:
     *   - POS sales subtotal (channel = 'POS')
     *   - Online orders subtotal (channel = 'ONLINE')
     *   - Total completed invoices count for today
     * - All-Time Revenue: Aggregate gross revenue (SUM(total_amount)) across all historical completed transactions
     * - All-Time Channel Breakdown and percentage split
     */
    public static function getFinancialKpis(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();

        $row = $pdo->query("
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
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalRevenue = (float)($row['total_revenue'] ?? 0.0);
        $allPosSales = (float)($row['all_pos_sales'] ?? 0.0);
        $allOnlineSales = (float)($row['all_online_sales'] ?? 0.0);

        $posPercentage = $totalRevenue > 0.0 ? round(($allPosSales / $totalRevenue) * 100, 1) : 0.0;
        $onlinePercentage = $totalRevenue > 0.0 ? round(($allOnlineSales / $totalRevenue) * 100, 1) : 0.0;

        $todayTotalSales = (float)($row['today_total_sales'] ?? 0.0);
        $todayPosSales = (float)($row['today_pos_sales'] ?? 0.0);
        $todayOnlineSales = (float)($row['today_online_sales'] ?? 0.0);

        return [
            'today_total_sales' => $todayTotalSales,
            'today_pos_sales' => $todayPosSales,
            'today_online_sales' => $todayOnlineSales,
            'today_invoices_count' => (int)($row['today_invoices_count'] ?? 0),
            'total_revenue' => $totalRevenue,
            'total_invoices_all' => (int)($row['total_invoices_all'] ?? 0),
            'all_pos_sales' => $allPosSales,
            'all_online_sales' => $allOnlineSales,
            'all_pos_count' => (int)($row['all_pos_count'] ?? 0),
            'all_online_count' => (int)($row['all_online_count'] ?? 0),
            'pos_percentage' => $posPercentage,
            'online_percentage' => $onlinePercentage,
        ];
    }

    /**
     * Inventory & Stock Health KPIs:
     * - Total Active Batches & Stock Units: Count of active medicine batches (active = 1) and total available stock units (SUM(quantity))
     * - Low Stock Count: Count of active, non-expired batches where quantity <= 10
     * - Expired Batches Count: Batches where expiry_date < CURDATE()
     * - Expiring Soon Count: Batches where expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)
     */
    public static function getInventoryKpis(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();

        $row = $pdo->query("
            SELECT 
                COUNT(CASE WHEN active = 1 THEN 1 END) AS active_batches,
                COALESCE(SUM(CASE WHEN active = 1 THEN quantity ELSE 0 END), 0) AS active_stock_units,
                COUNT(CASE WHEN active = 1 AND expiry_date >= CURDATE() AND quantity <= 10 THEN 1 END) AS low_stock_count,
                COUNT(CASE WHEN expiry_date < CURDATE() THEN 1 END) AS expired_count,
                COUNT(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 END) AS expiring_soon_count
            FROM medicines
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'active_batches' => (int)($row['active_batches'] ?? 0),
            'active_stock_units' => (int)($row['active_stock_units'] ?? 0),
            'low_stock_count' => (int)($row['low_stock_count'] ?? 0),
            'expired_count' => (int)($row['expired_count'] ?? 0),
            'expiring_soon_count' => (int)($row['expiring_soon_count'] ?? 0),
        ];
    }

    /**
     * Fulfillment & Operations KPIs:
     * - Pending Online Orders: Count of active online orders awaiting fulfillment (order_status IN ('PENDING', 'CONFIRMED'))
     * - Prescriptions Needing Review: Count of submitted customer prescriptions with verification_status = 'PENDING'
     */
    public static function getOperationsKpis(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();

        $pendingOrders = (int)$pdo->query("
            SELECT COUNT(*) FROM orders WHERE order_status IN ('PENDING', 'CONFIRMED')
        ")->fetchColumn();

        $pendingPrescriptions = (int)$pdo->query("
            SELECT COUNT(*) FROM prescriptions WHERE verification_status = 'PENDING'
        ")->fetchColumn();

        return [
            'pending_orders' => $pendingOrders,
            'pending_prescriptions' => $pendingPrescriptions,
        ];
    }

    /**
     * Top-Selling Medicines:
     * Top 5 medicines by unit quantity sold, querying sale_items joined with medicines and categories.
     * Include medicine name, category tag, and total sold units.
     */
    public static function getTopSellingMedicines(int $limit = 5, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();
        $limit = max(1, min(50, $limit));

        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.name,
                m.generic_name,
                COALESCE(c.name, 'General') AS category_name,
                COALESCE(SUM(si.quantity), 0) AS total_units_sold,
                COALESCE(SUM(si.line_total), 0.00) AS total_revenue_generated
            FROM sale_items si
            JOIN medicines m ON si.medicine_id = m.id
            LEFT JOIN categories c ON m.category_id = c.id
            GROUP BY m.id, m.name, m.generic_name, c.name
            ORDER BY total_units_sold DESC, total_revenue_generated DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recent Unified Transactions Feed:
     * Latest 8-10 transactions from sales (encompassing both POS counter sales and completed ONLINE orders bridged from orders)
     */
    public static function getRecentTransactions(int $limit = 10, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();
        $limit = max(1, min(50, $limit));

        $stmt = $pdo->prepare("
            SELECT 
                s.id,
                s.invoice_number,
                s.sale_date,
                s.customer_name,
                s.customer_phone,
                s.total_amount,
                s.channel,
                s.order_id,
                u.username AS seller_name,
                u.full_name AS seller_full_name,
                o.order_number
            FROM sales s
            LEFT JOIN app_users u ON s.sold_by_id = u.id
            LEFT JOIN orders o ON s.order_id = o.id
            ORDER BY s.sale_date DESC, s.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Inventory Stock & Expiry Alerts:
     * Medicines requiring immediate attention (expired, low stock, or expiring soon)
     */
    public static function getInventoryAlerts(int $limit = 8, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();
        $limit = max(1, min(50, $limit));

        $stmt = $pdo->prepare("
            SELECT 
                m.*, 
                COALESCE(c.name, 'General') AS category_name, 
                s.name AS supplier_name
            FROM medicines m
            LEFT JOIN categories c ON c.id = m.category_id
            LEFT JOIN suppliers s ON s.id = m.supplier_id
            WHERE m.active = 1 
              AND (
                  m.expiry_date < CURDATE() 
                  OR m.quantity <= 10 
                  OR m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
              )
            ORDER BY 
                (m.expiry_date < CURDATE()) DESC, 
                (m.quantity <= 10) DESC, 
                m.expiry_date ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $alerts = [];
        foreach ($rows as $m) {
            $status = medicine_status($m);
            if ($status !== 'AVAILABLE') {
                $alerts[] = $m + ['computed_status' => $status];
            }
        }
        return $alerts;
    }

    /**
     * Unified Aggregate Dashboard Summary
     */
    public static function getSummary(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? db();

        return [
            'financial' => self::getFinancialKpis($pdo),
            'inventory' => self::getInventoryKpis($pdo),
            'operations' => self::getOperationsKpis($pdo),
            'top_selling' => self::getTopSellingMedicines(5, $pdo),
            'recent_transactions' => self::getRecentTransactions(10, $pdo),
            'inventory_alerts' => self::getInventoryAlerts(8, $pdo),
        ];
    }
}
