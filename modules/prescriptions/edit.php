<?php
/**
 * Optical Shop Management CMS (optical-mgt)
 * Edit Prescription Details
 */

$pageTitle = 'Edit Prescription';
require_once __DIR__ . '/../../includes/header.php';

// Enforce Role Authorization
requireRole(['admin', 'optician']);

$pdo = getDbConnection();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid prescription identifier.');
    redirect('modules/prescriptions/index.php');
}

// Fetch existing prescription with customer and creator info
$stmt = $pdo->prepare("
    SELECT p.*, c.customer_code, c.full_name AS customer_name, c.phone AS customer_phone
    FROM prescriptions p
    JOIN customers c ON p.customer_id = c.id
    WHERE p.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $id]);
$prescription = $stmt->fetch();

if (!$prescription) {
    setFlash('error', 'Prescription record not found.');
    redirect('modules/prescriptions/index.php');
}

// Fetch all customers in case customer needs changing
$custStmt = $pdo->query("SELECT id, customer_code, full_name, phone FROM customers ORDER BY full_name ASC");
$allCustomers = $custStmt->fetchAll();

$error = '';
$customerId = $prescription['customer_id'];
$rxDate     = $prescription['prescription_date'];
$doctorName = $prescription['doctor_name'] ?? '';
$notes      = $prescription['notes'] ?? '';

// Right Eye (OD)
$rightSph  = $prescription['right_sph'] !== null ? (float)$prescription['right_sph'] : '';
$rightCyl  = $prescription['right_cyl'] !== null ? (float)$prescription['right_cyl'] : '';
$rightAxis = $prescription['right_axis'] !== null ? (int)$prescription['right_axis'] : '';
$rightAdd  = $prescription['right_add'] !== null ? (float)$prescription['right_add'] : '';
$rightPd   = $prescription['right_pd'] !== null ? (float)$prescription['right_pd'] : '';

// Left Eye (OS)
$leftSph  = $prescription['left_sph'] !== null ? (float)$prescription['left_sph'] : '';
$leftCyl  = $prescription['left_cyl'] !== null ? (float)$prescription['left_cyl'] : '';
$leftAxis = $prescription['left_axis'] !== null ? (int)$prescription['left_axis'] : '';
$leftAdd  = $prescription['left_add'] !== null ? (float)$prescription['left_add'] : '';
$leftPd   = $prescription['left_pd'] !== null ? (float)$prescription['left_pd'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken  = $_POST['csrf_token'] ?? '';
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $rxDate     = trim($_POST['prescription_date'] ?? '');
    $doctorName = trim($_POST['doctor_name'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    // OD values
    $rightSph  = trim($_POST['right_sph'] ?? '');
    $rightCyl  = trim($_POST['right_cyl'] ?? '');
    $rightAxis = trim($_POST['right_axis'] ?? '');
    $rightAdd  = trim($_POST['right_add'] ?? '');
    $rightPd   = trim($_POST['right_pd'] ?? '');

    // OS values
    $leftSph  = trim($_POST['left_sph'] ?? '');
    $leftCyl  = trim($_POST['left_cyl'] ?? '');
    $leftAxis = trim($_POST['left_axis'] ?? '');
    $leftAdd  = trim($_POST['left_add'] ?? '');
    $leftPd   = trim($_POST['left_pd'] ?? '');

    // Validations
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Security session expired. Please submit the form again.';
    } elseif ($customerId <= 0) {
        $error = 'Please select a valid customer.';
    } elseif (empty($rxDate)) {
        $error = 'Prescription examination date is required.';
    } elseif ($rightAxis !== '' && (!is_numeric($rightAxis) || $rightAxis < 0 || $rightAxis > 180)) {
        $error = 'Right Eye (OD) Axis must be a number between 0 and 180 degrees.';
    } elseif ($leftAxis !== '' && (!is_numeric($leftAxis) || $leftAxis < 0 || $leftAxis > 180)) {
        $error = 'Left Eye (OS) Axis must be a number between 0 and 180 degrees.';
    } else {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE prescriptions 
                SET customer_id = :customer_id,
                    prescription_date = :rx_date,
                    right_sph = :right_sph,
                    right_cyl = :right_cyl,
                    right_axis = :right_axis,
                    right_add = :right_add,
                    right_pd = :right_pd,
                    left_sph = :left_sph,
                    left_cyl = :left_cyl,
                    left_axis = :left_axis,
                    left_add = :left_add,
                    left_pd = :left_pd,
                    doctor_name = :doctor_name,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $updateStmt->execute([
                'customer_id' => $customerId,
                'rx_date'     => $rxDate,
                'right_sph'   => is_numeric($rightSph) ? (float) $rightSph : null,
                'right_cyl'   => is_numeric($rightCyl) ? (float) $rightCyl : null,
                'right_axis'  => is_numeric($rightAxis) ? (int) $rightAxis : null,
                'right_add'   => is_numeric($rightAdd) ? (float) $rightAdd : null,
                'right_pd'    => is_numeric($rightPd) ? (float) $rightPd : null,
                'left_sph'    => is_numeric($leftSph) ? (float) $leftSph : null,
                'left_cyl'    => is_numeric($leftCyl) ? (float) $leftCyl : null,
                'left_axis'   => is_numeric($leftAxis) ? (int) $leftAxis : null,
                'left_add'    => is_numeric($leftAdd) ? (float) $leftAdd : null,
                'left_pd'     => is_numeric($leftPd) ? (float) $leftPd : null,
                'doctor_name' => !empty($doctorName) ? $doctorName : null,
                'notes'       => !empty($notes) ? $notes : null,
                'id'          => $id
            ]);

            setFlash('success', 'Prescription #' . $id . ' updated successfully.');
            redirect('modules/prescriptions/view.php?id=' . $id);
        } catch (Exception $e) {
            error_log('Prescription Update Error: ' . $e->getMessage());
            $error = 'An error occurred while updating the prescription.';
        }
    }
}
?>

