<?php
require_once __DIR__ . '/includes/session.php';

$redirectPage = 'auth/student_login.php';
if (!empty($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'teacher':
            $redirectPage = 'auth/teacher_login.php';
            break;
        case 'admin':
            $redirectPage = 'auth/admin_login.php';
            break;
        case 'student':
        default:
            $redirectPage = 'auth/student_login.php';
            break;
    }
}

logout();
header('Location: ' . $redirectPage);
exit;
