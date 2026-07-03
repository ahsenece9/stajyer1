<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$periods = db()->query('SELECT * FROM internship_periods WHERE end_date >= CURDATE() ORDER BY start_date ASC')->fetchAll();
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen formu tekrar doldurun.';
    } else {
        $tc       = trim((string) ($_POST['tc_no'] ?? ''));
        $first    = trim((string) ($_POST['first_name'] ?? ''));
        $last     = trim((string) ($_POST['last_name'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $phone    = trim((string) ($_POST['phone'] ?? ''));
        $school   = trim((string) ($_POST['school'] ?? ''));
        $dept     = trim((string) ($_POST['department'] ?? ''));
        $level    = trim((string) ($_POST['level'] ?? 'lisans'));
        $type     = trim((string) ($_POST['type'] ?? 'zorunlu'));
        $duration = (int) ($_POST['duration'] ?? 20);
        $address  = trim((string) ($_POST['address'] ?? ''));
        $periodId = (int) ($_POST['period_id'] ?? 0);

        if ($tc === '' || $first === '' || $last === '' || $email === '' || $phone === '' || $school === '' || $dept === '' || $periodId === 0) {
            $error = 'Lütfen tüm yıldızlı (*) alanları doldurun.';
        } elseif (strlen($tc) !== 11 || !ctype_digit($tc)) {
            $error = 'TC Kimlik Numarası 11 haneli bir sayı olmalıdır.';
        } else {
            // Upload documents helper
            $uploaded = [];
            $uploadFields = [
                'doc_student_cert' => 'öğrenci belgesi',
                'doc_intern_form' => 'staj formu',
                'doc_sgk' => 'SGK bildirgesi',
                'photo' => 'fotoğraf'
            ];

            try {
                foreach ($uploadFields as $field => $label) {
                    if (empty($_FILES[$field]['name'])) {
                        throw new RuntimeException("Lütfen " . $label . " belgesini yükleyin.");
                    }

                    $file = $_FILES[$field];
                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException("Dosya yüklenirken hata oluştu (" . $label . ").");
                    }

                    if ($file['size'] > UPLOAD_MAX_SIZE) {
                        throw new RuntimeException($label . " boyutu 4 MB'tan büyük olamaz.");
                    }

                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
                    if ($field === 'photo') {
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                    }

                    if (!in_array($ext, $allowed, true)) {
                        throw new RuntimeException($label . " için geçersiz dosya biçimi.");
                    }

                    $newFilename = bin2hex(random_bytes(16)) . '.' . $ext;
                    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $newFilename)) {
                        throw new RuntimeException("Dosya kaydedilemedi (" . $label . ").");
                    }

                    $uploaded[$field] = $newFilename;
                }

                // Insert into DB
                $stmt = db()->prepare('
                    INSERT INTO applications (period_id, tc_no, first_name, last_name, email, phone, school, department, level, type, duration, address, doc_student_cert, doc_intern_form, doc_sgk, photo, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'beklemede\')
                ');
                $stmt->execute([
                    $periodId, $tc, $first, $last, $email, $phone, $school, $dept, $level, $type, $duration, $address,
                    $uploaded['doc_student_cert'], $uploaded['doc_intern_form'], $uploaded['doc_sgk'], $uploaded['photo']
                ]);

                $success = true;
            } catch (Exception $ex) {
                // Delete uploaded files on failure
                foreach ($uploaded as $fname) {
                    @unlink(UPLOAD_DIR . '/' . $fname);
                }
                $error = $ex->getMessage();
            }
        }
    }
}

render_head('Staj Başvuru Formu');
?>
<body class="auth-body">
<div class="auth-card" style="max-width: 650px; margin: 40px auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="sb-logo" style="width: 50px; height: 50px; font-size: 24px; margin: 0 auto 12px;"><span class="ms">school</span></span>
        <h1 style="margin: 0; font-size: 24px;">Staj Başvuru Formu</h1>
        <p class="muted">Bilgilerinizi eksiksiz doldurarak başvurunuzu gerçekleştirebilirsiniz.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="text-align: center; padding: 24px;">
            <span class="ms" style="font-size: 48px; color: var(--success); margin-bottom: 12px;">check_circle</span>
            <h2>Başvurunuz Alındı!</h2>
            <p>Staj başvurunuz başarıyla sisteme kaydedilmiştir. Belgeleriniz İnsan Kaynakları tarafından incelendikten sonra tarafınıza e-posta veya telefon yoluyla bilgilendirme yapılacaktır.</p>
            <div style="margin-top: 20px;">
                <a href="apply.php" class="btn btn-primary btn-sm">Yeni Başvuru Yap</a>
            </div>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 20px;"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (!$periods): ?>
            <div class="alert alert-error" style="text-align: center;">
                <p>Şu anda aktif veya başvurulabilir staj dönemi bulunmamaktadır. Lütfen daha sonra tekrar deneyiniz.</p>
            </div>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <h3 style="border-bottom:1px solid var(--line-soft); padding-bottom:6px; margin-bottom:16px; color:var(--primary);">Kişisel ve İletişim Bilgileri</h3>
                
                <div class="grid-2">
                    <label>T.C. Kimlik No *
                        <input type="text" name="tc_no" required maxlength="11" minlength="11" pattern="\d{11}" placeholder="11 haneli sayı">
                    </label>
                    <label>Staj Dönemi Seçimi *
                        <select name="period_id" required>
                            <?php foreach ($periods as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= date('d.m.Y', strtotime($p['start_date'])) ?> – <?= date('d.m.Y', strtotime($p['end_date'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="grid-2">
                    <label>Adınız *
                        <input type="text" name="first_name" required>
                    </label>
                    <label>Soyadınız *
                        <input type="text" name="last_name" required>
                    </label>
                </div>

                <div class="grid-2">
                    <label>E-posta Adresiniz *
                        <input type="email" name="email" required placeholder="örn. isim@mail.com">
                    </label>
                    <label>Telefon Numaranız *
                        <input type="tel" name="phone" required placeholder="örn. 0555 555 5555">
                    </label>
                </div>

                <h3 style="border-bottom:1px solid var(--line-soft); padding-bottom:6px; margin-top:24px; margin-bottom:16px; color:var(--primary);">Okul ve Eğitim Bilgileri</h3>

                <div class="grid-2">
                    <label>Okul Adı *
                        <input type="text" name="school" required placeholder="örn. İstanbul Üniversitesi">
                    </label>
                    <label>Bölüm *
                        <input type="text" name="department" required placeholder="örn. Bilgisayar Mühendisliği">
                    </label>
                </div>

                <div class="grid-3">
                    <label>Eğitim Seviyesi *
                        <select name="level" required>
                            <option value="lise">Lise</option>
                            <option value="onlisans">Önlisans</option>
                            <option value="lisans" selected>Lisans</option>
                        </select>
                    </label>
                    <label>Staj Türü *
                        <select name="type" required>
                            <option value="zorunlu">Zorunlu Staj</option>
                            <option value="gonullu">Gönüllü Staj</option>
                        </select>
                    </label>
                    <label>Staj Süresi (İş Günü) *
                        <input type="number" name="duration" min="5" max="100" required value="20">
                    </label>
                </div>

                <label>İkametgah Bilgileri / Adres *
                    <textarea name="address" rows="2" required placeholder="İkamet ettiğiniz tam adresi yazınız…"></textarea>
                </label>

                <h3 style="border-bottom:1px solid var(--line-soft); padding-bottom:6px; margin-top:24px; margin-bottom:16px; color:var(--primary);">Gerekli Evraklar ve Fotoğraf</h3>
                <p class="muted" style="font-size:12.5px; margin-top:-10px; margin-bottom:16px;">Dosya formatları: PDF, JPG, PNG, WEBP (Max 4 MB)</p>

                <div class="grid-2">
                    <label>Öğrenci Belgesi *
                        <input type="file" name="doc_student_cert" required accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </label>
                    <label>Okul Staj Kabul Formu *
                        <input type="file" name="doc_intern_form" required accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </label>
                </div>

                <div class="grid-2">
                    <label>SGK Giriş / Zorunluluk Bildirgesi *
                        <input type="file" name="doc_sgk" required accept=".pdf,.jpg,.jpeg,.png,.webp">
                    </label>
                    <label>Vesikalık Fotoğraf *
                        <input type="file" name="photo" required accept=".jpg,.jpeg,.png,.webp">
                    </label>
                </div>

                <div style="margin-top: 30px; text-align: center;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px; font-size: 15px;"><span class="ms sm">task_alt</span> Başvuruyu Tamamla</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
