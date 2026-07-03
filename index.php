<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$durum  = $_GET['durum'] ?? 'hepsi';
$q      = trim((string) ($_GET['q'] ?? ''));
$seviye = (string) ($_GET['seviye'] ?? '');
if ($seviye !== '' && !isset(LEVELS[$seviye])) {
    $seviye = '';
}

$where  = [];
$params = [];

if ($seviye !== '') {
    $where[] = 'level = ?';
    $params[] = $seviye;
}

switch ($durum) {
    case 'aktif':
        $where[] = 'start_date <= CURDATE() AND end_date >= CURDATE()';
        break;
    case 'bitti':
        $where[] = 'end_date < CURDATE()';
        break;
    case 'baslamadi':
        $where[] = 'start_date > CURDATE()';
        break;
    default:
        $durum = 'hepsi';
}

if ($q !== '') {
    $where[] = "(CONCAT(first_name, ' ', last_name) LIKE ? OR department LIKE ? OR school LIKE ? OR phone LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}

if (is_mentor()) {
    $where[] = 'mentor_id = ?';
    $params[] = $_SESSION['user_id'];
}

$sql = 'SELECT * FROM interns';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY start_date DESC, last_name ASC';

$upgradeNeeded = false;
try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $interns = $stmt->fetchAll();
} catch (PDOException) {
    // level sütunu yoksa (guncelleme.php çalıştırılmamışsa) filtresiz devam et
    $upgradeNeeded = true;
    $seviye = '';
    $stmt = db()->query('SELECT * FROM interns ORDER BY start_date DESC, last_name ASC');
    $interns = $stmt->fetchAll();
}

// Sekme sayaçları
$countsQuery = "SELECT
    COUNT(*) AS hepsi,
    SUM(start_date <= CURDATE() AND end_date >= CURDATE()) AS aktif,
    SUM(end_date < CURDATE()) AS bitti,
    SUM(start_date > CURDATE()) AS baslamadi
 FROM interns";
if (is_mentor()) {
    $countsQuery .= " WHERE mentor_id = " . (int)$_SESSION['user_id'];
}
$counts = db()->query($countsQuery)->fetch();

$tabs = [
    'hepsi'     => 'Tümü',
    'aktif'     => 'Aktif Stajyerler',
    'bitti'     => 'Geçmiş Stajyerler',
    'baslamadi' => 'Başlamayanlar',
];
$qs = ($q !== '' ? '&q=' . urlencode($q) : '') . ($seviye !== '' ? '&seviye=' . urlencode($seviye) : '');

render_header('Stajyerler', 'index');
if ($upgradeNeeded) {
    echo '<div class="alert alert-error">Okul ve seviye özellikleri için veritabanı güncellemesi gerekli:
          tarayıcıdan <a href="guncelleme.php" class="row-link" style="color:inherit;text-decoration:underline;"><b>guncelleme.php</b></a> sayfasını bir kez açın.</div>';
}
?>
<div class="page-head">
    <div>
        <h1>Stajyer Listesi</h1>
        <p class="page-sub">Sistemdeki tüm aktif ve geçmiş stajyerlerin takibi ve yönetimi.</p>
    </div>
    <div class="page-actions">
        <?php if (!is_mentor()): ?>
            <a href="form.php" class="btn btn-primary"><span class="ms sm">add</span> Yeni Stajyer Ekle</a>
        <?php endif; ?>
    </div>
</div>

<div class="tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="index.php?durum=<?= $key ?><?= $qs ?>"
           class="tab<?= $durum === $key ? ' active' : '' ?>">
            <?= e($label) ?> <span class="count"><?= (int) ($counts[$key] ?? 0) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="card" style="display:flex; flex-wrap:wrap; align-items:center; gap:12px; padding:14px 18px;">
    <span class="section-label" style="display:inline-flex; align-items:center; gap:6px;">
        <span class="ms sm">filter_list</span> Filtrele:
    </span>
    <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; flex:1;">
        <input type="hidden" name="durum" value="<?= e($durum) ?>">
        <select name="seviye" onchange="this.form.submit()" style="margin:0; width:auto; min-width:200px;">
            <option value="">Tüm Seviyeler</option>
            <?php foreach (LEVELS as $key => $lbl): ?>
                <option value="<?= $key ?>" <?= $seviye === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="liveSearch" name="q" autocomplete="off"
               placeholder="Ad, okul, bölüm veya telefon ara…" value="<?= e($q) ?>"
               style="margin:0; flex:1; min-width:220px; max-width:420px;">
        <?php if ($q !== '' || $seviye !== ''): ?>
            <a href="index.php?durum=<?= e($durum) ?>" class="btn btn-light btn-sm">Temizle</a>
        <?php endif; ?>
    </form>
