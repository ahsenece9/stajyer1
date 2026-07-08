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

if (is_mentor() && trim(mb_strtolower((string)($intern['assigned_department'] ?? ''))) !== trim(mb_strtolower($_SESSION['user_department'] ?? ''))) {
    flash_set('error', 'Bu stajyerin bilgilerini görüntüleme yetkiniz bulunmamaktadır.');
    redirect('index.php');
}

$fullName = $intern['first_name'] . ' ' . $intern['last_name'];
$upgradeNeeded = false;

// Geçmiş stajlar: aynı TC'ye ait diğer staj kayıtları (bu kayıt hariç).
// Her kayıt için ortalama değerlendirme puanı ve staj döneminin adı da getirilir.
$pastInternships = [];
if (!empty($intern['tc_no'])) {
    try {
        $piStmt = db()->prepare(
            'SELECT i.*,
                    (SELECT ROUND(AVG(e.score),1) FROM evaluations e WHERE e.intern_id = i.id AND e.score IS NOT NULL) AS avg_score,
                    (SELECT COUNT(*) FROM evaluations e WHERE e.intern_id = i.id) AS eval_count,
                    (SELECT p.name FROM internship_periods p WHERE p.start_date = i.start_date AND p.end_date = i.end_date LIMIT 1) AS period_name
             FROM interns i
             WHERE i.tc_no = ? AND i.id <> ?
             ORDER BY i.start_date DESC'
        );
        $piStmt->execute([$intern['tc_no'], $id]);
        $pastInternships = $piStmt->fetchAll();
    } catch (PDOException) {
        $pastInternships = [];
    }
}

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
            $week = (string) ($_POST['week_start'] ?? '');
            $validWeeks = array_column(intern_weeks($intern), 'start');

            if (!in_array($week, $validWeeks, true)) {
                flash_set('error', 'Geçersiz hafta seçimi.');
            } else {
                $stmt = db()->prepare('SELECT * FROM evaluations WHERE intern_id = ? AND week_start = ?');
                $stmt->execute([$id, $week]);
                $existing = $stmt->fetch();

                if (isset($_POST['score'])) {
                    $score = (int) $_POST['score'];
                } else {
                    $score = $existing ? (int) $existing['score'] : 0;
                }

                if (isset($_POST['comment'])) {
                    $comment = trim((string) $_POST['comment']);
                } else {
                    $comment = $existing ? (string) $existing['comment'] : '';
                }

                if (isset($_POST['task'])) {
                    $task = trim((string) $_POST['task']);
                } else {
                    $task = $existing ? (string) $existing['task'] : '';
                }

                if ($score < 1 && $task === '' && $comment === '') {
                    flash_set('error', 'Değerlendirme için en az bir alan doldurun.');
                } else {
                    db()->prepare(
                        'INSERT INTO evaluations (intern_id, week_start, score, task, comment, user_name)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE score = ?, task = ?, comment = ?, user_name = ?'
                    )->execute([
                        $id,
                        $week,
                        $score >= 1 && $score <= 10 ? $score : null,
                        $task,
                        $comment,
                        (string) $_SESSION['user_name'],
                        $score >= 1 && $score <= 10 ? $score : null,
                        $task,
                        $comment,
                        (string) $_SESSION['user_name']
                    ]);
                    log_action('degerlendirme', $fullName . ' — ' . format_date($week) . ' haftası');
                    flash_set('success', 'Haftalık görev/değerlendirme kaydedildi.');
                }
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
$cntGeldi = 0;
try {
    $startD = new DateTimeImmutable($intern['start_date']);
    $limitD = new DateTimeImmutable($intern['end_date']);
    $limitD = $limitD < $todayD ? $limitD : $todayD;
    for ($d = $startD; $d <= $limitD; $d = $d->modify('+1 day')) {
        $dStr = $d->format('Y-m-d');
        if ((int) $d->format('N') <= 5 && !is_turkish_holiday($dStr)) {
            $status = $attMap[$dStr] ?? '';
            if ($dStr === $todayStr) {
                if ($status === 'geldi') {
                    $cntGeldi++;
                }
            } else {
                if ($status !== 'devamsiz' && $status !== 'izinli' && $status !== 'raporlu') {
                    $cntGeldi++;
                }
            }
        }
    }
} catch (Exception) {
    $cntGeldi = 0;
}

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
                <span class="info-icon" style="width:38px;height:38px;"><span class="ms">' . svg_icon('description') . '</span></span>
                <div style="flex:1; min-width:0;">
                    <b style="font-size:13.5px; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . e($d['orig_name']) . '</b>
                    <span class="row-sub">' . number_format($d['filesize'] / 1024, 0, ',', '.') . ' KB · ' . e(date('d.m.Y', strtotime($d['created_at']))) . '</span>
                </div>
                <a class="btn-icon" href="download.php?id=' . (int) $d['id'] . '" title="İndir"><span class="ms">' . svg_icon('download') . '</span></a>
                <form method="post" style="margin:0;" onsubmit="return confirm(\'Bu belge kalıcı olarak silinecek. Emin misiniz?\');">' .
                    csrf_field() . '
                    <input type="hidden" name="action" value="doc_delete">
                    <input type="hidden" name="intern_id" value="' . $id . '">
                    <input type="hidden" name="doc_id" value="' . (int) $d['id'] . '">
                    <button type="submit" class="btn-icon danger" title="Sil"><span class="ms">' . svg_icon('delete') . '</span></button>
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
                    <span class="info-icon"><span class="ms"><?= svg_icon('fingerprint') ?></span></span>
                    <div><div class="k">T.C. Kimlik No</div><div class="v"><?= e($intern['tc_no'] ?: '-') ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms"><?= svg_icon('phone') ?></span></span>
                    <div><div class="k">Telefon</div><div class="v"><?= e($intern['phone']) ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms"><?= svg_icon('home') ?></span></span>
                    <div><div class="k">Adres</div>
                        <div class="v"><?= $intern['address'] !== '' ? nl2br(e($intern['address'])) : '-' ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms"><?= svg_icon('emergency') ?></span></span>
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
                    <span class="info-icon"><span class="ms"><?= svg_icon('history_edu') ?></span></span>
                    <div><div class="k">Staj Türü</div>
                        <div class="v"><?= ($intern['type'] ?? 'zorunlu') === 'gonullu' ? 'Gönüllü' : 'Zorunlu' ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms"><?= svg_icon('corporate_fare') ?></span></span>
                    <div><div class="k">Staj Yaptığı Birim</div>
                        <div class="v"><?= !empty($intern['assigned_department']) ? e($intern['assigned_department']) : '<span class="muted">Birim Atanmadı</span>' ?></div></div>
                </div>
                <div class="info-row">
                    <span class="info-icon"><span class="ms"><?= svg_icon('manage_accounts') ?></span></span>
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
                    <button type="button" class="btn-icon" id="calPrevBtn" style="border:none; border-right:1px solid var(--card-border); border-radius:0; width:36px; height:100%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;" title="Önceki Ay"><span class="ms"><?= svg_icon('chevron_left') ?></span></button>
                    <span id="calMonthTitle" style="font-size:14px; font-weight:700; color:var(--text); padding:0 20px; min-width:140px; text-align:center;">-</span>
                    <button type="button" class="btn-icon" id="calNextBtn" style="border:none; border-left:1px solid var(--card-border); border-radius:0; width:36px; height:100%; display:inline-flex; align-items:center; justify-content:center; cursor:pointer;" title="Sonraki Ay"><span class="ms"><?= svg_icon('chevron_right') ?></span></button>
                </div>
            </div>
            <p class="cal-help">Geçmiş ve bugünkü iş günleri otomatik <b>Geldi</b> (yeşil) sayılır. Bir güne tıklayıp
               <b>Devamsız</b> veya <b>İzinli</b> işaretleyin; sonra <b>Değişiklikleri Kaydet</b>'e basın.</p>

            <div id="saveBar" class="floating-save-bar">
                <span style="color:var(--text); font-weight:700; font-size:13.5px; display:inline-flex; align-items:center; gap:8px;">
                    <span class="ms" style="color:var(--primary);"><?= svg_icon('info') ?></span>
                    Kaydedilmemiş <b id="pendCount" style="color:var(--primary); font-weight:800;">0</b> değişiklik var
                </span>
                <button type="button" class="btn btn-primary btn-sm" id="saveBtn"><span class="ms sm"><?= svg_icon('save') ?></span> Kaydet</button>
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
                                elseif ($dStr === $todayStr && $status !== 'geldi') { $classes .= ' today-pending'; }
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

<?php
// Compute evaluation metrics
$scoredEvals = [];
if (!empty($evals)) {
    $scoredEvals = array_filter($evals, function($ev) {
        return $ev['score'] !== null && $ev['score'] > 0;
    });
}
$scoreCount = count($scoredEvals);
$totalScore = array_sum(array_column($scoredEvals, 'score'));
$averageScore = $scoreCount > 0 ? round($totalScore / $scoreCount, 2) : null;
$weekLabels = [];
if (!empty($weeks)) {
    $weekLabels = array_column($weeks, 'label', 'start');
}
?>

<div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; margin-top:20px;">
    <!-- Sol Taraf: Görev Girişi Kartı -->
    <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; justify-content:space-between; min-height:280px; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin:0;">
                <span class="ms" style="color:var(--primary);"><?= svg_icon('edit') ?></span> Görev Girişi
            </h3>
            <button type="submit" form="saveTaskForm" id="saveTaskBtn" class="btn btn-primary btn-sm" disabled style="margin:0;"><span class="ms sm"><?= svg_icon('save') ?></span> Kaydet</button>
        </div>

        <?php if (!$weeks): ?>
            <p class="muted">Staj tarihleri tanımlı değil.</p>
        <?php else: ?>
            <form method="post" id="saveTaskForm" class="eval-form" style="display:flex; flex-direction:column; justify-content:space-between; flex:1; margin:0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="eval_save">
                <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
                
                <div style="margin-bottom:10px;">
                    <label style="margin:0;">Hafta Seçimi
                        <select name="week_start" id="weekSelect" style="margin:0;">
                             <?php foreach ($weeks as $w): $has = isset($evalByWeek[$w['start']]); ?>
                                <option value="<?= $w['start'] ?>" <?= $w['start'] === $defaultWeek ? 'selected' : '' ?>>
                                    <?= e($w['label']) ?><?= $has ? ' ✓' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                
                <div style="margin-bottom:12px; flex:1; display:flex; flex-direction:column;">
                    <label style="margin:0; flex:1; display:flex; flex-direction:column;">Bu Haftaki Görev
                        <textarea name="task" id="taskTextarea" placeholder="Bu hafta stajyerin yapması gereken görevleri buraya girin…" style="margin:0; flex:1; min-height:80px; resize:none;"></textarea>
                    </label>
                </div>

                <div style="text-align: center; margin-top: 10px; border-top: 1px solid var(--line-soft); padding-top: 10px;">
                    <a href="tasks_history.php?id=<?= $id ?>" class="row-link" style="font-weight: 700; font-size: 13.5px; color: var(--primary); display: inline-flex; align-items: center; gap: 4px; justify-content: center; width: 100%;">
                        <span class="ms sm"><?= svg_icon('history') ?></span> Görev Geçmişi
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Sağ Taraf: Büyük Yıldız ve Ağırlıklı Puan Kartı -->
    <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; min-height:280px; box-sizing:border-box;">
        <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin-bottom:12px; align-self:flex-start; margin-top:0;">
            <span class="ms" style="color:var(--primary);"><?= svg_icon('star') ?></span> Genel Puan
        </h3>
        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; width: 100%;">
            <!-- Big Star Icon (Custom Image or Fallback SVG) -->
            <?php
            $customStarPath = 'assets/img/star.png';
            if (file_exists(__DIR__ . '/' . $customStarPath)): ?>
                <img src="<?= $customStarPath ?>" style="width: 64px; height: 64px; object-fit: contain; margin-bottom: 12px;" alt="Star">
            <?php else: ?>
                <div style="width: 64px; height: 64px; display: flex; align-items: center; justify-content: center; background: rgba(245, 158, 11, 0.12); color: #f59e0b; border-radius: 50%; margin-bottom: 12px; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.12);">
                    <span class="ms" style="font-size: 32px; width: 32px; height: 32px; display: block; line-height: 1;"><?= svg_icon('star') ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Weighted Score -->
            <?php if ($averageScore !== null): ?>
                <div style="font-size: 36px; font-weight: 800; color: var(--text); margin-bottom: 4px; line-height: 1; font-family: var(--font-head);">
                    <?= $averageScore ?> <span style="font-size: 16px; color: var(--text-2); font-weight: 500;">/ 10</span>
                </div>
                <!-- Success Status -->
                <div style="font-weight: 700; font-size: 12.5px; margin-top: 2px;">
                    <?php if ($averageScore >= 8): ?>
                        <span style="color: var(--success);">Pekiyi</span>
                    <?php elseif ($averageScore >= 5): ?>
                        <span style="color: var(--warning);">Orta</span>
                    <?php else: ?>
                        <span style="color: var(--danger);">Yetersiz</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="font-size: 14px; font-weight: 600; color: var(--text-2); margin-bottom: 4px;">
                    Puan Yok
                </div>
                <div style="font-size: 10px; color: var(--muted); max-width: 150px;">
                    Puanlar girildiğinde hesaplanır.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('taskTextarea');
    const saveBtn = document.getElementById('saveTaskBtn');
    if (textarea && saveBtn) {
        textarea.addEventListener('input', function() {
            saveBtn.disabled = (textarea.value.trim() === '');
        });
    }
});
</script>

