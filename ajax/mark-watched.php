<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';
include_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$moduleId = (int)($_POST['module_id'] ?? 0);
$courseId = (int)($_POST['course_id'] ?? 0);
$token    = $_POST['csrf_token'] ?? '';

if (!verifyCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!$moduleId || !$courseId) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$userId = currentUserId();

if (!isEnrolled($conn, $userId, $courseId)) {
    echo json_encode(['success' => false, 'message' => 'Not enrolled']);
    exit;
}

markVideoWatched($conn, $userId, $moduleId, $courseId);

echo json_encode(['success' => true, 'message' => 'Video marked as watched']);
