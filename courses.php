<?php
include_once './includes/db.php';
include_once './includes/auth.php';
include_once './includes/functions.php';

$courses = getActiveCourses($conn);
$userId  = isLoggedIn() ? currentUserId() : 0;

// Tag enrollment status per course
foreach ($courses as &$course) {
    $course['enrolled'] = $userId ? isEnrolled($conn, $userId, $course['id']) : false;
    if ($course['enrolled']) {
        $course['progress'] = getCourseProgress($conn, $userId, $course['id']);
    }
}
unset($course);

$pageTitle = 'Courses | Candlelight LMS';
include './includes/header-public.php';
?>

<section class="pt-32 pb-8 bg-gradient-to-br from-navy-900 via-navy-800 to-navy-700 text-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 text-center">
        <span class="inline-block px-4 py-1.5 bg-candlelight-500/20 text-candlelight-300 rounded-full text-sm font-semibold mb-4 border border-candlelight-500/30">Our Courses</span>
        <h1 class="font-display text-4xl md:text-5xl font-bold mb-4">Learn. Grow. Make an Impact.</h1>
        <p class="text-gray-300 text-lg max-w-2xl mx-auto">Evidence-based courses crafted by Candlelight Foundation specialists, designed for parents, caregivers, educators, and healthcare professionals.</p>
    </div>
</section>

<section class="bg-white py-12 shadow-sm sticky top-16 z-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-wrap items-center gap-4">
        <span class="text-gray-500 text-sm font-medium"><?= count($courses) ?> course<?= count($courses) !== 1 ? 's' : '' ?> available</span>
        <div class="flex flex-wrap gap-2">
            <span class="px-3 py-1 bg-candlelight-100 text-candlelight-700 text-xs font-semibold rounded-full border border-candlelight-200">All Free</span>
            <span class="px-3 py-1 bg-navy-100 text-navy-700 text-xs font-semibold rounded-full border border-navy-200">Certificate Included</span>
        </div>
    </div>
</section>

<section class="bg-gray-50 py-16">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <?php if (empty($courses)): ?>
        <div class="text-center py-20">
            <i class="fas fa-graduation-cap text-6xl text-gray-200 mb-4 block"></i>
            <p class="text-gray-500 text-lg">No courses available yet. Check back soon!</p>
        </div>
        <?php else: ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($courses as $course): ?>
            <?php $enrolled = $course['enrolled'] ?? false; ?>
            <article class="lms-card group flex flex-col">
                <!-- Thumbnail -->
                <div class="relative overflow-hidden rounded-t-2xl course-thumb-ph aspect-video bg-gradient-to-br from-navy-800 to-navy-600 flex items-center justify-center">
                    <?php if (!empty($course['thumbnail']) && file_exists(__DIR__ . '/uploads/thumbnails/' . basename($course['thumbnail']))): ?>
                        <img src="/clsn-lms/uploads/thumbnails/<?= htmlspecialchars(basename($course['thumbnail'])) ?>" alt="<?= htmlspecialchars($course['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                        <div class="text-center text-white/70 p-6">
                            <i class="fas fa-graduation-cap text-4xl mb-3 text-candlelight-400 block"></i>
                            <span class="text-xs font-semibold tracking-wider uppercase text-white/60"><?= htmlspecialchars($course['level']) ?></span>
                        </div>
                    <?php endif; ?>
                    <!-- Badges -->
                    <div class="absolute top-3 left-3 flex gap-2">
                        <?php if ($course['is_free']): ?>
                        <span class="px-2.5 py-1 bg-green-500 text-white text-xs font-bold rounded-lg">FREE</span>
                        <?php endif; ?>
                        <?php if ($enrolled): ?>
                        <span class="px-2.5 py-1 bg-candlelight-500 text-white text-xs font-bold rounded-lg"><i class="fas fa-check mr-1"></i>Enrolled</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6 flex flex-col flex-1">
                    <div class="flex items-center gap-3 text-xs text-gray-500 mb-3">
                        <span class="flex items-center gap-1"><i class="fas fa-layer-group text-candlelight-500"></i> <?= (int)$course['total_modules'] ?> modules</span>
                        <span class="flex items-center gap-1"><i class="fas fa-clock text-candlelight-500"></i> <?= htmlspecialchars($course['duration']) ?></span>
                        <span class="flex items-center gap-1"><i class="fas fa-signal text-candlelight-500"></i> <?= htmlspecialchars($course['level']) ?></span>
                    </div>

                    <h3 class="font-display font-bold text-lg text-navy-900 leading-snug mb-2 group-hover:text-candlelight-600 transition-colors"><?= htmlspecialchars($course['title']) ?></h3>
                    <p class="text-gray-500 text-sm leading-relaxed flex-1 mb-4"><?= htmlspecialchars($course['short_description']) ?></p>

                    <!-- Instructor -->
                    <div class="flex items-center gap-2 mb-4 pb-4 border-b border-gray-100">
                        <div class="w-7 h-7 rounded-full bg-candlelight-100 flex items-center justify-center"><i class="fas fa-user text-candlelight-600 text-xs"></i></div>
                        <span class="text-xs text-gray-500"><?= htmlspecialchars($course['instructor'] ?? 'Candlelight Foundation') ?></span>
                    </div>

                    <?php if ($enrolled && isset($course['progress'])): ?>
                    <!-- Progress bar -->
                    <div class="mb-4">
                        <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                            <span><?= $course['progress']['completed'] ?> / <?= $course['progress']['total'] ?> modules completed</span>
                            <span class="font-semibold text-candlelight-600"><?= $course['progress']['percent'] ?>%</span>
                        </div>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" data-percent="<?= $course['progress']['percent'] ?>"></div>
                        </div>
                    </div>
                    <a href="/clsn-lms/course.php?slug=<?= urlencode($course['slug']) ?>" class="btn-lms-primary text-center text-sm">
                        <i class="fas fa-play-circle"></i> Continue Learning
                    </a>
                    <?php else: ?>
                    <div class="flex gap-3">
                        <a href="/clsn-lms/course.php?slug=<?= urlencode($course['slug']) ?>" class="flex-1 btn-lms-secondary text-center text-sm py-2.5">View Course</a>
                        <?php if (isLoggedIn()): ?>
                        <form action="/clsn-lms/enroll.php" method="POST" class="flex-1">
                            <?= csrfField() ?>
                            <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                            <button type="submit" class="btn-lms-primary w-full text-sm py-2.5"><i class="fas fa-plus-circle"></i> Enroll Free</button>
                        </form>
                        <?php else: ?>
                        <a href="/clsn-lms/register.php" class="flex-1 btn-lms-primary text-center text-sm py-2.5"><i class="fas fa-plus-circle"></i> Enroll Free</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA -->
<?php if (!isLoggedIn()): ?>
<section class="bg-gradient-to-r from-navy-900 to-navy-800 py-16 text-white text-center">
    <div class="max-w-2xl mx-auto px-4">
        <h2 class="font-display text-3xl font-bold mb-3">Ready to begin your journey?</h2>
        <p class="text-gray-300 mb-8">Create a free account and get instant access to all courses.</p>
        <a href="/clsn-lms/register.php" class="inline-flex items-center gap-2 px-8 py-4 bg-candlelight-500 hover:bg-candlelight-600 text-white font-bold rounded-2xl transition-all shadow-lg hover:shadow-candlelight-500/30 hover:-translate-y-1">
            <i class="fas fa-rocket"></i> Get Started (It's Free)
        </a>
    </div>
</section>
<?php endif; ?>

<?php include './includes/footer-public.php'; ?>
<script src="/clsn-lms/js/main.js"></script>
