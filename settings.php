<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$myId = (int) $_SESSION['user_id'];

// Check and add columns to users table if they are missing
$columns = [
    'phone' => 'VARCHAR(50) NULL',
    'address' => 'TEXT NULL',
    'bio' => 'TEXT NULL',
    'birth_date' => 'VARCHAR(20) NULL',
    'title' => 'VARCHAR(100) NULL',
    'department' => 'VARCHAR(150) NULL'
];
foreach ($columns as $col => $definition) {
    try {
        db()->query("SELECT $col FROM users LIMIT 1");
    } catch (PDOException $e) {
        try {
            db()->exec("ALTER TABLE users ADD COLUMN $col $definition");
        } catch (PDOException $ex) {
            // Ignore error
        }
    }
}

// Mevcut kullanıcı bilgilerini çek
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$myId]);
$me = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $fullName   = trim((string) ($_POST['full_name'] ?? ''));
        $email      = trim((string) ($_POST['email'] ?? ''));
        $phone      = trim((string) ($_POST['phone'] ?? ''));
        $address    = trim((string) ($_POST['address'] ?? ''));
        $bio        = trim((string) ($_POST['bio'] ?? ''));
        $birthDate  = trim((string) ($_POST['birth_date'] ?? ''));
        $title      = trim((string) ($_POST['title'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));

        // Fotoğraf yükleme işlemi
        $newPhoto = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            if ($file['size'] <= UPLOAD_MAX_SIZE) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $newPhoto = bin2hex(random_bytes(16)) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $newPhoto)) {
                        // Eski fotoğrafı sil
                        if (!empty($me['photo'])) {
                            @unlink(UPLOAD_DIR . '/' . $me['photo']);
                        }
                    } else {
                        $newPhoto = null;
                    }
                }
            }
        }

        if ($fullName === '') {
            flash_set('error', 'Tam Ad alanı boş bırakılamaz.');
        } else {
            if ($newPhoto !== null) {
                db()->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, bio = ?, birth_date = ?, title = ?, department = ?, photo = ? WHERE id = ?')
                    ->execute([$fullName, $email, $phone, $address, $bio, $birthDate, $title, $department, $newPhoto, $myId]);
            } else {
                db()->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, bio = ?, birth_date = ?, title = ?, department = ? WHERE id = ?')
                    ->execute([$fullName, $email, $phone, $address, $bio, $birthDate, $title, $department, $myId]);
            }
            $_SESSION['user_name'] = $fullName;
            log_action('profil_guncelle', 'Ad: ' . $fullName . ', E-posta: ' . $email);
            flash_set('success', 'Profil bilgileriniz güncellendi.');
        }
        redirect('settings.php');
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!$me || !password_verify($current, $me['password_hash'])) {
            flash_set('error', 'Mevcut şifreniz hatalı.');
        } elseif (mb_strlen($new) < 8) {
            flash_set('error', 'Yeni şifre en az 8 karakter olmalıdır.');
        } elseif ($new !== $confirm) {
            flash_set('error', 'Yeni şifre doğrulaması uyuşmuyor.');
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $myId]);
            log_action('sifre_degistir');
            flash_set('success', 'Şifreniz güncellendi.');
        }
        redirect('settings.php');
    }
}

// Güncel bilgileri tekrar yükle
$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$myId]);
$me = $stmt->fetch();

$initials = mb_strtoupper(mb_substr($me['full_name'] ?? '', 0, 1, 'UTF-8'), 'UTF-8');
$userPhotoUrl = !empty($me['photo']) ? 'uploads/' . $me['photo'] : 'https://lh3.googleusercontent.com/aida-public/AB6AXuDCegj1Wl6UmMlarZfs9im9qJqXBcU1Bglv1E_UFc9HU6R6ttyeAfZgDyfBOe_hYwVqZO8qg3QcmGs9dhCW41_Cl8GjwmBd2N32Hg4bFOOWoDCRxWCKgmR_nkHnDjJfqSlVC9xqWU_cY9HMBkwx4i35hPQ76SrTmfsVEo4edT4ZU9R251zxA2wJi0avq7nAaPzNfTWnWOvHEhokOcSoOyDT-bTQRbLjdtZjgpq7zoECE43J-IS1Zjx9bw';

