<?php
include_once './includes/db.php';
include_once './includes/auth.php';

requireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /courses');
    exit;
}

if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
    header('Location: /courses?error=invalid_request');
    exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
$userId   = currentUserId();

if (!$courseId) {
    header('Location: /courses?error=invalid_course');
    exit;
}

include_once './includes/functions.php';

$course = getCourse($conn, $courseId);
if (!$course) {
    header('Location: /courses?error=course_not_found');
    exit;
}

enrollUser($conn, $userId, $courseId);

header("Location: /course.php?slug={$course['slug']}&enrolled=1");
exit;
