<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_role(['sistem_yoneticisi', 'kurum_staj_sorumlusu']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();

$id = (int) ($_POST['id'] ?? 0);

$stmt = db()->prepare('SELECT photo, first_name, last_name FROM interns WHERE id = ?');
$stmt->execute([$id]);
$intern = $stmt->fetch();

if ($intern) {
    db()->prepare('DELETE FROM interns WHERE id = ?')->execute([$id]);
    delete_photo($intern['photo']);
    log_action('stajyer_sil', $intern['first_name'] . ' ' . $intern['last_name']);
    flash_set('success', 'Stajyer kaydı silindi.');
} else {
    flash_set('error', 'Stajyer bulunamadı.');
}

redirect('index.php');
