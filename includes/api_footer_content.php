<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $contacts = get_library_setting('footer_contacts', '[]');
    $locations = get_library_setting('footer_locations', '[]');
    $social = get_library_setting('footer_social', '[]');
    echo json_encode([ 'contacts' => json_decode($contacts, true), 'locations' => json_decode($locations, true), 'social' => json_decode($social, true) ]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $contacts = isset($input['contacts']) ? json_encode(array_values($input['contacts'])) : json_encode([]);
    $locations = isset($input['locations']) ? json_encode(array_values($input['locations'])) : json_encode([]);
    $social = isset($input['social']) ? json_encode(array_values($input['social'])) : json_encode([]);

    $ok1 = set_library_setting('footer_contacts', $contacts);
    $ok2 = set_library_setting('footer_locations', $locations);
    $ok3 = set_library_setting('footer_social', $social);

    if ($ok1 && $ok2 && $ok3) {
        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['error' => 'Could not save settings']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

?>
