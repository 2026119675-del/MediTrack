<?php
require_once '../includes/auth.php';
requireRole(['receptionist']);
require_once '../config/database.php';

$pageTitle = "Medical Records Management";
$search = trim($_GET['search'] ?? '');
$successMessage = '';
$errorMessage = '';
$editPatientData = null;

// HANDLE UPDATE / SAVE RECORD UNTUK PESAKIT SEDIA ADA SAHAJA (TIADA ADD NEW RECORD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $recordId = (int)($_POST['record_id'] ?? 0);
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $visitDate = $_POST['visit_date'] ?? date('Y-m-d');

    try {
        if ($patientId > 0) {
            if ($recordId > 0) {
                $stmt = $pdo->prepare("UPDATE medical_records SET doctor_id=?, diagnosis=?, description=?, visit_date=? WHERE record_id=?");
                $stmt->execute([$doctorId, $diagnosis, $description, $visitDate, $recordId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO medical_records (patient_id, doctor_id, diagnosis, description, visit_date) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$patientId, $doctorId, $diagnosis, $description, $visitDate]);
            }
            header("Location: medical_records.php?updated=1");
            exit;
        } else {
            $errorMessage = "Invalid patient selected.";
        }
    } catch (PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

// HANDLE DELETE MEDICAL RECORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $recordId = (int)($_POST['record_id'] ?? 0);
    try {
        if ($recordId > 0) {
            $stmt = $pdo->prepare("DELETE FROM medical_records WHERE record_id = ?");
            $stmt->execute([$recordId]);
            header("Location: medical_records.php?deleted=1");
            exit;
        }
    } catch (PDOException $e) {
        $errorMessage = "Error deleting record: " . $e->getMessage();
    }
}

if (isset($_GET['updated'])) $successMessage = "Patient medical record updated successfully.";
if (isset($_GET['deleted'])) $successMessage = "Medical record cleared successfully.";

// LOAD ALLERGIES DARI TABLE 'patient_allergies' (SAMA SEPERTI DOCTOR/PATIENTS.PHP)
$patientAllergies = [];
try {
    $stmtAllergy = $pdo->query("SELECT patient_id, allergy_name, reaction, severity, notes FROM patient_allergies ORDER BY allergy_name ASC");
    foreach ($stmtAllergy->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $patientAllergies[(int)$a['patient_id']][] = $a;
    }
} catch (PDOException $e) {
    $errorMessage = "Unable to load allergy data: " . $e->getMessage();
}

