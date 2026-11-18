<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// LOAD GENERAL INFORMATION

$pageTitle = $pageTitle ?? 'Dashboard';

$userRole = strtolower(
    trim((string)($_SESSION['role'] ?? 'user'))
);

$role = ucfirst($userRole);

$fullName = $_SESSION['full_name'] ?? 'User';

$currentPage = basename($_SERVER['PHP_SELF']);

// LOAD SAVED SYSTEM APPEARANCE

$appearanceDefaults = [

    'system_name' => 'MediTrack',

    'theme_mode' => 'light',

    'primary_color' => '#05668d',

    'sidebar_color' => '#ffffff',

    'font_family' => 'Arial',

    'font_size' => 14,

    'logo' => '/meditrack/assets/Logo.png'

];

$appearance = $appearanceDefaults;

$appearanceFile =
    __DIR__ . '/../config/appearance.json';

if (is_file($appearanceFile)) {

    $appearanceContent = file_get_contents(
        $appearanceFile
    );

    if ($appearanceContent !== false) {

        $savedAppearance = json_decode(
            $appearanceContent,
            true
        );

        if (is_array($savedAppearance)) {

            $appearance = array_merge(
                $appearanceDefaults,
                $savedAppearance
            );

        }

    }

}

// VALIDATE APPEARANCE SETTINGS

$systemName = is_string($appearance['system_name'])
    ? $appearance['system_name']
    : 'MediTrack';

$themeMode = in_array(($appearance['theme_mode'] ?? 'light'), ['light', 'dark'], true)
    ? $appearance['theme_mode']
    : 'light';

$isDarkMode = ($themeMode === 'dark');

$primaryColor = $appearance['primary_color'];

$sidebarColor = $appearance['sidebar_color'];

$fontFamily = $appearance['font_family'];

$fontSize = max(
    12,
    min(20, (int)$appearance['font_size'])
);

$systemLogo = $appearance['logo'];

if (!is_string($primaryColor) ||
    !preg_match('/^#[0-9a-fA-F]{6}$/D', $primaryColor)) {

    $primaryColor = '#05668d';

}

if (!is_string($sidebarColor) ||
    !preg_match('/^#[0-9a-fA-F]{6}$/D', $sidebarColor)) {

    $sidebarColor = '#ffffff';

}

$headerAllowedFonts = [
    'Arial',
    'Poppins',
    'Roboto',
    'Verdana',
    'Tahoma',
    'Georgia'
];

if (!in_array($fontFamily, $headerAllowedFonts, true)) {

    $fontFamily = 'Arial';

}

if (
    !is_string($systemLogo) ||
    (
        $systemLogo !== $appearanceDefaults['logo'] &&
        !preg_match(
            '#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#D',
            $systemLogo
        )
    )
) {

    $systemLogo = $appearanceDefaults['logo'];

}

// GENERATE MATCHING THEME COLORS

function meditrackAdjustColor($hex, $factor) {

    $hex = ltrim($hex, '#');

    $red = hexdec(substr($hex, 0, 2));
    $green = hexdec(substr($hex, 2, 2));
    $blue = hexdec(substr($hex, 4, 2));

    $red = max(0, min(255, round($red * $factor)));
    $green = max(0, min(255, round($green * $factor)));
    $blue = max(0, min(255, round($blue * $factor)));

    return sprintf(
        '#%02x%02x%02x',
        $red,
        $green,
        $blue
    );

}

function meditrackLightColor($hex, $amount = 0.9) {

    $hex = ltrim($hex, '#');

    $red = hexdec(substr($hex, 0, 2));
    $green = hexdec(substr($hex, 2, 2));
    $blue = hexdec(substr($hex, 4, 2));

    $red = round($red + (255 - $red) * $amount);
    $green = round($green + (255 - $green) * $amount);
    $blue = round($blue + (255 - $blue) * $amount);

    return sprintf(
        '#%02x%02x%02x',
        $red,
        $green,
        $blue
    );

}

