<?php
include_once './includes/db.php';
include_once './includes/auth.php';
include_once './includes/functions.php';

requireLogin();

$moduleId = (int)($_GET['id'] ?? 0);
$module   = $moduleId ? getModule($conn, $moduleId) : null;
if (!$module) { header('Location: /clsn-lms/courses.php'); exit; }

$userId   = currentUserId();
$courseId = (int)$module['course_id'];
$course   = getCourse($conn, $courseId);

// Must be enrolled
if (!isEnrolled($conn, $userId, $courseId)) {
    header("Location: /clsn-lms/course.php?slug={$module['course_slug']}");
    exit;
}

$allModules     = getCourseModules($conn, $courseId);
$moduleProgress = getModuleProgress($conn, $userId, $moduleId);
$pdfs           = getModulePdfs($conn, $moduleId);
$quiz           = getQuizByModule($conn, $moduleId);
$quizPassed     = $quiz ? hasPassed($conn, $userId, $quiz['id']) : false;
$quizAttempts   = $quiz ? getQuizAttemptCount($conn, $userId, $quiz['id']) : 0;
$unlocked       = isModuleUnlocked($conn, $userId, $module, $allModules);

if (!$unlocked) { header("Location: /clsn-lms/course.php?slug={$module['course_slug']}"); exit; }

