<?php
require_once '../includes/auth.php';
requireRole(['admin']);

require_once '../config/database.php';

$pageTitle = "System Settings";

$defaultSettings = [
    'system_name' => 'ERMS',
    'welcome_message' => 'Manage and monitor your ERMS system from one place.',
    'primary_color' => '#800020',
    'sidebar_color' => '#65001B',
    'font_family' => 'Poppins',
    'font_size' => 14,
    'logo' => null
];

$allowedFonts = [
    'Poppins',
    'Arial',
    'Roboto',
    'Verdana',
    'Georgia',
    'Tahoma'
];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getSettings($pdo, $defaults) {
    $stmt = $pdo->query("SELECT * FROM system_settings WHERE id = 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    return array_merge($defaults, $settings ?: []);
}

function validColor($value, $fallback) {
    return preg_match('/^#[0-9A-Fa-f]{6}$/', $value)
        ? $value
        : $fallback;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['settings_csrf'])) {
    $_SESSION['settings_csrf'] = bin2hex(random_bytes(32));
}

$success = '';
$error = '';

try {
    $settings = getSettings($pdo, $defaultSettings);
} catch (PDOException $e) {
    die("Please create the system_settings table first.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals(
        $_SESSION['settings_csrf'],
        $_POST['csrf_token'] ?? ''
    )) {
        $error = "Invalid request. Please refresh the page.";
    } else {
        try {
            $action = $_POST['action'] ?? 'save';

            if ($action === 'reset') {
                $stmt = $pdo->prepare("
                    UPDATE system_settings SET
                        system_name = ?,
                        welcome_message = ?,
                        primary_color = ?,
                        sidebar_color = ?,
                        font_family = ?,
                        font_size = ?,
                        logo = NULL
                    WHERE id = 1
                ");

                $stmt->execute([
                    $defaultSettings['system_name'],
                    $defaultSettings['welcome_message'],
                    $defaultSettings['primary_color'],
                    $defaultSettings['sidebar_color'],
                    $defaultSettings['font_family'],
                    $defaultSettings['font_size']
                ]);

                $success = "Settings restored to default successfully!";
            } else {

                $systemName = trim($_POST['system_name'] ?? 'ERMS');
                $welcomeMessage = trim($_POST['welcome_message'] ?? '');

                $primaryColor = validColor(
                    $_POST['primary_color'] ?? '',
                    '#800020'
                );

                $sidebarColor = validColor(
                    $_POST['sidebar_color'] ?? '',
                    '#65001B'
                );

                $fontFamily = $_POST['font_family'] ?? 'Poppins';

                if (!in_array($fontFamily, $allowedFonts, true)) {
                    $fontFamily = 'Poppins';
                }

                $fontSize = (int)($_POST['font_size'] ?? 14);
                $fontSize = max(12, min(20, $fontSize));

                if ($systemName === '' || strlen($systemName) > 100) {
                    throw new Exception("System name must be 1–100 characters.");
                }

                if (strlen($welcomeMessage) > 255) {
                    throw new Exception("Welcome message is too long.");
                }

                $logo = $settings['logo'];

                if (
                    isset($_FILES['logo']) &&
                    $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE
                ) {
                    if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception("Logo upload failed.");
                    }

                    if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                        throw new Exception("Logo must be smaller than 2MB.");
                    }

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($_FILES['logo']['tmp_name']);

                    $allowedTypes = [
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'image/webp' => 'webp'
                    ];

                    if (!isset($allowedTypes[$mime])) {
                        throw new Exception("Only PNG, JPG and WEBP logos are allowed.");
                    }

                    $uploadDir = __DIR__ . '/../uploads/branding/';

                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true)) {
                            throw new Exception("Cannot create upload folder.");
                        }
                    }

                    $filename = bin2hex(random_bytes(16))
                        . '.' . $allowedTypes[$mime];

                    $destination = $uploadDir . $filename;

                    if (!move_uploaded_file(
                        $_FILES['logo']['tmp_name'],
                        $destination
                    )) {
                        throw new Exception("Cannot save uploaded logo.");
                    }

                    $logo = 'uploads/branding/' . $filename;
                }

                $stmt = $pdo->prepare("
                    UPDATE system_settings SET
                        system_name = ?,
                        welcome_message = ?,
                        primary_color = ?,
                        sidebar_color = ?,
                        font_family = ?,
                        font_size = ?,
                        logo = ?
                    WHERE id = 1
                ");

                $stmt->execute([
                    $systemName,
                    $welcomeMessage,
                    $primaryColor,
                    $sidebarColor,
                    $fontFamily,
                    $fontSize,
                    $logo
                ]);

                $success = "System settings updated successfully!";
            }

            $settings = getSettings($pdo, $defaultSettings);

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

