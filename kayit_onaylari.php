<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Sadece sistem yöneticileri kayıt taleplerini onaylayabilir
require_role(['sistem_yoneticisi']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    // Talebi çek
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? AND status = \'pending\'');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        flash_set('error', 'Kayıt talebi bulunamadı veya zaten işleme alınmış.');
        redirect('kayit_onaylari.php');
    }

    if ($action === 'approve') {
        $role = trim((string) ($_POST['role'] ?? 'birim_sorumlusu'));
        $dept = trim((string) ($_POST['department'] ?? ''));

        if (!in_array($role, ['sistem_yoneticisi', 'kurum_staj_sorumlusu', 'birim_sorumlusu'], true)) {
            flash_set('error', 'Geçersiz rol seçimi.');
            redirect('kayit_onaylari.php');
        }
        // Birim sorumlusu için birim ataması zorunlu
        if ($role === 'birim_sorumlusu' && $dept === '') {
            flash_set('error', 'Birim sorumlusu için bir birim (daire başkanlığı) atamalısınız.');
            redirect('kayit_onaylari.php');
        }

        db()->prepare('UPDATE users SET status = \'active\', role = ?, department = ? WHERE id = ?')
            ->execute([$role, $dept !== '' ? $dept : null, $id]);
        log_action('kayit_onayla', $target['full_name'] . ' (' . $target['username'] . ') onaylandı — Rol: ' . $role . ($dept !== '' ? ', Birim: ' . $dept : ''));
        flash_set('success', $target['full_name'] . ' onaylandı ve sisteme kabul edildi.');
        redirect('kayit_onaylari.php');
    }

    if ($action === 'reject') {
        db()->prepare('UPDATE users SET status = \'rejected\' WHERE id = ?')->execute([$id]);
        log_action('kayit_reddet', $target['full_name'] . ' (' . $target['username'] . ') kaydı reddedildi.');
        flash_set('success', $target['full_name'] . ' kaydı reddedildi.');
        redirect('kayit_onaylari.php');
    }

    redirect('kayit_onaylari.php');
}

// Bekleyen talepler
$pending = db()->query('SELECT * FROM users WHERE status = \'pending\' ORDER BY created_at ASC')->fetchAll();

// Birim önerileri (kontenjanlarda tanımlı daire başkanlıkları)
$departmentOptions = db()->query('SELECT DISTINCT department_name FROM department_quotas ORDER BY department_name')->fetchAll(PDO::FETCH_COLUMN);

render_header('Kayıt Onayları', 'kayit_onaylari');
?>
<div class="page-head">
    <div>
        <h1>Kayıt Onayları</h1>
        <p class="page-sub">Yeni kayıt talepleri. Onaylanan kullanıcılar bir rol ve birime atanarak sisteme kabul edilir.</p>
    </div>
</div>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <h2 style="margin:0;">Bekleyen Talepler</h2>
        <span class="badge badge-<?= $pending ? 'devamsiz' : 'aktif' ?>"><?= count($pending) ?> bekliyor</span>
    </div>

    <?php if (!$pending): ?>
        <p class="muted" style="margin:0;">Bekleyen kayıt talebi bulunmamaktadır.</p>
    <?php else: ?>
        <div style="display:flex; flex-direction:column; gap:14px;">
            <?php foreach ($pending as $p): ?>
                <div style="border:1px solid var(--line-soft); border-radius:12px; padding:16px;">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
                        <span class="avatar avatar-empty" style="width:42px;height:42px;font-size:15px;margin:0;">
                            <?= e(mb_strtoupper(mb_substr($p['full_name'], 0, 1), 'UTF-8')) ?>
                        </span>
                        <div>
                            <b style="font-size:15px;"><?= e($p['full_name']) ?></b>
                            <div class="muted" style="font-size:12.5px;">
                                @<?= e($p['username']) ?>
                                <?= !empty($p['email']) ? ' · ' . e($p['email']) : '' ?>
                                · <?= e(date('d.m.Y H:i', strtotime($p['created_at']))) ?>
                            </div>
                        </div>
                    </div>

                    <form method="post" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <label style="margin:0; flex:1; min-width:180px;">Rol
                            <select name="role" class="role-select" onchange="toggleDept(this)">
                                <option value="birim_sorumlusu">Birim Sorumlusu</option>
                                <option value="kurum_staj_sorumlusu">Staj Sorumlusu (İK)</option>
                                <option value="sistem_yoneticisi">Sistem Yöneticisi</option>
                            </select>
                        </label>
                        <label style="margin:0; flex:1; min-width:220px;">Birim / Daire Başkanlığı
                            <input type="text" name="department" list="departmentOptions" placeholder="örn. Bilgi İşlem Dairesi Başkanlığı">
                        </label>
                        <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm" style="height:42px;">
                            <span class="ms sm"><?= svg_icon('check_circle') ?></span> Onayla
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-light btn-sm" style="height:42px; color:var(--danger);" onclick="return confirm('Bu kayıt talebi reddedilecek. Emin misiniz?');">
                            <span class="ms sm"><?= svg_icon('close') ?></span> Reddet
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<datalist id="departmentOptions">
    <?php foreach ($departmentOptions as $deptOption): ?>
        <option value="<?= e($deptOption) ?>">
    <?php endforeach; ?>
</datalist>

<script>
function toggleDept(sel) {
    // Sistem yöneticisi/İK için birim opsiyonel; birim sorumlusu için gerekli (görsel ipucu)
    var deptInput = sel.closest('form').querySelector('input[name="department"]');
    if (sel.value === 'birim_sorumlusu') {
        deptInput.setAttribute('required', 'required');
        deptInput.style.opacity = '1';
    } else {
        deptInput.removeAttribute('required');
        deptInput.style.opacity = '0.7';
    }
}
</script>
<?php render_footer(); ?>
