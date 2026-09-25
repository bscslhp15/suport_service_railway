<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/template_merger.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'library') {
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

// Handle new export functionality
if (isset($_POST['export_report'])) {
    $reportType = $_POST['report_type'] ?? 'fines';
    $exportFormat = $_POST['export_format'] ?? 'excel';
    $courseFilter = $_POST['course_filter'] ?? 'all';
    $useTemplate = isset($_POST['use_template']) && $_POST['use_template'] === '1' ? true : false;

    // =========================================================================
    // 🔥 FIX 1: AUTO-DETECT FORMAT FROM TEMPLATE (FIXES DISABLED DROPDOWN BUG)
    // =========================================================================
    // When template is used, auto-detect the format from template extension
    // This bypasses the disabled dropdown issue where format wasn't being sent in POST
    if ($useTemplate) {
        $template_path = get_latest_template();
        if ($template_path) {
            $template_ext = strtolower(pathinfo($template_path, PATHINFO_EXTENSION));
            if (in_array($template_ext, ['docx', 'doc'])) {
                $exportFormat = 'docs';
            } elseif (in_array($template_ext, ['xlsx', 'xls'])) {
                $exportFormat = 'excel';
            } elseif ($template_ext === 'pdf') {
                $exportFormat = 'pdf';
            }
        }
    }

    $courseGroups = [
        'all' => [],
        'cs_act' => [
            'Bachelor of Science in Computer Science',
            'Associate in Computer Technology',
        ],
        'bba_abk' => [
            'Bachelor of Science in Business Administration',
            'Associate in Business Knowledge',
        ],
        'bshm_ahm' => [
            'Bachelor of Science in Hospitality Management',
            'Associate in Hospitality Management',
        ],
        'bstm_atm' => [
            'Bachelor of Science in Tourism Management',
            'Associate in Tourism Management',
        ],
        'bsa' => ['Bachelor of Science in Accountancy'],
        'beed' => ['Bachelor in Elementary Education'],
        'bscrim' => ['Bachelor of Science in Criminology'],
    ];

    $filteredFineRows = $fineReportRows;
    $selectedCourseLabel = 'All Courses';

    if ($reportType === 'fines' && $courseFilter !== 'all' && isset($courseGroups[$courseFilter])) {
        $selectedCourses = $courseGroups[$courseFilter];
        $selectedCourseLabel = match ($courseFilter) {
            'cs_act' => 'Bachelor of Science in Computer Science / Associate in Computer Technology',
            'bba_abk' => 'Bachelor of Science in Business Administration / Associate in Business Knowledge',
            'bshm_ahm' => 'Bachelor of Science in Hospitality Management / Associate in Hospitality Management',
            'bstm_atm' => 'Bachelor of Science in Tourism Management / Associate in Tourism Management',
            'bsa' => 'Bachelor of Science in Accountancy',
            'beed' => 'Bachelor in Elementary Education',
            'bscrim' => 'Bachelor of Science in Criminology',
            default => 'All Courses',
        };

        $conditions = [];
        $params = [];
        foreach ($selectedCourses as $courseName) {
            $conditions[] = 'u.course_year LIKE ?';
            $params[] = $courseName . ' %';
        }

        if (!empty($conditions)) {
            $sql = "SELECT 
                u.full_name AS name,
                u.course_year AS course_year,
                SUM(lf.amount) AS total_fines,
                CASE WHEN SUM(CASE WHEN lf.paid = 0 THEN 1 ELSE 0 END) > 0 THEN 'UNPAID' ELSE 'PAID' END AS status
                FROM library_fines lf
                JOIN users u ON lf.user_id = u.id
                WHERE " . implode(' OR ', $conditions) . "
                GROUP BY u.id, u.full_name, u.course_year
                ORDER BY u.full_name ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $filteredFineRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Prepare report data based on type
    $reportData = [];
    if ($reportType === 'fines') {
        $reportData = $filteredFineRows;
    } elseif ($reportType === 'books') {
        $reportData = $topBooks;
    } elseif ($reportType === 'checkins') {
        $reportData = $checkinsReport;
    }

    // Check if template should be used
    if ($useTemplate) {
        $template_path = get_latest_template();
        if ($template_path && file_exists(__DIR__ . '/' . $template_path)) {
            // Use template merging
            $output_file = generate_report_with_template(__DIR__ . '/' . $template_path, $reportData, $reportType, $exportFormat);
            if ($output_file && file_exists($output_file)) {
                // Determine MIME type based on ACTUAL generated file
                $actual_file_extension = strtolower(pathinfo($output_file, PATHINFO_EXTENSION));
                
                // =========================================================================
                // 🔥 FIX 2: AGGRESSIVE OUTPUT BUFFER CLEARING (FIXES CORRUPTED BINARY FILES)
                // =========================================================================
                // Binary files like DOCX/PDF get corrupted if ANY output is sent before file content
                // Clear ALL output buffers, not just one layer
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Set appropriate headers based on ACTUAL file type generated
                if ($actual_file_extension === 'csv') {
                    header('Content-Type: text/csv; charset=UTF-8');
                    header('Content-Description: File Transfer');
                    header('Content-Disposition: attachment; filename="library_' . $reportType . '_report.csv"');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Expires: 0');
                } elseif ($actual_file_extension === 'pdf') {
                    header('Content-Type: application/pdf');
                    header('Content-Description: File Transfer');
                    header('Content-Disposition: attachment; filename="library_' . $reportType . '_report.pdf"');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Expires: 0');
                } elseif (in_array($actual_file_extension, ['html', 'htm'])) {
                    header('Content-Type: text/html; charset=UTF-8');
                    header('Content-Description: File Transfer');
                    header('Content-Disposition: attachment; filename="library_' . $reportType . '_report.html"');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Expires: 0');
                } elseif (in_array($actual_file_extension, ['docx', 'doc'])) {
                    header('Content-Description: File Transfer');
                    header('Content-Disposition: attachment; filename="library_' . $reportType . '_report.docx"');
                    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                    header('Content-Transfer-Encoding: binary');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Expires: 0');
                } elseif (in_array($actual_file_extension, ['xlsx', 'xls'])) {
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Description: File Transfer');
                    header('Content-Disposition: attachment; filename="library_' . $reportType . '_report.xlsx"');
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Expires: 0');
                } else {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="library_' . $reportType . '_report.' . $actual_file_extension . '"');
                }

                // Stream file content
                if (is_readable($output_file)) {
                    $filesize = filesize($output_file);
                    header('Content-Length: ' . $filesize);
                    readfile($output_file);
                    @unlink($output_file);
                    exit;
                }
            }
        }
    }

    // Fallback to regular export (without template)
    // =========================================================================
    // 🔥 FIX 2B: BUFFER CLEARING FOR FALLBACK EXPORTS (PREVENTS CORRUPTION)
    // =========================================================================
    // Must clear output buffers BEFORE sending ANY headers to prevent corruption
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if ($exportFormat === 'excel' || $exportFormat === 'xlsx') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        $filename = "library_{$reportType}_report.csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if ($reportType === 'fines') {
            echo "Name,Course Year,Total Fines,Status\r\n";
            foreach ($filteredFineRows as $row) {
                $name = str_replace('"', '""', $row['name']);
                $course = str_replace('"', '""', $row['course_year']);
                $total = number_format((float)$row['total_fines'], 2, '.', '');
                $status = $row['status'];
                // 🔥 FIX 3: Use double quotes so \r\n is treated as actual newline, not literal text
                echo '"' . $name . '","' . $course . '","' . $total . '","' . $status . "\"\r\n";
            }
        } elseif ($reportType === 'books') {
            echo "Title,Borrow Count\r\n";
            foreach ($topBooks as $book) {
                $title = str_replace('"', '""', $book['title']);
                echo '"' . $title . '","' . $book['borrow_count'] . "\"\r\n";
            }
        } elseif ($reportType === 'checkins') {
            echo "Name,Course Year,Time In,Time Out,Duration (Minutes),Purpose\r\n";
            foreach ($checkinsReport as $checkin) {
                $name = str_replace('"', '""', $checkin['name'] ?: 'N/A');
                $course = str_replace('"', '""', $checkin['course_year'] ?: 'N/A');
                $timeIn = $checkin['time_in'];
                $timeOut = $checkin['time_out'] ?: 'Still Active';
                $duration = $checkin['duration_minutes'];
                $purpose = str_replace('"', '""', $checkin['purpose'] ?: 'N/A');
                echo '"' . $name . '","' . $course . '","' . $timeIn . '","' . $timeOut . '","' . $duration . '","' . $purpose . "\"\r\n";
            }
        } elseif ($reportType === 'others') {
            // Export department usage and book categories
            echo "Department Usage Report\r\n";
            echo "Department,Borrow Count\r\n";
            foreach ($departmentUsage as $dept) {
                echo '"' . $dept['department'] . '","' . $dept['borrow_count'] . "\"\r\n";
            }
            echo "\r\nBook Categories Report\r\n";
            echo "Category,Total Books,Available Books\r\n";
            foreach ($bookCategories as $cat) {
                echo '"' . $cat['category'] . '","' . $cat['total_books'] . '","' . $cat['available_books'] . "\"\r\n";
            }
        }
    } elseif ($exportFormat === 'docs' || $exportFormat === 'docx') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $filename = "library_{$reportType}_report.docx";
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');

        // =========================================================================
        // 🔥 FIX 3: PROPER OFFICE 2003 XML FORMAT (BYPASSES WORD EXTENSION HARDENING)
        // =========================================================================
        // Modern Word 2016+ requires valid Office XML with proper namespaces and structure
        // HTML with namespace tricks is deprecated; use proper Office 2003 WordML format
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>" . PHP_EOL;
        $xml .= "<w:wordDocument xmlns:w=\"http://schemas.microsoft.com/office/word/2003/wordml\"" . PHP_EOL;
        $xml .= "  xmlns:v=\"urn:schemas-microsoft-com:vml\"" . PHP_EOL;
        $xml .= "  xmlns:o=\"urn:schemas-microsoft-com:office:office\"" . PHP_EOL;
        $xml .= "  xmlns:wx=\"http://schemas.microsoft.com/office/word/2003/auxHint\"" . PHP_EOL;
        $xml .= "  w:macrosEnabled=\"0\">" . PHP_EOL;
        $xml .= "<w:body>" . PHP_EOL;

        // Title
        $xml .= "  <w:p><w:pPr><w:pStyle w:val=\"Heading1\"/></w:pPr>" . PHP_EOL;
        $xml .= "    <w:r><w:rPr><w:rFonts w:ascii=\"Calibri\" w:hAnsi=\"Calibri\"/><w:b/><w:sz w:val=\"32\"/></w:rPr>" . PHP_EOL;
        $xml .= "    <w:t>Library " . htmlspecialchars(ucfirst($reportType)) . " Report</w:t></w:r></w:p>" . PHP_EOL;

        // Date
        $xml .= "  <w:p><w:r><w:t>Generated on: " . htmlspecialchars(date('Y-m-d H:i:s')) . "</w:t></w:r></w:p>" . PHP_EOL;

        if ($reportType === 'fines') {
            $xml .= "  <w:p><w:pPr><w:pStyle w:val=\"Heading2\"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Fines Report</w:t></w:r></w:p>" . PHP_EOL;
            $xml .= "  <w:p><w:r><w:t><strong>Course Group:</strong> " . htmlspecialchars($selectedCourseLabel) . "</w:t></w:r></w:p>" . PHP_EOL;
            $xml .= "  <w:tbl><w:tblPr><w:tblW w:w=\"5000\" w:type=\"auto\"/></w:tblPr>" . PHP_EOL;
            $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"400\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Name</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Course</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Fines</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Status</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "    </w:tr>" . PHP_EOL;
            foreach ($filteredFineRows as $row) {
                $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"300\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($row['name']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($row['course_year']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>₱" . htmlspecialchars(number_format((float)$row['total_fines'], 2)) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($row['status']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "    </w:tr>" . PHP_EOL;
            }
            $xml .= "  </w:tbl>" . PHP_EOL;
        } elseif ($reportType === 'books') {
            $xml .= "  <w:p><w:pPr><w:pStyle w:val=\"Heading2\"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Top Books Report</w:t></w:r></w:p>" . PHP_EOL;
            $xml .= "  <w:tbl><w:tblPr><w:tblW w:w=\"5000\" w:type=\"auto\"/></w:tblPr>" . PHP_EOL;
            $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"400\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Title</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Borrow Count</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "    </w:tr>" . PHP_EOL;
            foreach ($topBooks as $book) {
                $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"300\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($book['title']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($book['borrow_count']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "    </w:tr>" . PHP_EOL;
            }
            $xml .= "  </w:tbl>" . PHP_EOL;
        } elseif ($reportType === 'checkins') {
            $xml .= "  <w:p><w:pPr><w:pStyle w:val=\"Heading2\"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Library Check-ins Report</w:t></w:r></w:p>" . PHP_EOL;
            $xml .= "  <w:tbl><w:tblPr><w:tblW w:w=\"5000\" w:type=\"auto\"/></w:tblPr>" . PHP_EOL;
            $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"400\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Name</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Course</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Time In</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Time Out</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Duration (Min)</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Purpose</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "    </w:tr>" . PHP_EOL;
            foreach ($checkinsReport as $checkin) {
                $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"300\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($checkin['name'] ?: 'N/A') . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($checkin['course_year'] ?: 'N/A') . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($checkin['time_in']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($checkin['time_out'] ?: 'Still Active') . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($checkin['duration_minutes']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($checkin['purpose'] ?: 'N/A') . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "    </w:tr>" . PHP_EOL;
            }
            $xml .= "  </w:tbl>" . PHP_EOL;
        } elseif ($reportType === 'others') {
            $xml .= "  <w:p><w:pPr><w:pStyle w:val=\"Heading2\"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Department Usage Report</w:t></w:r></w:p>" . PHP_EOL;
            $xml .= "  <w:tbl><w:tblPr><w:tblW w:w=\"5000\" w:type=\"auto\"/></w:tblPr>" . PHP_EOL;
            $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"400\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Department</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Borrow Count</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "    </w:tr>" . PHP_EOL;
            foreach ($departmentUsage as $dept) {
                $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"300\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($dept['department']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($dept['borrow_count']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "    </w:tr>" . PHP_EOL;
            }
            $xml .= "  </w:tbl>" . PHP_EOL;
            $xml .= "  <w:p><w:br/></w:p>" . PHP_EOL;
            $xml .= "  <w:p><w:pPr><w:pStyle w:val=\"Heading2\"/></w:pPr><w:r><w:rPr><w:b/></w:rPr><w:t>Book Categories Report</w:t></w:r></w:p>" . PHP_EOL;
            $xml .= "  <w:tbl><w:tblPr><w:tblW w:w=\"5000\" w:type=\"auto\"/></w:tblPr>" . PHP_EOL;
            $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"400\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Category</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Total Books</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "      <w:tc><w:tcPr><w:shd w:fill=\"D3D3D3\"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Available</w:t></w:r></w:p></w:tc>" . PHP_EOL;
            $xml .= "    </w:tr>" . PHP_EOL;
            foreach ($bookCategories as $cat) {
                $xml .= "    <w:tr><w:trPr><w:trHeight w:val=\"300\" w:type=\"auto\"/></w:trPr>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($cat['category']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($cat['total_books']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "      <w:tc><w:p><w:r><w:t>" . htmlspecialchars($cat['available_books']) . "</w:t></w:r></w:p></w:tc>" . PHP_EOL;
                $xml .= "    </w:tr>" . PHP_EOL;
            }
            $xml .= "  </w:tbl>" . PHP_EOL;
        }

        $xml .= "</w:body></w:wordDocument>" . PHP_EOL;
        
        echo $xml;
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
</head>
<body class="library-reports">
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
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $serviceOpen ? 'true' : 'false' ?>" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= $serviceOpen ? 'false' : 'true' ?>">
                        <a href="dashboard/guidance_home.php" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="dashboard/librarian_home.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="dashboard/nurse_home.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="dashboard/ssc_head_home.php" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="dashboard/ssaa_home.php" data-tooltip="Alumni">
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
                <a href="dashboard/profile.php" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="dashboard/about.php" data-tooltip="About">
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
                <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow" style="color: #000000;">Library Reports</p>
                            <h1 style="color: #000000;">Comprehensive library analytics</h1>
                            <p class="dashboard-subtitle" style="color: var(--text-soft);">Monitor borrowing trends, user activity, inventory status, and financial performance across all library operations.</p>
                        </div>
                        <div class="dashboard-summary">
                            <div>
                                <span>Today's Check-ins</span>
                                <strong><?= htmlspecialchars($todayCheckins) ?></strong>
                            </div>
                            <div>
                                <span>Active Reservations</span>
                                <strong><?= htmlspecialchars($activeReservations) ?></strong>
                            </div>
                            <div>
                                <span>Overdue Items</span>
                                <strong style="color: #ef4444;"><?= htmlspecialchars($overdueItems) ?></strong>
                            </div>
                            <div>
                                <span>Pending Approvals</span>
                                <strong style="color: #f59e0b;"><?= htmlspecialchars($pendingApprovals) ?></strong>
                            </div>
                        </div>
                    </section>

                    <!-- Letterhead Template Section -->
                    <section class="dashboard-section">
                        <h2>Letterhead Template Management</h2>
                        <div class="metric-card" style="width: 100%; max-width: none; padding: 24px;">
                            <div style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid #22c55e;">
                                <h4 style="margin: 0 0 10px 0; color: #22c55e; font-size: 15px;"><i class="fa-solid fa-circle-info"></i> Upload Letterhead Template</h4>
                                <p style="margin: 0; font-size: 13px; color: #64748b;">Upload a PDF, Word, or Excel file with your school's letterhead. This template will be used as the background layer when exporting reports. The report data will be overlaid below the letterhead area.</p>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                <!-- Upload Box -->
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Upload Template File</label>
                                    <div style="border: 2px dashed #d6a84a; border-radius: 10px; padding: 24px; text-align: center; cursor: pointer; transition: all 0.3s;" id="templateDropZone">
                                        <div style="font-size: 32px; color: #d6a84a; margin-bottom: 8px;">
                                            <i class="fa-solid fa-cloud-arrow-up"></i>
                                        </div>
                                        <p style="margin: 0 0 8px 0; font-weight: 600; color: #1f2937;">Drag & drop your template here</p>
                                        <p style="margin: 0; font-size: 13px; color: #64748b;">or click to browse</p>
                                        <p style="margin: 8px 0 0 0; font-size: 12px; color: #999;">Accepted: PDF, DOC, DOCX, XLS, XLSX (Max 10MB)</p>
                                        <input type="file" id="templateFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx" style="display: none;">
                                    </div>
                                    <div id="uploadProgress" style="display: none; margin-top: 10px;">
                                        <div style="background: #e2e8f0; border-radius: 4px; height: 6px; overflow: hidden;">
                                            <div id="progressBar" style="background: linear-gradient(90deg, var(--deep-maroon), var(--soft-gold)); height: 100%; width: 0%; transition: width 0.3s;"></div>
                                        </div>
                                        <small style="color: #64748b; display: block; margin-top: 4px;">Uploading...</small>
                                    </div>
                                </div>

                                <!-- Current Template -->
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Current Template</label>
                                    <div style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 16px; background: #f8fafc; min-height: 100px; display: flex; align-items: center; justify-content: center;">
                                        <div id="currentTemplateInfo" style="text-align: center;">
                                            <p style="margin: 0; color: #64748b; font-size: 13px;">No template uploaded yet</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="uploadMessage" style="display: none; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px;">
                                <!-- Message will appear here -->
                            </div>
                        </div>
                    </section>

                    <!-- Export Reports Section -->
                    <section class="dashboard-section">
                        <h2>Export Reports</h2>
                        <div class="metric-card" style="width: 100%; max-width: none; padding: 24px;">
                            <div style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                                <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                                <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                    <li><strong>Fines:</strong> Student fine records with payment status</li>
                                    <li><strong>Books:</strong> Top borrowed books in the last 30 days</li>
                                    <li><strong>Check-ins:</strong> Library visit records for the last 30 days</li>
                                    <li><strong>Others:</strong> Department usage and book category summaries</li>
                                </ul>
                                <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Bachelor and ladderized course pairs are grouped together when exporting fines.</p>
                            </div>
                            <form method="post" style="width: 100%;">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                    <div>
                                        <label for="report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                        <select name="report_type" id="report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                            <option value="fines">Fines Report</option>
                                            <option value="books">Books Report</option>
                                            <option value="checkins">Check-ins Report</option>
                                            <option value="others">Other Reports (Department & Categories)</option>
                                        </select>
                                    </div>
                                    <div class="course-filter-group" style="display: none;">
                                        <label for="course_filter" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Course Group</label>
                                        <select name="course_filter" id="course_filter" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                            <option value="all">All Courses</option>
                                            <option value="cs_act">Bachelor of Science in Computer Science / Associate in Computer Technology</option>
                                            <option value="bba_abk">Bachelor of Science in Business Administration / Associate in Business Knowledge</option>
                                            <option value="bshm_ahm">Bachelor of Science in Hospitality Management / Associate in Hospitality Management</option>
                                            <option value="bstm_atm">Bachelor of Science in Tourism Management / Associate in Tourism Management</option>
                                            <option value="bsa">Bachelor of Science in Accountancy</option>
                                            <option value="beed">Bachelor in Elementary Education</option>
                                            <option value="bscrim">Bachelor of Science in Criminology</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                        <select name="export_format" id="export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                            <option value="excel">Excel (CSV)</option>
                                            <option value="docs">Word Document</option>
                                            <option value="pdf">PDF</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Use Letterhead Template</label>
                                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8fafc; border-radius: 10px; border: 2px solid #e2e8f0;">
                                            <input type="checkbox" id="useTemplate" name="use_template" value="1" style="width: 18px; height: 18px; cursor: pointer;">
                                            <label for="useTemplate" style="margin: 0; cursor: pointer; font-size: 13px; color: #475569;">
                                                Apply uploaded letterhead to report
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: flex-start;">
                                    <button type="submit" name="export_report" value="1" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px;">
                                        <i class="fa-solid fa-download"></i>
                                        Export Report
                                    </button>
                                </div>
                            </form>
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

            function toggleCourseFilter() {
                if (!reportTypeSelect || !courseFilterGroup) {
                    return;
                }
                courseFilterGroup.style.display = reportTypeSelect.value === 'fines' ? 'block' : 'none';
            }

            if (reportTypeSelect) {
                reportTypeSelect.addEventListener('change', toggleCourseFilter);
                toggleCourseFilter();
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

    <!-- Template Upload Handler -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const templateDropZone = document.getElementById('templateDropZone');
            const fileInput = document.getElementById('templateFileInput');
            const uploadProgress = document.getElementById('uploadProgress');
            const progressBar = document.getElementById('progressBar');
            const uploadMessage = document.getElementById('uploadMessage');
            const currentTemplateInfo = document.getElementById('currentTemplateInfo');
            const useTemplateCheckbox = document.getElementById('useTemplate');

            // Load current template info on page load
            loadTemplateList();

            // Handle drop zone click
            if (templateDropZone) {
                templateDropZone.addEventListener('click', () => fileInput.click());

                // Drag and drop
                templateDropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    templateDropZone.style.backgroundColor = '#fff3cd';
                    templateDropZone.style.borderColor = '#ffc107';
                });

                templateDropZone.addEventListener('dragleave', () => {
                    templateDropZone.style.backgroundColor = 'transparent';
                    templateDropZone.style.borderColor = '#d6a84a';
                });

                templateDropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    templateDropZone.style.backgroundColor = 'transparent';
                    templateDropZone.style.borderColor = '#d6a84a';

                    if (e.dataTransfer.files.length > 0) {
                        handleFileUpload(e.dataTransfer.files[0]);
                    }
                });
            }

            // Handle file input change
            if (fileInput) {
                fileInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        handleFileUpload(e.target.files[0]);
                    }
                });
            }

            function handleFileUpload(file) {
                // Validate file type
                const allowedTypes = ['application/pdf', 'application/msword', 
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                
                if (!allowedTypes.includes(file.type)) {
                    showMessage('❌ Invalid file type. Please upload PDF, DOC, DOCX, XLS, or XLSX.', 'error');
                    return;
                }

                // Validate file size (10MB)
                if (file.size > 10 * 1024 * 1024) {
                    showMessage('❌ File size exceeds 10MB limit.', 'error');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('template_file', file);
                formData.append('template_name', file.name.split('.')[0]);

                uploadProgress.style.display = 'block';
                progressBar.style.width = '0%';

                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                    }
                });

                xhr.addEventListener('load', () => {
                    uploadProgress.style.display = 'none';
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            showMessage('✅ Template uploaded successfully!', 'success');
                            loadTemplateList();
                            useTemplateCheckbox.checked = true;
                            fileInput.value = '';
                        } else {
                            showMessage('❌ ' + (response.message || 'Upload failed'), 'error');
                        }
                    } catch (e) {
                        showMessage('❌ Error processing response', 'error');
                    }
                });

                xhr.addEventListener('error', () => {
                    uploadProgress.style.display = 'none';
                    showMessage('❌ Upload failed. Please try again.', 'error');
                });

                xhr.open('POST', 'includes/api_letterhead_templates.php');
                xhr.send(formData);
            }

            function updateExportFormatDropdown(templateFilename) {
                const formatDropdown = document.getElementById('export_format');
                const ext = templateFilename.split('.').pop().toLowerCase();
                
                // Clear and rebuild dropdown based on template type
                formatDropdown.innerHTML = '';
                
                // 🔥 FIX: Do NOT disable the field (disabled fields aren't sent in POST)
                // Instead, just show locked status visually and let backend auto-detect format
                if (ext === 'pdf') {
                    formatDropdown.innerHTML = '<option value="pdf" selected>PDF (Locked to Template Format)</option>';
                } else if (ext === 'docx' || ext === 'doc') {
                    formatDropdown.innerHTML = '<option value="docs" selected>Word Document (Locked to Template Format)</option>';
                } else if (ext === 'xlsx' || ext === 'xls') {
                    formatDropdown.innerHTML = '<option value="excel" selected>Excel (Locked to Template Format)</option>';
                }
                // Keep enabled so it gets submitted in POST (backend will auto-detect anyway)
                formatDropdown.style.opacity = '0.8';
                formatDropdown.style.backgroundColor = '#f0fdf4';
                formatDropdown.style.borderColor = '#22c55e';
                formatDropdown.style.borderWidth = '2px';
            }
            
            function resetExportFormatDropdown() {
                const formatDropdown = document.getElementById('export_format');
                formatDropdown.innerHTML = `
                    <option value="excel">Excel (CSV)</option>
                    <option value="docs">Word Document</option>
                    <option value="pdf">PDF</option>
                `;
                formatDropdown.style.opacity = '1';
                formatDropdown.style.backgroundColor = '#ffffff';
                formatDropdown.style.borderColor = '';
                formatDropdown.style.borderWidth = '1px';
            }
            
            function loadTemplateList() {
                fetch('includes/api_letterhead_templates.php?action=list')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.templates && data.templates.length > 0) {
                            const latest = data.templates[0];
                            const sizeKB = (latest.size / 1024).toFixed(2);
                            const ext = latest.filename.split('.').pop().toUpperCase();
                            
                            // Update export format dropdown to match template type
                            updateExportFormatDropdown(latest.filename);
                            
                            currentTemplateInfo.innerHTML = `
                                <div style="text-align: center;">
                                    <div style="font-size: 24px; color: #22c55e; margin-bottom: 8px;">
                                        <i class="fa-solid fa-file-check"></i>
                                    </div>
                                    <p style="margin: 0 0 4px 0; font-weight: 600; color: #1f2937;">
                                        ${latest.filename}
                                    </p>
                                    <p style="margin: 0; font-size: 12px; color: #64748b;">
                                        <strong>${ext}</strong> • ${sizeKB} KB
                                    </p>
                                    <p style="margin: 8px 0 0 0; font-size: 11px; color: #999;">
                                        Uploaded: ${new Date(latest.modified).toLocaleDateString()}
                                    </p>
                                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #800000; font-weight: 600;">
                                        ✓ Export format locked to ${ext}
                                    </p>
                                    <button type="button" onclick="deleteTemplate('${latest.filename}')" style="margin-top: 8px; padding: 6px 12px; background: #ef4444; color: white; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">
                                        Delete Template
                                    </button>
                                </div>
                            `;
                        } else {
                            currentTemplateInfo.innerHTML = `<p style="margin: 0; color: #64748b; font-size: 13px;">No template uploaded yet</p>`;
                            useTemplateCheckbox.checked = false;
                            // Reset dropdown when no template
                            resetExportFormatDropdown();
                        }
                    })
                    .catch(error => console.error('Error loading templates:', error));
            }

            function showMessage(message, type) {
                uploadMessage.innerHTML = message;
                uploadMessage.style.display = 'block';
                uploadMessage.style.backgroundColor = type === 'success' ? '#dcfce7' : '#fee2e2';
                uploadMessage.style.borderLeft = '4px solid ' + (type === 'success' ? '#22c55e' : '#ef4444');
                uploadMessage.style.color = type === 'success' ? '#166534' : '#991b1b';

                if (type === 'success') {
                    setTimeout(() => {
                        uploadMessage.style.display = 'none';
                    }, 4000);
                }
            }

            window.deleteTemplate = function(filename) {
                if (!confirm('Are you sure you want to delete this template?')) return;

                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('filename', filename);

                fetch('includes/api_letterhead_templates.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage('✅ Template deleted successfully', 'success');
                        loadTemplateList();
                    } else {
                        showMessage('❌ ' + (data.message || 'Delete failed'), 'error');
                    }
                })
                .catch(error => {
                    showMessage('❌ Error deleting template', 'error');
                });
            };
        });
    </script>
    <script src="assets/js/app.js" defer></script>
    <?php include 'AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
