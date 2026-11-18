<?php
require_once '../includes/auth.php';
requireRole(['admin']);
require_once '../config/database.php';

$pageTitle = "Medical Records & Billing Overview";
$search = trim($_GET['search'] ?? '');
$monthFilter = trim($_GET['month'] ?? '');
$billFilter = trim($_GET['bill_status'] ?? 'All');

// ==========================================
// 1. AMBIL DATA ALLERGIES DARI 'patient_allergies'
// ==========================================
$patientAllergies = [];
try {
    $stmtAllergy = $pdo->query("SELECT * FROM patient_allergies ORDER BY allergy_name ASC");
    foreach ($stmtAllergy->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $patientAllergies[$a['patient_id']][] = $a;
    }
} catch (PDOException $e) {
    $patientAllergies = [];
}

// ==========================================
// 2. AMBIL SEMUA DATA INVOICES (BILLING)
// ==========================================
$invoiceMap = [];
$totalPaidCount = 0;
$totalPendingCount = 0;
$totalUnpaidCount = 0;
$totalCollectedAmount = 0.0;

try {
    $allInvoices = $pdo->query("SELECT * FROM invoices ORDER BY invoice_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allInvoices as $inv) {
        $pId = $inv['patient_id'] ?? '';
        $statusRaw = trim((string)($inv['payment_status'] ?? $inv['status'] ?? 'Unpaid'));
        $statusNorm = ucfirst(strtolower($statusRaw));
        $amount = (float)($inv['total_amount'] ?? $inv['amount'] ?? 0);

        if ($statusNorm === 'Paid') {
            $totalPaidCount++;
            $totalCollectedAmount += $amount;
        } elseif ($statusNorm === 'Pending') {
            $totalPendingCount++;
        } else {
            $statusNorm = 'Unpaid';
            $totalUnpaidCount++;
        }

        if ($pId !== '' && !isset($invoiceMap[$pId])) {
            $invoiceMap[$pId] = [
                'invoice_id' => $inv['invoice_id'] ?? $inv['id'] ?? '',
                'amount'     => $amount,
                'status'     => $statusNorm
            ];
        }
    }
} catch (PDOException $e) {}

// ==========================================
// 3. AMBIL REKOD PERUBATAN TERKINI SETIAP PESAKIT
// ==========================================
$doctorMap = [];
try {
    $users = $pdo->query("SELECT user_id, full_name, username FROM users")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $doctorMap[$u['user_id']] = !empty($u['full_name']) ? $u['full_name'] : $u['username'];
    }
} catch (PDOException $e) {}

$medRecordMap = [];
try {
    $allMed = $pdo->query("SELECT * FROM medical_records ORDER BY visit_date DESC, record_id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allMed as $m) {
        $pId = $m['patient_id'] ?? '';
        if ($pId !== '' && !isset($medRecordMap[$pId])) {
            $medRecordMap[$pId] = [
                'visit_date'  => $m['visit_date'] ?? '',
                'diagnosis'   => $m['diagnosis'] ?? '',
                'description' => $m['description'] ?? '',
                'doctor_name' => $doctorMap[$m['doctor_id'] ?? ''] ?? '-'
            ];
        }
    }
} catch (PDOException $e) {}

// ==========================================
// 4. DATA UNTUK CHART (6 BULAN TERKINI)
// ==========================================
$monthsLabels = [];
$patientCounts = [];
$appointmentCounts = [];

for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $monthsLabels[] = date('M', strtotime("-$i months"));

    try {
        $stmtP = $pdo->prepare("SELECT COUNT(*) FROM patients WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
        $stmtP->execute([$ym]);
        $patientCounts[] = (int)$stmtP->fetchColumn();
    } catch (PDOException $e) {
        $patientCounts[] = 0;
    }

    try {
        $stmtA = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = ?");
        $stmtA->execute([$ym]);
        $appointmentCounts[] = (int)$stmtA->fetchColumn();
    } catch (PDOException $e) {
        $appointmentCounts[] = 0;
    }
}

