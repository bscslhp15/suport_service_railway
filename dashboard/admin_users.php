<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

ensure_pending_registration_schema();
ensure_user_archive_schema();
$pdo = get_db();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalTeachers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$totalAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

$message = '';
$messageType = 'success';
$isSuperAdmin = strtolower(trim($user['email'])) === 'admin@supportsystem.local';
$activeTab = $_GET['active_tab'] ?? 'tab-directory';
if (!$isSuperAdmin && $activeTab === 'tab-create-account') {
    $activeTab = 'tab-directory';
}
$approvalEmailData = null;
$showVerificationModal = false;
$verificationData = [
    'email' => '',
    'name' => '',
    'code' => '',
    'expires' => null,
    'sendOnLoad' => false,
    'actionType' => 'create_account',
];
$verificationAction = 'verify_email';
$verificationStage = 'enter_code';
$verificationTitle = 'Check Your mail.';
$verificationDescription = '';

$pendingVerificationEmail = secure_input($_GET['email'] ?? '');
$manageVerificationRequest = !empty($_GET['manage_verify']) && $_GET['manage_verify'] === '1';

if ($manageVerificationRequest) {
    $manageAction = $_SESSION['manage_account_action'] ?? null;
    if (!empty($_GET['action']) && $_GET['action'] === 'refresh') {
        if ($manageAction) {
            $verificationCode = generate_code();
            $verificationExpires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
            $_SESSION['manage_account_action']['verification_code'] = $verificationCode;
            $_SESSION['manage_account_action']['verification_expires'] = $verificationExpires;
            header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode('tab-manage-account') . '&manage_verify=1&send=1');
            exit;
        }
    }

    if ($manageAction) {
        $showVerificationModal = true;
        $verificationData['actionType'] = $manageAction['type'];
        if ($manageAction['type'] === 'change_email') {
            $verificationData['email'] = $manageAction['new_email'];
        } else {
            $targetUser = find_user_by_id((int) $manageAction['user_id']);
            $verificationData['email'] = $targetUser['email'] ?? '';
            $verificationData['name'] = $targetUser['full_name'] ?? '';
        }
        $verificationData['code'] = $manageAction['verification_code'] ?? '';
        $verificationData['expires'] = !empty($manageAction['verification_expires']) ? (new DateTime($manageAction['verification_expires']))->getTimestamp() : null;
        $verificationData['sendOnLoad'] = isset($_GET['send']) && $_GET['send'] === '1';
        $activeTab = 'tab-manage-account';

        if ($manageAction['type'] === 'reset_password') {
            if (!empty($_GET['verified']) && $_GET['verified'] === '1' && !empty($manageAction['verified'])) {
                $verificationStage = 'set_password';
                $verificationAction = 'confirm_manage_action';
                $verificationTitle = 'Set New Password';
                $verificationDescription = 'Enter a new password for the selected user to complete the reset process.';
            } else {
                $verificationAction = 'verify_manage_action';
                $verificationTitle = 'Verify Password Reset Code';
                $verificationDescription = 'A six-digit verification code was sent to <strong>' . htmlspecialchars($verificationData['email']) . '</strong>. Enter the code below to continue.';
            }
        } elseif ($manageAction['type'] === 'change_email') {
            $verificationAction = 'verify_manage_action';
            $verificationTitle = 'Verify Email Change';
            $verificationDescription = 'A six-digit verification code was sent to <strong>' . htmlspecialchars($verificationData['email']) . '</strong>. Enter the code below to confirm the new email address.';
        }
    } else {
        $message = 'No pending account management action found. Please start again.';
        $messageType = 'error';
        $activeTab = 'tab-manage-account';
    }
} elseif (!empty($_GET['verify_email']) && $_GET['verify_email'] === '1' && $pendingVerificationEmail) {
    $pendingRegistration = get_pending_registration($pendingVerificationEmail);
    if ($pendingRegistration) {
        if (!empty($_GET['action']) && $_GET['action'] === 'refresh') {
            $verificationCode = generate_code();
            $verificationExpires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
            $pendingRegistration['verification_code'] = $verificationCode;
            $pendingRegistration['verification_expires'] = $verificationExpires;
            set_pending_registration($pendingRegistration);
            header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode('tab-create-account') . '&verify_email=1&email=' . urlencode($pendingVerificationEmail) . '&send=1');
            exit;
        }

        $showVerificationModal = true;
        $verificationData = [
            'email' => $pendingRegistration['email'],
            'name' => $pendingRegistration['full_name'] ?? '',
            'code' => $pendingRegistration['verification_code'] ?? '',
            'expires' => !empty($pendingRegistration['verification_expires']) ? (new DateTime($pendingRegistration['verification_expires']))->getTimestamp() : null,
            'sendOnLoad' => isset($_GET['send']) && $_GET['send'] === '1',
            'actionType' => 'create_account',
        ];
        $verificationAction = 'verify_email';
        $verificationStage = 'enter_code';
        $verificationTitle = 'Check Your mail.';
        $verificationDescription = 'A six-digit verification code is on its way to <strong>' . htmlspecialchars($verificationData['email']) . '</strong>. It expires in 10 minutes.';
        $activeTab = 'tab-create-account';
    } else {
        $message = 'No pending registration found for that email. Please register again.';
        $messageType = 'error';
        $activeTab = 'tab-create-account';
    }
}

if (!empty($_SESSION['admin_users_flash'])) {
    $flash = $_SESSION['admin_users_flash'];
    $message = $flash['message'] ?? '';
    $messageType = $flash['messageType'] ?? 'success';
    $activeTab = $flash['activeTab'] ?? $activeTab;
    unset($_SESSION['admin_users_flash']);
}

if (!empty($_SESSION['approval_email_data'])) {
    $approvalEmailData = $_SESSION['approval_email_data'];
    unset($_SESSION['approval_email_data']);
}

