<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Yetkili Ata POST İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_mentor') {
    csrf_check();
    $internId = (int) ($_POST['intern_id'] ?? 0);
    $mentorId = (int) ($_POST['mentor_id'] ?? 0);
    
    $intern = db()->prepare("SELECT * FROM interns WHERE id = ?");
    $intern->execute([$internId]);
    $internData = $intern->fetch();
    
    if ($internData) {
        $stmt = db()->prepare("UPDATE interns SET mentor_id = ? WHERE id = ?");
        $stmt->execute([$mentorId !== 0 ? $mentorId : null, $internId]);
        
        if ($mentorId !== 0) {
            $mentorQuery = db()->prepare("SELECT full_name FROM users WHERE id = ?");
            $mentorQuery->execute([$mentorId]);
            $mentorName = $mentorQuery->fetchColumn() ?: 'Bilinmeyen';
            log_action('yetkili_ata', $internData['first_name'] . ' ' . $internData['last_name'] . ' -> ' . $mentorName);
            flash_set('success', $internData['first_name'] . ' ' . $internData['last_name'] . ' stajyeri ' . $mentorName . ' yetkilisine atandı.');
        } else {
            log_action('yetkili_ata', $internData['first_name'] . ' ' . $internData['last_name'] . ' -> Yetkili Kaldırıldı');
            flash_set('success', $internData['first_name'] . ' ' . $internData['last_name'] . ' stajyerinin yetkili ataması kaldırıldı.');
        }
    }
    redirect('index.php');
}

// Yetkilileri çek ve haritala
$mentors = db()->query("SELECT id, full_name, photo, role, department FROM users ORDER BY full_name ASC")->fetchAll();
$mentorsMap = [];
foreach ($mentors as $m) {
    $mentorsMap[(int)$m['id']] = [
        'name' => $m['full_name'],
        'photo' => $m['photo'],
        'role' => $m['role'],
        'department' => $m['department']
    ];
}

$durum  = $_GET['durum'] ?? 'hepsi';
$q      = trim((string) ($_GET['q'] ?? ''));
$seviye = (string) ($_GET['seviye'] ?? '');
if ($seviye !== '' && !isset(LEVELS[$seviye])) {
    $seviye = '';
}
$birim  = trim((string) ($_GET['birim'] ?? ''));

// Birimleri veritabanından çek
$allBirimler = db()->query("SELECT DISTINCT assigned_department FROM interns WHERE assigned_department IS NOT NULL AND assigned_department != '' ORDER BY assigned_department ASC")->fetchAll(PDO::FETCH_COLUMN);

$where  = [];
$params = [];

if ($seviye !== '') {
    $where[] = 'level = ?';
    $params[] = $seviye;
}