// ==========================================
// 5. GABUNGKAN SEMUA PESAKIT DARI TABLE 'patients'
// ==========================================
$records = [];
$allergyAlertCount = 0;

try {
    $allPatients = $pdo->query("SELECT * FROM patients ORDER BY patient_id DESC")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allPatients as $p) {
        $pId = $p['patient_id'] ?? $p['id'] ?? '';
        $pName = $p['patient_name'] ?? $p['full_name'] ?? $p['name'] ?? ('Patient #' . $pId);
        $pPhone = $p['phone'] ?? $p['contact_number'] ?? '-';
        $pAllergies = $patientAllergies[$pId] ?? [];

        $medInfo = $medRecordMap[$pId] ?? [
            'visit_date'  => $p['created_at'] ?? '',
            'diagnosis'   => '',
            'description' => 'No clinical notes yet',
            'doctor_name' => '-'
        ];

        $billInfo = $invoiceMap[$pId] ?? [
            'invoice_id' => '-',
            'amount'     => 0,
            'status'     => 'No Bill'
        ];

        if ($monthFilter !== '' && !empty($medInfo['visit_date'])) {
            if (date('Y-m', strtotime($medInfo['visit_date'])) !== $monthFilter) {
                continue;
            }
        }

        if ($search !== '') {
            $allergyNames = implode(' ', array_column($pAllergies, 'allergy_name'));
            $haystack = strtolower($pName . ' ' . $pPhone . ' ' . $medInfo['diagnosis'] . ' ' . $medInfo['description'] . ' ' . $allergyNames);
            if (strpos($haystack, strtolower($search)) === false) {
                continue;
            }
        }

        if ($billFilter !== 'All' && strcasecmp($billInfo['status'], $billFilter) !== 0) {
            continue;
        }

        if (!empty($pAllergies)) {
            $allergyAlertCount++;
        }

        $records[] = [
            'patient_id'   => $pId,
            'patient_name' => $pName,
            'phone'        => $pPhone,
            'allergies'    => $pAllergies,
            'visit_date'   => $medInfo['visit_date'],
            'diagnosis'    => $medInfo['diagnosis'],
            'description'  => $medInfo['description'],
            'doctor_name'  => $medInfo['doctor_name'],
            'bill_amount'  => $billInfo['amount'],
            'bill_status'  => $billInfo['status']
        ];
    }
} catch (PDOException $e) {
    $records = [];
}

