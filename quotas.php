<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Sadece sistem yöneticileri bu sayfaya erişebilir
require_role(['sistem_yoneticisi']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'period_add') {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $start = (string) ($_POST['start_date'] ?? '');
        $end   = (string) ($_POST['end_date'] ?? '');

        if ($name === '' || $start === '' || $end === '') {
            flash_set('error', 'Tüm alanlar zorunludur.');
        } else {
            db()->prepare('INSERT INTO internship_periods (name, start_date, end_date) VALUES (?, ?, ?)')
                ->execute([$name, $start, $end]);
            log_action('donem_ekle', $name);
            flash_set('success', 'Staj dönemi başarıyla eklendi: ' . $name);
        }
    }

    if ($action === 'quota_add') {
        $periodId = (int) ($_POST['period_id'] ?? 0);
        $dept     = trim((string) ($_POST['department_name'] ?? ''));
        $lise     = (int) ($_POST['lise_quota'] ?? 0);
        $onlisans = (int) ($_POST['onlisans_quota'] ?? 0);
        $lisans   = (int) ($_POST['lisans_quota'] ?? 0);

        if ($periodId <= 0 || $dept === '') {
            flash_set('error', 'Lütfen geçerli bir dönem ve daire başkanlığı adı girin.');
        } else {
            db()->prepare('INSERT INTO department_quotas (period_id, department_name, lise_quota, onlisans_quota, lisans_quota) VALUES (?, ?, ?, ?, ?)')
                ->execute([$periodId, $dept, $lise, $onlisans, $lisans]);
            log_action('kontenjan_ekle', $dept . ' (' . $lise . ' Lise, ' . $onlisans . ' Önlisans, ' . $lisans . ' Lisans)');
            flash_set('success', 'Daire başkanlığı kontenjanı eklendi.');
        }
    }

    if ($action === 'period_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM internship_periods WHERE id = ?')->execute([$id]);
        log_action('donem_sil', 'ID: ' . $id);
        flash_set('success', 'Staj dönemi silindi.');
    }

    if ($action === 'quota_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM department_quotas WHERE id = ?')->execute([$id]);
        log_action('kontenjan_sil', 'ID: ' . $id);
        flash_set('success', 'Kontenjan kaydı silindi.');
    }

    redirect('quotas.php');
}

$periods = db()->query('SELECT * FROM internship_periods ORDER BY start_date DESC')->fetchAll();
$quotas = db()->query('SELECT q.*, p.name as period_name FROM department_quotas q JOIN internship_periods p ON p.id = q.period_id ORDER BY p.start_date DESC, q.department_name ASC')->fetchAll();

$quotasByPeriod = [];
foreach ($quotas as $q) {
    $quotasByPeriod[(int) $q['period_id']][] = $q;
}

render_header('Dönem & Kontenjan Yönetimi', 'quotas');
?>
<div class="page-head">
    <div>
        <h1>Dönem & Kontenjan Yönetimi</h1>
        <p class="page-sub">Lise, Önlisans ve Lisans düzeyinde Daire Başkanlıkları bazında kontenjan planlaması.</p>
    </div>
</div>

