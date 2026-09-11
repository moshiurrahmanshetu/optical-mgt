-- ==============================================================================
-- VisionCare Optical Shop Management CMS (optical-mgt)
-- Master Database Installation Package (database/install.sql)
-- Complete Schema & Default Application Dataset
-- Compatible with MySQL 5.7+, MySQL 8.0+, MariaDB 10.3+ / phpMyAdmin
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ------------------------------------------------------------------------------
-- 1. Table: roles
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'admin', 'Administrator', 'Full system access, user management, and system settings'),
(2, 'optician', 'Optician', 'Clinical eye testing, prescription entry, and dispensing'),
(3, 'sales_staff', 'Sales Staff', 'Customer management, product sales, and billing');

-- ------------------------------------------------------------------------------
-- 2. Table: users
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `password` VARCHAR(255) NOT NULL,
  `avatar` VARCHAR(255) NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role_id` (`role_id`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `role_id`, `name`, `username`, `email`, `phone`, `password`, `avatar`, `status`, `last_login_at`) VALUES
(
  1,
  1,
  'System Administrator',
  'admin',
  'admin@opticalmgt.com',
  '+1 (555) 019-2831',
  '$2y$10$bsoRi3agYeThBk7vvw5M8uD2/ezPd2fPpN0KOGBf8kHKxMh8Rop46',
  NULL,
  'active',
  NULL
),
(
  2,
  2,
  'Dr. Sarah Connor',
  'optician',
  'optician@opticalmgt.com',
  '+1 (555) 019-2832',
  '$2y$10$bsoRi3agYeThBk7vvw5M8uD2/ezPd2fPpN0KOGBf8kHKxMh8Rop46',
  NULL,
  'active',
  NULL
),
(
  3,
  3,
  'John Miller',
  'sales',
  'sales@opticalmgt.com',
  '+1 (555) 019-2833',
  '$2y$10$bsoRi3agYeThBk7vvw5M8uD2/ezPd2fPpN0KOGBf8kHKxMh8Rop46',
  NULL,
  'active',
  NULL
);

-- ------------------------------------------------------------------------------
-- 3. Table: customers
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` VARCHAR(20) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(150) NULL,
  `gender` ENUM('male', 'female', 'other') NULL,
  `date_of_birth` DATE NULL,
  `address` TEXT NULL,
  `notes` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customers_code` (`customer_code`),
  KEY `idx_customers_phone` (`phone`),
  KEY `idx_customers_status` (`status`),
  KEY `idx_customers_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `customers` (`id`, `customer_code`, `full_name`, `phone`, `email`, `gender`, `date_of_birth`, `address`, `notes`, `status`, `created_at`) VALUES
(
  1,
  'CUS-00001',
  'Rahim Ahmed',
  '+880 1711-234567',
  'rahim.ahmed@example.com',
  'male',
  '1988-05-14',
  'House 24, Road 5, Dhanmondi, Dhaka',
  'Requires blue-light filter and anti-glare single vision lenses for daily computer work.',
  'active',
  NOW() - INTERVAL 15 DAY
),
(
  2,
  'CUS-00002',
  'Farzana Yasmin',
  '+880 1819-345678',
  'farzana.y@example.com',
  'female',
  '1994-11-20',
  'Flat 4B, Sector 7, Uttara, Dhaka',
  'Prefers lightweight rimless or titanium frames. Regular annual checkup patient.',
  'active',
  NOW() - INTERVAL 10 DAY
),
(
  3,
  'CUS-00003',
  'Michael Sterling',
  '+880 1912-456789',
  'michael.s@example.com',
  'male',
  '1976-03-08',
  'Road 11, Banani, Dhaka',
  'Progressive lenses wearer with slight astigmatism correction in right eye.',
  'active',
  NOW() - INTERVAL 6 DAY
),
(
  4,
  'CUS-00004',
  'Nusrat Jahan',
  '+880 1610-567890',
  'nusrat.jahan@example.com',
  'female',
  '2001-08-25',
  'Block C, Bashundhara R/A, Dhaka',
  'Contact lens regular user; uses monthly soft disposable lenses with hydrating solution.',
  'active',
  NOW() - INTERVAL 2 DAY
),
(
  5,
  'CUS-00005',
  'David Vance',
  '+880 1722-678901',
  'david.vance@example.com',
  'male',
  '1982-12-10',
  'Avenue 2, Mirpur DOHS, Dhaka',
  'Account temporarily inactive; pending prescription renewal and eye test appointment.',
  'inactive',
  NOW() - INTERVAL 1 DAY
);

