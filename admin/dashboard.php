<?php
require_once '../includes/auth.php';
requireRole(['admin']);
require_once '../config/database.php';

// 1. TOTAL REGISTERED USERS
$totalUsers = 0;
try {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (PDOException $e) {}

// 2. TOTAL REGISTERED PATIENTS
$totalPatients = 0;
try {
    $totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
} catch (PDOException $e) {}

// 3. TOTAL MEDICAL RECORDS
$totalMedicalRecords = 0;
try {
    $totalMedicalRecords = (int)$pdo->query("SELECT COUNT(*) FROM medical_records")->fetchColumn();
} catch (PDOException $e) {}

// 4. PAYMENTS SUMMARY (TOTAL COLLECTED RM & PAID COUNT)
$totalPaymentsAmount = 0.0;
$paidInvoicesCount = 0;
try {
    $invoices = $pdo->query("SELECT * FROM invoices")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($invoices as $inv) {
        $status = strtolower(trim((string)($inv['payment_status'] ?? $inv['status'] ?? '')));
        $amount = (float)($inv['total_amount'] ?? $inv['amount'] ?? 0);
        if ($status === 'paid') {
            $totalPaymentsAmount += $amount;
            $paidInvoicesCount++;
        }
    }
} catch (PDOException $e) {}

// 5. DATA UNTUK CHART SYSTEM OVERVIEW (6 BULAN TERKINI)
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

$pageTitle = "Admin Dashboard";
require_once '../includes/header.php';
?>

<style>
    .dashboard-stat-link {
        text-decoration: none;
        color: inherit;
        display: block;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .dashboard-stat-link:hover {
        transform: translateY(-3px);
        color: inherit;
    }
    .dashboard-stat-link:hover .stat-card {
        box-shadow: 0 6px 16px rgba(5, 102, 141, 0.12);
    }
    .quick-access-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border: 1px solid #e6efef;
        border-radius: 10px;
        background: #f8fbfb;
        color: #1f2937;
        text-decoration: none;
        transition: all 0.15s ease;
    }
    .quick-access-item:hover {
        background: #e8f5f5;
        border-color: #05668d;
        color: #05668d;
        transform: translateX(3px);
    }
    .quick-access-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        background: #e5f4f3;
        color: #05668d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
</style>

<!-- 1. WELCOME BACK BANNER -->
<div class="welcome-card">
    <h3 class="mb-1">
        Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?>!
    </h3>
    <p class="mb-0 opacity-75">
        Manage and monitor your MediTrack system from one place.
    </p>
</div>

<!-- 2. BARIS 4 KAD STATISTIK: TOTAL USERS | TOTAL PATIENTS | MEDICAL RECORDS | PAYMENTS -->
<div class="row g-3 mb-4">

    <!-- Total Users -->
    <div class="col-md-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-number"><?= $totalUsers ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>

    <!-- Total Patients -->
    <div class="col-md-6 col-xl-3">
        <a href="patients.php" class="dashboard-stat-link h-100">
            <div class="stat-card h-100">
                <div class="stat-icon" style="background: #e0f2fe; color: #0369a1;">
                    <i class="bi bi-person-heart"></i>
                </div>
                <div class="stat-number"><?= $totalPatients ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
        </a>
    </div>

    <!-- Medical Records -->
    <div class="col-md-6 col-xl-3">
        <a href="medical_records.php" class="dashboard-stat-link h-100">
            <div class="stat-card h-100">
                <div class="stat-icon" style="background: #fef3c7; color: #92400e;">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
                <div class="stat-number"><?= $totalMedicalRecords ?></div>
                <div class="stat-label">Medical Records</div>
            </div>
        </a>
    </div>

    <!-- Payments -->
    <div class="col-md-6 col-xl-3">
        <a href="medical_records.php" class="dashboard-stat-link h-100">
            <div class="stat-card h-100">
                <div class="stat-icon" style="background: #d1fae5; color: #065f46;">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-number">RM <?= number_format($totalPaymentsAmount, 2) ?></div>
                <div class="stat-label">Payments (<?= $paidInvoicesCount ?> Paid)</div>
            </div>
        </a>
    </div>

</div>

<!-- 3. BARIS BAWAH: SYSTEM OVERVIEW (KIRI) & QUICK ACCESS (KANAN) -->
<div class="row g-4">

    <!-- System Overview (Kiri - Lebih Lebar) -->
    <div class="col-lg-8">
        <div class="section-card h-100">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-0">System Overview</h5>
                    <small class="text-muted">Monthly patient registrations and appointment activity</small>
                </div>
                <span class="badge" style="background: #e5f4f3; color: #05668d;">Last 6 Months</span>
            </div>

            <div style="height: 270px; width: 100%;">
                <canvas id="adminSystemOverviewChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Access (Kanan) -->
    <div class="col-lg-4">
        <div class="section-card h-100 d-flex flex-column justify-content-between">
            <div>
                <h5 class="mb-1">Quick Access</h5>
                <p class="text-muted small mb-3">Direct shortcuts to manage core records.</p>

                <div class="d-flex flex-column gap-3">
                    <!-- Edit Patients -->
                    <a href="patients.php" class="quick-access-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="quick-access-icon">
                                <i class="bi bi-person-gear"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Edit Patients</div>
                                <div class="text-muted" style="font-size: 11px;">Manage & update patient profiles</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>

                    <!-- Edit Appointments -->
                    <a href="appointments.php" class="quick-access-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="quick-access-icon">
                                <i class="bi bi-calendar2-week"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Edit Appointments</div>
                                <div class="text-muted" style="font-size: 11px;">Update schedules & booking status</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>

                    <!-- Medical Records & Payments -->
                    <a href="medical_records.php" class="quick-access-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="quick-access-icon">
                                <i class="bi bi-clipboard2-check"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">Medical Records & Bills</div>
                                <div class="text-muted" style="font-size: 11px;">Review diagnoses & payment status</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>

                    <!-- Settings -->
                    <a href="settings.php" class="quick-access-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="quick-access-icon">
                                <i class="bi bi-gear"></i>
                            </div>
                            <div>
                                <div class="fw-semibold small">System Settings</div>
                                <div class="text-muted" style="font-size: 11px;">Configure account & preferences</div>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const ctxAdmin = document.getElementById('adminSystemOverviewChart').getContext('2d');

const gradPatients = ctxAdmin.createLinearGradient(0, 0, 0, 260);
gradPatients.addColorStop(0, 'rgba(5, 102, 141, 0.28)');
gradPatients.addColorStop(1, 'rgba(5, 102, 141, 0.01)');

const gradAppointments = ctxAdmin.createLinearGradient(0, 0, 0, 260);
gradAppointments.addColorStop(0, 'rgba(0, 168, 150, 0.22)');
gradAppointments.addColorStop(1, 'rgba(0, 168, 150, 0.01)');

new Chart(ctxAdmin, {
    type: 'line',
    data: {
        labels: <?= json_encode($monthsLabels) ?>,
        datasets: [
            {
                label: 'Patients',
                data: <?= json_encode($patientCounts) ?>,
                borderColor: '#05668d',
                backgroundColor: gradPatients,
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
                backgroundColor: gradAppointments,
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
                    padding: 18
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