if (!is_mentor() && $birim !== '') {
    $where[] = 'assigned_department = ?';
    $params[] = $birim;
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
    $where[] = "(CONCAT(first_name, ' ', last_name) LIKE ? OR department LIKE ? OR school LIKE ? OR phone LIKE ? OR assigned_department LIKE ?)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

if (is_mentor()) {
    $where[] = 'assigned_department = ?';
    $params[] = $_SESSION['user_department'] ?? '';
}

// Toplam stajyer sayısı
$countSql = 'SELECT COUNT(*) FROM interns';
if ($where) {
    $countSql .= ' WHERE ' . implode(' AND ', $where);
}
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$totalInterns = (int) $countStmt->fetchColumn();

$perPage = 10;
$totalPages = (int) ceil($totalInterns / $perPage);
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

$sql = 'SELECT * FROM interns';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY start_date DESC, last_name ASC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

$upgradeNeeded = false;
try {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $interns = $stmt->fetchAll();
} catch (PDOException) {
    // level sütunu yoksa (guncelleme.php çalıştırılmamışsa) filtresiz devam et
    $upgradeNeeded = true;
    $seviye = '';
    $sql = 'SELECT * FROM interns ORDER BY start_date DESC, last_name ASC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    $stmt = db()->query($sql);
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
    $countsQuery .= " WHERE assigned_department = " . db()->quote($_SESSION['user_department'] ?? '');
}
$counts = db()->query($countsQuery)->fetch();

$tabs = [
    'hepsi'     => 'Tümü',
    'aktif'     => 'Aktif Stajyerler',
    'bitti'     => 'Geçmiş Stajyerler',
    'baslamadi' => 'Başlamayanlar',
];
$qs = ($q !== '' ? '&q=' . urlencode($q) : '') . ($seviye !== '' ? '&seviye=' . urlencode($seviye) : '') . ($birim !== '' ? '&birim=' . urlencode($birim) : '');

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
</div>

<div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; flex-wrap: wrap;">
    <!-- Left: Tabs -->
    <div class="tabs" style="margin: 0; display: inline-flex; flex-wrap: wrap;">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="index.php?durum=<?= $key ?><?= $qs ?>"
               class="tab<?= $durum === $key ? ' active' : '' ?>">
                <?= e($label) ?> <span class="count"><?= (int) ($counts[$key] ?? 0) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Right: Stat Cards (Horizontal Row) -->
    <div class="mini-stats-row" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <!-- Aktif Stajyerler -->
        <div style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; box-shadow: var(--shadow-sm);">
            <span class="ms sm" style="font-size: 16px; color: var(--primary);"><?= svg_icon('check_circle') ?></span>
            <span style="font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase;">Aktif: <b style="font-size: 13px; color: var(--text);"><?= (int) ($counts['aktif'] ?? 0) ?></b></span>
        </div>
        <!-- Bekleyen (Başlamayan) -->
        <div style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; box-shadow: var(--shadow-sm);">
            <span class="ms sm" style="font-size: 16px; color: var(--warning);"><?= svg_icon('schedule') ?></span>
            <span style="font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase;">Bekleyen: <b style="font-size: 13px; color: var(--text);"><?= (int) ($counts['baslamadi'] ?? 0) ?></b></span>
        </div>
        <!-- Geçmiş Stajyerler -->
        <div style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; box-shadow: var(--shadow-sm);">
            <span class="ms sm" style="font-size: 16px; color: var(--success);"><?= svg_icon('task_alt') ?></span>
            <span style="font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase;">Geçmiş: <b style="font-size: 13px; color: var(--text);"><?= (int) ($counts['bitti'] ?? 0) ?></b></span>
        </div>
        <!-- Toplam Kayıt -->
        <div style="display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 12px; box-shadow: var(--shadow-sm);">
            <span class="ms sm" style="font-size: 16px; color: var(--muted);"><?= svg_icon('groups') ?></span>
            <span style="font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase;">Toplam: <b style="font-size: 13px; color: var(--text);"><?= (int) ($counts['hepsi'] ?? 0) ?></b></span>
        </div>
    </div>
</div>

<div class="card" style="display:flex; flex-wrap:wrap; align-items:center; gap:12px; padding:14px 18px;">
    <span class="section-label" style="display:inline-flex; align-items:center; gap:6px;">
        <span class="ms sm"><?= svg_icon('filter_list') ?></span> Filtrele:
    </span>
    <form method="get" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; flex:1;">
        <input type="hidden" name="durum" value="<?= e($durum) ?>">
        <select name="seviye" onchange="this.form.submit()" style="margin:0; width:auto; min-width:160px;">
            <option value="">Tüm Seviyeler</option>
            <?php foreach (LEVELS as $key => $lbl): ?>
                <option value="<?= $key ?>" <?= $seviye === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!is_mentor()): ?>
        <select name="birim" onchange="this.form.submit()" style="margin:0; width:auto; min-width:180px;">
            <option value="">Tüm Birimler</option>
            <?php foreach ($allBirimler as $bName): ?>
                <option value="<?= e($bName) ?>" <?= $birim === $bName ? 'selected' : '' ?>><?= e($bName) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <input type="text" id="liveSearch" name="q" autocomplete="off"
               placeholder="Ad, okul, bölüm veya telefon ara…" value="<?= e($q) ?>"
               style="margin:0; flex:1; min-width:220px; max-width:420px;">
        <?php if ($q !== '' || $seviye !== ''): ?>
            <a href="index.php?durum=<?= e($durum) ?>" class="btn btn-light btn-sm">Temizle</a>
        <?php endif; ?>
    </form>
    
    <?php if (!is_mentor()): ?>
        <a href="form.php" class="btn btn-primary" style="margin-left: auto; white-space: nowrap;"><span class="ms sm"><?= svg_icon('add') ?></span> Yeni Stajyer Ekle</a>
    <?php endif; ?>
