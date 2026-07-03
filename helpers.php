<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Geçersiz istek (CSRF doğrulaması başarısız).');
    }
}

/* ---------- Flash mesajları ---------- */

function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/* ---------- Yetkilendirme ve Roller ---------- */

function is_admin(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'sistem_yoneticisi';
}

function is_hr(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'kurum_staj_sorumlusu';
}

function is_mentor(): bool
{
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'birim_sorumlusu';
}

function require_role(array $roles): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        flash_set('error', 'Bu sayfaya erişim yetkiniz bulunmamaktadır.');
        redirect('index.php');
    }
}

/* ---------- Log sistemi ---------- */

/**
 * İşlem kaydı düşer. Log tablosu yoksa ya da hata olursa
 * uygulamayı ASLA durdurmaz — sessizce devam eder.
 */
function log_action(string $action, string $detail = ''): void
{
    try {
        db()->prepare('INSERT INTO logs (user_id, user_name, action, detail, ip) VALUES (?, ?, ?, ?, ?)')
            ->execute([
                $_SESSION['user_id'] ?? null,
                (string) ($_SESSION['user_name'] ?? 'Bilinmeyen'),
                $action,
                mb_substr($detail, 0, 250),
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ]);
    } catch (Throwable) {
        // loglama hiçbir zaman asıl işlemi bozmasın
    }
}

/** Log işlem türleri: [etiket, rozet-sınıfı] */
const LOG_ACTIONS = [
    'giris'            => ['Giriş Yapıldı', 'aktif'],
    'giris_hatali'     => ['Hatalı Giriş Denemesi', 'devamsiz'],
    'stajyer_ekle'     => ['Stajyer Eklendi', 'info'],
    'stajyer_guncelle' => ['Stajyer Güncellendi', 'info'],
    'stajyer_sil'      => ['Stajyer Silindi', 'baslamadi'],
    'yoklama'          => ['Yoklama Kaydedildi', 'info'],
    'not_ekle'         => ['Staj Notu Eklendi', 'info'],
    'not_sil'          => ['Staj Notu Silindi', 'baslamadi'],
    'belge_yukle'      => ['Belge Yüklendi', 'info'],
    'belge_sil'        => ['Belge Silindi', 'baslamadi'],
    'degerlendirme'    => ['Değerlendirme Kaydedildi', 'info'],
    'kullanici_ekle'   => ['Kullanıcı Eklendi', 'aktif'],
    'kullanici_sil'    => ['Kullanıcı Silindi', 'baslamadi'],
    'sifre_degistir'   => ['Şifre Değiştirildi', 'info'],
    'log_temizle'      => ['Loglar Temizlendi', 'baslamadi'],
];

/** Eğitim seviyeleri */
const LEVELS = [
    'lise'         => 'Lise',
    'onlisans'     => 'Ön Lisans',
    'lisans'       => 'Üniversite (Lisans)',
    'yukseklisans' => 'Yüksek Lisans',
    'mezun'        => 'Üniversite Mezunu',
];

/* ---------- Stajyer durumu ---------- */

/**
 * Tarihlere göre staj durumu.
 * @return array{0:string,1:string} [css-sınıfı, etiket]
 */
function intern_status(array $intern): array
{
    $today = date('Y-m-d');
    if ($today < $intern['start_date']) {
        return ['baslamadi', 'Başlamadı'];
    }
    if ($today > $intern['end_date']) {
        return ['bitti', 'Stajı Bitti'];
    }
    return ['aktif', 'Aktif'];
}

function format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : '-';
}

/**
 * Türkiye'deki resmi tatil kontrolü.
 */
