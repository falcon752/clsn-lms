<?php
include_once './includes/db.php';
include_once './includes/auth.php';
include_once './includes/functions.php';

requireLogin();

$user   = currentUser();
$userId = currentUserId();

// Load all enrollments for this user with progress
$enrollments = getUserEnrollments($conn, $userId);
foreach ($enrollments as &$enrollment) {
    $enrollment['progress'] = getCourseProgress($conn, $userId, $enrollment['course_id']);
}
unset($enrollment);

// Stats
$totalModulesCompleted = 0;
$totalQuizzesPassed    = 0;
foreach ($enrollments as $e) { $totalModulesCompleted += $e['progress']['completed']; }

$certResult = $conn->prepare("SELECT COUNT(*) AS cnt FROM lms_certificates WHERE user_id = ?");
$certResult->bind_param('i', $userId);
$certResult->execute();
$totalCerts = (int)$certResult->get_result()->fetch_assoc()['cnt'];
$certResult->close();

$welcome       = isset($_GET['welcome']) && $_GET['welcome'] === '1';
$dashPageTitle = 'Dashboard';
include './includes/header-dash.php';
?>

<?php if ($welcome): ?>
<div id="welcome-toast" class="fixed top-4 right-4 z-50 bg-navy-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 max-w-sm">
    <div class="w-10 h-10 rounded-full bg-candlelight-500 flex items-center justify-center flex-shrink-0">🎉</div>
    <div>
        <div class="font-bold text-sm">Welcome to Candlelight LMS!</div>
        <div class="text-xs text-gray-300 mt-0.5">You've been enrolled in the Autism course.</div>
    </div>
    <button onclick="this.parentElement.remove()" class="ml-auto text-gray-400 hover:text-white"><i class="fas fa-times"></i></button>
</div>
<script>setTimeout(() => document.getElementById('welcome-toast')?.remove(), 5000);</script>
<?php endif; ?>

<!-- Welcome Header -->
<div class="mb-8">
    <h2 class="font-display text-2xl font-bold text-navy-900">
        Welcome back, <?= htmlspecialchars($user['first_name']) ?>! 👋
    </h2>
    <p class="text-gray-500 mt-1">Here's your learning progress at a glance.</p>
</div>

