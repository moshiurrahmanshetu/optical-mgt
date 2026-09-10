<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Delete Customer Handler (POST Only, Admin Only)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/flash.php';

// Strict Role Authorization: Only Administrator can delete customers
requireRole('admin');

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
    $stmt = $pdo->prepare("SELECT id, customer_code, full_name FROM customers WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        setFlash('error', 'Customer record not found or already deleted.');
        redirect('modules/customers/index.php');
    }

    // Delete customer
    $deleteStmt = $pdo->prepare("DELETE FROM customers WHERE id = :id");
    $deleteStmt->execute(['id' => $id]);

    setFlash('success', 'Customer ' . e($customer['customer_code']) . ' (' . e($customer['full_name']) . ') has been deleted successfully.');
} catch (Exception $e) {
    error_log('Delete Customer Error: ' . $e->getMessage());
    setFlash('error', 'An error occurred while deleting the customer. Please try again.');
}

redirect('modules/customers/index.php');