</div>

<?php if (!$interns): ?>
    <div class="empty-state">
        <p>Kayıt bulunamadı.</p>
        <a href="form.php" class="btn btn-primary">İlk stajyeri ekle</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Stajyer</th>
            <th>Okul / Bölüm</th>
            <th>Seviye</th>
            <th>Telefon</th>
            <th>Staj Dönemi</th>
            <th>Durum</th>
            <th style="text-align:right;">İşlemler</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($interns as $i): [$cls, $label] = intern_status($i); $pct = intern_progress($i); ?>
        <tr data-search="<?= e($i['first_name'] . ' ' . $i['last_name'] . ' ' . ($i['school'] ?? '') . ' ' . $i['department'] . ' ' . $i['phone']) ?>">
            <td>
                <a class="row-link" href="view.php?id=<?= (int) $i['id'] ?>" style="display:flex;align-items:center;gap:12px;">
                    <?php if ($i['photo']): ?>
                        <img class="avatar" src="uploads/<?= e($i['photo']) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar avatar-empty"><?= e(mb_strtoupper(mb_substr($i['first_name'], 0, 1) . mb_substr($i['last_name'], 0, 1), 'UTF-8')) ?></span>
                    <?php endif; ?>
                    <b><?= e($i['first_name'] . ' ' . $i['last_name']) ?></b>
                </a>
            </td>
            <td>
                <b style="font-size:13.5px;"><?= ($i['school'] ?? '') !== '' ? e($i['school']) : '<span class="muted">—</span>' ?></b><br>
                <span class="row-sub"><?= e($i['department']) ?></span>
            </td>
            <td><?= e(LEVELS[$i['level'] ?? 'lisans'] ?? '—') ?></td>
            <td><?= e($i['phone']) ?></td>
            <td>
                <?= format_date($i['start_date']) ?> – <?= format_date($i['end_date']) ?>
                <span class="row-sub"> · <?= intern_days($i) ?> gün</span>
                <div class="progress" style="margin-top:7px;"><i class="<?= $pct >= 100 ? 'done' : '' ?>" style="width:<?= $pct ?>%;"></i></div>
            </td>
            <td><span class="badge badge-<?= $cls ?>"><?= $label ?></span></td>
            <td class="actions">
                <a href="view.php?id=<?= (int) $i['id'] ?>" class="btn-icon" title="Görüntüle"><span class="ms">visibility</span></a>
                <a href="form.php?id=<?= (int) $i['id'] ?>" class="btn-icon" title="Düzenle"><span class="ms">edit</span></a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p id="noMatch" class="muted" style="display:none; padding:16px 4px;">Eşleşen stajyer yok.</p>
<?php endif; ?>

<div class="stats-grid" style="margin-top:8px;">
    <div class="stat-card" style="border-left:4px solid var(--primary);">
        <div>
            <div class="stat-label">Aktif Stajyerler</div>
            <div class="stat-value"><?= (int) ($counts['aktif'] ?? 0) ?></div>
        </div>
        <span class="stat-icon i-primary"><span class="ms">check_circle</span></span>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--warning);">
        <div>
            <div class="stat-label">Bekleyen (Başlamayan)</div>
            <div class="stat-value"><?= (int) ($counts['baslamadi'] ?? 0) ?></div>
        </div>
        <span class="stat-icon i-warning"><span class="ms">schedule</span></span>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--success);">
        <div>
            <div class="stat-label">Geçmiş Stajyerler</div>
            <div class="stat-value"><?= (int) ($counts['bitti'] ?? 0) ?></div>
        </div>
        <span class="stat-icon i-success"><span class="ms">task_alt</span></span>
    </div>
    <div class="stat-card" style="border-left:4px solid var(--neutral);">
        <div>
            <div class="stat-label">Toplam Kayıt</div>
            <div class="stat-value"><?= (int) ($counts['hepsi'] ?? 0) ?></div>
        </div>
        <span class="stat-icon i-primary"><span class="ms">groups</span></span>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('liveSearch');
    if (!input) return;
    var rows  = document.querySelectorAll('tbody tr[data-search]');
    var empty = document.getElementById('noMatch');

    function filter() {
        var q = input.value.trim().toLocaleLowerCase('tr');
        var visible = 0;
        rows.forEach(function (row) {
            var hay = row.getAttribute('data-search').toLocaleLowerCase('tr');
            var match = hay.indexOf(q) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (empty) empty.style.display = (visible === 0) ? '' : 'none';
    }

    input.addEventListener('input', filter);
    // Enter'a basınca sayfa yenilenmesin, canlı filtre yeter
    input.form.addEventListener('submit', function (ev) { ev.preventDefault(); });
})();
</script>
<?php render_footer(); ?>
