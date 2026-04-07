<?php
include_once './includes/db.php';
include_once './includes/auth.php';
include_once './includes/functions.php';

$slug   = trim($_GET['slug'] ?? '');
$course = $slug ? getCourseBySlug($conn, $slug) : null;
if (!$course) { header('Location: /clsn-lms/courses.php'); exit; }

$userId   = isLoggedIn() ? currentUserId() : 0;
$enrolled = $userId ? isEnrolled($conn, $userId, $course['id']) : false;
$modules  = getCourseModules($conn, $course['id']);
$progress = ($enrolled && $userId) ? getCourseProgress($conn, $userId, $course['id']) : null;

// Load per-module progress if enrolled
$moduleProgress = [];
if ($enrolled && $userId) {
    foreach ($modules as $mod) {
        $moduleProgress[$mod['id']] = getModuleProgress($conn, $userId, $mod['id']);
    }
}

// Find current module (first uncompleted)
$continueModule = null;
if ($enrolled) {
    foreach ($modules as $mod) {
        if (empty($moduleProgress[$mod['id']]['is_completed'])) {
            $continueModule = $mod;
            break;
        }
    }
}

$welcome      = isset($_GET['enrolled']) && $_GET['enrolled'] === '1';
$courseReset  = isset($_GET['reset'])    && $_GET['reset']    === '1';
$pageTitle    = htmlspecialchars($course['title']) . ' | Candlelight LMS';
include './includes/header-public.php';
?>

<?php if ($welcome): ?>
<div class="fixed top-24 left-1/2 -translate-x-1/2 z-50 bg-green-500 text-white px-6 py-3 rounded-2xl shadow-xl flex items-center gap-2 text-sm font-semibold animate-bounce-in" id="enrolled-toast">
    <i class="fas fa-check-circle"></i> Successfully enrolled! Let's start learning.
</div>
<script>setTimeout(() => { document.getElementById('enrolled-toast')?.remove(); }, 4000);</script>
<?php endif; ?>

<?php if ($courseReset): ?>
<div class="fixed top-24 left-1/2 -translate-x-1/2 z-50 bg-red-600 text-white px-6 py-4 rounded-2xl shadow-xl flex items-center gap-3 text-sm font-semibold max-w-md" id="reset-toast">
    <i class="fas fa-rotate-left text-lg"></i>
    <div>
        <div class="font-bold">Course Reset</div>
        <div class="font-normal opacity-90">You've used all attempts including the grace attempt. Your progress has been reset — please restart from Module 1.</div>
    </div>
    <button onclick="this.parentElement.remove()" class="ml-auto opacity-70 hover:opacity-100"><i class="fas fa-times"></i></button>
</div>
<script>setTimeout(() => { document.getElementById('reset-toast')?.remove(); }, 10000);</script>
<?php endif; ?>

