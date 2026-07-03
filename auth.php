<?php
declare(strict_types=1);

/**
 * Giriş zorunluluğu — şifresiz kimse hiçbir veri göremez.
 * Veri gösteren her sayfa bu dosyayı dahil eder.
 */
require_once __DIR__ . '/helpers.php';

if (empty($_SESSION['user_id'])) {
    redirect('login.php');
}
