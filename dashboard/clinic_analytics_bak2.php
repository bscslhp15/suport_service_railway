<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'clinic') {
    header('Location: /auth/teacher_login.php');
    exit;
}

// Initialize clinic schema
ensure_clinic_schema();

// Manage section remains collapsed by default on clinic management pages
$is_manage_open = false;

// Get analytics data
$analytics = get_clinic_analytics();

// Condition filters (for course + timeframe + reason breakdown)
$cond_timeframe = $_REQUEST['cond_timeframe'] ?? 'month'; // week|month|overall
$cond_reason = trim($_REQUEST['cond_reason'] ?? '');
$cond_course = trim($_REQUEST['cond_course'] ?? '');
$cond_type = trim($_REQUEST['cond_type'] ?? '');

// Get lists for dropdowns
// Use the same reason options as clinic_visit_logs.php
$distinctReasons = ['Common Cold', 'Headache', 'Fever', 'Stomach Pain', 'Allergies', 'Other'];
$distinctTypes = ['student', 'teacher'];
// Use the same course options as the student registration page
$distinctCourses = [
    'Bachelor of Science in Accountancy',
    'Bachelor of Science in Business Administration',
    'Bachelor in Elementary Education',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Criminology',
    'Bachelor of Science in Hospitality Management',
    'Bachelor of Science in Tourism Management',
];

// Compute breakdown when requested or by default for current month if reason selected
$conditionCourseBreakdown = [];

// Load all recent visitors, then filter client-side to avoid page refresh
$conditionVisitors = get_condition_visitors('', '', null, null);

// Only show breakdown table if reason is explicitly selected
if ($cond_reason !== '') {
    $conditionCourseBreakdown = get_condition_course_breakdown($cond_timeframe, $cond_reason, $cond_course ?: null, $cond_type ?: null);
}

// If timeframe filter is set (even without reason), override top conditions/needs
$daysMap = ['week' => 7, 'month' => 30, 'overall' => 0];
$daysForAnalytics = $daysMap[$cond_timeframe] ?? 30;
$analytics['top_conditions'] = get_top_conditions($daysForAnalytics);
$analytics['top_needs'] = get_top_needs($daysForAnalytics);

// Set the active analytics tab to Conditions if any condition filters are present
$activeAnalyticsTab = 'analytics-overview';
if (!empty($cond_timeframe) || !empty($cond_reason) || !empty($cond_course) || !empty($cond_type)) {
    $activeAnalyticsTab = 'analytics-conditions';
}

// ---------------------------------------------------------------------
// Export helpers
// ---------------------------------------------------------------------
// A "block" describes one piece of a report in a format-agnostic way:
//   ['title' => string|null, 'headers' => array|null, 'rows' => array<array>]
// This same $blocks array is used to render CSV, the old HTML-based
// ".doc", or to be merged into an uploaded .docx / .xlsx letterhead file.

