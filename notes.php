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
                    "primary": "#3525cd",
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
            background-color: #f7f9fb;
            font-family: 'Inter', sans-serif;
            color: #191c1e;
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
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
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
    </style>
</head>
<body class="bg-background">

<?php if ($flash = flash_get()): ?>
    <div class="toast-box <?= $flash['type'] === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>" onclick="this.style.display='none'">
        <?= e($flash['msg']) ?>
    </div>
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
        <a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface hover:bg-black/5 transition-all duration-150 rounded-xl" href="index.php">
            <span class="material-symbols-outlined">event_busy</span>
            <span class="font-semibold text-sm">Devamsızlık Takibi</span>
        </a>
    </nav>
    <div class="mt-4 p-4 bg-primary-fixed/30 rounded-2xl border border-primary-fixed-dim/20">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center text-on-primary-container font-bold text-sm"><?= $userInitials ?></div>
            <div>
                <p class="text-sm font-semibold"><?= e($activeUser) ?></p>
                <p class="text-xs text-on-surface-variant">Yetkili Sorumlu</p>
            </div>
        </div>
        <a href="form.php" class="block w-full py-2 bg-primary text-white text-center rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors">
            Yeni Stajyer Ekle
        </a>
    </div>
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
            <button onclick="openFormModal()" class="flex items-center gap-2 px-6 py-3 bg-primary text-on-primary rounded-xl font-semibold shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all">
                <span class="material-symbols-outlined">add_circle</span>
                <span>Yeni Not Ekle</span>
            </button>
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
</script>
</body>
</html>
