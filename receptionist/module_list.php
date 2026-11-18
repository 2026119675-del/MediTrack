<?php
$table = $module['table'];
$pageTitle = $module['title'];
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['status'] ?? '');

$records = [];
$errorMessage = '';

try {
    $check = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));

    if (!$check->fetch()) {
        throw new Exception("Database table '$table' has not been created.");
    }

    $records = $pdo->query("SELECT * FROM `$table`")->fetchAll();

} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

$patientNames = [];

try {
    $patientRows = $pdo->query(
        "SELECT patient_id, patient_name FROM patients"
    )->fetchAll();

    foreach ($patientRows as $patient) {
        $patientNames[$patient['patient_id']] = $patient['patient_name'];
    }

} catch (Throwable $e) {
    // Patient lookup unavailable.
}

function displayModuleValue($row, $field, $patientNames) {
    if ($field === 'patient_name') {
        return $patientNames[$row['patient_id'] ?? ''] ?? '-';
    }

    $value = $row[$field] ?? '-';

    if ($value === null || $value === '') {
        return '-';
    }

    if (in_array($field, ['amount', 'total_amount', 'invoice_amount'])) {
        return 'RM ' . number_format((float)$value, 2);
    }

    return (string)$value;
}

$filteredRecords = [];

foreach ($records as $record) {

    $searchText = strtolower(implode(' ', array_map('strval', $record)));

    if (isset($record['patient_id'])) {
        $searchText .= ' ' . strtolower(
            $patientNames[$record['patient_id']] ?? ''
        );
    }

    if ($search !== '' && strpos($searchText, strtolower($search)) === false) {
        continue;
    }

    if ($filter !== '' && isset($record['status'])) {
        if (strtolower($record['status']) !== strtolower($filter)) {
            continue;
        }
    }

    $filteredRecords[] = $record;
}

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">

    <div>
        <h4><?= htmlspecialchars($module['heading']) ?></h4>

        <p class="text-muted small mb-0">
            <?= htmlspecialchars($module['description']) ?>
        </p>
    </div>

    <button class="btn btn-main"
            data-bs-toggle="modal"
            data-bs-target="#addModal">

        <i class="bi bi-plus-lg"></i>
        <?= htmlspecialchars($module['add_label']) ?>

    </button>

</div>

<?php if ($module['tabs']): ?>

<div class="d-flex flex-wrap gap-2 mb-3">

    <?php foreach ($module['tabs'] as $tab): ?>

        <a href="?status=<?= urlencode($tab['value']) ?>"
           class="btn btn-sm <?= $filter === $tab['value'] ? 'btn-main' : 'btn-outline-secondary' ?>">

            <?= htmlspecialchars($tab['label']) ?>

        </a>

    <?php endforeach; ?>

    <a href="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>"
       class="btn btn-sm btn-outline-secondary">
        All
    </a>

</div>

<?php endif; ?>

<div class="section-card">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">

        <div>
            <h5><?= htmlspecialchars($module['list_title']) ?></h5>

            <small class="text-muted">
                <?= count($filteredRecords) ?> record(s)
            </small>
        </div>

        <form method="GET" class="d-flex gap-2">

            <?php if ($filter !== ''): ?>
                <input type="hidden" name="status"
                       value="<?= htmlspecialchars($filter) ?>">
            <?php endif; ?>

            <input type="search"
                   name="search"
                   class="form-control"
                   placeholder="Search..."
                   value="<?= htmlspecialchars($search) ?>">

            <button type="submit" class="btn btn-main">
                <i class="bi bi-search"></i>
                Search
            </button>

        </form>

    </div>

    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-warning">
            <i class="bi bi-info-circle"></i>
            <?= htmlspecialchars($errorMessage) ?>
        </div>

    <?php else: ?>

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>
                <tr>

                    <?php foreach ($module['columns'] as $field => $label): ?>

                        <th><?= htmlspecialchars($label) ?></th>

                    <?php endforeach; ?>

                    <th>Action</th>

                </tr>
            </thead>

            <tbody>

            <?php foreach ($filteredRecords as $index => $record): ?>

                <tr>

                    <?php foreach ($module['columns'] as $field => $label): ?>

                        <td>
                            <?= htmlspecialchars(
                                displayModuleValue($record, $field, $patientNames)
                            ) ?>
                        </td>

                    <?php endforeach; ?>

                    <td class="text-nowrap">

                        <button class="btn btn-sm btn-outline-info"
                                data-bs-toggle="modal"
                                data-bs-target="#view<?= $index ?>">

                            <i class="bi bi-eye"></i>

                        </button>

                        <button class="btn btn-sm btn-outline-primary"
                                onclick="alert('Edit requires database field mapping.')">

                            <i class="bi bi-pencil"></i>

                        </button>

                        <button class="btn btn-sm btn-outline-danger"
                                onclick="alert('Delete requires database field mapping.')">

                            <i class="bi bi-trash"></i>

                        </button>

                    </td>

                </tr>

                <!-- View Details Modal -->

                <div class="modal fade" id="view<?= $index ?>" tabindex="-1">

                    <div class="modal-dialog">

                        <div class="modal-content">

                            <div class="modal-header">

                                <h5 class="modal-title">Record Details</h5>

                                <button class="btn-close"
                                        data-bs-dismiss="modal"></button>

                            </div>

                            <div class="modal-body">

                                <?php foreach ($record as $field => $value): ?>

                                    <p>
                                        <strong>
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?>:
                                        </strong>

                                        <?= htmlspecialchars((string)($value ?? '-')) ?>
                                    </p>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

            <?php if (!$filteredRecords): ?>

                <tr>
                    <td colspan="<?= count($module['columns']) + 1 ?>"
                        class="text-center text-muted py-4">

                        No records found.

                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <?php endif; ?>

</div>

<!-- Add Record Modal -->

<div class="modal fade" id="addModal" tabindex="-1">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    <?= htmlspecialchars($module['add_label']) ?>
                </h5>

                <button class="btn-close"
                        data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <div class="alert alert-info mb-0">
                    This form interface is ready for the module.
                    Database saving will be connected after confirming
                    the table columns.
                </div>

            </div>

            <div class="modal-footer">

                <button class="btn btn-secondary"
                        data-bs-dismiss="modal">
                    Close
                </button>

            </div>

        </div>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>