<div class="grid-2 align-start">
    <!-- Sol: Dönemler -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0;">Staj Dönemleri</h2>
            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('addPeriodForm').style.display='block'"><span class="ms sm">add</span> Yeni Dönem</button>
        </div>

        <form id="addPeriodForm" method="post" style="display:none; margin-bottom:20px; padding:15px; border:1px solid var(--line-soft); border-radius:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="period_add">
            <h3 style="margin-top:0;">Yeni Dönem Ekle</h3>
            <label>Dönem Adı
                <input type="text" name="name" required placeholder="örn. 2026 Yaz Dönemi">
            </label>
            <div class="grid-2">
                <label>Başlangıç Tarihi
                    <input type="date" name="start_date" required>
                </label>
                <label>Bitiş Tarihi
                    <input type="date" name="end_date" required>
                </label>
            </div>
            <div style="margin-top:12px; display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
                <button type="button" class="btn btn-light btn-sm" onclick="document.getElementById('addPeriodForm').style.display='none'">Vazgeç</button>
            </div>
        </form>

        <?php if (!$periods): ?>
            <p class="muted">Henüz tanımlanmış bir staj dönemi bulunmamaktadır.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Dönem Adı</th>
                        <th>Tarih Aralığı</th>
                        <th style="text-align:right;">İşlem</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($periods as $p): ?>
                        <tr>
                            <td><b><?= e($p['name']) ?></b></td>
                            <td><?= e(date('d.m.Y', strtotime($p['start_date']))) ?> – <?= e(date('d.m.Y', strtotime($p['end_date']))) ?></td>
                            <td style="text-align:right;">
                                <form method="post" onsubmit="return confirm('Bu staj dönemini silmek, buna bağlı tüm kontenjanları silecek. Emin misiniz?');" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="period_delete">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn-icon danger" title="Sil"><span class="ms">delete</span></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sağ: Kontenjanlar -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h2 style="margin:0;">Birim / Daire Kontenjanları</h2>
            <?php if ($periods): ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('addQuotaForm').style.display='block'"><span class="ms sm">add</span> Kontenjan Ekle</button>
            <?php endif; ?>
        </div>

        <form id="addQuotaForm" method="post" style="display:none; margin-bottom:20px; padding:15px; border:1px solid var(--line-soft); border-radius:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="quota_add">
            <h3 style="margin-top:0;">Yeni Kontenjan Planı</h3>
            <label>Staj Dönemi
                <select name="period_id" required>
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Daire Başkanlığı / Birim Adı
                <input type="text" name="department_name" required placeholder="örn. Bilgi İşlem Daire Başkanlığı">
            </label>
            <div class="grid-3" style="margin-top:10px;">
                <label>Lise Kontenjanı
                    <input type="number" name="lise_quota" min="0" required value="0">
                </label>
                <label>Önlisans Kontenjanı
                    <input type="number" name="onlisans_quota" min="0" required value="0">
                </label>
                <label>Lisans Kontenjanı
                    <input type="number" name="lisans_quota" min="0" required value="0">
                </label>
            </div>
            <div style="margin-top:16px; display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
                <button type="button" class="btn btn-light btn-sm" onclick="document.getElementById('addQuotaForm').style.display='none'">Vazgeç</button>
            </div>
        </form>

        <?php if (!$periods): ?>
            <p class="muted">Kontenjan planlamak için önce bir staj dönemi tanımlamalısınız.</p>
        <?php elseif (!$quotas): ?>
            <p class="muted">Tanımlı daire başkanlığı kontenjanı bulunmamaktadır.</p>
        <?php else: ?>
            <?php foreach ($periods as $p): $pList = $quotasByPeriod[(int) $p['id']] ?? []; ?>
                <div style="margin-bottom:24px;">
                    <h3 style="border-bottom:1px solid var(--line-soft); padding-bottom:8px; color:var(--primary);"><?= e($p['name']) ?> Planı</h3>
                    <?php if (!$pList): ?>
                        <p class="muted" style="font-size:13px;">Bu dönem için henüz kontenjan planı yapılmadı.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Daire / Birim</th>
                                    <th style="text-align:center;">Lise</th>
                                    <th style="text-align:center;">Önlisans</th>
                                    <th style="text-align:center;">Lisans</th>
                                    <th style="text-align:right;"></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($pList as $q): ?>
                                    <tr>
                                        <td><b><?= e($q['department_name']) ?></b></td>
                                        <td style="text-align:center;"><span class="badge badge-success"><?= (int) $q['lise_quota'] ?></span></td>
                                        <td style="text-align:center;"><span class="badge badge-info"><?= (int) $q['onlisans_quota'] ?></span></td>
                                        <td style="text-align:center;"><span class="badge badge-primary"><?= (int) $q['lisans_quota'] ?></span></td>
                                        <td style="text-align:right;">
                                            <form method="post" onsubmit="return confirm('Bu kontenjan kaydını silmek istediğinize emin misiniz?');" style="margin:0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="quota_delete">
                                                <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                                                <button type="submit" class="btn-icon danger" title="Sil"><span class="ms">delete</span></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
