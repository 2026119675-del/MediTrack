
<?php
require_once '../includes/auth.php';
requireRole(['admin']);

require_once '../config/database.php';

// Search & Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10;
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0)
    ? (int) $_GET['page'] : 1;

$offset = ($page - 1) * $limit;

// Get total records
if ($search !== '') {

    $searchParam = "%$search%";

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM patients
        WHERE patient_name LIKE :name
        OR ic_number LIKE :ic
        OR phone LIKE :phone
    ");

    $countStmt->execute([
        ':name' => $searchParam,
        ':ic' => $searchParam,
        ':phone' => $searchParam
    ]);

} else {

    $countStmt = $pdo->query("SELECT COUNT(*) FROM patients");

}

$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Get patient records
if ($search !== '') {

    $stmt = $pdo->prepare("
        SELECT *
        FROM patients
        WHERE patient_name LIKE :name
        OR ic_number LIKE :ic
        OR phone LIKE :phone
        ORDER BY patient_id ASC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':name', $searchParam, PDO::PARAM_STR);
    $stmt->bindValue(':ic', $searchParam, PDO::PARAM_STR);
    $stmt->bindValue(':phone', $searchParam, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

} else {

    $stmt = $pdo->prepare("
        SELECT *
        FROM patients
        ORDER BY patient_id ASC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

}

$stmt->execute();
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Patient Management";

require_once '../includes/header.php';
?>

<!-- Notifications -->
<?php if (isset($_GET['msg'])): ?>

    <div class="alert alert-success alert-dismissible fade show" role="alert">

        <?php
        if ($_GET['msg'] === 'added') {
            echo "Patient record successfully added.";
        } elseif ($_GET['msg'] === 'updated') {
            echo "Patient information successfully updated.";
        } elseif ($_GET['msg'] === 'deleted') {
            echo "Patient record successfully deleted.";
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


<!-- Search & Add Patient -->
<div class="section-card mb-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

        <form method="GET" action="patients.php"
            class="d-flex flex-grow-1" style="max-width: 400px;">

            <div class="input-group">

                <span class="input-group-text bg-white">
                    <i class="bi bi-search text-muted"></i>
                </span>

                <input type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search by Name, IC or Phone..."
                    value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">

                <?php if ($search !== ''): ?>
                    <a href="patients.php"
                        class="btn btn-outline-secondary">
                        Reset
                    </a>
                <?php endif; ?>

            </div>
        </form>

        <button class="btn btn-main text-nowrap"
            data-bs-toggle="modal"
            data-bs-target="#addPatientModal">

            <i class="bi bi-plus-lg me-1"></i>
            Add Patient

        </button>

    </div>
</div>


<!-- Patient Table -->
<div class="section-card">

    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead>
                <tr>
                    <th>No.</th>
                    <th>Patient ID</th>
                    <th>Name</th>
                    <th>IC No.</th>
                    <th>Gender</th>
                    <th>Date of Birth</th>
                    <th>Phone</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>

            <tbody>

                <?php if (count($patients) > 0): ?>

                    <?php
                    $no = $offset + 1;

                    foreach ($patients as $row):
                    ?>

                    <tr>

                        <td><?= $no++ ?></td>

                        <td class="fw-semibold text-primary">
                            <?= htmlspecialchars((string)$row['patient_id']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['patient_name'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['ic_number'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(ucfirst($row['gender'] ?? '-')) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['date_of_birth'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['phone'] ?? '-') ?>
                        </td>

                        <td class="text-center">

                            <!-- Edit Button -->
                            <button type="button"
                                class="btn btn-sm btn-outline-primary edit-btn"

                                data-id="<?= (int)$row['patient_id'] ?>"
                                data-name="<?= htmlspecialchars($row['patient_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-ic="<?= htmlspecialchars($row['ic_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-gender="<?= htmlspecialchars($row['gender'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-dob="<?= htmlspecialchars($row['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-phone="<?= htmlspecialchars($row['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-email="<?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-address="<?= htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-emergency="<?= htmlspecialchars($row['emergency_contact'] ?? '', ENT_QUOTES, 'UTF-8') ?>"

                                data-bs-toggle="modal"
                                data-bs-target="#editPatientModal">

                                <i class="bi bi-pencil-square"></i>

                            </button>


                            <!-- Delete Button -->
                            <a href="patient_action.php?delete_id=<?= (int)$row['patient_id'] ?>"
                                class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Are you sure you want to delete this patient?');">

                                <i class="bi bi-trash"></i>

                            </a>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8"
                            class="text-center text-muted py-4">

                            No patient records found.

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

            <!-- Previous -->
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">

                <a class="page-link"
                    href="?page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search) ?>">

                    &laquo;

                </a>

            </li>


            <!-- Page Numbers -->
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">

                    <a class="page-link"
                        href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">

                        <?= $i ?>

                    </a>

                </li>

            <?php endfor; ?>


            <!-- Next -->
            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">

                <a class="page-link"
                    href="?page=<?= min($totalPages, $page + 1) ?>&search=<?= urlencode($search) ?>">

                    &raquo;

                </a>

            </li>

        </ul>

    </nav>

    <?php endif; ?>

</div>


<!-- ADD PATIENT MODAL -->
<div class="modal fade" id="addPatientModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <form action="patient_action.php"
            method="POST"
            class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">
                    Add New Patient
                </h5>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>


            <div class="modal-body">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">Full Name</label>

                        <input type="text"
                            name="patient_name"
                            class="form-control"
                            required>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">IC Number</label>

                        <input type="text"
                            name="ic_number"
                            class="form-control">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Gender</label>

                        <select name="gender" class="form-select">

                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>

                        </select>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Date of Birth</label>

                        <input type="date"
                            name="date_of_birth"
                            class="form-control">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Phone Number</label>

                        <input type="text"
                            name="phone"
                            class="form-control">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Email</label>

                        <input type="email"
                            name="email"
                            class="form-control">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Emergency Contact</label>

                        <input type="text"
                            name="emergency_contact"
                            class="form-control">

                    </div>


                    <div class="col-12 mb-3">

                        <label class="form-label">Address</label>

                        <textarea name="address"
                            class="form-control"
                            rows="3"></textarea>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="submit"
                    name="add_patient"
                    class="btn btn-main">

                    Save Patient

                </button>

            </div>

        </form>

    </div>

</div>


<!-- EDIT PATIENT MODAL -->
<div class="modal fade" id="editPatientModal" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <form action="patient_action.php"
            method="POST"
            class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">
                    Edit Patient
                </h5>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>


            <div class="modal-body">

                <input type="hidden"
                    name="patient_id"
                    id="edit_id">


                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label">Full Name</label>

                        <input type="text"
                            name="patient_name"
                            id="edit_name"
                            class="form-control"
                            required>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">IC Number</label>

                        <input type="text"
                            name="ic_number"
                            id="edit_ic"
                            class="form-control">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Gender</label>

                        <select name="gender"
                            id="edit_gender"
                            class="form-select">

                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>

                        </select>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Date of Birth</label>

                        <input type="date"
                            name="date_of_birth"
                            id="edit_dob"
                            class="form-control">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Phone Number</label>

                        <input type="text"
                            name="phone"
                            id="edit_phone"
                            class="form-control">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Email</label>

                        <input type="email"
                            name="email"
                            id="edit_email"
                            class="form-control">

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="form-label">Emergency Contact</label>

                        <input type="text"
                            name="emergency_contact"
                            id="edit_emergency"
                            class="form-control">

                    </div>


                    <div class="col-12 mb-3">

                        <label class="form-label">Address</label>

                        <textarea name="address"
                            id="edit_address"
                            class="form-control"
                            rows="3"></textarea>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="submit"
                    name="edit_patient"
                    class="btn btn-main">

                    Update Patient

                </button>

            </div>

        </form>

    </div>

</div>


<!-- EDIT MODAL SCRIPT -->
<script>

document.querySelectorAll('.edit-btn').forEach(btn => {

    btn.addEventListener('click', function () {

        document.getElementById('edit_id').value = this.dataset.id;

        document.getElementById('edit_name').value = this.dataset.name;

        document.getElementById('edit_ic').value = this.dataset.ic;

        document.getElementById('edit_gender').value = this.dataset.gender;

        document.getElementById('edit_dob').value = this.dataset.dob;

        document.getElementById('edit_phone').value = this.dataset.phone;

        document.getElementById('edit_email').value = this.dataset.email;

        document.getElementById('edit_address').value = this.dataset.address;

        document.getElementById('edit_emergency').value = this.dataset.emergency;

    });

});

</script>


<?php require_once '../includes/footer.php'; ?>
