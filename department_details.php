<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$deptName = trim((string) ($_GET['name'] ?? ''));
$periodId = isset($_GET['period_id']) ? (int) $_GET['period_id'] : 0;

// Dönem bilgilerini çek
$stmt = db()->prepare('SELECT * FROM internship_periods WHERE id = ?');
$stmt->execute([$periodId]);
$period = $stmt->fetch();

if (!$period || $deptName === '') {
    flash_set('error', 'Geçersiz parametreler.');
    redirect('quotas.php');
}

// Birim yetkililerini çek (maksimum 5 adet listelenecek, fixed height ile)
$stmt = db()->prepare('
    SELECT id, username, full_name, email, role, phone, photo 
    FROM users 
    WHERE department = ? 
    ORDER BY full_name ASC
');
$stmt->execute([$deptName]);
$users = $stmt->fetchAll();

// Birimdeki stajyerleri çek (maksimum 10 adet listelenecek, fixed height ile)
$stmt = db()->prepare('
    SELECT * 
    FROM interns 
    WHERE assigned_department = ? 
      AND start_date >= ? AND end_date <= ?
    ORDER BY start_date DESC, last_name ASC
');
$stmt->execute([$deptName, $period['start_date'], $period['end_date']]);
$interns = $stmt->fetchAll();

render_header($deptName . ' Detayları', 'quotas');
?>
<div class="page-head">
    <div style="display:flex; align-items:center; gap:12px;">
        <a href="period_quotas.php?period_id=<?= (int)$periodId ?>" class="btn btn-light btn-sm flex items-center justify-center" style="padding:8px; border-radius:50%; width:36px; height:36px; text-decoration:none;" title="Geri Dön">
            <span class="ms text-lg" style="margin:0;">arrow_back</span>
        </a>
        <div>
            <h1><?= e($deptName) ?></h1>
            <p class="page-sub"><?= e($period['name']) ?> Planı (<?= e(date('d.m.Y', strtotime($period['start_date']))) ?> – <?= e(date('d.m.Y', strtotime($period['end_date']))) ?>)</p>
        </div>
    </div>
</div>

<style>
    .fixed-table-container {
        overflow-y: auto;
        border: 1px solid var(--card-border);
        border-radius: 12px;
        background: var(--card-bg);
        box-shadow: var(--shadow-sm);
        margin-bottom: 30px;
    }
    .fixed-table-container table {
        margin: 0;
        border: none;
        width: 100%;
        border-collapse: collapse;
    }
    .fixed-table-container thead th {
        position: sticky;
        top: 0;
        background: var(--hover);
        z-index: 10;
        box-shadow: inset 0 -1px 0 var(--card-border);
        padding: 12px;
        font-size: 11px;
        text-transform: uppercase;
        font-family: 'JetBrains Mono', monospace;
        color: var(--text-2);
        font-weight: 700;
    }
    .fixed-table-container tbody td {
        padding: 12px;
        border-bottom: 1px solid var(--card-border);
        font-size: 13px;
        color: var(--text);
    }
    .fixed-table-container tbody tr:last-child td {
        border-bottom: none;
    }
    .fixed-table-container tbody tr:hover {
        background: var(--hover);
    }
    
    /* Scrollbar Styling */
    .fixed-table-container::-webkit-scrollbar {
        width: 6px;
    }
    .fixed-table-container::-webkit-scrollbar-track {
        background: transparent;
    }
    .fixed-table-container::-webkit-scrollbar-thumb {
        background-color: var(--card-border);
        border-radius: 3px;
    }
</style>

<!-- Birim Yetkilileri Kartı (Maksimum 5 Satır Yükseklik) -->
<div class="card" style="margin-bottom: 24px;">
    <h2 style="margin-top:0; margin-bottom:14px; font-size:16px; display:flex; align-items:center; gap:8px;">
        <span class="ms" style="font-size:20px; color:var(--primary);">manage_accounts</span> Birim Yetkilileri
    </h2>
    
    <?php if (!$users): ?>
        <p class="muted">Bu birime atanmış yetkili personel bulunmamaktadır.</p>
    <?php else: ?>
        <!-- Max 5 satır sığacak şekilde fixed height container (yaklaşık 5 * 60px = 300px) -->
        <div class="fixed-table-container" style="max-height: 290px;">
            <table>
                <thead>
                    <tr>
                        <th style="text-align: left; width: 60px;">Fotoğraf</th>
                        <th style="text-align: left;">Ad Soyad</th>
                        <th style="text-align: center; width: 150px;">Rol</th>
                        <th style="text-align: left;">E-posta</th>
                        <th style="text-align: right; width: 120px;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $roleLabels = [
                        'sistem_yoneticisi' => 'Sistem Yöneticisi',
                        'kurum_staj_sorumlusu' => 'Staj Sorumlusu (İK)',
                        'birim_sorumlusu' => 'Birim Sorumlusu',
                    ];
                    foreach ($users as $u): 
                        $label = $roleLabels[$u['role']] ?? $u['role'];
                        $badgeClass = $u['role'] === 'sistem_yoneticisi' ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-secondary-container text-on-secondary-container border border-outline-variant/30';
                        $initials = mb_strtoupper(mb_substr($u['full_name'], 0, 2, 'UTF-8'), 'UTF-8');
                    ?>
                        <tr style="cursor: pointer;" onclick="window.location.href='user_detail.php?id=<?= (int)$u['id'] ?>'">
                            <td>
                                <?php if (!empty($u['photo'])): ?>
                                    <img src="uploads/<?= e($u['photo']) ?>" style="width:32px; height:32px; object-fit:cover; border-radius:8px;">
                                <?php else: ?>
                                    <div style="width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(99, 102, 241, 0.1); color:var(--primary); font-weight:700; font-size:11px;">
                                        <?= e($initials) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><b><?= e($u['full_name']) ?></b></td>
                            <td style="text-align: center;">
                                <span class="badge" style="padding:4px 8px; border-radius:20px; font-size:11px; font-weight:600; background:rgba(53, 37, 205, 0.08); color:var(--primary); border:1px solid rgba(53, 37, 205, 0.15);">
                                    <?= e($label) ?>
                                </span>
                            </td>
                            <td><?= e($u['email']) ?></td>
                            <td style="text-align: right;">
                                <a href="user_detail.php?id=<?= (int)$u['id'] ?>" class="btn btn-light btn-xs">Profil Gör</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Birim Stajyerleri Kartı (Maksimum 10 Satır Yükseklik) -->
<div class="card">
    <h2 style="margin-top:0; margin-bottom:14px; font-size:16px; display:flex; align-items:center; gap:8px;">
        <span class="ms" style="font-size:20px; color:var(--primary);">groups</span> Birimdeki Stajyerler
    </h2>
    
    <?php if (!$interns): ?>
        <p class="muted">Bu birime bu dönem atanmış stajyer bulunmamaktadır.</p>
    <?php else: ?>
        <!-- Max 10 satır sığacak şekilde fixed height container (yaklaşık 10 * 60px = 600px) -->
        <div class="fixed-table-container" style="max-height: 520px;">
            <table>
                <thead>
                    <tr>
                        <th style="text-align: left; width: 60px;">Fotoğraf</th>
                        <th style="text-align: left;">Ad Soyad</th>
                        <th style="text-align: center; width: 120px;">Seviye</th>
                        <th style="text-align: center; width: 100px;">Staj Türü</th>
                        <th style="text-align: center; width: 90px;">Gün</th>
                        <th style="text-align: center; width: 200px;">Staj Dönemi</th>
                        <th style="text-align: center; width: 100px;">Durum</th>
                        <th style="text-align: right; width: 80px;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($interns as $i): 
                        [$cls, $label] = intern_status($i); 
                        $pct = intern_progress($i); 
                        $initials = mb_strtoupper(mb_substr($i['first_name'], 0, 1, 'UTF-8') . mb_substr($i['last_name'], 0, 1, 'UTF-8'), 'UTF-8');
                    ?>
                        <tr style="cursor: pointer;" onclick="window.location.href='view.php?id=<?= (int)$i['id'] ?>'">
                            <td>
                                <?php if ($i['photo']): ?>
                                    <img src="uploads/<?= e($i['photo']) ?>" style="width:32px; height:32px; object-fit:cover; border-radius:8px;">
                                <?php else: ?>
                                    <div style="width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(99, 102, 241, 0.1); color:var(--primary); font-weight:700; font-size:11px;">
                                        <?= e($initials) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><b><?= e($i['first_name'] . ' ' . $i['last_name']) ?></b></td>
                            <td style="text-align: center;"><?= e(LEVELS[$i['level'] ?? 'lisans'] ?? '—') ?></td>
                            <td style="text-align: center;">
                                <span class="badge" style="padding:4px 8px; border-radius:6px; font-size:11px; font-weight:600; background:rgba(99, 102, 241, 0.08); color:var(--primary);">
                                    <?= ($i['type'] ?? 'zorunlu') === 'gonullu' ? 'Gönüllü' : 'Zorunlu' ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 700;"><?= intern_days($i) ?> Gün</td>
                            <td style="text-align: center;">
                                <?= format_date($i['start_date']) ?> – <?= format_date($i['end_date']) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?= $cls ?> inline-block" style="padding:4px 8px; border-radius:20px; font-size:11.5px;"><?= $label ?></span>
                            </td>
                            <td style="text-align: right;">
                                <a href="view.php?id=<?= (int)$i['id'] ?>" class="btn-icon" title="Görüntüle"><span class="ms"><?= svg_icon('visibility') ?></span></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php render_footer(); ?>
