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
            // Kayıt onay durumu kontrolü — sadece "active" kullanıcılar giriş yapabilir
            $status = $user['status'] ?? 'active';
            if ($status === 'pending') {
                log_action('giris_reddedildi', 'Onay bekleyen hesap giriş denedi: ' . $username, $user['full_name']);
                $error = 'Hesabınız henüz sistem yöneticisi tarafından onaylanmadı. Onaylandığında giriş yapabilirsiniz.';
            } elseif ($status === 'rejected') {
                log_action('giris_reddedildi', 'Reddedilmiş hesap giriş denedi: ' . $username, $user['full_name']);
                $error = 'Kayıt talebiniz reddedilmiştir. Lütfen sistem yöneticisiyle iletişime geçin.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id']   = (int) $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_department'] = $user['department'] ?? '';
                unset($_SESSION['login_attempts']);
                log_action('giris');
                redirect('dashboard.php');
            }
        } else {
            $_SESSION['login_attempts']++;
            log_action('giris_hatali', 'Denenen kullanıcı adı: ' . $username, $username !== '' ? $username : null);
            $error = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Samsun Büyükşehir Belediyesi Staj Takip Sistemi - Giriş Yap</title>
    <!-- Ortak tema scriptini yükle -->
    <script>
      (function() {
        var explicit = localStorage.getItem("theme-explicit");
        if (!explicit) {
            localStorage.setItem("theme", "corporate");
            localStorage.setItem("theme-explicit", "1");
        }
        var theme = localStorage.getItem("theme") || "corporate";
        var bgColors = {"dark": "#091324", "light": "#f7f9fb", "ocean": "#0f0d36", "rose": "#fff0f5", "corporate": "#f4f6fa"};
        document.documentElement.style.backgroundColor = bgColors[theme] || "#f4f6fa";
        document.documentElement.dataset.theme = theme;
        
        var bg = localStorage.getItem("theme-bg") || "none";
        if (bg !== "none") {
            document.documentElement.classList.add("has-custom-bg");
            var overlay = "rgba(9, 19, 36, 0.55)";
            if (theme === "light") overlay = "rgba(247, 249, 251, 0.45)";
            else if (theme === "ocean") overlay = "rgba(15, 13, 54, 0.35)";
            else if (theme === "rose") overlay = "rgba(255, 240, 245, 0.45)";
            else if (theme === "corporate") overlay = "rgba(13, 25, 52, 0.40)";
            var css = "body { background: linear-gradient(" + overlay + ", " + overlay + "), url('assets/img/backgrounds/" + theme + "_" + bg + ".png') !important; background-size: cover !important; background-attachment: fixed !important; background-position: center !important; }";
            var style = document.createElement("style");
            style.id = "dynamic-bg-style";
            style.appendChild(document.createTextNode(css));
            document.head.appendChild(style);
        }
      })();
    </script>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "secondary-fixed-dim": "#c4c1fb",
                        "on-background": "#191c1e",
                        "on-secondary-container": "#514f81",
                        "on-primary-fixed-variant": "#003ea8",
                        "background": "#f7f9fb",
                        "on-tertiary": "#ffffff",
                        "on-tertiary-container": "#f1eeff",
                        "on-primary": "#ffffff",
                        "on-secondary-fixed-variant": "#444173",
                        "surface-variant": "#e0e3e5",
                        "inverse-primary": "#b4c5ff",
                        "secondary-fixed": "#e3dfff",
                        "tertiary-fixed-dim": "#c0c1ff",
                        "tertiary-container": "#585be6",
                        "on-surface-variant": "#434655",
                        "tertiary": "#3e3fcc",
                        "surface-container-high": "#e6e8ea",
                        "primary": "#1A3673",
                        "outline": "#737686",
                        "error-container": "#ffdad6",
                        "primary-fixed-dim": "#b4c5ff",
                        "tertiary-fixed": "#e1e0ff",
                        "surface-container-low": "#f2f4f6",
                        "on-tertiary-fixed": "#07006c",
                        "surface-container-lowest": "#ffffff",
                        "surface-container": "#eceef0",
                        "primary-fixed": "#dbe1ff",
                        "on-surface": "#191c1e",
                        "surface-bright": "#f7f9fb",
                        "outline-variant": "#c3c6d7",
                        "primary-container": "#F58220",
                        "secondary-container": "#c7c3fe",
                        "inverse-surface": "#2d3133",
                        "on-error": "#ffffff",
                        "on-primary-fixed": "#00174b",
                        "inverse-on-surface": "#eff1f3",
                        "error": "#ba1a1a",
                        "secondary": "#5b598c",
                        "surface-tint": "#0053db",
                        "on-tertiary-fixed-variant": "#2f2ebe",
                        "on-primary-container": "#eeefff",
                        "on-secondary": "#ffffff",
                        "surface-container-highest": "#e0e3e5",
                        "surface": "#f7f9fb",
                        "surface-dim": "#d8dadc",
                        "on-secondary-fixed": "#181445",
                        "on-error-container": "#93000a"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "margin-mobile": "16px",
                        "sm": "12px",
                        "lg": "40px",
                        "md": "24px",
                        "base": "8px",
                        "gutter": "24px",
                        "xs": "4px",
                        "xl": "64px",
                        "margin-desktop": "32px"
                    },
                    "fontFamily": {
                        "headline-xl": ["Inter"],
                        "body-sm": ["Inter"],
                        "label-md": ["Inter"],
                        "body-lg": ["Inter"],
                        "body-md": ["Inter"],
                        "headline-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "label-sm": ["Inter"],
                        "headline-lg-mobile": ["Inter"]
                    },
                    "fontSize": {
                        "headline-xl": ["36px", {"lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "500"}],
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "headline-md": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                        "headline-lg": ["28px", {"lineHeight": "36px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}],
                        "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "600"}]
                    }
                },
            },
        }
    </script>
    <style>
        /* Shader arka planının görünmesi için sayfa arka planı şeffaf.
           Yeni shader AÇIK temalı olduğu için html'e açık bir yedek renk verildi. */
        html {
            background-color: #f4f6fa !important;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: transparent !important;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .slideshow-img {
            transition: opacity 1.5s ease-in-out;
            opacity: 0;
        }
        .slideshow-img.active {
            opacity: 1;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }
        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-entrance {
            animation: slideUpFade 0.8s ease-out forwards;
        }
        /* Giriş Yap butonu - imleci takip eden spot ışığı efekti */
        .login-cta {
            position: relative;
            overflow: hidden;
            background-color: #1A3673;               /* Kurumsal lacivert taban */
            color: #ffffff;                          /* Beyaz yazı */
            transition: background-color .35s ease, color .35s ease, box-shadow .35s ease, transform .15s ease;
        }
        .login-cta::before {
            content: "";
            position: absolute;
            inset: 0;
            /* imlecin olduğu yerde hafifçe koyulaşan daire */
            background: radial-gradient(circle 140px at var(--mx, 50%) var(--my, 50%),
                        rgba(245, 130, 32, 0.35) 0%, transparent 65%);
            opacity: 0;
            transition: opacity .4s ease;
            pointer-events: none;
        }
        .login-cta:hover {
            background-color: #F58220;                /* Hover'da kurumsal turuncu */
            color: #ffffff;
            box-shadow: 0 12px 28px rgba(245, 130, 32, 0.30);
        }
        .login-cta:hover::before {
            opacity: 1;                              /* spot ışığı yumuşakça belirir */
        }
        /* assets/style.css içindeki genel input[type=...] kuralı, özgüllük nedeniyle
           Tailwind'in pl-12/pr-12 sınıflarını eziyor ve ikonlarla metni üst üste bindiriyor.
           Bu formun input'larını burada üstünde tutuyoruz. */
        #login-form input[type="text"] { padding-left: 3rem !important; padding-right: 1rem !important; }
        #login-form input[type="password"] { padding-left: 3rem !important; padding-right: 3rem !important; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center overflow-hidden">

<!-- ==================== ANIMASYONLU ARKA PLAN (WebGL Shader - ANIMATION_74) ==================== -->
<!-- Boyut parent'tan DEĞİL doğrudan pencereden alınır; böylece Tailwind CDN'in
     sınıfları uygulamasını beklemez ve canvas asla 0x0 kalmaz. -->
<canvas id="shader-canvas-ANIMATION_74" class="pointer-events-none"
        style="position:fixed; inset:0; width:100vw; height:100vh; display:block; z-index:0;"></canvas>
<script>
(function () {
    const canvas = document.getElementById('shader-canvas-ANIMATION_74');
    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (!gl) {
        // WebGL yoksa açık tonlu düz bir yedek göster
        canvas.style.background = 'radial-gradient(circle at 30% 20%, #f4f6fa 0%, #d8e0ef 70%)';
        return;
    }

    function resize() {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const w = Math.max(1, Math.floor(window.innerWidth  * dpr));
        const h = Math.max(1, Math.floor(window.innerHeight * dpr));
        if (canvas.width !== w || canvas.height !== h) {
            canvas.width = w;
            canvas.height = h;
        }
    }
    resize();
    window.addEventListener('resize', resize);

    const vs = `attribute vec2 a_position;
varying vec2 v_texCoord;
void main() {
    v_texCoord = a_position * 0.5 + 0.5;
    gl_Position = vec4(a_position, 0.0, 1.0);
}`;

    const fs = `
#ifdef GL_FRAGMENT_PRECISION_HIGH
precision highp float;
#else
precision mediump float;
#endif
uniform float u_time;
uniform vec2 u_resolution;
uniform vec2 u_mouse;
varying vec2 v_texCoord;

vec3 permute(vec3 x) { return mod(((x * 34.0) + 1.0) * x, 289.0); }

float snoise(vec2 v) {
    const vec4 C = vec4(0.211324865405187, 0.366025403784439,
                       -0.577350269189626, 0.024390243902439);
    vec2 i  = floor(v + dot(v, C.yy));
    vec2 x0 = v - i + dot(i, C.xx);
    vec2 i1;
    i1 = (x0.x > x0.y) ? vec2(1.0, 0.0) : vec2(0.0, 1.0);
    vec4 x12 = x0.xyxy + C.xxzz;
    x12.xy -= i1;
    i = mod(i, 289.0);
    vec3 p = permute(permute(i.y + vec3(0.0, i1.y, 1.0))
           + i.x + vec3(0.0, i1.x, 1.0));
    vec3 m = max(0.5 - vec3(dot(x0, x0), dot(x12.xy, x12.xy),
                            dot(x12.zw, x12.zw)), 0.0);
    m = m * m;
    m = m * m;
    vec3 x = 2.0 * fract(p * C.www) - 1.0;
    vec3 h = abs(x) - 0.5;
    vec3 a0 = x - floor(x + 0.5);
    vec3 g = a0.x * vec3(x0.x, x12.xz) + h.x * vec3(x0.y, x12.yw);
    return 130.0 * dot(m, g);
}

void main() {
    vec2 uv = v_texCoord;
    vec2 st = uv * vec2(u_resolution.x / max(u_resolution.y, 1.0), 1.0);

    // Kurumsal (lacivert + turuncu) açık palet
    vec3 bg      = vec3(0.87, 0.90, 0.95); // Çok yumuşak lacivert-gri
    vec3 accent1 = vec3(0.35, 0.45, 0.65); // Yumuşak lacivert
    vec3 accent2 = vec3(0.96, 0.65, 0.35); // Yumuşak turuncu
    vec3 accent3 = vec3(0.75, 0.78, 0.85); // Açık orta ton lacivert-gri

    float n = snoise(st * 0.3 + u_time * 0.02);
    n += 0.3 * snoise(st * 0.7 - u_time * 0.03);

    float strength = smoothstep(-1.0, 1.0, n);

    vec3 color = mix(bg, accent3, strength * 0.6);
    color = mix(color, accent1 * 0.4, smoothstep(0.1, 0.8, strength));
    color = mix(color, accent2 * 0.2, smoothstep(0.4, 1.0, strength));

    float streaks = snoise(vec2(st.x * 0.05, st.y * 0.8 + u_time * 0.05));
    color += accent2 * pow(max(0.0, streaks), 3.0) * 0.05;

    float dist = distance(uv, u_mouse / max(u_resolution, vec2(1.0)));
    float glow = exp(-dist * 4.0);
    color += accent1 * glow * 0.05;

    gl_FragColor = vec4(color, 1.0);
}`;

    function compile(type, src) {
        const s = gl.createShader(type);
        gl.shaderSource(s, src);
        gl.compileShader(s);
        if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
            console.error('Shader derleme hatası:', gl.getShaderInfoLog(s));
            gl.deleteShader(s);
            return null;
        }
        return s;
    }

    const vShader = compile(gl.VERTEX_SHADER, vs);
    const fShader = compile(gl.FRAGMENT_SHADER, fs);
    if (!vShader || !fShader) return;

    const prog = gl.createProgram();
    gl.attachShader(prog, vShader);
    gl.attachShader(prog, fShader);
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
        console.error('Program link hatası:', gl.getProgramInfoLog(prog));
        return;
    }
    gl.useProgram(prog);

    const buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);

    const pos = gl.getAttribLocation(prog, 'a_position');
    gl.enableVertexAttribArray(pos);
    gl.vertexAttribPointer(pos, 2, gl.FLOAT, false, 0, 0);

    const uTime  = gl.getUniformLocation(prog, 'u_time');
    const uRes   = gl.getUniformLocation(prog, 'u_resolution');
    const uMouse = gl.getUniformLocation(prog, 'u_mouse');

    let mouse = { x: canvas.width / 2, y: canvas.height / 2 };
    window.addEventListener('mousemove', (event) => {
        const rect = canvas.getBoundingClientRect();
        if (rect.width && rect.height) {
            mouse.x = (event.clientX - rect.left) / rect.width * canvas.width;
            mouse.y = (1.0 - (event.clientY - rect.top) / rect.height) * canvas.height;
        }
    });

    const start = performance.now();
    function render() {
        gl.viewport(0, 0, canvas.width, canvas.height);
        const t = (performance.now() - start) * 0.001;
        gl.uniform1f(uTime, t);
        gl.uniform2f(uRes, canvas.width, canvas.height);
        gl.uniform2f(uMouse, mouse.x, mouse.y);
        gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
        requestAnimationFrame(render);
    }
    render();
})();
</script>
<!-- ==================== / ANIMASYONLU ARKA PLAN ==================== -->

