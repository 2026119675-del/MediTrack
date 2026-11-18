<?php
require_once '../includes/auth.php';
requireRole(['doctor']);
require_once '../config/database.php';

$pageTitle = "Patient Information";
$doctorId = (int)($_SESSION['user_id'] ?? 0);
$patientId = (int)($_POST['patient_id'] ?? $_GET['patient_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$mode = $_GET['mode'] ?? 'view';

$successMessage = $_SESSION['patient_success'] ?? '';
unset($_SESSION['patient_success']);

$errorMessage = '';
$selectedPatient = null;
$medicalRecords = [];
$allergies = [];
$editRecord = null;

function escPatient($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['patient_csrf'])) {
    $_SESSION['patient_csrf'] = bin2hex(random_bytes(32));
}

// SAVE MEDICAL RECORD AND ALLERGY
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    try {
        if (!hash_equals(
            $_SESSION['patient_csrf'],
            (string)($_POST['csrf_token'] ?? '')
        )) {
            throw new RuntimeException("Invalid request.");
        }

        if ($doctorId <= 0 || $patientId <= 0) {
            throw new RuntimeException("Invalid doctor or patient.");
        }

        $stmt = $pdo->prepare(
            "SELECT patient_id FROM patients WHERE patient_id = ?"
        );
        $stmt->execute([$patientId]);

        if (!$stmt->fetch()) {
            throw new RuntimeException("Patient not found.");
        }

        // ADD MEDICAL RECORD
        if ($action === 'add_record') {

            $mode = 'add';

            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $visitDate = $_POST['visit_date'] ?? date('Y-m-d');

            if ($diagnosis === '') {
                throw new RuntimeException("Please enter a diagnosis.");
            }

            $stmt = $pdo->prepare(
                "INSERT INTO medical_records
                (patient_id, doctor_id, diagnosis, description, visit_date)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $patientId,
                $doctorId,
                $diagnosis,
                $description,
                $visitDate
            ]);

            $_SESSION['patient_success'] =
                "Medical record added successfully.";

        }

        // EDIT MEDICAL RECORD
        elseif ($action === 'edit_record') {

            $mode = 'edit';

            $recordId = (int)($_POST['record_id'] ?? 0);
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $visitDate = $_POST['visit_date'] ?? date('Y-m-d');

            if ($diagnosis === '') {
                throw new RuntimeException("Please enter a diagnosis.");
            }

            $stmt = $pdo->prepare(
                "SELECT record_id FROM medical_records
                 WHERE record_id = ?
                 AND patient_id = ?
                 AND doctor_id = ?"
            );

            $stmt->execute([$recordId, $patientId, $doctorId]);

            if (!$stmt->fetch()) {
                throw new RuntimeException(
                    "You can only edit your own medical records."
                );
            }

            $stmt = $pdo->prepare(
                "UPDATE medical_records
                 SET diagnosis = ?,
                     description = ?,
                     visit_date = ?
                 WHERE record_id = ?
                 AND patient_id = ?
                 AND doctor_id = ?"
            );

            $stmt->execute([
                $diagnosis,
                $description,
                $visitDate,
                $recordId,
                $patientId,
                $doctorId
            ]);

            $_SESSION['patient_success'] =
                "Medical record updated successfully.";

        }

        // ADD ALLERGY
        elseif ($action === 'add_allergy') {

            $mode = 'allergy';

            $allergyName = trim($_POST['allergy_name'] ?? '');
            $reaction = trim($_POST['reaction'] ?? '');
            $severity = $_POST['severity'] ?? '';
            $notes = trim($_POST['notes'] ?? '');

            if ($allergyName === '') {
                throw new RuntimeException("Please enter allergy name.");
            }

            if (!in_array($severity, ['mild', 'moderate', 'severe'], true)) {
                throw new RuntimeException("Invalid allergy severity.");
            }

            $stmt = $pdo->prepare(
                "SELECT patient_id
                 FROM patient_allergies
                 WHERE patient_id = ?
                 AND LOWER(TRIM(allergy_name)) = LOWER(?)"
            );

            $stmt->execute([$patientId, $allergyName]);

            if ($stmt->fetch()) {
                throw new RuntimeException("This allergy is already recorded.");
            }

            $stmt = $pdo->prepare(
                "INSERT INTO patient_allergies
                 (patient_id, allergy_name, reaction, severity, notes)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $patientId,
                $allergyName,
                $reaction,
                $severity,
                $notes
            ]);

            $_SESSION['patient_success'] =
                "Patient allergy added successfully.";

        } else {
            throw new RuntimeException("Invalid action.");
        }

        header("Location: patients.php?patient_id=" . $patientId . "#patientDetails");
        exit;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        $errorMessage = "Unable to save information. Database operation failed.";
    } catch (RuntimeException $e) {
        $errorMessage = $e->getMessage();
    }
}

