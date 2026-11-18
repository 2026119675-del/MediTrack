<?php
require_once '../includes/auth.php';
requireRole(['receptionist']);
require_once '../config/database.php';

$pageTitle = "Prescription Management";
$search = trim($_GET['search'] ?? '');
$successMessage = '';
$errorMessage = '';
$editRecord = null;

// LOAD ALLERGIES DARI TABLE 'patient_allergies' (YANG DIMASUKKAN DOCTOR)
$patientAllergies = [];
try {
    $stmtAllergy = $pdo->query("SELECT patient_id, allergy_name, reaction, severity, notes FROM patient_allergies ORDER BY allergy_name ASC");
    foreach ($stmtAllergy->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $patientAllergies[(int)$a['patient_id']][] = $a;
    }
} catch (PDOException $e) {
    $patientAllergies = [];
}

// HANDLE SAVE (ADD & EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['prescription_id'] ?? 0);
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
    $medicine = trim($_POST['medicine_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $mcRequired = isset($_POST['mc_required']) ? 1 : 0;
    $mcDays = (int)($_POST['mc_days'] ?? 0);

    // SEMAK KONFLIK UBAT DENGAN ALAHAN PESAKIT
    $conflicts = [];
    foreach ($patientAllergies[$patientId] ?? [] as $al) {
        $aName = trim((string)($al['allergy_name'] ?? ''));
        if ($aName !== '' && (stripos($medicine, $aName) !== false || stripos($aName, $medicine) !== false)) {
            $conflicts[] = $aName;
        }
    }

    if (!empty($conflicts)) {
        $errorMessage = "ALLERGY ALERT! Patient is allergic to: " . implode(', ', $conflicts) . ". Prescription blocked.";
    } else {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE prescriptions SET patient_id=?, doctor_id=?, medicine_name=?, dosage=?, description=?, mc_required=?, mc_days=? WHERE prescription_id=?");
                $stmt->execute([$patientId, $doctorId, $medicine, $dosage, $description, $mcRequired, $mcDays, $id]);
                header("Location: prescriptions.php?updated=1");
                exit;
            } else {
                $stmt = $pdo->prepare("INSERT INTO prescriptions (patient_id, doctor_id, medicine_name, dosage, description, mc_required, mc_days, prescribed_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$patientId, $doctorId, $medicine, $dosage, $description, $mcRequired, $mcDays]);
                header("Location: prescriptions.php?added=1");
                exit;
            }
        } catch (PDOException $e) {
            $errorMessage = "Database error: " . $e->getMessage();
        }
    }
}

// HANDLE DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE prescription_id = ?");
        $stmt->execute([(int)$_POST['prescription_id']]);
        header("Location: prescriptions.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $errorMessage = "Error deleting prescription: " . $e->getMessage();
    }
}

if (isset($_GET['added']))   $successMessage = "Prescription added successfully.";
if (isset($_GET['updated'])) $successMessage = "Prescription updated successfully.";
if (isset($_GET['deleted'])) $successMessage = "Prescription deleted successfully.";

// LOAD FOR EDIT
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM prescriptions WHERE prescription_id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);
}

// LOAD PATIENTS
$patients = $pdo->query("SELECT patient_id, patient_name FROM patients ORDER BY patient_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// LOAD DOCTORS (JOIN users dengan roles guna role_id)
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
    } catch (PDOException $e2) {
        $errorMessage = "Doctor query error: " . $e2->getMessage();
    }
}

foreach ($doctors as $d) {
    $dId = $d['user_id'];
    $dName = !empty($d['full_name']) ? $d['full_name'] : $d['username'];
    $doctorMap[$dId] = $dName;
}

// LOAD PRESCRIPTIONS
$stmt = $pdo->prepare("
    SELECT pr.*, p.patient_name 
    FROM prescriptions pr 
    LEFT JOIN patients p ON pr.patient_id = p.patient_id 
    WHERE p.patient_name LIKE ? OR pr.medicine_name LIKE ?
    ORDER BY pr.prescribed_at DESC, pr.prescription_id DESC
");
$stmt->execute(['%' . $search . '%', '%' . $search . '%']);
$prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// KIRA BERAPA PESAKIT DALAM SENARAI PRESKRIPSI YANG ADA ALAHAN
$allergyPatientsInList = [];
foreach ($prescriptions as $pr) {
    $pId = (int)($pr['patient_id'] ?? 0);
    if (!empty($patientAllergies[$pId])) {
        $allergyPatientsInList[$pId] = true;
    }
}
$allergyBannerCount = count($allergyPatientsInList);

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
        <h4>Prescription Management</h4>
        <p class="text-muted small mb-0">View and manage patient prescriptions & MC.</p>
    </div>
    <a href="prescriptions.php?add=1" class="btn btn-main">
        <i class="bi bi-plus-lg"></i> Add Prescription
    </a>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<?php if ($allergyBannerCount > 0): ?>
    <div class="allergy-banner">
        <div class="allergy-banner-icon">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div>
            <h6 class="text-danger fw-bold mb-1">Patient Allergy Alert</h6>
            <p class="mb-0 small" style="color: #991b1b;">
                <strong><?= $allergyBannerCount ?></strong> patient(s) in this prescription list have recorded allergies entered by the doctor.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['add']) || $editRecord): ?>