</div>

<?php if (!$interns): ?>
    <div class="empty-state">
        <p>Kayıt bulunamadı.</p>
        <a href="form.php" class="btn btn-primary">İlk stajyeri ekle</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
    <table id="intern-table">
        <thead>
        <tr>
            <th style="text-align: center;">Stajyer</th>
            <th style="text-align: center;">Seviye</th>
            <th style="text-align: center;">Birim</th>
            <th style="text-align: center;">Gün</th>
            <th style="text-align: center;">Staj Dönemi</th>
            <th style="text-align: center;">Yetkili Kişi</th>
            <th style="text-align: center;">Durum</th>
            <th style="text-align: center;">İşlemler</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($interns as $i): 
            [$cls, $label] = intern_status($i); 
            $pct = intern_progress($i); 
            
            // Get mentor info from map
            $mId = (int) $i['mentor_id'];
            $mentorName = isset($mentorsMap[$mId]) ? $mentorsMap[$mId]['name'] : '';
        ?>
        <tr data-search="<?= e($i['first_name'] . ' ' . $i['last_name'] . ' ' . ($i['school'] ?? '') . ' ' . $i['department'] . ' ' . $i['phone'] . ' ' . $mentorName) ?>">
            <td style="text-align: center;">
                <a class="row-link" href="view.php?id=<?= (int) $i['id'] ?>" style="display:inline-flex;align-items:center;gap:12px;text-align:left;">
                    <?php if ($i['photo']): ?>
                        <img class="avatar" src="uploads/<?= e($i['photo']) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar avatar-empty"><?= e(mb_strtoupper(mb_substr($i['first_name'], 0, 1) . mb_substr($i['last_name'], 0, 1), 'UTF-8')) ?></span>
                    <?php endif; ?>
                    <span><?= e($i['first_name'] . ' ' . $i['last_name']) ?></span>
                </a>
            </td>
            <td style="text-align: center;"><?= e(LEVELS[$i['level'] ?? 'lisans'] ?? '—') ?></td>
            <td style="text-align: center;">
                <span style="font-size:13px; font-weight:700;"><?= !empty($i['assigned_department']) ? e($i['assigned_department']) : '<span class="muted">—</span>' ?></span>
            </td>
            <td style="text-align: center; font-weight: 700;"><?= intern_days($i) ?> Gün</td>
            <td style="text-align: center;">
                <?= format_date($i['start_date']) ?> – <?= format_date($i['end_date']) ?>
                <div class="progress" style="margin-top:7px; margin-left:auto; margin-right:auto;"><i class="<?= $pct >= 100 ? 'done' : '' ?>" style="width:<?= $pct ?>%;"></i></div>
            </td>
            <td style="text-align: center;">
                <div style="display: inline-flex; align-items: center; gap: 6px; justify-content: center;">
                    <?php if ($mentorName !== ''): ?>
                        <span class="ms sm" style="font-size: 16px; color: var(--primary);"><?= svg_icon('person') ?></span>
                        <?= e($mentorName) ?>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </div>
            </td>
            <td style="text-align: center;"><span class="badge badge-<?= $cls ?> inline-block"><?= $label ?></span></td>
            <td class="actions" style="text-align: center;">
                <a href="view.php?id=<?= (int) $i['id'] ?>" class="btn-icon" title="Görüntüle"><span class="ms"><?= svg_icon('visibility') ?></span></a>
                <a href="form.php?id=<?= (int) $i['id'] ?>" class="btn-icon" title="Düzenle"><span class="ms"><?= svg_icon('edit') ?></span></a>
                <a href="javascript:void(0)" onclick="openAssignModal(<?= (int)$i['id'] ?>, <?= (int)$i['mentor_id'] ?>, '<?= e($i['assigned_department'] ?? '') ?>')" class="btn-icon" title="Yetkili Ata" style="color: var(--primary);"><span class="ms"><?= svg_icon('person_add') ?></span></a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination Summary / Table Footer -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; margin-bottom: 24px; padding: 0 4px;">
        <p class="text-xs text-[var(--text-2)] m-0" style="margin: 0; font-size: 12px; color: var(--text-2);">Toplam <?= $totalInterns ?> kayıttan <?= $offset + 1 ?>-<?= min($totalInterns, $offset + $perPage) ?> arası gösteriliyor.</p>
        <div style="display: flex; gap: 6px; align-items: center;">
            <?php 
            $queryData = $_GET;
            
            // Previous
            $queryData['page'] = $page - 1;
            $prevUrl = 'index.php?' . http_build_query($queryData);
            $prevDisabled = ($page <= 1);
            
            // Next
            $queryData['page'] = $page + 1;
            $nextUrl = 'index.php?' . http_build_query($queryData);
            $nextDisabled = ($page >= $totalPages);
            ?>
            
            <?php if ($prevDisabled): ?>
                <button type="button" class="btn btn-light btn-sm flex items-center justify-center p-1" disabled style="opacity:0.5; cursor:not-allowed; display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px;">
                    <span class="ms text-[16px]"><?= svg_icon('chevron_left') ?></span>
                </button>
            <?php else: ?>
                <a href="<?= e($prevUrl) ?>" class="btn btn-light btn-sm flex items-center justify-center p-1 logs-pagination-btn" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px;">
                    <span class="ms text-[16px]"><?= svg_icon('chevron_left') ?></span>
                </a>
            <?php endif; ?>
            
            <span class="px-2 py-1 text-xs font-bold text-[var(--text)] border border-[var(--card-border)] rounded bg-[var(--card-bg)]" style="font-size:11px; padding:4px 8px; border-radius:6px; border:1px solid var(--card-border); background:var(--card-bg);">
                <?= $page ?> / <?= $totalPages ?>
            </span>
            
            <?php if ($nextDisabled): ?>
                <button type="button" class="btn btn-light btn-sm flex items-center justify-center p-1" disabled style="opacity:0.5; cursor:not-allowed; display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px;">
                    <span class="ms text-[16px]"><?= svg_icon('chevron_right') ?></span>
                </button>
            <?php else: ?>
                <a href="<?= e($nextUrl) ?>" class="btn btn-light btn-sm flex items-center justify-center p-1 logs-pagination-btn" style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px;">
                    <span class="ms text-[16px]"><?= svg_icon('chevron_right') ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>



