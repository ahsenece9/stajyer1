<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

render_header('Sürüm Bilgisi', 'surum');
?>
<div class="page-head">
    <div>
        <h1>Sürüm Bilgisi</h1>
        <p class="page-sub">Uygulamanın güncel sürümü ve tüm değişiklik geçmişi.</p>
    </div>
</div>

<div class="card" style="margin-bottom:20px; display:flex; align-items:center; gap:16px;">
    <div style="width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; background:var(--primary); color:#fff; flex:none;">
        <span class="ms" style="width:28px; height:28px;"><?= svg_icon('info') ?></span>
    </div>
    <div>
        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted);">Güncel Sürüm</div>
        <div style="font-size:28px; font-weight:800; color:var(--text); line-height:1.1;">v<?= e(APP_VERSION) ?></div>
    </div>
    <div style="margin-left:auto; text-align:right;">
        <div style="font-size:12px; color:var(--muted);">Yayın Tarihi</div>
        <div style="font-weight:700; color:var(--text);"><?= e(date('d.m.Y', strtotime(APP_CHANGELOG[0][1]))) ?></div>
    </div>
</div>

<div class="card">
    <h2 style="margin:0 0 4px;">Değişiklik Günlüğü</h2>
    <p class="muted" style="margin:0 0 18px; font-size:13px;">En küçük değişiklik dahi burada kayıt altına alınır.</p>

    <div style="display:flex; flex-direction:column; gap:0;">
        <?php foreach (APP_CHANGELOG as $i => [$ver, $date, $desc]): ?>
            <div style="display:flex; gap:16px; padding:16px 0; <?= $i < count(APP_CHANGELOG) - 1 ? 'border-bottom:1px solid var(--line-soft);' : '' ?>">
                <div style="flex:none; width:96px;">
                    <span style="display:inline-block; padding:4px 10px; border-radius:999px; font-size:13px; font-weight:700; background:<?= $i === 0 ? 'var(--primary)' : 'var(--neutral-bg)' ?>; color:<?= $i === 0 ? '#fff' : 'var(--text-2)' ?>;">v<?= e($ver) ?></span>
                    <div style="font-size:11px; color:var(--muted); margin-top:6px; padding-left:2px;"><?= e(date('d.m.Y', strtotime($date))) ?></div>
                </div>
                <div style="flex:1; font-size:14px; color:var(--text-2); line-height:1.5; padding-top:3px;">
                    <?= e($desc) ?>
                    <?php if ($i === 0): ?>
                        <span style="margin-left:8px; font-size:11px; font-weight:700; color:var(--success); background:var(--success-bg); padding:2px 8px; border-radius:999px;">GÜNCEL</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php render_footer(); ?>
