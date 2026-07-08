<?php
declare(strict_types=1);

/**
 * Güncelleme sihirbazı — mevcut veritabanına yeni alanları ve tabloları ekler.
 * Giriş yapmış bir yetkili tarafından bir kez çalıştırılır; işi bitince silin.
 * Her adım bağımsızdır: zaten uygulanmış adımlar atlanır, sistem bozulmaz.
 */
require_once __DIR__ . '/auth.php';

$steps = [
    'Stajyerlere TC Kimlik No alanı' =>
        "ALTER TABLE interns ADD COLUMN IF NOT EXISTS tc_no VARCHAR(11) NOT NULL DEFAULT '' AFTER id",
    'Kullanıcılara rol alanı' =>
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(30) NOT NULL DEFAULT 'birim_sorumlusu' AFTER full_name",
    'Kullanıcılara e-posta alanı' =>
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) NOT NULL DEFAULT '' AFTER full_name",
    'Kullanıcılara fotoğraf alanı' =>
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS photo VARCHAR(120) DEFAULT NULL AFTER role",
    'Yoklama tablosuna giriş-çıkış zamanı alanları' =>
        "ALTER TABLE attendance ADD COLUMN IF NOT EXISTS check_in TIME DEFAULT NULL AFTER work_date, ADD COLUMN IF NOT EXISTS check_out TIME DEFAULT NULL AFTER check_in",
    'Yoklama durumu ENUM güncellemesi' =>
        "ALTER TABLE attendance MODIFY COLUMN status ENUM('geldi', 'devamsiz', 'izinli', 'raporlu') NOT NULL",
    'Staj Dönemleri tablosu' =>
        "CREATE TABLE IF NOT EXISTS internship_periods (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci",
    'Daire Kontenjanları tablosu' =>
        "CREATE TABLE IF NOT EXISTS department_quotas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            period_id INT UNSIGNED NOT NULL,
            department_name VARCHAR(150) NOT NULL,
            lise_quota INT UNSIGNED NOT NULL DEFAULT 0,
            onlisans_quota INT UNSIGNED NOT NULL DEFAULT 0,
            lisans_quota INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_quota_period (period_id),
            CONSTRAINT fk_quota_period FOREIGN KEY (period_id)
                REFERENCES internship_periods (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci",
    'Başvurular tablosu' =>
        "CREATE TABLE IF NOT EXISTS applications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            period_id INT UNSIGNED DEFAULT NULL,
            tc_no VARCHAR(11) NOT NULL,
            first_name VARCHAR(60) NOT NULL,
            last_name VARCHAR(60) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            school VARCHAR(150) NOT NULL,
            department VARCHAR(120) NOT NULL,
            level VARCHAR(20) NOT NULL,
            type ENUM('zorunlu', 'gonullu') NOT NULL DEFAULT 'zorunlu',
            duration INT UNSIGNED NOT NULL DEFAULT 20,
            address TEXT,
            doc_student_cert VARCHAR(120) DEFAULT NULL,
            doc_intern_form VARCHAR(120) DEFAULT NULL,
            doc_sgk VARCHAR(120) DEFAULT NULL,
            photo VARCHAR(120) DEFAULT NULL,
            status ENUM('beklemede', 'onaylandi', 'reddedildi') NOT NULL DEFAULT 'beklemede',
            assigned_department VARCHAR(150) DEFAULT NULL,
            mentor_id INT UNSIGNED DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci",
    'Stajyerlere okul alanı' =>
        "ALTER TABLE interns ADD COLUMN IF NOT EXISTS school VARCHAR(150) NOT NULL DEFAULT '' AFTER department",
    'Stajyerlere eğitim seviyesi alanı' =>
        "ALTER TABLE interns ADD COLUMN IF NOT EXISTS level VARCHAR(20) NOT NULL DEFAULT 'lisans' AFTER school",
    'Yetkililere birim alanı' =>
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(150) DEFAULT NULL AFTER role",
    'Stajyerlere sorumlu yetkili alanı' =>
        "ALTER TABLE interns ADD COLUMN IF NOT EXISTS mentor_id INT UNSIGNED DEFAULT NULL AFTER note",
    'Stajyerlere beceriler alanı' =>
        "ALTER TABLE interns ADD COLUMN IF NOT EXISTS skills TEXT AFTER note",
    'Staj notlarına başlık alanı' =>
        "ALTER TABLE intern_notes ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL DEFAULT '' AFTER user_name",
    'Staj notlarına kategori alanı' =>
        "ALTER TABLE intern_notes ADD COLUMN IF NOT EXISTS category VARCHAR(100) NOT NULL DEFAULT 'Genel' AFTER title",
    'Staj notları tablosu' =>
        "CREATE TABLE IF NOT EXISTS intern_notes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            intern_id INT UNSIGNED NOT NULL,
            user_name VARCHAR(100) NOT NULL DEFAULT '',
            title VARCHAR(255) NOT NULL DEFAULT '',
            category VARCHAR(100) NOT NULL DEFAULT 'Genel',
            note_text TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_note_intern (intern_id),
            CONSTRAINT fk_note_intern FOREIGN KEY (intern_id)
                REFERENCES interns (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci",
    'Belgeler tablosu' =>
        "CREATE TABLE IF NOT EXISTS intern_documents (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci",
    'Haftalık değerlendirme tablosu' =>
        "CREATE TABLE IF NOT EXISTS evaluations (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci",
    'Log tablosu' =>
        "CREATE TABLE IF NOT EXISTS logs (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci",
    'Stajyerlere staj yaptığı birim alanı' =>
        "ALTER TABLE interns ADD COLUMN IF NOT EXISTS assigned_department VARCHAR(150) DEFAULT NULL AFTER level",
];

$results = [];
foreach ($steps as $label => $sql) {
    try {
        db()->exec($sql);
        $results[] = [$label, true, ''];
    } catch (PDOException $ex) {
        $results[] = [$label, false, $ex->getMessage()];
    }
}
$allOk = !in_array(false, array_column($results, 1), true);

render_header('Güncelleme', '');
?>
<div class="page-head">
    <div>
        <h1>Veritabanı Güncellemesi</h1>
        <p class="page-sub">Yeni özellikler için gerekli alanlar ve tablolar eklendi.</p>
    </div>
</div>

<div class="card">
    <h2>Sonuçlar</h2>
    <table>
        <tbody>
        <?php foreach ($results as [$label, $ok, $err]): ?>
            <tr>
                <td><?= e($label) ?></td>
                <td>
                    <?php if ($ok): ?>
                        <span class="badge badge-aktif">Tamam</span>
                    <?php else: ?>
                        <span class="badge badge-devamsiz">Hata</span>
                        <div class="row-sub" style="white-space:normal;"><?= e($err) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($allOk): ?>
    <div class="alert alert-success">Güncelleme tamamlandı! Artık bu dosyayı (<code>guncelleme.php</code>) sunucudan silebilirsiniz.</div>
    <a href="dashboard.php" class="btn btn-primary">Dashboard'a Git</a>
<?php else: ?>
    <div class="alert alert-error">Bazı adımlar başarısız oldu. Hata mesajlarını iletirseniz birlikte çözebiliriz.</div>
<?php endif; ?>
<?php render_footer(); ?>