function is_turkish_holiday(string $date): bool
{
    $md = substr($date, 5, 5); // MM-DD
    $fixedHolidays = [
        '01-01', // Yılbaşı
        '04-23', // Ulusal Egemenlik ve Çocuk Bayramı
        '05-01', // Emek ve Dayanışma Günü
        '05-19', // Atatürk'ü Anma, Gençlik ve Spor Bayramı
        '07-15', // Demokrasi ve Milli Birlik Günü
        '08-30', // Zafer Bayramı
        '10-29', // Cumhuriyet Bayramı
    ];
    if (in_array($md, $fixedHolidays, true)) {
        return true;
    }

    // Hareketli dini bayramlar ve arifeleri (2024-2027)
    static $movingHolidays = [
        // 2024
        '2024-04-09', '2024-04-10', '2024-04-11', '2024-04-12', // Ramazan
        '2024-06-15', '2024-06-16', '2024-06-17', '2024-06-18', '2024-06-19', // Kurban
        // 2025
        '2025-03-29', '2025-03-30', '2025-03-31', '2025-04-01', // Ramazan
        '2025-06-05', '2025-06-06', '2025-06-07', '2025-06-08', '2025-06-09', // Kurban
        // 2026
        '2026-03-19', '2026-03-20', '2026-03-21', '2026-03-22', // Ramazan
        '2026-05-26', '2026-05-27', '2026-05-28', '2026-05-29', '2026-05-30', // Kurban
        // 2027
        '2027-03-08', '2027-03-09', '2027-03-10', '2027-03-11', // Ramazan
        '2027-05-15', '2027-05-16', '2027-05-17', '2027-05-18', '2027-05-19', // Kurban
    ];

    return in_array($date, $movingHolidays, true);
}

/**
 * Stajın toplam süresi (iş günü). Hafta sonları ve resmi tatiller hariç.
 */
function intern_days(array $intern): int
{
    try {
        $start = new DateTimeImmutable($intern['start_date']);
        $end   = new DateTimeImmutable($intern['end_date']);
    } catch (Exception) {
        return 0;
    }
    $count = 0;
    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
        $dStr = $d->format('Y-m-d');
        if ((int) $d->format('N') <= 5 && !is_turkish_holiday($dStr)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Staj ilerlemesi (iş günü bazında yüzde 0-100).
 */
function intern_progress(array $intern): int
{
    $today = date('Y-m-d');
    if ($today < $intern['start_date']) {
        return 0;
    }
    if ($today >= $intern['end_date']) {
        return 100;
    }
    $total = intern_days($intern);
    if ($total < 1) {
        return 100;
    }
    $elapsed = intern_past_workdays($intern);
    return (int) round($elapsed * 100 / $total);
}

/**
 * Staj başından bugüne (veya bitişe) kadar geçen iş günü sayısı (resmi tatiller hariç).
 */
function intern_past_workdays(array $intern): int
{
    try {
        $start = new DateTimeImmutable($intern['start_date']);
        $end   = new DateTimeImmutable($intern['end_date']);
        $today = new DateTimeImmutable('today');
    } catch (Exception) {
        return 0;
    }
    $limit = $end < $today ? $end : $today;
    $count = 0;
    for ($d = $start; $d <= $limit; $d = $d->modify('+1 day')) {
        $dStr = $d->format('Y-m-d');
        if ((int) $d->format('N') <= 5 && !is_turkish_holiday($dStr)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Stajın bitmesine kalan iş günü sayısı.
 */
function intern_remaining_workdays(array $intern): int
{
    try {
        $today = new DateTimeImmutable('today');
        $end   = new DateTimeImmutable($intern['end_date']);
        $start = new DateTimeImmutable($intern['start_date']);
    } catch (Exception) {
        return 0;
    }
    if ($today > $end) {
        return 0;
    }
    $cursor = $today < $start ? $start : $today;
    $count = 0;
    for ($d = $cursor; $d <= $end; $d = $d->modify('+1 day')) {
        $dStr = $d->format('Y-m-d');
        if ((int) $d->format('N') <= 5 && !is_turkish_holiday($dStr)) {
            $count++;
        }
    }
    return $count;
}

/* ---------- Yoklama ---------- */

/** İşaretlenebilir istisna durumları (kayıt yoksa gün "Geldi" sayılır) */
const ATTENDANCE_STATUSES = [
    'devamsiz' => 'Devamsız',
    'izinli'   => 'İzinli',
    'raporlu'  => 'Raporlu',
];

/**
 * Bir stajyerin tüm yoklama istisnaları.
 * @return array<string,string> ['2026-07-03' => 'izinli', ...]
 */
function attendance_map(int $internId): array
{
    $stmt = db()->prepare('SELECT work_date, status FROM attendance WHERE intern_id = ?');
    $stmt->execute([$internId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['work_date']] = $row['status'];
    }
    return $map;
}

function attendance_details_map(int $internId): array
{
    $stmt = db()->prepare('SELECT work_date, status, check_in, check_out FROM attendance WHERE intern_id = ?');
    $stmt->execute([$internId]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['work_date']] = $row;
    }
    return $map;
}

/* ---------- Fotoğraf yükleme ---------- */

/**
 * Fotoğrafı doğrulayıp uploads/ altına kaydeder, dosya adını döndürür.
 * Dosya seçilmemişse null döner; hata varsa RuntimeException fırlatır.
 */
function handle_photo_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Fotoğraf yüklenirken bir hata oluştu (kod: ' . $file['error'] . ').');
    }
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        throw new RuntimeException('Fotoğraf en fazla ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . ' MB olabilir.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Sadece JPG, PNG veya WEBP formatında fotoğraf yükleyebilirsiniz.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Fotoğraf kaydedilemedi. uploads/ klasörünün yazma iznini kontrol edin.');
    }
    return $name;
}

function delete_photo(?string $filename): void
{
    if ($filename && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $filename)) {
        $path = UPLOAD_DIR . '/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/* ---------- Belge yükleme ---------- */

/**
 * Belgeyi doğrulayıp uploads/docs/ altına kaydeder.
 * @return array{0:string,1:string,2:int} [kayıtlı ad, orijinal ad, boyut]
 */
function handle_document_upload(string $field): array
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Lütfen bir dosya seçin.');
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Dosya yüklenirken bir hata oluştu (kod: ' . $file['error'] . ').');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Belge en fazla 10 MB olabilir.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Sadece PDF, JPG, PNG, WEBP, Word veya Excel dosyası yükleyebilirsiniz.');
    }

    $dir = UPLOAD_DIR . '/docs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        @file_put_contents($dir . '/index.html', '');
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Belge kaydedilemedi. uploads/ klasörünün yazma iznini kontrol edin.');
    }
    $orig = trim((string) $file['name']);
    if ($orig === '') {
        $orig = 'belge.' . $allowed[$mime];
    }
    return [$name, mb_substr($orig, 0, 190), (int) $file['size']];
}

