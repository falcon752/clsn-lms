<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$isNew    = isset($_GET['new']) && (int)($_GET['course_id'] ?? 0) > 0;
$moduleId = $isNew ? 0 : (int)($_GET['id'] ?? 0);
$module   = null;

if ($isNew) {
    $courseId = (int)$_GET['course_id'];
    $cStmt = $conn->prepare("SELECT * FROM lms_courses WHERE id = ?");
    $cStmt->bind_param('i', $courseId);
    $cStmt->execute();
    $courseRow = $cStmt->get_result()->fetch_assoc();
    $cStmt->close();
    if (!$courseRow) { header('Location: /admin/courses'); exit; }

    // Next module number
    $r = $conn->prepare("SELECT COALESCE(MAX(module_number),0)+1 AS next_num FROM lms_modules WHERE course_id=?");
    $r->bind_param('i', $courseId);
    $r->execute();
    $nextNum = (int)$r->get_result()->fetch_assoc()['next_num'];
    $r->close();

    $module = [
        'id'               => 0,
        'course_id'        => $courseId,
        'course_title'     => $courseRow['title'],
        'module_number'    => $nextNum,
        'title'            => '',
        'description'      => '',
        'video_type'       => 'youtube',
        'video_url'        => '',
        'notes'            => '',
        'duration_minutes' => 30,
        'is_active'        => 1,
    ];
} else {
    if (!$moduleId) { header('Location: /admin/courses'); exit; }
    $stmt = $conn->prepare("SELECT m.*, c.title AS course_title, c.id AS course_id FROM lms_modules m JOIN lms_courses c ON c.id=m.course_id WHERE m.id=?");
    $stmt->bind_param('i', $moduleId);
    $stmt->execute();
    $module = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$module) { header('Location: /admin/courses'); exit; }
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_module'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } else {
        $title     = trim($_POST['title']           ?? '');
        $desc      = trim($_POST['description']     ?? '');
        $videoType = $_POST['video_type']            ?? 'youtube';
        $videoUrl  = trim($_POST['video_url']        ?? '');
        $notes     = $_POST['notes']                ?? '';
        $duration  = (int)($_POST['duration_minutes'] ?? 0);
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title)) { $err = 'Module title is required.'; }
        else {
            if ($isNew) {
                $sortOrder = $module['module_number'];
                $stmt = $conn->prepare("INSERT INTO lms_modules (course_id,module_number,title,description,video_type,video_url,notes,duration_minutes,is_active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('iisssssiis', $module['course_id'], $module['module_number'], $title, $desc, $videoType, $videoUrl, $notes, $duration, $isActive, $sortOrder);
                if ($stmt->execute()) {
                    $newModId = $conn->insert_id;
                    // Update total_modules count
                    $conn->query("UPDATE lms_courses SET total_modules=(SELECT COUNT(*) FROM lms_modules WHERE course_id={$module['course_id']}) WHERE id={$module['course_id']}");
                    $stmt->close();
                    header('Location: /admin/modules?course_id=' . $module['course_id'] . '&added=1');
                    exit;
                } else {
                    $err = 'Database error: ' . $conn->error;
                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare("UPDATE lms_modules SET title=?,description=?,video_type=?,video_url=?,notes=?,duration_minutes=?,is_active=? WHERE id=?");
                $stmt->bind_param('sssssiis', $title, $desc, $videoType, $videoUrl, $notes, $duration, $isActive, $moduleId);
                $stmt->execute();
                $stmt->close();
                $module['title']       = $title;
                $module['description'] = $desc;
                $module['video_type']  = $videoType;
                $module['video_url']   = $videoUrl;
                $module['notes']       = $notes;
                $module['duration_minutes'] = $duration;
                $module['is_active']   = $isActive;
                $msg = 'Module updated successfully!';
            }
        }
    }
}

$adminPageTitle = $isNew ? 'Add Module' : 'Edit Module';

