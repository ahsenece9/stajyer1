<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/* ---- Genel sayılar ---- */
$countsQuery = "SELECT
    COUNT(*) AS toplam,
    SUM(start_date <= CURDATE() AND end_date >= CURDATE()) AS aktif,
    SUM(end_date < CURDATE()) AS bitti,
    SUM(start_date > CURDATE()) AS baslamadi
 FROM interns";
if (is_mentor()) {
    $countsQuery .= " WHERE mentor_id = " . (int)$_SESSION['user_id'];
}
$counts = db()->query($countsQuery)->fetch();
$toplam = (int) $counts['toplam'];

$attTotalsQuery = "SELECT SUM(a.status='devamsiz') AS dev, SUM(a.status='izinli') AS izin FROM attendance a";
if (is_mentor()) {
    $attTotalsQuery .= " JOIN interns i ON i.id = a.intern_id WHERE i.mentor_id = " . (int)$_SESSION['user_id'];
}
$attTotals = db()->query($attTotalsQuery)->fetch();

/* ---- Bölüm bazlı dağılım (en kalabalık 6 bölüm) ---- */
$deptsQuery = 'SELECT department, COUNT(*) AS c FROM interns';
if (is_mentor()) {
    $deptsQuery .= ' WHERE mentor_id = ' . (int)$_SESSION['user_id'];
}
$deptsQuery .= ' GROUP BY department ORDER BY c DESC, department ASC LIMIT 6';
$depts = db()->query($deptsQuery)->fetchAll();

$maxDept = 0;
foreach ($depts as $d) {
    $maxDept = max($maxDept, (int) $d['c']);
}

/* ---- Durum dağılımı (halka grafik) ---- */
$statusData = [
    ['Aktif',      (int) $counts['aktif'],     'var(--success)'],
    ['Stajı Bitti', (int) $counts['bitti'],     'var(--neutral)'],
    ['Başlamadı',  (int) $counts['baslamadi'], 'var(--warning)'],
];
$r = 80;
$circ = 2 * M_PI * $r;
$gap = $toplam > 0 ? 2.5 : 0; // segmentler arası boşluk (derece cinsinden değil, uzunluk)
$segments = [];
$offset = 0.0;
foreach ($statusData as [$name, $val, $color]) {
    if ($toplam > 0 && $val > 0) {
        $len = $val / $toplam * $circ;
        $segments[] = ['len' => max(0, $len - $gap), 'off' => $offset, 'color' => $color];
        $offset += $len;
    }
}

/* ---- Stajyer bazlı devam özeti ---- */
$attByIntern = [];
$attByInternQuery = "SELECT a.intern_id, SUM(a.status='devamsiz') AS dev, SUM(a.status='izinli') AS izin FROM attendance a";
if (is_mentor()) {
    $attByInternQuery .= " JOIN interns i ON i.id = a.intern_id WHERE i.mentor_id = " . (int)$_SESSION['user_id'];
}
$attByInternQuery .= " GROUP BY a.intern_id";
foreach (db()->query($attByInternQuery)->fetchAll() as $row) {
    $attByIntern[(int) $row['intern_id']] = $row;
}

$internsQuery = 'SELECT * FROM interns WHERE start_date <= CURDATE()';
if (is_mentor()) {
    $internsQuery .= ' AND mentor_id = ' . (int)$_SESSION['user_id'];
}
$internsQuery .= ' ORDER BY start_date DESC LIMIT 25';
$interns = db()->query($internsQuery)->fetchAll();

render_header('Raporlar', 'reports');
?>
<div class="page-head">
    <div>
        <h1>Raporlar &amp; Analizler</h1>
        <p class="page-sub">Stajyer dağılımları ve devam durumu özetleri.</p>
    </div>
    <div class="page-actions">
        <button type="button" class="btn btn-light" onclick="window.print()"><span class="ms sm">print</span> Yazdır</button>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Toplam Stajyer</div>
            <div class="stat-value c-primary"><?= $toplam ?></div>
        </div>
        <span class="stat-icon i-primary"><span class="ms">groups</span></span>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Aktif Staj</div>
            <div class="stat-value"><?= (int) $counts['aktif'] ?></div>
        </div>
        <span class="stat-icon i-success"><span class="ms">task_alt</span></span>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Toplam Devamsızlık</div>
            <div class="stat-value c-danger"><?= (int) ($attTotals['dev'] ?? 0) ?></div>
            <div class="stat-note">İşaretlenen günler</div>
        </div>
        <span class="stat-icon i-danger"><span class="ms">event_busy</span></span>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Toplam İzin</div>
            <div class="stat-value"><?= (int) ($attTotals['izin'] ?? 0) ?></div>
            <div class="stat-note">İşaretlenen günler</div>
        </div>
        <span class="stat-icon i-warning"><span class="ms">beach_access</span></span>
    </div>
