<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

$adminUser     = currentUser();
$adminPageTitle = $adminPageTitle ?? 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($adminPageTitle) ?> | CLSN LMS Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/x-icon" href="/images/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    candlelight: { 50:'#FFF9F0',100:'#FFF3E0',200:'#FFE0B2',300:'#FFCC80',400:'#FFB74D',500:'#FFA726',600:'#FB8C00',700:'#F57C00',800:'#EF6C00',900:'#E65100' },
                    navy: { 50:'#E8EAF6',100:'#C5CAE9',200:'#9FA8DA',300:'#7986CB',400:'#5C6BC0',500:'#3F51B5',600:'#3949AB',700:'#303F9F',800:'#283593',900:'#1A237E' }
                },
                fontFamily: {
                    display: ['Playfair Display','serif'],
                    body:    ['Plus Jakarta Sans','sans-serif'],
                }
            }
        }
    }
    </script>
    <link rel="stylesheet" href="/css/custom.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #FFA726; border-radius: 3px; }
        #admin-sidebar { transition: transform 0.3s ease; }
        #admin-sidebar.sidebar-hidden { transform: translateX(-100%); }
        .admin-nav-link.active { background: linear-gradient(135deg,#FFF3E0,#FFE0B2); color: #E65100; font-weight: 700; }
        .admin-nav-link.active i { color: #FB8C00; }
    </style>
</head>
<body class="font-body bg-gray-50 text-gray-900 overflow-x-hidden">

<div class="flex h-screen overflow-hidden">

    <!-- Sidebar -->
    <aside id="admin-sidebar" class="fixed lg:static inset-y-0 left-0 w-64 bg-navy-900 text-white flex flex-col z-50 lg:z-auto">
        <div class="px-6 py-5 border-b border-white/10 flex items-center justify-between">
            <a href="/admin/index.php" class="flex items-center gap-3">
                <img src="/images/logo-white.svg" alt="Logo" class="w-9 h-9 object-contain">
                <div>
                    <div class="font-display font-bold text-base leading-tight">Candlelight LMS</div>
                    <div class="text-xs text-candlelight-400 font-semibold tracking-wide mt-0.5">Admin Panel</div>
                </div>
            </a>
            <button id="admin-sidebar-close" class="lg:hidden w-8 h-8 bg-white/10 rounded-full flex items-center justify-center hover:bg-white/20">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <?php
            $cp = basename($_SERVER['PHP_SELF']);
            $sections = [
                ['href'=>'/admin/index.php',        'icon'=>'fa-tachometer-alt','label'=>'Dashboard',      'match'=>'index.php'],
                ['href'=>'/admin/users.php',         'icon'=>'fa-users',          'label'=>'Users',          'match'=>'users.php'],
                ['href'=>'/admin/courses.php',       'icon'=>'fa-graduation-cap', 'label'=>'Courses',        'match'=>'courses.php|course-form.php'],
                ['href'=>'/admin/modules.php',       'icon'=>'fa-layer-group',    'label'=>'Modules',        'match'=>'modules.php|module-form.php'],
                ['href'=>'/admin/quiz-builder.php',  'icon'=>'fa-question-circle','label'=>'Quiz Builder',   'match'=>'quiz-builder.php'],
                ['href'=>'/admin/qa-manager.php',    'icon'=>'fa-comments',       'label'=>'Q&A Manager',    'match'=>'qa-manager.php'],
            ];
            foreach ($sections as $s):
                $active = in_array($cp, explode('|', $s['match']));
            ?>
            <a href="<?= $s['href'] ?>" class="admin-nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-white/10 <?= $active ? 'active' : 'text-gray-300' ?>">
                <i class="fas <?= $s['icon'] ?> w-5 text-center <?= $active ? 'text-candlelight-500' : 'text-gray-400' ?>"></i>
                <span class="text-sm"><?= $s['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="px-4 py-4 border-t border-white/10">
            <div class="flex items-center gap-3 px-3 py-2 rounded-xl bg-white/5 mb-2">
                <div class="w-8 h-8 rounded-full bg-candlelight-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    <?= strtoupper(substr($adminUser['first_name'], 0, 1)) ?>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold truncate"><?= htmlspecialchars($adminUser['first_name'] . ' ' . $adminUser['last_name']) ?></div>
                    <div class="text-xs text-candlelight-400">Administrator</div>
                </div>
            </div>
            <a href="/logout.php" class="flex items-center gap-3 px-3 py-2 rounded-xl text-gray-400 hover:text-red-400 hover:bg-white/5 transition-all text-sm">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> <span>Logout</span>
            </a>
        </div>
    </aside>

    <div id="admin-overlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen overflow-y-auto">
        <!-- Top Bar -->
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center gap-4 sticky top-0 z-30">
            <button id="admin-sidebar-toggle" class="lg:hidden w-9 h-9 flex items-center justify-center rounded-xl hover:bg-gray-100">
                <i class="fas fa-bars text-gray-600"></i>
            </button>
            <img src="/images/logo-candlelight.svg" alt="Logo" class="w-8 h-8 object-contain flex-shrink-0">
            <h1 class="font-display font-bold text-xl text-navy-900"><?= htmlspecialchars($adminPageTitle) ?></h1>
        </header>

        <main class="flex-1 p-6 lg:p-8">
