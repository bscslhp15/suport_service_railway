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

function ensureAlumniCareerMilestonesSchema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ssaa_alumni_career_milestones (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        alumni_id INT(10) UNSIGNED NOT NULL,
        milestone_type VARCHAR(100) NOT NULL,
        title VARCHAR(150) NOT NULL,
        milestone_date DATE NOT NULL,
        company VARCHAR(150) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
        PRIMARY KEY (id),
        INDEX (alumni_id),
        FOREIGN KEY (alumni_id) REFERENCES ssaa_alumni(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columnCheck = $pdo->query(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS " .
        "WHERE TABLE_SCHEMA = DATABASE() " .
        "  AND TABLE_NAME = 'ssaa_alumni_career_milestones' " .
        "  AND COLUMN_NAME = 'alumni_id'"
    )->fetchColumn();

    if (!$columnCheck) {
        $pdo->exec("ALTER TABLE ssaa_alumni_career_milestones ADD COLUMN alumni_id INT(10) UNSIGNED NOT NULL AFTER id");
    }
}

function normalize_alumni_course_value(?string $course): string {
    $course = trim((string) ($course ?? ''));
    if ($course === '') {
        return '';
    }

    $course = preg_replace('/\s+(?:1st|2nd|3rd|4th|graduate|[1-4])\s*$/i', '', $course);
    $course = preg_replace('/\s*(?:\/|-|–|—)\s*(?:1st|2nd|3rd|4th|graduate|[1-4])\s*$/i', '', $course);
    $course = preg_replace('/\s+\d+\s*$/', '', $course);
    $course = preg_replace('/\s+/', ' ', $course);

    return mb_strtolower($course, 'UTF-8');
}

function ensureAlumniExtraColumns($pdo) {
    $columns = [
        'achievements' => 'TEXT DEFAULT NULL',
        'quote' => 'TEXT DEFAULT NULL',
        'profile_image' => 'VARCHAR(255) DEFAULT NULL',
        'ladderized_course' => "VARCHAR(10) DEFAULT NULL",
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME DEFAULT NULL',
        'archived_by' => 'INT(11) DEFAULT NULL'
    ];

    foreach ($columns as $column => $definition) {
        $columnCheck = $pdo->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS " .
            "WHERE TABLE_SCHEMA = DATABASE() " .
            "  AND TABLE_NAME = 'ssaa_alumni' " .
            "  AND COLUMN_NAME = '$column'"
        )->fetchColumn();

        if (!$columnCheck) {
            $pdo->exec("ALTER TABLE ssaa_alumni ADD COLUMN `$column` $definition");
        }
    }
}

function save_alumni_profile_image_upload(array $file): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed, true)) {
        return null;
    }

    $baseDir = __DIR__ . '/../IMG ASSETS/alumni_profiles';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('alumni_', true) . '.' . strtolower($ext);
    $destination = $baseDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }

    return '../IMG ASSETS/alumni_profiles/' . $filename;
}

ensureAlumniCareerMilestonesSchema($pdo);
ensureAlumniExtraColumns($pdo);

