<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
$db = db();

try {
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(30) NOT NULL DEFAULT 'birim_sorumlusu' AFTER full_name");
    $db->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS check_in TIME DEFAULT NULL AFTER work_date");
    $db->exec("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS check_out TIME DEFAULT NULL AFTER check_in");
    $db->exec("ALTER TABLE attendance MODIFY COLUMN status ENUM('devamsiz', 'izinli', 'raporlu') NOT NULL");

    $db->exec("CREATE TABLE IF NOT EXISTS internship_periods (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS department_quotas (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS applications (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");

    echo "Database updated successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
