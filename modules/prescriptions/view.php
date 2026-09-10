<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Prescription Details & Optical Refraction View
 */

$pageTitle = 'Prescription Details';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDbConnection();
$canManage = hasRole(['admin', 'optician']);

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid prescription identifier.');
    redirect('modules/prescriptions/index.php');
}

$stmt = $pdo->prepare("
    SELECT p.*, c.customer_code, c.full_name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
           c.gender AS customer_gender, c.date_of_birth AS customer_dob,
           u.name AS created_by_name
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$prescription = $stmt->fetch();

if (!$prescription) {
    setFlash('error', 'Prescription record not found.');
    redirect('modules/prescriptions/index.php');
}
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2">
      <h4 class="fw-bold text-dark m-0">Prescription #<?= $prescription['id']; ?></h4>
      <span class="badge bg-light text-dark border font-monospace"><?= e($prescription['customer_code']); ?></span>
    </div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small mt-1">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/prescriptions/index.php" class="text-decoration-none">Prescriptions</a></li>
        <li class="breadcrumb-item active" aria-current="page">Rx #<?= $prescription['id']; ?></li>
      </ol>
    </nav>
  </div>

  <div class="d-flex flex-wrap gap-2">
    <!-- Print Rx Button -->
    <button type="button" onclick="window.print();" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-printer"></i> Print Rx
    </button>

    <!-- Edit Rx Button -->
    <?php if ($canManage): ?>
      <a href="<?= BASE_URL; ?>modules/prescriptions/edit.php?id=<?= $prescription['id']; ?>" class="btn btn-primary d-inline-flex align-items-center gap-1">
        <i class="bi bi-pencil-square"></i> Edit Rx
      </a>

      <!-- Delete Rx Form -->
      <form method="POST" action="<?= BASE_URL; ?>modules/prescriptions/delete.php" class="d-inline" onsubmit="return confirm('Permanently delete Prescription #<?= $prescription['id']; ?> for <?= e(addslashes($prescription['customer_name'])); ?>?');">
        <?= csrfField(); ?>
        <input type="hidden" name="id" value="<?= $prescription['id']; ?>">
        <button type="submit" class="btn btn-outline-danger d-inline-flex align-items-center gap-1">
          <i class="bi bi-trash"></i> Delete
        </button>
      </form>
    <?php endif; ?>

    <!-- Back to Customer -->
    <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $prescription['customer_id']; ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-person"></i> Customer Profile
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Customer & Exam Info Card -->
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-person-badge-fill me-2 text-primary"></i> Patient & Exam Summary
        </h6>
      </div>
      <div class="card-body p-4">
        <!-- Patient Info -->
        <div class="mb-4">
          <span class="text-uppercase text-secondary fw-bold small" style="font-size: 0.72rem; letter-spacing: 0.05em;">Customer Information</span>
          <h5 class="fw-bold text-dark mt-1 mb-1">
            <a href="<?= BASE_URL; ?>modules/customers/view.php?id=<?= $prescription['customer_id']; ?>" class="text-dark text-decoration-none hover-primary">
              <?= e($prescription['customer_name']); ?>
            </a>
          </h5>
          <span class="badge bg-light text-primary border font-monospace"><?= e($prescription['customer_code']); ?></span>
        </div>

        <div class="small">
          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-telephone me-1"></i> Phone:</span>
            <span class="fw-semibold text-dark"><?= e($prescription['customer_phone']); ?></span>
          </div>

          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-envelope me-1"></i> Email:</span>
            <span class="fw-medium text-dark"><?= !empty($prescription['customer_email']) ? e($prescription['customer_email']) : '<span class="text-muted">&mdash;</span>'; ?></span>
          </div>

          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-calendar-event me-1"></i> Exam Date:</span>
            <span class="fw-bold text-primary"><?= date('M d, Y', strtotime($prescription['prescription_date'])); ?></span>
          </div>

          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-person-badge me-1"></i> Optician / Dr:</span>
            <span class="fw-medium text-dark"><?= !empty($prescription['doctor_name']) ? e($prescription['doctor_name']) : '<span class="text-muted">&mdash;</span>'; ?></span>
          </div>

          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-muted"><i class="bi bi-person-check me-1"></i> Recorded By:</span>
            <span class="fw-medium text-dark"><?= e($prescription['created_by_name'] ?? 'Staff'); ?></span>
          </div>

          <div class="d-flex justify-content-between py-2">
            <span class="text-muted"><i class="bi bi-clock-history me-1"></i> Recorded On:</span>
            <span class="fw-medium text-dark"><?= date('M d, Y h:i A', strtotime($prescription['created_at'])); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Optical Refraction Grid & Clinical Notes -->
  <div class="col-lg-8">
    <!-- Optical Measurements Card -->
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-eyeglasses me-2 text-primary"></i> Optical Refraction Parameters
        </h6>
        <span class="badge bg-light text-dark border font-monospace">Rx #<?= $prescription['id']; ?></span>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered align-middle text-center mb-0" style="border-color: #e2e8f0;">
            <thead style="background-color: #f8fafc;">
              <tr>
                <th class="text-start ps-4" style="width: 25%; font-size: 0.8rem; text-transform: uppercase; color: #475569;">Eye Parameter</th>
                <th style="width: 37.5%; font-size: 0.85rem; color: #0284c7;">
                  <i class="bi bi-eye-fill me-1"></i> RIGHT EYE (OD)
                </th>
                <th style="width: 37.5%; font-size: 0.85rem; color: #10b981;">
                  <i class="bi bi-eye-fill me-1"></i> LEFT EYE (OS)
                </th>
              </tr>
            </thead>
            <tbody class="font-monospace">
              <tr>
                <td class="text-start ps-4 font-sans-serif fw-semibold text-dark">SPH (Sphere)</td>
                <td class="fs-6 fw-bold text-dark"><?= formatOpticalPower($prescription['right_sph']); ?> <span class="small text-muted fw-normal">D</span></td>
                <td class="fs-6 fw-bold text-dark"><?= formatOpticalPower($prescription['left_sph']); ?> <span class="small text-muted fw-normal">D</span></td>
              </tr>
              <tr>
                <td class="text-start ps-4 font-sans-serif fw-semibold text-dark">CYL (Cylinder)</td>
                <td class="fs-6 fw-bold text-dark"><?= formatOpticalPower($prescription['right_cyl']); ?> <span class="small text-muted fw-normal">D</span></td>
                <td class="fs-6 fw-bold text-dark"><?= formatOpticalPower($prescription['left_cyl']); ?> <span class="small text-muted fw-normal">D</span></td>
              </tr>
              <tr>
                <td class="text-start ps-4 font-sans-serif fw-semibold text-dark">AXIS</td>
                <td class="fs-6 fw-bold text-dark"><?= formatAxis($prescription['right_axis']); ?></td>
                <td class="fs-6 fw-bold text-dark"><?= formatAxis($prescription['left_axis']); ?></td>
              </tr>
              <tr>
                <td class="text-start ps-4 font-sans-serif fw-semibold text-dark">ADD (Near Addition)</td>
                <td class="fs-6 fw-bold text-dark"><?= formatOpticalPower($prescription['right_add']); ?> <span class="small text-muted fw-normal">D</span></td>
                <td class="fs-6 fw-bold text-dark"><?= formatOpticalPower($prescription['left_add']); ?> <span class="small text-muted fw-normal">D</span></td>
              </tr>
              <tr>
                <td class="text-start ps-4 font-sans-serif fw-semibold text-dark">PD (Pupillary Distance)</td>
                <td class="fs-6 fw-bold text-dark"><?= formatPd($prescription['right_pd']); ?></td>
                <td class="fs-6 fw-bold text-dark"><?= formatPd($prescription['left_pd']); ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Clinical Notes & Lens Recommendations -->
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-file-text-fill me-2 text-primary"></i> Clinical Notes & Lens Recommendations
        </h6>
      </div>
      <div class="card-body p-4">
        <?php if (!empty($prescription['notes'])): ?>
          <div class="p-3 bg-light rounded border text-dark small">
            <?= nl2br(e($prescription['notes'])); ?>
          </div>
        <?php else: ?>
          <p class="text-muted small m-0">No special clinical notes or lens dispensing remarks recorded for this prescription.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