<!-- Main Container -->
<main class="relative z-10 w-full max-w-5xl mx-4 lg:mx-auto h-[90vh] max-h-[620px] flex shadow-2xl rounded-3xl overflow-hidden bg-white/10 backdrop-blur-sm animate-entrance">
    <!-- Left Side: Slideshow -->
    <section class="hidden lg:flex w-1/2 relative overflow-hidden">
        <div class="absolute inset-0 w-full h-full" id="slideshow-container">
            <!-- Slide 1 -->
            <div class="slideshow-img active absolute inset-0 w-full h-full bg-cover bg-center" data-alt="A modern tech office interior" style="background-image: url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1000&q=85')">
            </div>
            <!-- Slide 2 -->
            <div class="slideshow-img absolute inset-0 w-full h-full bg-cover bg-center" data-alt="A close-up shot of interns" style="background-image: url('https://images.unsplash.com/photo-1531538606174-0f90ff5dce83?auto=format&fit=crop&w=1000&q=85')">
            </div>
            <!-- Slide 3 -->
            <div class="slideshow-img absolute inset-0 w-full h-full bg-cover bg-center" data-alt="A futuristic co-working space" style="background-image: url('https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1000&q=85')">
            </div>
            <!-- Overlay Gradient -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
            <!-- Branding Overlay -->
            <div class="absolute inset-0 p-12 flex flex-col justify-between z-20">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-white rounded-2xl flex items-center justify-center shadow-lg overflow-hidden flex-shrink-0">
                        <img src="<?= logo_url() ?>" style="width:80%; height:80%; object-fit:contain;">
                    </div>
                    <div class="flex flex-col justify-center">
                        <span class="text-white text-[22px] font-extrabold tracking-tight leading-tight">Samsun Büyükşehir Belediyesi</span>
                        <span class="text-white text-[22px] font-extrabold tracking-tight leading-tight">Staj Takip Sistemi</span>
                    </div>
                </div>
                <div class="max-w-md">
                    <h2 class="text-white font-headline-xl text-headline-xl mb-4 leading-tight">Geleceğin Yeteneklerini Bugün Yönetin.</h2>
                    <p class="text-white/80 font-body-md text-body-md">Kapsamlı stajyer takip sistemi ile operasyonel verimliliğinizi artırın, potansiyeli başarıya dönüştürün.</p>
                    <div class="flex gap-2 mt-8">
                        <div class="h-1 w-12 rounded-full bg-white transition-all duration-300 slide-indicator" data-index="0"></div>
                        <div class="h-1 w-4 rounded-full bg-white/30 transition-all duration-300 slide-indicator" data-index="1"></div>
                        <div class="h-1 w-4 rounded-full bg-white/30 transition-all duration-300 slide-indicator" data-index="2"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Right Side: Login Form -->
    <section class="w-full lg:w-1/2 glass-card p-8 md:p-16 flex flex-col justify-center">
        <div class="max-w-md w-full mx-auto">
            <!-- Mobile Branding -->
            <div class="lg:hidden flex items-center gap-3 mb-12">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-lg overflow-hidden border border-outline-variant/30 flex-shrink-0">
                    <img src="<?= logo_url() ?>" style="width:80%; height:80%; object-fit:contain;">
                </div>
                <div class="flex flex-col justify-center">
                    <span class="text-primary text-[18px] font-extrabold tracking-tight leading-tight">Samsun Büyükşehir Belediyesi</span>
                    <span class="text-primary text-[18px] font-extrabold tracking-tight leading-tight">Staj Takip Sistemi</span>
                </div>
            </div>
            <div class="mb-10">
                <h1 class="font-headline-lg text-headline-lg text-on-background mb-2">Hoş Geldiniz</h1>
                <p class="font-body-md text-body-md text-on-surface-variant">Yönetim paneline erişmek için lütfen giriş yapın.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="p-4 mb-6 text-sm text-red-800 rounded-xl bg-red-50 border border-red-200" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form class="space-y-6" id="login-form" method="post" autocomplete="off">
                <?= csrf_field() ?>
                <!-- Kullanıcı Adı -->
                <div class="space-y-2">
                    <label class="block font-label-sm text-label-sm text-secondary" for="username">Kullanıcı Adı</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">person</span>
                        <input class="w-full pl-12 pr-4 py-3 bg-white border border-outline-variant rounded-xl text-on-surface font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" id="username" name="username" placeholder="kullanıcı adınız" required="" type="text" value="<?= e($_POST['username'] ?? '') ?>"/>
                    </div>
                </div>
                <!-- Şifre -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <label class="block font-label-sm text-label-sm text-secondary" for="password">Şifre</label>
                        <a class="text-primary font-label-sm text-label-sm hover:underline" href="#">Şifremi Unuttum</a>
                    </div>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">lock</span>
                        <input class="w-full pl-12 pr-12 py-3 bg-white border border-outline-variant rounded-xl text-on-surface font-body-md focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" id="password" name="password" placeholder="••••••••" required="" type="password"/>
                        <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors" type="button" id="toggle-password">
                            <span class="material-symbols-outlined text-[20px]">visibility</span>
                        </button>
                    </div>
                </div>
                <!-- Beni Hatırla -->
                <div class="flex items-center gap-3">
                    <input class="w-5 h-5 rounded border-outline-variant text-primary focus:ring-primary" id="remember" type="checkbox"/>
                    <label class="font-body-sm text-body-sm text-on-surface-variant select-none" for="remember">Beni Hatırla</label>
                </div>
                <!-- CTA Button -->
                <button class="login-cta group w-full py-4 rounded-xl font-headline-md text-headline-md flex items-center justify-center gap-2 shadow-lg active:scale-[0.98]" id="login-btn" type="submit" style="--mx:50%; --my:50%;">
                    <span class="relative z-10">Giriş Yap</span>
                    <span class="material-symbols-outlined relative z-10 transition-transform group-hover:translate-x-1">arrow_forward</span>
                </button>
            </form>
            <!-- Footer Links -->
            <div class="mt-12 text-center">
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Henüz bir hesabınız yok mu? <a class="text-primary font-bold hover:underline" href="register.php">Kurumsal Kayıt</a>
                </p>
            </div>
        </div>
    </section>
