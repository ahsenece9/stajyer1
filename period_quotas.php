<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Sadece sistem yöneticileri bu sayfaya erişebilir
require_role(['sistem_yoneticisi']);

$periodId = isset($_GET['period_id']) ? (int) $_GET['period_id'] : 0;

// Dönem bilgilerini çek
$stmt = db()->prepare('SELECT * FROM internship_periods WHERE id = ?');
$stmt->execute([$periodId]);
$period = $stmt->fetch();

if (!$period) {
    flash_set('error', 'Staj dönemi bulunamadı.');
    redirect('quotas.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'quota_add') {
        $dept     = trim((string) ($_POST['department_name'] ?? ''));
        $lise     = (int) ($_POST['lise_quota'] ?? 0);
        $onlisans = (int) ($_POST['onlisans_quota'] ?? 0);
        $lisans   = (int) ($_POST['lisans_quota'] ?? 0);

        if ($dept === '') {
            flash_set('error', 'Lütfen geçerli bir daire başkanlığı adı girin.');
        } else {
            db()->prepare('INSERT INTO department_quotas (period_id, department_name, lise_quota, onlisans_quota, lisans_quota) VALUES (?, ?, ?, ?, ?)')
                ->execute([$periodId, $dept, $lise, $onlisans, $lisans]);
            log_action('kontenjan_ekle', $dept . ' (' . $lise . ' Lise, ' . $onlisans . ' Önlisans, ' . $lisans . ' Lisans) - Dönem: ' . $period['name']);
            flash_set('success', 'Daire başkanlığı kontenjanı başarıyla eklendi.');
        }
    }

    if ($action === 'quota_edit') {
        $id       = (int) ($_POST['id'] ?? 0);
        $dept     = trim((string) ($_POST['department_name'] ?? ''));
        $lise     = (int) ($_POST['lise_quota'] ?? 0);
        $onlisans = (int) ($_POST['onlisans_quota'] ?? 0);
        $lisans   = (int) ($_POST['lisans_quota'] ?? 0);

        if ($dept === '') {
            flash_set('error', 'Lütfen geçerli bir daire başkanlığı adı girin.');
        } else {
            db()->prepare('UPDATE department_quotas SET department_name = ?, lise_quota = ?, onlisans_quota = ?, lisans_quota = ? WHERE id = ? AND period_id = ?')
                ->execute([$dept, $lise, $onlisans, $lisans, $id, $periodId]);
            log_action('kontenjan_guncelle', $dept . ' (' . $lise . ' Lise, ' . $onlisans . ' Önlisans, ' . $lisans . ' Lisans) - Dönem: ' . $period['name']);
            flash_set('success', 'Kontenjan kaydı başarıyla güncellendi.');
        }
    }

    if ($action === 'quota_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM department_quotas WHERE id = ? AND period_id = ?')->execute([$id, $periodId]);
        log_action('kontenjan_sil', 'ID: ' . $id);
        flash_set('success', 'Kontenjan kaydı silindi.');
    }

    redirect('period_quotas.php?period_id=' . $periodId);
}

// Bu dönemin kontenjanlarını çek
$quotasStmt = db()->prepare('SELECT * FROM department_quotas WHERE period_id = ? ORDER BY department_name ASC');
$quotasStmt->execute([$periodId]);
$quotas = $quotasStmt->fetchAll();

// Bu dönem staj yapacak stajyerleri çek (start_date ve end_date aralığı eşleşen stajyerler)
$internsStmt = db()->prepare('SELECT id, first_name, last_name, department, level FROM interns WHERE start_date = ? AND end_date = ?');
$internsStmt->execute([$period['start_date'], $period['end_date']]);
$assignedInterns = $internsStmt->fetchAll();

// Stajyerleri departmanlarına göre grupla
$internsByDept = [];
foreach ($assignedInterns as $intern) {
    $internsByDept[trim(mb_strtolower($intern['department'], 'UTF-8'))][] = $intern;
}

render_header($period['name'] . ' Kontenjanları', 'quotas');
?>
<div class="page-head">
    <div style="display:flex; align-items:center; gap:12px;">
        <a href="quotas.php" class="btn btn-light btn-sm flex items-center justify-center" style="padding:8px; border-radius:50%; width:36px; height:36px; text-decoration:none;" title="Geri Dön">
            <span class="ms text-lg" style="margin:0;">arrow_back</span>
        </a>
        <div>
            <h1><?= e($period['name']) ?> Planı</h1>
            <p class="page-sub"><?= e(date('d.m.Y', strtotime($period['start_date']))) ?> – <?= e(date('d.m.Y', strtotime($period['end_date']))) ?> tarihleri arasındaki kontenjan detayları.</p>
        </div>
    </div>
