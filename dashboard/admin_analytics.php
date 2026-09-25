<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

$pdo = get_db();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$roleCounts = [];
foreach (['student', 'teacher', 'admin'] as $roleName) {
    $roleStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = :role");
    $roleStmt->execute([':role' => $roleName]);
    $roleCounts[$roleName] = (int) $roleStmt->fetchColumn();
}
$roleCounts['other'] = max(0, $totalUsers - array_sum($roleCounts));
$services = [
    'library' => 'Library',
    'clinic' => 'Clinic',
    'ssc_scholarship' => 'SSC / Scholarship',
    'guidance' => 'Guidance',
];
$headCounts = [];
foreach ($services as $serviceKey => $serviceLabel) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND head_service = :service");
    $countStmt->execute([':service' => $serviceKey]);
    $headCounts[$serviceKey] = (int) $countStmt->fetchColumn();
}
$pendingApprovals = (int) $pdo->query("SELECT COUNT(*) FROM pending_student_registrations WHERE status = 'pending_admin'")->fetchColumn();
$newUserCount30 = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$newPendingCount30 = (int) $pdo->query("SELECT COUNT(*) FROM pending_student_registrations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = in_array($currentPage, ['student_promotion.php', 'graduation_events.php', 'alumni_management.php', 'reports_analytics.php'], true);

// ---------------------------------------------------------------------
// Letterhead merge helpers (no Composer libraries needed).
// If the user uploads a .docx / .xlsx file that already has a letterhead
// (logo, header, etc.), the exported data is appended to a copy of that
// file via direct ZIP/XML manipulation. If no file is uploaded, export
// stays as a plain/raw CSV or .doc file.
// ---------------------------------------------------------------------
function docx_escape($text) {
    return htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function docx_build_table($headers, $rows) {
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
        . '<w:top w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:left w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:right w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:insideH w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '<w:insideV w:val="single" w:sz="4" w:space="0" w:color="999999"/>'
        . '</w:tblBorders></w:tblPr>';

    $xml .= '<w:tr>';
    foreach ($headers as $h) {
        $xml .= '<w:tc><w:tcPr><w:shd w:val="clear" w:fill="EFEFEF"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">' . docx_escape($h) . '</w:t></w:r></w:p></w:tc>';
    }
    $xml .= '</w:tr>';

    foreach ($rows as $row) {
        $xml .= '<w:tr>';
        foreach ($row as $cell) {
            $xml .= '<w:tc><w:p><w:r><w:t xml:space="preserve">' . docx_escape($cell) . '</w:t></w:r></w:p></w:tc>';
        }
        $xml .= '</w:tr>';
    }
    $xml .= '</w:tbl>';
    return $xml;
}

// Inserts the report title + table after the existing content of an
// uploaded .docx letterhead template, then streams the merged file.
function export_merge_into_docx($templatePath, $reportTitle, $headers, $rows, $downloadName) {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('The PHP "zip" extension is required on the server to merge into a Word letterhead template.');
    }

    $workPath = tempnam(sys_get_temp_dir(), 'docxmrg_');
    copy($templatePath, $workPath);

    $zip = new ZipArchive();
    if ($zip->open($workPath) !== true) {
        unlink($workPath);
        http_response_code(400);
        exit('Hindi mabuksan ang na-upload na letterhead. Siguraduhing valid .docx file ito.');
    }

    $documentXml = $zip->getFromName('word/document.xml');
    if ($documentXml === false) {
        $zip->close();
        unlink($workPath);
        http_response_code(400);
        exit('Ang na-upload na file ay hindi wastong .docx (walang word/document.xml).');
    }

    $insert = '<w:p/><w:p/>';
    $insert .= '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t xml:space="preserve">' . docx_escape($reportTitle) . '</w:t></w:r></w:p>';
    $insert .= '<w:p><w:r><w:t xml:space="preserve">' . docx_escape('Generated on: ' . date('Y-m-d H:i:s')) . '</w:t></w:r></w:p>';
    $insert .= '<w:p/>';
    $insert .= docx_build_table($headers, $rows);

    $sectPos = strrpos($documentXml, '<w:sectPr');
    if ($sectPos !== false) {
        $documentXml = substr($documentXml, 0, $sectPos) . $insert . substr($documentXml, $sectPos);
    } else {
        $bodyClosePos = strrpos($documentXml, '</w:body>');
        $documentXml = substr($documentXml, 0, $bodyClosePos) . $insert . substr($documentXml, $bodyClosePos);
    }

    $zip->deleteName('word/document.xml');
    $zip->addFromString('word/document.xml', $documentXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($workPath));
    readfile($workPath);
    unlink($workPath);
}

function xlsx_col_letter($n) {
    $letter = '';
    while ($n > 0) {
        $rem = ($n - 1) % 26;
        $letter = chr(65 + $rem) . $letter;
        $n = intdiv($n - 1, 26);
    }
    return $letter;
}

function xlsx_row($rowNum, array $cells) {
    $xml = '<row r="' . $rowNum . '">';
    $col = 1;
    foreach ($cells as $value) {
        $ref = xlsx_col_letter($col) . $rowNum;
        $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        if ($value !== '' && is_numeric($value)) {
            $xml .= '<c r="' . $ref . '"><v>' . $escaped . '</v></c>';
        } else {
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $escaped . '</t></is></c>';
        }
        $col++;
    }
    $xml .= '</row>';
    return $xml;
}

// Appends the report title + table as new rows below the existing content
// of an uploaded .xlsx letterhead template, then streams the merged file.
function export_merge_into_xlsx($templatePath, $reportTitle, $headers, $rows, $downloadName) {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('The PHP "zip" extension is required on the server to merge into an Excel letterhead template.');
    }

    $workPath = tempnam(sys_get_temp_dir(), 'xlsxmrg_');
    copy($templatePath, $workPath);

    $zip = new ZipArchive();
    if ($zip->open($workPath) !== true) {
        unlink($workPath);
        http_response_code(400);
        exit('Hindi mabuksan ang na-upload na letterhead. Siguraduhing valid .xlsx file ito.');
    }

    $sheetName = 'xl/worksheets/sheet1.xml';
    $sheetXml = $zip->getFromName($sheetName);
    if ($sheetXml === false) {
        $zip->close();
        unlink($workPath);
        http_response_code(400);
        exit('Ang na-upload na file ay hindi wastong .xlsx (walang worksheet data).');
    }

    $lastRow = 0;
    if (preg_match_all('/<row[^>]*\br="(\d+)"/', $sheetXml, $m)) {
        $lastRow = max(array_map('intval', $m[1]));
    }
    $rowNum = $lastRow + 2;

    $newRows = '';
    $newRows .= xlsx_row($rowNum++, [$reportTitle]);
    $newRows .= xlsx_row($rowNum++, ['Generated on: ' . date('Y-m-d H:i:s')]);
    $rowNum++;
    $newRows .= xlsx_row($rowNum++, $headers);
    foreach ($rows as $row) {
        $newRows .= xlsx_row($rowNum++, $row);
    }

    $sheetDataClose = strpos($sheetXml, '</sheetData>');
    if ($sheetDataClose === false) {
        $zip->close();
        unlink($workPath);
        http_response_code(400);
        exit('Unexpected na format ng worksheet sa na-upload na letterhead.');
    }
    $sheetXml = substr($sheetXml, 0, $sheetDataClose) . $newRows . substr($sheetXml, $sheetDataClose);
    $sheetXml = preg_replace('/<dimension[^>]*\/>/', '', $sheetXml, 1);

    $zip->deleteName($sheetName);
    $zip->addFromString($sheetName, $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($workPath));
    readfile($workPath);
    unlink($workPath);
}

