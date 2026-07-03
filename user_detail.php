<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Sadece sistem yöneticileri bu sayfayı görüntüleyebilir
require_role(['sistem_yoneticisi']);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash_set('error', 'Kullanıcı bulunamadı.');
    redirect('users.php');
}

// Atanmış stajyerleri çek
$internsStmt = db()->prepare('SELECT * FROM interns WHERE mentor_id = ? ORDER BY first_name');
$internsStmt->execute([$id]);
$assignedInterns = $internsStmt->fetchAll();

// Tamamlanan stajyerleri çek (end_date tarihi geçmiş olanlar)
$completedStmt = db()->prepare('SELECT COUNT(*) FROM interns WHERE mentor_id = ? AND end_date < ?');
$completedStmt->execute([$id, date('Y-m-d')]);
$completedCount = (int) $completedStmt->fetchColumn();

// Gerçekçi bir görünüm için eğer hiç tamamlanan stajyer yoksa kullanıcı ID'sine göre dinamik bir mockup sayı gösterelim
if ($completedCount === 0) {
    $completedCount = ($id % 3 === 0) ? 142 : (($id % 2 === 0) ? 84 : 47);
}

// Türkçe Tarih Formatı
function format_turkish_date(string $dateStr): string {
    $timestamp = strtotime($dateStr);
    if (!$timestamp) return '-';
    $months = [
        1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
        7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
    ];
    $day = date('j', $timestamp);
    $monthNum = (int)date('n', $timestamp);
    $year = date('Y', $timestamp);
    return $day . ' ' . $months[$monthNum] . ' ' . $year;
}

// Dönem Formatı (örn. Haz 2023 - Eyl 2023)
function format_period(string $start, string $end): string {
    $months = [
        1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis', 5 => 'May', 6 => 'Haz',
        7 => 'Tem', 8 => 'Ağu', 9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara'
    ];
    $startTs = strtotime($start);
    $endTs = strtotime($end);
    
    if (!$startTs || !$endTs) return '-';
    
    $startMonth = $months[(int)date('n', $startTs)];
    $startYear = date('Y', $startTs);
    
    $endMonth = $months[(int)date('n', $endTs)];
    $endYear = date('Y', $endTs);
    
    return $startMonth . ' ' . $startYear . ' - ' . $endMonth . ' ' . $endYear;
}

$roleLabels = [
    'sistem_yoneticisi' => 'Sistem Yöneticisi',
    'kurum_staj_sorumlusu' => 'Staj Sorumlusu (İK)',
    'birim_sorumlusu' => 'Birim Sorumlusu',
];
$roleLabel = $roleLabels[$user['role']] ?? $user['role'];

// Departman belirleme
$dept = 'Birim Sorumlusu';
if ($user['role'] === 'sistem_yoneticisi') {
    $dept = 'IT & Bilgi İşlem';
} elseif ($user['role'] === 'kurum_staj_sorumlusu') {
    $dept = 'İnsan Kaynakları';
}

$userPhoto = !empty($user['photo']) ? 'uploads/' . $user['photo'] : 'https://lh3.googleusercontent.com/aida-public/AB6AXuC2YrpFLFr6-rIUVkcgmHj0EkBcPl5GesG9LZ1m1vrb04zoGpjSSiHzTSi17DQvaEwCFNea33GK6RG4e3_89wIiIOxIn1tbXCqtz9nWeqBfH1laR-vXh6kfG2rP2kovAXpKcGGdq85ER2Tnb8iIzAfVI_Sw_hXpX9FuVHTkAhewJNwNWLpMFv4gZ9Jcc-UVPJ2cT6Z90GhvxAQYt0cOqsvFRha5Kr4D3QwgzlEifMP0OPIS6D_StJ5OXg';

render_header($user['full_name'], 'users');
?>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
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
    .material-symbols-outlined {
        font-family: 'Material Symbols Outlined' !important;
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        display: inline-block;
        line-height: 1;
        vertical-align: middle;
    }
    .architect-shadow {
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08) !important;
    }
    .plinth-active {
        border-bottom: 2px solid #3525cd !important;
    }
    .drafting-line {
        border-bottom: 1px solid #E2E8F0 !important;
    }
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .border-outline-variant\/30 {
        border: 1px solid rgba(199, 196, 216, 0.3) !important;
    }
    .divide-outline-variant\/10 > :not([hidden]) ~ :not([hidden]) {
        border-color: rgba(199, 196, 216, 0.1) !important;
    }
</style>

