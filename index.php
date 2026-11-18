<?php

require_once 'includes/auth.php';

requireLogin();

switch ($_SESSION['role']) {

    case 'admin':
        header("Location: admin/dashboard.php");
        exit();

    case 'doctor':
        header("Location: doctor/dashboard.php");
        exit();

    case 'receptionist':
        header("Location: receptionist/dashboard.php");
        exit();

    default:
        session_destroy();
        header("Location: login.php");
        exit();

}

?>