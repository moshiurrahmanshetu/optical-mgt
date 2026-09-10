<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Flash Messaging Service
 */

require_once __DIR__ . '/../config/config.php';

initSession();

/**
 * Set a flash notification message in the session
 *
 * @param string $type success | error | danger | warning | info
 * @param string $message
 * @return void
 */
function setFlash(string $type, string $message): void {
    initSession();
    
    // Normalize danger to error
    $type = ($type === 'danger') ? 'error' : $type;
    
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Check if there are any flash messages stored
 *
 * @return bool
 */
function hasFlash(): bool {
    initSession();
    return !empty($_SESSION['flash_messages']);
}

/**
 * Retrieve and clear all stored flash messages
 *
 * @return array
 */
function getFlash(): array {
    initSession();
    if (isset($_SESSION['flash_messages'])) {
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
    return [];
}

/**
 * Render all flash messages as Bootstrap 5 alerts with clean solid styling
 *
 * @return void
 */
function displayFlash(): void {
    $messages = getFlash();
    if (empty($messages)) {
        return;
    }

    $iconMap = [
        'success' => 'bi-check-circle-fill',
        'error'   => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill'
    ];

    $classMap = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        'info'    => 'alert-info'
    ];

    foreach ($messages as $item) {
        $type = $item['type'];
        $icon = $iconMap[$type] ?? 'bi-info-circle-fill';
        $alertClass = $classMap[$type] ?? 'alert-info';
        $message = htmlspecialchars($item['message'], ENT_QUOTES, 'UTF-8');

        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm" role="alert">';
        echo '  <i class="bi ' . $icon . ' flex-shrink-0 me-2 fs-5"></i>';
        echo '  <div class="flex-grow-1">' . $message . '</div>';
        echo '  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}
