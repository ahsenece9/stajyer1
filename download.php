<?php
declare(strict_types=1);

/**
 * Belge indirme — sadece giriş yapmış yetkililer indirebilir.
 */
require_once __DIR__ . '/auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = db()->prepare('SELECT * FROM intern_documents WHERE id = ?');
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc || !preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $doc['filename'])) {
    http_response_code(404);
    exit('Belge bulunamadı.');
}

$path = UPLOAD_DIR . '/docs/' . $doc['filename'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Dosya sunucuda bulunamadı.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($doc['orig_name']));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
