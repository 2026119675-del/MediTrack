<?php
require_once '../includes/auth.php';
requireRole(['receptionist']);
require_once '../config/database.php';

$pageTitle = "Billing & Payment";
$search = trim($_GET['search'] ?? '');
$successMessage = '';
$errorMessage = '';
$editRecord = null;

// HANDLE SAVE (ADD & EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id = (int)($_POST['invoice_id'] ?? 0);
    $patientId = (int)($_POST['patient_id'] ?? 0);
    $invoiceNo = trim($_POST['invoice_no'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $status = $_POST['status'] ?? 'Unpaid';
    $invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d');

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE invoices SET patient_id=?, invoice_no=?, amount=?, status=?, invoice_date=? WHERE invoice_id=?");
            $stmt->execute([$patientId, $invoiceNo, $amount, $status, $invoiceDate, $id]);
            header("Location: billing.php?updated=1");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO invoices (patient_id, invoice_no, amount, status, invoice_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$patientId, $invoiceNo, $amount, $status, $invoiceDate]);
            header("Location: billing.php?added=1");
            exit;
        }
    } catch (PDOException $e) {
        $errorMessage = "Database error: " . $e->getMessage();
    }
}

// HANDLE DELETE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM invoices WHERE invoice_id = ?");
        $stmt->execute([(int)$_POST['invoice_id']]);
        header("Location: billing.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $errorMessage = "Error deleting invoice: " . $e->getMessage();
    }
}

if (isset($_GET['added']))   $successMessage = "Invoice created successfully.";
if (isset($_GET['updated'])) $successMessage = "Invoice updated successfully.";
if (isset($_GET['deleted'])) $successMessage = "Invoice deleted successfully.";

// LOAD FOR EDIT
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);
}

$patients = $pdo->query("SELECT patient_id, patient_name FROM patients ORDER BY patient_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT i.*, p.patient_name 
    FROM invoices i 
    LEFT JOIN patients p ON i.patient_id = p.patient_id 
    WHERE p.patient_name LIKE ? OR i.invoice_no LIKE ?
    ORDER BY i.invoice_date DESC, i.invoice_id DESC
");
$stmt->execute(['%' . $search . '%', '%' . $search . '%']);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4>Billing & Payment</h4>
        <p class="text-muted small mb-0">Manage patient invoices and payment status.</p>
    </div>
    <a href="billing.php?add=1" class="btn btn-main">
        <i class="bi bi-plus-lg"></i> Create Invoice
    </a>
</div>

<?php if ($successMessage): ?><div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div><?php endif; ?>
<?php if ($errorMessage): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div><?php endif; ?>

<?php if (isset($_GET['add']) || $editRecord): ?>
<div class="section-card mb-4">
    <h5 class="mb-3"><?= $editRecord ? 'Edit Invoice' : 'Create Invoice' ?></h5>
    <form method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="invoice_id" value="<?= htmlspecialchars((string)($editRecord['invoice_id'] ?? '')) ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Patient <span class="text-danger">*</span></label>
                <select name="patient_id" class="form-select" required>
                    <option value="">-- Select Patient --</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['patient_id'] ?>" <?= ($editRecord['patient_id'] ?? '') == $p['patient_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['patient_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Invoice No. <span class="text-danger">*</span></label>
                <input type="text" name="invoice_no" class="form-control" value="<?= htmlspecialchars($editRecord['invoice_no'] ?? ('INV-' . date('Ymd') . '-' . rand(100, 999))) ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Amount (RM) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?= htmlspecialchars((string)($editRecord['amount'] ?? '')) ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                <input type="date" name="invoice_date" class="form-control" value="<?= htmlspecialchars($editRecord['invoice_date'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Unpaid" <?= ($editRecord['status'] ?? 'Unpaid') === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                    <option value="Paid" <?= ($editRecord['status'] ?? '') === 'Paid' ? 'selected' : '' ?>>Paid</option>
                </select>
            </div>
        </div>

        <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-main"><i class="bi bi-save me-1"></i> Save Invoice</button>
            <a href="billing.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="section-card">
    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
        <h5>Invoice List</h5>
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" class="form-control" placeholder="Search patient or invoice no..." value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-main"><i class="bi bi-search"></i> Search</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Patient Name</th>
                    <th>Invoice Date</th>
                    <th>Amount (RM)</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($inv['invoice_no'] ?? '-') ?></strong></td>
                        <td><?= htmlspecialchars($inv['patient_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($inv['invoice_date'] ?? '-') ?></td>
                        <td>RM <?= number_format((float)($inv['amount'] ?? 0), 2) ?></td>
                        <td>
                            <span class="badge <?= ($inv['status'] ?? '') === 'Paid' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= htmlspecialchars($inv['status'] ?? '-') ?>
                            </span>
                        </td>
                        <td class="text-nowrap">
                            <a href="?edit=<?= (int)$inv['invoice_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this invoice?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="invoice_id" value="<?= (int)$inv['invoice_id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>