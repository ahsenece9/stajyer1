<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function handle_ajax_response(bool $ok, string $msgOrError) {
    if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
        header('Content-Type: application/json');
        if ($ok) {
            echo json_encode(['ok' => true, 'msg' => $msgOrError]);
        } else {
            echo json_encode(['ok' => false, 'error' => $msgOrError]);
        }
        exit;
    }
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['intern_id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM interns WHERE id = ?');
$stmt->execute([$id]);
$intern = $stmt->fetch();

if (!$intern) {
    flash_set('error', 'Stajyer bulunamadı.');
    redirect('index.php');
}

if (is_mentor() && (int) $intern['mentor_id'] !== (int) $_SESSION['user_id']) {
    flash_set('error', 'Bu stajyerin bilgilerini görüntüleme yetkiniz bulunmamaktadır.');
    redirect('index.php');
}

$fullName = $intern['first_name'] . ' ' . $intern['last_name'];
$upgradeNeeded = false;

/* ---------- Form işlemleri (not / belge / değerlendirme) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'note_add') {
            $text = trim((string) ($_POST['note_text'] ?? ''));
            if ($text === '') {
                handle_ajax_response(false, 'Not boş olamaz.');
                flash_set('error', 'Not boş olamaz.');
            } else {
                db()->prepare('INSERT INTO intern_notes (intern_id, user_name, note_text) VALUES (?, ?, ?)')
                    ->execute([$id, (string) $_SESSION['user_name'], $text]);
                log_action('not_ekle', $fullName);
                handle_ajax_response(true, 'Staj notu eklendi.');
                flash_set('success', 'Staj notu eklendi.');
            }
        }

        if ($action === 'note_delete') {
            db()->prepare('DELETE FROM intern_notes WHERE id = ? AND intern_id = ?')
                ->execute([(int) ($_POST['note_id'] ?? 0), $id]);
            log_action('not_sil', $fullName);
            handle_ajax_response(true, 'Staj notu silindi.');
            flash_set('success', 'Staj notu silindi.');
        }

        if ($action === 'note_edit') {
            $noteId = (int) ($_POST['note_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? 'Genel'));
            $text = trim((string) ($_POST['note_text'] ?? ''));
            if ($text === '') {
                handle_ajax_response(false, 'Not boş olamaz.');
                flash_set('error', 'Not boş olamaz.');
            } else {
                db()->prepare('UPDATE intern_notes SET title = ?, category = ?, note_text = ? WHERE id = ? AND intern_id = ?')
                    ->execute([$title, $category, $text, $noteId, $id]);
                log_action('not_guncelle', $fullName);
                handle_ajax_response(true, 'Staj notu güncellendi.');
                flash_set('success', 'Staj notu güncellendi.');
            }
        }

        if ($action === 'doc_upload') {
            try {
                [$fname, $orig, $size] = handle_document_upload('document');
                db()->prepare('INSERT INTO intern_documents (intern_id, filename, orig_name, filesize) VALUES (?, ?, ?, ?)')
                    ->execute([$id, $fname, $orig, $size]);
                log_action('belge_yukle', $fullName . ' — ' . $orig);
                handle_ajax_response(true, 'Belge yüklendi: ' . $orig);
                flash_set('success', 'Belge yüklendi: ' . $orig);
            } catch (RuntimeException $ex) {
                handle_ajax_response(false, $ex->getMessage());
                flash_set('error', $ex->getMessage());
            }
        }

        if ($action === 'doc_delete') {
            $docStmt = db()->prepare('SELECT * FROM intern_documents WHERE id = ? AND intern_id = ?');
            $docStmt->execute([(int) ($_POST['doc_id'] ?? 0), $id]);
            if ($doc = $docStmt->fetch()) {
                db()->prepare('DELETE FROM intern_documents WHERE id = ?')->execute([(int) $doc['id']]);
                delete_document_file($doc['filename']);
                log_action('belge_sil', $fullName . ' — ' . $doc['orig_name']);
                handle_ajax_response(true, 'Belge silindi.');
                flash_set('success', 'Belge silindi.');
            } else {
                handle_ajax_response(false, 'Belge bulunamadı.');
            }
        }

        if ($action === 'eval_save') {
            $week    = (string) ($_POST['week_start'] ?? '');
            $score   = (int) ($_POST['score'] ?? 0);
            $task    = trim((string) ($_POST['task'] ?? ''));
            $comment = trim((string) ($_POST['comment'] ?? ''));
            $validWeeks = array_column(intern_weeks($intern), 'start');

            if (!in_array($week, $validWeeks, true)) {
                flash_set('error', 'Geçersiz hafta seçimi.');
            } elseif ($score < 1 && $task === '' && $comment === '') {
                flash_set('error', 'Değerlendirme için en az bir alan doldurun.');
            } else {
                db()->prepare(
                    'INSERT INTO evaluations (intern_id, week_start, score, task, comment, user_name)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE score = VALUES(score), task = VALUES(task),
                                             comment = VALUES(comment), user_name = VALUES(user_name)'
                )->execute([$id, $week, $score >= 1 && $score <= 10 ? $score : null, $task, $comment, (string) $_SESSION['user_name']]);
                log_action('degerlendirme', $fullName . ' — ' . format_date($week) . ' haftası');
                flash_set('success', 'Haftalık değerlendirme kaydedildi.');
            }
        }

        if ($action === 'eval_delete') {
            db()->prepare('DELETE FROM evaluations WHERE id = ? AND intern_id = ?')
                ->execute([(int) ($_POST['eval_id'] ?? 0), $id]);
            flash_set('success', 'Değerlendirme silindi.');
        }
    } catch (PDOException) {
        flash_set('error', 'Bu özellik için veritabanı güncellemesi gerekli: tarayıcıdan guncelleme.php sayfasını bir kez açın.');
    }

    redirect('view.php?id=' . $id);
}

[$cls, $label] = intern_status($intern);
$pct = intern_progress($intern);

/* ---------- Yoklama verileri ---------- */
$attMap     = attendance_map((int) $intern['id']);
$attDetails = attendance_details_map((int) $intern['id']);
$calStart = new DateTimeImmutable($intern['start_date']);
$calEnd   = new DateTimeImmutable($intern['end_date']);
$todayD   = new DateTimeImmutable('today');
$todayStr = $todayD->format('Y-m-d');

