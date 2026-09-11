<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * WordPress-Style One-Click Installer
 */

define('IN_INSTALLER', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

initSession();

// --- 1. Installation Lock Verification ---
if (isInstalled()) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installation Locked — VisionCare Optical CMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/css/installer.css">
    </head>
    <body>
        <div class="installer-container">
            <div class="installer-logo">
                <div class="logo-icon"><i class="bi bi-shield-lock-fill"></i></div>
                <h3 class="fw-bold mb-0 text-dark">VisionCare Optical CMS</h3>
            </div>
            <div class="installer-card">
                <div class="installer-header bg-danger">
                    <h5 class="fw-bold mb-1 text-white"><i class="bi bi-lock-fill me-2"></i>Installation Completed &amp; Locked</h5>
                    <p class="small text-white-50 mb-0">The application is already installed and protected against unauthorized reinstallation.</p>
                </div>
                <div class="installer-body text-center py-5">
                    <i class="bi bi-shield-check text-success fs-1 mb-3 d-block"></i>
                    <h5 class="fw-bold text-dark mb-2">Application is Live &amp; Ready</h5>
                    <p class="text-muted small mx-auto mb-4" style="max-width: 480px;">
                        To protect your database, patient records, and optical inventory, the installer has been permanently locked. Direct access or attempts to reinstall are prohibited.
                    </p>
                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-primary px-4">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Go to Login
                    </a>
                </div>
                <div class="installer-footer justify-content-center">
                    <span class="text-muted small">Need to reinstall? Follow the safe reset instructions in <code>README.md</code>.</span>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- 2. Installer Workflow State & Step Handler ---
$currentStep = (int) ($_GET['step'] ?? 1);
if ($currentStep < 1 || $currentStep > 6) {
    $currentStep = 1;
}

// Error & Success Messages
$errorMsg   = '';
$successMsg = '';
$warningMsg = '';

if (!isset($_SESSION['installer'])) {
    $_SESSION['installer'] = [
        'db_host'      => 'localhost',
        'db_port'      => '3306',
        'db_name'      => 'optical_mgt',
        'db_user'      => 'root',
        'db_pass'      => '',
        'db_connected' => false,
        'sql_source'   => 'default',
        'custom_sql'   => '',
        'admin_name'   => 'System Administrator',
        'admin_user'   => 'admin',
        'admin_email'  => 'admin@opticalmgt.com',
    ];
}

// --- 3. Step Form Processors (POST Actions) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $errorMsg = 'Security validation token expired. Please try again.';
    } else {
        // ACTION: STEP 2 - Test Database Connection
        if ($action === 'test_db') {
            $host = trim($_POST['db_host'] ?? 'localhost');
            $port = trim($_POST['db_port'] ?? '3306');
            $name = trim($_POST['db_name'] ?? '');
            $user = trim($_POST['db_user'] ?? 'root');
            $pass = (string) ($_POST['db_pass'] ?? '');

            if (empty($host) || empty($name) || empty($user)) {
                $errorMsg = 'Database Host, Database Name, and Username are required.';
            } else {
                try {
                    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
                    $testPdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 5
                    ]);

                    // Check for existing tables
                    $tablesStmt = $testPdo->query("SHOW TABLES");
                    $existingTables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

                    $_SESSION['installer']['db_host']      = $host;
                    $_SESSION['installer']['db_port']      = $port;
                    $_SESSION['installer']['db_name']      = $name;
                    $_SESSION['installer']['db_user']      = $user;
                    $_SESSION['installer']['db_pass']      = $pass;
                    $_SESSION['installer']['db_connected'] = true;
                    $_SESSION['installer']['table_count']  = count($existingTables);

                    redirect('install/index.php?step=3');
                } catch (PDOException $e) {
                    $errorMsg = 'Could not connect to the MySQL database. Please verify your host, port, database name, username, and password.';
                    $_SESSION['installer']['db_connected'] = false;
                }
            }
        }

        // ACTION: STEP 3 - Choose SQL Package
        elseif ($action === 'select_sql') {
            $sqlSource = trim($_POST['sql_source'] ?? 'default');
            $_SESSION['installer']['sql_source'] = $sqlSource;

            if ($sqlSource === 'upload') {
                if (!isset($_FILES['custom_sql_file']) || $_FILES['custom_sql_file']['error'] !== UPLOAD_ERR_OK) {
                    $errorMsg = 'Please select a valid .sql file to upload.';
                } else {
                    $file = $_FILES['custom_sql_file'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if ($ext !== 'sql') {
                        $errorMsg = 'Invalid file type. Only .sql database dump files are accepted.';
                    } elseif ($file['size'] > 15 * 1024 * 1024) {
                        $errorMsg = 'SQL file is too large (maximum 15MB allowed).';
                    } else {
                        $tempDir = ROOT_PATH . '/config';
                        $tempDest = $tempDir . '/temp_import_' . time() . '.sql';
                        if (move_uploaded_file($file['tmp_name'], $tempDest)) {
                            $_SESSION['installer']['custom_sql'] = $tempDest;
                            redirect('install/index.php?step=4');
                        } else {
                            $errorMsg = 'Failed to process the uploaded SQL file. Please check folder write permissions.';
                        }
                    }
                }
            } else {
                // Default SQL
                $defaultSql = ROOT_PATH . '/database/install.sql';
                if (!file_exists($defaultSql) || !is_readable($defaultSql)) {
                    $errorMsg = 'Default database package (database/install.sql) was not found or is unreadable.';
                } else {
                    $_SESSION['installer']['custom_sql'] = '';
                    redirect('install/index.php?step=4');
                }
            }
        }

        // ACTION: STEP 4 - Setup Admin Account
        elseif ($action === 'setup_admin') {
            $adminName  = trim($_POST['admin_name'] ?? '');
            $adminUser  = trim($_POST['admin_user'] ?? '');
            $adminEmail = trim($_POST['admin_email'] ?? '');
            $adminPass  = (string) ($_POST['admin_pass'] ?? '');
            $adminConf  = (string) ($_POST['admin_conf'] ?? '');

            if (empty($adminName)) {
                $errorMsg = 'Administrator Full Name is required.';
            } elseif (empty($adminUser) || !preg_match('/^[a-zA-Z0-9_\-\.]{3,30}$/', $adminUser)) {
                $errorMsg = 'Administrator Username must be between 3 and 30 alphanumeric characters.';
            } elseif (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $errorMsg = 'Please enter a valid Administrator Email address.';
            } elseif (strlen($adminPass) < 6) {
                $errorMsg = 'Administrator Password must be at least 6 characters long.';
            } elseif ($adminPass !== $adminConf) {
                $errorMsg = 'Password and Confirmation do not match.';
            } else {
                $_SESSION['installer']['admin_name']  = $adminName;
                $_SESSION['installer']['admin_user']  = $adminUser;
                $_SESSION['installer']['admin_email'] = $adminEmail;
                $_SESSION['installer']['admin_pass']  = $adminPass;

                redirect('install/index.php?step=5');
            }
        }

        // ACTION: STEP 5 - Execute Complete Installation
        elseif ($action === 'execute_installation') {
            $dbHost = $_SESSION['installer']['db_host'] ?? 'localhost';
            $dbPort = $_SESSION['installer']['db_port'] ?? '3306';
            $dbName = $_SESSION['installer']['db_name'] ?? 'optical_mgt';
            $dbUser = $_SESSION['installer']['db_user'] ?? 'root';
            $dbPass = $_SESSION['installer']['db_pass'] ?? '';

            $adminName = $_SESSION['installer']['admin_name'] ?? 'System Administrator';
            $adminUser = $_SESSION['installer']['admin_user'] ?? 'admin';
            $adminEmail= $_SESSION['installer']['admin_email'] ?? 'admin@opticalmgt.com';
            $adminPass = $_SESSION['installer']['admin_pass'] ?? 'Admin@123';

            $sqlFile = !empty($_SESSION['installer']['custom_sql']) && file_exists($_SESSION['installer']['custom_sql'])
                ? $_SESSION['installer']['custom_sql']
                : ROOT_PATH . '/database/install.sql';

            if (!file_exists($sqlFile) || !is_readable($sqlFile)) {
                $errorMsg = 'Installation SQL package is missing or unreadable.';
            } else {
                try {
                    // 1. Establish PDO Connection
                    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false
                    ]);

                    // 2. Read and Parse SQL Statements Safely
                    $sqlContent = file_get_contents($sqlFile);
                    
                    // Split SQL by semicolon while ignoring semicolons inside strings
                    $statements = parseSqlStatements($sqlContent);

                    // Execute statements
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    foreach ($statements as $stmtSql) {
                        $stmtSql = trim($stmtSql);
                        if (!empty($stmtSql)) {
                            $pdo->exec($stmtSql);
                        }
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

                    // 3. Create or Update Administrator Account
                    $hashedPassword = password_hash($adminPass, PASSWORD_BCRYPT);
                    $adminStmt = $pdo->prepare("
                        INSERT INTO `users` (`id`, `role_id`, `name`, `username`, `email`, `phone`, `password`, `status`, `created_at`)
                        VALUES (1, 1, :name, :username, :email, '', :password, 'active', NOW())
                        ON DUPLICATE KEY UPDATE
                            `name` = VALUES(`name`),
                            `username` = VALUES(`username`),
                            `email` = VALUES(`email`),
                            `password` = VALUES(`password`),
                            `role_id` = 1,
                            `status` = 'active'
                    ");
                    $adminStmt->execute([
                        ':name'     => $adminName,
                        ':username' => $adminUser,
                        ':email'    => $adminEmail,
                        ':password' => $hashedPassword
                    ]);

                    // 4. Generate and Write config/database.php
                    $configContent = generateDatabaseConfigFile($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
                    $configFile = ROOT_PATH . '/config/database.php';
                    
                    if (@file_put_contents($configFile, $configContent) === false) {
                        throw new Exception('Could not write configuration to config/database.php. Please check folder write permissions.');
                    }

                    // 5. Clean up temporary uploaded SQL if applicable
                    if (!empty($_SESSION['installer']['custom_sql']) && file_exists($_SESSION['installer']['custom_sql'])) {
                        @unlink($_SESSION['installer']['custom_sql']);
                    }

                    // 6. Write Installation Lock File config/installed.php
                    $lockContent = "<?php\n/**\n * VisionCare Optical Shop Management CMS\n * Installation Lock File\n * Generated on " . date('Y-m-d H:i:s') . "\n */\n\nreturn [\n    'installed'    => true,\n    'installed_at' => '" . date('Y-m-d H:i:s') . "',\n    'app_version'  => '" . APP_VERSION . "'\n];\n";
                    $lockFile = ROOT_PATH . '/config/installed.php';
                    
                    if (@file_put_contents($lockFile, $lockContent) === false) {
                        throw new Exception('Could not write installation lock file config/installed.php.');
                    }

                    // Reset installer session
                    unset($_SESSION['installer']);

                    // Redirect to final success step
                    redirect('install/index.php?step=6');
                } catch (Exception $e) {
                    $errorMsg = 'Installation Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// --- 4. Helper Function: Robust SQL Parser ---
function parseSqlStatements(string $sql): array {
    $tokens = [];
    $length = strlen($sql);
    $current = '';
    $inString = false;
    $stringChar = '';
    $inComment = false;
    $commentType = '';

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

        // Handle string literals
        if ($inString) {
            $current .= $char;
            if ($char === '\\') {
                // Escape next character
                if ($i + 1 < $length) {
                    $i++;
                    $current .= $sql[$i];
                }
            } elseif ($char === $stringChar) {
                $inString = false;
            }
            continue;
        }

        // Handle comments
        if ($inComment) {
            if ($commentType === 'line' && ($char === "\n" || $char === "\r")) {
                $inComment = false;
            } elseif ($commentType === 'block' && $char === '*' && $next === '/') {
                $inComment = false;
                $i++; // skip /
            }
            continue;
        }

        // Check for comment start
        if ($char === '-' && $next === '-') {
            $inComment = true;
            $commentType = 'line';
            $i++;
            continue;
        }
        if ($char === '#') {
            $inComment = true;
            $commentType = 'line';
            continue;
        }
        if ($char === '/' && $next === '*') {
            $inComment = true;
            $commentType = 'block';
            $i++;
            continue;
        }

        // Check for string start
        if ($char === "'" || $char === '"' || $char === '`') {
            $inString = true;
            $stringChar = $char;
            $current .= $char;
            continue;
        }

        // Check for statement delimiter
        if ($char === ';') {
            $trimmed = trim($current);
            if (!empty($trimmed)) {
                $tokens[] = $trimmed;
            }
            $current = '';
            continue;
        }

        $current .= $char;
    }

    $trimmed = trim($current);
    if (!empty($trimmed)) {
        $tokens[] = $trimmed;
    }

    return $tokens;
}

// --- 5. Helper Function: Generate config/database.php ---
function generateDatabaseConfigFile(string $host, string $port, string $name, string $user, string $pass): string {
    $escapedHost = addcslashes($host, "'\\");
    $escapedPort = addcslashes($port, "'\\");
    $escapedName = addcslashes($name, "'\\");
    $escapedUser = addcslashes($user, "'\\");
    $escapedPass = addcslashes($pass, "'\\");

    return "<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Database Connection using PDO
 * Generated by VisionCare Installer on " . date('Y-m-d H:i:s') . "
 */

require_once __DIR__ . '/config.php';

// Database Credentials
define('DB_HOST', getenv('DB_HOST') ?: '{$escapedHost}');
define('DB_PORT', getenv('DB_PORT') ?: '{$escapedPort}');
define('DB_NAME', getenv('DB_NAME') ?: '{$escapedName}');
define('DB_USER', getenv('DB_USER') ?: '{$escapedUser}');
define('DB_PASS', getenv('DB_PASS') ?: '{$escapedPass}');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get or create singleton PDO Database Connection
 *
 * @return PDO
 * @throws PDOException
 */
function getDbConnection(): PDO {
    static \$pdo = null;

    if (\$pdo === null) {
        \$dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        \$options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => \"SET NAMES \" . DB_CHARSET . \" COLLATE utf8mb4_unicode_ci\"
        ];

        try {
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            error_log('Database Connection Error: ' . \$e->getMessage());
            die('Database Connection Failed. Please verify your database configuration and ensure MySQL is running.');
        }
    }

    return \$pdo;
}
";
}

// --- 6. Step 1: Requirements Check Evaluation ---
$reqs = [
    'php' => [
        'name'     => 'PHP Version (>= 8.0.0)',
        'current'  => PHP_VERSION,
        'required' => '>= 8.0.0',
        'passed'   => version_compare(PHP_VERSION, '8.0.0', '>=')
    ],
    'pdo' => [
        'name'     => 'PDO Extension',
        'current'  => extension_loaded('pdo') ? 'Enabled' : 'Missing',
        'required' => 'Enabled',
        'passed'   => extension_loaded('pdo')
    ],
    'pdo_mysql' => [
        'name'     => 'PDO MySQL Driver',
        'current'  => extension_loaded('pdo_mysql') ? 'Enabled' : 'Missing',
        'required' => 'Enabled',
        'passed'   => extension_loaded('pdo_mysql')
    ],
    'session' => [
        'name'     => 'Session Support',
        'current'  => function_exists('session_start') ? 'Enabled' : 'Disabled',
        'required' => 'Enabled',
        'passed'   => function_exists('session_start')
    ],
    'config_writable' => [
        'name'     => 'Config Directory Writable (config/)',
        'current'  => is_writable(ROOT_PATH . '/config') ? 'Writable' : 'Not Writable',
        'required' => 'Writable',
        'passed'   => is_writable(ROOT_PATH . '/config')
    ],
    'uploads_writable' => [
        'name'     => 'Uploads Directory Writable (uploads/)',
        'current'  => is_writable(ROOT_PATH . '/uploads') || is_writable(ROOT_PATH . '/uploads/avatars') ? 'Writable' : 'Not Writable',
        'required' => 'Writable',
        'passed'   => is_writable(ROOT_PATH . '/uploads') || is_writable(ROOT_PATH . '/uploads/avatars')
    ],
    'default_sql' => [
        'name'     => 'Default SQL Package (database/install.sql)',
        'current'  => file_exists(ROOT_PATH . '/database/install.sql') ? 'Available' : 'Missing',
        'required' => 'Available',
        'passed'   => file_exists(ROOT_PATH . '/database/install.sql')
    ],
];

$allReqsPassed = true;
foreach ($reqs as $r) {
    if (!$r['passed']) {
        $allReqsPassed = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionCare Optical CMS — Quick Installer</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/installer.css">
</head>
<body>

<div class="installer-container">
    <!-- Brand Logo -->
    <div class="installer-logo">
        <div class="logo-icon"><i class="bi bi-eyeglasses"></i></div>
        <div>
            <h3 class="fw-bold mb-0 text-dark">VisionCare Optical CMS</h3>
            <span class="text-muted small">One-Click Production Installation Wizard</span>
        </div>
    </div>

    <!-- Main Card -->
    <div class="installer-card">
        <!-- Header -->
        <div class="installer-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-1 text-white">System Setup &amp; Deployment</h5>
                    <p class="small text-white-50 mb-0">Follow the steps below to configure your database and administrator account.</p>
                </div>
                <span class="badge bg-primary px-3 py-2">Step <?= $currentStep ?> of 6</span>
            </div>
        </div>

        <div class="installer-body">
            <!-- Progress Tracker -->
            <div class="step-progress">
                <div class="step-item <?= $currentStep >= 1 ? ($currentStep > 1 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle"><?= $currentStep > 1 ? '<i class="bi bi-check-lg"></i>' : '1' ?></div>
                    <div class="step-label">Requirements</div>
                </div>
                <div class="step-item <?= $currentStep >= 2 ? ($currentStep > 2 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle"><?= $currentStep > 2 ? '<i class="bi bi-check-lg"></i>' : '2' ?></div>
                    <div class="step-label">Database</div>
                </div>
                <div class="step-item <?= $currentStep >= 3 ? ($currentStep > 3 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle"><?= $currentStep > 3 ? '<i class="bi bi-check-lg"></i>' : '3' ?></div>
                    <div class="step-label">SQL Package</div>
                </div>
                <div class="step-item <?= $currentStep >= 4 ? ($currentStep > 4 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle"><?= $currentStep > 4 ? '<i class="bi bi-check-lg"></i>' : '4' ?></div>
                    <div class="step-label">Admin Account</div>
                </div>
                <div class="step-item <?= $currentStep >= 5 ? ($currentStep > 5 ? 'completed' : 'active') : '' ?>">
                    <div class="step-circle"><?= $currentStep > 5 ? '<i class="bi bi-check-lg"></i>' : '5' ?></div>
                    <div class="step-label">Install</div>
                </div>
                <div class="step-item <?= $currentStep === 6 ? 'completed' : '' ?>">
                    <div class="step-circle"><?= $currentStep === 6 ? '<i class="bi bi-check-lg"></i>' : '6' ?></div>
                    <div class="step-label">Complete</div>
                </div>
            </div>

            <!-- Global Error / Warning Alerts -->
            <?php if (!empty($errorMsg)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i>
                    <div><?= e($errorMsg) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($warningMsg)): ?>
                <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill fs-5 me-2"></i>
                    <div><?= e($warningMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- ============================================================= -->
            <!-- STEP 1: SERVER REQUIREMENTS CHECK -->
            <!-- ============================================================= -->
            <?php if ($currentStep === 1): ?>
                <h5 class="fw-bold text-dark mb-2">Step 1: System Requirements Verification</h5>
                <p class="text-muted small mb-4">We are checking whether your hosting environment meets the prerequisites to run VisionCare Optical CMS.</p>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered req-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Component / Permission</th>
                                <th>Required</th>
                                <th>Detected</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reqs as $k => $r): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= e($r['name']) ?></td>
                                    <td class="text-muted"><?= e($r['required']) ?></td>
                                    <td><?= e($r['current']) ?></td>
                                    <td class="text-center">
                                        <?php if ($r['passed']): ?>
                                            <span class="badge-pass"><i class="bi bi-check-circle-fill me-1"></i> Passed</span>
                                        <?php else: ?>
                                            <span class="badge-fail"><i class="bi bi-x-circle-fill me-1"></i> Failed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($allReqsPassed): ?>
                    <div class="alert alert-success d-flex align-items-center mb-0">
                        <i class="bi bi-check-circle-fill fs-5 me-2"></i>
                        <div><strong>Excellent!</strong> Your server meets all requirements. Click continue to configure your MySQL database.</div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger d-flex align-items-center mb-0">
                        <i class="bi bi-x-circle-fill fs-5 me-2"></i>
                        <div><strong>Attention:</strong> One or more critical requirements failed. Please adjust your PHP settings or folder permissions to proceed.</div>
                    </div>
                <?php endif; ?>

            <!-- ============================================================= -->
            <!-- STEP 2: DATABASE CONFIGURATION -->
            <!-- ============================================================= -->
            <?php elseif ($currentStep === 2): ?>
                <h5 class="fw-bold text-dark mb-2">Step 2: MySQL Database Connection</h5>
                <p class="text-muted small mb-4">
                    Please create an empty database in phpMyAdmin / cPanel, then enter its connection credentials below.
                </p>

                <form method="POST" action="index.php?step=2" class="installer-form">
                    <input type="hidden" name="action" value="test_db">
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Database Host <span class="text-danger">*</span></label>
                            <input type="text" name="db_host" class="form-control" required value="<?= e($_SESSION['installer']['db_host']) ?>" placeholder="localhost">
                            <small class="text-muted">Usually <code>localhost</code> or <code>127.0.0.1</code>.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Port</label>
                            <input type="text" name="db_port" class="form-control" value="<?= e($_SESSION['installer']['db_port']) ?>" placeholder="3306">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Database Name <span class="text-danger">*</span></label>
                            <input type="text" name="db_name" class="form-control" required value="<?= e($_SESSION['installer']['db_name']) ?>" placeholder="optical_mgt">
                            <small class="text-muted">The name of the database created for this CMS.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Database Username <span class="text-danger">*</span></label>
                            <input type="text" name="db_user" class="form-control" required value="<?= e($_SESSION['installer']['db_user']) ?>" placeholder="root">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Database Password</label>
                            <div class="input-group">
                                <input type="password" name="db_pass" id="dbPassInput" class="form-control" value="<?= e($_SESSION['installer']['db_pass']) ?>" placeholder="Leave blank if none">
                                <button type="button" class="btn btn-outline-secondary toggle-password-btn" data-target="dbPassInput">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="index.php?step=1" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            Test Connection &amp; Continue <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

            <!-- ============================================================= -->
            <!-- STEP 3: SQL PACKAGE SELECTION -->
            <!-- ============================================================= -->
            <?php elseif ($currentStep === 3): ?>
                <h5 class="fw-bold text-dark mb-2">Step 3: Database Package &amp; Seed Data</h5>
                <p class="text-muted small mb-4">Choose whether to deploy the included default database package or upload your own custom SQL dump.</p>

                <?php if (!empty($_SESSION['installer']['table_count']) && $_SESSION['installer']['table_count'] > 0): ?>
                    <div class="alert alert-warning mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>Notice:</strong> We detected <strong><?= (int)$_SESSION['installer']['table_count'] ?></strong> existing tables in database <code><?= e($_SESSION['installer']['db_name']) ?></code>. The installation package will overwrite/recreate core application tables.
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php?step=3" enctype="multipart/form-data" class="installer-form">
                    <input type="hidden" name="action" value="select_sql">
                    <?= csrfField() ?>

                    <div class="row g-3 mb-4">
                        <!-- Option 1: Default SQL Package -->
                        <div class="col-md-6">
                            <label class="w-100 h-100" for="sourceDefault">
                                <div class="source-option-card selected h-100 d-flex flex-column justify-content-between" id="cardDefault">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <input type="radio" name="sql_source" id="sourceDefault" value="default" class="form-check-input mt-0" checked>
                                            <h6 class="fw-bold text-dark mb-0">Use Default SQL Package</h6>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            Recommended for fresh installations. Installs complete database structure, sample categories, optical products, and demo records.
                                        </p>
                                    </div>
                                    <span class="badge bg-light text-primary border">database/install.sql (Detected)</span>
                                </div>
                            </label>
                        </div>

                        <!-- Option 2: Upload Custom SQL -->
                        <div class="col-md-6">
                            <label class="w-100 h-100" for="sourceUpload">
                                <div class="source-option-card h-100 d-flex flex-column justify-content-between" id="cardUpload">
                                    <div>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <input type="radio" name="sql_source" id="sourceUpload" value="upload" class="form-check-input mt-0">
                                            <h6 class="fw-bold text-dark mb-0">Upload Custom SQL File</h6>
                                        </div>
                                        <p class="text-muted small mb-2">
                                            Upload an existing <code>.sql</code> backup or custom schema file (maximum 15MB).
                                        </p>
                                    </div>
                                    <span class="badge bg-light text-secondary border">Custom .sql upload</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Custom Upload File Input (Hidden by default) -->
                    <div id="customUploadContainer" style="display: none;" class="mb-4 p-3 bg-light rounded border">
                        <label class="form-label">Select .sql file to upload <span class="text-danger">*</span></label>
                        <input type="file" name="custom_sql_file" class="form-control" accept=".sql">
                        <small class="text-muted">The file will be securely executed and removed immediately after installation.</small>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="index.php?step=2" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            Continue <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

            <!-- ============================================================= -->
            <!-- STEP 4: ADMINISTRATOR ACCOUNT SETUP -->
            <!-- ============================================================= -->
            <?php elseif ($currentStep === 4): ?>
                <h5 class="fw-bold text-dark mb-2">Step 4: Administrator Account Setup</h5>
                <p class="text-muted small mb-4">Set up your super-administrator credentials. You will use these to log in to the Optical Shop CMS.</p>

                <form method="POST" action="index.php?step=4" class="installer-form">
                    <input type="hidden" name="action" value="setup_admin">
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Administrator Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="admin_name" class="form-control" required value="<?= e($_SESSION['installer']['admin_name']) ?>" placeholder="e.g. John Doe">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="admin_user" class="form-control" required value="<?= e($_SESSION['installer']['admin_user']) ?>" placeholder="admin">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Administrator Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="admin_email" class="form-control" required value="<?= e($_SESSION['installer']['admin_email']) ?>" placeholder="admin@example.com">
                            <small class="text-muted">Used for login identification and administrative notifications.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="admin_pass" id="adminPassInput" class="form-control" required placeholder="Minimum 6 characters">
                                <button type="button" class="btn btn-outline-secondary toggle-password-btn" data-target="adminPassInput">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="admin_conf" id="adminConfInput" class="form-control" required placeholder="Re-enter password">
                                <button type="button" class="btn btn-outline-secondary toggle-password-btn" data-target="adminConfInput">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="index.php?step=3" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            Review &amp; Ready to Install <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </form>

            <!-- ============================================================= -->
            <!-- STEP 5: PRE-INSTALLATION REVIEW & EXECUTION -->
            <!-- ============================================================= -->
            <?php elseif ($currentStep === 5): ?>
                <h5 class="fw-bold text-dark mb-2">Step 5: Review &amp; Execute Installation</h5>
                <p class="text-muted small mb-4">Please confirm your configuration summary. Clicking "Install Now" will build the database and lock the installer.</p>

                <div class="card bg-light border p-3 mb-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-sliders me-1"></i> Deployment Summary</h6>
                    <div class="row g-2 small">
                        <div class="col-sm-4 text-muted">Database Host:</div>
                        <div class="col-sm-8 fw-semibold text-dark"><?= e($_SESSION['installer']['db_host']) ?>:<?= e($_SESSION['installer']['db_port']) ?></div>

                        <div class="col-sm-4 text-muted">Database Name:</div>
                        <div class="col-sm-8 fw-semibold text-dark"><?= e($_SESSION['installer']['db_name']) ?></div>

                        <div class="col-sm-4 text-muted">Database User:</div>
                        <div class="col-sm-8 fw-semibold text-dark"><?= e($_SESSION['installer']['db_user']) ?></div>

                        <div class="col-sm-4 text-muted">SQL Source:</div>
                        <div class="col-sm-8 fw-semibold text-dark"><?= $_SESSION['installer']['sql_source'] === 'upload' ? 'Custom Uploaded SQL File' : 'Default database/install.sql Package' ?></div>

                        <div class="col-sm-4 text-muted">Administrator:</div>
                        <div class="col-sm-8 fw-semibold text-dark"><?= e($_SESSION['installer']['admin_name']) ?> (<?= e($_SESSION['installer']['admin_user']) ?>)</div>

                        <div class="col-sm-4 text-muted">Admin Email:</div>
                        <div class="col-sm-8 fw-semibold text-dark"><?= e($_SESSION['installer']['admin_email']) ?></div>
                    </div>
                </div>

                <div class="alert alert-info d-flex align-items-center mb-4">
                    <i class="bi bi-info-circle-fill fs-5 me-2"></i>
                    <div>
                        <strong>Installation Process:</strong> The installer will import all required tables, configure roles, seed sample data, create your administrator account, and generate <code>config/database.php</code>.
                    </div>
                </div>

                <form method="POST" action="index.php?step=5" class="installer-form">
                    <input type="hidden" name="action" value="execute_installation">
                    <?= csrfField() ?>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <a href="index.php?step=4" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="bi bi-rocket-takeoff-fill me-1"></i> Install VisionCare CMS Now
                        </button>
                    </div>
                </form>

            <!-- ============================================================= -->
            <!-- STEP 6: INSTALLATION SUCCESS -->
            <!-- ============================================================= -->
            <?php elseif ($currentStep === 6): ?>
                <div class="text-center py-4">
                    <div class="d-inline-flex p-3 bg-success text-white rounded-circle mb-3">
                        <i class="bi bi-check2-all fs-1"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">Installation Completed Successfully!</h3>
                    <p class="text-muted small mx-auto mb-4" style="max-width: 500px;">
                        VisionCare Optical CMS has been deployed and configured. The installation is now locked for your security.
                    </p>

                    <div class="card bg-light border p-3 text-start mx-auto mb-4" style="max-width: 520px;">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-clipboard2-check me-1"></i> Deployment Milestones</h6>
                        <ul class="list-unstyled small mb-0">
                            <li class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i> MySQL Database tables created</li>
                            <li class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i> System roles &amp; optical categories initialized</li>
                            <li class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i> Administrator account created &amp; password encrypted</li>
                            <li class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i> Application configuration saved (<code>config/database.php</code>)</li>
                            <li class="text-success"><i class="bi bi-check-circle-fill me-2"></i> Installation locked (<code>config/installed.php</code>)</li>
                        </ul>
                    </div>

                    <a href="<?= BASE_URL ?>auth/login.php" class="btn btn-primary btn-lg px-5 shadow-sm">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Go to Login Page
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <?php if ($currentStep === 1): ?>
            <div class="installer-footer">
                <span class="text-muted small">VisionCare Optical CMS &bull; v<?= APP_VERSION ?></span>
                <a href="index.php?step=2" class="btn btn-primary px-4 <?= !$allReqsPassed ? 'disabled' : '' ?>">
                    Continue to Database Setup <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Copyright -->
    <div class="installer-copyright">
        &copy; <?= date('Y') ?> VisionCare Optical CMS. All rights reserved.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/installer.js"></script>

</body>
</html>