// ---------------------------------------------------------------------
// Export Reports (new): Overview summary, Role Distribution, Head Coverage.
// ---------------------------------------------------------------------
if (isset($_POST['export_admin_report'])) {
    $reportType = $_POST['report_type'] ?? 'overview';
    $exportFormat = $_POST['export_format'] ?? 'excel';
    $headers = [];
    $rows = [];

    if ($reportType === 'roles') {
        $headers = ['Role', 'Count', 'Percentage'];
        foreach ($roleCounts as $role => $count) {
            $pct = $totalUsers ? round($count / $totalUsers * 100, 1) : 0;
            $rows[] = [ucfirst($role), $count, $pct . '%'];
        }
    } elseif ($reportType === 'heads') {
        $headers = ['Service', 'Assigned Heads'];
        foreach ($headCounts as $serviceKey => $count) {
            $rows[] = [$services[$serviceKey], $count];
        }
    } else {
        $reportType = 'overview';
        $headers = ['Metric', 'Value'];
        $rows = [
            ['Total Users', $totalUsers],
            ['Pending Approvals', $pendingApprovals],
            ['New Users (30d)', $newUserCount30],
            ['New Pending Applications (30d)', $newPendingCount30],
        ];
    }

    $reportTitle = 'Admin Analytics — ' . ucfirst($reportType) . ' Report';
    $hasLetterhead = isset($_FILES['letterhead_template']) && $_FILES['letterhead_template']['error'] === UPLOAD_ERR_OK;

    if ($hasLetterhead) {
        $tmpPath = $_FILES['letterhead_template']['tmp_name'];
        $origName = $_FILES['letterhead_template']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($exportFormat === 'docs') {
            if ($ext !== 'docx') {
                http_response_code(400);
                exit('Ang letterhead template para sa Word export ay dapat .docx file.');
            }
            export_merge_into_docx($tmpPath, $reportTitle, $headers, $rows, "admin_{$reportType}_report.docx");
        } else {
            if ($ext !== 'xlsx') {
                http_response_code(400);
                exit('Ang letterhead template para sa Excel export ay dapat .xlsx file.');
            }
            export_merge_into_xlsx($tmpPath, $reportTitle, $headers, $rows, "admin_{$reportType}_report.xlsx");
        }
        exit;
    }

    // No letterhead uploaded: export raw data only.
    if ($exportFormat === 'docs') {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        $filename = "admin_{$reportType}_report.doc";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "<html><head><title>{$reportTitle}</title></head><body>";
        echo "<h1>{$reportTitle}</h1>";
        echo "<p>Generated on: " . date('Y-m-d H:i:s') . "</p><br>";
        echo "<table border='1'><tr>" . implode('', array_map(fn($h) => '<th>' . htmlspecialchars($h) . '</th>', $headers)) . '</tr>';
        foreach ($rows as $row) {
            echo '<tr>' . implode('', array_map(fn($cell) => '<td>' . htmlspecialchars((string) $cell) . '</td>', $row)) . '</tr>';
        }
        echo '</table>';
        echo "</body></html>";
    } else {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        $filename = "admin_{$reportType}_report.csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\r\n";
        foreach ($rows as $row) {
            echo implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', (string) $cell) . '"', $row)) . "\r\n";
        }
    }
    exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics | Admin | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('../assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
        /* Smooth scrolling for anchor links */
        html {
            scroll-behavior: smooth;
        }

        #swipe-refresh-spinner {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, 0.92);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        #swipe-refresh-spinner .spinner-ring {
            width: 60px;
            height: 60px;
            border: 4px solid #e2e8f0;
            border-top-color: #800000;
            border-radius: 50%;
            animation: spin-refresh 1s linear infinite;
            margin: 0 auto 16px;
        }

        #swipe-refresh-spinner .spinner-label {
            color: #666;
            font-size: 14px;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            text-align: center;
        }

        .case-page-header {
            position: relative;
            min-height: 245px;
            display: flex;
            align-items: center;
            overflow: hidden;
            margin: 0 0 24px;
            padding: 42px 48px 30px;
            background: #f8f1e7;
            border: 0;
            border-radius: 0 0 24px 24px;
        }

        .case-page-header::before {
            content: '';
            position: absolute;
            z-index: 0;
            inset: 0 auto 0 0;
            width: 3px;
            background: #861b17;
        }

        .case-page-header > div:first-child {
            position: relative;
            z-index: 2;
            width: 65%;
        }

        .case-page-header .eyebrow {
            margin: 0;
            color: #861b17 !important;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 3px;
            line-height: 1;
            text-transform: uppercase;
        }

        .case-page-header h1 {
            margin: 14px 0 12px;
            color: #111 !important;
            font-family: Georgia, serif;
            font-size: clamp(36px, 4vw, 62px);
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.05;
        }

        .case-page-header .dashboard-subtitle {
            max-width: 760px;
            margin: 0;
            color: #34302d !important;
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }

        .case-header__art {
            position: absolute;
            z-index: 1;
            top: 0;
            right: 0;
            width: 43%;
            height: 100%;
            color: #d7a58d;
            opacity: .78;
        }

        .case-header__art::before {
            content: '';
            position: absolute;
            right: 13%;
            bottom: 2%;
            width: 82%;
            height: 30%;
            background: radial-gradient(ellipse at center, rgba(235, 205, 167, .48) 0 42%, transparent 43%);
            border-radius: 50%;
        }

        .case-header__art::after {
            content: '';
            position: absolute;
            top: 23%;
            left: 4%;
            width: 76%;
            height: 42%;
            border: 1px solid rgba(215, 165, 141, .45);
            border-left-color: transparent;
            border-radius: 50%;
            transform: rotate(-13deg);
        }

        .case-header__art i {
            position: absolute;
            z-index: 2;
            font-family: 'Font Awesome 6 Free';
            font-size: 28px;
            font-style: normal;
            font-weight: 900;
        }

        .case-header__art .art-chat {
            top: 57%;
            left: 2%;
            padding: 12px 14px;
            border: 2px solid rgba(215, 165, 141, .7);
            border-radius: 9px;
            font-size: 15px;
        }

        .case-header__art .art-chat::after {
            content: '';
            position: absolute;
            right: 12px;
            bottom: -9px;
            width: 14px;
            height: 14px;
            border-right: 2px solid rgba(215, 165, 141, .7);
            border-bottom: 2px solid rgba(215, 165, 141, .7);
            background: #f8f1e7;
            transform: rotate(45deg);
        }

        .case-header__art .art-file {
            top: 17%;
            left: 39%;
            padding: 16px 19px;
            border: 2px solid rgba(215, 165, 141, .58);
            border-radius: 9px;
            box-shadow: 20px 10px 0 -1px #f8f1e7, 20px 10px 0 1px rgba(215, 165, 141, .48), 36px 20px 0 -1px #f8f1e7, 36px 20px 0 1px rgba(215, 165, 141, .42);
            font-size: 35px;
        }

        .case-header__art .art-card {
            top: 35%;
            left: 47%;
            padding: 13px 16px;
            border: 2px solid rgba(215, 165, 141, .65);
            border-radius: 8px;
            background: rgba(248, 241, 231, .7);
            font-size: 28px;
        }

        .case-header__art .art-check {
            top: 22%;
            right: 7%;
            padding: 12px;
            border: 2px solid #e3b968;
            border-radius: 50%;
            color: #e3b968;
            font-size: 21px;
        }

        .case-header__art .art-pin {
            bottom: 10%;
            left: 42%;
            width: 31px;
            height: 31px;
            color: transparent;
            background: #cf9589;
            border-radius: 50% 50% 50% 0;
            font-size: 0;
            transform: rotate(-45deg);
        }

        .case-header__art .art-pin::after {
            content: '';
            position: absolute;
            top: 9px;
            left: 9px;
            width: 13px;
            height: 13px;
            background: #f8f1e7;
            border-radius: 50%;
        }

        .case-header__art .art-dots {
            right: 25%;
            bottom: 12%;
            color: #d7a58d;
            font-size: 28px;
        }

        @keyframes spin-refresh {
            to {
                transform: rotate(360deg);
            }
        }

        .service-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0 24px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }

        .service-tab {
            appearance: none;
            border: 1px solid #d9d9d9;
            background: #f8fafc;
            color: var(--deep-maroon, #800000);
            font-weight: 600;
            padding: 12px 18px;
            border-radius: 10px 10px 0 0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .service-tab.active {
            background: linear-gradient(135deg, var(--deep-maroon, #800000) 0%, #a63b3b 100%);
            color: #fff;
            border-color: var(--deep-maroon, #800000);
            box-shadow: 0 8px 18px rgba(128, 0, 0, 0.14);
        }

        .service-tab-panel {
            display: none;
            animation: fadeInTab 0.2s ease;
        }

        .service-tab-panel.active {
            display: block;
        }

        @keyframes fadeInTab {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                overscroll-behavior-y: none;
            }

            .service-tabs {
                overflow-x: auto;
                white-space: nowrap;
                flex-wrap: nowrap;
            }

            .service-tab {
                flex: 0 0 auto;
            }
        }
    </style>
</head>
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none;">
        <div style="text-align: center;">
            <div class="spinner-ring"></div>
            <p class="spinner-label">Refreshing...</p>
        </div>
    </div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="admin_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-user-shield"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="admin_assignments.php" data-tooltip="Head Assignments">
                    <span class="nav-icon"><i class="fa-solid fa-user-tie"></i></span>
                    <span class="nav-text">Head Assignments</span>
                </a>
                <a href="admin_users.php" data-tooltip="Users">
                    <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="nav-text">Users</span>
                </a>
                <a href="admin_analytics.php" class="active" data-tooltip="Analytics">
                    <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                    <span class="nav-text">Analytics</span>
                </a>
                <a href="admin_calendar_manager.php" data-tooltip="School Calendar">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                    <span class="nav-text">School Calendar</span>
                </a>
                <a href="admin_settings.php" data-tooltip="Settings">
                    <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
                <a href="feedback_and_ratings.php" data-tooltip="Feedback">
                    <span class="nav-icon"><i class="fa-solid fa-star"></i></span>
                    <span class="nav-text">Feedback</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $ssaaOpen ? 'true' : 'false' ?>" data-tooltip="SSAA Management">
                        <span class="nav-icon"><i class="fa-solid fa-building-columns"></i></span>
                        <span class="nav-text">SSAA Management</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $ssaaOpen ? ' open' : '' ?>" aria-hidden="<?= $ssaaOpen ? 'false' : 'true' ?>">
                        <a href="student_promotion.php" data-tooltip="Student Promotion">
                            <span class="nav-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            <span class="nav-text">Student Promotion</span>
                        </a>
                        <a href="alumni_management.php" data-tooltip="Alumni Management">
                            <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                            <span class="nav-text">Alumni Management</span>
                        </a>
                        <a href="reports_analytics.php" data-tooltip="Reports & Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Reports & Analytics</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open mobile menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Admin Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Admin</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <!-- Profile button removed per request -->
                    <!-- support icon removed per UI update -->

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest system alerts</span>
                        </div>
                        <div class="menu-empty">No new notifications.</div>
                        <a class="menu-link menu-footer-link" href="admin_analytics.php">View system analytics</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="admin_assignments.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">Manage admin assignments</span>
                        </div>
                        <a href="admin_users.php" class="menu-action">View users</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="admin_settings.php">System settings</a>
                            <span class="menu-subtext">Configure system policies and access.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="../about.php">Help center</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="page-overlay" id="pageOverlay"></div>
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <p class="eyebrow">Admin Analytics</p>
                            <h1>Service Health & User Metrics</h1>
                            <p class="dashboard-subtitle">Monitor user registrations, pending approvals, and service head coverage from one central admin screen.</p>
                        </div>
                        <div class="case-header__art" aria-hidden="true">
                            <i class="fa-solid fa-file-lines art-file"></i>
                            <i class="fa-solid fa-user-tie art-card"></i>
                            <i class="fa-solid fa-clipboard-check art-chat"></i>
                            <i class="fa-solid fa-check art-check"></i>
                            <i class="fa-solid fa-location-dot art-pin"></i>
                            <i class="fa-solid fa-leaf art-dots"></i>
                        </div>
                    </section>

                    <section class="analytics-grid">
                        <div class="analytics-card">
                            <h3>Role Distribution</h3>
                            <div class="analytics-key">
                                <span><strong><?= htmlspecialchars($roleCounts['student']) ?></strong> Students</span>
                                <span><strong><?= htmlspecialchars($roleCounts['teacher']) ?></strong> Teachers</span>
                                <span><strong><?= htmlspecialchars($roleCounts['admin']) ?></strong> Admins</span>
                            </div>
                            <div class="role-bars">
                                <?php foreach ($roleCounts as $role => $count): ?>
                                    <?php $width = $totalUsers ? round($count / $totalUsers * 100) : 0; ?>
                                    <div class="role-row">
                                        <span class="role-label"><?= htmlspecialchars(ucfirst($role)) ?></span>
                                        <div class="role-track">
                                            <div class="role-fill role-fill-<?= htmlspecialchars($role) ?>" style="width: <?= $width ?>%;"></div>
                                        </div>
                                        <span class="role-value"><?= htmlspecialchars($count) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <h3>Head Coverage</h3>
                            <div class="head-coverage-note">How many assigned heads exist for each service.</div>
                            <?php foreach ($headCounts as $serviceKey => $count): ?>
                                <div class="coverage-row">
                                    <strong><?= htmlspecialchars($services[$serviceKey]) ?></strong>
                                    <span><?= htmlspecialchars($count) ?> assigned</span>
                                </div>
                                <div class="coverage-track">
                                    <div class="coverage-fill" style="width: <?= min(100, $count * 24) ?>%;"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="analytics-grid" style="grid-template-columns: 1fr;">
                        <div class="analytics-card">
                            <h3><i class="fa-solid fa-file-export"></i> Export Reports</h3>

                            <div class="service-tabs" role="tablist" aria-label="Service reports tabs">
                                <button type="button" class="service-tab active" data-target="all-tab" role="tab" aria-selected="true">All</button>
                                <button type="button" class="service-tab" data-target="admin-tab" role="tab" aria-selected="false">Admin Analytics</button>
                                <button type="button" class="service-tab" data-target="guidance-tab" role="tab" aria-selected="false">Guidance</button>
                                <button type="button" class="service-tab" data-target="library-tab" role="tab" aria-selected="false">Library</button>
                                <button type="button" class="service-tab" data-target="nurse-tab" role="tab" aria-selected="false">Nurse</button>
                                <button type="button" class="service-tab" data-target="ssc-tab" role="tab" aria-selected="false">SSC</button>
                                <button type="button" class="service-tab" data-target="scholarship-tab" role="tab" aria-selected="false">Scholarship</button>
                                <button type="button" class="service-tab" data-target="alumni-tab" role="tab" aria-selected="false">Alumni Analytics</button>
                            </div>

                            <div id="all-tab" class="service-tab-panel active" role="tabpanel">
                                <div style="display: grid; gap: 24px;">
                                    <div style="padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                        <h4 style="margin: 0 0 12px; color: var(--deep-maroon);">Admin Analytics Export</h4>
                                        <p style="margin: 0; color: #475569; line-height: 1.7;">Overview, role distribution, and head coverage summaries.</p>
                                    </div>
                                    <div style="padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;">
                                        <h4 style="margin: 0 0 12px; color: var(--deep-maroon);">Guidance Export</h4>
                                        <p style="margin: 0; color: #475569; line-height: 1.7;">Case reports, concerns, attendance, and resolutions generated from the guidance service export panel.</p>
                                    </div>
                                    <div style="padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;">
                                        <h4 style="margin: 0 0 12px; color: var(--deep-maroon);">Library Export</h4>
                                        <p style="margin: 0; color: #475569; line-height: 1.7;">Visitor logs, asset inventory, circulation history, fines, and e-resources utilization.</p>
                                    </div>
                                    <div style="padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;">
                                        <h4 style="margin: 0 0 12px; color: var(--deep-maroon);">Nurse Export</h4>
                                        <p style="margin: 0; color: #475569; line-height: 1.7;">Condition visitors and medicine inventory records with flexible date filtering.</p>
                                    </div>
                                    <div style="padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;">
                                        <h4 style="margin: 0 0 12px; color: var(--deep-maroon);">SSC Export</h4>
                                        <p style="margin: 0; color: #475569; line-height: 1.7;">Events, candidates, initiatives, officers, and statistics from the student council reporting panel.</p>
                                    </div>
                                    <div style="padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;">
                                        <h4 style="margin: 0 0 12px; color: var(--deep-maroon);">Scholarship Export</h4>
                                        <p style="margin: 0; color: #475569; line-height: 1.7;">Announcements, applications, validations, and general scholarship statistics.</p>
                                    </div>
                                    <div style="padding: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;">
                                        <h4 style="margin: 0 0 12px; color: var(--deep-maroon);">Alumni Analytics Export</h4>
                                        <p style="margin: 0; color: #475569; line-height: 1.7;">Master directory, employment status, and graduate tracer survey summary reports with year, course, and status filters.</p>
                                    </div>
                                </div>
                            </div>

                            <div id="admin-tab" class="service-tab-panel" role="tabpanel">
                                <div style="margin: 12px 0 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                    <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                        <li><strong>Overview:</strong> Total users, pending approvals, and new user/application counts (30 days)</li>
                                        <li><strong>Role Distribution:</strong> User counts and percentage per role</li>
                                        <li><strong>Head Coverage:</strong> Assigned heads per service</li>
                                    </ul>
                                    <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Export data in multiple formats for reporting and analysis.</p>
                                </div>
                                <form method="post" action="admin_analytics.php" enctype="multipart/form-data" style="width: 100%;">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                        <div>
                                            <label for="admin_report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                            <select name="report_type" id="admin_report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="overview">Overview Summary</option>
                                                <option value="roles">Role Distribution</option>
                                                <option value="heads">Head Coverage</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="admin_export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                            <select name="export_format" id="admin_export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="excel">Excel (CSV)</option>
                                                <option value="docs">Word Document</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 20px;">
                                        <label for="admin_letterhead_template" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Letterhead Template (optional)</label>
                                        <input type="file" name="letterhead_template" id="admin_letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8f9fa;">
                                        <small style="display: block; margin-top: 6px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                            If you do not upload a letterhead template, only the raw data (no letterhead) will be exported.
                                            If you upload a template, it must be a <strong>.docx</strong> file for Word exports or a <strong>.xlsx</strong> file for Excel exports —
                                            the file should already contain the letterhead/header design. The exported data will be appended below the existing content of that file.
                                            <br>Legacy <strong>.doc</strong> / <strong>.xls</strong> formats are not supported — please use <strong>.docx</strong> / <strong>.xlsx</strong>.
                                        </small>
                                    </div>
                                    <div style="display: flex; justify-content: flex-start;">
                                        <button type="submit" name="export_admin_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                                            <i class="fa-solid fa-download"></i>
                                            Export Report
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div id="guidance-tab" class="service-tab-panel" role="tabpanel">
                                <div style="margin: 12px 0 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                    <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                        <li><strong>Cases:</strong> Detailed case information with case ID, student name, course year, and status</li>
                                        <li><strong>Concerns:</strong> Cases grouped by concern type with case ID, student name, and course year</li>
                                        <li><strong>Attendance:</strong> Session attendance statistics</li>
                                        <li><strong>Resolutions:</strong> Resolution outcomes for closed cases</li>
                                    </ul>
                                    <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Export data in multiple formats for reporting and analysis.</p>
                                </div>
                                <form method="post" action="reports.php" enctype="multipart/form-data" style="width: 100%;" id="guidanceExportForm">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                        <div>
                                            <label for="guidance_report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                            <select name="report_type" id="guidance_report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="cases">Detailed Case Information</option>
                                                <option value="concerns">Cases by Concern Type</option>
                                                <option value="attendance">Session Attendance</option>
                                                <option value="resolutions">Resolution Outcomes</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="guidance_export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                            <select name="export_format" id="guidance_export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="excel">Excel (CSV)</option>
                                                <option value="docs">Word Document</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 20px;">
                                        <label for="guidance_letterhead_template" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Letterhead Template (optional)</label>
                                        <input type="file" name="letterhead_template" id="guidance_letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8f9fa;">
                                        <small style="display: block; margin-top: 6px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                            If you do not upload a letterhead template, only the raw data (no letterhead) will be exported, as before.
                                            If you upload a template, it must be a <strong>.docx</strong> file for Word exports or a <strong>.xlsx</strong> file for Excel exports —
                                            the file should already contain the letterhead/header design. The exported data will be appended below the existing content of that file.
                                            <br>Legacy <strong>.doc</strong> / <strong>.xls</strong> formats are not supported — please use <strong>.docx</strong> / <strong>.xlsx</strong>.
                                        </small>
                                    </div>
                                    <div style="display: flex; justify-content: flex-start;">
                                        <button type="submit" name="export_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                                            <i class="fa-solid fa-download"></i>
                                            Export Report
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div id="library-tab" class="service-tab-panel" role="tabpanel">
                                <div style="margin: 12px 0 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                    <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                        <li><strong>Visitor Log Analytics:</strong> Library access records and visit activity</li>
                                        <li><strong>Book Inventory & Asset Report:</strong> Collection status and catalog overview</li>
                                        <li><strong>Borrow & Return Circulation History:</strong> Borrowing patterns and circulation activity</li>
                                        <li><strong>Fines & Penalties Collection Report:</strong> Student fine records with payment status</li>
                                        <li><strong>E-Resources Utilization Report:</strong> Resource downloads and access usage</li>
                                    </ul>
                                    <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Bachelor and ladderized course pairs are grouped together when exporting the fines & penalties report.</p>
                                </div>
                                <form method="post" action="../library_reports.php" enctype="multipart/form-data" style="width: 100%;" id="exportReportForm">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                        <div>
                                            <label for="report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                            <select name="report_type" id="report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="visitor_log_analytics">Visitor Log Analytics</option>
                                                <option value="book_inventory_asset_report">Book Inventory & Asset Report</option>
                                                <option value="borrow_return_circulation_history">Borrow & Return Circulation History</option>
                                                <option value="fines_penalties_collection_report">Fines & Penalties Collection Report</option>
                                                <option value="e_resources_utilization_report">E-Resources Utilization Report</option>
                                            </select>
                                        </div>
                                        <div class="date-range-group" style="display: none;">
                                            <label for="date_range" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Date Range</label>
                                            <select name="date_range" id="date_range" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="today">Today</option>
                                                <option value="week">This Week</option>
                                                <option value="month">This Month</option>
                                                <option value="custom_date">Custom Date</option>
                                                <option value="all">All Records</option>
                                            </select>
                                            <div id="custom-date-fields" style="display: none; margin-top: 12px; display: grid; grid-template-columns: repeat(2, minmax(150px, 1fr)); gap: 12px;">
                                                <div>
                                                    <label for="custom_start_date" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--deep-maroon);">Start Date</label>
                                                    <input type="date" name="custom_start_date" id="custom_start_date" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                </div>
                                                <div>
                                                    <label for="custom_end_date" style="display: block; margin-bottom: 6px; font-weight: 600; color: var(--deep-maroon);">End Date</label>
                                                    <input type="date" name="custom_end_date" id="custom_end_date" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="course-filter-group" style="display: none;">
                                            <label for="course_filter" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Course</label>
                                            <select name="course_filter" id="course_filter" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="all">All</option>
                                                <option value="bsa">Bachelor of Science in Accountancy</option>
                                                <option value="bba">Bachelor of Science in Business Administration</option>
                                                <option value="beed">Bachelor in Elementary Education</option>
                                                <option value="bscs">Bachelor of Science in Computer Science</option>
                                                <option value="bscrim">Bachelor of Science in Criminology</option>
                                                <option value="bshm">Bachelor of Science in Hospitality Management</option>
                                                <option value="bstm">Bachelor of Science in Tourism Management</option>
                                                <option value="act">Associate in Computer Technology</option>
                                                <option value="abk">Associate in Business Knowledge</option>
                                                <option value="ahm">Associate in Hotel Management</option>
                                                <option value="atm">Associate in Tourism Management</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                            <select name="export_format" id="export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="excel">Excel (CSV)</option>
                                                <option value="docs">Word Document</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 20px;">
                                        <label for="letterhead_template" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Letterhead Template (optional)</label>
                                        <input type="file" name="letterhead_template" id="letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8fafc;">
                                        <small id="letterheadHint" style="display: block; margin-top: 6px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                            If you do not upload a letterhead template, only the raw data (no letterhead) will be exported, as before.
                                            If you upload a template, it must be a <strong>.docx</strong> file for Word exports or a <strong>.xlsx</strong> file for Excel exports — the file should already contain the letterhead/header design.
                                            The exported data will be appended below the existing content of that file.
                                            <br>Legacy <strong>.doc</strong> / <strong>.xls</strong> formats are not supported — please use modern <strong>.docx</strong> / <strong>.xlsx</strong> files. Upload the template each time you export; it will not be saved permanently.
                                        </small>
                                    </div>
                                    <div style="display: flex; justify-content: flex-start;">
                                        <button type="submit" name="export_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                                            <i class="fa-solid fa-download"></i>
                                            Export Report
                                        </button>
                                    </div>
                                </form>
                                <script>
                                    (function () {
                                        var form = document.getElementById('exportReportForm');
                                        var reportTypeSelect = document.getElementById('report_type');
                                        var courseFilterGroup = document.querySelector('.course-filter-group');
                                        var dateRangeGroup = document.querySelector('.date-range-group');
                                        var dateRangeSelect = document.getElementById('date_range');
                                        var customDateFields = document.getElementById('custom-date-fields');
                                        var formatSelect = document.getElementById('export_format');
                                        var fileInput = document.getElementById('letterhead_template');
                                        var allowedFilters = ['visitor_log_analytics', 'borrow_return_circulation_history', 'fines_penalties_collection_report', 'e_resources_utilization_report'];

                                        function toggleCourseFilter() {
                                            if (!reportTypeSelect || !courseFilterGroup) return;
                                            var showCourseFilter = allowedFilters.includes(reportTypeSelect.value);
                                            courseFilterGroup.style.display = showCourseFilter ? 'block' : 'none';
                                        }

                                        function toggleDateRange() {
                                            if (!reportTypeSelect || !dateRangeGroup) return;
                                            var showDateRange = allowedFilters.includes(reportTypeSelect.value);
                                            dateRangeGroup.style.display = showDateRange ? 'block' : 'none';
                                            if (dateRangeSelect && customDateFields) {
                                                if (!showDateRange) {
                                                    dateRangeSelect.value = 'all';
                                                    customDateFields.style.display = 'none';
                                                } else {
                                                    customDateFields.style.display = dateRangeSelect.value === 'custom_date' ? 'grid' : 'none';
                                                }
                                            }
                                        }

                                        if (reportTypeSelect) {
                                            reportTypeSelect.addEventListener('change', function () {
                                                toggleCourseFilter();
                                                toggleDateRange();
                                            });
                                            toggleCourseFilter();
                                            toggleDateRange();
                                        }

                                        if (dateRangeSelect) {
                                            dateRangeSelect.addEventListener('change', function () {
                                                if (customDateFields) {
                                                    customDateFields.style.display = dateRangeSelect.value === 'custom_date' ? 'grid' : 'none';
                                                }
                                            });
                                        }

                                        if (!form || !formatSelect || !fileInput) return;

                                        function requiredExt() {
                                            return formatSelect.value === 'docs' ? 'docx' : 'xlsx';
                                        }

                                        function fileExt(filename) {
                                            var parts = filename.split('.');
                                            return parts.length > 1 ? parts.pop().toLowerCase() : '';
                                        }

                                        fileInput.addEventListener('change', function () {
                                            if (!fileInput.files || !fileInput.files.length) return;
                                            var ext = fileExt(fileInput.files[0].name);
                                            var expected = requiredExt();
                                            if (ext !== expected) {
                                                alert('Ang napiling letterhead file ay ".' + ext + '" pero kailangan ay ".' + expected + '" para sa "' + (formatSelect.value === 'docs' ? 'Word Document' : 'Excel (CSV)') + '" na export format. Piliin ulit ang tamang file, o palitan ang Export Format.');
                                            }
                                        });

                                        form.addEventListener('submit', function (e) {
                                            if (!fileInput.files || !fileInput.files.length) return;
                                            var ext = fileExt(fileInput.files[0].name);
                                            var expected = requiredExt();
                                            if (ext !== expected) {
                                                e.preventDefault();
                                                alert('Hindi ma-e-export: ang letterhead file ay dapat ".' + expected + '" para sa napiling Export Format.');
                                            }
                                        });
                                    })();
                                </script>
                            </div>

                            <div id="nurse-tab" class="service-tab-panel" role="tabpanel">
                                <div style="margin: 12px 0 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                    <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                        <li><strong>Condition Visitors:</strong> Export Condition visitor records with Name, Type, Course, Reason, Date, and Time</li>
                                        <li><strong>Inventory:</strong> Export medicine stock levels and expiry dates</li>
                                    </ul>
                                </div>
                                <form method="post" action="clinic_analytics.php" enctype="multipart/form-data" style="width: 100%;" id="clinicExportForm">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                        <div>
                                            <label for="clinic_report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                            <select name="report_type" id="clinic_report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="visits">Condition Visitors</option>
                                                <option value="inventory">Medicine Inventory</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="clinic_export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                            <select name="export_format" id="clinic_export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="excel">Excel (CSV)</option>
                                                <option value="docs">Word Document</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="conditionVisitorFilters" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; padding: 18px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff;">
                                        <div style="display:flex; flex-direction: column; gap: 8px;">
                                            <label for="clinic_date_range" style="font-weight: 600; color: var(--deep-maroon);">Date Range</label>
                                            <select name="date_range" id="clinic_date_range" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="today">Today</option>
                                                <option value="week">This Week</option>
                                                <option value="month">This Month</option>
                                                <option value="custom_date">Custom Date</option>
                                                <option value="all">All Records</option>
                                            </select>
                                            <div id="clinic-custom-date-fields" style="display: none; margin-top: 12px; grid-template-columns: repeat(2, minmax(140px, 1fr)); gap: 12px;">
                                                <div>
                                                    <label for="clinic_custom_start_date" style="font-weight: 600; color: var(--deep-maroon);">Start Date</label>
                                                    <input type="date" name="custom_start_date" id="clinic_custom_start_date" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                </div>
                                                <div>
                                                    <label for="clinic_custom_end_date" style="font-weight: 600; color: var(--deep-maroon);">End Date</label>
                                                    <input type="date" name="custom_end_date" id="clinic_custom_end_date" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div style="display:flex; flex-direction: column; gap: 8px;">
                                            <label for="clinic_cond_reason" style="font-weight: 600; color: var(--deep-maroon);">Reason</label>
                                            <select name="cond_reason" id="clinic_cond_reason" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="">-- Select reason --</option>
                                                <option value="Common Cold">Common Cold</option>
                                                <option value="Headache">Headache</option>
                                                <option value="Fever">Fever</option>
                                                <option value="Stomach Pain">Stomach Pain</option>
                                                <option value="Allergies">Allergies</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div style="display:flex; flex-direction: column; gap: 8px;">
                                            <label for="clinic_cond_course" style="font-weight: 600; color: var(--deep-maroon);">Course</label>
                                            <select name="cond_course" id="clinic_cond_course" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="">All Courses</option>
                                                <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                                                <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                                <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                                                <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                                                <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                                                <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                                <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                            </select>
                                        </div>
                                        <div style="display:flex; flex-direction: column; gap: 8px;">
                                            <label for="clinic_cond_type" style="font-weight: 600; color: var(--deep-maroon);">Type</label>
                                            <select name="cond_type" id="clinic_cond_type" style="width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="">All Types</option>
                                                <option value="student">Student</option>
                                                <option value="teacher">Teacher</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 20px;">
                                        <label for="clinic_letterhead_template" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Letterhead Template (optional)</label>
                                        <input type="file" name="letterhead_template" id="clinic_letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8f9fa;">
                                        <small style="display: block; margin-top: 6px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                            If you do not upload a letterhead template, only the raw data (no letterhead) will be exported, as before.
                                            If you do upload a template, it must be a <strong>.docx</strong> file for Word exports or a <strong>.xlsx</strong> file for Excel exports — the file should already contain the letterhead/header design.
                                            The exported data will be appended below the existing content of that file.
                                            <br>Legacy <strong>.doc</strong> / <strong>.xls</strong> formats are not supported — please use modern <strong>.docx</strong> / <strong>.xlsx</strong> files.
                                        </small>
                                    </div>
                                    <div style="display: flex; justify-content: flex-start;">
                                        <button type="submit" name="export_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                                            <i class="fa-solid fa-download"></i>
                                            Export Report
                                        </button>
                                    </div>
                                </form>
                                <script>
                                    (function () {
                                        var reportType = document.getElementById('clinic_report_type');
                                        var filterPanel = document.getElementById('conditionVisitorFilters');
                                        var dateRange = document.getElementById('clinic_date_range');
                                        var customDateFields = document.getElementById('clinic-custom-date-fields');

                                        function updateClinicFilters() {
                                            var isVisits = reportType && reportType.value === 'visits';
                                            if (filterPanel) {
                                                filterPanel.style.display = isVisits ? 'grid' : 'none';
                                            }
                                            if (customDateFields) {
                                                customDateFields.style.display = isVisits && dateRange && dateRange.value === 'custom_date' ? 'grid' : 'none';
                                            }
                                        }

                                        if (reportType) reportType.addEventListener('change', updateClinicFilters);
                                        if (dateRange) dateRange.addEventListener('change', updateClinicFilters);
                                        updateClinicFilters();
                                    })();
                                </script>
                            </div>

                            <div id="ssc-tab" class="service-tab-panel" role="tabpanel">
                                <div style="margin: 12px 0 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                    <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                        <li><strong>Events:</strong> All SSC events with dates, locations, status, and organizers</li>
                                        <li><strong>Candidates:</strong> List of all candidates, positions, and status information</li>
                                        <li><strong>Initiatives:</strong> Detailed records of SSC initiatives with timeline and participation</li>
                                        <li><strong>Officers:</strong> SSC appointed officers and advisers for selected academic year</li>
                                        <li><strong>Statistics:</strong> General SSC statistics and summary metrics</li>
                                    </ul>
                                    <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Export data in multiple formats for reporting and analysis.</p>
                                </div>
                                <form id="sscExportForm" method="post" action="ssc_reports.php" enctype="multipart/form-data" style="width: 100%;">
                                    <div class="report-export-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                        <div>
                                            <label for="ssc_report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                            <select name="report_type" id="ssc_report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="events">Events Report</option>
                                                <option value="candidates">Candidates Report</option>
                                                <option value="initiatives">Initiatives Report</option>
                                                <option value="officers">Officers Report</option>
                                                <option value="statistics">General Statistics</option>
                                            </select>
                                        </div>
                                        <div id="officer_year_div" style="display: none;">
                                            <label for="officer_year" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Year</label>
                                            <select name="officer_year" id="officer_year" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="present">Present</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="ssc_export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                            <select name="export_format" id="ssc_export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="excel">Excel (CSV)</option>
                                                <option value="docs">Word Document</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 20px;">
                                        <label for="ssc_letterhead_template" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Letterhead Template (optional)</label>
                                        <input type="file" name="letterhead_template" id="ssc_letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8f9fa;">
                                        <small style="display: block; margin-top: 6px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                            If you do not upload a letterhead template, only the raw data (no letterhead) will be exported, as before.
                                            If you upload a template, it must be a <strong>.docx</strong> file for Word exports or a <strong>.xlsx</strong> file for Excel exports —
                                            the file should already contain the letterhead/header design. The exported data will be appended below the existing content of that file.
                                            <br>Legacy <strong>.doc</strong> / <strong>.xls</strong> formats are not supported — please use <strong>.docx</strong> / <strong>.xlsx</strong>.
                                        </small>
                                    </div>
                                    <div class="report-actions" style="display: flex; justify-content: flex-start;">
                                        <button type="submit" name="export_ssc_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; width: auto;">
                                            <i class="fa-solid fa-download"></i>
                                            Export Report
                                        </button>
                                    </div>
                                </form>
                                <script>
                                    (function () {
                                        var reportTypeSelect = document.getElementById('ssc_report_type');
                                        var officerYearDiv = document.getElementById('officer_year_div');
                                        var officerYearSelect = document.getElementById('officer_year');

                                        function loadOfficerYears() {
                                            fetch('ssc_api.php?action=get_officer_years')
                                                .then(function (response) { return response.json(); })
                                                .then(function (data) {
                                                    if (!officerYearSelect || !data.years || !data.years.length) return;
                                                    officerYearSelect.innerHTML = '<option value="present">Present</option>';
                                                    data.years.forEach(function (year) {
                                                        if (year !== 'present') {
                                                            var option = document.createElement('option');
                                                            option.value = year;
                                                            option.textContent = year;
                                                            officerYearSelect.appendChild(option);
                                                        }
                                                    });
                                                })
                                                .catch(function () {});
                                        }

                                        function toggleOfficerYear() {
                                            var showYear = reportTypeSelect && reportTypeSelect.value === 'officers';
                                            if (officerYearDiv) officerYearDiv.style.display = showYear ? 'block' : 'none';
                                            if (showYear) loadOfficerYears();
                                        }

                                        if (reportTypeSelect) {
                                            reportTypeSelect.addEventListener('change', toggleOfficerYear);
                                            toggleOfficerYear();
                                        }
                                    })();
                                </script>
                            </div>

                            <div id="scholarship-tab" class="service-tab-panel" role="tabpanel">
                                <div style="margin: 12px 0 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                    <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                        <li><strong>Announcements:</strong> All scholarship announcements with titles, content, and deadlines</li>
                                        <li><strong>Applications:</strong> Student application submissions and status tracking</li>
                                        <li><strong>Validations:</strong> Document validation records and submission status</li>
                                        <li><strong>Others:</strong> General scholarship statistics and summaries</li>
                                    </ul>
                                    <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Export data in multiple formats for reporting and analysis.</p>
                                </div>
                                <form id="scholarshipExportForm" method="post" action="scholarship_reports.php" enctype="multipart/form-data" style="width: 100%;">
                                    <div class="report-export-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                        <div>
                                            <label for="scholarship_report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                            <select name="report_type" id="scholarship_report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="announcements">Announcements Report</option>
                                                <option value="applications">Applications Report</option>
                                                <option value="validations">Validations Report</option>
                                                <option value="others">General Statistics</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="scholarship_export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                            <select name="export_format" id="scholarship_export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="excel">Excel (CSV)</option>
                                                <option value="docs">Word Document</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 20px;">
                                        <label for="scholarship_letterhead_template" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Letterhead Template (optional)</label>
                                        <input type="file" name="letterhead_template" id="scholarship_letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8f9fa;">
                                        <small style="display: block; margin-top: 6px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                            If you do not upload a letterhead template, only the raw data (no letterhead) will be exported, as before.
                                            If you upload a template, it must be a <strong>.docx</strong> file for Word exports or a <strong>.xlsx</strong> file for Excel exports —
                                            the file should already contain the letterhead/header design. The exported data will be appended below the existing content of that file.
                                            <br>Legacy <strong>.doc</strong> / <strong>.xls</strong> formats are not supported — please use <strong>.docx</strong> / <strong>.xlsx</strong>.
                                        </small>
                                    </div>
                                    <div class="report-actions" style="display: flex; justify-content: flex-start;">
                                        <button type="submit" name="export_scholarship_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; width: auto;">
                                            <i class="fa-solid fa-download"></i>
                                            Export Report
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div id="alumni-tab" class="service-tab-panel" role="tabpanel">
                                <div style="margin: 12px 0 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                    <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                        <li><strong>Alumni Master Directory:</strong> Comprehensive alumni directory listing</li>
                                        <li><strong>Employment Status Report:</strong> Employment distribution by alumni record</li>
                                        <li><strong>Graduate Tracer Survey (GTS) Summary:</strong> Survey summary and tracer analytics</li>
                                    </ul>
                                </div>
                                <form method="post" action="reports_analytics.php" enctype="multipart/form-data" style="width: 100%;">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                        <div>
                                            <label for="alumni_report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Report Type</label>
                                            <select name="report_type" id="alumni_report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="master_directory">Alumni Master Directory</option>
                                                <option value="employment_status">Employment Status Report</option>
                                                <option value="gts_summary">Graduate Tracer Survey (GTS) Summary</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="alumni_graduation_year" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Graduation Year / Batch</label>
                                            <select name="graduation_year" id="alumni_graduation_year" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="all">All</option>
                                                <option value="2024">2024</option>
                                                <option value="2023">2023</option>
                                                <option value="2022">2022</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="alumni_course" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Course</label>
                                            <select name="course" id="alumni_course" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="all">All</option>
                                                <option value="BSCS">BSCS</option>
                                                <option value="BEED">BEED</option>
                                                <option value="BSA">BSA</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="alumni_employment_status" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Employment Status</label>
                                            <select name="employment_status" id="alumni_employment_status" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="all">All</option>
                                                <option value="employed">Employed</option>
                                                <option value="unemployed">Unemployed</option>
                                                <option value="self-employed">Self-Employed</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="alumni_export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Export Format</label>
                                            <select name="export_format" id="alumni_export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="excel">Excel (CSV)</option>
                                                <option value="docs">Word Document</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 20px;">
                                        <label for="alumni_letterhead_template" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Letterhead Template (optional)</label>
                                        <input type="file" name="letterhead_template" id="alumni_letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8f9fa;">
                                        <small style="display: block; margin-top: 6px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                            Kung walang i-a-upload, raw data lang (walang letterhead) ang mae-export.
                                            Kapag nag-upload ka, dapat <strong>.docx</strong> file ito para sa Word export, o <strong>.xlsx</strong> file para sa Excel export —
                                            dapat mayroon nang letterhead/header design ang file na iyon. Ang datos ay idadagdag sa ilalim ng existing content ng file.
                                            <br>Hindi supported ang lumang <strong>.doc</strong> / <strong>.xls</strong> — gamitin ang <strong>.docx</strong> / <strong>.xlsx</strong>.
                                        </small>
                                    </div>
                                    <div style="display: flex; justify-content: flex-start;">
                                        <button type="submit" name="export_alumni_report" value="1" class="action-button">
                                            <i class="fa-solid fa-download"></i>
                                            Export Report
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </section>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const pageOverlay = document.getElementById('pageOverlay');
        const sideNav = document.querySelector('.side-nav');
        if (!mobileToggle || !pageOverlay || !sideNav) return;
        function openMobileNav(){ sideNav.classList.add('mobile-open'); pageOverlay.classList.add('active'); }
        function closeMobileNav(){ sideNav.classList.remove('mobile-open'); pageOverlay.classList.remove('active'); }
        mobileToggle.addEventListener('click', function(){ sideNav.classList.contains('mobile-open') ? closeMobileNav() : openMobileNav(); });
        pageOverlay.addEventListener('click', closeMobileNav);
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMobileNav(); });

        const tabButtons = document.querySelectorAll('.service-tab');
        const tabPanels = document.querySelectorAll('.service-tab-panel');

        tabButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');

                tabButtons.forEach(function(btn) {
                    btn.classList.toggle('active', btn === button);
                    btn.setAttribute('aria-selected', btn === button ? 'true' : 'false');
                });

                tabPanels.forEach(function(panel) {
                    panel.classList.toggle('active', panel.id === targetId);
                });
            });
        });
    });
    </script>
    <script src="../assets/js/app.js" defer></script>
    <script>window.addEventListener('DOMContentLoaded', function(){ window.dispatchEvent(new Event('resize')); });</script>
    <script>
    (function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();
    </script>
</body>
</html>