<!-- Staj Notları ve Belgeler Kartları (Row 2) -->
<div class="grid-2" style="margin-top:20px;">
    <!-- Staj Notları -->
    <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; height:520px; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin:0;">
                <span class="ms" style="color:var(--primary);">edit</span> Staj Notları
            </h3>
            <a href="notes.php?id=<?= $id ?>" id="viewAllNotesLink" class="btn btn-light btn-xs" style="font-size:11.5px; font-weight:700; <?= count($notes) > 3 ? '' : 'display:none;' ?>">Tümünü Görüntüle</a>
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
                <?php foreach (array_slice($notes, 0, 3) as $n): ?>
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
    <div class="card" style="margin-bottom:0; display:flex; flex-direction:column; height:520px; box-sizing:border-box;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin:0;">
                <span class="ms" style="color:var(--primary);">folder</span> Yüklenen Belgeler
            </h3>
            <a href="documents.php?id=<?= $id ?>" id="viewAllDocsLink" class="btn btn-light btn-xs" style="font-size:11.5px; font-weight:700; <?= count($docs) > 3 ? '' : 'display:none;' ?>">Tümünü Görüntüle</a>
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
                <?php foreach (array_slice($docs, 0, 3) as $d): ?>
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

<!-- Haftalık Değerlendirmeler Tablosu (Row 3) -->
<?php if ($weeks && $evals): ?>
    <div class="card" style="margin-top:20px; margin-bottom:0;">
        <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin-bottom:16px; margin-top:0;">
            <span class="ms" style="color:var(--primary);">calendar_today</span> Haftalık Değerlendirmeler
        </h3>
        
        <div style="overflow-x: auto; border: 1px solid var(--line-soft); border-radius: 8px;">
            <table class="eval-table" style="width:100%; table-layout:fixed; border-collapse:collapse;">
                <colgroup>
                    <col style="width: 170px;"> <!-- Hafta -->
                    <col style="width: 90px;">  <!-- Not -->
                    <col style="width: 220px;"> <!-- Görev -->
                    <col style="width: 250px;"> <!-- Değerlendirme -->
                    <col style="width: 140px;"> <!-- Yetkili / Tarih -->
                    <col style="width: 70px;">  <!-- İşlem -->
                </colgroup>
                <thead>
                    <tr style="background: var(--hover);">
                        <th style="padding: 10px 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--line-soft);">Hafta</th>
                        <th style="padding: 10px 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--line-soft); text-align:center;">Not</th>
                        <th style="padding: 10px 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--line-soft);">Görev</th>
                        <th style="padding: 10px 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--line-soft);">Değerlendirme</th>
                        <th style="padding: 10px 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--line-soft);">Yetkili / Tarih</th>
                        <th style="padding: 10px 12px; font-size: 11px; font-weight: 700; color: var(--text-2); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--line-soft); text-align:right;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($evals, 0, 3) as $ev): ?>
                        <tr style="border-bottom: 1px solid var(--line-soft);">
                            <td style="padding: 10px 12px; font-size: 13px; vertical-align: middle;">
                                <form id="row_form_<?= $ev['id'] ?>" method="post" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="eval_save">
                                    <input type="hidden" name="week_start" value="<?= e($ev['week_start']) ?>">
                                    <input type="hidden" name="task" value="<?= e($ev['task']) ?>">
                                </form>
                                <span style="font-weight:600; font-size:13px; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;">
                                    <span class="ms sm" style="font-size:15px; color:var(--primary); width:15px; height:15px;">calendar_today</span>
                                    <?= e($weekLabels[$ev['week_start']] ?? format_date($ev['week_start'])) ?>
                                </span>
                            </td>
                            <td style="padding: 10px 12px; font-size: 13px; vertical-align: middle; text-align:center;">
                                <select name="score" form="row_form_<?= $ev['id'] ?>" style="margin:0; padding:4px 6px; font-size:12.5px; width:100%; box-sizing:border-box;">
                                    <option value="0">—</option>
                                    <?php for($p=10; $p>=1; $p--): ?>
                                        <option value="<?= $p ?>" <?= (int)$ev['score'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                            <td style="padding: 10px 12px; font-size: 13px; vertical-align: middle; word-wrap: break-word !important; word-break: break-all !important; white-space: normal !important; line-height:1.4;">
                                <?= (string) $ev['task'] !== '' ? e($ev['task']) : '<span class="muted">—</span>' ?>
                            </td>
                            <td style="padding: 10px 12px; font-size: 13px; vertical-align: middle;">
                                <input type="text" name="comment" value="<?= e($ev['comment']) ?>" form="row_form_<?= $ev['id'] ?>" style="margin:0; padding:6px; font-size:12.5px; width:100%; box-sizing:border-box;" placeholder="Değerlendirme girin…">
                            </td>
                            <td style="padding: 10px 12px; font-size: 13px; vertical-align: middle; line-height:1.3;">
                                <span style="font-size:12px; color:var(--text); font-weight:600; display:block; white-space:nowrap;"><?= e($ev['user_name']) ?></span>
                                <span class="row-sub" style="font-size:11px; white-space:nowrap;"><?= e(date('d.m.Y', strtotime($ev['updated_at']))) ?></span>
                            </td>
                            <td style="padding: 10px 12px; font-size: 13px; vertical-align: middle; text-align:right;">
                                <div style="display:flex; gap:4px; align-items:center; justify-content:flex-end;">
                                    <button type="submit" form="row_form_<?= $ev['id'] ?>" class="btn-icon" style="width:30px; height:30px; color:var(--success);" title="Kaydet">
                                        <span class="ms sm">save</span>
                                    </button>
                                    <form method="post" style="margin:0;" onsubmit="return confirm('Bu değerlendirmeyi silmek istediğinize emin misiniz?');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="eval_delete">
                                        <input type="hidden" name="intern_id" value="<?= (int) $intern['id'] ?>">
                                        <input type="hidden" name="eval_id" value="<?= (int) $ev['id'] ?>">
                                        <button type="submit" class="btn-icon danger" style="width:30px; height:30px;" title="Sil"><span class="ms sm">delete</span></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

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
        var dev = 0, izin = 0, rapor = 0, geldi = 0;
        document.querySelectorAll('.cal-day.clickable').forEach(function (c) {
            var past = c.dataset.date < TODAY;
            var isToday = c.dataset.date === TODAY;
            var status = c.dataset.status || '';
            
            if (past) {
                if      (status === 'devamsiz') { dev++; }
                else if (status === 'izinli')   { izin++; }
                else if (status === 'raporlu')  { rapor++; }
                else                            { geldi++; }
            } else if (isToday) {
                if      (status === 'devamsiz') { dev++; }
                else if (status === 'izinli')   { izin++; }
                else if (status === 'raporlu')  { rapor++; }
                else if (status === 'geldi')    { geldi++; }
            } else {
                if      (status === 'devamsiz') { dev++; }
                else if (status === 'izinli')   { izin++; }
                else if (status === 'raporlu')  { rapor++; }
            }
        });
        document.getElementById('cntDev').textContent   = dev;
        document.getElementById('cntIzin').textContent  = izin;
        document.getElementById('cntRapor').textContent = rapor;
        document.getElementById('cntGeldi').textContent = geldi;
    }

    function paint(cell, status) {
        cell.dataset.status = status;
        cell.classList.remove('dev', 'izin', 'rapor', 'geldi', 'fut', 'today-pending');
        if      (status === 'devamsiz') { cell.classList.add('dev'); }
        else if (status === 'izinli')   { cell.classList.add('izin'); }
        else if (status === 'raporlu')  { cell.classList.add('rapor'); }
        else if (cell.dataset.date > TODAY) { cell.classList.add('fut'); }
        else if (cell.dataset.date === TODAY && status !== 'geldi') { cell.classList.add('today-pending'); }
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
                if (link) link.style.display = res.count > 3 ? 'inline-block' : 'none';
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
                if (link) link.style.display = res.count > 3 ? 'inline-block' : 'none';
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
            var taskField = form.querySelector('[name=task]');
            if (taskField) taskField.value = found ? found.task : '';
            
            // Update save task button disabled state
            const saveBtn = document.getElementById('saveTaskBtn');
            if (saveBtn && taskField) {
                saveBtn.disabled = (taskField.value.trim() === '');
            }
        }
        weekSel.addEventListener('change', fillEval);
        fillEval();
    }
})();
</script>

