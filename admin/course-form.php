<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$courseId = (int)($_GET['id'] ?? 0);
$isEdit   = $courseId > 0;

$course = [
    'title'             => '',
    'slug'              => '',
    'short_description' => '',
    'description'       => '',
    'instructor'        => 'Candlelight Foundation',
    'duration'          => '8 Weeks',
    'level'             => 'Beginner',
    'is_free'           => 1,
    'is_active'         => 1,
    'thumbnail'         => '',
    'youtube_url'       => '',
];

if ($isEdit) {
    $stmt = $conn->prepare("SELECT * FROM lms_courses WHERE id = ?");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $fetched = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fetched) { header('Location: /clsn-lms/admin/courses.php'); exit; }
    $course = $fetched;
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $err = 'Security check failed.';
    } else {
        $title       = trim($_POST['title']             ?? '');
        $slug        = trim($_POST['slug']              ?? '');
        $shortDesc   = trim($_POST['short_description'] ?? '');
        $description = trim($_POST['description']       ?? '');
        $instructor  = trim($_POST['instructor']        ?? 'Candlelight Foundation');
        $duration    = trim($_POST['duration']          ?? '8 Weeks');
        $level       = $_POST['level']                  ?? 'Beginner';
        $isFree      = isset($_POST['is_free'])   ? 1 : 0;
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $youtubeUrl  = trim($_POST['youtube_url'] ?? '');
        // Strip non-http junk from YouTube URL
        if ($youtubeUrl && !preg_match('#^https?://#', $youtubeUrl)) $youtubeUrl = '';

        // Handle thumbnail upload
        $thumbnailFile = $isEdit ? $course['thumbnail'] : '';
        if (!empty($_FILES['thumbnail']['tmp_name'])) {
            $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
            $ftype   = mime_content_type($_FILES['thumbnail']['tmp_name']);
            if (!in_array($ftype, $allowed)) {
                $err = 'Thumbnail must be a JPG, PNG, WEBP or GIF image.';
            } elseif ($_FILES['thumbnail']['size'] > 2 * 1024 * 1024) {
                $err = 'Thumbnail must be under 2 MB.';
            } else {
                $ext  = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $name = 'course-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $dest = __DIR__ . '/../uploads/thumbnails/' . $name;
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dest)) {
                    // Delete old thumbnail
                    if ($isEdit && !empty($course['thumbnail'])) {
                        $old = __DIR__ . '/../uploads/thumbnails/' . basename($course['thumbnail']);
                        if (file_exists($old)) @unlink($old);
                    }
                    $thumbnailFile = $name;
                } else {
                    $err = 'Could not save thumbnail. Check folder permissions.';
                }
            }
        }

        // Auto-generate slug if empty
        if (empty($slug) && !empty($title)) {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-'));
        }

        if (empty($title)) {
            $err = 'Course title is required.';
        } elseif (empty($slug)) {
            $err = 'Course slug is required.';
        } else {
            if ($isEdit) {
                // Check slug uniqueness excluding self
                $chk = $conn->prepare("SELECT id FROM lms_courses WHERE slug = ? AND id != ?");
                $chk->bind_param('si', $slug, $courseId);
                $chk->execute();
                $chk->store_result();
                if ($chk->num_rows > 0) {
                    $err = 'That slug is already used by another course.';
                }
                $chk->close();
                if (!$err) {
                    $stmt = $conn->prepare("UPDATE lms_courses SET title=?,slug=?,short_description=?,description=?,instructor=?,duration=?,level=?,is_free=?,is_active=?,thumbnail=?,youtube_url=? WHERE id=?");
                    $stmt->bind_param('sssssssiissi', $title, $slug, $shortDesc, $description, $instructor, $duration, $level, $isFree, $isActive, $thumbnailFile, $youtubeUrl, $courseId);
                    if ($stmt->execute()) {
                        $msg = 'Course updated successfully!';
                        $course = array_merge($course, compact('title','slug','shortDesc','description','instructor','duration','level','isFree','isActive'));
                        $course['short_description'] = $shortDesc;
                        $course['is_free']     = $isFree;
                        $course['is_active']   = $isActive;
                        $course['thumbnail']   = $thumbnailFile;
                        $course['youtube_url'] = $youtubeUrl;
                    } else {
                        $err = 'Database error: ' . $conn->error;
                    }
                    $stmt->close();
                }
            } else {
                // Check slug uniqueness
                $chk = $conn->prepare("SELECT id FROM lms_courses WHERE slug = ?");
                $chk->bind_param('s', $slug);
                $chk->execute();
                $chk->store_result();
                if ($chk->num_rows > 0) {
                    $err = 'That slug is already taken. Choose a unique one.';
                }
                $chk->close();
                if (!$err) {
                    $stmt = $conn->prepare("INSERT INTO lms_courses (title,slug,short_description,description,instructor,duration,level,is_free,is_active,thumbnail,youtube_url) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->bind_param('sssssssiiss', $title, $slug, $shortDesc, $description, $instructor, $duration, $level, $isFree, $isActive, $thumbnailFile, $youtubeUrl);
                    if ($stmt->execute()) {
                        $newId = $conn->insert_id;
                        $stmt->close();
                        header('Location: /clsn-lms/admin/modules.php?course_id=' . $newId . '&created=1');
                        exit;
                    } else {
                        $err = 'Database error: ' . $conn->error;
                        $stmt->close();
                    }
                }
            }
        }

        // Repopulate on error
        if ($err) {
            $course['title']             = $title;
            $course['slug']              = $slug;
            $course['short_description'] = $shortDesc;
            $course['description']       = $description;
            $course['instructor']        = $instructor;
            $course['duration']          = $duration;
            $course['level']             = $level;
            $course['is_free']           = $isFree;
            $course['is_active']         = $isActive;
            $course['youtube_url']       = $youtubeUrl;
        }
    }
}

