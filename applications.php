<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Sistem yöneticisi veya Kurum Staj Sorumlusu bu sayfaya erişebilir
require_role(['sistem_yoneticisi', 'kurum_staj_sorumlusu']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'reject') {
        $appId = (int) ($_POST['app_id'] ?? 0);
        db()->prepare('UPDATE applications SET status = \'reddedildi\' WHERE id = ?')->execute([$appId]);
        log_action('basvuru_red', 'Başvuru ID: ' . $appId);
        flash_set('success', 'Staj başvurusu reddedildi.');
    }

    if ($action === 'approve') {
        $appId    = (int) ($_POST['app_id'] ?? 0);
        $quotaId  = (int) ($_POST['quota_id'] ?? 0); // Eşleştirilecek daire başkanlığı kontenjan kaydı
        $mentorId = (int) ($_POST['mentor_id'] ?? 0);

        // Fetch application details
        $stmt = db()->prepare('SELECT * FROM applications WHERE id = ? AND status = \'beklemede\'');
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        // Fetch quota details
        $stmt = db()->prepare('SELECT * FROM department_quotas WHERE id = ?');
        $stmt->execute([$quotaId]);
        $quota = $stmt->fetch();

        // Fetch period details
        $stmt = db()->prepare('SELECT * FROM internship_periods WHERE id = ?');
        $stmt->execute([$app['period_id'] ?? 0]);
        $period = $stmt->fetch();

        if (!$app) {
            flash_set('error', 'Başvuru bulunamadı veya zaten işlendi.');
        } elseif (!$quota || !$period) {
            flash_set('error', 'Lütfen geçerli bir Daire Başkanlığı eşleştirmesi yapın.');
        } else {
            // Eşleştirilen birim sorumlusunun geçerliliğini kontrol et
            $mentorStmt = db()->prepare('SELECT id, full_name FROM users WHERE id = ?');
            $mentorStmt->execute([$mentorId]);
            $mentor = $mentorStmt->fetch();

            db()->beginTransaction();
            try {
                // Update application status
                db()->prepare('
                    UPDATE applications 
                    SET status = \'onaylandi\', assigned_department = ?, mentor_id = ? 
                    WHERE id = ?
                ')->execute([$quota['department_name'], $mentorId ?: null, $appId]);

                // Create intern record
                db()->prepare('
                    INSERT INTO interns (first_name, last_name, department, school, level, type, phone, address, start_date, end_date, mentor_id, photo, note, assigned_department)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ')->execute([
                    $app['first_name'], $app['last_name'], $app['department'], $app['school'], $app['level'], $app['type'],
                    $app['phone'], $app['address'], $period['start_date'], $period['end_date'],
                    $mentorId ?: null, $app['photo'], 'Online başvuru onaylandı. Başvuru ID: ' . $appId,
                    $quota['department_name']
                ]);

                // Log the action
                log_action('basvuru_onay', $app['first_name'] . ' ' . $app['last_name'] . ' -> ' . $quota['department_name']);
                db()->commit();
                flash_set('success', 'Başvuru onaylandı ve stajyer sisteme kaydedildi.');
            } catch (Exception $e) {
                db()->rollBack();
                flash_set('error', 'Onaylama sırasında bir hata oluştu: ' . $e->getMessage());
            }
        }
    }

    redirect('applications.php');
}

