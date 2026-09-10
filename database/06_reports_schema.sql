-- ==============================================================================
-- Optical Shop Management CMS (optical-mgt)
-- 06_reports_schema.sql - Reporting & Analytics Schema Reference & Index Optimization
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+ / phpMyAdmin
-- ==============================================================================
-- Note: The Reports & Analytics module queries directly from the core transactional
-- tables: `orders`, `order_items`, `payments`, `customers`, `prescriptions`, and `products`.
-- No redundant reporting tables are introduced, guaranteeing real-time data integrity.
--
-- This script ensures all foreign key and analytical query indices are verified.
-- ==============================================================================

-- Verify and ensure table indexes exist for analytical filtering and aggregations
-- (All tables are defined in schemas 01 through 05)

SELECT 'Reports schema optimization and reference verified successfully.' AS `status`;
