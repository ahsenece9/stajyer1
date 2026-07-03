<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Basit kaba kuvvet önlemi: art arda hatalı denemelerde bekletme
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0);
    if ($_SESSION['login_attempts'] >= 5) {
        sleep(3);
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre zorunludur.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int) $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            unset($_SESSION['login_attempts']);
            log_action('giris');
            redirect('dashboard.php');
        }

        $_SESSION['login_attempts']++;
        log_action('giris_hatali', 'Denenen kullanıcı adı: ' . $username);
        $error = 'Kullanıcı adı veya şifre hatalı.';
    }
}

render_head('Giriş');
?>
<body class="auth-body">
<button type="button" class="theme-toggle" id="themeToggle" title="Tema değiştir"
        style="position:fixed; top:20px; right:20px; z-index:60;">
    <span class="ms" id="themeIcon">light_mode</span>
</button>
<div class="auth-split">
    <section class="auth-left">
        <div class="auth-brand">
            <span class="sb-logo sb-logo--img"><img src="assets/logo.png" alt="Samsun Büyükşehir Belediyesi"></span>
            <b>Staj Takip</b>
        </div>
        <div class="auth-slogan">
            <h1>Geleceğin Yeteneklerini Keşfedin</h1>
            <p>Staj yönetim süreçlerinizi modernize edin; stajyer kayıtlarını, yoklamaları ve raporları tek panelden yönetin.</p>
        </div>
        <div class="auth-meta">
            <div>Platform<b>Staj Takip Sistemi</b></div>
            <div>Erişim<b>Sadece Yetkililer</b></div>
        </div>
    </section>

    <section class="auth-right">
        <div class="auth-right-inner">
            <h2>Sisteme Giriş</h2>
            <p class="lead">Yönetim paneline erişmek için kimlik bilgilerinizi kullanın.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <label>Kullanıcı Adı
                    <span class="input-icon" style="display:block; position:relative;">
                        <span class="ms">person</span>
                        <input type="text" name="username" required autofocus
                               placeholder="kullanıcı adınız"
                               value="<?= e($_POST['username'] ?? '') ?>">
                    </span>
                </label>
                <label>Şifre
                    <span class="input-icon" style="display:block; position:relative;">
                        <span class="ms">lock</span>
                        <input type="password" name="password" required placeholder="••••••••">
                    </span>
                </label>
                <button type="submit" class="btn btn-primary btn-block">
                    Giriş Yap <span class="ms sm">arrow_forward</span>
                </button>
            </form>

            <div class="auth-foot">
                <span class="auth-secure"><span class="ms">verified_user</span> Şifresiz erişim kapalıdır</span>
            </div>
        </div>
    </section>
</div>
<script>
(function () {
    var btn = document.getElementById("themeToggle");
    var icon = document.getElementById("themeIcon");
    function sync() {
        var t = document.documentElement.dataset.theme || "dark";
        icon.textContent = t === "dark" ? "light_mode" : "dark_mode";
        if (window.renderIcons) window.renderIcons(btn);
    }
    sync();
    btn.addEventListener("click", function () {
        var next = (document.documentElement.dataset.theme === "dark") ? "light" : "dark";
        document.documentElement.dataset.theme = next;
        localStorage.setItem("theme", next);
        sync();
    });
})();
</script>
</body>
</html>
