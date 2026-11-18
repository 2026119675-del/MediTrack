<?php
session_start();
require_once 'config/database.php';

// ==========================================
// LOAD ADMIN APPEARANCE SETTINGS
// ==========================================

$loginDefaults = [
    'system_name' => 'MediTrack',
    'theme_mode' => 'light',
    'primary_color' => '#05668d',
    'sidebar_color' => '#ffffff',
    'font_family' => 'Arial',
    'font_size' => 14,
    'logo' => '/meditrack/assets/Logo.png'
];

$loginAppearance = $loginDefaults;

$appearanceFile = __DIR__ . '/config/appearance.json';

if (is_file($appearanceFile) && is_readable($appearanceFile)) {

    $appearanceContent = file_get_contents($appearanceFile);

    if ($appearanceContent !== false) {

        $savedAppearance = json_decode($appearanceContent, true);

        if (is_array($savedAppearance)) {
            $loginAppearance = array_merge(
                $loginDefaults,
                $savedAppearance
            );
        }
    }
}

// ==========================================
// VALIDATE SYSTEM SETTINGS
// ==========================================

$systemName = $loginAppearance['system_name'];

$themeMode = in_array(
    ($loginAppearance['theme_mode'] ?? 'light'),
    ['light', 'dark'],
    true
) ? $loginAppearance['theme_mode'] : 'light';

$primaryColor = $loginAppearance['primary_color'];
$sidebarColor = $loginAppearance['sidebar_color'];
$fontFamily = $loginAppearance['font_family'];
$systemLogo = $loginAppearance['logo'];

$fontSize = max(
    12,
    min(20, (int) $loginAppearance['font_size'])
);

if (!is_string($systemName) || trim($systemName) === '') {
    $systemName = 'MediTrack';
}

if (
    !is_string($primaryColor) ||
    !preg_match('/^#[0-9a-fA-F]{6}$/D', $primaryColor)
) {
    $primaryColor = '#05668d';
}

if (
    !is_string($sidebarColor) ||
    !preg_match('/^#[0-9a-fA-F]{6}$/D', $sidebarColor)
) {
    $sidebarColor = '#ffffff';
}

$allowedFonts = [
    'Arial',
    'Poppins',
    'Roboto',
    'Verdana',
    'Tahoma',
    'Georgia'
];

if (!in_array($fontFamily, $allowedFonts, true)) {
    $fontFamily = 'Arial';
}

if (
    !is_string($systemLogo) ||
    (
        $systemLogo !== $loginDefaults['logo'] &&
        !preg_match(
            '#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#D',
            $systemLogo
        )
    )
) {
    $systemLogo = $loginDefaults['logo'];
}

// ==========================================
// GENERATE MATCHING THEME COLORS
// ==========================================

function loginAdjustColor($hex, $factor) {

    $hex = ltrim($hex, '#');

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, min(255, round($r * $factor)));
    $g = max(0, min(255, round($g * $factor)));
    $b = max(0, min(255, round($b * $factor)));

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function loginLightColor($hex, $amount = 0.92) {

    $hex = ltrim($hex, '#');

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = round($r + (255 - $r) * $amount);
    $g = round($g + (255 - $g) * $amount);
    $b = round($b + (255 - $b) * $amount);

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function loginContrast($hex) {

    $hex = ltrim($hex, '#');

    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $brightness = (
        $r * 299 +
        $g * 587 +
        $b * 114
    ) / 1000;

    return $brightness < 145
        ? '#ffffff'
        : '#333333';
}

function loginEscape($value) {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

$secondaryColor = loginAdjustColor($primaryColor, 0.80);
$lightColor = loginLightColor($primaryColor, 0.92);
$borderColor = loginLightColor($primaryColor, 0.80);
$backgroundColor = loginLightColor($primaryColor, 0.97);

$primaryText = loginContrast($primaryColor);
$secondaryText = loginContrast($secondaryColor);
$headerText = loginContrast($sidebarColor);

$headerBrandColor = $headerText === '#ffffff'
    ? '#ffffff'
    : $primaryColor;

$hex = ltrim($primaryColor, '#');

$primaryRgb = implode(',', [
    hexdec(substr($hex, 0, 2)),
    hexdec(substr($hex, 2, 2)),
    hexdec(substr($hex, 4, 2))
]);

if (strtolower($primaryColor) === '#05668d') {

    $secondaryColor = '#028090';
    $secondaryText = '#ffffff';
    $backgroundColor = '#f8fbfb';
    $lightColor = '#eaf6f8';
    $borderColor = '#cbe5ea';
    $accentColor = '#00a896';

} else {

    $accentColor = $primaryColor;

}

// ==========================================
// LOGIN AUTHENTICATION - EMAIL
// ==========================================

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error = "Please enter your email and password.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        $sql = "SELECT
                    users.user_id,
                    users.username,
                    users.email,
                    users.full_name,
                    users.password,
                    users.status,
                    roles.role_name
                FROM users
                INNER JOIN roles
                ON users.role_id = roles.role_id
                WHERE users.email = ?
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if (
            $user &&
            password_verify($password, $user['password'])
        ) {

            if ($user['status'] != 'active') {

                $error = "Your account is inactive. Please contact administrator.";

            } else {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role_name'];

                header("Location: index.php");
                exit();

            }

        } else {

            $error = "Invalid email or password.";

        }
    }
}

