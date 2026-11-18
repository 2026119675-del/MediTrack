<?php
require_once '../includes/auth.php';
requireRole(['admin']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Settings";

$settingsFile = __DIR__ . '/../config/appearance.json';

$defaults = [
    'system_name' => 'MediTrack',
    'theme_mode' => 'light',
    'primary_color' => '#05668d',
    'sidebar_color' => '#ffffff',
    'font_family' => 'Arial',
    'font_size' => 14,
    'logo' => '/meditrack/assets/Logo.png'
];

$allowedFonts = [
    'Arial',
    'Poppins',
    'Roboto',
    'Verdana',
    'Tahoma',
    'Georgia'
];

$settings = $defaults;

if (is_file($settingsFile)) {
    $savedSettings = json_decode(
        file_get_contents($settingsFile),
        true
    );

    if (is_array($savedSettings)) {
        $settings = array_merge($defaults, $savedSettings);
    }
}

if (empty($_SESSION['appearance_token'])) {
    $_SESSION['appearance_token'] = bin2hex(random_bytes(32));
}

$success = $_SESSION['appearance_success'] ?? '';
unset($_SESSION['appearance_success']);

$error = '';

function settingEscape($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// SAVE AND RESET SETTINGS

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        if (!hash_equals(
            $_SESSION['appearance_token'],
            (string)($_POST['csrf_token'] ?? '')
        )) {
            throw new RuntimeException(
                'Invalid request. Please refresh the page.'
            );
        }

        $action = $_POST['action'] ?? 'save';

        if ($action === 'reset') {

            $newSettings = $defaults;

            $message =
                'Settings restored to default successfully.';

        } else {

            $systemName = trim(
                $_POST['system_name'] ?? 'MediTrack'
            );

            $themeMode = in_array(($_POST['theme_mode'] ?? 'light'), ['light', 'dark'], true)
                ? $_POST['theme_mode']
                : 'light';

            $primaryColor =
                $_POST['primary_color'] ?? '#05668d';

            $sidebarColor =
                $_POST['sidebar_color'] ?? '#ffffff';

            $fontFamily =
                $_POST['font_family'] ?? 'Arial';

            $fontSize =
                (int)($_POST['font_size'] ?? 14);

            if ($systemName === '' || strlen($systemName) > 50) {
                throw new RuntimeException(
                    'System name must be between 1 and 50 characters.'
                );
            }

            if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $primaryColor)) {
                $primaryColor = '#05668d';
            }

            if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $sidebarColor)) {
                $sidebarColor = '#ffffff';
            }

            if (!in_array($fontFamily, $allowedFonts, true)) {
                $fontFamily = 'Arial';
            }

            $fontSize = max(12, min(20, $fontSize));

            $logo = $settings['logo'];

            // UPLOAD SYSTEM LOGO

            if (
                isset($_FILES['logo']) &&
                $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                $file = $_FILES['logo'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException(
                        'Logo upload failed.'
                    );
                }

                if ($file['size'] > 1024 * 1024) {
                    throw new RuntimeException(
                        'Logo size must be less than 1MB.'
                    );
                }

                if (!is_uploaded_file($file['tmp_name'])) {
                    throw new RuntimeException(
                        'Invalid uploaded file.'
                    );
                }

                $imageInfo = @getimagesize($file['tmp_name']);

                $allowedTypes = [
                    'image/png',
                    'image/jpeg',
                    'image/webp'
                ];

                if (
                    !$imageInfo ||
                    !in_array(
                        $imageInfo['mime'],
                        $allowedTypes,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Only PNG, JPG and WEBP images are allowed.'
                    );
                }

                $imageData = file_get_contents(
                    $file['tmp_name']
                );

                if ($imageData === false) {
                    throw new RuntimeException(
                        'Unable to read logo.'
                    );
                }

                $logo = 'data:' .
                    $imageInfo['mime'] .
                    ';base64,' .
                    base64_encode($imageData);
            }

            $newSettings = [
                'system_name' => $systemName,
                'theme_mode' => $themeMode,
                'primary_color' => $primaryColor,
                'sidebar_color' => $sidebarColor,
                'font_family' => $fontFamily,
                'font_size' => $fontSize,
                'logo' => $logo
            ];

            $message =
                'Your settings have been saved and synced across all users!';
        }

        // SAVE SETTINGS PERMANENTLY

        $json = json_encode(
            $newSettings,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException(
                'Unable to encode appearance settings.'
            );
        }

        $tempFile = tempnam(dirname($settingsFile), 'appearance_');

        if ($tempFile === false) {
            throw new RuntimeException(
                'Unable to create settings file.'
            );
        }

        if (
            file_put_contents($tempFile, $json, LOCK_EX) === false ||
            !rename($tempFile, $settingsFile)
        ) {
            @unlink($tempFile);

            throw new RuntimeException(
                'Unable to save settings. Please check folder permissions.'
            );
        }

        $_SESSION['admin_appearance'] = $newSettings;
        $_SESSION['appearance_success'] = $message;

        header("Location: settings.php");
        exit;

    } catch (Throwable $e) {

        $error = $e->getMessage();

    }
}