<style>
.settings-page {
    padding: 25px;
    font-family: 'Poppins', sans-serif;
}

.settings-heading {
    margin-bottom: 25px;
}

.settings-heading h2 {
    font-size: 25px;
    font-weight: 700;
    color: #29212a;
    margin-bottom: 6px;
}

.settings-heading p {
    color: #777;
    font-size: 14px;
}

.settings-layout {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 24px;
    align-items: start;
}

.settings-card {
    background: white;
    border-radius: 14px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 3px 15px rgba(0,0,0,.06);
    border: 1px solid #f0e5e9;
}

.settings-card h3 {
    font-size: 17px;
    color: #75132f;
    margin-bottom: 8px;
    font-weight: 600;
}

.settings-card .description {
    color: #888;
    font-size: 12px;
    margin-bottom: 22px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #444;
}

.form-control-settings {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #e5dce0;
    border-radius: 8px;
    font-size: 13px;
    background: #fff;
    color: #333;
    outline: none;
}

.form-control-settings:focus {
    border-color: #800020;
    box-shadow: 0 0 0 3px rgba(128,0,32,.08);
}

textarea.form-control-settings {
    min-height: 85px;
    resize: vertical;
}

.color-field {
    display: flex;
    gap: 12px;
    align-items: center;
}

.color-field input[type=color] {
    width: 55px;
    height: 43px;
    border: 1px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    padding: 4px;
}

.color-field input[type=text] {
    flex: 1;
}

.color-presets {
    display: flex;
    gap: 12px;
    margin-top: 15px;
}

.color-preset {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 0 0 1px #ddd;
    cursor: pointer;
}

.color-preset:hover {
    transform: scale(1.12);
}

.logo-preview {
    width: 95px;
    height: 95px;
    border: 2px dashed #e5d0d8;
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 15px;
    background: #fff9fb;
    overflow: hidden;
}

.logo-preview img {
    max-width: 85%;
    max-height: 85%;
    object-fit: contain;
}

.logo-preview span {
    color: #800020;
    font-size: 28px;
    font-weight: 700;
}

.preview-card {
    position: sticky;
    top: 20px;
}

.preview-window {
    display: flex;
    min-height: 350px;
    border: 1px solid #e6dce0;
    border-radius: 10px;
    overflow: hidden;
    background: #faf8f9;
}

.preview-sidebar {
    width: 125px;
    flex-shrink: 0;
    background: #65001B;
    color: white;
    padding: 16px 10px;
    font-size: 10px;
}

.preview-brand {
    text-align: center;
    margin-bottom: 25px;
    font-weight: 700;
}

.preview-brand img {
    width: 35px;
    height: 35px;
    object-fit: contain;
    display: block;
    margin: 0 auto 7px;
}

.preview-nav {
    padding: 9px 6px;
    margin-bottom: 7px;
    border-radius: 6px;
}

.preview-nav.active {
    background: rgba(255,255,255,.2);
}

.preview-content {
    flex: 1;
    min-width: 0;
    padding: 18px;
}

.preview-content h4 {
    font-size: 17px;
    margin-bottom: 5px;
    color: #333;
}

.preview-content p {
    font-size: 10px;
    color: #777;
    margin-bottom: 18px;
}