<!-- Yetkili Ata Modalı (Popup) -->
<div class="user-modal-overlay" id="assignModalOverlay">
    <div class="user-modal-card" style="max-width: 440px;">
        <div class="flex items-center justify-between mb-5" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 class="text-base font-bold text-[var(--text)] m-0" style="margin:0; font-size:16px;">Yetkili Ata</h3>
            <button type="button" onclick="closeAssignModal()" class="text-[var(--text-2)] hover:text-[var(--text)] border-none bg-transparent cursor-pointer flex items-center p-0" style="border:none; background:transparent; cursor:pointer; display:flex; align-items:center; padding:0; color:var(--text-2);">
                <span class="ms text-[20px]" style="font-size:20px;">close</span>
            </button>
        </div>
        
        <p class="text-xs text-[var(--text-2)] mb-4" style="margin-top:0; margin-bottom:16px; font-size:12px; color:var(--text-2);">Lütfen stajyerin atanacağı yetkili kişiyi seçin:</p>
        
        <!-- Yetkili Listesi -->
        <div class="space-y-2" style="max-height:300px; overflow-y:auto; padding-right:4px; display:flex; flex-direction:column; gap:8px;">
            <!-- Atamayı Kaldır -->
            <div onclick="selectMentor(0)" class="flex items-center gap-3 p-2.5 rounded-xl cursor-pointer transition-all" style="display:flex; align-items:center; gap:12px; padding:10px; border-radius:12px; cursor:pointer; border:1.5px dashed rgba(217, 45, 32, 0.45);" onmouseover="this.style.borderColor='rgba(217,45,32,0.85)'; this.style.background='var(--danger-bg)';" onmouseout="this.style.borderColor='rgba(217,45,32,0.45)'; this.style.background='transparent';">
                <div class="w-9 h-9 bg-rose-100 dark:bg-rose-950 text-rose-600 rounded-lg flex items-center justify-center" style="width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; background:rgba(239, 68, 68, 0.1); color:#dc2626; flex:none;">
                    <span class="ms text-lg">delete</span>
                </div>
                <div style="flex:1;">
                    <p class="text-xs font-bold text-rose-600 m-0" style="margin:0; font-size:12px; font-weight:700; color:#dc2626;">Atamayı Kaldır</p>
                    <p class="text-[10px] text-rose-500 m-0 mt-0.5" style="margin:2px 0 0 0; font-size:10px; opacity:0.8; color:#dc2626;">Stajyerin yetkili atamasını temizler</p>
                </div>
            </div>
            
            <?php 
            $roleLabels = [
                'sistem_yoneticisi' => 'Sistem Yöneticisi',
                'kurum_staj_sorumlusu' => 'Staj Sorumlusu (İK)',
                'birim_sorumlusu' => 'Birim Sorumlusu',
            ];
            foreach ($mentors as $m): 
                $initials = mb_strtoupper(mb_substr($m['full_name'], 0, 2, 'UTF-8'), 'UTF-8');
                $label = $roleLabels[$m['role']] ?? $m['role'];
            ?>
            <div onclick="selectMentor(<?= (int)$m['id'] ?>)" class="mentor-item-row" data-mentor-id="<?= (int)$m['id'] ?>" data-role="<?= e($m['role']) ?>" data-department="<?= e($m['department'] ?? '') ?>" style="display:flex; align-items:center; gap:12px; padding:10px; border-radius:12px; cursor:pointer; border: 1px solid var(--card-border); transition:all 0.2s;">
                <?php if (!empty($m['photo'])): ?>
                    <img src="uploads/<?= e($m['photo']) ?>" style="width:36px; height:36px; object-fit:cover; border-radius:50%; margin:0; flex:none;">
                <?php else: ?>
                    <div class="w-9 h-9 bg-primary/10 text-primary rounded-full flex items-center justify-center font-bold text-xs" style="width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:rgba(99, 102, 241, 0.1); color:var(--primary); font-weight:700; font-size:12px; flex:none;">
                        <?= e($initials) ?>
                    </div>
                <?php endif; ?>
                <div style="flex:1;">
                    <p class="text-xs font-bold text-[var(--text)] m-0" style="margin:0; font-size:12px; font-weight:700; color:var(--text);"><?= e($m['full_name']) ?></p>
                    <p class="text-[10px] text-[var(--text-2)] m-0 mt-0.5" style="margin:2px 0 0 0; font-size:10px; color:var(--text-2);"><?= e($label) ?><?= !empty($m['department']) ? ' · ' . e($m['department']) : '' ?></p>
                </div>
                <span class="ms text-primary text-sm opacity-0 check-icon" style="color:var(--primary); font-size:16px; margin-left:auto; transition:opacity 0.2s;">check_circle</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<form method="post" id="assignForm" action="index.php" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="assign_mentor">
    <input type="hidden" name="intern_id" id="assignInternId">
    <input type="hidden" name="mentor_id" id="assignMentorId">
