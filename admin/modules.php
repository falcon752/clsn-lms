<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$courseId = (int)($_GET['course_id'] ?? 0);
$isOverview = !$courseId;

$course = null;
if (!$isOverview) {
    $cq = $conn->prepare("SELECT * FROM lms_courses WHERE id=?");
    $cq->bind_param('i', $courseId);
    $cq->execute();
    $course = $cq->get_result()->fetch_assoc();
    $cq->close();
    if (!$course) { header('Location: /clsn-lms/admin/modules.php'); exit; }
}

$msg = '';
$err = '';

if (isset($_GET['added']))   $msg = 'Module added successfully!';
if (isset($_GET['created'])) $msg = 'Course created! Now add your first module.';

// Handle delete module (per-course mode only)
if (!$isOverview && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_module'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } else {
        $delId = (int)($_POST['module_id'] ?? 0);
        if ($delId) {
            $stmt = $conn->prepare("DELETE FROM lms_modules WHERE id = ? AND course_id = ?");
            $stmt->bind_param('ii', $delId, $courseId);
            $stmt->execute();
            $stmt->close();
            $mods = $conn->query("SELECT id FROM lms_modules WHERE course_id=$courseId ORDER BY sort_order, module_number ASC")->fetch_all(MYSQLI_ASSOC);
            foreach ($mods as $i => $m) {
                $num = $i + 1;
                $conn->query("UPDATE lms_modules SET module_number=$num, sort_order=$num WHERE id={$m['id']}");
            }
            $conn->query("UPDATE lms_courses SET total_modules=(SELECT COUNT(*) FROM lms_modules WHERE course_id=$courseId) WHERE id=$courseId");
            $msg = 'Module deleted and list renumbered.';
        }
    }
}

