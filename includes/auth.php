<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login
function requireLogin() {

    if (!isset($_SESSION['user_id'])) {
        header("Location: /meditrack/login.php");
        exit();
    }

}

// Check user role
function requireRole($allowedRoles) {

    requireLogin();

    if (!in_array($_SESSION['role'], $allowedRoles)) {
        header("Location: /meditrack/index.php");
        exit();
    }

}

?>