// LOAD DOCTORS
$doctors = [];
$doctorMap = [];
try {
    $doctors = $pdo->query("
        SELECT u.user_id, u.full_name, u.username 
        FROM users u 
        INNER JOIN roles r ON u.role_id = r.role_id 
        WHERE LOWER(r.role_name) = 'doctor'
        ORDER BY u.full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $doctors = $pdo->query("
            SELECT u.user_id, u.full_name, u.username 
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.role_id 
            WHERE LOWER(r.name) = 'doctor'
            ORDER BY u.full_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
}

foreach ($doctors as $d) {
    $doctorMap[(int)$d['user_id']] = !empty($d['full_name']) ? $d['full_name'] : $d['username'];
}

// LOAD MEDICAL RECORDS TERKINI MENGIKUT patient_id
$medRecordMap = [];
try {
    $allMed = $pdo->query("SELECT * FROM medical_records ORDER BY visit_date DESC, record_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allMed as $m) {
        $pId = (int)$m['patient_id'];
        if (!isset($medRecordMap[$pId])) {
            $medRecordMap[$pId] = $m;
        }
    }
} catch (PDOException $e) {}

// LOAD PATIENT UNTUK EDIT / VIEW
if (isset($_GET['edit_patient'])) {
    $pId = (int)$_GET['edit_patient'];
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->execute([$pId]);
    $pRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pRow) {
        $mRow = $medRecordMap[$pId] ?? [];
        $editPatientData = [
            'patient_id'   => $pId,
            'patient_name' => $pRow['patient_name'],
            'record_id'    => $mRow['record_id'] ?? 0,
            'doctor_id'    => $mRow['doctor_id'] ?? '',
            'visit_date'   => $mRow['visit_date'] ?? date('Y-m-d'),
            'diagnosis'    => $mRow['diagnosis'] ?? '',
            'description'  => $mRow['description'] ?? ''
        ];
    }
}

// AMBIL SEMUA PESAKIT DARI TABLE 'patients'
$allPatients = $pdo->query("SELECT * FROM patients ORDER BY patient_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$rows = [];
$allergyAlertCount = 0;

foreach ($allPatients as $p) {
    $pId = (int)$p['patient_id'];
    $pName = $p['patient_name'] ?? '-';
    $mRow = $medRecordMap[$pId] ?? null;
    $pAllergies = $patientAllergies[$pId] ?? [];

    $diagnosis = $mRow['diagnosis'] ?? '';
    $notes = $mRow['description'] ?? '';

    if ($search !== '') {
        $allergyText = implode(' ', array_column($pAllergies, 'allergy_name'));
        $haystack = strtolower($pName . ' ' . $diagnosis . ' ' . $notes . ' ' . $allergyText);
        if (strpos($haystack, strtolower($search)) === false) {
            continue;
        }
    }

    if (!empty($pAllergies)) {
        $allergyAlertCount++;
    }

    $rows[] = [
        'patient_id'   => $pId,
        'patient_name' => $pName,
        'phone'        => $p['phone'] ?? '-',
        'allergies'    => $pAllergies,
        'record_id'    => (int)($mRow['record_id'] ?? 0),
        'doctor_id'    => (int)($mRow['doctor_id'] ?? 0),
        'visit_date'   => $mRow['visit_date'] ?? '',
        'diagnosis'    => $diagnosis,
        'description'  => $notes
    ];
}

require_once '../includes/header.php';
?>

<style>
    .allergy-banner {
        background: #fff1f2;
        border: 1px solid #fecdd3;
        border-left: 5px solid #dc3545;
        border-radius: 10px;
        padding: 16px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .allergy-banner-icon {
        width: 44px;
        height: 44px;
        flex-shrink: 0;
        background: #fce0e3;
        color: #dc3545;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4>Medical Records Management</h4>
        <p class="text-muted small mb-0">View, update, or remove patient medical records.</p>
    </div>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<?php if ($allergyAlertCount > 0): ?>
    <div class="allergy-banner">
        <div class="allergy-banner-icon">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div>
            <h6 class="text-danger fw-bold mb-1">Patient Allergy Warning</h6>
            <p class="mb-0 small" style="color: #991b1b;">
                <strong><?= $allergyAlertCount ?></strong> patient(s) in this list have recorded allergies entered by the doctor.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php if ($editPatientData): ?>
    <?php $editAllergies = $patientAllergies[$editPatientData['patient_id']] ?? []; ?>
    <div class="section-card mb-4">
        <h5 class="mb-3">Update Medical Record &mdash; <?= htmlspecialchars($editPatientData['patient_name']) ?></h5>

        <?php if (!empty($editAllergies)): ?>
            <div class="alert alert-danger mb-3">
                <div class="fw-bold mb-1">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> ALLERGY WARNING FOR THIS PATIENT!
                </div>
                <ul class="mb-0 small ps-3">
                    <?php foreach ($editAllergies as $ea): ?>
                        <li>
                            <strong><?= htmlspecialchars($ea['allergy_name']) ?></strong>
                            <?php if (!empty($ea['severity'])): ?>
                                (Severity: <span class="text-uppercase fw-bold"><?= htmlspecialchars($ea['severity']) ?></span>)
                            <?php endif; ?>
                            <?php if (!empty($ea['reaction'])): ?>
                                &mdash; Reaction: <?= htmlspecialchars($ea['reaction']) ?>
                            <?php endif; ?>
                            <?php if (!empty($ea['notes'])): ?>
                                &mdash; Notes: <?= htmlspecialchars($ea['notes']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="record_id" value="<?= (int)$editPatientData['record_id'] ?>">
            <input type="hidden" name="patient_id" value="<?= (int)$editPatientData['patient_id'] ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Patient Name</label>
                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($editPatientData['patient_name']) ?>" readonly>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Doctor</label>
                    <select name="doctor_id" class="form-select">
                        <option value="">-- Select Doctor --</option>
                        <?php foreach ($doctorMap as $dId => $dName): ?>
                            <option value="<?= (int)$dId ?>" <?= $editPatientData['doctor_id'] == $dId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Visit Date <span class="text-danger">*</span></label>
                    <input type="date" name="visit_date" class="form-control" value="<?= htmlspecialchars($editPatientData['visit_date']) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Diagnosis <span class="text-danger">*</span></label>
                    <input type="text" name="diagnosis" class="form-control" value="<?= htmlspecialchars($editPatientData['diagnosis']) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Description / Notes</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($editPatientData['description']) ?></textarea>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-main"><i class="bi bi-save me-1"></i> Update Record</button>
                <a href="medical_records.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
<?php endif; ?>

<div class="section-card">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
        <h5>Medical Records List</h5>
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" class="form-control" placeholder="Search patient or diagnosis..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-main"><i class="bi bi-search"></i> Search</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Patient Name</th>
                    <th>Allergy Status</th>
                    <th>Doctor</th>
                    <th>Visit Date</th>
                    <th>Diagnosis</th>
                    <th>Notes</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $r): 
                    $hasAllergy = !empty($r['allergies']);
                ?>
                    <tr style="<?= $hasAllergy ? '--bs-table-bg: #fff8f8;' : '' ?>">
                        <td><?= $index + 1 ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($r['patient_name']) ?></td>
                        <td>
                            <?php if ($hasAllergy): ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Allergy Alert
                                </span>
                                <?php foreach ($r['allergies'] as $pa): ?>
                                    <div class="small text-danger fw-semibold mt-1">
                                        &bull; <?= htmlspecialchars($pa['allergy_name']) ?>
                                        <?php if (!empty($pa['severity'])): ?>
                                            <span class="text-muted fw-normal">(<?= htmlspecialchars(ucfirst($pa['severity'])) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border">No Recorded Allergy</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($doctorMap[$r['doctor_id']] ?? '-') ?></td>
                        <td><?= !empty($r['visit_date']) ? htmlspecialchars($r['visit_date']) : '-' ?></td>
                        <td><?= !empty($r['diagnosis']) ? htmlspecialchars($r['diagnosis']) : '<span class="text-muted small">Not recorded yet</span>' ?></td>
                        <td><?= !empty($r['description']) ? htmlspecialchars($r['description']) : '-' ?></td>
                        <td class="text-nowrap">
                            <a href="?edit_patient=<?= (int)$r['patient_id'] ?>" class="btn btn-sm btn-outline-primary" title="View / Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($r['record_id'] > 0): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this medical record?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="record_id" value="<?= (int)$r['record_id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted">No patients found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>