<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$courseId = (int)($_GET['course_id'] ?? 0);
if (!$courseId) { header('Location: /clsn-lms/admin/courses.php'); exit; }

$course = $conn->prepare("SELECT * FROM lms_courses WHERE id=?");
$course->bind_param('i', $courseId);
$course->execute();
$course = $course->get_result()->fetch_assoc();
if (!$course) { header('Location: /clsn-lms/admin/courses.php'); exit; }

$modules = $conn->prepare("
    SELECT m.*,
           (SELECT COUNT(*) FROM lms_quizzes q WHERE q.module_id = m.id) AS has_quiz
    FROM lms_modules m
    WHERE m.course_id = ?
    ORDER BY m.sort_order ASC
");
$modules->bind_param('i', $courseId);
$modules->execute();
$modules = $modules->get_result()->fetch_all(MYSQLI_ASSOC);

$adminPageTitle = 'Modules — ' . $course['title'];
include './includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
    <a href="/clsn-lms/admin/courses.php" class="hover:text-candlelight-600 transition-colors">Courses</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <span class="text-gray-800"><?= htmlspecialchars($course['title']) ?></span>
</nav>

<div class="flex items-center justify-between mb-6">
    <p class="text-gray-500 text-sm"><?= count($modules) ?> modules in this course</p>
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
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($modules)): ?>
    <div class="lms-card p-12 text-center text-gray-400">No modules found for this course.</div>
    <?php endif; ?>
</div>

<?php include './includes/footer.php'; ?>