function delete_document_file(?string $filename): void
{
    if ($filename && preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $filename)) {
        $path = UPLOAD_DIR . '/docs/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/* ---------- Staj haftaları (değerlendirme için) ---------- */

/**
 * Stajın kapsadığı haftalar (pazartesi başlangıçlı).
 * @return array<int,array{start:string,label:string}>
 */
function intern_weeks(array $intern): array
{
    try {
        $start = (new DateTimeImmutable($intern['start_date']))->modify('monday this week');
        $end   = new DateTimeImmutable($intern['end_date']);
    } catch (Exception) {
        return [];
    }
    $months = [1 => 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    $weeks = [];
    $guard = 0;
    for ($w = $start; $w <= $end && $guard++ < 60; $w = $w->modify('+1 week')) {
        $fri = $w->modify('+4 days');
        $weeks[] = [
            'start' => $w->format('Y-m-d'),
            'label' => (int) $w->format('j') . ' ' . $months[(int) $w->format('n')]
                . ' – ' . (int) $fri->format('j') . ' ' . $months[(int) $fri->format('n')]
                . ' ' . $fri->format('Y'),
        ];
    }
    return $weeks;
}

/* ---------- Ortak <head> (fontlar + tema) ---------- */

function render_head(string $title): void
{
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' — Staj Takip</title>';
    echo '<link rel="stylesheet" href="assets/style.css">';
    echo '<script src="assets/icons.js" defer></script>';
    // Tema tercihini CSS boyanmadan önce uygula (parlama olmasın)
    echo '<script>document.documentElement.dataset.theme = localStorage.getItem("theme") || "dark";</script>';
    echo '</head>';
}

/* ---------- Sayfa iskeleti: yan menü + üst bar ---------- */

function render_header(string $title, string $active = ''): void
{
    $user = (string) ($_SESSION['user_name'] ?? '');
    $initials = mb_strtoupper(mb_substr($user, 0, 1, 'UTF-8'), 'UTF-8');
    $role = (string) ($_SESSION['user_role'] ?? 'birim_sorumlusu');

    // Fetch user photo
    $userPhoto = null;
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId !== null) {
        try {
            $stmt = db()->prepare('SELECT photo FROM users WHERE id = ?');
            $stmt->execute([(int) $userId]);
            $userPhoto = $stmt->fetchColumn() ?: null;
        } catch (PDOException) {
            // photo sütunu yoksa geç
        }
    }

    $roleLabels = [
        'sistem_yoneticisi' => 'Sistem Yöneticisi',
        'kurum_staj_sorumlusu' => 'Staj Sorumlusu',
        'birim_sorumlusu' => 'Birim Sorumlusu',
    ];
    $roleLabel = $roleLabels[$role] ?? 'Birim Sorumlusu';

    $nav = [
        'dashboard' => ['dashboard.php', 'dashboard', 'Dashboard'],
        'index'     => ['index.php',     'group',     'Stajyerler'],
    ];

    if ($role === 'sistem_yoneticisi' || $role === 'kurum_staj_sorumlusu') {
        $nav['qr_generator'] = ['qr_generator.php', 'qr_code', 'Yoklama QR'];
        $nav['applications'] = ['applications.php', 'verified_user', 'Başvurular'];
    }

    if ($role === 'sistem_yoneticisi') {
        $nav['quotas'] = ['quotas.php', 'calendar_today', 'Dönem & Kontenjan'];
    }

    $nav['reports'] = ['reports.php', 'assessment', 'Raporlar'];

    if ($role === 'sistem_yoneticisi' || $role === 'kurum_staj_sorumlusu') {
        $nav['logs'] = ['logs.php', 'history_edu', 'Log Sistemi'];
        if ($role === 'sistem_yoneticisi') {
            $nav['users'] = ['users.php', 'manage_accounts', 'Kullanıcılar'];
        }
    }

    $nav['settings'] = ['settings.php', 'settings', 'Ayarlar'];

    render_head($title);
    echo '<body><div class="app">';

    /* Yan menü */
    echo '<aside class="sidebar">';
    echo '<div class="sb-brand"><span class="sb-logo"><span class="ms">school</span></span>';
    echo '<div><a class="sb-brand-name" href="dashboard.php">Staj Takip</a>';
    echo '<span class="sb-brand-sub">Yönetim Paneli</span></div></div>';

    echo '<nav>';
    foreach ($nav as $key => [$href, $icon, $label]) {
        $cls = $key === $active ? ' class="active"' : '';
        echo '<a href="' . $href . '"' . $cls . '><span class="ms">' . $icon . '</span>' . e($label) . '</a>';
    }
    echo '</nav>';
    echo '</aside>';

    /* Ana alan */
    echo '<div class="main">';
    echo '<header class="topbar">';
    echo '<form class="topbar-search" method="get" action="index.php">';
    echo '<span class="ms">search</span>';
    echo '<input type="text" name="q" placeholder="Stajyer, bölüm veya telefon ara…" value="">';
    echo '<input type="hidden" name="durum" value="hepsi">';
    echo '</form>';
    
    // Topbar Profile Dropdown
    echo '<div class="topbar-right">';
    echo '<div class="profile-dropdown">';
    echo '<button type="button" class="profile-btn" id="profileDropdownBtn">';
    if ($userPhoto !== null) {
        echo '<span class="avatar"><img src="uploads/' . e($userPhoto) . '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></span>';
    } else {
        echo '<span class="avatar">' . e($initials) . '</span>';
    }
    echo '<div style="flex: 1;"></div>';
    echo '<span class="profile-name">' . e($user) . '</span>';
    echo '<span class="ms sm">keyboard_arrow_down</span>';
    echo '</button>';
    echo '<div class="dropdown-menu" id="profileDropdown">';
    echo '<div class="dropdown-header"><b>' . e($user) . '</b><span>' . e($roleLabel) . '</span></div>';
    echo '<hr>';
    echo '<a href="settings.php"><span class="ms sm">settings</span> Ayarlar</a>';
    echo '<a href="logout.php" style="color: var(--danger);"><span class="ms sm" style="color: var(--danger);">logout</span> Çıkış Yap</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    echo '</header>';

    echo '<script>
    document.addEventListener("DOMContentLoaded", function () {
        var btn = document.getElementById("profileDropdownBtn");
        var dropdown = document.getElementById("profileDropdown");
        if (btn && dropdown) {
            btn.addEventListener("click", function (ev) {
                ev.stopPropagation();
                dropdown.classList.toggle("open");
            });
            document.addEventListener("click", function () {
                dropdown.classList.remove("open");
            });
        }
    });
    </script>';

    echo '<main class="content">';

    if ($flash = flash_get()) {
        echo '<div class="alert alert-' . e($flash['type']) . '">' . e($flash['msg']) . '</div>';
    }
}

function render_footer(): void
{
    echo '</main><footer class="footer">Staj Takip Sistemi</footer></div></div>';
    echo '</body></html>';
}
