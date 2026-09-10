<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Add New Customer
 */

$pageTitle = 'Add New Customer';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();

$error = '';
$fullName = '';
$phone = '';
$email = '';
$gender = '';
$dob = '';
$address = '';
$notes = '';
$status = 'active';

// Preview next customer code
$previewCode = generateCustomerCode($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $fullName  = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $gender    = trim($_POST['gender'] ?? '');
    $dob       = trim($_POST['date_of_birth'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');
    $status    = trim($_POST['status'] ?? 'active');

    // Validation
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Security session expired. Please submit the form again.';
    } elseif (empty($fullName)) {
        $error = 'Customer Full Name is required.';
    } elseif (empty($phone)) {
        $error = 'Customer Phone Number is required.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (!empty($gender) && !in_array($gender, ['male', 'female', 'other'], true)) {
        $error = 'Please select a valid gender option.';
    } elseif (!empty($dob) && strtotime($dob) > time()) {
        $error = 'Date of birth cannot be in the future.';
    } else {
        try {
            // Generate guaranteed unique customer code
            $customerCode = generateCustomerCode($pdo);

            $stmt = $pdo->prepare("
                INSERT INTO customers (
                    customer_code, full_name, phone, email, gender, date_of_birth, address, notes, status, created_at
                ) VALUES (
                    :code, :full_name, :phone, :email, :gender, :dob, :address, :notes, :status, NOW()
                )
            ");

            $stmt->execute([
                'code'      => $customerCode,
                'full_name' => $fullName,
                'phone'     => $phone,
                'email'     => !empty($email) ? $email : null,
                'gender'    => !empty($gender) ? $gender : null,
                'dob'       => !empty($dob) ? $dob : null,
                'address'   => !empty($address) ? $address : null,
                'notes'     => !empty($notes) ? $notes : null,
                'status'    => in_array($status, ['active', 'inactive'], true) ? $status : 'active'
            ]);

            $newId = (int) $pdo->lastInsertId();

            setFlash('success', 'Customer ' . e($customerCode) . ' (' . e($fullName) . ') has been registered successfully.');
            redirect('modules/customers/view.php?id=' . $newId);
        } catch (Exception $e) {
            error_log('Customer Creation Error: ' . $e->getMessage());
            $error = 'An error occurred while registering the customer. Please try again.';
        }
    }
}
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Add Customer</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/customers/index.php" class="text-decoration-none">Customers</a></li>
        <li class="breadcrumb-item active" aria-current="page">Add New</li>
      </ol>
    </nav>
  </div>
  <div>
    <a href="<?= BASE_URL; ?>modules/customers/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-arrow-left"></i> Back to List
    </a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-10">
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-person-plus-fill me-2 text-primary"></i> Customer Registration Form
        </h6>
        <span class="badge bg-light text-dark border font-monospace">
          Auto Code: <?= e($previewCode); ?>
        </span>
      </div>

      <div class="card-body p-4">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger d-flex align-items-center mb-4 py-2 px-3 small shadow-sm" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-6"></i>
            <div><?= e($error); ?></div>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL; ?>modules/customers/create.php" novalidate>
          <?= csrfField(); ?>

          <!-- Personal Information Section -->
          <div class="mb-4">
            <h6 class="text-uppercase text-secondary fw-bold small mb-3" style="letter-spacing: 0.05em;">
              <i class="bi bi-person-lines-fill me-1"></i> Contact & Personal Information
            </h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="full_name" class="form-label small fw-semibold text-dark">Full Name <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                  <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($fullName); ?>" placeholder="e.g. Rahim Ahmed" required autofocus>
                </div>
              </div>

              <div class="col-md-6">
                <label for="phone" class="form-label small fw-semibold text-dark">Phone Number <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted"><i class="bi bi-telephone"></i></span>
                  <input type="text" class="form-control" id="phone" name="phone" value="<?= e($phone); ?>" placeholder="e.g. +880 1711-234567" required>
                </div>
              </div>

              <div class="col-md-6">
                <label for="email" class="form-label small fw-semibold text-dark">Email Address</label>
                <div class="input-group">
                  <span class="input-group-text bg-light text-muted"><i class="bi bi-envelope"></i></span>
                  <input type="email" class="form-control" id="email" name="email" value="<?= e($email); ?>" placeholder="e.g. rahim.ahmed@example.com">
                </div>
              </div>

              <div class="col-md-3 col-sm-6">
                <label for="gender" class="form-label small fw-semibold text-dark">Gender</label>
                <select class="form-select" id="gender" name="gender">
                  <option value="">Select Gender</option>
                  <option value="male" <?= $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                  <option value="female" <?= $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                  <option value="other" <?= $gender === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
              </div>

              <div class="col-md-3 col-sm-6">
                <label for="date_of_birth" class="form-label small fw-semibold text-dark">Date of Birth</label>
                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?= e($dob); ?>" max="<?= date('Y-m-d'); ?>">
              </div>

              <div class="col-md-12">
                <label for="status" class="form-label small fw-semibold text-dark">Account Status</label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" id="status_active" value="active" <?= $status === 'active' ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="status_active"><span class="badge badge-status-active">Active</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="status" id="status_inactive" value="inactive" <?= $status === 'inactive' ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="status_inactive"><span class="badge badge-status-inactive">Inactive</span></label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <hr class="text-muted my-4">

          <!-- Address & Notes Section -->
          <div class="mb-4">
            <h6 class="text-uppercase text-secondary fw-bold small mb-3" style="letter-spacing: 0.05em;">
              <i class="bi bi-geo-alt-fill me-1"></i> Address & Optical Notes
            </h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="address" class="form-label small fw-semibold text-dark">Street Address</label>
                <textarea class="form-control" id="address" name="address" rows="3" placeholder="Full postal address..."><?= e($address); ?></textarea>
              </div>

              <div class="col-md-6">
                <label for="notes" class="form-label small fw-semibold text-dark">Clinical / Optical Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Lens preferences, previous frame style, special considerations..."><?= e($notes); ?></textarea>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="<?= BASE_URL; ?>modules/customers/index.php" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-check2-circle me-1"></i> Register Customer
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
