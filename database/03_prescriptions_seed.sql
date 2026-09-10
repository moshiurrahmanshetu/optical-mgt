-- ==============================================================================
-- Optical Shop Management CMS (optical-mgt)
-- 03_prescriptions_seed.sql - Realistic Sample Prescriptions Seed Data
-- ==============================================================================

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
)
ON DUPLICATE KEY UPDATE
  `customer_id` = VALUES(`customer_id`),
  `right_sph` = VALUES(`right_sph`),
  `left_sph` = VALUES(`left_sph`);
