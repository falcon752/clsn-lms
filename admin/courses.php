<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$msg = '';
$err = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } else {
        $delId = (int)($_POST['course_id'] ?? 0);
        if ($delId) {
            $stmt = $conn->prepare("DELETE FROM lms_courses WHERE id = ?");
            $stmt->bind_param('i', $delId);
            $stmt->execute();
            $stmt->close();
            $msg = 'Course deleted successfully.';
        }
    }
}

$courses = $conn->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM lms_enrollments e WHERE e.course_id = c.id) AS enrollment_count
    FROM lms_courses c
    ORDER BY c.id ASC
")->fetch_all(MYSQLI_ASSOC);

$adminPageTitle = 'Courses';
include './includes/header.php';
?>

<?php if ($msg): ?><div class="lms-alert lms-alert-success mb-5"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="lms-alert lms-alert-error mb-5"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="flex items-center justify-between mb-6">
    <p class="text-gray-500 text-sm"><?= count($courses) ?> course<?= count($courses) !== 1 ? 's' : '' ?> total</p>
    <a href="/clsn-lms/admin/course-form.php" class="btn-lms-primary text-sm">
        <i class="fas fa-plus"></i> New Course
    </a>
</div>

<div class="space-y-4">
    <?php foreach ($courses as $c): ?>
    <div class="lms-card p-5 flex items-center gap-5">
        <?php
        $aThumb = '';
        if (!empty($c['thumbnail']) && file_exists(__DIR__ . '/../uploads/thumbnails/' . basename($c['thumbnail']))) {
            $aThumb = '/clsn-lms/uploads/thumbnails/' . htmlspecialchars(basename($c['thumbnail']));
        } elseif (!empty($c['youtube_url'])) {
            preg_match('#(?:v=|youtu\.be/|embed/)([a-zA-Z0-9_-]{11})#', $c['youtube_url'], $aYtm);
            if (!empty($aYtm[1])) {
                $aThumb = 'https://img.youtube.com/vi/' . htmlspecialchars($aYtm[1]) . '/hqdefault.jpg';
            }
        }
        ?>
        <div class="w-16 h-16 rounded-2xl overflow-hidden flex-shrink-0 bg-gradient-to-br from-navy-700 to-navy-900 flex items-center justify-center">
            <?php if ($aThumb): ?>
            <img src="<?= $aThumb ?>" alt="<?= htmlspecialchars($c['title']) ?>" class="w-full h-full object-cover">
            <?php else: ?>
            <i class="fas fa-graduation-cap text-candlelight-400 text-lg"></i>
            <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-0.5">
                <h3 class="font-display font-bold text-navy-900 text-base truncate"><?= htmlspecialchars($c['title']) ?></h3>
                <?php if ($c['is_active']): ?>
                <span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full flex-shrink-0">Active</span>
                <?php else: ?>
                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full flex-shrink-0">Hidden</span>
                <?php endif; ?>
            </div>
            <p class="text-gray-400 text-xs line-clamp-1 mb-2"><?= htmlspecialchars($c['short_description']) ?></p>
            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-400">
                <span><i class="fas fa-layer-group text-candlelight-500 mr-1"></i><?= (int)$c['total_modules'] ?> modules</span>
                <span><i class="fas fa-users text-candlelight-500 mr-1"></i><?= $c['enrollment_count'] ?> enrolled</span>
                <span><i class="fas fa-clock text-candlelight-500 mr-1"></i><?= htmlspecialchars($c['duration']) ?></span>
                <span><i class="fas fa-signal text-candlelight-500 mr-1"></i><?= htmlspecialchars($c['level']) ?></span>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <a href="/clsn-lms/admin/modules.php?course_id=<?= $c['id'] ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-navy-100 text-navy-700 rounded-xl text-xs font-semibold hover:bg-navy-200 transition-colors">
                <i class="fas fa-layer-group"></i> Modules
            </a>
            <a href="/clsn-lms/admin/course-form.php?id=<?= $c['id'] ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-candlelight-100 text-candlelight-700 rounded-xl text-xs font-semibold hover:bg-candlelight-200 transition-colors">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="/clsn-lms/course.php?slug=<?= urlencode($c['slug']) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-100 text-gray-600 rounded-xl text-xs font-semibold hover:bg-gray-200 transition-colors">
                <i class="fas fa-eye"></i> Preview
            </a>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                <button type="submit" name="delete_course"
                    data-confirm="Delete this course and ALL its modules? This cannot be undone."
                    data-confirm-title="Delete Course?"
                    data-confirm-label="Yes, Delete"
                    data-confirm-icon='<i class="fas fa-trash"></i>'
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-red-50 text-red-600 rounded-xl text-xs font-semibold hover:bg-red-100 transition-colors">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($courses)): ?>
    <div class="lms-card p-12 text-center text-gray-400">No courses found. Run setup.php to seed data.</div>
    <?php endif; ?>
</div>

<?php include './includes/footer.php'; ?>
