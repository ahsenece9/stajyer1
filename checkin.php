<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$error = '';
$success = '';
$todayStr = date('Y-m-d');
$nowTime  = date('H:i:s');

// Daily secret QR validation
$secretSalt = 'staj_takip_secret_salt';
$expectedCode = md5($todayStr . $secretSalt);
$codeParam = $_GET['code'] ?? '';
$isQrValid = ($codeParam === $expectedCode);

// Network range validation (Kurumsal Ağ simülasyonu)
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$isLocalNetwork = (str_starts_with($ip, '127.') || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.') || $ip === '::1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tc     = trim((string) ($_POST['tc_no'] ?? ''));
    $action = trim((string) ($_POST['action'] ?? '')); // giris veya cikis

    // Enforce QR code scan or local network access
    if (!$isQrValid && !$isLocalNetwork) {
        $error = 'Yoklama kaydı sadece kurum içi ağdan veya güncel QR kod okutularak yapılabilir.';
    } elseif ($tc === '' || !in_array($action, ['giris', 'cikis'], true)) {
        $error = 'Lütfen T.C. Kimlik Numaranızı girin ve işleminizi seçin.';
    } else {
        // Find intern by T.C. No
        $stmt = db()->prepare('SELECT * FROM interns WHERE tc_no = ?');
        $stmt->execute([$tc]);
        $intern = $stmt->fetch();

        if (!$intern) {
            $error = 'Girdiğiniz T.C. Kimlik Numarasına ait kayıtlı stajyer bulunamadı.';
        } else {
            $internId = (int) $intern['id'];
            $fullName = $intern['first_name'] . ' ' . $intern['last_name'];

            // Check if staj is active today
            if ($todayStr < $intern['start_date'] || $todayStr > $intern['end_date']) {
                $error = 'Staj döneminiz dışındaki günlerde yoklama kaydı yapamazsınız.';
            } elseif ((int) date('N') >= 6 || is_turkish_holiday($todayStr)) {
                $error = 'Hafta sonları ve resmi tatillerde yoklama kaydı alınmamaktadır.';
            } else {
                // Check if there is already an exception status like izinli or raporlu today
                $stmt = db()->prepare('SELECT * FROM attendance WHERE intern_id = ? AND work_date = ?');
                $stmt->execute([$internId, $todayStr]);
                $existing = $stmt->fetch();

                if ($existing && in_array($existing['status'], ['izinli', 'raporlu'], true)) {
                    $error = 'Bugün için izinli veya raporlu olarak işaretlenmişsiniz. Giriş/Çıkış yapamazsınız.';
                } else {
                    if ($action === 'giris') {
                        if ($existing && $existing['check_in'] !== null) {
                            $error = 'Bugün zaten giriş kaydı yaptınız (Giriş saati: ' . $existing['check_in'] . ').';
                        } else {
                            db()->prepare('
                                INSERT INTO attendance (intern_id, work_date, check_in, status)
                                VALUES (?, ?, ?, \'geldi\')
                                ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), status = \'geldi\'
                            ')->execute([$internId, $todayStr, $nowTime]);
                            log_action('yoklama_giris', $fullName . ' - Saat: ' . $nowTime);
                            $success = $fullName . ' için Günlük GİRİŞ kaydı başarıyla alındı. (Saat: ' . $nowTime . ')';
                        }
                    } elseif ($action === 'cikis') {
                        if (!$existing || $existing['check_in'] === null) {
                            $error = 'Çıkış yapabilmek için önce bugün giriş kaydı yapmış olmalısınız.';
                        } elseif ($existing['check_out'] !== null) {
                            $error = 'Bugün zaten çıkış kaydı yaptınız (Çıkış saati: ' . $existing['check_out'] . ').';
                        } else {
                            db()->prepare('
                                UPDATE attendance 
                                SET check_out = ?, status = \'geldi\' 
                                WHERE intern_id = ? AND work_date = ?
                            ')->execute([$nowTime, $internId, $todayStr]);
                            log_action('yoklama_cikis', $fullName . ' - Saat: ' . $nowTime);
                            $success = $fullName . ' için Günlük ÇIKIŞ kaydı başarıyla alındı. (Saat: ' . $nowTime . ')';
                        }
                    }
                }
            }
        }
    }
}

render_head('Dijital Yoklama Sistemi');
?>
<body class="auth-body">
<div class="auth-card" style="max-width: 480px; margin: 60px auto; padding: 30px;">
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="sb-logo" style="width: 50px; height: 50px; font-size: 24px; margin: 0 auto 12px; background:var(--primary); color:white;"><span class="ms">schedule</span></span>
        <h1 style="margin: 0; font-size: 24px;">Dijital Yoklama Sistemi</h1>
        <p class="muted">Kurumsal Ağ veya QR Kod Giriş/Çıkış Sistemi</p>
    </div>

    <!-- IP & Connection Status Info -->
    <div style="padding: 12px; border-radius: 10px; background: var(--panel-bg); border: 1px solid var(--line-soft); margin-bottom: 20px; font-size: 13px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
            <span>İnternet IP Adresiniz:</span>
            <b><?= e($ip) ?></b>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span>Bağlantı Türü:</span>
            <?php if ($isQrValid): ?>
                <span class="badge badge-success" style="font-size:10px;">QR Kod Doğrulandı</span>
            <?php elseif ($isLocalNetwork): ?>
                <span class="badge badge-info" style="font-size:10px;">Kurumsal Ağ (Lokal IP)</span>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size:10px;">Dış Ağ (Yoklama Kapalı)</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 20px;"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 20px; text-align:center; padding: 20px;">
            <span class="ms" style="font-size: 40px; color: var(--success); margin-bottom: 8px;">check_circle</span>
            <p style="margin:0; font-weight:600;"><?= e($success) ?></p>
        </div>
        <div style="text-align: center;">
            <a href="checkin.php?code=<?= e($codeParam) ?>" class="btn btn-light btn-sm">Geri Dön</a>
        </div>
    <?php else: ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            
            <label>T.C. Kimlik Numaranız
                <input type="text" name="tc_no" required maxlength="11" minlength="11" pattern="\d{11}" placeholder="11 haneli T.C. Kimlik numaranız" style="font-size: 16px; text-align: center; letter-spacing: 0.1em;">
            </label>

            <div class="grid-2" style="margin-top: 24px; gap:16px;">
                <button type="submit" name="action" value="giris" class="btn btn-primary" style="padding: 16px; font-size:15px; font-weight:700; display:flex; flex-direction:column; align-items:center; gap:6px;">
                    <span class="ms" style="font-size:24px;">login</span>
                    <span>GİRİŞ YAP (Check-In)</span>
                </button>
                <button type="submit" name="action" value="cikis" class="btn btn-light" style="padding: 16px; font-size:15px; font-weight:700; border: 1px solid var(--primary); color: var(--primary); display:flex; flex-direction:column; align-items:center; gap:6px;">
                    <span class="ms" style="font-size:24px;">logout</span>
                    <span>ÇIKIŞ YAP (Check-Out)</span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