<!-- Stats Row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    <?php
    $stats = [
        ['icon'=>'fas fa-graduation-cap','value'=>count($enrollments),'label'=>'Courses Enrolled','color'=>'text-navy-700','bg'=>'bg-navy-50'],
        ['icon'=>'fas fa-check-circle',  'value'=>$totalModulesCompleted,'label'=>'Modules Completed','color'=>'text-green-700','bg'=>'bg-green-50'],
        ['icon'=>'fas fa-certificate',   'value'=>$totalCerts,'label'=>'Certificates Earned','color'=>'text-candlelight-700','bg'=>'bg-candlelight-50'],
        ['icon'=>'fas fa-chart-line',    'value'=>(count($enrollments) > 0 ? round(array_sum(array_column(array_column($enrollments,'progress'),'percent')) / count($enrollments)) : 0).'%','label'=>'Avg Progress','color'=>'text-purple-700','bg'=>'bg-purple-50'],
    ];
    foreach ($stats as $s):
    ?>
    <div class="lms-card p-6 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl <?= $s['bg'] ?> flex items-center justify-center flex-shrink-0">
            <i class="<?= $s['icon'] ?> <?= $s['color'] ?> text-xl"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 leading-none"><?= $s['value'] ?></div>
            <div class="text-xs text-gray-500 mt-1"><?= $s['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- My Courses -->
<div class="mb-10">
    <div class="flex items-center justify-between mb-6">
        <h2 class="font-display text-xl font-bold text-navy-900">My Courses</h2>
        <a href="/clsn-lms/courses.php" class="text-candlelight-600 text-sm font-semibold hover:text-candlelight-700 transition-colors flex items-center gap-1">
            Browse All Courses <i class="fas fa-arrow-right text-xs"></i>
        </a>
    </div>

    <?php if (empty($enrollments)): ?>
    <div class="lms-card p-12 text-center">
        <div class="w-20 h-20 rounded-3xl bg-navy-50 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-graduation-cap text-navy-400 text-3xl"></i>
        </div>
        <h3 class="font-display font-bold text-navy-900 text-xl mb-2">Start Learning Today</h3>
        <p class="text-gray-500 mb-6">You haven't enrolled in any courses yet. Browse our free courses and get started!</p>
        <a href="/clsn-lms/courses.php" class="btn-lms-primary inline-flex mx-auto">
            <i class="fas fa-compass"></i> Explore Courses
        </a>
    </div>
    <?php else: ?>
    <div class="grid md:grid-cols-2 gap-6">
        <?php foreach ($enrollments as $enrollment): ?>
        <?php $p = $enrollment['progress']; ?>
        <div class="lms-card p-6 flex flex-col">
            <div class="flex items-start gap-4 mb-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-navy-700 to-navy-900 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-graduation-cap text-candlelight-400 text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-display font-bold text-navy-900 text-base leading-snug line-clamp-2"><?= htmlspecialchars($enrollment['title']) ?></h3>
                    <p class="text-xs text-gray-500 mt-1">Enrolled <?= formatDate($enrollment['enrolled_at']) ?></p>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mb-4">
                <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                    <span><?= $p['completed'] ?> / <?= $p['total'] ?> modules</span>
                    <span class="font-semibold <?= $p['percent'] >= 100 ? 'text-green-600' : 'text-candlelight-600' ?>"><?= $p['percent'] ?>%</span>
                </div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar-fill <?= $p['percent'] >= 100 ? '!from-green-500 !to-green-600' : '' ?>" data-percent="<?= $p['percent'] ?>"></div>
                </div>
            </div>

            <div class="flex gap-2 mt-auto">
                <?php if ($p['percent'] >= 100): ?>
                <a href="/clsn-lms/certificate.php" class="flex-1 btn-lms-secondary text-center text-sm py-2.5">
                    <i class="fas fa-certificate text-candlelight-500"></i> View Certificate
                </a>
                <?php else: ?>
                <a href="/clsn-lms/course.php?slug=<?= urlencode($enrollment['slug']) ?>" class="flex-1 btn-lms-primary text-center text-sm py-2.5">
                    <i class="fas fa-play-circle"></i> Continue
                </a>
                <?php endif; ?>
                <a href="/clsn-lms/course.php?slug=<?= urlencode($enrollment['slug']) ?>" class="px-3 py-2.5 border border-gray-200 rounded-xl text-gray-500 hover:bg-gray-50 transition-colors text-sm">
                    <i class="fas fa-list"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Tips -->
<div class="lms-card p-6 bg-gradient-to-r from-navy-50 to-navy-100 border border-navy-200">
    <h3 class="font-display font-bold text-navy-900 mb-3"><i class="fas fa-lightbulb text-candlelight-500 mr-2"></i>Learning Tips</h3>
    <div class="grid sm:grid-cols-3 gap-4">
        <?php foreach ([
            ['icon'=>'fas fa-calendar-check','title'=>'Set a Schedule','desc'=>'Study for 30 minutes daily for the best results.'],
            ['icon'=>'fas fa-book-reader','title'=>'Review Notes','desc'=>'Download the workbook PDFs after each module.'],
            ['icon'=>'fas fa-trophy','title'=>'Pass the Quizzes','desc'=>'A score of 70% or higher unlocks the next module.'],
        ] as $tip): ?>
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-candlelight-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                <i class="<?= $tip['icon'] ?> text-candlelight-600 text-sm"></i>
            </div>
            <div><p class="text-sm font-semibold text-navy-900"><?= $tip['title'] ?></p><p class="text-xs text-gray-500 mt-0.5"><?= $tip['desc'] ?></p></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include './includes/footer-dash.php'; ?>
<script src="/clsn-lms/js/main.js"></script>
