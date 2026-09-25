<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

// Verify user is a teacher/librarian
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'library') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'upload') {
    // Handle template upload
    if (!isset($_FILES['template_file'])) {
        echo json_encode(['success' => false, 'message' => 'No file provided']);
        exit;
    }

    $file = $_FILES['template_file'];
    $allowed_mimes = [
        'application/pdf' => '.pdf',
        'application/msword' => '.doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
        'application/vnd.ms-excel' => '.xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
    ];

    // Validate file type
    $file_type = $file['type'];
    if (!isset($allowed_mimes[$file_type])) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed. Accept: PDF, DOC, DOCX, XLS, XLSX']);
        exit;
    }

    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
        exit;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
        exit;
    }

    // Generate safe filename
    $template_name = $_POST['template_name'] ?? 'letterhead_template';
    $template_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $template_name);
    $extension = $allowed_mimes[$file_type];
    $filename = $template_name . '_' . time() . $extension;

    // Save to letterhead folder
    $upload_dir = __DIR__ . '/../letterhead/letterhead template/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filepath = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Store template info in database (optional, for tracking)
        try {
            $pdo = get_db();
            $stmt = $pdo->prepare("
                INSERT INTO library_letterhead_templates (template_name, file_path, file_type, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), created_at = NOW()
            ");
            $stmt->execute([
                $template_name,
                'letterhead/letterhead template/' . $filename,
                $file_type,
                $user['id']
            ]);
        } catch (Exception $e) {
            // Template table might not exist, that's okay
        }

        echo json_encode([
            'success' => true,
            'message' => 'Template uploaded successfully',
            'filename' => $filename,
            'file_path' => 'letterhead/letterhead template/' . $filename,
            'file_type' => $file_type,
            'template_name' => $template_name
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    }
}
elseif ($action === 'list') {
    // List available templates
    $template_dir = __DIR__ . '/../letterhead/letterhead template/';
    $templates = [];

    if (is_dir($template_dir)) {
        $files = scandir($template_dir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && is_file($template_dir . $file)) {
                $templates[] = [
                    'filename' => $file,
                    'path' => 'letterhead/letterhead template/' . $file,
                    'size' => filesize($template_dir . $file),
                    'modified' => date('Y-m-d H:i:s', filemtime($template_dir . $file))
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'templates' => $templates,
        'count' => count($templates)
    ]);
}
elseif ($action === 'delete') {
    // Delete template
    $filename = $_POST['filename'] ?? '';
    $filename = basename($filename); // Prevent directory traversal

    $template_dir = __DIR__ . '/../letterhead/letterhead template/';
    $filepath = $template_dir . $filename;

    if (file_exists($filepath) && is_file($filepath)) {
        if (unlink($filepath)) {
            echo json_encode(['success' => true, 'message' => 'Template deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete template']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Template not found']);
    }
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
