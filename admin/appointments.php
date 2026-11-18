<?php
require_once '../includes/auth.php';
requireRole(['admin']);

require_once '../config/database.php';

// Search and Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = $_GET['status'] ?? '';

$limit = 10;

$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0)
    ? (int)$_GET['page'] : 1;

$offset = ($page - 1) * $limit;

// Build WHERE condition
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(
        p.patient_name LIKE :patient
        OR u.full_name LIKE :doctor
        OR a.reason LIKE :reason
    )";

    $keyword = "%$search%";

    $params[':patient'] = $keyword;
    $params[':doctor'] = $keyword;
    $params[':reason'] = $keyword;
}

if (in_array($statusFilter, ['Upcoming', 'Pending', 'Completed', 'Cancelled'], true)) {
    $where[] = "a.status = :status";
    $params[':status'] = $statusFilter;
}

$whereSQL = count($where) > 0
    ? "WHERE " . implode(" AND ", $where)
    : "";

// Count records (LEFT JOIN users terus menggunakan a.doctor_id = u.user_id)
$countSql = "
    SELECT COUNT(*)
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN users u ON a.doctor_id = u.user_id
    $whereSQL
";

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);

$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Get appointment records
$sql = "
    SELECT
        a.appointment_id,
        a.patient_id,
        a.doctor_id,
        a.appointment_date,
        a.reason,
        a.status,
        p.patient_name,
        COALESCE(NULLIF(u.full_name, ''), u.username, 'Unassigned') AS doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.patient_id
    LEFT JOIN users u ON a.doctor_id = u.user_id
    $whereSQL
    ORDER BY a.appointment_date DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get patients