</div>

<div class="charts-row align-start" style="margin-bottom:20px;">
    <div class="card" style="margin-bottom:0;">
        <h3 class="card-title">Bölüm Bazlı Stajyer Dağılımı</h3>
        <p class="muted" style="margin:0 0 10px; font-size:13px;">En kalabalık <?= count($depts) ?> bölüm</p>
        <?php if (!$depts): ?>
            <p class="muted">Henüz veri yok.</p>
        <?php else: ?>
            <div class="chart-bars">
                <?php foreach ($depts as $d): $h = $maxDept > 0 ? (int) round((int) $d['c'] * 100 / $maxDept) : 0; ?>
                    <div class="chart-col" title="<?= e($d['department']) ?>: <?= (int) $d['c'] ?> stajyer">
                        <div class="bar-area"><div class="bar" style="height:<?= max(4, $h) ?>%;"></div></div>
                        <span class="bar-val"><?= (int) $d['c'] ?></span>
                        <span class="bar-label"><?= e($d['department']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card donut-wrap" style="margin-bottom:0;">
        <h3 class="card-title" style="align-self:flex-start;">Staj Durum Dağılımı</h3>
        <div class="donut-box">
            <svg width="200" height="200" viewBox="0 0 200 200" role="img" aria-label="Staj durum dağılımı">
                <circle cx="100" cy="100" r="<?= $r ?>" fill="none" stroke="var(--neutral-bg)" stroke-width="18"></circle>
                <?php foreach ($segments as $s): ?>
                    <circle cx="100" cy="100" r="<?= $r ?>" fill="none"
                            stroke="<?= $s['color'] ?>" stroke-width="18" stroke-linecap="butt"
                            stroke-dasharray="<?= round($s['len'], 2) ?> <?= round($circ - $s['len'], 2) ?>"
                            stroke-dashoffset="<?= round(-$s['off'], 2) ?>"></circle>
                <?php endforeach; ?>
            </svg>
            <div class="donut-center"><b><?= $toplam ?></b><span>Toplam</span></div>
        </div>
        <div class="donut-legend">
            <?php foreach ($statusData as [$name, $val, $color]): ?>
                <div class="row">
                    <span class="name"><span class="dot" style="background:<?= $color ?>;"></span><?= e($name) ?></span>
                    <b><?= $val ?></b>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="table-wrap">
    <div style="padding:20px 20px 0;">
        <h3 class="card-title">Devam Durumu Özeti</h3>
        <p class="muted" style="margin:4px 0 12px; font-size:13px;">Başlamış stajlar için iş günü bazında devam oranı (izin günleri orana dahil edilmez).</p>
    </div>
    <table>
        <thead>
        <tr>
            <th>Stajyer</th>
            <th>Bölüm</th>
            <th>Geçen İş Günü</th>
            <th>Geldi</th>
            <th>Devamsız</th>
            <th>İzinli</th>
            <th>Devam Oranı</th>
            <th>Durum</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$interns): ?>
            <tr><td colspan="8" class="muted">Başlamış staj kaydı yok.</td></tr>
        <?php endif; ?>
        <?php foreach ($interns as $i):
            [$cls, $label] = intern_status($i);
            $past = intern_past_workdays($i);
            $att  = $attByIntern[(int) $i['id']] ?? ['dev' => 0, 'izin' => 0];
            $dev  = (int) $att['dev'];
            $izin = (int) $att['izin'];
            $geldi = max(0, $past - $dev - $izin);
            $baz   = max(0, $past - $izin); // izinli günler orana dahil değil
            $oran  = $baz > 0 ? (int) round($geldi * 100 / $baz) : 100;
            $oranColor = $oran >= 90 ? 'var(--success)' : ($oran >= 70 ? 'var(--warning)' : 'var(--danger)');
        ?>
            <tr>
                <td><a class="row-link" href="view.php?id=<?= (int) $i['id'] ?>"><b><?= e($i['first_name'] . ' ' . $i['last_name']) ?></b></a></td>
                <td><?= e($i['department']) ?></td>
                <td><?= $past ?></td>
                <td style="color:var(--success); font-weight:700;"><?= $geldi ?></td>
                <td style="color:<?= $dev > 0 ? 'var(--danger)' : 'var(--muted)' ?>; font-weight:700;"><?= $dev ?></td>
                <td style="color:<?= $izin > 0 ? 'var(--warning)' : 'var(--muted)' ?>; font-weight:700;"><?= $izin ?></td>
                <td>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="progress"><i style="width:<?= $oran ?>%; background:<?= $oranColor ?>;"></i></span>
                        <b style="font-size:13px;">%<?= $oran ?></b>
                    </div>
                </td>
                <td><span class="badge badge-<?= $cls ?>"><?= $label ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer(); ?>