// Get alumni statistics
$alumniStats = $pdo->query("
    SELECT
        COUNT(*) as total_alumni,
        SUM(CASE WHEN employment_status = 'employed' THEN 1 ELSE 0 END) as employed_count,
        SUM(CASE WHEN employment_status = 'unemployed' THEN 1 ELSE 0 END) as unemployed_count,
        SUM(CASE WHEN employment_status = 'unknown' THEN 1 ELSE 0 END) as unknown_count
    FROM ssaa_alumni
    WHERE is_archived = 0 OR is_archived IS NULL
")->fetch(PDO::FETCH_ASSOC);

// Get recent alumni additions
$recentAlumni = $pdo->query("
    SELECT a.*, u.full_name as student_name
    FROM ssaa_alumni a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Get employment statistics by course
$courseStats = $pdo->query("
    SELECT course, COUNT(*) as count
    FROM ssaa_alumni
    WHERE course IS NOT NULL AND course != ''
    GROUP BY course
    ORDER BY count DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Compute dynamic career analytics for the Career Tracking tab
$avgEmploymentTime = $pdo->query(
    "SELECT AVG(TIMESTAMPDIFF(MONTH, STR_TO_DATE(CONCAT(graduation_year, '-01-01'), '%Y-%m-%d'), employment_date)) AS avg_months " .
    "FROM ssaa_alumni " .
    "WHERE employment_date IS NOT NULL " .
    "  AND graduation_year IS NOT NULL " .
    "  AND graduation_year != '' " .
    "  AND employment_date != '0000-00-00'"
)->fetch(PDO::FETCH_ASSOC);
$avgEmploymentTimeMonths = $avgEmploymentTime['avg_months'] ? round($avgEmploymentTime['avg_months'], 1) : null;

$totalAlumniForRates = $alumniStats['total_alumni'] > 0 ? (int)$alumniStats['total_alumni'] : 0;
$promotionCount = $pdo->query(
    "SELECT COUNT(DISTINCT alumni_id) AS count " .
    "FROM ssaa_alumni_career_milestones " .
    "WHERE milestone_type = 'Promotion'"
)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$furtherEducationCount = $pdo->query(
    "SELECT COUNT(DISTINCT alumni_id) AS count " .
    "FROM ssaa_alumni_career_milestones " .
    "WHERE milestone_type = 'Further Education'"
)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
$entrepreneurCount = $pdo->query(
    "SELECT COUNT(*) AS count " .
    "FROM ssaa_alumni " .
    "WHERE employment_status = 'Entrepreneur'"
)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$promotionRate = $totalAlumniForRates > 0 ? round(($promotionCount / $totalAlumniForRates) * 100) : 0;
$educationRate = $totalAlumniForRates > 0 ? round(($furtherEducationCount / $totalAlumniForRates) * 100) : 0;
$entrepreneurRate = $totalAlumniForRates > 0 ? round(($entrepreneurCount / $totalAlumniForRates) * 100) : 0;

$topIndustries = $pdo->query(
    "SELECT industry, COUNT(*) AS count " .
    "FROM ssaa_alumni " .
    "WHERE industry IS NOT NULL AND industry != '' " .
    "GROUP BY industry " .
    "ORDER BY count DESC " .
    "LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$popularCertifications = $pdo->query(
    "SELECT title, COUNT(*) AS count " .
    "FROM ssaa_alumni_career_milestones " .
    "WHERE milestone_type = 'Certification' " .
    "  AND title IS NOT NULL " .
    "  AND title != '' " .
    "GROUP BY title " .
    "ORDER BY count DESC " .
    "LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$salaryLevels = $pdo->query(
    "SELECT job_level, COUNT(*) AS count " .
    "FROM ssaa_alumni " .
    "WHERE job_level IS NOT NULL AND job_level != '' " .
    "GROUP BY job_level " .
    "ORDER BY FIELD(job_level, 'Entry Level', 'Junior', 'Mid Level', 'Senior', 'Management', 'Executive'), count DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$currentPage = basename($_SERVER['PHP_SELF']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'search_graduates':
                $query = $_POST['query'] ?? '';
                $stmt = $pdo->prepare("
                    SELECT sp.id, sp.student_name, sp.course, sp.graduation_year
                    FROM ssaa_student_progression sp
                    WHERE sp.graduation_year IS NOT NULL
                    AND (sp.student_name LIKE ? OR sp.course LIKE ? OR sp.student_id LIKE ?)
                    ORDER BY sp.graduation_year DESC, sp.student_name
                    LIMIT 10
                ");
                $searchTerm = "%$query%";
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

            case 'import_graduate':
                $studentId = $_POST['student_id'];
                $includeAcademic = isset($_POST['academic']) && $_POST['academic'] === 'true';
                $includeContact = isset($_POST['contact']) && $_POST['contact'] === 'true';

                // Get student data
                $stmt = $pdo->prepare("SELECT * FROM ssaa_student_progression WHERE id = ?");
                $stmt->execute([$studentId]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    echo json_encode(['success' => false, 'message' => 'Student not found']);
                    exit;
                }

                // Check if already exists
                $stmt = $pdo->prepare("SELECT id FROM ssaa_alumni WHERE student_id = ?");
                $stmt->execute([$student['student_id']]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Student already imported as alumni']);
                    exit;
                }

                // Import alumni
                $stmt = $pdo->prepare("
                    INSERT INTO ssaa_alumni (
                        full_name, email, student_id, course, graduation_year,
                        contact_number, address, academic_performance,
                        employment_status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unknown', NOW())
                ");

                $academicData = $includeAcademic ? json_encode([
                    'gpa' => $student['gpa'] ?? null,
                    'honors' => $student['honors'] ?? null,
                    'achievements' => $student['achievements'] ?? null
                ]) : null;

                $stmt->execute([
                    $student['student_name'],
                    $student['email'] ?? '',
                    $student['student_id'],
                    $student['course'],
                    $student['graduation_year'],
                    $includeContact ? ($student['contact_number'] ?? '') : '',
                    $includeContact ? ($student['address'] ?? '') : '',
                    $academicData
                ]);

                echo json_encode(['success' => true]);
                break;

            case 'add_manual_alumni':
                $fullName = trim($_POST['alumni-full-name'] ?? '');
                $email = trim($_POST['alumni-email'] ?? '');
                $studentId = trim($_POST['alumni-student-id'] ?? '');
                $course = trim($_POST['alumni-course'] ?? '');
                $graduationYear = trim($_POST['alumni-graduation-year'] ?? '');
                $contactNumber = trim($_POST['alumni-contact'] ?? '');
                $quote = trim($_POST['alumni-quote'] ?? '');
                $achievementsText = trim($_POST['achievements'] ?? '');
                $profileImage = null;

                if (!empty($_FILES['alumni-profile-photo']['tmp_name'])) {
                    $profileImage = save_alumni_profile_image_upload($_FILES['alumni-profile-photo']);
                }

                if ($fullName === '' || $email === '' || $course === '' || $graduationYear === '') {
                    echo json_encode(['success' => false, 'message' => 'Please complete all required alumni fields.']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO ssaa_alumni (
                        full_name, email, student_id, course, graduation_year,
                        contact_number, achievements, quote, profile_image,
                        employment_status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unknown', NOW())
                ");
                $stmt->execute([
                    $fullName,
                    $email,
                    $studentId !== '' ? $studentId : null,
                    $course,
                    $graduationYear,
                    $contactNumber !== '' ? $contactNumber : null,
                    $achievementsText !== '' ? $achievementsText : null,
                    $quote !== '' ? $quote : null,
                    $profileImage
                ]);
                echo json_encode(['success' => true]);
                break;

            case 'search_alumni':
                $query = $_POST['query'] ?? '';
                $stmt = $pdo->prepare("
                    SELECT id, full_name, course, graduation_year, employment_status,
                           company_name AS company, job_title, email, contact_number
                    FROM ssaa_alumni
                    WHERE full_name LIKE ? OR email LIKE ? OR student_id LIKE ? OR company_name LIKE ? OR job_title LIKE ?
                    ORDER BY full_name
                    LIMIT 10
                ");
                $searchTerm = "%$query%";
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

            case 'get_alumni_details':
                $alumniId = $_POST['alumni_id'];
                $stmt = $pdo->prepare("SELECT id, full_name, email, student_id, course, graduation_year, contact_number, employment_status, company_name AS company, job_title, industry, job_level, salary_range, employment_date, location, job_description, achievements, quote, ladderized_course, profile_image FROM ssaa_alumni WHERE id = ?");
                $stmt->execute([$alumniId]);
                $alumni = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$alumni) {
                    echo json_encode(['success' => false, 'message' => 'Alumni record not found']);
                    break;
                }
                echo json_encode(['success' => true, 'alumni' => $alumni]);
                break;

            case 'update_alumni':
                $alumniId = $_POST['alumni-id'] ?? null;
                $fullName = trim($_POST['full_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $studentId = trim($_POST['student_id'] ?? '');
                $course = trim($_POST['course'] ?? '');
                $graduationYear = trim($_POST['graduation_year'] ?? '');
                $contactNumber = trim($_POST['contact_number'] ?? '');
                $companyName = trim($_POST['company_name'] ?? '');
                $jobTitle = trim($_POST['job_title'] ?? '');
                $achievementsText = trim($_POST['achievements'] ?? '');
                $quote = trim($_POST['quote'] ?? '');

                if (!$alumniId || $fullName === '' || $email === '' || $course === '' || $graduationYear === '') {
                    echo json_encode(['success' => false, 'message' => 'Please complete required alumni fields.']);
                    exit;
                }

                    $ladderized = trim($_POST['ladderized'] ?? '');
                    $profileImage = null;
                    if (!empty($_FILES['edit-alumni-profile-image']['tmp_name'])) {
                        $profileImage = save_alumni_profile_image_upload($_FILES['edit-alumni-profile-image']);
                        if ($profileImage === null) {
                            echo json_encode(['success' => false, 'message' => 'Please upload a valid JPG, PNG, or WEBP image.']);
                            exit;
                        }
                    }

                    $stmt = $pdo->prepare("\n                    UPDATE ssaa_alumni SET\n                        full_name = ?, email = ?, student_id = ?, course = ?,\n                        graduation_year = ?, contact_number = ?, company_name = ?, job_title = ?,\n                        achievements = ?, quote = ?, ladderized_course = ?, profile_image = COALESCE(?, profile_image), last_updated = NOW()\n                    WHERE id = ?\n                ");
                $stmt->execute([
                    $fullName,
                    $email,
                    $studentId !== '' ? $studentId : null,
                    $course,
                    $graduationYear,
                    $contactNumber !== '' ? $contactNumber : null,
                    $companyName !== '' ? $companyName : null,
                    $jobTitle !== '' ? $jobTitle : null,
                    $achievementsText !== '' ? $achievementsText : null,
                    $quote !== '' ? $quote : null,
                    $ladderized !== '' ? $ladderized : null,
                    $profileImage,
                    $alumniId
                ]);

                echo json_encode(['success' => true]);
                break;

            case 'delete_alumni':
                $alumniId = $_POST['alumni_id'] ?? null;
                if (!$alumniId) {
                    echo json_encode(['success' => false, 'message' => 'Alumni id is required to delete.']);
                    exit;
                }
                $stmt = $pdo->prepare("DELETE FROM ssaa_alumni WHERE id = ?");
                $stmt->execute([$alumniId]);
                echo json_encode(['success' => true]);
                break;

            case 'update_employment':
                $alumniId = $_POST['employment-alumni-id'] ?? null;
                $stmt = $pdo->prepare("
                    UPDATE ssaa_alumni SET
                        employment_status = ?,
                        company_name = ?,
                        job_title = ?,
                        industry = ?,
                        job_level = ?,
                        salary_range = ?,
                        employment_date = ?,
                        location = ?,
                        job_description = ?,
                        last_updated = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['employment-status'] ?? null,
                    $_POST['company-name'] ?? 'N/A',
                    $_POST['job-title'] ?? 'N/A',
                    $_POST['industry'] ?? 'N/A',
                    $_POST['job-level'] ?? 'N/A',
                    $_POST['salary-range'] ?? 'N/A',
                    $_POST['employment-date'] !== '' ? $_POST['employment-date'] : null,
                    $_POST['location'] ?? 'N/A',
                    $_POST['job-description'] ?? 'N/A',
                    $alumniId
                ]);
                echo json_encode(['success' => true]);
                break;

            case 'get_employment_history':
                $stmt = $pdo->query("
                    SELECT a.full_name, a.employment_status, a.company_name AS company, a.job_title, a.last_updated AS updated_at
                    FROM ssaa_alumni a
                    WHERE a.last_updated IS NOT NULL
                    ORDER BY a.last_updated DESC
                    LIMIT 10
                ");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

            case 'get_contact_preferences':
                $alumniId = $_POST['alumni_id'];
                $stmt = $pdo->prepare("
                    SELECT email_updates, job_opportunities, events, newsletter,
                           surveys, mentoring, contact_frequency
                    FROM ssaa_alumni WHERE id = ?
                ");
                $stmt->execute([$alumniId]);
                $prefs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                echo json_encode(['success' => true, 'preferences' => $prefs]);
                break;

            case 'update_contact_preferences':
                $alumniId = $_POST['contact-alumni-id'];
                $stmt = $pdo->prepare("
                    UPDATE ssaa_alumni SET
                        email_updates = ?,
                        job_opportunities = ?,
                        events = ?,
                        newsletter = ?,
                        surveys = ?,
                        mentoring = ?,
                        contact_frequency = ?,
                        last_updated = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    isset($_POST['pref-email-updates']) ? 1 : 0,
                    isset($_POST['pref-job-opportunities']) ? 1 : 0,
                    isset($_POST['pref-events']) ? 1 : 0,
                    isset($_POST['pref-newsletter']) ? 1 : 0,
                    isset($_POST['pref-surveys']) ? 1 : 0,
                    isset($_POST['pref-mentoring']) ? 1 : 0,
                    $_POST['contact-frequency'],
                    $alumniId
                ]);
                echo json_encode(['success' => true]);
                break;

            case 'send_bulk_communication':
                $type = $_POST['type'];
                $audience = $_POST['audience'];
                $subject = $_POST['subject'];
                $message = $_POST['message'];

                // Build query based on audience
                $whereClause = "WHERE 1=1";
                $params = [];

                switch ($audience) {
                    case 'employed':
                        $whereClause .= " AND employment_status = 'employed'";
                        break;
                    case 'unemployed':
                        $whereClause .= " AND employment_status = 'unemployed'";
                        break;
                    case 'by-course':
                        $whereClause .= " AND course = ?";
                        $params[] = $_POST['course'];
                        break;
                    case 'by-graduation-year':
                        // This would need additional implementation
                        break;
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ssaa_alumni $whereClause");
                $stmt->execute($params);
                $count = $stmt->fetch()['count'];

                // Log communication (in a real system, this would send actual emails/SMS)
                $stmt = $pdo->prepare("
                    INSERT INTO ssaa_alumni_contact_history (
                        communication_type, subject, message, recipient_count, sent_by, sent_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$type, $subject, $message, $count, $user['id']]);

                echo json_encode(['success' => true, 'recipient_count' => $count]);
                break;

            case 'get_communication_history':
                $stmt = $pdo->query("
                    SELECT communication_type as type, subject, recipient_count, sent_at
                    FROM ssaa_alumni_contact_history
                    ORDER BY sent_at DESC
                    LIMIT 10
                ");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

            case 'get_all_alumni':
                $query = $_POST['query'] ?? '';
                $year = $_POST['year'] ?? '';
                $status = $_POST['status'] ?? '';
                $course = $_POST['course'] ?? '';

                $where = 'WHERE (is_archived = 0 OR is_archived IS NULL)';
                $params = [];

                if ($query !== '') {
                    $where .= ' AND (full_name LIKE ? OR email LIKE ? OR course LIKE ? OR job_title LIKE ? OR company_name LIKE ?)';
                    $searchTerm = "%$query%";
                    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                }

                if ($course !== '') {
                    $where .= ' AND course = ?';
                    $params[] = $course;
                }

                if ($year !== '') {
                    $where .= ' AND graduation_year = ?';
                    $params[] = $year;
                }

                if ($status !== '') {
                    $where .= ' AND employment_status = ?';
                    $params[] = $status;
                }

                $stmt = $pdo->prepare("SELECT 
                    id, full_name, student_id, course, graduation_year,
                    gender, civil_status, dob, nationality,
                    personal_email, contact_number, permanent_address, location,
                    employment_status, company_name AS company, job_title, 
                    industry, salary_range, job_description
                FROM ssaa_alumni $where ORDER BY graduation_year DESC, full_name LIMIT 200");
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

            case 'get_alumni_info':
                // Fetch alumni with onboarding profile information
                $query = $_POST['query'] ?? '';
                $year = $_POST['year'] ?? '';
                $course = $_POST['course'] ?? '';

                $where = 'WHERE (is_archived = 0 OR is_archived IS NULL)';
                $params = [];

                if ($query !== '') {
                    $where .= ' AND (full_name LIKE ? OR email LIKE ? OR student_id LIKE ? OR personal_email LIKE ?)';
                    $searchTerm = "%$query%";
                    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                }

                if ($course !== '') {
                    $where .= ' AND course = ?';
                    $params[] = $course;
                }

                if ($year !== '') {
                    $where .= ' AND graduation_year = ?';
                    $params[] = $year;
                }

                $stmt = $pdo->prepare("
                    SELECT 
                        id, full_name, student_id, course, graduation_year, 
                        gender, civil_status, dob, permanent_address, personal_email, contact_number, nationality,
                        employment_status, company_name AS company, industry, salary_range, job_description, location
                    FROM ssaa_alumni 
                    $where 
                    ORDER BY graduation_year DESC, full_name 
                    LIMIT 200
                ");
                $stmt->execute($params);
                $alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $normalizedCourseFilter = normalize_alumni_course_value($course);
                if ($normalizedCourseFilter !== '') {
                    $alumni = array_values(array_filter($alumni, function (array $alumnus) use ($normalizedCourseFilter): bool {
                        return normalize_alumni_course_value($alumnus['course'] ?? '') === $normalizedCourseFilter;
                    }));
                }

                foreach ($alumni as &$a) {
                    if (!empty($a['course'])) {
                        $a['course'] = preg_replace('/\s+([1-4])$/', '', $a['course']);
                    }
                }

                echo json_encode($alumni);
                break;

            case 'archive_alumni_by_filter':
                $course = trim($_POST['course'] ?? '');
                $year = trim($_POST['year'] ?? '');
                $query = 'SELECT id, course, graduation_year FROM ssaa_alumni WHERE (is_archived = 0 OR is_archived IS NULL)';
                $params = [];

                if ($year !== '' && $year !== 'All Years') {
                    $query .= ' AND graduation_year = ?';
                    $params[] = $year;
                }

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $alumniRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $targetIds = [];
                foreach ($alumniRows as $alumni) {
                    $matchesCourse = true;
                    if ($course !== '' && $course !== 'All Courses') {
                        $matchesCourse = normalize_alumni_course_value($alumni['course'] ?? '') === normalize_alumni_course_value($course);
                    }

                    if ($matchesCourse) {
                        $targetIds[] = (int) $alumni['id'];
                    }
                }

                if (empty($targetIds)) {
                    echo json_encode([
                        'success' => true,
                        'archived_count' => 0,
                        'message' => 'No matching alumni records were found to archive.'
                    ]);
                    break;
                }

                $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
                $updateSql = 'UPDATE ssaa_alumni SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE id IN (' . $placeholders . ')';
                $updateParams = [$user['id'] ?? 0, ...$targetIds];
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute($updateParams);

                echo json_encode([
                    'success' => true,
                    'archived_count' => $updateStmt->rowCount(),
                    'message' => 'Selected alumni records have been archived.'
                ]);
                break;

            case 'get_archived_alumni':
                $course = trim($_POST['course'] ?? '');
                $year = trim($_POST['year'] ?? '');
                $baseQuery = "
                    SELECT id, full_name, email, student_id, course, graduation_year, contact_number, employment_status, archived_at
                    FROM ssaa_alumni
                    WHERE is_archived = 1";
                $params = [];

                if ($year !== '' && $year !== 'All Years') {
                    $baseQuery .= ' AND graduation_year = ?';
                    $params[] = $year;
                }

                $stmt = $pdo->prepare($baseQuery . ' ORDER BY archived_at DESC, full_name ASC');
                $stmt->execute($params);
                $archivedAlumni = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($course !== '' && $course !== 'All Courses') {
                    $normalizedSearchCourse = normalize_alumni_course_value($course);
                    $archivedAlumni = array_values(array_filter($archivedAlumni, function (array $alumnus) use ($normalizedSearchCourse): bool {
                        return normalize_alumni_course_value($alumnus['course'] ?? '') === $normalizedSearchCourse;
                    }));
                }

                echo json_encode($archivedAlumni);
                break;

            case 'archive_alumni':
                $alumniId = (int) ($_POST['alumni_id'] ?? 0);
                if ($alumniId <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Alumni record not found.']);
                    break;
                }

                $stmt = $pdo->prepare(
                    'UPDATE ssaa_alumni
                     SET is_archived = 1, archived_at = NOW(), archived_by = ?
                     WHERE id = ? AND (is_archived = 0 OR is_archived IS NULL)'
                );
                $stmt->execute([$user['id'] ?? 0, $alumniId]);
                $archived = $stmt->rowCount() > 0;

                echo json_encode([
                    'success' => $archived,
                    'message' => $archived ? 'Alumni record archived successfully.' : 'Alumni record was not found or is already archived.'
                ]);
                break;

            case 'restore_archived_alumni':
                $alumniId = $_POST['alumni_id'] ?? null;
                if (!$alumniId) {
                    echo json_encode(['success' => false, 'message' => 'Alumni record not found.']);
                    break;
                }

                $stmt = $pdo->prepare('UPDATE ssaa_alumni SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = ?');
                $stmt->execute([$alumniId]);
                echo json_encode(['success' => true, 'message' => 'Alumni record restored.']);
                break;

            case 'get_batch_years':
                // Get graduation years with alumni count
                $stmt = $pdo->prepare("
                    SELECT DISTINCT graduation_year AS year, COUNT(*) as count 
                    FROM ssaa_alumni 
                    WHERE graduation_year IS NOT NULL
                      AND (is_archived = 0 OR is_archived IS NULL)
                    GROUP BY graduation_year 
                    ORDER BY graduation_year DESC
                ");
                $stmt->execute();
                echo json_encode(['years' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            case 'send_tracer_emails':
                // Send EmailJS tracer form invitations to alumni of a specific year
                $year = $_POST['year'] ?? '';
                if (!$year) {
                    echo json_encode(['success' => false, 'message' => 'Year is required']);
                    break;
                }

                $stmt = $pdo->prepare("SELECT id, full_name, personal_email, email FROM ssaa_alumni WHERE graduation_year = ? AND (is_archived = 0 OR is_archived IS NULL)");
                $stmt->execute([$year]);
                $alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($alumni)) {
                    echo json_encode(['success' => false, 'message' => 'No alumni found for this year']);
                    break;
                }

                // Note: Actual EmailJS sending would require client-side implementation with template_id
                // This just logs the action in database
                $sent_count = count($alumni);
                $stmt = $pdo->prepare("
                    INSERT INTO ssaa_alumni_batch_emails (batch_year, sent_by_user_id, alumni_count, sent_count, email_status) 
                    VALUES (?, ?, ?, ?, 'completed')
                ");
                $stmt->execute([$year, $user_id ?? 0, $sent_count, $sent_count]);

                echo json_encode(['success' => true, 'sent_count' => $sent_count]);
                break;

            case 'get_survey_questions':
                // Get survey questions for a specific section
                $section = $_POST['section'] ?? 'default';
                
                // Build WHERE clause to get both default and custom questions
                if ($section === 'default') {
                    // Show all default questions (employment_status = 'all')
                    $where = 'WHERE employment_status = "all"';
                } elseif ($section === 'employed') {
                    // Show default questions + questions specific to employed
                    $where = 'WHERE employment_status IN ("all", "employed")';
                } elseif ($section === 'self-employed') {
                    // Show default questions + questions specific to self-employed
                    $where = 'WHERE employment_status IN ("all", "self-employed")';
                } elseif ($section === 'unemployed') {
                    // Show default questions + questions specific to unemployed
                    $where = 'WHERE employment_status IN ("all", "unemployed")';
                } else {
                    $where = 'WHERE employment_status = "all"';
                }

                $stmt = $pdo->prepare("
                    SELECT * FROM ssaa_alumni_survey_questions 
                    $where 
                    ORDER BY is_default DESC, order_index ASC
                ");
                $stmt->execute();
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

            case 'add_survey_question':
                // Add custom survey question
                $question = $_POST['question'] ?? '';
                $type = $_POST['type'] ?? 'text';
                $employment_status = $_POST['employment_status'] ?? 'all';
                $data = $_POST['data'] ?? null;  // Can be choices (for multiple choice) or placeholder (for text)

                if (!$question) {
                    echo json_encode(['success' => false, 'message' => 'Question text is required']);
                    break;
                }

                // For multiple choice, data contains comma-separated choices
                // For text input, data contains placeholder text
                $choicesData = $data;  // Store as-is in answer_choices column

                // Get the next order_index
                $maxStmt = $pdo->prepare("SELECT IFNULL(MAX(order_index), 0) as max_order FROM ssaa_alumni_survey_questions");
                $maxStmt->execute();
                $maxOrder = $maxStmt->fetch(PDO::FETCH_ASSOC);
                $nextOrder = intval($maxOrder['max_order']) + 1;

                $stmt = $pdo->prepare("
                    INSERT INTO ssaa_alumni_survey_questions (question_text, question_type, employment_status, is_default, is_removable, answer_choices, order_index)
                    VALUES (?, ?, ?, 0, 1, ?, ?)
                ");
                $stmt->execute([$question, $type, $employment_status, $choicesData, $nextOrder]);

                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'employment_status' => $employment_status]);
                break;

            case 'remove_survey_question':
                // Remove custom survey question
                $id = $_POST['id'] ?? 0;
                
                $stmt = $pdo->prepare("SELECT is_removable FROM ssaa_alumni_survey_questions WHERE id = ?");
                $stmt->execute([$id]);
                $question = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$question || !$question['is_removable']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot remove this question']);
                    break;
                }

                $stmt = $pdo->prepare("DELETE FROM ssaa_alumni_survey_questions WHERE id = ?");
                $stmt->execute([$id]);

                echo json_encode(['success' => true]);
                break;

            case 'get_tracer_responses':
                // Get alumni tracer form responses
                $search = $_POST['search'] ?? '';
                $year = $_POST['year'] ?? '';
                $employment = $_POST['employment'] ?? '';

                $query = "
                    SELECT sar.id, sar.created_at, sa.full_name, sa.course, sa.graduation_year, 
                           sar.response_data
                    FROM ssaa_alumni_survey_responses sar
                    JOIN ssaa_alumni sa ON sar.alumni_id = sa.id
                    WHERE 1=1
                ";
                $params = [];

                if ($search) {
                    $query .= " AND (sa.full_name LIKE ? OR sa.personal_email LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }

                if ($year) {
                    $query .= " AND sa.graduation_year = ?";
                    $params[] = $year;
                }

                $query .= " ORDER BY sar.created_at DESC LIMIT 100";

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Extract employment_status from response_data
                $result = [];
                foreach ($responses as $r) {
                    $data = json_decode($r['response_data'], true);
                    $r['employment_status'] = $data['employment_status'] ?? 'N/A';
                    $result[] = $r;
                }

                if ($employment && !empty($result)) {
                    $result = array_filter($result, function($r) use ($employment) {
                        return $r['employment_status'] === $employment;
                    });
                }

                echo json_encode(array_values($result));
                break;

            case 'get_response_detail':
                // Get full details of a specific response
                $id = $_POST['id'] ?? 0;

                $stmt = $pdo->prepare("
                    SELECT sar.response_data, sa.full_name, sa.course, sa.graduation_year
                    FROM ssaa_alumni_survey_responses sar
                    JOIN ssaa_alumni sa ON sar.alumni_id = sa.id
                    WHERE sar.id = ?
                ");
                $stmt->execute([$id]);
                $response = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$response) {
                    echo json_encode(['success' => false, 'message' => 'Response not found']);
                    break;
                }

                $data = json_decode($response['response_data'], true);
                $data['full_name'] = $response['full_name'];
                $data['course'] = $response['course'];
                $data['graduation_year'] = $response['graduation_year'];

                echo json_encode(['success' => true, 'response' => $data]);
                break;

            case 'get_alumni_for_tracer':
                // Get alumni with personal email addresses for tracer invitation
                $year = $_POST['year'] ?? '';
                if (!$year) {
                    echo json_encode([]);
                    break;
                }

                $stmt = $pdo->prepare("
                    SELECT id, full_name, email, personal_email, course, graduation_year, student_id
                    FROM ssaa_alumni
                    WHERE graduation_year = ?
                      AND (is_archived = 0 OR is_archived IS NULL)
                      AND (personal_email IS NOT NULL AND personal_email != '')
                    ORDER BY full_name
                ");
                $stmt->execute([$year]);
                $alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode($alumni);
                break;

            case 'log_batch_emails':
                // Log batch email sending in database
                $year = $_POST['year'] ?? '';
                $count = $_POST['count'] ?? 0;

                if (!$year) {
                    echo json_encode(['success' => false]);
                    break;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO ssaa_alumni_batch_emails (batch_year, sent_by_user_id, alumni_count, sent_count, email_status)
                    VALUES (?, ?, ?, ?, 'completed')
                ");
                $stmt->execute([$year, $user_id ?? 0, $count, $count]);

                echo json_encode(['success' => true, 'batch_id' => $pdo->lastInsertId()]);
                break;

            case 'add_career_milestone':
                $stmt = $pdo->prepare("
                    INSERT INTO ssaa_alumni_career_milestones (
                        alumni_id, milestone_type, title, milestone_date, company, description, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $_POST['milestone-alumni-id'],
                    $_POST['milestone-type'],
                    $_POST['milestone-title'],
                    $_POST['milestone-date'],
                    $_POST['milestone-company'] ?: null,
                    $_POST['milestone-description'] ?: null
                ]);
                echo json_encode(['success' => true]);
                break;

            case 'get_career_milestones':
                $stmt = $pdo->query("
                    SELECT m.*, a.full_name as alumni_name
                    FROM ssaa_alumni_career_milestones m
                    JOIN ssaa_alumni a ON m.alumni_id = a.id
                    ORDER BY m.milestone_date DESC
                    LIMIT 10
                ");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Management | SSAA Management | PASS Support System</title>
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

        .content-panel {
            background: transparent;
            border: none;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-icon {
            font-size: 2.5rem;
            color: #800000;
            margin-bottom: 12px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 12px 0;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .alumni-actions {
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

        .alumni-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .alumni-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .alumni-icon {
            color: #800000;
            font-size: 1.5rem;
        }

        .alumni-content p {
            margin: 0 0 4px 0;
            font-size: 0.9rem;
        }

        .alumni-content small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .course-chart {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            align-items: center;
        }

        .course-bar {
            height: 20px;
            background: #800000;
            border-radius: 10px;
            position: relative;
        }

        .course-bar::after {
            content: attr(data-percentage) '%';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Tabbed Actions Styles */
        .tabbed-actions {
            margin: 24px 0;
        }

        .tab-buttons {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .tab-button {
            background: none;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            color: #6c757d;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .tab-button:hover {
            background: #f3f4f6;
            color: #800000;
        }

        .tab-button.active {
            color: white;
            background: linear-gradient(135deg, #800000, #a84a38);
            border-bottom-color: transparent;
            border-radius: 999px;
            box-shadow: 0 2px 8px rgba(120, 40, 40, 0.18);
        }

        .tab-panels {
            margin-top: 20px;
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        .panel-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .panel-header {
            padding: 24px;
            border-bottom: 1px solid #e5e7eb;
        }

        .panel-header h3 {
            margin: 0 0 8px 0;
            color: #2c3e50;
        }

        .panel-header p {
            margin: 0;
            color: #7f8c8d;
        }

        /* Search and Results Styles */
        .search-section {
            margin-bottom: 20px;
        }

        .search-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .search-section input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .search-section input:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }

        .search-results {
            margin-top: 12px;
            max-height: 300px;
            overflow-y: auto;
        }

        .search-result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 8px;
            background: #f8f9fa;
        }

        .alumni-table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }

        .alumni-info-table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        .alumni-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .alumni-table th,
        .alumni-table td {
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            text-align: left;
            font-size: 0.9rem;
            white-space: nowrap;
            min-width: 180px;
        }

        .alumni-table th {
            background: #f1f5f9;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .alumni-table,
            .alumni-table thead,
            .alumni-table tbody,
            .alumni-table th,
            .alumni-table td,
            .alumni-table tr {
                display: block;
                width: 100%;
            }

            .alumni-table thead { display: none; }

            .alumni-table tbody tr {
                display: flex;
                flex-direction: column;
                margin-bottom: 14px;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 6px 18px rgba(15,23,42,0.04);
            }

            .alumni-table td {
                padding: 10px 12px;
                border: none;
                border-bottom: 1px solid #f3f4f6;
                display: grid;
                grid-template-columns: max-content minmax(0, 1fr);
                column-gap: 12px;
                align-items: center;
                width: 100% !important;
                min-width: 0 !important;
                word-break: break-word !important;
                overflow-wrap: anywhere !important;
                white-space: normal !important;
            }

            .alumni-table td:last-child { border-bottom: none; }

            .alumni-table td:nth-child(1)::before { content: "Name"; font-weight:700; color: #6b7280; text-transform: none; }
            .alumni-table td:nth-child(2)::before { content: "Course"; font-weight:700; color: #6b7280; }
            .alumni-table td:nth-child(3)::before { content: "Graduation Year"; font-weight:700; color: #6b7280; }
            .alumni-table td:nth-child(4)::before { content: "Status"; font-weight:700; color: #6b7280; }
            .alumni-table td:nth-child(5)::before { content: "Actions"; font-weight:700; color: #6b7280; }

            .alumni-table td::before { display: inline-block; white-space: nowrap; }
        }

        @media (max-width: 425px) {
            .alumni-table td { padding: 8px 10px; column-gap: 8px; }
            .alumni-table tbody tr { margin-bottom: 12px; }
            .alumni-table td:nth-child(1)::before { content: "Name"; }
        }

        /* Alumni Info table: convert to label/value rows on small screens */
        @media (max-width: 768px) {
            #alumni-info-table,
            #alumni-info-table thead,
            #alumni-info-table tbody,
            #alumni-info-table th,
            #alumni-info-table td,
            #alumni-info-table tr {
                display: block;
                width: 100%;
            }

            #alumni-info-table thead { display: none; }

            #alumni-info-table tbody tr {
                margin-bottom: 14px;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 6px 18px rgba(15,23,42,0.04);
            }

            #alumni-info-table td {
                display: grid;
                grid-template-columns: 45% 55%;
                padding: 10px 12px;
                border-bottom: 1px solid #f3f4f6;
                align-items: start;
                gap: 8px;
                min-width: 0 !important;
                word-break: break-word !important;
                overflow-wrap: anywhere !important;
                white-space: normal !important;
            }

            /* label for each column - left side */
            #alumni-info-table td:nth-child(1)::before { content: "Full Name"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(2)::before { content: "Student ID"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(3)::before { content: "Course"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(4)::before { content: "Year Graduated"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(5)::before { content: "Gender"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(6)::before { content: "Civil Status"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(7)::before { content: "Date of Birth"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(8)::before { content: "Nationality"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(9)::before { content: "Personal Email"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(10)::before { content: "Mobile Number"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(11)::before { content: "Address"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(12)::before { content: "Status"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(13)::before { content: "Company / Title"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(14)::before { content: "Location"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(15)::before { content: "Industry"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(16)::before { content: "Salary Range"; font-weight:700; color:#6b7280; }
            #alumni-info-table td:nth-child(17)::before { content: "Job Description"; font-weight:700; color:#6b7280; }

            #alumni-info-table td::before { display: block; margin-bottom: 6px; }
            #alumni-info-table td:last-child { border-bottom: none; }
        }

        @media (max-width: 425px) {
            #alumni-info-table td { grid-template-columns: 40% 60%; padding: 8px 10px; }
        }

        /* Make the Edit Alumni card wider on small screens to use more horizontal space */
        @media (max-width: 768px) {
            /* expand the panel card inside the Edit Alumni tab to reduce narrow feeling */
            #edit-alumni-tab .panel-card {
                width: calc(100% + 32px);
                margin-left: -16px;
                margin-right: -16px;
                padding-left: 16px;
                padding-right: 16px;
                box-sizing: border-box;
                border-radius: 10px;
            }

            /* ensure inner table wrapper uses full width */
            #edit-alumni-tab .alumni-table-wrapper {
                padding-left: 0;
                padding-right: 0;
            }
        }

        @media (max-width: 425px) {
            #edit-alumni-tab .panel-card {
                width: calc(100% + 20px);
                margin-left: -10px;
                margin-right: -10px;
                padding-left: 12px;
                padding-right: 12px;
            }
        }

        /* Apply the same wider-card treatment to the other alumni tabs */
        @media (max-width: 768px) {
            #add-alumni-tab .panel-card,
            #update-employment-tab .panel-card,
            #all-alumni-tab .panel-card,
            #alumni-info-tab .panel-card,
            #alumni-tracer-tab .panel-card {
                width: calc(100% + 32px);
                margin-left: -16px;
                margin-right: -16px;
                padding-left: 16px;
                padding-right: 16px;
                box-sizing: border-box;
                border-radius: 10px;
            }

            /* Ensure inner wrappers use full width */
            #add-alumni-tab .alumni-table-wrapper,
            #update-employment-tab .alumni-table-wrapper,
            #all-alumni-tab .alumni-table-wrapper,
            #alumni-info-tab .alumni-table-wrapper,
            #alumni-tracer-tab .alumni-table-wrapper {
                padding-left: 0;
                padding-right: 0;
            }
        }

        @media (max-width: 425px) {
            #add-alumni-tab .panel-card,
            #update-employment-tab .panel-card,
            #all-alumni-tab .panel-card,
            #alumni-info-tab .panel-card,
            #alumni-tracer-tab .panel-card {
                width: calc(100% + 20px);
                margin-left: -10px;
                margin-right: -10px;
                padding-left: 12px;
                padding-right: 12px;
            }
        }

        /* Make Alumni Information tab truly full-width (full-bleed) between 768px and 320px */
        @media (max-width: 768px) {
            #alumni-info-tab .panel-card {
                position: relative;
                left: 50%;
                right: 50%;
                margin-left: -50vw;
                margin-right: -50vw;
                width: 100vw;
                max-width: 100vw;
                padding-left: 18px;
                padding-right: 18px;
                box-sizing: border-box;
                border-radius: 0;
            }
        }

        @media (max-width: 425px) {
            #alumni-info-tab .panel-card {
                padding-left: 12px;
                padding-right: 12px;
            }
        }

        /* Apply full-bleed treatment to the other alumni tabs as requested */
        @media (max-width: 768px) {
            #add-alumni-tab .panel-card,
            #update-employment-tab .panel-card,
            #edit-alumni-tab .panel-card,
            #all-alumni-tab .panel-card,
            #alumni-tracer-tab .panel-card {
                position: relative;
                left: 50%;
                right: 50%;
                margin-left: -50vw;
                margin-right: -50vw;
                width: 100vw;
                max-width: 100vw;
                padding-left: 18px;
                padding-right: 18px;
                box-sizing: border-box;
                border-radius: 0;
            }

            /* ensure their inner wrappers use full width */
            #add-alumni-tab .alumni-table-wrapper,
            #update-employment-tab .alumni-table-wrapper,
            #edit-alumni-tab .alumni-table-wrapper,
            #all-alumni-tab .alumni-table-wrapper,
            #alumni-tracer-tab .alumni-table-wrapper {
                padding-left: 0;
                padding-right: 0;
            }
        }

        @media (max-width: 425px) {
            #add-alumni-tab .panel-card,
            #update-employment-tab .panel-card,
            #edit-alumni-tab .panel-card,
            #all-alumni-tab .panel-card,
            #alumni-tracer-tab .panel-card {
                padding-left: 12px;
                padding-right: 12px;
            }
        }

        /* Tracer sub-tabs header: make scrollable and prepare for swipe handling */
        .tracer-subtabs-header {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        .tracer-subtabs-header .tracer-subtab-btn {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .tracer-subtabs-header { flex-wrap: nowrap !important; }
        }

        /* Trace Alumni sub-tab: stack controls and make inputs/buttons full width on mobile */
        @media (max-width: 768px) {
            #trace-alumni-subtab .trace-grid {
                grid-template-columns: 1fr !important;
            }

            #trace-alumni-subtab .trace-grid > div {
                width: 100%;
            }

            #trace-alumni-subtab .trace-grid button,
            #trace-alumni-subtab .trace-grid input,
            #trace-alumni-subtab .trace-grid select {
                width: 100% !important;
                box-sizing: border-box;
            }

            #trace-alumni-subtab .panel-card {
                padding-left: 16px;
                padding-right: 16px;
            }

            #trace-alumni-subtab #email-status {
                width: 100%;
                box-sizing: border-box;
                padding: 12px;
            }
        }

        @media (max-width: 425px) {
            #trace-alumni-subtab .panel-card {
                padding-left: 12px;
                padding-right: 12px;
            }
        }

        /* View Responses: stack filters and convert table to cards on mobile */
        @media (max-width: 768px) {
            .responses-grid { grid-template-columns: 1fr !important; }
            .responses-grid > div { width: 100%; }
            .responses-grid input, .responses-grid select { width: 100% !important; box-sizing: border-box; }

            .responses-table-wrapper { padding-left: 0; padding-right: 0; }

            .view-responses-table,
            .view-responses-table thead,
            .view-responses-table tbody,
            .view-responses-table th,
            .view-responses-table td,
            .view-responses-table tr {
                display: block;
                width: 100%;
            }

            .view-responses-table thead { display: none; }

            .view-responses-table tbody tr {
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                margin-bottom: 12px;
                overflow: hidden;
                background: #fff;
                box-shadow: 0 6px 18px rgba(15,23,42,0.04);
            }

            .view-responses-table td {
                padding: 10px 12px;
                border: none;
                border-bottom: 1px solid #f3f4f6;
                display: grid;
                grid-template-columns: 42% 58%;
                column-gap: 10px;
                align-items: center;
                white-space: normal !important;
                word-break: break-word !important;
                overflow-wrap: anywhere !important;
            }

            .view-responses-table td:last-child { border-bottom: none; }

            .view-responses-table td:nth-child(1)::before { content: "Full Name"; font-weight:700; color:#6b7280; }
            .view-responses-table td:nth-child(2)::before { content: "Course"; font-weight:700; color:#6b7280; }
            .view-responses-table td:nth-child(3)::before { content: "Year Graduated"; font-weight:700; color:#6b7280; }
            .view-responses-table td:nth-child(4)::before { content: "Employment Status"; font-weight:700; color:#6b7280; }
            .view-responses-table td:nth-child(5)::before { content: "Submitted Date"; font-weight:700; color:#6b7280; }
            .view-responses-table td:nth-child(6)::before { content: "Action"; font-weight:700; color:#6b7280; }

            .view-responses-table td::before { display: block; margin-bottom: 6px; }

            .view-responses-table td .action-button {
                width: 100%;
                box-sizing: border-box;
            }
        }

        .search-result-item:hover {
            background: #e9ecef;
        }

        .search-result-item div {
            flex: 1;
        }

        .search-result-item strong {
            display: block;
            color: #333;
        }

        .search-result-item small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .table-scroll-hint {
            text-align: center;
            font-size: 0.85rem;
            color: #800000;
            padding: 10px;
            display: block;
            background: #f5f5f5;
            border-top: 1px solid #e5e7eb;
            font-weight: 500;
            cursor: grab;
        }

        .table-scroll-hint:active {
            cursor: grabbing;
        }

        @media (max-width: 1024px) {
            .table-scroll-hint {
                animation: pulse 1.5s infinite;
            }

            @keyframes pulse {
                0%, 100% { color: #800000; }
                50% { color: #333; }
            }
        }

        /* Form Styles */
        .alumni-form, .employment-form, .contact-management, .career-tracking {
            padding: 12px 0;
        }

        .form-section {
            margin-bottom: 24px;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .form-section h4 {
            margin: 0 0 14px 0;
            color: #333;
            font-size: 1.1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
            margin-bottom: 12px;
            align-items: start;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .primary-button {
            background: #800000;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .primary-button:hover {
            background: #660000;
        }

        .primary-button.small {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .secondary-button {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .secondary-button:hover {
            background: #5a6268;
        }

        .danger-button {
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background-color 0.2s;
        }

        .danger-button:hover {
            background: #b91c1c;
        }

        .profile-image-picker {
            display: grid;
            justify-items: center;
            gap: 8px;
            padding: 14px 12px;
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            background: #f8fafc;
            width: 100%;
            max-width: 320px;
            cursor: pointer;
            transition: border-color 0.2s, background-color 0.2s;
        }

        .profile-image-picker:hover,
        .profile-image-picker:focus {
            border-color: #800000;
            background: #f3e6e6;
            outline: none;
        }

        .profile-image-preview {
            width: 88px;
            height: 88px;
            border-radius: 999px;
            background: #e2e8f0;
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .profile-image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-image-text {
            color: #475569;
            font-size: 0.9rem;
            text-align: center;
        }

        .achievements-summary {
            min-height: 36px;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #ffffff;
            color: #334155;
            font-size: 0.9rem;
            line-height: 1.4;
            margin-bottom: 10px;
        }

        .profile-group .profile-image-picker,
        .achievements-group .profile-image-picker {
            width: 100%;
            max-width: none;
        }

        .achievements-group {
            display: grid;
            gap: 10px;
        }

        .achievements-group button {
            justify-self: start;
            width: auto;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .achievement-modal .modal-body {
            display: grid;
            gap: 16px;
        }

        .achievement-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .achievement-checkboxes label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8fafc;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .achievement-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .achievement-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f3e6e6;
            color: #800000;
            font-size: 0.85rem;
        }

        .achievement-tag button {
            border: none;
            background: transparent;
            color: #800000;
            font-size: 0.95rem;
            cursor: pointer;
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 9999;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            width: min(620px, 100%);
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(15, 23, 42, 0.18);
        }

        .modal-header {
            padding: 22px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #111827;
        }

        .modal-close {
            border: none;
            background: transparent;
            font-size: 1.5rem;
            color: #475569;
            cursor: pointer;
        }

        .modal-actions {
            padding: 18px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .modal-body {
            padding: 24px;
        }

        /* Import Options */
        .import-options {
            margin-top: 16px;
        }

        .import-options label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        /* Tool Sections */
        .tool-section {
            margin-bottom: 30px;
        }

        .tool-section h4 {
            margin: 0 0 16px 0;
            color: #333;
            font-size: 1.1rem;
        }

        /* Communication Form */
        .communication-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .communication-form .form-group:nth-child(3) {
            grid-column: 1 / -1;
        }

        .communication-form .form-group:nth-child(4) {
            grid-column: 1 / -1;
        }

        /* Contact Preferences */
        .contact-preferences {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .preference-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .preference-item label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .preference-item label:hover {
            background: #e9ecef;
        }

        .preference-item input[type="checkbox"] {
            margin: 0;
        }

        /* Analytics Cards */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }

        .analytics-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }

        .analytics-card h5 {
            margin: 0 0 12px 0;
            color: #333;
            font-size: 0.9rem;
        }

        .stat-large {
            font-size: 2rem;
            font-weight: 700;
            color: #800000;
            margin-bottom: 8px;
        }

        .stat-trend {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stat-trend.positive {
            color: #27ae60;
        }

        .stat-trend.negative {
            color: #e74c3c;
        }

        .stat-trend.neutral {
            color: #6c757d;
        }

        /* Insights */
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .insight-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .insight-card h5 {
            margin: 0 0 16px 0;
            color: #333;
        }

        .insight-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .insight-card li {
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
        }

        .insight-card li:last-child {
            border-bottom: none;
        }

        /* History Lists */
        .history-list {
            margin-top: 20px;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 8px;
            background: white;
        }

        .history-icon {
            width: 32px;
            height: 32px;
            background: #800000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .history-content p {
            margin: 0 0 4px 0;
            font-weight: 600;
            color: #333;
        }

        .history-content small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        /* Notification Styles */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .notification {
            background: #800000;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out;
        }

        .notification-success {
            background: #27ae60;
        }

        .notification-error {
            background: #e74c3c;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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
    </style>
    <!-- EmailJS Library for Alumni Tracer -->
    <script>
        // Alumni Tracer EmailJS Configuration
        window.emailJsConfig = {
            serviceId: 'service_29ejn1d',
            templateId: 'template_tpt01vu',
            publicKey: 'IXC8qLoI_tNcsslwC'
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script>
        // Initialize EmailJS after library loads
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof emailjs !== 'undefined') {
                emailjs.init(window.emailJsConfig.publicKey);
            }
        });
    </script>
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
                        <a href="alumni_management.php" class="active" data-tooltip="Alumni Management">
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
                <div class="content-panel">
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <p class="eyebrow">SSAA Management - Alumni Management</p>
                            <h1>Alumni Management</h1>
                            <p class="dashboard-subtitle">Track and update alumni employment status, manage contact information, and monitor career progression.</p>
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
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                            <div>
                                <h4>Total Alumni</h4>
                                <p class="stat-number" id="total-alumni"><?= $alumniStats['total_alumni'] ?? 0 ?></p>
                                <p class="stat-label">Registered alumni</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa-solid fa-briefcase"></i></div>
                            <div>
                                <h4>Employed Alumni</h4>
                                <p class="stat-number" id="employed-alumni"><?= $alumniStats['employed_count'] ?? 0 ?></p>
                                <p class="stat-label">Currently employed</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa-solid fa-search"></i></div>
                            <div>
                                <h4>Unemployed Alumni</h4>
                                <p class="stat-number" id="unemployed-alumni"><?= $alumniStats['unemployed_count'] ?? 0 ?></p>
                                <p class="stat-label">Seeking employment</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa-solid fa-question-circle"></i></div>
                            <div>
                                <h4>Unknown Status</h4>
                                <p class="stat-number" id="unknown-alumni"><?= $alumniStats['unknown_count'] ?? 0 ?></p>
                                <p class="stat-label">Status not updated</p>
                            </div>
                        </div>
                    </div>

                    <!-- Alumni Management Actions -->
                    <div class="tabbed-actions">
                        <div class="tab-buttons" role="tablist">
                            <button type="button" class="tab-button active" data-tab="add-alumni-tab" role="tab" aria-selected="true">Add New Alumni</button>
                            <button type="button" class="tab-button" data-tab="update-employment-tab" role="tab" aria-selected="false">Update Employment</button>
                            <button type="button" class="tab-button" data-tab="edit-alumni-tab" role="tab" aria-selected="false">Edit Alumni</button>
                            <button type="button" class="tab-button" data-tab="all-alumni-tab" role="tab" aria-selected="false">All Alumni</button>
                            <button type="button" class="tab-button" data-tab="alumni-info-tab" role="tab" aria-selected="false">Alumni Information</button>
                            <button type="button" class="tab-button" data-tab="alumni-tracer-tab" role="tab" aria-selected="false">Alumni Tracer</button>
                        </div>

                        <div class="tab-panels">
                            <div class="tab-panel active" id="add-alumni-tab" role="tabpanel">
                                <div class="panel-card">
                                    <div class="panel-header">
                                        <h3>Add New Alumni</h3>
                                        <p>Import graduates from the student system or manually add alumni records.</p>
                                    </div>
                                    <div class="alumni-form">
                                        <div class="form-section">
                                            <h4>Import from Student System</h4>
                                            <p>Find students who have graduated and convert them to alumni records.</p>
                                            <div class="search-section">
                                                <label for="graduate-search">Search Graduates</label>
                                                <input type="text" id="graduate-search" placeholder="Search by name, student ID, or course..." onkeyup="searchGraduates()">
                                                <div id="graduate-search-results" class="search-results"></div>
                                            </div>
                                            <div class="import-options">
                                                <label><input type="checkbox" id="import-academic-data"> Include academic performance data</label>
                                                <label><input type="checkbox" id="import-contact-info" checked> Include contact information</label>
                                            </div>
                                        </div>

                                        <div class="form-section">
                                            <h4>Manual Entry</h4>
                                            <p>Add alumni who graduated from other institutions.</p>
                                            <form id="manual-alumni-form" onsubmit="addManualAlumni(event)" enctype="multipart/form-data">
                                                <div class="form-grid">
                                                    <div class="form-group">
                                                        <label for="alumni-full-name">Full Name *</label>
                                                        <input type="text" id="alumni-full-name" name="alumni-full-name" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="alumni-email">Email *</label>
                                                        <input type="email" id="alumni-email" name="alumni-email" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="alumni-student-id">Student ID</label>
                                                        <input type="text" id="alumni-student-id" name="alumni-student-id">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="alumni-course">Ladderized Course/Program *</label>
                                                        <select id="alumni-course" name="alumni-course" required>
                                                            <option value="">Select Course</option>
                                                            <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                                                            <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                                            <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                                                            <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                                                            <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                                                            <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                                            <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                                            <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                                            <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                                            <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                                            <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="alumni-graduation-year">Graduation Year *</label>
                                                        <select id="alumni-graduation-year" name="alumni-graduation-year" required>
                                                            <option value="">Select Year</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="alumni-contact">Contact Number</label>
                                                        <input type="tel" id="alumni-contact" name="alumni-contact">
                                                    </div>
                                                    <div class="form-group profile-group">
                                                        <label>Profile Photo</label>
                                                        <div class="profile-image-picker" id="alumniProfilePicker" tabindex="0">
                                                            <div class="profile-image-preview" id="alumniProfilePreview">
                                                                <i class="fa-solid fa-user"></i>
                                                            </div>
                                                            <span class="profile-image-text">Click to upload a profile photo</span>
                                                            <input type="file" id="alumni-profile-photo" name="alumni-profile-photo" accept="image/png,image/jpeg,image/webp" hidden>
                                                        </div>
                                                    </div>
                                                    <div class="form-group achievements-group">
                                                        <label>Achievements</label>
                                                        <div class="achievements-summary" id="achievementsSummary">No achievements selected yet.</div>
                                                        <button type="button" class="secondary-button" onclick="openAchievementsModal()">Add Achievements</button>
                                                        <input type="hidden" id="alumni-achievements" name="achievements">
                                                    </div>
                                                    <div class="form-group full-width">
                                                        <label for="alumni-quote">Quote</label>
                                                        <textarea id="alumni-quote" name="alumni-quote" rows="3" placeholder="Enter a favorite quote or message from the alumni"></textarea>
                                                    </div>
                                                </div>
                                                <div class="form-actions">
                                                    <button type="submit" class="primary-button">Add Alumni</button>
                                                    <button type="button" class="secondary-button" onclick="resetManualForm()">Reset</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div id="achievementsModal" class="modal achievement-modal" aria-hidden="true">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h3>Add Achievements</h3>
                                                <button class="modal-close" type="button" onclick="closeAchievementsModal()">&times;</button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="form-group">
                                                    <p>Select default achievements or add your own custom achievements.</p>
                                                </div>
                                                <div class="achievement-checkboxes">
                                                    <label><input type="checkbox" class="achievement-checkbox" value="Cum Laude" onchange="updateAchievementSelection()"> Cum Laude</label>
                                                    <label><input type="checkbox" class="achievement-checkbox" value="Magna Cum Laude" onchange="updateAchievementSelection()"> Magna Cum Laude</label>
                                                    <label><input type="checkbox" class="achievement-checkbox" value="Summa Cum Laude" onchange="updateAchievementSelection()"> Summa Cum Laude</label>
                                                    <label><input type="checkbox" class="achievement-checkbox" value="Dean's Lister" onchange="updateAchievementSelection()"> Dean's Lister</label>
                                                    <label><input type="checkbox" class="achievement-checkbox" value="Leadership Award" onchange="updateAchievementSelection()"> Leadership Award</label>
                                                    <label><input type="checkbox" class="achievement-checkbox" value="Community Service Recognition" onchange="updateAchievementSelection()"> Community Service Recognition</label>
                                                </div>

                                                <div class="form-group">
                                                    <label for="achievement-custom-text">Custom achievement</label>
                                                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                                        <input type="text" id="achievement-custom-text" placeholder="e.g. Student Council President" style="flex:1; min-width:200px;" />
                                                        <button type="button" class="secondary-button" onclick="addCustomAchievement()">Add</button>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label>Added achievements</label>
                                                    <div id="achievementTags" class="achievement-tags"></div>
                                                </div>
                                            </div>
                                            <div class="modal-actions">
                                                <button type="button" class="secondary-button" onclick="closeAchievementsModal()">Cancel</button>
                                                <button type="button" class="primary-button" onclick="saveAchievements()">Save Achievements</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-panel" id="update-employment-tab" role="tabpanel">
                                <div class="panel-card">
                                    <div class="panel-header">
                                        <h3>Update Employment</h3>
                                        <p>Track and update alumni employment status, career progression, and job changes.</p>
                                    </div>
                                    <div class="employment-management">
                                        <div class="search-section">
                                            <label for="alumni-employment-search">Search Alumni</label>
                                            <input type="text" id="alumni-employment-search" placeholder="Search by name, company, or job title..." onkeyup="searchAlumniEmployment()">
                                            <div id="alumni-employment-results" class="search-results"></div>
                                        </div>

                                        <div class="employment-history" id="employment-history">
                                            <h4>Recent Employment Updates</h4>
                                            <div id="employment-history-list" class="history-list">
                                                <!-- Employment history will be loaded here -->
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-panel" id="edit-alumni-tab" role="tabpanel">
                                <div class="panel-card">
                                    <div class="panel-header">
                                        <h3>Edit Alumni Records</h3>
                                        <p>Search, edit, and delete alumni records.</p>
                                    </div>
                                    <div class="edit-alumni-management">
                                        <div class="search-section">
                                            <label for="edit-alumni-search">Search Alumni</label>
                                            <input type="text" id="edit-alumni-search" placeholder="Search by name, email, or student ID..." onkeyup="searchEditAlumni()">
                                            <div id="edit-alumni-results" class="search-results"></div>
                                        </div>

                                        <div class="form-grid" style="margin-top:12px; margin-bottom:12px;">
                                            <div class="form-group">
                                                <label for="edit-alumni-course-filter">Filter Course</label>
                                                <select id="edit-alumni-course-filter">
                                                    <option value="">All Courses</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="edit-alumni-graduation-year-filter">Filter Graduation Year</label>
                                                <select id="edit-alumni-graduation-year-filter">
                                                    <option value="">All Years</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="alumni-table-wrapper">
                                            <table class="alumni-table" id="edit-alumni-table">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Course</th>
                                                        <th>Graduation Year</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="edit-alumni-list">
                                                    <tr><td colspan="5">Loading alumni...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div id="editAlumniForm" class="modal" aria-hidden="true">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h3>Edit Alumni Information</h3>
                                                    <button class="modal-close" type="button" onclick="cancelEditAlumni()">&times;</button>
                                                </div>
                                                <div class="modal-body">
                                                    <form onsubmit="saveEditedAlumni(event)">
                                                        <div class="form-grid">
                                                            <input type="hidden" id="edit-alumni-id" name="alumni-id">
                                                            <div class="form-group">
                                                                <label for="edit-alumni-full-name">Full Name</label>
                                                                <input type="text" id="edit-alumni-full-name" name="full_name" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-email">Email</label>
                                                                <input type="email" id="edit-alumni-email" name="email" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-student-id">Student ID</label>
                                                                <input type="text" id="edit-alumni-student-id" name="student_id">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-course">Ladderized Course/Program *</label>
                                                                <select id="edit-alumni-course" name="course" required>
                                                                    <option value="">Select Course</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-graduation-year">Graduation Year *</label>
                                                                <select id="edit-alumni-graduation-year" name="graduation_year" required>
                                                                    <option value="">Select Year</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-ladderized">Ladderized</label>
                                                                <select id="edit-alumni-ladderized" name="ladderized">
                                                                    <option value="">Unspecified</option>
                                                                    <option value="Yes">Yes</option>
                                                                    <option value="No">No</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-contact-number">Contact Number</label>
                                                                <input type="text" id="edit-alumni-contact-number" name="contact_number">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-company">Company</label>
                                                                <input type="text" id="edit-alumni-company" name="company_name">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-job-title">Job Title</label>
                                                                <input type="text" id="edit-alumni-job-title" name="job_title">
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-achievements">Achievements</label>
                                                                <textarea id="edit-alumni-achievements" name="achievements" rows="3" placeholder="Enter achievements or honors"></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-quote">Quote</label>
                                                                <textarea id="edit-alumni-quote" name="quote" rows="3" placeholder="Enter a favorite quote"></textarea>
                                                            </div>
                                                            <div class="form-group">
                                                                <label for="edit-alumni-profile-image">Profile Image</label>
                                                                <input type="file" id="edit-alumni-profile-image" name="edit-alumni-profile-image" accept="image/jpeg,image/png,image/webp">
                                                                <small>Upload a JPG, PNG, or WEBP image. Leave empty to keep the current image.</small>
                                                            </div>
                                                        </div>
                                                        <div class="modal-actions form-actions">
                                                            <button type="submit" class="primary-button">Save Changes</button>
                                                            <button type="button" class="secondary-button" onclick="cancelEditAlumni()">Cancel</button>
                                                            <button type="button" class="danger-button" onclick="confirmDeleteAlumni()">Delete Alumni</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-panel" id="all-alumni-tab" role="tabpanel">
                                <div class="panel-card">
                                    <div class="panel-header">
                                        <h3>All Alumni</h3>
                                        <p>Browse all alumni records and filter by graduation year or name.</p>
                                    </div>
                                    <div class="all-alumni-management">
                                        <div class="search-section">
                                            <label for="all-alumni-search">Search Alumni</label>
                                            <input type="text" id="all-alumni-search" placeholder="Search by name, course, or status..." onkeyup="searchAllAlumni()">
                                        </div>
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label for="filter-graduation-year">Graduation Year</label>
                                                <select id="filter-graduation-year" onchange="loadAllAlumni()">
                                                    <option value="">All Years</option>
                                                    <?php for ($year = date('Y'); $year >= 2000; $year--): ?>
                                                        <option value="<?= $year ?>"><?= $year ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="filter-employment-status">Employment Status</label>
                                                <select id="filter-employment-status" onchange="loadAllAlumni()">
                                                    <option value="">All Statuses</option>
                                                    <option value="Employed">Employed</option>
                                                    <option value="Unemployed">Unemployed</option>
                                                    <option value="Self-Employed">Self-Employed</option>
                                                    <option value="Further Studies">Further Studies</option>
                                                    <option value="Entrepreneur">Entrepreneur</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="alumni-table-wrapper">
                                            <table class="alumni-table" id="all-alumni-table">
                                                <thead>
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Course</th>
                                                        <th>Graduation Year</th>
                                                        <th>Status</th>
                                                        <th>Company / Job</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="all-alumni-list">
                                                    <tr><td colspan="5">Loading alumni...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Alumni Information Tab - Shows submitted onboarding forms -->
                            <div class="tab-panel" id="alumni-info-tab" role="tabpanel">
                                <div class="panel-card">
                                    <div class="panel-header">
                                        <h3>Alumni Information Profiles</h3>
                                        <p>View submitted alumni onboarding forms with personal and professional details.</p>
                                    </div>
                                    <div class="alumni-info-management">
                                        <div class="search-section">
                                            <label for="alumni-info-search">Search Alumni</label>
                                            <input type="text" id="alumni-info-search" placeholder="Search by name, email, or student ID..." onkeyup="searchAlumniInfo()">
                                        </div>
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label for="alumni-info-year-filter">Graduation Year</label>
                                                <select id="alumni-info-year-filter" onchange="applyAlumniInfoFilters()">
                                                    <option value="">All Years</option>
                                                    <?php for ($year = date('Y'); $year >= 2000; $year--): ?>
                                                        <option value="<?= $year ?>"><?= $year ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="alumni-info-course-filter">Course</label>
                                                <select id="alumni-info-course-filter" onchange="applyAlumniInfoFilters()">
                                                    <option value="">All Courses</option>
                                                    <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                                                    <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                                    <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                                                    <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                                                    <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                                                    <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                                    <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                                    <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                                    <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                                    <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                                    <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div style="display:flex; justify-content:flex-end; margin: 12px 0 18px;">
                                            <button type="button" class="danger-button" onclick="openArchiveAlumniModal()">
                                                <i class="fa-solid fa-box-archive"></i> Archive Alumni
                                            </button>
                                        </div>
                                        <div class="alumni-info-table-wrapper">
                                            <table class="alumni-table" id="alumni-info-table">
                                                <thead>
                                                    <tr>
                                                        <th>Full Name</th>
                                                        <th>Student ID</th>
                                                        <th>Course</th>
                                                        <th>Year Graduated</th>
                                                        <th>Gender</th>
                                                        <th>Civil Status</th>
                                                        <th>Date of Birth</th>
                                                        <th>Nationality</th>
                                                        <th>Personal Email</th>
                                                        <th>Mobile Number</th>
                                                        <th>Address</th>
                                                        <th>Status</th>
                                                        <th>Company / Title</th>
                                                        <th>Location</th>
                                                        <th>Industry</th>
                                                        <th>Salary Range</th>
                                                        <th>Job Description</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="alumni-info-list">
                                                    <tr><td colspan="18">Loading alumni information...</td></tr>
                                                </tbody>
                                            </table>
                                            <div class="table-scroll-hint">← Scroll or swipe for more →</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="archiveAlumniModal" class="modal" aria-hidden="true">
                                <div class="modal-content" style="max-width: 520px;">
                                    <div class="modal-header">
                                        <h3>Archive Alumni</h3>
                                        <button class="modal-close" type="button" onclick="closeArchiveAlumniModal()">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Select a course and year to archive all matching alumni records from the Alumni Information list.</p>
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label for="archive-alumni-course">Course</label>
                                                <select id="archive-alumni-course">
                                                    <option value="">All Courses</option>
                                                    <option value="Bachelor of Science in Accountancy">Bachelor of Science in Accountancy</option>
                                                    <option value="Bachelor of Science in Business Administration">Bachelor of Science in Business Administration</option>
                                                    <option value="Bachelor in Elementary Education">Bachelor in Elementary Education</option>
                                                    <option value="Bachelor of Science in Computer Science">Bachelor of Science in Computer Science</option>
                                                    <option value="Bachelor of Science in Criminology">Bachelor of Science in Criminology</option>
                                                    <option value="Bachelor of Science in Hospitality Management">Bachelor of Science in Hospitality Management</option>
                                                    <option value="Bachelor of Science in Tourism Management">Bachelor of Science in Tourism Management</option>
                                                    <option value="Associate in Computer Technology">Associate in Computer Technology</option>
                                                    <option value="Associate in Business Knowledge">Associate in Business Knowledge</option>
                                                    <option value="Associate in Hospitality Management">Associate in Hospitality Management</option>
                                                    <option value="Associate in Tourism Management">Associate in Tourism Management</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="archive-alumni-year">Year Graduated</label>
                                                <select id="archive-alumni-year">
                                                    <option value="">All Years</option>
                                                    <?php for ($year = date('Y'); $year >= 2000; $year--): ?>
                                                        <option value="<?= $year ?>"><?= $year ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-actions form-actions">
                                        <button type="button" class="secondary-button" onclick="closeArchiveAlumniModal()">Cancel</button>
                                        <button type="button" class="danger-button" onclick="archiveSelectedAlumni()">Archive</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Alumni Tracer Tab -->
                            <div class="tab-panel" id="alumni-tracer-tab" role="tabpanel">
                                <div class="panel-card">
                                    <div class="panel-header">
                                        <h3>Alumni Tracer System</h3>
                                        <p>Track alumni careers, manage surveys, and view responses.</p>
                                    </div>

                                    <!-- Alumni Tracer Sub-tabs -->
                                    <div style="margin-top: 20px; border-bottom: 2px solid #e9ecef;">
                                        <div class="tracer-subtabs-header" style="display: flex; gap: 10px; flex-wrap: wrap;">
                                            <button type="button" class="tracer-subtab-btn active" data-subtab="trace-alumni-subtab" style="padding: 10px 20px; background: transparent; border: none; border-bottom: 3px solid #800000; cursor: pointer; font-weight: 600; color: #800000;">
                                                <i class="fa-solid fa-envelope"></i> Trace Alumni
                                            </button>
                                            <button type="button" class="tracer-subtab-btn" data-subtab="survey-config-subtab" style="padding: 10px 20px; background: transparent; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: #666;">
                                                <i class="fa-solid fa-clipboard"></i> Survey
                                            </button>
                                            <button type="button" class="tracer-subtab-btn" data-subtab="view-responses-subtab" style="padding: 10px 20px; background: transparent; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: #666;">
                                                <i class="fa-solid fa-chart-bar"></i> View Responses
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Trace Alumni Sub-tab -->
                                    <div class="tracer-subtab active" id="trace-alumni-subtab" style="display: block; padding: 20px; 0;">
                                        <h4 style="margin-top: 0; color: #333;">Send Tracer Forms to Alumni</h4>
                                        <p style="color: #666; margin-bottom: 20px;">Select a graduation batch and send email invitations for alumni to complete their tracer forms.</p>
                                        
                                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                                            <div class="trace-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                                                <div>
                                                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Graduation Year:</label>
                                                    <select id="batch-year-select" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                                        <option value="">-- Select Year --</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Alumni Count:</label>
                                                    <input type="text" id="batch-count" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; background: #f0f0f0; color: #666;">
                                                </div>
                                                <div style="display: flex; align-items: flex-end;">
                                                    <button type="button" onclick="sendTracerEmails()" style="width: 100%; padding: 8px 15px; background: #800000; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                                        <i class="fa-solid fa-paper-plane"></i> Send Emails
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="email-status" style="display: none; padding: 15px; border-radius: 5px; margin-bottom: 20px;"></div>

                                        <div style="background: #f3e6e6; padding: 15px; border-radius: 5px; border-left: 4px solid #800000;">
                                            <strong>Email Template:</strong>
                                            <p style="margin: 10px 0 0 0; font-size: 13px; color: #555;">Alumni will receive an invitation email with a link to complete the Alumni Tracer Form. The form includes employment status, contact information, and career details.</p>
                                        </div>
                                    </div>

                                    <!-- Survey Configuration Sub-tab -->
                                    <div class="tracer-subtab" id="survey-config-subtab" style="display: none; padding: 20px;">
                                        <h4 style="margin-top: 0; color: #333;">Survey Configuration</h4>
                                        <p style="color: #666; margin-bottom: 20px;">Customize survey questions for different employment statuses. Default fields cannot be removed.</p>
                                        
                                        <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                                            <button type="button" class="survey-section-btn active" data-section="default" style="padding: 8px 15px; background: #800000; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 13px;">
                                                All Employees
                                            </button>
                                            <button type="button" class="survey-section-btn" data-section="employed" style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 13px;">
                                                Employed
                                            </button>
                                            <button type="button" class="survey-section-btn" data-section="self-employed" style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 13px;">
                                                Self-Employed
                                            </button>
                                            <button type="button" class="survey-section-btn" data-section="unemployed" style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 13px;">
                                                Unemployed
                                            </button>
                                        </div>

                                        <div id="survey-questions-container" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; min-height: 200px;">
                                            <p style="text-align: center; color: #666;">Loading questions...</p>
                                        </div>

                                        <div style="display: flex; gap: 10px;">
                                            <button type="button" onclick="openAddQuestionModal()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                                <i class="fa-solid fa-plus"></i> Add Custom Question
                                            </button>
                                            <button type="button" onclick="saveSurveyConfig()" style="padding: 10px 20px; background: #800000; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                                <i class="fa-solid fa-save"></i> Save Configuration
                                            </button>
                                        </div>

                                        <!-- Add Survey Question Modal -->
                                        <div id="addQuestionModal" class="modal" aria-hidden="true" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
                                            <div class="modal-content" style="background: white; border-radius: 8px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
                                                <div class="modal-header" style="padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                                                    <h3 style="margin: 0; color: #333;">Add Survey Question</h3>
                                                    <button type="button" class="modal-close" onclick="closeAddQuestionModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
                                                </div>

                                                <div class="modal-body" style="padding: 20px;">
                                                    <div style="margin-bottom: 20px;">
                                                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Question Text <span style="color: #dc3545;">*</span></label>
                                                        <input type="text" id="question-text" placeholder="Enter your question here..." style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; box-sizing: border-box;">
                                                    </div>

                                                    <div style="margin-bottom: 20px;">
                                                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Apply To <span style="color: #dc3545;">*</span></label>
                                                        <select id="employment-status" style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                                                            <option value="all">All Alumni</option>
                                                            <option value="employed">Employed Only</option>
                                                            <option value="self-employed">Self-Employed Only</option>
                                                            <option value="unemployed">Unemployed Only</option>
                                                        </select>
                                                    </div>

                                                    <div style="margin-bottom: 20px;">
                                                        <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #333;">Question Type <span style="color: #dc3545;">*</span></label>
                                                        <div style="display: flex; gap: 15px;">
                                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                                                <input type="radio" name="question-type" value="text" checked onchange="updateQuestionTypeUI()"> Text Input
                                                            </label>
                                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                                                <input type="radio" name="question-type" value="choice" onchange="updateQuestionTypeUI()"> Multiple Choice
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <!-- Placeholder Text (for Text Input) -->
                                                    <div id="placeholder-section" style="margin-bottom: 20px;">
                                                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Placeholder Text</label>
                                                        <input type="text" id="placeholder-text" placeholder="e.g. Enter your answer here..." style="width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; box-sizing: border-box;">
                                                    </div>

                                                    <!-- Choices Section (for Multiple Choice) -->
                                                    <div id="choices-section" style="margin-bottom: 20px; display: none;">
                                                        <label style="display: block; font-weight: 600; margin-bottom: 12px; color: #333;">Answer Choices <span style="color: #dc3545;">*</span></label>
                                                        <div id="choices-container" style="margin-bottom: 10px;">
                                                            <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                                                                <input type="text" class="choice-input" placeholder="Choice 1" style="flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                                                                <button type="button" onclick="removeChoiceInput(this)" style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Remove</button>
                                                            </div>
                                                            <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                                                                <input type="text" class="choice-input" placeholder="Choice 2" style="flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                                                                <button type="button" onclick="removeChoiceInput(this)" style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Remove</button>
                                                            </div>
                                                        </div>
                                                        <button type="button" onclick="addChoiceInput()" style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 13px;">
                                                            <i class="fa-solid fa-plus"></i> Add Another Choice
                                                        </button>
                                                    </div>
                                                </div>

                                                <div style="padding: 20px; border-top: 1px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end;">
                                                    <button type="button" onclick="closeAddQuestionModal()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Cancel</button>
                                                    <button type="button" onclick="submitNewQuestion()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Add Question</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- View Responses Sub-tab -->
                                    <div class="tracer-subtab" id="view-responses-subtab" style="display: none; padding: 20px;">
                                        <h4 style="margin-top: 0; color: #333;">Submitted Responses</h4>
                                        <p style="color: #666; margin-bottom: 20px;">View all alumni who have completed the tracer form.</p>
                                        
                                        <div class="responses-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                                            <div>
                                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Search:</label>
                                                <input type="text" id="response-search" placeholder="Name or Email..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                            </div>
                                            <div>
                                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Year Graduated:</label>
                                                <select id="response-year" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                                    <option value="">-- All Years --</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #333;">Employment Status:</label>
                                                <select id="response-employment" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                                    <option value="">-- All Status --</option>
                                                    <option value="Employed">Employed</option>
                                                    <option value="Self-Employed">Self-Employed</option>
                                                    <option value="Unemployed">Unemployed</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="responses-table-wrapper" style="overflow-x: auto;">
                                            <table class="view-responses-table" style="width: 100%; border-collapse: collapse; background: white;">
                                                <thead>
                                                    <tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">
                                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Full Name</th>
                                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Course</th>
                                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Year Graduated</th>
                                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Employment Status</th>
                                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Submitted Date</th>
                                                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #333;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="responses-list">
                                                    <tr><td colspan="6" style="padding: 20px; text-align: center; color: #666;">Loading responses...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>


                    <!-- Recent Alumni Additions -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-clock"></i> Recent Alumni Additions</h3>
                        </div>
                        <div class="card-content">
                            <?php if (empty($recentAlumni)): ?>
                                <p>No recent alumni additions to display.</p>
                            <?php else: ?>
                                <div class="alumni-list">
                                    <?php foreach ($recentAlumni as $alumni): ?>
                                        <div class="alumni-item">
                                            <div class="alumni-icon">
                                                <i class="fa-solid fa-user-graduate"></i>
                                            </div>
                                            <div class="alumni-content">
                                                <p><strong><?= htmlspecialchars($alumni['student_name'] ?: $alumni['full_name']) ?></strong></p>
                                                <div class="alumni-meta">
                                                    <span class="alumni-status"><?= htmlspecialchars($alumni['employment_status'] ?: 'Unknown') ?></span>
                                                    <span class="alumni-added">Added <?= date('M j, Y', strtotime($alumni['created_at'])) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Employment Update Modal -->
    <div id="employmentModal" class="modal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Employment Information</h3>
                <button class="modal-close" type="button" onclick="closeEmploymentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="employment-modal-form" onsubmit="updateEmployment(event)">
                    <input type="hidden" id="modal-employment-alumni-id" name="employment-alumni-id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="modal-employment-status">Employment Status *</label>
                            <select id="modal-employment-status" name="employment-status" required onchange="toggleEmploymentFields()">
                                <option value="">Select Status</option>
                                <option value="Employed">Employed</option>
                                <option value="Self-Employed">Self-Employed</option>
                                <option value="Unemployed">Unemployed</option>
                                <option value="Further Studies">Further Studies</option>
                                <option value="Entrepreneur">Entrepreneur</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modal-company-name">Company Name</label>
                            <input type="text" id="modal-company-name" name="company-name">
                        </div>
                        <div class="form-group">
                            <label for="modal-job-title">Job Title</label>
                            <input type="text" id="modal-job-title" name="job-title">
                        </div>
                        <div class="form-group">
                            <label for="modal-industry">Industry</label>
                            <select id="modal-industry" name="industry">
                                <option value="">Select Industry</option>
                                <option value="Technology">Technology</option>
                                <option value="Healthcare">Healthcare</option>
                                <option value="Education">Education</option>
                                <option value="Finance">Finance</option>
                                <option value="Manufacturing">Manufacturing</option>
                                <option value="Retail">Retail</option>
                                <option value="Hospitality">Hospitality</option>
                                <option value="Government">Government</option>
                                <option value="Non-Profit">Non-Profit</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modal-job-level">Job Level</label>
                            <select id="modal-job-level" name="job-level">
                                <option value="">Select Level</option>
                                <option value="Entry Level">Entry Level</option>
                                <option value="Junior">Junior</option>
                                <option value="Mid Level">Mid Level</option>
                                <option value="Senior">Senior</option>
                                <option value="Management">Management</option>
                                <option value="Executive">Executive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modal-salary-range">Salary Range</label>
                            <select id="modal-salary-range" name="salary-range">
                                <option value="">Select Range</option>
                                <option value="Under ₱15,000">Under ₱15,000</option>
                                <option value="₱15,000 - ₱25,000">₱15,000 - ₱25,000</option>
                                <option value="₱25,000 - ₱35,000">₱25,000 - ₱35,000</option>
                                <option value="₱35,000 - ₱50,000">₱35,000 - ₱50,000</option>
                                <option value="₱50,000 - ₱75,000">₱50,000 - ₱75,000</option>
                                <option value="₱75,000 - ₱100,000">₱75,000 - ₱100,000</option>
                                <option value="Above ₱100,000">Above ₱100,000</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="modal-employment-date">Employment Start Date</label>
                            <input type="date" id="modal-employment-date" name="employment-date">
                        </div>
                        <div class="form-group">
                            <label for="modal-location">Location</label>
                            <input type="text" id="modal-location" name="location" placeholder="City, Province">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal-job-description">Job Description</label>
                        <textarea id="modal-job-description" name="job-description" rows="3" placeholder="Brief description of responsibilities..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="primary-button">Save Changes</button>
                        <button type="button" class="secondary-button" onclick="closeEmploymentModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="notification-container" class="notification-container"></div>

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
        function showNotification(message, type) {
    (function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // Add New Alumni Functions
        function searchGraduates() {
            const query = document.getElementById('graduate-search').value;
            if (query.length < 2) {
                document.getElementById('graduate-search-results').innerHTML = '';
                return;
            }

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=search_graduates&query=${encodeURIComponent(query)}`
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('graduate-search-results');
                if (!Array.isArray(data)) {
                    console.error('Unexpected search graduates response:', data);
                    resultsDiv.innerHTML = '<p>Error searching graduates.</p>';
                    return;
                }
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<p>No graduates found.</p>';
                    return;
                }

                resultsDiv.innerHTML = data.map(student => `
                    <div class="search-result-item">
                        <div>
                            <strong>${student.student_name}</strong>
                            <small>${student.course} - Graduated ${student.graduation_year}</small>
                        </div>
                        <button type="button" class="primary-button small" onclick="importGraduate(${student.id})">Import</button>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error searching graduates:', error);
                showNotification('Error searching graduates', 'error');
            });
        }

        function importGraduate(studentId) {
            const includeAcademic = document.getElementById('import-academic-data').checked;
            const includeContact = document.getElementById('import-contact-info').checked;

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=import_graduate&student_id=${studentId}&academic=${includeAcademic}&contact=${includeContact}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Alumni imported successfully', 'success');
                    document.getElementById('graduate-search').value = '';
                    document.getElementById('graduate-search-results').innerHTML = '';
                } else {
                    showNotification(data.message || 'Error importing alumni', 'error');
                }
            })
            .catch(error => {
                console.error('Error importing graduate:', error);
                showNotification('Error importing alumni', 'error');
            });
        }

        function addManualAlumni(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            formData.append('action', 'add_manual_alumni');

            fetch('alumni_management.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (err) {
                    console.error('Invalid JSON response while adding alumni:', text);
                    showNotification('Error adding alumni: invalid server response', 'error');
                    throw err;
                }
            })
            .then(data => {
                if (data.success) {
                    showNotification('Alumni added successfully', 'success');
                    event.target.reset();
                } else {
                    showNotification(data.message || 'Error adding alumni', 'error');
                }
            })
            .catch(error => {
                console.error('Error adding alumni:', error);
                if (!(error instanceof SyntaxError)) {
                    showNotification('Error adding alumni', 'error');
                }
            });
        }

        function resetManualForm() {
            document.getElementById('manual-alumni-form').reset();
            document.getElementById('achievementsSummary').textContent = 'No achievements selected yet.';
            document.getElementById('alumniProfilePreview').innerHTML = '<i class="fa-solid fa-user"></i>';
            document.getElementById('alumni-achievements').value = '';
            document.querySelectorAll('.achievement-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('achievement-custom-text').value = '';
            document.getElementById('achievementTags').innerHTML = '';
            selectedAchievements = [];
            customAchievements = [];
        }

        function openAchievementsModal() {
            document.getElementById('achievementsModal').classList.add('show');
        }

        function closeAchievementsModal() {
            document.getElementById('achievementsModal').classList.remove('show');
        }

        function saveAchievements() {
            updateAchievementSelection();
            closeAchievementsModal();
        }

        function updateAchievementSelection() {
            const checked = Array.from(document.querySelectorAll('.achievement-checkbox:checked')).map(el => el.value.trim());
            const values = [...new Set([...checked, ...customAchievements])].filter(v => v !== '');
            document.getElementById('alumni-achievements').value = values.join(', ');
            document.getElementById('achievementsSummary').textContent = values.length ? values.join(', ') : 'No achievements selected yet.';
        }

        let selectedAchievements = [];
        let customAchievements = [];

        function addCustomAchievement() {
            const input = document.getElementById('achievement-custom-text');
            const value = input.value.trim();
            if (!value) {
                return;
            }
            if (!customAchievements.includes(value)) {
                customAchievements.push(value);
            }
            input.value = '';
            renderAchievementTags();
        }

        function renderAchievementTags() {
            const container = document.getElementById('achievementTags');
            container.innerHTML = customAchievements.map(achievement => `
                <span class="achievement-tag">
                    ${achievement}
                    <button type="button" onclick="removeCustomAchievement(${JSON.stringify(achievement)})">×</button>
                </span>
            `).join('');
            updateAchievementSelection();
        }

        function removeCustomAchievement(value) {
            customAchievements = customAchievements.filter(a => a !== value);
            renderAchievementTags();
        }

        function setupProfileImageUpload() {
            const picker = document.getElementById('alumniProfilePicker');
            const input = document.getElementById('alumni-profile-photo');
            const preview = document.getElementById('alumniProfilePreview');

            if (!picker || !input || !preview) return;

            picker.addEventListener('click', () => input.click());
            picker.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    input.click();
                }
            });

            input.addEventListener('change', () => {
                if (input.files && input.files[0]) {
                    const file = input.files[0];
                    if (!file.type.startsWith('image/')) {
                        showNotification('Please choose a valid image file.', 'error');
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        preview.innerHTML = `<img src="${e.target.result}" alt="Profile image">`;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupProfileImageUpload();
        });

        // Update Employment Functions
        function searchAlumniEmployment() {
            const query = document.getElementById('alumni-employment-search').value;
            if (query.length < 2) {
                document.getElementById('alumni-employment-results').innerHTML = '';
                return;
            }

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=search_alumni&query=${encodeURIComponent(query)}`
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('alumni-employment-results');
                if (!Array.isArray(data)) {
                    console.error('Unexpected search employment response:', data);
                    resultsDiv.innerHTML = '<p>Error searching alumni.</p>';
                    return;
                }
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<p>No alumni found.</p>';
                    return;
                }

                resultsDiv.innerHTML = data.map(alumni => `
                    <div class="search-result-item">
                        <div>
                            <strong>${alumni.full_name}</strong>
                            <small>${alumni.course} - ${alumni.graduation_year}</small>
                            <small>Status: ${alumni.employment_status || 'Unknown'}</small>
                        </div>
                        <button type="button" class="primary-button small" onclick="editEmployment(${alumni.id})">Update</button>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error searching alumni:', error);
                showNotification('Error searching alumni', 'error');
            });
        }

        function editEmployment(alumniId) {
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_alumni_details&alumni_id=${alumniId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.alumni) {
                    const alumni = data.alumni;
                    document.getElementById('modal-employment-alumni-id').value = alumni.id;
                    document.getElementById('modal-employment-status').value = alumni.employment_status || '';
                    document.getElementById('modal-company-name').value = alumni.company || '';
                    document.getElementById('modal-job-title').value = alumni.job_title || '';
                    document.getElementById('modal-industry').value = alumni.industry || '';
                    document.getElementById('modal-job-level').value = alumni.job_level || '';
                    document.getElementById('modal-salary-range').value = alumni.salary_range || '';
                    document.getElementById('modal-employment-date').value = alumni.employment_date || '';
                    document.getElementById('modal-location').value = alumni.location || '';
                    document.getElementById('modal-job-description').value = alumni.job_description || '';

                    const employmentModal = document.getElementById('employmentModal');
                    employmentModal.classList.add('show');
                    employmentModal.setAttribute('aria-hidden', 'false');
                    document.getElementById('alumni-employment-results').innerHTML = '';
                    document.getElementById('alumni-employment-search').value = '';
                } else {
                    showNotification('Error loading alumni details', 'error');
                }
            })
            .catch(error => {
                console.error('Error loading alumni details:', error);
                showNotification('Error loading alumni details', 'error');
            });
        }

        function closeEmploymentModal() {
            const modal = document.getElementById('employmentModal');
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.getElementById('employment-modal-form').reset();
            document.getElementById('modal-employment-status').value = '';
            toggleEmploymentFields();
        }

        function updateEmployment(event) {
            event.preventDefault();

            const form = event.target;
            const optionalFields = ['modal-company-name', 'modal-job-title', 'modal-industry', 'modal-job-level', 'modal-salary-range', 'modal-location', 'modal-job-description'];
            optionalFields.forEach(id => {
                const field = document.getElementById(id);
                if (field && field.value.trim() === '') {
                    field.value = 'N/A';
                }
            });

            // Ensure all fields are included in the request, even if they are currently disabled.
            const disabledFields = Array.from(form.querySelectorAll(':disabled'));
            disabledFields.forEach(field => { field.disabled = false; });

            const formData = new FormData(form);
            formData.append('action', 'update_employment');

            fetch('alumni_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Employment updated successfully', 'success');
                    closeEmploymentModal();
                    loadEmploymentHistory();
                    loadAllAlumni();
                } else {
                    showNotification(data.message || 'Error updating employment', 'error');
                }
            })
            .catch(error => {
                console.error('Error updating employment:', error);
                showNotification('Error updating employment', 'error');
            });
        }

        function cancelEmploymentUpdate() {
            closeEmploymentModal();
        }

        function toggleEmploymentFields() {
            const status = document.getElementById('modal-employment-status').value;
            const fields = ['modal-company-name', 'modal-job-title', 'modal-industry', 'modal-job-level', 'modal-salary-range', 'modal-employment-date', 'modal-location', 'modal-job-description'];

            if (status === 'Unemployed' || status === 'Further Studies') {
                fields.forEach(field => {
                    document.getElementById(field).disabled = true;
                    document.getElementById(field).value = '';
                });
            } else {
                fields.forEach(field => {
                    document.getElementById(field).disabled = false;
                });
            }
        }

        function loadEmploymentHistory() {
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_employment_history'
            })
            .then(response => response.json())
            .then(data => {
                const historyDiv = document.getElementById('employment-history-list');
                if (!Array.isArray(data)) {
                    console.error('Unexpected employment history response', data);
                    historyDiv.innerHTML = '<p>Error loading employment history.</p>';
                    return;
                }

                if (data.length === 0) {
                    historyDiv.innerHTML = '<p>No recent employment updates.</p>';
                    return;
                }

                historyDiv.innerHTML = data.map(item => `
                    <div class="history-item">
                        <div class="history-icon">
                            <i class="fa-solid fa-briefcase"></i>
                        </div>
                        <div class="history-content">
                            <p><strong>${item.full_name}</strong> - ${item.employment_status}</p>
                            <small>${item.company ? item.company + ' - ' : ''}${item.job_title || 'Position updated'} · ${new Date(item.updated_at).toLocaleDateString()}</small>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading employment history:', error);
            });
        }

        // All Alumni Functions
        function searchAllAlumni() {
            const query = document.getElementById('all-alumni-search').value;
            const year = document.getElementById('filter-graduation-year').value;
            const status = document.getElementById('filter-employment-status').value;
            loadAllAlumni(query, year, status);
        }

        function loadAllAlumni(query = '', year = '', status = '') {
            const params = new URLSearchParams();
            params.append('action', 'get_all_alumni');
            params.append('query', query);
            params.append('year', year);
            params.append('status', status);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                const listBody = document.getElementById('all-alumni-list');
                if (!Array.isArray(data) || data.length === 0) {
                    listBody.innerHTML = '<tr><td colspan="5">No alumni records found.</td></tr>';
                    return;
                }

                listBody.innerHTML = data.map(alumni => `
                    <tr>
                        <td>${alumni.full_name}</td>
                        <td>${alumni.course || 'N/A'}</td>
                        <td>${alumni.graduation_year || 'N/A'}</td>
                        <td>${alumni.employment_status || 'Unknown'}</td>
                        <td>${alumni.company ? alumni.company + ' / ' + (alumni.job_title || '') : alumni.job_title || 'N/A'}</td>
                    </tr>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading alumni list:', error);
            });
        }

        // Load alumni list for Edit tab
        function loadEditAlumniList(query = '', course = '', year = '') {
            const params = new URLSearchParams();
            params.append('action', 'get_all_alumni');
            params.append('query', query);
            params.append('course', course);
            params.append('year', year);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                const listBody = document.getElementById('edit-alumni-list');
                if (!Array.isArray(data) || data.length === 0) {
                    listBody.innerHTML = '<tr><td colspan="5">No alumni records found.</td></tr>';
                    return;
                }

                listBody.innerHTML = data.map(alumni => `
                    <tr>
                        <td>${alumni.full_name}</td>
                        <td>${alumni.course || 'N/A'}</td>
                        <td>${alumni.graduation_year || 'N/A'}</td>
                        <td>${alumni.employment_status || 'Unknown'}</td>
                        <td>
                            <button type="button" class="primary-button small" onclick="loadAlumniForEdit(${alumni.id})">Edit</button>
                            <button type="button" class="danger-button small" onclick="deleteAlumni(${alumni.id}, ${JSON.stringify(alumni.full_name)})">Delete</button>
                        </td>
                    </tr>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading edit alumni list:', error);
            });
        }

        function searchEditAlumni() {
            const query = document.getElementById('edit-alumni-search').value;
            const resultsDiv = document.getElementById('edit-alumni-results');
            const courseFilter = document.getElementById('edit-alumni-course-filter') ? document.getElementById('edit-alumni-course-filter').value : '';
            const yearFilter = document.getElementById('edit-alumni-graduation-year-filter') ? document.getElementById('edit-alumni-graduation-year-filter').value : '';

            // Hide inline results container since we will update the table instead
            if (resultsDiv) resultsDiv.innerHTML = '';

            if (query.length < 2) {
                // if query is short, reload the full table based on current filters
                loadEditAlumniList('', courseFilter, yearFilter);
                return;
            }

            // Use existing table loader to display filtered results
            loadEditAlumniList(query, courseFilter, yearFilter);
        }

        function loadAlumniForEdit(alumniId) {
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_alumni_details&alumni_id=${alumniId}`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.alumni) {
                    showNotification(data.message || 'Unable to load alumni details', 'error');
                    return;
                }

                const alumni = data.alumni;
                document.getElementById('edit-alumni-id').value = alumni.id;
                document.getElementById('edit-alumni-full-name').value = alumni.full_name || '';
                document.getElementById('edit-alumni-email').value = alumni.email || '';
                document.getElementById('edit-alumni-student-id').value = alumni.student_id || '';
                document.getElementById('edit-alumni-course').value = alumni.course || '';
                document.getElementById('edit-alumni-graduation-year').value = alumni.graduation_year || '';
                document.getElementById('edit-alumni-contact-number').value = alumni.contact_number || '';
                document.getElementById('edit-alumni-company').value = alumni.company || '';
                document.getElementById('edit-alumni-job-title').value = alumni.job_title || '';
                if (document.getElementById('edit-alumni-ladderized')) {
                    document.getElementById('edit-alumni-ladderized').value = alumni.ladderized_course || '';
                }
                document.getElementById('edit-alumni-achievements').value = alumni.achievements || '';
                document.getElementById('edit-alumni-quote').value = alumni.quote || '';

                const editModal = document.getElementById('editAlumniForm');
                if (editModal) {
                    editModal.classList.add('show');
                    editModal.setAttribute('aria-hidden', 'false');
                }
            })
            .catch(error => {
                console.error('Error loading alumni details:', error);
                showNotification('Error loading alumni details', 'error');
            });
        }

        function saveEditedAlumni(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'update_alumni');

            fetch('alumni_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Alumni updated successfully', 'success');
                    const editModal = document.getElementById('editAlumniForm');
                    if (editModal) {
                        editModal.classList.remove('show');
                        editModal.setAttribute('aria-hidden', 'true');
                    }
                    loadAllAlumni();
                    searchEditAlumni();
                } else {
                    showNotification(data.message || 'Error updating alumni', 'error');
                }
            })
            .catch(error => {
                console.error('Error saving alumni edits:', error);
                showNotification('Error updating alumni', 'error');
            });
        }

        function cancelEditAlumni() {
            const form = document.querySelector('#editAlumniForm form');
            if (form) {
                form.reset();
            }
            const editModal = document.getElementById('editAlumniForm');
            if (editModal) {
                editModal.classList.remove('show');
                editModal.setAttribute('aria-hidden', 'true');
            }
            const results = document.getElementById('edit-alumni-results');
            if (results) {
                results.innerHTML = '';
            }
            const searchInput = document.getElementById('edit-alumni-search');
            if (searchInput) {
                searchInput.value = '';
            }
        }

        function confirmDeleteAlumni() {
            const alumniId = document.getElementById('edit-alumni-id').value;
            if (!alumniId) {
                showNotification('No alumni selected to delete', 'error');
                return;
            }
            deleteAlumni(alumniId);
        }

        function deleteAlumni(alumniId, alumniName = '') {
            const confirmed = window.confirm(`Delete alumni record for ${alumniName || 'this person'}? This cannot be undone.`);
            if (!confirmed) {
                return;
            }

            const params = new URLSearchParams();
            params.append('action', 'delete_alumni');
            params.append('alumni_id', alumniId);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Alumni deleted successfully', 'success');
                    cancelEditAlumni();
                    loadAllAlumni();
                    document.getElementById('edit-alumni-results').innerHTML = '';
                } else {
                    showNotification(data.message || 'Error deleting alumni', 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting alumni:', error);
                showNotification('Error deleting alumni', 'error');
            });
        }

        // Alumni Information Functions (Onboarding Profiles)
        function getAlumniInfoFilters() {
            return {
                query: document.getElementById('alumni-info-search') ? document.getElementById('alumni-info-search').value : '',
                year: document.getElementById('alumni-info-year-filter') ? document.getElementById('alumni-info-year-filter').value : '',
                course: document.getElementById('alumni-info-course-filter') ? document.getElementById('alumni-info-course-filter').value : ''
            };
        }

        function applyAlumniInfoFilters() {
            const { query, year, course } = getAlumniInfoFilters();
            loadAlumniInfo(query, year, course);
        }

        function searchAlumniInfo() {
            applyAlumniInfoFilters();
        }

        function openArchiveAlumniModal() {
            const modal = document.getElementById('archiveAlumniModal');
            if (!modal) return;
            document.getElementById('archive-alumni-course').value = document.getElementById('alumni-info-course-filter') ? document.getElementById('alumni-info-course-filter').value : '';
            document.getElementById('archive-alumni-year').value = document.getElementById('alumni-info-year-filter') ? document.getElementById('alumni-info-year-filter').value : '';
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeArchiveAlumniModal() {
            const modal = document.getElementById('archiveAlumniModal');
            if (!modal) return;
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }

        function archiveSelectedAlumni() {
            const course = document.getElementById('archive-alumni-course')?.value || '';
            const year = document.getElementById('archive-alumni-year')?.value || '';

            console.log('Archiving with course:', course, 'year:', year);

            const params = new URLSearchParams();
            params.append('action', 'archive_alumni_by_filter');
            params.append('course', course);
            params.append('year', year);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => {
                console.log('Archive response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Archive response text:', text);
                try {
                    const data = JSON.parse(text);
                    if (data && data.success) {
                        closeArchiveAlumniModal();
                        showNotification(data.message || 'Alumni archived successfully', 'success');
                        applyAlumniInfoFilters();
                        if (typeof loadArchivedAlumni === 'function') {
                            loadArchivedAlumni();
                        }
                    } else {
                        showNotification(data && data.message ? data.message : 'Unable to archive alumni', 'error');
                    }
                } catch(e) {
                    console.error('Failed to parse response:', e);
                    showNotification('Server error: ' + text, 'error');
                }
            })
            .catch(error => {
                console.error('Error archiving alumni:', error);
                showNotification('Error archiving alumni: ' + error.message, 'error');
            });
        }

        function archiveSingleAlumni(alumniId) {
            if (!window.confirm('Archive this alumni record?')) {
                return;
            }

            const params = new URLSearchParams();
            params.append('action', 'archive_alumni');
            params.append('alumni_id', alumniId);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Alumni archived successfully', 'success');
                    applyAlumniInfoFilters();
                    if (typeof loadArchivedAlumni === 'function') {
                        loadArchivedAlumni();
                    }
                } else {
                    showNotification(data.message || 'Unable to archive alumni', 'error');
                }
            })
            .catch(error => {
                console.error('Error archiving alumni:', error);
                showNotification('Error archiving alumni: ' + error.message, 'error');
            });
        }

        function loadAlumniInfo(query = '', year = '', course = '') {
            const params = new URLSearchParams();
            params.append('action', 'get_alumni_info');
            params.append('query', query);
            params.append('year', year);
            params.append('course', course);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                const listBody = document.getElementById('alumni-info-list');
                if (!Array.isArray(data) || data.length === 0) {
                    listBody.innerHTML = '<tr><td colspan="18">No alumni information records found.</td></tr>';
                    return;
                }

                listBody.innerHTML = data.map(alumni => `
                    <tr>
                        <td>${alumni.full_name || 'N/A'}</td>
                        <td>${alumni.student_id || 'N/A'}</td>
                        <td>${alumni.course || 'N/A'}</td>
                        <td>${alumni.graduation_year || 'N/A'}</td>
                        <td>${alumni.gender || 'N/A'}</td>
                        <td>${alumni.civil_status || 'N/A'}</td>
                        <td>${alumni.dob || 'N/A'}</td>
                        <td>${alumni.nationality || 'N/A'}</td>
                        <td>${alumni.personal_email || 'N/A'}</td>
                        <td>${alumni.contact_number || 'N/A'}</td>
                        <td>${alumni.permanent_address || 'N/A'}</td>
                        <td>${alumni.employment_status || 'N/A'}</td>
                        <td>${alumni.company || 'N/A'}</td>
                        <td>${alumni.location || 'N/A'}</td>
                        <td>${alumni.industry || 'N/A'}</td>
                        <td>${alumni.salary_range || 'N/A'}</td>
                        <td>${alumni.job_description || 'N/A'}</td>
                        <td>
                            <button type="button" class="danger-button small" onclick="archiveSingleAlumni(${alumni.id})">
                                <i class="fa-solid fa-box-archive"></i> Archive
                            </button>
                        </td>
                    </tr>
                `).join('');
                // Enable drag-scroll after loading
                enableTableDragScroll();
            })
            .catch(error => {
                console.error('Error loading alumni info:', error);
            });
        }

        // Enable mouse drag scrolling for table
        function enableTableDragScroll() {
            const wrapper = document.querySelector('.alumni-info-table-wrapper');
            if (!wrapper) return;

            let isDown = false;
            let startX;
            let scrollLeft;

            wrapper.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX - wrapper.offsetLeft;
                scrollLeft = wrapper.scrollLeft;
                wrapper.style.cursor = 'grabbing';
                e.preventDefault();
            });

            wrapper.addEventListener('mouseleave', () => {
                isDown = false;
                wrapper.style.cursor = 'default';
            });

            wrapper.addEventListener('mouseup', () => {
                isDown = false;
                wrapper.style.cursor = 'default';
            });

            wrapper.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - wrapper.offsetLeft;
                const walk = (x - startX) * 1;
                wrapper.scrollLeft = scrollLeft - walk;
            });
        }

        // Career Tracking Functions
        function searchAlumniCareers() {
            const query = document.getElementById('career-search').value;
            if (query.length < 2) {
                document.getElementById('career-search-results').innerHTML = '';
                return;
            }

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=search_alumni&query=${encodeURIComponent(query)}`
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('career-search-results');
                if (!Array.isArray(data)) {
                    console.error('Unexpected career search response:', data);
                    resultsDiv.innerHTML = '<p>Error searching alumni.</p>';
                    return;
                }
                if (data.length === 0) {
                    resultsDiv.innerHTML = '<p>No alumni found.</p>';
                    return;
                }

                resultsDiv.innerHTML = data.map(alumni => `
                    <div class="search-result-item">
                        <div>
                            <strong>${alumni.full_name}</strong>
                            <small>${alumni.course} - ${alumni.graduation_year}</small>
                        </div>
                        <button type="button" class="primary-button small" onclick="addMilestone(${alumni.id})">Add Milestone</button>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error searching alumni:', error);
                showNotification('Error searching alumni', 'error');
            });
        }

        function addMilestone(alumniId) {
            document.getElementById('milestone-alumni-id').value = alumniId;
            document.getElementById('milestone-form').style.display = 'block';
            document.getElementById('career-search-results').innerHTML = '';
            document.getElementById('career-search').value = '';
        }

        function addCareerMilestone(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            formData.append('action', 'add_career_milestone');

            fetch('alumni_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Career milestone added successfully', 'success');
                    document.getElementById('milestone-form').style.display = 'none';
                    event.target.reset();
                    loadCareerMilestones();
                } else {
                    showNotification(data.message || 'Error adding milestone', 'error');
                }
            })
            .catch(error => {
                console.error('Error adding career milestone:', error);
                showNotification('Error adding milestone', 'error');
            });
        }

        function cancelMilestone() {
            document.getElementById('milestone-form').style.display = 'none';
            document.getElementById('add-milestone-form').reset();
        }

        function loadCareerMilestones() {
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_career_milestones'
            })
            .then(response => response.json())
            .then(data => {
                const milestonesDiv = document.getElementById('milestones-list');
                if (!Array.isArray(data)) {
                    console.error('Unexpected career milestones response', data);
                    milestonesDiv.innerHTML = '<p>Error loading career milestones.</p>';
                    return;
                }

                if (data.length === 0) {
                    milestonesDiv.innerHTML = '<p>No recent career milestones.</p>';
                    return;
                }

                milestonesDiv.innerHTML = data.map(milestone => `
                    <div class="history-item">
                        <div class="history-icon">
                            <i class="fa-solid fa-trophy"></i>
                        </div>
                        <div class="history-content">
                            <p><strong>${milestone.alumni_name}</strong> - ${milestone.milestone_type}</p>
                            <small>${milestone.title} · ${new Date(milestone.milestone_date).toLocaleDateString()}</small>
                        </div>
                    </div>
                `).join('');
            })
            .catch(error => {
                console.error('Error loading career milestones:', error);
            });
        }

        function initAlumniTabs() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabPanels = document.querySelectorAll('.tab-panel');

            if (!tabButtons.length || !tabPanels.length) {
                return;
            }

            tabButtons.forEach(button => {
                button.addEventListener('click', function(event) {
                    event.preventDefault();
                    const targetTab = this.getAttribute('data-tab');
                    const targetPanel = document.getElementById(targetTab);
                    if (!targetTab || !targetPanel) {
                        return;
                    }

                    tabButtons.forEach(btn => {
                        btn.classList.remove('active');
                        btn.setAttribute('aria-selected', 'false');
                    });
                    tabPanels.forEach(panel => {
                        panel.classList.remove('active');
                        panel.setAttribute('aria-hidden', 'true');
                    });

                    this.classList.add('active');
                    this.setAttribute('aria-selected', 'true');
                    targetPanel.classList.add('active');
                    targetPanel.setAttribute('aria-hidden', 'false');
                    // when Edit Alumni tab becomes active, refresh its list
                    if (targetTab === 'edit-alumni-tab') {
                        const course = document.getElementById('edit-alumni-course-filter') ? document.getElementById('edit-alumni-course-filter').value : '';
                        const year = document.getElementById('edit-alumni-graduation-year-filter') ? document.getElementById('edit-alumni-graduation-year-filter').value : '';
                        loadEditAlumniList('', course, year);
                    }
                    // when Alumni Information tab becomes active, load alumni info
                    if (targetTab === 'alumni-info-tab') {
                        const { query, year, course } = getAlumniInfoFilters();
                        loadAlumniInfo(query, year, course);
                    }
                    // when Alumni Tracer tab becomes active, load tracer data
                    if (targetTab === 'alumni-tracer-tab') {
                        initTracerSubtabs();
                        loadBatchYears();
                        loadTracerResponses();
                    }
                });
            });

        }

        function initializePage() {
            initAlumniTabs();
            loadEmploymentHistory();
            loadAllAlumni();
            populateCourseAndYearDropdowns();
            // preload edit alumni list so it's ready when user opens the tab
            loadEditAlumniList();
        }

        function populateCourseAndYearDropdowns() {
            // Populate graduation years dynamically for Add Alumni form
            const addYearSelect = document.getElementById('alumni-graduation-year');
            const editYearSelect = document.getElementById('edit-alumni-graduation-year');
            const editYearFilter = document.getElementById('edit-alumni-graduation-year-filter');
            const currentYear = new Date().getFullYear();
            
            const yearOptions = '<option value="">Select Year</option>' + Array.from({length: currentYear - 1979}, (_, i) => {
                const year = currentYear - i;
                return `<option value="${year}">${year}</option>`;
            }).join('');
            
            if (addYearSelect) {
                addYearSelect.innerHTML = yearOptions;
            }
            if (editYearSelect) {
                editYearSelect.innerHTML = yearOptions;
            }
            if (editYearFilter) {
                // include an "All Years" option at the top
                editYearFilter.innerHTML = '<option value="">All Years</option>' + Array.from({length: currentYear - 1979}, (_, i) => {
                    const year = currentYear - i;
                    return `<option value="${year}">${year}</option>`;
                }).join('');
            }

            // Populate course options by copying from add form select if available
            const addCourseSelect = document.getElementById('alumni-course');
            const editCourseSelect = document.getElementById('edit-alumni-course');
            const editCourseFilter = document.getElementById('edit-alumni-course-filter');
            if (addCourseSelect && editCourseSelect) {
                // Clone options
                editCourseSelect.innerHTML = '<option value="">Select Course</option>' + Array.from(addCourseSelect.options).slice(1).map(opt => {
                    return `<option value="${opt.value}">${opt.text}</option>`;
                }).join('');
            }
            if (addCourseSelect && editCourseFilter) {
                editCourseFilter.innerHTML = '<option value="">All Courses</option>' + Array.from(addCourseSelect.options).slice(1).map(opt => {
                    return `<option value="${opt.value}">${opt.text}</option>`;
                }).join('');
            }
            // attach change handlers to filters
            if (document.getElementById('edit-alumni-course-filter')) {
                document.getElementById('edit-alumni-course-filter').addEventListener('change', () => {
                    const course = document.getElementById('edit-alumni-course-filter').value;
                    const year = document.getElementById('edit-alumni-graduation-year-filter') ? document.getElementById('edit-alumni-graduation-year-filter').value : '';
                    loadEditAlumniList('', course, year);
                });
            }
            if (document.getElementById('edit-alumni-graduation-year-filter')) {
                document.getElementById('edit-alumni-graduation-year-filter').addEventListener('change', () => {
                    const course = document.getElementById('edit-alumni-course-filter') ? document.getElementById('edit-alumni-course-filter').value : '';
                    const year = document.getElementById('edit-alumni-graduation-year-filter').value;
                    loadEditAlumniList('', course, year);
                });
            }
        }

        function setupSidebarSubmenu() {
            const sideNav = document.querySelector('.side-nav');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const navGroups = document.querySelectorAll('.nav-group');

            if (!sideNav || !sidebarToggle || !navGroups.length) return;

            function closeAllSubmenus() {
                navGroups.forEach(group => {
                    const btn = group.querySelector('.nav-toggle');
                    const sm = group.querySelector('.submenu');
                    const arrow = group.querySelector('.toggle-arrow');
                    if (btn) btn.setAttribute('aria-expanded', 'false');
                    if (sm) {
                        sm.setAttribute('aria-hidden', 'true');
                        sm.classList.remove('open');
                        sm.style.display = 'none';
                    }
                    if (arrow) arrow.style.transform = 'rotate(0deg)';
                });
            }

            function openSubmenuFor(group) {
                const btn = group.querySelector('.nav-toggle');
                const sm = group.querySelector('.submenu');
                const arrow = group.querySelector('.toggle-arrow');
                if (btn) btn.setAttribute('aria-expanded', 'true');
                if (sm) {
                    sm.setAttribute('aria-hidden', 'false');
                    sm.classList.add('open');
                    sm.style.display = 'grid';
                }
                if (arrow) arrow.style.transform = 'rotate(180deg)';
            }

            // Initialize each nav-group's toggle
            navGroups.forEach(group => {
                const btn = group.querySelector('.nav-toggle');
                const sm = group.querySelector('.submenu');
                const arrow = group.querySelector('.toggle-arrow');
                if (!btn || !sm) return;

                // Set initial state from markup
                const initiallyExpanded = btn.getAttribute('aria-expanded') === 'true';
                if (initiallyExpanded) {
                    openSubmenuFor(group);
                } else {
                    sm.setAttribute('aria-hidden', 'true');
                    sm.classList.remove('open');
                    sm.style.display = 'none';
                    if (arrow) arrow.style.transform = 'rotate(0deg)';
                }

                btn.addEventListener('click', function (e) {
                    const isExpanded = btn.getAttribute('aria-expanded') === 'true';
                    if (isExpanded) {
                        btn.setAttribute('aria-expanded', 'false');
                        sm.setAttribute('aria-hidden', 'true');
                        sm.classList.remove('open');
                        sm.style.display = 'none';
                        if (arrow) arrow.style.transform = 'rotate(0deg)';
                    } else {
                        // close others, then open this one
                        closeAllSubmenus();
                        openSubmenuFor(group);
                    }
                });
            });

            // Close all when sidebar collapses
            sidebarToggle.addEventListener('click', function () {
                sideNav.classList.toggle('collapsed');
                if (sideNav.classList.contains('collapsed')) {
                    closeAllSubmenus();
                }
            });

            // Close when pointer leaves the sidebar
            sideNav.addEventListener('pointerleave', function () {
                closeAllSubmenus();
            });

            // Close when clicking or touching outside the side nav
            document.addEventListener('click', function (e) {
                if (!sideNav.contains(e.target)) {
                    closeAllSubmenus();
                }
            });
            document.addEventListener('touchstart', function (e) {
                if (!sideNav.contains(e.target)) {
                    closeAllSubmenus();
                }
            }, { passive: true });

            // Close on window resize
            window.addEventListener('resize', closeAllSubmenus);
        }

        // Alumni Tracer Functions
        function initTracerSubtabs() {
            const subTabBtns = document.querySelectorAll('.tracer-subtab-btn');
            const subTabs = document.querySelectorAll('.tracer-subtab');

            subTabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetSubtab = this.getAttribute('data-subtab');
                    
                    // Remove active from all
                    subTabBtns.forEach(b => {
                        b.style.color = '#666';
                        b.style.borderBottomColor = 'transparent';
                        b.classList.remove('active');
                    });
                    subTabs.forEach(tab => tab.style.display = 'none');

                    // Add active to clicked
                    this.style.color = '#800000';
                    this.style.borderBottomColor = '#800000';
                    this.classList.add('active');
                    document.getElementById(targetSubtab).style.display = 'block';

                    // Load data for specific subtab
                    if (targetSubtab === 'survey-config-subtab') {
                        loadSurveyQuestions();
                    } else if (targetSubtab === 'view-responses-subtab') {
                        loadTracerResponses();
                    }
                });
            });

            // Add survey section button listeners
            const surveyBtns = document.querySelectorAll('.survey-section-btn');
            surveyBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Update active state - both class AND styling
                    surveyBtns.forEach(b => {
                        b.classList.remove('active');
                        b.style.background = '#6c757d';
                        b.style.color = 'white';
                    });
                    this.classList.add('active');
                    this.style.background = '#800000';
                    this.style.color = 'white';
                    
                    console.log('Survey section button clicked:', this.getAttribute('data-section'));
                    
                    // Load questions for new section
                    loadSurveyQuestions();
                });
            });

            // Add modal close handler (click outside modal)
            const modal = document.getElementById('addQuestionModal');
            if (modal) {
                window.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        closeAddQuestionModal();
                    }
                });
            }
        }

        function loadBatchYears() {
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_batch_years'
            })
            .then(response => response.json())
            .then(data => {
                const yearSelect = document.getElementById('batch-year-select');
                if (yearSelect && data.years) {
                    yearSelect.innerHTML = '<option value="">-- Select Year --</option>' + 
                        data.years.map(y => `<option value="${y.year}">${y.year} (${y.count} alumni)</option>`).join('');
                    
                    // Add change event
                    yearSelect.onchange = function() {
                        if (this.value) {
                            document.getElementById('batch-count').value = this.options[this.selectedIndex].text.match(/\((\d+)/)[1];
                        } else {
                            document.getElementById('batch-count').value = '';
                        }
                    };
                }
            })
            .catch(error => console.error('Error loading batch years:', error));
        }

        function sendTracerEmails() {
            // Check if EmailJS is loaded
            if (typeof emailjs === 'undefined') {
                alert('Email service is not loaded. Please refresh the page and try again.');
                return;
            }

            const year = document.getElementById('batch-year-select').value;
            if (!year) {
                alert('Please select a graduation year');
                return;
            }

            const statusDiv = document.getElementById('email-status');
            statusDiv.style.display = 'block';
            statusDiv.style.background = '#f3e6e6';
            statusDiv.style.color = '#800000';
            statusDiv.style.borderLeft = '4px solid #800000';
            statusDiv.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Fetching alumni list...';

            // Step 1: Get alumni list from backend
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_alumni_for_tracer&year=${year}`
            })
            .then(response => response.json())
            .then(alumniList => {
                if (!Array.isArray(alumniList) || alumniList.length === 0) {
                    statusDiv.style.background = '#f8d7da';
                    statusDiv.style.color = '#721c24';
                    statusDiv.style.borderLeft = '4px solid #dc3545';
                    statusDiv.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> No alumni found for year ' + year;
                    return;
                }

                statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Sending emails to ${alumniList.length} alumni...`;
                
                // Step 2: Send email to each alumni
                let successCount = 0;
                let failureCount = 0;
                let sentCount = 0;

                alumniList.forEach((alumni, index) => {
                    // Use setTimeout to send emails sequentially to avoid rate limiting
                    setTimeout(() => {
                        const templateParams = {
                            user_name: alumni.full_name || 'Alumni',
                            email: alumni.personal_email || alumni.email,
                            course: alumni.course || 'N/A',
                            graduation_year: alumni.graduation_year || year,
                            tracer_link: new URL(`/THESIS/SUPPORTSERVICESYSTEM/alumni_tracer_form.php?alumni_id=${encodeURIComponent(alumni.id)}`, window.location.origin).href
                        };

                        emailjs.send(window.emailJsConfig.serviceId, window.emailJsConfig.templateId, templateParams)
                            .then(response => {
                                successCount++;
                                sentCount++;
                                const progress = Math.floor((sentCount / alumniList.length) * 100);
                                statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Sending emails (${sentCount}/${alumniList.length} - ${progress}%)...`;
                            })
                            .catch(error => {
                                failureCount++;
                                sentCount++;
                                console.error('Email send failed for ' + alumni.full_name + ':', error);
                                const progress = Math.floor((sentCount / alumniList.length) * 100);
                                statusDiv.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Sending emails (${sentCount}/${alumniList.length} - ${progress}%)...`;
                            });
                    }, index * 800); // 800ms delay between emails to avoid rate limiting
                });

                // Step 3: Update status after all emails are sent
                setTimeout(() => {
                    if (failureCount === 0) {
                        statusDiv.style.background = '#d4edda';
                        statusDiv.style.color = '#155724';
                        statusDiv.style.borderLeft = '4px solid #28a745';
                        statusDiv.innerHTML = `<i class="fa-solid fa-check-circle"></i> ✓ Successfully sent ${successCount} tracer invitation emails to alumni from ${year}`;
                    } else {
                        statusDiv.style.background = '#fff3cd';
                        statusDiv.style.color = '#856404';
                        statusDiv.style.borderLeft = '4px solid #ffc107';
                        statusDiv.innerHTML = `<i class="fa-solid fa-exclamation-triangle"></i> Sent ${successCount} emails successfully, ${failureCount} failed. Please check the console for details.`;
                    }
                }, (alumniList.length * 800) + 500);

                // Log batch in database
                fetch('alumni_management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=log_batch_emails&year=${year}&count=${alumniList.length}`
                }).catch(err => console.error('Error logging batch:', err));
            })
            .catch(error => {
                statusDiv.style.background = '#f8d7da';
                statusDiv.style.color = '#721c24';
                statusDiv.style.borderLeft = '4px solid #dc3545';
                statusDiv.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> Error: ${error.message}`;
                console.error('Error fetching alumni:', error);
            });

            // Attach swipe navigation for tracer sub-tabs on touch devices / small screens
            const tracerHeader = document.querySelector('.tracer-subtabs-header');
            if (tracerHeader) {
                attachSwipeNavigation(tracerHeader, Array.from(subTabBtns), function(idx) {
                    const btn = subTabBtns[idx];
                    if (btn) btn.click();
                });
            }
        }

        // Generic swipe navigation helper
        function attachSwipeNavigation(container, buttons, onSelect) {
            if (!container || !buttons || buttons.length === 0) return;
            let startX = 0, startY = 0, distX = 0, threshold = 60;

            container.addEventListener('touchstart', function(e) {
                const t = e.changedTouches[0];
                startX = t.pageX;
                startY = t.pageY;
            }, { passive: true });

            container.addEventListener('touchend', function(e) {
                const t = e.changedTouches[0];
                distX = t.pageX - startX;
                const distY = t.pageY - startY;

                // ignore mostly-vertical moves
                if (Math.abs(distX) < Math.abs(distY)) return;

                if (Math.abs(distX) > threshold) {
                    // find current active index
                    let activeIndex = buttons.findIndex(b => b.classList.contains('active'));
                    if (activeIndex === -1) activeIndex = 0;

                    if (distX < 0) {
                        // swipe left -> next
                        const next = Math.min(buttons.length - 1, activeIndex + 1);
                        if (next !== activeIndex) {
                            onSelect(next);
                            buttons[next].scrollIntoView({ behavior: 'smooth', inline: 'center' });
                        }
                    } else {
                        // swipe right -> prev
                        const prev = Math.max(0, activeIndex - 1);
                        if (prev !== activeIndex) {
                            onSelect(prev);
                            buttons[prev].scrollIntoView({ behavior: 'smooth', inline: 'center' });
                        }
                    }
                }
            }, { passive: true });
        }

        function loadSurveyQuestions() {
            const container = document.getElementById('survey-questions-container');
            const section = document.querySelector('.survey-section-btn.active').getAttribute('data-section');
            
            console.log('loadSurveyQuestions: Loading questions for section:', section);
            
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_survey_questions&section=${section}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('loadSurveyQuestions: Received', data.length, 'questions for section:', section);
                if (Array.isArray(data) && data.length > 0) {
                    container.innerHTML = data.map(q => {
                        let typeInfo = '';
                        if (q.question_type === 'choice' && q.answer_choices) {
                            const choices = q.answer_choices.split(',');
                            typeInfo = `<div style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                                <strong>Choices:</strong> ${choices.map(c => `<span style="display: inline-block; background: #e9ecef; padding: 3px 8px; margin: 2px; border-radius: 3px;">${c.trim()}</span>`).join('')}
                            </div>`;
                        } else if (q.question_type === 'text' && q.answer_choices) {
                            typeInfo = `<div style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                                <strong>Placeholder:</strong> <em>"${q.answer_choices}"</em>
                            </div>`;
                        }

                        return `
                            <div style="padding: 15px; background: white; border-radius: 5px; margin-bottom: 10px; border-left: 4px solid ${q.is_default ? '#800000' : '#28a745'}; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                <div style="display: flex; justify-content: space-between; align-items: start;">
                                    <div style="flex: 1;">
                                        <strong style="font-size: 15px; color: #333;">${q.question_text}</strong>
                                        <div style="font-size: 12px; color: #666; margin-top: 6px;">
                                            <span style="display: inline-block; background: ${q.question_type === 'choice' ? '#e3f2fd' : '#fff3e0'}; padding: 3px 8px; border-radius: 3px; margin-right: 8px;">
                                                ${q.question_type === 'choice' ? '📋 Multiple Choice' : '📝 Text Input'}
                                            </span>
                                            ${q.is_default ? '<span style="display: inline-block; background: #e3f2fd; padding: 3px 8px; border-radius: 3px; color: #1976d2;">🔒 Default</span>' : '<span style="display: inline-block; background: #f0f9ff; padding: 3px 8px; border-radius: 3px; color: #059669;">✓ Custom</span>'}
                                        </div>
                                        ${typeInfo}
                                    </div>
                                    ${!q.is_default ? `<button onclick="removeQuestion(${q.id})" style="padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; margin-left: 10px;">
                                        <i class="fa-solid fa-trash"></i> Remove
                                    </button>` : ''}
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<div style="text-align: center; color: #666; padding: 20px;"><i class="fa-solid fa-inbox"></i> No custom questions yet. Click "Add Custom Question" to create one.</div>';
                }
            })
            .catch(error => {
                container.innerHTML = '<p style="color: #dc3545;"><i class="fa-solid fa-exclamation-circle"></i> Error loading questions: ' + error.message + '</p>';
            });
        }

        // Modal Functions for Add Question
        function openAddQuestionModal() {
            const modal = document.getElementById('addQuestionModal');
            modal.style.display = 'flex';
            // Reset form
            document.getElementById('question-text').value = '';
            document.getElementById('placeholder-text').value = '';
            document.getElementById('employment-status').value = 'all';
            document.querySelector('input[name="question-type"][value="text"]').checked = true;
            resetChoicesInputs();
            updateQuestionTypeUI();
        }

        function closeAddQuestionModal() {
            const modal = document.getElementById('addQuestionModal');
            modal.style.display = 'none';
        }

        function updateQuestionTypeUI() {
            const type = document.querySelector('input[name="question-type"]:checked').value;
            const placeholderSection = document.getElementById('placeholder-section');
            const choicesSection = document.getElementById('choices-section');

            if (type === 'text') {
                placeholderSection.style.display = 'block';
                choicesSection.style.display = 'none';
            } else {
                placeholderSection.style.display = 'none';
                choicesSection.style.display = 'block';
            }
        }

        function resetChoicesInputs() {
            const container = document.getElementById('choices-container');
            container.innerHTML = `
                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <input type="text" class="choice-input" placeholder="Choice 1" style="flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                    <button type="button" onclick="removeChoiceInput(this)" style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Remove</button>
                </div>
                <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                    <input type="text" class="choice-input" placeholder="Choice 2" style="flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                    <button type="button" onclick="removeChoiceInput(this)" style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Remove</button>
                </div>
            `;
        }

        function addChoiceInput() {
            const container = document.getElementById('choices-container');
            const choiceNum = container.querySelectorAll('.choice-input').length + 1;
            const div = document.createElement('div');
            div.style.cssText = 'display: flex; gap: 8px; margin-bottom: 8px;';
            div.innerHTML = `
                <input type="text" class="choice-input" placeholder="Choice ${choiceNum}" style="flex: 1; padding: 10px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                <button type="button" onclick="removeChoiceInput(this)" style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">Remove</button>
            `;
            container.appendChild(div);
        }

        function removeChoiceInput(btn) {
            const container = document.getElementById('choices-container');
            const inputs = container.querySelectorAll('.choice-input');
            if (inputs.length > 2) {
                btn.parentElement.remove();
            } else {
                alert('You must have at least 2 choices');
            }
        }

        function submitNewQuestion() {
            const questionText = document.getElementById('question-text').value.trim();
            const type = document.querySelector('input[name="question-type"]:checked').value;
            const employmentStatus = document.getElementById('employment-status').value;

            console.log('submitNewQuestion DEBUG:', {
                questionText: questionText,
                type: type,
                employmentStatus: employmentStatus,
                elementExists: document.getElementById('employment-status') ? 'YES' : 'NO'
            });

            if (!questionText) {
                alert('Please enter a question');
                return;
            }

            let additionalData = '';
            if (type === 'text') {
                additionalData = document.getElementById('placeholder-text').value.trim();
            } else {
                const choices = Array.from(document.querySelectorAll('.choice-input')).map(input => input.value.trim()).filter(v => v);
                if (choices.length < 2) {
                    alert('Please add at least 2 choices');
                    return;
                }
                additionalData = choices.join(',');
            }

            const postData = `action=add_survey_question&question=${encodeURIComponent(questionText)}&type=${type}&employment_status=${employmentStatus}&data=${encodeURIComponent(additionalData)}`;
            console.log('POST DATA:', postData);

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: postData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                if (data.success) {
                    closeAddQuestionModal();
                    loadSurveyQuestions();
                    alert('Question added successfully!');
                } else {
                    alert('Error adding question: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error: ' + error.message);
            });
        }

        function removeQuestion(id) {
            if (!confirm('Remove this question?')) return;

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=remove_survey_question&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadSurveyQuestions();
                    alert('Question removed successfully!');
                } else {
                    alert('Error removing question');
                }
            })
            .catch(error => alert('Error: ' + error.message));
        }

        function saveSurveyConfig() {
            alert('Survey configuration saved successfully!');
        }

        function loadTracerResponses() {
            const search = document.getElementById('response-search') ? document.getElementById('response-search').value : '';
            const year = document.getElementById('response-year') ? document.getElementById('response-year').value : '';
            const employment = document.getElementById('response-employment') ? document.getElementById('response-employment').value : '';

            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_tracer_responses&search=${encodeURIComponent(search)}&year=${year}&employment=${employment}`
            })
            .then(response => response.json())
            .then(data => {
                const listBody = document.getElementById('responses-list');
                if (Array.isArray(data) && data.length > 0) {
                    listBody.innerHTML = data.map(r => `
                        <tr style="border-bottom: 1px solid #ddd; hover: {background: #f8f9fa;}">
                            <td style="padding: 12px;">${r.full_name}</td>
                            <td style="padding: 12px;">${r.course}</td>
                            <td style="padding: 12px;">${r.graduation_year}</td>
                            <td style="padding: 12px;"><span style="display: inline-block; padding: 4px 8px; background: #e3f2fd; color: #1976d2; border-radius: 3px; font-size: 12px;">${r.employment_status || 'N/A'}</span></td>
                            <td style="padding: 12px; font-size: 13px; color: #666;">${new Date(r.created_at).toLocaleDateString()}</td>
                            <td style="padding: 12px; text-align: center;">
                                <button onclick="viewResponse(${r.id})" style="padding: 5px 12px; background: #800000; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">View</button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    listBody.innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: #666;">No responses found.</td></tr>';
                }
            })
            .catch(error => {
                document.getElementById('responses-list').innerHTML = '<tr><td colspan="6" style="padding: 20px; text-align: center; color: #dc3545;">Error loading responses</td></tr>';
            });
        }

        function viewResponse(responseId) {
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=get_response_detail&id=${responseId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const responseData = data.response;
                    
                    // Organize fields into sections
                    const sections = {
                        'Basic Information': ['full_name', 'student_id', 'graduation_year', 'course'],
                        'Personal Information': ['gender', 'civil_status', 'date_of_birth', 'nationality', 'personal_email', 'mobile_number', 'address'],
                        'Employment Information': ['employment_status', 'company_title', 'employment_location', 'industry', 'salary_range', 'job_description'],
                        'Self-Employed Information': ['self_employed_description', 'self_employed_industry', 'self_employed_location', 'self_employed_income'],
                        'Unemployment Information': ['unemployment_reason', 'unemployment_comments'],
                        'Additional Responses': []
                    };
                    
                    // Add any remaining fields not in defined sections
                    const definedKeys = new Set();
                    Object.values(sections).forEach(keys => keys.forEach(k => definedKeys.add(k)));
                    Object.keys(responseData).forEach(key => {
                        if (!definedKeys.has(key)) {
                            sections['Additional Responses'].push(key);
                        }
                    });
                    
                    // Build modal content with sections
                    let modalContent = '';
                    Object.entries(sections).forEach(([sectionTitle, keys]) => {
                        const sectionFields = keys.filter(key => responseData.hasOwnProperty(key) && responseData[key]);
                        if (sectionFields.length > 0) {
                            modalContent += `<div style="margin-bottom: 25px;">
                                <h4 style="color: #800000; border-bottom: 2px solid #800000; padding-bottom: 8px; margin-bottom: 15px; font-size: 14px; font-weight: 700; text-transform: uppercase;">${sectionTitle}</h4>`;
                            sectionFields.forEach(key => {
                                const value = responseData[key];
                                const label = key.replace(/_/g, ' ').toLowerCase().split(' ')
                                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                                    .join(' ');
                                modalContent += `
                                <div style="margin-bottom: 12px;">
                                    <strong style="color: #333; display: block; font-size: 13px;">${label}</strong>
                                    <p style="margin: 4px 0 0 0; color: #666; font-size: 13px;">${value || 'N/A'}</p>
                                </div>`;
                            });
                            modalContent += '</div>';
                        }
                    });

                    const modal = document.createElement('div');
                    modal.className = 'alumni-response-modal';
                    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;';
                    modal.innerHTML = `
                        <div style="background: white; border-radius: 8px; max-width: 700px; width: 95%; max-height: 85vh; overflow-y: auto; padding: 30px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                                <h3 style="margin: 0; color: #333; font-size: 20px;">Alumni Response Details</h3>
                                <button onclick="this.closest('.alumni-response-modal').remove()" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #999; hover: #333;">&times;</button>
                            </div>
                            <div>${modalContent}</div>
                            <button onclick="this.closest('.alumni-response-modal').remove()" style="width: 100%; padding: 12px; background: #800000; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; margin-top: 20px; font-size: 14px;">Close</button>
                        </div>
                    `;
                    document.body.appendChild(modal);
                }
            })
            .catch(error => alert('Error loading response details'));
        }

        // Survey section button handlers
        document.addEventListener('DOMContentLoaded', function() {
            const surveyBtns = document.querySelectorAll('.survey-section-btn');
            surveyBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    surveyBtns.forEach(b => {
                        b.style.background = '#6c757d';
                    });
                    this.style.background = '#800000';
                    loadSurveyQuestions();
                });
            });

            // Add event listeners to response filters
            const responseSearch = document.getElementById('response-search');
            const responseYear = document.getElementById('response-year');
            const responseEmployment = document.getElementById('response-employment');

            if (responseSearch) responseSearch.addEventListener('input', loadTracerResponses);
            if (responseYear) responseYear.addEventListener('change', loadTracerResponses);
            if (responseEmployment) responseEmployment.addEventListener('change', loadTracerResponses);

            // Populate response year filter
            fetch('alumni_management.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_batch_years'
            })
            .then(response => response.json())
            .then(data => {
                if (data.years && responseYear) {
                    responseYear.innerHTML = '<option value="">-- All Years --</option>' +
                        data.years.map(y => `<option value="${y.year}">${y.year}</option>`).join('');
                }
            });
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                initializePage();
                setupSidebarSubmenu();
            });
        } else {
            initializePage();
            setupSidebarSubmenu();
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