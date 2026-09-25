<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = false;

$pdo = get_db();

// Get comprehensive SSAA statistics
$ssaaStats = [];

// Alumni statistics
$alumniStats = $pdo->query("
    SELECT
        COUNT(*) as total_alumni,
        SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) as employed_count,
        SUM(CASE WHEN employment_status = 'Unemployed' THEN 1 ELSE 0 END) as unemployed_count,
        SUM(CASE WHEN employment_status = 'unknown' THEN 1 ELSE 0 END) as unknown_count,
        ROUND(AVG(CASE WHEN graduation_year IS NOT NULL AND graduation_year != '' THEN YEAR(CURDATE()) - graduation_year ELSE NULL END), 1) as avg_years_since_graduation
    FROM ssaa_alumni
")->fetch(PDO::FETCH_ASSOC);

$alumniStats['employment_rate'] = $alumniStats['total_alumni'] > 0 ? round(($alumniStats['employed_count'] / $alumniStats['total_alumni']) * 100, 1) : 0;
$ssaaStats['alumni'] = $alumniStats;

// Student progression statistics
$progressStats = $pdo->query("
    SELECT
        COUNT(*) as total_students,
        SUM(CASE WHEN academic_status = 'passed' THEN 1 ELSE 0 END) as passed_count,
        SUM(CASE WHEN academic_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN academic_status = 'irregular' THEN 1 ELSE 0 END) as irregular_count,
        ROUND((SUM(CASE WHEN academic_status = 'passed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as pass_rate
    FROM ssaa_student_progression
    WHERE academic_year LIKE '%4' OR academic_year LIKE '%4th%'
")->fetch(PDO::FETCH_ASSOC);

$ssaaStats['progression'] = $progressStats;

// Graduation events statistics
$graduationStats = $pdo->query("
    SELECT
        COUNT(*) as total_events,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events,
        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming_events,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_events,
        ROUND(AVG(CASE WHEN graduates_count IS NOT NULL THEN graduates_count ELSE NULL END), 0) as avg_attendance
    FROM ssaa_graduation_events
")->fetch(PDO::FETCH_ASSOC);

$ssaaStats['graduation'] = $graduationStats;

// Employment trends by graduation year
$employmentTrends = $pdo->query("
    SELECT
        graduation_year,
        COUNT(*) as total_graduates,
        SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) as employed_count,
        ROUND((SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as employment_rate
    FROM ssaa_alumni
    WHERE graduation_year IS NOT NULL
    GROUP BY graduation_year
    ORDER BY graduation_year DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Alumni analytics by course and graduation year
$alumniByCourseYear = $pdo->query("
    SELECT
        course,
        graduation_year,
        COUNT(*) as total_alumni,
        SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) as employed_count,
        ROUND((SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as employment_rate
    FROM ssaa_alumni
    WHERE course IS NOT NULL AND course != ''
      AND graduation_year IS NOT NULL AND graduation_year != ''
    GROUP BY course, graduation_year
    ORDER BY course ASC, graduation_year DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

$batchYears = $pdo->query("SELECT DISTINCT graduation_year FROM ssaa_alumni WHERE graduation_year IS NOT NULL AND graduation_year != '' ORDER BY graduation_year DESC")->fetchAll(PDO::FETCH_COLUMN);

$alumniCourseOptions = [
    'Bachelor of Science in Accountancy',
    'Bachelor of Science in Business Administration',
    'Bachelor in Elementary Education',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Criminology',
    'Bachelor of Science in Hospitality Management',
    'Bachelor of Science in Tourism Management',
    'Associate in Computer Technology',
    'Associate in Business Knowledge',
    'Associate in Hotel Management',
    'Associate in Tourism Management',
];

$currentPage = basename($_SERVER['PHP_SELF']);

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
// Alumni Reports export (Alumni Reports tab).
// ---------------------------------------------------------------------
if (isset($_POST['export_alumni_report'])) {
    $reportType = $_POST['report_type'] ?? 'master_directory';
    $exportFormat = $_POST['export_format'] ?? 'excel';
    $graduationYearFilter = $_POST['graduation_year'] ?? 'all';
    $courseFilter = $_POST['course'] ?? 'all';
    $employmentStatusFilter = $_POST['employment_status'] ?? 'all';
    $headers = [];
    $rows = [];

    $whereClauses = ['1=1'];
    $params = [];

    if ($graduationYearFilter !== 'all' && $graduationYearFilter !== '') {
        $whereClauses[] = 'graduation_year = ?';
        $params[] = $graduationYearFilter;
    }

    if ($courseFilter !== 'all' && $courseFilter !== '') {
        $whereClauses[] = 'course = ?';
        $params[] = $courseFilter;
    }

    if ($employmentStatusFilter !== 'all' && $employmentStatusFilter !== '') {
        $whereClauses[] = 'LOWER(COALESCE(employment_status, "")) = LOWER(?)';
        $params[] = strtolower($employmentStatusFilter);
    }

    $alumniQueryBase = ' FROM ssaa_alumni WHERE ' . implode(' AND ', $whereClauses);

    if ($reportType === 'master_directory') {
        $headers = ['ID', 'Full Name', 'Course', 'Graduation Year', 'Personal Email', 'Contact Number', 'Employment Status', 'Company Name', 'Job Title', 'Location'];
        $stmt = $pdo->prepare("SELECT id, full_name, course, graduation_year, personal_email, contact_number, employment_status, company_name, job_title, location" . $alumniQueryBase . " ORDER BY full_name ASC");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $alumni) {
            $rows[] = [
                $alumni['id'],
                $alumni['full_name'],
                $alumni['course'],
                $alumni['graduation_year'],
                $alumni['personal_email'],
                $alumni['contact_number'],
                $alumni['employment_status'],
                $alumni['company_name'],
                $alumni['job_title'],
                $alumni['location'],
            ];
        }
    } elseif ($reportType === 'employment_status') {
        $headers = ['Employment Status', 'Count', 'Percentage'];
        $whereClause = implode(' AND ', $whereClauses);
        $countSql = "SELECT employment_status, COUNT(*) AS total FROM ssaa_alumni WHERE " . $whereClause . " AND employment_status IS NOT NULL AND employment_status != '' GROUP BY employment_status ORDER BY CASE LOWER(employment_status) WHEN 'employed' THEN 1 WHEN 'self-employed' THEN 2 WHEN 'unemployed' THEN 3 ELSE 4 END, employment_status ASC";
        $statusStmt = $pdo->prepare($countSql);
        $statusStmt->execute($params);

        $totalAlumni = 0;
        $statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($statusRows as $statusRow) {
            $totalAlumni += (int) $statusRow['total'];
        }

        foreach ($statusRows as $statusRow) {
            $count = (int) $statusRow['total'];
            $percent = $totalAlumni > 0 ? round(($count / $totalAlumni) * 100, 1) : 0;
            $rows[] = [$statusRow['employment_status'], $count, $percent . '%'];
        }
    } elseif ($reportType === 'gts_summary') {
        $headers = ['Metric', 'Value'];
        $responseSql = "SELECT sar.response_data, sa.graduation_year, sa.course, sa.employment_status FROM ssaa_alumni_survey_responses sar JOIN ssaa_alumni sa ON sa.id = sar.alumni_id WHERE 1=1";
        $responseParams = [];

        if ($graduationYearFilter !== 'all' && $graduationYearFilter !== '') {
            $responseSql .= ' AND sa.graduation_year = ?';
            $responseParams[] = $graduationYearFilter;
        }

        if ($courseFilter !== 'all' && $courseFilter !== '') {
            $responseSql .= ' AND sa.course = ?';
            $responseParams[] = $courseFilter;
        }

        if ($employmentStatusFilter !== 'all' && $employmentStatusFilter !== '') {
            $responseSql .= ' AND LOWER(COALESCE(sa.employment_status, "")) = LOWER(?)';
            $responseParams[] = strtolower($employmentStatusFilter);
        }

        $responseStmt = $pdo->prepare($responseSql);
        $responseStmt->execute($responseParams);
        $responses = $responseStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalResponses = count($responses);
        $statusSummary = ['Employed' => 0, 'Unemployed' => 0, 'Self-Employed' => 0, 'Unknown' => 0];
        $curriculumFeedbackCount = 0;
        $sampleCurriculumFeedback = [];

        foreach ($responses as $responseData) {
            $data = json_decode($responseData['response_data'], true);
            if (!is_array($data)) {
                continue;
            }

            $status = trim((string) ($data['employment_status'] ?? $responseData['employment_status'] ?? 'Unknown'));
            if (isset($statusSummary[$status])) {
                $statusSummary[$status]++;
            } else {
                $statusSummary['Unknown']++;
            }

            foreach ($data as $key => $value) {
                $normalizedKey = strtolower((string) $key);
                $normalizedValue = strtolower((string) $value);
                if ((strpos($normalizedKey, 'curriculum') !== false || strpos($normalizedKey, 'feedback') !== false || strpos($normalizedKey, 'satisfaction') !== false || strpos($normalizedValue, 'curriculum') !== false || strpos($normalizedValue, 'feedback') !== false || strpos($normalizedValue, 'satisfaction') !== false) && !empty($value)) {
                    $curriculumFeedbackCount++;
                    if (count($sampleCurriculumFeedback) < 3) {
                        $sampleCurriculumFeedback[] = is_array($value) ? json_encode($value) : (string) $value;
                    }
                }
            }
        }

        $rows = [
            ['Total Survey Responses', $totalResponses],
            ['Employed Responses', $statusSummary['Employed']],
            ['Unemployed Responses', $statusSummary['Unemployed']],
            ['Self-Employed Responses', $statusSummary['Self-Employed']],
            ['Curriculum Feedback Entries', $curriculumFeedbackCount],
            ['Sample Curriculum Feedback', implode(' | ', $sampleCurriculumFeedback) ?: 'No curriculum feedback available'],
        ];
    } else {
        $reportType = 'master_directory';
        $headers = ['ID', 'Full Name', 'Course', 'Graduation Year', 'Personal Email', 'Contact Number', 'Employment Status', 'Company Name', 'Job Title', 'Location'];
        $stmt = $pdo->prepare("SELECT id, full_name, course, graduation_year, personal_email, contact_number, employment_status, company_name, job_title, location" . $alumniQueryBase . " ORDER BY full_name ASC");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $alumni) {
            $rows[] = [
                $alumni['id'],
                $alumni['full_name'],
                $alumni['course'],
                $alumni['graduation_year'],
                $alumni['personal_email'],
                $alumni['contact_number'],
                $alumni['employment_status'],
                $alumni['company_name'],
                $alumni['job_title'],
                $alumni['location'],
            ];
        }
    }

    if ($reportType === 'master_directory') {
        $reportTitle = 'Alumni Master Directory';
    } elseif ($reportType === 'employment_status') {
        $reportTitle = 'Employment Status Report';
    } elseif ($reportType === 'gts_summary') {
        $reportTitle = 'Graduate Tracer Survey (GTS) Summary';
    } else {
        $reportTitle = 'Alumni Master Directory';
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
            export_merge_into_docx($tmpPath, $reportTitle, $headers, $rows, "alumni_{$reportType}_report.docx");
        } else {
            if ($ext !== 'xlsx') {
                http_response_code(400);
                exit('Ang letterhead template para sa Excel export ay dapat .xlsx file.');
            }
            export_merge_into_xlsx($tmpPath, $reportTitle, $headers, $rows, "alumni_{$reportType}_report.xlsx");
        }
        exit;
    }

    // No letterhead uploaded: export raw data only.
    if ($exportFormat === 'docs') {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        $filename = "alumni_{$reportType}_report.doc";
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
        $filename = "alumni_{$reportType}_report.csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\r\n";
        foreach ($rows as $row) {
            echo implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', (string) $cell) . '"', $row)) . "\r\n";
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | SSAA Management | PASS Support System</title>
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
        .topbar-divider { width: 1px; height: 24px; background: #e9ecef; margin: 0 12px; }
        .mic-button { background: none; border: none; cursor: pointer; color: #666; padding: 0 8px; }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }

        .overview-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .overview-card h3 {
            color: #2c3e50;
            margin-bottom: 16px;
            font-size: 1.2rem;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .metric-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #800000;
            display: block;
        }

        .metric-label {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .report-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }

        .action-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .action-card:hover {
            transform: translateY(-2px);
        }

        .action-icon {
            font-size: 3rem;
            color: #800000;
            margin-bottom: 16px;
        }

        .action-button {
            background: #800000;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 12px;
        }

        .action-button:hover {
            background: #660000;
        }

        .chart-subtitle {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .chart-filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 18px 0 12px;
        }

        .chart-filter-row label {
            color: #374151;
            font-weight: 600;
        }

        .chart-filter-row input {
            flex: 1;
            min-width: 200px;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            color: #111827;
        }

        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin: 24px 0;
        }

        .chart-container canvas {
            width: 100% !important;
            max-width: 100%;
            min-height: 240px;
            max-height: 360px;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-placeholder {
            height: 200px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .trend-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .trend-table th,
        .trend-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .trend-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .employment-rate {
            color: #27ae60;
            font-weight: 600;
        }

        /* Expandable Navigation Styles */

        .report-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 24px 0 16px;
        }

        .report-tab {
            padding: 12px 22px;
            border-radius: 999px;
            border: 1px solid #dee2e6;
            background: white;
            color: #3b4255;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
        }

        .report-tab.active {
            background: #c49b34;
            color: white;
            border-color: #c49b34;
        }

        .report-tab:hover {
            background: #f2f4f7;
        }

        .report-tab-panel {
            display: none;
        }

        .report-tab-panel.active {
            display: block;
        }

        .custom-report-form {
            padding: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.95rem;
            color: #374151;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #ffffff;
            color: #1f2937;
        }

        .form-actions {
            align-self: flex-end;
            display: flex;
            justify-content: flex-end;
        }

        [data-tooltip] {
            position: relative;
        }

        .side-nav.collapsed [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: #333;
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-left: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .side-nav.collapsed [data-tooltip]:hover::before {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-right-color: #333;
            margin-left: 2px;
            z-index: 1000;
        }
        .content-panel {
            background: transparent !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 0 !important;
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

        .case-header__art .art-cap {
            top: 18%;
            left: 40%;
            padding: 14px 15px;
            border: 2px solid rgba(215, 165, 141, .7);
            border-radius: 9px;
            background: rgba(248, 241, 231, .7);
            font-size: 30px;
        }

        .case-header__art .art-briefcase {
            top: 38%;
            left: 50%;
            padding: 15px 17px;
            border: 2px solid rgba(215, 165, 141, .7);
            border-radius: 9px;
            font-size: 28px;
        }

        .case-header__art .art-user {
            top: 58%;
            left: 20%;
            padding: 12px 14px;
            border: 2px solid rgba(215, 165, 141, .7);
            border-radius: 50%;
            font-size: 20px;
        }

        .case-header__art .art-check {
            top: 25%;
            right: 7%;
            padding: 12px;
            border: 2px solid #e3b968;
            border-radius: 50%;
            color: #e3b968;
            font-size: 21px;
        }

        .case-header__art .art-pin {
            bottom: 12%;
            left: 44%;
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

        .case-header__art .art-star {
            right: 22%;
            bottom: 12%;
            color: #d7a58d;
            font-size: 28px;
        }

        /* Mobile adjustments for main content (720px -> 320px) */
        @media (max-width: 768px) {
            .content-panel { padding: 12px; }

            .stats-overview { gap: 12px; }

            .overview-card { padding: 16px; }
            .overview-card h3 { font-size: 1rem; }

            .metric-grid { grid-template-columns: 1fr; gap: 10px; }
            .metric-item { padding: 10px; }
            .metric-value { font-size: 1.25rem; }

            .report-tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
            .report-tab { flex: 0 0 auto; padding: 10px 16px; margin-right: 8px; }

            .chart-container { padding: 16px; margin: 16px 0; }
            .chart-container canvas { min-height: 180px; max-height: 320px; }

            .chart-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            .chart-actions { display: flex; gap: 8px; width: 100%; }
            .chart-actions .action-button { flex: 1; }

            .chart-filter-row { flex-direction: column; align-items: stretch; }
            .chart-filter-row label { margin-bottom: 6px; }
            .chart-filter-row input { min-width: 0; width: 100%; }

            /* Trend tables -> card rows */
            .trend-table, .trend-table thead, .trend-table tbody, .trend-table th, .trend-table td, .trend-table tr {
                display: block;
                width: 100%;
            }
            .trend-table thead { display: none; }
            .trend-table tbody tr { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 12px; padding: 10px; box-shadow: 0 6px 18px rgba(15,23,42,0.04); }
            .trend-table td { display: grid; grid-template-columns: 45% 55%; padding: 8px 10px; border: none; border-bottom: 1px solid #f3f4f6; }
            .trend-table td:last-child { border-bottom: none; }
            .trend-table td::before { display: block; margin-bottom: 6px; color: #6b7280; font-weight: 700; }
            .trend-table td:nth-child(1)::before { content: attr(data-label) ""; }

            /* Custom report form spacing */
            .custom-report-form .form-grid { grid-template-columns: 1fr; }
            .form-actions { justify-content: stretch; }
            .form-actions .action-button { width: 100%; }

            /* Make all report card containers full bleed on mobile */
            .report-tab-panel .chart-container {
                position: relative;
                left: 50%;
                right: 50%;
                width: 100vw;
                max-width: 100vw;
                margin-left: -50vw;
                margin-right: -50vw;
                padding-left: 18px;
                padding-right: 18px;
                box-sizing: border-box;
                border-radius: 0;
            }

            .report-tab-panel .chart-header,
            .report-tab-panel .chart-filter-row,
            .report-tab-panel .chart-actions {
                width: 100%;
            }

            .report-tab-panel .chart-actions .action-button {
                flex: 1;
                min-width: 0;
            }
        }

        @keyframes spin-refresh {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                overscroll-behavior-y: none;
            }
        }

        @media (max-width: 425px) {
            .content-panel { padding: 8px; }
            .overview-card { padding: 12px; }
            .chart-container { padding: 12px; }
            .report-tab { padding: 8px 12px; }
            .report-tab-panel .chart-container {
                position: relative;
                left: 50%;
                right: 50%;
                width: 100vw;
                max-width: 100vw;
                margin-left: -50vw;
                margin-right: -50vw;
                padding-left: 14px;
                padding-right: 14px;
                box-sizing: border-box;
                border-radius: 0;
            }
        }
    </style>
</head>
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; inset: 0; background: rgba(255, 255, 255, 0.92); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0; text-align: center;">Refreshing...</p></div></div>
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
                <a href="admin_analytics.php" data-tooltip="Analytics">
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
                        <a href="reports_analytics.php" class="active" data-tooltip="Reports & Analytics">
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
                <div class="content-panel">
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <p class="eyebrow">SSAA Management - Reports & Analytics</p>
                            <h1>Reports & Analytics</h1>
                            <p class="dashboard-subtitle">SSAA-focused analytics for alumni outcomes, student progression, graduation events, and career readiness across the support system.</p>
                        </div>
                        <div class="case-header__art" aria-hidden="true">
                            <i class="fa-solid fa-graduation-cap art-cap"></i>
                            <i class="fa-solid fa-briefcase art-briefcase"></i>
                            <i class="fa-solid fa-user art-user"></i>
                            <i class="fa-solid fa-check art-check"></i>
                            <i class="fa-solid fa-location-dot art-pin"></i>
                            <i class="fa-solid fa-star art-star"></i>
                        </div>
                    </section>

                    <!-- Statistics Overview -->
                    <div class="stats-overview">
                        <div class="overview-card">
                            <h3><i class="fa-solid fa-users"></i> Alumni Overview</h3>
                            <div class="metric-grid">
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['alumni']['total_alumni'] ?? 0 ?></span>
                                    <span class="metric-label">Total Alumni</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['alumni']['employed_count'] ?? 0 ?></span>
                                    <span class="metric-label">Employed</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['alumni']['unemployed_count'] ?? 0 ?></span>
                                    <span class="metric-label">Unemployed</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['alumni']['avg_years_since_graduation'] ?? 0 ?> yrs</span>
                                    <span class="metric-label">Avg Time Since Grad</span>
                                </div>
                            </div>
                        </div>

                        <div class="overview-card">
                            <h3><i class="fa-solid fa-graduation-cap"></i> Student Progression</h3>
                            <div class="metric-grid">
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['progression']['total_students'] ?? 0 ?></span>
                                    <span class="metric-label">Total Students</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['progression']['passed_count'] ?? 0 ?></span>
                                    <span class="metric-label">Passed</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['progression']['pass_rate'] ?? 0 ?>%</span>
                                    <span class="metric-label">Pass Rate</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['progression']['failed_count'] ?? 0 ?></span>
                                    <span class="metric-label">Failed</span>
                                </div>
                            </div>
                        </div>

                        <div class="overview-card">
                            <h3><i class="fa-solid fa-calendar"></i> Graduation Events</h3>
                            <div class="metric-grid">
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['graduation']['total_events'] ?? 0 ?></span>
                                    <span class="metric-label">Total Events</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['graduation']['completed_events'] ?? 0 ?></span>
                                    <span class="metric-label">Completed</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['graduation']['upcoming_events'] ?? 0 ?></span>
                                    <span class="metric-label">Upcoming</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-value"><?= $ssaaStats['graduation']['avg_attendance'] ?? 0 ?></span>
                                    <span class="metric-label">Avg Attendance</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Report Tabs -->
                    <div class="report-tabs" role="tablist" aria-label="Report categories">
                        <button id="tab-alumni-button" class="report-tab active" type="button" data-target="tab-alumni" role="tab" aria-controls="tab-alumni" aria-selected="true">Alumni Reports</button>
                        <button id="tab-progression-button" class="report-tab" type="button" data-target="tab-progression" role="tab" aria-controls="tab-progression" aria-selected="false">Progression Reports</button>
                        <button id="tab-events-button" class="report-tab" type="button" data-target="tab-events" role="tab" aria-controls="tab-events" aria-selected="false">Event Reports</button>
                        <button id="tab-custom-button" class="report-tab" type="button" data-target="tab-custom" role="tab" aria-controls="tab-custom" aria-selected="false">Custom Reports</button>
                    </div>

                    <div class="report-tab-panels">
                        <section id="tab-alumni" class="report-tab-panel active" role="tabpanel" aria-labelledby="tab-alumni-button">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <div>
                                        <h3><i class="fa-solid fa-file-export"></i> Export Alumni Reports</h3>
                                        <p class="chart-subtitle">I-export ang alumni data, kasama na ang opsyonal na letterhead template.</p>
                                    </div>
                                </div>
                                <form method="post" enctype="multipart/form-data" style="width: 100%;">
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
                                                <?php foreach ($batchYears as $year): ?>
                                                    <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="alumni_course" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Course</label>
                                            <select name="course" id="alumni_course" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                                <option value="all">All</option>
                                                <?php foreach ($alumniCourseOptions as $course): ?>
                                                    <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                                <?php endforeach; ?>
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
                                <script>
                                    (function () {
                                        var formatSelect = document.getElementById('alumni_export_format');
                                        var fileInput = document.getElementById('alumni_letterhead_template');
                                        if (!formatSelect || !fileInput) return;

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
                                    })();
                                </script>
                            </div>

                            <div class="chart-container">
                                <div class="chart-header">
                                    <div>
                                        <h3><i class="fa-solid fa-chart-line"></i> Employment Trends by Graduation Year</h3>
                                        <p class="chart-subtitle">Zoom and pan the timeline to inspect longer graduation year ranges.</p>
                                    </div>
                                    <div class="chart-actions">
                                        <button class="action-button" onclick="resetEmploymentTrendZoom()">Reset Zoom</button>
                                        <button class="action-button" onclick="exportChart()">Export Data</button>
                                    </div>
                                </div>
                                <div class="chart-filter-row">
                                    <label for="employment-trend-search">Filter by course or year:</label>
                                    <input id="employment-trend-search" type="search" placeholder="Type course or year" oninput="filterCourseYearTable()" />
                                </div>
                                <canvas id="employmentTrendChart" height="220"></canvas>
                                <table class="trend-table" id="employment-trend-table">
                                    <thead>
                                        <tr>
                                            <th>Graduation Year</th>
                                            <th>Total Graduates</th>
                                            <th>Employed</th>
                                            <th>Employment Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employmentTrends as $trend): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($trend['graduation_year']) ?></td>
                                                <td><?= htmlspecialchars($trend['total_graduates']) ?></td>
                                                <td><?= htmlspecialchars($trend['employed_count']) ?></td>
                                                <td class="employment-rate"><?= htmlspecialchars($trend['employment_rate']) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3><i class="fa-solid fa-school"></i> Alumni Analytics by Course and Year</h3>
                                    <button class="action-button" onclick="showNotification('Course/year analytics export coming soon', 'info')">Export Data</button>
                                </div>
                                <div class="chart-filter-row">
                                    <label for="alumni-course-year-search">Filter course/year:</label>
                                    <input id="alumni-course-year-search" type="search" placeholder="Type course or year" oninput="filterAlumniCourseYearTable()" />
                                </div>
                                <table class="trend-table" id="alumni-course-year-table">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Graduation Year</th>
                                            <th>Total Alumni</th>
                                            <th>Employed</th>
                                            <th>Employment Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alumniByCourseYear as $courseYear): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($courseYear['course']) ?></td>
                                                <td><?= htmlspecialchars($courseYear['graduation_year']) ?></td>
                                                <td><?= htmlspecialchars($courseYear['total_alumni']) ?></td>
                                                <td><?= htmlspecialchars($courseYear['employed_count']) ?></td>
                                                <td class="employment-rate"><?= htmlspecialchars($courseYear['employment_rate']) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        </section>

                        <section id="tab-progression" class="report-tab-panel" role="tabpanel" aria-labelledby="tab-progression-button">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3><i class="fa-solid fa-chart-line"></i> Progression Metrics</h3>
                                    <button class="action-button" onclick="exportProgressionData()">Export Data</button>
                                </div>
                                <div class="chart-placeholder">
                                    <i class="fa-solid fa-chart-line" style="font-size: 3rem; color: #dee2e6; margin-bottom: 16px;"></i>
                                    <p>Progression analytics and pass rate chart will be displayed here.</p>
                                </div>
                                <table class="trend-table">
                                    <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Students</td><td><?= htmlspecialchars($ssaaStats['progression']['total_students'] ?? 0) ?></td></tr>
                                        <tr><td>Passed</td><td><?= htmlspecialchars($ssaaStats['progression']['passed_count'] ?? 0) ?></td></tr>
                                        <tr><td>Failed</td><td><?= htmlspecialchars($ssaaStats['progression']['failed_count'] ?? 0) ?></td></tr>
                                        <tr><td>Pass Rate</td><td><?= htmlspecialchars($ssaaStats['progression']['pass_rate'] ?? 0) ?>%</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section id="tab-events" class="report-tab-panel" role="tabpanel" aria-labelledby="tab-events-button">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3><i class="fa-solid fa-calendar-check"></i> Graduation Event Analytics</h3>
                                    <button class="action-button" onclick="exportEventData()">Export Data</button>
                                </div>
                                <div class="chart-placeholder">
                                    <i class="fa-solid fa-calendar-check" style="font-size: 3rem; color: #dee2e6; margin-bottom: 16px;"></i>
                                    <p>Graduation event analytics will be displayed here.</p>
                                </div>
                                <table class="trend-table">
                                    <thead>
                                        <tr>
                                            <th>Metric</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Events</td><td><?= htmlspecialchars($ssaaStats['graduation']['total_events'] ?? 0) ?></td></tr>
                                        <tr><td>Completed</td><td><?= htmlspecialchars($ssaaStats['graduation']['completed_events'] ?? 0) ?></td></tr>
                                        <tr><td>Upcoming</td><td><?= htmlspecialchars($ssaaStats['graduation']['upcoming_events'] ?? 0) ?></td></tr>
                                        <tr><td>Cancelled</td><td><?= htmlspecialchars($ssaaStats['graduation']['cancelled_events'] ?? 0) ?></td></tr>
                                        <tr><td>Average Attendance</td><td><?= htmlspecialchars($ssaaStats['graduation']['avg_attendance'] ?? 0) ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section id="tab-custom" class="report-tab-panel" role="tabpanel" aria-labelledby="tab-custom-button">
                            <div class="chart-container">
                                <div class="chart-header">
                                    <h3><i class="fa-solid fa-file-export"></i> Custom Report Builder</h3>
                                </div>
                                <div class="custom-report-form">
                                    <p>Select filters and report type to build a custom SSAA report.</p>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="custom-report-type">Report Type</label>
                                            <select id="custom-report-type">
                                                <option value="alumni">Alumni Outcomes</option>
                                                <option value="progression">Student Progression</option>
                                                <option value="events">Graduation Events</option>
                                                <option value="career">Employment Analytics</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="custom-report-year">Graduation Year</label>
                                            <input id="custom-report-year" type="text" placeholder="e.g. 2023" />
                                        </div>
                                        <div class="form-group">
                                            <label for="custom-report-status">Status / Industry</label>
                                            <input id="custom-report-status" type="text" placeholder="e.g. Employed, Technology" />
                                        </div>
                                        <div class="form-group form-actions">
                                            <button class="action-button" type="button" onclick="showNotification('Custom report generation is coming soon', 'info')">Generate Report</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                </div>
            </div>
        </main>
    </div>

    <div id="notification-container" class="notification-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-zoom/dist/chartjs-plugin-zoom.min.js"></script>
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
    });
    </script>
    <script src="../assets/js/app.js" defer></script>
    <script>window.addEventListener('DOMContentLoaded', function(){ window.dispatchEvent(new Event('resize')); });</script>
    <script>
        const employmentTrendData = <?= json_encode(array_map(function($trend) {
            return [
                'year' => $trend['graduation_year'],
                'total_graduates' => (int)$trend['total_graduates'],
                'employed_count' => (int)$trend['employed_count'],
                'employment_rate' => (float)$trend['employment_rate']
            ];
        }, $employmentTrends), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        let employmentTrendChart;

        function buildEmploymentTrendChart() {
            if (typeof ChartZoom !== 'undefined' && Chart && Chart.register) {
                Chart.register(ChartZoom);
            }

            const ctx = document.getElementById('employmentTrendChart').getContext('2d');
            const labels = employmentTrendData.map(item => item.year);
            const employed = employmentTrendData.map(item => item.employed_count);
            const rates = employmentTrendData.map(item => item.employment_rate);

            employmentTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Employment Rate (%)',
                            data: rates,
                            borderColor: '#800000',
                            backgroundColor: 'rgba(128, 0, 0, 0.2)',
                            yAxisID: 'y',
                            tension: 0.3,
                            pointRadius: 4,
                            fill: true
                        },
                        {
                            label: 'Total Graduates',
                            data: employed,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.2)',
                            yAxisID: 'y1',
                            tension: 0.3,
                            pointRadius: 3,
                            borderDash: [5, 5]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    stacked: false,
                    scales: {
                        y: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Employment Rate (%)'
                            },
                            ticks: {
                                callback: value => `${value}%`
                            },
                            beginAtZero: true,
                            max: 100
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Total Graduates'
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        zoom: {
                            pan: {
                                enabled: true,
                                mode: 'x'
                            },
                            zoom: {
                                wheel: {
                                    enabled: true
                                },
                                pinch: {
                                    enabled: true
                                },
                                mode: 'x'
                            }
                        }
                    }
                }
            });
        }

        function resetEmploymentTrendZoom() {
            if (employmentTrendChart) {
                employmentTrendChart.resetZoom();
            }
        }

        function filterCourseYearTable() {
            const search = document.getElementById('employment-trend-search').value.toLowerCase();
            const rows = document.querySelectorAll('#employment-trend-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        }

        function filterAlumniCourseYearTable() {
            const search = document.getElementById('alumni-course-year-search').value.toLowerCase();
            const rows = document.querySelectorAll('#alumni-course-year-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        }

        function activateReportTab(tabId) {
            const tabs = document.querySelectorAll('.report-tab');
            const panels = document.querySelectorAll('.report-tab-panel');

            if (!tabId) {
                return;
            }

            tabs.forEach(tab => {
                const isActive = tab.dataset.target === tabId;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive.toString());
            });

            panels.forEach(panel => {
                panel.classList.toggle('active', panel.id === tabId);
            });
        }

        function initReportTabs() {
            const reportTabs = document.querySelectorAll('.report-tab');
            if (!reportTabs.length) {
                return;
            }

            reportTabs.forEach(tab => {
                tab.addEventListener('click', event => {
                    event.preventDefault();
                    activateReportTab(tab.dataset.target);
                });
            });
        }

        function initializeReportsPage() {
            buildEmploymentTrendChart();
            initReportTabs();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeReportsPage);
        } else {
            initializeReportsPage();
        }

        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        function exportChart() {
            showNotification('Employment trends export coming soon', 'info');
        }

        function exportIndustryData() {
            showNotification('Industry distribution export coming soon', 'info');
        }

        function exportProgressionData() {
            showNotification('Progression analytics export coming soon', 'info');
        }

        function exportEventData() {
            showNotification('Graduation event export coming soon', 'info');
        }

    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const swipeRefreshSpinner = document.getElementById('swipe-refresh-spinner');
            if (!swipeRefreshSpinner || window.innerWidth > 768) {
                return;
            }

            let touchStartY = 0;
            let isPulling = false;

            document.addEventListener('touchstart', function (event) {
                if (window.scrollY > 0 || event.touches.length !== 1) {
                    return;
                }
                touchStartY = event.touches[0].clientY;
                isPulling = true;
            }, { passive: true });

            document.addEventListener('touchmove', function (event) {
                if (!isPulling) {
                    return;
                }

                const deltaY = event.touches[0].clientY - touchStartY;
                if (deltaY > 90) {
                    swipeRefreshSpinner.style.display = 'flex';
                    setTimeout(function () {
                        window.location.reload();
                    }, 450);
                    isPulling = false;
                }
            }, { passive: true });

            document.addEventListener('touchend', function () {
                isPulling = false;
            }, { passive: true });
        });
    </script>
</body>
</html>