// ==========================================
// 6. ANALITIK TAMBAHAN (TOP DIAGNOSES & DOCTOR SUMMARY)
// ==========================================
$topDiagnoses = [];
try {
    $topDiagnoses = $pdo->query("
        SELECT diagnosis, COUNT(*) AS total 
        FROM medical_records 
        WHERE diagnosis IS NOT NULL AND diagnosis != ''
        GROUP BY diagnosis 
        ORDER BY total DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$doctorStats = [];
try {
    $doctorStats = $pdo->query("
        SELECT COALESCE(u.full_name, 'Unassigned Doctor') AS doctor_name, 
               COUNT(m.record_id) AS total_records,
               MAX(m.visit_date) AS last_visit
        FROM medical_records m
        LEFT JOIN users u ON m.doctor_id = u.user_id
        GROUP BY m.doctor_id, u.full_name
        ORDER BY total_records DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

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
    .allergy-alert-badge {
        display: inline-block;
        background: #fce4e4;
        color: #b91c1c;
        border: 1px solid #f7caca;
        border-radius: 6px;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 600;
        margin-top: 4px;
    }
    .allergy-detail-list {
        font-size: 12px;
        color: #b91c1c;
        margin-top: 3px;
    }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1">Medical Records & Billing Tracking</h4>
        <p class="text-muted small mb-0">Monitor registered patients, clinical records, allergy warnings, and invoice payment status.</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-printer me-1"></i> Print Report
    </button>
</div>

<?php if ($allergyAlertCount > 0): ?>
    <div class="allergy-banner">
        <div class="allergy-banner-icon">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <div>
            <h6 class="text-danger fw-bold mb-1">Patient Allergy Alert</h6>
            <p class="mb-0 small" style="color: #991b1b;">
                <strong><?= $allergyAlertCount ?></strong> patient(s) in this list have recorded allergies entered by the doctor.
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- KAD RINGKASAN STATUS BIL -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-number"><?= $totalPaidCount ?></div>
            <div class="stat-label">Paid Bills</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div class="stat-number"><?= $totalPendingCount ?></div>
            <div class="stat-label">Pending Bills</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fee2e2; color: #991b1b;">
                <i class="bi bi-exclamation-circle"></i>
            </div>
            <div class="stat-number"><?= $totalUnpaidCount ?></div>
            <div class="stat-label">Unpaid Bills</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="stat-number">RM <?= number_format($totalCollectedAmount, 2) ?></div>
            <div class="stat-label">Total Collected</div>
        </div>
    </div>
</div>

<!-- BAHAGIAN 1: CHART (SYSTEM OVERVIEW) -->
<div class="section-card mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="mb-0">System Overview</h5>
        <span class="text-muted small">Last 6 Months Trend</span>
    </div>
    <div style="height: 260px; width: 100%;">
        <canvas id="systemOverviewChart"></canvas>
    </div>
</div>

<!-- BAHAGIAN 2: JADUAL PATIENT MEDICAL RECORDS & BILLING -->
<div class="section-card mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h5 class="mb-1">Patient Medical Records & Billing</h5>
            <div class="d-flex flex-wrap gap-1 mt-2">
                <?php foreach (['All', 'Paid', 'Pending', 'Unpaid', 'No Bill'] as $bStatus): ?>
                    <a href="?bill_status=<?= urlencode($bStatus) ?>&month=<?= urlencode($monthFilter) ?>&search=<?= urlencode($search) ?>"
                       class="btn btn-sm <?= $billFilter === $bStatus ? 'btn-main' : 'btn-outline-secondary' ?>">
                        <?= $bStatus ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="GET" class="d-flex flex-wrap gap-2">
            <input type="hidden" name="bill_status" value="<?= htmlspecialchars($billFilter) ?>">
            <input type="month" name="month" class="form-control form-control-sm" style="width: auto;" value="<?= htmlspecialchars($monthFilter) ?>">
            <input type="search" name="search" class="form-control form-control-sm" style="width: 220px;" placeholder="Search patient, allergy or diagnosis..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-main btn-sm"><i class="bi bi-search"></i> Filter</button>
            <?php if ($search !== '' || $monthFilter !== '' || $billFilter !== 'All'): ?>
                <a href="medical_records.php" class="btn btn-outline-secondary btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Patient Name & Allergy Status</th>
                    <th>Visit / Reg. Date</th>
                    <th>Diagnosis</th>
                    <th>Notes</th>
                    <th>Doctor</th>
                    <th>Bill Amount</th>
                    <th>Payment Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $index => $row): 
                    $st = $row['bill_status'];
                    $badgeClass = 'bg-secondary';
                    if ($st === 'Paid') $badgeClass = 'bg-success';
                    elseif ($st === 'Pending') $badgeClass = 'bg-warning text-dark';
                    elseif ($st === 'Unpaid') $badgeClass = 'bg-danger';

                    $hasAllergy = !empty($row['allergies']);
                ?>
                    <tr style="<?= $hasAllergy ? '--bs-table-bg: #fff8f8;' : '' ?>">
                        <td><?= $index + 1 ?></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($row['patient_name']) ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($row['phone']) ?></div>

                            <?php if ($hasAllergy): ?>
                                <span class="allergy-alert-badge">
                                    <i class="bi bi-exclamation-triangle-fill"></i> Allergy Warning
                                </span>
                                <div class="allergy-detail-list">
                                    <?php foreach ($row['allergies'] as $al): ?>
                                        <div>
                                            &bull; <strong><?= htmlspecialchars($al['allergy_name']) ?></strong>
                                            <?php if (!empty($al['severity'])): ?>
                                                (<?= htmlspecialchars(ucfirst($al['severity'])) ?>)
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($row['visit_date']) ? date('d/m/Y', strtotime($row['visit_date'])) : '-' ?></td>
                        <td>
                            <?php if (!empty($row['diagnosis'])): ?>
                                <span class="badge" style="background-color: #e5f4f3; color: #05668d;">
                                    <?= htmlspecialchars($row['diagnosis']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">Pending Checkup</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                        <td class="fw-semibold">
                            <?= $st !== 'No Bill' ? 'RM ' . number_format((float)$row['bill_amount'], 2) : '-' ?>
                        </td>
                        <td>
                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($st) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No patients found in the database.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- BAHAGIAN 3: TOP DIAGNOSES & DOCTOR SUMMARY -->
<div class="row g-4">
    <div class="col-lg-6">
        <div class="section-card h-100">
            <h5 class="mb-1">Top Recorded Diagnoses</h5>
            <p class="text-muted small mb-3">Most common health conditions recorded across all visits.</p>

            <?php if (!empty($topDiagnoses)): 
                $maxDiag = max(array_column($topDiagnoses, 'total'));
            ?>
                <?php foreach ($topDiagnoses as $diag): 
                    $pct = $maxDiag > 0 ? round(($diag['total'] / $maxDiag) * 100) : 0;
                ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold"><?= htmlspecialchars($diag['diagnosis']) ?></span>
                            <span class="text-muted"><?= (int)$diag['total'] ?> case(s)</span>
                        </div>
                        <div class="progress" style="height: 8px; background-color: #e8f5f5;">
                            <div class="progress-bar" role="progressbar" style="width: <?= $pct ?>%; background-color: #05668d;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted small mb-0">No diagnosis statistics available yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="section-card h-100">
            <h5 class="mb-1">Doctor Records Summary</h5>
            <p class="text-muted small mb-3">Total medical records logged by each attending doctor.</p>

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Doctor Name</th>
                            <th class="text-center">Total Records</th>
                            <th class="text-end">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctorStats as $ds): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($ds['doctor_name']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-info text-dark"><?= (int)$ds['total_records'] ?></span>
                                </td>
                                <td class="text-end text-muted">
                                    <?= !empty($ds['last_visit']) ? date('d/m/Y', strtotime($ds['last_visit'])) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($doctorStats)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">No doctor activity recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- CHART.JS CDN & SCRIPT -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('systemOverviewChart').getContext('2d');

const gradientPatients = ctx.createLinearGradient(0, 0, 0, 250);
gradientPatients.addColorStop(0, 'rgba(5, 102, 141, 0.28)');
gradientPatients.addColorStop(1, 'rgba(5, 102, 141, 0.01)');

const gradientAppointments = ctx.createLinearGradient(0, 0, 0, 250);
gradientAppointments.addColorStop(0, 'rgba(0, 168, 150, 0.22)');
gradientAppointments.addColorStop(1, 'rgba(0, 168, 150, 0.01)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($monthsLabels) ?>,
        datasets: [
            {
                label: 'Patients',
                data: <?= json_encode($patientCounts) ?>,
                borderColor: '#05668d',
                backgroundColor: gradientPatients,
                borderWidth: 2.5,
                pointBackgroundColor: '#05668d',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.25
            },
            {
                label: 'Appointments',
                data: <?= json_encode($appointmentCounts) ?>,
                borderColor: '#00a896',
                backgroundColor: gradientAppointments,
                borderWidth: 2.5,
                pointBackgroundColor: '#00a896',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.25
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                    padding: 20
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 },
                grid: { color: '#f0f4f4' }
            },
            x: {
                grid: { display: false }
            }
        }
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>