-- ------------------------------------------------------------------------------
-- 4. Table: prescriptions
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `prescription_date` DATE NOT NULL,
  
  -- Right Eye (OD - Oculus Dexter) Measurements
  `right_sph` DECIMAL(5,2) NULL,
  `right_cyl` DECIMAL(5,2) NULL,
  `right_axis` SMALLINT UNSIGNED NULL,
  `right_add` DECIMAL(4,2) NULL,
  `right_pd` DECIMAL(4,1) NULL,
  
  -- Left Eye (OS - Oculus Sinister) Measurements
  `left_sph` DECIMAL(5,2) NULL,
  `left_cyl` DECIMAL(5,2) NULL,
  `left_axis` SMALLINT UNSIGNED NULL,
  `left_add` DECIMAL(4,2) NULL,
  `left_pd` DECIMAL(4,1) NULL,
  
  -- Clinical & Examination Information
  `doctor_name` VARCHAR(150) NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_prescriptions_customer_id` (`customer_id`),
  KEY `idx_prescriptions_date` (`prescription_date`),
  KEY `idx_prescriptions_created_by` (`created_by`),
  
  CONSTRAINT `fk_prescriptions_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_prescriptions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `prescriptions` (
  `id`, `customer_id`, `prescription_date`,
  `right_sph`, `right_cyl`, `right_axis`, `right_add`, `right_pd`,
  `left_sph`, `left_cyl`, `left_axis`, `left_add`, `left_pd`,
  `doctor_name`, `notes`, `created_by`, `created_at`
) VALUES
(
  1, 1, DATE_SUB(CURDATE(), INTERVAL 180 DAY),
  -2.00, -0.50, 180, 1.25, 31.0,
  -1.75, -0.50, 175, 1.25, 31.0,
  'Dr. Sarah Connor', 'Initial reading and computer glasses examination.', 2,
  DATE_SUB(NOW(), INTERVAL 180 DAY)
),
(
  2, 1, DATE_SUB(CURDATE(), INTERVAL 15 DAY),
  -2.25, -0.75, 180, 1.50, 31.5,
  -2.00, -0.50, 175, 1.50, 31.5,
  'Dr. Sarah Connor', 'Annual follow-up check. Blue-light filter coating recommended for daily PC work.', 2,
  DATE_SUB(NOW(), INTERVAL 15 DAY)
),
(
  3, 2, DATE_SUB(CURDATE(), INTERVAL 10 DAY),
  -1.50, -0.25, 90, NULL, 30.5,
  -1.25, 0.00, NULL, NULL, 30.5,
  'Dr. Sarah Connor', 'Single vision distance prescription for highway driving and general use.', 2,
  DATE_SUB(NOW(), INTERVAL 10 DAY)
),
(
  4, 3, DATE_SUB(CURDATE(), INTERVAL 6 DAY),
  1.50, -1.00, 45, 2.25, 32.0,
  1.75, -0.75, 135, 2.25, 32.0,
  'Dr. Sarah Connor', 'Progressive lenses prescription with anti-scratch and anti-reflective coating.', 1,
  DATE_SUB(NOW(), INTERVAL 6 DAY)
),
(
  5, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY),
  -3.00, 0.00, NULL, NULL, 31.0,
  -2.75, 0.00, NULL, NULL, 31.0,
  'Dr. Sarah Connor', 'Soft monthly disposable contact lens refraction prescription.', 2,
  DATE_SUB(NOW(), INTERVAL 2 DAY)
);

-- ------------------------------------------------------------------------------
-- 5. Table: categories
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('frame', 'lens', 'accessory') NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categories_type` (`type`),
  KEY `idx_categories_status` (`status`),
  KEY `idx_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `type`, `status`) VALUES