function meditrackTextContrast($hex) {

    $hex = ltrim($hex, '#');

    $red = hexdec(substr($hex, 0, 2));
    $green = hexdec(substr($hex, 2, 2));
    $blue = hexdec(substr($hex, 4, 2));

    $brightness =
        ($red * 299 + $green * 587 + $blue * 114)
        / 1000;

    return $brightness < 145
        ? '#ffffff'
        : '#333333';

}

$primaryDark = meditrackAdjustColor(
    $primaryColor,
    0.80
);

$primaryLight = meditrackLightColor(
    $primaryColor,
    0.90
);

// Jika Dark Mode aktif dan sidebar masih putih asal, tukar ke permukaan gelap
$effectiveSidebarColor = ($isDarkMode && strtolower($sidebarColor) === '#ffffff')
    ? '#161b22'
    : $sidebarColor;

$sidebarText = meditrackTextContrast(
    $effectiveSidebarColor
);

$primaryText = meditrackTextContrast(
    $primaryColor
);

$brandColor = $sidebarText === '#ffffff'
    ? '#ffffff'
    : $primaryColor;

$themeHex = ltrim($primaryColor, '#');

$primaryRgb = implode(',', [
    hexdec(substr($themeHex, 0, 2)),
    hexdec(substr($themeHex, 2, 2)),
    hexdec(substr($themeHex, 4, 2))
]);

// RECEPTIONIST MENU

$receptionistMenu = [

    'dashboard.php' =>
        ['Dashboard', 'bi-grid'],

    'patient.php' =>
        ['Patient Management', 'bi-people'],

    'appointments.php' =>
        ['Appointment', 'bi-calendar-check'],

    'medical_records.php' =>
        ['Medical Records', 'bi-clipboard2-pulse'],

    'prescriptions.php' =>
        ['Prescriptions', 'bi-capsule'],

    'billing.php' =>
        ['Billing & Payments', 'bi-receipt']

];

// DOCTOR MENU

$doctorMenu = [

    'dashboard.php' =>
        ['Dashboard', 'bi-grid'],

    'patients.php' =>
        ['Patients', 'bi-people'],

    'appointments.php' =>
        ['Appointments', 'bi-calendar-check'],

    'prescriptions.php' =>
        ['Prescriptions', 'bi-capsule']

];

// ADMIN MENU

$adminMenu = [

    'dashboard.php' =>
        ['Dashboard', 'bi-grid'],

    'patients.php' =>
        ['Patient Management', 'bi-people'],

    'appointments.php' =>
        ['Appointment Management', 'bi-calendar-check'],

    'medical_records.php' =>
        ['Medical Records', 'bi-clipboard2-pulse'],

    'settings.php' =>
        ['Settings', 'bi-gear']

];

$menu = [];
$menuFolder = '';

if ($userRole === 'doctor') {

    $menu = $doctorMenu;
    $menuFolder = 'doctor';

} elseif ($userRole === 'receptionist') {

    $menu = $receptionistMenu;
    $menuFolder = 'receptionist';

} elseif ($userRole === 'admin') {

    $menu = $adminMenu;
    $menuFolder = 'admin';

}

?>

<!DOCTYPE html>