<div class="section-card mb-4">
    <h5 class="mb-3"><?= $editRecord ? 'Edit Prescription' : 'Add Prescription' ?></h5>

    <!-- KOTAK AMARAN ALLERGY DINAMIK BILA PILIH PESAKIT -->
    <div id="dynamicAllergyAlert" class="alert alert-danger d-none mb-3">
        <div class="fw-bold mb-1">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> ALLERGY WARNING FOR SELECTED PATIENT!
        </div>
        <ul id="dynamicAllergyList" class="mb-0 small ps-3"></ul>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="prescription_id" value="<?= htmlspecialchars((string)($editRecord['prescription_id'] ?? '')) ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Patient <span class="text-danger">*</span></label>
                <select name="patient_id" id="patientSelect" class="form-select" required>
                    <option value="">-- Select Patient --</option>
                    <?php foreach ($patients as $p): 
                        $hasAl = !empty($patientAllergies[(int)$p['patient_id']]);
                    ?>
                        <option value="<?= $p['patient_id'] ?>" <?= ($editRecord['patient_id'] ?? ($_POST['patient_id'] ?? '')) == $p['patient_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['patient_name']) ?><?= $hasAl ? ' ⚠️ [ALLERGY]' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Doctor (Optional)</label>
                <select name="doctor_id" class="form-select">
                    <option value="">-- Select Doctor --</option>
                    <?php foreach ($doctorMap as $dId => $dName): ?>
                        <option value="<?= htmlspecialchars((string)$dId) ?>" <?= ($editRecord['doctor_id'] ?? ($_POST['doctor_id'] ?? '')) == $dId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Medicine Name <span class="text-danger">*</span></label>
                <input type="text" name="medicine_name" class="form-control" value="<?= htmlspecialchars($editRecord['medicine_name'] ?? ($_POST['medicine_name'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Dosage <span class="text-danger">*</span></label>
                <input type="text" name="dosage" class="form-control" placeholder="e.g. 500mg, 3x daily" value="<?= htmlspecialchars($editRecord['dosage'] ?? ($_POST['dosage'] ?? '')) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Medical Certificate (MC)</label>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="mc_required" id="mcCheck" value="1" <?= !empty($editRecord['mc_required']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mcCheck">MC Required</label>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">MC Days</label>
                <input type="number" name="mc_days" class="form-control" min="0" value="<?= htmlspecialchars((string)($editRecord['mc_days'] ?? 0)) ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Instructions / Description</label>
                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editRecord['description'] ?? ($_POST['description'] ?? '')) ?></textarea>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-main"><i class="bi bi-save me-1"></i> Save Prescription</button>
            <a href="prescriptions.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
const allergiesData = <?= json_encode($patientAllergies) ?>;
const patientSelect = document.getElementById('patientSelect');
const alertBox = document.getElementById('dynamicAllergyAlert');
const alertList = document.getElementById('dynamicAllergyList');

function updateAllergyWarning() {
    const pid = patientSelect.value;
    alertList.innerHTML = '';

    if (pid && allergiesData[pid] && allergiesData[pid].length > 0) {
        allergiesData[pid].forEach(item => {
            const li = document.createElement('li');
            let text = `<strong>${item.allergy_name}</strong>`;
            if (item.severity) text += ` (Severity: <span class="text-uppercase fw-bold">${item.severity}</span>)`;
            if (item.reaction) text += ` &mdash; Reaction: ${item.reaction}`;
            li.innerHTML = text;
            alertList.appendChild(li);
        });
        alertBox.classList.remove('d-none');
    } else {
        alertBox.classList.add('d-none');
    }
}

patientSelect.addEventListener('change', updateAllergyWarning);
updateAllergyWarning();
</script>
<?php endif; ?>

<div class="section-card">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
        <h5>Prescription List</h5>
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" class="form-control" placeholder="Search patient or medicine..." value="<?= htmlspecialchars($search) ?>">
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
                    <th>Medicine</th>
                    <th>Dosage</th>
                    <th>MC</th>
                    <th>Prescribed At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prescriptions as $index => $pr): 
                    $pAllergies = $patientAllergies[(int)($pr['patient_id'] ?? 0)] ?? [];
                    $hasAllergy = !empty($pAllergies);
                ?>
                    <tr style="<?= $hasAllergy ? '--bs-table-bg: #fff8f8;' : '' ?>">
                        <td><?= $index + 1 ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($pr['patient_name'] ?? '-') ?></td>
                        <td>
                            <?php if ($hasAllergy): ?>
                                <span class="badge bg-danger">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Allergy Alert
                                </span>
                                <?php foreach ($pAllergies as $pa): ?>
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
                        <td><?= htmlspecialchars($doctorMap[$pr['doctor_id'] ?? ''] ?? '-') ?></td>
                        <td><?= htmlspecialchars($pr['medicine_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($pr['dosage'] ?? '-') ?></td>
                        <td><?= !empty($pr['mc_required']) ? ($pr['mc_days'] . ' day(s)') : 'No' ?></td>
                        <td><?= htmlspecialchars($pr['prescribed_at'] ?? '-') ?></td>
                        <td class="text-nowrap">
                            <a href="?edit=<?= (int)$pr['prescription_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this prescription?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="prescription_id" value="<?= (int)$pr['prescription_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($prescriptions)): ?>
                    <tr><td colspan="9" class="text-center text-muted">No prescriptions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>