<!-- Hero Banner -->
<section class="pt-32 pb-12 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700 text-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
            <a href="/clsn-lms/courses.php" class="hover:text-candlelight-400 transition-colors">Courses</a>
            <i class="fas fa-chevron-right text-xs"></i>
            <span class="text-gray-200"><?= htmlspecialchars($course['title']) ?></span>
        </nav>
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <div class="flex flex-wrap gap-2 mb-4">
                    <?php if ($course['is_free']): ?><span class="px-3 py-1 bg-green-500/20 text-green-300 border border-green-500/30 text-xs font-semibold rounded-full">FREE</span><?php endif; ?>
                    <span class="px-3 py-1 bg-candlelight-500/20 text-candlelight-300 border border-candlelight-500/30 text-xs font-semibold rounded-full"><?= htmlspecialchars($course['level']) ?></span>
                    <span class="px-3 py-1 bg-white/10 text-gray-300 border border-white/20 text-xs font-semibold rounded-full"><?= (int)$course['total_modules'] ?> Modules</span>
                </div>
                <h1 class="font-display text-4xl lg:text-5xl font-bold leading-tight mb-4"><?= htmlspecialchars($course['title']) ?></h1>
                <p class="text-gray-300 text-lg leading-relaxed mb-6"><?= htmlspecialchars($course['short_description']) ?></p>
                <div class="flex flex-wrap items-center gap-6 text-sm text-gray-300 mb-8">
                    <span><i class="fas fa-layer-group text-candlelight-400 mr-2"></i><?= (int)$course['total_modules'] ?> modules</span>
                    <span><i class="fas fa-clock text-candlelight-400 mr-2"></i><?= htmlspecialchars($course['duration']) ?></span>
                    <span><i class="fas fa-certificate text-candlelight-400 mr-2"></i>Certificate included</span>
                    <span><i class="fas fa-user text-candlelight-400 mr-2"></i><?= htmlspecialchars($course['instructor'] ?? 'Candlelight Foundation') ?></span>
                </div>

                <?php if ($enrolled): ?>
                <div class="flex flex-wrap gap-3 mb-4">
                    <?php if ($continueModule): ?>
                    <a href="/clsn-lms/module.php?id=<?= $continueModule['id'] ?>" class="inline-flex items-center gap-2 px-8 py-4 bg-candlelight-500 hover:bg-candlelight-600 text-white font-bold rounded-2xl transition-all shadow-lg">
                        <i class="fas fa-play-circle"></i> Continue Learning
                    </a>
                    <?php else: ?>
                    <a href="/clsn-lms/certificate.php" class="inline-flex items-center gap-2 px-8 py-4 bg-candlelight-500 hover:bg-candlelight-600 text-white font-bold rounded-2xl transition-all shadow-lg">
                        <i class="fas fa-award"></i> View Certificate
                    </a>
                    <?php endif; ?>
                    <a href="/clsn-lms/dashboard.php" class="inline-flex items-center gap-2 px-6 py-4 bg-white/10 hover:bg-white/20 text-white font-semibold rounded-2xl transition-all border border-white/20">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </div>
                <!-- Progress -->
                <?php if ($progress): ?>
                <div class="max-w-sm">
                    <div class="flex justify-between text-sm text-gray-300 mb-2">
                        <span><?= $progress['completed'] ?> / <?= $progress['total'] ?> modules completed</span>
                        <span class="text-candlelight-400 font-bold"><?= $progress['percent'] ?>%</span>
                    </div>
                    <div class="progress-bar-wrap"><div class="progress-bar-fill" data-percent="<?= $progress['percent'] ?>"></div></div>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="flex flex-wrap gap-3">
                    <?php if (isLoggedIn()): ?>
                    <form action="/clsn-lms/enroll.php" method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                        <button type="submit" class="inline-flex items-center gap-2 px-8 py-4 bg-candlelight-500 hover:bg-candlelight-600 text-white font-bold rounded-2xl transition-all shadow-lg hover:shadow-candlelight-500/40 hover:-translate-y-0.5">
                            <i class="fas fa-rocket"></i> Enroll Now (Free)
                        </button>
                    </form>
                    <?php else: ?>
                    <a href="/clsn-lms/register.php" class="inline-flex items-center gap-2 px-8 py-4 bg-candlelight-500 hover:bg-candlelight-600 text-white font-bold rounded-2xl transition-all shadow-lg">
                        <i class="fas fa-rocket"></i> Get Started (Free)
                    </a>
                    <a href="/clsn-lms/login.php" class="inline-flex items-center gap-2 px-6 py-4 bg-white/10 hover:bg-white/20 text-white font-semibold rounded-2xl transition-all border border-white/20">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats card -->
            <div class="lg:flex justify-end hidden">
                <div class="bg-white/10 backdrop-blur-sm rounded-3xl overflow-hidden border border-white/20 w-full max-w-sm">
                    <?php
                    $heroThumbSrc = '';
                    if (!empty($course['thumbnail']) && file_exists(__DIR__ . '/uploads/thumbnails/' . basename($course['thumbnail']))) {
                        $heroThumbSrc = '/clsn-lms/uploads/thumbnails/' . htmlspecialchars(basename($course['thumbnail']));
                    } elseif (!empty($course['youtube_url'])) {
                        preg_match('#(?:v=|youtu\.be/|embed/)([a-zA-Z0-9_-]{11})#', $course['youtube_url'], $hYtm);
                        if (!empty($hYtm[1])) {
                            $heroThumbSrc = 'https://img.youtube.com/vi/' . htmlspecialchars($hYtm[1]) . '/hqdefault.jpg';
                        }
                    }
                    ?>
                    <?php if ($heroThumbSrc): ?>
                    <div class="aspect-video w-full overflow-hidden">
                        <img src="<?= $heroThumbSrc ?>" alt="<?= htmlspecialchars($course['title']) ?>" class="w-full h-full object-cover">
                    </div>
                    <?php else: ?>
                    <div class="aspect-video w-full bg-gradient-to-br from-navy-700 to-navy-600 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-candlelight-400 text-5xl"></i>
                    </div>
                    <?php endif; ?>
                    <div class="p-8">
                    <h3 class="font-display font-bold text-xl mb-6">What You'll Learn</h3>
                    <ul class="space-y-3">
                        <?php foreach (array_slice($modules, 0, 5) as $mod): ?>
                        <li class="flex items-start gap-3 text-sm text-gray-300">
                            <span class="w-6 h-6 rounded-full bg-candlelight-500/20 text-candlelight-300 flex items-center justify-center text-xs flex-shrink-0 mt-0.5"><?= $mod['module_number'] ?></span>
                            <?= htmlspecialchars($mod['title']) ?>
                        </li>
                        <?php endforeach; ?>
                        <?php if (count($modules) > 5): ?>
                        <li class="text-sm text-gray-400 pl-9">+ <?= count($modules) - 5 ?> more modules</li>
                        <?php endif; ?>
                    </ul>
                    <div class="mt-6 pt-6 border-t border-white/10 grid grid-cols-3 gap-4 text-center">
                        <div><div class="text-2xl font-bold text-candlelight-400"><?= count($modules) ?></div><div class="text-xs text-gray-400">Modules</div></div>
                        <div><div class="text-2xl font-bold text-candlelight-400"><?= count($modules) ?></div><div class="text-xs text-gray-400">Quizzes</div></div>
                        <div><div class="text-2xl font-bold text-candlelight-400">1</div><div class="text-xs text-gray-400">Certificate</div></div>
                    </div>
                    </div><!-- /p-8 -->
                </div><!-- /stats-card -->
            </div>
        </div>
    </div>
