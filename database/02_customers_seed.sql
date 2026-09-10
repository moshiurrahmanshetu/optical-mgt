-- ==============================================================================
-- Optical Shop Management CMS (optical-mgt)
-- 02_customers_seed.sql - Realistic Sample Customers Seed Data
-- ==============================================================================

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
)
ON DUPLICATE KEY UPDATE
  `full_name` = VALUES(`full_name`),
  `phone` = VALUES(`phone`),
  `email` = VALUES(`email`),
  `status` = VALUES(`status`);