(1, 'Designer Eyeglasses', 'frame', 'active'),
(2, 'Titanium Optical Frames', 'frame', 'active'),
(3, 'Sunglasses & Polarized', 'frame', 'active'),
(4, 'Single Vision Lenses', 'lens', 'active'),
(5, 'Progressive & Multifocal', 'lens', 'active'),
(6, 'Blue-Cut & Digital Protection', 'lens', 'active'),
(7, 'Lens Care & Cleaning', 'accessory', 'active'),
(8, 'Cases & Straps', 'accessory', 'active');

-- ------------------------------------------------------------------------------
-- 6. Table: products
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `product_code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `brand` VARCHAR(100) NULL,
  `model` VARCHAR(100) NULL,
  `description` TEXT NULL,
  
  -- Type Specific Attributes
  `lens_type` VARCHAR(50) NULL,
  `material` VARCHAR(50) NULL,
  `color` VARCHAR(50) NULL,
  
  -- Financial & Inventory Tracking
  `purchase_price` DECIMAL(10,2) NULL DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_quantity` INT NOT NULL DEFAULT 0,
  `low_stock_threshold` INT NOT NULL DEFAULT 5,
  
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_code` (`product_code`),
  KEY `idx_products_category_id` (`category_id`),
  KEY `idx_products_status` (`status`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_brand` (`brand`),
  CONSTRAINT `fk_products_category_id` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (
  `id`, `category_id`, `product_code`, `name`, `brand`, `model`, `description`,
  `lens_type`, `material`, `color`,
  `purchase_price`, `selling_price`, `stock_quantity`, `low_stock_threshold`, `status`
) VALUES
(
  1, 1, 'FRM-00001', 'Ray-Ban Aviator Optical Frame', 'Ray-Ban', 'RB6489',
  'Classic teardrop pilot optical frame with lightweight metal rim.',
  NULL, 'Metal Alloy', 'Gold',
  1800.00, 2800.00, 12, 3, 'active'
),
(
  2, 2, 'FRM-00002', 'Oakley Socket 5.0 Titanium', 'Oakley', 'OX3217',
  'Ultra-lightweight durable titanium rectangular prescription frame.',
  NULL, 'Titanium', 'Satin Black',
  2400.00, 3900.00, 4, 5, 'active'
),
(
  3, 4, 'LNS-00001', 'Essilor Crizal Easy Pro 1.56', 'Essilor', NULL,
  'Single vision standard refractive index lens with premium anti-reflective coating.',
  'Single Vision', 'CR-39 Hard Resin', NULL,
  1200.00, 2200.00, 25, 5, 'active'
),
(
  4, 5, 'LNS-00002', 'Zeiss Progressive Choice 1.60', 'Zeiss', NULL,
  'Digital precision progressive corridor design with wide intermediate field.',
  'Progressive', 'High-Index Plastic', NULL,
  3500.00, 6500.00, 0, 2, 'active'
),
(
  5, 6, 'LNS-00003', 'Hoya BlueControl 1.60 Digital', 'Hoya', NULL,
  'Anti-blue light reflection coating for high digital screen usage.',
  'Blue Cut', 'MR-8 Resin', NULL,
  1600.00, 2900.00, 15, 4, 'active'
),
(
  6, 7, 'ACC-00001', 'Anti-Fog Lens Cleaner Spray 60ml', 'VisionCare', NULL,
  'Nano-protective anti-fog solution spray for optical prescription lenses.',
  NULL, NULL, NULL,
  80.00, 200.00, 50, 10, 'active'
),
(
  7, 8, 'ACC-00002', 'Hard Shell Optical Clamshell Case', 'Generic', NULL,
  'Heavy-duty velvet-lined protective eyeglass storage case.',
  NULL, 'Reinforced ABS', 'Matte Navy',
  120.00, 300.00, 18, 5, 'active'
);

