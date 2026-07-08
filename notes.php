<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = db()->prepare('SELECT * FROM interns WHERE id = ?');
$stmt->execute([$id]);
$intern = $stmt->fetch();

if (!$intern) {
    flash_set('error', 'Stajyer bulunamadı.');
    redirect('index.php');
}

if (is_mentor() && trim(mb_strtolower((string)($intern['assigned_department'] ?? ''))) !== trim(mb_strtolower($_SESSION['user_department'] ?? ''))) {
    flash_set('error', 'Bu stajyerin notlarını görüntüleme yetkiniz bulunmamaktadır.');
    redirect('index.php');
}

$fullName = $intern['first_name'] . ' ' . $intern['last_name'];
$userInitials = mb_strtoupper(mb_substr((string)($_SESSION['user_name'] ?? 'Y'), 0, 2));
$activeUser = (string) ($_SESSION['user_name'] ?? 'Ahsen Ece');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'note_add') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'Genel'));
        $text = trim((string) ($_POST['note_text'] ?? ''));
        if ($text === '') {
            flash_set('error', 'Not içeriği boş olamaz.');
        } else {
            db()->prepare('INSERT INTO intern_notes (intern_id, user_name, title, category, note_text) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $activeUser, $title, $category, $text]);
            log_action('not_ekle', $fullName);
            flash_set('success', 'Staj notu eklendi.');
        }
    }

    if ($action === 'note_edit') {
        $noteId = (int) ($_POST['note_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? 'Genel'));
        $text = trim((string) ($_POST['note_text'] ?? ''));
        if ($text === '') {
            flash_set('error', 'Not içeriği boş olamaz.');
        } else {
            db()->prepare('UPDATE intern_notes SET title = ?, category = ?, note_text = ? WHERE id = ? AND intern_id = ?')
                ->execute([$title, $category, $text, $noteId, $id]);
            log_action('not_guncelle', $fullName);
            flash_set('success', 'Staj notu güncellendi.');
        }
    }

    if ($action === 'note_delete') {
        db()->prepare('DELETE FROM intern_notes WHERE id = ? AND intern_id = ?')
            ->execute([(int) ($_POST['note_id'] ?? 0), $id]);
        log_action('not_sil', $fullName);
        flash_set('success', 'Staj notu silindi.');
    }

    redirect('notes.php?id=' . $id);
}

$notes = [];
$s = db()->prepare('SELECT * FROM intern_notes WHERE intern_id = ? ORDER BY id DESC');
$s->execute([$id]);
$notes = $s->fetchAll();

// Dinamik hafta hesabı
$startD = new DateTimeImmutable($intern['start_date']);
$todayD = new DateTimeImmutable('today');
$diff = $startD->diff($todayD);
$currentWeek = (int) ceil(($diff->days + 1) / 7);
if ($currentWeek < 1) $currentWeek = 1;
$subtitle = e($intern['department']) . ' Stajyeri • ' . $currentWeek . '. Hafta Performans Kayıtları';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Staj Notları | Staj Takip Sistemi</title>
    <script>
      (function() {
        var theme = localStorage.getItem("theme") || "corporate";
        localStorage.setItem("theme", theme);
        var bgColors = {"dark": "#091324", "light": "#f7f9fb", "ocean": "#0f0d36", "rose": "#fff0f5", "corporate": "#f4f6fa"};
        document.documentElement.style.backgroundColor = bgColors[theme] || "#f4f6fa";
        document.documentElement.dataset.theme = theme;

        var anim = localStorage.getItem("bg-animation") !== "disabled";
        if (anim) {
          document.documentElement.classList.add("has-bg-animation");
        } else {
          document.documentElement.classList.remove("has-bg-animation");
        }

        var bg = localStorage.getItem("theme-bg") || "none";
        if (bg !== "none") {
          document.documentElement.classList.add("has-custom-bg");
          var overlay = "rgba(9, 19, 36, 0.55)";
          if (theme === "light") overlay = "rgba(247, 249, 251, 0.45)";
          else if (theme === "ocean") overlay = "rgba(15, 13, 54, 0.35)";
          else if (theme === "rose") overlay = "rgba(255, 240, 245, 0.45)";
          else if (theme === "corporate") overlay = "rgba(13, 25, 52, 0.40)";
          var css = "body { background: linear-gradient(" + overlay + ", " + overlay + "), url('assets/img/backgrounds/" + theme + "_" + bg + ".png') !important; background-size: cover !important; background-attachment: fixed !important; background-position: center !important; }";
          var style = document.createElement("style");
          style.id = "dynamic-bg-style";
          style.appendChild(document.createTextNode(css));
          document.head.appendChild(style);
        } else {
          document.documentElement.classList.remove("has-custom-bg");
        }
      })();
    </script>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=JetBrains+Mono:wght@500&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            "colors": {
                    "tertiary-fixed-dim": "#b7c8e1",
                    "primary": "var(--primary)",
                    "primary-fixed-dim": "#c3c0ff",
                    "on-primary-fixed": "#0f0069",
                    "secondary": "#565e74",
                    "inverse-on-surface": "#eff1f3",
                    "on-error-container": "#93000a",
                    "inverse-surface": "#2d3133",
                    "secondary-fixed": "#dae2fd",
                    "on-secondary-fixed": "#131b2e",
                    "error-container": "#ffdad6",
                    "surface-container": "#eceef0",
                    "on-tertiary-fixed": "#0b1c30",
                    "outline": "#777587",
                    "on-secondary": "#ffffff",
                    "on-error": "#ffffff",
                    "surface-container-highest": "#e0e3e5",
                    "on-secondary-container": "#5c647a",
                    "surface-tint": "#4d44e3",
                    "primary-container": "#4f46e5",
                    "on-tertiary-container": "#ccdcf7",
                    "on-surface-variant": "#464555",
                    "tertiary-container": "#516177",
                    "on-primary": "#ffffff",
                    "on-primary-container": "#dad7ff",
                    "surface-container-lowest": "#ffffff",
                    "on-tertiary": "#ffffff",
                    "surface-container-high": "#e6e8ea",
                    "secondary-fixed-dim": "#bec6e0",
                    "background": "#f7f9fb",
                    "outline-variant": "#c7c4d8",
                    "on-background": "#191c1e",
                    "on-surface": "#191c1e",
                    "secondary-container": "#dae2fd",
                    "primary-fixed": "#e2dfff",
                    "surface-container-low": "#f2f4f6",
                    "surface": "#f7f9fb",
                    "error": "#ba1a1a",
                    "tertiary-fixed": "#d3e4fe",
                    "surface-bright": "#f7f9fb",
                    "on-primary-fixed-variant": "#3323cc",
                    "inverse-primary": "#c3c0ff",
                    "surface-dim": "#d8dadc",
                    "on-tertiary-fixed-variant": "#38485d",
                    "on-secondary-fixed-variant": "#3f465c",
                    "tertiary": "#3a495f",
                    "surface-variant": "#e0e3e5"
            },
            "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
            },
            "spacing": {
                    "margin-desktop": "64px",
                    "container-max": "1440px",
                    "unit": "4px",
                    "margin-mobile": "20px",
                    "gutter": "24px"
            },
            "fontFamily": {
                    "headline-lg-mobile": ["Hanken Grotesk"],
                    "body-sm": ["Inter"],
                    "headline-lg": ["Hanken Grotesk"],
                    "body-lg": ["Inter"],
                    "body-md": ["Inter"],
                    "label-technical": ["JetBrains Mono"],
                    "headline-md": ["Hanken Grotesk"],
                    "headline-xl": ["Hanken Grotesk"]
            },
            "fontSize": {
                    "headline-lg-mobile": ["28px", {"lineHeight": "36px", "fontWeight": "600"}],
                    "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                    "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                    "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                    "label-technical": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "500"}],
                    "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                    "headline-xl": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}]
            }
          },
        },
      }
    </script>
    <style>
        body {
            background-color: var(--bg) !important;
            background-image: var(--bg-image) !important;
            font-family: 'Inter', sans-serif;
            color: var(--text) !important;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .sidebar-active {
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.3);
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        .glass-header {
            background: var(--panel-bg) !important;
            backdrop-filter: blur(12px) !important;
        }
        /* Theme Integration Overrides for Tailwind classes */
        .bg-background {
            background-color: var(--bg) !important;
        }
        .bg-surface {
            background-color: var(--panel-bg) !important;
        }
        .bg-white {
            background-color: var(--card-bg) !important;
            backdrop-filter: blur(12px) !important;
            box-shadow: var(--shadow) !important;
        }
        .border-outline-variant,
        .border-outline-variant\/30 {
            border-color: var(--card-border) !important;
        }
        .text-on-surface-variant {
            color: var(--text-2) !important;
        }
        .text-on-surface {
            color: var(--text) !important;
        }
        .text-on-primary-container {
            color: var(--text) !important;
        }
        .bg-primary-fixed\/30,
        .bg-primary-fixed\/5 {
            background-color: var(--line-soft) !important;
        }
        .bg-surface-container-low {
            background-color: var(--input-bg) !important;
        }
        .text-headline-md, .text-body-md, .text-body-sm, .text-label-technical {
            color: var(--text) !important;
        }
        .note-card:hover .note-actions {
            opacity: 1;
        }
        /* Alert Toast */
        .toast-box {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 2000;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            animation: toastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards,
                       toastOut 0.3s cubic-bezier(0.16, 1, 0.3, 1) 3.5s forwards;
            cursor: pointer;
        }
        @keyframes toastIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes toastOut {
            from { transform: translateY(0); opacity: 1; }
            to { transform: translateY(-20px); opacity: 0; }
        }

        /* Light Theme Overrides */
        html[data-theme="light"] .sidebar-active {
            background-color: #efede6 !important;
            color: #191c1e !important;
            box-shadow: none !important;
            transform: none !important;
        }
        html[data-theme="light"] .sidebar-active span {
            color: #191c1e !important;
        }
        html[data-theme="light"] button.border-dashed {
            border-color: rgba(25, 28, 30, 0.15) !important;
            background: rgba(239, 237, 230, 0.25) !important;
        }
        html[data-theme="light"] button.border-dashed:hover {
            border-color: #191c1e !important;
            background: rgba(239, 237, 230, 0.5) !important;
        }
        html[data-theme="light"] button.border-dashed .text-primary {
            color: #191c1e !important;
        }
        html[data-theme="light"] #viewModal > div,
        html[data-theme="light"] #formModal > div {
            background-color: #ffffff !important;
            backdrop-filter: none !important;
        }
        html[data-theme="light"] #viewModal > div *,
        html[data-theme="light"] #formModal > div * {
            color: #191c1e !important;
        }
        html[data-theme="light"] a.bg-primary,
        html[data-theme="light"] button.bg-primary {
            background-color: #efede6 !important;
            color: #191c1e !important;
            border: 1px solid rgba(25, 28, 30, 0.15) !important;
            box-shadow: 0 2px 8px rgba(25, 28, 30, 0.04) !important;
        }
        html[data-theme="light"] a.bg-primary:hover,
        html[data-theme="light"] button.bg-primary:hover {
            background-color: #e5e3da !important;
            color: #191c1e !important;
        }
        html[data-theme="light"] a.bg-primary span,
        html[data-theme="light"] button.bg-primary span {
            color: #191c1e !important;
        }

        /* ===== Tüm temalar için ortak düzeltmeler ===== */
        /* Arama kutuları: global input[type=text] kuralı pl-10'u eziyordu; ikon ile metin çakışmasın */
        #topSearch, #searchNotes {
            padding-left: 2.5rem !important;
        }
        /* Sabit indigo Tailwind renklerini aktif tema paletine bağla */
        .bg-primary-container {
            background-color: var(--primary) !important;
        }
        .text-on-primary-container,
        .bg-primary-container .material-symbols-outlined {
            color: #ffffff !important;
        }
        .bg-primary-fixed {
            background-color: var(--hover) !important;
        }
        .bg-secondary-fixed {
            background-color: var(--neutral-bg) !important;
        }
        /* Modal panelleri: koyu overlay üstünde grimsi görünmesin — opak zemin */
        #viewModal > div,
        #formModal > div {
            background-color: var(--panel-bg) !important;
            backdrop-filter: blur(20px) !important;
        }
        html[data-theme="light"] #viewModal > div,
        html[data-theme="light"] #formModal > div,
        html[data-theme="rose"] #viewModal > div,
        html[data-theme="rose"] #formModal > div,
        html[data-theme="corporate"] #viewModal > div,
        html[data-theme="corporate"] #formModal > div {
            background-color: #ffffff !important;
            backdrop-filter: none !important;
        }

        /* Corporate (Kurumsal) Theme Overrides */
        html[data-theme="corporate"] .sidebar-active {
            background: #F58220 !important;
            color: #ffffff !important;
            box-shadow: 0 6px 18px rgba(245, 130, 32, .35) !important;
            transform: none !important;
        }
        html[data-theme="corporate"] .sidebar-active span {
            color: #ffffff !important;
        }
        html[data-theme="corporate"] button.border-dashed {
            border-color: rgba(26, 54, 115, 0.20) !important;
            background: rgba(26, 54, 115, 0.04) !important;
        }
        html[data-theme="corporate"] button.border-dashed:hover {
            border-color: #1A3673 !important;
            background: rgba(26, 54, 115, 0.08) !important;
        }
        html[data-theme="corporate"] button.border-dashed .text-primary {
            color: #1A3673 !important;
        }
        html[data-theme="corporate"] #viewModal > div,
        html[data-theme="corporate"] #formModal > div {
            background-color: #ffffff !important;
            backdrop-filter: none !important;
        }
        html[data-theme="corporate"] #viewModal > div *,
        html[data-theme="corporate"] #formModal > div * {
            color: #1A2440 !important;
        }
        html[data-theme="corporate"] a.bg-primary,
        html[data-theme="corporate"] button.bg-primary {
            background: linear-gradient(90deg, #1A3673 0%, #F58220 100%) !important;
            color: #ffffff !important;
            border: none !important;
            box-shadow: 0 4px 20px rgba(245, 130, 32, .30) !important;
        }
        html[data-theme="corporate"] a.bg-primary:hover,
        html[data-theme="corporate"] button.bg-primary:hover {
            box-shadow: 0 6px 26px rgba(245, 130, 32, .45) !important;
        }
        html[data-theme="corporate"] a.bg-primary span,
        html[data-theme="corporate"] button.bg-primary span {
            color: #ffffff !important;
        }
    </style>
</head>
<body class="bg-background"><canvas id="rose-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas><canvas id="ocean-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas><canvas id="dark-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas><canvas id="light-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas><canvas id="corporate-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas>

<?php if ($flash = flash_get()):
    $swalType  = $flash['type'] === 'error' ? 'error' : ($flash['type'] === 'warning' ? 'warning' : 'success');
    $swalTitle = $swalType === 'error' ? 'Hata' : ($swalType === 'warning' ? 'Uyarı' : 'Başarılı');
?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    (function () {
        function showFlash() {
            if (typeof Swal === "undefined") { setTimeout(showFlash, 60); return; }
            Swal.fire({
                title: <?= json_encode($swalTitle, JSON_UNESCAPED_UNICODE) ?>,
                text: <?= json_encode($flash['msg'], JSON_UNESCAPED_UNICODE) ?>,
                icon: <?= json_encode($swalType) ?>,
                confirmButtonText: "Tamam",
                confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue("--primary").trim() || "#1A3673"
            });
        }
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", showFlash);
        } else {
            showFlash();
        }
    })();
    </script>