// Fetch pending applications
$pending = db()->query('
    SELECT a.*, p.name as period_name 
    FROM applications a 
    LEFT JOIN internship_periods p ON p.id = a.period_id 
    WHERE a.status = \'beklemede\' 
    ORDER BY a.created_at ASC
')->fetchAll();

// Fetch mentors
$mentors = db()->query('SELECT id, full_name FROM users WHERE role = \'birim_sorumlusu\' ORDER BY full_name ASC')->fetchAll();

// Fetch quotas to display in the match dropdown
$quotas = db()->query('
    SELECT q.*, p.name as period_name, p.start_date, p.end_date 
    FROM department_quotas q 
    JOIN internship_periods p ON p.id = q.period_id 
    ORDER BY p.start_date DESC, q.department_name ASC
')->fetchAll();

// Calculate remaining quotas dynamically
$quotaOptions = [];
foreach ($quotas as $q) {
    foreach (['lise', 'onlisans', 'lisans'] as $lvl) {
        // Count existing interns for this department, level and period dates
        $stmt = db()->prepare('
            SELECT COUNT(*) 
            FROM interns 
            WHERE department = ? AND level = ? 
              AND start_date >= ? AND end_date <= ?
        ');
        $stmt->execute([$q['department_name'], $lvl, $q['start_date'], $q['end_date']]);
        $filled = (int) $stmt->fetchColumn();

        $limit = 0;
        if ($lvl === 'lise') $limit = $q['lise_quota'];
        elseif ($lvl === 'onlisans') $limit = $q['onlisans_quota'];
        elseif ($lvl === 'lisans') $limit = $q['lisans_quota'];

        $remaining = max(0, $limit - $filled);
        $quotaOptions[] = [
            'id' => $q['id'],
            'period_id' => $q['period_id'],
            'department_name' => $q['department_name'],
            'level' => $lvl,
            'remaining' => $remaining,
            'limit' => $limit,
            'label' => $q['department_name'] . ' (' . ucfirst($lvl) . ') — Kalan: ' . $remaining . '/' . $limit
        ];
    }
}

render_header('Başvuru Kabul Modülü', 'applications');
?>
<div class="page-head">
    <div>
        <h1>Başvuru Kabul Modülü</h1>
        <p class="page-sub">Online staj başvurularının evrak kontrolleri, onay süreçleri ve birim/mentor eşleştirmeleri.</p>
    </div>
</div>

<?php if (!$pending): ?>
    <div class="card" style="text-align: center; padding: 40px 20px;">
        <span class="ms" style="font-size:48px; color:var(--muted); margin-bottom:12px;">check_circle</span>
        <h2>Bekleyen Başvuru Bulunmamaktadır</h2>
        <p class="muted">Tüm online başvurular işlenmiş veya onaylanmıştır.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Fotoğraf & Ad Soyad</th>
                <th>T.C. No & İletişim</th>
                <th>Okul & Bölüm</th>
                <th>Dönem & Süre</th>
                <th>Evraklar</th>
                <th style="text-align:right;">İşlemler</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pending as $app): ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <?php if ($app['photo']): ?>
                                <img src="uploads/<?= e($app['photo']) ?>" alt="" style="width:48px; height:48px; border-radius:8px; object-fit:cover;">
                            <?php else: ?>
                                <div class="avatar avatar-empty" style="width:48px; height:48px; border-radius:8px; font-size:18px;">
                                    <?= e(mb_strtoupper(mb_substr($app['first_name'], 0, 1) . mb_substr($app['last_name'], 0, 1), 'UTF-8')) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <b><?= e($app['first_name'] . ' ' . $app['last_name']) ?></b>
                                <span class="row-sub" style="display:block; text-transform:uppercase; font-size:11px; font-weight:700; color:var(--primary);"><?= e($app['level']) ?> / <?= e($app['type']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <b><?= e($app['tc_no']) ?></b>
                        <span class="row-sub" style="display:block;"><?= e($app['phone']) ?></span>
                        <span class="row-sub" style="display:block;"><?= e($app['email']) ?></span>
                    </td>
                    <td>
                        <b><?= e($app['school']) ?></b>
                        <span class="row-sub" style="display:block;"><?= e($app['department']) ?></span>
                    </td>
                    <td>
                        <b><?= e($app['period_name']) ?></b>
                        <span class="row-sub" style="display:block;"><?= (int) $app['duration'] ?> İş Günü</span>
                    </td>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:4px; font-size:12px;">
                            <a href="uploads/<?= e($app['doc_student_cert']) ?>" target="_blank" class="row-link" style="color:var(--primary);"><span class="ms sm">description</span> Öğrenci Belgesi</a>
                            <a href="uploads/<?= e($app['doc_intern_form']) ?>" target="_blank" class="row-link" style="color:var(--primary);"><span class="ms sm">description</span> Staj Formu</a>
                            <a href="uploads/<?= e($app['doc_sgk']) ?>" target="_blank" class="row-link" style="color:var(--primary);"><span class="ms sm">description</span> SGK Bildirgesi</a>
                        </div>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex; justify-content:flex-end; gap:8px;">
                            <button type="button" class="btn btn-primary btn-xs" onclick="openApproveModal(<?= (int)$app['id'] ?>, <?= (int)$app['period_id'] ?>, '<?= e($app['first_name'] . ' ' . $app['last_name']) ?>', '<?= e($app['level']) ?>')"><span class="ms sm">check_circle</span> Onayla</button>
                            <form method="post" onsubmit="return confirm('Bu başvuruyu reddetmek istediğinize emin misiniz?');" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="app_id" value="<?= (int) $app['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-xs"><span class="ms sm">close</span> Reddet</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Approve Match Modal -->
<div id="approveModal" class="modal-overlay">
    <div class="modal-content" style="max-width:480px;">
        <div class="modal-header">
            <h3>Başvuru Onay & Eşleştirme</h3>
            <button type="button" onclick="closeApproveModal()" class="btn-icon" style="width:32px;height:32px; border:none; background:transparent; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;"><span class="ms">close</span></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="app_id" id="approveAppId" value="0">
            
            <p><strong>Başvuran:</strong> <span id="approveName"></span></p>
            <p><strong>Eğitim Düzeyi:</strong> <span id="approveLevel" style="text-transform:uppercase; font-weight:700;"></span></p>

            <label style="margin-bottom:12px; display:block; text-align:left;">Daire Başkanlığı / Kontenjan Eşleştirme *
                <select name="quota_id" id="approveQuotaSelect" required>
                    <option value="">-- Daire Başkanlığı Seçin --</option>
                </select>
            </label>

            <label style="margin-bottom:16px; display:block; text-align:left;">Birim Sorumlusu (Mentor) *
                <select name="mentor_id" required>
                    <option value="">-- Sorumlu Seçin --</option>
                    <?php foreach ($mentors as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= e($m['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="modal-footer">
                <button type="button" onclick="closeApproveModal()" class="btn btn-light">Vazgeç</button>
                <button type="submit" class="btn btn-primary">Onayla ve Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
var quotaOptions = <?= json_encode($quotaOptions) ?>;

function openApproveModal(id, periodId, name, level) {
    document.getElementById('approveAppId').value = id;
    document.getElementById('approveName').textContent = name;
    document.getElementById('approveLevel').textContent = level;

    // Filter quota options by period_id and level
    var select = document.getElementById('approveQuotaSelect');
    select.innerHTML = '<option value="">-- Daire Başkanlığı Seçin --</option>';

    var filtered = quotaOptions.filter(function (opt) {
        return opt.period_id === periodId && opt.level === level;
    });

    filtered.forEach(function (opt) {
        var el = document.createElement('option');
        el.value = opt.id;
        el.textContent = opt.label;
        if (opt.remaining <= 0) {
            el.disabled = true;
            el.textContent += ' (KONTENJAN DOLU)';
        }
        select.appendChild(el);
    });

    document.getElementById('approveModal').classList.add('open');
}

function closeApproveModal() {
    document.getElementById('approveModal').classList.remove('open');
}
</script>
<?php render_footer(); ?>
