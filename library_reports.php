<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/template_merger.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin' && ($user['role'] !== 'teacher' || $user['head_service'] !== 'library')) {
    header('Location: /auth/teacher_login.php');
    exit;
}
ensure_library_schema();
$todayCheckins = get_today_library_checkins();
$activeReservations = get_active_library_reservations();
$pendingApprovalsData = get_pending_resource_approvals();
$pendingApprovals = count($pendingApprovalsData);
$overdueItems = get_overdue_library_items();
$calendarBlocks = get_library_calendar_blocks(6);

// Get additional report data
$pdo = get_db();
$weeklyStats = $pdo->query("
    SELECT 
        DATE(time_in) as date,
        COUNT(*) as checkins
    FROM library_visits 
    WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(time_in)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

$monthlyBorrowing = $pdo->query("
    SELECT 
        DATE(borrow_date) as month,
        COUNT(*) as borrows
    FROM library_borrows 
    WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(borrow_date, '%Y-%m')
    ORDER BY month
")->fetchAll(PDO::FETCH_ASSOC);

$topBooks = $pdo->query("
    SELECT 
        b.title,
        COUNT(lb.id) as borrow_count
    FROM library_books b
    JOIN library_borrows lb ON b.id = lb.book_id
    WHERE lb.borrow_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY b.id, b.title
    ORDER BY borrow_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$departmentUsage = $pdo->query("
    SELECT 
        u.course_year as department,
        COUNT(lb.id) as borrow_count
    FROM users u
    JOIN library_borrows lb ON u.id = lb.user_id
    WHERE lb.borrow_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY u.course_year
    ORDER BY borrow_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$bookCategories = $pdo->query("
    SELECT 
        category,
        COUNT(*) as total_books,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available_books
    FROM library_books
    GROUP BY category
    ORDER BY total_books DESC
")->fetchAll(PDO::FETCH_ASSOC);

$fineStats = $pdo->query("
    SELECT 
        SUM(CASE WHEN paid = 1 THEN amount ELSE 0 END) as collected_fines,
        SUM(CASE WHEN paid = 0 THEN amount ELSE 0 END) as outstanding_fines,
        COUNT(CASE WHEN paid = 0 THEN 1 END) as unpaid_fines_count
    FROM library_fines
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
")->fetch(PDO::FETCH_ASSOC);

$fineReportRows = $pdo->query("
    SELECT 
        u.full_name AS name,
        u.course_year AS course_year,
        SUM(lf.amount) AS total_fines,
        CASE WHEN SUM(CASE WHEN lf.paid = 0 THEN 1 ELSE 0 END) > 0 THEN 'UNPAID' ELSE 'PAID' END AS status
    FROM library_fines lf
    JOIN users u ON lf.user_id = u.id
    GROUP BY u.id, u.full_name, u.course_year
    ORDER BY u.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Get check-ins data for export
$checkinsReport = $pdo->query("
    SELECT 
        u.full_name AS name,
        u.course_year AS course_year,
        lv.time_in,
        lv.time_out,
        TIMESTAMPDIFF(MINUTE, lv.time_in, COALESCE(lv.time_out, NOW())) as duration_minutes,
        lv.purpose
    FROM library_visits lv
    LEFT JOIN users u ON lv.user_id = u.id
    WHERE lv.time_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY lv.time_in DESC
")->fetchAll(PDO::FETCH_ASSOC);

$recentActivityReport = $pdo->query("
    SELECT 
        u.full_name AS name,
        u.course_year AS course_year,
        lv.time_in,
        lv.time_out,
        TIMESTAMPDIFF(MINUTE, lv.time_in, COALESCE(lv.time_out, NOW())) as duration_minutes,
        lv.purpose,
        lv.status
    FROM library_visits lv
    LEFT JOIN users u ON lv.user_id = u.id
    ORDER BY lv.time_in DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$inventoryAssetReport = $pdo->query("
    SELECT 
        b.title,
        b.author,
        b.category,
        b.total_copies,
        b.available_copies,
        b.status
    FROM library_books b
    ORDER BY b.title ASC
")->fetchAll(PDO::FETCH_ASSOC);

$borrowHistoryReport = $pdo->query("
    SELECT 
        u.full_name AS name,
        u.course_year AS course_year,
        lb.title,
        lb.author,
        b.borrow_date,
        b.due_date,
        b.returned_at,
        b.status
    FROM library_borrows b
    JOIN users u ON b.user_id = u.id
    JOIN library_books lb ON b.book_id = lb.id
    ORDER BY b.borrow_date DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$resourceUsageReport = $pdo->query("
    SELECT 
        title,
        category,
        course_year,
        download_count,
        created_at
    FROM library_resources
    WHERE is_approved = 1
    ORDER BY download_count DESC, created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['export_fines']) && $_GET['export_fines'] === '1') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="library_fines_report.csv"');
    echo "Name,Course Year,Total Fines,Status\r\n";
    foreach ($fineReportRows as $row) {
        $name = str_replace('"', '""', $row['name']);
        $course = str_replace('"', '""', $row['course_year']);
        $total = number_format((float)$row['total_fines'], 2, '.', '');
        $status = $row['status'];
        echo '"' . $name . '","' . $course . '","' . $total . '","' . $status . '"\r\n';
    }
    exit;
}

// ---------------------------------------------------------------------
// Export helpers
// ---------------------------------------------------------------------
// A "block" describes one piece of a report in a format-agnostic way:
//   ['title' => string|null, 'headers' => array|null, 'rows' => array<array>]
// The same $blocks array is used to render CSV, the plain HTML-based
// ".doc", or to be merged into an uploaded .docx / .xlsx letterhead file.

function build_library_export_blocks($reportType, $filteredFineRows, $topBooks, $recentActivityReport, $inventoryAssetReport, $borrowHistoryReport, $selectedCourseLabel, $resourceUsageReport = []) {
    $blocks = [];
    $reportKey = match ($reportType) {
        'visitor_log_analytics', 'visitor_log' => 'visitor_log',
        'book_inventory_asset_report', 'inventory' => 'inventory',
        'borrow_return_circulation_history', 'circulation' => 'circulation',
        'fines_penalties_collection_report', 'fines' => 'fines',
        'e_resources_utilization_report', 'resources' => 'resources',
        'books', 'checkins', 'others', 'resources' => $reportType,
        default => 'fines',
    };

    if ($reportKey === 'fines') {
        $blocks[] = ['title' => 'Course Group: ' . $selectedCourseLabel, 'headers' => null, 'rows' => []];
        $rows = [];
        foreach ($filteredFineRows as $row) {
            $rows[] = [
                $row['name'],
                $row['course_year'],
                '₱' . number_format((float) $row['total_fines'], 2),
                $row['status'],
            ];
        }
        $blocks[] = ['title' => null, 'headers' => ['Name', 'Course', 'Fines', 'Status'], 'rows' => $rows];
    } elseif ($reportKey === 'visitor_log') {
        $rows = [];
        foreach ($recentActivityReport as $entry) {
            $rows[] = [
                $entry['name'] ?: 'N/A',
                $entry['course_year'] ?: 'N/A',
                $entry['time_in'],
                $entry['time_out'] ?: 'Still Active',
                $entry['duration_minutes'],
                $entry['purpose'] ?: 'N/A',
            ];
        }
        $blocks[] = ['title' => null, 'headers' => ['Name', 'Course', 'Time In', 'Time Out', 'Duration (Min)', 'Purpose'], 'rows' => $rows];
    } elseif ($reportKey === 'inventory') {
        $rows = [];
        foreach ($inventoryAssetReport as $book) {
            $rows[] = [
                $book['title'] ?: 'Untitled',
                $book['author'] ?: 'Unknown',
                $book['category'] ?: 'General',
                (int)($book['total_copies'] ?? 0),
                (int)($book['available_copies'] ?? 0),
                $book['status'] ?: 'Unknown',
            ];
        }
        $blocks[] = ['title' => null, 'headers' => ['Title', 'Author', 'Category', 'Total Copies', 'Available Copies', 'Status'], 'rows' => $rows];
    } elseif ($reportKey === 'circulation') {
        $rows = [];
        foreach ($borrowHistoryReport as $borrow) {
            $rows[] = [
                $borrow['name'] ?: 'N/A',
                $borrow['course_year'] ?: 'N/A',
                $borrow['title'] ?: 'Untitled',
                $borrow['borrow_date'],
                $borrow['due_date'],
                $borrow['returned_at'] ?: 'Not Returned',
                $borrow['status'] ?: 'Unknown',
            ];
        }
        $blocks[] = ['title' => null, 'headers' => ['Name', 'Course', 'Book Title', 'Borrow Date', 'Due Date', 'Returned At', 'Status'], 'rows' => $rows];
    } elseif ($reportKey === 'resources') {
        $resourceRows = [];
        foreach ($resourceUsageReport as $resource) {
            $resourceRows[] = [
                $resource['title'] ?: 'Untitled Resource',
                $resource['category'] ?: 'General',
                $resource['course_year'] ?: 'All',
                $resource['download_count'] ?? 0,
            ];
        }
        $blocks[] = ['title' => 'E-Resources Utilization', 'headers' => ['Title', 'Category', 'Course', 'Downloads'], 'rows' => $resourceRows];
    } elseif ($reportKey === 'books') {
        $rows = [];
        foreach ($topBooks as $book) {
            $rows[] = [$book['title'], $book['borrow_count']];
        }
        $blocks[] = ['title' => null, 'headers' => ['Title', 'Borrow Count'], 'rows' => $rows];
    } elseif ($reportKey === 'checkins') {
        $rows = [];
        foreach ($recentActivityReport as $checkin) {
            $rows[] = [
                $checkin['name'] ?: 'N/A',
                $checkin['course_year'] ?: 'N/A',
                $checkin['time_in'],
                $checkin['time_out'] ?: 'Still Active',
                $checkin['duration_minutes'],
                $checkin['purpose'] ?: 'N/A',
            ];
        }
        $blocks[] = ['title' => null, 'headers' => ['Name', 'Course', 'Time In', 'Time Out', 'Duration (Min)', 'Purpose'], 'rows' => $rows];
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

    $insert = '<w:p/><w:p/>';
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

// Handle new export functionality
if (isset($_POST['export_report'])) {
    $reportType = $_POST['report_type'] ?? 'fines_penalties_collection_report';
    $dateRange = $_POST['date_range'] ?? 'all';
    $customStartDate = $_POST['custom_start_date'] ?? '';
    $customEndDate = $_POST['custom_end_date'] ?? '';
    $exportFormat = $_POST['export_format'] ?? 'excel'; // 'excel' or 'docs'
    $courseFilter = $_POST['course_filter'] ?? 'all';

    $courseGroups = [
        'all' => [],
        'bsa' => ['Bachelor of Science in Accountancy'],
        'bba' => ['Bachelor of Science in Business Administration'],
        'beed' => ['Bachelor in Elementary Education'],
        'bscs' => ['Bachelor of Science in Computer Science'],
        'bscrim' => ['Bachelor of Science in Criminology'],
        'bshm' => ['Bachelor of Science in Hospitality Management'],
        'bstm' => ['Bachelor of Science in Tourism Management'],
        'act' => ['Associate in Computer Technology'],
        'abk' => ['Associate in Business Knowledge'],
        'ahm' => ['Associate in Hotel Management'],
        'atm' => ['Associate in Tourism Management'],
    ];

    $reportTypeKey = match ($reportType) {
        'visitor_log_analytics' => 'visitor_log',
        'book_inventory_asset_report' => 'inventory',
        'borrow_return_circulation_history' => 'circulation',
        'fines_penalties_collection_report' => 'fines',
        'e_resources_utilization_report' => 'resources',
        'fines', 'books', 'checkins', 'others', 'resources', 'visitor_log', 'inventory', 'circulation' => $reportType,
        default => 'fines',
    };

    $reportTitleLabel = match ($reportType) {
        'visitor_log_analytics' => 'Visitor Log Analytics',
        'book_inventory_asset_report' => 'Book Inventory & Asset Report',
        'borrow_return_circulation_history' => 'Borrow & Return Circulation History',
        'fines_penalties_collection_report' => 'Fines & Penalties Collection Report',
        'e_resources_utilization_report' => 'E-Resources Utilization Report',
        'fines' => 'Fines Report',
        'books' => 'Books Report',
        'checkins' => 'Check-ins Report',
        'others' => 'Other Reports',
        default => 'Library Report',
    };

    $filteredFineRows = $fineReportRows;
    $filteredTopBooks = $topBooks;
    $filteredRecentActivityReport = $recentActivityReport;
    $filteredInventoryAssetReport = $inventoryAssetReport;
    $filteredBorrowHistoryReport = $borrowHistoryReport;
    $filteredResourceUsageReport = $resourceUsageReport;
    $selectedCourseLabel = 'All Courses';

    $customDateRangeCondition = '';
    if ($dateRange === 'custom_date') {
        if (!empty($customStartDate) && !empty($customEndDate)) {
            $customDateRangeCondition = "DATE(lf.created_at) BETWEEN '{$customStartDate}' AND '{$customEndDate}'";
        } else {
            $customDateRangeCondition = '1 = 1';
        }
    }

    $dateRangeCondition = match ($dateRange) {
        'today' => 'DATE(lf.created_at) = CURDATE()',
        'week' => 'lf.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
        'month' => 'lf.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
        'custom_date' => $customDateRangeCondition !== '' ? $customDateRangeCondition : '1 = 1',
        default => '1 = 1',
    };

    if ($dateRange !== 'all') {
        $finesSql = "SELECT 
                u.full_name AS name,
                u.course_year AS course_year,
                SUM(lf.amount) AS total_fines,
                CASE WHEN SUM(CASE WHEN lf.paid = 0 THEN 1 ELSE 0 END) > 0 THEN 'UNPAID' ELSE 'PAID' END AS status
                FROM library_fines lf
                JOIN users u ON lf.user_id = u.id
                WHERE " . $dateRangeCondition . "
                GROUP BY u.id, u.full_name, u.course_year
                ORDER BY u.full_name ASC";
        $fineStmt = $pdo->prepare($finesSql);
        $fineStmt->execute();
        $filteredFineRows = $fineStmt->fetchAll(PDO::FETCH_ASSOC);

        $borrowDateClause = match ($dateRange) {
            'today' => 'AND DATE(lb.borrow_date) = CURDATE()',
            'week' => 'AND lb.borrow_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'month' => 'AND lb.borrow_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            'custom_date' => (!empty($customStartDate) && !empty($customEndDate)) ? "AND DATE(lb.borrow_date) BETWEEN '{$customStartDate}' AND '{$customEndDate}'" : '',
            default => '',
        };

        $topBooksSql = "SELECT 
                b.title,
                COUNT(lb.id) as borrow_count
            FROM library_books b
            JOIN library_borrows lb ON b.id = lb.book_id
            WHERE 1 = 1 " . $borrowDateClause . "
            GROUP BY b.id, b.title
            ORDER BY borrow_count DESC
            LIMIT 10";
        $topBooksStmt = $pdo->query($topBooksSql);
        $filteredTopBooks = $topBooksStmt->fetchAll(PDO::FETCH_ASSOC);

        $checkinDateClause = match ($dateRange) {
            'today' => 'AND DATE(lv.time_in) = CURDATE()',
            'week' => 'AND lv.time_in >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'month' => 'AND lv.time_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            'custom_date' => (!empty($customStartDate) && !empty($customEndDate)) ? "AND DATE(lv.time_in) BETWEEN '{$customStartDate}' AND '{$customEndDate}'" : '',
            default => '',
        };

        $checkinsSql = "SELECT 
                u.full_name AS name,
                u.course_year AS course_year,
                lv.time_in,
                lv.time_out,
                TIMESTAMPDIFF(MINUTE, lv.time_in, COALESCE(lv.time_out, NOW())) as duration_minutes,
                lv.purpose,
                lv.status
            FROM library_visits lv
            LEFT JOIN users u ON lv.user_id = u.id
            WHERE 1 = 1 " . $checkinDateClause . "
            ORDER BY lv.time_in DESC";
        $checkinStmt = $pdo->query($checkinsSql);
        $filteredRecentActivityReport = $checkinStmt->fetchAll(PDO::FETCH_ASSOC);

        $inventoryDateClause = match ($dateRange) {
            'today' => 'AND DATE(b.created_at) = CURDATE()',
            'week' => 'AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'month' => 'AND b.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            'custom_date' => (!empty($customStartDate) && !empty($customEndDate)) ? "AND DATE(b.created_at) BETWEEN '{$customStartDate}' AND '{$customEndDate}'" : '',
            default => '',
        };

        $inventorySql = "SELECT 
                b.title,
                b.author,
                b.category,
                b.total_copies,
                b.available_copies,
                b.status
            FROM library_books b
            WHERE 1 = 1 " . $inventoryDateClause . "
            ORDER BY b.title ASC";
        $inventoryStmt = $pdo->query($inventorySql);
        $filteredInventoryAssetReport = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

        $borrowDateRangeClause = match ($dateRange) {
            'today' => 'AND DATE(b.borrow_date) = CURDATE()',
            'week' => 'AND b.borrow_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'month' => 'AND b.borrow_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            'custom_date' => (!empty($customStartDate) && !empty($customEndDate)) ? "AND DATE(b.borrow_date) BETWEEN '{$customStartDate}' AND '{$customEndDate}'" : '',
            default => '',
        };

        $borrowHistorySql = "SELECT 
                u.full_name AS name,
                u.course_year AS course_year,
                lb.title,
                lb.author,
                b.borrow_date,
                b.due_date,
                b.returned_at,
                b.status
            FROM library_borrows b
            JOIN users u ON b.user_id = u.id
            JOIN library_books lb ON b.book_id = lb.id
            WHERE 1 = 1 " . $borrowDateRangeClause . "
            ORDER BY b.borrow_date DESC";
        $borrowStmt = $pdo->query($borrowHistorySql);
        $filteredBorrowHistoryReport = $borrowStmt->fetchAll(PDO::FETCH_ASSOC);

        $resourceClause = match ($dateRange) {
            'today' => 'AND DATE(lr.created_at) = CURDATE()',
            'week' => 'AND lr.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'month' => 'AND lr.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
            'custom_date' => (!empty($customStartDate) && !empty($customEndDate)) ? "AND DATE(lr.created_at) BETWEEN '{$customStartDate}' AND '{$customEndDate}'" : '',
            default => '',
        };

        $resourceSql = "SELECT 
                title,
                category,
                course_year,
                download_count,
                created_at
            FROM library_resources lr
            WHERE is_approved = 1 " . $resourceClause . "
            ORDER BY download_count DESC, created_at DESC
            LIMIT 20";
        $resourceStmt = $pdo->query($resourceSql);
        $filteredResourceUsageReport = $resourceStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $courseFilterClause = '';
    $selectedCourseLabel = 'All Courses';

    if ($courseFilter !== 'all' && isset($courseGroups[$courseFilter])) {
        $selectedCourseValue = $courseGroups[$courseFilter][0];
        $selectedCourseLabel = match ($courseFilter) {
            'bsa' => 'Bachelor of Science in Accountancy',
            'bba' => 'Bachelor of Science in Business Administration',
            'beed' => 'Bachelor in Elementary Education',
            'bscs' => 'Bachelor of Science in Computer Science',
            'bscrim' => 'Bachelor of Science in Criminology',
            'bshm' => 'Bachelor of Science in Hospitality Management',
            'bstm' => 'Bachelor of Science in Tourism Management',
            'act' => 'Associate in Computer Technology',
            'abk' => 'Associate in Business Knowledge',
            'ahm' => 'Associate in Hotel Management',
            'atm' => 'Associate in Tourism Management',
            default => 'All Courses',
        };
        $courseFilterClause = " AND u.course_year LIKE '%" . str_replace("'", "''", $selectedCourseValue) . "%'";
    }

    if ($reportTypeKey === 'fines' && $courseFilter !== 'all' && isset($courseGroups[$courseFilter])) {
        $sql = "SELECT 
                u.full_name AS name,
                u.course_year AS course_year,
                SUM(lf.amount) AS total_fines,
                CASE WHEN SUM(CASE WHEN lf.paid = 0 THEN 1 ELSE 0 END) > 0 THEN 'UNPAID' ELSE 'PAID' END AS status
                FROM library_fines lf
                JOIN users u ON lf.user_id = u.id
                WHERE 1 = 1" . $courseFilterClause . "
                GROUP BY u.id, u.full_name, u.course_year
                ORDER BY u.full_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $filteredFineRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (in_array($reportTypeKey, ['visitor_log', 'circulation', 'resources'], true) && $courseFilter !== 'all' && isset($courseGroups[$courseFilter])) {
        $selectedCourseValue = $courseGroups[$courseFilter][0];
        $selectedCourseLabel = match ($courseFilter) {
            'bsa' => 'Bachelor of Science in Accountancy',
            'bba' => 'Bachelor of Science in Business Administration',
            'beed' => 'Bachelor in Elementary Education',
            'bscs' => 'Bachelor of Science in Computer Science',
            'bscrim' => 'Bachelor of Science in Criminology',
            'bshm' => 'Bachelor of Science in Hospitality Management',
            'bstm' => 'Bachelor of Science in Tourism Management',
            'act' => 'Associate in Computer Technology',
            'abk' => 'Associate in Business Knowledge',
            'ahm' => 'Associate in Hotel Management',
            'atm' => 'Associate in Tourism Management',
            default => 'All Courses',
        };
    }

    $reportTitle = 'Library ' . $reportTitleLabel . ' Report';
    $blocks = build_library_export_blocks(
        $reportTypeKey,
        $filteredFineRows,
        $filteredTopBooks,
        $filteredRecentActivityReport,
        $filteredInventoryAssetReport,
        $filteredBorrowHistoryReport,
        $selectedCourseLabel,
        $filteredResourceUsageReport
    );

    $hasLetterhead = isset($_FILES['letterhead_template']) && $_FILES['letterhead_template']['error'] === UPLOAD_ERR_OK;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if ($hasLetterhead) {
        $tmpPath = $_FILES['letterhead_template']['tmp_name'];
        $origName = $_FILES['letterhead_template']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($exportFormat === 'docs') {
            if ($ext !== 'docx') {
                http_response_code(400);
                exit('Ang letterhead template para sa Word export ay dapat .docx file.');
            }
            export_merge_into_docx($tmpPath, $reportTitle, $blocks, "library_{$reportType}_report.docx");
        } else {
            if ($ext !== 'xlsx') {
                http_response_code(400);
                exit('Ang letterhead template para sa Excel export ay dapat .xlsx file.');
            }
            export_merge_into_xlsx($tmpPath, $reportTitle, $blocks, "library_{$reportType}_report.xlsx");
        }
        exit;
    }

    // No letterhead uploaded: export raw data only, same formats as before.
    if ($exportFormat === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        $filename = "library_{$reportType}_report.csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        export_render_csv($blocks);
    } else {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        $filename = "library_{$reportType}_report.doc";
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        export_render_html_doc($reportTitle, $blocks);
    }
    exit;
}

$resourceDownloads = $pdo->query("
    SELECT 
        title,
        download_count
    FROM library_resources
    ORDER BY download_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
$currentPage = basename($_SERVER['PHP_SELF']);
$serviceOpen = false;
$manageOpen = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Reports | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/library-header.css">
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: #fcfaf5;
            border-radius: 30px;
            padding: 28px;
            box-shadow: 0 18px 45px rgba(32,20,10,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid rgba(129,90,74,0.16);
        }

        .metric-icon {
            font-size: 32px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%);
            border-radius: 16px;
            color: white;
        }

        .metric-content h3 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            color: var(--deep-maroon);
        }

        .metric-content p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .ranking-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .ranking-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: #fcfaf5;
            border-radius: 12px;
            border: 1px solid rgba(214,168,74,0.18);
        }

        .ranking-number {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--deep-maroon);
            color: white;
            border-radius: 50%;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .ranking-content h4 {
            margin: 0;
            font-size: 16px;
            color: #1f2937;
            line-height: 1.3;
        }

        .ranking-content p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 14px;
        }

        .category-stats {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .category-item {
            padding: 16px;
            background: #fcfaf5;
            border-radius: 12px;
            border: 1px solid rgba(214,168,74,0.18);
        }

        .category-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .category-info h4 {
            margin: 0;
            font-size: 16px;
            color: #1f2937;
        }

        .category-numbers {
            display: flex;
            gap: 16px;
            font-size: 14px;    
        } 

        .category-numbers .total {
            color: #64748b;
        }

        .category-numbers .available {
            color: #22c55e;
            font-weight: 500;
        }

        .category-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
            transition: width 0.3s ease;
        }

        .dashboard-section h2 {
            margin-bottom: 16px;
            color: var(--deep-maroon);
            font-size: 20px;
            font-weight: 600;
        }

        .management-card {
            background: #fcfaf5;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 18px 40px rgba(15,23,42,0.08);
            border: 1px solid rgba(214,168,74,0.18);
        }

        @media (max-width: 768px) {
            .dashboard-intro {
                display: grid;
                gap: 20px;
            }

            .dashboard-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 12px;
            }

            .dashboard-summary div {
                background: #ffffff;
                padding: 16px;
                border-radius: 18px;
                border: 1px solid rgba(0, 0, 0, 0.06);
            }

            .dashboard-summary span {
                display: block;
                font-size: 13px;
                color: #6b7280;
            }

            .dashboard-summary strong {
                display: block;
                margin-top: 6px;
                font-size: 1.75rem;
                line-height: 1.1;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .reports-grid {
                grid-template-columns: 1fr;
            }

            .metric-card,
            .management-card,
            .metric-card > div,
            .management-card > div {
                padding: 20px;
            }

            .metric-card {
                flex-direction: row;
                gap: 14px;
            }

            .metric-icon {
                width: 48px;
                height: 48px;
                font-size: 22px;
            }

            .metric-content h3 {
                font-size: 22px;
            }

            .metric-content p {
                font-size: 13px;
            }

            .dashboard-section h2 {
                font-size: 18px;
            }

            .management-card {
                overflow-x: hidden;
            }

            .management-card canvas {
                width: 100% !important;
                height: auto !important;
            }

            .dashboard-section {
                padding: 0;
            }

            .dashboard-section > .metric-card,
            .dashboard-section > .management-card {
                padding: 20px;
            }

            .management-card .category-item,
            .management-card .ranking-item,
            .management-card .category-stats {
                gap: 14px;
            }

            .reports-grid > section {
                margin-bottom: 0;
            }

            .dashboard-intro > div,
            .dashboard-summary,
            .metric-card,
            .management-card {
                width: 100%;
            }
        }
    </style>
    <style>
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body class="library-reports swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Library Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="dashboard/librarian_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="dashboard/school_announcements.php?service=library" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $serviceOpen ? 'true' : 'false' ?>" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= $serviceOpen ? 'false' : 'true' ?>">
                        <a href="dashboard/guidance_dashboard.php?service=library" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="dashboard/library_dashboard.php?service=library" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="dashboard/clinic_dashboard.php?service=library" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="dashboard/ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="dashboard/scholarship/scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="dashboard/ssaa_student_home.php?service=library" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $manageOpen ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $manageOpen ? ' open' : '' ?>" aria-hidden="<?= $manageOpen ? 'false' : 'true' ?>">
                        <a href="library_checkin.php" data-tooltip="QR Check-In">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">QR Check-In</span>
                        </a>
                        <a href="library_catalog.php" data-tooltip="Digital Catalog">
                            <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                            <span class="nav-text">Digital Catalog</span>
                        </a>
                        <a href="library_inventory.php" data-tooltip="Inventory">
                            <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                            <span class="nav-text">Inventory</span>
                        </a>
                        <a class="active" href="library_reports.php" data-tooltip="Reports">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-text">Reports</span>
                        </a>
                        <a href="library_settings.php" data-tooltip="Settings">
                            <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                            <span class="nav-text">Settings</span>
                        </a>
                        <a href="library_qr.php" data-tooltip="Library QR">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">Library QR</span>
                        </a>
                    </div>
                </div>
                <a href="dashboard/profile.php?service=library" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="dashboard/about.php?service=library" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="logout.php" class="logout-link" data-tooltip="Logout">
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
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Library Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Library Head</span>
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
                        <a class="menu-link menu-footer-link" href="dashboard/about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="dashboard/profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="dashboard/profile.php" class="menu-action">View profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="dashboard/about.php">Help center</a>
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
                <section class="dashboard-intro library-page-header">
                        <div class="library-header__content">
                            <span class="eyebrow">LIBRARY REPORTS</span>
                            <h1>Comprehensive Library Analytics</h1>
                            <p class="dashboard-subtitle">Monitor borrowing trends, user activity, inventory status, and financial performance across all library operations.</p>
                        </div>
                        <div class="library-header__art" aria-hidden="true"><i class="fa-solid fa-layer-group book-stack"></i><i class="fa-solid fa-book-open open-book"></i><i class="fa-solid fa-id-card library-card"></i><i class="fa-solid fa-circle-check library-check"></i><i class="fa-solid fa-location-pin library-pin"></i><i class="fa-solid fa-leaf library-leaf"></i></div>
                    </section>

                    <!-- Export Reports Section -->
                    <section class="dashboard-section">
                        <h2>Export Reports</h2>
                        <div class="metric-card" style="width: 100%; max-width: none; padding: 24px;">
                            <div style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
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
                            <form method="post" enctype="multipart/form-data" style="width: 100%;" id="exportReportForm">
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
                                    var formatSelect = document.getElementById('export_format');
                                    var fileInput = document.getElementById('letterhead_template');
                                    var dateRangeSelect = document.getElementById('date_range');
                                    var customDateFields = document.getElementById('custom-date-fields');
                                    if (!form || !formatSelect || !fileInput) return;

                                    function toggleDateFields() {
                                        if (!dateRangeSelect || !customDateFields) return;
                                        var show = dateRangeSelect.value === 'custom_date';
                                        customDateFields.style.display = show ? 'grid' : 'none';
                                    }

                                    if (dateRangeSelect) {
                                        dateRangeSelect.addEventListener('change', toggleDateFields);
                                        toggleDateFields();
                                    }

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
                    </section>

                    <!-- Key Metrics Row -->
                    <section class="dashboard-section">
                        <h2>Financial Overview (Last 30 Days)</h2>
                        <div class="metrics-grid">
                            <div class="metric-card">
                                <div class="metric-icon"><i class="fa-solid fa-coins"></i></div>
                                <div class="metric-content">
                                    <h3>₱<?= number_format($fineStats['collected_fines'] ?? 0, 2) ?></h3>
                                    <p>Fines Collected</p>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                                <div class="metric-content">
                                    <h3>₱<?= number_format($fineStats['outstanding_fines'] ?? 0, 2) ?></h3>
                                    <p>Outstanding Fines</p>
                                </div>
                            </div>
                            <div class="metric-card">
                                <div class="metric-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                                <div class="metric-content">
                                    <h3><?= htmlspecialchars($fineStats['unpaid_fines_count'] ?? 0) ?></h3>
                                    <p>Unpaid Fine Records</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Weekly Activity Trend -->
                    <section class="dashboard-section">
                        <h2>Weekly Check-in Activity</h2>
                        <div class="management-card">
                            <canvas id="weeklyActivityChart" width="400" height="220"></canvas>
                        </div>
                    </section>

                    <!-- Monthly Borrowing Trend -->
                    <section class="dashboard-section">
                        <h2>Monthly Borrowing Trends</h2>
                        <div class="management-card">
                            <canvas id="monthlyBorrowingChart" width="400" height="220"></canvas>
                        </div>
                    </section>

                    <!-- Top Content Row -->
                    <div class="reports-grid">
                        <!-- Most Borrowed Books -->
                        <section class="dashboard-section">
                            <h2>Most Borrowed Books (Last 30 Days)</h2>
                            <div class="management-card">
                                <?php if (empty($topBooks)): ?>
                                    <p>No borrowing activity in the last 30 days.</p>
                                <?php else: ?>
                                    <div class="ranking-list">
                                        <?php foreach ($topBooks as $index => $book): ?>
                                            <div class="ranking-item">
                                                <div class="ranking-number">#<?= $index + 1 ?></div>
                                                <div class="ranking-content">
                                                    <h4><?= htmlspecialchars($book['title']) ?></h4>
                                                    <p><?= htmlspecialchars($book['borrow_count']) ?> borrow(s)</p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Department Usage -->
                        <section class="dashboard-section">
                            <h2>Department Usage (Last 30 Days)</h2>
                            <div class="management-card">
                                <?php if (empty($departmentUsage)): ?>
                                    <p>No department activity recorded.</p>
                                <?php else: ?>
                                    <div class="ranking-list">
                                        <?php foreach ($departmentUsage as $index => $dept): ?>
                                            <div class="ranking-item">
                                                <div class="ranking-number">#<?= $index + 1 ?></div>
                                                <div class="ranking-content">
                                                    <h4><?= htmlspecialchars($dept['department'] ?: 'General') ?></h4>
                                                    <p><?= htmlspecialchars($dept['borrow_count']) ?> borrow(s)</p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Book Categories & Resources -->
                    <div class="reports-grid">
                        <!-- Book Inventory by Category -->
                        <section class="dashboard-section">
                            <h2>Book Inventory by Category</h2>
                            <div class="management-card">
                                <?php if (empty($bookCategories)): ?>
                                    <p>No books in inventory.</p>
                                <?php else: ?>
                                    <div class="category-stats">
                                        <?php foreach ($bookCategories as $category): ?>
                                            <div class="category-item">
                                                <div class="category-info">
                                                    <h4><?= htmlspecialchars($category['category'] ?: 'Uncategorized') ?></h4>
                                                    <div class="category-numbers">
                                                        <span class="total">Total: <?= htmlspecialchars($category['total_books']) ?></span>
                                                        <span class="available">Available: <?= htmlspecialchars($category['available_books']) ?></span>
                                                    </div>
                                                </div>
                                                <div class="category-bar">
                                                    <div class="bar-fill" style="width: <?= $category['total_books'] > 0 ? ($category['available_books'] / $category['total_books'] * 100) : 0 ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>

                        <!-- Popular E-Resources -->
                        <section class="dashboard-section">
                            <h2>Most Downloaded E-Resources</h2>
                            <div class="management-card">
                                <?php if (empty($resourceDownloads)): ?>
                                    <p>No resource downloads recorded.</p>
                                <?php else: ?>
                                    <div class="ranking-list">
                                        <?php foreach ($resourceDownloads as $index => $resource): ?>
                                            <div class="ranking-item">
                                                <div class="ranking-number">#<?= $index + 1 ?></div>
                                                <div class="ranking-content">
                                                    <h4><?= htmlspecialchars($resource['title']) ?></h4>
                                                    <p><?= htmlspecialchars($resource['download_count']) ?> downloads</p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Calendar Blocks -->
                    <section class="dashboard-section">
                        <h2>Upcoming Calendar Blocks</h2>
                        <div class="management-card">
                            <ul class="activity-list">
                                <?php if (empty($calendarBlocks)): ?>
                                    <li>No calendar blocks scheduled.</li>
                                <?php else: ?>
                                    <?php foreach ($calendarBlocks as $block): ?>
                                        <li>
                                            <strong><?= htmlspecialchars(date('M j, Y', strtotime($block['event_date']))) ?></strong> — 
                                            <?= htmlspecialchars($block['event_type']) ?>
                                            <?php if (!empty($block['description'])): ?>
                                                <br><small style="color: #64748b;"><?= htmlspecialchars($block['description']) ?></small>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </section>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const reportTypeSelect = document.getElementById('report_type');
            const courseFilterGroup = document.querySelector('.course-filter-group');
            const dateRangeGroup = document.querySelector('.date-range-group');
            const allowedFilters = ['visitor_log_analytics', 'borrow_return_circulation_history', 'fines_penalties_collection_report', 'e_resources_utilization_report'];

            function toggleCourseFilter() {
                if (!reportTypeSelect || !courseFilterGroup) {
                    return;
                }
                const showCourseFilter = allowedFilters.includes(reportTypeSelect.value);
                courseFilterGroup.style.display = showCourseFilter ? 'block' : 'none';
            }

            function toggleDateRange() {
                if (!reportTypeSelect || !dateRangeGroup) {
                    return;
                }
                const showDateRange = allowedFilters.includes(reportTypeSelect.value);
                dateRangeGroup.style.display = showDateRange ? 'block' : 'none';
                const dateRangeSelect = document.getElementById('date_range');
                const customDateFields = document.getElementById('custom-date-fields');
                if (dateRangeSelect && customDateFields) {
                    if (!showDateRange) {
                        dateRangeSelect.value = 'all';
                        customDateFields.style.display = 'none';
                    } else {
                        const showCustom = dateRangeSelect.value === 'custom_date';
                        customDateFields.style.display = showCustom ? 'grid' : 'none';
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

            // Weekly Activity Chart
            const weeklyCtx = document.getElementById('weeklyActivityChart');
            if (weeklyCtx) {
                const weeklyData = <?= json_encode($weeklyStats) ?>;
                new Chart(weeklyCtx, {
                    type: 'line',
                    data: {
                        labels: weeklyData.map(item => new Date(item.date).toLocaleDateString('en-US', { weekday: 'short' })),
                        datasets: [{
                            label: 'Daily Check-ins',
                            data: weeklyData.map(item => parseInt(item.checkins)),
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }

            // Monthly Borrowing Chart
            const monthlyCtx = document.getElementById('monthlyBorrowingChart');
            if (monthlyCtx) {
                const monthlyData = <?= json_encode($monthlyBorrowing) ?>;
                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyData.map(item => new Date(item.month + '-01').toLocaleDateString('en-US', { month: 'short', year: 'numeric' })),
                        datasets: [{
                            label: 'Monthly Borrows',
                            data: monthlyData.map(item => parseInt(item.borrows)),
                            backgroundColor: '#22c55e',
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        },
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
        });
    </script>
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

    <script src="assets/js/app.js" defer></script>
    <?php include 'AI CHAT BOT/chat_widget.php'; ?>
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
    </script>
</body>
</html>