// ── Overview: load all courses with their modules ─────────────────────────
if ($isOverview) {
    $courses = $conn->query("SELECT * FROM lms_courses ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    $allModules = $conn->query("
        SELECT m.*,
               (SELECT COUNT(*) FROM lms_quizzes q WHERE q.module_id = m.id) AS has_quiz
        FROM lms_modules m
        ORDER BY m.course_id ASC, m.sort_order ASC
    ")->fetch_all(MYSQLI_ASSOC);
    // Group modules by course_id
    $modulesByCourse = [];
    foreach ($allModules as $m) {
        $modulesByCourse[$m['course_id']][] = $m;
    }
    $adminPageTitle = 'Modules';
} else {
    // ── Per-course: load modules for this course ──────────────────────────
    $mq = $conn->prepare("
        SELECT m.*,
               (SELECT COUNT(*) FROM lms_quizzes q WHERE q.module_id = m.id) AS has_quiz
        FROM lms_modules m
        WHERE m.course_id = ?
        ORDER BY m.sort_order ASC
    ");
    $mq->bind_param('i', $courseId);
    $mq->execute();
    $modules = $mq->get_result()->fetch_all(MYSQLI_ASSOC);
    $mq->close();
    $adminPageTitle = 'Modules: ' . $course['title'];
}

include './includes/header.php';
?>

<?php if ($msg): ?><div class="lms-alert lms-alert-success mb-5"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="lms-alert lms-alert-error mb-5"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

<?php if ($isOverview): ?>

<!-- ── Overview: all courses + modules ───────────────────────────────── -->
<div class="flex items-center justify-between mb-6">
    <p class="text-gray-500 text-sm"><?= count($courses) ?> course<?= count($courses) !== 1 ? 's' : '' ?></p>
</div>

<div class="space-y-6">
    <?php foreach ($courses as $c): ?>
    <?php $cms = $modulesByCourse[$c['id']] ?? []; ?>
    <div class="lms-card overflow-hidden">
        <!-- Course Header -->
        <div class="flex items-center justify-between px-5 py-4 bg-navy-900 text-white">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-graduation-cap text-candlelight-400 text-sm"></i>
                </div>
                <div>
                    <div class="font-display font-bold text-sm leading-tight"><?= htmlspecialchars($c['title']) ?></div>
                    <div class="text-xs text-gray-400 mt-0.5"><?= count($cms) ?> module<?= count($cms) !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <a href="/clsn-lms/admin/module-form.php?new=1&course_id=<?= $c['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-candlelight-500 hover:bg-candlelight-600 text-white rounded-lg text-xs font-semibold transition-colors">
                <i class="fas fa-plus"></i> Add Module
            </a>
        </div>
        <!-- Modules -->
        <?php if (empty($cms)): ?>
        <div class="px-5 py-6 text-center text-gray-400 text-sm">No modules yet for this course.</div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($cms as $m): ?>
            <div class="flex items-center gap-4 px-5 py-3.5">
                <div class="w-8 h-8 rounded-lg <?= $m['is_active'] ? 'bg-candlelight-100' : 'bg-gray-100' ?> flex items-center justify-center flex-shrink-0">
                    <span class="font-bold text-xs <?= $m['is_active'] ? 'text-candlelight-700' : 'text-gray-400' ?>"><?= $m['module_number'] ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <span class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($m['title']) ?></span>
                    <div class="flex flex-wrap items-center gap-3 mt-0.5 text-xs text-gray-400">
                        <?php if ($m['video_url']): ?><span><i class="fas fa-video text-candlelight-400 mr-1"></i>Video</span><?php endif; ?>
                        <?php if ($m['notes']): ?><span><i class="fas fa-file-alt text-candlelight-400 mr-1"></i>Notes</span><?php endif; ?>
                        <?php if ($m['has_quiz']): ?><span><i class="fas fa-question-circle text-candlelight-400 mr-1"></i>Quiz</span><?php endif; ?>
                        <?php if ($m['duration_minutes']): ?><span><i class="fas fa-clock mr-1"></i><?= $m['duration_minutes'] ?>min</span><?php endif; ?>
                        <?php if (!$m['is_active']): ?><span class="text-gray-300">Hidden</span><?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="/clsn-lms/admin/module-form.php?id=<?= $m['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-navy-100 text-navy-700 rounded-lg text-xs font-semibold hover:bg-navy-200 transition-colors">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="/clsn-lms/admin/quiz-builder.php?module_id=<?= $m['id'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-candlelight-100 text-candlelight-700 rounded-lg text-xs font-semibold hover:bg-candlelight-200 transition-colors">
                        <i class="fas fa-pencil-alt"></i> Quiz
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($courses)): ?>
    <div class="lms-card p-12 text-center text-gray-400">No courses found.</div>
    <?php endif; ?>
</div>

<?php else: ?>

<!-- ── Per-course view ────────────────────────────────────────────────── -->
<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
    <a href="/clsn-lms/admin/courses.php" class="hover:text-candlelight-600 transition-colors">Courses</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/clsn-lms/admin/modules.php" class="hover:text-candlelight-600 transition-colors">Modules</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <span class="text-gray-800 font-medium"><?= htmlspecialchars($course['title']) ?></span>
</nav>

<div class="flex items-center justify-between mb-6">
    <p class="text-gray-500 text-sm"><?= count($modules) ?> modules in this course</p>
    <a href="/clsn-lms/admin/module-form.php?new=1&course_id=<?= $courseId ?>" class="btn-lms-primary text-sm">
        <i class="fas fa-plus"></i> Add Module
    </a>
</div>

<div class="space-y-3">
    <?php foreach ($modules as $m): ?>
    <div class="lms-card p-5 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl <?= $m['is_active'] ? 'bg-candlelight-100' : 'bg-gray-100' ?> flex items-center justify-center flex-shrink-0">
            <span class="font-bold text-sm <?= $m['is_active'] ? 'text-candlelight-700' : 'text-gray-400' ?>"><?= $m['module_number'] ?></span>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($m['title']) ?></h3>
            <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-400">
                <?php if ($m['video_url']): ?><span><i class="fas fa-video text-candlelight-500 mr-1"></i>Has Video</span><?php endif; ?>
                <?php if ($m['notes']): ?><span><i class="fas fa-file-alt text-candlelight-500 mr-1"></i>Has Notes</span><?php endif; ?>
                <?php if ($m['has_quiz']): ?><span><i class="fas fa-question-circle text-candlelight-500 mr-1"></i>Has Quiz</span><?php endif; ?>
                <?php if ($m['duration_minutes']): ?><span><i class="fas fa-clock mr-1"></i><?= $m['duration_minutes'] ?>min</span><?php endif; ?>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <?php if (!$m['is_active']): ?>
            <span class="px-2.5 py-1 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">Hidden</span>
            <?php endif; ?>
            <a href="/clsn-lms/admin/module-form.php?id=<?= $m['id'] ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-navy-100 text-navy-700 rounded-xl text-xs font-semibold hover:bg-navy-200 transition-colors">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="/clsn-lms/admin/quiz-builder.php?module_id=<?= $m['id'] ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-candlelight-100 text-candlelight-700 rounded-xl text-xs font-semibold hover:bg-candlelight-200 transition-colors">
                <i class="fas fa-pencil-alt"></i> Quiz
            </a>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="module_id" value="<?= $m['id'] ?>">
                <button type="submit" name="delete_module"
                    data-confirm="Delete this module? This will also remove its quiz and progress data."
                    data-confirm-title="Delete Module?"
                    data-confirm-label="Yes, Delete"
                    data-confirm-icon='<i class="fas fa-trash"></i>'
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-red-50 text-red-600 rounded-xl text-xs font-semibold hover:bg-red-100 transition-colors">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($modules)): ?>
    <div class="lms-card p-12 text-center text-gray-400">No modules found for this course.</div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include './includes/footer.php'; ?>
