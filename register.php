<?php

require_once 'config/database.php';

$error = "";
$success = "";

$fullname = "";
$email = "";
$selected_role = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $selected_role = $_POST['role_id'] ?? '';

    if (
        $fullname == "" ||
        $email == "" ||
        $password == "" ||
        $confirm_password == "" ||
        $selected_role == ""
    ) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check whether email already exists
        $check = $pdo->prepare(
            "SELECT user_id FROM users WHERE email = ?"
        );
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = "Email already exists.";
        } else {
            // Verify selected role exists and is only doctor or receptionist
            $roleCheck = $pdo->prepare(
                "SELECT role_id FROM roles 
                 WHERE role_id = ? AND LOWER(role_name) IN ('doctor', 'receptionist')"
            );
            $roleCheck->execute([$selected_role]);

            if (!$roleCheck->fetch()) {
                $error = "Invalid role selected.";
            } else {
                // Auto-generate unique username from email prefix
                $baseUsername = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', strstr($email, '@', true)));
                if ($baseUsername === '') {
                    $baseUsername = 'user';
                }
                $generatedUsername = $baseUsername;
                $counter = 1;

                $userCheck = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
                while (true) {
                    $userCheck->execute([$generatedUsername]);
                    if (!$userCheck->fetch()) {
                        break;
                    }
                    $generatedUsername = $baseUsername . $counter;
                    $counter++;
                }

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert new user
                $stmt = $pdo->prepare(
                    "INSERT INTO users (role_id, username, full_name, email, password)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $selected_role,
                    $generatedUsername,
                    $fullname,
                    $email,
                    $hashed_password
                ]);

                $success = "Registration successful! Your username is <strong>" . htmlspecialchars($generatedUsername) . "</strong>. You can login now.";

                // Clear form
                $fullname = "";
                $email = "";
                $selected_role = "";
            }
        }
    }
}

// Get available roles (only doctor and receptionist)
$roles = $pdo->query(
    "SELECT * FROM roles 
     WHERE LOWER(role_name) IN ('doctor', 'receptionist') 
     ORDER BY role_id ASC"
)->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediTrack | Register</title>

    <!-- Bootstrap CSS & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #05668d;
            --secondary: #028090;
            --teal: #00a896;
            --green: #02c39a;
            --cream: #f0f3bd;
            --white: #ffffff;
        }

        body {
            background: #f4f8f9;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }

        .register-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            padding: 40px 15px;
        }

        .register-card {
            border: none;
            border-radius: 15px;
            border-top: 5px solid var(--teal);
        }

        .brand {
            color: var(--primary);
            font-weight: bold;
        }

        .btn-register {
            background-color: var(--primary);
            color: var(--white);
            border: none;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        .btn-register:hover {
            background-color: var(--secondary);
            color: var(--white);
        }

        .form-control,
        .form-select {
            padding: 11px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 0.2rem rgba(0, 168, 150, 0.2);
        }

        .login-link {
            color: var(--secondary);
        }

        .login-link:hover {
            color: var(--primary);
        }

        /* =========================
           FOOTER
        ========================= */
        .footer {
            background: var(--primary);
            color: white;
            padding: 35px 7% 20px;
            margin-top: auto;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 1fr 1.3fr 1fr;
            gap: 40px;
        }

        .footer h5 {
            color: var(--cream);
            font-weight: bold;
            margin-bottom: 15px;
        }

        .footer p {
            font-size: 13px;
            color: #e5f2f3;
            line-height: 1.8;
            margin-bottom: 5px;
        }

        .footer a {
            color: #e5f2f3;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer a:hover {
            color: var(--green);
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .footer-logo img {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }

        .footer-logo i {
            font-size: 35px;
            color: var(--green);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.25);
            text-align: center;
            margin-top: 25px;
            padding-top: 15px;
            font-size: 12px;
            color: #d5e8e8;
        }

        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }
    </style>
</head>

<body>

<!-- Main Register Content -->
<div class="register-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card register-card shadow">
                    <div class="card-body p-4 p-md-5">

                        <div class="text-center mb-4">
                            <h2 class="brand">
                                <i class="bi bi-heart-pulse-fill" style="color: var(--teal);"></i> MediTrack
                            </h2>
                            <p class="text-muted mb-2">Electronic Medical Record System</p>
                            <h4 class="fw-bold">Create Account</h4>
                            <p class="text-muted small">Register to access MediTrack</p>
                        </div>

                        <!-- Error Message -->
                        <?php if ($error != ""): ?>
                            <div class="alert alert-danger">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Success Message -->
                        <?php if ($success != ""): ?>
                            <div class="alert alert-success">
                                <?= $success ?>
                                <a href="login.php" class="alert-link">Login here</a>
                            </div>
                        <?php endif; ?>

                        <!-- Registration Form -->
                        <form method="POST" action="">

                            <!-- Full Name -->
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text"
                                       name="fullname"
                                       class="form-control"
                                       placeholder="Enter your full name"
                                       value="<?= htmlspecialchars($fullname) ?>"
                                       maxlength="100"
                                       required>
                            </div>

                            <!-- Email -->
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email"
                                       name="email"
                                       class="form-control"
                                       placeholder="Enter email address"
                                       value="<?= htmlspecialchars($email) ?>"
                                       maxlength="100"
                                       required>
                            </div>

                            <!-- Role -->
                            <div class="mb-3">
                                <label class="form-label">User Role</label>
                                <select name="role_id" class="form-select" required>
                                    <option value="">Select your role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['role_id'] ?>"
                                            <?= (string)$selected_role === (string)$role['role_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(ucfirst($role['role_name'])) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Password -->
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password"
                                       name="password"
                                       class="form-control"
                                       placeholder="Minimum 8 characters"
                                       minlength="8"
                                       required>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-4">
                                <label class="form-label">Confirm Password</label>
                                <input type="password"
                                       name="confirm_password"
                                       class="form-control"
                                       placeholder="Re-enter password"
                                       required>
                            </div>

                            <!-- Register Button -->
                            <button type="submit" class="btn btn-register w-100 py-2">
                                Create Account
                            </button>

                        </form>

                        <div class="text-center mt-4">
                            <p class="mb-0 text-muted">
                                Already have an account?
                                <a href="login.php" class="text-decoration-none fw-bold login-link">
                                    Login
                                </a>
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================
     FOOTER
========================= -->

<footer class="footer">

    <div class="footer-content">

        <!-- FOOTER LOGO -->
        <div class="footer-logo">

            <img src="/meditrack/assets/Logo.png"
                 alt="MediTrack Logo"
                 onerror="this.style.display='none'">

            <div>
                <h5>MediTrack</h5>
                <p>Electronic Medical Record System</p>
            </div>

        </div>

        <!-- MEDITRACK INFORMATION -->
        <div>

            <h5>MediTrack</h5>

            <p>
                <i class="fa-regular fa-clock me-2"></i>
                Operation Hours
            </p>

            <p>Wednesday - Monday: 24 hours</p>

        </div>

        <!-- CONTACT US -->
        <div>

            <h5>Contact Us</h5>

            <p>
                <i class="fa-solid fa-phone me-2"></i>
                Phone: +60 3-0000 0000
            </p>

            <p>
                <i class="fa-solid fa-location-dot me-2"></i>
                Location: Malaysia
            </p>

            <p>
                <i class="fa-solid fa-envelope me-2"></i>
                Email: info@meditrack.com
            </p>

        </div>

    </div>

    <!-- COPYRIGHT -->
    <div class="footer-bottom">
        © <?= date("Y") ?> MediTrack.
        All Rights Reserved.
    </div>

</footer>

</body>
</html>