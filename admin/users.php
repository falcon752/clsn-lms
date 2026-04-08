<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

// Search
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = $search ? "WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)" : '';
$param  = '%' . $search . '%';

// Count
$cSql  = "SELECT COUNT(*) AS cnt FROM lms_users u $where";
$cStmt = $conn->prepare($cSql);
if ($search) { $cStmt->bind_param('sss', $param, $param, $param); }
$cStmt->execute();
$total = (int)$cStmt->get_result()->fetch_assoc()['cnt'];
$cStmt->close();
$pages = max(1, ceil($total / $limit));

// Fetch
$sql  = "SELECT u.*, (SELECT COUNT(*) FROM lms_enrollments e WHERE e.user_id=u.id) AS enrollments, (SELECT COUNT(*) FROM lms_certificates c WHERE c.user_id=u.id) AS certs FROM lms_users u $where ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($search) { $stmt->bind_param('sssii', $param, $param, $param, $limit, $offset); }
else         { $stmt->bind_param('ii', $limit, $offset); }
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$adminPageTitle = 'Users';
include './includes/header.php';
?>

<!-- Search Bar -->
<form method="GET" class="flex gap-3 mb-6 max-w-md">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="lms-input flex-1 text-sm" placeholder="Search by name or email...">
    <button type="submit" class="btn-lms-primary text-sm px-5"><i class="fas fa-search"></i></button>
    <?php if ($search): ?><a href="/clsn-lms/admin/users.php" class="btn-lms-secondary text-sm px-4"><i class="fas fa-times"></i></a><?php endif; ?>
</form>

<div class="lms-card overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <span class="text-sm text-gray-500"><?= $total ?> user<?= $total !== 1 ? 's' : '' ?><?= $search ? " matching \"$search\"" : '' ?></span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-5 py-3 font-semibold text-gray-600">Name</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Email</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Role</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Enrolled</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Certificates</th>
                    <th class="text-left px-4 py-3 font-semibold text-gray-600">Joined</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Status</th>
                    <th class="text-center px-4 py-3 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-gray-50 transition-colors" data-user-id="<?= $u['id'] ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-candlelight-100 flex items-center justify-center font-bold text-candlelight-700 text-xs flex-shrink-0">
                                <?= strtoupper(substr($u['first_name'],0,1)) ?>
                            </div>
                            <span class="font-semibold text-gray-800"><?= htmlspecialchars($u['first_name'].' '.$u['last_name']) ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 text-gray-500 truncate max-w-[200px]"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($u['role'] === 'admin'): ?>
                        <span class="role-badge inline-flex items-center px-2.5 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">Admin</span>
                        <?php else: ?>
                        <span class="role-badge inline-flex items-center px-2.5 py-1 bg-navy-100 text-navy-700 rounded-full text-xs font-semibold">User</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-center"><span class="inline-block px-2.5 py-1 bg-navy-100 text-navy-700 rounded-lg text-xs font-semibold"><?= $u['enrollments'] ?></span></td>
                    <td class="px-4 py-3.5 text-center"><span class="inline-block px-2.5 py-1 bg-candlelight-100 text-candlelight-700 rounded-lg text-xs font-semibold"><?= $u['certs'] ?></span></td>
                    <td class="px-4 py-3.5 text-gray-400"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($u['is_active']): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-green-100 text-green-700 rounded-full text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active</span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-100 text-red-600 rounded-full text-xs font-semibold"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($u['id'] !== (int)$_SESSION['lms_user_id'] && $u['email'] !== 'admin@candlelightspecialneeds.org'): ?>
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="toggleRole(this, <?= $u['id'] ?>, '<?= $u['role'] ?>')"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-colors <?= $u['role'] === 'admin' ? 'border-purple-300 text-purple-700 hover:bg-purple-50' : 'border-navy-300 text-navy-700 hover:bg-navy-50' ?>"
                                title="<?= $u['role'] === 'admin' ? 'Demote to User' : 'Promote to Admin' ?>">
                                <i class="fas <?= $u['role'] === 'admin' ? 'fa-user-minus' : 'fa-user-shield' ?>"></i>
                                <?= $u['role'] === 'admin' ? 'Demote' : 'Promote' ?>
                            </button>
                            <button onclick="deleteUser(this, <?= $u['id'] ?>, '<?= htmlspecialchars($u['first_name'].' '.$u['last_name'], ENT_QUOTES) ?>')"
                                class="px-2.5 py-1.5 rounded-lg text-xs font-semibold border border-red-200 text-red-600 hover:bg-red-50 transition-colors"
                                title="Delete Account">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-gray-300">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="px-5 py-4 border-t border-gray-100 flex items-center gap-2">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg text-sm <?= $i === $page ? 'bg-candlelight-500 text-white font-bold' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?> transition-colors">
            <?= $i ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>


<script>
const CSRF = '<?= csrfToken() ?>';

async function manageUser(payload) {
    const res  = await fetch('/clsn-lms/ajax/manage-user.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({...payload, csrf_token: CSRF})
    });
    return res.json();
}

async function toggleRole(btn, userId, currentRole) {
    btn.disabled = true;
    const data = await manageUser({action: 'toggle_role', user_id: userId});
    if (!data.ok) { alert(data.msg); btn.disabled = false; return; }

    const newRole    = data.new_role;
    const isAdmin    = newRole === 'admin';
    const row        = btn.closest('tr');
    const badge      = row.querySelector('.role-badge');

    // Update badge
    badge.textContent = isAdmin ? 'Admin' : 'User';
    badge.className   = 'role-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ' +
        (isAdmin ? 'bg-purple-100 text-purple-700' : 'bg-navy-100 text-navy-700');

    // Update button
    btn.className = btn.className.replace(/border-(purple|navy)-300 text-(purple|navy)-700 hover:bg-(purple|navy)-50/g, '') +
        (isAdmin ? ' border-purple-300 text-purple-700 hover:bg-purple-50' : ' border-navy-300 text-navy-700 hover:bg-navy-50');
    btn.innerHTML = `<i class="fas ${isAdmin ? 'fa-user-minus' : 'fa-user-shield'}"></i> ${isAdmin ? 'Demote' : 'Promote'}`;
    btn.title     = isAdmin ? 'Demote to User' : 'Promote to Admin';
    btn.onclick   = () => toggleRole(btn, userId, newRole);
    btn.disabled  = false;
}

async function deleteUser(btn, userId, name) {
    if (!confirm(`Delete account for "${name}"? This cannot be undone.`)) return;
    btn.disabled = true;
    const data = await manageUser({action: 'delete_user', user_id: userId});
    if (!data.ok) { alert(data.msg); btn.disabled = false; return; }
    btn.closest('tr').remove();
}
</script>

<?php include './includes/footer.php'; ?>
