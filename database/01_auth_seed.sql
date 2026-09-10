-- ==============================================================================
-- Optical Shop Management CMS (optical-mgt)
-- 01_auth_seed.sql - Default Seed Data for Roles and Initial Administrator
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- Seed Roles
-- ------------------------------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `display_name`, `description`) VALUES
(1, 'admin', 'Administrator', 'Full system access, user management and system settings'),
(2, 'optician', 'Optician', 'Clinical eye testing, prescription entry and dispensing'),
(3, 'sales_staff', 'Sales Staff', 'Customer management, product sales and billing')
ON DUPLICATE KEY UPDATE 
  `display_name` = VALUES(`display_name`),
  `description` = VALUES(`description`);

-- ------------------------------------------------------------------------------
-- Seed Default Users
-- Default Passwords:
-- admin       => Admin@123
-- optician    => Optician@123
-- sales       => Sales@123
-- ------------------------------------------------------------------------------
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
)
ON DUPLICATE KEY UPDATE 
  `name` = VALUES(`name`),
  `role_id` = VALUES(`role_id`),
  `email` = VALUES(`email`),
  `status` = VALUES(`status`);