$roleLabels = [
    'sistem_yoneticisi' => 'Sistem Yöneticisi',
    'kurum_staj_sorumlusu' => 'Staj Sorumlusu',
    'birim_sorumlusu' => 'Birim Sorumlusu',
];
$roleLabel = $roleLabels[$me['role']] ?? 'Birim Sorumlusu';

// Hazır temalar ve gelecekte eklenecek temalar için dinamik array
$availableThemes = [
    'dark' => [
        'name' => 'Koyu Tema',
        'desc' => 'Lacivert komuta merkezi görünümü',
        'preview_bg' => 'bg-deep-navy',
        'preview_elements' => '<div class="absolute inset-0 p-3 flex gap-2"><div class="w-12 h-full bg-white/5 rounded-sm"></div><div class="flex-1 space-y-2"><div class="w-1/2 h-2 bg-primary/40 rounded-full"></div><div class="w-3/4 h-2 bg-white/10 rounded-full"></div></div></div>',
        'card_bg' => 'bg-deep-navy',
        'text_color' => 'text-white',
        'desc_color' => 'text-white/50'
    ],
    'light' => [
        'name' => 'Açık Tema',
        'desc' => 'Aydınlık stüdyo görünümü',
        'preview_bg' => 'bg-surface-bright border-b border-outline-variant/30',
        'preview_elements' => '<div class="absolute inset-0 p-3 flex gap-2"><div class="w-12 h-full bg-primary/5 rounded-sm"></div><div class="flex-1 space-y-2"><div class="w-1/2 h-2 bg-primary/20 rounded-full"></div><div class="w-3/4 h-2 bg-primary/5 rounded-full"></div></div></div>',
        'card_bg' => 'bg-white',
        'text_color' => 'text-on-surface',
        'desc_color' => 'text-on-surface-variant'
    ],
    'ocean' => [
        'name' => 'Deep Ocean',
        'desc' => 'Okyanus derinliği ve koyu mavi',
        'preview_bg' => 'bg-[#1E1B4B]',
        'preview_elements' => '<div class="absolute inset-0 p-3 flex gap-2"><div class="w-12 h-full bg-white/5 rounded-sm"></div><div class="flex-1 space-y-2"><div class="w-1/2 h-2 bg-[#818cf8]/40 rounded-full"></div><div class="w-3/4 h-2 bg-white/10 rounded-full"></div></div></div>',
        'card_bg' => 'bg-[#1E1B4B]',
        'text_color' => 'text-white',
        'desc_color' => 'text-white/50'
    ],
    'rose' => [
        'name' => 'Rose Petal',
        'desc' => 'Gül yaprağı açık pembe görünüm',
        'preview_bg' => 'bg-[#DB7093]',
        'preview_elements' => '<div class="absolute inset-0 p-3 flex gap-2"><div class="w-12 h-full bg-[#4a2c3a]/5 rounded-sm"></div><div class="flex-1 space-y-2"><div class="w-1/2 h-2 bg-[#DB7093]/40 rounded-full"></div><div class="w-3/4 h-2 bg-[#4a2c3a]/10 rounded-full"></div></div></div>',
        'card_bg' => 'bg-[#fff0f5]',
        'text_color' => 'text-neutral-800',
        'desc_color' => 'text-neutral-500'
    ],
];

