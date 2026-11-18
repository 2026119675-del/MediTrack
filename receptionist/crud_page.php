<?php
require_once '../includes/auth.php';
requireRole(['receptionist']);
require_once '../config/database.php';

if (!isset($moduleConfig) || !is_array($moduleConfig)) {
    exit('Module configuration is missing.');
}

$table = $moduleConfig['table'];
$primaryKey = $moduleConfig['primary_key'];
$pageTitle = $moduleConfig['title'];
$fields = $moduleConfig['fields'];
$listColumns = $moduleConfig['list_columns'];

$message = '';
$error = '';
$patients = [];
$doctors = [];
$editRecord = null;

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Load patient list for dropdowns
try {
    $patients = $pdo->query(
        "SELECT patient_id, patient_name FROM patients ORDER BY patient_name"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    $error = 'Unable to load patients. Check the patients table.';
}

// Load doctor list for dropdowns
try {
    $doctors = $pdo->query(
        "SELECT user_id AS doctor_id, full_name AS doctor_name FROM users WHERE role = 'doctor' ORDER BY full_name"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $ex) {
    try {
        $doctors = $pdo->query(
            "SELECT doctor_id, doctor_name FROM doctors ORDER BY doctor_name"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex2) {
        $doctors = [];
    }
}

// Handle Add, Edit and Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete') {
            $id = (int)($_POST['record_id'] ?? 0);

            if ($id > 0) {
                $stmt = $pdo->prepare(
                    "DELETE FROM `$table` WHERE `$primaryKey` = ?"
                );
                $stmt->execute([$id]);
            }

            header('Location: ' . basename($_SERVER['PHP_SELF']) . '?deleted=1');
            exit;
        }

        if ($action === 'save') {
            $id = (int)($_POST['record_id'] ?? 0);
            $values = [];

            foreach ($fields as $field) {
                $name = $field['name'];
                $type = $field['type'] ?? 'text';

                if ($type === 'checkbox') {
                    $values[$name] = isset($_POST[$name]) ? 1 : 0;
                } else {
                    $rawVal = trim($_POST[$name] ?? '');
                    if (!empty($field['required']) && $rawVal === '') {
                        throw new Exception($field['label'] . ' is required.');
                    }
                    // Convert empty optional foreign keys / dates to NULL
                    $values[$name] = ($rawVal === '') ? null : $rawVal;
                }
            }

            // Validate selected patient
            if (isset($values['patient_id'])) {
                $check = $pdo->prepare(
                    "SELECT COUNT(*) FROM patients WHERE patient_id = ?"
                );
                $check->execute([(int)$values['patient_id']]);

                if ((int)$check->fetchColumn() === 0) {
                    throw new Exception('Please select a valid patient.');
                }
            }

            if ($id > 0) {
                $setParts = [];

                foreach (array_keys($values) as $name) {
                    $setParts[] = "`$name` = ?";
                }

                $sql = "UPDATE `$table` SET "
                     . implode(', ', $setParts)
                     . " WHERE `$primaryKey` = ?";

                $valuesList = array_values($values);
                $valuesList[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($valuesList);

                header('Location: ' . basename($_SERVER['PHP_SELF']) . '?updated=1');
                exit;

            } else {
                $columnNames = array_map(
                    fn($name) => "`$name`",
                    array_keys($values)
                );

                $placeholders = array_fill(0, count($values), '?');

                $sql = "INSERT INTO `$table` ("
                     . implode(',', $columnNames)
                     . ") VALUES ("
                     . implode(',', $placeholders)
                     . ")";

                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($values));

                header('Location: ' . basename($_SERVER['PHP_SELF']) . '?added=1');
                exit;
            }
        }

    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

// Success messages
if (isset($_GET['added'])) {
    $message = 'Record added successfully.';
} elseif (isset($_GET['updated'])) {
    $message = 'Record updated successfully.';
} elseif (isset($_GET['deleted'])) {
    $message = 'Record deleted successfully.';
}

// Load record for editing
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        "SELECT * FROM `$table` WHERE `$primaryKey` = ?"
    );
    $stmt->execute([(int)$_GET['edit']]);
    $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$editRecord) {
        $error = 'Record not found.';
    }
}

// Search records
$search = trim($_GET['search'] ?? '');
$searchConditions = [];
$params = [];

foreach ($listColumns as $column) {
    if ($column['name'] === 'patient_name') {
        $searchConditions[] = 'p.patient_name LIKE ?';
    } else {
        $searchConditions[] = "CAST(t.`{$column['name']}` AS CHAR) LIKE ?";
    }

    $params[] = '%' . $search . '%';
}

$sql = "SELECT t.*, p.patient_name
        FROM `$table` t
        LEFT JOIN patients p ON p.patient_id = t.patient_id";

if ($search !== '' && $searchConditions) {
    $sql .= ' WHERE (' . implode(' OR ', $searchConditions) . ')';
}

$sql .= " ORDER BY t.`$primaryKey` DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h4 class="mb-1"><?= e($pageTitle) ?></h4>
        <p class="text-muted small mb-0">
            Manage and update <?= e(strtolower($pageTitle)) ?>.
        </p>
    </div>

    <a href="<?= e(basename($_SERVER['PHP_SELF'])) ?>?add=1"
       class="btn btn-main">
        <i class="bi bi-plus-lg me-1"></i>
        <?= e($moduleConfig['add_label']) ?>
    </a>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['add']) || $editRecord): ?>

