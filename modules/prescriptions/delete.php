<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Delete Prescription Handler (POST Only)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

// Enforce Role Authorization
requireRole(['admin', 'optician']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    redirect('modules/prescriptions/index.php');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    setFlash('error', 'Security session expired. Please try again.');
    redirect('modules/prescriptions/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid prescription identifier.');
    redirect('modules/prescriptions/index.php');
}

try {
    $pdo = getDbConnection();

    // Check prescription exists
    $stmt = $pdo->prepare("
        SELECT p.id, c.full_name AS customer_name, c.customer_code
        FROM prescriptions p
        JOIN customers c ON p.customer_id = c.id
        WHERE p.id = :id
        LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $rx = $stmt->fetch();

    if (!$rx) {
        setFlash('error', 'Prescription record not found or already deleted.');
        redirect('modules/prescriptions/index.php');
    }

    // Delete prescription
    $deleteStmt = $pdo->prepare("DELETE FROM prescriptions WHERE id = :id");
    $deleteStmt->execute(['id' => $id]);

    setFlash('success', 'Prescription #' . $id . ' for ' . e($rx['customer_name']) . ' (' . e($rx['customer_code']) . ') has been deleted successfully.');
} catch (Exception $e) {
    error_log('Delete Prescription Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while deleting the prescription.');
}

// Redirect back to referring customer page if present, otherwise index
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer) && strpos($referer, BASE_URL) === 0) {
    header('Location: ' . $referer);
    exit;
}

redirect('modules/prescriptions/index.php');
