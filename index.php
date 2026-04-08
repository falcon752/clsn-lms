<?php
include_once './includes/db.php';
include_once './includes/auth.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

include_once './includes/functions.php';
$courses = getActiveCourses($conn);
$pageTitle = 'Candlelight LMS | Learn. Grow. Shine.';
include './includes/header-public.php';
?>

<!-- HERO -->
<section class="relative min-h-[92vh] flex items-center justify-center overflow-hidden bg-white">
    <div class="absolute top-0 right-0 w-[700px] h-[700px] bg-candlelight-100 rounded-full blur-3xl opacity-50 translate-x-1/3 -translate-y-1/4"></div>
    <div class="absolute bottom-0 left-0 w-[500px] h-[500px] bg-navy-100 rounded-full blur-3xl opacity-30 -translate-x-1/4 translate-y-1/4"></div>

    <div class="relative z-10 max-w-7xl mx-auto px-6 py-20">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <!-- Left -->
            <div class="space-y-8">
                <div class="inline-flex items-center gap-2 px-5 py-2 rounded-full border border-navy-200 bg-navy-50">
                    <div class="w-2 h-2 rounded-full bg-candlelight-500 animate-pulse"></div>
                    <span class="text-sm font-semibold text-navy-700">Now Open for Enrollment</span>
                </div>

                <h1 class="font-display text-5xl md:text-6xl lg:text-7xl font-bold text-navy-900 leading-tight">
                    Learn.<br>
                    <span class="text-candlelight-600">Understand.</span><br>
                    Make a Difference.
                </h1>

                <p class="text-lg text-gray-600 leading-relaxed max-w-xl">
                    Evidence-based online courses for parents, caregivers, and educators supporting children with Autism, ADHD, and other special needs.
                </p>

                <div class="flex flex-wrap gap-4">
                    <a href="/courses.php" class="btn-lms-primary">
                        <i class="fas fa-graduation-cap"></i> Browse Courses
                    </a>
                    <a href="/register.php" class="btn-lms-secondary">
                        <i class="fas fa-user-plus"></i> Create Free Account
                    </a>
                </div>

                <!-- Trust badges -->
                <div class="flex flex-wrap gap-6 pt-4">
                    <div class="flex items-center gap-2 text-gray-600 text-sm">
                        <i class="fas fa-certificate text-candlelight-500"></i>
                        Certificate of Completion
                    </div>
                    <div class="flex items-center gap-2 text-gray-600 text-sm">
                        <i class="fas fa-mobile-alt text-candlelight-500"></i>
                        Mobile Friendly
                    </div>
                    <div class="flex items-center gap-2 text-gray-600 text-sm">
                        <i class="fas fa-infinity text-candlelight-500"></i>
                        Learn at Your Pace
                    </div>
                </div>
            </div>

            <!-- Right: Course Preview -->
            <div class="relative">
                <div class="bg-white rounded-3xl shadow-2xl p-8 border border-gray-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-candlelight-500 to-candlelight-700 flex items-center justify-center">
                            <i class="fas fa-puzzle-piece text-white text-lg"></i>
                        </div>
                        <div>
                            <div class="font-bold text-navy-900">Understanding Autism</div>
                            <div class="text-sm text-gray-500">Flagship Course &middot; 8 Modules</div>
                        </div>
                        <span class="ml-auto px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">FREE</span>
                    </div>

                    <!-- Module preview list -->
                    <div class="space-y-3 mb-6">
                        <?php
                        $previews = [
                            ['1', 'Introduction to Autism Spectrum Disorder'],
                            ['2', 'Early Signs and Diagnosis'],
                            ['3', 'Communication Strategies'],
                            ['4', 'ABA Therapy Fundamentals'],
                        ];
                        foreach ($previews as $i => $p): ?>
                        <div class="flex items-center gap-3 py-2 px-3 rounded-xl bg-gray-50">
                            <span class="w-7 h-7 rounded-full bg-candlelight-100 text-candlelight-700 text-xs font-bold flex items-center justify-center flex-shrink-0"><?= $p[0] ?></span>
                            <span class="text-sm text-gray-700 font-medium"><?= htmlspecialchars($p[1]) ?></span>
                            <?php if ($i === 0): ?><i class="fas fa-play-circle text-candlelight-500 ml-auto text-sm"></i><?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        <div class="text-center text-sm text-gray-400 pt-1">+ 4 more modules</div>
                    </div>

                    <a href="/courses.php" class="block text-center px-6 py-3 bg-navy-900 text-white rounded-2xl font-semibold hover:bg-navy-800 transition-colors">
                        View Full Course →
                    </a>
                </div>

                <!-- Floating badge -->
                <div class="absolute -bottom-4 -left-4 bg-white rounded-2xl shadow-xl px-5 py-3 flex items-center gap-3 border border-gray-100">
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="fas fa-certificate text-green-600 text-lg"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-gray-800">Certificate Included</div>
                        <div class="text-xs text-gray-500">Upon completion</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-6">
        <div class="text-center mb-16">
            <span class="inline-block px-5 py-2 bg-candlelight-100 text-candlelight-700 font-semibold rounded-full text-sm mb-4">How It Works</span>
            <h2 class="font-display text-4xl md:text-5xl font-bold text-navy-900">
                Your Learning Journey
            </h2>
        </div>
        <div class="grid md:grid-cols-4 gap-8">
            <?php
            $steps = [
                ['1', 'fa-user-plus',       'Create Account',    'Sign up for free in under a minute.'],
                ['2', 'fa-book-open',        'Enroll in a Course','Browse and enroll in our expert-designed courses.'],
                ['3', 'fa-play-circle',      'Learn at Your Pace','Watch videos, read notes, download resources.'],
                ['4', 'fa-certificate',      'Get Certified',     'Pass all quizzes and earn your certificate.'],
            ];
            foreach ($steps as $step): ?>
            <div class="text-center lms-card bg-white p-8 shadow-md">
                <div class="w-14 h-14 bg-gradient-to-br from-candlelight-500 to-candlelight-700 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas <?= $step[1] ?> text-white text-2xl"></i>
                </div>
                <div class="text-xs font-bold text-candlelight-500 mb-2">STEP <?= $step[0] ?></div>
                <h3 class="font-display font-bold text-xl text-navy-900 mb-3"><?= $step[2] ?></h3>
                <p class="text-gray-600 text-sm leading-relaxed"><?= $step[3] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FEATURED COURSES -->