.preview-stats {
    display: grid;
    grid-template-columns: repeat(2,1fr);
    gap: 10px;
}

.preview-stat {
    background: white;
    border-radius: 8px;
    padding: 12px;
    border: 1px solid #eee;
    border-top: 3px solid #800020;
}

.preview-stat strong {
    display: block;
    font-size: 18px;
}

.preview-stat small {
    font-size: 9px;
    color: #888;
}

.preview-button {
    margin-top: 18px;
    border: 0;
    background: #800020;
    color: white;
    padding: 10px 14px;
    border-radius: 7px;
    font-size: 11px;
}

.settings-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 10px;
}

.btn-save {
    border: none;
    background: #800020;
    color: white;
    padding: 12px 22px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-reset {
    border: 1px solid #ddd;
    background: white;
    color: #555;
    padding: 12px 22px;
    border-radius: 8px;
    cursor: pointer;
}

.settings-alert {
    padding: 13px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
}

.settings-alert.success {
    background: #e8f7ee;
    color: #176c38;
}

.settings-alert.error {
    background: #fdeaea;
    color: #a52222;
}

@media (max-width: 900px) {
    .settings-layout {
        grid-template-columns: 1fr;
    }

    .preview-card {
        position: static;
    }
}

@media (max-width: 500px) {
    .settings-page {
        padding: 12px;
    }

    .settings-card {
        padding: 17px;
    }

    .preview-sidebar {
        width: 100px;
    }
}
</style>

<div class="settings-page">

    <div class="settings-heading">
        <h2>System Settings</h2>
        <p>Customize the appearance and branding of your ERMS dashboard.</p>
    </div>

    <?php if ($success): ?>
        <div class="settings-alert success">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="settings-alert error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="settingsForm">

        <input type="hidden"
               name="csrf_token"
               value="<?= e($_SESSION['settings_csrf']) ?>">

        <input type="hidden" name="action" id="formAction" value="save">

        <div class="settings-layout">

            <div class="settings-left">

                <!-- GENERAL SETTINGS -->
                <div class="settings-card">
                    <h3>General Settings</h3>
                    <p class="description">
                        Manage your system identity and welcome message.
                    </p>

                    <div class="form-group">
                        <label>System Name</label>
                        <input
                            type="text"
                            name="system_name"
                            id="systemName"
                            class="form-control-settings"
                            maxlength="100"
                            value="<?= e($settings['system_name']) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label>Dashboard Welcome Message</label>
                        <textarea
                            name="welcome_message"
                            id="welcomeMessage"
                            class="form-control-settings"
                            maxlength="255"
                        ><?= e($settings['welcome_message']) ?></textarea>
                    </div>
                </div>

                <!-- LOGO SETTINGS -->
                <div class="settings-card">
                    <h3>System Logo</h3>
                    <p class="description">
                        Upload a logo for your medical record system.
                    </p>

                    <div class="logo-preview" id="logoPreview">
                        <?php if (!empty($settings['logo'])): ?>
                            <img
                                src="../<?= e($settings['logo']) ?>"
                                alt="System Logo"
                            >
                        <?php else: ?>
                            <span>ERMS</span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Upload New Logo</label>
                        <input
                            type="file"
                            name="logo"
                            id="logoInput"
                            accept=".png,.jpg,.jpeg,.webp"
                            class="form-control-settings"
                        >
                        <small style="color:#888">
                            PNG, JPG or WEBP. Maximum 2MB.
                        </small>
                    </div>
                </div>

                <!-- THEME SETTINGS -->
                <div class="settings-card">
                    <h3>Theme Customization</h3>
                    <p class="description">
                        Choose the colors used throughout the dashboard.
                    </p>

                    <div class="form-group">
                        <label>Primary Theme Color</label>

                        <div class="color-field">
                            <input
                                type="color"
                                name="primary_color"
                                id="primaryColor"
                                value="<?= e($settings['primary_color']) ?>"
                            >

                            <input
                                type="text"
                                id="primaryHex"
                                class="form-control-settings"
                                value="<?= e($settings['primary_color']) ?>"
                                maxlength="7"
                            >
                        </div>

                        <div class="color-presets">
                            <button type="button" class="color-preset"
                                style="background:#800020"
                                data-color="#800020"
                                aria-label="Maroon theme"></button>

                            <button type="button" class="color-preset"
                                style="background:#2563EB"
                                data-color="#2563EB"
                                aria-label="Blue theme"></button>

                            <button type="button" class="color-preset"
                                style="background:#15803D"
                                data-color="#15803D"
                                aria-label="Green theme"></button>

                            <button type="button" class="color-preset"
                                style="background:#7C3AED"
                                data-color="#7C3AED"
                                aria-label="Purple theme"></button>

                            <button type="button" class="color-preset"
                                style="background:#334155"
                                data-color="#334155"
                                aria-label="Slate theme"></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Sidebar Color</label>

                        <div class="color-field">
                            <input
                                type="color"
                                name="sidebar_color"
                                id="sidebarColor"
                                value="<?= e($settings['sidebar_color']) ?>"
                            >

                            <input
                                type="text"
                                id="sidebarHex"
                                class="form-control-settings"
                                value="<?= e($settings['sidebar_color']) ?>"
                                maxlength="7"
                            >
                        </div>
                    </div>
                </div>

                <!-- FONT SETTINGS -->
                <div class="settings-card">
                    <h3>Typography Settings</h3>
                    <p class="description">
                        Customize the font family and text size.
                    </p>

                    <div class="form-group">
                        <label>Font Family</label>

                        <select
                            name="font_family"
                            id="fontFamily"
                            class="form-control-settings"
                        >
                            <?php foreach ($allowedFonts as $font): ?>
                                <option
                                    value="<?= e($font) ?>"
                                    <?= $settings['font_family'] === $font ? 'selected' : '' ?>
                                >
                                    <?= e($font) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            Font Size:
                            <span id="fontSizeValue">
                                <?= (int)$settings['font_size'] ?>px
                            </span>
                        </label>

                        <input
                            type="range"
                            name="font_size"
                            id="fontSize"
                            min="12"
                            max="20"
                            value="<?= (int)$settings['font_size'] ?>"
                            style="width:100%"
                        >
                    </div>
                </div>

            </div>

            <!-- LIVE PREVIEW -->
            <div class="settings-right">

                <div class="settings-card preview-card">

                    <h3>Live Dashboard Preview</h3>
                    <p class="description">
                        Preview your changes before saving.
                    </p>

                    <div class="preview-window" id="previewWindow">

                        <div class="preview-sidebar" id="previewSidebar">

                            <div class="preview-brand">
                                <div id="previewLogo">
                                    <?php if (!empty($settings['logo'])): ?>
                                        <img
                                            src="../<?= e($settings['logo']) ?>"
                                            alt="Logo"
                                        >
                                    <?php else: ?>
                                        ♡
                                    <?php endif; ?>
                                </div>

                                <span id="previewSystemName">
                                    <?= e($settings['system_name']) ?>
                                </span>
                            </div>

                            <div class="preview-nav active">⌂ Dashboard</div>
                            <div class="preview-nav">♙ Patient Management</div>
                            <div class="preview-nav">▦ Appointments</div>
                            <div class="preview-nav">▤ Medical Records</div>
                            <div class="preview-nav">⚙ Settings</div>

                        </div>

                        <div class="preview-content" id="previewContent">

                            <h4>Dashboard</h4>

                            <p id="previewWelcome">
                                <?= e($settings['welcome_message']) ?>
                            </p>

                            <div class="preview-stats">

                                <div class="preview-stat">
                                    <strong>245</strong>
                                    <small>Total Patients</small>
                                </div>

                                <div class="preview-stat">
                                    <strong>28</strong>
                                    <small>Appointments</small>
                                </div>

                                <div class="preview-stat">
                                    <strong>312</strong>
                                    <small>Medical Records</small>
                                </div>

                                <div class="preview-stat">
                                    <strong>156</strong>
                                    <small>Payments</small>
                                </div>

                            </div>

                            <button type="button"
                                    class="preview-button"
                                    id="previewButton">
                                + Add Patient
                            </button>

                        </div>
                    </div>

                    <p style="font-size:11px;color:#999;margin-top:15px">
                        Preview data is for demonstration only.
                    </p>

                </div>
            </div>
        </div>

        <!-- ACTION BUTTONS -->
        <div class="settings-actions">

            <button type="button"
                    class="btn-reset"
                    onclick="resetSettings()">
                Reset to Default
            </button>

            <button type="submit" class="btn-save">
                Save Settings
            </button>

        </div>

    </form>
</div>

<script>
const primaryColor = document.getElementById('primaryColor');
const sidebarColor = document.getElementById('sidebarColor');

const primaryHex = document.getElementById('primaryHex');
const sidebarHex = document.getElementById('sidebarHex');

const fontFamily = document.getElementById('fontFamily');
const fontSize = document.getElementById('fontSize');

const systemName = document.getElementById('systemName');
const welcomeMessage = document.getElementById('welcomeMessage');

const previewSidebar = document.getElementById('previewSidebar');
const previewContent = document.getElementById('previewContent');

function updatePreview() {

    const mainColor = primaryColor.value;
    const sideColor = sidebarColor.value;

    previewSidebar.style.backgroundColor = sideColor;

    document.querySelectorAll('.preview-stat').forEach(card => {
        card.style.borderTopColor = mainColor;
    });

    document.getElementById('previewButton').style.backgroundColor = mainColor;

    document.getElementById('previewSystemName').textContent =
        systemName.value || 'ERMS';

    document.getElementById('previewWelcome').textContent =
        welcomeMessage.value;

    document.getElementById('fontSizeValue').textContent =
        fontSize.value + 'px';

    previewContent.style.fontFamily =
        "'" + fontFamily.value + "', sans-serif";

    previewContent.style.fontSize = fontSize.value + 'px';

    primaryHex.value = mainColor.toUpperCase();
    sidebarHex.value = sideColor.toUpperCase();
}

function syncHex(textInput, colorInput) {
    const value = textInput.value.trim();

    if (/^#[0-9a-fA-F]{6}$/.test(value)) {
        colorInput.value = value;
        updatePreview();
    }
}

primaryColor.addEventListener('input', updatePreview);
sidebarColor.addEventListener('input', updatePreview);

primaryHex.addEventListener('input', () => {
    syncHex(primaryHex, primaryColor);
});

sidebarHex.addEventListener('input', () => {
    syncHex(sidebarHex, sidebarColor);
});

fontFamily.addEventListener('change', updatePreview);
fontSize.addEventListener('input', updatePreview);

systemName.addEventListener('input', updatePreview);
welcomeMessage.addEventListener('input', updatePreview);

document.querySelectorAll('.color-preset').forEach(button => {
    button.addEventListener('click', () => {
        primaryColor.value = button.dataset.color;
        updatePreview();
    });
});

document.getElementById('logoInput').addEventListener('change', function() {

    const file = this.files[0];

    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        alert('Logo must be smaller than 2MB.');
        this.value = '';
        return;
    }

    const allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];

    if (!allowedTypes.includes(file.type)) {
        alert('Please select a PNG, JPG or WEBP image.');
        this.value = '';
        return;
    }

    const reader = new FileReader();

    reader.onload = function(event) {
        const imageUrl = event.target.result;

        document.getElementById('logoPreview').innerHTML =
            '<img src="' + imageUrl + '" alt="Logo">';

        document.getElementById('previewLogo').innerHTML =
            '<img src="' + imageUrl + '" alt="Logo">';
    };

    reader.readAsDataURL(file);
});

function resetSettings() {
    if (confirm('Are you sure you want to reset all settings to default?')) {
        document.getElementById('formAction').value = 'reset';
        document.getElementById('settingsForm').submit();
    }
}

updatePreview();
</script>

<?php require_once '../includes/footer.php'; ?>