<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Customer Profile & Comprehensive Details View
 */

$pageTitle = 'Customer Profile';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();
$isAdmin = hasRole('admin');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid customer identifier.');
    redirect('modules/customers/index.php');
}

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$customer = $stmt->fetch();

if (!$customer) {
    setFlash('error', 'Customer record not found.');
    redirect('modules/customers/index.php');
}

// Calculate age if DOB exists
$ageText = '';
if (!empty($customer['date_of_birth']) && $customer['date_of_birth'] !== '0000-00-00') {
    try {
        $dobDate = new DateTime($customer['date_of_birth']);
        $today = new DateTime('today');
        $age = $dobDate->diff($today)->y;
        $ageText = ' (' . $age . ' yrs)';
    } catch (Exception $e) {
        $ageText = '';
    }
}
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2">
      <h4 class="fw-bold text-dark m-0"><?= e($customer['full_name']); ?></h4>
      <span class="badge bg-light text-dark border font-monospace"><?= e($customer['customer_code']); ?></span>
      <span class="badge <?= $customer['status'] === 'active' ? 'badge-status-active' : 'badge-status-inactive'; ?>">
        <?= ucfirst(e($customer['status'])); ?>
      </span>
    </div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/customers/index.php" class="text-decoration-none">Customers</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($customer['customer_code']); ?></li>
      </ol>
    </nav>
  </div>
  
  <div class="d-flex flex-wrap gap-2">
    <!-- Edit Button -->
    <a href="<?= BASE_URL; ?>modules/customers/edit.php?id=<?= $customer['id']; ?>" class="btn btn-primary d-inline-flex align-items-center gap-1">
      <i class="bi bi-pencil-square"></i> Edit Customer
    </a>

    <!-- Status Toggle Form -->
    <form method="POST" action="<?= BASE_URL; ?>modules/customers/toggle-status.php" class="d-inline" onsubmit="return confirm('Toggle status of <?= e(addslashes($customer['full_name'])); ?> to <?= $customer['status'] === 'active' ? 'Inactive' : 'Active'; ?>?');">
      <?= csrfField(); ?>
      <input type="hidden" name="id" value="<?= $customer['id']; ?>">
      <button type="submit" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
        <i class="bi <?= $customer['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off text-secondary'; ?>"></i>
        <?= $customer['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
      </button>
    </form>

    <!-- Delete Button (Admin Only) -->
    <?php if ($isAdmin): ?>
      <form method="POST" action="<?= BASE_URL; ?>modules/customers/delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete customer <?= e(addslashes($customer['full_name'])); ?> (<?= e($customer['customer_code']); ?>)? This action cannot be undone.');">
        <?= csrfField(); ?>
        <input type="hidden" name="id" value="<?= $customer['id']; ?>">
        <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center gap-1">
          <i class="bi bi-trash"></i> Delete
        </button>
      </form>
    <?php endif; ?>

    <!-- Back to List -->
    <a href="<?= BASE_URL; ?>modules/customers/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-arrow-left"></i> List
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Customer Details Overview Card -->
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-person-badge-fill me-2 text-primary"></i> Customer Summary
        </h6>
      </div>
      <div class="card-body p-4">
        <div class="text-center mb-4">
          <div class="d-inline-flex align-items-center justify-content-center bg-light border rounded-circle mb-3" style="width: 80px; height: 80px; font-size: 2rem; color: #0284c7; font-weight: 700;">
            <?= e(getUserInitials($customer['full_name'])); ?>
          </div>
          <h5 class="fw-bold text-dark mb-1"><?= e($customer['full_name']); ?></h5>
          <span class="font-monospace text-primary fw-medium small"><?= e($customer['customer_code']); ?></span>
        </div>

        <div class="small">
          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-telephone me-1"></i> Phone:</span>
            <span class="fw-semibold text-dark"><?= e($customer['phone']); ?></span>
          </div>

          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-envelope me-1"></i> Email:</span>
            <span class="fw-medium text-dark"><?= !empty($customer['email']) ? e($customer['email']) : '<span class="text-muted">&mdash;</span>'; ?></span>
          </div>

          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-gender-ambiguous me-1"></i> Gender:</span>
            <span class="fw-medium text-dark"><?= !empty($customer['gender']) ? ucfirst(e($customer['gender'])) : '<span class="text-muted">&mdash;</span>'; ?></span>
          </div>

          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-calendar-event me-1"></i> Date of Birth:</span>
            <span class="fw-medium text-dark">
              <?= !empty($customer['date_of_birth']) ? date('M d, Y', strtotime($customer['date_of_birth'])) . e($ageText) : '<span class="text-muted">&mdash;</span>'; ?>
            </span>
          </div>

          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-calendar-check me-1"></i> Registered:</span>
            <span class="fw-medium text-dark"><?= date('M d, Y', strtotime($customer['created_at'])); ?></span>
          </div>

          <div class="d-flex justify-content-between py-2">
            <span class="text-muted"><i class="bi bi-clock-history me-1"></i> Last Updated:</span>
            <span class="fw-medium text-dark"><?= date('M d, Y h:i A', strtotime($customer['updated_at'])); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Address, Notes & Future Modules Section -->
  <div class="col-lg-8">
    <div class="row g-4">
      <!-- Address & Notes -->
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-header bg-white py-3">
            <h6 class="m-0 fw-semibold text-dark">
              <i class="bi bi-geo-alt-fill me-2 text-primary"></i> Address & Clinical Notes
            </h6>
          </div>
          <div class="card-body p-4">
            <div class="row g-4">
              <div class="col-md-6">
                <div class="text-muted small fw-semibold text-uppercase mb-1">Postal Address</div>
                <p class="text-dark m-0 bg-light p-3 rounded border small">
                  <?= !empty($customer['address']) ? nl2br(e($customer['address'])) : '<span class="text-muted">No postal address recorded.</span>'; ?>
                </p>
              </div>
              <div class="col-md-6">
                <div class="text-muted small fw-semibold text-uppercase mb-1">Optical Notes & Lens History</div>
                <p class="text-dark m-0 bg-light p-3 rounded border small">
                  <?= !empty($customer['notes']) ? nl2br(e($customer['notes'])) : '<span class="text-muted">No clinical notes recorded.</span>'; ?>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Future Module Links: Prescriptions & Orders (Reserved Placeholders) -->
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-semibold text-dark">
              <i class="bi bi-journal-medical me-2 text-primary"></i> Optical History & Prescriptions
            </h6>
            <span class="badge bg-light text-secondary border">Phase 3 & 4 Modules</span>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">
              <!-- Prescriptions Section -->
              <div class="col-md-6">
                <div class="p-3 bg-light rounded border h-100">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold text-dark small"><i class="bi bi-file-earmark-medical me-1 text-primary"></i> Prescriptions (Rx)</span>
                    <span class="badge bg-secondary text-white" style="font-size: 0.65rem;">Phase 3</span>
                  </div>
                  <p class="text-muted small m-0">
                    Spherical (SPH), Cylinder (CYL), Axis, and Pupillary Distance (PD) prescription tests for <?= e($customer['full_name']); ?> will link here upon Phase 3 completion.
                  </p>
                </div>
              </div>

              <!-- Orders Section -->
              <div class="col-md-6">
                <div class="p-3 bg-light rounded border h-100">
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold text-dark small"><i class="bi bi-cart-check me-1 text-primary"></i> Orders & Billing</span>
                    <span class="badge bg-secondary text-white" style="font-size: 0.65rem;">Phase 4</span>
                  </div>
                  <p class="text-muted small m-0">
                    Dispensed frames, prescription lenses, payment history, and invoices for this customer will appear here upon Phase 4 completion.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