// LOAD PATIENTS
$stmt = $pdo->prepare(
    "SELECT p.*,
    (
        SELECT COUNT(*)
        FROM patient_allergies pa
        WHERE pa.patient_id = p.patient_id
    ) AS allergy_count
    FROM patients p
    WHERE p.patient_name LIKE ?
    ORDER BY p.patient_name ASC"
);

$stmt->execute(['%' . $search . '%']);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// LOAD ALL ALLERGIES
$patientAllergies = [];

$stmt = $pdo->query(
    "SELECT * FROM patient_allergies ORDER BY allergy_name ASC"
);

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $patientAllergies[$a['patient_id']][] = $a;
}

// LOAD SELECTED PATIENT
if ($patientId > 0) {

    $stmt = $pdo->prepare(
        "SELECT * FROM patients WHERE patient_id = ?"
    );

    $stmt->execute([$patientId]);
    $selectedPatient = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedPatient) {

        $allergies = $patientAllergies[$patientId] ?? [];

        // LOAD MEDICAL HISTORY
        try {

            $stmt = $pdo->prepare(
                "SELECT m.*, u.full_name AS doctor_name
                 FROM medical_records m
                 LEFT JOIN users u ON m.doctor_id = u.user_id
                 WHERE m.patient_id = ?
                 ORDER BY m.visit_date DESC, m.record_id DESC"
            );

            $stmt->execute([$patientId]);
            $medicalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // LOAD EDIT RECORD
            if ($mode === 'edit') {

                $recordId = (int)(
                    $_POST['record_id'] ??
                    $_GET['record_id'] ?? 0
                );

                $stmt = $pdo->prepare(
                    "SELECT * FROM medical_records
                     WHERE record_id = ?
                     AND patient_id = ?
                     AND doctor_id = ?"
                );

                $stmt->execute([
                    $recordId,
                    $patientId,
                    $doctorId
                ]);

                $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$editRecord) {
                    $errorMessage = "Medical record not found or access denied.";
                    $mode = 'view';
                }
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errorMessage = "Unable to load medical history.";
            $mode = 'view';
        }
    }
}

require_once '../includes/header.php';
?>

<style>
.doctor-patients .detail-box {
    background: #f5f9fa;
    border: 1px solid #e4ecee;
    border-radius: 10px;
    padding: 18px;
    height: 100%;
}

.doctor-patients .detail-box small {
    display: block;
    color: #888;
    margin-bottom: 8px;
}

.doctor-patients .record-form {
    background: #f5f9fa;
    border: 1px solid #dce8eb;
    border-radius: 10px;
    padding: 20px;
}

.doctor-patients .allergy-card {
    background: #fff5f5;
    border-left: 4px solid #dc3545;
    padding: 18px;
    border-radius: 8px;
    margin-bottom: 12px;
}

.doctor-patients .table td {
    vertical-align: middle;
}

.doctor-patients .btn-medical {
    background: #086788;
    color: white;
    border: 1px solid #086788;
}

.doctor-patients .btn-medical:hover {
    background: #07546e;
    color: white;
}
</style>