// Varsayılan aktif ay hesaplama (slayt geçişleri için)
$activeMonthStr = '';
try {
    $tempCursor = $calStart->modify('first day of this month');
    $tempEnd    = $calEnd->modify('first day of this month');
    $monthsList = [];
    $guard      = 0;
    while ($tempCursor <= $tempEnd && $guard++ < 24) {
        $monthsList[] = $tempCursor->format('Y-m');
        $tempCursor   = $tempCursor->modify('+1 month');
    }
    
    $currentMonthStr = date('Y-m');
    if (in_array($currentMonthStr, $monthsList, true)) {
        $activeMonthStr = $currentMonthStr;
    } elseif ($currentMonthStr < ($monthsList[0] ?? '')) {
        $activeMonthStr = $monthsList[0] ?? '';
    } else {
        $activeMonthStr = end($monthsList) ?: '';
    }
} catch (Exception) {
    $activeMonthStr = '';
}

$pastWorkdays = intern_past_workdays($intern);
$cntDev = $cntIzin = $cntRapor = $cntDevPast = $cntIzinPast = $cntRaporPast = 0;
foreach ($attMap as $dt => $st) {
    if ($st === 'devamsiz') { $cntDev++;  if ($dt <= $todayStr) $cntDevPast++; }
    elseif ($st === 'izinli') { $cntIzin++; if ($dt <= $todayStr) $cntIzinPast++; }
    elseif ($st === 'raporlu') { $cntRapor++; if ($dt <= $todayStr) $cntRaporPast++; }
}
$cntGeldi = max(0, $pastWorkdays - $cntDevPast - $cntIzinPast - $cntRaporPast);

$monthNames = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
               'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$dowNames   = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];

/* ---------- Notlar, belgeler, değerlendirmeler ---------- */
$notes = $docs = $evals = [];
try {
    $s = db()->prepare('SELECT * FROM intern_notes WHERE intern_id = ? ORDER BY id DESC');
    $s->execute([$id]);
    $notes = $s->fetchAll();

    $s = db()->prepare('SELECT * FROM intern_documents WHERE intern_id = ? ORDER BY id DESC');
    $s->execute([$id]);
    $docs = $s->fetchAll();

    $s = db()->prepare('SELECT * FROM evaluations WHERE intern_id = ? ORDER BY week_start DESC');
    $s->execute([$id]);
    $evals = $s->fetchAll();
} catch (PDOException) {
    $upgradeNeeded = true;
}

if (isset($_GET['ajax_get_notes'])) {
    ob_start();
    if (!$notes) {
        echo '<p class="muted" style="margin:0;">Henüz staj notu eklenmemiş.</p>';
    } else {
        foreach (array_slice($notes, 0, 4) as $n) {
            $badge = '';
            if ($n['category'] !== 'Genel') {
                $badge = ' <span class="badge badge-info" style="margin-left: 6px; font-size: 10px; font-weight: 700; padding: 2px 6px;">' . e($n['category']) . '</span>';
            }
            $titleStr = '';
            if ($n['title'] !== '') {
                $titleStr = '<strong style="font-size: 14.5px; display: block; margin-bottom: 4px; color: var(--text);">' . e($n['title']) . '</strong>';
            }
            echo '
            <div class="note-item" style="border-bottom:1px solid var(--line-soft); padding-bottom:12px; margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span class="section-label" style="color:var(--primary);">' .
                        e(mb_strtoupper(date('d.m.Y H:i', strtotime($n['created_at'])), 'UTF-8')) . ' · ' . e($n['user_name']) . $badge . '
                    </span>
                    <div style="display:flex; gap:4px;">
                        <button type="button" class="btn-icon" style="width:30px;height:30px;" title="Düzenle"
                                onclick="openEditNote(' . (int)$n['id'] . ', \'' . e($n['title']) . '\', \'' . e($n['category']) . '\', `' . e($n['note_text']) . '`)">
                            <span class="ms sm">edit</span>
                        </button>
                        <form method="post" style="margin:0;" onsubmit="return confirm(\'Bu not silinsin mi?\');">' .
                            csrf_field() . '
                            <input type="hidden" name="action" value="note_delete">
                            <input type="hidden" name="intern_id" value="' . $id . '">
                            <input type="hidden" name="note_id" value="' . (int) $n['id'] . '">
                            <button type="submit" class="btn-icon danger" style="width:30px;height:30px;" title="Sil"><span class="ms sm">delete</span></button>
                        </form>
                    </div>
                </div>
                <div class="note-text-display" style="font-size:14px; color:var(--text); line-height:1.5;">' .
                    $titleStr . nl2br(e($n['note_text'])) . '
                </div>
            </div>';
        }
    }
    $html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'html' => $html,
        'count' => count($notes)
    ]);
    exit;
}

if (isset($_GET['ajax_get_docs'])) {
    ob_start();
    if (!$docs) {
        echo '<p class="muted" style="margin:0;">Henüz belge yüklenmemiş.</p>';
    } else {
        foreach (array_slice($docs, 0, 4) as $d) {
            echo '
            <div class="doc-item">
                <span class="info-icon" style="width:38px;height:38px;"><span class="ms">description</span></span>
                <div style="flex:1; min-width:0;">
                    <b style="font-size:13.5px; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . e($d['orig_name']) . '</b>
                    <span class="row-sub">' . number_format($d['filesize'] / 1024, 0, ',', '.') . ' KB · ' . e(date('d.m.Y', strtotime($d['created_at']))) . '</span>
                </div>
                <a class="btn-icon" href="download.php?id=' . (int) $d['id'] . '" title="İndir"><span class="ms">download</span></a>
                <form method="post" style="margin:0;" onsubmit="return confirm(\'Bu belge kalıcı olarak silinecek. Emin misiniz?\');">' .
                    csrf_field() . '
                    <input type="hidden" name="action" value="doc_delete">
                    <input type="hidden" name="intern_id" value="' . $id . '">
                    <input type="hidden" name="doc_id" value="' . (int) $d['id'] . '">
                    <button type="submit" class="btn-icon danger" title="Sil"><span class="ms">delete</span></button>
                </form>
            </div>';
        }
    }
    $html = ob_get_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'html' => $html,
        'count' => count($docs)
    ]);
    exit;
}

