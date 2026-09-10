<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Toggle Customer Status Handler (POST Only)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    redirect('modules/customers/index.php');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    setFlash('error', 'Security session expired. Please try again.');
    redirect('modules/customers/index.php');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid customer identifier.');
    redirect('modules/customers/index.php');
}

try {
    $pdo = getDbConnection();

    // Check customer exists
    $stmt = $pdo->prepare("SELECT id, customer_code, full_name, status FROM customers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        setFlash('error', 'Customer record not found.');
        redirect('modules/customers/index.php');
    }

    $newStatus = ($customer['status'] === 'active') ? 'inactive' : 'active';

    $updateStmt = $pdo->prepare("UPDATE customers SET status = :status, updated_at = NOW() WHERE id = :id");
    $updateStmt->execute([
        'status' => $newStatus,
        'id'     => $id
    ]);

    $statusLabel = ucfirst($newStatus);
    setFlash('success', 'Customer ' . e($customer['customer_code']) . ' (' . e($customer['full_name']) . ') status changed to ' . $statusLabel . '.');
} catch (Exception $e) {
    error_log('Toggle Status Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while updating customer status.');
}

// Redirect back to referring page if available
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($referer) && strpos($referer, BASE_URL) === 0) {
    header('Location: ' . $referer);
    exit;
}

redirect('modules/customers/index.php');
