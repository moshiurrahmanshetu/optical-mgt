<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Reusable Helper and Security Functions
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Escape HTML output to prevent XSS attacks
 *
 * @param mixed $value
 * @return string
 */
function e($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Generate CSRF token and store in session
 *
 * @return string
 */
function generateCsrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Generate hidden HTML input field containing the CSRF token
 *
 * @return string
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Alias for csrfField()
 *
 * @return string
 */
function getCsrfField(): string {
    return csrfField();
}

/**
 * Verify submitted CSRF token
 *
 * @param string|null $token
 * @return bool
 */
function verifyCsrfToken(?string $token): bool {
    initSession();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Safe redirect to a given application path or URL
 *
 * @param string $path Relative path or absolute URL
 * @return void
 */
function redirect(string $path): void {
    if (preg_match('/^https?:\/\//i', $path)) {
        $url = $path;
    } else {
        $url = BASE_URL . ltrim($path, '/');
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Generate full URL based on BASE_URL
 *
 * @param string $path
 * @return string
 */
function baseUrl(string $path = ''): string {
    return BASE_URL . ltrim($path, '/');
}

/**
 * Alias for formatMoney
 *
 * @param float|int|string|null $amount
 * @param string $currency
 * @return string
 */
function formatCurrency($amount, string $currency = '$'): string {
    return formatMoney($amount, $currency);
}

/**
 * Get user initials from full name
 *
 * @param string $name
 * @return string
 */
function getUserInitials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    $initials = '';
    if (!empty($words[0])) {
        $initials .= mb_substr($words[0], 0, 1);
    }
    if (count($words) > 1 && !empty($words[count($words) - 1])) {
        $initials .= mb_substr($words[count($words) - 1], 0, 1);
    }
    return strtoupper($initials ?: 'U');
}

/**
 * Generate inline SVG data URI for default avatar with user initials
 *
 * @param string $name
 * @param string $bgColor Solid background color
 * @param string $textColor Text color
 * @return string Data URI
 */
function getDefaultAvatarSvg(string $name = 'User', string $bgColor = '#0f172a', string $textColor = '#f8fafc'): string {
    $initials = e(getUserInitials($name));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">'
         . '<rect width="100" height="100" fill="' . $bgColor . '" rx="50"/>'
         . '<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" '
         . 'font-family="system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif" '
         . 'font-size="38" font-weight="600" fill="' . $textColor . '">' . $initials . '</text>'
         . '</svg>';

    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

/**
 * Get avatar URL for a user, or clean SVG initials fallback if no avatar uploaded
 *
 * @param string|null $avatarFilename
 * @param string $name
 * @return string
 */
function getAvatarUrl(?string $avatarFilename, string $name = 'User'): string {
    if (!empty($avatarFilename)) {
        $filePath = UPLOAD_PATH . '/' . basename($avatarFilename);
        if (file_exists($filePath) && is_file($filePath)) {
            return UPLOAD_URL . rawurlencode(basename($avatarFilename));
        }
    }
    return getDefaultAvatarSvg($name);
}

/**
 * Securely validate and process an uploaded avatar file
 *
 * @param array $file $_FILES['avatar'] element
 * @param string|null $oldAvatar Previous avatar filename to delete upon replacement
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function handleAvatarUpload(array $file, ?string $oldAvatar = null): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => $oldAvatar, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => null, 'error' => 'File upload error code: ' . $file['error']];
    }

    // Check file size (max 2MB = 2097152 bytes)
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'filename' => null, 'error' => 'Avatar file size must not exceed 2MB.'];
    }

    // Validate Extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExtensions, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid file extension. Allowed formats: JPG, PNG, WebP.'];
    }

    // Validate MIME Type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!array_key_exists($mimeType, $allowedMimes)) {
        return ['success' => false, 'filename' => null, 'error' => 'Invalid image content type (' . $mimeType . ').'];
    }

    // Ensure upload directory exists
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    // Generate unique random filename
    $newFilename = 'avatar_' . bin2hex(random_bytes(10)) . '.' . $ext;
    $destination = UPLOAD_PATH . '/' . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'filename' => null, 'error' => 'Failed to save uploaded image. Please check directory permissions.'];
    }

    // Safely delete previous avatar file if exists
    if (!empty($oldAvatar)) {
        $oldFilePath = UPLOAD_PATH . '/' . basename($oldAvatar);
        if (file_exists($oldFilePath) && is_file($oldFilePath)) {
            @unlink($oldFilePath);
        }
    }

    return ['success' => true, 'filename' => $newFilename, 'error' => null];
}

/**
 * Generate a unique sequential customer code (e.g. CUS-00001)
 *
 * @param PDO $pdo
 * @return string
 */
function generateCustomerCode(PDO $pdo): string {
    // Find highest numerical suffix from existing codes
    $stmt = $pdo->query("
        SELECT MAX(CAST(SUBSTRING(customer_code, 5) AS UNSIGNED)) AS max_code
        FROM customers
        WHERE customer_code REGEXP '^CUS-[0-9]+$'
    ");
    $maxNum = (int) $stmt->fetchColumn();
    $nextNum = max($maxNum + 1, 1);

    // Collision-safe verification
    $checkStmt = $pdo->prepare("SELECT id FROM customers WHERE customer_code = :code LIMIT 1");
    do {
        $candidateCode = sprintf('CUS-%05d', $nextNum);
        $checkStmt->execute(['code' => $candidateCode]);
        $exists = $checkStmt->fetchColumn();
        if (!$exists) {
            return $candidateCode;
        }
        $nextNum++;
    } while (true);
}

/**
 * Format a nullable date string safely
 *
 * @param string|null $date
 * @param string $format
 * @return string
 */
function formatDate(?string $date, string $format = 'M d, Y'): string {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '<span class="text-muted">&mdash;</span>';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '<span class="text-muted">&mdash;</span>';
}

/**
 * Format optical power values (SPH, CYL, ADD) with + sign for positive values
 *
 * @param float|string|null $val
 * @return string
 */
function formatOpticalPower($val): string {
    if ($val === null || $val === '' || !is_numeric($val)) {
        return '<span class="text-muted">&mdash;</span>';
    }
    $num = (float) $val;
    if ($num > 0) {
        return '+' . number_format($num, 2);
    }
    return number_format($num, 2);
}

/**
 * Format optical cylinder axis degrees (0–180°)
 *
 * @param int|string|null $axis
 * @return string
 */
function formatAxis($axis): string {
    if ($axis === null || $axis === '' || !is_numeric($axis)) {
        return '<span class="text-muted">&mdash;</span>';
    }
    return (int) $axis . '&deg;';
}

/**
 * Format pupillary distance (PD in mm)
 *
 * @param float|string|null $pd
 * @return string
 */
function formatPd($pd): string {
    if ($pd === null || $pd === '' || !is_numeric($pd)) {
        return '<span class="text-muted">&mdash;</span>';
    }
    return number_format((float) $pd, 1) . ' mm';
}

/**
 * Generate a unique sequential product code based on product type
 * Frame => FRM-00001, Lens => LNS-00001, Accessory => ACC-00001
 *
 * @param PDO $pdo
 * @param string $type
 * @return string
 */
function generateProductCode(PDO $pdo, string $type = 'frame'): string {
    $prefixMap = [
        'frame'     => 'FRM-',
        'lens'      => 'LNS-',
        'accessory' => 'ACC-'
    ];

    $prefix = $prefixMap[$type] ?? 'FRM-';

    // Find highest numerical suffix with this prefix
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(product_code, 5) AS UNSIGNED)) AS max_code
        FROM products
        WHERE product_code LIKE :prefix_pattern
    ");
    $stmt->execute(['prefix_pattern' => $prefix . '%']);
    $maxNum = (int) $stmt->fetchColumn();
    $nextNum = max($maxNum + 1, 1);

    // Collision-safe loop
    $checkStmt = $pdo->prepare("SELECT id FROM products WHERE product_code = :code LIMIT 1");
    do {
        $candidateCode = sprintf($prefix . '%05d', $nextNum);
        $checkStmt->execute(['code' => $candidateCode]);
        $exists = $checkStmt->fetchColumn();
        if (!$exists) {
            return $candidateCode;
        }
        $nextNum++;
    } while (true);
}

/**
 * Format monetary amount with currency symbol
 *
 * @param float|int|string|null $amount
 * @param string $currency
 * @return string
 */
function formatMoney($amount, string $currency = '$'): string {
    if ($amount === null || $amount === '' || !is_numeric($amount)) {
        return '<span class="text-muted">&mdash;</span>';
    }
    return $currency . number_format((float) $amount, 2);
}

/**
 * Render stock health badge (In Stock, Low Stock, Out of Stock)
 *
 * @param int $stock
 * @param int $threshold
 * @param string $status
 * @return string HTML Badge
 */
function getStockBadge(int $stock, int $threshold = 5, string $status = 'active'): string {
    if ($status === 'inactive') {
        return '<span class="badge badge-status-inactive">Inactive</span>';
    }

    if ($stock <= 0) {
        return '<span class="badge badge-status-inactive" style="font-weight: 600;"><i class="bi bi-x-circle me-1"></i>Out of Stock (0)</span>';
    }

    if ($stock <= $threshold) {
        return '<span class="badge bg-warning text-dark border" style="font-weight: 600;"><i class="bi bi-exclamation-triangle me-1"></i>Low Stock (' . $stock . ')</span>';
    }

    return '<span class="badge badge-status-active" style="font-weight: 600;"><i class="bi bi-check-circle me-1"></i>In Stock (' . $stock . ')</span>';
}

/**
 * Generate a unique sequential order code (e.g. ORD-00001)
 *
 * @param PDO $pdo
 * @return string
 */
function generateOrderCode(PDO $pdo): string {
    $stmt = $pdo->query("
        SELECT MAX(CAST(SUBSTRING(order_code, 5) AS UNSIGNED)) AS max_code
        FROM orders
        WHERE order_code REGEXP '^ORD-[0-9]+$'
    ");
    $maxNum = (int) $stmt->fetchColumn();
    $nextNum = max($maxNum + 1, 1);

    // Collision-safe verification
    $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE order_code = :code LIMIT 1");
    do {
        $candidateCode = sprintf('ORD-%05d', $nextNum);
        $checkStmt->execute(['code' => $candidateCode]);
        $exists = $checkStmt->fetchColumn();
        if (!$exists) {
            return $candidateCode;
        }
        $nextNum++;
    } while (true);
}

/**
 * Render order status badge using clean solid Bootstrap colors
 *
 * @param string $status
 * @return string HTML Badge
 */
function getOrderStatusBadge(string $status): string {
    $status = strtolower(trim($status));
    $map = [
        'pending'    => ['bg' => 'bg-secondary',          'text' => 'text-white', 'icon' => 'bi-clock-history',      'label' => 'Pending'],
        'confirmed'  => ['bg' => 'bg-primary',            'text' => 'text-white', 'icon' => 'bi-check2-circle',      'label' => 'Confirmed'],
        'processing' => ['bg' => 'bg-info text-dark',     'text' => '',           'icon' => 'bi-gear-wide-connected', 'label' => 'Processing'],
        'ready'      => ['bg' => 'bg-warning text-dark',  'text' => '',           'icon' => 'bi-box-seam',            'label' => 'Ready'],
        'delivered'  => ['bg' => 'bg-success',            'text' => 'text-white', 'icon' => 'bi-check-all',          'label' => 'Delivered'],
        'cancelled'  => ['bg' => 'bg-danger',             'text' => 'text-white', 'icon' => 'bi-x-circle',           'label' => 'Cancelled'],
    ];

    $cfg = $map[$status] ?? ['bg' => 'bg-secondary', 'text' => 'text-white', 'icon' => 'bi-question-circle', 'label' => ucfirst($status)];
    return '<span class="badge ' . $cfg['bg'] . ' ' . $cfg['text'] . ' d-inline-flex align-items-center gap-1 font-sans-serif" style="font-weight: 600;">'
         . '<i class="bi ' . $cfg['icon'] . '"></i> ' . e($cfg['label'])
         . '</span>';
}

/**
 * Render payment settlement badge (Paid in Full, Partial Due, Unpaid/Due)
 *
 * @param float|int|string $grandTotal
 * @param float|int|string $paidAmount
 * @param float|int|string $dueAmount
 * @return string HTML Badge
 */
function getPaymentStatusBadge($grandTotal, $paidAmount, $dueAmount): string {
    $gt = (float) $grandTotal;
    $paid = (float) $paidAmount;
    $due = (float) $dueAmount;

    if ($gt <= 0) {
        return '<span class="badge bg-light text-secondary border">No Charge</span>';
    }

    if ($due <= 0.005) {
        return '<span class="badge bg-success text-white d-inline-flex align-items-center gap-1"><i class="bi bi-check-circle-fill"></i> Fully Paid</span>';
    }

    if ($paid > 0.005) {
        return '<span class="badge bg-warning text-dark border d-inline-flex align-items-center gap-1"><i class="bi bi-pie-chart-fill"></i> Partial Due</span>';
    }

    return '<span class="badge bg-danger text-white d-inline-flex align-items-center gap-1"><i class="bi bi-exclamation-circle-fill"></i> Unpaid</span>';
}

/**
 * Parse standard date period string into from/to date strings and human-readable label
 *
 * @param string $period today | yesterday | this_week | this_month | last_month | this_year | custom | all
 * @param string $customFrom
 * @param string $customTo
 * @return array ['from' => ?string, 'to' => ?string, 'label' => string, 'period' => string]
 */
function parseDatePeriod(string $period, string $customFrom = '', string $customTo = ''): array {
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'today':
            return [
                'from'   => $today,
                'to'     => $today,
                'label'  => 'Today (' . date('M d, Y') . ')',
                'period' => 'today'
            ];
            
        case 'yesterday':
            $yest = date('Y-m-d', strtotime('-1 day'));
            return [
                'from'   => $yest,
                'to'     => $yest,
                'label'  => 'Yesterday (' . date('M d, Y', strtotime('-1 day')) . ')',
                'period' => 'yesterday'
            ];
            
        case 'this_week':
            $monday = date('Y-m-d', strtotime('monday this week'));
            return [
                'from'   => $monday,
                'to'     => $today,
                'label'  => 'This Week (' . date('M d', strtotime($monday)) . ' - ' . date('M d, Y') . ')',
                'period' => 'this_week'
            ];
            
        case 'this_month':
            $firstDay = date('Y-m-01');
            return [
                'from'   => $firstDay,
                'to'     => $today,
                'label'  => 'This Month (' . date('M Y') . ')',
                'period' => 'this_month'
            ];
            
        case 'last_month':
            $firstLastMonth = date('Y-m-01', strtotime('first day of last month'));
            $lastLastMonth  = date('Y-m-t', strtotime('last month'));
            return [
                'from'   => $firstLastMonth,
                'to'     => $lastLastMonth,
                'label'  => 'Last Month (' . date('M Y', strtotime('last month')) . ')',
                'period' => 'last_month'
            ];
            
        case 'this_year':
            $firstDayYear = date('Y-01-01');
            return [
                'from'   => $firstDayYear,
                'to'     => $today,
                'label'  => 'This Year (' . date('Y') . ')',
                'period' => 'this_year'
            ];
            
        case 'custom':
            $from = !empty($customFrom) ? $customFrom : null;
            $to   = !empty($customTo) ? $customTo : null;
            $label = 'Custom Range';
            if ($from && $to) {
                $label = date('M d, Y', strtotime($from)) . ' to ' . date('M d, Y', strtotime($to));
            } elseif ($from) {
                $label = 'From ' . date('M d, Y', strtotime($from));
            } elseif ($to) {
                $label = 'Until ' . date('M d, Y', strtotime($to));
            } else {
                $label = 'All Time';
            }
            return [
                'from'   => $from,
                'to'     => $to,
                'label'  => $label,
                'period' => 'custom'
            ];
            
        case 'all':
        default:
            return [
                'from'   => null,
                'to'     => null,
                'label'  => 'All Time',
                'period' => 'all'
            ];
    }
}