<html lang="en" data-bs-theme="<?= $isDarkMode ? 'dark' : 'light' ?>">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
        |
        <?= htmlspecialchars($systemName, ENT_QUOTES, 'UTF-8') ?>
    </title>

    <!-- BOOTSTRAP -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- BOOTSTRAP ICONS -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <!-- GOOGLE FONTS -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>

        :root {

            --primary: <?= $primaryColor ?>;

            --primary-dark: <?= $primaryDark ?>;

            --primary-light: <?= $isDarkMode ? "rgba($primaryRgb, 0.22)" : $primaryLight ?>;

            --primary-text: <?= $primaryText ?>;

            --sidebar-background: <?= $effectiveSidebarColor ?>;

            --sidebar-text: <?= $sidebarText ?>;

            --brand-color: <?= $brandColor ?>;

            --system-font:
                '<?= $fontFamily ?>', sans-serif;

            --system-font-size: <?= $fontSize ?>px;

            --background: <?= $isDarkMode ? '#0d1117' : '#f8fbfb' ?>;

            --surface-bg: <?= $isDarkMode ? '#161b22' : '#ffffff' ?>;

            --border-color: <?= $isDarkMode ? '#30363d' : '#eeeeee' ?>;

            --text-main: <?= $isDarkMode ? '#e6edf3' : '#263238' ?>;

            --text-muted: <?= $isDarkMode ? '#8b949e' : '#888888' ?>;

            --secondary: <?= $primaryDark ?>;

            --teal: #00a896;

            --green: #02c39a;

            --cream: #f0f3bd;

            --white: #ffffff;

            --sidebar-width: 250px;

            --bs-primary: <?= $primaryColor ?>;

            --bs-primary-rgb: <?= $primaryRgb ?>;

            --bs-body-font-family: var(--system-font);

            --bs-body-font-size: var(--system-font-size);

        }

        html {
            font-size: var(--system-font-size);
        }

        body {

            margin: 0;

            background: var(--background);

            color: var(--text-main);

            font-family: var(--system-font);

            font-size: var(--system-font-size);

            min-height: 100vh;

        }

        button,
        input,
        select,
        textarea {

            font-family: var(--system-font);

        }

        /* SIDEBAR */

        .sidebar {

            width: var(--sidebar-width);

            height: 100vh;

            position: fixed;

            top: 0;

            left: 0;

            background: var(--sidebar-background);

            border-right: 1px solid var(--border-color);

            padding: 25px 15px;

            z-index: 1000;

            overflow-y: auto;

        }

        .brand {

            color: var(--brand-color);

            font-size: 1.8rem;

            font-weight: bold;

            padding: 0 12px;

            display: flex;

            align-items: center;

            gap: 9px;

            overflow-wrap: anywhere;

        }

        .brand-logo {

            width: 38px;

            height: 38px;

            flex-shrink: 0;

            object-fit: contain;

            border-radius: 5px;

        }

        .brand-fallback {

            font-size: 27px;

        }

        .brand-name {

            min-width: 0;

        }

        .brand-subtitle {

            font-size: .8rem;

            color: var(--sidebar-text);

            opacity: .65;

            padding: 0 12px;

            margin-top: 6px;

            margin-bottom: 30px;

        }

        .nav-label {

            font-size: .8rem;

            color: var(--sidebar-text);

            opacity: .65;

            font-weight: bold;

            padding: 10px 12px;

            text-transform: uppercase;

        }

        .sidebar .nav-link {

            color: var(--sidebar-text);

            padding: 12px;

            border-radius: 8px;

            margin-bottom: 5px;

            font-size: 1rem;

        }

        .sidebar .nav-link i {

            margin-right: 10px;

            font-size: 1.2rem;

        }

        .sidebar .nav-link.active {

            background: var(--primary);

            color: var(--primary-text);

        }

        .sidebar .nav-link:hover:not(.active) {

            background: var(--primary-light);

            color: <?= $isDarkMode ? '#ffffff' : 'var(--primary)' ?>;

        }

        /* MAIN CONTENT */

        .main-content {

            margin-left: var(--sidebar-width);

            min-height: 100vh;

            display: flex;

            flex-direction: column;

        }

        .topbar {

            background: var(--surface-bg);

            padding: 18px 30px;

            border-bottom: 1px solid var(--border-color);

            display: flex;

            justify-content: space-between;

            align-items: center;

        }

        .content-area {

            padding: 30px;

            flex: 1;

        }

        /* DASHBOARD */

        .welcome-card {

            background: var(--primary);

            color: var(--primary-text);

            padding: 28px;

            border-radius: 12px;

            margin-bottom: 25px;

        }

        .stat-card,
        .section-card {

            background: var(--surface-bg);

            border: 1px solid <?= $isDarkMode ? 'var(--border-color)' : 'transparent' ?>;

            border-radius: 12px;

            padding: 22px;

            box-shadow: 0 2px 8px rgba(0,0,0,<?= $isDarkMode ? '0.25' : '0.04' ?>);

        }

        .stat-icon {

            width: 45px;

            height: 45px;

            border-radius: 10px;

            background: var(--primary-light);

            color: <?= $isDarkMode ? '#ffffff' : 'var(--primary)' ?>;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 1.5rem;

        }

        .stat-number {

            font-size: 1.9rem;

            font-weight: bold;

            margin-top: 15px;

            color: var(--text-main);

        }

        .stat-label {

            color: var(--text-muted);

            font-size: .93rem;

        }

        .profile-circle {

            width: 38px;

            height: 38px;

            border-radius: 50%;

            background: var(--primary-light);

            color: <?= $isDarkMode ? '#ffffff' : 'var(--primary)' ?>;

            display: flex;

            justify-content: center;

            align-items: center;

            font-weight: bold;

        }

        /* BUTTONS */

        .btn-main,
        .btn-primary {

            background: var(--primary);

            color: var(--primary-text);

            border: 1px solid var(--primary);

            --bs-btn-bg: var(--primary);

            --bs-btn-border-color: var(--primary);

            --bs-btn-color: var(--primary-text);

            --bs-btn-hover-bg: var(--primary-dark);

            --bs-btn-hover-border-color: var(--primary-dark);

            --bs-btn-hover-color: #ffffff;

            --bs-btn-active-bg: var(--primary-dark);

            --bs-btn-active-border-color: var(--primary-dark);

        }

        .btn-main:hover,
        .btn-primary:hover {

            background: var(--primary-dark);

            border-color: var(--primary-dark);

            color: white;

        }

        .btn-outline-primary {

            --bs-btn-color: var(--primary);

            --bs-btn-border-color: var(--primary);

            --bs-btn-hover-bg: var(--primary);

            --bs-btn-hover-border-color: var(--primary);

            --bs-btn-hover-color: var(--primary-text);

            --bs-btn-active-bg: var(--primary-dark);

            --bs-btn-active-border-color: var(--primary-dark);

        }

        /* TABLES */

        .table thead th {

            background: var(--primary-light);

            color: <?= $isDarkMode ? '#ffffff' : 'var(--primary)' ?>;

            white-space: nowrap;

            font-size: .93rem;

        }

        .table td {

            vertical-align: middle;

            font-size: .93rem;

        }

        /* DARK MODE COMPATIBILITY FOR HARDCODED LIGHT BOXES */
        <?php if ($isDarkMode): ?>
        [data-bs-theme="dark"] .text-dark {
            color: #e6edf3 !important;
        }
        [data-bs-theme="dark"] .bg-white,
        [data-bs-theme="dark"] .bg-light,
        [data-bs-theme="dark"] .detail-box,
        [data-bs-theme="dark"] .record-form,
        [data-bs-theme="dark"] .quick-access-item,
        [data-bs-theme="dark"] .settings-panel,
        [data-bs-theme="dark"] .settings-input,
        [data-bs-theme="dark"] .settings-reset {
            background-color: #161b22 !important;
            color: #e6edf3 !important;
            border-color: #30363d !important;
        }
        [data-bs-theme="dark"] .settings-page,
        [data-bs-theme="dark"] .settings-field label {
            color: #e6edf3 !important;
        }
        [data-bs-theme="dark"] .allergy-card,
        [data-bs-theme="dark"] .allergy-banner {
            background-color: #2b1519 !important;
            border-color: #6e222b !important;
            color: #f8d7da !important;
        }
        <?php endif; ?>

        /* FORM CONTROLS */

        .form-control:focus,
        .form-select:focus {

            border-color: var(--primary);

            box-shadow: 0 0 0 .15rem
                rgba(<?= $primaryRgb ?>, .15);

        }

        /* FOOTER */

        .page-footer {

            background: var(--surface-bg);

            border-top: 1px solid var(--border-color);

            padding: 16px;

            text-align: center;

            color: var(--text-muted);

            font-size: .85rem;

            width: 100%;

            margin-top: auto;

        }

        /* RESPONSIVE DESIGN */

        @media (max-width: 768px) {

            .sidebar {

                width: 70px;

                padding: 20px 8px;

            }

            .brand {

                font-size: 13px;

                padding: 0;

                justify-content: center;

                text-align: center;

            }

            .brand-logo {

                width: 38px;

                height: 38px;

            }

            .brand-name,

            .brand-subtitle,

            .nav-label,

            .sidebar .nav-link span {

                display: none;

            }

            .sidebar .nav-link {

                text-align: center;

            }

            .sidebar .nav-link i {

                margin: 0;

            }

            .main-content {

                margin-left: 70px;

            }

            .content-area {

                padding: 15px;

            }

            .topbar {

                padding: 15px;

            }

        }

    </style>

