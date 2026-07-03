<?php
declare(strict_types=1);
require_once __DIR__ . '/../helpers.php';
$db = db();

try {
    $hash = password_hash('sifre12345', PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password_hash = ? WHERE username = 'ahsenece'")->execute([$hash]);
    echo "Password reset successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