</section>

<!-- Course Content -->
<section class="bg-white py-16 lg:py-0">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:h-full">
        <div class="grid lg:grid-cols-3 gap-12">
            <!-- Module List -->
            <div class="lg:col-span-2 lg:h-[calc(100vh-96px)] lg:overflow-y-auto lg:py-16 lg:pr-3 course-col-scroll">
                <?php if (!empty($course['description'])): ?>
                <div class="mb-10">
                    <h2 class="font-display text-2xl font-bold text-navy-900 mb-4">About This Course</h2>
                    <div class="text-gray-600 leading-relaxed"><?= $course['description'] ?></div>
                </div>
                <?php endif; ?>

                <?php
                // Course preview video
                $previewVidId = '';
                if (!empty($course['youtube_url'])) {
                    preg_match('#(?:v=|youtu\.be/|embed/)([a-zA-Z0-9_-]{11})#', $course['youtube_url'], $pvM);
                    $previewVidId = $pvM[1] ?? '';
                }
                ?>
                <?php if ($previewVidId): ?>
                <div class="mb-10">
                    <h2 class="font-display text-2xl font-bold text-navy-900 mb-4">Course Preview</h2>
                    <div class="relative aspect-video w-full rounded-2xl overflow-hidden bg-black group cursor-pointer" id="preview-wrap" onclick="playPreview('<?= htmlspecialchars($previewVidId) ?>')">
                        <!-- Thumbnail -->
                        <img src="https://img.youtube.com/vi/<?= htmlspecialchars($previewVidId) ?>/maxresdefault.jpg"
                             onerror="this.src='https://img.youtube.com/vi/<?= htmlspecialchars($previewVidId) ?>/hqdefault.jpg'"
                             alt="Course preview thumbnail"
                             class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" id="preview-thumb">
                        <!-- Overlay -->
                        <div class="absolute inset-0 bg-black/30 flex items-center justify-center" id="preview-overlay">
                            <div class="w-20 h-20 rounded-full bg-candlelight-500 flex items-center justify-center shadow-2xl transition-transform duration-200 group-hover:scale-110">
                                <i class="fas fa-play text-white text-2xl ml-1"></i>
                            </div>
                        </div>
                        <!-- Label -->
                        <div class="absolute bottom-4 left-4 bg-black/60 backdrop-blur-sm text-white text-xs font-semibold px-3 py-1.5 rounded-full" id="preview-label">
                            <i class="fas fa-play-circle mr-1 text-candlelight-400"></i> Watch Preview
                        </div>
                        <!-- iframe injected here on click -->
                    </div>
                </div>
                <script>
                function playPreview(id) {
                    var wrap = document.getElementById('preview-wrap');
                    wrap.onclick = null;
                    wrap.innerHTML = '<iframe src="https://www.youtube.com/embed/' + id + '?autoplay=1&rel=0" allow="autoplay; encrypted-media; fullscreen" allowfullscreen class="absolute inset-0 w-full h-full border-0"></iframe>';
                    wrap.style.cursor = 'default';
                }
                </script>
                <?php endif; ?>

                <div>
                    <h2 class="font-display text-2xl font-bold text-navy-900 mb-6">Course Curriculum</h2>
                    <div class="space-y-3">
                        <?php foreach ($modules as $i => $mod): ?>
                        <?php
                            $mp      = $moduleProgress[$mod['id']] ?? [];
                            $done    = !empty($mp['is_completed']);
                            $unlocked = $enrolled ? isModuleUnlocked($conn, $userId, $mod, $modules) : false;
                        ?>
                        <div class="rounded-2xl border <?= $done ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white' ?> overflow-hidden">
                            <div class="flex items-center gap-4 p-5">
                                <!-- Number/Status icon -->
                                <div class="flex-shrink-0 w-10 h-10 rounded-xl <?= $done ? 'bg-green-500' : ($unlocked ? 'bg-candlelight-100' : 'bg-gray-100') ?> flex items-center justify-center">
                                    <?php if ($done): ?>
                                        <i class="fas fa-check text-white text-sm"></i>
                                    <?php elseif ($unlocked): ?>
                                        <span class="text-candlelight-700 font-bold text-sm"><?= $mod['module_number'] ?></span>
                                    <?php else: ?>
                                        <i class="fas fa-lock text-gray-400 text-sm"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-semibold text-gray-400 mb-0.5">Module <?= $mod['module_number'] ?></div>
                                    <h3 class="font-semibold text-gray-900 text-sm leading-snug"><?= htmlspecialchars($mod['title']) ?></h3>
                                    <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                        <?php if ($mod['video_url']): ?><span><i class="fas fa-video mr-1"></i>Video</span><?php endif; ?>
                                        <span><i class="fas fa-file-pdf mr-1"></i>Notes & PDFs</span>
                                        <span><i class="fas fa-question-circle mr-1"></i>Quiz</span>
                                        <?php if ($mod['duration_minutes']): ?><span><i class="fas fa-clock mr-1"></i><?= $mod['duration_minutes'] ?> min</span><?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($enrolled && $unlocked): ?>
                                <a href="/clsn-lms/module.php?id=<?= $mod['id'] ?>" class="flex-shrink-0 inline-flex items-center gap-1.5 px-4 py-2 <?= $done ? 'bg-green-100 text-green-700' : 'bg-candlelight-500 text-white' ?> rounded-xl text-xs font-semibold hover:opacity-90 transition-all">
                                    <?= $done ? '<i class="fas fa-redo"></i> Review' : '<i class="fas fa-play"></i> Start' ?>
                                </a>
                                <?php elseif (!$enrolled): ?>
                                <span class="flex-shrink-0 px-4 py-2 bg-gray-100 text-gray-400 rounded-xl text-xs font-semibold cursor-not-allowed">
                                    <i class="fas fa-lock mr-1"></i> Enroll to Access
                                </span>
                                <?php else: ?>
                                <span class="flex-shrink-0 px-4 py-2 bg-gray-100 text-gray-400 rounded-xl text-xs font-semibold cursor-not-allowed">
                                    <i class="fas fa-lock mr-1"></i> Locked
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1 lg:h-[calc(100vh-96px)] lg:overflow-y-auto lg:py-16 course-col-scroll">
                <div class="space-y-4">
                    <!-- What's Included -->
                    <div class="lms-card p-6">
                        <h3 class="font-display font-bold text-navy-900 mb-4">What's Included</h3>
                        <ul class="space-y-3 text-sm text-gray-600">
                            <li class="flex items-center gap-3"><i class="fas fa-video text-candlelight-500 w-5"></i>Video lessons per module</li>
                            <li class="flex items-center gap-3"><i class="fas fa-file-pdf text-candlelight-500 w-5"></i>Downloadable workbooks</li>
                            <li class="flex items-center gap-3"><i class="fas fa-sticky-note text-candlelight-500 w-5"></i>In-depth reading notes</li>
                            <li class="flex items-center gap-3"><i class="fas fa-question-circle text-candlelight-500 w-5"></i>Module quizzes with feedback</li>
                            <li class="flex items-center gap-3"><i class="fas fa-comments text-candlelight-500 w-5"></i>Q&amp;A section per module</li>
                            <li class="flex items-center gap-3"><i class="fas fa-certificate text-candlelight-500 w-5"></i>Certificate on completion</li>
                            <li class="flex items-center gap-3"><i class="fas fa-infinity text-candlelight-500 w-5"></i>Lifetime access</li>
                        </ul>
                    </div>

                    <!-- CTA if not enrolled -->
                    <?php if (!$enrolled): ?>
                    <div class="lms-card p-6 bg-gradient-to-br from-candlelight-50 to-candlelight-100 border border-candlelight-200">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-navy-900 mb-1"><?= $course['is_free'] ? 'Free' : 'Paid' ?></div>
                            <p class="text-gray-500 text-sm mb-4">No credit card required</p>
                            <?php if (isLoggedIn()): ?>
                            <form action="/clsn-lms/enroll.php" method="POST">
                                <?= csrfField() ?>
                                <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                <button type="submit" class="btn-lms-primary w-full">
                                    <i class="fas fa-rocket"></i> Enroll Now
                                </button>
                            </form>
                            <?php else: ?>
                            <a href="/clsn-lms/register.php" class="btn-lms-primary w-full text-center block">
                                <i class="fas fa-rocket"></i> Get Started Free
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include './includes/footer-public.php'; ?>
<script src="/clsn-lms/js/main.js"></script>
