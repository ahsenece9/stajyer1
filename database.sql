-- Staj Takip Sistemi — veritabanı şeması
-- Not: install.php bu tabloları otomatik oluşturur; bu dosya manuel kurulum içindir.
-- Mevcut kurulumu güncellemek için guncelleme.php dosyasını kullanın.

CREATE DATABASE IF NOT EXISTS stajyer_takip
    CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;

USE stajyer_takip;

-- Yetkili kullanıcılar (sisteme giriş yapabilenler)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL DEFAULT '',
    role VARCHAR(30) NOT NULL DEFAULT 'birim_sorumlusu',
    photo VARCHAR(120) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Stajyerler
CREATE TABLE IF NOT EXISTS interns (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tc_no VARCHAR(11) NOT NULL DEFAULT '',
    first_name VARCHAR(60) NOT NULL,            -- Ad
    last_name VARCHAR(60) NOT NULL,             -- Soyad
    department VARCHAR(120) NOT NULL,           -- Okuduğu bölüm
    school VARCHAR(150) NOT NULL DEFAULT '',    -- Okul
    level VARCHAR(20) NOT NULL DEFAULT 'lisans',-- Eğitim seviyesi (lise/onlisans/lisans/yukseklisans/mezun)
    phone VARCHAR(30) NOT NULL,                 -- Telefon numarası
    address TEXT,                               -- Adres
    emergency_name VARCHAR(100) NOT NULL DEFAULT '',     -- Acil durum kişisi adı
    emergency_relation VARCHAR(60) NOT NULL DEFAULT '',  -- Yakınlık derecesi
    emergency_phone VARCHAR(30) NOT NULL DEFAULT '',     -- Acil durum kişisi telefonu
    start_date DATE NOT NULL,                   -- Resmi staja başlama tarihi
    end_date DATE NOT NULL,                     -- Staj bitiş tarihi
    note TEXT,                                  -- Genel not
    skills TEXT,                                -- Beceriler ve araçlar (virgülle ayrılmış)
    mentor_id INT UNSIGNED DEFAULT NULL,        -- Sorumlu yetkili (users.id)
    photo VARCHAR(120) DEFAULT NULL,            -- uploads/ altındaki dosya adı
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dates (start_date, end_date),
    KEY idx_mentor (mentor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Yoklama: yalnızca istisnalar kaydedilir (kayıt yoksa o gün "Geldi" sayılır)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Tarihli staj notları
CREATE TABLE IF NOT EXISTS intern_notes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Yüklenen belgeler
CREATE TABLE IF NOT EXISTS intern_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intern_id INT UNSIGNED NOT NULL,
    filename VARCHAR(120) NOT NULL,             -- uploads/docs/ altındaki rastgele ad
    orig_name VARCHAR(200) NOT NULL,            -- kullanıcının verdiği dosya adı
    filesize INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_doc_intern (intern_id),
    CONSTRAINT fk_doc_intern FOREIGN KEY (intern_id)
        REFERENCES interns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Haftalık değerlendirme ve görevler
CREATE TABLE IF NOT EXISTS evaluations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    intern_id INT UNSIGNED NOT NULL,
    week_start DATE NOT NULL,                   -- haftanın pazartesi günü
    score TINYINT UNSIGNED DEFAULT NULL,        -- 1-10 arası not
    task TEXT,                                  -- o hafta verilen görev
    comment TEXT,                               -- değerlendirme
    user_name VARCHAR(100) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_intern_week (intern_id, week_start),
    CONSTRAINT fk_eval_intern FOREIGN KEY (intern_id)
        REFERENCES interns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- İşlem kayıtları (log sistemi)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- İlk yetkili kullanıcıyı install.php üzerinden oluşturun
-- (şifreler bcrypt ile hashlendiği için elle INSERT önerilmez).
