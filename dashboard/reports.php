<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if ($user['role'] !== 'admin' && ($user['role'] !== 'teacher' || $user['head_service'] !== 'guidance')) {
    header('Location: guidance_home.php');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
ensure_guidance_schema();

$pdo = get_db();

// Get case statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'under_counseling' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM guidance_cases
");
$caseStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get cases by concern type
$stmt = $pdo->query("
    SELECT type_of_concern, COUNT(*) as count
    FROM guidance_cases
    GROUP BY type_of_concern
    ORDER BY count DESC
");
$concernStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance stats
$stmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN attendance_status = 'attended' THEN 1 ELSE 0 END) as attended,
        SUM(CASE WHEN attendance_status = 'non_appearance' THEN 1 ELSE 0 END) as non_appearance,
        SUM(CASE WHEN attendance_status = 'pending' THEN 1 ELSE 0 END) as pending,
        COUNT(*) as total
    FROM guidance_sessions
");
$attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get resolution statistics
$stmt = $pdo->query("
    SELECT external_resolution, COUNT(*) as count
    FROM guidance_cases
    WHERE status = 'closed'
    GROUP BY external_resolution
    ORDER BY count DESC
");
$resolutionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly trend
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m-01') as month,
        COUNT(*) as count
    FROM guidance_cases
    GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
    ORDER BY month DESC
    LIMIT 12
");
$monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------------
// Letterhead merge helpers (no Composer libraries needed).
// If the user uploads a .docx / .xlsx file that already has a letterhead
// (logo, header, etc.), the exported data is appended to a copy of that
// file via direct ZIP/XML manipulation. If no file is uploaded, export
// stays exactly as before (raw CSV / .doc).
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

// Handle export functionality
if (isset($_POST['export_report'])) {
    $reportType = $_POST['report_type'] ?? 'cases';
    $exportFormat = $_POST['export_format'] ?? 'excel';
    $reportTitle = 'Guidance ' . ucfirst($reportType) . ' Report';

    $headers = [];
    $rows = [];

    if ($reportType === 'cases') {
        $stmt = $pdo->query("
            SELECT 
                c.id as case_id,
                c.case_number,
                u.full_name as student_name,
                c.course_year,
                c.status,
                c.type_of_concern,
                c.created_at
            FROM guidance_cases c
            LEFT JOIN users u ON c.reported_student_id = u.id
            ORDER BY c.created_at DESC
        ");
        $caseDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headers = ['Case ID', 'Case Number', 'Student Name', 'Course Year', 'Status', 'Type of Concern', 'Created Date'];
        foreach ($caseDetails as $case) {
            $rows[] = [
                $case['case_id'],
                $case['case_number'],
                $case['student_name'] ?: 'N/A',
                $case['course_year'] ?: 'N/A',
                $case['status'],
                $case['type_of_concern'] ?: 'N/A',
                $case['created_at'],
            ];
        }
        $rows[] = ['TOTAL', count($caseDetails) . ' cases', '', '', '', '', ''];
    } elseif ($reportType === 'concerns') {
        $stmt = $pdo->query("
            SELECT 
                c.id as case_id,
                c.case_number,
                u.full_name as student_name,
                c.course_year,
                c.type_of_concern,
                c.status,
                c.created_at
            FROM guidance_cases c
            LEFT JOIN users u ON c.reported_student_id = u.id
            ORDER BY c.type_of_concern ASC, c.created_at DESC
        ");
        $concernDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headers = ['Case ID', 'Case Number', 'Student Name', 'Course Year', 'Type of Concern', 'Status', 'Created Date'];
        foreach ($concernDetails as $concern) {
            $rows[] = [
                $concern['case_id'],
                $concern['case_number'],
                $concern['student_name'] ?: 'N/A',
                $concern['course_year'] ?: 'N/A',
                $concern['type_of_concern'] ?: 'N/A',
                $concern['status'],
                $concern['created_at'],
            ];
        }
        $rows[] = ['TOTAL', count($concernDetails) . ' cases', '', '', '', '', ''];
    } elseif ($reportType === 'attendance') {
        $headers = ['Attendance Status', 'Count'];
        $rows = [
            ['Attended', $attendanceStats['attended'] ?? 0],
            ['Non-Appearance', $attendanceStats['non_appearance'] ?? 0],
            ['Pending', $attendanceStats['pending'] ?? 0],
            ['Total Sessions', $attendanceStats['total'] ?? 0],
        ];
    } elseif ($reportType === 'resolutions') {
        $headers = ['Resolution Type', 'Count'];
        $totalResolutions = 0;
        foreach ($resolutionStats as $resolution) {
            $resolutionType = str_replace('_', ' ', ucfirst($resolution['external_resolution'] ?: 'N/A'));
            $rows[] = [$resolutionType, $resolution['count']];
            $totalResolutions += $resolution['count'];
        }
        $rows[] = ['TOTAL RESOLUTIONS', $totalResolutions];
    }

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
            export_merge_into_docx($tmpPath, $reportTitle, $headers, $rows, "guidance_{$reportType}_report.docx");
        } else {
            if ($ext !== 'xlsx') {
                http_response_code(400);
                exit('Ang letterhead template para sa Excel export ay dapat .xlsx file.');
            }
            export_merge_into_xlsx($tmpPath, $reportTitle, $headers, $rows, "guidance_{$reportType}_report.xlsx");
        }
        exit;
    }

    // No letterhead uploaded: export raw data only, same formats as before.
    if ($exportFormat === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        $filename = "guidance_{$reportType}_report.csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\r\n";
        foreach ($rows as $row) {
            echo implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', (string) $cell) . '"', $row)) . "\r\n";
        }
    } elseif ($exportFormat === 'docs') {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        $filename = "guidance_{$reportType}_report.doc";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "<html><head><title>" . htmlspecialchars($reportTitle) . "</title></head><body>";
        echo "<h1>" . htmlspecialchars($reportTitle) . "</h1>";
        echo "<p>Generated on: " . date('Y-m-d H:i:s') . "</p><br>";
        echo "<table border='1'><tr>";
        foreach ($headers as $h) {
            echo "<th>" . htmlspecialchars($h) . "</th>";
        }
        echo "</tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $cell) {
                echo "<td>" . htmlspecialchars((string) $cell) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        echo "</body></html>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | Guidance System</title>
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

        .case-page-header { position: relative; min-height: 245px; display: flex; align-items: center; overflow: hidden; margin: 0 0 24px; padding: 42px 48px 30px; background: #f8f1e7; border: 0; border-radius: 0 0 24px 24px; }
        .case-page-header::before { content: ''; position: absolute; z-index: 0; inset: 0 auto 0 0; width: 3px; background: #861b17; }
        .case-page-header > div:first-child { position: relative; z-index: 2; width: 65%; }
        .case-page-header .eyebrow { margin: 0; color: #861b17 !important; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 3px; line-height: 1; text-transform: uppercase; }
        .case-page-header h1 { margin: 14px 0 12px; color: #111 !important; font-family: Georgia, serif; font-size: clamp(36px, 4vw, 62px); font-weight: 600; letter-spacing: 0; line-height: 1.05; }
        .case-page-header .dashboard-subtitle { max-width: 760px; margin: 0; color: #34302d !important; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; }
        .case-header__art { position: absolute; z-index: 1; top: 0; right: 0; width: 43%; height: 100%; color: #d7a58d; opacity: .78; }
        .case-header__art::before { content: ''; position: absolute; right: 13%; bottom: 2%; width: 82%; height: 30%; background: radial-gradient(ellipse at center, rgba(235, 205, 167, .48) 0 42%, transparent 43%); border-radius: 50%; }
        .case-header__art::after { content: ''; position: absolute; top: 23%; left: 4%; width: 76%; height: 42%; border: 1px solid rgba(215, 165, 141, .45); border-left-color: transparent; border-radius: 50%; transform: rotate(-13deg); }
        .case-header__art i { position: absolute; z-index: 2; font-family: 'Font Awesome 6 Free'; font-size: 28px; font-style: normal; font-weight: 900; }
        .case-header__art .art-chat { top: 57%; left: 2%; padding: 12px 14px; border: 2px solid rgba(215, 165, 141, .7); border-radius: 9px; font-size: 15px; }
        .case-header__art .art-file { top: 17%; left: 39%; padding: 16px 19px; border: 2px solid rgba(215, 165, 141, .58); border-radius: 9px; box-shadow: 20px 10px 0 -1px #f8f1e7, 20px 10px 0 1px rgba(215, 165, 141, .48), 36px 20px 0 -1px #f8f1e7, 36px 20px 0 1px rgba(215, 165, 141, .42); font-size: 35px; }
        .case-header__art .art-card { top: 35%; left: 47%; padding: 13px 16px; border: 2px solid rgba(215, 165, 141, .65); border-radius: 8px; background: rgba(248, 241, 231, .7); font-size: 28px; }
        .case-header__art .art-check { top: 22%; right: 7%; padding: 12px; border: 2px solid #e3b968; border-radius: 50%; color: #e3b968; font-size: 21px; }
        .case-header__art .art-pin { bottom: 10%; left: 42%; width: 31px; height: 31px; color: transparent; background: #cf9589; border-radius: 50% 50% 50% 0; font-size: 0; transform: rotate(-45deg); }
        .case-header__art .art-pin::after { content: ''; position: absolute; top: 9px; left: 9px; width: 13px; height: 13px; background: #f8f1e7; border-radius: 50%; }
        .case-header__art .art-dots { right: 25%; bottom: 12%; color: #d7a58d; font-size: 28px; }
        @media (max-width: 768px) { .case-page-header { min-height: 245px; padding: 32px 24px 110px; } .case-page-header > div:first-child { width: 100%; } .case-page-header h1 { font-size: 36px; } .case-header__art { width: 55%; opacity: .35; } }

        .card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 8px; background: white; margin-bottom: 16px; }
        .card h3 { margin-top: 0; color: #1f2937; }
        .metric-card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; background: white; }
        .metric-card h3 { margin: 0 0 8px 0; color: #6b7280; font-size: 14px; font-weight: 500; text-transform: uppercase; }
        .metric-card .value { font-size: 32px; font-weight: 700; color: #1f2937; margin: 8px 0; }
        .metric-card .status { font-size: 13px; color: #6b7280; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .alert-info { background: #dbeafe; border-left: 4px solid #3b82f6; color: #1e40af; }
        .stats-table { width: 100%; border-collapse: collapse; }
        .stats-table th, .stats-table td { padding: 12px 10px; border: 1px solid #e2e8f0; text-align: left; }
        .stats-table th { background: #f8fafc; font-weight: 600; }
        .stats-table tr:hover { background: #f9fafb; }
        .progress-bar { height: 24px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #3b82f6; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; font-weight: 600; }
        .chart-container { background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom: 16px; }
        .legend { display: flex; flex-wrap: wrap; gap: 16px; font-size: 14px; }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-color { width: 12px; height: 12px; border-radius: 2px; }
        @media (max-width: 720px) {
            .page-shell { padding: 0 12px; }
            .topbar, .card, .metric-card, .chart-container, .tabs-header { width: 100%; }
            .main-scroll { padding: 0; }
            .dashboard-intro h1 { font-size: 24px; }
            .dashboard-intro p { font-size: 14px; }
            .metric-card { padding: 16px; }
            .metric-card .value { font-size: 26px; }
            .metric-card .status { font-size: 12px; }
            .card { padding: 16px; border-radius: 14px; }
            .card h3 { font-size: 18px; }
            .card form > div { display: block; }
            .card form > div > div { width: 100%; }
            .card form button { width: 100%; justify-content: center; }
            .stats-table, .students-table { width: 100%; }
            .stats-table th, .stats-table td, .students-table th, .students-table td { padding: 10px 8px; font-size: 12px; }
            .stats-table thead, .students-table thead { display: none; }
            .stats-table tr, .students-table tr { display: block; width: 100%; margin-bottom: 14px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
            .stats-table td, .students-table td { display: block; width: 100%; border: none; border-bottom: 1px solid #e2e8f0; padding: 10px 0; }
            .stats-table td:last-child, .students-table td:last-child { border-bottom: none; }
            .stats-table td::before, .students-table td::before { content: attr(data-label); font-weight: 700; display: block; margin-bottom: 6px; color: #334155; }
            .progress-bar { height: 20px; }
            .progress-fill { font-size: 11px; }
            .chart-container { padding: 14px; }
            .legend { gap: 12px; }
            .legend-item { font-size: 13px; }
            .card h4, .card p, .metric-card h3 { font-size: 14px; }
            .card form select, .card form input, .card form textarea { font-size: 14px; }
            .card form label { font-size: 13px; }
        }
        @media (max-width: 480px) {
            .dashboard-intro h1 { font-size: 22px; }
            .dashboard-intro p { font-size: 13px; }
            .metric-card { padding: 14px; }
            .card { padding: 14px; }
            .card h3 { font-size: 16px; }
            .tab-button { min-width: 110px; padding: 10px 10px; font-size: 12px; }
            .stats-table td::before, .students-table td::before { font-size: 12px; }
        }
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Guidance Counselor</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="guidance_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="school_announcements.php?service=guidance" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="library_dashboard.php?service=guidance" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="clinic_dashboard.php?service=guidance" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc/ssc_dashboard.php?service=guidance" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="scholarship/scholarship_dashboard.php?service=guidance" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="ssaa_student_home.php?service=guidance" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Guidance">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage Guidance</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="case_management.php" data-tooltip="Case Management">
                            <span class="nav-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                            <span class="nav-text">Case Management</span>
                        </a>
                        <a href="good_moral.php" data-tooltip="Good Moral">
                            <span class="nav-icon"><i class="fa-solid fa-certificate"></i></span>
                            <span class="nav-text">Good Moral</span>
                        </a>
                        <a href="reports.php" class="active" data-tooltip="Reports">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-text">Reports</span>
                        </a>
                    </div>
                </div>
                <a href="profile.php?service=guidance" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php?service=guidance" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>

        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Guidance Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Guidance Counselor</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <!-- support icon removed per UI update -->

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <div class="menu-empty">No new announcements.</div>
                        <a class="menu-link menu-footer-link" href="about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="profile.php" class="menu-action">View profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="about.php">Help center</a>
                            <span class="menu-subtext">View support resources and FAQs.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="mailto:support@passcollege.edu">Email support</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <section class="dashboard-intro case-page-header">
                    <div>
                        <span class="eyebrow">REPORTS &amp; ANALYTICS</span>
                        <h1>Guidance Statistics and Insights</h1>
                        <p class="dashboard-subtitle">Analyze case statistics, session attendance, concern types, and resolution outcomes in one comprehensive view.</p>
                    </div>
                    <div class="case-header__art" aria-hidden="true">
                        <i class="fa-solid fa-message art-chat"></i>
                        <i class="fa-solid fa-folder-open art-file"></i>
                        <i class="fa-solid fa-address-card art-card"></i>
                        <i class="fa-solid fa-circle-check art-check"></i>
                        <i class="fa-solid fa-location-pin art-pin"></i>
                        <i class="fa-solid fa-leaf art-dots"></i>
                    </div>
                </section>

                    <!-- CASE STATISTICS OVERVIEW -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px;">
                        <div class="metric-card">
                            <h3>Total Cases</h3>
                            <div class="value"><?= (int)$caseStats['total'] ?></div>
                            <div class="status">All time submissions</div>
                        </div>
                        <div class="metric-card">
                            <h3>Pending Cases</h3>
                            <div class="value"><?= (int)$caseStats['pending'] ?></div>
                            <div class="status">Awaiting review</div>
                        </div>
                        <div class="metric-card">
                            <h3>Active Cases</h3>
                            <div class="value"><?= (int)$caseStats['active'] ?></div>
                            <div class="status">Under counseling</div>
                        </div>
                        <div class="metric-card">
                            <h3>Closed Cases</h3>
                            <div class="value"><?= (int)$caseStats['closed'] ?></div>
                            <div class="status">Resolved</div>
                        </div>
                    </div>

                    <!-- EXPORT REPORTS SECTION -->
                    <div class="card">
                        <h3><i class="fa-solid fa-file-export"></i> Export Reports</h3>
                        <div style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                            <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                <li><strong>Cases:</strong> Detailed case information with case ID, student name, course year, and status</li>
                                <li><strong>Concerns:</strong> Cases grouped by concern type with case ID, student name, and course year</li>
                                <li><strong>Attendance:</strong> Session attendance statistics</li>
                                <li><strong>Resolutions:</strong> Resolution outcomes for closed cases</li>
                            </ul>
                            <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Export data in multiple formats for reporting and analysis.</p>
                        </div>
                        <form method="post" enctype="multipart/form-data" id="exportReportForm" style="width: 100%;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label for="report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                    <select name="report_type" id="report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                        <option value="cases">Detailed Case Information</option>
                                        <option value="concerns">Cases by Concern Type</option>
                                        <option value="attendance">Session Attendance</option>
                                        <option value="resolutions">Resolution Outcomes</option>
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
                                <input type="file" name="letterhead_template" id="letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8f9fa;">
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
                        <script>
                            (function () {
                                var form = document.getElementById('exportReportForm');
                                var formatSelect = document.getElementById('export_format');
                                var fileInput = document.getElementById('letterhead_template');
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

                    <!-- CASE STATUS BREAKDOWN -->
                    <div class="card">
                        <h3><i class="fa-solid fa-chart-bar"></i> Case Status Distribution</h3>
                        <div class="chart-container">
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Pending Review</strong></span>
                                    <span><?= (int)$caseStats['pending'] ?> cases</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $caseStats['total'] > 0 ? (((int)$caseStats['pending'] / (int)$caseStats['total']) * 100) : 0 ?>%">
                                        <?= $caseStats['total'] > 0 ? round(((int)$caseStats['pending'] / (int)$caseStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Active Cases</strong></span>
                                    <span><?= (int)$caseStats['active'] ?> cases</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="background: #10b981; width: <?= $caseStats['total'] > 0 ? (((int)$caseStats['active'] / (int)$caseStats['total']) * 100) : 0 ?>%">
                                        <?= $caseStats['total'] > 0 ? round(((int)$caseStats['active'] / (int)$caseStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Closed Cases</strong></span>
                                    <span><?= (int)$caseStats['closed'] ?> cases</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="background: #6b7280; width: <?= $caseStats['total'] > 0 ? (((int)$caseStats['closed'] / (int)$caseStats['total']) * 100) : 0 ?>%">
                                        <?= $caseStats['total'] > 0 ? round(((int)$caseStats['closed'] / (int)$caseStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CONCERNS BY TYPE -->
                    <div class="card">
                        <h3><i class="fa-solid fa-magnifying-glass"></i> Cases by Concern Type</h3>
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>Concern Type</th>
                                    <th>Number of Cases</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($concernStats as $concern): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($concern['type_of_concern']) ?></td>
                                        <td><?= (int)$concern['count'] ?></td>
                                        <td><?= $caseStats['total'] > 0 ? round(((int)$concern['count'] / (int)$caseStats['total']) * 100, 1) : 0 ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- ATTENDANCE STATISTICS -->
                    <div class="card">
                        <h3><i class="fa-solid fa-calendar-days"></i> Session Attendance Statistics</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                            <div class="metric-card">
                                <h3>Total Sessions</h3>
                                <div class="value"><?= (int)$attendanceStats['total'] ?></div>
                            </div>
                            <div class="metric-card">
                                <h3>Attended</h3>
                                <div class="value" style="color: #10b981;"><?= (int)$attendanceStats['attended'] ?></div>
                            </div>
                            <div class="metric-card">
                                <h3>Non-Appearance</h3>
                                <div class="value" style="color: #ef4444;"><?= (int)$attendanceStats['non_appearance'] ?></div>
                            </div>
                            <div class="metric-card">
                                <h3>Pending</h3>
                                <div class="value" style="color: #f59e0b;"><?= (int)$attendanceStats['pending'] ?></div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Attended</strong></span>
                                    <span><?= (int)$attendanceStats['attended'] ?> sessions</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="background: #10b981; width: <?= $attendanceStats['total'] > 0 ? (((int)$attendanceStats['attended'] / (int)$attendanceStats['total']) * 100) : 0 ?>%">
                                        <?= $attendanceStats['total'] > 0 ? round(((int)$attendanceStats['attended'] / (int)$attendanceStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span><strong>Non-Appearance</strong></span>
                                    <span><?= (int)$attendanceStats['non_appearance'] ?> sessions</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="background: #ef4444; width: <?= $attendanceStats['total'] > 0 ? (((int)$attendanceStats['non_appearance'] / (int)$attendanceStats['total']) * 100) : 0 ?>%">
                                        <?= $attendanceStats['total'] > 0 ? round(((int)$attendanceStats['non_appearance'] / (int)$attendanceStats['total']) * 100) : 0 ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RESOLUTION OUTCOMES -->
                    <div class="card">
                        <h3><i class="fa-solid fa-check"></i> Resolution Outcomes - Closed Cases</h3>
                        <table class="stats-table">
                            <thead>
                                <tr>
                                    <th>Resolution Type</th>
                                    <th>Number of Cases</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($resolutionStats)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #6b7280;">No closed cases yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resolutionStats as $resolution): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(str_replace('_', ' ', ucfirst($resolution['external_resolution']))) ?></td>
                                            <td><?= (int)$resolution['count'] ?></td>
                                            <td><?= $caseStats['closed'] > 0 ? round(((int)$resolution['count'] / (int)$caseStats['closed']) * 100, 1) : 0 ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function isScreenMobile() {
                return window.innerWidth >= 320 && window.innerWidth <= 768;
            }
            
            let touchStartY = 0;
            const SWIPE_THRESHOLD = 100;
            const SWIPE_START_LIMIT = 100;
            
            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.touches.length !== 1) return;
                touchStartY = e.touches[0].clientY;
            }, { passive: true });
            
            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.changedTouches.length !== 1) return;
                
                const touchEndY = e.changedTouches[0].clientY;
                const distance = touchEndY - touchStartY;
                
                if (touchStartY <= SWIPE_START_LIMIT && distance >= SWIPE_THRESHOLD) {
                    const spinner = document.getElementById('swipe-refresh-spinner');
                    if (spinner) {
                        spinner.style.display = 'flex';
                    }
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                }
            }, { passive: true });
        });
        
        document.addEventListener('DOMContentLoaded', function () {
            const mobileToggle = document.getElementById('mobileMenuToggle');
            const pageOverlay = document.getElementById('pageOverlay');
            const sideNav = document.querySelector('.side-nav');

            function openMobileNav() {
                if (sideNav) sideNav.classList.add('mobile-open');
                if (pageOverlay) pageOverlay.classList.add('active');
            }

            function closeMobileNav() {
                if (sideNav) sideNav.classList.remove('mobile-open');
                if (pageOverlay) pageOverlay.classList.remove('active');
            }

            if (mobileToggle) {
                mobileToggle.addEventListener('click', function () {
                    if (sideNav && sideNav.classList.contains('mobile-open')) {
                        closeMobileNav();
                    } else {
                        openMobileNav();
                    }
                });
            }

            if (pageOverlay) {
                pageOverlay.addEventListener('click', closeMobileNav);
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeMobileNav();
            });
        });
    </script>
</body>
</html>
