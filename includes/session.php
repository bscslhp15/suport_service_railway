<?php
require_once __DIR__ . '/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ../auth/student_login.php');
        exit;
    }
}   

function require_role(string $role): void {
    require_login();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ../auth/student_login.php');
        exit;
    }
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $user = find_user_by_id((int) $_SESSION['user_id']);
    if (!$user) {
        return null;
    }
    return $user;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function record_user_activity(string $action): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['activity_history']) || !is_array($_SESSION['activity_history'])) {
        $_SESSION['activity_history'] = [];
    }
    $_SESSION['activity_history'][] = [
        'action' => $action,
        'timestamp' => (new DateTime())->format('M d, Y H:i'),
    ];
    if (count($_SESSION['activity_history']) > 12) {
        array_shift($_SESSION['activity_history']);
    }
}

function get_user_activity(int $limit = 8): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $history = $_SESSION['activity_history'] ?? [];
    if (!is_array($history)) {
        return [];
    }
    return array_slice(array_reverse($history), 0, $limit);
}
