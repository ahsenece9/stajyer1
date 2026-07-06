<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = db()->prepare('SELECT * FROM interns WHERE id = ?');
$stmt->execute([$id]);
$intern = $stmt->fetch();

if (!$intern) {
    flash_set('error', 'Stajyer bulunamadı.');
    redirect('index.php');
}

$fullName = $intern['first_name'] . ' ' . $intern['last_name'];

// Fetch all evaluations (tasks)
$s = db()->prepare('SELECT * FROM evaluations WHERE intern_id = ? ORDER BY week_start DESC');
$s->execute([$id]);
$evals = $s->fetchAll();

$weeks = intern_weeks($intern);
$weekLabels = array_column($weeks, 'label', 'start');

render_header($fullName . ' — Görev Geçmişi', 'index');
?>
<div class="page-head">
    <div>
        <p class="page-sub" style="margin:0 0 4px;">
            <a href="index.php" class="row-link muted">Stajyer Listesi</a>
            <span class="ms sm" style="font-size:15px;">chevron_right</span>
            <a href="view.php?id=<?= $id ?>" class="row-link muted"><?= e($fullName) ?></a>
            <span class="ms sm" style="font-size:15px;">chevron_right</span>
            Görev Geçmişi
        </p>
        <h1>Görev Geçmişi</h1>
    </div>
    <div class="page-actions">
        <a href="view.php?id=<?= $id ?>" class="btn btn-light"><span class="ms sm">arrow_back</span> Profil Sayfasına Dön</a>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
        <span class="ms" style="color:var(--primary);">calendar_today</span> Tüm Haftalık Görevler (<?= count($evals) ?>)
    </h3>
    <?php if (!$evals): ?>
        <p class="muted" style="margin:0;">Henüz haftalık görev girilmemiş.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="eval-table" style="width:100%; table-layout:fixed; border-collapse:collapse;">
                <colgroup>
                    <col style="width: 180px;"> <!-- Hafta -->
                    <col style="width: 450px;"> <!-- Görev -->
                    <col style="width: 150px;"> <!-- Ekleyen -->
                    <col style="width: 120px;"> <!-- Tarih -->
                </colgroup>
                <thead>
                    <tr style="background: var(--hover);">
                        <th style="padding: 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; border-bottom: 1px solid var(--line-soft);">Hafta</th>
                        <th style="padding: 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; border-bottom: 1px solid var(--line-soft);">Görev</th>
                        <th style="padding: 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; border-bottom: 1px solid var(--line-soft);">Yetkili</th>
                        <th style="padding: 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; border-bottom: 1px solid var(--line-soft);">Kayıt Tarihi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evals as $ev): ?>
                        <tr style="border-bottom: 1px solid var(--line-soft);">
                            <td style="padding: 12px; font-size: 13px; vertical-align: middle;">
                                <span style="font-weight:600; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">
                                    <span class="ms sm" style="font-size:15px; color:var(--primary);">calendar_today</span>
                                    <?= e($weekLabels[$ev['week_start']] ?? format_date($ev['week_start'])) ?>
                                </span>
                            </td>
                            <td style="padding: 12px; font-size: 13.5px; vertical-align: middle; word-wrap: break-word !important; word-break: break-all !important; white-space: normal !important; line-height:1.5;">
                                <?= (string) $ev['task'] !== '' ? e($ev['task']) : '<span class="muted">—</span>' ?>
                            </td>
                            <td style="padding: 12px; font-size: 13px; vertical-align: middle; font-weight:600;">
                                <?= e($ev['user_name']) ?>
                            </td>
                            <td style="padding: 12px; font-size: 12px; vertical-align: middle; color: var(--text-2);">
                                <?= e(date('d.m.Y', strtotime($ev['updated_at']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
