<?php
require_once '../includes/auth.php';
requireRole(['admin']);

require_once '../config/database.php';

try {

    // ==========================================
    // 1. ADD NEW PATIENT
    // ==========================================

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['add_patient'])
    ) {

        $patient_name = trim($_POST['patient_name'] ?? '');
        $ic_number = trim($_POST['ic_number'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');

        if ($patient_name === '') {
            header("Location: patients.php?err=required");
            exit();
        }

        $stmt = $pdo->prepare("
            INSERT INTO patients (
                patient_name,
                ic_number,
                gender,
                date_of_birth,
                phone,
                email,
                address,
                emergency_contact
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $success = $stmt->execute([
            $patient_name,
            $ic_number,
            $gender !== '' ? $gender : null,
            $date_of_birth !== '' ? $date_of_birth : null,
            $phone,
            $email,
            $address,
            $emergency_contact
        ]);

        if ($success) {
            header("Location: patients.php?msg=added");
        } else {
            header("Location: patients.php?err=failed");
        }

        exit();
    }


    // ==========================================
    // 2. EDIT / UPDATE PATIENT
    // ==========================================

    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['edit_patient'])
    ) {

        $patient_id = (int) ($_POST['patient_id'] ?? 0);

        $patient_name = trim($_POST['patient_name'] ?? '');
        $ic_number = trim($_POST['ic_number'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');

        if ($patient_id <= 0 || $patient_name === '') {
            header("Location: patients.php?err=invalid");
            exit();
        }

        // Check whether patient exists

        $check = $pdo->prepare("
            SELECT patient_id
            FROM patients
            WHERE patient_id = ?
        ");

        $check->execute([$patient_id]);

        if (!$check->fetchColumn()) {
            header("Location: patients.php?err=notfound");
            exit();
        }

        // Update patient information

        $stmt = $pdo->prepare("
            UPDATE patients
            SET
                patient_name = ?,
                ic_number = ?,
                gender = ?,
                date_of_birth = ?,
                phone = ?,
                email = ?,
                address = ?,
                emergency_contact = ?
            WHERE patient_id = ?
        ");

        $success = $stmt->execute([
            $patient_name,
            $ic_number,
            $gender !== '' ? $gender : null,
            $date_of_birth !== '' ? $date_of_birth : null,
            $phone,
            $email,
            $address,
            $emergency_contact,
            $patient_id
        ]);

        if ($success) {
            header("Location: patients.php?msg=updated");
        } else {
            header("Location: patients.php?err=failed");
        }

        exit();
    }


    // ==========================================
    // 3. DELETE PATIENT
    // ==========================================

    if (isset($_GET['delete_id'])) {

        $patient_id = (int) $_GET['delete_id'];

        if ($patient_id <= 0) {
            header("Location: patients.php?err=invalid");
            exit();
        }

        // Prevent deletion of patients with medical data

        $relatedTables = [
            'appointments',
            'medical_records',
            'prescriptions',
            'patient_allergies'
        ];

        foreach ($relatedTables as $table) {

            $check = $pdo->prepare("
                SELECT COUNT(*)
                FROM `$table`
                WHERE patient_id = ?
            ");

            $check->execute([$patient_id]);

            if ((int) $check->fetchColumn() > 0) {
                header("Location: patients.php?err=linked");
                exit();
            }
        }

        // Delete patient

        $stmt = $pdo->prepare("
            DELETE FROM patients
            WHERE patient_id = ?
        ");

        $success = $stmt->execute([$patient_id]);

        if ($success && $stmt->rowCount() > 0) {
            header("Location: patients.php?msg=deleted");
        } else {
            header("Location: patients.php?err=failed");
        }

        exit();
    }

} catch (PDOException $e) {

    // Handle database errors

    error_log("Patient Management Error: " . $e->getMessage());

    header("Location: patients.php?err=database");
    exit();
}

// Redirect back to Patient Management

header("Location: patients.php");
exit();