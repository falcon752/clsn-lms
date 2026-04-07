<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$isNew    = isset($_GET['new']) && (int)($_GET['course_id'] ?? 0) > 0;
$moduleId = $isNew ? 0 : (int)($_GET['id'] ?? 0);
$module   = null;

if ($isNew) {
    $courseId = (int)$_GET['course_id'];
    $cStmt = $conn->prepare("SELECT * FROM lms_courses WHERE id = ?");
    $cStmt->bind_param('i', $courseId);
    $cStmt->execute();
    $courseRow = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();
    if (!$courseRow) { header('Location: /clsn-lms/admin/courses.php'); exit; }

    // Next module number
    $r = $conn->prepare("SELECT COALESCE(MAX(module_number),0)+1 AS next_num FROM lms_modules WHERE course_id=?");
    $r->bind_param('i', $courseId);
    $r->execute();
    $nextNum = (int)$r->get_result()->fetch_assoc()['next_num'];
    $r->close();

    $module = [
        'id'               => 0,
        'course_id'        => $courseId,
        'course_title'     => $courseRow['title'],
        'module_number'    => $nextNum,
        'title'            => '',
        'description'      => '',
        'video_type'       => 'youtube',
        'video_url'        => '',
        'notes'            => '',
        'duration_minutes' => 30,
        'is_active'        => 1,
    ];
} else {
    if (!$moduleId) { header('Location: /clsn-lms/admin/courses.php'); exit; }
    $stmt = $conn->prepare("SELECT m.*, c.title AS course_title, c.id AS course_id FROM lms_modules m JOIN lms_courses c ON c.id=m.course_id WHERE m.id=?");
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $module = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$module) { header('Location: /clsn-lms/admin/courses.php'); exit; }
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_module'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } else {
        $title     = trim($_POST['title']           ?? '');
        $desc      = trim($_POST['description']     ?? '');
        $videoType = $_POST['video_type']            ?? 'youtube';
        $videoUrl  = trim($_POST['video_url']        ?? '');
        $notes     = $_POST['notes']                ?? '';
        $duration  = (int)($_POST['duration_minutes'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title)) { $err = 'Module title is required.'; }
        else {
            if ($isNew) {
                $sortOrder = $module['module_number'];
                $stmt = $conn->prepare("INSERT INTO lms_modules (course_id,module_number,title,description,video_type,video_url,notes,duration_minutes,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('iisssssiis', $module['course_id'], $module['module_number'], $title, $desc, $videoType, $videoUrl, $notes, $duration, $isActive, $sortOrder);
                if ($stmt->execute()) {
                    $newModId = $conn->insert_id;
                    // Update total_modules count
                    $conn->query("UPDATE lms_courses SET total_modules=(SELECT COUNT(*) FROM lms_modules WHERE course_id={$module['course_id']}) WHERE id={$module['course_id']}");
                    $stmt->close();
                    header('Location: /clsn-lms/admin/modules.php?course_id=' . $module['course_id'] . '&added=1');
                    exit;
                } else {
                    $err = 'Database error: ' . $conn->error;
                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare("UPDATE lms_modules SET title=?,description=?,video_type=?,video_url=?,notes=?,duration_minutes=?,is_active=? WHERE id=?");
                $stmt->bind_param('sssssiis', $title, $desc, $videoType, $videoUrl, $notes, $duration, $isActive, $moduleId);
                $stmt->execute();
                $stmt->close();
                $module['title']       = $title;
                $module['description'] = $desc;
                $module['video_type']  = $videoType;
                $module['video_url']   = $videoUrl;
                $module['notes']       = $notes;
                $module['duration_minutes'] = $duration;
                $module['is_active']   = $isActive;
                $msg = 'Module updated successfully!';
            }
        }
    }
}

$adminPageTitle = $isNew ? 'Add Module' : 'Edit Module';
include './includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
    <a href="/clsn-lms/admin/courses.php" class="hover:text-candlelight-600 transition-colors">Courses</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/clsn-lms/admin/courses.php" class="hover:text-candlelight-600 transition-colors"><?= htmlspecialchars($module['course_title']) ?></a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/clsn-lms/admin/modules.php?course_id=<?= $module['course_id'] ?>" class="hover:text-candlelight-600 transition-colors">Modules</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <span class="text-gray-800 font-medium"><?= $isNew ? 'New Module' : 'Edit Module ' . $module['module_number'] ?></span>
</nav>

<div class="max-w-3xl">
    <?php if ($msg): ?><div class="lms-alert lms-alert-success mb-5"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="lms-alert lms-alert-error mb-5"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="POST" class="space-y-5">
        <?= csrfField() ?>
        <input type="hidden" name="update_module" value="1">

        <div class="lms-card p-6 space-y-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Module Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" required class="lms-input"
                    value="<?= htmlspecialchars($module['title']) ?>">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Short Description</label>
                <textarea name="description" rows="3" class="lms-input resize-none"><?= htmlspecialchars($module['description'] ?? '') ?></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Video Type</label>
                    <select name="video_type" class="lms-input">
                        <?php foreach (['youtube'=>'YouTube','vimeo'=>'Vimeo','file'=>'Self-Hosted File'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $module['video_type']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Duration (minutes)</label>
                    <input type="number" name="duration_minutes" class="lms-input" min="0"
                        value="<?= $module['duration_minutes'] ?>">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Video URL</label>
                <input type="text" name="video_url" class="lms-input"
                    placeholder="https://www.youtube.com/watch?v=..."
                    value="<?= htmlspecialchars($module['video_url'] ?? '') ?>">
                <p class="text-xs text-gray-400 mt-1">Paste a YouTube or Vimeo URL. For self-hosted, add the filename (e.g., module1.mp4).</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Module Notes</label>
                <textarea name="notes" rows="12" class="lms-input font-mono text-xs resize-y"><?= htmlspecialchars($module['notes'] ?? '') ?></textarea>
                <p class="text-xs text-gray-400 mt-1">You can use HTML tags (&lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;em&gt;, etc.)</p>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" class="w-4 h-4 accent-candlelight-500"
                    <?= $module['is_active'] ? 'checked' : '' ?>>
                <label for="is_active" class="text-sm font-semibold text-gray-700">Module is active (visible to students)</label>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-lms-primary"><i class="fas fa-save"></i> <?= $isNew ? 'Create Module' : 'Save Changes' ?></button>
            <a href="/clsn-lms/admin/modules.php?course_id=<?= $module['course_id'] ?>" class="btn-lms-secondary">
                <i class="fas fa-arrow-left"></i> Back to Modules
            </a>
            <?php if (!$isNew): ?>
            <a href="/clsn-lms/admin/quiz-builder.php?module_id=<?= $moduleId ?>" class="btn-lms-secondary">
                <i class="fas fa-pencil-alt"></i> Edit Quiz
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php include './includes/footer.php'; ?>
