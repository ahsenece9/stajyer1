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

    if ($action === 'period_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM internship_periods WHERE id = ?')->execute([$id]);
        log_action('donem_sil', 'ID: ' . $id);
        flash_set('success', 'Staj dönemi silindi.');
    }

    redirect('quotas.php');
}

$periods = db()->query('SELECT * FROM internship_periods ORDER BY start_date DESC')->fetchAll();

render_header('Staj Dönemleri Yönetimi', 'quotas');
?>
<div class="page-head">
    <div>
        <h1>Dönem & Kontenjan Yönetimi</h1>
        <p class="page-sub">Lise, Önlisans ve Lisans düzeyinde Daire Başkanlıkları bazında kontenjan planlaması.</p>
    </div>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Staj Dönemleri</h2>
        <button type="button" id="togglePeriodBtn" class="btn btn-primary btn-sm" onclick="togglePeriodForm()"><span class="ms sm">add</span> Yeni Dönem</button>
    </div>

    <form id="addPeriodForm" method="post" style="display:none; margin-bottom:20px; padding:15px; border:1px solid var(--line-soft); border-radius:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="period_add">
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
            <button type="button" class="btn btn-light btn-sm" onclick="togglePeriodForm()">Vazgeç</button>
        </div>
    </form>

    <?php if (!$periods): ?>
        <p class="muted">Henüz tanımlanmış bir staj dönemi bulunmamaktadır.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th style="text-align: center;">Dönem Adı</th>
                    <th style="text-align: center;">Tarih Aralığı</th>
                    <th style="text-align: center;">İşlemler</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($periods as $p): ?>
                    <tr>
                        <td style="text-align: center;"><b><?= e($p['name']) ?></b></td>
                        <td style="text-align: center;"><?= e(date('d.m.Y', strtotime($p['start_date']))) ?> – <?= e(date('d.m.Y', strtotime($p['end_date']))) ?></td>
                        <td style="text-align: center;">
                            <div style="display:inline-flex; align-items:center; gap:16px; justify-content:center;">
                                <a href="period_quotas.php?period_id=<?= (int)$p['id'] ?>" style="color: var(--primary); font-weight: 700; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                    <span class="ms" style="font-size:16px;">list_alt</span> Kontenjanlar
                                </a>
                                <form method="post" onsubmit="return confirm('Bu staj dönemini silmek, buna bağlı tüm kontenjanları silecek. Emin misiniz?');" style="display:inline; margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="period_delete">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn-icon danger" title="Sil" style="border:none; background:transparent; cursor:pointer; color:var(--danger); display:flex; align-items:center; justify-content:center; padding:0; width:24px; height:24px;"><span class="ms" style="font-size:18px;">delete</span></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function togglePeriodForm() {
    var form = document.getElementById('addPeriodForm');
    var btn = document.getElementById('togglePeriodBtn');
    if (form.style.display === 'none') {
        form.style.display = 'block';
        btn.innerHTML = '<span class="ms sm">arrow_back</span> Geri Dön';
        btn.className = 'btn btn-light btn-sm';
    } else {
        form.style.display = 'none';
        btn.innerHTML = '<span class="ms sm">add</span> Yeni Dönem';
        btn.className = 'btn btn-primary btn-sm';
    }
}
</script>
<?php render_footer(); ?>