// Q&A for this module
$qaStmt = $conn->prepare("
    SELECT q.*, CONCAT(u.first_name,' ',u.last_name) AS user_name, u.first_name
    FROM lms_module_qa q
    JOIN lms_users u ON u.id = q.user_id
    WHERE q.module_id = ?
    ORDER BY q.created_at ASC
");
$qaStmt->bind_param('i', $moduleId);
$qaStmt->execute();
$qaList = $qaStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qaStmt->close();

// Next / Previous module
$prevModule = null; $nextModule = null;
foreach ($allModules as $i => $m) {
    if ($m['id'] === $moduleId) {
        $prevModule = $allModules[$i - 1] ?? null;
        $nextModule = $allModules[$i + 1] ?? null;
        break;
    }
}

// Embed URL
$embedUrl = '';
if (!empty($module['video_url'])) {
    $embedUrl = getEmbedUrl($module['video_url'], $module['video_type']);
}

$dashPageTitle = 'Module ' . $module['module_number'] . ': ' . $module['title'];
include './includes/header-dash.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 flex-wrap">
    <a href="/clsn-lms/dashboard.php" class="hover:text-candlelight-600 transition-colors">Dashboard</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/clsn-lms/course.php?slug=<?= urlencode($module['course_slug']) ?>" class="hover:text-candlelight-600 transition-colors"><?= htmlspecialchars($module['course_title']) ?></a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <span class="text-gray-800 font-medium">Module <?= $module['module_number'] ?></span>
</nav>

<div class="flex gap-8 items-start">

    <!-- ── Module Sidebar ───────────────────────────────────────────── -->
    <aside class="module-sidebar hidden lg:block">
        <div class="lms-card overflow-hidden">
            <div class="px-4 py-3 bg-navy-900 text-white">
                <div class="text-xs font-semibold text-gray-300 uppercase tracking-wider">Course Modules</div>
                <div class="text-sm font-bold mt-0.5 truncate"><?= htmlspecialchars($module['course_title']) ?></div>
            </div>
            <?php
            $courseProgress = getCourseProgress($conn, $userId, $courseId);
            ?>
            <div class="px-4 py-3 border-b border-gray-100">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span><?= $courseProgress['completed'] ?> / <?= $courseProgress['total'] ?> completed</span>
                    <span class="font-semibold text-candlelight-600"><?= $courseProgress['percent'] ?>%</span>
                </div>
                <div class="progress-bar-wrap h-1.5"><div class="progress-bar-fill" data-percent="<?= $courseProgress['percent'] ?>"></div></div>
            </div>
            <div class="py-2">
                <?php foreach ($allModules as $m): ?>
                <?php
                    $mp       = getModuleProgress($conn, $userId, $m['id']);
                    $isActive = $m['id'] === $moduleId;
                    $isDone   = !empty($mp['is_completed']);
                    $isLocked = !isModuleUnlocked($conn, $userId, $m, $allModules);
                    $cls      = $isActive ? 'active' : ($isDone ? 'completed' : ($isLocked ? 'locked' : ''));
                ?>
                <?php if (!$isLocked): ?>
                <a href="/clsn-lms/module.php?id=<?= $m['id'] ?>" class="module-nav-item <?= $cls ?>">
                <?php else: ?>
                <div class="module-nav-item <?= $cls ?>">
                <?php endif; ?>
                    <span class="module-nav-num">
                        <?php if ($isDone): ?><i class="fas fa-check text-green-600 text-xs"></i>
                        <?php elseif ($isLocked): ?><i class="fas fa-lock text-gray-400 text-xs"></i>
                        <?php else: ?><?= $m['module_number'] ?><?php endif; ?>
                    </span>
                    <span class="module-nav-title line-clamp-2"><?= htmlspecialchars($m['title']) ?></span>
                <?php if (!$isLocked): ?></a><?php else: ?></div><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </aside>

    <!-- ── Module Content ───────────────────────────────────────────── -->
    <div class="flex-1 min-w-0 space-y-6">

        <!-- Module Header -->
        <div class="lms-card p-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <div class="text-xs font-semibold text-candlelight-600 uppercase tracking-wider mb-1">Module <?= $module['module_number'] ?> of <?= count($allModules) ?></div>
                    <h1 class="font-display text-2xl font-bold text-navy-900"><?= htmlspecialchars($module['title']) ?></h1>
                    <?php if ($module['description']): ?>
                    <p class="text-gray-500 mt-2 text-sm"><?= htmlspecialchars($module['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <?php if (!empty($moduleProgress['is_completed'])): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-100 text-green-700 rounded-lg text-xs font-bold">
                        <i class="fas fa-check-circle"></i> Completed
                    </span>
                    <?php endif; ?>
                    <?php if ($module['duration_minutes']): ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-semibold">
                        <i class="fas fa-clock"></i> ~<?= $module['duration_minutes'] ?> min
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Video Section -->
        <?php if ($embedUrl || $module['video_type'] === 'file'): ?>
        <div class="lms-card overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center gap-2">
                <i class="fas fa-play-circle text-candlelight-500"></i>
                <span class="font-semibold text-sm text-gray-700">Video Lesson</span>
                <?php if (!empty($moduleProgress['video_watched'])): ?>
                <span class="ml-auto text-xs text-green-600 font-semibold flex items-center gap-1"><i class="fas fa-check-circle"></i> Watched</span>
                <?php endif; ?>
            </div>
            <div class="relative bg-black">
                <?php if ($embedUrl && in_array($module['video_type'], ['youtube','vimeo'])): ?>
                <div class="video-wrapper">
                    <iframe src="<?= htmlspecialchars($embedUrl) ?>" title="<?= htmlspecialchars($module['title']) ?>"
                        frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="w-full"></iframe>
                </div>
                <?php elseif ($module['video_type'] === 'file' && !empty($module['video_url'])): ?>
                <div class="video-wrapper">
                    <video controls class="w-full h-full" id="module-video">
                        <source src="/clsn-lms/uploads/videos/<?= htmlspecialchars(basename($module['video_url'])) ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
                <?php else: ?>
                <div class="aspect-video bg-gradient-to-br from-navy-800 to-navy-900 flex items-center justify-center">
                    <div class="text-center text-white/60 p-8">
                        <i class="fas fa-video-slash text-4xl mb-3 block"></i>
                        <p>Video will be added soon.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- Mark Watched -->
            <?php if (empty($moduleProgress['video_watched'])): ?>
            <div class="p-4 bg-candlelight-50 border-t border-candlelight-200">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <p class="text-sm text-candlelight-800 flex items-center gap-2">
                        <i class="fas fa-info-circle"></i>
                        After watching the video, mark it as watched to unlock the quiz.
                    </p>
                    <button id="mark-watched-btn"
                        data-module-id="<?= $moduleId ?>"
                        data-course-id="<?= $courseId ?>"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-candlelight-500 hover:bg-candlelight-600 text-white font-semibold rounded-xl text-sm transition-all">
                        <i class="fas fa-eye"></i> Mark as Watched
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="p-4 bg-green-50 border-t border-green-200">
                <p class="text-sm text-green-700 flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Video watched — you can now take the quiz.
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tabs: Notes / Resources / Q&A / Quiz -->
        <div class="lms-card overflow-hidden" data-tab-container="module">
            <!-- Tab Nav -->
            <div class="flex border-b border-gray-200 overflow-x-auto">
                <button class="tab-btn active flex items-center gap-2 px-5 py-3.5 text-sm font-semibold whitespace-nowrap" data-tab-target="notes">
                    <i class="fas fa-book-open"></i> Notes
                </button>
                <?php if (!empty($pdfs)): ?>
                <button class="tab-btn flex items-center gap-2 px-5 py-3.5 text-sm font-semibold whitespace-nowrap" data-tab-target="resources">
                    <i class="fas fa-file-pdf"></i> Resources <span class="bg-candlelight-100 text-candlelight-700 text-xs px-1.5 py-0.5 rounded-md ml-1"><?= count($pdfs) ?></span>
                </button>
                <?php endif; ?>
                <button class="tab-btn flex items-center gap-2 px-5 py-3.5 text-sm font-semibold whitespace-nowrap" data-tab-target="qa">
                    <i class="fas fa-comments"></i> Q&amp;A <span class="bg-navy-100 text-navy-700 text-xs px-1.5 py-0.5 rounded-md ml-1"><?= count($qaList) ?></span>
                </button>
                <?php if ($quiz): ?>
                <button class="tab-btn flex items-center gap-2 px-5 py-3.5 text-sm font-semibold whitespace-nowrap" data-tab-target="quiz">
                    <i class="fas fa-question-circle"></i> Quiz
                    <?php if ($quizPassed): ?><span class="bg-green-100 text-green-700 text-xs px-1.5 py-0.5 rounded-md ml-1">Passed ✓</span><?php endif; ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Notes Panel -->
            <div class="tab-panel active p-6 lg:p-8" id="tab-notes">
                <?php if (!empty($module['notes'])): ?>
                <div class="notes-content max-w-3xl"><?= $module['notes'] ?></div>
                <?php else: ?>
                <p class="text-gray-400 text-center py-8 italic">Notes for this module will be available soon.</p>
                <?php endif; ?>
            </div>

            <!-- Resources Panel -->
            <?php if (!empty($pdfs)): ?>
            <div class="tab-panel p-6" id="tab-resources" style="display:none">
                <h3 class="font-semibold text-gray-700 mb-4">Downloadable Resources</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <?php foreach ($pdfs as $pdf): ?>
                    <a href="/clsn-lms/uploads/pdfs/<?= htmlspecialchars(basename($pdf['file_path'])) ?>"
                       download
                       class="pdf-card group">
                        <div class="pdf-card-icon"><i class="fas fa-file-pdf"></i></div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-gray-800 group-hover:text-candlelight-600 transition-colors truncate"><?= htmlspecialchars($pdf['title']) ?></div>
                            <?php if ($pdf['file_size']): ?><div class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($pdf['file_size']) ?></div><?php endif; ?>
                        </div>
                        <i class="fas fa-download text-gray-300 group-hover:text-candlelight-500 transition-colors flex-shrink-0"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Q&A Panel -->
            <div class="tab-panel p-6" id="tab-qa" style="display:none">
                <div id="qa-list" class="space-y-4 mb-8">
                    <?php if (empty($qaList)): ?>
                    <p class="text-center text-gray-400 py-6 italic" id="qa-empty">Be the first to ask a question!</p>
                    <?php endif; ?>
                    <?php foreach ($qaList as $qa): ?>
                    <div class="qa-item">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-full bg-candlelight-100 flex items-center justify-center font-bold text-candlelight-700 text-sm flex-shrink-0">
                                <?= strtoupper(substr($qa['first_name'], 0, 1)) ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-sm text-gray-800"><?= htmlspecialchars($qa['user_name']) ?></span>
                                    <span class="text-xs text-gray-400"><?= timeAgo($qa['created_at']) ?></span>
                                </div>
                                <p class="text-gray-700 text-sm"><?= nl2br(htmlspecialchars($qa['question'])) ?></p>
                                <?php if ($qa['answer']): ?>
                                <div class="qa-answer">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-user-shield text-candlelight-600 text-xs"></i>
                                        <span class="text-xs font-semibold text-candlelight-700">Candlelight Foundation</span>
                                    </div>
                                    <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($qa['answer'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Q&A Form -->
                <div class="border-t border-gray-100 pt-6">
                    <h4 class="font-semibold text-gray-700 mb-3">Ask a Question</h4>
                    <form id="qa-form" class="flex gap-3 items-start">
                        <input type="hidden" name="module_id" value="<?= $moduleId ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <textarea name="question" id="qa-question" rows="3" required
                            class="lms-input flex-1 resize-none text-sm"
                            placeholder="Type your question here..."></textarea>
                        <button type="submit" class="btn-lms-primary flex-shrink-0 self-start text-sm py-3 px-5">
                            <i class="fas fa-paper-plane"></i> Post
                        </button>
                    </form>
                    <p id="qa-status" class="text-sm mt-2 hidden"></p>
                </div>
            </div>

            <!-- Quiz Panel -->
            <?php if ($quiz): ?>
            <div class="tab-panel p-6" id="tab-quiz" style="display:none">
                <?php if ($quizPassed): ?>
                <div class="text-center py-8">
                    <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-trophy text-4xl text-green-500"></i>
                    </div>
                    <h3 class="font-display text-2xl font-bold text-green-700 mb-2">Quiz Passed!</h3>
                    <p class="text-gray-500 mb-6">You've already passed this module's quiz. Keep going!</p>
                    <?php if ($nextModule): ?>
                    <?php $nextUnlocked = isModuleUnlocked($conn, $userId, $nextModule, $allModules); ?>
                    <a href="/clsn-lms/module.php?id=<?= $nextModule['id'] ?>" class="btn-lms-primary inline-flex mx-auto">
                        <i class="fas fa-arrow-right"></i> Next Module
                    </a>
                    <?php else: ?>
                    <a href="/clsn-lms/certificate.php" class="btn-lms-primary inline-flex mx-auto">
                        <i class="fas fa-award"></i> Get Your Certificate
                    </a>
                    <?php endif; ?>
                </div>
                <?php elseif (empty($moduleProgress['video_watched']) && !empty($module['video_url'])): ?>
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-lock text-4xl mb-3 block opacity-30"></i>
                    <p class="font-semibold">Watch the video first to unlock the quiz.</p>
                </div>
                <?php else: ?>
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h3 class="font-display text-xl font-bold text-navy-900"><?= htmlspecialchars($quiz['title']) ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Pass mark: <?= $quiz['pass_percentage'] ?>% &mdash; <?= $quiz['max_attempts'] - $quizAttempts ?> attempt<?= ($quiz['max_attempts'] - $quizAttempts) !== 1 ? 's' : '' ?> remaining</p>
                    </div>
                    <?php if ($quizAttempts > 0 && !$quizPassed): ?>
                    <span class="px-3 py-1.5 bg-red-50 text-red-600 border border-red-200 rounded-lg text-xs font-semibold">Not Passed Yet</span>
                    <?php endif; ?>
                </div>

                <?php if ($quizAttempts >= $quiz['max_attempts'] && !$quizPassed): ?>
                <div class="lms-alert lms-alert-error">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    You've used all <?= $quiz['max_attempts'] ?> attempts. Please contact support to reset your quiz.
                </div>
                <?php else: ?>
                <a href="/clsn-lms/quiz.php?module_id=<?= $moduleId ?>" class="btn-lms-primary inline-flex">
                    <i class="fas fa-pencil-alt"></i> Start Quiz Now
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="flex items-center justify-between gap-4">
            <?php if ($prevModule): ?>
            <a href="/clsn-lms/module.php?id=<?= $prevModule['id'] ?>" class="flex items-center gap-2 px-5 py-3 bg-white border border-gray-200 rounded-2xl text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:border-gray-300 transition-all">
                <i class="fas fa-chevron-left text-xs"></i> <span class="hidden sm:inline"><?= htmlspecialchars($prevModule['title']) ?></span><span class="sm:hidden">Previous</span>
            </a>
            <?php else: ?><div></div><?php endif; ?>

            <?php if ($nextModule): ?>
            <?php $nextUnlocked = isModuleUnlocked($conn, $userId, $nextModule, $allModules); ?>
            <?php if ($nextUnlocked): ?>
            <a href="/clsn-lms/module.php?id=<?= $nextModule['id'] ?>" class="flex items-center gap-2 px-5 py-3 bg-candlelight-500 hover:bg-candlelight-600 rounded-2xl text-sm font-semibold text-white transition-all shadow-md">
                <span class="hidden sm:inline"><?= htmlspecialchars($nextModule['title']) ?></span><span class="sm:hidden">Next</span> <i class="fas fa-chevron-right text-xs"></i>
            </a>
            <?php else: ?>
            <span class="flex items-center gap-2 px-5 py-3 bg-gray-100 rounded-2xl text-sm font-semibold text-gray-400 cursor-not-allowed">
                <i class="fas fa-lock text-xs"></i> Complete quiz to unlock
            </span>
            <?php endif; ?>
            <?php elseif (!empty($moduleProgress['is_completed'])): ?>
            <a href="/clsn-lms/certificate.php" class="flex items-center gap-2 px-5 py-3 bg-candlelight-500 hover:bg-candlelight-600 rounded-2xl text-sm font-semibold text-white transition-all shadow-md">
                <i class="fas fa-award"></i> Get Certificate
            </a>
            <?php endif; ?>
        </div>

    </div><!-- /module content -->
</div><!-- /grid -->

<?php include './includes/footer-dash.php'; ?>
<script src="/clsn-lms/js/main.js"></script>
