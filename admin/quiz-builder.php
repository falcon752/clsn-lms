<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$moduleId   = (int)($_GET['module_id'] ?? 0);
$isOverview = !$moduleId;

// ── Overview mode: list all modules grouped by course ─────────────────────
if ($isOverview) {
    $allCourses = $conn->query("SELECT * FROM lms_courses ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    $allMods = $conn->query("
        SELECT m.id, m.module_number, m.title AS module_title, m.course_id, m.is_active,
               q.id AS quiz_id, q.pass_percentage, q.max_attempts,
               (SELECT COUNT(*) FROM lms_quiz_questions qq WHERE qq.quiz_id = q.id) AS question_count
        FROM lms_modules m
        LEFT JOIN lms_quizzes q ON q.module_id = m.id
        ORDER BY m.course_id ASC, m.sort_order ASC
    ")->fetch_all(MYSQLI_ASSOC);
    $modsByCourse = [];
    foreach ($allMods as $m) { $modsByCourse[$m['course_id']][] = $m; }
    $adminPageTitle = 'Quiz Builder';
    include './includes/header.php';
    ?>

    <div class="flex items-center justify-between mb-6">
        <p class="text-gray-500 text-sm">Select a module to build or edit its quiz.</p>
    </div>

    <div class="space-y-6">
        <?php foreach ($allCourses as $c): ?>
        <?php $mods = $modsByCourse[$c['id']] ?? []; ?>
        <div class="lms-card overflow-hidden">
            <!-- Course Header -->
            <div class="flex items-center gap-3 px-5 py-4 bg-navy-900 text-white">
                <div class="w-8 h-8 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-graduation-cap text-candlelight-400 text-sm"></i>
                </div>
                <div>
                    <div class="font-display font-bold text-sm leading-tight"><?= htmlspecialchars($c['title']) ?></div>
                    <div class="text-xs text-gray-400 mt-0.5"><?= count($mods) ?> module<?= count($mods) !== 1 ? 's' : '' ?></div>
                </div>
            </div>
            <!-- Modules + Quiz status -->
            <?php if (empty($mods)): ?>
            <div class="px-5 py-6 text-center text-gray-400 text-sm">No modules yet for this course.</div>
            <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($mods as $m): ?>
                <div class="flex items-center gap-4 px-5 py-3.5">
                    <div class="w-8 h-8 rounded-lg <?= $m['is_active'] ? 'bg-candlelight-100' : 'bg-gray-100' ?> flex items-center justify-center flex-shrink-0">
                        <span class="font-bold text-xs <?= $m['is_active'] ? 'text-candlelight-700' : 'text-gray-400' ?>"><?= $m['module_number'] ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($m['module_title']) ?></span>
                        <div class="flex flex-wrap items-center gap-3 mt-0.5 text-xs">
                            <?php if ($m['quiz_id']): ?>
                            <span class="text-green-600 font-semibold"><i class="fas fa-check-circle mr-1"></i><?= $m['question_count'] ?> question<?= $m['question_count'] !== 1 ? 's' : '' ?> &middot; Pass <?= $m['pass_percentage'] ?>%</span>
                            <?php else: ?>
                            <span class="text-gray-400"><i class="fas fa-minus-circle mr-1"></i>No quiz yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="/clsn-lms/admin/quiz-builder.php?module_id=<?= $m['id'] ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 <?= $m['quiz_id'] ? 'bg-candlelight-100 text-candlelight-700 hover:bg-candlelight-200' : 'bg-navy-100 text-navy-700 hover:bg-navy-200' ?> rounded-xl text-xs font-semibold transition-colors flex-shrink-0">
                        <i class="fas <?= $m['quiz_id'] ? 'fa-pencil-alt' : 'fa-plus-circle' ?>"></i>
                        <?= $m['quiz_id'] ? 'Edit Quiz' : 'Create Quiz' ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($allCourses)): ?>
        <div class="lms-card p-12 text-center text-gray-400">No courses found.</div>
        <?php endif; ?>
    </div>

    <?php include './includes/footer.php'; ?>
    <?php exit; ?>
<?php } // end overview

