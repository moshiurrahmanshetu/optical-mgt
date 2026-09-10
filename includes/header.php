<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Common Header Include
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash.php';

// Enforce login for all layout-wrapped pages
requireLogin();

$currentUser = currentUser();
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle); ?> &mdash; <?= e(APP_NAME); ?></title>
  
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <!-- Custom Solid Design Stylesheet (No Gradients) -->
  <link rel="stylesheet" href="<?= ASSETS_URL; ?>css/style.css?v=<?= APP_VERSION; ?>">
</head>
<body>

<div class="app-wrapper">
  <!-- Sidebar Navigation -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content Wrapper -->
  <div class="app-main">
    <!-- Top Navbar -->
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <!-- Main Container -->
    <main class="flex-grow-1 p-4">
      <!-- Flash Messages -->
      <?php displayFlash(); ?>
