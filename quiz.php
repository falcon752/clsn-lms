<?php
include_once './includes/db.php';
include_once './includes/auth.php';
include_once './includes/functions.php';

requireStudent();

$moduleId = (int)($_GET['module_id'] ?? 0);
if (!$moduleId) { header('Location: /clsn-lms/dashboard.php'); exit; }

$module = getModule($conn, $moduleId);
if (!$module) { header('Location: /clsn-lms/dashboard.php'); exit; }

$userId   = currentUserId();
$courseId = (int)$module['course_id'];

if (!isEnrolled($conn, $userId, $courseId)) {
    header("Location: /clsn-lms/course.php?slug={$module['course_slug']}");
    exit;
}

$allModules = getCourseModules($conn, $courseId);
$quiz       = getQuizByModule($conn, $moduleId);
if (!$quiz) { header("Location: /clsn-lms/module.php?id={$moduleId}"); exit; }

$quizAttempts  = getQuizAttemptCount($conn, $userId, $quiz['id']);
$alreadyPassed = hasPassed($conn, $userId, $quiz['id']);

// ── Grace-attempt logic ───────────────────────────────────────────────────────
// Once all regular attempts are used and the user still hasn't passed,
// grant ONE extra attempt that unlocks 48 hours after the last failed try.
$maxRegular      = (int)$quiz['max_attempts'];
$regularExhausted = !$alreadyPassed && $quizAttempts >= $maxRegular;
$graceUnlocked   = false;
$graceSecondsLeft = 0;
$lastFailedAt    = null;

if ($regularExhausted) {
    $lastFailedAt = getLastFailedAttemptTime($conn, $userId, $quiz['id']);
    if ($lastFailedAt) {
        $unlockAt         = strtotime($lastFailedAt) + (48 * 3600);
        $graceSecondsLeft = max(0, $unlockAt - time());
        $graceUnlocked    = $graceSecondsLeft === 0;
    }
}

// Total effective attempts = regular + (1 grace if exhausted)
$effectiveMax = $regularExhausted ? $maxRegular + 1 : $maxRegular;
$attemptsLeft = max(0, $effectiveMax - $quizAttempts);
$canAttempt   = !$alreadyPassed && ($attemptsLeft > 0) && (!$regularExhausted || $graceUnlocked);

$questions = getQuizQuestions($conn, $quiz['id']);