render_header('Ayarlar', 'settings');
?>
<!-- Tailwind CSS safe integration with preflight disabled to prevent sidebar override -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
    tailwind.config = {
        corePlugins: {
            preflight: false,
        },
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "surface-container": "#eceef0",
                    "primary-container": "#4f46e5",
                    "on-tertiary-fixed-variant": "#38485d",
                    "inverse-primary": "#c3c0ff",
                    "background": "#f7f9fb",
                    "surface-tint": "#4d44e3",
                    "secondary-fixed": "#dae2fd",
                    "outline-variant": "#c7c4d8",
                    "surface-container-low": "#f2f4f6",
                    "surface-variant": "#e0e3e5",
                    "on-surface": "#191c1e",
                    "on-surface-variant": "#464555",
                    "tertiary-fixed": "#d3e4fe",
                    "on-primary-container": "#dad7ff",
                    "tertiary": "#3a495f",
                    "surface-container-lowest": "#ffffff",
                    "on-secondary-fixed-variant": "#3f465c",
                    "on-secondary-fixed": "#131b2e",
                    "on-tertiary-fixed": "#0b1c30",
                    "on-secondary-container": "#5c647a",
                    "outline": "#777587",
                    "surface-container-highest": "#e0e3e5",
                    "surface-bright": "#f7f9fb",
                    "on-error-container": "#93000a",
                    "surface": "#f7f9fb",
                    "primary-fixed-dim": "#c3c0ff",
                    "primary-fixed": "#e2dfff",
                    "surface-dim": "#d8dadc",
                    "secondary": "#565e74",
                    "secondary-fixed-dim": "#bec6e0",
                    "error-container": "#ffdad6",
                    "on-tertiary": "#ffffff",
                    "on-primary-fixed-variant": "#3323cc",
                    "inverse-surface": "#2d3133",
                    "tertiary-fixed-dim": "#b7c8e1",
                    "tertiary-container": "#516177",
                    "inverse-on-surface": "#eff1f3",
                    "on-secondary": "#ffffff",
                    "primary": "var(--primary)",
                    "on-tertiary-container": "#ccdcf7",
                    "on-primary": "#ffffff",
                    "on-error": "#ffffff",
                    "surface-container-high": "#e6e8ea",
                    "secondary-container": "#dae2fd",
                    "on-background": "#191c1e",
                    "error": "#ba1a1a",
                    "on-primary-fixed": "#0f0069",
                    "deep-navy": "#0b0e14",
                    "electric-blue": "#6366f1"
                },
                "fontFamily": {
                    "headline-lg-mobile": ["Hanken Grotesk"],
                    "body-sm": ["Inter"],
                    "headline-md": ["Hanken Grotesk"],
                    "label-technical": ["JetBrains Mono"],
                    "headline-lg": ["Hanken Grotesk"],
                    "body-lg": ["Inter"],
                    "headline-xl": ["Hanken Grotesk"],
                    "body-md": ["Inter"]
                },
                "fontSize": {
                    "headline-lg-mobile": ["28px", {"lineHeight": "36px", "fontWeight": "600"}],
                    "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                    "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
                    "label-technical": ["12px", {"lineHeight": "16px", "letterSpacing": "0.05em", "fontWeight": "500"}],
                    "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                    "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                    "headline-xl": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                    "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}]
                }
            },
        }
    }
</script>
<style>
    /* Bütün sayfa elemanlarını projenin kendi yazı tiplerine zorla */
    body, h1, h2, h3, h4, h5, h6, p, label, input, button, span, div, strong, b, a {
        font-family: var(--font-body) !important;
    }
    h1, h2, h3, h4, h5, h6 {
        font-family: var(--font-head) !important;
    }
    .material-symbols-outlined, .ms {
        font-family: 'Material Symbols Outlined' !important;
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }

    /* Universal settings card compact overrides (slightly relaxed for natural breathing room) */
    .card {
        padding: 20px 24px !important; /* relaxed padding */
        margin-bottom: 20px !important;
        display: block !important;
        height: auto !important;
    }
    .card form {
        display: block !important;
        margin: 0 !important;
    }
    
    /* Gaps between card titles and descriptions */
    .card h3, .card h2 {
        margin: 0 !important;
    }
    .card p {
        margin: 4px 0 0 0 !important; /* comfortable spacing */
    }
    .card .mb-6 {
        margin-bottom: 16px !important;
    }
    
    /* Input Girdileri - Universal ufak ve kompakt tasarım */
    .settings-input-group label {
        display: block !important;
        font-family: 'JetBrains Mono', monospace !important;
        font-size: 9.5px !important;
        color: var(--text-2) !important;
        text-transform: uppercase !important;
        letter-spacing: 0.05em !important;
        font-weight: 700 !important;
        margin-bottom: 4px !important;
    }
    .settings-input-group input, .settings-input-group textarea {
        width: 100% !important;
        background-color: var(--input-bg) !important;
        border: 1px solid var(--input-border) !important;
        border-radius: 6px !important;
        padding: 8px 12px !important; /* comfortable padding */
        font-size: 13px !important;
        color: var(--text) !important;
        outline: none !important;
        transition: all 0.2s ease !important;
        margin-bottom: 0px !important;
    }
    .settings-input-group input:focus, .settings-input-group textarea:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 3px rgba(53, 37, 205, 0.12) !important;
    }
    
    /* Profile & Password Form Custom Underline Input Style */
    #profile-form input, #password-form input {
        border: none !important;
        border-bottom: 1.5px solid var(--input-border) !important;
        border-radius: 0px !important;
        background: transparent !important;
        padding: 6px 0px !important; /* comfortable vertical padding */
        box-shadow: none !important;
        transition: border-color 0.2s ease !important;
        width: 100% !important;
    }
    #profile-form input:focus, #password-form input:focus {
        border-color: var(--primary) !important;
        outline: none !important;
    }
    #profileSaveBtn:disabled {
        opacity: 0.45 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
        box-shadow: none !important;
        transform: none !important;
    }
    
    /* Remove vertical spacing variables in tailwind layout rules */
    .space-y-4 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 12px !important;
    }
    .grid {
        gap: 16px !important;
    }
    
    /* Theme Card & Background Card Styling - Clear borders & shadow to stand out against white background */
    .theme-card, .bg-card {
        border: 1px solid rgba(0, 0, 0, 0.15) !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
        background-color: var(--card-bg) !important;
    }
    html[data-theme="light"] .theme-card, 
    html[data-theme="light"] .bg-card {
        border-color: #cbd5e1 !important; /* solid gray border for visibility in light theme */
        background-color: #ffffff !important;
    }
    .theme-card:hover, .bg-card:hover {
        border-color: var(--primary) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
    }
    
    /* Hover scale for background selection items */
    .bg-card {
        border-radius: 8px !important;
    }
