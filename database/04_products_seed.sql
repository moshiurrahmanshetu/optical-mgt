-- ==============================================================================
-- Optical Shop Management CMS (optical-mgt)
-- 04_products_seed.sql - Realistic Seed Data for Categories & Products
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Seed Categories
-- ------------------------------------------------------------------------------
INSERT INTO `categories` (`id`, `name`, `type`, `status`) VALUES
(1, 'Designer Eyeglasses', 'frame', 'active'),
(2, 'Titanium Optical Frames', 'frame', 'active'),
(3, 'Sunglasses & Polarized', 'frame', 'active'),
(4, 'Single Vision Lenses', 'lens', 'active'),
(5, 'Progressive & Multifocal', 'lens', 'active'),
(6, 'Blue-Cut & Digital Protection', 'lens', 'active'),
(7, 'Lens Care & Cleaning', 'accessory', 'active'),
(8, 'Cases & Straps', 'accessory', 'active')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `type` = VALUES(`type`),
  `status` = VALUES(`status`);

-- ------------------------------------------------------------------------------
-- Seed Sample Products
-- ------------------------------------------------------------------------------
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
)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `selling_price` = VALUES(`selling_price`),
  `stock_quantity` = VALUES(`stock_quantity`);