/* Sorumlu yetkili */
$mentorName = null;
if (!empty($intern['mentor_id'])) {
    $s = db()->prepare('SELECT full_name FROM users WHERE id = ?');
    $s->execute([(int) $intern['mentor_id']]);
    $mentorName = $s->fetchColumn() ?: null;
}

$weeks = intern_weeks($intern);
$evalByWeek = [];
foreach ($evals as $ev) {
    $evalByWeek[$ev['week_start']] = $ev;
}
// Varsayılan seçili hafta: bugünü kapsayan hafta, yoksa ilk hafta
$currentWeek = (new DateTimeImmutable('monday this week'))->format('Y-m-d');
$defaultWeek = $weeks ? $weeks[0]['start'] : '';
foreach ($weeks as $w) {
    if ($w['start'] === $currentWeek) {
        $defaultWeek = $currentWeek;
        break;
    }
}

render_header($fullName, 'index');

if ($upgradeNeeded) {
    echo '<div class="alert alert-error">Staj notları, belgeler ve değerlendirme özellikleri için veritabanı güncellemesi gerekli:
          tarayıcıdan <b><a href="guncelleme.php" class="row-link" style="color:inherit;text-decoration:underline;">guncelleme.php</a></b> sayfasını bir kez açmanız yeterli.</div>';
}
?>
<div class="page-head">
    <div>
        <p class="page-sub" style="margin:0 0 4px;">
            <a href="index.php" class="row-link muted">Stajyer Listesi</a>
            <span class="ms sm" style="font-size:15px;">chevron_right</span>
            Stajyer Detayları
        </p>
        <h1><?= e($fullName) ?>
            <span class="badge badge-<?= $cls ?>"><?= $label ?></span>
        </h1>
    </div>
    <div class="page-actions">
        <?php if (!is_mentor()): ?>
            <a href="form.php?id=<?= (int) $intern['id'] ?>" class="btn btn-primary"><span class="ms sm">edit</span> Düzenle</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-light"><span class="ms sm">arrow_back</span> Listeye Dön</a>
    </div>
</div>

