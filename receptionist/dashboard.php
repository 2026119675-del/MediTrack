<?php
require_once '../includes/auth.php';
requireRole(['receptionist']);
require_once '../config/database.php';

$pageTitle = "Receptionist Dashboard";

$totalPatients = 0;
$todayAppointments = 0;
$pendingAppointments = 0;
$newRegistrations = 0;

try {
    $totalPatients = (int)$pdo->query(
        "SELECT COUNT(*) FROM patients"
    )->fetchColumn();

    $newRegistrations = (int)$pdo->query(
        "SELECT COUNT(*) FROM patients
         WHERE DATE(created_at) = CURDATE()"
    )->fetchColumn();

    $todayAppointments = (int)$pdo->query(
        "SELECT COUNT(*) FROM appointments
         WHERE DATE(appointment_date) = CURDATE()"
    )->fetchColumn();

    $pendingAppointments = (int)$pdo->query(
        "SELECT COUNT(*) FROM appointments
         WHERE status IN ('Pending', 'Upcoming')"
    )->fetchColumn();

} catch (PDOException $e) {
    // Some tables or columns may not exist yet.
}

require_once '../includes/header.php';
?>

<div class="welcome-card">
    <h3>
        Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Receptionist') ?>!
    </h3>
    <p class="mb-0">
        Manage patient registration, appointments and daily clinic activities.
    </p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-number">
                <?= $totalPatients ?>
            </div>
            <div class="stat-label">Total Patients</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="stat-number">
                <?= $todayAppointments ?>
            </div>
            <div class="stat-label">Today's Appointments</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-calendar-plus"></i>
            </div>
            <div class="stat-number">
                <?= $pendingAppointments ?>
            </div>
            <div class="stat-label">Pending Appointments</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-person-plus"></i>
            </div>
            <div class="stat-number">
                <?= $newRegistrations ?>
            </div>
            <div class="stat-label">New Registrations</div>
        </div>
    </div>
</div>

<div class="section-card">
    <h5>Daily Operations</h5>
    <p class="text-muted small">
        Select a module to manage clinic information.
    </p>

    <div class="row g-3">
        <?php
        $modules = [
            ['Patient Management','patient.php','bi-people','Register and manage patient information.'],
            ['Appointment','appointments.php','bi-calendar-check','Manage patient appointments.'],
            ['Medical Records','medical_records.php','bi-clipboard2-pulse','View medical record information.'],
            ['Prescriptions','prescriptions.php','bi-capsule','Manage prescription information.'],
            ['Billing & Payments','billing.php','bi-receipt','Manage invoices and payments.']
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