<!-- Header Actions & Breadcrumbs -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
  <div>
    <h4 class="fw-bold text-dark m-0">Edit Prescription #<?= $id; ?></h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb m-0 small">
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/prescriptions/index.php" class="text-decoration-none">Prescriptions</a></li>
        <li class="breadcrumb-item"><a href="<?= BASE_URL; ?>modules/prescriptions/view.php?id=<?= $id; ?>" class="text-decoration-none">Rx #<?= $id; ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL; ?>modules/prescriptions/view.php?id=<?= $id; ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-eye"></i> View Rx
    </a>
    <a href="<?= BASE_URL; ?>modules/prescriptions/index.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
      <i class="bi bi-arrow-left"></i> List
    </a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-11">
    <div class="card shadow-sm">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 fw-semibold text-dark">
          <i class="bi bi-pencil-square me-2 text-primary"></i> Edit Optical Refraction Record
        </h6>
        <span class="badge bg-light text-dark border font-monospace">
          Rx ID: #<?= $id; ?>
        </span>
      </div>

      <div class="card-body p-4">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger d-flex align-items-center mb-4 py-2 px-3 small shadow-sm" role="alert">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-6"></i>
            <div><?= e($error); ?></div>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL; ?>modules/prescriptions/edit.php?id=<?= $id; ?>" novalidate>
          <?= csrfField(); ?>

          <!-- Section 1: Customer & Exam Header -->
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label for="customer_id" class="form-label small fw-semibold text-dark">Customer <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                <select class="form-select" id="customer_id" name="customer_id" required>
                  <?php foreach ($allCustomers as $cust): ?>
                    <option value="<?= $cust['id']; ?>" <?= ($customerId == $cust['id']) ? 'selected' : ''; ?>>
                      <?= e($cust['full_name']); ?> (<?= e($cust['customer_code']); ?>) &bull; <?= e($cust['phone']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="col-md-3 col-sm-6">
              <label for="prescription_date" class="form-label small fw-semibold text-dark">Rx Exam Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="prescription_date" name="prescription_date" value="<?= e($rxDate); ?>" required>
            </div>

            <div class="col-md-3 col-sm-6">
              <label for="doctor_name" class="form-label small fw-semibold text-dark">Examiner / Optician</label>
              <input type="text" class="form-control" id="doctor_name" name="doctor_name" value="<?= e($doctorName); ?>">
            </div>
          </div>

          <hr class="text-muted my-4">

          <!-- Section 2: Optical Refraction Cards (OD & OS) -->
          <div class="row g-4 mb-4">
            <!-- Right Eye (OD) Card -->
            <div class="col-md-6">
              <div class="card h-100 border" style="border-left: 4px solid #0284c7 !important;">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                  <span class="fw-bold text-dark small text-uppercase">
                    <i class="bi bi-eye-fill me-1 text-primary"></i> Right Eye (OD &mdash; Oculus Dexter)
                  </span>
                  <span class="badge bg-primary">OD</span>
                </div>
                <div class="card-body p-3">
                  <div class="row g-2">
                    <div class="col-sm-4">
                      <label for="right_sph" class="form-label small fw-semibold text-dark">SPH (Sphere)</label>
                      <input type="number" step="0.25" class="form-control text-center font-monospace" id="right_sph" name="right_sph" value="<?= e($rightSph); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">&plusmn; Diopters</div>
                    </div>

                    <div class="col-sm-4">
                      <label for="right_cyl" class="form-label small fw-semibold text-dark">CYL (Cylinder)</label>
                      <input type="number" step="0.25" class="form-control text-center font-monospace" id="right_cyl" name="right_cyl" value="<?= e($rightCyl); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">Astigmatism</div>
                    </div>

                    <div class="col-sm-4">
                      <label for="right_axis" class="form-label small fw-semibold text-dark">AXIS (Degrees)</label>
                      <input type="number" min="0" max="180" class="form-control text-center font-monospace" id="right_axis" name="right_axis" value="<?= e($rightAxis); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">0 &ndash; 180&deg;</div>
                    </div>

                    <div class="col-sm-6 mt-2">
                      <label for="right_add" class="form-label small fw-semibold text-dark">ADD (Near Addition)</label>
                      <input type="number" step="0.25" class="form-control text-center font-monospace" id="right_add" name="right_add" value="<?= e($rightAdd); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">Reading Power</div>
                    </div>

                    <div class="col-sm-6 mt-2">
                      <label for="right_pd" class="form-label small fw-semibold text-dark">PD (Distance / Mono)</label>
                      <input type="number" step="0.5" class="form-control text-center font-monospace" id="right_pd" name="right_pd" value="<?= e($rightPd); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">Millimeters (mm)</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Left Eye (OS) Card -->
            <div class="col-md-6">
              <div class="card h-100 border" style="border-left: 4px solid #10b981 !important;">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                  <span class="fw-bold text-dark small text-uppercase">
                    <i class="bi bi-eye-fill me-1 text-success"></i> Left Eye (OS &mdash; Oculus Sinister)
                  </span>
                  <span class="badge bg-success">OS</span>
                </div>
                <div class="card-body p-3">
                  <div class="row g-2">
                    <div class="col-sm-4">
                      <label for="left_sph" class="form-label small fw-semibold text-dark">SPH (Sphere)</label>
                      <input type="number" step="0.25" class="form-control text-center font-monospace" id="left_sph" name="left_sph" value="<?= e($leftSph); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">&plusmn; Diopters</div>
                    </div>

                    <div class="col-sm-4">
                      <label for="left_cyl" class="form-label small fw-semibold text-dark">CYL (Cylinder)</label>
                      <input type="number" step="0.25" class="form-control text-center font-monospace" id="left_cyl" name="left_cyl" value="<?= e($leftCyl); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">Astigmatism</div>
                    </div>

                    <div class="col-sm-4">
                      <label for="left_axis" class="form-label small fw-semibold text-dark">AXIS (Degrees)</label>
                      <input type="number" min="0" max="180" class="form-control text-center font-monospace" id="left_axis" name="left_axis" value="<?= e($leftAxis); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">0 &ndash; 180&deg;</div>
                    </div>

                    <div class="col-sm-6 mt-2">
                      <label for="left_add" class="form-label small fw-semibold text-dark">ADD (Near Addition)</label>
                      <input type="number" step="0.25" class="form-control text-center font-monospace" id="left_add" name="left_add" value="<?= e($leftAdd); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">Reading Power</div>
                    </div>

                    <div class="col-sm-6 mt-2">
                      <label for="left_pd" class="form-label small fw-semibold text-dark">PD (Distance / Mono)</label>
                      <input type="number" step="0.5" class="form-control text-center font-monospace" id="left_pd" name="left_pd" value="<?= e($leftPd); ?>">
                      <div class="form-text text-center" style="font-size: 0.7rem;">Millimeters (mm)</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 3: Clinical Notes -->
          <div class="mb-4">
            <label for="notes" class="form-label small fw-semibold text-dark">Clinical Notes & Lens Recommendations</label>
            <textarea class="form-control" id="notes" name="notes" rows="3"><?= e($notes); ?></textarea>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="<?= BASE_URL; ?>modules/prescriptions/view.php?id=<?= $id; ?>" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4">
              <i class="bi bi-check2-circle me-1"></i> Update Prescription
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
