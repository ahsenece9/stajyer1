<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/* ---- İstatistikler ---- */
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

$isWeekday = (int) date('N') <= 5;
$todayQuery = "SELECT
        SUM(a.status = 'devamsiz') AS dev,
        SUM(a.status = 'izinli')   AS izin
     FROM attendance a
     JOIN interns i ON i.id = a.intern_id
     WHERE a.work_date = CURDATE()
       AND i.start_date <= CURDATE() AND i.end_date >= CURDATE()";
if (is_mentor()) {
    $todayQuery .= " AND i.mentor_id = " . (int)$_SESSION['user_id'];
}
$today = db()->query($todayQuery)->fetch();

$devToday  = (int) ($today['dev'] ?? 0);
$izinToday = (int) ($today['izin'] ?? 0);
$aktif     = (int) ($counts['aktif'] ?? 0);
$geldiToday = $isWeekday ? max(0, $aktif - $devToday - $izinToday) : 0;

/* ---- Son eklenen stajyerler ---- */
$recentQuery = 'SELECT * FROM interns';
if (is_mentor()) {
    $recentQuery .= ' WHERE mentor_id = ' . (int)$_SESSION['user_id'];
}
$recentQuery .= ' ORDER BY created_at DESC, id DESC LIMIT 5';
$recent = db()->query($recentQuery)->fetchAll();

/* ---- Yaklaşan bitişler (aktif stajlar) ---- */
$endingQuery = "SELECT *, DATEDIFF(end_date, CURDATE()) AS kalan
     FROM interns
     WHERE start_date <= CURDATE() AND end_date >= CURDATE()";
if (is_mentor()) {
    $endingQuery .= " AND mentor_id = " . (int)$_SESSION['user_id'];
}
$endingQuery .= " ORDER BY end_date ASC LIMIT 5";
$ending = db()->query($endingQuery)->fetchAll();

render_header('Dashboard', 'dashboard');

$firstName = trim(explode(' ', (string) $_SESSION['user_name'])[0] ?? '');
?>
<div class="page-head">
    <div>
        <h1>Hoş Geldiniz<?= $firstName !== '' ? ', ' . e($firstName) : '' ?></h1>
        <p class="page-sub">Sistemdeki güncel durumu ve stajyerlerinizi buradan takip edebilirsiniz.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div>
            <div class="stat-label">Toplam Stajyer</div>
            <div class="stat-value c-primary"><?= (int) $counts['toplam'] ?></div>
            <div class="stat-note">Tüm kayıtlar</div>
        </div>
        <span class="stat-icon i-primary"><span class="ms">group</span></span>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Aktif Stajyerler</div>
            <div class="stat-value"><?= $aktif ?></div>
            <div class="stat-note">Stajı devam edenler</div>
        </div>
        <span class="stat-icon i-success"><span class="ms">check_circle</span></span>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Bugün Gelenler</div>
            <div class="stat-value"><?= $isWeekday ? $geldiToday : '—' ?></div>
            <div class="stat-note"><?= $isWeekday ? 'İşaretlenmeyenler geldi sayılır' : 'Bugün hafta sonu' ?></div>
        </div>
        <span class="stat-icon i-primary"><span class="ms">login</span></span>
    </div>
    <div class="stat-card">
        <div>
            <div class="stat-label">Bugün Devamsız</div>
            <div class="stat-value c-danger"><?= $isWeekday ? $devToday : '—' ?></div>
            <div class="stat-note"><?= $izinToday > 0 && $isWeekday ? '+ ' . $izinToday . ' izinli' : 'İzinsiz gelmeyenler' ?></div>
        </div>
        <span class="stat-icon i-danger"><span class="ms">event_busy</span></span>
    </div>
</div>

<div class="charts-row align-start">
    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 class="card-title">Son Eklenen Stajyerler</h3>
            <a href="index.php" class="btn btn-light btn-sm">Tümünü Gör <span class="ms sm">arrow_forward</span></a>
        </div>
        <?php if (!$recent): ?>
            <div class="empty-state"><p>Henüz stajyer kaydı yok.</p>
                <a href="form.php" class="btn btn-primary">İlk stajyeri ekle</a></div>
        <?php else: ?>
            <div style="overflow-x:auto;">
            <table>
                <thead><tr><th>Stajyer</th><th>Bölüm</th><th>Dönem</th><th>Durum</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $i): [$cls, $label] = intern_status($i); ?>
                    <tr>
                        <td>
                            <a class="row-link" href="view.php?id=<?= (int) $i['id'] ?>" style="display:flex;align-items:center;gap:10px;">
                                <?php if ($i['photo']): ?>
                                    <img class="avatar" src="uploads/<?= e($i['photo']) ?>" alt="">
                                <?php else: ?>
                                    <span class="avatar avatar-empty"><?= e(mb_strtoupper(mb_substr($i['first_name'], 0, 1) . mb_substr($i['last_name'], 0, 1), 'UTF-8')) ?></span>
                                <?php endif; ?>
                                <span><b><?= e($i['first_name'] . ' ' . $i['last_name']) ?></b><br>
                                <span class="row-sub"><?= e($i['phone']) ?></span></span>
                            </a>
                        </td>
                        <td><?= e($i['department']) ?></td>
                        <td><?= format_date($i['start_date']) ?> – <?= format_date($i['end_date']) ?><br>
                            <span class="row-sub"><?= intern_days($i) ?> gün</span></td>
                        <td><span class="badge badge-<?= $cls ?>"><?= $label ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-bottom:0;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 class="card-title">Yaklaşan Bitişler</h3>
            <span class="ms" style="color:var(--warning);">notification_important</span>
        </div>
        <?php if (!$ending): ?>
            <p class="muted" style="margin:0;">Şu anda aktif staj yok.</p>
        <?php else: ?>
            <?php foreach ($ending as $i2): $pct = intern_progress($i2); $kalan = (int) $i2['kalan']; ?>
                <a href="view.php?id=<?= (int) $i2['id'] ?>"
                   style="display:flex; align-items:center; gap:14px; padding:10px 8px; border-radius:12px; text-decoration:none; color:inherit;"
                   onmouseover="this.style.background='var(--hover)'" onmouseout="this.style.background=''">
                    <?php if ($i2['photo']): ?>
                        <img class="avatar" src="uploads/<?= e($i2['photo']) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar avatar-empty"><?= e(mb_strtoupper(mb_substr($i2['first_name'], 0, 1) . mb_substr($i2['last_name'], 0, 1), 'UTF-8')) ?></span>
                    <?php endif; ?>
                    <span style="flex:1; min-width:0;">
                        <b style="font-size:13.5px;"><?= e($i2['first_name'] . ' ' . $i2['last_name']) ?></b><br>
                        <span class="row-sub"><?= $kalan === 0 ? 'Bugün bitiyor' : $kalan . ' gün kaldı' ?></span>
                    </span>
                    <span class="progress"><i style="width:<?= $pct ?>%;<?= $kalan <= 7 ? 'background:var(--warning);' : '' ?>"></i></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