<div class="detail-layout">
    <!-- Sol: Profil kartı -->
    <div>
        <div class="card profile-card">
            <div class="profile-cover">
                <?php if ($intern['photo']): ?>
                    <img src="uploads/<?= e($intern['photo']) ?>" alt="<?= e($intern['first_name']) ?> fotoğrafı">
                <?php else: ?>
                    <div class="avatar-empty avatar" style="width:100%;height:100%;border-radius:0;font-size:64px;">
                        <?= e(mb_strtoupper(mb_substr($intern['first_name'], 0, 1) . mb_substr($intern['last_name'], 0, 1), 'UTF-8')) ?>
                    </div>
                <?php endif; ?>
                <span class="badge badge-<?= $cls ?>"><?= mb_strtoupper($label, 'UTF-8') ?></span>
            </div>
            <div class="profile-body">
                <h3 class="card-title">Stajyer Bilgileri</h3>
                <p class="muted" style="margin:0 0 20px; font-size:13.5px;">
                    <?= ($intern['school'] ?? '') !== '' ? e($intern['school']) . ' · ' : '' ?><?= e($intern['department']) ?>
                    · <?= e(LEVELS[$intern['level'] ?? 'lisans'] ?? '') ?>
                </p>

                <div class="info-row">
                    <span class="info-icon"><span class="ms">fingerprint</span></span>
                    <div><div class="k">T.C. Kimlik No</div><div class="v"><?= e($intern['tc_no'] ?: '-') ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms">phone</span></span>
                    <div><div class="k">Telefon</div><div class="v"><?= e($intern['phone']) ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms">home</span></span>
                    <div><div class="k">Adres</div>
                        <div class="v"><?= $intern['address'] !== '' ? nl2br(e($intern['address'])) : '-' ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms">emergency</span></span>
                    <div><div class="k">Acil Durum Kişisi</div>
                        <div class="v">
                            <?php if ($intern['emergency_name'] !== ''): ?>
                                <?= e($intern['emergency_name']) ?>
                                <?= $intern['emergency_relation'] !== '' ? ' <span class="muted">(' . e($intern['emergency_relation']) . ')</span>' : '' ?><br>
                                <span class="row-sub"><?= e($intern['emergency_phone']) ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms">manage_accounts</span></span>
                    <div><div class="k">Sorumlu Yetkili</div>
                        <div class="v"><?= $mentorName !== null ? e($mentorName) : '<span class="muted">Atanmadı</span>' ?></div></div>
                </div>

                <div class="date-split">
                    <div><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);">Başlangıç</div>
                        <b><?= format_date($intern['start_date']) ?></b></div>
                    <div style="text-align: right;"><div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);">Bitiş</div>
                        <b><?= format_date($intern['end_date']) ?></b></div>
                </div>

                <div class="progress-wide"><i style="width:<?= $pct ?>%;"></i></div>
                <div class="progress-meta">
                    <span>STAJ İLERLEMESİ · <?= intern_days($intern) ?> GÜN</span>
                    <span>%<?= $pct ?></span>
                </div>
                <div class="progress-meta" style="margin-top: 4px; font-size: 11px; font-weight: 700; color: var(--text-2);">
                    <span>KALAN SÜRE</span>
                    <span><?= intern_remaining_workdays($intern) ?> İŞ GÜNÜ</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ: Yoklama takvimi & Beceriler -->
    <div>
        <div class="card calendar-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
                <div>
                    <h3 class="card-title" style="margin:0;">Yoklama Takvimi</h3>
                    <p class="muted" style="margin:4px 0 0; font-size:13px;">Aylık devam durumu takibi</p>
                </div>
                <div class="cal-month-header" style="display:flex; align-items:center; gap:0; border:1px solid var(--card-border); border-radius:10px; background:var(--input-bg); overflow:hidden; height:38px;">
                    <button type="button" class="btn-icon" id="calPrevBtn" style="border:none; border-right:1px solid var(--card-border); border-radius:0; width:36px; height:100%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;" title="Önceki Ay"><span class="ms">chevron_left</span></button>
                    <span id="calMonthTitle" style="font-size:14px; font-weight:700; color:var(--text); padding:0 20px; min-width:140px; text-align:center;">-</span>
                    <button type="button" class="btn-icon" id="calNextBtn" style="border:none; border-left:1px solid var(--card-border); border-radius:0; width:36px; height:100%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;" title="Sonraki Ay"><span class="ms">chevron_right</span></button>
                </div>
            </div>
            <p class="cal-help">Geçmiş ve bugünkü iş günleri otomatik <b>Geldi</b> (yeşil) sayılır. Bir güne tıklayıp
               <b>Devamsız</b> veya <b>İzinli</b> işaretleyin; sonra <b>Değişiklikleri Kaydet</b>'e basın.</p>

            <div id="saveBar" class="floating-save-bar">
                <span style="color:var(--text); font-weight:700; font-size:13.5px; display:inline-flex; align-items:center; gap:8px;">
                    <span class="ms" style="color:var(--primary);">info</span>
                    Kaydedilmemiş <b id="pendCount" style="color:var(--primary); font-weight:800;">0</b> değişiklik var
                </span>
                <button type="button" class="btn btn-primary btn-sm" id="saveBtn"><span class="ms sm">save</span> Kaydet</button>
                <button type="button" class="btn btn-light btn-sm" id="discardBtn">Vazgeç</button>
            </div>

            <div class="cal-months-container">
                <?php
                $cursor   = $calStart->modify('first day of this month');
                $endMonth = $calEnd->modify('first day of this month');
                $guard    = 0;
                while ($cursor <= $endMonth && $guard++ < 24):
                    $y = (int) $cursor->format('Y');
                    $m = (int) $cursor->format('n');
                    $daysInMonth = (int) $cursor->format('t');
                    $firstDow    = (int) $cursor->format('N');
                    $slideTitle  = $monthNames[$m] . ' ' . $y;
                    $isSlideActive = ($cursor->format('Y-m') === $activeMonthStr);
                ?>
                <div class="cal-month-slide<?= $isSlideActive ? ' active' : '' ?>" data-title="<?= e($slideTitle) ?>">
                    <div class="cal-grid">
                        <?php foreach ($dowNames as $dn): ?>
                            <span class="cal-dow"><?= $dn ?></span>
                        <?php endforeach; ?>

                        <?php for ($i = 1; $i < $firstDow; $i++): ?>
                            <span class="cal-day out"></span>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= $daysInMonth; $day++):
                            $dObj    = $cursor->setDate($y, $m, $day);
                            $dStr    = $dObj->format('Y-m-d');
                            $inRange = $dStr >= $intern['start_date'] && $dStr <= $intern['end_date'];
                            $weekend = (int) $dObj->format('N') >= 6;
                            $isHoliday = is_turkish_holiday($dStr);
                            $status  = $attMap[$dStr] ?? '';

                            $classes = 'cal-day';
                            $text    = '';
                            if (!$inRange)     { 
                                $classes .= ' inactive'; 
                            }
                            elseif ($weekend || $isHoliday)  { 
                                $classes .= ' wknd'; 
                                $text = $weekend ? 'Hafta Sonu' : 'Resmi Tatil'; 
                            }
                            else {
                                $classes .= ' clickable';
                                if     ($status === 'devamsiz') { $classes .= ' dev'; }
                                elseif ($status === 'izinli')   { $classes .= ' izin'; }
                                elseif ($status === 'raporlu')  { $classes .= ' rapor'; }
                                elseif ($dStr > $todayStr)      { $classes .= ' fut'; }
                                else                            { $classes .= ' geldi'; }
                                if ($dStr === $todayStr)        { $classes .= ' today'; }
                                $text = ''; // No labels inside cells for workdays
                            }
                            $dayTitle = '';
                            if ($inRange && !$weekend && !$isHoliday && isset($attDetails[$dStr])) {
                                $det = $attDetails[$dStr];
                                if ($det['check_in'] || $det['check_out']) {
                                    $dayTitle = 'Giriş: ' . ($det['check_in'] ? date('H:i', strtotime($det['check_in'])) : '--:--') . ' | Çıkış: ' . ($det['check_out'] ? date('H:i', strtotime($det['check_out'])) : '--:--');
                                }
                            }
                        ?>
                            <span class="<?= $classes ?>"
                                  <?= $inRange && !$weekend && !$isHoliday ? 'data-date="' . $dStr . '" data-status="' . e($status) . '"' : '' ?>
                                  <?= $dayTitle !== '' ? 'title="' . e($dayTitle) . '"' : '' ?>
                            ><span class="d"><?= $day ?></span><span class="s"><?= $text ?></span></span>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php
                    $cursor = $cursor->modify('+1 month');
                endwhile;
                ?>
            </div>

            <div class="cal-legend" style="display:flex; gap:16px; margin-top:16px; padding-top:16px; border-top:1px solid var(--line-soft); font-size:13px; font-weight:700;">
                <span style="display:flex; align-items:center; gap:6px; color:var(--text);"><span style="width:8px; height:8px; border-radius:50%; background:var(--success); display:inline-block;"></span> Geldi: <b id="cntGeldi"><?= $cntGeldi ?></b></span>
                <span style="display:flex; align-items:center; gap:6px; color:var(--text);"><span style="width:8px; height:8px; border-radius:50%; background:var(--warning); display:inline-block;"></span> İzinli: <b id="cntIzin"><?= $cntIzin ?></b></span>
                <span style="display:flex; align-items:center; gap:6px; color:var(--text);"><span style="width:8px; height:8px; border-radius:50%; background:var(--danger); display:inline-block;"></span> Devamsız: <b id="cntDev"><?= $cntDev ?></b></span>
                <span style="display:flex; align-items:center; gap:6px; color:var(--text);"><span style="width:8px; height:8px; border-radius:50%; background:var(--info); display:inline-block;"></span> Raporlu: <b id="cntRapor"><?= $cntRapor ?></b></span>
            </div>
        </div>

        <div class="card skills-card">
            <h3 class="card-title" style="font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; padding-bottom:12px; border-bottom:1px solid var(--line-soft); margin-bottom:12px;">Becerİler &amp; Araçlar</h3>
            <div class="skills-container">
                <?php
                $skills = array_filter(array_map('trim', explode(',', $intern['skills'] ?? '')));
                if (empty($skills)):
                ?>
                    <span class="muted" style="font-size:13.5px;">Beceri eklenmemiş.</span>
                <?php else: ?>
                    <?php foreach ($skills as $s): ?>
                        <span class="skill-tag"><?= e($s) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Alt bölüm: Değerlendirme + Notlar + Belgeler -->