</div>

<style>
    /* Modal styles */
    .user-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        pointer-events: none;
        transition: all 0.25s ease;
    }
    .user-modal-overlay.open {
        opacity: 1;
        pointer-events: auto;
    }
    .user-modal-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 16px;
        padding: 24px;
        width: 100%;
        max-width: 440px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        transform: scale(0.92);
        transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .user-modal-overlay.open .user-modal-card {
        transform: scale(1);
    }
    /* Remove dot from status-like badges in tables */
    .badge-no-dot::before {
        display: none !important;
    }
</style>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <h2 style="margin:0;">Tanımlı Kontenjanlar</h2>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <!-- Arama Kutusu -->
            <div style="position:relative; width: 200px;">
                <span class="ms" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--muted); font-size:18px;">search</span>
                <input type="text" id="quotaSearch" placeholder="Birim / Daire ara..." onkeyup="filterQuotas()" style="width:100%; padding:8px 12px 8px 34px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text); font-size:13px; margin:0;">
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="toggleFormBtn" onclick="openQuotaModal(0, '', 0, 0, 0)"><span class="ms sm">add</span> Yeni Kontenjan Tanımla</button>
        </div>
    </div>

    <?php if (!$quotas): ?>
        <p class="muted">Bu döneme ait henüz kontenjan planı yapılmamıştır.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table id="quotasTable">
                <thead>
                <tr>
                    <th>Daire / Birim Adı</th>
                    <th style="text-align:center; width: 140px;">Lise</th>
                    <th style="text-align:center; width: 140px;">Önlisans</th>
                    <th style="text-align:center; width: 140px;">Lisans</th>
                    <th style="text-align:center; width: 140px;">Toplam</th>
                    <th style="text-align:right; width: 110px;">İşlem</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($quotas as $q): 
                    $liseQ     = (int) $q['lise_quota'];
                    $onlisansQ = (int) $q['onlisans_quota'];
                    $lisansQ   = (int) $q['lisans_quota'];
                    $totalQ    = $liseQ + $onlisansQ + $lisansQ;

                    // Bu birime ve bu döneme atanan stajyerlerin sayısını dinamik sorgula
                    $filledStmt = db()->prepare('
                        SELECT level, COUNT(*) as count 
                        FROM interns 
                        WHERE assigned_department = ? 
                          AND start_date >= ? AND end_date <= ?
                        GROUP BY level
                    ');
                    $filledStmt->execute([$q['department_name'], $period['start_date'], $period['end_date']]);
                    $filledRows = $filledStmt->fetchAll();
                    
                    $filled = ['lise' => 0, 'onlisans' => 0, 'lisans' => 0];
                    foreach ($filledRows as $fr) {
                        $filled[$fr['level']] = (int)$fr['count'];
                    }
                    $totalFilled = array_sum($filled);
                ?>
                    <tr class="quota-row">
                        <td class="dept-name-cell">
                            <a href="department_details.php?name=<?= urlencode($q['department_name']) ?>&period_id=<?= (int)$periodId ?>" class="row-link" style="font-weight: 700; color: var(--text); text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                <?= e($q['department_name']) ?>
                            </a>
                        </td>
                        <td style="text-align:center;"><span class="badge badge-no-dot badge-success"><?= $filled['lise'] ?> &nbsp; / &nbsp; <?= $liseQ ?></span></td>
                        <td style="text-align:center;"><span class="badge badge-no-dot badge-info"><?= $filled['onlisans'] ?> &nbsp; / &nbsp; <?= $onlisansQ ?></span></td>
                        <td style="text-align:center;"><span class="badge badge-no-dot badge-primary"><?= $filled['lisans'] ?> &nbsp; / &nbsp; <?= $lisansQ ?></span></td>
                        <td style="text-align:center;"><b><?= $totalFilled ?> &nbsp; / &nbsp; <?= $totalQ ?></b></td>
                        <td style="text-align:right;">
                            <div style="display:inline-flex; align-items:center; gap:8px; justify-content:flex-end;">
                                <button type="button" class="btn-icon" title="Düzenle" onclick="openQuotaModal(<?= (int)$q['id'] ?>, '<?= e($q['department_name']) ?>', <?= $liseQ ?>, <?= $onlisansQ ?>, <?= $lisansQ ?>)" style="color:var(--primary); border:none; background:transparent; cursor:pointer; padding:0; display:flex; align-items:center; justify-content:center; width:24px; height:24px;"><span class="ms" style="font-size:18px;">edit</span></button>
                                <form method="post" onsubmit="return confirm('Bu kontenjan kaydını silmek istediğinize emin misiniz?');" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="quota_delete">
                                    <input type="hidden" name="id" value="<?= (int) $q['id'] ?>">
                                    <button type="submit" class="btn-icon danger" title="Sil" style="border:none; background:transparent; cursor:pointer; color:var(--danger); padding:0; display:flex; align-items:center; justify-content:center; width:24px; height:24px;"><span class="ms" style="font-size:18px;">delete</span></button>
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

<!-- Kontenjan Tanımlama / Düzenleme Modalı -->
<div id="quotaModal" class="user-modal-overlay">
    <div class="user-modal-card" style="max-width: 440px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0; font-size:16px; font-weight:700;">Yeni Kontenjan Tanımla</h3>
            <button type="button" onclick="closeQuotaModal()" style="border:none; background:transparent; cursor:pointer; color:var(--text-2); display:flex; align-items:center; padding:0; width:24px; height:24px;"><span class="ms" style="font-size:20px;">close</span></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="modalAction" value="quota_add">
            <input type="hidden" name="id" id="modalQuotaId" value="0">
            
            <div style="display:flex; flex-direction:column; gap:12px; text-align:left;">
                <label style="font-weight:700; font-size:11px; text-transform:uppercase; color:var(--text-2); display:block;">Daire Başkanlığı / Birim Adı
                    <input type="text" name="department_name" id="modalDeptName" required placeholder="örn. Bilgi İşlem Daire Başkanlığı" style="width:100%; margin-top:6px; padding:10px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text); box-sizing:border-box;">
                </label>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px;">
                    <label style="font-weight:700; font-size:11px; text-transform:uppercase; color:var(--text-2); display:block;">Lise
                        <input type="number" name="lise_quota" id="modalLiseQ" min="0" required value="0" style="width:100%; margin-top:6px; padding:10px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text); box-sizing:border-box;">
                    </label>
                    <label style="font-weight:700; font-size:11px; text-transform:uppercase; color:var(--text-2); display:block;">Önlisans
                        <input type="number" name="onlisans_quota" id="modalOnlisansQ" min="0" required value="0" style="width:100%; margin-top:6px; padding:10px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text); box-sizing:border-box;">
                    </label>
                    <label style="font-weight:700; font-size:11px; text-transform:uppercase; color:var(--text-2); display:block;">Lisans
                        <input type="number" name="lisans_quota" id="modalLisansQ" min="0" required value="0" style="width:100%; margin-top:6px; padding:10px; border-radius:8px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text); box-sizing:border-box;">
                    </label>
                </div>
            </div>
            
            <div style="margin-top:20px; display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn btn-light btn-sm" onclick="closeQuotaModal()">Vazgeç</button>
                <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<script>
function openQuotaModal(id, name, lise, onlisans, lisans) {
    document.getElementById('modalQuotaId').value = id;
    document.getElementById('modalDeptName').value = name;
    document.getElementById('modalLiseQ').value = lise;
    document.getElementById('modalOnlisansQ').value = onlisans;
    document.getElementById('modalLisansQ').value = lisans;
    
    if (id > 0) {
        document.getElementById('modalTitle').textContent = 'Kontenjan Düzenle';
        document.getElementById('modalAction').value = 'quota_edit';
    } else {
        document.getElementById('modalTitle').textContent = 'Yeni Kontenjan Tanımla';
        document.getElementById('modalAction').value = 'quota_add';
    }
    
    document.getElementById('quotaModal').classList.add('open');
}

function closeQuotaModal() {
    document.getElementById('quotaModal').classList.remove('open');
}

// Close modal when clicking background overlay
document.getElementById('quotaModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeQuotaModal();
    }
});

function filterQuotas() {
    var input = document.getElementById("quotaSearch");
    var filter = input.value.toUpperCase();
    var table = document.getElementById("quotasTable");
    if (!table) return;
    
    var rows = table.getElementsByClassName("quota-row");
    for (var i = 0; i < rows.length; i++) {
        var cell = rows[i].getElementsByClassName("dept-name-cell")[0];
        if (cell) {
            var text = cell.textContent || cell.innerText;
            if (text.toUpperCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    }
}
</script>
<?php render_footer(); ?>
