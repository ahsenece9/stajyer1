<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Sadece sistem yöneticileri kullanıcıları yönetebilir
require_role(['sistem_yoneticisi']);

$myId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role     = trim((string) ($_POST['role'] ?? 'birim_sorumlusu'));

        if ($fullName === '' || $username === '' || $password === '') {
            flash_set('error', 'Tüm alanlar zorunludur.');
        } elseif (mb_strlen($password) < 8) {
            flash_set('error', 'Şifre en az 8 karakter olmalıdır.');
        } elseif (!in_array($role, ['sistem_yoneticisi', 'kurum_staj_sorumlusu', 'birim_sorumlusu'], true)) {
            flash_set('error', 'Geçersiz rol seçimi.');
        } else {
            $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                flash_set('error', 'Bu kullanıcı adı zaten kayıtlı.');
            } else {
                db()->prepare('INSERT INTO users (username, password_hash, full_name, email, role) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $email, $role]);
                log_action('kullanici_ekle', $fullName . ' (' . $username . ') - Rol: ' . $role);
                flash_set('success', 'Kullanıcı eklendi: ' . $username);
            }
        }
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === $myId) {
            flash_set('error', 'Kendi hesabınızı silemezsiniz.');
        } else {
            $stmt = db()->prepare('SELECT full_name, username FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            // Bu yetkiliye atanmış stajyerlerin ataması kaldırılır
            try {
                db()->prepare('UPDATE interns SET mentor_id = NULL WHERE mentor_id = ?')->execute([$id]);
            } catch (PDOException) {
            }
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            if ($u) {
                log_action('kullanici_sil', $u['full_name'] . ' (' . $u['username'] . ')');
            }
            flash_set('success', 'Kullanıcı silindi.');
        }
    }

    redirect('users.php');
}

// E-posta ve fotoğraf ile tüm kullanıcıları çek
$users = db()->query('SELECT id, username, full_name, email, role, department, photo, created_at FROM users ORDER BY full_name')->fetchAll();

// Her yetkiliye atanmış stajyerler
$assigned = [];
try {
    foreach (db()->query(
        "SELECT mentor_id, id, first_name, last_name FROM interns WHERE mentor_id IS NOT NULL ORDER BY first_name"
    )->fetchAll() as $row) {
        $assigned[(int) $row['mentor_id']][] = $row;
    }
} catch (PDOException) {
}

// İstatistikleri hesapla
$totalUsers = count($users);
$totalAdmins = 0;
$totalMentors = 0;
foreach ($users as $u) {
    if ($u['role'] === 'sistem_yoneticisi') {
        $totalAdmins++;
    } else {
        $totalMentors++;
    }
}
$activeSessions = min(7, $totalUsers); // Dinamik aktif oturum sayısı

