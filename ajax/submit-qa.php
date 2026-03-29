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
$question = trim($_POST['question']   ?? '');
$token    = $_POST['csrf_token']      ?? '';

if (!verifyCsrf($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!$moduleId || empty($question)) {
    echo json_encode(['success' => false, 'message' => 'Question text is required']);
    exit;
}

if (strlen($question) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Question is too long (max 1000 characters)']);
    exit;
}

$userId = currentUserId();
$user   = currentUser();

// Verify module exists
$stmt = $conn->prepare("SELECT id FROM lms_modules WHERE id = ?");
$stmt->bind_param('i', $moduleId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Module not found']);
    exit;
}
$stmt->close();

// Insert Q&A
$stmt = $conn->prepare("INSERT INTO lms_module_qa (module_id, user_id, question) VALUES (?,?,?)");
$stmt->bind_param('iis', $moduleId, $userId, $question);
$stmt->execute();
$newId = $conn->insert_id;
$stmt->close();

// Build HTML for new Q&A item
$initials   = strtoupper(substr($user['first_name'], 0, 1));
$name       = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
$questionHtml = nl2br(htmlspecialchars($question));
$time       = 'Just now';

$html = <<<HTML
<div class="qa-item" id="qa-{$newId}">
    <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-full bg-candlelight-100 flex items-center justify-center font-bold text-candlelight-700 text-sm flex-shrink-0">
            {$initials}
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="font-semibold text-sm text-gray-800">{$name}</span>
                <span class="text-xs text-gray-400">{$time}</span>
            </div>
            <p class="text-gray-700 text-sm">{$questionHtml}</p>
        </div>
    </div>
</div>
HTML;

echo json_encode(['success' => true, 'html' => $html, 'id' => $newId]);