-- ------------------------------------------------------------------------------
-- 7. Table: orders
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_code` VARCHAR(30) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `prescription_id` INT UNSIGNED NULL DEFAULT NULL,
  `order_date` DATE NOT NULL,
  
  -- Financial Totals
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `due_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  
  -- Workflow & Inventory Control
  `status` ENUM('pending', 'confirmed', 'processing', 'ready', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  `stock_deducted` TINYINT(1) NOT NULL DEFAULT 0,
  
  `delivery_date` DATE NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_code` (`order_code`),
  KEY `idx_orders_customer_id` (`customer_id`),
  KEY `idx_orders_prescription_id` (`prescription_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_date` (`order_date`),
  KEY `idx_orders_created_by` (`created_by`),
  
  CONSTRAINT `fk_orders_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_prescription_id` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
);

-- ------------------------------------------------------------------------------
-- 8. Table: order_items
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  
  -- Snapshots of product at time of sale
  `product_name` VARCHAR(150) NOT NULL,
  `product_code` VARCHAR(30) NOT NULL,
  
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order_id` (`order_id`),
  KEY `idx_order_items_product_id` (`product_id`),
  
  CONSTRAINT `fk_order_items_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_items` (
  `id`, `order_id`, `product_id`, `product_name`, `product_code`,
  `quantity`, `unit_price`, `total_price`, `created_at`
) VALUES
(1, 1, 1, 'Ray-Ban Aviator Optical Frame', 'FRM-00001', 1, 2800.00, 2800.00, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 1, 5, 'Hoya BlueControl 1.60 Digital', 'LNS-00003', 1, 2900.00, 2900.00, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(3, 1, 6, 'Anti-Fog Lens Cleaner Spray 60ml', 'ACC-00001', 1, 200.00, 200.00, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(4, 2, 2, 'Oakley Socket 5.0 Titanium', 'FRM-00002', 1, 3900.00, 3900.00, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(5, 2, 3, 'Essilor Crizal Easy Pro 1.56', 'LNS-00001', 1, 2200.00, 2200.00, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(6, 2, 7, 'Hard Shell Optical Clamshell Case', 'ACC-00002', 1, 300.00, 300.00, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(7, 3, 1, 'Ray-Ban Aviator Optical Frame', 'FRM-00001', 1, 2800.00, 2800.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(8, 3, 3, 'Essilor Crizal Easy Pro 1.56', 'LNS-00001', 1, 2200.00, 2200.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(9, 4, 6, 'Anti-Fog Lens Cleaner Spray 60ml', 'ACC-00001', 2, 200.00, 400.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(10, 4, 7, 'Hard Shell Optical Clamshell Case', 'ACC-00002', 1, 300.00, 300.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(11, 5, 2, 'Oakley Socket 5.0 Titanium', 'FRM-00002', 1, 3900.00, 3900.00, NOW());

-- ------------------------------------------------------------------------------
-- 9. Table: payments
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('Cash', 'Card', 'Mobile Banking', 'Bank Transfer', 'Other') NOT NULL DEFAULT 'Cash',
  `reference` VARCHAR(100) NULL DEFAULT NULL,
  `notes` TEXT NULL,
  `received_by` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_payments_order_id` (`order_id`),
  KEY `idx_payments_date` (`payment_date`),
  KEY `idx_payments_received_by` (`received_by`),
  
  CONSTRAINT `fk_payments_order_id` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` (
  `id`, `order_id`, `payment_date`, `amount`, `payment_method`,
  `reference`, `notes`, `received_by`, `created_at`
) VALUES
(1, 1, DATE_SUB(CURDATE(), INTERVAL 12 DAY), 3000.00, 'Card', 'TXN-98412', 'Initial 50%+ advance payment received via POS card machine.', 3, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(2, 1, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 2500.00, 'Cash', 'RCP-00192', 'Final settlement upon dispensing and fitting delivery.', 3, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(3, 2, DATE_SUB(CURDATE(), INTERVAL 8 DAY), 3000.00, 'Mobile Banking', 'BKASH-87129', 'Advance payment received via bKash merchant wallet.', 3, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(4, 3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 2000.00, 'Card', 'POS-77182', 'Card payment at clinical consultation.', 2, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(5, 4, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 700.00, 'Cash', 'RCP-00201', 'Direct cash payment for optical accessories at counter.', 3, DATE_SUB(NOW(), INTERVAL 2 DAY));

SET FOREIGN_KEY_CHECKS = 1;
