<?php
require_once __DIR__ . '/session.php';
require_login();
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$vision = isset($_POST['vision']) ? trim((string)$_POST['vision']) : '';
$mission = isset($_POST['mission']) ? trim((string)$_POST['mission']) : '';
$coreValues = [];
if (isset($_POST['coreValues'])) {
    $decodedCore = json_decode((string)$_POST['coreValues'], true);
    if (is_array($decodedCore)) {
        $coreValues = array_values(array_filter(array_map(function($item) {
            $value = trim((string)($item['value'] ?? $item ?? ''));
            return $value !== '' ? $value : null;
        }, $decodedCore), function($value) { return $value !== null; }));
    } else {
        $coreValues = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|,/', (string)$_POST['coreValues'])), function($value) { return $value !== ''; }));
    }
}

$programs = [];
if (isset($_POST['programs'])) {
    $decodedPrograms = json_decode((string)$_POST['programs'], true);
    if (is_array($decodedPrograms)) {
        $programs = array_values(array_filter(array_map(function($item) {
            $title = trim((string)($item['title'] ?? $item['name'] ?? ''));
            $description = trim((string)($item['description'] ?? ''));
            $color = trim((string)($item['color'] ?? ''));
            if ($title === '' && $description === '' && $color === '') {
                return null;
            }
            return [
                'title' => $title,
                'description' => $description,
                'color' => $color,
            ];
        }, $decodedPrograms), function($value) { return $value !== null; }));
    }
}

$people = [];
if (isset($_POST['people'])) {
    $decodedPeople = json_decode((string)$_POST['people'], true);
    if (is_array($decodedPeople)) {
        $people = array_values(array_filter(array_map(function($item) {
            $name = trim((string)($item['name'] ?? ''));
            $category = trim((string)($item['category'] ?? ''));
            $image = trim((string)($item['image'] ?? ''));
            if ($name === '' && $category === '' && $image === '') {
                return null;
            }
            return [
                'name' => $name,
                'category' => $category,
                'image' => $image,
            ];
        }, $decodedPeople), function($value) { return $value !== null; }));
    }
}

$uploadDir = __DIR__ . '/../assets/uploads/about_people/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$uploadedPaths = [];
if (!empty($_FILES['people_images']['name'])) {
    $fileCount = is_array($_FILES['people_images']['name']) ? count($_FILES['people_images']['name']) : 1;
    for ($i = 0; $i < $fileCount; $i++) {
        if (empty($_FILES['people_images']['name'][$i])) {
            continue;
        }
        $tmpName = $_FILES['people_images']['tmp_name'][$i] ?? '';
        if (!is_uploaded_file($tmpName)) {
            continue;
        }
        $originalName = basename((string)($_FILES['people_images']['name'][$i] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowed, true)) {
            $extension = 'jpg';
        }
        $fileName = 'person-' . time() . '-' . $i . '.' . $extension;
        $destPath = $uploadDir . $fileName;
        if (move_uploaded_file($tmpName, $destPath)) {
            $uploadedPaths[] = '../assets/uploads/about_people/' . $fileName;
        } else {
            $uploadedPaths[] = '';
        }
    }
}

foreach ($people as $index => &$person) {
    if (!empty($uploadedPaths[$index])) {
        $person['image'] = $uploadedPaths[$index];
    }
}
unset($person);

$ok1 = set_library_setting('about_vision', $vision);
$ok2 = set_library_setting('about_mission', $mission);
$ok3 = set_library_setting('about_core_values', json_encode($coreValues, JSON_UNESCAPED_UNICODE));
$ok4 = set_library_setting('about_programs', json_encode($programs, JSON_UNESCAPED_UNICODE));
$ok5 = set_library_setting('about_people', json_encode($people, JSON_UNESCAPED_UNICODE));

if ($ok1 && $ok2 && $ok3 && $ok4 && $ok5) {
    echo json_encode(['success' => true, 'message' => 'About page settings saved.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Unable to save about page settings.']);
}