</head>

<body>

<!-- SIDEBAR -->

<div class="sidebar">

    <div class="brand">

        <img
            src="<?= htmlspecialchars($systemLogo, ENT_QUOTES, 'UTF-8') ?>"
            alt="System Logo"
            class="brand-logo"
            onerror="this.style.display='none';document.getElementById('brandFallback').style.display='inline-block';"
        >

        <i
            class="bi bi-heart-pulse-fill brand-fallback"
            id="brandFallback"
            style="display:none;"
        ></i>

        <span class="brand-name">

            <?= htmlspecialchars(
                $systemName,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </span>

    </div>

    <div class="brand-subtitle">

        Electronic Medical Record System

    </div>

    <div class="nav-label">

        <?= htmlspecialchars(
            $role,
            ENT_QUOTES,
            'UTF-8'
        ) ?> Menu

    </div>

    <nav class="nav flex-column">

        <?php foreach ($menu as $file => $item): ?>

            <a

                href="/meditrack/<?= htmlspecialchars($menuFolder) ?>/<?= htmlspecialchars($file) ?>"

                class="nav-link <?= $currentPage === $file ? 'active' : '' ?>"

            >

                <i class="bi <?= htmlspecialchars($item[1]) ?>"></i>

                <span>

                    <?= htmlspecialchars(
                        $item[0],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </a>

        <?php endforeach; ?>

    </nav>

</div>

<!-- MAIN CONTENT -->

<div class="main-content">

    <!-- TOPBAR -->

    <div class="topbar">

        <div class="d-flex align-items-center gap-2">
            <h5 class="mb-0">

                <?= htmlspecialchars(
                    $pageTitle,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h5>

            <span class="badge rounded-pill <?= $isDarkMode ? 'bg-dark border text-light' : 'bg-light border text-dark' ?> small">
                <i class="bi <?= $isDarkMode ? 'bi-moon-stars-fill text-warning' : 'bi-sun-fill text-warning' ?> me-1"></i>
                <?= $isDarkMode ? 'Dark Mode' : 'Light Mode' ?>
            </span>
        </div>

        <div class="d-flex align-items-center gap-3">

            <div class="text-end d-none d-sm-block">

                <div class="fw-semibold small">

                    <?= htmlspecialchars(
                        $fullName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

                <div class="text-muted small">

                    <?= htmlspecialchars(
                        $role,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>

            <div class="profile-circle">

                <?= htmlspecialchars(
                    strtoupper(substr($fullName, 0, 1)),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

            <a

                href="/meditrack/logout.php"

                class="btn btn-outline-danger btn-sm"

            >

                <i class="bi bi-box-arrow-right"></i>

                Logout

            </a>

        </div>

    </div>

    <main class="content-area">