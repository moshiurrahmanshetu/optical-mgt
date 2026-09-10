<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Login Page & Authentication Handler
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/flash.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$loginInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken  = $_POST['csrf_token'] ?? '';
    $loginInput = trim($_POST['login'] ?? '');
    $password   = $_POST['password'] ?? '';

    // Verify CSRF
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } elseif (empty($loginInput) || empty($password)) {
        $error = 'Please enter both your username/email and password.';
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                SELECT u.*, r.name AS role_name, r.display_name AS role_display_name
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE (u.username = :login_user OR u.email = :login_email)
                LIMIT 1
            ");
            $stmt->execute([
                'login_user'  => $loginInput,
                'login_email' => $loginInput
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account has been deactivated. Please contact an administrator.';
                } else {
                    // Successful login
                    loginUser($user);
                    setFlash('success', 'Welcome back, ' . htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') . '!');
                    redirect('index.php');
                }
            } else {
                $error = 'Invalid credentials. Please check your username/email and password.';
            }
        } catch (Exception $e) {
            error_log('Login Error: ' . $e->getMessage());
            $error = 'An unexpected system error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In &mdash; <?= e(APP_NAME); ?></title>
  
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
<body class="auth-wrapper">

  <div class="auth-card">
    <div class="auth-brand">
      <div class="auth-logo-badge">
        <i class="bi bi-eyeglasses"></i>
      </div>
      <h4 class="fw-bold text-dark m-0"><?= e(APP_NAME); ?></h4>
      <p class="text-muted small m-0 mt-1">Optical Management Portal</p>
    </div>

    <!-- Flash Messages (e.g. after logout) -->
    <?php displayFlash(); ?>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger d-flex align-items-center mb-3 py-2 px-3 small shadow-sm" role="alert">
        <i class="bi bi-exclamation-octagon-fill me-2 fs-6"></i>
        <div><?= e($error); ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL; ?>auth/login.php" novalidate>
      <?= csrfField(); ?>

      <div class="mb-3">
        <label for="login" class="form-label small fw-semibold text-dark">Username or Email</label>
        <div class="input-group">
          <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
          <input type="text" class="form-control border-start-0 ps-0" id="login" name="login" value="<?= e($loginInput); ?>" placeholder="admin or admin@opticalmgt.com" required autofocus>
        </div>
      </div>

      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label for="password" class="form-label small fw-semibold text-dark m-0">Password</label>
        </div>
        <div class="input-group">
          <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
          <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="••••••••" required>
        </div>
      </div>

      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-primary py-2 fw-medium">
          <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
        </button>
      </div>
    </form>

    <div class="text-center mt-3 pt-3 border-top">
      <small class="text-muted">Default admin login: <code>admin</code> / <code>Admin@123</code></small>
    </div>
  </div>

  <!-- Bootstrap 5 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- App JS -->
  <script src="<?= ASSETS_URL; ?>js/app.js?v=<?= APP_VERSION; ?>"></script>
</body>
</html>
