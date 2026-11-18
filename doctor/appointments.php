<?php
require_once '../includes/auth.php';
requireRole(['doctor']);
require_once '../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

$pageTitle = "My Appointments";
$doctorId = (int)($_SESSION['user_id'] ?? 0);

if ($doctorId <= 0) {
    exit("Doctor session not found. Please login again.");
}

function escAppointment($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['appointment_csrf'])) {
    $_SESSION['appointment_csrf'] = bin2hex(random_bytes(32));
}

$successMessage = $_SESSION['appointment_success'] ?? '';
unset($_SESSION['appointment_success']);

$errorMessage = '';

$allowedTabs = ['upcoming', 'past', 'cancelled'];
$tab = $_GET['tab'] ?? 'upcoming';

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'upcoming';
}

// UPDATE APPOINTMENT STATUS

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    $token = (string)($_POST['csrf_token'] ?? '');

    try {

        if (!hash_equals($_SESSION['appointment_csrf'], $token)) {
            throw new RuntimeException("Invalid request.");
        }

        if ($appointmentId <= 0 ||
            !in_array($newStatus, ['Past', 'Cancelled'], true)) {
            throw new RuntimeException("Invalid appointment status.");
        }

        $stmt = $pdo->prepare(
            "UPDATE appointments
             SET status = ?
             WHERE appointment_id = ?
             AND doctor_id = ?
             AND LOWER(TRIM(status)) = 'upcoming'"
        );

        $stmt->execute([
            $newStatus,
            $appointmentId,
            $doctorId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                "Appointment not found or already updated."
            );
        }

        $_SESSION['appointment_success'] =
            $newStatus === 'Past'
            ? 'Appointment completed successfully.'
            : 'Appointment cancelled successfully.';

        $redirectTab =
            $newStatus === 'Cancelled' ? 'cancelled' : 'past';

        header("Location: appointments.php?tab=" . $redirectTab);
        exit;

    } catch (PDOException $e) {
        error_log($e->getMessage());
        $errorMessage = "Unable to update appointment.";
    } catch (RuntimeException $e) {
        $errorMessage = $e->getMessage();
    }
}

// SAME STATUS AS RECEPTIONIST

$conditions = [

    'upcoming' =>
        "LOWER(TRIM(a.status)) = 'upcoming'",

    'past' =>
        "LOWER(TRIM(a.status)) IN ('past', 'completed')",

    'cancelled' =>
        "LOWER(TRIM(a.status)) = 'cancelled'"

];

// LOAD APPOINTMENTS ASSIGNED TO LOGGED-IN DOCTOR

$appointments = [];

try {

    $order = $tab === 'upcoming' ? 'ASC' : 'DESC';

    $sql = "SELECT
                a.appointment_id,
                a.patient_id,
                a.doctor_id,
                a.appointment_date,
                a.reason,
                a.status,
                p.patient_name
            FROM appointments a
            LEFT JOIN patients p
                ON a.patient_id = p.patient_id
            WHERE a.doctor_id = ?
            AND " . $conditions[$tab] . "
            ORDER BY a.appointment_date " . $order;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$doctorId]);

    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $errorMessage = "Unable to load appointments.";
}

require_once '../includes/header.php';
?>

<div class="section-card">

    <h4>My Appointments</h4>

    <p class="text-muted">
        View and manage your patient appointments.
    </p>

    <?php if ($successMessage): ?>

        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <?= escAppointment($successMessage) ?>
        </div>

    <?php endif; ?>

    <?php if ($errorMessage): ?>

        <div class="alert alert-danger">
            <?= escAppointment($errorMessage) ?>
        </div>

    <?php endif; ?>

    <!-- APPOINTMENT TABS -->

    <div class="d-flex gap-2 mb-4 flex-wrap">

        <?php foreach ($allowedTabs as $item): ?>

            <a href="appointments.php?tab=<?= $item ?>"
               class="btn <?= $tab === $item
                   ? 'btn-danger'
                   : 'btn-outline-danger' ?>">

                <?= ucfirst($item) ?>

            </a>

        <?php endforeach; ?>

    </div>

    <!-- APPOINTMENTS TABLE -->

    <div class="table-responsive">

        <table class="table table-hover align-middle">

            <thead>

                <tr>
                    <th>No.</th>
                    <th>Date & Time</th>
                    <th>Patient Name</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($appointments as $index => $a): ?>

                    <?php

                    $status = strtolower(trim($a['status'] ?? ''));
                    $statusLabel = ucfirst($status);
                    $badgeClass = 'bg-secondary';

                    if ($status === 'upcoming') {
                        $badgeClass = 'bg-primary';
                    } elseif (in_array($status, ['past', 'completed'], true)) {
                        $badgeClass = 'bg-success';
                        $statusLabel = 'Past';
                    } elseif ($status === 'cancelled') {
                        $badgeClass = 'bg-danger';
                    }

                    ?>

                    <tr>

                        <td><?= $index + 1 ?></td>

                        <td>

                            <?= !empty($a['appointment_date'])
                                ? escAppointment(date(
                                    'd/m/Y h:i A',
                                    strtotime($a['appointment_date'])
                                ))
                                : '-' ?>

                            <?php if (
                                $status === 'upcoming' &&
                                !empty($a['appointment_date']) &&
                                strtotime($a['appointment_date']) < time()
                            ): ?>

                                <div class="text-warning small">
                                    Overdue
                                </div>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= escAppointment($a['patient_name'] ?? '-') ?>
                        </td>

                        <td>
                            <?= escAppointment($a['reason'] ?? '-') ?>
                        </td>

                        <td>

                            <span class="badge <?= $badgeClass ?>">
                                <?= escAppointment($statusLabel) ?>
                            </span>

                        </td>

                        <td>

                            <div class="d-flex flex-wrap gap-2">

                                <?php if (!empty($a['patient_name'])): ?>

                                    <a href="patients.php?patient_id=<?= (int)$a['patient_id'] ?>&amp;mode=view#patientDetails"
                                       class="btn btn-sm btn-outline-primary">

                                        <i class="bi bi-eye"></i>
                                        View Patient

                                    </a>

                                <?php endif; ?>

                                <?php if ($status === 'upcoming'): ?>

                                    <form method="POST"
                                          action="appointments.php"
                                          class="d-flex flex-wrap gap-2">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= escAppointment($_SESSION['appointment_csrf']) ?>">

                                        <input type="hidden"
                                               name="appointment_id"
                                               value="<?= (int)$a['appointment_id'] ?>">

                                        <button type="submit"
                                                name="status"
                                                value="Past"
                                                class="btn btn-sm btn-success"
                                                onclick="return confirm('Mark this appointment as completed?')">

                                            <i class="bi bi-check-lg"></i>
                                            Complete

                                        </button>

                                        <button type="submit"
                                                name="status"
                                                value="Cancelled"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Cancel this appointment?')">

                                            Cancel

                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                <?php if (empty($appointments)): ?>

                    <tr>

                        <td colspan="6"
                            class="text-center text-muted py-4">

                            No <?= escAppointment($tab) ?> appointments found.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php require_once '../includes/footer.php'; ?>