<?php

// Apply customization for Admin only

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    strtolower(trim((string) ($_SESSION['role'] ?? ''))) !== 'admin'
) {
    return;
}

if (
    empty($_SESSION['admin_appearance']) ||
    !is_array($_SESSION['admin_appearance'])
) {
    return;
}

$appearanceDefaults = [
    'system_name' => 'MediTrack',
    'primary_color' => '#05668d',
    'sidebar_color' => '#ffffff',
    'font_family' => 'Arial',
    'font_size' => 14,
    'logo' => '/meditrack/assets/Logo.png'
];

$adminAppearance = array_merge(
    $appearanceDefaults,
    $_SESSION['admin_appearance']
);

// Validate theme color

$appearancePrimary = preg_match(
    '/^#[0-9a-fA-F]{6}$/D',
    $adminAppearance['primary_color']
) ? $adminAppearance['primary_color'] : '#05668d';

// Validate sidebar color

$appearanceSidebar = preg_match(
    '/^#[0-9a-fA-F]{6}$/D',
    $adminAppearance['sidebar_color']
) ? $adminAppearance['sidebar_color'] : '#ffffff';

// Validate font

$appearanceFonts = [
    'Arial',
    'Poppins',
    'Roboto',
    'Verdana',
    'Tahoma',
    'Georgia'
];

$appearanceFont = in_array(
    $adminAppearance['font_family'],
    $appearanceFonts,
    true
) ? $adminAppearance['font_family'] : 'Arial';

$appearanceSize = max(
    12,
    min(20, (int) $adminAppearance['font_size'])
);

// Automatic sidebar text color

$appearanceRed = hexdec(
    substr($appearanceSidebar, 1, 2)
);

$appearanceGreen = hexdec(
    substr($appearanceSidebar, 3, 2)
);

$appearanceBlue = hexdec(
    substr($appearanceSidebar, 5, 2)
);

$appearanceBrightness = (
    $appearanceRed * 299 +
    $appearanceGreen * 587 +
    $appearanceBlue * 114
) / 1000;

$appearanceText = $appearanceBrightness < 145
    ? '#ffffff'
    : '#555555';

$appearanceBrandColor = $appearanceBrightness < 145
    ? '#ffffff'
    : $appearancePrimary;

// Automatic primary text color

$primaryBrightness = (
    hexdec(substr($appearancePrimary, 1, 2)) * 299 +
    hexdec(substr($appearancePrimary, 3, 2)) * 587 +
    hexdec(substr($appearancePrimary, 5, 2)) * 114
) / 1000;

$appearanceContrast = $primaryBrightness < 145
    ? '#ffffff'
    : '#222222';

?>

<link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
    rel="stylesheet"
>

<style>

    :root {
        --primary: <?= $appearancePrimary ?>;
        --primary-dark: <?= $appearancePrimary ?>;
        --primary-color: <?= $appearancePrimary ?>;
        --secondary: <?= $appearancePrimary ?>;
        --custom-sidebar: <?= $appearanceSidebar ?>;
        --custom-font: '<?= $appearanceFont ?>', sans-serif;
        --custom-font-size: <?= $appearanceSize ?>px;
        --custom-sidebar-text: <?= $appearanceText ?>;
        --custom-contrast: <?= $appearanceContrast ?>;
    }

    /* FONT FAMILY */

    body,
    .main-content,
    .sidebar,
    .topbar,
    .content-area,
    input,
    select,
    textarea,
    button {
        font-family: var(--custom-font) !important;
    }

    /* FONT SIZE */

    body,
    .sidebar .nav-link,
    .content-area p,
    .content-area label,
    .content-area .form-control,
    .content-area .form-select,
    .content-area .btn,
    .table td,
    .table th {
        font-size: var(--custom-font-size) !important;
    }

    /* SIDEBAR */

    .sidebar {
        background: var(--custom-sidebar) !important;
    }

    .sidebar .brand {
        color: <?= $appearanceBrandColor ?> !important;
    }

    .sidebar .nav-link,
    .sidebar .nav-label,
    .sidebar .brand-subtitle {
        color: var(--custom-sidebar-text) !important;
    }

    .sidebar .nav-link.active {
        background: var(--primary) !important;
        color: var(--custom-contrast) !important;
    }

    .sidebar .nav-link:hover:not(.active) {
        background: rgba(128,128,128,.15) !important;
    }

    /* DASHBOARD WELCOME CARD */

    .welcome-card {
        background: var(--primary) !important;
        color: var(--custom-contrast) !important;
    }

    /* BUTTONS */

    .btn-main,
    .btn-primary,
    .settings-save {
        background: var(--primary) !important;
        border-color: var(--primary) !important;
        color: var(--custom-contrast) !important;
    }

    .btn-main:hover,
    .btn-primary:hover,
    .settings-save:hover {
        filter: brightness(.90);
    }

    .btn-outline-primary {
        color: var(--primary) !important;
        border-color: var(--primary) !important;
    }

    .btn-outline-primary:hover {
        background: var(--primary) !important;
        color: var(--custom-contrast) !important;
    }

    /* DASHBOARD ICONS */

    .stat-icon,
    .profile-circle,
    .settings-panel-icon {
        color: var(--primary) !important;
        background: color-mix(
            in srgb,
            var(--primary) 10%,
            white
        ) !important;
    }

    /* TABLE */

    .table thead th {
        background: color-mix(
            in srgb,
            var(--primary) 9%,
            white
        ) !important;

        color: var(--primary) !important;
    }

    /* SETTINGS PAGE */

    .settings-page {
        --settings-primary: var(--primary) !important;
    }

    .settings-banner {
        background: var(--primary) !important;
        color: var(--custom-contrast) !important;
    }

    .settings-slider {
        accent-color: var(--primary) !important;
    }

    /* CUSTOM SYSTEM LOGO */

    .admin-custom-logo {
        width: 38px;
        height: 38px;
        object-fit: contain;
        vertical-align: middle;
        margin-right: 7px;
    }

    .admin-custom-name {
        overflow-wrap: anywhere;
    }

    @media(max-width: 768px) {

        .admin-custom-logo {
            width: 30px;
            height: 30px;
            margin-right: 0;
        }

        .admin-custom-name {
            display: none;
        }

    }

</style>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const appearance = <?= json_encode(
        [
            'name' => $adminAppearance['system_name'],
            'logo' => $adminAppearance['logo']
        ],
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT |
        JSON_INVALID_UTF8_SUBSTITUTE
    ) ?: '{}' ?>;

    // Change sidebar logo and system name

    const brand = document.querySelector('.sidebar .brand');

    if (brand && appearance.name && appearance.logo) {

        const logo = document.createElement('img');

        logo.src = appearance.logo;
        logo.alt = 'System Logo';
        logo.className = 'admin-custom-logo';

        logo.onerror = function () {

            this.onerror = null;
            this.src = '/meditrack/assets/Logo.png';

        };

        const name = document.createElement('span');

        name.className = 'admin-custom-name';
        name.textContent = appearance.name;

        brand.textContent = '';

        brand.appendChild(logo);
        brand.appendChild(name);

    }

    // Change browser tab title

    if (appearance.name) {

        const pageName = document.title.split('|')[0].trim();

        document.title = pageName + ' | ' + appearance.name;

    }

});

</script>