// ==========================================
// CURRENT DATE AND TIME
// ==========================================

date_default_timezone_set('Asia/Kuala_Lumpur');

$googleMapsUrl = "https://www.google.com/maps/dir/3.0769152,101.4464512/Seri+Melawati+Medgroup+Sdn+Bhd,+9143,+Jalan+Bandar+4,+Taman+Melawati,+53100+Kuala+Lumpur,+Federal+Territory+of+Kuala+Lumpur/@3.1448569,101.267897,10z/data=!4m9!4m8!1m1!4e1!1m5!1m1!1s0x31cc37fa33d48e39:0xf77b393d5b2028aa!2m2!1d101.7513887!2d3.2108277?entry=ttu";

?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= loginEscape($themeMode) ?>">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= loginEscape($systemName) ?> | Electronic Medical Record System
    </title>

    <!-- BOOTSTRAP -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <!-- GOOGLE FONTS -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>

        /* =========================
           DYNAMIC COLOR THEME
        ========================= */

        :root {

            --primary: <?= $primaryColor ?>;
            --secondary: <?= $secondaryColor ?>;
            --teal: <?= $accentColor ?>;

            --green: #02c39a;
            --cream: #f0f3bd;
            --white: #ffffff;

            --primary-text: <?= $primaryText ?>;
            --secondary-text: <?= $secondaryText ?>;

            --primary-light: <?= $lightColor ?>;
            --theme-border: <?= $borderColor ?>;
            --page-background: <?= $backgroundColor ?>;

            --header-background: <?= $sidebarColor ?>;
            --header-text: <?= $headerText ?>;
            --header-brand: <?= $headerBrandColor ?>;

            --surface-card: #ffffff;
            --input-background: #ffffff;

            --body-text: #333333;
            --muted-text: #555555;
            --subtitle-text: #888888;
            --mission-text: #3b4b55;

            --heading-accent: var(--primary);
            --subheading-accent: var(--secondary);

            --system-font: '<?= $fontFamily ?>', sans-serif;
            --system-font-size: <?= $fontSize ?>px;

            --bs-body-font-family: var(--system-font);
            --bs-body-font-size: var(--system-font-size);

            --bs-primary: var(--primary);
            --bs-primary-rgb: <?= $primaryRgb ?>;

        }

        [data-theme="dark"] {

            --page-background: #0d1117;
            --header-background: #161b22;
            --header-text: #e6edf3;
            --header-brand: #58a6ff;

            --surface-card: #161b22;
            --input-background: #0d1117;

            --primary-light: #1c2532;
            --theme-border: #30363d;

            --body-text: #e6edf3;
            --muted-text: #9ba3af;
            --subtitle-text: #8b949e;
            --mission-text: #c9d1d9;

            --heading-accent: #58a6ff;
            --subheading-accent: #3fb9a8;

        }

        * {
            box-sizing: border-box;
        }

        html {
            font-size: var(--system-font-size);
            scroll-behavior: smooth;
        }

        body {

            margin: 0;
            font-family: var(--system-font);
            font-size: var(--system-font-size);

            background: var(--page-background);
            color: var(--body-text);

            min-height: 100vh;

            display: flex;
            flex-direction: column;

            transition: background-color 0.25s ease, color 0.25s ease;

        }

        button,
        input,
        select,
        textarea {
            font-family: var(--system-font);
        }

        a {
            text-decoration: none;
        }

        /* =========================
           HEADER
        ========================= */

        .header {
            background: var(--header-background);
            border-bottom: 3px solid var(--teal);
            transition: background-color 0.25s ease;
        }

        .header-top {

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 12px 5%;
            min-height: 90px;

        }

        .logo-area {

            display: flex;
            align-items: center;
            gap: 15px;

        }

        .logo-area img {

            width: 75px;
            height: 65px;
            object-fit: contain;

        }

        .logo-text h3 {

            margin: 0;
            color: var(--header-brand);
            font-weight: bold;
            font-size: 2rem;
            overflow-wrap: anywhere;

        }

        .logo-text p {

            margin: 3px 0 0;
            font-size: .86rem;
            color: var(--header-text);
            opacity: .8;

        }

        .header-information {

            display: flex;
            gap: 35px;
            text-align: right;

        }

        .header-information h6 {

            color: var(--header-brand);
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 1.15rem;

        }

        .header-information p {

            font-size: .93rem;
            color: var(--header-text);
            margin: 0;
            opacity: .85;

        }

        /* =========================
           NAVIGATION & THEME TOGGLE
        ========================= */

        .navbar-custom {

            background: var(--primary);
            padding: 0 5%;

            display: flex;
            align-items: center;
            justify-content: space-between;

        }

        .navbar-custom a {

            display: inline-block;
            color: var(--primary-text);

            padding: 15px 22px;

            font-weight: 600;
            font-size: 1.15rem;

            transition: 0.3s;

        }

        .navbar-custom a:hover,
        .navbar-custom a.active {

            background: var(--secondary);
            color: var(--secondary-text);

        }

        .navbar-right {

            display: flex;
            align-items: center;
            gap: 18px;

        }

        .navbar-date {

            color: var(--primary-text);
            font-size: .93rem;
            padding: 12px 0;

        }

        .theme-switcher {

            display: inline-flex;
            align-items: center;

            background: rgba(0, 0, 0, 0.22);

            padding: 3px;
            border-radius: 20px;

            border: 1px solid rgba(255, 255, 255, 0.25);

        }

        .theme-btn {

            border: none;
            background: transparent;

            color: rgba(255, 255, 255, 0.85);

            font-size: .85rem;
            font-weight: 600;

            padding: 5px 12px;
            border-radius: 16px;

            cursor: pointer;

            display: inline-flex;
            align-items: center;
            gap: 5px;

            transition: all 0.2s ease;

        }

        .theme-btn.active {

            background: #ffffff;
            color: #05668d;

            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.15);

        }

        /* =========================
           MAIN CONTENT
        ========================= */

        .main-container {

            min-height: 520px;
            padding: 55px 7%;

            display: grid;
            grid-template-columns: 1.15fr 0.85fr;

            gap: 60px;
            align-items: center;

            flex: 1;

        }

        /* COMPANY INFORMATION */

        .company-info h1 {

            font-size: 3rem;
            color: var(--heading-accent);

            font-weight: bold;
            margin-bottom: 12px;

            overflow-wrap: anywhere;

        }

        .company-info h4 {

            color: var(--subheading-accent);
            margin-bottom: 20px;
            font-size: 1.7rem;

        }

        .company-info p {

            color: var(--muted-text);
            line-height: 1.85;
            font-size: 1.07rem;

        }

        .company-icon {

            color: var(--teal);
            font-size: 3.2rem;
            margin-bottom: 15px;

        }

        /* PATIENT ALLERGY FEATURE */

        .allergy-feature-box {

            background: var(--surface-card);

            border: 1px solid var(--theme-border);
            border-left: 5px solid var(--teal);

            padding: 16px 18px;
            margin-top: 20px;

            border-radius: 8px;

            box-shadow: 0 2px 8px
                rgba(<?= $primaryRgb ?>, 0.04);

            transition: background-color 0.25s ease, border-color 0.25s ease;

        }

        .allergy-feature-box h6 {

            color: var(--subheading-accent);
            font-weight: bold;

            margin-bottom: 6px;
            font-size: 1.15rem;

        }

        .allergy-feature-box p {

            font-size: 1rem;
            color: var(--muted-text);

            margin-bottom: 0;
            line-height: 1.7;

        }

        /* OUR MISSION */

        .info-highlight {

            background: var(--primary-light);

            border: 1px solid var(--theme-border);
            border-left: 5px solid var(--primary);

            padding: 16px 18px;
            margin-top: 16px;

            border-radius: 8px;

            transition: background-color 0.25s ease, border-color 0.25s ease;

        }

        .info-highlight h6 {

            color: var(--heading-accent);
            font-weight: bold;

            margin-bottom: 6px;
            font-size: 1.15rem;

        }

        .info-highlight p {

            font-size: 1rem;
            color: var(--mission-text);
            line-height: 1.7;

        }

        /* =========================
           LOGIN BOX
        ========================= */

        .login-box {

            background: var(--surface-card);

            border: 2px solid var(--secondary);
            border-radius: 12px;

            padding: 40px;

            box-shadow: 0 5px 20px
                rgba(<?= $primaryRgb ?>, 0.10);

            transition: background-color 0.25s ease;

        }

        .login-box h2 {

            color: var(--heading-accent);
            text-align: center;

            font-weight: bold;
            margin-bottom: 10px;

            font-size: 2.3rem;

        }

        .login-box .subtitle {

            text-align: center;
            color: var(--subtitle-text);

            margin-bottom: 30px;
            font-size: 1.15rem;

            overflow-wrap: anywhere;

        }

        .form-label {

            color: var(--heading-accent);
            font-weight: bold;
            font-size: 1.1rem;

        }

        .form-control {

            padding: 12px;

            background: var(--input-background);
            color: var(--body-text);

            border: 1px solid var(--theme-border);
            border-radius: 7px;

            font-size: 1.15rem;

        }

        .form-control::placeholder {

            color: var(--subtitle-text);
            opacity: 0.75;

        }

        .form-control:focus {

            background: var(--surface-card);
            color: var(--body-text);

            border-color: var(--primary);

            box-shadow: 0 0 0 0.2rem
                rgba(<?= $primaryRgb ?>, 0.15);

        }

        .btn-login {

            background: var(--primary);
            color: var(--primary-text);

            width: 100%;
            padding: 12px;

            border: none;
            border-radius: 7px;

            font-weight: bold;
            font-size: 1.15rem;

            transition: 0.3s;

        }

        .btn-login:hover {

            background: var(--secondary);
            color: var(--secondary-text);

        }

        .register-link {

            color: var(--subheading-accent);
            font-weight: bold;

        }

        .register-link:hover {

            color: var(--heading-accent);
            text-decoration: underline;

        }

        [data-theme="dark"] .text-muted {
            color: #9ba3af !important;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {

            background: linear-gradient(
                110deg,
                var(--primary),
                var(--secondary)
            );

            color: var(--primary-text);

            width: 100%;
            padding: 35px 7% 15px;

            margin-top: auto;

        }

        .footer-content {

            display: grid;
            grid-template-columns: 1.3fr 1fr 1fr;

            gap: 35px;
            align-items: start;

        }

        .footer h5 {

            font-size: 1.2rem;
            color: var(--primary-text);

            font-weight: bold;
            margin-bottom: 15px;

        }

        .footer p {

            font-size: .93rem;
            color: var(--primary-text);

            line-height: 1.6;
            margin-bottom: 10px;

        }

        .footer a {

            color: var(--primary-text);
            text-decoration: none;

        }

        .footer a:hover {
            text-decoration: underline;
        }

        .footer-logo {

            display: flex;
            align-items: center;
            gap: 15px;

        }

        .footer-logo img {

            width: 75px;
            height: 75px;

            object-fit: contain;

            background: white;
            padding: 5px;

            border-radius: 8px;
            flex-shrink: 0;

        }

        .footer-logo h5 {
            overflow-wrap: anywhere;
        }

        .copy-item {

            cursor: pointer;

            display: inline-flex;
            align-items: center;
            gap: 6px;

            transition: opacity 0.2s;

        }

        .copy-item:hover {

            opacity: 0.85;
            text-decoration: underline;

        }

        .copied-badge {

            background: var(--primary-light);
            color: var(--heading-accent);

            font-size: .8rem;
            font-weight: bold;

            padding: 1px 6px;
            border-radius: 4px;

            display: none;

        }

        .footer-bottom {

            border-top: 1px solid rgba(255,255,255,0.3);

            text-align: center;

            margin-top: 25px;
            padding-top: 12px;

            font-size: .86rem;
            color: var(--primary-text);

        }

        /* ORIGINAL DEFAULT FOOTER HEADING */

        <?php if (strtolower($primaryColor) === '#05668d'): ?>

        .footer h5 {
            color: var(--cream);
        }

        .navbar-custom a:hover,
        .navbar-custom a.active {
            color: var(--cream);
        }

        <?php endif; ?>

        /* =========================
           RESPONSIVE DESIGN
        ========================= */

        @media (max-width: 992px) {

            .main-container {
                gap: 35px;
                padding: 40px 5%;
            }

            .login-box {
                padding: 30px;
            }

        }

        @media (max-width: 768px) {

            .header-top {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .header-information {
                justify-content: center;
                text-align: center;
                gap: 20px;
            }

            .navbar-custom {
                flex-wrap: wrap;
                justify-content: center;
                padding-bottom: 10px;
            }

            .navbar-custom a {
                padding: 12px;
            }

            .navbar-right {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }

            .main-container {
                grid-template-columns: 1fr;
                gap: 35px;
                padding: 35px 6%;
            }

            .company-info h1 {
                font-size: 2.3rem;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

        }

        @media (max-width: 480px) {

            .login-box {
                padding: 25px 20px;
            }

            .header-information {
                flex-wrap: wrap;
            }

            .logo-area {
                gap: 8px;
            }

            .logo-area img {
                width: 55px;
                height: 55px;
            }

            .logo-text h3 {
                font-size: 1.65rem;
            }

        }

    </style>

</head>

<body>

<!-- =========================
     HEADER TOP
========================= -->

<header class="header">

    <div class="header-top">

        <div class="logo-area">

            <img
                src="<?= loginEscape($systemLogo) ?>"
                alt="<?= loginEscape($systemName) ?> Logo"
                onerror="this.style.display='none'"
            >

            <div class="logo-text">

                <h3>
                    <?= loginEscape($systemName) ?>
                </h3>

                <p>Electronic Medical Record System</p>

            </div>

        </div>

        <!-- OPERATION HOURS AND CONTACT -->

        <div class="header-information">

            <div>

                <h6>
                    <i class="fa-regular fa-clock"></i>
                    Operation Hours
                </h6>

                <p>Wednesday - Monday</p>
                <p>24 Hours</p>

            </div>

            <div>

                <h6>
                    <i class="fa-solid fa-envelope"></i>
                    Contact Us
                </h6>

                <p>info@meditrack.com</p>
                <p>Healthcare Information</p>

            </div>

        </div>

    </div>

    <!-- NAVIGATION BAR -->

    <nav class="navbar-custom">

        <div>

            <a href="#home" class="active">
                <i class="fa-solid fa-house me-1"></i>
                Home
            </a>

            <a href="#login">
                <i class="fa-solid fa-right-to-bracket me-1"></i>
                Login
            </a>

        </div>

        <div class="navbar-right">

            <!-- LIGHT / DARK MODE -->

            <div class="theme-switcher"
                 role="group"
                 aria-label="Theme Switcher">

                <button
                    type="button"
                    id="btnLightMode"
                    class="theme-btn <?= $themeMode === 'light' ? 'active' : '' ?>"
                    onclick="setLoginTheme('light', true)"
                >
                    <i class="fa-solid fa-sun"></i> Light
                </button>

                <button
                    type="button"
                    id="btnDarkMode"
                    class="theme-btn <?= $themeMode === 'dark' ? 'active' : '' ?>"
                    onclick="setLoginTheme('dark', true)"
                >
                    <i class="fa-solid fa-moon"></i> Dark
                </button>

            </div>

            <div class="navbar-date">

                <i class="fa-regular fa-calendar me-1"></i>

                <?= date("d F Y") ?>

                &nbsp; | &nbsp;

                <i class="fa-regular fa-clock me-1"></i>

                <?= date("h:i A") ?>

            </div>

        </div>

    </nav>

</header>

<!-- =========================
     MAIN CONTENT
========================= -->

<main class="main-container" id="home">

    <!-- LEFT: COMPANY INFORMATION -->

    <section class="company-info">

        <div class="company-icon">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>

        <h1>
            <?= loginEscape($systemName) ?>
        </h1>

        <h4>Electronic Medical Record System</h4>

        <p>

            <?= loginEscape($systemName) ?>

            is an Electronic Medical Record System designed to support
            efficient healthcare information management. Our centralized platform
            streamlines patient registration, appointments, medical histories,
            prescriptions, and billing across doctors, receptionists, and administrators.

        </p>

        <!-- SMART PATIENT ALLERGY ALERT -->

        <div class="allergy-feature-box">

            <h6>
                <i class="fa-solid fa-shield-virus me-2"></i>
                Smart Patient Allergy Alert System
            </h6>

            <p>

                Unlike standard record systems,

                <?= loginEscape($systemName) ?>

                features an integrated

                <strong>Patient Allergy Alert</strong>

                module. Whenever a doctor records a patient's allergy,
                severity, and reaction, warning banners and medication
                conflict checks help identify potential allergy risks
                in medical records and prescriptions.

            </p>

        </div>

        <!-- OUR MISSION -->

        <div class="info-highlight">

            <h6>
                <i class="fa-solid fa-circle-info me-2"></i>
                Our Mission
            </h6>

            <p class="mb-0">

                To support safer, faster, and higher-quality healthcare services
                through organized, accessible, and accurate medical information
                management.

            </p>

        </div>

    </section>

    <!-- RIGHT: LOGIN FORM -->

    <section id="login">

        <div class="login-box">

            <h2>
                <i class="fa-solid fa-user-circle me-2"></i>
                Login
            </h2>

            <p class="subtitle">
                Welcome to <?= loginEscape($systemName) ?>
            </p>

            <!-- ERROR MESSAGE -->

            <?php if ($error != ""): ?>

                <div class="alert alert-danger">
                    <?= loginEscape($error) ?>
                </div>

            <?php endif; ?>

            <!-- LOGIN FORM -->

            <form method="POST" action="">

                <!-- EMAIL ADDRESS -->

                <div class="mb-4">

                    <label class="form-label" for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        class="form-control"
                        placeholder="Enter your email address"
                        autocomplete="email"
                        required
                    >

                </div>

                <!-- PASSWORD -->

                <div class="mb-4">

                    <label class="form-label" for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="btn-login"
                >

                    <i class="fa-solid fa-right-to-bracket me-2"></i>
                    Login

                </button>

            </form>

            <!-- REGISTER LINK -->

            <div class="text-center mt-4">

                <p class="text-muted mb-0">

                    Don't have an account?

                    <a href="register.php"
                       class="register-link">
                        Register
                    </a>

                </p>

            </div>

            <div class="text-center mt-3">

                <small class="text-muted">

                    <i class="fa-solid fa-lock me-1"></i>
                    Authorized personnel only

                </small>

            </div>

        </div>

    </section>

</main>

<!-- =========================
     FOOTER
========================= -->

<footer class="footer">

    <div class="footer-content">

        <!-- FOOTER LOGO -->

        <div class="footer-logo">

            <img
                src="<?= loginEscape($systemLogo) ?>"
                alt="<?= loginEscape($systemName) ?> Logo"
                onerror="this.style.display='none'"
            >

            <div>

                <h5>
                    <?= loginEscape($systemName) ?>
                </h5>

                <p class="mb-0">
                    Electronic Medical Record System
                </p>

            </div>

        </div>

        <!-- SYSTEM INFORMATION -->

        <div>

            <h5>
                <?= loginEscape($systemName) ?>
            </h5>

            <p>
                <i class="fa-regular fa-clock me-2"></i>
                Operation Hours:
            </p>

            <p>Wednesday - Monday: 24 hours</p>

            <p class="mb-0">

                <a href="#home">
                    <i class="fa-solid fa-house me-2"></i>
                    Home
                </a>

            </p>

        </div>

        <!-- CONTACT US -->

        <div>

            <h5>Contact Us</h5>

            <p>

                <span
                    class="copy-item"
                    onclick="copyContactText('+60 3-0000 0000', 'phoneCopied')"
                    title="Click to copy phone number"
                >

                    <i class="fa-solid fa-phone me-1"></i>
                    Phone: +60 3-0000 0000
                    <i class="fa-regular fa-copy ms-1 small"></i>

                </span>

                <span
                    id="phoneCopied"
                    class="copied-badge ms-1"
                >
                    Copied!
                </span>

            </p>

            <p>

                <a
                    href="<?= loginEscape($googleMapsUrl) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Open location in Google Maps"
                >

                    <i class="fa-solid fa-location-dot me-2"></i>
                    Location: Seri Melawati Medgroup, Kuala Lumpur

                </a>

            </p>

            <p class="mb-0">

                <span
                    class="copy-item"
                    onclick="copyContactText('info@meditrack.com', 'emailCopied')"
                    title="Click to copy email"
                >

                    <i class="fa-solid fa-envelope me-1"></i>
                    Email: info@meditrack.com
                    <i class="fa-regular fa-copy ms-1 small"></i>

                </span>

                <span
                    id="emailCopied"
                    class="copied-badge ms-1"
                >
                    Copied!
                </span>

            </p>

        </div>

    </div>

    <!-- COPYRIGHT -->

    <div class="footer-bottom">

        &copy; <?= date("Y") ?>

        <?= loginEscape($systemName) ?>.

        All Rights Reserved.

    </div>

</footer>

<!-- =========================
     THEME SYNC & COPY SCRIPT
========================= -->

<script>

const serverThemeMode = <?= json_encode($themeMode) ?>;

function setLoginTheme(mode, isManual = false) {

    const validMode = (mode === 'dark') ? 'dark' : 'light';

    document.documentElement.setAttribute(
        'data-theme',
        validMode
    );

    const btnLight = document.getElementById('btnLightMode');
    const btnDark = document.getElementById('btnDarkMode');

    if (btnLight && btnDark) {

        btnLight.classList.toggle(
            'active',
            validMode === 'light'
        );

        btnDark.classList.toggle(
            'active',
            validMode === 'dark'
        );

    }

    if (isManual) {

        localStorage.setItem(
            'meditrack_login_theme',
            validMode
        );

        localStorage.setItem(
            'meditrack_server_snapshot',
            serverThemeMode
        );

    }

}

(function initLoginTheme() {

    const lastServerSnapshot =
        localStorage.getItem('meditrack_server_snapshot');

    const manualChoice =
        localStorage.getItem('meditrack_login_theme');

    if (lastServerSnapshot !== serverThemeMode) {

        localStorage.removeItem('meditrack_login_theme');

        localStorage.setItem(
            'meditrack_server_snapshot',
            serverThemeMode
        );

        setLoginTheme(serverThemeMode, false);

    } else if (
        manualChoice === 'dark' ||
        manualChoice === 'light'
    ) {

        setLoginTheme(manualChoice, false);

    } else {

        setLoginTheme(serverThemeMode, false);

    }

})();

// ==========================================
// COPY CONTACT INFORMATION
// ==========================================

async function copyContactText(text, badgeId) {

    let copied = false;

    try {

        if (navigator.clipboard && window.isSecureContext) {

            await navigator.clipboard.writeText(text);

            copied = true;

        } else {

            const tempInput = document.createElement('textarea');

            tempInput.value = text;

            tempInput.style.position = 'fixed';
            tempInput.style.opacity = '0';

            document.body.appendChild(tempInput);

            tempInput.select();

            try {

                copied = document.execCommand('copy');

            } finally {

                document.body.removeChild(tempInput);

            }

        }

    } catch (error) {

        copied = false;

    }

    if (!copied) {

        alert(
            'Unable to copy automatically. Please copy the contact information manually.'
        );

        return;

    }

    const badge = document.getElementById(badgeId);

    if (badge) {

        badge.style.display = 'inline-block';

        setTimeout(() => {

            badge.style.display = 'none';

        }, 1800);

    }

}

</script>

</body>
</html>