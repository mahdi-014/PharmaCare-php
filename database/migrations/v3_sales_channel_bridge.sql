-- ============================================================================
-- PharmaCare Migration: v3_sales_channel_bridge.sql
-- Description: Unifies Online Customer Orders with Core Sales & Invoicing Ledger
-- Channel differentiation: 'POS' (In-person counter sale) vs 'ONLINE' (Storefront order)
-- ============================================================================

-- 1. Add channel and order_id to sales table
ALTER TABLE sales 
    ADD COLUMN channel VARCHAR(20) NOT NULL DEFAULT 'POS' AFTER sold_by_id,
    ADD COLUMN order_id BIGINT UNSIGNED NULL AFTER channel;

-- 2. Add relational foreign key and performance indexes
ALTER TABLE sales 
    ADD CONSTRAINT fk_sale_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    ADD INDEX idx_sale_channel (channel),
    ADD INDEX idx_sale_order (order_id);

-- 3. Backfill any existing completed orders into sales & sale_items
-- Checks for completed orders that have not yet been bridged to sales
INSERT INTO sales (invoice_number, sale_date, customer_name, customer_phone, subtotal, discount, total_amount, sold_by_id, channel, order_id)
SELECT 
    CONCAT('INV-ORD-', SUBSTRING(o.order_number, 5)),
    COALESCE(o.updated_at, o.created_at),
    o.customer_name,
    o.customer_phone,
    o.subtotal,
    0.00,
    o.total_amount,
    COALESCE(o.customer_id, 1),
    'ONLINE',
    o.id
FROM orders o
WHERE o.order_status = 'COMPLETED'
  AND NOT EXISTS (SELECT 1 FROM sales s WHERE s.order_id = o.id);

-- Backfill corresponding sale_items for bridged completed orders
INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, line_total)
SELECT 
    s.id,
    oi.medicine_id,
    oi.quantity,
    oi.unit_price,
    oi.line_total
FROM orders o
JOIN sales s ON s.order_id = o.id
JOIN order_items oi ON oi.order_id = o.id
WHERE o.order_status = 'COMPLETED'
  AND NOT EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id);

-- 4. Record migration execution in schema_migrations
INSERT INTO schema_migrations (version, applied_at, description) 
VALUES ('v3_sales_channel_bridge', NOW(), 'Add channel and order_id to sales table for online order synchronization')
ON DUPLICATE KEY UPDATE applied_at = NOW();