function build_export_blocks($reportType, $analytics) {
    $blocks = [];

    if ($reportType === 'overview') {
        $rows = [
            ['Total Patients', $analytics['total_patients']],
            ['Monthly Visits', $analytics['monthly_visits']],
            ["Today's Visits", $analytics['today_visits']],
            ['Weekly Visits', $analytics['week_visits']],
            ['Average Rating', $analytics['avg_rating']],
            ['Total Medicines', $analytics['total_medicines']],
            ['Low Stock Alerts', $analytics['low_stock_alerts']],
            ['Expired Medicines', $analytics['expired_medicines']],
            ['Pending Clearances', $analytics['pending_clearances']],
            ['Approved Clearances (Monthly)', $analytics['approved_clearances']],
            ['Rejected Clearances (Monthly)', $analytics['rejected_clearances']],
            ['Peak Hour', $analytics['peak_hour'] . ':00'],
            ['Average Daily Visits', $analytics['avg_daily_visits']],
            ['Monthly Treatments', $analytics['monthly_treatments']],
            ['Average Service Time (Minutes)', $analytics['avg_service_time']],
        ];
        $blocks[] = ['title' => null, 'headers' => ['Metric', 'Value'], 'rows' => $rows];
    } elseif ($reportType === 'visits') {
        $exportTimeframe = $_POST['cond_timeframe'] ?? $_GET['cond_timeframe'] ?? 'month';
        $exportReason = trim($_POST['cond_reason'] ?? $_GET['cond_reason'] ?? '');
        $exportCourse = trim($_POST['cond_course'] ?? $_GET['cond_course'] ?? '');
        $exportType = trim($_POST['cond_type'] ?? $_GET['cond_type'] ?? '');
        $conditionVisitors = get_condition_visitors(
            $exportTimeframe === 'overall' ? '' : $exportTimeframe,
            $exportReason,
            $exportCourse !== '' ? $exportCourse : null,
            $exportType !== '' ? $exportType : null
        );

        $visitorGroups = [];
        foreach ($conditionVisitors as $visitor) {
            $typeKey = strtolower($visitor['visitor_type'] ?? 'student');
            $courseKey = trim($visitor['course_department'] ?? 'Unspecified');
            if ($typeKey === 'student') {
                $visitorGroups['student:' . $courseKey][] = $visitor;
            } else {
                $visitorGroups['teacher:' . $typeKey][] = $visitor;
            }
        }

        $filterNote = 'Filtered by: ' . $exportTimeframe . ' / ' . ($exportReason ?: 'All Reasons') . ' / ' . ($exportCourse ?: 'All Courses') . ' / ' . ($exportType ?: 'All Types');
        $blocks[] = ['title' => $filterNote, 'headers' => null, 'rows' => []];

        foreach ($visitorGroups as $group => $visitors) {
            list($groupType, $groupName) = explode(':', $group, 2);
            $blockTitle = $groupType === 'student' ? ('Student Course: ' . $groupName) : 'Teacher Visits';
            $rows = [];
            foreach ($visitors as $visitor) {
                $timeValue = $visitor['time_in'] ?? '';
                $dateValue = $timeValue ? date('Y-m-d', strtotime($timeValue)) : '-';
                $clockValue = $timeValue ? date('H:i:s', strtotime($timeValue)) : '-';
                $rows[] = [
                    $visitor['name'] ?? '-',
                    ucfirst($visitor['visitor_type'] ?? 'student'),
                    $visitor['course_department'] ?? '-',
                    $visitor['reason_for_visit'] ?? '-',
                    $dateValue,
                    $clockValue,
                ];
            }
            $blocks[] = ['title' => $blockTitle, 'headers' => ['Name', 'Type', 'Course / Department', 'Reason', 'Date', 'Time'], 'rows' => $rows];
        }
    } elseif ($reportType === 'needs') {
        $rows = [];
        if (!empty($analytics['top_needs'])) {
            foreach ($analytics['top_needs'] as $need) {
                $rows[] = [$need['need'], $need['count'], $need['percentage'] . '%'];
            }
        }
        $blocks[] = ['title' => null, 'headers' => ['Need Category', 'Cases', 'Percentage'], 'rows' => $rows];
    } elseif ($reportType === 'inventory') {
        $pdo = get_db();
        $medicines = $pdo->query("SELECT name, stock_quantity, min_stock_level, expiry_date FROM clinic_medicines ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $rows = [];
        foreach ($medicines as $med) {
            $rows[] = [$med['name'], $med['stock_quantity'], $med['min_stock_level'], $med['expiry_date'] ?: 'N/A'];
        }
        $blocks[] = ['title' => null, 'headers' => ['Medicine Name', 'Stock Quantity', 'Min Stock Level', 'Expiry Date'], 'rows' => $rows];
    } elseif ($reportType === 'clearances') {
        $pdo = get_db();
        $clearances = $pdo->query("SELECT cr.id, u.full_name, cr.reason, cr.status, cr.submitted_at, cr.reviewed_at FROM clinic_clearance_requests cr JOIN users u ON cr.user_id = u.id ORDER BY cr.submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $rows = [];
        foreach ($clearances as $clearance) {
            $rows[] = [$clearance['id'], $clearance['full_name'], $clearance['reason'], $clearance['status'], $clearance['submitted_at'], $clearance['reviewed_at'] ?: 'N/A'];
        }
        $blocks[] = ['title' => null, 'headers' => ['ID', 'Student Name', 'Reason', 'Status', 'Submitted At', 'Reviewed At'], 'rows' => $rows];
    }

    return $blocks;
}

function export_render_csv($blocks) {
    foreach ($blocks as $block) {
        if (!empty($block['title'])) {
            echo '"' . str_replace('"', '""', $block['title']) . "\"\r\n";
        }
        if (!empty($block['headers'])) {
            echo implode(',', $block['headers']) . "\r\n";
        }
        foreach ($block['rows'] as $row) {
            echo implode(',', array_map(function ($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\r\n";
        }
        if (!empty($block['headers']) || !empty($block['rows'])) {
            echo "\r\n";
        }
    }
}

function export_render_html_doc($reportTitle, $blocks) {
    echo "<html><head><title>" . htmlspecialchars($reportTitle) . "</title></head><body>";
    echo "<h1>" . htmlspecialchars($reportTitle) . "</h1>";
    echo "<p>Generated on: " . date('Y-m-d H:i:s') . "</p><br>";
    foreach ($blocks as $block) {
        if (!empty($block['title'])) {
            echo "<h3>" . htmlspecialchars($block['title']) . "</h3>";
        }
        if (!empty($block['headers'])) {
            echo "<table border='1' cellpadding='5' cellspacing='0'><tr>";
            foreach ($block['headers'] as $h) {
                echo "<th>" . htmlspecialchars($h) . "</th>";
            }
            echo "</tr>";
            foreach ($block['rows'] as $row) {
                echo "<tr>";
                foreach ($row as $cell) {
                    echo "<td>" . htmlspecialchars($cell) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
            echo "<div style='page-break-after: always;'></div>";
        }
    }
    echo "</body></html>";
}

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

// Inserts the report title + all $blocks after the existing content of an
// uploaded .docx letterhead template, then streams the merged file.
function export_merge_into_docx($templatePath, $reportTitle, $blocks, $downloadName) {
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

    $insert = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    $insert .= '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t xml:space="preserve">' . docx_escape($reportTitle) . '</w:t></w:r></w:p>';
    $insert .= '<w:p><w:r><w:t xml:space="preserve">' . docx_escape('Generated on: ' . date('Y-m-d H:i:s')) . '</w:t></w:r></w:p>';
    $insert .= '<w:p/>';

    foreach ($blocks as $block) {
        if (!empty($block['title'])) {
            $insert .= '<w:p><w:r><w:rPr><w:b/></w:rPr><w:t xml:space="preserve">' . docx_escape($block['title']) . '</w:t></w:r></w:p>';
        }
        if (!empty($block['headers'])) {
            $insert .= docx_build_table($block['headers'], $block['rows']);
            $insert .= '<w:p/>';
        }
    }

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

// Appends the report title + all $blocks as new rows below the existing
// content of an uploaded .xlsx letterhead template, then streams the merged file.
function export_merge_into_xlsx($templatePath, $reportTitle, $blocks, $downloadName) {
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

    foreach ($blocks as $block) {
        if (!empty($block['title'])) {
            $newRows .= xlsx_row($rowNum++, [$block['title']]);
        }
        if (!empty($block['headers'])) {
            $newRows .= xlsx_row($rowNum++, $block['headers']);
            foreach ($block['rows'] as $row) {
                $newRows .= xlsx_row($rowNum++, $row);
            }
            $rowNum++;
        }
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
    $reportType = $_POST['report_type'] ?? 'overview';
    $exportFormat = $_POST['export_format'] ?? 'excel'; // 'excel' or 'docs'
    $reportTitle = 'Clinic ' . ucfirst($reportType) . ' Report';
    $blocks = build_export_blocks($reportType, $analytics);

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
            export_merge_into_docx($tmpPath, $reportTitle, $blocks, "clinic_{$reportType}_report.docx");
        } else {
            if ($ext !== 'xlsx') {
                http_response_code(400);
                exit('Ang letterhead template para sa Excel export ay dapat .xlsx file.');
            }
            export_merge_into_xlsx($tmpPath, $reportTitle, $blocks, "clinic_{$reportType}_report.xlsx");
        }
        exit;
    }

    // No letterhead uploaded: export raw data only, same formats as before.
    if ($exportFormat === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        $filename = $reportType === 'visits' ? 'clinic_condition_visitors_report.csv' : "clinic_{$reportType}_report.csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        export_render_csv($blocks);
    } else {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        $filename = "clinic_{$reportType}_report.doc";
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        export_render_html_doc($reportTitle, $blocks);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinic Analytics | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-light: #f8f9fa;
        }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
            color: var(--clinic-maroon);
        }

        .section-subtitle {
            margin-top: 10px;
            color: #4b5563;
            font-size: 0.95rem;
            line-height: 1.6;
            max-width: 760px;
        }

        .page-header-panel {
            margin-bottom: 48px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid var(--clinic-maroon);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card h4 {
            margin: 0 0 10px 0;
            color: var(--clinic-maroon);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--clinic-maroon);
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .stat-card .trend {
            display: flex;
            align-items: center;
            margin-top: 10px;
            font-size: 0.8rem;
        }

        .trend.positive {
            color: #28a745;
        }

        .trend.negative {
            color: #dc3545;
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            margin: 0;
            color: var(--clinic-maroon);
        }

        .chart-placeholder {
            height: 300px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 1.2rem;
            border: 2px dashed #dee2e6;
        }

        /* Ensure charts and canvases scale on small screens */
        .chart-container canvas, .chart-container .chart-placeholder {
            width: 100% !important;
            max-width: 100% !important;
            height: auto !important;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
            gap: 24px;
            align-items: start;
        }

        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .metric-card h4 {
            margin: 0 0 15px 0;
            color: var(--clinic-maroon);
            font-size: 16px;
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .metric-item:last-child {
            border-bottom: none;
        }

        .metric-item .label {
            font-weight: 500;
            color: #333;
        }

        .metric-item .value {
            font-weight: bold;
            color: var(--clinic-maroon);
        }

        .inventory-tab-bar,
        .analytics-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 10px;
            background: #ffffff;
            border-radius: 12px;
            justify-content: space-between;
            border: 1px solid #e5e7eb;
        }

        .analytics-tab,
        .library-tab-button {
            border: 2px solid transparent;
            background: transparent;
            color: #334155;
            padding: 14px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 150px;
            text-align: center;
            flex: 1;
        }

        .analytics-tab:hover,
        .library-tab-button:hover {
            background: var(--clinic-maroon);
            color: white;
            border-color: var(--clinic-gold);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .analytics-tab.active,
        .library-tab-button.active {
            background: var(--clinic-maroon);
            color: #ffffff;
            border-color: var(--clinic-gold);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .analytics-tab-content {
            display: none;
        }

        .analytics-tab-content.active {
            display: block;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 0;
        }

        .metric-table-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            padding: 20px;
        }

        .metric-table-card .section-header {
            margin-bottom: 16px;
        }

        .metric-table-card .section-header h2 {
            font-size: 18px;
            margin: 0;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .metric-table-card .data-table {
            width: 100%;
        }

        .metric-table-card .data-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .metric-table-card .data-table tr:last-child td {
            border-bottom: none;
        }

        .metric-table-card .data-table td:first-child {
            color: #555;
        }

        .metric-table-card .data-table td:last-child {
            text-align: right;
            font-weight: 700;
            color: var(--clinic-maroon);
        }

        .alert-card {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-card.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-card.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background: var(--clinic-maroon);
            color: white;
        }

        .btn-primary:hover {
            transform: scale(1.05);
        }
        /* Mobile adjustments for main content (720px -> 320px) */
        @media (max-width: 720px) {
            .content-section { padding: 12px !important; }

            .stats-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .stat-card {
                padding: 14px !important;
            }

            .stat-card .stat-value {
                font-size: 1.8rem !important;
            }

            .chart-container { padding: 12px !important; }
            .chart-placeholder { height: 180px !important; font-size: 1rem !important; }

            .metric-grid, .metrics-grid {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .metric-card, .metric-table-card {
                padding: 12px !important;
            }

            /* Make tables horizontally scrollable and touch-friendly */
            .table-responsive {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            .data-table th, .data-table td {
                padding: 10px !important;
                font-size: 14px !important;
            }

            /* Reduce page header spacing */
            .page-header-panel { margin-bottom: 18px !important; }

            /* Ensure main-scroll has padding to avoid edge collisions */
            .main-scroll { padding: 12px !important; }
        }

        /* Conditions section: make visitor and course breakdown tables stack into cards on small screens */
        @media (max-width: 720px) {
            #analytics-conditions .data-table {
                display: block !important;
                width: 100% !important;
                border: none !important;
                margin: 0 !important;
            }

            #analytics-conditions .data-table thead { display: none !important; }

            #analytics-conditions .data-table tbody,
            #analytics-conditions .data-table tr {
                display: block !important;
                width: 100% !important;
                margin-bottom: 12px !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 12px !important;
                padding: 12px !important;
                background: #fff !important;
                box-shadow: 0 2px 8px rgba(15,23,42,0.04) !important;
            }

            #analytics-conditions .data-table td {
                display: block !important;
                width: 100% !important;
                padding: 6px 0 !important;
                border: none !important;
                box-sizing: border-box !important;
            }

            #analytics-conditions .data-table td:before {
                content: attr(data-label) !important;
                display: block !important;
                font-size: 0.78rem !important;
                font-weight: 700 !important;
                color: #475569 !important;
                margin-bottom: 6px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.02em !important;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="nurse_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_home.php" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="librarian_home.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="nurse_home.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc_head_home.php" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="ssc_head_home.php#scholarship" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="ssaa_home.php" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">SSAA</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $is_manage_open ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu <?= $is_manage_open ? 'open' : '' ?>" aria-hidden="<?= $is_manage_open ? 'false' : 'true' ?>">
                        <a href="clinic_status.php" data-tooltip="Clinic Status">
                            <span class="nav-icon"><i class="fa-solid fa-clock"></i></span>
                            <span class="nav-text">Clinic Status</span>
                        </a>
                        <a href="clinic_health_records.php" data-tooltip="Health Records">
                            <span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>
                            <span class="nav-text">Health Records</span>
                        </a>
                        <a href="clinic_medicine_inventory.php" data-tooltip="Medicine Inventory">
                            <span class="nav-icon"><i class="fa-solid fa-pills"></i></span>
                            <span class="nav-text">Medicine Inventory</span>
                        </a>
                        <a href="clinic_visit_logs.php" data-tooltip="Visit Logs">
                            <span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>
                            <span class="nav-text">Visit Logs</span>
                        </a>
                      
                        <a href="clinic_announcements.php" data-tooltip="Announcements">
                            <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                            <span class="nav-text">Announcements</span>
                        </a>
                        <a href="clinic_analytics.php" class="active" data-tooltip="Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Analytics</span>
                        </a>
                    </div>
                </div>
                <a href="profile.php" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php" data-tooltip="About">
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
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Clinic Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Clinic Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                </div>
            </header>
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <div class="content-panel">
                    <div class="page-header-panel">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-chart-pie"></i> Clinic Analytics Overview</h2>
                        </div>
                        <p class="section-subtitle">See clinic performance, visit trends, and patient activity in one place.</p>
                    </div>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h4><i class="fa-solid fa-users"></i> Total Patients</h4>
                                <div class="stat-value"><?= number_format($analytics['total_patients']) ?></div>
                                <div class="stat-label">All time patients served</div>
                                <div class="trend">All-time total</div>
                            </div>
                            <div class="stat-card">
                                <h4><i class="fa-solid fa-calendar-check"></i> Monthly Visits</h4>
                                <div class="stat-value"><?= number_format($analytics['monthly_visits']) ?></div>
                                <div class="stat-label">Visits this month</div>
                                <div class="trend">Last 30 days</div>
                            </div>
                            <div class="stat-card">
                                <h4><i class="fa-solid fa-sun"></i> Today’s Visits</h4>
                                <div class="stat-value"><?= number_format($analytics['today_visits']) ?></div>
                                <div class="stat-label">Completed today</div>
                                <div class="trend">Live visit count</div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Reports Section -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-file-export"></i> Export Reports</h2>
                        </div>
                        <div style="padding: 24px; background: white; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="margin-bottom: 20px; padding: 20px; background: #f8f9fa; border-radius: 12px; border-left: 4px solid var(--clinic-maroon);">
                                <h4 style="margin: 0 0 10px 0; color: var(--clinic-maroon); font-size: 15px;">Report Types:</h4>
                                <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                    <li><strong>Overview:</strong> General clinic analytics and metrics</li>
                                    <li><strong>Condition Visitors:</strong> Export the Condition table data by Name, Type, Course, Reason, and Time</li>
                                    <li><strong>Needs:</strong> Visit needs and categories breakdown</li>
                                    <li><strong>Inventory:</strong> Medicine stock levels and expiry dates</li>
                                </ul>
                            </div>
                            <form method="post" enctype="multipart/form-data" style="width: 100%;" id="exportReportForm">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                    <div>
                                        <label for="report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--clinic-maroon);">Report Type</label>
                                        <select name="report_type" id="report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                    <option value="overview">Analytics Overview</option>
                                            <option value="visits">Condition Visitors</option>
                                            <option value="needs">Visit Needs</option>
                                            <option value="inventory">Medicine Inventory</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--clinic-maroon);">Export Format</label>
                                        <select name="export_format" id="export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                            <option value="excel">Excel (CSV)</option>
                                            <option value="docs">Word Document</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-bottom: 20px;">
                                    <label for="letterhead_template" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--clinic-maroon);">Letterhead Template (optional)</label>
                                    <input type="file" name="letterhead_template" id="letterhead_template" style="width: 100%; padding: 12px; border: 2px dashed #e2e8f0; border-radius: 10px; font-size: 14px; background: #f8f9fa;">
                                    <small id="letterheadHint" style="display: block; margin-top: 6px; color: #64748b; font-size: 12.5px; line-height: 1.5;">
                                        Kung wala kang i-upload dito, raw data lang (walang letterhead) ang ie-export, tulad ng dati.
                                        Kung mag-uupload ka, kailangan ay <strong>.docx</strong> file kapag Word ang piniling format, o <strong>.xlsx</strong> file kapag Excel — dapat ito yung file na may nakalagay nang letterhead/header design.
                                        Ang exported data ay idadagdag sa ilalim ng laman ng file na iyon.
                                        <br>Hindi kasama dito ang matandang <strong>.doc</strong> / <strong>.xls</strong> format — dapat bagong <strong>.docx</strong> / <strong>.xlsx</strong>.
                                    </small>
                                </div>
                                <input type="hidden" name="cond_timeframe" value="<?= htmlspecialchars($cond_timeframe) ?>" />
                                <input type="hidden" name="cond_reason" value="<?= htmlspecialchars($cond_reason) ?>" />
                                <input type="hidden" name="cond_course" value="<?= htmlspecialchars($cond_course) ?>" />
                                <input type="hidden" name="cond_type" value="<?= htmlspecialchars($cond_type) ?>" />
                                <div style="display: flex; justify-content: flex-start;">
                                    <button type="submit" name="export_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--clinic-maroon) 0%, var(--clinic-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
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

                                    // Warn (but don't block picking) as soon as a file is chosen, so the
                                    // user finds out right away if it doesn't match the selected format.
                                    fileInput.addEventListener('change', function () {
                                        if (!fileInput.files || !fileInput.files.length) return;
                                        var ext = fileExt(fileInput.files[0].name);
                                        var expected = requiredExt();
                                        if (ext !== expected) {
                                            alert('Ang napiling letterhead file ay ".' + ext + '" pero kailangan ay ".' + expected + '" para sa "' + (formatSelect.value === 'docs' ? 'Word Document' : 'Excel (CSV)') + '" na export format. Piliin ulit ang tamang file, o palitan ang Export Format.');
                                        }
                                    });

                                    // Final safety check before actually submitting the form.
                                    form.addEventListener('submit', function (e) {
                                        if (!fileInput.files || !fileInput.files.length) return; // no letterhead, that's fine
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
                    </div>

                    <div class="inventory-tab-bar analytics-tabs" role="tablist" aria-label="Analytics navigation">
                        <button class="analytics-tab library-tab-button <?= ($activeAnalyticsTab === 'analytics-overview') ? 'active' : '' ?>" type="button" onclick="switchAnalyticsTab('analytics-overview')">Overview</button>
                        <button class="analytics-tab library-tab-button <?= ($activeAnalyticsTab === 'analytics-conditions') ? 'active' : '' ?>" type="button" onclick="switchAnalyticsTab('analytics-conditions')">Conditions</button>
                        <button class="analytics-tab library-tab-button <?= ($activeAnalyticsTab === 'analytics-needs') ? 'active' : '' ?>" type="button" onclick="switchAnalyticsTab('analytics-needs')">Needs</button>
                    </div>
                    <div id="analytics-overview" class="analytics-tab-content <?= ($activeAnalyticsTab === 'analytics-overview') ? 'active' : '' ?>">

                    <?php if ($analytics['low_stock_alerts'] > 0): ?>
                    <div class="alert-card warning">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        <strong>Medicine Alert:</strong> <?= $analytics['low_stock_alerts'] ?> medicines are running low on stock.
                        <a href="clinic_medicine_inventory.php" class="btn btn-primary" style="margin-left: auto;">View Inventory</a>
                    </div>
                    <?php endif; ?>


                    <!-- Visit Trends Chart -->
                    <div class="chart-container">
                        <div class="chart-header">
                            <h3><i class="fa-solid fa-chart-line"></i> Visit Trends (Last 12 Months)</h3>
                        </div>
                        <div class="chart-placeholder">
                            <div style="text-align: center;">
                                <i class="fa-solid fa-chart-line" style="font-size: 48px; color: var(--clinic-maroon); margin-bottom: 15px;"></i>
                                <div>Visit Trends Chart</div>
                                <small style="color: #999;">Interactive chart would be displayed here</small>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Analytics -->
                    <div class="metrics-grid">
                        <div class="metric-table-card">
                            <div class="section-header">
                                <h2><i class="fa-solid fa-calendar-alt"></i> Visit Statistics</h2>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <tbody>
                                        <tr>
                                            <td>Today's Visits</td>
                                            <td><?= $analytics['today_visits'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>This Week</td>
                                            <td><?= $analytics['week_visits'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>This Month</td>
                                            <td><?= $analytics['monthly_visits'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>Peak Hour</td>
                                            <td><?= $analytics['peak_hour'] ?>:00</td>
                                        </tr>
                                        <tr>
                                            <td>Average per Day</td>
                                            <td><?= number_format($analytics['avg_daily_visits'], 1) ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="metric-table-card">
                            <div class="section-header">
                                <h2><i class="fa-solid fa-pills"></i> Medicine Usage</h2>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <tbody>
                                        <tr>
                                            <td>Total Medicines</td>
                                            <td><?= $analytics['total_medicines'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>Low Stock Items</td>
                                            <td><?= $analytics['low_stock_alerts'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>Expired Items</td>
                                            <td><?= $analytics['expired_medicines'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>Treatments This Month</td>
                                            <td><?= $analytics['monthly_treatments'] ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="metric-table-card">
                            <div class="section-header">
                                <h2><i class="fa-solid fa-clipboard-check"></i> Clearance Requests</h2>
                            </div>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <tbody>
                                        <tr>
                                            <td>Pending Requests</td>
                                            <td><?= $analytics['pending_clearances'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>Approved This Month</td>
                                            <td><?= $analytics['approved_clearances'] ?></td>
                                        </tr>
                                        <tr>
                                            <td>Rejected This Month</td>
                                            <td><?= $analytics['rejected_clearances'] ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                    <div id="analytics-conditions" class="analytics-tab-content <?= ($activeAnalyticsTab === 'analytics-conditions') ? 'active' : '' ?>">
                        <div class="content-section">
                            <div class="section-header">
                                <h2><i class="fa-solid fa-heartbeat"></i> Most Common Conditions (This Month)</h2>
                            </div>
                            <div style="margin: 12px 0; display:flex; flex-wrap:wrap; gap:12px; align-items:center;">
                                <form id="conditionFiltersForm" onsubmit="return false;" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                                    <label style="font-weight:600;">Timeframe:</label>
                                    <select id="cond_timeframe" name="cond_timeframe" style="padding:8px; min-width:160px;" onchange="filterConditionVisitors()">
                                        <option value="week" <?= $cond_timeframe === 'week' ? 'selected' : '' ?>>Last 7 days</option>
                                        <option value="month" <?= $cond_timeframe === 'month' ? 'selected' : '' ?>>Last 30 days</option>
                                        <option value="overall" <?= $cond_timeframe === 'overall' ? 'selected' : '' ?>>Overall</option>
                                    </select>
                                    <label style="font-weight:600; margin-left:8px;">Reason:</label>
                                    <select id="cond_reason" name="cond_reason" style="padding:8px; min-width:220px;" onchange="filterConditionVisitors()">
                                        <option value="">-- Select reason --</option>
                                        <?php foreach ($distinctReasons as $r): ?>
                                            <option value="<?= htmlspecialchars($r) ?>" <?= $cond_reason === $r ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label style="font-weight:600; margin-left:8px;">Course:</label>
                                    <select id="cond_course" name="cond_course" style="padding:8px; min-width:220px;" onchange="filterConditionVisitors()">
                                        <option value="">All Courses</option>
                                        <?php foreach ($distinctCourses as $c): ?>
                                            <option value="<?= htmlspecialchars($c) ?>" <?= $cond_course === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label style="font-weight:600; margin-left:8px;">Type:</label>
                                    <select id="cond_type" name="cond_type" style="padding:8px; min-width:160px;" onchange="filterConditionVisitors()">
                                        <option value="">All Types</option>
                                        <?php foreach ($distinctTypes as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>" <?= $cond_type === $type ? 'selected' : '' ?>><?= ucfirst($type) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <table class="data-table" id="conditionVisitorsTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Course / Department</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($conditionVisitors)): ?>
                                        <?php foreach ($conditionVisitors as $visitor): ?>
                                        <tr data-time="<?= htmlspecialchars($visitor['time_in'] ?? '') ?>" data-type="<?= htmlspecialchars(strtolower($visitor['visitor_type'] ?? 'student')) ?>" data-course="<?= htmlspecialchars($visitor['course_department'] ?? '') ?>" data-reason="<?= htmlspecialchars($visitor['reason_for_visit'] ?? '') ?>">
                                            <td data-label="Name"><?= htmlspecialchars($visitor['name'] ?? '-') ?></td>
                                            <td data-label="Type"><?= htmlspecialchars(ucfirst($visitor['visitor_type'] ?? 'student')) ?></td>
                                            <td data-label="Course / Department"><?= htmlspecialchars($visitor['course_department'] ?? '-') ?></td>
                                            <td data-label="Reason"><?= htmlspecialchars($visitor['reason_for_visit'] ?? '-') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4">No visitor records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <!-- Condition breakdown by course and names -->
                            <div style="margin-top:18px;">
                                <h3 style="margin:6px 0 10px 0;">Condition — Course Breakdown</h3>
                                <?php if ($cond_reason === ''): ?>
                                    <p style="color:#64748b;">Select a reason above to see per-course counts and visitor names for the selected timeframe.</p>
                                <?php else: ?>
                                    <?php if (empty($conditionCourseBreakdown)): ?>
                                        <p style="color:#64748b;">No visits found for "<?= htmlspecialchars($cond_reason) ?>" in the selected timeframe/course.</p>
                                    <?php else: ?>
                                        <table class="data-table" style="margin-top:8px;">
                                            <thead>
                                                <tr><th>Course</th><th>Cases</th><th>Visitors (names)</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($conditionCourseBreakdown as $course => $info): ?>
                                                    <tr>
                                                        <td data-label="Course"><?= htmlspecialchars($course) ?></td>
                                                        <td data-label="Cases"><?= (int)$info['count'] ?></td>
                                                        <td data-label="Visitors"><?= htmlspecialchars(implode(', ', array_unique($info['names']))) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="analytics-needs" class="analytics-tab-content <?= ($activeAnalyticsTab === 'analytics-needs') ? 'active' : '' ?>">
                        <div class="content-section">
                            <div class="section-header">
                                <h2><i class="fa-solid fa-list-check"></i> Visit Needs Summary</h2>
                            </div>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Need Category</th>
                                        <th>Cases</th>
                                        <th>Percentage</th>
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($analytics['top_needs'])): ?>
                                        <?php foreach ($analytics['top_needs'] as $need): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($need['need']) ?></td>
                                            <td><?= $need['count'] ?></td>
                                            <td><?= number_format($need['percentage'], 1) ?>%</td>
                                            <td>
                                                <span class="trend <?= $need['trend'] > 0 ? 'positive' : 'negative' ?>">
                                                    <i class="fa-solid fa-arrow-<?= $need['trend'] > 0 ? 'up' : 'down' ?>"></i>
                                                    <?= abs($need['trend']) ?>%
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4">No need category data recorded yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile Menu Toggle (small screens)
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sideNav = document.querySelector('.side-nav');
            const pageOverlay = document.getElementById('pageOverlay');

            if (mobileMenuToggle && sideNav && pageOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sideNav.classList.toggle('mobile-open');
                    pageOverlay.classList.toggle('active');
                });

                pageOverlay.addEventListener('click', function() {
                    sideNav.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });

                const navLinks = sideNav.querySelectorAll('a, .nav-toggle');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (!this.classList.contains('nav-toggle')) {
                            sideNav.classList.remove('mobile-open');
                            pageOverlay.classList.remove('active');
                        }
                    });
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sideNav.classList.contains('mobile-open')) {
                        sideNav.classList.remove('mobile-open');
                        pageOverlay.classList.remove('active');
                    }
                });
            }
        });

    </script>
    <script src="../assets/js/app.js" defer></script>
    <script>
        function switchAnalyticsTab(tabId) {
            document.querySelectorAll('.analytics-tab').forEach(function(button) {
                button.classList.toggle('active', button.getAttribute('onclick').includes(tabId));
            });
            document.querySelectorAll('.analytics-tab-content').forEach(function(section) {
                section.classList.toggle('active', section.id === tabId);
            });
        }

        function parseDateTime(value) {
            if (!value) return null;
            value = value.replace(' ', 'T');
            const date = new Date(value);
            return isNaN(date.getTime()) ? null : date;
        }

        function filterConditionVisitors() {
            const timeframe = document.getElementById('cond_timeframe').value;
            const reason = document.getElementById('cond_reason').value.toLowerCase();
            const course = document.getElementById('cond_course').value.toLowerCase();
            const type = document.getElementById('cond_type').value.toLowerCase();
            const rows = document.querySelectorAll('#conditionVisitorsTable tbody tr');
            const now = new Date();
            const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
            const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);

            rows.forEach(function(row) {
                let visible = true;
                const rowTime = parseDateTime(row.dataset.time);

                if (timeframe === 'week' && rowTime) {
                    visible = visible && rowTime >= weekAgo;
                } else if (timeframe === 'month' && rowTime) {
                    visible = visible && rowTime >= monthAgo;
                }

                if (visible && reason) {
                    visible = row.dataset.reason.toLowerCase() === reason;
                }

                if (visible && course) {
                    visible = row.dataset.course.toLowerCase() === course;
                }

                if (visible && type) {
                    visible = row.dataset.type.toLowerCase() === type;
                }

                row.style.display = visible ? '' : 'none';
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            filterConditionVisitors();
        });
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>