</style>

<div class="mb-6">
    <div class="text-[11px] text-[var(--text-2)] mb-2">
        <a href="dashboard.php" class="hover:underline text-[var(--text-2)]">Ana Sayfa</a> / 
        <a href="settings.php" class="hover:underline text-[var(--text-2)]">Profil Ayarları</a> / 
        <span class="text-[var(--text)] font-semibold">Kişisel Bilgiler</span>
    </div>
    <h2 class="text-xl font-extrabold text-[var(--text)] m-0">Modern Kurumsal Profil Ayarları</h2>
    <p class="text-xs text-[var(--text-2)] m-0 mt-0.5">Kişisel bilgilerinizi ve avatarınızı güncelleyin.</p>
</div>

<div class="settings-grid-container grid grid-cols-1 lg:grid-cols-12 gap-6 w-full">
    <!-- Profile Settings Section -->
    <section class="lg:col-span-6 card relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-bl-full -mr-8 -mt-8 transition-transform duration-500 group-hover:scale-110"></div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-base font-bold text-[var(--text)] m-0">Kişisel Bilgiler</h3>
                <p class="text-xs text-[var(--text-2)] m-0 mt-0.5">Kişisel bilgilerinizi ve avatarınızı güncelleyin.</p>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" autocomplete="off" class="m-0" id="profile-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">

            <!-- Profil Fotoğrafı ve Yanındaki Başlık/Rol -->
            <div class="flex items-center gap-6 mb-6">
                <!-- Dairesel Profil Resmi -->
                <div class="relative w-32 h-32 rounded-full overflow-hidden bg-[var(--hover)] border-4 border-primary/10 cursor-pointer flex-none transition-all duration-300 hover:ring-4 hover:ring-primary/20" onclick="document.getElementById('photoInput').click();" title="Fotoğraf Seçmek İçin Tıklayın">
                    <?php if (!empty($me['photo'])): ?>
                        <img class="w-full h-full object-cover" id="avatarPreview" src="uploads/<?= e($me['photo']) ?>">
                    <?php else: ?>
                        <div class="w-full h-full bg-primary text-white flex items-center justify-center font-bold text-4xl" id="avatarPreviewInitials">
                            <?= e($initials) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="this.form.submit()">

                <!-- İsim ve Rol -->
                <div class="flex-1 min-w-0" style="display: flex; flex-direction: column; gap: 4px;">
                    <h4 class="font-black text-[var(--text)] m-0 leading-tight tracking-tight break-words" style="font-size: 26px;"><?= e($me['full_name']) ?></h4>
                    <p class="text-[12px] text-[var(--primary)] font-extrabold uppercase tracking-widest m-0 break-words"><?= e($me['title'] ?: 'Sistem Yöneticisi') ?></p>
                </div>
            </div>
            
            <!-- Alt Satır: Tek Sütun Halinde Sıralı Form Girdileri (Sıkı ve Alt Alta) -->
            <div class="w-full settings-input-group" style="display: flex; flex-direction: column; gap: 12px;">
                <div style="margin: 0;">
                    <label>Tam Ad</label>
                    <input type="text" name="full_name" required value="<?= e($me['full_name']) ?>" class="w-full">
                </div>
                <div style="margin: 0;">
                    <label>Görev / Unvan</label>
                    <input type="text" name="title" value="<?= e($me['title'] ?? '') ?>" placeholder="örn. Birim Sorumlusu" class="w-full">
                </div>
                <div style="margin: 0;">
                    <label>Çalıştığı Birim / Daire Başkanlığı</label>
                    <input type="text" name="department" list="settings_dept_list" value="<?= e($me['department'] ?? '') ?>" placeholder="örn. Bilgi İşlem Daire Başkanlığı" class="w-full">
                    <datalist id="settings_dept_list">
                        <?php 
                        $depts = db()->query('SELECT DISTINCT department_name FROM department_quotas ORDER BY department_name ASC')->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($depts as $d): ?>
                            <option value="<?= e($d) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div style="margin: 0;">
                    <label>E-posta Adresi</label>
                    <input type="email" name="email" value="<?= e($me['email']) ?>" placeholder="örn. isim@mail.com" class="w-full">
                </div>
                <div style="margin: 0;">
                    <label>Telefon Numarası</label>
                    <input type="text" name="phone" value="<?= e($me['phone'] ?? '') ?>" placeholder="örn. 0555 555 5555" class="w-full">
                </div>
                <div style="margin: 0;">
                    <label>Doğum Tarihi</label>
                    <input type="text" name="birth_date" value="<?= e($me['birth_date'] ?? '') ?>" placeholder="GG.AA.YYYY" class="w-full">
                </div>
                <div style="margin: 0;">
                    <label>Adres</label>
                    <input type="text" name="address" value="<?= e($me['address'] ?? '') ?>" placeholder="Ev veya iş adresi" class="w-full">
                </div>
                
                <div class="pt-2 flex justify-end" style="margin: 0;">
                    <button type="submit" id="profileSaveBtn" disabled class="px-6 py-2 bg-primary text-white rounded-lg font-bold text-xs shadow-sm hover:shadow-primary/10 transition-all active:scale-95 border-none cursor-pointer">Değişiklikleri Kaydet</button>
                </div>
            </div>
        </form>
    </section>

    <!-- Theme Preference Section -->
    <section class="lg:col-span-6 card">
        <h3 class="text-base font-bold text-[var(--text)] m-0 mb-1">Tema Tercihi</h3>
        <p class="text-xs text-[var(--text-2)] m-0 mb-6">Uygulama görünümünü çalışma ortamınıza göre özelleştirin.</p>
        
        <!-- Tema listesi: 2x2 Izgara şeklinde 4 kutu olarak hizalanır -->
        <div class="grid grid-cols-2 gap-3">
            <?php foreach ($availableThemes as $themeKey => $tInfo): ?>
                <div class="theme-card relative cursor-pointer group border rounded-xl overflow-hidden transition-all border-[#d1d5db] dark:border-neutral-700 hover:border-primary shadow-md" data-theme-id="<?= e($themeKey) ?>">
                    <div class="h-16 relative overflow-hidden <?= e($tInfo['preview_bg']) ?>">
                        <?= $tInfo['preview_elements'] ?>
                    </div>
                    <div class="p-3 flex items-center justify-between bg-[var(--card-bg)] text-[var(--text)] border-t border-[#d1d5db] dark:border-neutral-700">
                        <div>
                            <p class="font-bold text-xs m-0"><?= e($tInfo['name']) ?></p>
                            <p class="text-[10px] m-0 mt-0.5 text-[var(--text-2)]"><?= e($tInfo['desc']) ?></p>
                        </div>
                        <span class="ms text-primary theme-check-icon hidden" style="font-variation-settings: 'FILL' 1; font-size:16px;">check_circle</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Arka Plan Görseli Seçimi -->
        <div class="mt-8 border-t border-[var(--card-border)] pt-6">
            <h4 class="text-sm font-bold text-[var(--text)] m-0 mb-1">Arka Plan Görseli</h4>
            <p class="text-[11px] text-[var(--text-2)] m-0 mb-6">Seçilen temaya özel bir arka plan resmi belirleyin veya düz renk kullanın.</p>
            
            <div class="grid grid-cols-4 gap-4" id="bg-selector-grid">
                <!-- Düz Renk Kartı -->
                <div class="bg-card cursor-pointer border rounded-lg overflow-hidden transition-all hover:border-primary relative flex flex-col justify-between" data-bg-id="none">
                    <div class="h-12 bg-[var(--bg)] flex items-center justify-center text-[var(--text-2)] text-[10px]">
                        <span class="ms text-lg">format_color_fill</span>
                    </div>
                    <div class="p-2 bg-[var(--card-bg)] border-t border-[var(--card-border)] text-center">
                        <span class="font-bold text-[9px] text-[var(--text)] block truncate">Düz Renk</span>
                    </div>
                    <span class="ms text-primary bg-white rounded-full absolute top-1 right-1 bg-clip-padding hidden bg-check-icon" style="font-variation-settings: 'FILL' 1; font-size:12px; line-height: 1;">check_circle</span>
                </div>
                
                <!-- 1'den 7'ye Arka Plan Kartları -->
                <?php for ($i = 1; $i <= 7; $i++): ?>
                    <div class="bg-card cursor-pointer border rounded-lg overflow-hidden transition-all hover:border-primary relative flex flex-col justify-between" data-bg-id="<?= $i ?>">
                        <div class="h-12 bg-cover bg-center bg-no-repeat bg-preview-box" data-bg-num="<?= $i ?>">
                        </div>
                        <div class="p-2 bg-[var(--card-bg)] border-t border-[var(--card-border)] text-center">
                            <span class="font-bold text-[9px] text-[var(--text)] block">Arka Plan <?= $i ?></span>
                        </div>
                        <span class="ms text-primary bg-white rounded-full absolute top-1 right-1 bg-clip-padding hidden bg-check-icon" style="font-variation-settings: 'FILL' 1; font-size:12px; line-height: 1;">check_circle</span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <!-- Account Security Section -->
    <section class="lg:col-span-6 card">
        <div class="mb-6">
            <h3 class="text-base font-bold text-[var(--text)] m-0">Hesap Güvenliği</h3>
            <p class="text-xs text-[var(--text-2)] m-0 mt-0.5">Şifre ve oturum güvenliğinizi sağlayın.</p>
        </div>

        <form method="post" autocomplete="off" class="m-0 space-y-4 settings-input-group" id="password-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">

            <div>
                <label>Mevcut Şifre</label>
                <div class="relative">
                    <input type="password" name="current_password" id="currentPasswordInput" required placeholder="••••••••">
                    <span class="ms absolute right-3 top-1/2 -translate-y-1/2 text-[var(--text-2)] cursor-pointer" id="toggleCurrentPassword" style="font-size:16px;">visibility</span>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label>Yeni Şifre</label>
                    <input type="password" name="new_password" required minlength="8" placeholder="En az 8 karakter">
                </div>
                <div>
                    <label>Yeni Şifreyi Doğrula</label>
                    <input type="password" name="confirm_password" required minlength="8" placeholder="Tekrar girin">
                </div>
            </div>
            <button type="submit" class="w-full py-2.5 bg-primary text-white rounded-lg font-bold text-xs shadow-sm hover:shadow-primary/10 transition-all flex items-center justify-center gap-2 border-none cursor-pointer" style="margin-top: 24px !important;">
                <span class="ms text-[16px]">key</span>
                Şifreyi Güncelle
            </button>
        </form>
    </section>

    <!-- Notification Settings Section -->
    <section class="lg:col-span-6 card">
        <div class="mb-6">
            <h3 class="text-base font-bold text-[var(--text)] m-0">Bildirim Ayarları</h3>
            <p class="text-xs text-[var(--text-2)] m-0 mt-0.5">Hangi olaylardan haberdar olmak istediğinizi seçin.</p>
        </div>
        
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-3 p-3 rounded-xl border border-outline-variant/30 bg-[var(--card-bg)] hover:bg-[var(--hover)] transition-colors group">
                <div class="flex-1">
                    <p class="font-bold text-xs text-[var(--text)] m-0">E-posta Bildirimleri</p>
                    <p class="text-[11px] text-[var(--text-2)] m-0 mt-0.5">Haftalık özet raporları ve önemli sistem güncellemelerini mail olarak al.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input checked="" class="sr-only peer" type="checkbox" id="notifyEmail">
                    <div class="w-9 h-5 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                </label>
            </div>
            
            <div class="flex items-start justify-between gap-3 p-3 rounded-xl border border-outline-variant/30 bg-[var(--card-bg)] hover:bg-[var(--hover)] transition-colors group">
                <div class="flex-1">
                    <p class="font-bold text-xs text-[var(--text)] m-0">Sistem Bildirimleri</p>
                    <p class="text-[11px] text-[var(--text-2)] m-0 mt-0.5">Panel içi anlık uyarılar ve kullanıcı işlem günlükleri.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input checked="" class="sr-only peer" type="checkbox" id="notifySystem">
                    <div class="w-9 h-5 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                </label>
            </div>
            
            <div class="flex items-start justify-between gap-3 p-3 rounded-xl border border-outline-variant/30 bg-[var(--card-bg)] hover:bg-[var(--hover)] transition-colors group">
                <div class="flex-1">
                    <p class="font-bold text-xs text-[var(--text)] m-0">Yeni Başvuru Uyarıları</p>
                    <p class="text-[11px] text-[var(--text-2)] m-0 mt-0.5">Yeni bir stajyer başvurusu yapıldığında anında bilgilendir.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer mt-1">
                    <input checked="" class="sr-only peer" type="checkbox" id="notifyApp">
                    <div class="w-9 h-5 bg-outline-variant peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                </label>
            </div>
        </div>
    </section>
