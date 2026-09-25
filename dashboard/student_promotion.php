<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = false;

$pdo = get_db();

// Get student progression statistics
$progressStats = $pdo->query("
    SELECT
        COUNT(DISTINCT sp.user_id) as total_students,
        SUM(CASE WHEN sp.academic_status = 'passed' AND sp.enrollment_status = 'active' THEN 1 ELSE 0 END) as passed_count,
        SUM(CASE WHEN sp.academic_status = 'failed' AND sp.enrollment_status = 'active' THEN 1 ELSE 0 END) as failed_count,
        SUM(CASE WHEN sp.academic_status = 'irregular' AND sp.enrollment_status = 'active' THEN 1 ELSE 0 END) as irregular_count,
        SUM(CASE WHEN sp.enrollment_status = 'transferred' THEN 1 ELSE 0 END) as transferred_count,
        SUM(CASE WHEN sp.enrollment_status = 'discontinued' THEN 1 ELSE 0 END) as discontinued_count,
        SUM(CASE WHEN sp.enrollment_status = 'graduating' THEN 1 ELSE 0 END) as graduating_count,
        SUM(CASE WHEN sp.enrollment_status = 'graduated' THEN 1 ELSE 0 END) as graduated_count
    FROM ssaa_student_progression sp
    JOIN users u ON sp.user_id = u.id
    WHERE u.role = 'student'
")->fetch(PDO::FETCH_ASSOC);

// Get recent promotions
$recentPromotions = $pdo->query("
    SELECT sp.*, u.full_name as student_name
    FROM ssaa_student_progression sp
    JOIN users u ON sp.user_id = u.id
    WHERE sp.academic_status = 'passed'
    ORDER BY sp.updated_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$courseOptions = [
    'Bachelor of Science in Accountancy',
    'Bachelor of Science in Business Administration',
    'Bachelor in Elementary Education',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Criminology',
    'Bachelor of Science in Hospitality Management',
    'Bachelor of Science in Tourism Management',
    'Associate in Computer Technology',
    'Associate in Business Knowledge',
    'Associate in Hospitality Management',
    'Associate in Tourism Management',
];

function advance_course_year(string $courseYear, string $academicStatus = 'passed', string $enrollmentStatus = 'active'): array {
    $courseYear = trim($courseYear);
    if ($courseYear === '') {
        return ['new_course_year' => '', 'action' => 'no_change', 'reason' => 'Empty course year'];
    }

    // Define 2-year programs that graduate at 2nd year
    $twoYearCourses = ['ACT', 'ABK', 'AHM', 'ATM'];

    $parts = explode(' ', $courseYear);
    $last = array_pop($parts);
    $course = implode(' ', $parts);
    $result = ['new_course_year' => $courseYear, 'action' => 'no_change', 'reason' => 'Unknown course structure'];

    // Check if this is a 2-year program
    $isTwoYearProgram = false;
    foreach ($twoYearCourses as $code) {
        if (stripos($course, $code) !== false) {
            $isTwoYearProgram = true;
            break;
        }
    }

    if (is_numeric($last)) {
        $year = (int) $last;

        // Check enrollment status first
        if ($enrollmentStatus !== 'active') {
            return [
                'new_course_year' => $courseYear,
                'action' => 'no_change',
                'reason' => "Student is $enrollmentStatus - no promotion possible"
            ];
        }

        // Check academic status
        if ($academicStatus === 'failed' || $academicStatus === 'retained') {
            return [
                'new_course_year' => $courseYear,
                'action' => 'retain',
                'reason' => "Student $academicStatus - retained in current level"
            ];
        }

        if ($academicStatus === 'irregular') {
            return [
                'new_course_year' => $courseYear,
                'action' => 'irregular',
                'reason' => 'Student has irregular status - requires review'
            ];
        }

        // Handle promotion logic based on program type
        if ($isTwoYearProgram) {
            // 2-year programs: 1st Year -> 2nd Year -> Graduate
            if ($year === 1) {
                $result = [
                    'new_course_year' => trim($course . ' 2'),
                    'action' => 'promote',
                    'reason' => 'Advanced to 2nd Year (2-year program)'
                ];
            } elseif ($year === 2) {
                $result = [
                    'new_course_year' => $courseYear,
                    'action' => 'graduating',
                    'reason' => 'Ready for graduation from 2-year program'
                ];
            } else {
                $result = [
                    'new_course_year' => $courseYear,
                    'action' => 'no_change',
                    'reason' => 'Already graduated or invalid year for 2-year program'
                ];
            }
        } else {
            // 4-year programs: 1st Year -> 2nd Year -> 3rd Year -> 4th Year -> Graduate
            if ($year >= 1 && $year < 4) {
                $nextYear = $year + 1;
                $result = [
                    'new_course_year' => trim($course . ' ' . $nextYear),
                    'action' => 'promote',
                    'reason' => "Advanced to {$nextYear} Year"
                ];
            } elseif ($year === 4) {
                $result = [
                    'new_course_year' => $courseYear,
                    'action' => 'graduating',
                    'reason' => 'Ready for graduation from 4-year program'
                ];
            } else {
                $result = [
                    'new_course_year' => $courseYear,
                    'action' => 'no_change',
                    'reason' => 'Already graduated or invalid year for 4-year program'
                ];
            }
        }
    } elseif (strtolower($last) === 'graduate') {
        $result = [
            'new_course_year' => $courseYear,
            'action' => 'no_change',
            'reason' => 'Already graduated'
        ];
    }

    return $result;
}

// Handle student transfer scenarios
function handle_student_transfer(PDO $pdo, int $userId, string $transferReason, int $adminId): array {
    try {
        $pdo->beginTransaction();

        // Update student progression record
        $stmt = $pdo->prepare("
            UPDATE ssaa_student_progression
            SET enrollment_status = 'transferred',
                transfer_date = CURDATE(),
                transfer_reason = ?,
                promotion_eligible = 0,
                updated_at = NOW()
            WHERE user_id = ? AND enrollment_status = 'active'
        ");
        $stmt->execute([$transferReason, $userId]);

        // Update user record for data security
        $stmt = $pdo->prepare("
            UPDATE users
            SET enrollment_status = 'transferred',
                account_deactivation_date = CURDATE(),
                deactivation_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$transferReason, $userId]);

        // Log the action
        log_audit_action($pdo, $adminId, $userId, 'transfer', "Student transferred: $transferReason");

        $pdo->commit();
        return ['success' => true, 'message' => 'Student transfer processed successfully'];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error processing transfer: ' . $e->getMessage()];
    }
}

// Handle student discontinuation
function handle_student_discontinuation(PDO $pdo, int $userId, string $discontinuationReason, int $adminId): array {
    try {
        $pdo->beginTransaction();

        // Update student progression record
        $stmt = $pdo->prepare("
            UPDATE ssaa_student_progression
            SET enrollment_status = 'discontinued',
                discontinuation_date = CURDATE(),
                discontinuation_reason = ?,
                promotion_eligible = 0,
                updated_at = NOW()
            WHERE user_id = ? AND enrollment_status = 'active'
        ");
        $stmt->execute([$discontinuationReason, $userId]);

        // Update user record for data security
        $stmt = $pdo->prepare("
            UPDATE users
            SET enrollment_status = 'discontinued',
                account_deactivation_date = CURDATE(),
                deactivation_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$discontinuationReason, $userId]);

        // Log the action
        log_audit_action($pdo, $adminId, $userId, 'discontinuation', "Student discontinued studies: $discontinuationReason");

        $pdo->commit();
        return ['success' => true, 'message' => 'Student discontinuation processed successfully'];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error processing discontinuation: ' . $e->getMessage()];
    }
}

// Handle student graduation
function handle_student_graduation(PDO $pdo, int $userId, int $adminId): array {
    try {
        $pdo->beginTransaction();

        // Update student progression record
        $stmt = $pdo->prepare("
            UPDATE ssaa_student_progression
            SET enrollment_status = 'graduated',
                graduation_date = CURDATE(),
                promotion_eligible = 0,
                updated_at = NOW()
            WHERE user_id = ? AND enrollment_status IN ('active', 'graduating')
        ");
        $stmt->execute([$userId]);

        // Update user record
        $stmt = $pdo->prepare("
            UPDATE users
            SET enrollment_status = 'graduated',
                account_deactivation_date = CURDATE(),
                deactivation_reason = 'Graduated from program'
            WHERE id = ?
        ");
        $stmt->execute([$userId]);

        // Move to alumni table (if exists)
        $stmt = $pdo->prepare("
            INSERT INTO ssaa_alumni (user_id, full_name, email, student_id, course, graduation_year, created_at)
            SELECT u.id, u.full_name, u.email, u.student_id, u.course_year, YEAR(CURDATE()),
                   NOW()
            FROM users u
            WHERE u.id = ? AND NOT EXISTS (SELECT 1 FROM ssaa_alumni WHERE user_id = ?)
        ");
        $stmt->execute([$userId, $userId]);

        // Log the action
        log_audit_action($pdo, $adminId, $userId, 'graduation', 'Student graduated successfully');

        $pdo->commit();
        return ['success' => true, 'message' => 'Student graduation processed successfully'];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Error processing graduation: ' . $e->getMessage()];
    }
}

// Enhanced promotion function with validation
function promote_student_with_validation(PDO $pdo, int $userId, int $adminId): array {
    try {
        // Get current student data
        $stmt = $pdo->prepare("
            SELECT u.course_year, sp.academic_status, sp.enrollment_status, sp.gpa, sp.promotion_eligible
            FROM users u
            LEFT JOIN ssaa_student_progression sp ON u.id = sp.user_id
            WHERE u.id = ? AND u.role = 'student'
            ORDER BY sp.created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $student = $stmt->fetch();

        if (!$student) {
            return ['success' => false, 'message' => 'Student not found'];
        }

        // Validate eligibility
        $currentEnrollmentStatus = $student['enrollment_status'] ?? 'active';
        if ($currentEnrollmentStatus !== 'active' && $currentEnrollmentStatus !== 'graduating') {
            return ['success' => false, 'message' => "Student is {$currentEnrollmentStatus} - cannot promote"];
        }

        if ($student['promotion_eligible'] !== null && !$student['promotion_eligible']) {
            return ['success' => false, 'message' => 'Student is not eligible for promotion'];
        }

        $currentAcademicStatus = $student['academic_status'] ?? 'passed';
        if ($currentAcademicStatus === 'failed' || $currentAcademicStatus === 'retained') {
            return ['success' => false, 'message' => 'Student has failing status - cannot promote'];
        }

        // Calculate new course year
        $promotionResult = advance_course_year($student['course_year'], $currentAcademicStatus, $currentEnrollmentStatus);

        if ($promotionResult['action'] === 'no_change') {
            return ['success' => false, 'message' => $promotionResult['reason']];
        }

        $pdo->beginTransaction();

        // Update course year in users table
        $stmt = $pdo->prepare("UPDATE users SET course_year = ? WHERE id = ?");
        $stmt->execute([$promotionResult['new_course_year'], $userId]);

        // Update or insert progression record
        // Default enrollment status remains 'active' after promotion.
        // We do NOT mark students as 'graduating' during regular promotion
        // to avoid showing the "(Graduating)" label until an admin explicitly
        // processes graduation via the new "Process Graduation" action.
        $enrollmentStatus = 'active';
        $stmt = $pdo->prepare("
            INSERT INTO ssaa_student_progression
            (user_id, academic_year, academic_status, gpa, enrollment_status, evaluated_by, evaluated_at, created_at, updated_at)
            VALUES (?, ?, 'passed', ?, ?, ?, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            academic_year = VALUES(academic_year),
            academic_status = VALUES(academic_status),
            gpa = VALUES(gpa),
            enrollment_status = VALUES(enrollment_status),
            evaluated_by = VALUES(evaluated_by),
            evaluated_at = VALUES(evaluated_at),
            updated_at = NOW()
        ");
        $stmt->execute([$userId, $promotionResult['new_course_year'], $student['gpa'], $enrollmentStatus, $adminId]);

        // Handle graduation if applicable

        // Log the promotion
        log_audit_action($pdo, $adminId, $userId, $promotionResult['action'], $promotionResult['reason']);

        $pdo->commit();
        return ['success' => true, 'message' => $promotionResult['reason'], 'action' => $promotionResult['action']];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Error processing promotion: ' . $e->getMessage()];
    }
}

// Log audit actions
function map_audit_action_type(string $action): string {
    return match ($action) {
        'promote', 'promotion' => 'promotion',
        'graduate', 'graduating' => 'graduation',
        'status_change', 'retain', 'retention', 'irregular', 'transfer', 'discontinuation' => 'status_change',
        default => 'status_change',
    };
}

function log_audit_action(PDO $pdo, int $adminId, int $studentId, string $action, string $description): void {
    $actionType = map_audit_action_type($action);
    $stmt = $pdo->prepare("
        INSERT INTO ssaa_audit_trail
        (admin_id, action_type, entity_type, entity_id, description, created_at)
        VALUES (?, ?, 'student', ?, ?, NOW())
    ");
    $stmt->execute([$adminId, $actionType, $studentId, $description]);
}

function get_course_year_patterns(string $course): array {
    $course = trim($course);
    if ($course === '') {
        return [];
    }

    $patterns = [$course . '%'];
    $aliases = [
        'Bachelor of Science in Accountancy' => ['BSA'],
        'Bachelor of Science in Business Administration' => ['BSBA'],
        'Bachelor in Elementary Education' => ['BEED'],
        'Bachelor of Science in Computer Science' => ['BSCS'],
        'Bachelor of Science in Criminology' => ['BSCR'],
        'Bachelor of Science in Hospitality Management' => ['BSHM'],
        'Bachelor of Science in Tourism Management' => ['BSTM'],
        'ACT' => ['ACT'],
        'ABK' => ['ABK'],
        'AHM' => ['AHM'],
        'ATM' => ['ATM'],
    ];

    if (isset($aliases[$course])) {
        foreach ($aliases[$course] as $alias) {
            $patterns[] = $alias . '%';
        }
    }

    return array_unique($patterns);
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'load_students':
                $course = $_POST['course'] ?? '';
                $year = $_POST['year'] ?? '';

                $query = "
                    SELECT u.id AS user_id, u.full_name, u.email, u.course_year,
                           sp.academic_status, sp.gpa, sp.academic_year
                    FROM users u
                    LEFT JOIN ssaa_student_progression sp ON u.id = sp.user_id
                    WHERE u.role = 'student' AND (sp.enrollment_status = 'active' OR sp.enrollment_status IS NULL)
                    AND (sp.promotion_eligible = 1 OR sp.promotion_eligible IS NULL)
                ";

                $params = [];
                if (!empty($course)) {
                    $patterns = get_course_year_patterns($course);
                    $query .= " AND (" . implode(" OR ", array_fill(0, count($patterns), "u.course_year LIKE ?")) . ")";
                    foreach ($patterns as $pattern) {
                        $params[] = $pattern;
                    }
                }
                if (!empty($year)) {
                    $yearMap = [
                        '1st Year' => '1',
                        '2nd Year' => '2',
                        '3rd Year' => '3',
                        '4th Year' => '4',
                    ];
                    if (isset($yearMap[$year])) {
                        $query .= " AND u.course_year LIKE ?";
                        $params[] = '% ' . $yearMap[$year];
                    }
                }

                $query .= " ORDER BY u.full_name";

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'students' => $students]);
                break;

            case 'bulk_promote':
                $studentIds = json_decode($_POST['student_ids'] ?? '[]', true);
                $adminId = $user['id'];

                if (!is_array($studentIds) || empty($studentIds)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No student IDs were provided for promotion.',
                    ]);
                    break;
                }

                $promoted = 0;
                $graduated = 0;
                $errors = [];

                foreach ($studentIds as $studentId) {
                    $result = promote_student_with_validation($pdo, $studentId, $adminId);

                    if ($result['success']) {
                        if (in_array($result['action'], ['graduate', 'graduating'], true)) {
                            $graduated++;
                        } else {
                            $promoted++;
                        }

                        // Send notification to student
                        require_once __DIR__ . '/../includes/functions.php';
                        if ($result['action'] === 'graduating') {
                            $notificationMessage = 'You are now marked as ready for graduation from your program.';
                        } elseif ($result['action'] === 'graduate') {
                            $notificationMessage = 'Congratulations! You have successfully graduated from your program.';
                        } else {
                            $notificationMessage = 'Congratulations! You have been promoted to the next academic level.';
                        }

                        create_library_notification(
                            $studentId,
                            null,
                            'ssaa_promotion',
                            'Academic ' . ucfirst($result['action']),
                            $notificationMessage
                        );
                    } else {
                        $errors[] = "Student ID $studentId: " . $result['message'];
                    }
                }

                if ($promoted + $graduated === 0) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No students were promoted. Please verify selected students are eligible.',
                        'warnings' => $errors,
                    ]);
                    break;
                }

                $response = [
                    'success' => true,
                    'promoted' => $promoted,
                    'graduated' => $graduated,
                    'message' => 'Promotion processed successfully.',
                ];
                if (!empty($errors)) {
                    $response['warnings'] = $errors;
                }

                echo json_encode($response);
                break;

            case 'process_graduation_bulk':
                $studentIds = json_decode($_POST['student_ids'] ?? '[]', true);
                $adminId = $user['id'];

                if (!is_array($studentIds) || empty($studentIds)) {
                    echo json_encode(['success' => false, 'message' => 'No student IDs provided.']);
                    break;
                }

                $eligibleLadderize = [
                    'associate in computer technology',
                    'associate in business knowledge',
                    'associate in hotel management',
                    'associate in tourism management',
                ];

                $processed = 0;
                $skipped = 0;
                $errors = [];

                foreach ($studentIds as $sid) {
                    $stmt = $pdo->prepare('SELECT id, course_year FROM users WHERE id = ? AND role = "student"');
                    $stmt->execute([$sid]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$student) {
                        $errors[] = "Student ID $sid not found.";
                        continue;
                    }

                    $courseYear = trim(strtolower($student['course_year'] ?? ''));
                    // Eligible if 4th year OR ladderize associate courses at 2nd year
                    $is4th = preg_match('/\b4$|\b4th$/i', $courseYear);
                    $isLadderize2nd = false;
                    foreach ($eligibleLadderize as $lname) {
                        if (stripos($courseYear, $lname) !== false && preg_match('/\b2$|\b2nd$/i', $courseYear)) {
                            $isLadderize2nd = true;
                            break;
                        }
                    }

                    if (!($is4th || $isLadderize2nd)) {
                        $skipped++;
                        continue;
                    }

                    // Update latest progression row to set enrollment_status = 'graduating'
                    $stmt = $pdo->prepare('SELECT id FROM ssaa_student_progression WHERE user_id = ? ORDER BY id DESC LIMIT 1');
                    $stmt->execute([$sid]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $stmt = $pdo->prepare('UPDATE ssaa_student_progression SET enrollment_status = ?, updated_at = NOW() WHERE id = ?');
                        $stmt->execute(['graduating', $row['id']]);
                    } else {
                        // Insert a minimal progression record if none exists
                        $stmt = $pdo->prepare('INSERT INTO ssaa_student_progression (user_id, academic_year, academic_status, enrollment_status, created_at, updated_at) VALUES (?, ?, "passed", ?, NOW(), NOW())');
                        $stmt->execute([$sid, $student['course_year'] ?? '', 'graduating']);
                    }

                    // Log audit
                    log_audit_action($pdo, $adminId, $sid, 'graduating', 'Marked as graduating via bulk Process Graduation');

                    $processed++;
                }

                echo json_encode(['success' => true, 'processed' => $processed, 'skipped' => $skipped, 'errors' => $errors]);
                break;

            case 'search_students':
                $search = $_POST['search'] ?? '';

                $stmt = $pdo->prepare("
                    SELECT u.id, u.full_name, u.email, u.course_year,
                           sp.academic_status, sp.gpa, sp.academic_year
                    FROM users u
                    LEFT JOIN ssaa_student_progression sp ON u.id = sp.user_id
                    WHERE u.role = 'student'
                    AND (sp.enrollment_status = 'active' OR sp.enrollment_status IS NULL)
                    AND (u.full_name LIKE ? OR u.email LIKE ? OR u.id = ?)
                    ORDER BY u.full_name
                    LIMIT 10
                ");
                $searchParam = "%$search%";
                $stmt->execute([$searchParam, $searchParam, $search]);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'students' => $students]);
                break;

            case 'individual_promote':
                $studentId = $_POST['student_id'];
                $action = $_POST['promotion_action'];
                $adminId = $user['id'];

                if ($action === 'promote') {
                    $result = promote_student_with_validation($pdo, $studentId, $adminId);

                    if ($result['success']) {
                        $notificationMessage = in_array($result['action'], ['graduate', 'graduating'], true)
                            ? 'Congratulations! You have successfully graduated from your program.'
                            : 'Congratulations! You have been promoted to the next academic level.';

                        // Send notification to student
                        require_once __DIR__ . '/../includes/functions.php';
                        create_library_notification(
                            $studentId,
                            null,
                            'ssaa_promotion',
                            'Academic ' . ucfirst($result['action']),
                            $notificationMessage
                        );

                        echo json_encode(['success' => true, 'message' => $result['message']]);
                    } else {
                        echo json_encode(['success' => false, 'message' => $result['message']]);
                    }
                } else {
                    // Handle retention
                    $stmt = $pdo->prepare("
                        UPDATE ssaa_student_progression
                        SET academic_status = 'retained', updated_at = NOW()
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$studentId]);

                    // Log to audit trail
                    log_audit_action($pdo, $adminId, $studentId, 'retention', 'Retained in current level');

                    // Send notification to student
                    require_once __DIR__ . '/../includes/functions.php';
                    create_library_notification(
                        $studentId,
                        null,
                        'ssaa_promotion',
                        'Academic Review Result',
                        'After academic review, you will be retained in your current academic level.'
                    );

                    echo json_encode(['success' => true, 'message' => 'Student retained in current level']);
                }
                break;

            case 'process_graduation_individual':
                $studentId = $_POST['student_id'] ?? null;
                $adminId = $user['id'];

                if (!$studentId) {
                    echo json_encode(['success' => false, 'message' => 'Student ID not provided.']);
                    break;
                }

                // Verify student exists and get their info
                $stmt = $pdo->prepare('SELECT id, course_year FROM users WHERE id = ? AND role = "student"');
                $stmt->execute([$studentId]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    echo json_encode(['success' => false, 'message' => 'Student not found.']);
                    break;
                }

                // Check eligibility
                $courseYear = trim(strtolower($student['course_year'] ?? ''));
                $is4th = preg_match('/\b4$|\b4th$/i', $courseYear);
                
                $eligibleLadderize = [
                    'associate in computer technology',
                    'associate in business knowledge',
                    'associate in hotel management',
                    'associate in tourism management',
                ];

                $isLadderize2nd = false;
                foreach ($eligibleLadderize as $lname) {
                    if (stripos($courseYear, $lname) !== false && preg_match('/\b2$|\b2nd$/i', $courseYear)) {
                        $isLadderize2nd = true;
                        break;
                    }
                }

                if (!($is4th || $isLadderize2nd)) {
                    echo json_encode(['success' => false, 'message' => 'Student is not eligible for graduation processing.']);
                    break;
                }

                // Update latest progression row to set enrollment_status = 'graduating'
                $stmt = $pdo->prepare('SELECT id FROM ssaa_student_progression WHERE user_id = ? ORDER BY id DESC LIMIT 1');
                $stmt->execute([$studentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    $stmt = $pdo->prepare('UPDATE ssaa_student_progression SET enrollment_status = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute(['graduating', $row['id']]);
                } else {
                    // Insert a minimal progression record if none exists
                    $stmt = $pdo->prepare('INSERT INTO ssaa_student_progression (user_id, academic_year, academic_status, enrollment_status, created_at, updated_at) VALUES (?, ?, "passed", ?, NOW(), NOW())');
                    $stmt->execute([$studentId, $student['course_year'] ?? '', 'graduating']);
                }

                // Log audit
                log_audit_action($pdo, $adminId, $studentId, 'graduating', 'Marked as graduating via Individual Promotion');

                echo json_encode(['success' => true, 'message' => 'Student marked as graduating successfully.']);
                break;

            case 'update_academic_status':
                $studentId = $_POST['student_id'];
                $status = $_POST['academic_status'];
                $notes = $_POST['notes'] ?? '';
                $adminId = $user['id'];

                // Update academic status
                $stmt = $pdo->prepare("
                    UPDATE ssaa_student_progression
                    SET academic_status = ?, review_notes = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$status, $notes, $studentId]);

                // Log to audit trail
                $stmt = $pdo->prepare("
                    INSERT INTO ssaa_audit_trail (admin_id, action_type, entity_type, entity_id, description, created_at)
                    VALUES (?, 'status_change', 'student', ?, ?, NOW())
                ");
                $stmt->execute([$adminId, $studentId, "Academic status changed to: $status. Notes: $notes"]);

                // Send notification to student
                require_once __DIR__ . '/../includes/functions.php';
                $statusMessage = match($status) {
                    'passed' => 'Your academic status has been updated to PASSED.',
                    'failed' => 'Your academic status has been updated to FAILED.',
                    'irregular' => 'Your academic status has been updated to IRREGULAR.',
                    'retained' => 'Your academic status has been updated to RETAINED.',
                    default => 'Your academic status has been updated.'
                };

                if (!empty($notes)) {
                    $statusMessage .= " Notes: $notes";
                }

                create_library_notification(
                    $studentId,
                    null,
                    'ssaa_academic_review',
                    'Academic Status Update',
                    $statusMessage
                );

                echo json_encode(['success' => true, 'message' => 'Academic status updated successfully']);
                break;

            case 'load_history':
                $course = $_POST['course'] ?? '';
                $year = $_POST['year'] ?? '';
                $actionType = $_POST['action_type'] ?? '';
                $dateRange = $_POST['date_range'] ?? '';

                $query = "
                    SELECT at.*, u.full_name as student_name, u.course_year,
                           admin.full_name as performed_by
                    FROM ssaa_audit_trail at
                    JOIN users u ON at.entity_type = 'student' AND at.entity_id = u.id
                    LEFT JOIN users admin ON at.admin_id = admin.id
                    WHERE 1=1
                ";

                $params = [];
                if (!empty($course)) {
                    $query .= " AND (
                        u.course_year LIKE ? OR
                        u.course LIKE ? OR
                        u.student_id LIKE ?
                    )";
                    $params[] = $course . '%';
                    $params[] = $course . '%';
                    $params[] = $course . '%';
                }
                if (!empty($year)) {
                    $query .= " AND u.course_year LIKE ?";
                    $params[] = "%$year%";
                }
                if (!empty($actionType)) {
                    $query .= " AND at.action_type = ?";
                    $params[] = $actionType;
                }
                if (!empty($dateRange)) {
                    $query .= " AND at.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                    $params[] = $dateRange;
                }

                $query .= " ORDER BY at.created_at DESC LIMIT 100";

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'history' => $history]);
                break;

            case 'transfer_student':
                $studentId = $_POST['student_id'];
                $transferReason = $_POST['transfer_reason'];
                $adminId = $user['id'];

                $result = handle_student_transfer($pdo, $studentId, $transferReason, $adminId);
                echo json_encode($result);
                break;

            case 'discontinue_student':
                $studentId = $_POST['student_id'];
                $discontinuationReason = $_POST['discontinuation_reason'];
                $adminId = $user['id'];

                $result = handle_student_discontinuation($pdo, $studentId, $discontinuationReason, $adminId);
                echo json_encode($result);
                break;

            case 'graduate_student':
                $studentId = $_POST['student_id'];
                $adminId = $user['id'];

                $result = handle_student_graduation($pdo, $studentId, $adminId);
                echo json_encode($result);
                break;

            case 'load_students':
                $course = $_POST['course'] ?? '';
                $year = $_POST['year'] ?? '';

                $query = "
                    SELECT u.id as user_id, u.full_name, u.email, u.course_year,
                           COALESCE(sp.academic_status, 'pending') as academic_status,
                           sp.gpa, sp.enrollment_status, sp.promotion_eligible
                    FROM users u
                    LEFT JOIN ssaa_student_progression sp ON u.id = sp.user_id
                    AND sp.id = (SELECT MAX(id) FROM ssaa_student_progression WHERE user_id = u.id)
                    WHERE u.role = 'student' AND u.enrollment_status = 'active'
                ";

                $params = [];
                if ($course) {
                    $query .= " AND u.course_year LIKE ?";
                    $params[] = "%$course%";
                }
                if ($year) {
                    $query .= " AND u.course_year LIKE ?";
                    $params[] = "%$year%";
                }

                $query .= " ORDER BY u.full_name";

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'students' => $students]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle export requests
if (isset($_GET['action']) && $_GET['action'] === 'export_history') {
    $course = $_GET['course'] ?? '';
    $year = $_GET['year'] ?? '';
    $actionType = $_GET['action_type'] ?? '';
    $dateRange = $_GET['date_range'] ?? '';

    $query = "
        SELECT at.*, u.full_name as student_name, u.course_year,
               admin.full_name as performed_by
        FROM ssaa_audit_trail at
        JOIN users u ON at.entity_type = 'student' AND at.entity_id = u.id
        LEFT JOIN users admin ON at.admin_id = admin.id
        WHERE 1=1
    ";

    $params = [];
    if (!empty($course)) {
        $query .= " AND u.course_year LIKE ?";
        $params[] = $course . '%';
    }
    if (!empty($year)) {
        $query .= " AND u.course_year LIKE ?";
        $params[] = "%$year%";
    }
    if (!empty($actionType)) {
        $query .= " AND at.action_type = ?";
        $params[] = $actionType;
    }
    if (!empty($dateRange)) {
        $query .= " AND at.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $dateRange;
    }

    $query .= " ORDER BY at.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="promotion_history_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Student Name', 'Course Year', 'Action Type', 'Description', 'Performed By']);

    foreach ($history as $item) {
        fputcsv($output, [
            $item['created_at'],
            $item['student_name'],
            $item['course_year'],
            $item['action_type'],
            $item['description'],
            $item['performed_by']
        ]);
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Promotion | SSAA Management | PASS Support System</title>
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
        .history-filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .promotion-history-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
        }

        /* Responsive adjustments for Promotion History */
        @media (max-width: 720px) {
            .history-filters {
                flex-direction: column;
                gap: 12px;
            }

            .filter-group {
                min-width: 0;
                width: 100%;
            }

            .promotion-history-list {
                grid-template-columns: 1fr;
                max-height: 320px;
                padding: 8px;
                gap: 12px;
            }

            .history-item {
                padding: 12px;
            }

            .history-header { gap: 8px; }
        }

        @media (max-width: 425px) {
            .promotion-history-list {
                max-height: 260px;
                padding: 8px 6px;
            }

            .history-item {
                padding: 10px;
                font-size: 14px;
            }

            .history-header { flex-direction: column; align-items: flex-start; gap: 6px; }

            .filter-group select {
                font-size: 14px;
                padding: 8px 10px;
            }
        }

        .history-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 0;
        }

        .history-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .student-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .action-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .action-type.promotion {
            background: #d4edda;
            color: #155724;
        }

        .action-type.graduation {
            background: #f3e6e6;
            color: #800000;
        }

        .action-type.status_change {
            background: #fff3cd;
            color: #856404;
        }

        .timestamp {
            color: #6c757d;
            font-size: 12px;
        }

        .history-details p {
            margin: 5px 0;
            color: #495057;
        }

        .no-data, .error {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        .error {
            color: #dc3545;
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

        .promotion-actions {
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

        .tabbed-actions {
            margin: 24px 0;
        }

        .tab-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .tab-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            background: #fff;
            border: 1px solid #dfe3e8;
            border-radius: 999px;
            color: #34495e;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 180px;
            text-align: center;
        }

        .tab-button.active {
            background: linear-gradient(135deg, #800000, #a84a38);
            color: white;
            border-color: #800000;
            box-shadow: 0 2px 8px rgba(120, 40, 40, 0.18);
        }

        .tab-panel {
            display: none;
            gap: 20px;
        }

        .tab-panel.active {
            display: block;
        }

        .tab-panel.single-card {
            display: none;
        }

        .tab-panel.single-card.active {
            display: block;
        }

        .sub-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .sub-tab {
            padding: 10px 18px;
            border: 1px solid #dfe3e8;
            border-radius: 999px;
            background: #ffffff;
            color: #34495e;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 160px;
            text-align: center;
        }

        .sub-tab:hover {
            background: #f3f4f6;
            color: #800000;
        }

        .sub-tab.active {
            background: linear-gradient(135deg, #800000, #a84a38);
            color: #ffffff;
            border-color: #800000;
            box-shadow: 0 2px 8px rgba(120, 40, 40, 0.18);
        }

        .subtab-panel {
            display: none;
        }

        .subtab-panel.active {
            display: block;
        }

        .subtab-panels {
            margin-top: 20px;
        }

        /* Mobile: make tab headers horizontally scrollable and swipe-friendly */
        @media (max-width: 720px) {
            .tab-buttons, .sub-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                gap: 12px;
                padding-bottom: 6px;
            }

            .tab-button, .sub-tab {
                min-width: 140px;
                flex: 0 0 auto;
            }
        }

        /* Improve main content and Bulk Promotion layout on small screens (720px and below) */
        @media (max-width: 720px) {
            /* Make main panel and selected list full width and reduce padding */
            .tab-panel .panel-card, .tab-panel .selected-list {
                width: 100%;
                box-sizing: border-box;
                padding: 14px;
                margin-bottom: 12px;
                border-radius: 10px;
            }

            /* Reduce overall panel padding to avoid horizontal overflow */
            .panel-card {
                padding: 14px;
            }

            /* Make promotion filters stack neatly and inputs full width */
            .promotion-filters {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .promotion-filters .filter-group {
                width: 100%;
            }

            .promotion-filters select {
                width: 100%;
            }

            /* Reduce the promotion list height so it fits comfortably */
            .promotion-list {
                max-height: 240px;
                padding: 8px;
            }

            /* Student item spacing and responsive wrapping */
            .student-item {
                gap: 8px;
                padding: 8px;
            }

            .student-info {
                min-width: 0;
            }

            /* Selected list items stack and allow long text to wrap */
            .selected-item {
                flex-direction: row;
                align-items: center;
                gap: 8px;
                padding: 10px;
            }

            .selected-item .selected-meta {
                overflow-wrap: anywhere;
            }

            /* Make modal actions stack vertically to avoid overflow */
            .modal-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .modal-actions .primary-button, .modal-actions .secondary-button {
                width: 100%;
            }
        }

        /* Extra small screens: 425px -> 320px adjustments to avoid cramped UI */
        @media (max-width: 425px) {
            .tab-buttons, .sub-tabs {
                gap: 8px;
                padding: 6px 8px;
            }

            .tab-button, .sub-tab {
                min-width: 100px;
                padding: 8px 12px;
                font-size: 14px;
            }

            .panel-card, .tab-panel .selected-list {
                padding: 10px;
                margin-bottom: 10px;
            }

            .panel-grid {
                gap: 12px;
            }

            .panel-header p {
                font-size: 13px;
                margin-bottom: 8px;
                line-height: 1.35;
            }

            .promotion-list {
                max-height: 200px;
                padding: 8px;
            }

            .student-item {
                padding: 8px;
                gap: 8px;
            }

            .student-name { font-size: 14px; }

            .selected-item {
                padding: 8px;
                gap: 8px;
            }

            .modal-actions {
                flex-direction: column-reverse;
                gap: 8px;
            }

            /* Ensure selects and inputs take available width without causing overflow */
            .promotion-filters select, .search-section input {
                width: 100%;
                box-sizing: border-box;
                font-size: 14px;
                padding: 8px 10px;
            }

            /* Slightly smaller scrollbars to save horizontal space */
            .tab-buttons::-webkit-scrollbar, .sub-tabs::-webkit-scrollbar {
                height: 6px;
            }
        }

        .selected-list {
            margin-top: 20px;
            background: #f4f8fb;
            border: 1px solid #dae4ed;
            border-radius: 12px;
            padding: 18px;
        }

        .selected-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            gap: 12px;
        }

        .selected-header h4 {
            margin: 0;
            color: #2c3e50;
        }

        .selected-header span {
            color: #55616f;
            font-size: 0.95rem;
        }

        #selected-students {
            display: grid;
            gap: 10px;
        }

        .selected-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid #dfe3e8;
        }

        .selected-item .selected-meta {
            display: grid;
            gap: 4px;
        }

        .selected-item .selected-meta strong {
            display: block;
            font-weight: 600;
            color: #2c3e50;
        }

        .remove-button {
            border: none;
            background: transparent;
            color: #e74c3c;
            cursor: pointer;
            font-weight: 700;
            font-size: 1rem;
        }

        .selected-list .no-data {
            margin: 0;
            padding: 16px 0;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }

        .panel-card {
            background: white;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 18px 40px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .panel-card.single-card {
            grid-column: span 2;
        }

        .panel-header h3 {
            margin: 0 0 8px;
            color: #2c3e50;
        }

        .panel-header p {
            margin: 0;
            color: #6c757d;
            line-height: 1.6;
        }

        .hidden-content {
            display: none;
        }

        .panel-card textarea {
            width: 100%;
            resize: vertical;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 14px;
            min-height: 120px;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .search-section label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #34495e;
        }

        .search-section input {
            width: 100%;
            box-sizing: border-box;
        }

        .student-info-card {
            min-height: 180px;
        }

        @media (max-width: 950px) {
            .panel-grid {
                grid-template-columns: 1fr;
            }
        }

        .promotion-filters, .search-section {
            margin-bottom: 20px;
        }

        .filter-group, .search-section {
            display: flex;
            flex-direction: column;
        }

        .filter-group label, .search-section label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }

        .filter-group select, .search-section input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .promotion-list, .search-results {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
        }

        .student-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 8px;
            background: #f8f9fa;
        }

        .student-item:hover {
            background: #e9ecef;
        }

        .student-checkbox {
            margin-right: 12px;
        }

        .student-info {
            flex: 1;
        }

        .student-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .student-details {
            color: #6c757d;
            font-size: 12px;
            margin-top: 2px;
        }

        .search-result-item {
            padding: 10px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            margin-bottom: 5px;
            cursor: pointer;
            background: #f8f9fa;
        }

        .search-result-item:hover {
            background: #e9ecef;
        }

        .no-results, .no-data {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }

        .student-info-card {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .student-info-card h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .student-info-card p {
            margin: 5px 0;
            color: #495057;
        }

        .promotion-options h4 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .radio-group label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .radio-group input[type="radio"] {
            margin-right: 8px;
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

        @media (max-width: 768px) {
            .case-page-header {
                min-height: 245px;
                padding: 32px 24px 110px;
            }

            .case-page-header > div:first-child {
                width: 100%;
            }

            .case-page-header h1 {
                font-size: 36px;
            }

            .case-header__art {
                width: 55%;
                opacity: .35;
            }
        }
    </style>
    <style>
        /* Temporarily neutralize global wrapper on very small screens to diagnose layout issues */
        @media (max-width: 425px) {
            .page-shell, .page-content, .main-scroll, .content-panel {
                width: 100% !important;
                min-width: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }

            /* Hide the side nav to free horizontal space on tiny screens (mobile behavior) */
            .side-nav {
                transform: translateX(-110%) !important;
                position: fixed !important;
                left: 0; top: 0; height: 100%;
                z-index: 1200;
                transition: transform 200ms ease-in-out;
            }

            /* When mobile-open class is toggled by JS, show the sidebar */
            .side-nav.mobile-open {
                transform: translateX(0) !important;
            }

            /* Page overlay to block background and allow click-to-close */
            .page-overlay { display: none; }
            .page-overlay.active {
                display: block;
                position: fixed;
                left: 0; top: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.35);
                z-index: 1100;
            }

            /* Reduce topbar padding and logo size */
            .topbar { padding: 8px 8px !important; }
            .nav-brand img { max-width: 28px !important; height: auto !important; }

            /* Ensure tab headers get full available width */
            .content-panel { overflow-x: hidden !important; }
            .tab-buttons, .sub-tabs { padding-left: 6px; padding-right: 6px; }
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
                        <a href="student_promotion.php" class="active" data-tooltip="Student Promotion">
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
                <div class="content-panel">
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <p class="eyebrow">SSAA Management - Student Promotion</p>
                            <h1>Student Promotion Management</h1>
                            <p class="dashboard-subtitle">Manage student academic progression, bulk promotions, and track student advancement through academic years.</p>
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



                    <!-- Promotion Actions -->
                    <div class="tabbed-actions">
                        <div class="tab-buttons" role="tablist">
                            <button type="button" class="tab-button active" data-tab="promotion-tab" role="tab" aria-selected="true">Promotion Actions</button>
                            <button type="button" class="tab-button" data-tab="promotion-history-tab" role="tab" aria-selected="false">Promotion History</button>
                        </div>

                        <div class="tab-panels">
                            <div class="tab-panel active" id="promotion-tab" role="tabpanel">
                                <div class="sub-tabs">
                                    <button type="button" class="sub-tab active" data-subtab="bulk-promotion">Bulk Promotion</button>
                                    <button type="button" class="sub-tab" data-subtab="individual-promotion">Individual Promotion</button>
                                </div>

                                <div class="subtab-panels">
                                    <div class="subtab-panel active" id="bulk-promotion">
                                        <div class="panel-card">
                                            <div class="panel-header">
                                                <h3>Bulk Promotion</h3>
                                                <p>Filter students by course and year level, then select the learners to advance.</p>
                                            </div>
                                            <div class="promotion-filters live-filters">
                                                <div class="filter-group">
                                                    <label for="promotion-course">Ladderized Course/Program</label>
                                                    <select id="promotion-course" onchange="onPromotionCourseChange()">
                                                        <option value="">All Courses</option>
                                                        <?php foreach ($courseOptions as $course): ?>
                                                            <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="filter-group">
                                                    <label for="promotion-year">Year Level</label>
                                                    <select id="promotion-year" onchange="filterPromotionStudents()">
                                                        <option value="">All Years</option>
                                                        <option value="1st Year">1st Year</option>
                                                        <option value="2nd Year">2nd Year</option>
                                                        <option value="3rd Year">3rd Year</option>
                                                        <option value="4th Year">4th Year</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="promotion-list" id="promotion-list">
                                                <p class="no-data">Choose course or year to view students for bulk promotion.</p>
                                            </div>
                                            <div class="modal-actions">
                                                <button type="button" class="secondary-button" onclick="resetBulkPromotion()">Reset</button>
                                                <button type="button" class="primary-button" onclick="executeBulkPromotion()" id="promote-btn" disabled>Promote Selected Students</button>
                                                <button type="button" class="primary-button" onclick="executeProcessGraduation()" id="process-graduation-btn" disabled style="margin-left:8px;">Process Graduation</button>
                                            </div>
                                        </div>
                                        <div class="selected-list" id="selected-list">
                                            <div class="selected-header">
                                                <h4>Selected for Promotion</h4>
                                                <span id="selected-count">0 selected</span>
                                            </div>
                                            <div id="selected-students"></div>
                                        </div>
                                        </div>
                                    </div>

                                    <div class="subtab-panel" id="individual-promotion">
                                        <div class="panel-card">
                                            <div class="panel-header">
                                                <h3>Individual Promotion</h3>
                                                <p>Search a student, review current level, and promote or retain directly in one place.</p>
                                            </div>
                                            <div class="search-section">
                                                <label for="student-search">Search by name, email, or ID</label>
                                                <input type="text" id="student-search" placeholder="Type at least 2 characters..." onkeyup="searchStudents()">
                                                <div id="student-search-results" class="search-results"></div>
                                            </div>

                                            <div id="individual-promotion-content" class="hidden-content">
                                                <div class="student-info-card">
                                                    <h4 id="student-name"></h4>
                                                    <p><strong>Current Level:</strong> <span id="current-level"></span></p>
                                                    <p><strong>Next Level:</strong> <span id="next-level"></span></p>
                                                    <p><strong>Academic Status:</strong> <span id="academic-status"></span></p>
                                                    <p><strong>GPA:</strong> <span id="student-gpa"></span></p>
                                                </div>

                                                <div class="promotion-options">
                                                    <h4>Promotion Action</h4>
                                                    <div class="radio-group">
                                                        <label>
                                                            <input type="radio" name="promotion-action" value="promote" checked>
                                                            Promote to next level
                                                        </label>
                                                        <label>
                                                            <input type="radio" name="promotion-action" value="retain">
                                                            Retain in current level
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="modal-actions">
                                                    <button type="button" class="secondary-button" onclick="resetIndividualPromotion()">Reset</button>
                                                    <button type="button" class="primary-button" onclick="executeIndividualPromotion()" id="individual-promote-btn" disabled>Execute Promotion</button>
                                                    <button type="button" class="primary-button" onclick="executeIndividualGraduation()" id="individual-graduation-btn" disabled style="margin-left:8px; background: linear-gradient(135deg, #800000 0%, #a84a38 100%);">Process Graduation</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-panel" id="promotion-history-tab" role="tabpanel">
                                <div class="panel-card single-card">
                                    <div class="panel-header">
                                        <h3>Promotion History</h3>
                                        <p>Filter audit trail records and export history for reporting.</p>
                                    </div>
                                    <div class="history-filters">
                                        <div class="filter-group">
                                            <label for="history-course">Ladderized Course/Program:</label>
                                            <select id="history-course" onchange="loadPromotionHistory()">
                                                <option value="">All Courses</option>
                                                <?php foreach ($courseOptions as $courseOption): ?>
                                                    <option value="<?= htmlspecialchars($courseOption) ?>"><?= htmlspecialchars($courseOption) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="filter-group">
                                            <label for="history-action">Action:</label>
                                            <select id="history-action" onchange="loadPromotionHistory()">
                                                <option value="">All Actions</option>
                                                <option value="promote">Promotion</option>
                                                <option value="graduate">Graduation</option>
                                                <option value="status_change">Status Change</option>
                                            </select>
                                        </div>
                                        <div class="filter-group">
                                            <label for="history-year">Year:</label>
                                            <select id="history-year" onchange="loadPromotionHistory()">
                                                <option value="">All Years</option>
                                                <option value="1st Year">1st Year</option>
                                                <option value="2nd Year">2nd Year</option>
                                                <option value="3rd Year">3rd Year</option>
                                                <option value="4th Year">4th Year</option>
                                                <option value="Graduate">Graduate</option>
                                            </select>
                                        </div>
                                        <div class="filter-group">
                                            <label for="history-date">Date Range:</label>
                                            <select id="history-date" onchange="loadPromotionHistory()">
                                                <option value="7">Last 7 days</option>
                                                <option value="30">Last 30 days</option>
                                                <option value="90">Last 3 months</option>
                                                <option value="">All time</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="promotion-history-list" id="promotion-history-list">
                                        <p class="no-data">Select filters or switch to this tab to view history.</p>
                                    </div>
                                    <div class="modal-actions">
                                        <button type="button" class="secondary-button" onclick="loadPromotionHistory()">Refresh</button>
                                        <button type="button" class="primary-button" onclick="exportHistory()">Export History</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
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
        const selectedPromotionStudents = {};
    (function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();
        const twoYearCourses = ['ACT', 'ABK', 'AHM', 'ATM'];

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setPromotionYearOptions(course, selectedYear = '') {
            const yearSelect = document.getElementById('promotion-year');
            if (!yearSelect) return;

            const isTwoYear = course && twoYearCourses.some(code => course.includes(code));
            const yearOptions = isTwoYear ? ['1st Year', '2nd Year'] : ['1st Year', '2nd Year', '3rd Year', '4th Year'];
            yearSelect.innerHTML = '<option value="">All Years</option>' + yearOptions.map(year => `
                <option value="${year}"${year === selectedYear ? ' selected' : ''}>${year}</option>`
            ).join('');
        }

        function onPromotionCourseChange() {
            const course = document.getElementById('promotion-course').value;
            setPromotionYearOptions(course);
            filterPromotionStudents();
        }

        function togglePromotionSelection(checkbox) {
            const studentId = checkbox.value;
            const studentData = {
                id: checkbox.value,
                name: checkbox.dataset.name,
                courseYear: checkbox.dataset.courseYear,
                gpa: checkbox.dataset.gpa,
                status: checkbox.dataset.status
            };

            if (checkbox.checked) {
                selectedPromotionStudents[studentId] = studentData;
            } else {
                delete selectedPromotionStudents[studentId];
            }

            updateSelectedList();
        }

        function removePromotionSelection(studentId) {
            delete selectedPromotionStudents[studentId];
            const checkbox = document.querySelector(`.student-checkbox[value="${studentId}"]`);
            if (checkbox) {
                checkbox.checked = false;
            }
            updateSelectedList();
        }

        function updateSelectedList() {
            const selectedContainer = document.getElementById('selected-students');
            const selectedCount = Object.keys(selectedPromotionStudents).length;
            document.getElementById('selected-count').textContent = `${selectedCount} selected`;
            const promoteBtn = document.getElementById('promote-btn');
            const processBtn = document.getElementById('process-graduation-btn');
            
            // Check if selected students are graduation-eligible
            const eligibleLadderize = [
                'associate in computer technology',
                'associate in business knowledge',
                'associate in hotel management',
                'associate in tourism management',
            ];
            
            let graduationEligibleCount = 0;
            let nonGraduationEligibleCount = 0;
            
            Object.values(selectedPromotionStudents).forEach(student => {
                const courseYear = student.courseYear.toLowerCase();
                
                // Check if 4th year
                const is4th = /\b4(?:th)?\b/i.test(courseYear);
                
                // Check if ladderize 2nd year
                let isLadderize2nd = false;
                for (const lname of eligibleLadderize) {
                    if (courseYear.includes(lname) && /\b2(?:nd)?\b/i.test(courseYear)) {
                        isLadderize2nd = true;
                        break;
                    }
                }
                
                if (is4th || isLadderize2nd) {
                    graduationEligibleCount++;
                } else {
                    nonGraduationEligibleCount++;
                }
            });
            
            // Enable/disable buttons based on selection
            if (selectedCount === 0) {
                promoteBtn.disabled = true;
                promoteBtn.textContent = 'Promote Selected Students';
                if (processBtn) {
                    processBtn.disabled = true;
                    processBtn.textContent = 'Process Graduation';
                }
            } else if (graduationEligibleCount > 0 && nonGraduationEligibleCount === 0) {
                // All selected students are graduation-eligible
                promoteBtn.disabled = true;
                promoteBtn.textContent = 'Promote Selected Students';
                if (processBtn) {
                    processBtn.disabled = false;
                    processBtn.textContent = `Process Graduation (${selectedCount})`;
                }
            } else if (nonGraduationEligibleCount > 0 && graduationEligibleCount === 0) {
                // All selected students are NOT graduation-eligible
                promoteBtn.disabled = false;
                promoteBtn.textContent = `Promote Selected Students (${selectedCount})`;
                if (processBtn) {
                    processBtn.disabled = true;
                    processBtn.textContent = 'Process Graduation';
                }
            } else {
                // Mixed selection - disable both
                promoteBtn.disabled = true;
                promoteBtn.textContent = 'Promote Selected Students';
                if (processBtn) {
                    processBtn.disabled = true;
                    processBtn.textContent = 'Process Graduation';
                }
            }

            if (selectedCount === 0) {
                selectedContainer.innerHTML = '<p class="no-data">No students selected.</p>';
                return;
            }

            selectedContainer.innerHTML = Object.values(selectedPromotionStudents).map(student => `
                <div class="selected-item">
                    <div class="selected-meta">
                        <strong>${escapeHtml(student.name)}</strong>
                        <span>${escapeHtml(student.courseYear)} · GPA ${escapeHtml(student.gpa)}</span>
                    </div>
                    <button type="button" class="remove-button" onclick="removePromotionSelection('${student.id}')">×</button>
                </div>
            `).join('');
        }

        function resetBulkPromotion() {
            Object.keys(selectedPromotionStudents).forEach(id => delete selectedPromotionStudents[id]);
            document.getElementById('promotion-course').value = '';
            setPromotionYearOptions('');
            filterPromotionStudents();
            updateSelectedList();
        }

        function filterPromotionStudents() {
            const course = document.getElementById('promotion-course').value;
            const year = document.getElementById('promotion-year').value;
            const params = new URLSearchParams({ action: 'load_students', course, year });

            fetch('student_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                const promotionList = document.getElementById('promotion-list');
                if (data.success && data.students.length > 0) {
                    promotionList.innerHTML = data.students.map(student => {
                        const safeName = escapeHtml(student.full_name);
                        const safeCourseYear = escapeHtml(student.course_year || 'Unknown course');
                        const safeGpa = escapeHtml(student.gpa || 'N/A');
                        const safeStatus = escapeHtml(student.academic_status || 'Unknown');
                        const isChecked = selectedPromotionStudents[student.user_id] ? 'checked' : '';

                        return `
                            <div class="student-item">
                                <label class="student-checkbox-wrapper">
                                    <input type="checkbox" class="student-checkbox" value="${student.user_id}"
                                        data-name="${safeName}"
                                        data-course-year="${safeCourseYear}"
                                        data-gpa="${safeGpa}"
                                        data-status="${safeStatus}"
                                        onchange="togglePromotionSelection(this)" ${isChecked}>
                                    <span class="custom-checkbox"></span>
                                </label>
                                <div class="student-info">
                                    <div class="student-name">${safeName}</div>
                                    <div class="student-details">${safeCourseYear} · ${safeStatus} · GPA ${safeGpa}</div>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    promotionList.innerHTML = '<p class="no-data">No students found.</p>';
                }

                updateSelectedList();
            })
            .catch(error => {
                console.error('Error loading students:', error);
                document.getElementById('promotion-list').innerHTML = '<p class="error">Error loading students.</p>';
            });
        }

        function updatePromoteButton() {
            updateSelectedList();
        }

        function executeBulkPromotion() {
            const selectedStudents = Object.keys(selectedPromotionStudents);

            if (selectedStudents.length === 0) {
                showNotification('Please select at least one student to promote.', 'error');
                return;
            }

            if (!confirm(`Are you sure you want to promote ${selectedStudents.length} student(s)?`)) {
                return;
            }

            const bulkParams = new URLSearchParams();
            bulkParams.append('action', 'bulk_promote');
            bulkParams.append('student_ids', JSON.stringify(selectedStudents));

            fetch('student_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: bulkParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const promoted = data.promoted || 0;
                    const graduated = data.graduated || 0;
                    let message = `Successfully processed ${promoted + graduated} student(s)!`;
                    if (promoted > 0 && graduated > 0) {
                        message = `Successfully promoted ${promoted} student(s) and graduated ${graduated} student(s)!`;
                    } else if (graduated > 0) {
                        message = `Successfully graduated ${graduated} student(s)!`;
                    } else {
                        message = `Successfully promoted ${promoted} student(s)!`;
                    }

                    if (data.warnings && data.warnings.length > 0) {
                        message += ' Some students could not be processed: ' + data.warnings.join(' ');
                    }

                    showNotification(message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    let errorMessage = data.message || 'Error promoting students.';
                    if (data.warnings && data.warnings.length > 0) {
                        errorMessage += ' ' + data.warnings.join(' ');
                    }
                    showNotification('Error promoting students: ' + errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error promoting students.', 'error');
            });
        }

            function executeProcessGraduation() {
                const selectedStudents = Object.keys(selectedPromotionStudents);

                if (selectedStudents.length === 0) {
                    showNotification('Please select at least one student to process graduation.', 'error');
                    return;
                }

                if (!confirm(`Are you sure you want to mark ${selectedStudents.length} student(s) as 'Graduating'? This will show the '(Graduating)' label on their profiles.`)) {
                    return;
                }

                const bulkParams = new URLSearchParams();
                bulkParams.append('action', 'process_graduation_bulk');
                bulkParams.append('student_ids', JSON.stringify(selectedStudents));

                fetch('student_promotion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: bulkParams.toString()
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const processed = data.processed || 0;
                        const skipped = data.skipped || 0;
                        let message = `Processed graduation for ${processed} student(s).`;
                        if (skipped > 0) message += ` Skipped ${skipped} ineligible student(s).`;
                        if (data.errors && data.errors.length) message += ' Some errors: ' + data.errors.join(' ');
                        showNotification(message, 'success');
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showNotification('Error processing graduation: ' + (data.message || 'Unknown error'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error processing graduation.', 'error');
                });
            }

        function searchStudents() {
            const searchTerm = document.getElementById('student-search').value.trim();

            if (searchTerm.length < 2) {
                document.getElementById('student-search-results').innerHTML = '';
                return;
            }

            const searchParams = new URLSearchParams();
            searchParams.append('action', 'search_students');
            searchParams.append('search', searchTerm);

            fetch('student_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: searchParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('student-search-results');
                if (data.success && data.students.length > 0) {
                    resultsDiv.innerHTML = data.students.map(student => `
                        <div class="search-result-item" onclick="selectStudent(${student.id}, '${student.full_name}', '${student.course_year}', '${student.academic_status}', '${student.gpa}')">
                            <div class="student-name">${student.full_name}</div>
                            <div class="student-details">${student.course_year}</div>
                        </div>
                    `).join('');
                } else {
                    resultsDiv.innerHTML = '<div class="no-results">No students found</div>';
                }
            })
            .catch(error => {
                console.error('Error searching students:', error);
            });
        }

        function formatPromotionTarget(courseYear) {
            const twoYearPrograms = ['ACT', 'ABK', 'AHM', 'ATM'];
            const normalized = courseYear.trim();
            const match = normalized.match(/^(.*\S)\s+(\d+)$/);
            if (match) {
                const program = match[1].toUpperCase();
                const year = Number(match[2]);
                const ordinals = {1: '1st Year', 2: '2nd Year', 3: '3rd Year', 4: '4th Year'};
                if (year >= 1 && year < 4) {
                    return ordinals[year + 1] || `${year + 1}th Year`;
                }
                if (year === 4) {
                    return '4th Year (Graduating)';
                }
                if (year === 2 && twoYearPrograms.some(code => program.includes(code))) {
                    return 'Graduate';
                }
                return `${year + 1}th Year`;
            }
            if (/graduate/i.test(normalized)) {
                return 'Graduating';
            }
            return 'Not applicable';
        }

        function selectStudent(id, name, courseYear, status, gpa) {
            document.getElementById('student-name').textContent = name;
            document.getElementById('current-level').textContent = courseYear;
            const nextLevel = formatPromotionTarget(courseYear);
            document.getElementById('next-level').textContent = nextLevel;
            document.getElementById('academic-status').textContent = status || 'Not Set';
            document.getElementById('student-gpa').textContent = gpa || 'N/A';

            document.getElementById('individual-promotion-content').style.display = 'block';
            
            // Disable promote button if student is at max year (ladderized 2nd year or regular 4th year)
            const canPromote = nextLevel !== 'Graduate' && nextLevel !== '4th Year (Graduating)';
            document.getElementById('individual-promote-btn').disabled = !canPromote;
            document.getElementById('individual-promote-btn').dataset.studentId = id;
            document.getElementById('individual-promote-btn').dataset.courseYear = courseYear;

            // Check if student is graduation-eligible
            const eligibleLadderize = [
                'associate in computer technology',
                'associate in business knowledge',
                'associate in hotel management',
                'associate in tourism management',
            ];
            
            const courseYearLower = courseYear.toLowerCase();
            const is4th = /\b4(?:th)?\b/i.test(courseYearLower);
            let isLadderize2nd = false;
            for (const lname of eligibleLadderize) {
                if (courseYearLower.includes(lname) && /\b2(?:nd)?\b/i.test(courseYearLower)) {
                    isLadderize2nd = true;
                    break;
                }
            }
            
            const graduationBtn = document.getElementById('individual-graduation-btn');
            if (graduationBtn) {
                graduationBtn.disabled = !(is4th || isLadderize2nd);
                graduationBtn.dataset.studentId = id;
                graduationBtn.dataset.courseYear = courseYear;
            }

            document.getElementById('student-search-results').innerHTML = '';
            document.getElementById('student-search').value = name;
        }

        function resetIndividualPromotion() {
            document.getElementById('student-search').value = '';
            document.getElementById('student-search-results').innerHTML = '';
            document.getElementById('individual-promotion-content').style.display = 'none';
            document.getElementById('individual-promote-btn').disabled = true;
            document.getElementById('individual-promote-btn').dataset.studentId = '';
            const graduationBtn = document.getElementById('individual-graduation-btn');
            if (graduationBtn) {
                graduationBtn.disabled = true;
                graduationBtn.dataset.studentId = '';
            }
        }

        function executeIndividualPromotion() {
            const studentId = document.getElementById('individual-promote-btn').dataset.studentId;
            const action = document.querySelector('input[name="promotion-action"]:checked').value;

            if (!studentId) {
                showNotification('Please select a student first.', 'error');
                return;
            }

            const individualParams = new URLSearchParams();
            individualParams.append('action', 'individual_promote');
            individualParams.append('student_id', studentId);
            individualParams.append('promotion_action', action);

            fetch('student_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: individualParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error processing promotion.', 'error');
            });
        }

        function executeIndividualGraduation() {
            const studentId = document.getElementById('individual-graduation-btn').dataset.studentId;

            if (!studentId) {
                showNotification('Please select a student first.', 'error');
                return;
            }

            if (!confirm('Are you sure you want to mark this student as "Graduating"? This will show the "(Graduating)" label on their profile.')) {
                return;
            }

            const graduationParams = new URLSearchParams();
            graduationParams.append('action', 'process_graduation_individual');
            graduationParams.append('student_id', studentId);

            fetch('student_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: graduationParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Student marked as graduating.', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showNotification('Error: ' + (data.message || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error processing graduation.', 'error');
            });
        }

        function searchAcademicStudents() {
            const searchTerm = document.getElementById('academic-student-search').value.trim();

            if (searchTerm.length < 2) {
                document.getElementById('academic-search-results').innerHTML = '';
                return;
            }

            const academicSearchParams = new URLSearchParams();
            academicSearchParams.append('action', 'search_students');
            academicSearchParams.append('search', searchTerm);

            fetch('student_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: academicSearchParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('academic-search-results');
                if (data.success && data.students.length > 0) {
                    resultsDiv.innerHTML = data.students.map(student => `
                        <div class="search-result-item" onclick="selectAcademicStudent(${student.id}, '${student.full_name}', '${student.course_year}', '${student.academic_status}', '${student.gpa}', '${student.academic_year}', '${student.review_notes || ''}')">
                            <div class="student-name">${student.full_name}</div>
                            <div class="student-details">${student.course_year} | Status: ${student.academic_status}</div>
                        </div>
                    `).join('');
                } else {
                    resultsDiv.innerHTML = '<div class="no-results">No students found</div>';
                }
            })
            .catch(error => {
                console.error('Error searching students:', error);
            });
        }

        function selectAcademicStudent(id, name, courseYear, status, gpa, academicYear, notes) {
            document.getElementById('academic-student-name').textContent = name;
            document.getElementById('academic-current-level').textContent = courseYear;
            document.getElementById('academic-current-status').textContent = status || 'Not Set';
            document.getElementById('academic-student-gpa').textContent = gpa || 'N/A';
            document.getElementById('academic-year').textContent = academicYear || 'N/A';
            document.getElementById('academic-review-notes').value = notes;

            // Set the current status in the radio buttons
            const statusRadios = document.querySelectorAll('input[name="academic-status"]');
            statusRadios.forEach(radio => {
                radio.checked = radio.value === status;
            });

            document.getElementById('academic-review-content').style.display = 'block';
            document.getElementById('academic-update-btn').disabled = false;
            document.getElementById('academic-update-btn').dataset.studentId = id;

            document.getElementById('academic-search-results').innerHTML = '';
            document.getElementById('academic-student-search').value = name;
        }

        function resetAcademicReview() {
            document.getElementById('academic-student-search').value = '';
            document.getElementById('academic-search-results').innerHTML = '';
            document.getElementById('academic-review-content').style.display = 'none';
            document.getElementById('academic-update-btn').disabled = true;
            document.getElementById('academic-update-btn').dataset.studentId = '';
        }

        function updateAcademicStatus() {
            const studentId = document.getElementById('academic-update-btn').dataset.studentId;
            const status = document.querySelector('input[name="academic-status"]:checked').value;
            const notes = document.getElementById('academic-review-notes').value.trim();

            if (!studentId) {
                showNotification('Please select a student first.', 'error');
                return;
            }

            fetch('student_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_academic_status&student_id=${studentId}&academic_status=${status}&notes=${encodeURIComponent(notes)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error updating academic status.', 'error');
            });
        }

        function initPromotionTabs() {
            const tabButtons = Array.from(document.querySelectorAll('.tab-button'));
            const tabPanels = Array.from(document.querySelectorAll('.tab-panel'));
            const subTabButtons = Array.from(document.querySelectorAll('.sub-tab'));
            const subTabPanels = Array.from(document.querySelectorAll('.subtab-panel'));

            function changeTab(button) {
                const targetTab = button.getAttribute('data-tab');
                if (!targetTab) return;
                tabButtons.forEach(btn => {
                    const isActive = btn === button;
                    btn.classList.toggle('active', isActive);
                    btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                tabPanels.forEach(panel => panel.classList.toggle('active', panel.id === targetTab));
                if (targetTab === 'promotion-tab') filterPromotionStudents();
                if (targetTab === 'promotion-history-tab') loadPromotionHistory();
            }

            function changeSubTab(button) {
                const targetSubTab = button.getAttribute('data-subtab');
                if (!targetSubTab) return;
                subTabButtons.forEach(btn => btn.classList.toggle('active', btn === button));
                subTabPanels.forEach(panel => panel.classList.toggle('active', panel.id === targetSubTab));
            }

            // Click handlers
            tabButtons.forEach(button => button.addEventListener('click', e => { e.preventDefault(); changeTab(button); }));
            subTabButtons.forEach(button => button.addEventListener('click', e => { e.preventDefault(); changeSubTab(button); }));

            // Attach swipe navigation on mobile-sized screens
            function attachSwipeNavigation(container, buttons, onSelect) {
                if (!container || !buttons || buttons.length < 2) return;
                let startX = 0, currentX = 0, isTouching = false;
                container.addEventListener('touchstart', function (e) {
                    if (e.touches.length !== 1) return;
                    startX = e.touches[0].clientX; currentX = startX; isTouching = true;
                }, { passive: true });
                container.addEventListener('touchmove', function (e) {
                    if (!isTouching || e.touches.length !== 1) return; currentX = e.touches[0].clientX;
                }, { passive: true });
                container.addEventListener('touchend', function () {
                    if (!isTouching) return; const delta = startX - currentX; const threshold = 50;
                    if (Math.abs(delta) >= threshold) {
                        const activeIndex = buttons.findIndex(b => b.classList.contains('active'));
                        if (activeIndex === -1) { isTouching = false; return; }
                        const nextIndex = delta > 0 ? Math.min(buttons.length - 1, activeIndex + 1) : Math.max(0, activeIndex - 1);
                        if (nextIndex !== activeIndex) onSelect(buttons[nextIndex]);
                    }
                    isTouching = false;
                }, { passive: true });
            }

            // Use header containers for swipe (match Events & Activities approach)
            const tabHeader = document.querySelector('.tab-buttons');
            const subTabHeader = document.querySelector('.sub-tabs');

            // Attach only on mobile widths to avoid interfering with desktop gestures
            function enableSwipesIfMobile() {
                const isMobile = window.matchMedia('(max-width: 720px)').matches;
                if (isMobile) {
                    attachSwipeNavigation(tabHeader, tabButtons, changeTab);
                    attachSwipeNavigation(subTabHeader, subTabButtons, changeSubTab);
                }
            }

            enableSwipesIfMobile();
            window.addEventListener('resize', enableSwipesIfMobile);
        }

        function initializePromotionPage() {
            initPromotionTabs();
            setPromotionYearOptions('');
            updateSelectedList();
            filterPromotionStudents();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializePromotionPage);
        } else {
            initializePromotionPage();
        }

        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        function loadPromotionHistory() {
            const course = document.getElementById('history-course').value;
            const action = document.getElementById('history-action').value;
            const year = document.getElementById('history-year').value;
            const dateRange = document.getElementById('history-date').value;

            fetch('student_promotion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=load_history&course=${course}&year=${year}&action_type=${action}&date_range=${dateRange}`
            })
            .then(response => response.json())
            .then(data => {
                const historyList = document.getElementById('promotion-history-list');
                if (data.success) {
                    historyList.innerHTML = data.history.map(item => `
                        <div class="history-item">
                            <div class="history-header">
                                <span class="student-name">${item.student_name}</span>
                                <span class="action-type ${item.action_type}">${item.action_type}</span>
                                <span class="timestamp">${item.timestamp}</span>
                            </div>
                            <div class="history-details">
                                <p><strong>Course Year:</strong> ${item.course_year}</p>
                                <p><strong>Action:</strong> ${item.description}</p>
                                <p><strong>Performed by:</strong> ${item.performed_by}</p>
                            </div>
                        </div>
                    `).join('');
                } else {
                    historyList.innerHTML = '<p class="no-data">No promotion history found.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading history:', error);
                document.getElementById('promotion-history-list').innerHTML = '<p class="error">Error loading history.</p>';
            });
        }

        function exportHistory() {
            const course = document.getElementById('history-course').value;
            const action = document.getElementById('history-action').value;
            const year = document.getElementById('history-year').value;
            const dateRange = document.getElementById('history-date').value;

            const url = `student_promotion.php?action=export_history&course=${course}&year=${year}&action_type=${action}&date_range=${dateRange}`;
            window.open(url, '_blank');
        }

        // Student Management Functions
        function searchTransferStudents() {
            const searchTerm = document.getElementById('transfer-student-search').value.trim();

            if (searchTerm.length < 2) {
                document.getElementById('transfer-search-results').innerHTML = '';
                return;
            }

            const searchParams = new URLSearchParams();
            searchParams.append('action', 'search_students');
            searchParams.append('search', searchTerm);

            fetch('student_promotion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: searchParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('transfer-search-results');
                if (data.success && data.students.length > 0) {
                    resultsDiv.innerHTML = data.students.map(student => `
                        <div class="search-result-item" onclick="selectTransferStudent(${student.id}, '${student.full_name}', '${student.course_year}', '${student.academic_status}', '${student.enrollment_status || 'active'}')">
                            <div class="student-name">${student.full_name}</div>
                            <div class="student-details">${student.course_year} | Status: ${student.enrollment_status || 'active'}</div>
                        </div>
                    `).join('');
                } else {
                    resultsDiv.innerHTML = '<div class="no-results">No students found</div>';
                }
            })
            .catch(error => console.error('Error searching students:', error));
        }

        function selectTransferStudent(id, name, courseYear, status, enrollmentStatus) {
            document.getElementById('transfer-student-name').textContent = name;
            document.getElementById('transfer-current-level').textContent = courseYear;
            document.getElementById('transfer-academic-status').textContent = status || 'Not Set';
            document.getElementById('transfer-enrollment-status').textContent = enrollmentStatus;

            document.getElementById('transfer-management-content').style.display = 'block';
            document.getElementById('transfer-btn').disabled = false;
            document.getElementById('transfer-btn').dataset.studentId = id;

            document.getElementById('transfer-search-results').innerHTML = '';
            document.getElementById('transfer-student-search').value = name;
        }

        function resetTransferManagement() {
            document.getElementById('transfer-student-search').value = '';
            document.getElementById('transfer-search-results').innerHTML = '';
            document.getElementById('transfer-management-content').style.display = 'none';
            document.getElementById('transfer-btn').disabled = true;
            document.getElementById('transfer-reason').value = '';
        }

        function processStudentTransfer() {
            const studentId = document.getElementById('transfer-btn').dataset.studentId;
            const transferReason = document.getElementById('transfer-reason').value.trim();

            if (!studentId) {
                showNotification('Please select a student first.', 'error');
                return;
            }

            if (!transferReason) {
                showNotification('Please provide a transfer reason.', 'error');
                return;
            }

            if (!confirm('Are you sure you want to process this student transfer? This will deactivate their account.')) {
                return;
            }

            fetch('student_promotion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=transfer_student&student_id=${studentId}&transfer_reason=${encodeURIComponent(transferReason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error processing transfer.', 'error');
            });
        }

        function searchDiscontinueStudents() {
            const searchTerm = document.getElementById('discontinue-student-search').value.trim();

            if (searchTerm.length < 2) {
                document.getElementById('discontinue-search-results').innerHTML = '';
                return;
            }

            const searchParams = new URLSearchParams();
            searchParams.append('action', 'search_students');
            searchParams.append('search', searchTerm);

            fetch('student_promotion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: searchParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('discontinue-search-results');
                if (data.success && data.students.length > 0) {
                    resultsDiv.innerHTML = data.students.map(student => `
                        <div class="search-result-item" onclick="selectDiscontinueStudent(${student.id}, '${student.full_name}', '${student.course_year}', '${student.academic_status}', '${student.enrollment_status || 'active'}')">
                            <div class="student-name">${student.full_name}</div>
                            <div class="student-details">${student.course_year} | Status: ${student.enrollment_status || 'active'}</div>
                        </div>
                    `).join('');
                } else {
                    resultsDiv.innerHTML = '<div class="no-results">No students found</div>';
                }
            })
            .catch(error => console.error('Error searching students:', error));
        }

        function selectDiscontinueStudent(id, name, courseYear, status, enrollmentStatus) {
            document.getElementById('discontinue-student-name').textContent = name;
            document.getElementById('discontinue-current-level').textContent = courseYear;
            document.getElementById('discontinue-academic-status').textContent = status || 'Not Set';
            document.getElementById('discontinue-enrollment-status').textContent = enrollmentStatus;

            document.getElementById('discontinuation-content').style.display = 'block';
            document.getElementById('discontinue-btn').disabled = false;
            document.getElementById('discontinue-btn').dataset.studentId = id;

            document.getElementById('discontinue-search-results').innerHTML = '';
            document.getElementById('discontinue-student-search').value = name;
        }

        function resetDiscontinuation() {
            document.getElementById('discontinue-student-search').value = '';
            document.getElementById('discontinue-search-results').innerHTML = '';
            document.getElementById('discontinuation-content').style.display = 'none';
            document.getElementById('discontinue-btn').disabled = true;
            document.getElementById('discontinuation-reason').value = '';
        }

        function processStudentDiscontinuation() {
            const studentId = document.getElementById('discontinue-btn').dataset.studentId;
            const discontinuationReason = document.getElementById('discontinuation-reason').value.trim();

            if (!studentId) {
                showNotification('Please select a student first.', 'error');
                return;
            }

            if (!discontinuationReason) {
                showNotification('Please provide a discontinuation reason.', 'error');
                return;
            }

            if (!confirm('Are you sure you want to process this student discontinuation? This will deactivate their account.')) {
                return;
            }

            fetch('student_promotion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=discontinue_student&student_id=${studentId}&discontinuation_reason=${encodeURIComponent(discontinuationReason)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error processing discontinuation.', 'error');
            });
        }

        function searchGraduateStudents() {
            const searchTerm = document.getElementById('graduate-student-search').value.trim();

            if (searchTerm.length < 2) {
                document.getElementById('graduate-search-results').innerHTML = '';
                return;
            }

            const searchParams = new URLSearchParams();
            searchParams.append('action', 'search_students');
            searchParams.append('search', searchTerm);

            fetch('student_promotion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: searchParams.toString()
            })
            .then(response => response.json())
            .then(data => {
                const resultsDiv = document.getElementById('graduate-search-results');
                if (data.success && data.students.length > 0) {
                    resultsDiv.innerHTML = data.students.map(student => `
                        <div class="search-result-item" onclick="selectGraduateStudent(${student.id}, '${student.full_name}', '${student.course_year}', '${student.academic_status}', '${student.gpa}')">
                            <div class="student-name">${student.full_name}</div>
                            <div class="student-details">${student.course_year} | GPA: ${student.gpa || 'N/A'}</div>
                        </div>
                    `).join('');
                } else {
                    resultsDiv.innerHTML = '<div class="no-results">No students found</div>';
                }
            })
            .catch(error => console.error('Error searching students:', error));
        }

        function selectGraduateStudent(id, name, courseYear, status, gpa) {
            document.getElementById('graduate-student-name').textContent = name;
            document.getElementById('graduate-current-level').textContent = courseYear;
            document.getElementById('graduate-academic-status').textContent = status || 'Not Set';
            document.getElementById('graduate-student-gpa').textContent = gpa || 'N/A';

            document.getElementById('graduation-content').style.display = 'block';
            document.getElementById('confirm-graduation').addEventListener('change', function() {
                document.getElementById('graduate-btn').disabled = !this.checked;
            });
            document.getElementById('graduate-btn').dataset.studentId = id;

            document.getElementById('graduate-search-results').innerHTML = '';
            document.getElementById('graduate-student-search').value = name;
        }

        function resetGraduation() {
            document.getElementById('graduate-student-search').value = '';
            document.getElementById('graduate-search-results').innerHTML = '';
            document.getElementById('graduation-content').style.display = 'none';
            document.getElementById('graduate-btn').disabled = true;
            document.getElementById('confirm-graduation').checked = false;
        }

        function processManualGraduation() {
            const studentId = document.getElementById('graduate-btn').dataset.studentId;
            const confirmed = document.getElementById('confirm-graduation').checked;

            if (!studentId) {
                showNotification('Please select a student first.', 'error');
                return;
            }

            if (!confirmed) {
                showNotification('Please confirm that the student meets graduation requirements.', 'error');
                return;
            }

            if (!confirm('Are you sure you want to graduate this student? This action cannot be undone.')) {
                return;
            }

            fetch('student_promotion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=graduate_student&student_id=${studentId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error processing graduation.', 'error');
            });
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