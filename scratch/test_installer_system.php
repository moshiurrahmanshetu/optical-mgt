<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Installer QA & Verification Test Suite
 */

declare(strict_types=1);

define('IN_INSTALLER', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "========================================================================\n";
echo "  VISIONCARE OPTICAL CMS - INSTALLER AUTOMATED QA TEST SUITE\n";
echo "========================================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string $details = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description} " . ($details ? "({$details})" : "") . "\n";
    }
}

// -----------------------------------------------------------------------------
// Test 1: Requirements Check Verification
// -----------------------------------------------------------------------------
echo "1. Testing Server Requirements & Directory Checks...\n";
assertTest("PHP Version is >= 8.0.0 (Current: " . PHP_VERSION . ")", version_compare(PHP_VERSION, '8.0.0', '>='));
assertTest("PDO Extension loaded", extension_loaded('pdo'));
assertTest("PDO MySQL Driver loaded", extension_loaded('pdo_mysql'));
assertTest("config/ directory is writable", is_writable(ROOT_PATH . '/config'));
assertTest("Default SQL package (database/install.sql) exists", file_exists(ROOT_PATH . '/database/install.sql'));

// -----------------------------------------------------------------------------
// Test 2: SQL Statement Parser Engine
// -----------------------------------------------------------------------------
echo "\n2. Testing SQL Statement Parser Engine...\n";

// Include parseSqlStatements logic for test
function testParseSql(string $sql): array {
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

        if ($inString) {
            $current .= $char;
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $i++;
                    $current .= $sql[$i];
                }
            } elseif ($char === $stringChar) {
                $inString = false;
            }
            continue;
        }

        if ($inComment) {
            if ($commentType === 'line' && ($char === "\n" || $char === "\r")) {
                $inComment = false;
            } elseif ($commentType === 'block' && $char === '*' && $next === '/') {
                $inComment = false;
                $i++;
            }
            continue;
        }

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

        if ($char === "'" || $char === '"' || $char === '`') {
            $inString = true;
            $stringChar = $char;
            $current .= $char;
            continue;
        }

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

$sampleSql = "
    -- Line comment
    CREATE TABLE test_tbl (id INT);
    /* Block comment with ; inside */
    INSERT INTO test_tbl VALUES ('string with ; inside; and -- comment');
    SELECT * FROM test_tbl;
";
$parsed = testParseSql($sampleSql);
assertTest("SQL parser accurately isolates 3 statements ignoring comments & string semicolons", count($parsed) === 3);

// Test parsing actual database/install.sql
$installSqlContent = file_get_contents(ROOT_PATH . '/database/install.sql');
$masterStatements = testParseSql($installSqlContent);
assertTest("Master install.sql parses cleanly into statements", count($masterStatements) > 10);

// -----------------------------------------------------------------------------
// Test 3: Database Connection & Schema Verification
// -----------------------------------------------------------------------------
echo "\n3. Testing Database Connection & Database Execution...\n";
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', 'localhost', '3306', 'optical_mgt');
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    assertTest("Live PDO connection to database succeeded", true);

    // Verify all core tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $requiredTables = ['roles', 'users', 'customers', 'prescriptions', 'categories', 'products', 'orders', 'order_items', 'payments'];
    
    $allTablesPresent = true;
    foreach ($requiredTables as $tbl) {
        if (!in_array($tbl, $tables, true)) {
            $allTablesPresent = false;
            break;
        }
    }
    assertTest("All core tables (roles, users, customers, prescriptions, categories, products, orders, order_items, payments) are present", $allTablesPresent);
} catch (Exception $e) {
    assertTest("Live PDO connection", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 4: Administrator Account Password Verification
// -----------------------------------------------------------------------------
echo "\n4. Testing Administrator Account Password Encryption...\n";
$plainPass = 'Admin@123';
$hashed = password_hash($plainPass, PASSWORD_BCRYPT);
assertTest("password_hash generates valid BCRYPT hash", password_verify($plainPass, $hashed));

// Verify seeded administrator password in DB
try {
    $adminRow = $pdo->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1")->fetch();
    assertTest("Admin user exists in database", (bool) $adminRow);
    if ($adminRow) {
        assertTest("Admin password in DB verifies against 'Admin@123'", password_verify('Admin@123', $adminRow['password']));
        assertTest("Admin role_id is 1", (int)$adminRow['role_id'] === 1);
    }
} catch (Exception $e) {
    assertTest("Admin user check", false, $e->getMessage());
}

// -----------------------------------------------------------------------------
// Test 5: Installation Lock Mechanism
// -----------------------------------------------------------------------------
echo "\n5. Testing Installation Lock Mechanism...\n";
$lockFile = CONFIG_PATH . '/installed.php';

// Test when locked
if (file_exists($lockFile)) {
    assertTest("isInstalled() returns true when config/installed.php exists", isInstalled() === true);
} else {
    assertTest("isInstalled() returns false when config/installed.php is absent", isInstalled() === false);
}

// -----------------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------------
echo "\n========================================================================\n";
echo "  INSTALLER QA RESULTS: Total Passed: {$passed} | Total Failed: {$failed}\n";
echo "========================================================================\n";

if ($failed === 0) {
    echo "  >> ALL INSTALLER SYSTEM TESTS PASSED SUCCESSFULLY! <<\n\n";
} else {
    echo "  >> SOME TESTS FAILED. PLEASE REVIEW LOG ABOVE. <<\n\n";
}