</main>
<!-- Success Feedback (Hidden by default) -->
<div class="fixed bottom-8 right-8 bg-tertiary text-on-tertiary px-6 py-4 rounded-2xl shadow-xl flex items-center gap-4 translate-y-24 transition-transform duration-500 z-50" id="success-feedback">
    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">check_circle</span>
    <span class="font-body-md text-body-md">Başarıyla giriş yapıldı. Yönlendiriliyorsunuz...</span>
</div>
<script>
    // Slideshow Logic
    const slides = document.querySelectorAll('.slideshow-img');
    const indicators = document.querySelectorAll('.slide-indicator');
    let currentSlide = 0;

    function showSlide(index) {
        slides.forEach((slide, i) => {
            if (i === index) {
                slide.classList.add('active');
                indicators[i].classList.add('w-12', 'bg-white');
                indicators[i].classList.remove('w-4', 'bg-white/30');
            } else {
                slide.classList.remove('active');
                indicators[i].classList.remove('w-12', 'bg-white');
                indicators[i].classList.add('w-4', 'bg-white/30');
            }
        });
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % slides.length;
        showSlide(currentSlide);
    }

    setInterval(nextSlide, 4500);

    // Form Submission Interaction with real PHP POST redirection
    const form = document.getElementById('login-form');
    const feedback = document.getElementById('success-feedback');

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const originalContent = btn.innerHTML;
        
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span><span>Giriş Yapılıyor...</span>';
        btn.disabled = true;

        // Perform actual form submission after animation
        setTimeout(() => {
            form.submit();
        }, 500);
    });

    // Password visibility toggle
    const toggleBtn = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');
    
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        toggleBtn.querySelector('span').textContent = isPassword ? 'visibility_off' : 'visibility';
    });

    // Giriş Yap butonu: spot ışığını mouse konumuna göre güncelle
    const loginBtn = document.getElementById('login-btn');
    loginBtn.addEventListener('pointermove', (e) => {
        const r = loginBtn.getBoundingClientRect();
        loginBtn.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        loginBtn.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
</script>
</body>
</html>