<div class="section-card mb-4">
    <h5 class="mb-3">
        <?= $editRecord ? 'Edit Record' : e($moduleConfig['add_label']) ?>
    </h5>

    <form method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="record_id"
               value="<?= e($editRecord[$primaryKey] ?? '') ?>">

        <div class="row g-3">

            <?php foreach ($fields as $field):
                $name = $field['name'];
                $type = $field['type'] ?? 'text';
                $value = $editRecord[$name] ?? ($field['default'] ?? '');
            ?>

            <div class="col-md-6">

                <label class="form-label">
                    <?= e($field['label']) ?>
                    <?php if (!empty($field['required'])): ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>

                <?php if ($type === 'patient'): ?>

                    <select name="<?= e($name) ?>"
                            class="form-select"
                            <?= !empty($field['required']) ? 'required' : '' ?>>
                        <option value="">Select Patient</option>

                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= e($patient['patient_id']) ?>"
                                <?= (string)$value === (string)$patient['patient_id'] ? 'selected' : '' ?>>
                                <?= e($patient['patient_name']) ?>
                                (ID: <?= e($patient['patient_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($type === 'doctor'): ?>

                    <select name="<?= e($name) ?>"
                            class="form-select"
                            <?= !empty($field['required']) ? 'required' : '' ?>>
                        <option value="">Select Doctor</option>

                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?= e($doctor['doctor_id']) ?>"
                                <?= (string)$value === (string)$doctor['doctor_id'] ? 'selected' : '' ?>>
                                <?= e($doctor['doctor_name']) ?>
                                (ID: <?= e($doctor['doctor_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($type === 'select'): ?>

                    <select name="<?= e($name) ?>"
                            class="form-select"
                            <?= !empty($field['required']) ? 'required' : '' ?>>
                        <?php foreach ($field['options'] as $option): ?>
                            <option value="<?= e($option) ?>"
                                <?= (string)$value === (string)$option ? 'selected' : '' ?>>
                                <?= e($option) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($type === 'textarea'): ?>

                    <textarea name="<?= e($name) ?>"
                              class="form-control"
                              rows="3"
                              <?= !empty($field['required']) ? 'required' : '' ?>><?= e($value) ?></textarea>

                <?php elseif ($type === 'checkbox'): ?>

                    <div class="form-check mt-2">
                        <input type="checkbox"
                               class="form-check-input"
                               name="<?= e($name) ?>"
                               id="<?= e($name) ?>"
                               value="1"
                               <?= !empty($value) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= e($name) ?>">
                            Yes
                        </label>
                    </div>

                <?php else: ?>

                    <input type="<?= e($type) ?>"
                           name="<?= e($name) ?>"
                           class="form-control"
                           value="<?= e($value) ?>"
                           <?= !empty($field['required']) ? 'required' : '' ?>
                           <?= isset($field['step']) ? 'step="' . e($field['step']) . '"' : '' ?>
                           <?= isset($field['min']) ? 'min="' . e($field['min']) . '"' : '' ?>>

                <?php endif; ?>

            </div>

            <?php endforeach; ?>

        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-main">
                <i class="bi bi-save me-1"></i> Save
            </button>

            <a href="<?= e(basename($_SERVER['PHP_SELF'])) ?>"
               class="btn btn-outline-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php endif; ?>

<div class="section-card">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h5 class="mb-1"><?= e($moduleConfig['list_title']) ?></h5>
            <small class="text-muted"><?= count($records) ?> record(s)</small>
        </div>

        <form method="GET" class="d-flex gap-2 search-box">
            <input type="search"
                   name="search"
                   class="form-control"
                   placeholder="Search..."
                   value="<?= e($search) ?>">

            <button class="btn btn-main">
                <i class="bi bi-search"></i>
            </button>

            <?php if ($search !== ''): ?>
                <a href="<?= e(basename($_SERVER['PHP_SELF'])) ?>"
                   class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">

            <thead>
                <tr>
                    <?php foreach ($listColumns as $column): ?>
                        <th><?= e($column['label']) ?></th>
                    <?php endforeach; ?>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$records): ?>
                <tr>
                    <td colspan="<?= count($listColumns) + 1 ?>"
                        class="text-center text-muted py-4">
                        No records found.
                    </td>
                </tr>
            <?php else: ?>

                <?php foreach ($records as $record): ?>
                    <tr>
                        <?php foreach ($listColumns as $column):
                            $name = $column['name'];
                            $value = $name === 'patient_name'
                                ? ($record['patient_name'] ?? '-')
                                : ($record[$name] ?? '-');

                            if ($name === 'amount' && is_numeric($value)) {
                                $value = 'RM ' . number_format((float)$value, 2);
                            }
                        ?>
                            <td><?= e($value) ?></td>
                        <?php endforeach; ?>

                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary"
                               href="?edit=<?= (int)$record[$primaryKey] ?>"
                               title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>

                            <form method="POST"
                                  class="d-inline"
                                  onsubmit="return confirm('Are you sure you want to delete this record?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="record_id"
                                       value="<?= (int)$record[$primaryKey] ?>">

                                <button class="btn btn-sm btn-outline-danger"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>