</div>

<script>
(function () {
    // Tema değiştirme işlemleri
    function syncActiveTheme() {
        var activeTheme = document.documentElement.dataset.theme || 'dark';
        document.querySelectorAll('.theme-card').forEach(function (card) {
            var cardTheme = card.dataset.themeId;
            var checkIcon = card.querySelector('.theme-check-icon');
            if (cardTheme === activeTheme) {
                card.classList.remove('border-[#d1d5db]', 'dark:border-neutral-700');
                card.classList.add('border-primary');
                if (checkIcon) checkIcon.classList.remove('hidden');
            } else {
                card.classList.remove('border-primary');
                card.classList.add('border-[#d1d5db]', 'dark:border-neutral-700');
                if (checkIcon) checkIcon.classList.add('hidden');
            }
        });
    }

    function updateBackgroundPreviews(activeTheme) {
        document.querySelectorAll('.bg-preview-box').forEach(function (box) {
            var bgNum = box.dataset.bgNum;
            box.style.backgroundImage = 'url("assets/img/backgrounds/' + activeTheme + '_' + bgNum + '.png")';
        });
    }

    function syncActiveBackground() {
        var activeBg = localStorage.getItem("theme-bg") || "none";
        document.querySelectorAll('.bg-card').forEach(function (card) {
            var cardBg = card.dataset.bgId;
            var checkIcon = card.querySelector('.bg-check-icon');
            if (cardBg === activeBg) {
                card.classList.remove('border-[#d1d5db]', 'dark:border-neutral-700');
                card.classList.add('border-primary');
                if (checkIcon) checkIcon.classList.remove('hidden');
            } else {
                card.classList.remove('border-primary');
                card.classList.add('border-[#d1d5db]', 'dark:border-neutral-700');
                if (checkIcon) checkIcon.classList.add('hidden');
            }
        });
    }

    document.querySelectorAll('.theme-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var selectedTheme = card.dataset.themeId;
            document.documentElement.dataset.theme = selectedTheme;
            localStorage.setItem('theme', selectedTheme);
            syncActiveTheme();
            updateBackgroundPreviews(selectedTheme);
            
            // Seçili arka plan varsa gövdeyi güncelle
            var activeBg = localStorage.getItem("theme-bg") || "none";
            var existingStyle = document.getElementById('dynamic-bg-style');
            if (existingStyle) {
                existingStyle.remove();
            }
            if (activeBg !== "none") {
                document.documentElement.classList.add("has-custom-bg");
                var overlay = "rgba(9, 19, 36, 0.55)";
                if (selectedTheme === "light") overlay = "rgba(247, 249, 251, 0.45)";
                else if (selectedTheme === "ocean") overlay = "rgba(15, 13, 54, 0.35)";
                else if (selectedTheme === "rose") overlay = "rgba(255, 240, 245, 0.45)";
                var css = 'body { background: linear-gradient(' + overlay + ', ' + overlay + '), url("assets/img/backgrounds/' + selectedTheme + '_' + activeBg + '.png") !important; background-size: cover !important; background-attachment: fixed !important; background-position: center !important; }';
                var style = document.createElement('style');
                style.id = 'dynamic-bg-style';
                style.type = 'text/css';
                style.appendChild(document.createTextNode(css));
                document.head.appendChild(style);
            } else {
                document.documentElement.classList.remove("has-custom-bg");
            }
        });
    });

    document.querySelectorAll('.bg-card').forEach(function (card) {
        card.addEventListener('click', function () {
            var selectedBg = card.dataset.bgId;
            localStorage.setItem('theme-bg', selectedBg);
            syncActiveBackground();
            
            var activeTheme = document.documentElement.dataset.theme || 'dark';
            var existingStyle = document.getElementById('dynamic-bg-style');
            if (existingStyle) {
                existingStyle.remove();
            }
            if (selectedBg !== "none") {
                document.documentElement.classList.add("has-custom-bg");
                var overlay = "rgba(9, 19, 36, 0.55)";
                if (activeTheme === "light") overlay = "rgba(247, 249, 251, 0.45)";
                else if (activeTheme === "ocean") overlay = "rgba(15, 13, 54, 0.35)";
                else if (activeTheme === "rose") overlay = "rgba(255, 240, 245, 0.45)";
                var css = 'body { background: linear-gradient(' + overlay + ', ' + overlay + '), url("assets/img/backgrounds/' + activeTheme + '_' + selectedBg + '.png") !important; background-size: cover !important; background-attachment: fixed !important; background-position: center !important; }';
                var style = document.createElement('style');
                style.id = 'dynamic-bg-style';
                style.type = 'text/css';
                style.appendChild(document.createTextNode(css));
                document.head.appendChild(style);
            } else {
                document.documentElement.classList.remove("has-custom-bg");
            }
        });
    });

    var initialTheme = document.documentElement.dataset.theme || 'dark';
    var initialBg = localStorage.getItem("theme-bg") || "none";
    if (initialBg !== "none") {
        document.documentElement.classList.add("has-custom-bg");
    } else {
        document.documentElement.classList.remove("has-custom-bg");
    }
    syncActiveTheme();
    updateBackgroundPreviews(initialTheme);
    syncActiveBackground();

    // Şifre görünürlük toggle
    var toggleBtn = document.getElementById('toggleCurrentPassword');
    var passwordInput = document.getElementById('currentPasswordInput');
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function () {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = 'visibility';
            }
        });
    }

    // Micro-interactions for inputs
    var textInputs = document.querySelectorAll('input[type="text"], input[type="email"], input[type="password"]');
    textInputs.forEach(function (input) {
        input.addEventListener('focus', function () {
            var wrapper = input.parentElement;
            if (wrapper) wrapper.classList.add('scale-[1.01]');
        });
        input.addEventListener('blur', function () {
            var wrapper = input.parentElement;
            if (wrapper) wrapper.classList.remove('scale-[1.01]');
        });
    });

    // Switches toggle decoration
    var switches = ['notifyEmail', 'notifySystem', 'notifyApp'];
    switches.forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            // Load state
            var saved = localStorage.getItem(id);
            if (saved !== null) {
                el.checked = (saved === 'true');
            }
            
            var updateBorder = function () {
                var parent = el.closest('div.group');
                if (parent) {
                    if (el.checked) {
                        parent.style.borderColor = 'rgba(79, 70, 229, 0.3)';
                    } else {
                        parent.style.borderColor = 'rgba(119, 117, 135, 0.1)';
                    }
                }
            };
            
            updateBorder();
            
            el.addEventListener('change', function () {
                localStorage.setItem(id, el.checked ? 'true' : 'false');
                updateBorder();
            });
        }
    });

    // Profile form dirty check to enable/disable Save button
    var profileForm = document.getElementById('profile-form');
    var profileSaveBtn = document.getElementById('profileSaveBtn');
    if (profileForm && profileSaveBtn) {
        var profileInputs = profileForm.querySelectorAll('input[type="text"], input[type="email"]');
        var originalValues = {};
        profileInputs.forEach(function (input) {
            originalValues[input.name] = input.value;
        });

        var checkChanges = function () {
            var changed = false;
            profileInputs.forEach(function (input) {
                if (input.value !== originalValues[input.name]) {
                    changed = true;
                }
            });
            profileSaveBtn.disabled = !changed;
        };

        profileInputs.forEach(function (input) {
            input.addEventListener('input', checkChanges);
            input.addEventListener('change', checkChanges);
        });
    }
})();
</script>

<?php render_footer(); ?>
