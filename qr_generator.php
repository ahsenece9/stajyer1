<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_role(['sistem_yoneticisi', 'kurum_staj_sorumlusu']);

$todayStr = date('Y-m-d');
$secretSalt = 'staj_takip_secret_salt';
$expectedCode = md5($todayStr . $secretSalt);

// Find the server address to construct local network checkin url
$serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$checkinUrl = 'http://' . $serverHost . '/stajyer/checkin.php?code=' . $expectedCode;
// Fallback if they are running in the root folder
if (strpos($_SERVER['REQUEST_URI'], '/stajyer/') === false) {
    $checkinUrl = 'http://' . $serverHost . '/checkin.php?code=' . $expectedCode;
}

$qrCodeApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($checkinUrl);

render_header('Günlük Yoklama QR Kodu', 'qr_generator');
?>
<div class="page-head">
    <div>
        <h1>Günlük Yoklama QR Kodu</h1>
        <p class="page-sub">Bu sayfayı ekrana yansıtarak stajyerlerin kendi mobil cihazlarıyla yoklama (Giriş/Çıkış) yapmalarını sağlayabilirsiniz.</p>
    </div>
</div>

<div class="card" style="max-width: 500px; margin: 0 auto; text-align: center; padding: 40px 20px;">
    <h2 style="margin-top:0; color:var(--primary); font-size:20px;">Günlük Hızlı Yoklama QR</h2>
    <p class="muted" style="font-size:13.5px; margin-bottom: 24px;">Bugünün tarihi: <b><?= e(date('d.m.Y')) ?></b></p>
    
    <div style="background: white; padding: 20px; border-radius: 16px; display: inline-block; box-shadow: var(--shadow-sm); border:1px solid var(--line-soft); margin-bottom: 24px;">
        <div id="qrcode" style="display:flex; justify-content:center; align-items:center; width:280px; height:280px; margin:0 auto; background:white;"></div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        function loadQR() {
            var container = document.getElementById("qrcode");
            if (!container) return;
            container.innerHTML = ""; // Clear loader
            
            // 1. Yol: Client-side JS Kütüphanesi ile üretmeyi dene
            if (typeof QRCode !== 'undefined') {
                new QRCode(container, {
                    text: "<?= $checkinUrl ?>",
                    width: 280,
                    height: 280,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            } else {
                // 2. Yol (Yedek): Google Chart API ile üretmeyi dene
                var img = document.createElement("img");
                img.src = "https://chart.googleapis.com/chart?cht=qr&chs=280x280&chl=<?= urlencode($checkinUrl) ?>";
                img.alt = "Yoklama QR Kodu";
                img.style.width = "280px";
                img.style.height = "280px";
                img.style.display = "block";
                img.style.margin = "0 auto";
                img.onerror = function() {
                    // 3. Yol (Son Çare Yedek): QR Server API ile dene
                    this.src = "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=<?= urlencode($checkinUrl) ?>";
                };
                container.appendChild(img);
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadQR);
        } else {
            loadQR();
        }
    </script>

    <div style="font-size:13px; text-align:left; background:var(--panel-bg); padding:16px; border-radius:10px; border:1px solid var(--line-soft); margin-bottom:20px;">
        <div style="margin-bottom:8px;">
            <strong>QR Linki (Tarayıcıdan da girilebilir):</strong>
            <div style="font-family: monospace; word-break:break-all; margin-top:4px; padding:6px; background:rgba(0,0,0,0.05); border-radius:4px; font-size:11px;">
                <a href="<?= e($checkinUrl) ?>" target="_blank" style="color:var(--primary); text-decoration:none;"><?= e($checkinUrl) ?></a>
            </div>
        </div>
        <p class="muted" style="margin:0; font-size:11.5px; line-height:1.4;">* Bu QR kod sadece bugün (<?= e(date('d.m.Y')) ?>) geçerlidir. Her gün otomatik olarak yeni bir güvenlik kodu üretilmektedir.</p>
    </div>

    <div style="display:flex; justify-content:center; gap:12px;">
        <a href="dashboard.php" class="btn btn-light"><span class="ms sm">arrow_back</span> Dashboard'a Dön</a>
    </div>
</div>
<?php render_footer(); ?>