<?php endif; ?>

<!-- SideNavBar Anchor -->
<aside class="h-screen w-64 fixed left-0 top-0 overflow-y-auto bg-surface border-r border-outline-variant/30 flex flex-col p-4 gap-2 z-50">
    <div class="mb-8 px-4 py-2">
        <h1 class="text-headline-md font-headline-md font-extrabold text-primary">Staj Takip</h1>
        <p class="text-label-technical text-on-surface-variant tracking-wider uppercase">Yönetim Paneli</p>
    </div>
    <nav class="flex flex-col gap-1 flex-1">
        <a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface hover:bg-black/5 transition-all duration-150 rounded-xl" href="index.php">
            <span class="material-symbols-outlined">dashboard</span>
            <span class="font-semibold text-sm">Dashboard</span>
        </a>
        <a class="flex items-center gap-3 px-4 py-3 bg-primary-container text-on-primary-container rounded-xl sidebar-active scale-[1.02] duration-150" href="index.php">
            <span class="material-symbols-outlined">group</span>
            <span class="font-semibold text-sm">Stajyer Listesi</span>
        </a>
    </nav>
</aside>

<!-- Main Wrapper -->
<main class="ml-64 min-h-screen">
    <!-- TopNavBar Anchor -->
    <header class="sticky top-0 z-40 w-full h-16 glass-header border-b border-outline-variant/30 flex justify-between items-center px-8">
        <div class="flex items-center gap-4 flex-1">
            <div class="relative w-full max-w-md">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-xl">search</span>
                <input id="topSearch" class="w-full bg-surface-container-low border-none rounded-full pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-primary/20" placeholder="Sistemde ara..." type="text"/>
            </div>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-on-surface-variant">Profilim</span>
                <div class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container font-bold text-sm"><?= $userInitials ?></div>
            </div>
        </div>
    </header>

    <!-- Content Canvas -->
    <div class="p-8 max-w-[1440px] mx-auto">
        <!-- Breadcrumbs & Title -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-label-technical text-on-surface-variant mb-2">
                    <a class="hover:text-primary transition-colors" href="index.php">STAJYERLER</a>
                    <span class="material-symbols-outlined text-xs">chevron_right</span>
                    <a class="hover:text-primary transition-colors" href="view.php?id=<?= $id ?>"><?= mb_strtoupper($fullName, 'UTF-8') ?></a>
                    <span class="material-symbols-outlined text-xs">chevron_right</span>
                    <span class="text-primary font-bold">STAJ NOTLARI</span>
                </div>
                <h2 class="font-headline-lg text-headline-lg text-on-surface">Performans Kayıtları: <span class="text-primary"><?= e($fullName) ?></span></h2>
                <p class="text-body-md text-on-surface-variant mt-1"><?= $subtitle ?></p>
            </div>
            <a href="view.php?id=<?= $id ?>" class="flex items-center gap-2 px-6 py-3 bg-primary text-on-primary rounded-xl font-semibold shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all" style="text-decoration: none;">
                <span class="material-symbols-outlined">arrow_back</span>
                <span>Geri Dön</span>
            </a>
        </div>

        <!-- Filters Bar -->
        <div class="bg-white border border-outline-variant p-4 rounded-2xl mb-8 flex flex-wrap items-center gap-4 shadow-sm">
            <div class="flex-1 min-w-[240px]">
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
                    <input id="searchNotes" class="w-full pl-10 border-outline-variant rounded-lg focus:border-primary focus:ring-1 focus:ring-primary text-sm" placeholder="Not başlığı veya içerik ara..." type="text"/>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-label-technical text-on-surface-variant">KATEGORİ:</span>
                <select id="filterCategory" class="border-outline-variant rounded-lg text-sm pr-10 focus:border-primary focus:ring-1 focus:ring-primary">
                    <option value="">Tümü</option>
                    <option value="Teknik Performans">Teknik Performans</option>
                    <option value="Davranışsal Gözlem">Davranışsal Gözlem</option>
                    <option value="İdari Süreç">İdari Süreç</option>
                    <option value="Genel">Genel</option>
                </select>
            </div>
        </div>

        <!-- Notes Grid / Bento Style -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="notesGrid">
            <!-- Add Note Card -->
            <button onclick="openFormModal()" class="border-2 border-dashed border-primary/30 bg-primary-fixed/5 rounded-2xl p-6 flex flex-col items-center justify-center gap-3 hover:border-primary hover:bg-primary-fixed/10 transition-all group min-h-[280px]">
                <div class="w-14 h-14 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container group-hover:scale-110 transition-transform shadow-sm">
                    <span class="material-symbols-outlined text-3xl">add</span>
                </div>
                <div class="text-center">
                    <p class="font-bold text-primary">Yeni Not Ekle</p>
                    <p class="text-xs text-on-surface-variant mt-1"><?= e($intern['first_name']) ?> için yeni bir performans kaydı oluştur</p>
                </div>
            </button>

            <?php foreach ($notes as $n):
                $badgeClass = 'bg-secondary-fixed text-secondary';
                if ($n['category'] === 'Teknik Performans') $badgeClass = 'bg-primary-fixed text-primary';
                elseif ($n['category'] === 'İdari Süreç') $badgeClass = 'bg-error-container text-error';
            ?>
                <!-- Note Card -->
                <div class="bg-white border border-outline-variant rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow relative note-card group flex flex-col justify-between min-h-[280px] cursor-pointer"
                     data-id="<?= (int)$n['id'] ?>"
                     data-title="<?= e($n['title']) ?>"
                     data-category="<?= e($n['category']) ?>"
                     data-text="<?= e($n['note_text']) ?>"
                     data-user="<?= e($n['user_name']) ?>"
                     data-date="<?= e(date('d M Y', strtotime($n['created_at']))) ?>"
                     onclick="viewNote(this)">
                    <div>
                        <div class="flex justify-between items-start mb-4" onclick="event.stopPropagation();">
                            <span class="px-2 py-1 <?= $badgeClass ?> text-[10px] font-bold rounded uppercase tracking-wider"><?= e($n['category']) ?></span>
                            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity note-actions">
                                <button onclick="editNote(<?= (int)$n['id'] ?>, '<?= e($n['title']) ?>', '<?= e($n['category']) ?>', `<?= e($n['note_text']) ?>`)" class="p-1.5 text-on-surface-variant hover:text-primary bg-surface-container-low rounded-md" title="Düzenle">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                                <form method="post" onsubmit="return confirm('Bu not silinsin mi?');" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="note_delete">
                                    <input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
                                    <button type="submit" class="p-1.5 text-error hover:bg-error/10 rounded-md" title="Sil">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <h3 class="font-bold text-lg mb-2 truncate-title"><?= e($n['title'] ?: 'Staj Notu') ?></h3>
                        <p class="text-sm text-on-surface-variant mb-4 line-clamp-4 leading-relaxed"><?= e($n['note_text']) ?></p>
                    </div>
                    <div class="text-label-technical text-on-surface-variant pt-4 border-t border-outline-variant/20">
                        <p><?= e(date('d M Y', strtotime($n['created_at']))) ?></p>
                        <p class="font-semibold text-on-surface"><?= e($n['user_name']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination (Static/Visual) -->
        <div class="mt-12 flex justify-between items-center bg-white p-4 rounded-xl border border-outline-variant">
            <span class="text-sm text-on-surface-variant">Toplam <span id="notesCount"><?= count($notes) ?></span> not gösteriliyor</span>
        </div>
    </div>
</main>

<!-- Modals -->
<!-- Add/Edit Modal -->
<div id="formModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="bg-white border border-outline-variant rounded-2xl w-full max-w-lg p-6 shadow-2xl relative overflow-hidden animate-scaleUp">
        <h3 id="modalTitle" class="text-headline-md font-bold text-on-surface mb-6">Yeni Not Ekle</h3>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="formAction" value="note_add">
            <input type="hidden" name="note_id" id="formNoteId" value="0">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Başlık *</label>
                <input type="text" name="title" id="formNoteTitle" required class="w-full border-outline-variant rounded-lg focus:border-primary focus:ring-1 focus:ring-primary text-sm" placeholder="örn. React Component Mimarisi">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Kategori *</label>
                <select name="category" id="formNoteCategory" required class="w-full border-outline-variant rounded-lg focus:border-primary focus:ring-1 focus:ring-primary text-sm">
                    <option value="Teknik Performans">Teknik Performans</option>
                    <option value="Davranışsal Gözlem">Davranışsal Gözlem</option>
                    <option value="İdari Süreç">İdari Süreç</option>
                    <option value="Genel">Genel</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold mb-2">Not İçeriği *</label>
                <textarea name="note_text" id="formNoteText" rows="6" required class="w-full border-outline-variant rounded-lg focus:border-primary focus:ring-1 focus:ring-primary text-sm" placeholder="Stajyer hakkındaki performans değerlendirmenizi yazın…"></textarea>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeFormModal()" class="px-5 py-2.5 border border-outline-variant rounded-xl text-sm font-semibold hover:bg-surface-container transition-colors">Vazgeç</button>
                <button type="submit" class="px-5 py-2.5 bg-primary text-on-primary rounded-xl text-sm font-semibold shadow-lg shadow-primary/20 hover:bg-primary/95 transition-all">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- View Note Modal -->
<div id="viewModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="bg-white border border-outline-variant rounded-2xl w-full max-w-xl p-6 shadow-2xl relative overflow-hidden animate-scaleUp">
        <button onclick="closeViewModal()" class="absolute top-4 right-4 p-1.5 text-on-surface-variant hover:text-on-surface bg-surface-container rounded-full">
            <span class="material-symbols-outlined">close</span>
        </button>
        <div class="mb-4">
            <span id="viewBadge" class="px-2 py-1 text-[10px] font-bold rounded uppercase tracking-wider"></span>
        </div>
        <h3 id="viewTitle" class="text-headline-md font-bold text-on-surface mb-4">Not Detayı</h3>
        <div class="text-sm text-on-surface-variant mb-6 whitespace-pre-wrap leading-relaxed max-h-[300px] overflow-y-auto custom-scrollbar" id="viewText"></div>
        <div class="pt-4 border-t border-outline-variant/30 text-label-technical text-on-surface-variant flex justify-between items-center">
            <div>
                <p class="font-semibold text-on-surface" id="viewUser"></p>
                <p id="viewDate"></p>
            </div>
            <button id="viewEditBtn" class="flex items-center gap-1.5 px-4 py-2 border border-outline-variant rounded-xl text-sm font-semibold hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined text-sm">edit</span> Düzenle
            </button>
        </div>
    </div>
</div>

<script>
    // Ripple and Micro-interactions
    document.querySelectorAll('button, a').forEach(el => {
        el.addEventListener('mousedown', () => el.classList.add('scale-[0.98]'));
        el.addEventListener('mouseup', () => el.classList.remove('scale-[0.98]'));
        el.addEventListener('mouseleave', () => el.classList.remove('scale-[0.98]'));
    });

    // Form Modal Functions
    function openFormModal() {
        document.getElementById('modalTitle').textContent = 'Yeni Not Ekle';
        document.getElementById('formAction').value = 'note_add';
        document.getElementById('formNoteId').value = '0';
        document.getElementById('formNoteTitle').value = '';
        document.getElementById('formNoteCategory').value = 'Teknik Performans';
        document.getElementById('formNoteText').value = '';
        document.getElementById('formModal').classList.remove('hidden');
    }

    function editNote(id, title, category, text) {
        document.getElementById('modalTitle').textContent = 'Notu Düzenle';
        document.getElementById('formAction').value = 'note_edit';
        document.getElementById('formNoteId').value = id;
        document.getElementById('formNoteTitle').value = title;
        document.getElementById('formNoteCategory').value = category;
        document.getElementById('formNoteText').value = text;
        document.getElementById('formModal').classList.remove('hidden');
    }

    function closeFormModal() {
        document.getElementById('formModal').classList.add('hidden');
    }

    // View Modal Functions
    function viewNote(card) {
        var id = card.dataset.id;
        var title = card.dataset.title;
        var category = card.dataset.category;
        var text = card.dataset.text;
        var user = card.dataset.user;
        var date = card.dataset.date;

        var badge = document.getElementById('viewBadge');
        badge.textContent = category;
        badge.className = "px-2 py-1 text-[10px] font-bold rounded uppercase tracking-wider ";
        if (category === 'Teknik Performans') badge.className += 'bg-primary-fixed text-primary';
        else if (category === 'İdari Süreç') badge.className += 'bg-error-container text-error';
        else badge.className += 'bg-secondary-fixed text-secondary';

        document.getElementById('viewTitle').textContent = title || 'Staj Notu';
        document.getElementById('viewText').textContent = text;
        document.getElementById('viewUser').textContent = user;
        document.getElementById('viewDate').textContent = date;

        document.getElementById('viewEditBtn').onclick = function () {
            closeViewModal();
            editNote(id, title, category, text);
        };

        document.getElementById('viewModal').classList.remove('hidden');
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
    }

    // Search and Filters
    var searchInput = document.getElementById('searchNotes');
    var categorySelect = document.getElementById('filterCategory');
    var topSearch = document.getElementById('topSearch');

    function filterNotes() {
        var query = searchInput.value.toLowerCase().trim();
        var topQuery = topSearch ? topSearch.value.toLowerCase().trim() : '';
        var category = categorySelect.value;
        var cards = document.querySelectorAll('.note-card');
        var visibleCount = 0;

        cards.forEach(function (card) {
            var title = card.dataset.title.toLowerCase();
            var text = card.dataset.text.toLowerCase();
            var cardCat = card.dataset.category;

            var matchesSearch = (title.includes(query) || text.includes(query));
            var matchesTop = (title.includes(topQuery) || text.includes(topQuery));
            var matchesCat = (category === '' || cardCat === category);

            if (matchesSearch && matchesTop && matchesCat) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        var countEl = document.getElementById('notesCount');
        if (countEl) countEl.textContent = visibleCount;
    }

    searchInput.addEventListener('input', filterNotes);
    categorySelect.addEventListener('change', filterNotes);
    if (topSearch) topSearch.addEventListener('input', filterNotes);
    // Rose petal animation
    (function () {
        const canvas = document.getElementById("rose-bg");
        if (!canvas) return;
        const ctx = canvas.getContext("2d");

        let W, H, dpr;
        function resize() {
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            W = window.innerWidth;
            H = window.innerHeight;
            canvas.width  = Math.floor(W * dpr);
            canvas.height = Math.floor(H * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        resize();
        window.addEventListener("resize", resize);

        const colors = ["#f9a8c9", "#f48fb1", "#ec7fa9", "#f7b7d0", "#e56b95", "#fcc5dc"];

        function makePetal() {
            return {
                x: Math.random() * W,
                y: Math.random() * -H,
                size: 8 + Math.random() * 10,
                speedY: 0.6 + Math.random() * 1.4,
                swaySpeed: 0.5 + Math.random() * 1.2,
                angle: Math.random() * Math.PI * 2,
                spin: (Math.random() - 0.5) * 0.04,
                phase: Math.random() * Math.PI * 2,
                color: colors[(Math.random() * colors.length) | 0],
                opacity: 0.65 + Math.random() * 0.35
            };
        }

        const count = Math.min(70, Math.max(28, Math.floor(W / 22)));
        let petals = Array.from({ length: count }, makePetal);

        function drawPetal(p) {
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.angle);
            ctx.globalAlpha = p.opacity;

            const s = p.size;
            const grad = ctx.createLinearGradient(0, -s, 0, s);
            grad.addColorStop(0, "#ffffff");
            grad.addColorStop(0.4, p.color);
            grad.addColorStop(1, p.color);
            ctx.fillStyle = grad;

            ctx.beginPath();
            ctx.moveTo(0, -s);
            ctx.bezierCurveTo( s, -s * 0.5,  s * 0.6, s * 0.8, 0, s);
            ctx.bezierCurveTo(-s * 0.6, s * 0.8, -s, -s * 0.5, 0, -s);
            ctx.closePath();
            ctx.fill();

            ctx.globalAlpha = p.opacity * 0.35;
            ctx.strokeStyle = "#ffffff";
            ctx.lineWidth = 0.8;
            ctx.beginPath();
            ctx.moveTo(0, -s * 0.7);
            ctx.lineTo(0, s * 0.7);
            ctx.stroke();

            ctx.restore();
        }

        function frame() {
            if (!document.documentElement.classList.contains("has-bg-animation") || document.documentElement.dataset.theme !== "rose") {
                canvas.style.display = "none";
                ctx.clearRect(0, 0, W, H);
                requestAnimationFrame(frame);
                return;
            }

            canvas.style.display = "block";
            ctx.clearRect(0, 0, W, H);
            for (const p of petals) {
                p.y += p.speedY;
                p.phase += 0.016 * p.swaySpeed;
                p.x += Math.sin(p.phase) * 0.6;
                p.angle += p.spin;

                if (p.y - p.size > H) {
                    p.y = -p.size - Math.random() * 60;
                    p.x = Math.random() * W;
                }
                drawPetal(p);
            }
            requestAnimationFrame(frame);
        }
        frame();
    })();

    // Ocean theme rain droplets animation
    (function () {
        const canvas = document.getElementById("ocean-bg");
        if (!canvas) return;
        const ctx = canvas.getContext("2d");

        let W, H, dpr;
        function resize() {
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            W = window.innerWidth; H = window.innerHeight;
            canvas.width = Math.floor(W * dpr);
            canvas.height = Math.floor(H * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        resize();
        window.addEventListener("resize", resize);

        function makeDrop() {
            return {
                x: Math.random() * (W + 100) - 50,
                y: Math.random() * -H,
                len: 10 + Math.random() * 14,
                speed: 2.2 + Math.random() * 3.0,
                opacity: 0.10 + Math.random() * 0.22
            };
        }
        const dropCount = Math.min(130, Math.max(50, Math.floor(W / 11)));
        let drops = Array.from({ length: dropCount }, makeDrop);
        const slantX = -0.8;

        let ripples = [];
        function spawnRipple() {
            ripples.push({
                x: Math.random() * W,
                y: H - Math.random() * (H * 0.28),
                r: 0, opacity: 0.4
            });
        }

        let frameNo = 0;
        function frame() {
            if (!document.documentElement.classList.contains("has-bg-animation") || document.documentElement.dataset.theme !== "ocean") {
                canvas.style.display = "none";
                ctx.clearRect(0, 0, W, H);
                requestAnimationFrame(frame);
                return;
            }

            canvas.style.display = "block";
            frameNo++;
            ctx.clearRect(0, 0, W, H);

            ctx.lineCap = "round";
            for (const d of drops) {
                ctx.strokeStyle = "rgba(210, 240, 255," + d.opacity + ")";
                ctx.lineWidth = 1.0;
                ctx.beginPath();
                ctx.moveTo(d.x, d.y);
                ctx.lineTo(d.x + slantX * d.len * 0.4, d.y + d.len);
                ctx.stroke();
                d.x += slantX * (d.speed * 0.12);
                d.y += d.speed;
                if (d.y > H) { d.x = Math.random() * (W + 100) - 50; d.y = -20; }
            }

            if (frameNo % 12 === 0) spawnRipple();

            for (let i = ripples.length - 1; i >= 0; i--) {
                const rp = ripples[i];
                rp.r += 0.4;
                rp.opacity -= 0.008;
                if (rp.opacity <= 0) { ripples.splice(i, 1); continue; }
                ctx.strokeStyle = "rgba(220, 245, 255," + rp.opacity + ")";
                ctx.lineWidth = 1.2;
                ctx.beginPath();
                ctx.ellipse(rp.x, rp.y, rp.r, rp.r * 0.35, 0, 0, Math.PI * 2);
                ctx.stroke();
            }

            requestAnimationFrame(frame);
        }
        frame();
    })();

    // Dark theme star animation
    (function () {
        const canvas = document.getElementById("dark-bg");
        if (!canvas) return;
        const ctx = canvas.getContext("2d");

        let W, H, dpr;
        function resize() {
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            W = window.innerWidth; H = window.innerHeight;
            canvas.width = Math.floor(W * dpr);
            canvas.height = Math.floor(H * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        resize();
        window.addEventListener("resize", resize);

        function makeStar() {
            return {
                x: Math.random() * W,
                y: Math.random() * H,
                r: Math.random() * 1.4 + 0.3,
                baseA: 0.2 + Math.random() * 0.5,
                twSpeed: 0.5 + Math.random() * 1.8,
                phase: Math.random() * Math.PI * 2,
                warm: Math.random() < 0.25
            };
        }
        const starCount = Math.min(220, Math.max(90, Math.floor((W * H) / 9000)));
        let stars = Array.from({ length: starCount }, makeStar);

        let shooting = null;
        function spawnShooting() {
            shooting = {
                x: Math.random() * W * 0.6,
                y: Math.random() * H * 0.4,
                vx: 6 + Math.random() * 4,
                vy: 3 + Math.random() * 2,
                life: 1
            };
        }

        let t = 0;
        function frame() {
            if (!document.documentElement.classList.contains("has-bg-animation") || document.documentElement.dataset.theme !== "dark") {
                canvas.style.display = "none";
                ctx.clearRect(0, 0, W, H);
                requestAnimationFrame(frame);
                return;
            }

            canvas.style.display = "block";
            t += 0.016;
            ctx.clearRect(0, 0, W, H);

            for (const s of stars) {
                const tw = Math.sin(t * s.twSpeed + s.phase) * 0.5 + 0.5;
                const alpha = s.baseA * (0.35 + tw * 0.65);
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                ctx.fillStyle = s.warm
                    ? "rgba(255, 240, 210," + alpha + ")"
                    : "rgba(220, 235, 255," + alpha + ")";
                ctx.fill();
                if (tw > 0.85 && s.r > 1) {
                    ctx.beginPath();
                    ctx.arc(s.x, s.y, s.r * 2.6, 0, Math.PI * 2);
                    ctx.fillStyle = "rgba(180, 210, 255," + alpha * 0.12 + ")";
                    ctx.fill();
                }
            }

            if (!shooting && Math.random() < 0.004) spawnShooting();

            if (shooting) {
                const s = shooting;
                const tailX = s.x - s.vx * 6, tailY = s.y - s.vy * 6;
                const grad = ctx.createLinearGradient(tailX, tailY, s.x, s.y);
                grad.addColorStop(0, "rgba(255,255,255,0)");
                grad.addColorStop(1, "rgba(255,255,255," + (0.8 * s.life) + ")");
                ctx.strokeStyle = grad;
                ctx.lineWidth = 2;
                ctx.lineCap = "round";
                ctx.beginPath();
                ctx.moveTo(tailX, tailY);
                ctx.lineTo(s.x, s.y);
                ctx.stroke();

                s.x += s.vx; s.y += s.vy; s.life -= 0.012;
                if (s.life <= 0 || s.x > W + 50 || s.y > H + 50) shooting = null;
            }

            requestAnimationFrame(frame);
        }
        frame();
    })();

    // Light theme shapes animation
    (function () {
        const canvas = document.getElementById("light-bg");
        if (!canvas) return;
        const ctx = canvas.getContext("2d");

        let W, H, dpr;
        function resize() {
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            W = window.innerWidth; H = window.innerHeight;
            canvas.width = Math.floor(W * dpr);
            canvas.height = Math.floor(H * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        resize();
        window.addEventListener("resize", resize);

        const palette = ["#c7d7f0", "#d7c7f0", "#c7ecf0", "#f0d7e4", "#d7e8c7", "#cfd8ea"];
        const shapeTypes = ["circle", "triangle", "square", "ring"];

        function makeShape() {
            return {
                x: Math.random() * W,
                y: Math.random() * H,
                size: 40 + Math.random() * 90,
                type: shapeTypes[(Math.random() * shapeTypes.length) | 0],
                color: palette[(Math.random() * palette.length) | 0],
                opacity: 0.18 + Math.random() * 0.22,
                angle: Math.random() * Math.PI * 2,
                spin: (Math.random() - 0.5) * 0.006,
                vx: (Math.random() - 0.5) * 0.35,
                vy: (Math.random() - 0.5) * 0.35,
                phase: Math.random() * Math.PI * 2
            };
        }
        const count = Math.min(16, Math.max(7, Math.floor(W / 130)));
        let shapes = Array.from({ length: count }, makeShape);

        function drawShape(s) {
            ctx.save();
            ctx.translate(s.x, s.y);
            ctx.rotate(s.angle);
            ctx.globalAlpha = s.opacity;
            ctx.fillStyle = s.color;
            ctx.strokeStyle = s.color;
            ctx.lineWidth = 6;
            const r = s.size / 2;

            if (s.type === "circle") {
                ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.fill();
            } else if (s.type === "ring") {
                ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.stroke();
            } else if (s.type === "square") {
                const rr = r * 0.3;
                ctx.beginPath();
                ctx.moveTo(-r + rr, -r);
                ctx.arcTo(  r, -r,  r,  r, rr);
                ctx.arcTo(  r,  r, -r,  r, rr);
                ctx.arcTo(-r,  r, -r, -r, rr);
                ctx.arcTo(-r, -r,  r, -r, rr);
                ctx.closePath(); ctx.fill();
            } else {
                ctx.beginPath();
                ctx.moveTo(0, -r);
                ctx.lineTo(r * 0.87, r * 0.5);
                ctx.lineTo(-r * 0.87, r * 0.5);
                ctx.closePath(); ctx.fill();
            }
            ctx.restore();
        }

        let t = 0;
        function frame() {
            if (!document.documentElement.classList.contains("has-bg-animation") || document.documentElement.dataset.theme !== "light") {
                canvas.style.display = "none";
                ctx.clearRect(0, 0, W, H);
                requestAnimationFrame(frame);
                return;
            }

            canvas.style.display = "block";
            t += 0.016;
            ctx.clearRect(0, 0, W, H);

            for (const s of shapes) {
                s.x += s.vx + Math.sin(t * 0.4 + s.phase) * 0.15;
                s.y += s.vy + Math.cos(t * 0.35 + s.phase) * 0.15;
                s.angle += s.spin;

                const m = s.size;
                if (s.x < -m) s.x = W + m;
                if (s.x > W + m) s.x = -m;
                if (s.y < -m) s.y = H + m;
                if (s.y > H + m) s.y = -m;

                drawShape(s);
            }
            requestAnimationFrame(frame);
        }
        frame();
    })();

    // Corporate theme animation — süzülen lacivert/turuncu "belge" kartları
    (function () {
        const canvas = document.getElementById("corporate-bg");
        if (!canvas) return;
        const ctx = canvas.getContext("2d");

        let W, H, dpr;
        function resize() {
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            W = window.innerWidth; H = window.innerHeight;
            canvas.width = Math.floor(W * dpr);
            canvas.height = Math.floor(H * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        }
        resize();
        window.addEventListener("resize", resize);

        const palette = ["#1A3673", "#F58220"];

        function makeCard() {
            return {
                x: Math.random() * W,
                y: Math.random() * H + H,
                w: 26 + Math.random() * 20,
                h: 34 + Math.random() * 26,
                color: palette[(Math.random() * palette.length) | 0],
                opacity: 0.06 + Math.random() * 0.10,
                speed: 0.25 + Math.random() * 0.5,
                angle: (Math.random() - 0.5) * 0.35,
                spin: (Math.random() - 0.5) * 0.0025,
                swaySpeed: 0.3 + Math.random() * 0.6,
                phase: Math.random() * Math.PI * 2
            };
        }
        const count = Math.min(22, Math.max(10, Math.floor(W / 110)));
        let cards = Array.from({ length: count }, makeCard);

        function drawCard(c) {
            ctx.save();
            ctx.translate(c.x, c.y);
            ctx.rotate(c.angle);
            ctx.globalAlpha = c.opacity;
            ctx.fillStyle = c.color;
            const rr = 4;
            const w = c.w, h = c.h;
            ctx.beginPath();
            ctx.moveTo(-w / 2 + rr, -h / 2);
            ctx.arcTo(w / 2, -h / 2, w / 2, h / 2, rr);
            ctx.arcTo(w / 2, h / 2, -w / 2, h / 2, rr);
            ctx.arcTo(-w / 2, h / 2, -w / 2, -h / 2, rr);
            ctx.arcTo(-w / 2, -h / 2, w / 2, -h / 2, rr);
            ctx.closePath();
            ctx.fill();

            ctx.globalAlpha = c.opacity * 1.4;
            ctx.strokeStyle = "#ffffff";
            ctx.lineWidth = 1;
            for (let i = 0; i < 3; i++) {
                const ly = -h / 2 + h * (0.3 + i * 0.22);
                ctx.beginPath();
                ctx.moveTo(-w / 2 + 5, ly);
                ctx.lineTo(w / 2 - 5, ly);
                ctx.stroke();
            }
            ctx.restore();
        }

        let t = 0;
        function frame() {
            if (!document.documentElement.classList.contains("has-bg-animation") || document.documentElement.dataset.theme !== "corporate") {
                canvas.style.display = "none";
                ctx.clearRect(0, 0, W, H);
                requestAnimationFrame(frame);
                return;
            }

            canvas.style.display = "block";
            t += 0.016;
            ctx.clearRect(0, 0, W, H);

            for (const c of cards) {
                c.y -= c.speed;
                c.phase += 0.016 * c.swaySpeed;
                c.x += Math.sin(c.phase) * 0.35;
                c.angle += c.spin;

                if (c.y + c.h < 0) {
                    c.y = H + c.h + Math.random() * 60;
                    c.x = Math.random() * W;
                }
                drawCard(c);
            }
            requestAnimationFrame(frame);
        }
        frame();
    })();

    /* İşlem sonrası sayfa en üste atmasın; gönderimden önceki kaydırma konumuna dön */
    (function () {
        var KEY = "scrollRestore:" + location.pathname;
        document.addEventListener("submit", function () {
            try { sessionStorage.setItem(KEY, String(window.scrollY || window.pageYOffset || 0)); } catch (e) {}
        }, true);
        function restore() {
            try {
                var y = sessionStorage.getItem(KEY);
                if (y !== null) {
                    sessionStorage.removeItem(KEY);
                    window.scrollTo(0, parseInt(y, 10) || 0);
                }
            } catch (e) {}
        }
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", restore);
        } else {
            restore();
        }
    })();
</script>
</body>
</html>
