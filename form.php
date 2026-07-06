<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_role(['sistem_yoneticisi', 'kurum_staj_sorumlusu']);

$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $id > 0;
$errors = [];

$mentors = db()->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();

// Daire başkanlıklarını çek
$depts = db()->query('SELECT DISTINCT department_name FROM department_quotas ORDER BY department_name ASC')->fetchAll(PDO::FETCH_COLUMN);

// Form alanları ve varsayılanlar
$intern = [
    'tc_no'              => '',
    'first_name'         => '',
    'last_name'          => '',
    'department'         => '',
    'school'             => '',
    'level'              => 'lisans',
    'type'               => 'zorunlu',
    'mentor_id'          => null,
    'assigned_department' => '',
    'phone'              => '',
    'address'            => '',
    'emergency_name'     => '',
    'emergency_relation' => '',
    'emergency_phone'    => '',
    'start_date'         => '',
    'end_date'           => '',
    'note'               => '',
    'skills'             => '',
    'photo'              => null,
];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM interns WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash_set('error', 'Stajyer bulunamadı.');
        redirect('index.php');
    }
    // Veritabanı henüz güncellenmemişse yeni alanlar için varsayılanlar
    $intern = $found + ['tc_no' => '', 'school' => '', 'level' => 'lisans', 'type' => 'zorunlu', 'mentor_id' => null, 'skills' => '', 'assigned_department' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (['tc_no', 'first_name', 'last_name', 'department', 'school', 'phone', 'address',
              'emergency_name', 'emergency_relation', 'emergency_phone',
              'start_date', 'end_date', 'note', 'skills', 'assigned_department'] as $field) {
        $intern[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $intern['level'] = isset(LEVELS[$_POST['level'] ?? '']) ? $_POST['level'] : 'lisans';
    $intern['type'] = in_array($_POST['type'] ?? '', ['zorunlu', 'gonullu'], true) ? $_POST['type'] : 'zorunlu';
    $intern['mentor_id'] = ((int) ($_POST['mentor_id'] ?? 0)) > 0 ? (int) $_POST['mentor_id'] : null;

    // Doğrulama
    $required = [
        'tc_no'      => 'T.C. Kimlik No',
        'first_name' => 'Ad',
        'last_name'  => 'Soyad',
        'department' => 'Okuduğu bölüm',
        'phone'      => 'Telefon numarası',
        'start_date' => 'Staja başlama tarihi',
        'end_date'   => 'Staj bitiş tarihi',
    ];
    foreach ($required as $field => $label) {
        if ($intern[$field] === '') {
            $errors[] = $label . ' alanı zorunludur.';
        }
    }

    if ($intern['tc_no'] !== '' && (strlen($intern['tc_no']) !== 11 || !ctype_digit($intern['tc_no']))) {
        $errors[] = 'T.C. Kimlik No 11 haneli bir sayı olmalıdır.';
    }

    $dateRe = '/^\d{4}-\d{2}-\d{2}$/';
    if ($intern['start_date'] !== '' && !preg_match($dateRe, $intern['start_date'])) {
        $errors[] = 'Başlama tarihi geçersiz.';
    }
    if ($intern['end_date'] !== '' && !preg_match($dateRe, $intern['end_date'])) {
        $errors[] = 'Bitiş tarihi geçersiz.';
    }
    if (!$errors && $intern['end_date'] < $intern['start_date']) {
        $errors[] = 'Bitiş tarihi, başlama tarihinden önce olamaz.';
    }

    // Fotoğraf
    $newPhoto = null;
    if (!$errors) {
        try {
            $newPhoto = handle_photo_upload('photo');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $fullName = $intern['first_name'] . ' ' . $intern['last_name'];
        $removePhoto = !empty($_POST['photo_delete']);
        $values = [
            $intern['tc_no'], $intern['first_name'], $intern['last_name'], $intern['department'],
            $intern['school'], $intern['level'], $intern['type'], $intern['mentor_id'],
            $intern['phone'], $intern['address'],
            $intern['emergency_name'], $intern['emergency_relation'], $intern['emergency_phone'],
            $intern['start_date'], $intern['end_date'], $intern['note'], $intern['skills'],
            $intern['assigned_department'],
        ];

        try {
        if ($isEdit) {
            $sql = 'UPDATE interns SET tc_no=?, first_name=?, last_name=?, department=?, school=?, level=?, type=?, mentor_id=?,
                    phone=?, address=?,
                    emergency_name=?, emergency_relation=?, emergency_phone=?,
                    start_date=?, end_date=?, note=?, skills=?, assigned_department=?';
            if ($newPhoto !== null) {
                $sql .= ', photo=?';
                $values[] = $newPhoto;
            } elseif ($removePhoto) {
                $sql .= ', photo=NULL';
            }
            $sql .= ' WHERE id=?';
            $values[] = $id;
            db()->prepare($sql)->execute($values);
            if ($newPhoto !== null || $removePhoto) {
                delete_photo($intern['photo']);
            }
            log_action('stajyer_guncelle', $fullName);
            flash_set('success', 'Stajyer bilgileri güncellendi.');
            redirect('view.php?id=' . $id);
        }

        $values[] = $newPhoto;
        db()->prepare(
            'INSERT INTO interns
             (tc_no, first_name, last_name, department, school, level, type, mentor_id,
              phone, address,
              emergency_name, emergency_relation, emergency_phone,
              start_date, end_date, note, skills, assigned_department, photo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute($values);
        log_action('stajyer_ekle', $fullName);
        flash_set('success', 'Stajyer kaydedildi.');
        redirect('view.php?id=' . (int) db()->lastInsertId());
        } catch (PDOException) {
            $errors[] = 'Veritabanı güncellemesi gerekli: tarayıcıdan guncelleme.php sayfasını bir kez açın, sonra tekrar kaydedin.';
        }
    }
}

render_header($isEdit ? 'Stajyer Düzenle' : 'Stajyer Ekle', 'index');
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Stajyer Düzenle' : 'Yeni Stajyer Ekle' ?></h1>
        <p class="page-sub"><?= $isEdit ? e($intern['first_name'] . ' ' . $intern['last_name']) . ' kaydını düzenliyorsunuz.' : 'Stajyerin kişisel, acil durum ve staj bilgilerini girin.' ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= $isEdit ? 'view.php?id=' . (int) $id : 'index.php' ?>" class="btn btn-light"><span class="ms sm">arrow_back</span> Geri Dön</a>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <ul>
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="card form-grid">
    <?= csrf_field() ?>

    <fieldset>
        <legend>Kişisel Bilgiler</legend>
        <div class="grid-2">
            <label style="grid-column: span 2;">T.C. Kimlik No *
                <input type="text" name="tc_no" required maxlength="11" minlength="11" pattern="\d{11}" placeholder="11 haneli T.C. Kimlik numarası" value="<?= e($intern['tc_no']) ?>">
            </label>
            <label>Ad *
                <input type="text" name="first_name" required value="<?= e($intern['first_name']) ?>">
            </label>
            <label>Soyad *
                <input type="text" name="last_name" required value="<?= e($intern['last_name']) ?>">
            </label>
            <label>Okulu
                <input type="text" name="school" placeholder="örn. İstanbul Teknik Üniversitesi"
                       value="<?= e($intern['school']) ?>">
            </label>
            <label>Okuduğu Bölüm *
                <input type="text" name="department" required placeholder="örn. Bilgisayar Mühendisliği"
                       value="<?= e($intern['department']) ?>">
            </label>
            <label>Eğitim Seviyesi
                <select name="level">
                    <?php foreach (LEVELS as $key => $lbl): ?>
                        <option value="<?= $key ?>" <?= $intern['level'] === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Staj Yaptığı Birim / Daire Başkanlığı
                <input type="text" name="assigned_department" list="dept_list" placeholder="örn. Bilgi İşlem Daire Başkanlığı" value="<?= e($intern['assigned_department'] ?? '') ?>">
                <datalist id="dept_list">
                    <?php foreach ($depts as $d): ?>
                        <option value="<?= e($d) ?>">
                    <?php endforeach; ?>
                </datalist>
            </label>
            <label>Telefon Numarası *
                <input type="tel" name="phone" required placeholder="05xx xxx xx xx"
                       value="<?= e($intern['phone']) ?>">
            </label>
            <label>Staj Türü
                <select name="type">
                    <option value="zorunlu" <?= $intern['type'] === 'zorunlu' ? 'selected' : '' ?>>Zorunlu</option>
                    <option value="gonullu" <?= $intern['type'] === 'gonullu' ? 'selected' : '' ?>>Gönüllü</option>
                </select>
            </label>
        </div>
        <label>Adres
            <textarea name="address" rows="2"><?= e($intern['address']) ?></textarea>
        </label>
        <label>Fotoğraf <?= $intern['photo'] ? '(yenisini seçerseniz eskisi değişir)' : '' ?>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
        </label>
        <?php if ($intern['photo']): ?>
            <div style="display:flex; align-items:center; gap:16px; margin-bottom:16px;">
                <img class="avatar avatar-lg" src="uploads/<?= e($intern['photo']) ?>" alt="Mevcut fotoğraf" style="margin-top:0;">
                <label style="display:flex; align-items:center; gap:8px; margin:0; cursor:pointer;">
                    <input type="checkbox" name="photo_delete" value="1"
                           style="width:auto; margin:0; accent-color:var(--danger);">
                    <span style="color:var(--danger);">Mevcut fotoğrafı kaldır</span>
                </label>
            </div>
        <?php endif; ?>
    </fieldset>

    <fieldset>
        <legend>Acil Durum Kişisi</legend>
        <div class="grid-3">
            <label>Adı Soyadı
                <input type="text" name="emergency_name" value="<?= e($intern['emergency_name']) ?>">
            </label>
            <label>Yakınlığı
                <input type="text" name="emergency_relation" placeholder="örn. Annesi"
                       value="<?= e($intern['emergency_relation']) ?>">
            </label>
            <label>Telefon Numarası
                <input type="tel" name="emergency_phone" value="<?= e($intern['emergency_phone']) ?>">
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>Staj Bilgileri</legend>
        <div class="grid-2">
            <label>Resmi Staja Başlama Tarihi *
                <input type="date" name="start_date" required value="<?= e($intern['start_date']) ?>">
            </label>
            <label>Staj Bitiş Tarihi *
                <input type="date" name="end_date" required value="<?= e($intern['end_date']) ?>">
            </label>
        </div>
        <label>Sorumlu Yetkili
            <select name="mentor_id">
                <option value="0">— Atanmadı —</option>
                <?php foreach ($mentors as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (int) ($intern['mentor_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>>
                        <?= e($m['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Beceriler &amp; Araçlar (Virgülle ayırarak yazın)
            <input type="text" name="skills" placeholder="örn. React.js, Tailwind CSS, Node.js, Figma" value="<?= e($intern['skills'] ?? '') ?>">
        </label>
        <label>Not
            <textarea name="note" rows="4" placeholder="Stajyer hakkında notlarınız…"><?= e($intern['note']) ?></textarea>
        </label>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><span class="ms sm">save</span> <?= $isEdit ? 'Değişiklikleri Kaydet' : 'Stajyeri Kaydet' ?></button>
        <a href="index.php" class="btn btn-light">Vazgeç</a>
    </div>
</form>
<?php render_footer(); ?>
