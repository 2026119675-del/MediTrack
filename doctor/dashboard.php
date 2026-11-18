<?php
require_once '../includes/auth.php';
requireRole(['doctor']);
require_once '../config/database.php';

$pageTitle = "Doctor Dashboard";
$doctorId = (int)($_SESSION['user_id'] ?? 0);

$totalPatients = 0;
$todayAppointments = 0;
$upcomingAppointments = 0;
$totalPrescriptions = 0;

try {
    $totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE()");
    $stmt->execute([$doctorId]);
    $todayAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status IN ('Upcoming', 'Pending')");
    $stmt->execute([$doctorId]);
    $upcomingAppointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE doctor_id = ?");
    $stmt->execute([$doctorId]);
    $totalPrescriptions = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    // Fallback jika ada column/table belum lengkap
}

require_once '../includes/header.php';
?>

<div class="welcome-card">
    <h3>
        Welcome, Dr. <?= htmlspecialchars($_SESSION['full_name'] ?? 'Doctor') ?>!
    </h3>
    <p class="mb-0">
        View your patient appointments, medical records and prescriptions.
    </p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-number"><?= $totalPatients ?></div>
            <div class="stat-label">Total Patients</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-number"><?= $todayAppointments ?></div>
            <div class="stat-label">Today's Appointments</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-number"><?= $upcomingAppointments ?></div>
            <div class="stat-label">Upcoming Appointments</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-capsule"></i>
            </div>
            <div class="stat-number"><?= $totalPrescriptions ?></div>
            <div class="stat-label">Prescriptions Issued</div>
        </div>
    </div>
</div>

<div class="section-card">
    <h5>Clinical Operations</h5>
    <p class="text-muted small">
        Select a module to manage clinical workflow.
    </p>

    <div class="row g-3">
        <?php
        $modules = [
            ['Patients', 'patients.php', 'bi-people', 'View patient details and medical history.'],
            ['Appointments', 'appointments.php', 'bi-calendar-check', 'Check your scheduled patient appointments.'],
            ['Prescriptions', 'prescriptions.php', 'bi-capsule', 'Issue and review patient prescriptions.']
        ];

        foreach ($modules as $module):
        ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= $module[1] ?>" class="text-decoration-none text-dark">
                <div class="border rounded p-3 h-100">
                    <i class="bi <?= $module[2] ?> fs-3 text-danger"></i>
                    <h6 class="mt-2"><?= $module[0] ?></h6>
                    <p class="text-muted small mb-0"><?= $module[3] ?></p>
                    <div class="text-danger small mt-3">
                        Open Module <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>