// ── PDF Resource Upload (edit mode only) ──────────────────────────────────────
if (!$isNew && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_pdf'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } elseif (empty($_FILES['pdf_file']['tmp_name'])) {
        $err = 'Please choose a file to upload.';
    } else {
        $allowedMimes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                         'application/msword', 'application/vnd.ms-powerpoint',
                         'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
        $fmime = mime_content_type($_FILES['pdf_file']['tmp_name']);
        $fsize = $_FILES['pdf_file']['size'];
        $pdfTitle = trim($_POST['pdf_title'] ?? '') ?: pathinfo($_FILES['pdf_file']['name'], PATHINFO_FILENAME);
        if (!in_array($fmime, $allowedMimes, true)) {
            $err = 'Only PDF, Word (.docx), and PowerPoint (.pptx) files are allowed.';
        } elseif ($fsize > 20 * 1024 * 1024) {
            $err = 'File must be under 20 MB.';
        } else {
            $ext  = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
            $name = uniqid('res_', true) . '.' . $ext;
            $dest = __DIR__ . '/../uploads/pdfs/' . $name;
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $dest)) {
                $sizeLabel = $fsize >= 1048576 ? round($fsize / 1048576, 1) . ' MB' : round($fsize / 1024) . ' KB';
                $ins = $conn->prepare("INSERT INTO lms_module_pdfs (module_id, title, file_path, file_size) VALUES (?, ?, ?, ?)");
                $ins->bind_param('isss', $moduleId, $pdfTitle, $name, $sizeLabel);
                $ins->execute();
                $ins->close();
                $msg = 'Resource uploaded successfully!';
            } else {
                $err = 'Could not save file. Check folder permissions.';
            }
        }
    }
}

// ── PDF Resource Delete (edit mode only) ─────────────────────────────────────
if (!$isNew && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_pdf'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } else {
        $delId = (int)($_POST['pdf_id'] ?? 0);
        $dStmt = $conn->prepare("SELECT file_path FROM lms_module_pdfs WHERE id = ? AND module_id = ?");
        $dStmt->bind_param('ii', $delId, $moduleId);
        $dStmt->execute();
        $dRow = $dStmt->get_result()->fetch_assoc();
        $dStmt->close();
        if ($dRow) {
            $fpath = __DIR__ . '/../uploads/pdfs/' . basename($dRow['file_path']);
            if (file_exists($fpath)) @unlink($fpath);
            $del = $conn->prepare("DELETE FROM lms_module_pdfs WHERE id = ?");
            $del->bind_param('i', $delId);
            $del->execute();
            $del->close();
            $msg = 'Resource deleted.';
        }
    }
}

include './includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
    <a href="/admin/courses.php" class="hover:text-candlelight-600 transition-colors">Courses</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/admin/courses.php" class="hover:text-candlelight-600 transition-colors"><?= htmlspecialchars($module['course_title']) ?></a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <a href="/admin/modules.php?course_id=<?= $module['course_id'] ?>" class="hover:text-candlelight-600 transition-colors">Modules</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <span class="text-gray-800 font-medium"><?= $isNew ? 'New Module' : 'Edit Module ' . $module['module_number'] ?></span>
</nav>

<div class="max-w-3xl">
    <?php if ($msg): ?><div class="lms-alert lms-alert-success mb-5"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="lms-alert lms-alert-error mb-5"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="POST" class="space-y-5">
        <?= csrfField() ?>
        <input type="hidden" name="update_module" value="1">

        <div class="lms-card p-6 space-y-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Module Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" required class="lms-input"
                    value="<?= htmlspecialchars($module['title']) ?>">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Short Description</label>
                <textarea name="description" rows="3" class="lms-input resize-none"><?= htmlspecialchars($module['description'] ?? '') ?></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Video Type</label>
                    <select name="video_type" class="lms-input">
                        <?php foreach (['youtube'=>'YouTube','vimeo'=>'Vimeo','file'=>'Self-Hosted File'] as $v=>$l): ?>
                        <option value="<?= $v ?>" <?= $module['video_type']===$v?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Duration (minutes)</label>
                    <input type="number" name="duration_minutes" class="lms-input" min="0"
                        value="<?= $module['duration_minutes'] ?>">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Video URL</label>
                <input type="text" name="video_url" class="lms-input"
                    placeholder="https://www.youtube.com/watch?v=..."
                    value="<?= htmlspecialchars($module['video_url'] ?? '') ?>">
                <p class="text-xs text-gray-400 mt-1">Paste a YouTube or Vimeo URL. For self-hosted, add the filename (e.g., module1.mp4).</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Module Notes</label>
                <textarea name="notes" rows="12" class="lms-input font-mono text-xs resize-y"><?= htmlspecialchars($module['notes'] ?? '') ?></textarea>
                <p class="text-xs text-gray-400 mt-1">You can use HTML tags (&lt;h2&gt;, &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;em&gt;, etc.)</p>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="is_active" name="is_active" class="w-4 h-4 accent-candlelight-500"
                    <?= $module['is_active'] ? 'checked' : '' ?>>
                <label for="is_active" class="text-sm font-semibold text-gray-700">Module is active (visible to students)</label>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-lms-primary"><i class="fas fa-save"></i> <?= $isNew ? 'Create Module' : 'Save Changes' ?></button>
            <a href="/admin/modules.php?course_id=<?= $module['course_id'] ?>" class="btn-lms-secondary">
                <i class="fas fa-arrow-left"></i> Back to Modules
            </a>
            <?php if (!$isNew): ?>
            <a href="/admin/quiz-builder.php?module_id=<?= $moduleId ?>" class="btn-lms-secondary">
                <i class="fas fa-pencil-alt"></i> Edit Quiz
            </a>
            <?php endif; ?>
        </div>
    </form>
