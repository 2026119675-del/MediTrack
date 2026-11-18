
<?php
require_once '../includes/auth.php';
requireRole(['admin']);

require_once '../config/database.php';

try {

    // ADD APPOINTMENT
    if (isset($_POST['add_appointment'])) {

        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $status = $_POST['status'] ?? 'Upcoming';

        if (
            $patient_id <= 0 ||
            $doctor_id <= 0 ||
            $appointment_date === '' ||
            strlen($reason) > 255 ||
            !in_array($status, ['Upcoming', 'Completed', 'Cancelled'], true)
        ) {
            header("Location: appointments.php?err=failed");
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO appointments
            (patient_id, doctor_id, appointment_date, reason, status)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $patient_id,
            $doctor_id,
            str_replace('T', ' ', $appointment_date) . ':00',
            $reason !== '' ? $reason : null,
            $status
        ]);

        header("Location: appointments.php?msg=added");
        exit;
    }


    // EDIT APPOINTMENT
    if (isset($_POST['edit_appointment'])) {

        $appointment_id = (int)($_POST['appointment_id'] ?? 0);
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $appointment_date = $_POST['appointment_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        $status = $_POST['status'] ?? '';

        if (
            $appointment_id <= 0 ||
            $patient_id <= 0 ||
            $doctor_id <= 0 ||
            $appointment_date === '' ||
            strlen($reason) > 255 ||
            !in_array($status, ['Upcoming', 'Completed', 'Cancelled'], true)
        ) {
            header("Location: appointments.php?err=failed");
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE appointments
            SET
                patient_id = ?,
                doctor_id = ?,
                appointment_date = ?,
                reason = ?,
                status = ?
            WHERE appointment_id = ?
        ");

        $stmt->execute([
            $patient_id,
            $doctor_id,
            str_replace('T', ' ', $appointment_date) . ':00',
            $reason !== '' ? $reason : null,
            $status,
            $appointment_id
        ]);

        header("Location: appointments.php?msg=updated");
        exit;
    }


    // DELETE APPOINTMENT
    if (isset($_GET['delete_id'])) {

        $appointment_id = (int)$_GET['delete_id'];

        if ($appointment_id <= 0) {
            header("Location: appointments.php?err=failed");
            exit;
        }

        $stmt = $pdo->prepare("
            DELETE FROM appointments
            WHERE appointment_id = ?
        ");

        $stmt->execute([$appointment_id]);

        header("Location: appointments.php?msg=deleted");
        exit;
    }

    header("Location: appointments.php");
    exit;

} catch (PDOException $e) {

    error_log("Appointment Error: " . $e->getMessage());

    header("Location: appointments.php?err=failed");
    exit;
}