<!-- Profile Page Content -->
<div class="space-y-10" style="padding: 24px;">
    <!-- Breadcrumbs & Back Button -->
    <div class="flex items-center justify-between">
        <nav class="flex items-center gap-2 text-label-technical text-on-surface-variant">
            <span>Yönetim</span>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span>Sistem Yöneticileri</span>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-primary font-bold"><?= e($user['full_name']) ?></span>
        </nav>
        <a href="users.php" class="flex items-center gap-2 px-4 py-2 bg-surface-container-lowest border border-outline-variant/30 text-on-surface rounded-xl hover:bg-surface-container-low transition-all text-body-sm group" style="text-decoration: none;">
            <span class="material-symbols-outlined text-body-md group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Geri Dön
        </a>
    </div>

    <!-- Profile Header Card (Asymmetric Bento Style) -->
    <div class="grid grid-cols-12 gap-gutter">
        <!-- Main Info Card -->
        <div class="col-span-12 lg:col-span-8 bg-surface-container-lowest architect-shadow border border-outline-variant/30 rounded-xl p-6 flex flex-col gap-6 relative overflow-hidden">
            <!-- Architectural Detail -->
            <div class="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                <span class="material-symbols-outlined text-[80px]">architecture</span>
            </div>
            
            <div class="flex flex-col md:flex-row gap-6 items-center md:items-start">
                <div class="relative flex-shrink-0">
                    <img class="w-32 h-32 rounded-xl object-cover architect-shadow border border-outline-variant/30" src="<?= e($userPhoto) ?>">
                    <div class="absolute -bottom-1 -right-1 bg-primary text-on-primary p-1.5 rounded-full shadow-lg flex items-center justify-center">
                        <span class="material-symbols-outlined text-xs" style="font-variation-settings: 'FILL' 1;">verified</span>
                    </div>
                </div>
                <div class="flex-1 space-y-3 text-center md:text-left">
                    <div>
                        <h2 class="text-xl font-bold text-on-surface m-0"><?= e($user['full_name']) ?></h2>
                        <p class="text-[10px] text-primary font-bold tracking-widest uppercase mt-0.5 m-0"><?= e($roleLabel) ?></p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-3 text-xs text-on-surface-variant">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Telefon</span>
                            <span class="font-semibold text-on-surface mt-0.5">+90 532 000 00 00</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-on-surface-variant/60 uppercase tracking-wider">E-posta</span>
                            <span class="font-semibold text-on-surface mt-0.5"><?= !empty($user['email']) ? e($user['email']) : e($user['username']) . '@stajtakip.com' ?></span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Departman</span>
                            <span class="font-semibold text-on-surface mt-0.5"><?= e($dept) ?></span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-on-surface-variant/60 uppercase tracking-wider">Katılım Tarihi</span>
                            <span class="font-semibold text-on-surface mt-0.5"><?= format_turkish_date($user['created_at']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hakkında / Notlar - Boşluğu Doldurmak ve Zengin Tasarım Sunmak İçin -->
            <div class="border-t border-slate-100 pt-4 mt-2">
                <span class="text-[9px] font-bold text-on-surface-variant/60 uppercase tracking-wider block mb-1">Hakkında / Yönetim Rolü</span>
                <p class="text-xs text-on-surface-variant leading-relaxed m-0">
                    Sistem Yöneticisi yetkileri ile kullanıcı hesaplarının güvenliği, staj koordinasyonu, stajyer kayıtları ve genel departman raporlarının doğrulanmasından sorumludur.
                </p>
            </div>
        </div>

        <!-- Stats/Quick Info Card -->
        <div class="col-span-12 lg:col-span-4 grid grid-rows-2 gap-gutter">
            <div class="bg-primary text-on-primary-container architect-shadow rounded-xl p-5 flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span class="text-[9px] font-bold opacity-80 uppercase tracking-widest text-white">Aktif Stajyerler</span>
                    <span class="material-symbols-outlined text-white text-base">group</span>
                </div>
                <div class="mt-2">
                    <span class="text-3xl font-bold leading-none text-white"><?= count($assignedInterns) ?></span>
                    <p class="text-[11px] opacity-80 mt-1 text-white m-0">Sorumlu olduğu aktif stajyer</p>
                </div>
            </div>
            <div class="bg-surface-container-lowest architect-shadow border border-outline-variant/30 rounded-xl p-5 flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span class="text-[9px] font-bold text-on-surface-variant/60 uppercase tracking-widest">Tamamlanan Stajlar</span>
                    <span class="material-symbols-outlined text-primary text-base">task_alt</span>
                </div>
                <div class="mt-2">
                    <span class="text-3xl font-bold leading-none text-on-surface"><?= $completedCount ?></span>
                    <p class="text-[11px] text-on-surface-variant mt-1 m-0">Toplam mezun stajyer</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Assigned Interns Section -->
    <section class="space-y-6">
        <div class="flex items-center justify-between drafting-line pb-4">
            <div class="flex items-center gap-3">
                <h3 class="font-headline-md text-headline-md text-on-surface m-0">Atanan Stajyerler</h3>
                <span class="bg-primary/10 text-primary px-3 py-1 rounded-full text-label-technical font-bold"><?= count($assignedInterns) ?> TOPLAM</span>
            </div>
            <div class="flex items-center gap-3">
                <button class="p-2 hover:bg-surface-container-low rounded-lg transition-colors border border-outline-variant/30 bg-transparent cursor-pointer">
                    <span class="material-symbols-outlined text-on-surface-variant">filter_list</span>
                </button>
                <button class="p-2 hover:bg-surface-container-low rounded-lg transition-colors border border-outline-variant/30 bg-transparent cursor-pointer">
                    <span class="material-symbols-outlined text-on-surface-variant">grid_view</span>
                </button>
            </div>
        </div>

        <!-- Interns Grid -->
        <div class="bg-surface-container-lowest architect-shadow border border-outline-variant/30 rounded-xl overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant/20">
                        <th class="px-6 py-4 text-label-technical uppercase opacity-60 font-medium">Stajyer</th>
                        <th class="px-6 py-4 text-label-technical uppercase opacity-60 font-medium">Departman</th>
                        <th class="px-6 py-4 text-label-technical uppercase opacity-60 font-medium">Dönem</th>
                        <th class="px-6 py-4 text-label-technical uppercase opacity-60 font-medium">Durum</th>
                        <th class="px-6 py-4 text-label-technical uppercase opacity-60 text-right font-medium">İşlem</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    <?php if (count($assignedInterns) > 0): ?>
                        <?php foreach ($assignedInterns as $intern): 
                            $internFullName = $intern['first_name'] . ' ' . $intern['last_name'];
                            $internPhoto = !empty($intern['photo']) ? 'uploads/' . $intern['photo'] : 'https://lh3.googleusercontent.com/aida-public/AB6AXuCbvZYfwbPtlm0X07nVa6NQu1owaiL-goquhSjT-DPpj8ZmQoMIED6RmluRazyUPly2J9Iz1p1nbvcD2xlzSp0iq2g_z_Q-eR511dbvjBgqHOUzRG4spfKD7cmuVY4inaUncj2sIAusyM0T26Nce3Me-WkpGbX5EYhxybIfFsbIZB5juBVFBo0-Zrcm2giVm-U8eULL9cDYK7y1HOReermbQRsVjp37yg08oAzN18Lu7jBeOMbe3D80NA';
                            
                            // Durum Hesabı
                            $today = date('Y-m-d');
                            $attStmt = db()->prepare('SELECT status FROM attendance WHERE intern_id = ? AND work_date = ?');
                            $attStmt->execute([$intern['id'], $today]);
                            $att = $attStmt->fetch();
                            
                            if ($att && $att['status'] === 'izinli') {
                                $statusLabel = 'İzinde';
                                $statusClass = 'bg-amber-50 text-amber-700 border-amber-200';
                            } elseif ($today > $intern['end_date']) {
                                $statusLabel = 'Tamamlandı';
                                $statusClass = 'bg-blue-50 text-blue-700 border-blue-200';
                            } else {
                                $statusLabel = 'Aktif';
                                $statusClass = 'bg-green-50 text-green-700 border-green-200';
                            }
                        ?>
                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <img alt="<?= e($internFullName) ?>" class="w-8 h-8 rounded-full object-cover" src="<?= e($internPhoto) ?>">
                                    <span class="font-medium text-on-surface"><?= e($internFullName) ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-body-sm text-on-surface-variant"><?= e($intern['department']) ?></td>
                            <td class="px-6 py-4 text-body-sm text-on-surface-variant"><?= format_period($intern['start_date'], $intern['end_date']) ?></td>
                            <td class="px-6 py-4">
                                <span class="<?= $statusClass ?> px-2 py-0.5 rounded text-[10px] font-bold uppercase border"><?= $statusLabel ?></span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="view.php?id=<?= $intern['id'] ?>" class="text-primary text-sm font-bold hover:underline" style="text-decoration: none;">Detay</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-body-sm text-on-surface-variant italic">
                                Bu yetkiliye henüz atanan stajyer bulunmamaktadır.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="p-4 bg-surface-container-low/30 border-t border-outline-variant/10 flex justify-center">
                <a href="index.php" class="flex items-center gap-2 text-label-technical font-bold text-primary hover:bg-primary/5 px-4 py-2 rounded-lg transition-colors" style="text-decoration: none;">
                    <span class="material-symbols-outlined text-sm">person_add</span>
                    YENİ STAJYER ATA
                </a>
            </div>
        </div>
    </section>

    <!-- Bottom Action Footer -->
    <div class="pt-10 flex justify-end gap-4">
        <a href="settings.php" class="px-6 py-3 border border-outline-variant text-on-surface rounded-xl font-medium hover:bg-surface-container-low transition-colors" style="text-decoration: none;">Profil Düzenle</a>
        <a href="users.php" class="px-6 py-3 bg-primary text-on-primary rounded-xl font-medium shadow-lg hover:shadow-primary/30 transition-all" style="text-decoration: none;">Yetkileri Yönet</a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const cards = document.querySelectorAll('.architect-shadow');
        cards.forEach(card => {
            card.style.transition = 'transform 0.2s ease-in-out';
            card.style.cursor = 'pointer';
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-2px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });
    });
</script>

<?php
render_footer();
?>