$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = in_array($currentPage, ['student_promotion.php', 'graduation_events.php', 'alumni_management.php', 'reports_analytics.php'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit_student_teacher' && !empty($_POST['user_id'])) {
        $userId = (int) $_POST['user_id'];
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $courseDepartment = trim((string) ($_POST['course_department'] ?? ''));
        $yearLevel = trim((string) ($_POST['year_level'] ?? ''));
        $idNumber = trim((string) ($_POST['id_number'] ?? ''));
        $targetUser = find_user_by_id($userId);

        if (!$targetUser || !in_array($targetUser['role'], ['student', 'teacher'], true)) {
            $message = 'Only student and teacher accounts can be edited here.';
            $messageType = 'error';
        } elseif ($fullName === '' || $courseDepartment === '' || $idNumber === '' || ($targetUser['role'] === 'student' && $yearLevel === '')) {
            $message = 'Name, Course / Department, and ID Number are required.';
            $messageType = 'error';
        } else {
            $courseYear = $targetUser['role'] === 'student' ? trim($courseDepartment . ' ' . $yearLevel) : $courseDepartment;
            $updated = update_user([
                'id' => $userId,
                'full_name' => $fullName,
                'course_year' => $courseYear,
                'student_id' => $targetUser['role'] === 'student' ? $idNumber : null,
                'employee_id' => $targetUser['role'] === 'teacher' ? $idNumber : null,
            ]);
            $message = $updated ? 'User information updated successfully.' : 'Unable to update user information.';
            $messageType = $updated ? 'success' : 'error';
        }

        $activeTab = 'tab-student-data';
        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];
        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab));
        exit;
    }

    if ($action === 'archive_user' && !empty($_POST['user_id'])) {
        $userId = (int) $_POST['user_id'];
        $stmt = $pdo->prepare('UPDATE users SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE id = ? AND (is_archived = 0 OR is_archived IS NULL)');
        $stmt->execute([$user['id'], $userId]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'User has been archived successfully.'
        ]);
        exit;
    }

    if ($action === 'archive_users_by_filter') {
        $course = trim((string) ($_POST['course'] ?? ''));
        $year = trim((string) ($_POST['year'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $stmt = $pdo->query("SELECT id, course_year, role FROM users WHERE role IN ('student','teacher') AND (is_archived = 0 OR is_archived IS NULL) ORDER BY role, full_name");
        $usersToArchive = $stmt->fetchAll();
        $userIds = [];

        foreach ($usersToArchive as $row) {
            [$rowCourse, $rowYear] = parse_user_course_and_year($row['course_year'] ?? '');
            $matchesCourse = $course === '' || $course === 'All Courses' || normalize_user_course_value($rowCourse) === normalize_user_course_value($course);
            $matchesYear = $year === '' || $year === 'All Years' || $rowYear === $year;
            $matchesRole = $role === '' || $role === 'All Roles' || ($row['role'] ?? '') === $role;

            if ($matchesCourse && $matchesYear && $matchesRole) {
                $userIds[] = (int) $row['id'];
            }
        }

        if (empty($userIds)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'archived_count' => 0, 'message' => 'No matching user records were found to archive.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $updateStmt = $pdo->prepare('UPDATE users SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE id IN (' . $placeholders . ')');
        $updateStmt->execute([$user['id'], ...$userIds]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'archived_count' => $updateStmt->rowCount(),
            'message' => 'Selected user records have been archived.'
        ]);
        exit;
    }

    if ($action === 'get_archived_users') {
        $course = trim((string) ($_POST['course'] ?? ''));
        $year = trim((string) ($_POST['year'] ?? ''));
        $stmt = $pdo->query("SELECT id, full_name, email, role, course_year, archived_at FROM users WHERE is_archived = 1 AND role IN ('student','teacher') ORDER BY archived_at DESC, full_name ASC");
        $archivedUsers = $stmt->fetchAll();

        if ($course !== '' && $course !== 'All Courses') {
            $archivedUsers = array_values(array_filter($archivedUsers, function (array $userRow) use ($course): bool {
                [$rowCourse] = parse_user_course_and_year($userRow['course_year'] ?? '');
                return normalize_user_course_value($rowCourse) === normalize_user_course_value($course);
            }));
        }

        if ($year !== '' && $year !== 'All Years') {
            $archivedUsers = array_values(array_filter($archivedUsers, function (array $userRow) use ($year): bool {
                [, $rowYear] = parse_user_course_and_year($userRow['course_year'] ?? '');
                return $rowYear === $year;
            }));
        }

        header('Content-Type: application/json');
        echo json_encode($archivedUsers);
        exit;
    }

    if ($action === 'restore_archived_user' && !empty($_POST['user_id'])) {
        $userId = (int) $_POST['user_id'];
        $stmt = $pdo->prepare('UPDATE users SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = ?');
        $stmt->execute([$userId]);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'User has been restored successfully.'
        ]);
        exit;
    }
    
    // If no explicit action but pending_id is present, default to approval
    // This handles cases where form is submitted programmatically
    if (empty($action) && !empty($_POST['pending_id'])) {
        $action = 'approve_pending';
    }
    
    if ($action === 'approve_pending' && !empty($_POST['pending_id'])) {
        $pendingId = (int) $_POST['pending_id'];
        $pendingRegistration = get_db_pending_registration_by_id($pendingId);
        if ($pendingRegistration && approve_db_pending_registration($pendingId, $user['id'])) {
            // Create the actual user account from the approved pending registration
            // Check if user already exists to avoid duplicate key error
            $existingUser = find_user_by_email($pendingRegistration['email']);
            
            if ($existingUser) {
                // User already exists, just mark as approved
                $message = 'Student registration approved. Account already exists.';
                $messageType = 'success';
                $activeTab = 'tab-student-data';
            } else {
                // Create new user account
                $accountCreated = save_user([
                    'full_name' => $pendingRegistration['full_name'],
                    'email' => $pendingRegistration['email'],
                    'username' => null,
                    'role' => $pendingRegistration['role'] ?? 'student',
                    'course_year' => $pendingRegistration['course_year'] ?? null,
                    'student_id' => $pendingRegistration['student_id'] ?? null,
                    'employee_id' => $pendingRegistration['employee_id'] ?? null,
                    'is_head' => $pendingRegistration['is_head'] ?? 0,
                    'head_service' => $pendingRegistration['head_service'] ?? 'none',
                    'email_verified' => 1,
                    'verification_code' => null,
                    'verification_expires' => null,
                    'password_hash' => $pendingRegistration['password_hash'],
                    'admin_type' => $pendingRegistration['admin_type'] ?? null,
                ]);

                if ($accountCreated) {
                    $message = 'Student registration approved and account has been created successfully.';
                    $messageType = 'success';
                    $activeTab = 'tab-student-data';
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $basePath = dirname(dirname($_SERVER['REQUEST_URI']));
                    $loginLink = rtrim($protocol . '://' . $_SERVER['HTTP_HOST'] . $basePath, '/') . '/auth/student_login.php';
                    $approvalEmailData = [
                        'sendOnLoad' => true,
                        'email' => $pendingRegistration['email'],
                        'user_name' => $pendingRegistration['full_name'],
                        'login_link' => $loginLink,
                    ];
                } else {
                    $message = 'Registration approved but failed to create account. Please try again.';
                    $messageType = 'error';
                    $activeTab = 'tab-pending-approvals';
                }
            }
        } else {
            $message = 'Unable to approve the registration. Please try again.';
            $messageType = 'error';
            $activeTab = 'tab-pending-approvals';
        }

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        if (!empty($approvalEmailData)) {
            $_SESSION['approval_email_data'] = $approvalEmailData;
        }

        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab));
        exit;
    }

    if ($action === 'reject_pending' && !empty($_POST['pending_id'])) {
        $pendingId = (int) $_POST['pending_id'];
        if (reject_db_pending_registration($pendingId)) {
            $message = 'Student registration request rejected.';
            $messageType = 'success';
        } else {
            $message = 'Unable to reject the registration. Please try again.';
            $messageType = 'error';
        }
        $activeTab = 'tab-pending-approvals';

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab));
        exit;
    }

    if ($action === 'create_account' && $isSuperAdmin) {
        $category = $_POST['account_category'] ?? '';
        $studentFirstName = trim($_POST['student_first_name'] ?? '');
        $studentMiddleName = trim($_POST['student_middle_name'] ?? '');
        $studentLastName = trim($_POST['student_last_name'] ?? '');
        $teacherFullName = trim($_POST['teacher_full_name'] ?? '');
        $subadminFullName = trim($_POST['subadmin_full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        $studentId = trim($_POST['student_id'] ?? '');
        $studentCourse = trim($_POST['student_course'] ?? '');
        $studentLadderizeCourse = trim($_POST['student_ladderize_course'] ?? '');
        $studentYear = trim($_POST['student_year'] ?? '');
        $teacherCourse = trim($_POST['teacher_course'] ?? '');
        $employeeId = trim($_POST['employee_id'] ?? '');
        $isHead = isset($_POST['is_head']) ? 1 : 0;
        $designatedOffice = $_POST['designated_office'] ?? 'none';

        if ($category === 'student') {
            $fullName = trim($studentFirstName . ' ' . ($studentMiddleName ? $studentMiddleName . ' ' : '') . $studentLastName);
            $fullName = ucwords(mb_strtolower($fullName));
        } elseif ($category === 'teacher') {
            $fullName = ucwords(mb_strtolower($teacherFullName));
        } elseif ($category === 'subadmin') {
            $fullName = ucwords(mb_strtolower($subadminFullName));
        }

        if (!$category || !$email || !$password || !$confirmPassword || ($category === 'student' && (!$studentFirstName || !$studentLastName || !$studentId || !$studentYear)) || (($category === 'teacher' || $category === 'subadmin') && !$fullName) || ($category === 'teacher' && !$employeeId)) {
            $message = 'Please complete all required account information fields.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif (find_user_by_email($email)) {
            $message = 'A user with this email address already exists.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif ($password !== $confirmPassword) {
            $message = 'Password and confirm password do not match.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters long.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif ($category === 'student' && $studentCourse && $studentLadderizeCourse) {
            $message = 'Please choose either Course or Ladderize Course, not both.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif ($category === 'student' && !$studentCourse && !$studentLadderizeCourse) {
            $message = 'Please choose a Course or a Ladderize Course.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif ($category === 'student' && !preg_match('/^[0-9]{2}-[0-9]{5}$/', $studentId)) {
            $message = 'Student ID must be in the format 22-12345.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } else {
            $role = 'student';
            $headService = 'none';
            $isHeadValue = 0;
            $adminType = null;
            $studentIdValue = null;
            $courseYearValue = null;
            $employeeIdValue = null;

            if ($category === 'student') {
                $role = 'student';
                $studentIdValue = $studentId;
                $selectedCourse = $studentLadderizeCourse ?: $studentCourse;
                $courseYearValue = $selectedCourse && $studentYear ? trim($selectedCourse . ' ' . $studentYear) : null;
            } elseif ($category === 'teacher') {
                $role = 'teacher';
                $employeeIdValue = $employeeId;
                $isHeadValue = $isHead;
                $headService = $isHead ? $designatedOffice : 'none';
                $courseYearValue = $teacherCourse ?: null;
            } elseif ($category === 'subadmin') {
                $role = 'admin';
                $adminType = 'sub-admin';
                $courseYearValue = null;
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $verificationCode = generate_code();
            $verificationExpires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

            set_pending_registration([
                'full_name' => $fullName,
                'email' => $email,
                'role' => $role,
                'course_year' => $courseYearValue,
                'student_id' => $studentIdValue,
                'employee_id' => $employeeIdValue,
                'is_head' => $isHeadValue,
                'head_service' => $headService,
                'admin_type' => $adminType,
                'email_verified' => 0,
                'verification_code' => $verificationCode,
                'verification_expires' => $verificationExpires,
                'password_hash' => $passwordHash,
            ]);

            $message = 'A verification code has been sent to the provided email. Complete verification to finish account registration.';
            $messageType = 'success';
            $activeTab = 'tab-create-account';

            $_SESSION['admin_users_flash'] = [
                'message' => $message,
                'messageType' => $messageType,
                'activeTab' => $activeTab,
            ];

            header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab) . '&verify_email=1&email=' . urlencode($email) . '&send=1');
            exit;
        }

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab));
        exit;
    }

    if ($action === 'verify_email' && $isSuperAdmin) {
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $pendingRegistration = get_pending_registration($email);

        if (!$pendingRegistration) {
            $message = 'No pending registration found for that email. Please register again.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif ($pendingRegistration['verification_code'] !== $code) {
            $message = 'Invalid verification code.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif (empty($pendingRegistration['verification_expires']) || new DateTime() > new DateTime($pendingRegistration['verification_expires'])) {
            $message = 'This verification code has expired.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } elseif (find_user_by_email($email)) {
            $message = 'That email is already registered.';
            $messageType = 'error';
            $activeTab = 'tab-create-account';
        } else {
            $created = save_user([
                'full_name' => $pendingRegistration['full_name'],
                'email' => $pendingRegistration['email'],
                'username' => null,
                'role' => $pendingRegistration['role'],
                'course_year' => $pendingRegistration['course_year'] ?? null,
                'student_id' => $pendingRegistration['student_id'] ?? null,
                'employee_id' => $pendingRegistration['employee_id'] ?? null,
                'is_head' => $pendingRegistration['is_head'] ?? 0,
                'head_service' => $pendingRegistration['head_service'] ?? 'none',
                'email_verified' => 1,
                'verification_code' => null,
                'verification_expires' => null,
                'password_hash' => $pendingRegistration['password_hash'],
                'admin_type' => $pendingRegistration['admin_type'] ?? null,
            ]);

            if ($created) {
                $message = 'New account was successfully created after email verification.';
                $messageType = 'success';
                $activeTab = 'tab-directory';
                $totalUsers++;
                if ($pendingRegistration['role'] === 'teacher') {
                    $totalTeachers++;
                }
                if ($pendingRegistration['role'] === 'admin') {
                    $totalAdmins++;
                }
                clear_pending_registration();
            } else {
                $message = 'Unable to create the account. Please check the input and try again.';
                $messageType = 'error';
                $activeTab = 'tab-create-account';
            }
        }

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        $redirectUrl = basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab);
        if ($activeTab === 'tab-create-account') {
            $redirectUrl .= '&verify_email=1&email=' . urlencode($email);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($action === 'manage_reset_password' && $isSuperAdmin) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $targetUser = find_user_by_id($userId);

        if (!$targetUser) {
            $message = 'User not found.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } else {
            $verificationCode = generate_code();
            $verificationExpires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

            $_SESSION['manage_account_action'] = [
                'type' => 'reset_password',
                'user_id' => $userId,
                'verification_code' => $verificationCode,
                'verification_expires' => $verificationExpires,
            ];

            $message = 'A verification code has been sent to ' . htmlspecialchars($targetUser['email']) . '. Enter the code below to set a new password.';
            $messageType = 'success';
            $activeTab = 'tab-manage-account';
        }

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab) . '&manage_verify=1&send=1');
        exit;
    }

    if ($action === 'manage_change_email' && $isSuperAdmin) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newEmail = trim($_POST['new_email'] ?? '');
        $targetUser = find_user_by_id($userId);

        if (!$targetUser) {
            $message = 'User not found.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } elseif ($newEmail === $targetUser['email']) {
            $message = 'The new email must be different from the current email.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } elseif (find_user_by_email($newEmail)) {
            $message = 'This email address is already in use.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } else {
            $verificationCode = generate_code();
            $verificationExpires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

            $_SESSION['manage_account_action'] = [
                'type' => 'change_email',
                'user_id' => $userId,
                'new_email' => $newEmail,
                'verification_code' => $verificationCode,
                'verification_expires' => $verificationExpires,
            ];

            $message = 'A verification code has been sent to ' . htmlspecialchars($newEmail) . '. Enter the code below to confirm the new email address.';
            $messageType = 'success';
            $activeTab = 'tab-manage-account';
        }

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab) . '&manage_verify=1&send=1');
        exit;
    }

    if ($action === 'delete_user' && $isSuperAdmin) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $targetUser = find_user_by_id($userId);

        if (!$targetUser) {
            $message = 'User not found.';
            $messageType = 'error';
            $activeTab = 'tab-directory';
        } elseif ($targetUser['id'] === $user['id']) {
            $message = 'You cannot delete your own account.';
            $messageType = 'error';
            $activeTab = 'tab-directory';
        } else {
            try {
                // Delete user from database
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                
                $message = 'User account for ' . htmlspecialchars($targetUser['full_name']) . ' has been deleted successfully.';
                $messageType = 'success';
                $activeTab = 'tab-directory';
            } catch (Exception $e) {
                $message = 'Error deleting user: ' . htmlspecialchars($e->getMessage());
                $messageType = 'error';
                $activeTab = 'tab-directory';
            }
        }

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab));
        exit;
    }

    if ($action === 'delete_student_teacher') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $targetUser = find_user_by_id($userId);

        if (!$targetUser) {
            $message = 'User not found.';
            $messageType = 'error';
        } elseif ($targetUser['id'] === $user['id']) {
            $message = 'You cannot delete your own account.';
            $messageType = 'error';
        } elseif (!in_array($targetUser['role'], ['student', 'teacher'], true)) {
            $message = 'Only student and teacher accounts can be removed from this tab.';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role IN ('student', 'teacher')");
                $stmt->execute([$userId]);
                $pdo->commit();
                $message = 'The account and related system data for ' . $targetUser['full_name'] . ' have been removed.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = 'Unable to remove this account and its related data.';
                $messageType = 'error';
            }
        }

        $activeTab = 'tab-student-data';
        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab));
        exit;
    }

    if ($action === 'verify_manage_action' && $isSuperAdmin) {
        $code = trim($_POST['code'] ?? '');
        $manageAction = $_SESSION['manage_account_action'] ?? null;

        if (!$manageAction) {
            $message = 'No pending account action found. Please start again.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } elseif (empty($code)) {
            $message = 'Please enter the verification code.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } elseif ($manageAction['verification_code'] !== $code) {
            $message = 'Invalid verification code.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } elseif (empty($manageAction['verification_expires']) || new DateTime() > new DateTime($manageAction['verification_expires'])) {
            $message = 'This verification code has expired.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } else {
            if ($manageAction['type'] === 'reset_password') {
                $_SESSION['manage_account_action']['verified'] = true;
                header('Location: ' . basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode('tab-manage-account') . '&manage_verify=1&verified=1');
                exit;
            }

            if ($manageAction['type'] === 'change_email') {
                $updated = update_user([
                    'id' => $manageAction['user_id'],
                    'email' => $manageAction['new_email'],
                    'email_verified' => 1,
                ]);

                if ($updated) {
                    unset($_SESSION['manage_account_action']);
                    $message = 'Email address updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Unable to update the email address. Please try again.';
                    $messageType = 'error';
                }
                $activeTab = 'tab-manage-account';
            }
        }

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        $redirectUrl = basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab);
        if ($activeTab === 'tab-manage-account' && $messageType === 'error') {
            $redirectUrl .= '&manage_verify=1';
            if (!empty($manageAction) && $manageAction['type'] === 'reset_password' && !empty($manageAction['verified'])) {
                $redirectUrl .= '&verified=1';
            }
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($action === 'confirm_manage_action' && $isSuperAdmin) {
        $manageAction = $_SESSION['manage_account_action'] ?? null;
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_new_password'] ?? '');

        if (!$manageAction || $manageAction['type'] !== 'reset_password' || empty($manageAction['verified'])) {
            $message = 'No verified reset password action was found. Please start again.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
        } elseif (empty($newPassword) || empty($confirmPassword)) {
            $message = 'Please enter and confirm the new password.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
            $redirectExtra = '&manage_verify=1&verified=1';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Password and confirm password do not match.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
            $redirectExtra = '&manage_verify=1&verified=1';
        } elseif (strlen($newPassword) < 8) {
            $message = 'Password must be at least 8 characters long.';
            $messageType = 'error';
            $activeTab = 'tab-manage-account';
            $redirectExtra = '&manage_verify=1&verified=1';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updated = update_user([
                'id' => $manageAction['user_id'],
                'password_hash' => $passwordHash,
            ]);

            if ($updated) {
                unset($_SESSION['manage_account_action']);
                $message = 'Password reset successfully.';
                $messageType = 'success';
                $activeTab = 'tab-manage-account';
            } else {
                $message = 'Unable to reset the password. Please try again.';
                $messageType = 'error';
                $activeTab = 'tab-manage-account';
                $redirectExtra = '&manage_verify=1&verified=1';
            }
        }

        $_SESSION['admin_users_flash'] = [
            'message' => $message,
            'messageType' => $messageType,
            'activeTab' => $activeTab,
        ];

        $redirectUrl = basename($_SERVER['PHP_SELF']) . '?active_tab=' . urlencode($activeTab);
        if (!empty($redirectExtra)) {
            $redirectUrl .= $redirectExtra;
        }
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$usersStmt = $pdo->query("SELECT id, full_name, email, role, head_service, student_id, employee_id, created_at FROM users WHERE (is_archived = 0 OR is_archived IS NULL) ORDER BY role, full_name");
$users = $usersStmt->fetchAll();
$studentStatement = $pdo->query("SELECT id, full_name, email, role, course_year, student_id, employee_id, created_at FROM users WHERE role IN ('student','teacher') AND (is_archived = 0 OR is_archived IS NULL) ORDER BY role, full_name");
$studentList = $studentStatement->fetchAll();
$pendingStatement = $pdo->query("SELECT id, full_name, email, student_id, course_year, documents, status, created_at FROM pending_student_registrations WHERE status = 'pending_admin' ORDER BY created_at ASC");
$pendingRegistrations = $pendingStatement->fetchAll();

$courseOptions = [
    'Bachelor of Science in Accountancy',
    'Bachelor of Science in Business Administration',
    'Bachelor in Elementary Education',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Criminology',
    'Bachelor of Science in Hospitality Management',
    'Bachelor of Science in Tourism Management',
];

$ladderizeOptions = [
    'Associate in Computer Technology',
    'Associate in Business Knowledge',
    'Associate in Hospitality Management',
    'Associate in Tourism Management',
];

$allCourseFilterOptions = array_merge($courseOptions, $ladderizeOptions);
$yearOptions = ['1', '2', '3', '4'];
$associateYearOptions = ['1', '2'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | Admin | PASS Support System</title>
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

        .admin-user-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(220px, 1fr));
            gap: 18px;
            margin: 0 0 24px;
        }

        .admin-user-summary .metric-card {
            min-height: 100px;
            border-radius: 18px;
            padding: 18px 22px;
            background: #f8f1e7;
            border: 2px solid #d4af37;
            box-shadow: none;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-user-summary .metric-card::before {
            display: none;
        }

        .admin-user-summary .metric-icon {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: #8a1d1d;
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .admin-user-summary .metric-content {
            display: flex;
            align-items: baseline;
            gap: 12px;
            color: #2d1c1d;
            font-weight: 700;
            width: 100%;
        }

        .admin-user-summary .metric-value {
            color: #8a1d1d;
            font-size: clamp(2rem, 3vw, 3rem);
            font-weight: 700;
            line-height: 1;
        }

        .admin-user-summary .metric-label {
            color: #2d1c1d;
            font-size: 1rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 700;
            white-space: nowrap;
        }

        .admin-tab-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #f8f4f0;
            color: #4c2f28;
            border: 1px solid rgba(133, 103, 88, 0.2);
            border-radius: 14px;
            padding: 14px 20px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.22s ease, box-shadow 0.22s ease, background 0.22s ease;
            margin-right: 10px;
        }

        .admin-tab-button.active {
            background: linear-gradient(135deg, #5C1F23 0%, #8B2E2F 55%, #A84A38 100%);
            color: #FAF7F0;
            box-shadow: 0 14px 27px rgba(92, 31, 35, 0.16);
        }

        .admin-tab-button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            background: #e7e1dd;
            color: #8a6f67;
        }

        .create-account-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        .create-card {
            background: white;
            border-radius: 28px;
            padding: 28px;
            border: 1px solid rgba(133, 103, 88, 0.16);
            box-shadow: 0 18px 40px rgba(66, 38, 25, 0.08);
        }

        .create-card-header {
            background: linear-gradient(135deg, #5C1F23 0%, #8B2E2F 55%, #A84A38 100%);
            color: #FAF7F0;
            padding: 20px 24px;
            margin: -28px -28px 24px;
            border-radius: 28px 28px 0 0;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .create-card h3 {
            margin: 0;
            font-size: 1.35rem;
        }

        .form-group {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
        }

        .form-group label {
            font-weight: 700;
            color: #5c372c;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            border-radius: 16px;
            border: 1px solid #d8d3cc;
            padding: 14px 16px;
            background: #fbf8f4;
            color: #312421;
            font-size: 1rem;
        }

        .radio-group {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            border-radius: 16px;
            border: 1px solid rgba(214, 168, 74, 0.24);
            background: #fff8f3;
            cursor: pointer;
            transition: background 0.22s ease, border-color 0.22s ease, transform 0.22s ease;
        }

        .radio-option:hover {
            background: #fff3e7;
            transform: translateY(-1px);
        }

        .radio-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .radio-option span {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            color: #4c2f28;
            font-weight: 700;
        }

        .radio-option span::before {
            content: '';
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid #b07a2b;
            background: #fff;
            flex-shrink: 0;
        }

        .radio-option input:checked + span::before {
            background: linear-gradient(135deg, #E0B250, #B07A2B);
            border-color: #8b2e2f;
        }

        .form-grid {
            display: grid;
            gap: 18px;
        }

        .student-name-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .course-selection-grid {
            grid-template-columns: 1fr 1fr;
        }

        .password-grid {
            grid-template-columns: 1fr 1fr;
        }

        .checkbox-group {
            margin-top: 4px;
        }

        .checkbox-option {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            cursor: pointer;
            color: #4c2f28;
        }

        .checkbox-option input {
            width: 18px;
            height: 18px;
            accent-color: #b07a2b;
        }

        .field-note {
            margin: 0;
            font-size: 0.94rem;
            color: #6e5548;
            line-height: 1.5;
        }

        .register-action {
            margin-top: 24px;
            text-align: right;
        }

        .btn-register {
            border: none;
            border-radius: 18px;
            padding: 16px 32px;
            color: #FAF7F0;
            background: linear-gradient(90deg, #E0B250, #B07A2B);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 14px 30px rgba(176, 122, 43, 0.18);
            transition: transform 0.22s ease, box-shadow 0.22s ease;
        }

        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 36px rgba(176, 122, 43, 0.22);
        }

        .form-section-note {
            margin: 0 0 24px;
            color: #6e5548;
            line-height: 1.8;
        }

        .hidden-field {
            display: none;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.48);
            z-index: 9999;
            padding: 24px;
        }

        .modal-overlay.visible {
            display: flex;
        }

        .verification-modal {
            width: min(560px, 100%);
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 28px 80px rgba(0, 0, 0, 0.18);
            padding: 28px 32px;
            position: relative;
            max-height: calc(100vh - 64px);
            overflow-y: auto;
        }

        .modal-close-btn {
            position: absolute;
            top: 18px;
            right: 18px;
            border: none;
            background: transparent;
            color: #4c2f28;
            font-size: 1.6rem;
            line-height: 1;
            cursor: pointer;
        }

        .verify-label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .verify-label {
            color: #b07a2b;
            font-weight: 700;
            letter-spacing: 0.2em;
            font-size: 0.85rem;
        }

        .verify-timer {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4c2f28;
            font-weight: 700;
        }

        .verify-title {
            margin: 0 0 16px;
            font-size: 2rem;
            line-height: 1.1;
            color: #2d2520;
        }

        .verify-title-mail {
            color: #b07a2b;
        }

        .verify-description {
            margin: 0 0 24px;
            color: #6e5548;
            line-height: 1.8;
        }

        .verify-input-card {
            display: grid;
            gap: 18px;
            margin-bottom: 16px;
        }

        .code-inputs {
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 12px;
        }

        .code-digit {
            width: 100%;
            padding: 18px 14px;
            border-radius: 16px;
            border: 1px solid #d8d3cc;
            background: #fbf8f4;
            color: #312421;
            font-size: 1.4rem;
            text-align: center;
        }

        .verify-submit-btn {
            border: none;
            border-radius: 18px;
            padding: 16px 24px;
            background: linear-gradient(90deg, #E0B250, #B07A2B);
            color: #faf7f0;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
        }

        .verify-submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 26px rgba(176, 122, 43, 0.18);
        }

        .verify-resend-section {
            margin-top: 12px;
            color: #6e5548;
            text-align: center;
            line-height: 1.7;
        }

        .verify-resend-link {
            background: none;
            border: none;
            padding: 0;
            color: #a84a38;
            cursor: pointer;
            font-weight: 700;
            text-decoration: underline;
        }

        .alert.verify-modal-alert {
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .manage-account-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 24px;
        }

        .manage-card {
            background: white;
            border-radius: 28px;
            padding: 28px;
            border: 1px solid rgba(133, 103, 88, 0.16);
            box-shadow: 0 18px 40px rgba(66, 38, 25, 0.08);
        }

        .manage-card-header {
            background: linear-gradient(135deg, #5C1F23 0%, #8B2E2F 55%, #A84A38 100%);
            color: #FAF7F0;
            padding: 20px 24px;
            margin: -28px -28px 24px;
            border-radius: 28px 28px 0 0;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .manage-card h3 {
            margin: 0;
            font-size: 1.35rem;
        }

        .search-results-list {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e0d5cc;
            border-radius: 16px;
            margin-top: 12px;
        }

        .search-result-item {
            padding: 14px 16px;
            border-bottom: 1px solid #e0d5cc;
            cursor: pointer;
            transition: background 0.22s ease;
        }

        .search-result-item:hover {
            background: #f5f1ed;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item strong {
            color: #312421;
            font-weight: 700;
        }

        .search-result-item small {
            display: block;
            color: #8a7167;
            font-size: 0.9rem;
            margin-top: 4px;
        }

        .account-info-display {
            background: #fbf8f4;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .account-info-display p {
            margin: 8px 0;
            color: #5c372c;
            font-size: 0.95rem;
        }

        .manage-actions-grid {
            display: grid;
            gap: 16px;
        }

        .action-card {
            background: #fff8f3;
            border: 1px solid rgba(214, 168, 74, 0.24);
            border-radius: 16px;
            padding: 18px;
            transition: background 0.22s ease, transform 0.22s ease;
        }

        .action-card:hover {
            background: #fff3e7;
            transform: translateY(-1px);
        }

        .action-card h4 {
            margin: 0 0 8px;
            color: #4c2f28;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-card p {
            margin: 0 0 12px;
            color: #6e5548;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .btn-action {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 12px;
            background: linear-gradient(90deg, #E0B250, #B07A2B);
            color: #faf7f0;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 20px rgba(176, 122, 43, 0.16);
        }

        @media (max-width: 640px) {
            .verification-modal {
                padding: 22px;
            }

            .verify-title {
                font-size: 1.75rem;
            }

            .code-inputs {
                gap: 10px;
            }
        }

        @media (max-width: 980px) {
            .create-account-grid {
                grid-template-columns: 1fr;
            }
            .student-name-grid,
            .course-selection-grid,
            .password-grid {
                grid-template-columns: 1fr;
            }
            .manage-account-container {
                grid-template-columns: 1fr;
            }
        }

        .delete-user-btn,
        .student-remove-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            min-height: 40px;
        }

        .delete-user-btn:hover,
        .student-remove-btn:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
        }

        .delete-user-btn:active,
        .student-remove-btn:active {
            transform: translateY(0);
        }

        #tab-student-data .student-row-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            min-width: 180px;
        }

        #tab-student-data .student-row-actions button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-width: 102px;
            min-height: 38px;
            padding: 9px 12px;
            border: 1px solid transparent;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color .2s ease, border-color .2s ease, transform .2s ease, box-shadow .2s ease;
        }

        #tab-student-data .student-row-actions button i {
            font-size: 12px;
        }

        #tab-student-data .student-row-actions .archive-user-btn {
            background: #fff7e6;
            border-color: #e8c875;
            color: #865b00;
        }

        #tab-student-data .student-row-actions .edit-student-teacher-btn {
            background: #eef6ff;
            border-color: #a8c9ed;
            color: #245b91;
        }

        #tab-student-data .student-row-actions .edit-student-teacher-btn:hover {
            background: #dcecff;
            border-color: #78aee0;
            box-shadow: 0 4px 10px rgba(36, 91, 145, .14);
            transform: translateY(-1px);
        }

        #tab-student-data .student-row-actions .archive-user-btn:hover {
            background: #ffefc2;
            border-color: #d4aa45;
            box-shadow: 0 4px 10px rgba(166, 106, 0, .14);
            transform: translateY(-1px);
        }

        #tab-student-data .student-row-actions .student-remove-btn {
            min-width: 94px;
            background: #fff1ed;
            border-color: #efb0a5;
            color: #b83226;
        }

        #tab-student-data .student-row-actions .student-remove-btn:hover {
            background: #ffe1dc;
            border-color: #dc8175;
            box-shadow: 0 4px 10px rgba(184, 50, 38, .14);
            transform: translateY(-1px);
        }

        @media (max-width: 1180px) {
            #tab-student-data .student-row-actions {
                min-width: 0;
            }
        }

        @media (max-width: 768px) {
            #tab-student-data .student-row-actions {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            #tab-student-data .student-row-actions button {
                width: 100%;
            }
        }

        #userActionConfirmModal .modal-content {
            width: min(460px, 100%);
            max-width: calc(100% - 32px);
            padding: 0;
            overflow: hidden;
            border-radius: 20px;
        }

        #editStudentTeacherModal .edit-user-modal-content {
            width: min(520px, 100%);
            max-width: calc(100% - 32px);
        }

        #editStudentTeacherModal .modal-body {
            display: grid;
            gap: 16px;
        }

        .user-action-confirm-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 24px 24px 18px;
            border-bottom: 1px solid #f1e6df;
        }

        .user-action-confirm-icon {
            display: grid;
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            place-items: center;
            border-radius: 14px;
            background: #fff1ed;
            color: #c2412d;
            font-size: 21px;
        }

        .user-action-confirm-header h3 {
            margin: 0;
            color: #3b2924;
            font-size: 20px;
        }

        .user-action-confirm-body {
            padding: 22px 24px 8px;
            color: #6b554d;
            line-height: 1.55;
        }

        .user-action-confirm-body strong {
            color: #3b2924;
        }

        .user-action-confirm-warning {
            display: flex;
            gap: 9px;
            margin-top: 16px;
            padding: 12px 14px;
            border-left: 3px solid #dc3545;
            border-radius: 8px;
            background: #fff7f5;
            color: #9f2d20;
            font-size: 13px;
        }

        .user-action-confirm-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 18px 24px 24px;
        }

        .user-action-confirm-footer button {
            min-height: 42px;
            padding: 10px 18px;
            border: 0;
            border-radius: 9px;
            font-weight: 700;
            cursor: pointer;
        }

        .user-action-cancel {
            background: #f5efeb;
            color: #5c443b;
        }

        .user-action-confirm {
            background: #b83226;
            color: #fff;
        }

        .user-action-confirm:hover {
            background: #98271e;
        }

        @media (max-width: 480px) {
            .user-action-confirm-footer {
                flex-direction: column-reverse;
            }

            .user-action-confirm-footer button {
                width: 100%;
            }
        }
        #tab-student-data .student-data-header {
            display: grid;
            grid-template-columns: minmax(220px, 0.8fr) minmax(0, 1.8fr);
            gap: 24px;
            align-items: start;
        }

        #tab-student-data .student-data-actions {
            display: grid;
            grid-template-columns: minmax(130px, 1fr) minmax(220px, 1.7fr) minmax(130px, 1fr) auto minmax(210px, 1.5fr);
            gap: 10px;
            align-items: center;
            min-width: 0;
        }

        #tab-student-data .student-data-actions .filter-container,
        #tab-student-data .student-data-actions .search-container {
            min-width: 0;
            width: 100%;
        }

        #tab-student-data .student-data-actions .filter-select,
        #tab-student-data .student-data-actions .search-input {
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }

        #tab-student-data .student-data-actions .archive-user-trigger {
            white-space: nowrap;
        }

        #tab-student-data .archive-filter-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 40px;
            padding: 10px 15px;
            border: 1px solid #d4aa45;
            border-radius: 9px;
            background: #fff7e6;
            color: #865b00;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color .2s ease, border-color .2s ease, transform .2s ease, box-shadow .2s ease;
        }

        #tab-student-data .archive-filter-button:hover {
            background: #ffefc2;
            border-color: #b8871d;
            box-shadow: 0 4px 10px rgba(166, 106, 0, .14);
            transform: translateY(-1px);
        }

        #tab-student-data .archive-filter-button:active {
            transform: translateY(0);
        }

        @media (max-width: 1180px) {
            #tab-student-data .student-data-header {
                grid-template-columns: 1fr;
            }

            #tab-student-data .student-data-actions {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            #tab-student-data .student-data-actions .search-container {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            #tab-student-data .student-data-actions {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            #tab-student-data .student-data-actions .search-container {
                grid-column: auto;
            }

            #tab-student-data .student-data-actions .archive-user-trigger {
                width: 100%;
            }
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
                <a href="admin_users.php" class="active" data-tooltip="Users">
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
                            <p class="eyebrow">User Management Center</p>
                            <h1>Admin User Portal</h1>
                            <p class="dashboard-subtitle">Comprehensive user management system for PASS College administrators. Monitor accounts, review registrations, and manage student access.</p>
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

                    <div class="admin-user-summary">
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-value"><?= htmlspecialchars($totalUsers) ?></span>
                                <span class="metric-label">Total Users</span>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fa-solid fa-chalkboard-teacher"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-value"><?= htmlspecialchars($totalTeachers) ?></span>
                                <span class="metric-label">Teachers</span>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-value"><?= htmlspecialchars($totalAdmins) ?></span>
                                <span class="metric-label">Admins</span>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon">
                                <i class="fa-solid fa-clock"></i>
                            </div>
                            <div class="metric-content">
                                <span class="metric-value"><?= count($pendingRegistrations) ?></span>
                                <span class="metric-label">Pending Approval</span>
                            </div>
                        </div>
                    </div>
                    <section class="content-panel card-panel">
                        <div class="panel-header">
                            <div class="panel-header-content">
                                <div>
                                    <p class="eyebrow">Management Tools</p>
                                    <h2>User Administration Dashboard</h2>
                                    <p>Access comprehensive user management features including account approval, data review, and system monitoring.</p>
                                </div>
                                <div class="panel-actions">
                                    <button type="button" class="btn-secondary" onclick="location.reload()">
                                        <i class="fa-solid fa-refresh"></i>
                                        Refresh Data
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?= htmlspecialchars($messageType) ?>" style="margin-bottom: 24px;">
                                <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <div class="admin-tabs-container">
                            <div class="admin-tabs">
                                <button type="button" class="admin-tab-button <?= $activeTab === 'tab-directory' ? 'active' : '' ?>" data-tab="tab-directory">
                                    <i class="fa-solid fa-address-book"></i>
                                    <span>User Directory</span>
                                    <span class="tab-count">(<?= count($users) ?>)</span>
                                </button>
                                <button type="button" class="admin-tab-button <?= $activeTab === 'tab-student-data' ? 'active' : '' ?>" data-tab="tab-student-data">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                    <span>Student & Teacher Data</span>
                                    <span class="tab-count">(<?= count($studentList) ?>)</span>
                                </button>
                                <button type="button" class="admin-tab-button <?= $activeTab === 'tab-pending-approvals' ? 'active' : '' ?>" data-tab="tab-pending-approvals">
                                    <i class="fa-solid fa-clock"></i>
                                    <span>Pending Approvals</span>
                                    <span class="tab-count">(<?= count($pendingRegistrations) ?>)</span>
                                </button>
                                <?php if ($isSuperAdmin): ?>
                                    <button type="button" class="admin-tab-button <?= $activeTab === 'tab-create-account' ? 'active' : '' ?>" data-tab="tab-create-account">
                                        <i class="fa-solid fa-user-plus"></i>
                                        <span>Create Account</span>
                                    </button>
                                    <button type="button" class="admin-tab-button <?= $activeTab === 'tab-manage-account' ? 'active' : '' ?>" data-tab="tab-manage-account">
                                        <i class="fa-solid fa-user-gear"></i>
                                        <span>Manage Account</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="admin-tab-panel" id="tab-directory" style="display: <?= $activeTab === 'tab-directory' ? 'block' : 'none' ?>;">
                            <div class="tab-content-header">
                                <div class="tab-description">
                                    <h3>User Directory</h3>
                                    <p>Complete list of all registered users in the system with their roles and service assignments.</p>
                                </div>
                                <div class="tab-actions">
                                    <div class="search-container">
                                        <i class="fa-solid fa-search"></i>
                                        <input type="text" id="user-search" placeholder="Search users..." class="search-input">
                                    </div>
                                </div>
                            </div>
                            <div class="user-table-container">
                                <table class="user-table">
                                    <thead>
                                        <tr>
                                            <th><i class="fa-solid fa-user"></i> Name</th>
                                            <th><i class="fa-solid fa-envelope"></i> Email</th>
                                            <th><i class="fa-solid fa-user-tag"></i> Role</th>
                                            <th><i class="fa-solid fa-cogs"></i> Service Head</th>
                                            <th><i class="fa-solid fa-calendar"></i> Joined</th>
                                            <th><i class="fa-solid fa-tasks"></i> Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $userRow): ?>
                                            <tr class="user-row" data-user-id="<?= htmlspecialchars($userRow['id']) ?>" data-role="<?= htmlspecialchars($userRow['role']) ?>" data-student-id="<?= htmlspecialchars($userRow['student_id'] ?? '') ?>" data-employee-id="<?= htmlspecialchars($userRow['employee_id'] ?? '') ?>">
                                                <td data-label="Name">
                                                    <div class="user-info">
                                                        <div class="user-avatar">
                                                            <i class="fa-solid fa-<?= $userRow['role'] === 'admin' ? 'user-shield' : ($userRow['role'] === 'teacher' ? 'chalkboard-teacher' : 'user') ?>"></i>
                                                        </div>
                                                        <span class="user-name"><?= htmlspecialchars($userRow['full_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td data-label="Email">
                                                    <a href="mailto:<?= htmlspecialchars($userRow['email']) ?>" class="email-link">
                                                        <?= htmlspecialchars($userRow['email']) ?>
                                                    </a>
                                                </td>
                                                <td data-label="Role">
                                                    <span class="role-badge role-<?= htmlspecialchars($userRow['role']) ?>">
                                                        <?= htmlspecialchars(ucfirst($userRow['role'])) ?>
                                                    </span>
                                                </td>
                                                <td data-label="Service Head">
                                                    <?php if ($userRow['head_service'] !== 'none'): ?>
                                                        <span class="service-badge">
                                                            <?= htmlspecialchars(str_replace('_', ' ', ucwords($userRow['head_service']))) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="no-service">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Joined">
                                                    <span class="join-date">
                                                        <?= htmlspecialchars(date('M j, Y', strtotime($userRow['created_at'] ?? 'now'))) ?>
                                                    </span>
                                                </td>
                                                <td data-label="Action">
                                                    <button type="button" class="delete-user-btn" data-user-id="<?= htmlspecialchars($userRow['id']) ?>" data-user-name="<?= htmlspecialchars($userRow['full_name']) ?>" data-user-email="<?= htmlspecialchars($userRow['email']) ?>" title="Delete this user account">
                                                        <i class="fa-solid fa-trash-alt"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="admin-tab-panel" id="tab-student-data" style="display: <?= $activeTab === 'tab-student-data' ? 'block' : 'none' ?>;">
                            <div class="tab-content-header student-data-header">
                                <div class="tab-description">
                                    <h3>Student & Teacher Data</h3>
                                    <p>Comprehensive overview of all enrolled students and teachers with their academic information and contact details.</p>
                                </div>
                                <div class="tab-actions student-data-actions">
                                    <div class="filter-container">
                                        <select id="role-filter" class="filter-select">
                                            <option value="">All Roles</option>
                                            <option value="student">Students Only</option>
                                            <option value="teacher">Teachers Only</option>
                                        </select>
                                    </div>
                                    <div class="filter-container">
                                        <select id="course-filter" class="filter-select">
                                            <option value="">All Courses</option>
                                            <?php foreach ($allCourseFilterOptions as $course): ?>
                                                <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-container">
                                        <select id="year-filter" class="filter-select">
                                            <option value="">All Years</option>
                                            <?php foreach ($yearOptions as $year): ?>
                                                <option value="<?= htmlspecialchars($year) ?>">Year <?= htmlspecialchars($year) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="filter-container">
                                        <button type="button" class="archive-filter-button archive-user-trigger" onclick="openArchiveUserModal()">
                                            <i class="fa-solid fa-box-archive"></i> Archive
                                        </button>
                                    </div>
                                    <div class="search-container">
                                        <i class="fa-solid fa-search"></i>
                                        <input type="text" id="student-search" placeholder="Search students/teachers..." class="search-input">
                                    </div>
                                </div>
                            </div>
                            <div id="archiveUserModal" class="modal" aria-hidden="true">
                                <div class="modal-content" style="max-width: 560px;">
                                    <div class="modal-header">
                                        <h3>Archive Student & Teacher Records</h3>
                                        <button class="modal-close" type="button" onclick="closeArchiveUserModal()">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Select the role, course, and year to archive all matching user records in the Student & Teacher Data list.</p>
                                        <div class="form-grid">
                                            <div class="form-group">
                                                <label for="archive-user-role">Role</label>
                                                <select id="archive-user-role">
                                                    <option value="">All Roles</option>
                                                    <option value="student">Students</option>
                                                    <option value="teacher">Teachers</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="archive-user-course">Course / Department</label>
                                                <select id="archive-user-course">
                                                    <option value="">All Courses</option>
                                                    <?php foreach ($allCourseFilterOptions as $course): ?>
                                                        <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="archive-user-year">Year</label>
                                                <select id="archive-user-year">
                                                    <option value="">All Years</option>
                                                    <?php foreach ($yearOptions as $year): ?>
                                                        <option value="<?= htmlspecialchars($year) ?>">Year <?= htmlspecialchars($year) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-actions form-actions">
                                        <button type="button" class="secondary-button" onclick="closeArchiveUserModal()">Cancel</button>
                                        <button type="button" class="archive-filter-button" onclick="archiveSelectedUsers()"><i class="fa-solid fa-box-archive"></i> Archive</button>
                                    </div>
                                </div>
                            </div>
                            <div id="userActionConfirmModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="userActionConfirmTitle">
                                <div class="modal-content">
                                    <div class="user-action-confirm-header">
                                        <div class="user-action-confirm-icon" id="userActionConfirmIcon">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                        </div>
                                        <h3 id="userActionConfirmTitle">Confirm action</h3>
                                    </div>
                                    <div class="user-action-confirm-body">
                                        <div id="userActionConfirmMessage"></div>
                                        <div class="user-action-confirm-warning">
                                            <i class="fa-solid fa-circle-exclamation"></i>
                                            <span id="userActionConfirmWarning">This action cannot be undone.</span>
                                        </div>
                                    </div>
                                    <div class="user-action-confirm-footer">
                                        <button type="button" class="user-action-cancel" id="userActionConfirmCancel">Cancel</button>
                                        <button type="button" class="user-action-confirm" id="userActionConfirmSubmit">Confirm</button>
                                    </div>
                                </div>
                            </div>
                            <div id="editStudentTeacherModal" class="modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="editStudentTeacherTitle">
                                <div class="modal-content edit-user-modal-content">
                                    <div class="modal-header">
                                        <h3 id="editStudentTeacherTitle">Edit Student / Teacher Data</h3>
                                        <button class="modal-close" type="button" id="closeEditStudentTeacherModal" aria-label="Close">&times;</button>
                                    </div>
                                    <form method="post" id="editStudentTeacherForm">
                                        <input type="hidden" name="action" value="edit_student_teacher">
                                        <input type="hidden" name="user_id" id="editUserId">
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label for="editUserName">Name</label>
                                                <input type="text" name="full_name" id="editUserName" required>
                                            </div>
                                            <div class="form-group">
                                                <label for="editUserCourse">Course / Department</label>
                                                <select name="course_department" id="editUserCourse" required>
                                                    <option value="">Select course / department</option>
                                                    <?php foreach ($allCourseFilterOptions as $course): ?>
                                                        <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group" id="editUserYearGroup">
                                                <label for="editUserYear">Year Level</label>
                                                <select name="year_level" id="editUserYear" required>
                                                    <option value="">Select year</option>
                                                    <?php foreach ($yearOptions as $year): ?>
                                                        <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label for="editUserIdNumber">ID Number</label>
                                                <input type="text" name="id_number" id="editUserIdNumber" required>
                                            </div>
                                        </div>
                                        <div class="modal-actions form-actions">
                                            <button type="button" class="secondary-button" id="cancelEditStudentTeacher">Cancel</button>
                                            <button type="submit" class="archive-filter-button"><i class="fa-solid fa-floppy-disk"></i> Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="user-table-container">
                                <table class="user-table">
                                    <thead>
                                        <tr>
                                            <th><i class="fa-solid fa-user"></i> Name</th>
                                            <th><i class="fa-solid fa-user-tag"></i> Role</th>
                                            <th><i class="fa-solid fa-graduation-cap"></i> Course / Department</th>
                                            <th><i class="fa-solid fa-id-card"></i> ID Number</th>
                                            <th><i class="fa-solid fa-envelope"></i> Email</th>
                                            <th><i class="fa-solid fa-calendar"></i> Enrolled</th>
                                            <th><i class="fa-solid fa-tasks"></i> Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($studentList as $studentRow): ?>
                                            <?php
                                                $courseValue = '';
                                                $yearValue = '';
                                                if ($studentRow['role'] === 'teacher') {
                                                    $courseValue = trim((string) ($studentRow['course_year'] ?? ''));
                                                } elseif (!empty($studentRow['course_year'])) {
                                                    $parts = preg_split('/\s+/', trim($studentRow['course_year']));
                                                    $yearValue = end($parts);
                                                    array_pop($parts);
                                                    $courseValue = implode(' ', $parts);
                                                }
                                            ?>
                                            <tr class="user-row" data-user-id="<?= htmlspecialchars($studentRow['id']) ?>" data-role="<?= htmlspecialchars($studentRow['role']) ?>" data-student-id="<?= htmlspecialchars($studentRow['student_id'] ?? '') ?>" data-employee-id="<?= htmlspecialchars($studentRow['employee_id'] ?? '') ?>" data-course="<?= htmlspecialchars($courseValue) ?>" data-year="<?= htmlspecialchars($yearValue) ?>">
                                                <td data-label="Name">
                                                    <div class="user-info">
                                                        <div class="user-avatar">
                                                            <i class="fa-solid fa-<?= $studentRow['role'] === 'student' ? 'user-graduate' : 'chalkboard-teacher' ?>"></i>
                                                        </div>
                                                        <span class="user-name"><?= htmlspecialchars($studentRow['full_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td data-label="Role">
                                                    <span class="role-badge role-<?= htmlspecialchars($studentRow['role']) ?>">
                                                        <?= htmlspecialchars(ucfirst($studentRow['role'])) ?>
                                                    </span>
                                                </td>
                                                <td data-label="Course / Department">
                                                    <?php if ($studentRow['course_year']): ?>
                                                        <span class="course-info">
                                                            <?= htmlspecialchars($studentRow['course_year']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="no-course">Not assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="ID Number">
                                                    <span class="id-number">
                                                        <?= htmlspecialchars($studentRow['student_id'] ?: $studentRow['employee_id'] ?: '—') ?>
                                                    </span>
                                                </td>
                                                <td data-label="Email">
                                                    <a href="mailto:<?= htmlspecialchars($studentRow['email']) ?>" class="email-link">
                                                        <?= htmlspecialchars($studentRow['email']) ?>
                                                    </a>
                                                </td>
                                                <td data-label="Enrolled">
                                                    <span class="join-date">
                                                        <?= htmlspecialchars(date('M j, Y', strtotime($studentRow['created_at'] ?? 'now'))) ?>
                                                    </span>
                                                </td>
                                                <td data-label="Action">
                                                    <div class="student-row-actions">
                                                        <button type="button" class="edit-student-teacher-btn" data-user-id="<?= (int) $studentRow['id'] ?>" data-user-role="<?= htmlspecialchars($studentRow['role']) ?>" data-user-name="<?= htmlspecialchars($studentRow['full_name']) ?>" data-user-course="<?= htmlspecialchars($courseValue) ?>" data-user-year="<?= htmlspecialchars($yearValue) ?>" data-user-id-number="<?= htmlspecialchars($studentRow['student_id'] ?: $studentRow['employee_id'] ?: '') ?>" title="Edit this user information">
                                                            <i class="fa-solid fa-pen"></i> Edit
                                                        </button>
                                                        <button type="button" class="archive-user-btn" data-user-id="<?= (int) $studentRow['id'] ?>" data-user-name="<?= htmlspecialchars($studentRow['full_name']) ?>">
                                                            <i class="fa-solid fa-box-archive"></i> Archive
                                                        </button>
                                                        <button type="button" class="delete-student-teacher-btn student-remove-btn" data-user-id="<?= (int) $studentRow['id'] ?>" data-user-name="<?= htmlspecialchars($studentRow['full_name']) ?>" data-user-email="<?= htmlspecialchars($studentRow['email']) ?>" title="Permanently remove this account and its data">
                                                            <i class="fa-solid fa-trash-can"></i> Remove
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="admin-tab-panel" id="tab-pending-approvals" style="display: <?= $activeTab === 'tab-pending-approvals' ? 'block' : 'none' ?>;">
                            <div class="tab-content-header">
                                <div class="tab-description">
                                    <h3>Pending Account Approvals</h3>
                                    <p>Review student registration applications and supporting documents before granting account access.</p>
                                </div>
                                <div class="tab-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?= count($pendingRegistrations) ?></span>
                                        <span class="stat-label">Pending Review</span>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($pendingRegistrations)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="fa-solid fa-check-circle"></i>
                                    </div>
                                    <h3>All Caught Up!</h3>
                                    <p>No pending student accounts are waiting for approval at this time.</p>
                                    <button type="button" class="btn-secondary" onclick="location.reload()">
                                        <i class="fa-solid fa-refresh"></i>
                                        Check for Updates
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="pending-approvals-grid">
                                    <?php foreach ($pendingRegistrations as $pending): ?>
                                        <div class="approval-card">
                                            <div class="approval-card-header">
                                                <div class="applicant-info">
                                                    <div class="applicant-avatar">
                                                        <i class="fa-solid fa-user-graduate"></i>
                                                    </div>
                                                    <div class="applicant-details">
                                                        <h4 class="applicant-name"><?= htmlspecialchars($pending['full_name']) ?></h4>
                                                        <p class="applicant-email">
                                                            <i class="fa-solid fa-envelope"></i>
                                                            <?= htmlspecialchars($pending['email']) ?>
                                                        </p>
                                                        <p class="applicant-id">
                                                            <i class="fa-solid fa-id-card"></i>
                                                            Student ID: <?= htmlspecialchars($pending['student_id']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="approval-status">
                                                    <span class="status-badge status-pending">
                                                        <i class="fa-solid fa-clock"></i>
                                                        <?= htmlspecialchars(str_replace('_', ' ', ucwords($pending['status']))) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="approval-card-body">
                                                <div class="application-details">
                                                    <div class="detail-row">
                                                        <span class="detail-label">Course & Year:</span>
                                                        <span class="detail-value">
                                                            <?= htmlspecialchars($pending['course_year'] ?: 'Not specified') ?>
                                                        </span>
                                                    </div>
                                                    <div class="detail-row">
                                                        <span class="detail-label">Application Date:</span>
                                                        <span class="detail-value">
                                                            <?= htmlspecialchars(date('F j, Y \a\t g:i A', strtotime($pending['created_at']))) ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div class="documents-section">
                                                    <h5>Supporting Documents</h5>
                                                    <?php
                                                        $docs = [];
                                                        if (!empty($pending['documents'])) {
                                                            $docs = json_decode($pending['documents'], true);
                                                        }
                                                    ?>
                                                    <?php if (empty($docs)): ?>
                                                        <div class="no-documents">
                                                            <i class="fa-solid fa-file-circle-xmark"></i>
                                                            <span>No documents were attached to this application.</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="documents-grid">
                                                            <?php foreach ($docs as $index => $docPath): ?>
                                                                <div class="document-item">
                                                                    <a href="../<?= htmlspecialchars($docPath) ?>" target="_blank" class="document-link">
                                                                        <img src="../<?= htmlspecialchars($docPath) ?>" alt="Document <?= $index + 1 ?>" class="document-preview">
                                                                        <div class="document-overlay">
                                                                            <i class="fa-solid fa-expand"></i>
                                                                            <span>View Full Size</span>
                                                                        </div>
                                                                    </a>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="approval-card-footer">
                                                <div class="approval-actions">
                                                    <form method="post" action="" class="approval-form">
                                                        <input type="hidden" name="pending_id" value="<?= (int)$pending['id'] ?>">
                                                        <button type="submit" name="action" value="approve_pending" class="btn-approve">
                                                            <i class="fa-solid fa-check"></i>
                                                            Approve Account
                                                        </button>
                                                        <button type="submit" name="action" value="reject_pending" class="btn-reject">
                                                            <i class="fa-solid fa-xmark"></i>
                                                            Reject Application
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($isSuperAdmin): ?>
                            <div class="admin-tab-panel" id="tab-create-account" style="display: <?= $activeTab === 'tab-create-account' ? 'block' : 'none' ?>;">
                                <div class="tab-content-header">
                                    <div class="tab-description">
                                        <h3>Create Account</h3>
                                        <p>Register a new user with role assignment and teacher office selection. This tab is restricted to Super Admin only.</p>
                                    </div>
                                </div>

                                <form method="post" action="" class="create-account-form">
                                    <input type="hidden" name="action" value="create_account">
                                    <p class="form-section-note">Select the account category and complete the fields below. Student accounts collect learner details and teacher accounts can include optional head office assignment.</p>
                                    <div class="create-account-grid">
                                        <div class="create-card">
                                            <div class="create-card-header">
                                                <h3>Account Information</h3>
                                            </div>
                                            <div class="form-group">
                                                <label>Account Category</label>
                                                <div class="radio-group">
                                                    <label class="radio-option">
                                                        <input type="radio" name="account_category" value="student" checked>
                                                        <span>Student</span>
                                                    </label>
                                                    <label class="radio-option">
                                                        <input type="radio" name="account_category" value="teacher">
                                                        <span>Teacher / Service Head</span>
                                                    </label>
                                                    <label class="radio-option">
                                                        <input type="radio" name="account_category" value="subadmin">
                                                        <span>Sub-Admin</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="email">Email Address</label>
                                                <input id="email" name="email" type="email" required>
                                            </div>
                                            <div id="student-fields">
                                                <div class="form-grid student-name-grid">
                                                    <div class="form-group">
                                                        <label for="student_first_name">First Name</label>
                                                        <input id="student_first_name" name="student_first_name" type="text" placeholder="Enter first name">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="student_middle_name">Middle Name</label>
                                                        <input id="student_middle_name" name="student_middle_name" type="text" placeholder="Enter middle name (optional)">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="student_last_name">Last Name</label>
                                                        <input id="student_last_name" name="student_last_name" type="text" placeholder="Enter last name">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="student_id">Student ID Number</label>
                                                    <input id="student_id" name="student_id" type="text" placeholder="22-12345" pattern="[0-9]{2}-[0-9]{5}" maxlength="8" inputmode="numeric" autocomplete="off" title="Format: 22-12345">
                                                </div>
                                                <div class="form-grid course-selection-grid">
                                                    <div class="form-group">
                                                        <label for="student_course">Course</label>
                                                        <select id="student_course" name="student_course">
                                                            <option value="">Select course</option>
                                                            <?php foreach ($courseOptions as $course): ?>
                                                                <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="student_ladderize_course">Ladderize Course</label>
                                                        <select id="student_ladderize_course" name="student_ladderize_course">
                                                            <option value="">Select ladderize course</option>
                                                            <?php foreach ($ladderizeOptions as $course): ?>
                                                                <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="student_year">Year Level</label>
                                                    <select id="student_year" name="student_year">
                                                        <option value="">Select year</option>
                                                        <?php foreach ($yearOptions as $year): ?>
                                                            <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="field-note">Choose either Course or Ladderize Course, then select the student year level.</div>
                                            </div>
                                            <div id="teacher-fields" class="hidden-field">
                                                <div class="form-group">
                                                    <label for="teacher_full_name">Full Name</label>
                                                    <input id="teacher_full_name" name="teacher_full_name" type="text" placeholder="Enter full name">
                                                </div>
                                                <div class="form-group">
                                                    <label for="employee_id">Employee ID</label>
                                                    <input id="employee_id" name="employee_id" type="text" placeholder="e.g. TCH-045">
                                                </div>
                                                <div class="form-group">
                                                    <label for="teacher_course">Department</label>
                                                    <select id="teacher_course" name="teacher_course">
                                                        <option value="">Select department</option>
                                                        <?php foreach ($courseOptions as $course): ?>
                                                            <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group checkbox-group">
                                                    <label class="checkbox-option">
                                                        <input type="checkbox" id="is_head" name="is_head">
                                                        Department Head
                                                    </label>
                                                </div>
                                                <div id="head-office-field" class="form-group hidden-field">
                                                    <label for="designated_office">Assigned Office</label>
                                                    <select id="designated_office" name="designated_office">
                                                        <option value="librarian">Librarian</option>
                                                        <option value="guidance">Guidance Counselor</option>
                                                        <option value="ssc_scholarship">SSC & Scholarship Head</option>
                                                        <option value="nurse">School Nurse</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div id="subadmin-fields" class="hidden-field">
                                                <div class="form-group">
                                                    <label for="subadmin_full_name">Full Name</label>
                                                    <input id="subadmin_full_name" name="subadmin_full_name" type="text" placeholder="Enter full name">
                                                </div>
                                            </div>
                                            <div class="form-grid password-grid">
                                                <div class="form-group">
                                                    <label for="password">Initial Password</label>
                                                    <input id="password" name="password" type="password" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="confirm_password">Confirm Password</label>
                                                    <input id="confirm_password" name="confirm_password" type="password" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="create-card">
                                            <div class="create-card-header">
                                                <h3>Role & Permissions Assignment</h3>
                                            </div>
                                            <div class="form-group">
                                                <label>Selected Role</label>
                                                <div id="selected-role-summary" class="form-note">Student</div>
                                            </div>
                                            <div class="form-group">
                                                <label>Assigned Service</label>
                                                <div id="selected-office-summary" class="form-note">Learner access only.</div>
                                            </div>
                                            <div class="form-group">
                                                <label>Account Scope</label>
                                                <div class="form-note">Student accounts receive learner access rights. Teacher/service heads receive office administration privileges. Sub-admin accounts receive elevated admin access.</div>
                                            </div>
                                            <div class="register-action">
                                                <button type="submit" class="btn-register">
                                                    <i class="fa-solid fa-user-plus"></i>
                                                    Register User
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                        <?php if ($isSuperAdmin): ?>
                            <div class="admin-tab-panel" id="tab-manage-account" style="display: <?= $activeTab === 'tab-manage-account' ? 'block' : 'none' ?>;">
                                <div class="tab-content-header">
                                    <div class="tab-description">
                                        <h3>Manage Account</h3>
                                        <p>Reset user passwords or change email addresses with OTP verification. This tab is restricted to Super Admin only.</p>
                                    </div>
                                </div>

                                <div class="manage-account-container">
                                    <div class="manage-card">
                                        <div class="manage-card-header">
                                            <h3>Find User Account</h3>
                                        </div>
                                        <div class="form-group">
                                            <label for="manage_search_input">Search by Name, Email, or ID</label>
                                            <input type="text" id="manage_search_input" placeholder="Enter name, email, or student/employee ID..." autocomplete="off">
                                        </div>
                                        <div id="manage_search_results" class="search-results-list"></div>
                                    </div>

                                    <div class="manage-card">
                                        <div class="manage-card-header">
                                            <h3>Account Management</h3>
                                        </div>
                                        <div id="manage_account_details" style="display: none;">
                                            <div class="account-info-display">
                                                <p><strong>Name:</strong> <span id="manage_account_name"></span></p>
                                                <p><strong>Email:</strong> <span id="manage_account_email"></span></p>
                                                <p><strong>Role:</strong> <span id="manage_account_role"></span></p>
                                                <p><strong>User ID:</strong> <span id="manage_account_id"></span></p>
                                            </div>

                                            <div class="manage-actions-grid">
                                                <div class="action-card">
                                                    <h4><i class="fa-solid fa-key"></i> Reset Password</h4>
                                                    <p>Send a reset code to the user's email. They'll need to verify it before changing their password.</p>
                                                    <button type="button" class="btn-action" id="btn_reset_password">
                                                        <i class="fa-solid fa-lock-open"></i>
                                                        Reset Password
                                                    </button>
                                                </div>

                                                <div class="action-card">
                                                    <h4><i class="fa-solid fa-envelope"></i> Change Email</h4>
                                                    <p>Update the user's email address with OTP verification on the new email.</p>
                                                    <button type="button" class="btn-action" id="btn_change_email">
                                                        <i class="fa-solid fa-envelope"></i>
                                                        Change Email
                                                    </button>
                                                </div>
                                            </div>

                                            <input type="hidden" id="manage_selected_user_id" value="">
                                        </div>
                                        <div id="manage_no_selection" style="display: block;">
                                            <p style="color: #6e5548; text-align: center; padding: 20px;">Select a user from the search results to manage their account.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
            </div>
        </main>
    </div>
    <div id="emailVerificationModal" class="modal-overlay<?= $showVerificationModal ? ' visible' : '' ?>" style="display: <?= $showVerificationModal ? 'flex' : 'none' ?>;">
        <div class="verification-modal" role="dialog" aria-modal="true" aria-labelledby="verificationModalTitle">
            <button type="button" class="modal-close-btn" id="closeEmailVerificationModal" aria-label="Close verification dialog">&times;</button>
            <div class="verify-header-section">
                <div class="verify-label-row">
                    <span class="verify-label">VERIFICATION</span>
                    <div class="verify-timer" style="<?= $verificationStage === 'set_password' ? 'display: none;' : '' ?>">
                        <i class="fas fa-clock"></i>
                        <span id="verificationCountdown">10:00</span>
                    </div>
                </div>
                <h1 id="verificationModalTitle" class="verify-title"><?= htmlspecialchars($verificationTitle) ?></h1>
                <p class="verify-description"><?= $verificationDescription ?></p>
            </div>
            <?php if (!empty($message) && $showVerificationModal && $messageType === 'error'): ?>
                <div class="alert error verify-modal-alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($verificationStage === 'enter_code'): ?>
                <div id="emailSentMessage" class="alert success" style="display: none;">
                    <i class="fa-solid fa-check-circle"></i>
                    Verification code sent successfully to <strong><?= htmlspecialchars($verificationData['email']) ?></strong>!
                </div>
            <?php endif; ?>
            <div class="verify-input-card">
                <form method="post" action="" class="verify-form" id="verifyForm">
                    <?php if ($verificationStage === 'enter_code'): ?>
                        <label class="verify-code-label">ENTER CODE</label>
                        <div class="code-inputs" id="codeInputs">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="code-digit" autocomplete="one-time-code" />
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="action" value="<?= htmlspecialchars($verificationAction) ?>">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($verificationData['email']) ?>">
                        <input type="hidden" name="code" id="codeInput">
                        <button type="submit" class="verify-submit-btn">Verify Code</button>
                    <?php elseif ($verificationStage === 'set_password'): ?>
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_new_password">Confirm New Password</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                        </div>
                        <input type="hidden" name="action" value="<?= htmlspecialchars($verificationAction) ?>">
                        <button type="submit" class="verify-submit-btn">Save New Password</button>
                    <?php endif; ?>
                </form>
            </div>
            <?php if ($verificationStage === 'enter_code'): ?>
                <div class="verify-resend-section">
                    <p class="verify-resend-text">Didn't get it? Check spam, or <button type="button" id="resendCodeBtn" class="verify-resend-link">Resend available in 10:00</button>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function openArchiveUserModal() {
            const modal = document.getElementById('archiveUserModal');
            if (!modal) return;

            const roleFilter = document.getElementById('role-filter');
            const courseFilter = document.getElementById('course-filter');
            const yearFilter = document.getElementById('year-filter');
            const roleSelect = document.getElementById('archive-user-role');
            const courseSelect = document.getElementById('archive-user-course');
            const yearSelect = document.getElementById('archive-user-year');

            if (roleSelect) roleSelect.value = roleFilter ? roleFilter.value : '';
            if (courseSelect) courseSelect.value = courseFilter ? courseFilter.value : '';
            if (yearSelect) yearSelect.value = yearFilter ? yearFilter.value : '';

            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeArchiveUserModal() {
            const modal = document.getElementById('archiveUserModal');
            if (!modal) return;
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }

        function archiveSelectedUsers() {
            const role = document.getElementById('archive-user-role')?.value || '';
            const course = document.getElementById('archive-user-course')?.value || '';
            const year = document.getElementById('archive-user-year')?.value || '';

            const params = new URLSearchParams();
            params.append('action', 'archive_users_by_filter');
            params.append('role', role);
            params.append('course', course);
            params.append('year', year);

            fetch('admin_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.success) {
                    closeArchiveUserModal();
                    alert(data.message || 'User records archived successfully.');
                    window.location.reload();
                } else {
                    alert(data && data.message ? data.message : 'Unable to archive user records.');
                }
            })
            .catch(error => {
                console.error('Error archiving users:', error);
                alert('Error archiving selected users.');
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Tab switching functionality
            const tabButtons = document.querySelectorAll('.admin-tab-button');
            const panels = document.querySelectorAll('.admin-tab-panel');

            function selectAdminTab(tabId, button) {
                tabButtons.forEach(btn => btn.classList.remove('active'));
                panels.forEach(panel => panel.style.display = 'none');
                button.classList.add('active');
                document.getElementById(tabId).style.display = 'block';
            }

            function attachAdminTabSwipe(tabBar) {
                if (!tabBar) return;
                let startX = 0;
                let currentX = 0;
                let isTouching = false;

                tabBar.addEventListener('touchstart', function (event) {
                    if (event.touches.length !== 1) return;
                    startX = event.touches[0].clientX;
                    currentX = startX;
                    isTouching = true;
                }, { passive: true });

                tabBar.addEventListener('touchmove', function (event) {
                    if (!isTouching || event.touches.length !== 1) return;
                    currentX = event.touches[0].clientX;
                }, { passive: true });

                tabBar.addEventListener('touchend', function () {
                    if (!isTouching) return;
                    const delta = startX - currentX;
                    const threshold = 60;
                    if (Math.abs(delta) >= threshold) {
                        const buttons = Array.from(tabButtons);
                        const activeIndex = buttons.findIndex(btn => btn.classList.contains('active'));
                        if (activeIndex !== -1) {
                            const nextIndex = delta > 0 ? Math.min(buttons.length - 1, activeIndex + 1) : Math.max(0, activeIndex - 1);
                            if (nextIndex !== activeIndex) {
                                selectAdminTab(buttons[nextIndex].getAttribute('data-tab'), buttons[nextIndex]);
                            }
                        }
                    }
                    isTouching = false;
                }, { passive: true });
            }

            tabButtons.forEach(button => {
                button.addEventListener('click', function () {
                    selectAdminTab(this.getAttribute('data-tab'), this);
                });
            });

            attachAdminTabSwipe(document.querySelector('.admin-tabs'));

            const accountCategoryRadios = document.querySelectorAll('input[name="account_category"]');
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');
            const subadminFields = document.getElementById('subadmin-fields');
            const headOfficeField = document.getElementById('head-office-field');
            const selectedRoleSummary = document.getElementById('selected-role-summary');
            const selectedOfficeSummary = document.getElementById('selected-office-summary');
            const designatedOfficeSelect = document.getElementById('designated_office');
            const isHeadCheckbox = document.getElementById('is_head');
            const studentCourseSelect = document.getElementById('student_course');
            const studentLadderizeSelect = document.getElementById('student_ladderize_course');
            const teacherCourseSelect = document.getElementById('teacher_course');
            const studentYearSelect = document.getElementById('student_year');
            const studentFirstNameInput = document.getElementById('student_first_name');
            const studentLastNameInput = document.getElementById('student_last_name');
            const studentIdInput = document.getElementById('student_id');
            const teacherFullNameInput = document.getElementById('teacher_full_name');
            const subadminFullNameInput = document.getElementById('subadmin_full_name');
            const employeeIdInput = document.getElementById('employee_id');

            function updateHeadOfficeVisibility() {
                if (isHeadCheckbox && isHeadCheckbox.checked) {
                    headOfficeField.classList.remove('hidden-field');
                } else {
                    headOfficeField.classList.add('hidden-field');
                }
            }

            function handleStudentCourseSelection() {
                if (!studentCourseSelect || !studentLadderizeSelect) {
                    return;
                }

                if (studentCourseSelect.value) {
                    studentLadderizeSelect.value = '';
                    studentLadderizeSelect.disabled = true;
                } else {
                    studentLadderizeSelect.disabled = false;
                }

                if (studentLadderizeSelect.value) {
                    studentCourseSelect.value = '';
                    studentCourseSelect.disabled = true;
                } else {
                    studentCourseSelect.disabled = false;
                }
            }

            function updateCreateAccountForm() {
                const selectedCategory = document.querySelector('input[name="account_category"]:checked').value;

                studentFields.classList.add('hidden-field');
                teacherFields.classList.add('hidden-field');
                subadminFields.classList.add('hidden-field');
                headOfficeField.classList.add('hidden-field');

                studentYearSelect.required = false;
                teacherCourseSelect.required = false;
                if (studentFirstNameInput) studentFirstNameInput.required = false;
                if (studentLastNameInput) studentLastNameInput.required = false;
                if (teacherFullNameInput) teacherFullNameInput.required = false;
                if (subadminFullNameInput) subadminFullNameInput.required = false;
                if (employeeIdInput) employeeIdInput.required = false;

                if (selectedCategory === 'student') {
                    studentFields.classList.remove('hidden-field');
                    selectedRoleSummary.textContent = 'Student';
                    selectedOfficeSummary.textContent = 'Learner access only.';
                    studentYearSelect.required = true;
                    if (teacherCourseSelect) teacherCourseSelect.required = false;
                    if (studentFirstNameInput) studentFirstNameInput.required = true;
                    if (studentLastNameInput) studentLastNameInput.required = true;
                    if (studentIdInput) studentIdInput.required = true;
                } else if (selectedCategory === 'teacher') {
                    if (studentIdInput) studentIdInput.required = false;
                    teacherFields.classList.remove('hidden-field');
                    selectedRoleSummary.textContent = 'Teacher / Service Head';
                    selectedOfficeSummary.textContent = isHeadCheckbox && isHeadCheckbox.checked && designatedOfficeSelect ? `Head of ${designatedOfficeSelect.options[designatedOfficeSelect.selectedIndex].text}` : 'Standard teacher access.';
                    if (teacherFullNameInput) teacherFullNameInput.required = true;
                    if (employeeIdInput) employeeIdInput.required = true;
                    updateHeadOfficeVisibility();
                } else {
                    subadminFields.classList.remove('hidden-field');
                    selectedRoleSummary.textContent = 'Sub-Admin';
                    selectedOfficeSummary.textContent = 'Administrative access only.';
                    if (subadminFullNameInput) subadminFullNameInput.required = true;
                }

                handleStudentCourseSelection();
            }

            accountCategoryRadios.forEach(radio => {
                radio.addEventListener('change', updateCreateAccountForm);
            });

            if (designatedOfficeSelect) {
                designatedOfficeSelect.addEventListener('change', updateCreateAccountForm);
            }

            if (isHeadCheckbox) {
                isHeadCheckbox.addEventListener('change', updateCreateAccountForm);
            }

            if (studentCourseSelect) {
                studentCourseSelect.addEventListener('change', updateCreateAccountForm);
            }

            if (studentLadderizeSelect) {
                studentLadderizeSelect.addEventListener('change', updateCreateAccountForm);
            }

            if (studentIdInput) {
                studentIdInput.addEventListener('input', function () {
                    let value = this.value.replace(/[^0-9]/g, '');
                    if (value.length > 2) {
                        value = value.slice(0, 2) + '-' + value.slice(2, 7);
                    }
                    this.value = value;
                });
            }

            updateCreateAccountForm();

            // User directory search functionality
            const userSearch = document.getElementById('user-search');
            if (userSearch) {
                userSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const userRows = document.querySelectorAll('#tab-directory .user-row');

                    userRows.forEach(row => {
                        const name = row.querySelector('.user-name').textContent.toLowerCase();
                        const email = row.querySelector('.email-link').textContent.toLowerCase();
                        const role = row.querySelector('.role-badge').textContent.toLowerCase();

                        if (name.includes(searchTerm) || email.includes(searchTerm) || role.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }

            // Student/Teacher search and filter functionality
            const studentSearch = document.getElementById('student-search');
            const roleFilter = document.getElementById('role-filter');
            const courseFilter = document.getElementById('course-filter');
            const yearFilter = document.getElementById('year-filter');

            function updateYearFilterOptions() {
                if (!courseFilter || !yearFilter) {
                    return;
                }

                const selectedCourse = courseFilter.value;
                const isAssociateCourse = selectedCourse && /associate in /i.test(selectedCourse);
                const allowedYears = isAssociateCourse ? ['1', '2'] : ['1', '2', '3', '4'];
                const currentYear = yearFilter.value;

                yearFilter.innerHTML = '<option value="">All Years</option>' + allowedYears.map(year => {
                    const label = year === '1' ? '1st Year' : year === '2' ? '2nd Year' : year === '3' ? '3rd Year' : '4th Year';
                    return `<option value="${year}">${label}</option>`;
                }).join('');

                if (allowedYears.includes(currentYear)) {
                    yearFilter.value = currentYear;
                } else {
                    yearFilter.value = '';
                }
            }

            function filterStudentRows() {
                const searchTerm = studentSearch ? studentSearch.value.toLowerCase() : '';
                const selectedRole = roleFilter ? roleFilter.value : '';
                const selectedCourse = courseFilter ? courseFilter.value : '';
                const selectedYear = yearFilter ? yearFilter.value : '';
                const studentRows = document.querySelectorAll('#tab-student-data .user-row');

                studentRows.forEach(row => {
                    const name = row.querySelector('.user-name').textContent.toLowerCase();
                    const email = row.querySelector('.email-link').textContent.toLowerCase();
                    const courseText = (row.getAttribute('data-course') || row.querySelector('.course-info, .no-course').textContent).trim();
                    const course = courseText.toLowerCase();
                    const year = (row.getAttribute('data-year') || '').trim();
                    const role = row.getAttribute('data-role');

                    const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm) || course.includes(searchTerm);
                    const matchesRole = !selectedRole || role === selectedRole;
                    const matchesCourse = !selectedCourse || course === selectedCourse.toLowerCase();
                    const matchesYear = !selectedYear || year === selectedYear;

                    if (matchesSearch && matchesRole && matchesCourse && matchesYear) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            if (studentSearch) {
                studentSearch.addEventListener('input', filterStudentRows);
            }

            if (roleFilter) {
                roleFilter.addEventListener('change', filterStudentRows);
            }

            if (courseFilter) {
                courseFilter.addEventListener('change', function() {
                    updateYearFilterOptions();
                    filterStudentRows();
                });
            }

            if (yearFilter) {
                yearFilter.addEventListener('change', filterStudentRows);
            }

            if (courseFilter) {
                updateYearFilterOptions();
            }

            const editStudentTeacherModal = document.getElementById('editStudentTeacherModal');
            const closeEditStudentTeacherModal = document.getElementById('closeEditStudentTeacherModal');
            const cancelEditStudentTeacher = document.getElementById('cancelEditStudentTeacher');
            const editUserId = document.getElementById('editUserId');
            const editUserName = document.getElementById('editUserName');
            const editUserCourse = document.getElementById('editUserCourse');
            const editUserYear = document.getElementById('editUserYear');
            const editUserYearGroup = document.getElementById('editUserYearGroup');
            const editUserIdNumber = document.getElementById('editUserIdNumber');

            function closeEditModal() {
                if (!editStudentTeacherModal) return;
                editStudentTeacherModal.classList.remove('show');
                editStudentTeacherModal.setAttribute('aria-hidden', 'true');
            }

            document.querySelectorAll('.edit-student-teacher-btn').forEach(button => {
                button.addEventListener('click', function () {
                    const isTeacher = (this.dataset.userRole || '') === 'teacher';
                    editUserId.value = this.dataset.userId || '';
                    editUserName.value = this.dataset.userName || '';
                    editUserCourse.value = this.dataset.userCourse || '';
                    editUserYear.value = this.dataset.userYear || '';
                    editUserYearGroup.hidden = isTeacher;
                    editUserYear.required = !isTeacher;
                    editUserIdNumber.value = this.dataset.userIdNumber || '';
                    editStudentTeacherModal.classList.add('show');
                    editStudentTeacherModal.setAttribute('aria-hidden', 'false');
                    editUserName.focus();
                });
            });

            if (closeEditStudentTeacherModal) closeEditStudentTeacherModal.addEventListener('click', closeEditModal);
            if (cancelEditStudentTeacher) cancelEditStudentTeacher.addEventListener('click', closeEditModal);
            if (editStudentTeacherModal) {
                editStudentTeacherModal.addEventListener('click', function (event) {
                    if (event.target === editStudentTeacherModal) closeEditModal();
                });
            }

            const userActionConfirmModal = document.getElementById('userActionConfirmModal');
            const userActionConfirmTitle = document.getElementById('userActionConfirmTitle');
            const userActionConfirmMessage = document.getElementById('userActionConfirmMessage');
            const userActionConfirmWarning = document.getElementById('userActionConfirmWarning');
            const userActionConfirmIcon = document.getElementById('userActionConfirmIcon');
            const userActionConfirmSubmit = document.getElementById('userActionConfirmSubmit');
            const userActionConfirmCancel = document.getElementById('userActionConfirmCancel');
            let pendingUserAction = null;

            function closeUserActionConfirmModal() {
                if (!userActionConfirmModal) {
                    return;
                }
                userActionConfirmModal.classList.remove('show');
                userActionConfirmModal.setAttribute('aria-hidden', 'true');
                pendingUserAction = null;
            }

            function openUserActionConfirmModal(action, userId, userName, userEmail) {
                if (!userActionConfirmModal) {
                    return;
                }

                const isRemove = action === 'delete_student_teacher';
                pendingUserAction = { action, userId };
                userActionConfirmTitle.textContent = isRemove ? 'Remove account?' : 'Archive account?';
                userActionConfirmMessage.replaceChildren();
                userActionConfirmMessage.append(
                    document.createTextNode(isRemove ? 'Remove the account of ' : 'Archive the account of ')
                );
                const accountName = document.createElement('strong');
                accountName.textContent = userName;
                userActionConfirmMessage.append(accountName, document.createElement('br'));
                const accountEmail = document.createElement('span');
                accountEmail.textContent = userEmail;
                userActionConfirmMessage.append(accountEmail, document.createTextNode('?'));
                userActionConfirmWarning.textContent = isRemove
                    ? 'The account and all related system data will be permanently deleted.'
                    : 'The account will be moved to the User Archive and can be restored later.';
                userActionConfirmIcon.innerHTML = isRemove
                    ? '<i class="fa-solid fa-trash-can"></i>'
                    : '<i class="fa-solid fa-box-archive"></i>';
                userActionConfirmIcon.style.background = isRemove ? '#fff1ed' : '#fff8e7';
                userActionConfirmIcon.style.color = isRemove ? '#c2412d' : '#a66a00';
                userActionConfirmSubmit.textContent = isRemove ? 'Remove permanently' : 'Archive';
                userActionConfirmSubmit.style.background = isRemove ? '#b83226' : '#a66a00';
                userActionConfirmModal.classList.add('show');
                userActionConfirmModal.setAttribute('aria-hidden', 'false');
                userActionConfirmSubmit.focus();
            }

            if (userActionConfirmCancel) {
                userActionConfirmCancel.addEventListener('click', closeUserActionConfirmModal);
            }

            if (userActionConfirmModal) {
                userActionConfirmModal.addEventListener('click', function (event) {
                    if (event.target === userActionConfirmModal) {
                        closeUserActionConfirmModal();
                    }
                });
            }

            if (userActionConfirmSubmit) {
                userActionConfirmSubmit.addEventListener('click', function () {
                    if (!pendingUserAction) {
                        return;
                    }

                    const { action, userId } = pendingUserAction;
                    closeUserActionConfirmModal();

                    if (action === 'archive_user') {
                        const params = new URLSearchParams();
                        params.append('action', action);
                        params.append('user_id', userId);
                        fetch('admin_users.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: params.toString()
                        }).then(response => response.json()).then(data => {
                            if (data && data.success) {
                                window.location.reload();
                            } else {
                                alert(data && data.message ? data.message : 'Unable to archive user.');
                            }
                        }).catch(() => alert('Unable to archive this user.'));
                        return;
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href;
                    form.innerHTML = `
                        <input type="hidden" name="action" value="${action}">
                        <input type="hidden" name="user_id" value="${userId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                });
            }

            const archiveButtons = document.querySelectorAll('.archive-user-btn');
            archiveButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name') || 'this user';
                    const userEmail = this.closest('tr')?.querySelector('.email-link')?.textContent.trim() || '';
                    if (!userId) {
                        return;
                    }
                    openUserActionConfirmModal('archive_user', userId, userName, userEmail);
                });
            });

            // Add loading states for form submissions
            const approvalForms = document.querySelectorAll('.approval-form');
            approvalForms.forEach(form => {
                // Track which button was clicked
                let clickedButton = null;
                
                const buttons = form.querySelectorAll('button[type="submit"]');
                buttons.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        clickedButton = this;
                    });
                });
                
                form.addEventListener('submit', function(e) {
                    // Add a hidden input with the action from the clicked button
                    if (clickedButton && clickedButton.name && clickedButton.value) {
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = clickedButton.name;
                        actionInput.value = clickedButton.value;
                        this.appendChild(actionInput);
                    }
                    
                    const buttons = this.querySelectorAll('button');
                    buttons.forEach(btn => {
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
                    });
                });
            });

            // Add smooth scrolling for better UX
            const verificationModal = document.getElementById('emailVerificationModal');
            const closeModalBtn = document.getElementById('closeEmailVerificationModal');
            if (closeModalBtn && verificationModal) {
                closeModalBtn.addEventListener('click', function () {
                    verificationModal.classList.remove('visible');
                    verificationModal.style.display = 'none';
                });
            }

            if (verificationModal && verificationModal.classList.contains('visible')) {
                const firstCodeInput = verificationModal.querySelector('.code-digit');
                if (firstCodeInput) {
                    firstCodeInput.focus();
                }
            }

            // Manage Account functionality
            const manageSearchInput = document.getElementById('manage_search_input');
            const manageSearchResults = document.getElementById('manage_search_results');
            const manageAccountDetails = document.getElementById('manage_account_details');
            const manageNoSelection = document.getElementById('manage_no_selection');
            const manageSelectedUserId = document.getElementById('manage_selected_user_id');
            const manageAccountName = document.getElementById('manage_account_name');
            const manageAccountEmail = document.getElementById('manage_account_email');
            const manageAccountRole = document.getElementById('manage_account_role');
            const manageAccountId = document.getElementById('manage_account_id');
            const btnResetPassword = document.getElementById('btn_reset_password');
            const btnChangeEmail = document.getElementById('btn_change_email');

            if (manageSearchInput) {
                manageSearchInput.addEventListener('input', function () {
                    const searchTerm = this.value.trim();
                    if (searchTerm.length < 1) {
                        manageSearchResults.innerHTML = '';
                        return;
                    }

                    const allUsers = document.querySelectorAll('#tab-student-data .user-row, #tab-directory .user-row');
                    const matchedUsers = [];

                    allUsers.forEach(row => {
                        const name = row.querySelector('.user-name')?.textContent.toLowerCase() || '';
                        const email = row.querySelector('.email-link')?.textContent.toLowerCase() || '';
                        const studentId = row.getAttribute('data-student-id') || '';
                        const employeeId = row.getAttribute('data-employee-id') || '';
                        const userId = row.getAttribute('data-user-id') || '';
                        const role = row.getAttribute('data-role') || '';
                        const displayId = studentId || employeeId || userId;

                        if (name.includes(searchTerm) || email.includes(searchTerm) || studentId.includes(searchTerm) || employeeId.includes(searchTerm) || userId.includes(searchTerm)) {
                            matchedUsers.push({
                                id: userId,
                                displayId: displayId,
                                name: name.replace(/</g, '&lt;').replace(/>/g, '&gt;'),
                                email: email.replace(/</g, '&lt;').replace(/>/g, '&gt;'),
                                role: role,
                                studentId: studentId,
                                employeeId: employeeId
                            });
                        }
                    });

                    if (matchedUsers.length === 0) {
                        manageSearchResults.innerHTML = '<div style="padding: 16px; text-align: center; color: #8a7167;">No users found.</div>';
                        return;
                    }

                    manageSearchResults.innerHTML = matchedUsers.map(user => `
                        <div class="search-result-item" data-user-id="${user.id}" data-user-display-id="${user.displayId}" data-user-name="${user.name}" data-user-email="${user.email}" data-user-role="${user.role}">
                            <strong>${user.name}</strong>
                            <small>${user.email}</small>
                            <small style="color: #b07a2b;">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</small>
                        </div>
                    `).join('');

                    document.querySelectorAll('.search-result-item').forEach(item => {
                        item.addEventListener('click', function () {
                            manageSelectedUserId.value = this.getAttribute('data-user-id');
                            manageAccountName.textContent = this.getAttribute('data-user-name');
                            manageAccountEmail.textContent = this.getAttribute('data-user-email');
                            manageAccountRole.textContent = this.getAttribute('data-user-role').charAt(0).toUpperCase() + this.getAttribute('data-user-role').slice(1);
                            manageAccountId.textContent = this.getAttribute('data-user-display-id') || this.getAttribute('data-user-id');

                            manageNoSelection.style.display = 'none';
                            manageAccountDetails.style.display = 'block';
                        });
                    });
                });
            }

            if (btnResetPassword) {
                btnResetPassword.addEventListener('click', function () {
                    const userId = manageSelectedUserId.value;
                    if (!userId) {
                        alert('Please select a user first.');
                        return;
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href;
                    form.innerHTML = `
                        <input type="hidden" name="action" value="manage_reset_password">
                        <input type="hidden" name="user_id" value="${userId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                });
            }

            if (btnChangeEmail) {
                btnChangeEmail.addEventListener('click', function () {
                    const userId = manageSelectedUserId.value;
                    const currentEmail = manageAccountEmail.textContent;
                    if (!userId) {
                        alert('Please select a user first.');
                        return;
                    }

                    const newEmail = prompt('Enter new email address:', currentEmail);
                    if (!newEmail || newEmail.trim() === '') {
                        return;
                    }

                    if (!newEmail.includes('@')) {
                        alert('Please enter a valid email address.');
                        return;
                    }

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = window.location.href;
                    form.innerHTML = `
                        <input type="hidden" name="action" value="manage_change_email">
                        <input type="hidden" name="user_id" value="${userId}">
                        <input type="hidden" name="new_email" value="${newEmail}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                });
            }

            // Delete user functionality
            const deleteUserButtons = document.querySelectorAll('.delete-user-btn');
            deleteUserButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');
                    const userEmail = this.getAttribute('data-user-email');

                    if (confirm(`Are you sure you want to delete the account for ${userName} (${userEmail})?\n\nThis action cannot be undone.`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = window.location.href;
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="${userId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });

            const deleteStudentTeacherButtons = document.querySelectorAll('.delete-student-teacher-btn');
            deleteStudentTeacherButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const userId = this.getAttribute('data-user-id');
                    const userName = this.getAttribute('data-user-name');
                    const userEmail = this.getAttribute('data-user-email');
                    if (!userId) {
                        return;
                    }
                    openUserActionConfirmModal('delete_student_teacher', userId, userName, userEmail);
                });
            });
        });
    </script>
    <script>
        window.approvalEmailData = <?= json_encode($approvalEmailData) ?>;
        window.emailJsApprovalConfig = {
            serviceId: 'service_843t745',
            templateId: 'template_t0z3o6u',
            publicKey: 'rLeRK76s3p-IQn-X1'
        };
    </script>
    <!-- Ensure EmailJS SDK is loaded for approval emails -->
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <?php if ($showVerificationModal && $verificationStage === 'enter_code'): ?>
    <script>
        window.emailVerifyData = <?= json_encode(array_merge($verificationData, [
            'serviceId' => 'service_843t745',
            'templateId' => 'template_jb5tjxv',
            'publicKey' => 'rLeRK76s3p-IQn-X1'
        ])) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script src="../assets/js/email_verify.js"></script>
    <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.approvalEmailData && window.approvalEmailData.sendOnLoad) {
                if (typeof emailjs === 'undefined') {
                    console.error('EmailJS SDK not loaded for approval email.');
                    return;
                }
                emailjs.init(window.emailJsApprovalConfig.publicKey);
                // Provide common recipient keys that EmailJS templates often expect
                const templateParams = {
                    user_name: window.approvalEmailData.user_name,
                    user_email: window.approvalEmailData.email,
                    to_email: window.approvalEmailData.email,
                    email: window.approvalEmailData.email,
                    to_name: window.approvalEmailData.user_name,
                    login_link: window.approvalEmailData.login_link
                };
                console.log('Approval email template params:', templateParams);
                emailjs.send(window.emailJsApprovalConfig.serviceId, window.emailJsApprovalConfig.templateId, templateParams)
                .then(function (response) {
                    console.log('Approval email sent successfully to', window.approvalEmailData.email, response);
                }, function (error) {
                    console.error('Approval EmailJS error:', error);
                });
            }
        });
    </script>
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
    <script>(function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();</script>
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