$patientStmt = $pdo->query("
    SELECT patient_id, patient_name
    FROM patients
    ORDER BY patient_name ASC
");

$patients = $patientStmt->fetchAll(PDO::FETCH_ASSOC);

// Get doctors dari jadual users + roles (doctor_id = user_id)
$doctors = [];
try {
    $doctorStmt = $pdo->query("
        SELECT u.user_id AS doctor_id,
               COALESCE(NULLIF(u.full_name, ''), u.username) AS full_name
        FROM users u
        INNER JOIN roles r ON u.role_id = r.role_id
        WHERE LOWER(r.role_name) = 'doctor'
        ORDER BY full_name ASC
    ");
    $doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $doctorStmt = $pdo->query("
            SELECT u.user_id AS doctor_id,
                   COALESCE(NULLIF(u.full_name, ''), u.username) AS full_name
            FROM users u
            INNER JOIN roles r ON u.role_id = r.role_id
            WHERE LOWER(r.name) = 'doctor'
            ORDER BY full_name ASC
        ");
        $doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        $doctors = [];
    }
}

$pageTitle = "Appointment Management";

require_once '../includes/header.php';
?>

<!-- Notifications -->
<?php if (isset($_GET['msg'])): ?>

    <div class="alert alert-success alert-dismissible fade show" role="alert">

        <?php
        if ($_GET['msg'] === 'added') {
            echo "Appointment successfully added.";
        } elseif ($_GET['msg'] === 'updated') {
            echo "Appointment successfully updated.";
        } elseif ($_GET['msg'] === 'deleted') {
            echo "Appointment successfully deleted.";
        }
        ?>

        <button type="button" class="btn-close"
            data-bs-dismiss="alert"></button>
    </div>

<?php elseif (isset($_GET['err'])): ?>

    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        Operation failed. Please try again.
        <button type="button" class="btn-close"
            data-bs-dismiss="alert"></button>
    </div>

<?php endif; ?>


<!-- Search, Filter and Add -->
<div class="section-card mb-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">

        <form method="GET" action="appointments.php"
            class="d-flex flex-wrap gap-2 flex-grow-1">

            <div class="input-group" style="max-width: 350px;">

                <span class="input-group-text bg-white">
                    <i class="bi bi-search text-muted"></i>
                </span>

                <input type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search patient, doctor or reason..."
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">

            </div>

            <select name="status" class="form-select"
                style="max-width: 170px;">

                <option value="">All Status</option>

                <option value="Upcoming"
                    <?= $statusFilter === 'Upcoming' ? 'selected' : '' ?>>
                    Upcoming
                </option>

                <option value="Pending"
                    <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>
                    Pending
                </option>

                <option value="Completed"
                    <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>
                    Completed
                </option>

                <option value="Cancelled"
                    <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>
                    Cancelled
                </option>

            </select>

            <button type="submit" class="btn btn-outline-primary">
                Search
            </button>

            <?php if ($search !== '' || $statusFilter !== ''): ?>
                <a href="appointments.php" class="btn btn-outline-secondary">
                    Reset
                </a>
            <?php endif; ?>

        </form>

        <button class="btn btn-main text-nowrap"
            data-bs-toggle="modal"
            data-bs-target="#addAppointmentModal">

            <i class="bi bi-plus-lg me-1"></i>
            Add Appointment

        </button>

    </div>

</div>


<!-- Appointment Table -->
<div class="section-card">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Appointment ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Date & Time</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if (count($appointments) > 0): ?>

                <?php
                $no = $offset + 1;

                foreach ($appointments as $row):
                ?>

                <tr>

                    <td><?= $no++ ?></td>

                    <td class="fw-semibold text-primary">
                        #<?= (int)$row['appointment_id'] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['patient_name'] ?? '-') ?>
                    </td>

                    <td>
                        <?= $row['doctor_name'] !== 'Unassigned' ? 'Dr. ' . htmlspecialchars($row['doctor_name']) : '-' ?>
                    </td>

                    <td>
                        <?= date('d M Y, h:i A', strtotime($row['appointment_date'])) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['reason'] ?? '-') ?>
                    </td>

                    <td>

                        <?php
                        $statusClass = [
                            'Upcoming' => 'bg-info-subtle text-info-emphasis',
                            'Pending' => 'bg-warning-subtle text-warning-emphasis',
                            'Completed' => 'bg-success-subtle text-success-emphasis',
                            'Cancelled' => 'bg-danger-subtle text-danger-emphasis'
                        ];

                        $badgeClass = $statusClass[$row['status']] ?? 'bg-secondary-subtle text-secondary-emphasis';
                        ?>

                        <span class="badge rounded-pill <?= $badgeClass ?>">
                            <?= htmlspecialchars($row['status']) ?>
                        </span>

                    </td>

                    <td class="text-center">

                        <!-- Edit -->
                        <button type="button"
                            class="btn btn-sm btn-outline-primary edit-btn"

                            data-id="<?= (int)$row['appointment_id'] ?>"
                            data-patient="<?= (int)$row['patient_id'] ?>"
                            data-doctor="<?= (int)$row['doctor_id'] ?>"
                            data-date="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($row['appointment_date'])), ENT_QUOTES, 'UTF-8') ?>"
                            data-reason="<?= htmlspecialchars($row['reason'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            data-status="<?= htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8') ?>"

                            data-bs-toggle="modal"
                            data-bs-target="#editAppointmentModal">

                            <i class="bi bi-pencil-square"></i>

                        </button>

                        <!-- Delete -->
                        <a href="appointment_action.php?delete_id=<?= (int)$row['appointment_id'] ?>"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Are you sure you want to delete this appointment?');">

                            <i class="bi bi-trash"></i>

                        </a>

                    </td>

                </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        No appointment records found.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>

    <nav class="d-flex justify-content-end mt-3">

        <ul class="pagination pagination-sm mb-0">

            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link"
                    href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">
                    &laquo;
                </a>
            </li>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link"
                        href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">
                        <?= $i ?>
                    </a>
                </li>

            <?php endfor; ?>

            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link"
                    href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">
                    &raquo;
                </a>
            </li>

        </ul>

    </nav>

    <?php endif; ?>

</div>


<!-- ADD APPOINTMENT MODAL -->
<div class="modal fade" id="addAppointmentModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <form action="appointment_action.php"
            method="POST"
            class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">
                    Add New Appointment
                </h5>

                <button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <div class="mb-3">

                    <label class="form-label">Patient</label>

                    <select name="patient_id" class="form-select" required>

                        <option value="">Select Patient</option>

                        <?php foreach ($patients as $patient): ?>

                            <option value="<?= (int)$patient['patient_id'] ?>">
                                <?= htmlspecialchars($patient['patient_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="mb-3">

                    <label class="form-label">Doctor</label>

                    <select name="doctor_id" class="form-select" required>

                        <option value="">Select Doctor</option>

                        <?php foreach ($doctors as $doctor): ?>

                            <option value="<?= (int)$doctor['doctor_id'] ?>">
                                Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="mb-3">

                    <label class="form-label">Appointment Date & Time</label>

                    <input type="datetime-local"
                        name="appointment_date"
                        class="form-control"
                        required>

                </div>


                <div class="mb-3">

                    <label class="form-label">Reason</label>

                    <textarea name="reason"
                        class="form-control"
                        rows="3"
                        maxlength="255"
                        placeholder="Enter appointment reason"></textarea>

                </div>


                <div class="mb-3">

                    <label class="form-label">Status</label>

                    <select name="status" class="form-select" required>

                        <option value="Upcoming">Upcoming</option>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>

                    </select>

                </div>

            </div>


            <div class="modal-footer">

                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="submit" name="add_appointment"
                    class="btn btn-main">
                    Save Appointment
                </button>

            </div>

        </form>

    </div>

</div>


<!-- EDIT APPOINTMENT MODAL -->
<div class="modal fade" id="editAppointmentModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <form action="appointment_action.php"
            method="POST"
            class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">
                    Edit Appointment
                </h5>

                <button type="button" class="btn-close"
                    data-bs-dismiss="modal"></button>

            </div>


            <div class="modal-body">

                <input type="hidden"
                    name="appointment_id"
                    id="edit_appointment_id">


                <div class="mb-3">

                    <label class="form-label">Patient</label>

                    <select name="patient_id"
                        id="edit_patient_id"
                        class="form-select"
                        required>

                        <option value="">Select Patient</option>

                        <?php foreach ($patients as $patient): ?>

                            <option value="<?= (int)$patient['patient_id'] ?>">
                                <?= htmlspecialchars($patient['patient_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="mb-3">

                    <label class="form-label">Doctor</label>

                    <select name="doctor_id"
                        id="edit_doctor_id"
                        class="form-select"
                        required>

                        <option value="">Select Doctor</option>

                        <?php foreach ($doctors as $doctor): ?>

                            <option value="<?= (int)$doctor['doctor_id'] ?>">
                                Dr. <?= htmlspecialchars($doctor['full_name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="mb-3">

                    <label class="form-label">Appointment Date & Time</label>

                    <input type="datetime-local"
                        name="appointment_date"
                        id="edit_appointment_date"
                        class="form-control"
                        required>

                </div>


                <div class="mb-3">

                    <label class="form-label">Reason</label>

                    <textarea name="reason"
                        id="edit_reason"
                        class="form-control"
                        rows="3"
                        maxlength="255"></textarea>

                </div>


                <div class="mb-3">

                    <label class="form-label">Status</label>

                    <select name="status"
                        id="edit_status"
                        class="form-select"
                        required>

                        <option value="Upcoming">Upcoming</option>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>

                    </select>

                </div>

            </div>


            <div class="modal-footer">

                <button type="button" class="btn btn-light"
                    data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="submit" name="edit_appointment"
                    class="btn btn-main">
                    Update Appointment
                </button>

            </div>

        </form>

    </div>

</div>


<!-- EDIT MODAL SCRIPT -->
<script>

document.querySelectorAll('.edit-btn').forEach(btn => {

    btn.addEventListener('click', function () {

        document.getElementById('edit_appointment_id').value = this.dataset.id;

        document.getElementById('edit_patient_id').value = this.dataset.patient;

        document.getElementById('edit_doctor_id').value = this.dataset.doctor;

        document.getElementById('edit_appointment_date').value = this.dataset.date;

        document.getElementById('edit_reason').value = this.dataset.reason;

        document.getElementById('edit_status').value = this.dataset.status;

    });

});

</script>


<?php require_once '../includes/footer.php'; ?>
