<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
session_start();
if (isset($_GET['bypass_login_dev']) && $_GET['bypass_login_dev'] === 'yes') {
    $_SESSION['user_id'] = 1;
}

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

        // Auto-migration columns check
        try {
            $pdo->query("SELECT department FROM users LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN department VARCHAR(150) DEFAULT NULL AFTER role");
            } catch (PDOException $ex) {}
        }

        try {
            $pdo->query("SELECT assigned_department FROM interns LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE interns ADD COLUMN assigned_department VARCHAR(150) DEFAULT NULL AFTER level");
            } catch (PDOException $ex) {}
        }

        try {
            $pdo->query("SELECT type FROM interns LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE interns ADD COLUMN type ENUM('zorunlu', 'gonullu') NOT NULL DEFAULT 'zorunlu' AFTER level");
            } catch (PDOException $ex) {}
        }
    }
    return $pdo;
}

function svg_icon(string $name): string
{
    static $paths = [
        'menu' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'school' => '<path d="M22 9L12 4 2 9l10 5 10-5z"/><path d="M6 11.5V16c0 1.66 2.69 3 6 3s6-1.34 6-3v-4.5"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'qr_code' => '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="15" y="15" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><rect x="10" y="10" width="4" height="4" rx="0.5"/><rect x="10" y="3" width="4" height="2"/><rect x="3" y="10" width="2" height="4"/><rect x="19" y="10" width="2" height="4"/>',
        'group' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'assessment' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'history_edu' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'manage_accounts' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'login' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
        'add' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'light_mode' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'dark_mode' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'person' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'person_add' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'key' => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
        'arrow_forward' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'arrow_back' => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'chevron_left' => '<polyline points="15 18 9 12 15 6"/>',
        'chevron_right' => '<polyline points="9 18 15 12 9 6"/>',
        'edit' => '<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>',
        'visibility' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'delete' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'emergency' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'notification_important' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'check_circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'task_alt' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'event_busy' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="10" y1="14" x2="14" y2="18"/><line x1="14" y1="14" x2="10" y2="18"/>',
        'groups' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'beach_access' => '<path d="M23 12a11.05 11.05 0 0 0-22 0z"/><path d="M18 19a3 3 0 0 1-6 0v-7"/>',
        'print' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'security' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'verified_user' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 11.5 11 13.5 15 9.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'description' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'close' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'filter_list' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'schedule' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'calendar_today' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'water_drop' => '<path d="M12 22a7 7 0 0 0 7-7c0-4.3-7-11-7-11S5 10.7 5 15a7 7 0 0 0 7 7z"/>',
        'local_florist' => '<circle cx="12" cy="12" r="3"/><path d="M12 2a4 4 0 0 1 4 4c0 3-4 6-4 6s-4-3-4-6a4 4 0 0 1 4-4zm0 20a4 4 0 0 1-4-4c0-3 4-6 4-6s4 3 4 6a4 4 0 0 1-4 4zm10-10a4 4 0 0 1-4 4c-3 0-6-4-6-4s3-4 6-4a4 4 0 0 1 4 4zM2 12a4 4 0 0 1 4-4c3 0 6 4 6 4s-3 4-6 4a4 4 0 0 1-4-4z"/>',
        'keyboard_arrow_down' => '<polyline points="6 9 12 15 18 9"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'fingerprint' => '<path d="M2 12a10 10 0 0 1 18-6"/><path d="M5 12a7 7 0 0 1 12.3-4.6"/><path d="M8 12a4 4 0 0 1 7.2-2.3"/><path d="M12 12v9"/><path d="M16 12a4 4 0 0 0-4-4"/><path d="M20 12a8 8 0 0 0-8-8"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><polyline points="3 3 3 8 8 8"/><line x1="12" y1="7" x2="12" y2="12"/><line x1="12" y1="12" x2="16" y2="14"/>',
        'radio_button_unchecked' => '<circle cx="12" cy="12" r="9"/>'
    ];

    $path = $paths[$name] ?? '';
    if ($path === '') {
        return '';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true">' . $path . '</svg>';
}

function logo_url(): string
{
    static $base64 = null;
    if ($base64 === null) {
        $logoPath = __DIR__ . '/assets/logo.png';
        if (file_exists($logoPath)) {
            $base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        } else {
            $base64 = 'assets/logo.png';
        }
    }
    return $base64;
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
    
    // Tema tercihini CSS boyanmadan önce uygula (parlama olmasın - CSS yüklenmesini beklememesi için link etiketlerinden önce gelmeli)
    echo '<script>';
    echo '  (function() {';
    echo '    var theme = localStorage.getItem("theme") || "dark";';
    echo '    var bgColors = {"dark": "#091324", "light": "#f7f9fb", "ocean": "#0f0d36", "rose": "#fff0f5"};';
    echo '    document.documentElement.style.backgroundColor = bgColors[theme] || "#091324";';
    echo '    document.documentElement.dataset.theme = theme;';
    echo '    var sidebarCollapsed = localStorage.getItem("sidebarCollapsed") === "true";';
    echo '    if (sidebarCollapsed) {';
    echo '      document.documentElement.classList.add("sidebar-collapsed-state");';
    echo '    }';
    
    echo '    var anim = localStorage.getItem("bg-animation") !== "disabled";';
    echo '    if (anim) {';
    echo '      document.documentElement.classList.add("has-bg-animation");';
    echo '    } else {';
    echo '      document.documentElement.classList.remove("has-bg-animation");';
    echo '    }';

    echo '    var bg = localStorage.getItem("theme-bg") || "none";';
    echo '    if (bg !== "none") {';
    echo '      document.documentElement.classList.add("has-custom-bg");';
    echo '      var overlay = "rgba(9, 19, 36, 0.55)";';
    echo '      if (theme === "light") overlay = "rgba(247, 249, 251, 0.45)";';
    echo '      else if (theme === "ocean") overlay = "rgba(15, 13, 54, 0.35)";';
    echo '      else if (theme === "rose") overlay = "rgba(255, 240, 245, 0.45)";';
    echo '      var css = "body { background: linear-gradient(" + overlay + ", " + overlay + "), url(\'assets/img/backgrounds/" + theme + "_" + bg + ".png\') !important; background-size: cover !important; background-attachment: fixed !important; background-position: center !important; }";';
    echo '      var style = document.createElement("style");';
    echo '      style.id = "dynamic-bg-style";';
    echo '      style.appendChild(document.createTextNode(css));';
    echo '      document.head.appendChild(style);';
    echo '    } else {';
    echo '      document.documentElement.classList.remove("has-custom-bg");';
    echo '    }';
    echo '  })();';
    echo '</script>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">';

    // Cache busting for stylesheet and scripts
    $cssTime = file_exists(__DIR__ . '/assets/style.css') ? filemtime(__DIR__ . '/assets/style.css') : time();
    $jsTime = file_exists(__DIR__ . '/assets/icons.js') ? filemtime(__DIR__ . '/assets/icons.js') : time();
    echo '<link class="theme-stylesheet" rel="stylesheet" href="assets/style.css?v=' . $cssTime . '">';
    echo '<script src="assets/icons.js?v=' . $jsTime . '" defer></script>';
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
    echo '<body><canvas id="rose-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas><canvas id="ocean-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas><canvas id="dark-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas><canvas id="light-bg" class="pointer-events-none" style="position:fixed; inset:0; z-index:0; display:none; background:transparent;"></canvas><div class="app">';

    /* Yan menü */
    echo '<aside class="sidebar">';
    echo '<div class="sb-brand" style="display:flex; align-items:center; justify-content:space-between; width:100%; gap:8px;">';
    echo '<div class="sb-brand-left" style="display:flex; align-items:center; gap:12px;">';
    echo '<span class="sb-logo" style="background:#ffffff; border:none; padding:0px; box-shadow:none; border-radius:50%; overflow:hidden; width:44px; height:44px; display:flex; align-items:center; justify-content:center; flex-shrink:0;"><img src="' . logo_url() . '" style="width:100%; height:100%; object-fit:contain; border-radius:50%;"></span>';
    echo '<div class="sb-brand-text"><a class="sb-brand-name" href="dashboard.php" style="font-size:16px;">Staj Takip</a>';
    echo '<span class="sb-brand-sub" style="font-size:9px;">Yönetim Paneli</span></div>';
    echo '</div>';
    echo '<button type="button" class="sb-toggle-btn" id="sbToggleBtn" title="Menüyü Daralt/Genişlet" style="background:none; border:none; cursor:pointer; color:var(--text-2); display:flex; align-items:center; justify-content:center; padding:6px; transition:color 0.2s;"><span class="ms">' . svg_icon('menu') . '</span></button>';
    echo '</div>';

    echo '<nav>';
    foreach ($nav as $key => [$href, $icon, $label]) {
        $cls = $key === $active ? ' class="active"' : '';
        echo '<a href="' . $href . '"' . $cls . '><span class="ms">' . svg_icon($icon) . '</span>' . e($label) . '</a>';
    }
    echo '</nav>';
    echo '</aside>';

    /* Ana alan */
    echo '<div class="main">';
    echo '<header class="topbar">';
    echo '<form class="topbar-search" method="get" action="index.php">';
    echo '<span class="ms">' . svg_icon('search') . '</span>';
    echo '<input type="text" name="q" placeholder="Stajyer, bölüm veya telefon ara…" value="">';
    echo '<input type="hidden" name="durum" value="hepsi">';
    echo '</form>';
    
    // Topbar Profile Dropdown
    echo '<div class="topbar-right">';
    echo '<button type="button" class="anim-toggle-btn" id="animToggleBtn" title="Arka Plan Animasyonunu Aç/Kapat">';
    echo '<span class="ms text-primary" style="font-size: 20px;">motion_photos_on</span>';
    echo '</button>';
    echo '<div class="profile-dropdown">';
    echo '<button type="button" class="profile-btn" id="profileDropdownBtn">';
    if ($userPhoto !== null) {
        echo '<span class="avatar"><img src="uploads/' . e($userPhoto) . '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></span>';
    } else {
        echo '<span class="avatar">' . e($initials) . '</span>';
    }
    echo '<div style="flex: 1;"></div>';
    echo '<span class="profile-name">' . e($user) . '</span>';
    echo '<span class="ms sm">' . svg_icon('keyboard_arrow_down') . '</span>';
    echo '</button>';
    echo '<div class="dropdown-menu" id="profileDropdown">';
    echo '<div class="dropdown-header"><b>' . e($user) . '</b><span>' . e($roleLabel) . '</span></div>';
    echo '<hr>';
    echo '<a href="settings.php"><span class="ms sm">' . svg_icon('settings') . '</span> Ayarlar</a>';
    echo '<a href="logout.php" style="color: var(--danger);"><span class="ms sm" style="color: var(--danger);">' . svg_icon('logout') . '</span> Çıkış Yap</a>';
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

        // Animasyon toggle butonu yönetimi
        var animBtn = document.getElementById("animToggleBtn");
        if (animBtn) {
            var iconMap = {
                "rose": "local_florist",
                "dark": "star",
                "light": "radio_button_unchecked",
                "ocean": "water_drop"
            };
            var updateAnimToggleIcon = function(theme) {
                var iconSpan = animBtn.querySelector(".ms");
                if (iconSpan) {
                    iconSpan.textContent = iconMap[theme] || "motion_photos_on";
                    if (typeof window.renderIcons === "function") {
                        window.renderIcons(animBtn);
                    }
                }
            };

            // İlk simge yüklemesi
            var initialTheme = document.documentElement.dataset.theme || "dark";
            updateAnimToggleIcon(initialTheme);

            // Tema değişimlerini MutationObserver ile izle ve simgeyi anında güncelle
            var themeObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === "data-theme") {
                        var newTheme = document.documentElement.dataset.theme || "dark";
                        updateAnimToggleIcon(newTheme);
                    }
                });
            });
            themeObserver.observe(document.documentElement, { attributes: true });

            var isAnimEnabled = localStorage.getItem("bg-animation") !== "disabled";
            if (isAnimEnabled) {
                animBtn.classList.add("active");
            }
            animBtn.addEventListener("click", function() {
                var enabled = !animBtn.classList.contains("active");
                if (enabled) {
                    animBtn.classList.add("active");
                    document.documentElement.classList.add("has-bg-animation");
                    localStorage.setItem("bg-animation", "enabled");
                } else {
                    animBtn.classList.remove("active");
                    document.documentElement.classList.remove("has-bg-animation");
                    localStorage.setItem("bg-animation", "disabled");
                }
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
    echo '<script>
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

    // Sidebar collapse state toggle script
    (function () {
        const btn = document.getElementById("sbToggleBtn");
        if (btn) {
            btn.addEventListener("click", function() {
                const app = document.querySelector(".app");
                if (app) {
                    const isCollapsed = app.classList.toggle("sidebar-collapsed");
                    localStorage.setItem("sidebarCollapsed", isCollapsed ? "true" : "false");
                    // Apply class on HTML tag for anti-flash on page load
                    if (isCollapsed) {
                        document.documentElement.classList.add("sidebar-collapsed-state");
                    } else {
                        document.documentElement.classList.remove("sidebar-collapsed-state");
                    }
                }
            });
        }
    })();
    </script>';
    echo '</body></html>';
}