</form>

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
        visibility: hidden;  /* kapalıyken tıklamayı yakalamasın (donma önlemi) */
        transition: opacity 0.25s ease, visibility 0.25s ease;
    }
    .user-modal-overlay.open {
        opacity: 1;
        pointer-events: auto;
        visibility: visible;
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

    /* Table styling overrides to prevent horizontal scrollbar */
    #intern-table th, #intern-table td {
        padding: 8px 10px !important; 
        font-size: 12px !important;    
        white-space: normal !important; 
    }
    #intern-table thead th {
        font-size: 11px !important;
        white-space: nowrap !important; 
    }
    #intern-table .avatar {
        width: 32px !important;  
        height: 32px !important;
    }
    #intern-table .progress {
        width: 90px !important;  
    }
    .table-wrap {
        overflow-x: hidden !important; 
    }
</style>

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

function openAssignModal(internId, currentMentorId, assignedDept) {
    document.getElementById('assignInternId').value = internId;
    
    // Highlight and filter mentors by department
    document.querySelectorAll('.mentor-item-row').forEach(function(row) {
        var mId = parseInt(row.getAttribute('data-mentor-id'), 10);
        var mDept = row.getAttribute('data-department') || '';
        var mRole = row.getAttribute('data-role') || '';
        
        // System admin and general HR coordinator can manage any intern, or if the intern has no department assigned, or if they match
        var isSystemAdmin = mRole === 'sistem_yoneticisi';
        var isStajSorumlusu = mRole === 'kurum_staj_sorumlusu';
        var isMatch = isSystemAdmin || isStajSorumlusu || !assignedDept || (mDept.toLowerCase().trim() === assignedDept.toLowerCase().trim());
        
        if (isMatch) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
        
        var checkIcon = row.querySelector('.check-icon');
        if (mId === currentMentorId) {
            row.style.borderColor = 'var(--primary)';
            row.style.background = 'var(--hover)';
            if (checkIcon) checkIcon.style.opacity = '1';
        } else {
            // Boş bırakılırsa border-color currentColor'a (koyu metin = siyah) düşüyordu; temaya uygun rengi açıkça ver
            row.style.borderColor = 'var(--card-border)';
            row.style.background = 'transparent';
            if (checkIcon) checkIcon.style.opacity = '0';
        }
    });
    
    document.getElementById('assignModalOverlay').classList.add('open');
}

function closeAssignModal() {
    document.getElementById('assignModalOverlay').classList.remove('open');
}

// Close modal when clicking overlay background
document.getElementById('assignModalOverlay').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAssignModal();
    }
});

function selectMentor(mentorId) {
    document.getElementById('assignMentorId').value = mentorId;
    // İşlem sonrası aynı kaydırma konumuna dönebilmek için sakla (programatik submit event tetiklemez)
    try { sessionStorage.setItem("scrollRestore:" + location.pathname, String(window.scrollY || 0)); } catch (e) {}
    document.getElementById('assignForm').submit();
}

// Restore and save scroll position on pagination
document.addEventListener("DOMContentLoaded", function() {
    var scrollPos = localStorage.getItem('interns_scroll_pos');
    if (scrollPos !== null) {
        window.scrollTo(0, parseInt(scrollPos, 10));
        localStorage.removeItem('interns_scroll_pos');
    }
    
    document.querySelectorAll('.logs-pagination-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            localStorage.setItem('interns_scroll_pos', window.scrollY);
        });
    });
});
</script>
<?php render_footer(); ?>