// Per-module mode: fetch module + parent course
$mq = $conn->prepare("
    SELECT m.*, c.title AS course_title
    FROM lms_modules m
    JOIN lms_courses c ON c.id = m.course_id
    WHERE m.id = ?
");
$mq->bind_param('i', $moduleId);
$mq->execute();
$module = $mq->get_result()->fetch_assoc();
$mq->close();
if (!$module) { header('Location: /clsn-lms/admin/quiz-builder.php'); exit; }

// Get or create quiz
$quiz = $conn->prepare("SELECT * FROM lms_quizzes WHERE module_id=?");
$quiz->bind_param('i', $moduleId);
$quiz->execute();
$quiz = $quiz->get_result()->fetch_assoc();

$msg = '';
$err = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } elseif (isset($_POST['create_quiz'])) {
        $quizTitle = 'Module ' . $module['module_number'] . ' Quiz';
        $stmt = $conn->prepare("INSERT INTO lms_quizzes (module_id, title, pass_percentage, max_attempts) VALUES (?, ?, 70, 3)");
        $stmt->bind_param('is', $moduleId, $quizTitle);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: /clsn-lms/admin/quiz-builder.php?module_id={$moduleId}&created=1");
            exit;
        } else {
            $err = 'Could not create quiz: ' . $conn->error;
            $stmt->close();
        }
    } elseif (isset($_POST['save_quiz_settings']) && $quiz) {
        $pass = max(1, min(100, (int)($_POST['pass_percentage'] ?? 70)));
        $max  = max(1, (int)($_POST['max_attempts'] ?? 3));
        $stmt = $conn->prepare("UPDATE lms_quizzes SET pass_percentage=?, max_attempts=? WHERE id=?");
        $stmt->bind_param('iii', $pass, $max, $quiz['id']);
        $stmt->execute();
        $stmt->close();
        $quiz['pass_percentage'] = $pass;
        $quiz['max_attempts']    = $max;
        $msg = 'Quiz settings saved.';
    } elseif (isset($_POST['add_question']) && $quiz) {
        $qText = trim($_POST['question_text'] ?? '');
        if (empty($qText)) { $err = 'Question text is required.'; }
        else {
            $order = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM lms_quiz_questions WHERE quiz_id={$quiz['id']}")->fetch_assoc()['n'];
            $stmt  = $conn->prepare("INSERT INTO lms_quiz_questions (quiz_id, question_text, sort_order) VALUES (?,?,?)");
            $stmt->bind_param('isi', $quiz['id'], $qText, $order);
            $stmt->execute();
            $qid = $conn->insert_id;
            $stmt->close();
            // Insert options
            for ($i = 1; $i <= 4; $i++) {
                $oText    = trim($_POST["option_{$i}"] ?? '');
                $isCorr   = ((int)($_POST['correct'] ?? 0) === $i) ? 1 : 0;
                if (!empty($oText)) {
                    $s = $conn->prepare("INSERT INTO lms_quiz_options (question_id, option_text, is_correct, sort_order) VALUES (?,?,?,?)");
                    $s->bind_param('isii', $qid, $oText, $isCorr, $i);
                    $s->execute();
                    $s->close();
                }
            }
            $msg = 'Question added!';
        }
    } elseif (isset($_POST['delete_question'])) {
        $qid = (int)($_POST['question_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM lms_quiz_questions WHERE id=? AND quiz_id=?");
        $stmt->bind_param('ii', $qid, $quiz['id']);
        $stmt->execute();
        $stmt->close();
        $msg = 'Question deleted.';
    }
}

if (isset($_GET['created'])) $msg = 'Quiz created! Now add your questions below.';

// Reload questions
$questions = [];
if ($quiz) {
    $qs = $conn->prepare("SELECT q.*, (SELECT COUNT(*) FROM lms_quiz_options WHERE question_id=q.id) AS opt_count FROM lms_quiz_questions q WHERE q.quiz_id=? ORDER BY q.sort_order ASC");
    $qs->bind_param('i', $quiz['id']);
    $qs->execute();
    $qRows = $qs->get_result()->fetch_all(MYSQLI_ASSOC);
    $qs->close();
    foreach ($qRows as $qr) {
        $os = $conn->prepare("SELECT * FROM lms_quiz_options WHERE question_id=? ORDER BY sort_order ASC");
        $os->bind_param('i', $qr['id']);
        $os->execute();
        $qr['options'] = $os->get_result()->fetch_all(MYSQLI_ASSOC);
        $os->close();
        $questions[] = $qr;
    }
}

$adminPageTitle = 'Quiz Builder: ' . $module['title'];
include './includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 flex-wrap">
    <a href="/clsn-lms/admin/courses.php" class="hover:text-candlelight-600 transition-colors">Courses</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/clsn-lms/admin/modules.php?course_id=<?= $module['course_id'] ?>" class="hover:text-candlelight-600 transition-colors"><?= htmlspecialchars($module['course_title']) ?></a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/clsn-lms/admin/quiz-builder.php" class="hover:text-candlelight-600 transition-colors">Quiz Builder</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <span class="text-gray-800 font-medium">Module <?= $module['module_number'] ?> Quiz</span>
</nav>

<?php if ($msg): ?><div class="lms-alert lms-alert-success mb-5"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="lms-alert lms-alert-error mb-5"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

<?php if (!$quiz): ?>
<div class="lms-card p-10 text-center max-w-md">
    <div class="w-16 h-16 rounded-2xl bg-candlelight-50 flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-question-circle text-candlelight-500 text-3xl"></i>
    </div>
    <h3 class="font-display font-bold text-navy-900 text-lg mb-2">No Quiz Yet</h3>
    <p class="text-gray-500 text-sm mb-6">This module doesn't have a quiz. Create one to let students test their knowledge after completing the lesson.</p>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="create_quiz" value="1">
        <button type="submit" class="btn-lms-primary">
            <i class="fas fa-plus-circle"></i> Create Quiz for this Module
        </button>
    </form>
</div>
<?php else: ?>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- Quiz Settings -->
    <div class="lg:col-span-1">
        <div class="lms-card p-5 mb-5">
            <h3 class="font-semibold text-gray-700 mb-4">Quiz Settings</h3>
            <form method="POST" class="space-y-3">
                <?= csrfField() ?><input type="hidden" name="save_quiz_settings" value="1">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Pass Percentage (%)</label>
                    <input type="number" name="pass_percentage" class="lms-input text-sm" min="1" max="100" value="<?= $quiz['pass_percentage'] ?>">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Max Attempts</label>
                    <input type="number" name="max_attempts" class="lms-input text-sm" min="1" value="<?= $quiz['max_attempts'] ?>">
                </div>
                <button type="submit" class="btn-lms-primary w-full text-sm py-2.5"><i class="fas fa-save"></i> Save Settings</button>
            </form>
        </div>
        <div class="lms-card p-4 bg-candlelight-50 border-candlelight-200">
            <p class="text-xs text-candlelight-800"><i class="fas fa-info-circle mr-1 text-candlelight-600"></i>
            <strong><?= count($questions) ?></strong> question<?= count($questions) !== 1 ? 's' : '' ?> total. Current pass threshold: <strong><?= $quiz['pass_percentage'] ?>%</strong> (&ge;<?= ceil(count($questions) * $quiz['pass_percentage'] / 100) ?> correct).</p>
        </div>
    </div>

    <!-- Questions List + Add Form -->
    <div class="lg:col-span-2 space-y-5">

        <!-- Existing Questions -->
        <div class="lms-card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
                <h3 class="font-semibold text-gray-700 text-sm">Current Questions (<?= count($questions) ?>)</h3>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach ($questions as $qi => $q): ?>
                <div class="p-5">
                    <div class="flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-candlelight-100 text-candlelight-700 font-bold text-xs flex items-center justify-center flex-shrink-0 mt-0.5"><?= $qi+1 ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 mb-2"><?= htmlspecialchars($q['question_text']) ?></p>
                            <div class="grid grid-cols-2 gap-2">
                                <?php foreach ($q['options'] as $opt): ?>
                                <div class="flex items-center gap-2 text-xs <?= $opt['is_correct'] ? 'text-green-700 font-semibold' : 'text-gray-500' ?>">
                                    <?php if ($opt['is_correct']): ?><i class="fas fa-check-circle text-green-500 flex-shrink-0"></i><?php else: ?><i class="far fa-circle text-gray-300 flex-shrink-0"></i><?php endif; ?>
                                    <?= htmlspecialchars($opt['option_text']) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <form method="POST">
                            <?= csrfField() ?><input type="hidden" name="delete_question" value="1">
                            <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                            <button type="submit" class="text-red-300 hover:text-red-500 transition-colors text-xs px-2 py-1 rounded-lg hover:bg-red-50"
                                onclick="return confirm('Delete this question?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($questions)): ?>
                <p class="p-5 text-gray-400 text-sm text-center">No questions yet. Add one below.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Question Form -->
        <div class="lms-card p-5">
            <h3 class="font-semibold text-gray-700 text-sm mb-4">Add a New Question</h3>
            <form method="POST" class="space-y-4">
                <?= csrfField() ?><input type="hidden" name="add_question" value="1">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Question Text <span class="text-red-500">*</span></label>
                    <textarea name="question_text" rows="2" required class="lms-input resize-none text-sm" placeholder="Enter your question..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <?php for ($i = 1; $i <= 4; $i++): ?>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Option <?= $i ?></label>
                        <input type="text" name="option_<?= $i ?>" class="lms-input text-sm" placeholder="Option <?= $i ?>...">
                    </div>
                    <?php endfor; ?>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-2">Correct Answer</label>
                    <div class="flex gap-3">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                        <label class="flex items-center gap-1.5 cursor-pointer text-sm">
                            <input type="radio" name="correct" value="<?= $i ?>" <?= $i===1?'checked':'' ?> class="accent-candlelight-500">
                            Option <?= $i ?>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <button type="submit" class="btn-lms-primary text-sm"><i class="fas fa-plus"></i> Add Question</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include './includes/footer.php'; ?>
