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

if (is_mentor() && trim(mb_strtolower((string)($intern['assigned_department'] ?? ''))) !== trim(mb_strtolower($_SESSION['user_department'] ?? ''))) {
    flash_set('error', 'Bu stajyerin belgelerini görüntüleme yetkiniz bulunmamaktadır.');
    redirect('index.php');
}

$fullName = $intern['first_name'] . ' ' . $intern['last_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'doc_upload') {
        try {
            [$fname, $orig, $size] = handle_document_upload('document');
            db()->prepare('INSERT INTO intern_documents (intern_id, filename, orig_name, filesize) VALUES (?, ?, ?, ?)')
                ->execute([$id, $fname, $orig, $size]);
            log_action('belge_yukle', $fullName . ' — ' . $orig);
            flash_set('success', 'Belge yüklendi: ' . $orig);
        } catch (RuntimeException $ex) {
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
            flash_set('success', 'Belge silindi.');
        }
    }

    redirect('documents.php?id=' . $id);
}

$docs = [];
$s = db()->prepare('SELECT * FROM intern_documents WHERE intern_id = ? ORDER BY id DESC');
$s->execute([$id]);
$docs = $s->fetchAll();

render_header($fullName . ' — Belgeler', 'index');
?>
<div class="page-head">
    <div>
        <p class="page-sub" style="margin:0 0 4px;">
            <a href="index.php" class="row-link muted">Stajyer Listesi</a>
            <span class="ms sm" style="font-size:15px;">chevron_right</span>
            <a href="view.php?id=<?= $id ?>" class="row-link muted"><?= e($fullName) ?></a>
            <span class="ms sm" style="font-size:15px;">chevron_right</span>
            Belgeler
        </p>
        <h1>Yüklenen Tüm Belgeler</h1>
    </div>
    <div class="page-actions">
        <a href="view.php?id=<?= $id ?>" class="btn btn-light"><span class="ms sm">arrow_back</span> Profil Sayfasına Dön</a>
    </div>
</div>

<div class="grid-2 align-start">
    <div class="card">
        <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
            <span class="ms" style="color:var(--primary);">upload</span> Yeni Belge Yükle
        </h3>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="doc_upload">
            <label style="display:block; margin-bottom:16px;">Belge Seç (PDF, resim, Word, Excel — en fazla 10 MB)
                <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
            </label>
            <button type="submit" class="btn btn-primary"><span class="ms sm">upload</span> Yükle</button>
        </form>
    </div>

    <div class="card">
        <h3 class="card-title" style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
            <span class="ms" style="color:var(--primary);">folder</span> Belgeler (<?= count($docs) ?>)
        </h3>
        <?php if (!$docs): ?>
            <p class="muted" style="margin:0;">Henüz belge yüklenmemiş.</p>
        <?php else: ?>
            <?php foreach ($docs as $d): ?>
                <div class="doc-item" style="display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--line-soft);">
                    <span class="info-icon" style="width:38px;height:38px; flex:none;"><span class="ms">description</span></span>
                    <div style="flex:1; min-width:0;">
                        <b style="font-size:13.5px; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($d['orig_name']) ?></b>
                        <span class="row-sub"><?= number_format($d['filesize'] / 1024, 0, ',', '.') ?> KB · <?= e(date('d.m.Y', strtotime($d['created_at']))) ?></span>
                    </div>
                    <a class="btn-icon" href="download.php?id=<?= (int) $d['id'] ?>" title="İndir"><span class="ms">download</span></a>
                    <form method="post" onsubmit="return confirm('Bu belge kalıcı olarak silinecek. Emin misiniz?');" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="doc_delete">
                        <input type="hidden" name="doc_id" value="<?= (int) $d['id'] ?>">
                        <button type="submit" class="btn-icon danger" title="Sil"><span class="ms">delete</span></button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
