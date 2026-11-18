<?php

function ermsSettings(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT * FROM system_settings WHERE id = 1
    ");

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'system_name' => 'ERMS',
        'welcome_message' => 'Manage and monitor your ERMS system from one place.',
        'primary_color' => '#800020',
        'sidebar_color' => '#65001B',
        'font_family' => 'Poppins',
        'font_size' => 14,
        'logo' => null
    ];
}

function ermsThemeCSS(PDO $pdo): string
{
    $s = ermsSettings($pdo);

    $primary = preg_match('/^#[0-9a-fA-F]{6}$/', $s['primary_color'])
        ? $s['primary_color'] : '#800020';

    $sidebar = preg_match('/^#[0-9a-fA-F]{6}$/', $s['sidebar_color'])
        ? $s['sidebar_color'] : '#65001B';

    $allowedFonts = ['Poppins', 'Arial', 'Roboto', 'Verdana', 'Georgia', 'Tahoma'];

    $font = in_array($s['font_family'], $allowedFonts, true)
        ? $s['font_family'] : 'Poppins';

    $size = max(12, min(20, (int)$s['font_size']));

    return "
    <style>
        :root {
            --primary-color: {$primary};
            --sidebar-color: {$sidebar};
            --system-font: '{$font}', sans-serif;
            --system-font-size: {$size}px;
        }

        body {
            font-family: var(--system-font) !important;
            font-size: var(--system-font-size);
        }

        .sidebar,
        .sidebar-menu {
            background-color: var(--sidebar-color);
        }

        .welcome-card,
        .btn-primary,
        .btn-maroon {
            background-color: var(--primary-color);
        }

        .sidebar .active,
        .sidebar-menu .active {
            background-color: var(--primary-color);
        }
    </style>
    ";
}