<div class="card" style="margin-top:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
            <span class="ms" style="color:var(--primary);">star</span> Haftalık Değerlendirme &amp; Görevler
        </h3>
    </div>

    <?php if (!$weeks): ?>
        <p class="muted">Staj tarihleri tanımlı değil.</p>
    <?php else: ?>
        <form method="post" class="eval-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="eval_save">
            <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
            <div class="grid-2">
                <label>Hafta
                    <select name="week_start" id="weekSelect">
                        <?php foreach ($weeks as $w): $has = isset($evalByWeek[$w['start']]); ?>
                            <option value="<?= $w['start'] ?>" <?= $w['start'] === $defaultWeek ? 'selected' : '' ?>>
                                <?= e($w['label']) ?><?= $has ? ' ✓' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Not (1-10)
                    <select name="score">
                        <option value="0">— Verilmedi —</option>
                        <?php for ($p = 10; $p >= 1; $p--): ?>
                            <option value="<?= $p ?>"><?= $p ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>
            <div class="grid-2">
                <label>Bu Haftaki Görev
                    <textarea name="task" rows="3" placeholder="Bu hafta stajyere verilen görev(ler)…"></textarea>
                </label>
                <label>Değerlendirme
                    <textarea name="comment" rows="3" placeholder="Performans değerlendirmesi…"></textarea>
                </label>
            </div>
            <button type="submit" class="btn btn-primary"><span class="ms sm">save</span> Değerlendirmeyi Kaydet</button>
            <p class="muted" style="font-size:12.5px; margin:10px 0 0;">Aynı hafta için tekrar kaydederseniz önceki değerlendirme güncellenir. ✓ işaretli haftaların kaydı vardır.</p>
        </form>

        <?php if ($evals): ?>
            <div style="margin-top:22px; border-top:1px solid var(--line-soft); overflow-x:auto;">
                <table class="eval-table">
                    <thead>
                        <tr>
                            <th>Hafta</th>
                            <th>Not</th>
                            <th>Görev</th>
                            <th>Değerlendirme</th>
                            <th>Yetkili / Tarih</th>
                            <th style="text-align:right;">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $weekLabels = array_column($weeks, 'label', 'start');
                        foreach ($evals as $ev): ?>
                            <tr>
                                <td>
                                    <span style="font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">
                                        <span class="ms sm" style="font-size:15px; color:var(--primary); width:15px; height:15px;">calendar_today</span>
                                        <?= e($weekLabels[$ev['week_start']] ?? format_date($ev['week_start'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($ev['score'] !== null): ?>
                                        <span class="badge <?= (int) $ev['score'] >= 8 ? 'badge-aktif' : ((int) $ev['score'] >= 5 ? 'badge-baslamadi' : 'badge-devamsiz') ?>">
                                            <?= (int) $ev['score'] ?>/10
                                        </span>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="white-space:pre-line; max-width:250px; line-height:1.4;"><?= (string) $ev['task'] !== '' ? e($ev['task']) : '<span class="muted">—</span>' ?></div>
                                </td>
                                <td>
                                    <div style="white-space:pre-line; max-width:250px; line-height:1.4;"><?= (string) $ev['comment'] !== '' ? e($ev['comment']) : '<span class="muted">—</span>' ?></div>
                                </td>
                                <td>
                                    <span style="font-size:12px; color:var(--text); font-weight:600; display:block; white-space:nowrap;"><?= e($ev['user_name']) ?></span>
                                    <span class="row-sub" style="font-size:11px; white-space:nowrap;"><?= e(date('d.m.Y', strtotime($ev['updated_at']))) ?></span>
                                </td>
                                <td style="text-align:right;">
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Bu değerlendirme silinsin mi?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="eval_delete">
                                        <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
                                        <input type="hidden" name="eval_id" value="<?= (int) $ev['id'] ?>">
                                        <button type="submit" class="btn-icon danger" title="Sil" style="width:30px; height:30px;"><span class="ms">delete</span></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="grid-2" style="margin-top:20px;">
    <!-- Staj Notları -->
    <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; height:640px; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin:0;">
                <span class="ms" style="color:var(--primary);">edit</span> Staj Notları
            </h3>
            <a href="notes.php?id=<?= $id ?>" id="viewAllNotesLink" class="btn btn-light btn-xs" style="font-size:11.5px; font-weight:700; <?= count($notes) > 4 ? '' : 'display:none;' ?>">Tümünü Görüntüle</a>
        </div>
        <form method="post" id="addNoteForm" style="margin-bottom:18px;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="note_add">
            <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
            <label>Yeni Not
                <textarea name="note_text" rows="2" required placeholder="Stajyerle ilgili tarihli bir not yazın…"></textarea>
            </label>
            <button type="submit" class="btn btn-primary btn-sm"><span class="ms sm">add</span> Not Ekle</button>
        </form>

        <div style="flex:1; overflow:hidden;" id="notesListContainer">
            <?php if (!$notes): ?>
                <p class="muted" style="margin:0;">Henüz staj notu eklenmemiş.</p>
            <?php else: ?>
                <?php foreach (array_slice($notes, 0, 4) as $n): ?>
                    <div class="note-item" style="border-bottom:1px solid var(--line-soft); padding-bottom:12px; margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span class="section-label" style="color:var(--primary);">
                                <?= e(mb_strtoupper(date('d.m.Y H:i', strtotime($n['created_at'])), 'UTF-8')) ?> · <?= e($n['user_name']) ?>
                                <?php if ($n['category'] !== 'Genel'): ?>
                                    <span class="badge badge-info" style="margin-left: 6px; font-size: 10px; font-weight: 700; padding: 2px 6px;"><?= e($n['category']) ?></span>
                                <?php endif; ?>
                            </span>
                            <div style="display:flex; gap:4px;">
                                <button type="button" class="btn-icon" style="width:30px;height:30px;" title="Düzenle"
                                        onclick="openEditNote(<?= (int)$n['id'] ?>, '<?= e($n['title']) ?>', '<?= e($n['category']) ?>', `<?= e($n['note_text']) ?>`)">
                                    <span class="ms sm">edit</span>
                                </button>
                                <form method="post" onsubmit="return confirm('Bu not silinsin mi?');" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="note_delete">
                                    <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
                                    <input type="hidden" name="note_id" value="<?= (int) $n['id'] ?>">
                                    <button type="submit" class="btn-icon danger" style="width:30px;height:30px;" title="Sil"><span class="ms sm">delete</span></button>
                                </form>
                            </div>
                        </div>
                        <div class="note-text-display" style="font-size:14px; color:var(--text); line-height:1.5;">
                            <?php if ($n['title'] !== ''): ?>
                                <strong style="font-size: 14.5px; display: block; margin-bottom: 4px; color: var(--text);"><?= e($n['title']) ?></strong>
                            <?php endif; ?>
                            <?= nl2br(e($n['note_text'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Yüklenen Belgeler -->
    <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; height:640px; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin:0;">
                <span class="ms" style="color:var(--primary);">folder</span> Yüklenen Belgeler
            </h3>
            <a href="documents.php?id=<?= $id ?>" id="viewAllDocsLink" class="btn btn-light btn-xs" style="font-size:11.5px; font-weight:700; <?= count($docs) > 4 ? '' : 'display:none;' ?>">Tümünü Görüntüle</a>
        </div>
        <form method="post" id="uploadDocForm" enctype="multipart/form-data" style="margin-bottom:18px; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="doc_upload">
            <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
            <label style="flex:1; min-width:220px; margin:0;">Belge Seç (PDF, resim, Word, Excel — en fazla 10 MB)
                <input type="file" name="document" required
                       accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
            </label>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-bottom:16px;"><span class="ms sm">upload</span> Yükle</button>
        </form>

        <div style="flex:1; overflow:hidden;" id="docsListContainer">
            <?php if (!$docs): ?>
                <p class="muted" style="margin:0;">Henüz belge yüklenmemiş.</p>
            <?php else: ?>
                <?php foreach (array_slice($docs, 0, 4) as $d): ?>
                    <div class="doc-item">
                        <span class="info-icon" style="width:38px;height:38px;"><span class="ms">description</span></span>
                        <div style="flex:1; min-width:0;">
                            <b style="font-size:13.5px; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($d['orig_name']) ?></b>
                            <span class="row-sub"><?= number_format($d['filesize'] / 1024, 0, ',', '.') ?> KB · <?= e(date('d.m.Y', strtotime($d['created_at']))) ?></span>
                        </div>
                        <a class="btn-icon" href="download.php?id=<?= (int) $d['id'] ?>" title="İndir"><span class="ms">download</span></a>
                        <form method="post" onsubmit="return confirm('Bu belge kalıcı olarak silinecek. Emin misiniz?');" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="doc_delete">
                            <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
                            <input type="hidden" name="doc_id" value="<?= (int) $d['id'] ?>">
                            <button type="submit" class="btn-icon danger" title="Sil"><span class="ms">delete</span></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="delete-section">
    <form method="post" action="delete.php" onsubmit="return confirm('Bu stajyer kaydı kalıcı olarak silinecek. Emin misiniz?');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $intern['id'] ?>">
        <button type="submit" class="btn btn-danger" style="padding: 10px 24px;">
            <span class="ms sm">delete</span> Stajyer Kaydını Sil
        </button>
    </form>
</div>

<!-- Not Düzenleme Modalı -->
<div id="noteEditModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Notu Düzenle</h3>
            <button type="button" onclick="closeEditModal()" class="btn-icon" style="width:32px;height:32px; border:none; background:transparent; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;"><span class="ms">close</span></button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="note_edit">
            <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
            <input type="hidden" name="note_id" id="editNoteId" value="0">
            
            <label style="margin-bottom:12px; display:block; text-align:left;">Başlık
                <input type="text" name="title" id="editNoteTitle" placeholder="örn. React Component Mimarisi">
            </label>

            <label style="margin-bottom:12px; display:block; text-align:left;">Kategori
                <select name="category" id="editNoteCategory">
                    <option value="Teknik Performans">Teknik Performans</option>
                    <option value="Davranışsal Gözlem">Davranışsal Gözlem</option>
                    <option value="İdari Süreç">İdari Süreç</option>
                    <option value="Genel">Genel</option>
                </select>
            </label>

            <label style="margin-bottom:16px; display:block; text-align:left;">Not İçeriği *
                <textarea name="note_text" id="editNoteText" rows="4" required placeholder="Not içeriği…"></textarea>
            </label>

            <div class="modal-footer">
                <button type="button" onclick="closeEditModal()" class="btn btn-light">Vazgeç</button>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </div>
        </form>
    </div>
</div>

<!-- Gün durumu menüsü -->
<div class="ctx-menu hidden" id="statusMenu">
    <button type="button" data-set="devamsiz"><span class="dot" style="background:var(--danger);"></span> Devamsız</button>
    <button type="button" data-set="izinli"><span class="dot" style="background:var(--warning);"></span> İzinli</button>
    <button type="button" data-set="raporlu"><span class="dot" style="background:var(--primary);"></span> Raporlu</button>
    <hr>
    <button type="button" data-set=""><span class="dot" style="background:var(--success);"></span> İşareti Kaldır (Geldi)</button>
</div>

<script>
(function () {
    var CSRF      = <?= json_encode(csrf_token()) ?>;
    var INTERN_ID = <?= (int) $intern['id'] ?>;
    var TODAY     = <?= json_encode($todayStr) ?>;
    var menu      = document.getElementById('statusMenu');
    var saveBar   = document.getElementById('saveBar');
    var pendEl    = document.getElementById('pendCount');
    var current   = null;
    var pending   = {};   // tarih -> yeni durum (kaydedilmemiş)
    var original  = {};   // tarih -> sayfa yüklendiğindeki durum

    document.querySelectorAll('.cal-day.clickable').forEach(function (c) {
        original[c.dataset.date] = c.dataset.status || '';
    });

    // Takvim slayt geçişleri (Ay değiştirme)
    var slides = document.querySelectorAll('.cal-month-slide');
    var activeIndex = 0;
    slides.forEach(function (slide, idx) {
        if (slide.classList.contains('active')) {
            activeIndex = idx;
        }
    });

    var prevBtn = document.getElementById('calPrevBtn');
    var nextBtn = document.getElementById('calNextBtn');
    var titleEl = document.getElementById('calMonthTitle');

    function updateSlides() {
        slides.forEach(function (slide, idx) {
            if (idx === activeIndex) {
                slide.classList.add('active');
                titleEl.textContent = slide.dataset.title;
            } else {
                slide.classList.remove('active');
            }
        });
        if (prevBtn) prevBtn.disabled = (activeIndex === 0);
        if (nextBtn) nextBtn.disabled = (activeIndex === slides.length - 1);
        if (typeof closeMenu === 'function') {
            closeMenu();
        }
    }

    if (prevBtn && nextBtn && titleEl && slides.length > 0) {
        prevBtn.addEventListener('click', function () {
            if (activeIndex > 0) {
                activeIndex--;
                updateSlides();
            }
        });
        nextBtn.addEventListener('click', function () {
            if (activeIndex < slides.length - 1) {
                activeIndex++;
                updateSlides();
            }
        });
        updateSlides();
    }

    function pendingCount() { return Object.keys(pending).length; }

    function updateBar() {
        var n = pendingCount();
        saveBar.style.display = n > 0 ? 'flex' : 'none';
        pendEl.textContent = n;
    }

    function recount() {
        var dev = 0, izin = 0, rapor = 0, devPast = 0, izinPast = 0, raporPast = 0, pastCells = 0;
        document.querySelectorAll('.cal-day.clickable').forEach(function (c) {
            var past = c.dataset.date <= TODAY;
            if (past) pastCells++;
            if (c.dataset.status === 'devamsiz') { dev++;  if (past) devPast++; }
            if (c.dataset.status === 'izinli')   { izin++; if (past) izinPast++; }
            if (c.dataset.status === 'raporlu')  { rapor++; if (past) raporPast++; }
        });
        document.getElementById('cntDev').textContent   = dev;
        document.getElementById('cntIzin').textContent  = izin;
        document.getElementById('cntRapor').textContent = rapor;
        document.getElementById('cntGeldi').textContent = Math.max(0, pastCells - devPast - izinPast - raporPast);
    }

    function paint(cell, status) {
        cell.dataset.status = status;
        cell.classList.remove('dev', 'izin', 'rapor', 'geldi', 'fut');
        if      (status === 'devamsiz') { cell.classList.add('dev'); }
        else if (status === 'izinli')   { cell.classList.add('izin'); }
        else if (status === 'raporlu')  { cell.classList.add('rapor'); }
        else if (cell.dataset.date > TODAY) { cell.classList.add('fut'); }
        else { cell.classList.add('geldi'); }
        var s = cell.querySelector('.s');
        if (s) s.textContent = '';
    }

    function closeMenu() { menu.classList.add('hidden'); current = null; }

    document.querySelectorAll('.cal-day.clickable').forEach(function (cell) {
        cell.addEventListener('click', function (ev) {
            ev.stopPropagation();
            current = cell;
            var r = cell.getBoundingClientRect();
            menu.classList.remove('hidden');
            var mw = menu.offsetWidth, mh = menu.offsetHeight;
            var left = Math.min(r.left, window.innerWidth - mw - 12);
            var top  = r.bottom + 8;
            if (top + mh > window.innerHeight - 12) top = r.top - mh - 8;
            menu.style.left = left + 'px';
            menu.style.top  = top + 'px';
        });
    });

    menu.querySelectorAll('button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!current) return;
            var next = btn.dataset.set;
            paint(current, next);
            if (original[current.dataset.date] === next) {
                delete pending[current.dataset.date];   // eski haline döndü
            } else {
                pending[current.dataset.date] = next;
            }
            recount();
            updateBar();
            closeMenu();
        });
    });

    document.getElementById('saveBtn').addEventListener('click', function () {
        if (pendingCount() === 0) return;
        var btn = this;
        btn.disabled = true;
        var fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('intern_id', INTERN_ID);
        fd.append('changes', JSON.stringify(pending));

        fetch('attendance.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.ok) { alert(res.error || 'Kaydedilemedi.'); return; }
                Object.keys(pending).forEach(function (d) { original[d] = pending[d]; });
                pending = {};
                updateBar();
            })
            .catch(function () { alert('Sunucuya ulaşılamadı.'); })
            .finally(function () { btn.disabled = false; });
    });

    document.getElementById('discardBtn').addEventListener('click', function () {
        document.querySelectorAll('.cal-day.clickable').forEach(function (c) {
            if (c.dataset.date in pending) paint(c, original[c.dataset.date]);
        });
        pending = {};
        recount();
        updateBar();
    });

    window.addEventListener('beforeunload', function (ev) {
        sessionStorage.setItem('scrollPos', window.scrollY);
        if (pendingCount() > 0) {
            ev.preventDefault();
            ev.returnValue = '';
        }
    });

    var scrollPos = sessionStorage.getItem('scrollPos');
    if (scrollPos) {
        window.scrollTo(0, parseInt(scrollPos, 10));
        sessionStorage.removeItem('scrollPos');
    }

    window.openEditNote = function (id, title, category, text) {
        document.getElementById('editNoteId').value = id;
        document.getElementById('editNoteTitle').value = title || '';
        document.getElementById('editNoteCategory').value = category || 'Genel';
        document.getElementById('editNoteText').value = text || '';
        document.getElementById('noteEditModal').classList.add('open');
    };
    window.closeEditModal = function () {
        document.getElementById('noteEditModal').classList.remove('open');
    };

    function showToast(msg, type) {
        var oldToast = document.querySelector('.toast-box');
        if (oldToast) oldToast.remove();

        var toast = document.createElement('div');
        toast.className = 'toast-box';
        toast.style.position = 'fixed';
        toast.style.top = '24px';
        toast.style.right = '24px';
        toast.style.zIndex = '2000';
        toast.style.padding = '14px 20px';
        toast.style.borderRadius = '12px';
        toast.style.fontSize = '14px';
        toast.style.fontWeight = '600';
        toast.style.boxShadow = '0 10px 30px rgba(0,0,0,0.15)';
        toast.style.background = type === 'success' ? '#22c55e' : '#ef4444';
        toast.style.color = '#fff';
        toast.style.cursor = 'pointer';
        toast.textContent = msg;
        
        toast.onclick = function () { toast.remove(); };
        document.body.appendChild(toast);
        
        setTimeout(function () {
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            setTimeout(function () { toast.remove(); }, 300);
        }, 3500);
    }

    function refreshNotes() {
        fetch('view.php?id=' + INTERN_ID + '&ajax_get_notes=1')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var container = document.getElementById('notesListContainer');
                container.innerHTML = res.html;
                if (window.renderIcons) window.renderIcons(container);
                var link = document.getElementById('viewAllNotesLink');
                if (link) link.style.display = res.count > 4 ? 'inline-block' : 'none';
            });
    }

    function refreshDocs() {
        fetch('view.php?id=' + INTERN_ID + '&ajax_get_docs=1')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var container = document.getElementById('docsListContainer');
                container.innerHTML = res.html;
                if (window.renderIcons) window.renderIcons(container);
                var link = document.getElementById('viewAllDocsLink');
                if (link) link.style.display = res.count > 4 ? 'inline-block' : 'none';
            });
    }

    document.addEventListener('submit', function (ev) {
        if (ev.defaultPrevented) return;
        
        var form = ev.target;
        var actionEl = form.querySelector('[name=action]');
        var action = actionEl ? actionEl.value : '';
        
        if (action === 'note_add' || action === 'note_edit' || action === 'note_delete' || action === 'doc_upload' || action === 'doc_delete') {
            ev.preventDefault();
            
            var fd = new FormData(form);
            fd.append('ajax', '1');
            
            var submitBtn = form.querySelector('[type=submit]');
            if (submitBtn) submitBtn.disabled = true;

            fetch('view.php?id=' + INTERN_ID, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (submitBtn) submitBtn.disabled = false;
                    
                    if (!res.ok) {
                        showToast(res.error || 'İşlem başarısız oldu.', 'error');
                        return;
                    }
                    showToast(res.msg, 'success');
                    
                    if (action === 'note_add') {
                        form.querySelector('textarea').value = '';
                        refreshNotes();
                    } else if (action === 'note_edit') {
                        closeEditModal();
                        refreshNotes();
                    } else if (action === 'note_delete') {
                        refreshNotes();
                    } else if (action === 'doc_upload') {
                        form.querySelector('input[type=file]').value = '';
                        refreshDocs();
                    } else if (action === 'doc_delete') {
                        refreshDocs();
                    }
                })
                .catch(function () {
                    if (submitBtn) submitBtn.disabled = false;
                    showToast('İletişim hatası oluştu.', 'error');
                });
        }
    });

    document.addEventListener('click', function (ev) {
        if (!menu.classList.contains('hidden') && !menu.contains(ev.target)) closeMenu();
    });
    window.addEventListener('scroll', closeMenu, true);

    /* Hafta seçilince varsa mevcut değerlendirmeyi forma doldur */
    var evalData = <?= json_encode(array_map(static fn($ev) => [
        'week' => $ev['week_start'],
        'score' => $ev['score'] !== null ? (int) $ev['score'] : 0,
        'task' => (string) $ev['task'],
        'comment' => (string) $ev['comment'],
    ], $evals), JSON_UNESCAPED_UNICODE) ?>;
    var weekSel = document.getElementById('weekSelect');
    if (weekSel) {
        var form = weekSel.closest('form');
        function fillEval() {
            var found = evalData.find(function (e) { return e.week === weekSel.value; });
            form.querySelector('[name=score]').value   = found ? found.score : 0;
            form.querySelector('[name=task]').value    = found ? found.task : '';
            form.querySelector('[name=comment]').value = found ? found.comment : '';
        }
        weekSel.addEventListener('change', fillEval);
        fillEval();
    }
})();
</script>
<?php render_footer(); ?>
