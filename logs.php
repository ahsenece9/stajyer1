<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_role(['sistem_yoneticisi', 'kurum_staj_sorumlusu']);

/* Zaman aralığı seçenekleri: anahtar => [etiket, SQL aralığı] */
$ranges = [
    ''    => ['Tüm Zamanlar', null],
    '1s'  => ['Son 1 Saat',   'INTERVAL 1 HOUR'],
    '24s' => ['Son 24 Saat',  'INTERVAL 24 HOUR'],
    '5g'  => ['Son 5 Gün',    'INTERVAL 5 DAY'],
    '30g' => ['Son 30 Gün',   'INTERVAL 30 DAY'],
];

$islem  = (string) ($_GET['islem'] ?? '');
$aralik = (string) ($_GET['aralik'] ?? '');
if ($islem !== '' && !isset(LOG_ACTIONS[$islem])) {
    $islem = '';
}
if (!isset($ranges[$aralik])) {
    $aralik = '';
}

$tableMissing = false;

/* Logları temizle */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    csrf_check();
    if (!is_admin()) {
        flash_set('error', 'Sadece sistem yöneticileri logları temizleyebilir.');
        redirect('logs.php');
    }
    try {
        $count = (int) db()->query('SELECT COUNT(*) FROM logs')->fetchColumn();
        db()->exec('DELETE FROM logs');
        log_action('log_temizle', $count . ' kayıt silindi');
        flash_set('success', 'Tüm log kayıtları temizlendi (' . $count . ' kayıt).');
    } catch (PDOException) {
        flash_set('error', 'Log tablosu bulunamadı.');
    }
    redirect('logs.php');
}

$logs = [];
$totalLogs = 0;
$perPage = 10;
$page = 1;
$totalPages = 1;
$offset = 0;

try {
    $where  = [];
    $params = [];
    if ($islem !== '') {
        $where[] = 'action = ?';
        $params[] = $islem;
    }
    if ($aralik !== '' && $ranges[$aralik][1] !== null) {
        $where[] = 'created_at >= NOW() - ' . $ranges[$aralik][1];
    }
    
    // Toplam log sayısı
    $countSql = 'SELECT COUNT(*) FROM logs' . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    $countStmt = db()->prepare($countSql);
    $countStmt->execute($params);
    $totalLogs = (int) $countStmt->fetchColumn();
    
    $totalPages = (int) ceil($totalLogs / $perPage);
    if ($totalPages < 1) {
        $totalPages = 1;
    }
    
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    
    $sql = 'SELECT * FROM logs'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY id DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Fetch users info for photos and roles
    $usersQuery = db()->query("SELECT id, photo, role FROM users")->fetchAll();
    $userPhotos = [];
    $userRoles = [];
    foreach ($usersQuery as $u) {
        $userPhotos[(int)$u['id']] = $u['photo'];
        $userRoles[(int)$u['id']] = $u['role'];
    }
    
    // Fetch interns info for details matching
    $allInterns = db()->query("SELECT id, first_name, last_name FROM interns")->fetchAll();
} catch (PDOException) {
    $tableMissing = true;
}

render_header('Log Sistemi', 'logs');
?>
<div class="page-head">
    <div>
        <p class="page-sub" style="margin:0 0 4px; display:flex; align-items:center; gap:6px; color:var(--primary); font-weight:700; text-transform:uppercase; font-size:11px; letter-spacing:.1em;">
            <span class="ms sm">security</span> Sistem Güvenliği
        </p>
        <h1>Log Sistemi</h1>
        <p class="page-sub">Tüm giriş denemeleri ve kullanıcı işlemleri burada kayıt altına alınır.</p>
    </div>
    <?php if (!$tableMissing && is_admin()): ?>
    <div class="page-actions">
        <form method="post" onsubmit="return confirm('TÜM log kayıtları kalıcı olarak silinecek. Emin misiniz?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn-danger"><span class="ms sm">delete</span> Logları Temizle</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if ($tableMissing): ?>
    <div class="alert alert-error">
        Log tablosu henüz oluşturulmamış. Tarayıcıdan <code>guncelleme.php</code> sayfasını bir kez açmanız yeterli —
        eksik tablolar otomatik kurulur. (Sistemin geri kalanı bu tablo olmadan da çalışır.)
    </div>
<?php else: ?>

<div class="card" style="display:flex; flex-wrap:wrap; align-items:center; gap:12px; padding:14px 18px;">
    <span class="section-label" style="display:inline-flex; align-items:center; gap:6px;">
        <span class="ms sm">filter_list</span> Filtrele:
    </span>
    <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <select name="aralik" onchange="this.form.submit()" style="margin:0; width:auto; min-width:160px;">
            <?php foreach ($ranges as $key => [$lbl, $int]): ?>
                <option value="<?= $key ?>" <?= $aralik === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="islem" onchange="this.form.submit()" style="margin:0; width:auto; min-width:210px;">
            <option value="">Tüm İşlemler</option>
            <?php foreach (LOG_ACTIONS as $key => [$lbl, $c]): ?>
                <option value="<?= $key ?>" <?= $islem === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($islem !== '' || $aralik !== ''): ?>
            <a href="logs.php" class="btn btn-light btn-sm">Temizle</a>
        <?php endif; ?>
    </form>
    <span class="muted" style="margin-left:auto; font-size:12.5px;">Toplam <?= $totalLogs ?> kayıt bulundu</span>
</div>

<?php if (!$logs): ?>
    <div class="empty-state"><p>Bu filtreye uyan log kaydı yok.</p></div>