$adminPageTitle = $isEdit ? 'Edit Course' : 'New Course';
include './includes/header.php';
?>

<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
    <a href="/clsn-lms/admin/courses.php" class="hover:text-candlelight-600 transition-colors">Courses</a>
    <i class="fas fa-chevron-right text-xs text-gray-300"></i>
    <span class="text-gray-800"><?= $isEdit ? htmlspecialchars($course['title']) : 'New Course' ?></span>
</nav>

<div class="max-w-3xl">
    <?php if ($msg): ?><div class="lms-alert lms-alert-success mb-5"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="lms-alert lms-alert-error mb-5"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-5">
        <?= csrfField() ?>

        <div class="lms-card p-6 space-y-5">
            <h2 class="font-display font-bold text-navy-900 text-lg border-b border-gray-100 pb-4">Course Details</h2>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Course Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="course-title" required class="lms-input"
                    value="<?= htmlspecialchars($course['title']) ?>"
                    placeholder="e.g. Understanding Autism: A Comprehensive Training Course">
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    URL Slug <span class="text-red-500">*</span>
                    <span class="font-normal text-gray-400 text-xs ml-1">(auto-generated from title, must be unique)</span>
                </label>
                <input type="text" name="slug" id="course-slug" required class="lms-input font-mono text-sm"
                    value="<?= htmlspecialchars($course['slug']) ?>"
                    placeholder="e.g. understanding-autism">
                <p class="text-xs text-gray-400 mt-1">Used in the course URL: /clsn-lms/course.php?slug=<strong id="slug-preview"><?= htmlspecialchars($course['slug']) ?: 'your-slug' ?></strong></p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Short Description <span class="text-gray-400 font-normal text-xs">(shown on course card, max 600 chars)</span></label>
                <textarea name="short_description" rows="3" maxlength="600" class="lms-input resize-none"
                    placeholder="A brief summary shown on the course listing page..."><?= htmlspecialchars($course['short_description']) ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Full Description <span class="text-gray-400 font-normal text-xs">(HTML supported)</span></label>
                <textarea name="description" rows="8" class="lms-input font-mono text-xs resize-y"
                    placeholder="<p>Full course description...</p>"><?= htmlspecialchars($course['description']) ?></textarea>
            </div>
        </div>

        <div class="lms-card p-6 space-y-5">
            <h2 class="font-display font-bold text-navy-900 text-lg border-b border-gray-100 pb-4">Settings</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Instructor</label>
                    <input type="text" name="instructor" class="lms-input"
                        value="<?= htmlspecialchars($course['instructor']) ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Duration</label>
                    <input type="text" name="duration" class="lms-input"
                        value="<?= htmlspecialchars($course['duration']) ?>"
                        placeholder="e.g. 8 Weeks">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Level</label>
                <select name="level" class="lms-input">
                    <?php foreach (['Beginner', 'Intermediate', 'Advanced'] as $lvl): ?>
                    <option value="<?= $lvl ?>" <?= $course['level'] === $lvl ? 'selected' : '' ?>><?= $lvl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Thumbnail -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Course Thumbnail <span class="text-gray-400 font-normal text-xs">(JPG/PNG/WEBP, max 2 MB)</span></label>
                <?php if (!empty($course['thumbnail'])): ?>
                <div class="mb-3 flex items-start gap-4">
                    <img src="/clsn-lms/uploads/thumbnails/<?= htmlspecialchars(basename($course['thumbnail'])) ?>"
                         alt="Current thumbnail" class="w-32 h-20 object-cover rounded-xl border border-gray-200">
                    <p class="text-xs text-gray-500 mt-2">Current thumbnail. Upload a new file to replace it.</p>
                </div>
                <?php endif; ?>
                <input type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp,image/gif"
                       class="lms-input py-2 text-sm" id="thumb-input">
                <div id="thumb-preview-wrap" class="mt-2 hidden">
                    <img id="thumb-preview" src="" alt="Preview" class="w-32 h-20 object-cover rounded-xl border border-gray-200">
                </div>
            </div>

            <!-- YouTube URL -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    YouTube Video URL <span class="text-gray-400 font-normal text-xs">(optional — auto-fills card thumbnail if no image uploaded)</span>
                </label>
                <input type="url" name="youtube_url" class="lms-input text-sm"
                       value="<?= htmlspecialchars($course['youtube_url'] ?? '') ?>"
                       placeholder="https://www.youtube.com/watch?v=...">
                <?php
                $ytId = '';
                if (!empty($course['youtube_url'])) {
                    preg_match('#(?:v=|youtu\.be/|embed/)([a-zA-Z0-9_-]{11})#', $course['youtube_url'], $m);
                    $ytId = $m[1] ?? '';
                }
                ?>
                <?php if ($ytId): ?>
                <div class="mt-2 flex items-center gap-3">
                    <img src="https://img.youtube.com/vi/<?= htmlspecialchars($ytId) ?>/hqdefault.jpg"
                         alt="YouTube thumbnail" class="w-32 h-20 object-cover rounded-xl border border-gray-200">
                    <p class="text-xs text-gray-500">Auto-fetched YouTube thumbnail preview.</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="is_free" name="is_free" class="w-4 h-4 accent-candlelight-500"
                        <?= $course['is_free'] ? 'checked' : '' ?>>
                    <label for="is_free" class="text-sm font-semibold text-gray-700">Free course (no payment required)</label>
                </div>
                <div class="flex items-center gap-3">
                    <input type="checkbox" id="is_active" name="is_active" class="w-4 h-4 accent-candlelight-500"
                        <?= $course['is_active'] ? 'checked' : '' ?>>
                    <label for="is_active" class="text-sm font-semibold text-gray-700">Active (visible to students)</label>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-lms-primary">
                <i class="fas fa-save"></i> <?= $isEdit ? 'Save Changes' : 'Create Course' ?>
            </button>
            <?php if ($isEdit): ?>
            <a href="/clsn-lms/admin/modules.php?course_id=<?= $courseId ?>" class="btn-lms-secondary">
                <i class="fas fa-layer-group"></i> Manage Modules
            </a>
            <?php endif; ?>
            <a href="/clsn-lms/admin/courses.php" class="btn-lms-secondary">
                <i class="fas fa-arrow-left"></i> Back to Courses
            </a>
        </div>
    </form>
</div>

<script>
// Auto-generate slug from title (only when slug is empty / untouched)
const titleInput = document.getElementById('course-title');
const slugInput  = document.getElementById('course-slug');
const slugPreview = document.getElementById('slug-preview');
let slugManual = <?= ($isEdit || !empty($course['slug'])) ? 'true' : 'false' ?>;

function makeSlug(str) {
    return str.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

titleInput.addEventListener('input', () => {
    if (!slugManual) {
        const s = makeSlug(titleInput.value);
        slugInput.value = s;
        slugPreview.textContent = s || 'your-slug';
    }
});
slugInput.addEventListener('input', () => {
    slugManual = true;
    slugPreview.textContent = slugInput.value || 'your-slug';
});

// Live thumbnail preview
document.getElementById('thumb-input').addEventListener('change', function () {
    const wrap = document.getElementById('thumb-preview-wrap');
    const img  = document.getElementById('thumb-preview');
    if (this.files && this.files[0]) {
        img.src = URL.createObjectURL(this.files[0]);
        wrap.classList.remove('hidden');
    } else {
        wrap.classList.add('hidden');
    }
});
</script>

<?php include './includes/footer.php'; ?>