require_once '../includes/header.php';
?>

<style>

.settings-page {
    --settings-primary: <?= settingEscape($settings['primary_color']) ?>;
    color: var(--text-main, #263238);
}

.settings-page * {
    box-sizing: border-box;
}

.settings-heading {
    margin-bottom: 25px;
}

.settings-heading h2 {
    font-size: 27px;
    font-weight: 700;
    margin-bottom: 7px;
}

.settings-heading p {
    font-size: 14px;
    color: var(--text-muted, #88929a);
    margin: 0;
}

.settings-banner {
    background: linear-gradient(
        120deg,
        var(--settings-primary),
        var(--primary-dark)
    );
    color: white;
    border-radius: 14px;
    padding: 25px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.settings-banner-icon {
    width: 65px;
    height: 65px;
    flex-shrink: 0;
    background: rgba(255,255,255,.18);
    border-radius: 14px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 30px;
}

.settings-banner h4 {
    font-size: 20px;
    margin-bottom: 6px;
}

.settings-banner p {
    font-size: 13px;
    margin: 0;
    opacity: .85;
}

.settings-grid {
    display: grid;
    grid-template-columns: minmax(0,1.1fr) minmax(0,1fr);
    gap: 24px;
    align-items: start;
}

.settings-panel {
    background: var(--surface-bg, #ffffff);
    padding: 24px;
    border-radius: 13px;
    border: 1px solid var(--border-color, #e9eeee);
    box-shadow: 0 2px 8px rgba(0,0,0,.03);
    margin-bottom: 22px;
}

.settings-panel-heading {
    display: flex;
    gap: 13px;
    align-items: center;
    margin-bottom: 22px;
}

.settings-panel-icon {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border-radius: 10px;
    background: var(--primary-light);
    color: var(--settings-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.settings-panel-heading h5 {
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 4px;
}

.settings-panel-heading p {
    font-size: 12px;
    color: var(--text-muted, #92999f);
    margin: 0;
}

.settings-field {
    margin-bottom: 20px;
}

.settings-field:last-child {
    margin-bottom: 0;
}

.settings-field label {
    display: block;
    font-size: 13px;
    color: var(--text-main, #495057);
    font-weight: 600;
    margin-bottom: 9px;
}

/* THEME MODE CARDS (LIGHT & DARK) */
.theme-mode-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.theme-mode-option {
    position: relative;
    cursor: pointer;
    margin: 0 !important;
}

.theme-mode-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.theme-mode-card {
    border: 2px solid var(--border-color, #dfe6e9);
    border-radius: 10px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s ease;
    background: var(--surface-bg, #ffffff);
}

.theme-mode-option input[type="radio"]:checked + .theme-mode-card {
    border-color: var(--settings-primary);
    background: var(--primary-light);
}

.theme-mode-badge {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.settings-input {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid var(--border-color, #dfe6e9);
    border-radius: 8px;
    font-size: 14px;
    background: var(--surface-bg, white);
    color: var(--text-main, #333);
    outline: none;
}

.settings-input:focus {
    border-color: var(--settings-primary);
    box-shadow: 0 0 0 3px rgba(5,102,141,.08);
}

.settings-hint {
    font-size: 11px;
    color: var(--text-muted, #92999f);
    margin-top: 8px;
    display: block;
}

.settings-logo-area {
    display: flex;
    gap: 18px;
    align-items: center;
    margin-bottom: 18px;
}

.settings-logo-box {
    width: 95px;
    height: 95px;
    flex-shrink: 0;
    border: 2px dashed #cbdcde;
    border-radius: 12px;
    background: #f8fbfb;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.settings-logo-box img {
    width: 75px;
    height: 75px;
    object-fit: contain;
}

.settings-logo-info h6 {
    font-size: 14px;
    margin-bottom: 6px;
}

.settings-logo-info p {
    font-size: 12px;
    color: var(--text-muted, #999);
    margin: 0;
}

.settings-color-field {
    display: flex;
    align-items: center;
    gap: 12px;
}

.settings-color-field input[type="color"] {
    width: 55px;
    height: 43px;
    padding: 4px;
    border: 1px solid var(--border-color, #dfe6e9);
    border-radius: 8px;
    background: white;
    cursor: pointer;
}

.settings-color-field .settings-input {
    flex: 1;
    min-width: 0;
}

.settings-presets {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 15px;
}

.settings-preset {
    width: 32px;
    height: 32px;
    border: 3px solid white;
    border-radius: 50%;
    box-shadow: 0 0 0 1px #dddddd;
    cursor: pointer;
    transition: transform .2s;
}

.settings-preset:hover {
    transform: scale(1.15);
}

.settings-slider {
    width: 100%;
    accent-color: var(--settings-primary);
    cursor: pointer;
}

.settings-slider-labels {
    display: flex;
    justify-content: space-between;
    color: var(--text-muted, #999);
    font-size: 11px;
    margin-top: 5px;
}

.settings-preview-sticky {
    position: sticky;
    top: 25px;
}

.settings-preview-window {
    display: flex;
    min-height: 380px;
    background: #f8fbfb;
    border: 1px solid #dfe6e9;
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.2s ease;
}

.settings-preview-sidebar {
    width: 120px;
    flex-shrink: 0;
    background: white;
    padding: 18px 8px;
    border-right: 1px solid #e9eeee;
    color: #555;
    transition: all 0.2s ease;
}

.settings-preview-brand {
    text-align: center;
    margin-bottom: 28px;
}

.settings-preview-brand img {
    display: block;
    width: 37px;
    height: 37px;
    object-fit: contain;
    margin: 0 auto 8px;
}

.settings-preview-brand strong {
    font-size: .9em;
    overflow-wrap: anywhere;
}

.settings-preview-brand small {
    display: block;
    font-size: .55em;
    margin-top: 5px;
    opacity: .7;
}

.settings-preview-menu {
    padding: 9px 6px;
    margin-bottom: 6px;
    border-radius: 5px;
    font-size: .7em;
}

.settings-preview-menu i {
    margin-right: 3px;
}

.settings-preview-menu.active {
    background: #05668d;
    color: white;
}

.settings-preview-main {
    flex: 1;
    min-width: 0;
}

.settings-preview-topbar {
    background: white;
    border-bottom: 1px solid #eeeeee;
    padding: 13px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .75em;
    transition: all 0.2s ease;
}

.settings-preview-profile {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #e5f4f3;
    color: #05668d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.settings-preview-content {
    padding: 12px;
}

.settings-preview-welcome {
    background: #05668d;
    padding: 14px 12px;
    border-radius: 7px;
    color: white;
    margin-bottom: 12px;
}

.settings-preview-welcome h6 {
    font-size: 1em;
    margin: 0 0 5px;
}

.settings-preview-welcome p {
    font-size: .65em;
    opacity: .85;
    margin: 0;
}

.settings-preview-stats {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 9px;
}

.settings-preview-stat {
    background: white;
    border: 1px solid #edf0f0;
    border-radius: 7px;
    padding: 10px;
    transition: all 0.2s ease;
}

.settings-preview-stat i {
    display: block;
    color: #05668d;
    font-size: 1.15em;
    margin-bottom: 5px;
}

.settings-preview-stat strong {
    font-size: 1.3em;
    display: block;
}

.settings-preview-stat small {
    font-size: .65em;
    color: #899299;
}

.settings-preview-button {
    background: #05668d;
    color: white;
    border: none;
    border-radius: 6px;
    padding: 9px 10px;
    font-size: .75em;
    margin-top: 15px;
}

.settings-preview-note {
    color: var(--text-muted, #999);
    font-size: 11px;
    margin-top: 13px;
}

.settings-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 18px 0;
}

.settings-save {
    border: none;
    background: var(--settings-primary);
    color: white;
    padding: 12px 22px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.settings-reset {
    border: 1px solid var(--border-color, #dfe6e9);
    background: var(--surface-bg, white);
    color: var(--text-muted, #666);
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
}

.settings-save:hover,
.settings-reset:hover {
    opacity: .85;
}

.settings-message {
    padding: 14px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.settings-success {
    color: #176b45;
    background: #e7f7ef;
    border: 1px solid #ccebdc;
}

.settings-error {
    color: #a52626;
    background: #fceaea;
    border: 1px solid #f2cece;
}

@media (max-width: 1100px) {
    .settings-grid {
        grid-template-columns: 1fr;
    }

    .settings-preview-sticky {
        position: static;
    }
}

@media (max-width: 576px) {
    .settings-panel {
        padding: 16px;
    }

    .settings-preview-sidebar {
        width: 85px;
    }

    .settings-logo-area {
        flex-wrap: wrap;
    }

    .settings-actions {
        flex-wrap: wrap;
    }

    .settings-banner {
        padding: 15px;
    }

    .theme-mode-grid {
        grid-template-columns: 1fr;
    }
}

</style>

<div class="settings-page">

    <div class="settings-heading">

        <h2>System Settings</h2>

        <p>
            Manage and personalize your
            <?= settingEscape($settings['system_name']) ?>
            interface across all user roles.
        </p>

    </div>

    <div class="settings-banner">

        <div class="settings-banner-icon">
            <i class="bi bi-gear"></i>
        </div>

        <div>

            <h4>Personalize &amp; Sync Workspace</h4>

            <p>
                Changes to Light/Dark Mode, colors, logo, and typography
                will automatically sync across Admin, Doctor, and Receptionist accounts.
            </p>

        </div>

    </div>

    <?php if ($success): ?>

        <div class="settings-message settings-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= settingEscape($success) ?>
        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="settings-message settings-error">
            <i class="bi bi-exclamation-circle-fill"></i>
            <?= settingEscape($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST"
          action="settings.php"
          enctype="multipart/form-data"
          id="appearanceForm">

        <input type="hidden"
               name="csrf_token"
               value="<?= settingEscape($_SESSION['appearance_token']) ?>">

        <div class="settings-grid">

            <div>

                <!-- DISPLAY MODE (LIGHT / DARK SYNC) -->

                <div class="settings-panel">

                    <div class="settings-panel-heading">

                        <div class="settings-panel-icon">
                            <i class="bi bi-circle-half"></i>
                        </div>

                        <div>
                            <h5>Display Mode (Synced Across All Users)</h5>
                            <p>Switch between Light Mode and Dark Mode for the entire system.</p>
                        </div>

                    </div>

                    <div class="theme-mode-grid">

                        <label class="theme-mode-option">
                            <input
                                type="radio"
                                name="theme_mode"
                                value="light"
                                <?= ($settings['theme_mode'] ?? 'light') === 'light' ? 'checked' : '' ?>
                            >
                            <div class="theme-mode-card">
                                <div class="theme-mode-badge" style="background: #fef3c7; color: #d97706;">
                                    <i class="bi bi-sun-fill"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small">Light Mode</div>
                                    <div class="text-muted" style="font-size: 11px;">Bright &amp; clean interface</div>
                                </div>
                            </div>
                        </label>

                        <label class="theme-mode-option">
                            <input
                                type="radio"
                                name="theme_mode"
                                value="dark"
                                <?= ($settings['theme_mode'] ?? 'light') === 'dark' ? 'checked' : '' ?>
                            >
                            <div class="theme-mode-card">
                                <div class="theme-mode-badge" style="background: #1e293b; color: #facc15;">
                                    <i class="bi bi-moon-stars-fill"></i>
                                </div>
                                <div>
                                    <div class="fw-bold small">Dark Mode</div>
                                    <div class="text-muted" style="font-size: 11px;">Easy on the eyes at night</div>
                                </div>
                            </div>
                        </label>

                    </div>

                </div>

                <!-- GENERAL SETTINGS -->

                <div class="settings-panel">

                    <div class="settings-panel-heading">

                        <div class="settings-panel-icon">
                            <i class="bi bi-sliders"></i>
                        </div>

                        <div>
                            <h5>General Settings</h5>
                            <p>Manage your system identity.</p>
                        </div>

                    </div>

                    <div class="settings-field">

                        <label for="systemName">
                            System Name
                        </label>

                        <input
                            type="text"
                            name="system_name"
                            id="systemName"
                            class="settings-input"
                            value="<?= settingEscape($settings['system_name']) ?>"
                            maxlength="50"
                            required
                        >

                        <small class="settings-hint">
                            This name will appear in the system sidebar.
                        </small>

                    </div>

                </div>

                <!-- SYSTEM BRANDING -->

                <div class="settings-panel">

                    <div class="settings-panel-heading">

                        <div class="settings-panel-icon">
                            <i class="bi bi-image"></i>
                        </div>

                        <div>
                            <h5>System Branding</h5>
                            <p>Customize your system logo.</p>
                        </div>

                    </div>

                    <div class="settings-logo-area">

                        <div class="settings-logo-box">

                            <img
                                src="<?= settingEscape($settings['logo']) ?>"
                                id="currentLogo"
                                alt="System Logo"
                            >

                        </div>

                        <div class="settings-logo-info">

                            <h6>System Logo</h6>

                            <p>
                                Recommended: 200 × 200 pixels.
                            </p>

                        </div>

                    </div>

                    <div class="settings-field">

                        <label for="logoInput">
                            Upload New Logo
                        </label>

                        <input
                            type="file"
                            name="logo"
                            id="logoInput"
                            class="settings-input"
                            accept="image/png,image/jpeg,image/webp"
                        >

                        <small class="settings-hint">
                            PNG, JPG or WEBP. Maximum size: 1MB.
                        </small>

                    </div>

                </div>

                <!-- THEME CUSTOMIZATION -->

                <div class="settings-panel">

                    <div class="settings-panel-heading">

                        <div class="settings-panel-icon">
                            <i class="bi bi-palette"></i>
                        </div>

                        <div>
                            <h5>Theme Customization</h5>
                            <p>Personalize your interface colors.</p>
                        </div>

                    </div>

                    <div class="settings-field">

                        <label for="primaryColor">
                            Primary Theme Color
                        </label>

                        <div class="settings-color-field">

                            <input
                                type="color"
                                name="primary_color"
                                id="primaryColor"
                                value="<?= settingEscape($settings['primary_color']) ?>"
                            >

                            <input
                                type="text"
                                class="settings-input"
                                id="primaryHex"
                                readonly
                            >

                        </div>

                        <div class="settings-presets">

                            <?php

                            $presets = [
                                '#05668d' => 'Ocean Blue',
                                '#028090' => 'Teal',
                                '#800020' => 'Maroon',
                                '#7c3aed' => 'Purple',
                                '#15803d' => 'Green',
                                '#334155' => 'Slate'
                            ];

                            foreach ($presets as $color => $label):

                            ?>

                                <button type="button"
                                        class="settings-preset"
                                        data-color="<?= settingEscape($color) ?>"
                                        style="background:<?= settingEscape($color) ?>"
                                        title="<?= settingEscape($label) ?>">
                                </button>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="settings-field">

                        <label for="sidebarColor">
                            Sidebar Background Color (Light Mode)
                        </label>

                        <div class="settings-color-field">

                            <input
                                type="color"
                                name="sidebar_color"
                                id="sidebarColor"
                                value="<?= settingEscape($settings['sidebar_color']) ?>"
                            >

                            <input
                                type="text"
                                class="settings-input"
                                id="sidebarHex"
                                readonly
                            >

                        </div>

                    </div>

                </div>

                <!-- TYPOGRAPHY -->

                <div class="settings-panel">

                    <div class="settings-panel-heading">

                        <div class="settings-panel-icon">
                            <i class="bi bi-fonts"></i>
                        </div>

                        <div>
                            <h5>Typography</h5>
                            <p>Adjust your preferred font and text size.</p>
                        </div>

                    </div>

                    <div class="settings-field">

                        <label for="fontFamily">
                            Font Family
                        </label>

                        <select
                            name="font_family"
                            id="fontFamily"
                            class="settings-input"
                        >

                            <?php foreach ($allowedFonts as $font): ?>

                                <option
                                    value="<?= settingEscape($font) ?>"
                                    <?= $settings['font_family'] === $font
                                        ? 'selected' : '' ?>
                                >

                                    <?= settingEscape($font) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="settings-field">

                        <label for="fontSize">

                            Font Size:

                            <span id="fontSizeDisplay">
                                <?= (int)$settings['font_size'] ?>px
                            </span>

                        </label>

                        <input
                            type="range"
                            name="font_size"
                            id="fontSize"
                            class="settings-slider"
                            min="12"
                            max="20"
                            value="<?= (int)$settings['font_size'] ?>"
                        >

                        <div class="settings-slider-labels">
                            <span>Small</span>
                            <span>Medium</span>
                            <span>Large</span>
                        </div>

                    </div>

                </div>

            </div>

            <!-- LIVE DASHBOARD PREVIEW -->

            <div class="settings-preview-sticky">

                <div class="settings-panel">

                    <div class="settings-panel-heading">

                        <div class="settings-panel-icon">
                            <i class="bi bi-window"></i>
                        </div>

                        <div>
                            <h5>Live Dashboard Preview</h5>
                            <p>Preview changes before saving.</p>
                        </div>

                    </div>

                    <div class="settings-preview-window"
                         id="previewWindow">

                        <div class="settings-preview-sidebar"
                             id="previewSidebar">

                            <div class="settings-preview-brand">

                                <img
                                    src="<?= settingEscape($settings['logo']) ?>"
                                    id="previewLogo"
                                    alt="Logo"
                                >

                                <strong id="previewName">
                                    <?= settingEscape($settings['system_name']) ?>
                                </strong>

                                <small>Admin Panel</small>

                            </div>

                            <div class="settings-preview-menu active"
                                 id="previewActive">

                                <i class="bi bi-grid"></i>
                                Dashboard

                            </div>

                            <div class="settings-preview-menu">
                                <i class="bi bi-people"></i>
                                Patients
                            </div>

                            <div class="settings-preview-menu">
                                <i class="bi bi-calendar-check"></i>
                                Appointments
                            </div>

                            <div class="settings-preview-menu">
                                <i class="bi bi-gear"></i>
                                Settings
                            </div>

                        </div>

                        <div class="settings-preview-main">

                            <div class="settings-preview-topbar" id="previewTopbar">

                                <strong>Dashboard</strong>

                                <div class="settings-preview-profile"
                                     id="previewProfile">
                                    A
                                </div>

                            </div>

                            <div class="settings-preview-content">

                                <div class="settings-preview-welcome"
                                     id="previewWelcome">

                                    <h6>Welcome back, Admin!</h6>

                                    <p>
                                        Manage and monitor your system.
                                    </p>

                                </div>

                                <div class="settings-preview-stats">

                                    <div class="settings-preview-stat">

                                        <i class="bi bi-people"></i>

                                        <strong>25</strong>

                                        <small>Total Users</small>

                                    </div>

                                    <div class="settings-preview-stat">

                                        <i class="bi bi-person-heart"></i>

                                        <strong>120</strong>

                                        <small>Total Patients</small>

                                    </div>

                                    <div class="settings-preview-stat">

                                        <i class="bi bi-calendar-check"></i>

                                        <strong>18</strong>

                                        <small>Appointments</small>

                                    </div>

                                    <div class="settings-preview-stat">

                                        <i class="bi bi-person-badge"></i>

                                        <strong>8</strong>

                                        <small>Total Doctors</small>

                                    </div>

                                </div>

                                <button
                                    type="button"
                                    class="settings-preview-button"
                                    id="previewButton"
                                >

                                    <i class="bi bi-plus-lg"></i>
                                    Add Patient

                                </button>

                            </div>

                        </div>

                    </div>

                    <p class="settings-preview-note">

                        <i class="bi bi-info-circle"></i>

                        This preview uses sample dashboard data.

                    </p>

                </div>

            </div>

        </div>

        <!-- SAVE AND RESET BUTTONS -->

        <div class="settings-actions">

            <button
                type="submit"
                name="action"
                value="reset"
                class="settings-reset"
                formnovalidate
                onclick="return confirm('Reset all appearance settings to default?')"
            >

                <i class="bi bi-arrow-counterclockwise"></i>
                Reset to Default

            </button>

            <button
                type="submit"
                name="action"
                value="save"
                class="settings-save"
            >

                <i class="bi bi-check-lg"></i>
                Save Changes

            </button>

        </div>

    </form>

</div>

<script>

const settingsPrimary = document.getElementById('primaryColor');
const settingsSidebar = document.getElementById('sidebarColor');
const settingsFont = document.getElementById('fontFamily');
const settingsSize = document.getElementById('fontSize');
const settingsName = document.getElementById('systemName');
const themeRadios = document.querySelectorAll('input[name="theme_mode"]');

function getSelectedThemeMode() {
    const checked = document.querySelector('input[name="theme_mode"]:checked');
    return checked ? checked.value : 'light';
}

function isColorDark(hex) {
    const clean = hex.replace('#', '');
    const r = parseInt(clean.substr(0, 2), 16);
    const g = parseInt(clean.substr(2, 2), 16);
    const b = parseInt(clean.substr(4, 2), 16);
    return ((r * 299 + g * 587 + b * 114) / 1000) < 145;
}

function updateSettingsPreview() {

    const primary = settingsPrimary.value;
    const sidebar = settingsSidebar.value;
    const font = settingsFont.value;
    const size = settingsSize.value;
    const mode = getSelectedThemeMode();
    const isDark = (mode === 'dark');

    document.getElementById('primaryHex').value = primary.toUpperCase();
    document.getElementById('sidebarHex').value = sidebar.toUpperCase();
    document.getElementById('previewName').textContent = settingsName.value || 'MediTrack';
    document.getElementById('fontSizeDisplay').textContent = size + 'px';

    const previewWindow = document.getElementById('previewWindow');
    const previewSidebar = document.getElementById('previewSidebar');
    const previewTopbar = document.getElementById('previewTopbar');
    const previewStats = document.querySelectorAll('.settings-preview-stat');

    previewWindow.style.fontFamily = `'${font}', sans-serif`;
    previewWindow.style.fontSize = size + 'px';

    // Apply Light vs Dark Mode to Preview
    const effectiveSidebar = (isDark && sidebar.toLowerCase() === '#ffffff') ? '#161b22' : sidebar;
    previewWindow.style.backgroundColor = isDark ? '#0d1117' : '#f8fbfb';
    previewWindow.style.borderColor = isDark ? '#30363d' : '#dfe6e9';

    previewSidebar.style.backgroundColor = effectiveSidebar;
    previewSidebar.style.color = isColorDark(effectiveSidebar) ? '#ffffff' : '#555555';
    previewSidebar.style.borderRightColor = isDark ? '#30363d' : '#e9eeee';

    previewTopbar.style.backgroundColor = isDark ? '#161b22' : '#ffffff';
    previewTopbar.style.color = isDark ? '#e6edf3' : '#263238';
    previewTopbar.style.borderBottomColor = isDark ? '#30363d' : '#eeeeee';

    previewStats.forEach(card => {
        card.style.backgroundColor = isDark ? '#161b22' : '#ffffff';
        card.style.color = isDark ? '#e6edf3' : '#263238';
        card.style.borderColor = isDark ? '#30363d' : '#edf0f0';
        const icon = card.querySelector('i');
        if (icon) icon.style.color = isDark ? '#58a6ff' : primary;
    });

    document.getElementById('previewActive').style.backgroundColor = primary;
    document.getElementById('previewWelcome').style.backgroundColor = primary;
    document.getElementById('previewButton').style.backgroundColor = primary;
}

document.querySelectorAll('.settings-preset').forEach(btn => {
    btn.addEventListener('click', () => {
        settingsPrimary.value = btn.dataset.color;
        updateSettingsPreview();
    });
});

document.getElementById('logoInput').addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('currentLogo').src = e.target.result;
        document.getElementById('previewLogo').src = e.target.result;
    };
    reader.readAsDataURL(file);
});

[settingsPrimary, settingsSidebar, settingsFont, settingsSize, settingsName].forEach(el => {
    el.addEventListener('input', updateSettingsPreview);
    el.addEventListener('change', updateSettingsPreview);
});

themeRadios.forEach(radio => {
    radio.addEventListener('change', updateSettingsPreview);
});

updateSettingsPreview();
</script>

<?php require_once '../includes/footer.php'; ?>