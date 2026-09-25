<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
    $db = get_db();
    $action = $_POST['action'] ?? $_GET['action'] ?? null;
    
    if ($action === 'get_images') {
        // Get images for a specific login type
        $loginType = $_GET['login_type'] ?? null;
        
        if (!$loginType || !in_array($loginType, ['student', 'teacher', 'both'])) {
            throw new Exception('Invalid login type');
        }
        
        if ($loginType === 'both') {
            $stmt = $db->prepare("
                SELECT id, image_path, display_order, login_type
                FROM carousel_images
                WHERE login_type IN ('student', 'teacher')
                ORDER BY login_type ASC, display_order ASC
            ");
            $stmt->execute();
        } else {
            $stmt = $db->prepare("
                SELECT id, image_path, display_order, login_type
                FROM carousel_images 
                WHERE login_type = ? 
                ORDER BY display_order ASC
            ");
            $stmt->execute([$loginType]);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }
    elseif ($action === 'upload_image') {
        // Check admin privilege
        if (!isset($_SESSION['user_id']) || current_user()['role'] !== 'admin') {
            throw new Exception('Unauthorized');
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error');
        }
        
        $loginType = $_POST['login_type'] ?? null;
        if (!$loginType || !in_array($loginType, ['student', 'teacher', 'both'])) {
            throw new Exception('Invalid login type');
        }
        
        $file = $_FILES['image'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Invalid file type. Only JPG, PNG, WebP, and GIF allowed');
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            throw new Exception('File too large. Maximum 5MB');
        }
        
        // Create uploads directory if needed
        $uploadDir = __DIR__ . '/../uploads/carousel/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'carousel_' . $loginType . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $filePath = $uploadDir . $filename;
        $relativePath = 'uploads/carousel/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save file');
        }
        
        $loginTypes = $loginType === 'both' ? ['student', 'teacher'] : [$loginType];
        $insertedRows = [];

        foreach ($loginTypes as $type) {
            $stmt = $db->prepare("
                SELECT MAX(display_order) as max_order 
                FROM carousel_images 
                WHERE login_type = ?
            ");
            $stmt->execute([$type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextOrder = ($result['max_order'] ?? -1) + 1;

            $stmt = $db->prepare("
                INSERT INTO carousel_images (login_type, image_path, display_order) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$type, $relativePath, $nextOrder]);
            $insertedRows[] = [
                'id' => $db->lastInsertId(),
                'login_type' => $type,
                'image_path' => $relativePath,
                'display_order' => $nextOrder,
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'data' => $insertedRows
        ]);
    }
    elseif ($action === 'delete_image') {
        // Check admin privilege
        if (!isset($_SESSION['user_id']) || current_user()['role'] !== 'admin') {
            throw new Exception('Unauthorized');
        }
        
        $imageId = $_POST['image_id'] ?? null;
        if (!$imageId) {
            throw new Exception('Image ID required');
        }
        
        // Get image path
        $stmt = $db->prepare("SELECT image_path FROM carousel_images WHERE id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$image) {
            throw new Exception('Image not found');
        }
        
        // Delete file
        $filePath = __DIR__ . '/../' . $image['image_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $stmt = $db->prepare("DELETE FROM carousel_images WHERE id = ?");
        $stmt->execute([$imageId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Image deleted successfully'
        ]);
    }
    else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
