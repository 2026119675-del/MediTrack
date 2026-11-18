<?php
require_once '../includes/auth.php';
requireRole(['receptionist']);
require_once '../config/database.php';

$pageTitle = "Patient Management";
$search = trim($_GET['search'] ?? '');
$successMessage = '';
$errorMessage = '';
$editPatient = null;

// HANDLE SAVE (ADD & EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['patient_id'] ?? 0);
    $data = [
        trim($_POST['patient_name'] ?? ''),
        trim($_POST['ic_number'] ?? ''),
        $_POST['gender'] ?? 'Male',
        !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null,
        trim($_POST['phone'] ?? ''),
        trim($_POST['email'] ?? ''),
        trim($_POST['address'] ?? ''),
        trim($_POST['emergency_contact'] ?? '')
    ];

    try {
        if ($id > 0) {
            $data[] = $id;
            $stmt = $pdo->prepare("UPDATE patients SET patient_name=?, ic_number=?, gender=?, date_of_birth=?, phone=?, email=?, address=?, emergency_contact=? WHERE patient_id=?");
            $stmt->execute($data);
            header("Location: patient.php?updated=1");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO patients (patient_name, ic_number, gender, date_of_birth, phone, email, address, emergency_contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute($data);
            header("Location: patient.php?added=1");
            exit;
        }
    } catch (PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

// HANDLE DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM patients WHERE patient_id = ?");
        $stmt->execute([(int)$_POST['patient_id']]);
        header("Location: patient.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $errorMessage = "Cannot delete patient (may have linked appointments/records).";
    }
}

if (isset($_GET['added']))   $successMessage = "Patient added successfully.";
if (isset($_GET['updated'])) $successMessage = "Patient updated successfully.";
if (isset($_GET['deleted'])) $successMessage = "Patient deleted successfully.";

// LOAD PATIENT FOR EDIT
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editPatient = $stmt->fetch(PDO::FETCH_ASSOC);
}

// LOAD PATIENTS LIST
$patients = [];
$selectedPatient = null;
$allergies = [];

try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_name LIKE ? OR ic_number LIKE ? ORDER BY patient_id DESC");
    $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (isset($_GET['patient_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE patient_id = ?");
        $stmt->execute([(int)$_GET['patient_id']]);
        $selectedPatient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedPatient) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM patient_allergies WHERE patient_id = ?");
                $stmt->execute([(int)$_GET['patient_id']]);
                $allergies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $allergies = [];
            }
        }
    }
} catch (PDOException $e) {
    $errorMessage = "Unable to load patient information.";
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4>Patient Management</h4>
        <p class="text-muted small mb-0">Manage registered patient information.</p>
    </div>
    <a href="patient.php?add=1" class="btn btn-main">
        <i class="bi bi-plus-lg"></i> Add Patient
    </a>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<!-- INLINE FORM UNTUK ADD / EDIT (TANPA MODAL) -->
<?php if (isset($_GET['add']) || $editPatient): ?>
<div class="section-card mb-4">
    <h5 class="mb-3"><?= $editPatient ? 'Edit Patient' : 'Add New Patient' ?></h5>
    <form method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="patient_id" value="<?= htmlspecialchars((string)($editPatient['patient_id'] ?? '')) ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="patient_name" class="form-control" value="<?= htmlspecialchars($editPatient['patient_name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">IC Number <span class="text-danger">*</span></label>
                <input type="text" name="ic_number" class="form-control" value="<?= htmlspecialchars($editPatient['ic_number'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <option value="Male" <?= ($editPatient['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($editPatient['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($editPatient['date_of_birth'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($editPatient['phone'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($editPatient['email'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Emergency Contact</label>
                <input type="text" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($editPatient['emergency_contact'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($editPatient['address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-main"><i class="bi bi-save me-1"></i> Save Patient</button>
            <a href="patient.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="section-card">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
        <h5>Patient List</h5>
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" class="form-control" placeholder="Search patient..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-main"><i class="bi bi-search"></i> Search</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>IC No.</th>
                    <th>Contact</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $index => $p): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars((string)$p['patient_id']) ?></td>
                        <td><?= htmlspecialchars($p['patient_name']) ?></td>
                        <td><?= htmlspecialchars($p['ic_number'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($p['phone'] ?? '-') ?></td>
                        <td class="text-nowrap">
                            <a href="?patient_id=<?= (int)$p['patient_id'] ?>" class="btn btn-sm btn-outline-info" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="?edit=<?= (int)$p['patient_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this patient?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="patient_id" value="<?= (int)$p['patient_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($patients)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No patients found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($selectedPatient): ?>
    <div class="section-card mt-4">
        <h5>Patient Details</h5>
        <p><strong>Patient ID:</strong> <?= htmlspecialchars((string)$selectedPatient['patient_id']) ?></p>
        <p><strong>Name:</strong> <?= htmlspecialchars($selectedPatient['patient_name']) ?></p>
        <p><strong>IC Number:</strong> <?= htmlspecialchars($selectedPatient['ic_number'] ?? '-') ?></p>
        <p><strong>Gender:</strong> <?= htmlspecialchars($selectedPatient['gender'] ?? '-') ?></p>
        <p><strong>Date of Birth:</strong> <?= htmlspecialchars($selectedPatient['date_of_birth'] ?? '-') ?></p>
        <p><strong>Phone:</strong> <?= htmlspecialchars($selectedPatient['phone'] ?? '-') ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($selectedPatient['email'] ?? '-') ?></p>
        <p><strong>Address:</strong> <?= htmlspecialchars($selectedPatient['address'] ?? '-') ?></p>
        <p><strong>Emergency Contact:</strong> <?= htmlspecialchars($selectedPatient['emergency_contact'] ?? '-') ?></p>
        <hr>
        <h5>Patient Allergy Information</h5>
        <?php if (!empty($allergies)): ?>
            <div class="alert alert-danger"><strong><i class="bi bi-exclamation-triangle-fill"></i> ALLERGY ALERT!</strong> This patient has recorded allergies.</div>
            <?php foreach ($allergies as $a): ?>
                <div class="border rounded p-3 mb-2">
                    <strong><?= htmlspecialchars($a['allergy_name'] ?? '-') ?></strong>
                    <p class="mb-1">Reaction: <?= htmlspecialchars($a['reaction'] ?? '-') ?></p>
                    <p class="mb-0">Severity: <?= htmlspecialchars($a['severity'] ?? '-') ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-secondary">No allergy information has been recorded.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>