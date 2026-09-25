<?php
require_once __DIR__ . '/session.php';

header('Content-Type: application/json');

try {
    $db = get_db();
    $action = $_POST['action'] ?? $_GET['action'] ?? null;

    if ($action === 'get_logo') {
        $logoPath = get_library_setting('login_logo_path', 'IMG ASSETS/passlogo.png');
        $logoPath = trim((string)$logoPath);
        $logoPath = preg_replace('#^\.{1,2}/+#', '', $logoPath);
        echo json_encode([
            'success' => true,
            'data' => ['path' => '../' . $logoPath]
        ]);
        exit;
    }

    if (!isset($_SESSION['user_id']) || current_user()['role'] !== 'admin') {
        throw new Exception('Unauthorized');
    }

    if ($action === 'upload_logo') {
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No logo file uploaded or upload error');
        }

        $file = $_FILES['logo'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new Exception('Invalid file type. Only JPG, PNG, WebP, and GIF allowed');
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File too large. Maximum 5MB');
        }

        $uploadDir = __DIR__ . '/../uploads/login_logo/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'login_logo_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
        $filePath = $uploadDir . $filename;
        $relativePath = 'uploads/login_logo/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save logo file');
        }

        $currentLogo = get_library_setting('login_logo_path', 'IMG ASSETS/passlogo.png');
        $currentLogo = trim((string)$currentLogo);
        $currentLogo = preg_replace('#^\.{1,2}/+#', '', $currentLogo);

        if ($currentLogo !== 'IMG ASSETS/passlogo.png' && strpos($currentLogo, 'uploads/login_logo/') === 0) {
            $currentPath = __DIR__ . '/../' . $currentLogo;
            if (file_exists($currentPath)) {
                @unlink($currentPath);
            }
        }

        if (!set_library_setting('login_logo_path', $relativePath)) {
            throw new Exception('Failed to update logo setting');
        }

        echo json_encode([
            'success' => true,
            'data' => ['path' => '../' . $relativePath]
        ]);
        exit;
    }

    throw new Exception('Invalid action');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
