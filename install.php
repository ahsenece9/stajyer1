<?php
declare(strict_types=1);

/**
 * Kurulum sihirbazı — tabloları oluşturur ve ilk yetkili kullanıcıyı ekler.
 * Kurulum bittikten sonra bu dosyayı sunucudan SİLİN.
 */
require __DIR__ . '/config.php';

$error = '';
$done  = false;

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $ex) {
    exit('<h2>Veritabanına bağlanılamadı</h2><p>config.php dosyasındaki DB_HOST, DB_NAME, DB_USER ve DB_PASS '
        . 'değerlerini kontrol edin. Veritabanını (<code>' . htmlspecialchars(DB_NAME) . '</code>) önceden oluşturmuş '
        . 'olmanız gerekir.</p><p>Hata: ' . htmlspecialchars($ex->getMessage()) . '</p>');
}

// Tabloları oluştur
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS interns (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        first_name VARCHAR(60) NOT NULL,
        last_name VARCHAR(60) NOT NULL,
        department VARCHAR(120) NOT NULL,
        school VARCHAR(150) NOT NULL DEFAULT '',
        level VARCHAR(20) NOT NULL DEFAULT 'lisans',
        phone VARCHAR(30) NOT NULL,
        address TEXT,
        emergency_name VARCHAR(100) NOT NULL DEFAULT '',
        emergency_relation VARCHAR(60) NOT NULL DEFAULT '',
        emergency_phone VARCHAR(30) NOT NULL DEFAULT '',
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        note TEXT,
        mentor_id INT UNSIGNED DEFAULT NULL,
        photo VARCHAR(120) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_dates (start_date, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS attendance (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        intern_id INT UNSIGNED NOT NULL,
        work_date DATE NOT NULL,
        status ENUM('devamsiz', 'izinli') NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_intern_day (intern_id, work_date),
        CONSTRAINT fk_att_intern FOREIGN KEY (intern_id)
            REFERENCES interns (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS intern_notes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        intern_id INT UNSIGNED NOT NULL,
        user_name VARCHAR(100) NOT NULL DEFAULT '',
        note_text TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_note_intern (intern_id),
        CONSTRAINT fk_note_intern FOREIGN KEY (intern_id)
            REFERENCES interns (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS intern_documents (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        intern_id INT UNSIGNED NOT NULL,
        filename VARCHAR(120) NOT NULL,
        orig_name VARCHAR(200) NOT NULL,
        filesize INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_doc_intern (intern_id),
        CONSTRAINT fk_doc_intern FOREIGN KEY (intern_id)
            REFERENCES interns (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS evaluations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        intern_id INT UNSIGNED NOT NULL,
        week_start DATE NOT NULL,
        score TINYINT UNSIGNED DEFAULT NULL,
        task TEXT,
        comment TEXT,
        user_name VARCHAR(100) NOT NULL DEFAULT '',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_intern_week (intern_id, week_start),
        CONSTRAINT fk_eval_intern FOREIGN KEY (intern_id)
            REFERENCES interns (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS logs (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED DEFAULT NULL,
        user_name VARCHAR(100) NOT NULL DEFAULT '',
        action VARCHAR(40) NOT NULL,
        detail VARCHAR(255) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_action (action),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci
");

// Zaten kullanıcı varsa kurulum kapalı
$hasUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;

if (!$hasUsers && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($fullName === '' || $username === '' || $password === '') {
        $error = 'Tüm alanlar zorunludur.';
    } elseif (mb_strlen($password) < 8) {
        $error = 'Şifre en az 8 karakter olmalıdır.';
    } else {
        $pdo->prepare('INSERT INTO users (username, password_hash, full_name) VALUES (?, ?, ?)')
            ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName]);
        $done = true;
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kurulum — Staj Takip</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1>📋 Staj Takip Kurulumu</h1>

    <?php if ($done): ?>
        <div class="alert alert-success">Kurulum tamamlandı! Tablolar oluşturuldu ve yetkili kullanıcınız eklendi.</div>
        <div class="alert alert-error"><strong>Önemli:</strong> Güvenlik için <code>install.php</code> dosyasını sunucudan silin.</div>
        <a href="login.php" class="btn btn-primary btn-block">Giriş Ekranına Git</a>

    <?php elseif ($hasUsers): ?>
        <div class="alert alert-error">Kurulum zaten yapılmış. Güvenlik için <code>install.php</code> dosyasını sunucudan silin.</div>
        <a href="login.php" class="btn btn-primary btn-block">Giriş Ekranına Git</a>

    <?php else: ?>
        <p class="muted">Tablolar hazırlandı. Şimdi ilk yetkili kullanıcıyı oluşturun.</p>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <label>Ad Soyad
                <input type="text" name="full_name" required autofocus>
            </label>
            <label>Kullanıcı Adı
                <input type="text" name="username" required>
            </label>
            <label>Şifre (en az 8 karakter)
                <input type="password" name="password" required minlength="8">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Kurulumu Tamamla</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