</div><!-- /max-w-3xl -->

<?php if (!$isNew): ?>
<!-- ── Downloadable Resources ─────────────────────────────────────────────── -->
<?php
$existingPdfs = [];
$pStmt = $conn->prepare("SELECT * FROM lms_module_pdfs WHERE module_id = ? ORDER BY id ASC");
$pStmt->bind_param('i', $moduleId);
$pStmt->execute();
$existingPdfs = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$pStmt->close();
?>
<div class="lms-card p-6 mt-6">
    <h2 class="font-display font-bold text-navy-900 text-lg mb-5 flex items-center gap-2">
        <i class="fas fa-paperclip text-candlelight-500"></i> Downloadable Resources
        <span class="text-xs font-normal text-gray-400 ml-1">(Workbooks, PDFs, slides — visible to enrolled students)</span>
    </h2>

    <!-- Existing resources -->
    <?php if (!empty($existingPdfs)): ?>
    <div class="space-y-2 mb-6">
        <?php foreach ($existingPdfs as $pdf): ?>
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl border border-gray-200">
            <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-file-pdf text-red-500"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($pdf['title']) ?></p>
                <p class="text-xs text-gray-400"><?= htmlspecialchars($pdf['file_size'] ?? '') ?> &middot; <?= htmlspecialchars(pathinfo($pdf['file_path'], PATHINFO_EXTENSION)) ?></p>
            </div>
            <a href="/uploads/pdfs/<?= htmlspecialchars(basename($pdf['file_path'])) ?>" target="_blank"
               class="px-3 py-1.5 text-xs font-semibold bg-white border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
                <i class="fas fa-eye mr-1"></i> Preview
            </a>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="delete_pdf" value="1">
                <input type="hidden" name="pdf_id" value="<?= $pdf['id'] ?>">
                <button type="submit"
                    data-confirm="Delete this resource? This cannot be undone."
                    data-confirm-title="Delete Resource?"
                    data-confirm-label="Yes, Delete"
                    data-confirm-icon='<i class="fas fa-trash"></i>'
                    class="px-3 py-1.5 text-xs font-semibold bg-red-50 border border-red-200 text-red-600 rounded-lg hover:bg-red-100 transition-colors">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="text-sm text-gray-400 mb-5 italic">No resources uploaded yet for this module.</p>
    <?php endif; ?>

    <!-- Upload form -->
    <form method="POST" enctype="multipart/form-data" class="space-y-4 border-t border-gray-100 pt-5">
        <?= csrfField() ?>
        <input type="hidden" name="upload_pdf" value="1">
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Resource Title</label>
                <input type="text" name="pdf_title" class="lms-input" placeholder="e.g. Module 1 Workbook">
                <p class="text-xs text-gray-400 mt-1">Leave blank to use the filename.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">File <span class="text-red-500">*</span></label>
                <input type="file" name="pdf_file" required
                       accept=".pdf,.doc,.docx,.ppt,.pptx"
                       class="lms-input py-2 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-candlelight-50 file:text-candlelight-700 hover:file:bg-candlelight-100 cursor-pointer">
                <p class="text-xs text-gray-400 mt-1">PDF, Word, or PowerPoint &mdash; max 20 MB.</p>
            </div>
        </div>
        <button type="submit" class="btn-lms-primary text-sm">
            <i class="fas fa-upload"></i> Upload Resource
        </button>
    </form>
</div>
<?php endif; ?>

<?php include './includes/footer.php'; ?>