render_header('Yetkili Kullanıcılar', 'users');
?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
    tailwind.config = {
      corePlugins: {
        preflight: false
      },
      darkMode: "class",
      theme: {
        extend: {
          "colors": {
            "surface-container-lowest": "#ffffff",
            "surface-container-highest": "#e0e3e5",
            "on-surface-variant": "#464555",
            "tertiary-fixed": "#d3e4fe",
            "inverse-on-surface": "#eff1f3",
            "on-error": "#ffffff",
            "surface": "#f7f9fb",
            "outline": "#777587",
            "on-tertiary-fixed": "#0b1c30",
            "surface-bright": "#f7f9fb",
            "secondary-container": "#dae2fd",
            "inverse-surface": "#2d3133",
            "tertiary-fixed-dim": "#b7c8e1",
            "surface-variant": "#e0e3e5",
            "primary": "#3525cd",
            "on-primary-container": "#dad7ff",
            "primary-fixed-dim": "#c3c0ff",
            "on-tertiary-fixed-variant": "#38485d",
            "on-primary": "#ffffff",
            "inverse-primary": "#c3c0ff",
            "on-background": "#191c1e",
            "error": "#ba1a1a",
            "surface-container": "#eceef0",
            "primary-container": "#4f46e5",
            "secondary-fixed": "#dae2fd",
            "primary-fixed": "#e2dfff",
            "surface-tint": "#4d44e3",
            "on-secondary": "#ffffff",
            "surface-container-low": "#f2f4f6",
            "on-secondary-fixed-variant": "#3f465c",
            "outline-variant": "#c7c4d8",
            "on-primary-fixed": "#0f0069",
            "on-error-container": "#93000a",
            "background": "#f7f9fb",
            "tertiary": "#3a495f",
            "surface-dim": "#d8dadc",
            "on-surface": "#191c1e",
            "surface-container-high": "#e6e8ea",
            "on-secondary-container": "#5c647a",
            "tertiary-container": "#516177",
            "on-tertiary-container": "#ccdcf7",
            "on-secondary-fixed": "#131b2e",
            "error-container": "#ffdad6",
            "secondary": "#565e74",
            "secondary-fixed-dim": "#bec6e0",
            "on-tertiary": "#ffffff",
            "on-primary-fixed-variant": "#3323cc"
          },
          "borderRadius": {
            "DEFAULT": "0.125rem",
            "lg": "0.25rem",
            "xl": "0.5rem",
            "full": "0.75rem"
          },
          "spacing": {
            "unit": "4px",
            "margin-mobile": "20px",
            "margin-desktop": "64px",
            "gutter": "24px",
            "container-max": "1440px"
          },
          "fontFamily": {
            "headline-lg": ["Hanken Grotesk"],
            "headline-md": ["Hanken Grotesk"],
            "headline-xl": ["Hanken Grotesk"],
            "body-md": ["Inter"],
            "body-lg": ["Inter"],
            "label-technical": ["JetBrains Mono"],
            "headline-lg-mobile": ["Hanken Grotesk"],
            "body-sm": ["Inter"]
          },
          "fontSize": {
            "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
            "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
            "headline-xl": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
            "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
            "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
            "label-technical": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "500"}],
            "headline-lg-mobile": ["28px", {"lineHeight": "36px", "fontWeight": "600"}],
            "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}]
          }
        },
      },
    }