// ── Handle Quiz Submission ────────────────────────────────────────────────────
$result = null;
// Capture before submission so we know if this was the grace attempt
$wasGraceAttempt = $regularExhausted && $graceUnlocked;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $error = 'Security check failed. Please try again.';
    } elseif (!$canAttempt) {
        header('Location: /clsn-lms/quiz.php?module_id=' . $moduleId);
        exit;
    } else {
        $correct = 0;
        $total   = count($questions);
        $answers = [];

        foreach ($questions as $q) {
            $selected = (int)($_POST['q_' . $q['id']] ?? 0);
            $answers[$q['id']] = $selected;
            // Check if selected option is correct
            foreach ($q['options'] as $opt) {
                if ($opt['id'] === $selected && $opt['is_correct']) {
                    $correct++;
                    break;
                }
            }
        }

        $percentage = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
        $passed     = $percentage >= $quiz['pass_percentage'];

        // Save attempt
        $stmt = $conn->prepare("INSERT INTO lms_quiz_attempts (user_id, quiz_id, module_id, score, total_questions, percentage, passed) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('iiiiidi', $userId, $quiz['id'], $moduleId, $correct, $total, $percentage, $passed);
        $stmt->execute();
        $stmt->close();

        // Update progress
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("
            INSERT INTO lms_module_progress (user_id, module_id, course_id, quiz_passed)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE quiz_passed = ?
        ");
        $stmt->bind_param('iiiii', $userId, $moduleId, $courseId, $passed, $passed);
        $stmt->execute();
        $stmt->close();

        if ($passed) {
            markModuleComplete($conn, $userId, $moduleId, $courseId);

            // Check if all modules complete → issue certificate
            $courseProgress = getCourseProgress($conn, $userId, $courseId);
            if ($courseProgress['percent'] >= 100) {
                issueCertificate($conn, $userId, $courseId);
            }
        } elseif ($wasGraceAttempt && !$passed) {
            // Grace attempt also failed — wipe all progress so the user restarts the course
            resetCourseProgress($conn, $userId, $courseId);
            header('Location: /clsn-lms/course.php?slug=' . urlencode($module['course_slug']) . '&reset=1');
            exit;
        }

        // Reload attempts
        $quizAttempts     = getQuizAttemptCount($conn, $userId, $quiz['id']);
        $alreadyPassed    = $passed;
        $regularExhausted = !$alreadyPassed && $quizAttempts >= $maxRegular;
        $graceUnlocked    = false;
        $graceSecondsLeft = 0;
        if ($regularExhausted) {
            $lastFailedAt = getLastFailedAttemptTime($conn, $userId, $quiz['id']);
            if ($lastFailedAt) {
                $unlockAt         = strtotime($lastFailedAt) + (48 * 3600);
                $graceSecondsLeft = max(0, $unlockAt - time());
                $graceUnlocked    = $graceSecondsLeft === 0;
            }
        }
        $effectiveMax = $regularExhausted ? $maxRegular + 1 : $maxRegular;
        $attemptsLeft = max(0, $effectiveMax - $quizAttempts);
        $canAttempt   = !$alreadyPassed && ($attemptsLeft > 0) && (!$regularExhausted || $graceUnlocked);

        $result = [
            'correct'    => $correct,
            'total'      => $total,
            'percentage' => $percentage,
            'passed'     => $passed,
            'answers'    => $answers,
        ];
    }
}

// Find next module
$nextModule = null;
foreach ($allModules as $i => $m) {
    if ($m['id'] === $moduleId && isset($allModules[$i + 1])) {
        $nextModule = $allModules[$i + 1];
        break;
    }
}

$dashPageTitle = 'Quiz: ' . $module['title'];
include './includes/header-dash.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 flex-wrap">
    <a href="/clsn-lms/dashboard.php" class="hover:text-candlelight-600 transition-colors">Dashboard</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/clsn-lms/module.php?id=<?= $moduleId ?>" class="hover:text-candlelight-600 transition-colors">Module <?= $module['module_number'] ?></a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <span class="text-gray-800 font-medium">Quiz</span>
</nav>

<div class="max-w-2xl mx-auto">

    <!-- Quiz Header -->
    <div class="lms-card p-6 mb-6">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-candlelight-100 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-pencil-alt text-candlelight-600 text-xl"></i>
            </div>
            <div class="flex-1">
                <h1 class="font-display text-2xl font-bold text-navy-900"><?= htmlspecialchars($quiz['title']) ?></h1>
                <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-gray-500">
                    <span><i class="fas fa-question-circle text-candlelight-500 mr-1"></i><?= count($questions) ?> questions</span>
                    <span><i class="fas fa-percentage text-candlelight-500 mr-1"></i>Pass: <?= $quiz['pass_percentage'] ?>%</span>
                    <?php if (!$alreadyPassed): ?>
                    <span><i class="fas fa-redo text-candlelight-500 mr-1"></i>
                        <?php if ($regularExhausted && !$graceUnlocked && $graceSecondsLeft > 0): ?>
                            Grace attempt available in <?= gmdate('H\h i\m', $graceSecondsLeft) ?>
                        <?php elseif ($regularExhausted && $graceUnlocked): ?>
                            1 grace attempt available
                        <?php else: ?>
                            <?= $attemptsLeft ?> attempt<?= $attemptsLeft !== 1 ? 's' : '' ?> remaining
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($result): ?>
    <!-- ── Results ─────────────────────────────────────────────────── -->
    <div class="lms-card p-8 mb-6 text-center">
        <?php if ($result['passed']): ?>
        <div class="w-24 h-24 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-trophy text-5xl text-green-500"></i>
        </div>
        <h2 class="font-display text-3xl font-bold text-green-600 mb-2">Congratulations!</h2>
        <p class="text-gray-500 mb-4">You passed the quiz with a score of</p>
        <?php else: ?>
        <div class="w-24 h-24 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-times-circle text-5xl text-red-400"></i>
        </div>
        <h2 class="font-display text-3xl font-bold text-red-500 mb-2">Not Passed</h2>
        <p class="text-gray-500 mb-4">You scored</p>
        <?php endif; ?>

        <div class="inline-flex items-center justify-center w-28 h-28 rounded-full border-8 <?= $result['passed'] ? 'border-green-400' : 'border-red-300' ?> mb-4">
            <span class="text-3xl font-bold <?= $result['passed'] ? 'text-green-600' : 'text-red-500' ?>"><?= round($result['percentage']) ?>%</span>
        </div>

        <p class="text-gray-500 text-sm mb-6">
            <?= $result['correct'] ?> correct out of <?= $result['total'] ?> questions
            (Pass mark: <?= $quiz['pass_percentage'] ?>%)
        </p>

        <?php if ($result['passed']): ?>
        <div class="flex flex-wrap gap-3 justify-center">
            <?php if ($nextModule): ?>
            <a href="/clsn-lms/module.php?id=<?= $nextModule['id'] ?>" class="btn-lms-primary">
                <i class="fas fa-arrow-right"></i> Next Module
            </a>
            <?php else: ?>
            <a href="/clsn-lms/certificate.php" class="btn-lms-primary">
                <i class="fas fa-award"></i> Get Your Certificate
            </a>
            <?php endif; ?>
            <a href="/clsn-lms/module.php?id=<?= $moduleId ?>" class="btn-lms-secondary">
                <i class="fas fa-book"></i> Review Module
            </a>
        </div>
        <?php elseif ($canAttempt): ?>
        <div class="flex flex-wrap gap-3 justify-center">
            <a href="/clsn-lms/quiz.php?module_id=<?= $moduleId ?>" class="btn-lms-primary">
                <i class="fas fa-redo"></i> Retake Quiz (<?= $attemptsLeft ?> left)
            </a>
            <a href="/clsn-lms/module.php?id=<?= $moduleId ?>" class="btn-lms-secondary">
                <i class="fas fa-book"></i> Review Notes
            </a>
        </div>
        <?php else: ?>
        <div class="lms-alert lms-alert-error mt-4 text-left">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <?php if ($regularExhausted && !$graceUnlocked && $graceSecondsLeft > 0): ?>
                You've used all <?= $maxRegular ?> attempts. Your <strong>grace attempt</strong> unlocks in <strong><?= gmdate('H\h i\m', $graceSecondsLeft) ?></strong>. Come back then!
            <?php elseif ($regularExhausted && $graceUnlocked): ?>
                Your grace attempt is ready — <a href="/clsn-lms/quiz.php?module_id=<?= $moduleId ?>" class="underline font-semibold">try again now</a>.
            <?php else: ?>
                You've used all available attempts. Please contact us to reset your quiz.
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Answer Review -->
    <div class="lms-card p-6">
        <h3 class="font-display font-bold text-navy-900 text-lg mb-4">Answer Review</h3>
        <div class="space-y-5">
            <?php foreach ($questions as $qi => $q): ?>
            <?php
                $selectedId = $result['answers'][$q['id']] ?? 0;
                $isCorrect  = false;
                foreach ($q['options'] as $opt) {
                    if ($opt['id'] === $selectedId && $opt['is_correct']) {
                        $isCorrect = true;
                        break;
                    }
                }
            ?>
            <div class="rounded-xl p-4 <?= $isCorrect ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' ?>">
                <p class="font-semibold text-sm text-gray-800 mb-3">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full <?= $isCorrect ? 'bg-green-500' : 'bg-red-400' ?> text-white text-xs mr-2"><?= $qi + 1 ?></span>
                    <?= htmlspecialchars($q['question']) ?>
                </p>
                <div class="space-y-2">
                    <?php foreach ($q['options'] as $opt): ?>
                    <?php
                        $isSelected = $opt['id'] === $selectedId;
                        $isOptCorrect = $opt['is_correct'];
                        $cls = '';
                        if ($isSelected && $isOptCorrect) $cls = 'bg-green-100 border-green-400 text-green-800';
                        elseif ($isSelected && !$isOptCorrect) $cls = 'bg-red-100 border-red-400 text-red-800';
                        elseif (!$isSelected && $isOptCorrect) $cls = 'bg-green-50 border-green-300 text-green-700';
                        else $cls = 'bg-white border-gray-200 text-gray-500';
                    ?>
                    <div class="flex items-center gap-3 px-3 py-2.5 rounded-lg border text-sm <?= $cls ?>">
                        <?php if ($isSelected && $isOptCorrect): ?><i class="fas fa-check-circle text-green-500 flex-shrink-0"></i>
                        <?php elseif ($isSelected && !$isOptCorrect): ?><i class="fas fa-times-circle text-red-400 flex-shrink-0"></i>
                        <?php elseif ($isOptCorrect): ?><i class="fas fa-check text-green-400 flex-shrink-0"></i>
                        <?php else: ?><i class="far fa-circle text-gray-300 flex-shrink-0"></i><?php endif; ?>
                        <?= htmlspecialchars($opt['text']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php elseif (!$canAttempt && !$alreadyPassed): ?>
    <div class="lms-card p-12 text-center">
        <?php if ($regularExhausted && !$graceUnlocked && $graceSecondsLeft > 0): ?>
        <i class="fas fa-hourglass-half text-candlelight-400 text-4xl mb-4 block"></i>
        <h3 class="font-display text-xl font-bold text-gray-800 mb-2">Grace Attempt Locked</h3>
        <p class="text-gray-500 mb-2">You've used all <?= $maxRegular ?> regular attempts.</p>
        <p class="text-gray-600 font-semibold mb-6">Your <span class="text-candlelight-600">bonus grace attempt</span> unlocks in:</p>
        <div class="inline-block bg-candlelight-50 border border-candlelight-200 rounded-2xl px-8 py-4 mb-6">
            <span class="font-mono text-3xl font-bold text-candlelight-700" id="grace-countdown"><?= gmdate('H:i:s', $graceSecondsLeft) ?></span>
        </div>
        <script>
        (function() {
            var s = <?= $graceSecondsLeft ?>;
            var el = document.getElementById('grace-countdown');
            function pad(n) { return String(n).padStart(2,'0'); }
            var t = setInterval(function() {
                if (s <= 0) { clearInterval(t); location.reload(); return; }
                s--;
                var h = Math.floor(s/3600), m = Math.floor((s%3600)/60), sec = s%60;
                el.textContent = pad(h)+':'+pad(m)+':'+pad(sec);
            }, 1000);
        })();
        </script>
        <p class="text-xs text-gray-400 mb-6">Review the module notes in the meantime to prepare.</p>
        <?php elseif ($regularExhausted && $graceUnlocked): ?>
        <i class="fas fa-unlock text-green-500 text-4xl mb-4 block"></i>
        <h3 class="font-display text-xl font-bold text-gray-800 mb-2">Grace Attempt Ready!</h3>
        <p class="text-gray-500 mb-6">Your bonus attempt is now available. Good luck!</p>
        <?php else: ?>
        <i class="fas fa-ban text-red-400 text-4xl mb-4 block"></i>
        <h3 class="font-display text-xl font-bold text-gray-800 mb-2">No Attempts Remaining</h3>
        <p class="text-gray-500 mb-4">You've used all <?= $maxRegular ?> attempts for this quiz.</p>
        <?php endif; ?>
        <div class="flex gap-3 justify-center flex-wrap">
            <?php if ($regularExhausted && $graceUnlocked): ?>
            <a href="/clsn-lms/quiz.php?module_id=<?= $moduleId ?>" class="btn-lms-primary inline-flex">
                <i class="fas fa-redo"></i> Take Grace Attempt
            </a>
            <?php endif; ?>
            <a href="/clsn-lms/module.php?id=<?= $moduleId ?>" class="btn-lms-secondary inline-flex">
                <i class="fas fa-book"></i> Review Module Notes
            </a>
        </div>
    </div>

    <?php elseif ($alreadyPassed): ?>
    <div class="lms-card p-12 text-center">
        <i class="fas fa-trophy text-green-500 text-5xl mb-4 block"></i>
        <h3 class="font-display text-xl font-bold text-green-700 mb-2">Already Passed!</h3>
        <p class="text-gray-500 mb-4">You've already passed this quiz. Move on to the next module.</p>
        <div class="flex gap-3 justify-center flex-wrap">
            <?php if ($nextModule): ?>
            <a href="/clsn-lms/module.php?id=<?= $nextModule['id'] ?>" class="btn-lms-primary">
                <i class="fas fa-arrow-right"></i> Next Module
            </a>
            <?php else: ?>
            <a href="/clsn-lms/certificate.php" class="btn-lms-primary">
                <i class="fas fa-award"></i> Get Certificate
            </a>
            <?php endif; ?>
            <a href="/clsn-lms/module.php?id=<?= $moduleId ?>" class="btn-lms-secondary">
                <i class="fas fa-book"></i> Review Module
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Quiz Form ───────────────────────────────────────────────── -->
    <form method="POST" action="" id="quiz-form">
        <?= csrfField() ?>
        <input type="hidden" name="submit_quiz" value="1">

        <?php if (isset($error)): ?>
        <div class="lms-alert lms-alert-error mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($quizAttempts > 0): ?>
        <div class="lms-alert lms-alert-error mb-4">
            <i class="fas fa-info-circle mr-2"></i>
            <?php if ($regularExhausted && $graceUnlocked): ?>
                This is your <strong>grace attempt</strong>. Make it count — review the module notes before submitting!
            <?php else: ?>
                Previous attempt(s) not passed. <?= $attemptsLeft ?> attempt<?= $attemptsLeft !== 1 ? 's' : '' ?> remaining.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="space-y-6">
            <?php foreach ($questions as $qi => $q): ?>
            <div class="lms-card p-6" data-question="q_<?= $q['id'] ?>">
                <p class="font-semibold text-gray-800 mb-4 leading-snug">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-candlelight-100 text-candlelight-700 font-bold text-sm mr-2"><?= $qi + 1 ?></span>
                    <?= htmlspecialchars($q['question']) ?>
                </p>
                <div class="space-y-3">
                    <?php foreach ($q['options'] as $opt): ?>
                    <label class="quiz-option">
                        <input type="radio" name="q_<?= $q['id'] ?>" value="<?= $opt['id'] ?>" class="sr-only">
                        <span class="option-indicator"></span>
                        <span class="option-text text-sm"><?= htmlspecialchars($opt['text']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-red-500 text-xs mt-2 error-msg hidden"><i class="fas fa-exclamation-circle mr-1"></i>Please select an answer.</p>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex items-center justify-between gap-4 mt-8">
            <a href="/clsn-lms/module.php?id=<?= $moduleId ?>" class="btn-lms-secondary">
                <i class="fas fa-arrow-left"></i> Back to Module
            </a>
            <button type="submit" class="btn-lms-primary px-8">
                <i class="fas fa-paper-plane"></i> Submit Quiz
            </button>
        </div>
    </form>
    <?php endif; ?>

</div><!-- /max-w -->

<?php include './includes/footer-dash.php'; ?>
<script src="/clsn-lms/js/main.js"></script>