<?php if (!empty($courses)): ?>
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-6">
        <div class="flex items-end justify-between mb-12">
            <div>
                <span class="inline-block px-5 py-2 bg-candlelight-100 text-candlelight-700 font-semibold rounded-full text-sm mb-4">Available Courses</span>
                <h2 class="font-display text-4xl font-bold text-navy-900">Start Learning Today</h2>
            </div>
            <a href="/courses.php" class="text-candlelight-600 font-semibold hover:text-candlelight-700 transition-colors hidden md:block">View All →</a>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($courses as $course): ?>
            <div class="lms-card bg-white border border-gray-100 shadow-md overflow-hidden">
                <?php
                $iThumb = '';
                if (!empty($course['thumbnail']) && file_exists(__DIR__ . '/uploads/thumbnails/' . basename($course['thumbnail']))) {
                    $iThumb = '/uploads/thumbnails/' . htmlspecialchars(basename($course['thumbnail']));
                } elseif (!empty($course['youtube_url'])) {
                    preg_match('#(?:v=|youtu\.be/|embed/)([a-zA-Z0-9_-]{11})#', $course['youtube_url'], $iYtm);
                    if (!empty($iYtm[1])) {
                        $iThumb = 'https://img.youtube.com/vi/' . htmlspecialchars($iYtm[1]) . '/hqdefault.jpg';
                    }
                }
                ?>
                <div class="h-48 overflow-hidden <?= $iThumb ? '' : 'course-thumb-ph' ?>">
                    <?php if ($iThumb): ?>
                    <img src="<?= $iThumb ?>" alt="<?= htmlspecialchars($course['title']) ?>" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                    <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-br from-navy-800 to-navy-600 flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-candlelight-400 text-5xl"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-3 py-1 bg-candlelight-100 text-candlelight-700 text-xs font-bold rounded-full"><?= htmlspecialchars($course['level']) ?></span>
                        <?php if ($course['is_free']): ?>
                        <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-bold rounded-full">FREE</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="font-display font-bold text-xl text-navy-900 mb-2 leading-tight"><?= htmlspecialchars($course['title']) ?></h3>
                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($course['short_description']) ?></p>
                    <div class="flex items-center justify-between text-sm text-gray-500 mb-4">
                        <span><i class="fas fa-layer-group mr-1 text-candlelight-500"></i><?= $course['total_modules'] ?> Modules</span>
                        <span><i class="fas fa-clock mr-1 text-candlelight-500"></i><?= htmlspecialchars($course['duration']) ?></span>
                        <span><i class="fas fa-signal mr-1 text-candlelight-500"></i><?= htmlspecialchars($course['level']) ?></span>
                    </div>
                    <a href="/course.php?slug=<?= urlencode($course['slug']) ?>" class="block text-center px-5 py-3 bg-navy-900 text-white rounded-xl font-semibold hover:bg-navy-800 transition-colors text-sm">
                        View Course →
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CTA BANNER -->
<section class="py-20 bg-gradient-to-r from-navy-900 to-navy-800 relative overflow-hidden">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-96 h-96 rounded-full bg-candlelight-500 blur-3xl translate-x-1/3 -translate-y-1/3"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 rounded-full bg-candlelight-400 blur-3xl -translate-x-1/3 translate-y-1/3"></div>
    </div>
    <div class="max-w-4xl mx-auto px-6 text-center relative z-10">
        <h2 class="font-display text-4xl md:text-5xl font-bold text-white mb-6">
            Ready to Make a<br><span class="text-candlelight-400">Real Difference?</span>
        </h2>
        <p class="text-gray-300 text-lg mb-10 max-w-2xl mx-auto">Join hundreds of parents, caregivers, and educators who are transforming their understanding and support of children with special needs.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/register.php" class="px-8 py-4 bg-candlelight-500 text-white rounded-full font-bold text-lg hover:bg-candlelight-600 transition-all hover:scale-105 shadow-lg">
                <i class="fas fa-user-plus mr-2"></i>Sign Up Free
            </a>
            <a href="/login.php" class="px-8 py-4 bg-white/10 backdrop-blur border-2 border-white/30 text-white rounded-full font-bold text-lg hover:bg-white/20 transition-all">
                Already have an account?
            </a>
        </div>
    </div>
</section>

<?php include './includes/footer-public.php'; ?>
<script src="/js/main.js"></script>
