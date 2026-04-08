<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();
header('Content-Type: application/json');

$action  = $_POST['action']  ?? '';
$userId  = (int)($_POST['user_id'] ?? 0);
$csrf    = $_POST['csrf_token'] ?? '';

if (!verifyCsrf($csrf)) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid CSRF token']);
    exit;
}

if (!$userId) {
    echo json_encode(['ok' => false, 'msg' => 'Invalid user']);
    exit;
}

// Prevent acting on own account
if ($userId === (int)$_SESSION['lms_user_id']) {
    echo json_encode(['ok' => false, 'msg' => 'You cannot modify your own account']);
    exit;
}

// Protect the superadmin account
$saStmt = $conn->prepare("SELECT email FROM lms_users WHERE id = ?");
$saStmt->bind_param('i', $userId);
$saStmt->execute();
$saRow = $saStmt->get_result()->fetch_assoc();
$saStmt->close();
if (($saRow['email'] ?? '') === 'admin@candlelightspecialneeds.org') {
    echo json_encode(['ok' => false, 'msg' => 'This account cannot be modified']);
    exit;
}

if ($action === 'toggle_role') {
    // Fetch current role
    $stmt = $conn->prepare("SELECT role FROM lms_users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => 'User not found']);
        exit;
    }

    $newRole = ($row['role'] === 'admin') ? 'student' : 'admin';
    $upd = $conn->prepare("UPDATE lms_users SET role = ? WHERE id = ?");
    $upd->bind_param('si', $newRole, $userId);
    $upd->execute();
    $upd->close();

    echo json_encode(['ok' => true, 'new_role' => $newRole]);

} elseif ($action === 'delete_user') {
    // Remove enrollments, progress, certificates, then user
    $tables = ['lms_enrollments', 'lms_progress', 'lms_certificates', 'lms_quiz_attempts'];
    foreach ($tables as $tbl) {
        $del = $conn->prepare("DELETE FROM $tbl WHERE user_id = ?");
        $del->bind_param('i', $userId);
        $del->execute();
        $del->close();
    }
    $del = $conn->prepare("DELETE FROM lms_users WHERE id = ?");
    $del->bind_param('i', $userId);
    $del->execute();
    $del->close();

    echo json_encode(['ok' => true]);

} else {
    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
}
