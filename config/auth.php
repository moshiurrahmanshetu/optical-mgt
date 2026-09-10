<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Authentication & Role-Based Access Control Functions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

// Initialize session
initSession();

/**
 * Check if a user is currently authenticated
 *
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get the current authenticated user array
 *
 * @return array|null
 */
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Get the ID of the current authenticated user
 *
 * @return int|null
 */
function currentUserId(): ?int {
    return $_SESSION['user']['id'] ?? null;
}

/**
 * Get the role slug/name of current authenticated user (e.g. 'admin', 'optician', 'sales_staff')
 *
 * @return string|null
 */
function currentRole(): ?string {
    return $_SESSION['user']['role_name'] ?? null;
}

/**
 * Check if the authenticated user has one of the specified roles
 *
 * @param array|string $roles
 * @return bool
 */
function hasRole(array|string $roles): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $current = currentRole();
    if (!$current) {
        return false;
    }

    if (is_string($roles)) {
        $roles = [$roles];
    }

    return in_array($current, $roles, true);
}

/**
 * Require authentication. Redirects to login if unauthenticated.
 *
 * @return void
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        if (function_exists('setFlash')) {
            setFlash('error', 'Please log in to access this page.');
        }
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

/**
 * Alias for requireLogin()
 *
 * @return void
 */
function requireAuth(): void {
    requireLogin();
}

/**
 * Require specific role(s) to access a page.
 *
 * @param array|string $roles
 * @return void
 */
function requireRole(array|string $roles): void {
    requireLogin();

    if (!hasRole($roles)) {
        if (function_exists('setFlash')) {
            setFlash('error', 'Access denied. You do not have permission to view this page.');
        }
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

/**
 * Log in a user by saving credentials in session and updating last_login_at
 *
 * @param array $user User database record
 * @return void
 */
function loginUser(array $user): void {
    initSession();
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'                => (int) $user['id'],
        'role_id'           => (int) $user['role_id'],
        'role_name'         => $user['role_name'] ?? 'admin',
        'role_display_name' => $user['role_display_name'] ?? 'Administrator',
        'name'              => $user['name'],
        'username'          => $user['username'],
        'email'             => $user['email'],
        'phone'             => $user['phone'] ?? '',
        'avatar'            => $user['avatar'] ?? null,
        'status'            => $user['status'] ?? 'active',
        'last_login_at'     => $user['last_login_at'] ?? null,
    ];

    // Update last_login_at in database
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => (int) $user['id']]);
    } catch (Exception $e) {
        error_log('Failed to update last_login_at: ' . $e->getMessage());
    }
}

/**
 * Refresh current user's session data from the database
 *
 * @return void
 */
function refreshUserSession(): void {
    $userId = currentUserId();
    if (!$userId) {
        return;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, r.name AS role_name, r.display_name AS role_display_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user']['role_id']           = (int) $user['role_id'];
            $_SESSION['user']['role_name']         = $user['role_name'];
            $_SESSION['user']['role_display_name'] = $user['role_display_name'];
            $_SESSION['user']['name']              = $user['name'];
            $_SESSION['user']['username']          = $user['username'];
            $_SESSION['user']['email']             = $user['email'];
            $_SESSION['user']['phone']             = $user['phone'] ?? '';
            $_SESSION['user']['avatar']            = $user['avatar'] ?? null;
            $_SESSION['user']['status']            = $user['status'];
            $_SESSION['user']['last_login_at']     = $user['last_login_at'];
        }
    } catch (Exception $e) {
        error_log('Failed to refresh user session: ' . $e->getMessage());
    }
}

/**
 * Securely log out the current user
 *
 * @return void
 */
function logoutUser(): void {
    initSession();
    
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