<div class="doctor-patients">

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <?= escPatient($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <?= escPatient($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- PATIENT LIST -->
    <div class="section-card mb-4">

        <h4>Patient Information</h4>

        <p class="text-muted">
            View and manage patient medical information.
        </p>

        <form method="GET" class="row g-2 mb-4">

            <div class="col-md-9">
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="Search patient name"
                       value="<?= escPatient($search) ?>">
            </div>

            <div class="col-md-3">
                <button class="btn btn-danger w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>

        </form>

        <div class="table-responsive">

            <table class="table table-hover">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient Name</th>
                        <th>Gender</th>
                        <th>Phone</th>
                        <th>Allergy Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($patients as $p): ?>

                    <tr>

                        <td><?= (int)$p['patient_id'] ?></td>

                        <td><?= escPatient($p['patient_name']) ?></td>

                        <td><?= escPatient($p['gender'] ?? '-') ?></td>

                        <td><?= escPatient($p['phone'] ?? '-') ?></td>

                        <td>

                            <?php if ($p['allergy_count'] > 0): ?>

                                <span class="badge bg-danger">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Allergy Alert
                                </span>

                                <?php foreach ($patientAllergies[$p['patient_id']] ?? [] as $a): ?>
                                    <div class="small text-danger mt-1">
                                        <?= escPatient($a['allergy_name']) ?>
                                    </div>
                                <?php endforeach; ?>

                            <?php else: ?>

                                <span class="badge bg-light text-secondary">
                                    No Recorded Allergy
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <div class="d-flex flex-wrap gap-2">

                                <a href="?patient_id=<?= (int)$p['patient_id'] ?>&amp;mode=view#patientDetails"
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-eye"></i> View
                                </a>

                                <a href="?patient_id=<?= (int)$p['patient_id'] ?>&amp;mode=add#medicalForm"
                                   class="btn btn-sm btn-medical">
                                    <i class="bi bi-plus-circle"></i>
                                    Add Medical Record
                                </a>

                                <a href="?patient_id=<?= (int)$p['patient_id'] ?>&amp;mode=view#medicalHistory"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil-square"></i>
                                    Medical History
                                </a>

                                <a href="?patient_id=<?= (int)$p['patient_id'] ?>&amp;mode=allergy#allergyForm"
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-plus"></i>
                                    Add Allergy
                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$patients): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            No patients found.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

    <?php if ($selectedPatient): ?>

    <!-- PATIENT DETAILS -->
    <div class="section-card mb-4" id="patientDetails">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <h5>
                <i class="bi bi-person-vcard me-2"></i>
                Patient Details
            </h5>

            <a href="patients.php"
               class="btn btn-sm btn-outline-secondary">
                Close
            </a>

        </div>

        <div class="row g-3 mb-4">

            <?php
            $details = [
                'Patient Name' => $selectedPatient['patient_name'],
                'Date of Birth' => $selectedPatient['date_of_birth'] ?? '-',
                'Gender' => $selectedPatient['gender'] ?? '-',
                'Phone Number' => $selectedPatient['phone'] ?? '-'
            ];
            ?>

            <?php foreach ($details as $label => $value): ?>

                <div class="col-md-3">

                    <div class="detail-box">
                        <small><?= escPatient($label) ?></small>
                        <strong><?= escPatient($value) ?></strong>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <!-- ADD / EDIT MEDICAL RECORD -->
        <?php if ($mode === 'add' || ($mode === 'edit' && $editRecord)): ?>

        <div class="record-form mb-4" id="medicalForm">

            <h5 class="mb-4">
                <?= $editRecord ? 'Edit Medical Record' : 'Add Medical Record' ?>
            </h5>

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= escPatient($_SESSION['patient_csrf']) ?>">

                <input type="hidden"
                       name="action"
                       value="<?= $editRecord ? 'edit_record' : 'add_record' ?>">

                <input type="hidden"
                       name="patient_id"
                       value="<?= $patientId ?>">

                <input type="hidden"
                       name="record_id"
                       value="<?= (int)($editRecord['record_id'] ?? 0) ?>">

                <div class="row g-3">

                    <div class="col-md-6">

                        <label class="form-label">Diagnosis *</label>

                        <input type="text"
                               name="diagnosis"
                               class="form-control"
                               placeholder="Enter patient's diagnosis"
                               value="<?= escPatient($_POST['diagnosis'] ?? $editRecord['diagnosis'] ?? '') ?>"
                               required>

                    </div>

                    <div class="col-md-6">

                        <label class="form-label">Visit Date *</label>

                        <input type="date"
                               name="visit_date"
                               class="form-control"
                               value="<?= escPatient($_POST['visit_date'] ?? $editRecord['visit_date'] ?? date('Y-m-d')) ?>"
                               required>

                    </div>

                    <div class="col-12">

                        <label class="form-label">
                            Description / Doctor's Notes
                        </label>

                        <textarea name="description"
                                  class="form-control"
                                  rows="4"
                                  placeholder="Enter symptoms and medical notes"><?= escPatient($_POST['description'] ?? $editRecord['description'] ?? '') ?></textarea>

                    </div>

                </div>

                <div class="mt-4 d-flex gap-2">

                    <button type="submit" class="btn btn-medical">
                        <i class="bi bi-save"></i>
                        <?= $editRecord ? 'Update Medical Record' : 'Save Medical Record' ?>
                    </button>

                    <a href="?patient_id=<?= $patientId ?>#patientDetails"
                       class="btn btn-outline-secondary">
                        Cancel
                    </a>

                </div>

            </form>

        </div>

        <?php endif; ?>

        <!-- ALLERGY INFORMATION -->

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

            <h5 class="mb-0">
                <i class="bi bi-shield-exclamation me-2"></i>
                Patient Allergy Information
            </h5>

            <a href="?patient_id=<?= $patientId ?>&amp;mode=allergy#allergyForm"
               class="btn btn-sm btn-outline-danger">
                <i class="bi bi-plus-circle"></i> Add Allergy
            </a>

        </div>

        <?php if ($allergies): ?>

            <div class="alert alert-danger">
                <strong>ALLERGY ALERT!</strong>
                Review patient allergies before prescribing medication.
            </div>

            <?php foreach ($allergies as $a): ?>

                <div class="allergy-card">

                    <h6 class="text-danger fw-bold">
                        <?= escPatient($a['allergy_name']) ?>
                    </h6>

                    <p class="mb-2">
                        <strong>Reaction:</strong>
                        <?= escPatient($a['reaction'] ?? '-') ?>
                    </p>

                    <p class="mb-2">
                        <strong>Severity:</strong>
                        <?= escPatient(ucfirst($a['severity'] ?? 'Unknown')) ?>
                    </p>

                    <p class="mb-0">
                        <strong>Notes:</strong>
                        <?= escPatient($a['notes'] ?? '-') ?>
                    </p>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="alert alert-secondary">
                No allergy information has been recorded.
                Please confirm the patient's allergy history.
            </div>

        <?php endif; ?>

        <!-- ADD ALLERGY FORM -->
        <?php if ($mode === 'allergy'): ?>

        <div class="record-form mb-4" id="allergyForm">

            <h5 class="mb-3">Add Patient Allergy</h5>

            <form method="POST">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= escPatient($_SESSION['patient_csrf']) ?>">

                <input type="hidden"
                       name="action"
                       value="add_allergy">

                <input type="hidden"
                       name="patient_id"
                       value="<?= $patientId ?>">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Allergy Name</label>
                        <input type="text"
                               name="allergy_name"
                               class="form-control"
                               placeholder="Example: Penicillin"
                               required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Reaction</label>
                        <input type="text"
                               name="reaction"
                               class="form-control"
                               placeholder="Enter allergy reaction"
                               required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Severity</label>
                        <select name="severity" class="form-select" required>
                            <option value="">-- Select Severity --</option>
                            <option value="mild">Mild</option>
                            <option value="moderate">Moderate</option>
                            <option value="severe">Severe</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes"
                                  class="form-control"
                                  rows="3"></textarea>
                    </div>

                </div>

                <div class="mt-3 d-flex gap-2">

                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-save"></i> Save Allergy
                    </button>

                    <a href="?patient_id=<?= $patientId ?>#patientDetails"
                       class="btn btn-outline-secondary">
                        Cancel
                    </a>

                </div>

            </form>

        </div>

        <?php endif; ?>

        <hr>

        <!-- MEDICAL HISTORY -->

        <div id="medicalHistory"
             class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

            <h5 class="mb-0">
                <i class="bi bi-clipboard2-pulse me-2"></i>
                Medical History
            </h5>

            <a href="?patient_id=<?= $patientId ?>&amp;mode=add#medicalForm"
               class="btn btn-medical">
                <i class="bi bi-plus-circle"></i>
                Add Medical Record
            </a>

        </div>

        <div class="table-responsive">

            <table class="table table-hover">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Visit Date</th>
                        <th>Doctor</th>
                        <th>Diagnosis</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($medicalRecords as $i => $r): ?>

                    <tr>

                        <td><?= $i + 1 ?></td>

                        <td><?= escPatient($r['visit_date']) ?></td>

                        <td><?= escPatient($r['doctor_name'] ?? '-') ?></td>

                        <td><?= escPatient($r['diagnosis']) ?></td>

                        <td><?= nl2br(escPatient($r['description'] ?? '-')) ?></td>

                        <td>

                            <?php if ((int)$r['doctor_id'] === $doctorId): ?>

                                <a href="?patient_id=<?= $patientId ?>&amp;mode=edit&amp;record_id=<?= (int)$r['record_id'] ?>#medicalForm"
                                   class="btn btn-sm btn-outline-primary">

                                    <i class="bi bi-pencil"></i> Edit

                                </a>

                            <?php else: ?>

                                <span class="text-muted small">View Only</span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (!$medicalRecords): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No medical records found.
                        </td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <hr>

        <a href="prescriptions.php?patient_id=<?= $patientId ?>"
           class="btn btn-danger">
            <i class="bi bi-capsule me-1"></i>
            Create Prescription
        </a>

    </div>

    <?php endif; ?>

</div>

<?php require_once '../includes/footer.php'; ?>