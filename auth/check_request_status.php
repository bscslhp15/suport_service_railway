<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$email = trim((string)($input['email'] ?? ''));
$studentId = trim((string)($input['student_id'] ?? ''));
$employeeId = trim((string)($input['employee_id'] ?? ''));

if ($email === '' && $studentId === '' && $employeeId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing email, student ID, or employee ID']);
    exit;
}

try {
    if ($email !== '') {
        $record = get_db_pending_registration_by_email($email);
    } else {
        $column = $studentId !== '' ? 'student_id' : 'employee_id';
        $value = $studentId !== '' ? $studentId : $employeeId;
        ensure_pending_registration_schema();
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM pending_student_registrations WHERE {$column} = :value ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['value' => $value]);
        $record = $stmt->fetch() ?: null;
    }

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'No pending request found']);
        exit;
    }

    // normalize result
    $out = [
        'id' => $record['id'] ?? null,
        'email' => $record['email'] ?? null,
        'student_id' => $record['student_id'] ?? null,
        'employee_id' => $record['employee_id'] ?? null,
        'status' => $record['status'] ?? null,
        'created_at' => $record['created_at'] ?? null,
        'approved_at' => $record['approved_at'] ?? null,
        'approved_by' => $record['approved_by'] ?? null,
    ];

    echo json_encode(['success' => true, 'record' => $out]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