<!-- Geçmiş Stajlar (aynı TC ile farklı dönemlerde yapılan stajlar) -->
<div class="card" style="margin-top:20px; margin-bottom:0;">
    <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin-bottom:4px; margin-top:0;">
        <span class="ms" style="color:var(--primary);">history</span> Geçmiş Stajlar
    </h3>
    <p class="muted" style="margin:0 0 16px; font-size:13px;">Aynı T.C. Kimlik No ile daha önce yapılmış diğer staj kayıtları ve değerlendirmeleri.</p>

    <?php if (!$pastInternships): ?>
        <p class="muted" style="margin:0;">Bu kişiye ait geçmiş bir staj kaydı bulunmuyor.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left;">Dönem</th>
                        <th style="text-align:left;">Okul / Bölüm</th>
                        <th style="text-align:center;">Seviye</th>
                        <th style="text-align:center;">Değerlendirme</th>
                        <th style="text-align:right; padding-right:16px;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pastInternships as $pi):
                        $piLevel = LEVELS[$pi['level']] ?? $pi['level'];
                        $piPeriod = $pi['period_name'] ?: (date('d.m.Y', strtotime($pi['start_date'])) . ' – ' . date('d.m.Y', strtotime($pi['end_date'])));
                    ?>
                        <tr>
                            <td style="text-align:left;">
                                <b><?= e($piPeriod) ?></b>
                                <div class="muted" style="font-size:12px;"><?= e(date('d.m.Y', strtotime($pi['start_date']))) ?> – <?= e(date('d.m.Y', strtotime($pi['end_date']))) ?></div>
                            </td>
                            <td style="text-align:left;">
                                <?= e($pi['school'] ?: '—') ?>
                                <div class="muted" style="font-size:12px;"><?= e($pi['department']) ?></div>
                            </td>
                            <td style="text-align:center;"><span class="badge badge-info"><?= e($piLevel) ?></span></td>
                            <td style="text-align:center;">
                                <?php if ($pi['avg_score'] !== null): ?>
                                    <b style="font-size:15px; color:var(--primary);"><?= e($pi['avg_score']) ?></b><span class="muted" style="font-size:12px;">/10</span>
                                    <div class="muted" style="font-size:11px;"><?= (int) $pi['eval_count'] ?> değerlendirme</div>
                                <?php else: ?>
                                    <span class="muted" style="font-size:12.5px;">Değerlendirme yok</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; padding-right:16px;">
                                <a href="view.php?id=<?= (int) $pi['id'] ?>" class="row-link" style="color:var(--primary); font-weight:700; font-size:13px; text-decoration:none;">Görüntüle</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