<?php else: ?>
    <div id="log-section" class="table-wrap" style="margin-bottom: 0; border-bottom: none; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
    <table>
        <thead>
        <tr>
            <th style="text-align: center;">Tarih &amp; Saat</th>
            <th style="text-align: center;">Gerçekleştiren</th>
            <th style="text-align: center;">İşlem</th>
            <th style="text-align: center;">Detay</th>
            <th style="text-align: center;">IP Adresi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log):
            [$lbl, $badge] = LOG_ACTIONS[$log['action']] ?? [$log['action'], 'info'];
            
            // Match intern dynamically from detail text
            $matchedInternId = null;
            $matchedInternName = null;
            if ($log['detail'] !== '') {
                foreach ($allInterns as $intern) {
                    $fullName = $intern['first_name'] . ' ' . $intern['last_name'];
                    if (strpos($log['detail'], $fullName) === 0) {
                        $matchedInternId = $intern['id'];
                        $matchedInternName = $fullName;
                        break;
                    }
                }
            }
            
            $detailHtml = e($log['detail']);
            if ($matchedInternId !== null) {
                $linkHtml = '<a href="view.php?id=' . (int)$matchedInternId . '" class="hover:underline font-semibold" style="text-decoration:none; color:inherit;">' . e($matchedInternName) . '</a>';
                $detailHtml = str_replace(e($matchedInternName), $linkHtml, $detailHtml);
            }
        ?>
            <tr>
                <td style="text-align: center;"><?= e(date('d.m.Y H:i:s', strtotime($log['created_at']))) ?></td>
                <td style="text-align: center;">
                    <?php if (!empty($log['user_id']) && isset($userRoles[(int)$log['user_id']])): ?>
                        <a href="user_detail.php?id=<?= (int)$log['user_id'] ?>" style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none; color: inherit;" class="hover:underline">
                            <?php if (!empty($userPhotos[(int)$log['user_id']])): ?>
                                <img src="uploads/<?= e($userPhotos[(int)$log['user_id']]) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:50%;margin:0;">
                            <?php else: ?>
                                <span class="avatar avatar-empty" style="width:32px;height:32px;font-size:12px;margin:0;">
                                    <?= e(mb_strtoupper(mb_substr($log['user_name'], 0, 1), 'UTF-8')) ?>
                                </span>
                            <?php endif; ?>
                            <b><?= e($log['user_name']) ?></b>
                        </a>
                    <?php else: ?>
                        <div style="display: inline-flex; align-items: center; gap: 10px;">
                            <span class="avatar avatar-empty" style="width:32px;height:32px;font-size:12px;margin:0;">
                                <?= e(mb_strtoupper(mb_substr($log['user_name'], 0, 1), 'UTF-8')) ?>
                            </span>
                            <b><?= e($log['user_name']) ?></b>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="text-align: center;"><span class="badge badge-<?= $badge ?>"><?= e($lbl) ?></span></td>
                <td style="text-align: center; white-space:normal; max-width:380px;"><?= $log['detail'] !== '' ? $detailHtml : '<span class="muted">—</span>' ?></td>
                <td class="muted" style="text-align: center; font-family:monospace; font-size:13px;"><?= e($log['ip']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination Summary / Table Footer -->
    <div class="px-5 py-3 flex justify-between items-center bg-[var(--hover)]/10 border border-[var(--card-border)] border-t-0" style="border-radius: 0 0 12px 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <p class="text-xs text-[var(--text-2)] m-0">Toplam <?= $totalLogs ?> kayıttan <?= $offset + 1 ?>-<?= min($totalLogs, $offset + $perPage) ?> arası gösteriliyor.</p>
        <div class="flex gap-1" style="display:flex; gap:4px; align-items:center;">
            <?php 
            $queryData = $_GET;
            
            // Previous
            $queryData['page'] = $page - 1;
            $prevUrl = 'logs.php?' . http_build_query($queryData);
            $prevDisabled = ($page <= 1);
            
            // Next
            $queryData['page'] = $page + 1;
            $nextUrl = 'logs.php?' . http_build_query($queryData);
            $nextDisabled = ($page >= $totalPages);
            ?>
            
            <?php if ($prevDisabled): ?>
                <button type="button" class="btn btn-light btn-sm flex items-center justify-center p-1" disabled style="opacity:0.5; cursor:not-allowed;">
                    <span class="ms text-[16px]">chevron_left</span>
                </button>
            <?php else: ?>
                <a href="<?= e($prevUrl) ?>" class="btn btn-light btn-sm flex items-center justify-center p-1 logs-pagination-btn">
                    <span class="ms text-[16px]">chevron_left</span>
                </a>
            <?php endif; ?>
            
            <span class="px-3 py-1 text-xs font-bold text-[var(--text)] border border-[var(--card-border)] rounded bg-[var(--card-bg)]">
                <?= $page ?> / <?= $totalPages ?>
            </span>
            
            <?php if ($nextDisabled): ?>
                <button type="button" class="btn btn-light btn-sm flex items-center justify-center p-1" disabled style="opacity:0.5; cursor:not-allowed;">
                    <span class="ms text-[16px]">chevron_right</span>
                </button>
            <?php else: ?>
                <a href="<?= e($nextUrl) ?>" class="btn btn-light btn-sm flex items-center justify-center p-1 logs-pagination-btn">
                    <span class="ms text-[16px]">chevron_right</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Restore scroll position
    var scrollPos = localStorage.getItem('logs_scroll_pos');
    if (scrollPos !== null) {
        window.scrollTo(0, parseInt(scrollPos, 10));
        localStorage.removeItem('logs_scroll_pos');
    }
    
    // Save scroll position on pagination link click
    document.querySelectorAll('.logs-pagination-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            localStorage.setItem('logs_scroll_pos', window.scrollY);
        });
    });
});
</script>
<?php render_footer(); ?>
