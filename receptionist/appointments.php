<?php
require_once '../includes/auth.php';
requireRole(['receptionist']);
require_once '../config/database.php';

$pageTitle = "Appointment Management";
$search = trim($_GET['search'] ?? '');
$tab = $_GET['tab'] ?? 'All';
$successMessage = '';
$errorMessage = '';
$editRecord = null;

// HANDLE SAVE (ADD & EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['appointment_id'] ?? 0);
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $doctorId = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
    $appDate = $_POST['appointment_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $status = $_POST['status'] ?? 'Upcoming';

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE appointments SET patient_id=?, doctor_id=?, appointment_date=?, reason=?, status=? WHERE appointment_id=?");
            $stmt->execute([$patientId, $doctorId, $appDate, $reason, $status, $id]);
            header("Location: appointments.php?updated=1");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, reason, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$patientId, $doctorId, $appDate, $reason, $status]);
            header("Location: appointments.php?added=1");
            exit;
        }
    } catch (PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

// HANDLE DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE appointment_id = ?");
        $stmt->execute([(int)$_POST['appointment_id']]);
        header("Location: appointments.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $errorMessage = "Error deleting appointment: " . $e->getMessage();
    }
}

if (isset($_GET['added']))   $successMessage = "Appointment added successfully.";
if (isset($_GET['updated'])) $successMessage = "Appointment updated successfully.";
if (isset($_GET['deleted'])) $successMessage = "Appointment deleted successfully.";

// LOAD RECORD FOR EDIT
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);
}

// LOAD PATIENTS
$patients = $pdo->query("SELECT patient_id, patient_name, ic_number FROM patients ORDER BY patient_name ASC")->fetchAll(PDO::FETCH_ASSOC);

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

// LOAD APPOINTMENTS LIST (ASINGKAN IKUT STATUS SEBENAR DALAM DATABASE)
$sql = "SELECT a.*, p.patient_name 
        FROM appointments a 
        LEFT JOIN patients p ON a.patient_id = p.patient_id 
        WHERE (p.patient_name LIKE ? OR a.reason LIKE ?)";
$params = ['%' . $search . '%', '%' . $search . '%'];

if ($tab === 'Upcoming') {
    $sql .= " AND LOWER(TRIM(a.status)) = 'upcoming'";
} elseif ($tab === 'Completed') {
    $sql .= " AND LOWER(TRIM(a.status)) IN ('completed', 'past')";
} elseif ($tab === 'Cancelled') {
    $sql .= " AND LOWER(TRIM(a.status)) = 'cancelled'";
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4>Appointment Management</h4>
        <p class="text-muted small mb-0">Schedule and manage patient appointments.</p>
    </div>
    <a href="appointments.php?add=1" class="btn btn-main">
        <i class="bi bi-plus-lg"></i> New Appointment
    </a>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<?php if (isset($_GET['add']) || $editRecord): ?>
<div class="section-card mb-4">
    <h5 class="mb-3"><?= $editRecord ? 'Edit Appointment' : 'New Appointment' ?></h5>
    <form method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="appointment_id" value="<?= htmlspecialchars((string)($editRecord['appointment_id'] ?? '')) ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Patient <span class="text-danger">*</span></label>
                <select name="patient_id" class="form-select" required>
                    <option value="">-- Select Patient --</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['patient_id'] ?>" <?= ($editRecord['patient_id'] ?? '') == $p['patient_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['patient_name']) ?> (<?= htmlspecialchars($p['ic_number'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Doctor (Optional)</label>
                <select name="doctor_id" class="form-select">
                    <option value="">-- Select Doctor --</option>
                    <?php foreach ($doctorMap as $dId => $dName): ?>
                        <option value="<?= htmlspecialchars((string)$dId) ?>" <?= ($editRecord['doctor_id'] ?? '') == $dId ? 'selected' : '' ?>>
                            Dr. <?= htmlspecialchars($dName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Appointment Date & Time <span class="text-danger">*</span></label>
                <?php 
                    $dtVal = '';
                    if (!empty($editRecord['appointment_date'])) {
                        $dtVal = date('Y-m-d\TH:i', strtotime($editRecord['appointment_date']));
                    }
                ?>
                <input type="datetime-local" name="appointment_date" class="form-control" value="<?= htmlspecialchars($dtVal) ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <?php 
                        $currentStatus = $editRecord['status'] ?? 'Upcoming';
                        if (strtolower($currentStatus) === 'past') $currentStatus = 'Completed';
                    ?>
                    <?php foreach (['Upcoming', 'Completed', 'Cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= strcasecmp($currentStatus, $st) === 0 ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label">Reason <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="2" required><?= htmlspecialchars($editRecord['reason'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-main"><i class="bi bi-save me-1"></i> Save Appointment</button>
            <a href="appointments.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach (['All', 'Upcoming', 'Completed', 'Cancelled'] as $t): ?>
        <a href="?tab=<?= $t ?>" class="btn btn-sm <?= $tab === $t ? 'btn-main' : 'btn-outline-secondary' ?>"><?= $t ?></a>
    <?php endforeach; ?>
</div>

<div class="section-card">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
        <h5>Appointment List</h5>
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            <input type="search" name="search" class="form-control" placeholder="Search patient or reason..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-main"><i class="bi bi-search"></i> Search</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Patient Name</th>
                    <th>Doctor</th>
                    <th>Date & Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($appointments as $index => $row): ?>
                    <?php
                        $rawStatus = trim((string)($row['status'] ?? '-'));
                        $lowerStatus = strtolower($rawStatus);

                        if ($lowerStatus === 'upcoming') {
                            $displayStatus = 'Upcoming';
                            $badgeClass = 'bg-info-subtle text-info-emphasis';
                        } elseif ($lowerStatus === 'completed' || $lowerStatus === 'past') {
                            $displayStatus = 'Completed';
                            $badgeClass = 'bg-success-subtle text-success-emphasis';
                        } elseif ($lowerStatus === 'cancelled') {
                            $displayStatus = 'Cancelled';
                            $badgeClass = 'bg-danger-subtle text-danger-emphasis';
                        } else {
                            $displayStatus = $rawStatus;
                            $badgeClass = 'bg-secondary-subtle text-secondary-emphasis';
                        }
                    ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($row['patient_name'] ?? '-') ?></td>
                        <td><?= !empty($doctorMap[$row['doctor_id'] ?? '']) ? 'Dr. ' . htmlspecialchars($doctorMap[$row['doctor_id']]) : '-' ?></td>
                        <td><?= !empty($row['appointment_date']) ? date('d M Y, h:i A', strtotime($row['appointment_date'])) : '-' ?></td>
                        <td><?= htmlspecialchars($row['reason'] ?? '-') ?></td>
                        <td><span class="badge rounded-pill <?= $badgeClass ?>"><?= htmlspecialchars($displayStatus) ?></span></td>
                        <td class="text-nowrap">
                            <a href="?edit=<?= (int)$row['appointment_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this appointment?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="appointment_id" value="<?= (int)$row['appointment_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No appointments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>