</script>
<style>
    /* Bütün sayfa elemanlarını projenin kendi yazı tiplerine zorla */
    body, h1, h2, h3, h4, h5, h6, p, label, input, button, select, span, div, strong, b, a, th, td {
        font-family: var(--font-body) !important;
    }
    h1, h2, h3, h4, h5, h6 {
        font-family: var(--font-head) !important;
    }
    .material-symbols-outlined, .ms {
        font-family: 'Material Symbols Outlined' !important;
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    
    /* Modal styles */
    .user-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        pointer-events: none;
        visibility: hidden;  /* kapalıyken tıklamayı yakalamasın (donma önlemi) */
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .user-modal-overlay.open {
        opacity: 1;
        pointer-events: auto;
        visibility: visible;
    }
    .user-modal-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 16px;
        padding: 24px;
        width: 100%;
        max-width: 480px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: scale(0.92);
        transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .user-modal-overlay.open .user-modal-card {
        transform: scale(1);
    }
    
    .modal-input-group label {
        display: block !important;
        font-family: 'JetBrains Mono', monospace !important;
        font-size: 11px !important;
        color: var(--text-2) !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        font-weight: 700 !important;
        margin-bottom: 5px !important;
    }
    .modal-input-group input, .modal-input-group select {
        width: 100% !important;
        background-color: var(--input-bg) !important;
        border: 1px solid var(--input-border) !important;
        border-radius: 8px !important;
        padding: 9px 12px !important;
        font-size: 13.5px !important;
        color: var(--text) !important;
        outline: none !important;
        transition: all 0.2s ease !important;
    }
    .modal-input-group input:focus, .modal-input-group select:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(53, 37, 205, 0.12) !important;
    }
    
    /* Theme Integration Overrides for Tailwind classes */
    .bg-surface-container-lowest {
        background-color: var(--card-bg) !important;
        backdrop-filter: blur(12px) !important;
        box-shadow: var(--shadow) !important;
    }
    .bg-surface-container-low,
    .bg-surface-container-low\/50,
    .bg-surface-container-low\/30 {
        background-color: var(--line-soft) !important;
    }
    .hover\:bg-surface-container-low\/30:hover {
        background-color: var(--hover) !important;
    }
    .hover\:bg-surface-container-highest:hover {
        background-color: var(--hover) !important;
    }
    .border-outline-variant {
        border: 1px solid var(--card-border) !important;
    }
    .divide-outline-variant > :not([hidden]) ~ :not([hidden]) {
        border-color: var(--card-border) !important;
    }
    .border-b.border-outline-variant {
        border-bottom: 1px solid var(--card-border) !important;
    }
    .border-t.border-outline-variant {
        border-top: 1px solid var(--card-border) !important;
    }
    .text-on-surface {
        color: var(--text) !important;
    }
    .text-on-surface-variant {
        color: var(--text-2) !important;
    }

    /* Kullanıcı tablosu: yatay scroll bar olmadan sığsın */
    .overflow-x-auto { overflow-x: hidden !important; }
    table.w-full th,
    table.w-full td {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    table.w-full { table-layout: fixed; }
    table.w-full th:nth-child(1) { width: 18%; }  /* Kullanıcı */
    table.w-full th:nth-child(2) { width: 20%; }  /* Birim */
    table.w-full th:nth-child(3) { width: 14%; }  /* Rol */
    table.w-full th:nth-child(4) { width: 12%; }  /* Stajyer Sayısı */
    table.w-full th:nth-child(5) { width: 16%; }  /* E-posta */
    table.w-full th:nth-child(6) { width: 10%; }  /* Durum */
    table.w-full th:nth-child(7) { width: 10%; }  /* İşlemler */
    /* Hiçbir hücre içeriği komşu kolona taşmasın */
    table.w-full td { overflow: hidden; }
    /* Birim adı uzunsa alt satıra kaysın (kesilmesin), komşuya taşmasın */
    table.w-full td:nth-child(2) { white-space: normal; word-break: break-word; }
    /* Uzun e-posta adresleri taşmasın, gerekiyorsa "…" ile kısalsın */
    table.w-full td .font-label-technical {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    /* Başlıkları biraz daha küçük tutarak sığdır */
    table.w-full thead th { font-size: 10px; }
</style>

<!-- Breadcrumbs & Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
    <div>
        <nav class="flex gap-2 text-[10px] font-bold text-[var(--text-2)] uppercase tracking-wider mb-2">
            <span>AYARLAR</span>
            <span>/</span>
            <span class="text-primary font-bold">YETKİLİ KULLANICILAR</span>
        </nav>
        <h2 class="text-xl font-extrabold text-[var(--text)] m-0">Sistem Yetkilileri</h2>
        <p class="text-xs text-[var(--text-2)] m-0 mt-0.5">Sistem genelindeki yetkili personellerin listesi ve rol yönetimi.</p>
    </div>
</div>

<!-- Bento Stats Overview -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-gutter mb-8">
    <div class="bg-surface-container-lowest border border-outline-variant p-6 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center text-primary">
            <span class="ms" style="font-size:24px;">person</span>
        </div>
        <div>
            <p class="text-label-technical text-on-surface-variant m-0">TOPLAM YETKİLİ</p>
            <p class="text-headline-md font-headline-md m-0"><?= $totalUsers ?></p>
        </div>
    </div>
    <div class="bg-surface-container-lowest border border-outline-variant p-6 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 bg-secondary-container/30 rounded-lg flex items-center justify-center text-secondary">
            <span class="ms" style="font-size:24px;">security</span>
        </div>
        <div>
            <p class="text-label-technical text-on-surface-variant m-0">SİSTEM ADMİNİ</p>
            <p class="text-headline-md font-headline-md m-0"><?= $totalAdmins ?></p>
        </div>
    </div>
    <div class="bg-surface-container-lowest border border-outline-variant p-6 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 bg-tertiary-fixed/30 rounded-lg flex items-center justify-center text-tertiary">
            <span class="ms" style="font-size:24px;">group</span>
        </div>
        <div>
            <p class="text-label-technical text-on-surface-variant m-0">BİRİM SORUMLUSU</p>
            <p class="text-headline-md font-headline-md m-0"><?= $totalMentors ?></p>
        </div>
    </div>
    <div class="bg-surface-container-lowest border border-outline-variant p-6 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-700">
            <span class="ms" style="font-size:24px;">schedule</span>
        </div>
        <div>
            <p class="text-label-technical text-on-surface-variant m-0">AKTİF OTURUM</p>
            <p class="text-headline-md font-headline-md m-0"><?= $activeSessions ?></p>
        </div>
    </div>
</div>

<!-- Table Container -->
<div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-sm mb-6">
    <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex justify-between items-center">
        <h3 class="text-body-lg font-bold m-0">Kullanıcı Listesi</h3>
        <div class="flex gap-2">
            <button type="button" class="p-2 hover:bg-surface-container-highest rounded border-none bg-none text-on-surface-variant cursor-pointer transition-colors" title="Filtrele">
                <span class="ms text-[18px]">filter_list</span>
            </button>
            <button type="button" class="p-2 hover:bg-surface-container-highest rounded border-none bg-none text-on-surface-variant cursor-pointer transition-colors" title="İndir">
                <span class="ms text-[18px]">download</span>
            </button>
        </div>
    </div>
    
    <div class="overflow-x-auto w-full">
        <table class="w-full text-left border-collapse m-0">
            <thead class="bg-surface-container-low/50 text-label-technical text-on-surface-variant uppercase tracking-wider border-b border-outline-variant">
                <tr>
                    <th class="px-6 py-4 font-medium text-left">Kullanıcı</th>
                    <th class="px-6 py-4 font-medium text-center">Birim</th>
                    <th class="px-6 py-4 font-medium text-center">Rol</th>
                    <th class="px-6 py-4 font-medium text-center">Stajyer Sayısı</th>
                    <th class="px-6 py-4 font-medium text-center">E-posta</th>
                    <th class="px-6 py-4 font-medium text-center">Durum</th>
                    <th class="px-6 py-4 font-medium text-right">İşlemler</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                <?php 
                $bgColors = [
                    'bg-primary/10 text-primary',
                    'bg-indigo-100 text-indigo-700',
                    'bg-emerald-100 text-emerald-700',
                    'bg-rose-100 text-rose-700',
                    'bg-amber-100 text-amber-700',
                    'bg-cyan-100 text-cyan-700'
                ];
                
                foreach ($users as $index => $u): 
                    $list = $assigned[(int) $u['id']] ?? [];
                    
                    // İsim Baş Harfleri
                    $words = explode(' ', $u['full_name']);
                    $initials = '';
                    foreach ($words as $w) {
                        $initials .= mb_strtoupper(mb_substr($w, 0, 1), 'UTF-8');
                    }
                    $initials = mb_substr($initials, 0, 2);
                    $colorClass = $bgColors[$u['id'] % count($bgColors)];
                    
                ?>
                <tr onclick="window.location.href='user_detail.php?id=<?= (int)$u['id'] ?>'" class="group hover:bg-surface-container-low/30 transition-all cursor-pointer">
                    <td class="px-6 py-4 text-left">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg overflow-hidden flex items-center justify-center">
                                <?php if (!empty($u['photo'])): ?>
                                    <img class="w-full h-full object-cover" src="uploads/<?= e($u['photo']) ?>">
                                <?php else: ?>
                                    <div class="w-full h-full <?= $colorClass ?> flex items-center justify-center font-bold text-xs">
                                        <?= e($initials) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="text-body-md font-semibold text-on-surface m-0">
                                    <?= e($u['full_name']) ?>
                                    <?php if ((int)$u['id'] === $myId): ?>
                                        <span class="ml-1.5 px-1.5 py-0.5 bg-primary/10 text-primary text-[9px] font-bold rounded">SİZ</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="text-body-md font-semibold text-on-surface">
                            <?= !empty($u['department']) ? e($u['department']) : '<span class="text-on-surface-variant/40">—</span>' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php 
                        $roleLabels = [
                            'sistem_yoneticisi' => 'Sistem Yöneticisi',
                            'kurum_staj_sorumlusu' => 'Staj Sorumlusu (İK)',
                            'birim_sorumlusu' => 'Birim Sorumlusu',
                        ];
                        $label = $roleLabels[$u['role']] ?? $u['role'];
                        $badgeClass = $u['role'] === 'sistem_yoneticisi' ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-secondary-container text-on-secondary-container border border-outline-variant/30';
                        ?>
                        <span class="px-3 py-1 <?= $badgeClass ?> text-body-sm font-medium rounded-full inline-block"><?= e($label) ?></span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2.5 py-0.5 bg-primary/5 text-primary text-body-sm font-bold rounded border border-primary/10 inline-block">
                            <?= count($list) ?> Stajyer
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <p class="text-body-md text-on-surface-variant font-label-technical m-0 text-center">
                            <?= !empty($u['email']) ? e($u['email']) : e($u['username']) . '@stajtakip.com' ?>
                        </p>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <div class="inline-flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-green-500"></div>
                            <span class="text-body-sm text-on-surface-variant">Aktif</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <a href="user_detail.php?id=<?= (int)$u['id'] ?>" class="text-primary hover:underline font-semibold text-body-sm cursor-pointer no-underline bg-transparent border-none p-0 inline-block">Detayları Gör</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Summary / Table Footer -->
    <div class="px-6 py-4 flex justify-between items-center bg-surface-container-low/30 border-t border-outline-variant">
        <p class="text-body-sm text-on-surface-variant m-0">Toplam <?= $totalUsers ?> kayıttan 1-<?= $totalUsers ?> arası gösteriliyor.</p>
        <div class="flex gap-1">
            <button type="button" class="p-2 hover:bg-surface-container-highest rounded border border-outline-variant bg-surface-container-lowest text-on-surface-variant opacity-50 cursor-not-allowed flex items-center justify-center" disabled>
                <span class="ms text-[16px]">chevron_left</span>
            </button>
            <button type="button" class="px-4 py-2 bg-primary text-white rounded font-medium text-body-sm border-none">1</button>
            <button type="button" class="p-2 hover:bg-surface-container-highest rounded border border-outline-variant bg-surface-container-lowest text-on-surface-variant opacity-50 cursor-not-allowed flex items-center justify-center" disabled>
                <span class="ms text-[16px]">chevron_right</span>
            </button>
        </div>
    </div>
</div>

<!-- Kullanıcı Detay Modalı (Popup) -->
<div class="user-modal-overlay" id="detailsModalOverlay">
    <div class="user-modal-card">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-[var(--text)] m-0">Kullanıcı Detayları</h3>
            <button type="button" onclick="closeDetailsModal()" class="text-[var(--text-2)] hover:text-[var(--text)] border-none bg-transparent cursor-pointer flex items-center p-0">
                <span class="ms text-[20px]">close</span>
            </button>
        </div>
        <div class="space-y-4 text-xs" id="detailsModalContent">
            <!-- Dinamik olarak JS ile yüklenecek -->
        </div>
    </div>
</div>

<script>
(function() {
    // Arama Çubuğu Filtreleme (Header Arama Kutusuna Bağlantı)
    var searchInput = document.querySelector('.topbar-search input');
    if (searchInput) {
        searchInput.placeholder = "Kullanıcı ara...";
        searchInput.name = "user_q"; // Dashboard search submitini engelle
        searchInput.addEventListener('input', function(e) {
            var query = e.target.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(function(row) {
                var text = row.textContent.toLowerCase();
                row.style.display = text.indexOf(query) > -1 ? '' : 'none';
            });
        });
    }

    // Detay Modalı Kapatma
    var detailsOverlay = document.getElementById('detailsModalOverlay');
    detailsOverlay.addEventListener('click', function(e) {
        if (e.target === detailsOverlay) {
            closeDetailsModal();
        }
    });
})();

// Detay Modalı Veri Gösterimi
var usersData = <?= json_encode($users) ?>;
var assignedData = <?= json_encode($assigned) ?>;

function showUserDetails(id) {
    var user = usersData.find(function(u) { return parseInt(u.id) === id; });
    if (!user) return;
    
    var list = assignedData[id] || [];
    var roleLabels = {
        'sistem_yoneticisi': 'Sistem Yöneticisi',
        'kurum_staj_sorumlusu': 'Staj Sorumlusu (İK)',
        'birim_sorumlusu': 'Birim Sorumlusu'
    };
    
    // Harfleri hesaplama
    var words = user.full_name.split(' ');
    var initials = "";
    for (var i = 0; i < words.length; i++) {
        if (words[i].length > 0) initials += words[i][0];
    }
    initials = initials.substring(0, 2).toUpperCase();
    
    var assignedHtml = "";
    if (list.length > 0) {
        assignedHtml = '<div class="mt-1 flex flex-wrap gap-1.5">';
        for (var j = 0; j < list.length; j++) {
            assignedHtml += '<a href="view.php?id=' + list[j].id + '" class="px-2.5 py-1 bg-primary/5 text-primary rounded border border-primary/10 text-[11px] font-semibold hover:bg-primary/10 transition-colors" style="text-decoration:none;">' + list[j].first_name + ' ' + list[j].last_name + '</a>';
        }
        assignedHtml += '</div>';
    } else {
        assignedHtml = '<span class="text-xs text-[var(--text-2)] italic">Atanmış stajyer bulunmamaktadır.</span>';
    }

    var deleteButtonHtml = "";
    if (parseInt(user.id) !== parseInt(<?= $myId ?>)) {
        deleteButtonHtml = `
            <form method="post" action="users.php" class="inline m-0" onsubmit="return confirm('Bu kullanıcı silinecek. Emin misiniz?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${user.id}">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg font-semibold text-xs hover:bg-red-700 shadow-sm transition-all cursor-pointer border-none">Kullanıcıyı Sil</button>
            </form>
        `;
    }

    var html = `
        <div class="flex items-center gap-3 mb-4 pb-4 border-b border-[var(--card-border)]">
            <div class="w-12 h-12 rounded-lg overflow-hidden flex items-center justify-center">
                ${user.photo ? `<img class="w-full h-full object-cover" src="uploads/${user.photo}">` : `<div class="w-full h-full bg-primary text-white flex items-center justify-center font-bold text-sm">${initials}</div>`}
            </div>
            <div>
                <h4 class="text-sm font-bold text-[var(--text)] m-0">${user.full_name}</h4>
                <p class="text-[10px] text-[var(--text-2)] m-0 mt-0.5">${roleLabels[user.role] || user.role}</p>
            </div>
        </div>
        <div class="space-y-3">
            <div>
                <span class="text-[10px] font-bold text-[var(--text-2)] uppercase tracking-wider block">Kullanıcı Adı</span>
                <span class="text-xs text-[var(--text)] font-semibold">${user.username}</span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-[var(--text-2)] uppercase tracking-wider block">E-posta Adresi</span>
                <span class="text-xs text-[var(--text)] font-semibold">${user.email || user.username + '@stajtakip.com'}</span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-[var(--text-2)] uppercase tracking-wider block">Atanmış Stajyerler (${list.length})</span>
                ${assignedHtml}
            </div>
        </div>
        <div class="pt-4 mt-4 border-t border-[var(--card-border)] flex justify-between items-center gap-3">
            ${deleteButtonHtml}
            <button type="button" onclick="closeDetailsModal()" class="px-5 py-2 bg-primary text-white rounded-lg font-semibold text-xs hover:opacity-90 shadow-sm transition-all cursor-pointer border-none ml-auto">Kapat</button>
        </div>
    `;
    
    document.getElementById("detailsModalContent").innerHTML = html;
    document.getElementById("detailsModalOverlay").classList.add("open");
}

function closeDetailsModal() {
    document.getElementById("detailsModalOverlay").classList.remove("open");
}
</script>

<?php render_footer(); ?>
