<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$filterModule = (int)($_GET['module_id'] ?? 0);

$msg = '';
$err = '';

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } elseif (isset($_POST['post_answer'])) {
        $qaId   = (int)($_POST['qa_id'] ?? 0);
        $answer = trim($_POST['answer_text'] ?? '');
        if (!$qaId || empty($answer)) {
            $err = 'Please enter an answer.';
        } else {
            $stmt = $conn->prepare("UPDATE lms_module_qa SET answer=?, is_answered=1, answered_at=NOW() WHERE id=?");
            $stmt->bind_param('si', $answer, $qaId);
            $stmt->execute();
            $stmt->close();
            $msg = 'Answer posted successfully.';
        }
    } elseif (isset($_POST['delete_qa'])) {
        $qaId = (int)($_POST['qa_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM lms_module_qa WHERE id=?");
        $stmt->bind_param('i', $qaId);
        $stmt->execute();
        $stmt->close();
        $msg = 'Question deleted.';
    }
}

// Build query
$where = $filterModule ? "WHERE q.module_id={$filterModule}" : '';
$qaList = $conn->query(
    "SELECT q.*, 
            m.title AS module_title, m.module_number,
            c.title AS course_title,
            CONCAT(u.first_name,' ',u.last_name) AS student_name
     FROM lms_module_qa q
     JOIN lms_modules m ON m.id=q.module_id
     JOIN lms_courses c ON c.id=m.course_id
     JOIN lms_users u ON u.id=q.user_id
     {$where}
     ORDER BY q.is_answered ASC, q.created_at DESC
     LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);

// Module dropdown list
$modulesForFilter = $conn->query(
    "SELECT m.id, m.title, m.module_number, c.title AS course_title FROM lms_modules m JOIN lms_courses c ON c.id=m.course_id ORDER BY c.id, m.module_number"
)->fetch_all(MYSQLI_ASSOC);

$unanswered = array_filter($qaList, fn($q) => !$q['is_answered']);

$adminPageTitle = 'Q&A Manager';
include './includes/header.php';
?>

<!-- Header Controls -->
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
        <span class="w-8 h-8 rounded-full bg-orange-100 text-orange-500 flex items-center justify-center text-sm font-bold"><?= count($unanswered) ?></span>
        <span class="text-sm text-gray-500">unanswered question<?= count($unanswered) !== 1 ? 's' : ''?></span>
    </div>
    <form method="GET" class="flex items-center gap-2">
        <select name="module_id" class="lms-input text-sm py-1.5 pr-8 pl-3">
            <option value="">All Modules</option>
            <?php foreach ($modulesForFilter as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $filterModule===$m['id']?'selected':'' ?>>
                <?= htmlspecialchars($m['course_title']) ?> (Mod <?= $m['module_number'] ?>): <?= htmlspecialchars(substr($m['title'],0,30)) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-lms-secondary text-sm py-1.5">Filter</button>
        <?php if ($filterModule): ?><a href="?" class="text-xs text-gray-400 hover:text-gray-600 underline">Clear</a><?php endif; ?>
    </form>
</div>

<?php if ($msg): ?><div class="lms-alert lms-alert-success mb-5"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="lms-alert lms-alert-error mb-5"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

<?php if (empty($qaList)): ?>
<div class="lms-card p-12 text-center">
    <i class="fas fa-comments text-gray-200 text-5xl mb-4 block"></i>
    <p class="text-gray-400">No questions yet.</p>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($qaList as $qa): ?>
    <div class="lms-card overflow-hidden <?= $qa['is_answered'] ? 'opacity-75' : '' ?>">
        <div class="p-4 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <span class="font-semibold text-gray-700"><?= htmlspecialchars($qa['student_name']) ?></span>
                <span>&middot;</span>
                <span><?= htmlspecialchars($qa['course_title']) ?> (Mod <?= $qa['module_number'] ?>)</span>
                <span>&middot;</span>
                <span><?= date('M j, Y g:ia', strtotime($qa['created_at'])) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($qa['is_answered']): ?>
                <span class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 px-2.5 py-0.5 rounded-full font-semibold"><i class="fas fa-check"></i> Answered</span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1 text-xs bg-orange-100 text-orange-700 px-2.5 py-0.5 rounded-full font-semibold"><i class="fas fa-clock"></i> Pending</span>
                <?php endif; ?>
                <form method="POST" class="inline">
                    <?= csrfField() ?><input type="hidden" name="delete_qa" value="1">
                    <input type="hidden" name="qa_id" value="<?= $qa['id'] ?>">
                    <button type="submit" onclick="return confirm('Delete this Q&A?')" class="text-red-300 hover:text-red-500 text-xs transition-colors">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <div class="p-5">
            <p class="text-sm font-semibold text-gray-800 mb-3"><?= htmlspecialchars($qa['question']) ?></p>
            <?php if ($qa['is_answered']): ?>
            <div class="pl-4 border-l-2 border-green-300 text-sm text-gray-600 italic">
                <?= nl2br(htmlspecialchars($qa['answer'])) ?>
                <p class="text-xs text-gray-400 mt-1 not-italic">Answered <?= $qa['answered_at'] ? date('M j, Y', strtotime($qa['answered_at'])) : '' ?></p>
            </div>
            <?php else: ?>
            <form method="POST" class="mt-1">
                <?= csrfField() ?><input type="hidden" name="post_answer" value="1">
                <input type="hidden" name="qa_id" value="<?= $qa['id'] ?>">
                <textarea name="answer_text" rows="3" required class="lms-input resize-none text-sm mb-2" placeholder="Type your answer for this student..."></textarea>
                <button type="submit" class="btn-lms-primary text-sm"><i class="fas fa-reply"></i> Post Answer</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include './includes/footer.php'; ?>
