-- ==============================================================================
-- Optical Shop Management CMS (optical-mgt)
-- 05_orders_seed.sql - Realistic Sample Orders, Order Items & Payments Seed Data
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- Seed Orders
-- ------------------------------------------------------------------------------
INSERT INTO `orders` (
  `id`, `order_code`, `customer_id`, `prescription_id`, `order_date`,
  `subtotal`, `discount`, `grand_total`, `paid_amount`, `due_amount`,
  `status`, `stock_deducted`, `delivery_date`, `notes`, `created_by`, `created_at`
) VALUES
(
  1,
  'ORD-00001',
  1,
  2,
  DATE_SUB(CURDATE(), INTERVAL 12 DAY),
  5900.00,
  400.00,
  5500.00,
  5500.00,
  0.00,
  'delivered',
  1,
  DATE_SUB(CURDATE(), INTERVAL 8 DAY),
  'Complete prescription eyeglasses with Hoya blue-filter lenses and cleaning kit. Delivered on time.',
  3,
  DATE_SUB(NOW(), INTERVAL 12 DAY)
),
(
  2,
  'ORD-00002',
  2,
  3,
  DATE_SUB(CURDATE(), INTERVAL 8 DAY),
  6400.00,
  300.00,
  6100.00,
  3000.00,
  3100.00,
  'ready',
  1,
  DATE_ADD(CURDATE(), INTERVAL 2 DAY),
  'Titanium frame fitted with Essilor Crizal single vision distance lenses and protective clamshell case.',
  3,
  DATE_SUB(NOW(), INTERVAL 8 DAY)
),
(
  3,
  'ORD-00003',
  3,
  4,
  DATE_SUB(CURDATE(), INTERVAL 4 DAY),
  5000.00,
  500.00,
  4500.00,
  2000.00,
  2500.00,
  'processing',
  1,
  DATE_ADD(CURDATE(), INTERVAL 4 DAY),
  'Ray-Ban optical frame with custom Crizal lenses. Fitting currently in workshop.',
  2,
  DATE_SUB(NOW(), INTERVAL 4 DAY)
),
(
  4,
  'ORD-00004',
  4,
  NULL,
  DATE_SUB(CURDATE(), INTERVAL 2 DAY),
  700.00,
  0.00,
  700.00,
  700.00,
  0.00,
  'confirmed',
  1,
  DATE_ADD(CURDATE(), INTERVAL 1 DAY),
  'Accessory package: Anti-fog cleaner spray and reinforced clamshell case.',
  3,
  DATE_SUB(NOW(), INTERVAL 2 DAY)
),
(
  5,
  'ORD-00005',
  1,
  2,
  CURDATE(),
  3900.00,
  200.00,
  3700.00,
  0.00,
  3700.00,
  'pending',
  0,
  DATE_ADD(CURDATE(), INTERVAL 7 DAY),
  'Draft order awaiting customer frame fitting confirmation and initial deposit.',
  1,
  NOW()
)
ON DUPLICATE KEY UPDATE
  `order_code` = VALUES(`order_code`),
  `grand_total` = VALUES(`grand_total`),
  `paid_amount` = VALUES(`paid_amount`),
  `due_amount` = VALUES(`due_amount`),
  `status` = VALUES(`status`);

-- ------------------------------------------------------------------------------
-- Seed Order Items
-- ------------------------------------------------------------------------------
INSERT INTO `order_items` (
  `id`, `order_id`, `product_id`, `product_name`, `product_code`,
  `quantity`, `unit_price`, `total_price`, `created_at`
) VALUES
-- ORD-00001 (Order 1)
(1, 1, 1, 'Ray-Ban Aviator Optical Frame', 'FRM-00001', 1, 2800.00, 2800.00, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 1, 5, 'Hoya BlueControl 1.60 Digital', 'LNS-00003', 1, 2900.00, 2900.00, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3, 1, 6, 'Anti-Fog Lens Cleaner Spray 60ml', 'ACC-00001', 1, 200.00, 200.00, DATE_SUB(NOW(), INTERVAL 12 DAY)),

-- ORD-00002 (Order 2)
(4, 2, 2, 'Oakley Socket 5.0 Titanium', 'FRM-00002', 1, 3900.00, 3900.00, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(5, 2, 3, 'Essilor Crizal Easy Pro 1.56', 'LNS-00001', 1, 2200.00, 2200.00, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(6, 2, 7, 'Hard Shell Optical Clamshell Case', 'ACC-00002', 1, 300.00, 300.00, DATE_SUB(NOW(), INTERVAL 8 DAY)),

-- ORD-00003 (Order 3)
(7, 3, 1, 'Ray-Ban Aviator Optical Frame', 'FRM-00001', 1, 2800.00, 2800.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8, 3, 3, 'Essilor Crizal Easy Pro 1.56', 'LNS-00001', 1, 2200.00, 2200.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),

-- ORD-00004 (Order 4)
(9, 4, 6, 'Anti-Fog Lens Cleaner Spray 60ml', 'ACC-00001', 2, 200.00, 400.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(10, 4, 7, 'Hard Shell Optical Clamshell Case', 'ACC-00002', 1, 300.00, 300.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),

-- ORD-00005 (Order 5)
(11, 5, 2, 'Oakley Socket 5.0 Titanium', 'FRM-00002', 1, 3900.00, 3900.00, NOW())
ON DUPLICATE KEY UPDATE
  `product_name` = VALUES(`product_name`),
  `unit_price` = VALUES(`unit_price`),
  `total_price` = VALUES(`total_price`);

-- ------------------------------------------------------------------------------
-- Seed Payments
-- ------------------------------------------------------------------------------
INSERT INTO `payments` (
  `id`, `order_id`, `payment_date`, `amount`, `payment_method`,
  `reference`, `notes`, `received_by`, `created_at`
) VALUES
-- Payments for Order 1 (Total: 5500.00)
(1, 1, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 3000.00, 'Card', 'TXN-98412', 'Initial 50%+ advance payment received via POS card machine.', 3, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 2500.00, 'Cash', 'RCP-00192', 'Final settlement upon dispensing and fitting delivery.', 3, DATE_SUB(NOW(), INTERVAL 8 DAY)),

-- Payment for Order 2 (Advance: 3000.00, Due: 3100.00)
(3, 2, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 3000.00, 'Mobile Banking', 'BKASH-87129', 'Advance payment received via bKash merchant wallet.', 3, DATE_SUB(NOW(), INTERVAL 8 DAY)),

-- Payment for Order 3 (Advance: 2000.00, Due: 2500.00)
(4, 3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 2000.00, 'Card', 'POS-77182', 'Card payment at clinical consultation.', 2, DATE_SUB(NOW(), INTERVAL 4 DAY)),

-- Payment for Order 4 (Full: 700.00, Due: 0.00)
(5, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 700.00, 'Cash', 'RCP-00201', 'Direct cash payment for optical accessories at counter.', 3, DATE_SUB(NOW(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE
  `amount` = VALUES(`amount`),
  `payment_method` = VALUES(`payment_method`);

SET FOREIGN_KEY_CHECKS = 1;
