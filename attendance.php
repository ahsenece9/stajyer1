<?php
declare(strict_types=1);

/**
 * Yoklama kaydetme ucu — "Değişiklikleri Kaydet" butonuna basılınca
 * biriken tüm değişiklikleri tek seferde kaydeder ve TEK log düşer.
 * status boş gelirse kayıt silinir (gün yeniden "Geldi" olur).
 */
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

function json_fail(string $msg, int $code = 400): never
{
    http_response_code($code);
    exit(json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail('Geçersiz istek yöntemi.', 405);
}
csrf_check();

$internId = (int) ($_POST['intern_id'] ?? 0);
$changes  = json_decode((string) ($_POST['changes'] ?? ''), true);

if (!is_array($changes) || $changes === []) {
    json_fail('Kaydedilecek değişiklik yok.');
}
if (count($changes) > 200) {
    json_fail('Tek seferde en fazla 200 değişiklik kaydedilebilir.');
}

$stmt = db()->prepare('SELECT start_date, end_date, first_name, last_name, assigned_department FROM interns WHERE id = ?');
$stmt->execute([$internId]);
$intern = $stmt->fetch();
if (!$intern) {
    json_fail('Stajyer bulunamadı.', 404);
}

if (is_mentor() && trim(mb_strtolower((string)($intern['assigned_department'] ?? ''))) !== trim(mb_strtolower($_SESSION['user_department'] ?? ''))) {
    json_fail('Bu stajyer için yoklama girmeye yetkiniz bulunmamaktadır.', 403);
}

/* Tüm değişiklikleri doğrula */
foreach ($changes as $date => $status) {
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        json_fail('Geçersiz tarih: ' . (string) $date);
    }
    if (!is_string($status) || ($status !== '' && !isset(ATTENDANCE_STATUSES[$status]))) {
        json_fail('Geçersiz durum.');
    }
    if ($date < $intern['start_date'] || $date > $intern['end_date']) {
        json_fail('Tarih staj dönemi dışında: ' . $date);
    }
    if ((int) (new DateTimeImmutable($date))->format('N') >= 6 || is_turkish_holiday($date)) {
        json_fail('Hafta sonu veya resmi tatil işaretlenemez: ' . $date);
    }
}

/* Uygula */
$pdo = db();
$pdo->beginTransaction();
try {
    $del = $pdo->prepare('DELETE FROM attendance WHERE intern_id = ? AND work_date = ?');
    $ins = $pdo->prepare(
        'INSERT INTO attendance (intern_id, work_date, status) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)'
    );
    ksort($changes);
    $parts = [];
    foreach ($changes as $date => $status) {
        if ($status === '') {
            $del->execute([$internId, $date]);
            $parts[] = date('d.m', strtotime($date)) . ': Geldi';
        } else {
            $ins->execute([$internId, $date, $status]);
            $parts[] = date('d.m', strtotime($date)) . ': ' . ATTENDANCE_STATUSES[$status];
        }
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    json_fail('Kaydedilemedi: ' . $ex->getMessage(), 500);
}

log_action('yoklama', sprintf(
    '%s %s — %d değişiklik (%s)',
    $intern['first_name'],
    $intern['last_name'],
    count($changes),
    implode(', ', $parts)
));

echo json_encode(['ok' => true, 'saved' => count($changes)], JSON_UNESCAPED_UNICODE);
