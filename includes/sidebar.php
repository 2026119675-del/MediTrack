<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
?>

<?php if ($role === 'doctor'): ?>

    <div class="sidebar-heading">DOCTOR MENU</div>

    <a href="/MediTrack/doctor/dashboard.php">
        <i class="bi bi-speedometer2"></i>
        Dashboard
    </a>

    <a href="/MediTrack/doctor/patients.php">
        <i class="bi bi-people"></i>
        Patients
    </a>

    <a href="/MediTrack/doctor/appointments.php">
        <i class="bi bi-calendar-check"></i>
        Appointments
    </a>

    <a href="/MediTrack/doctor/prescriptions.php">
        <i class="bi bi-capsule"></i>
        Prescriptions
    </a>

<?php endif; ?>