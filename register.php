<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
    
    if ($fullName === '' || $username === '' || $password === '') {
        $error = 'Ad Soyad, Kullanıcı Adı ve Şifre alanları zorunludur.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Şifreler uyuşmuyor.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        // Check if username already exists
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Bu kullanıcı adı zaten kullanımda.';
        } else {
            // Insert user into DB
            $passHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (username, password_hash, full_name, email, role) VALUES (?, ?, ?, ?, \'birim_sorumlusu\')');
            $stmt->execute([$username, $passHash, $fullName, $email]);
            
            $userId = (int) db()->lastInsertId();
            
            // Log action
            log_action('kayit', $fullName . ' (' . $username . ')');
            
            // Auto log in
            $_SESSION['user_id']   = $userId;
            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_role'] = 'birim_sorumlusu';
            
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="tr">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Kurumsal Kayıt — Samsun Büyükşehir Belediyesi Staj Takip</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
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
                        "primary": "#004ac6",
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
                        "primary-container": "#2563eb",
                        "secondary-container": "#c7c3fe",
                        "inverse-surface": "#2d3133",
                        "on-error": "#ffffff",
                        "on-primary-fixed": "#00174b",
                        "inverse-on-surface": "#eff1f3",
                        "error": "#ba1a1a",
                        "secondary": "#5b598c",
                        "surface-tint": "#0053db"
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    fontFamily: {
                        "headline-xl": ["Inter"],
                        "body-sm": ["Inter"],
                        "label-md": ["Inter"],
                        "body-lg": ["Inter"],
                        "body-md": ["Inter"],
                        "headline-md": ["Inter"],
                        "headline-lg": ["Inter"],
                        "label-sm": ["Inter"]
                    },
                    fontSize: {
                        "headline-xl": ["36px", {"lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
                        "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "500"}],
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "headline-md": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                        "headline-lg": ["28px", {"lineHeight": "36px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
                        "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "600"}]
                    }
                }
            }
        }
    </script>
    <style>
        html {
            background-color: #dce7f0 !important;
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
        .register-cta {
            position: relative;
            overflow: hidden;
            background-color: #6c7a89;
            color: #ffffff;
            transition: background-color .35s ease, color .35s ease, box-shadow .35s ease, transform .15s ease;
        }
        .register-cta::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle 140px at var(--mx, 50%) var(--my, 50%),
                        rgba(15, 23, 42, 0.25) 0%, transparent 65%);
            opacity: 0;
            transition: opacity .4s ease;
            pointer-events: none;
        }
        .register-cta:hover {
            background-color: #ffffff;
            color: #6c7a89;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        }
        .register-cta:hover::before {
            opacity: 1;
        }
        .form-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .form-scrollbar::-webkit-scrollbar-thumb {
            background-color: var(--outline-variant);
            border-radius: 4px;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center overflow-hidden">

<!-- ==================== ANIMASYONLU ARKA PLAN (WebGL Shader - ANIMATION_74) ==================== -->
<canvas id="shader-canvas-ANIMATION_74" class="pointer-events-none"
        style="position:fixed; inset:0; width:100vw; height:100vh; display:block; z-index:0;"></canvas>
<script>
(function () {
    const canvas = document.getElementById('shader-canvas-ANIMATION_74');
    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (!gl) {
        canvas.style.background = 'radial-gradient(circle at 30% 20%, #eaf2fb 0%, #cdddec 70%)';
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

    vec3 bg      = vec3(0.85, 0.90, 0.95);
    vec3 accent1 = vec3(0.40, 0.60, 0.90);
    vec3 accent2 = vec3(0.60, 0.80, 1.00);
    vec3 accent3 = vec3(0.70, 0.75, 0.85);

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
            <div class="slideshow-img active absolute inset-0 w-full h-full bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1000&q=85')"></div>
            <!-- Slide 2 -->
            <div class="slideshow-img absolute inset-0 w-full h-full bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1531538606174-0f90ff5dce83?auto=format&fit=crop&w=1000&q=85')"></div>
            <!-- Slide 3 -->
            <div class="slideshow-img absolute inset-0 w-full h-full bg-cover bg-center" style="background-image: url('https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1000&q=85')"></div>
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
                    <h2 class="text-white font-headline-xl text-headline-xl mb-4 leading-tight">Kurumsal Kayıt ile Dahil Olun.</h2>
                    <p class="text-white/80 font-body-md text-body-md">Sorumlu olduğunuz birimdeki stajyerleri ve yoklama süreçlerini yönetmek için hemen kayıt olun.</p>
                    <div class="flex gap-2 mt-8">
                        <div class="h-1 w-12 rounded-full bg-white transition-all duration-300 slide-indicator" data-index="0"></div>
                        <div class="h-1 w-4 rounded-full bg-white/30 transition-all duration-300 slide-indicator" data-index="1"></div>
                        <div class="h-1 w-4 rounded-full bg-white/30 transition-all duration-300 slide-indicator" data-index="2"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Right Side: Registration Form -->
    <section class="w-full lg:w-1/2 glass-card p-6 md:p-10 flex flex-col justify-center overflow-y-auto form-scrollbar">
        <div class="max-w-md w-full mx-auto">
            <!-- Mobile Branding -->
            <div class="lg:hidden flex items-center gap-3 mb-8">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-lg overflow-hidden border border-outline-variant/30 flex-shrink-0">
                    <img src="<?= logo_url() ?>" style="width:80%; height:80%; object-fit:contain;">
                </div>
                <div class="flex flex-col justify-center">
                    <span class="text-primary text-[18px] font-extrabold tracking-tight leading-tight">Samsun Büyükşehir Belediyesi</span>
                    <span class="text-primary text-[18px] font-extrabold tracking-tight leading-tight">Staj Takip Sistemi</span>
                </div>
            </div>
            <div class="mb-6">
                <h1 class="font-headline-lg text-headline-lg text-on-background mb-1">Kurumsal Kayıt</h1>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Birim sorumlusu hesabı oluşturmak için bilgilerinizi girin.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="p-3 mb-4 text-xs text-red-800 rounded-xl bg-red-50 border border-red-200" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form class="space-y-4" id="register-form" method="post" autocomplete="off">
                <?= csrf_field() ?>
                
                <!-- Yetkili Adı Soyadı -->
                <div class="space-y-1">
                    <label class="block font-label-sm text-label-sm text-secondary" for="full_name">Yetkili Adı Soyadı</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">person</span>
                        <input class="w-full pl-12 pr-4 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" id="full_name" name="full_name" placeholder="Adınız ve Soyadınız" required type="text" value="<?= e($_POST['full_name'] ?? '') ?>"/>
                    </div>
                </div>

                <!-- Kullanıcı Adı -->
                <div class="space-y-1">
                    <label class="block font-label-sm text-label-sm text-secondary" for="username">Kullanıcı Adı</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">badge</span>
                        <input class="w-full pl-12 pr-4 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" id="username" name="username" placeholder="kullanıcı adınız" required type="text" value="<?= e($_POST['username'] ?? '') ?>"/>
                    </div>
                </div>

                <!-- E-posta Adresi -->
                <div class="space-y-1">
                    <label class="block font-label-sm text-label-sm text-secondary" for="email">E-posta Adresi</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">mail</span>
                        <input class="w-full pl-12 pr-4 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" id="email" name="email" placeholder="ornek@mail.com" type="email" value="<?= e($_POST['email'] ?? '') ?>"/>
                    </div>
                </div>

                <!-- Şifre -->
                <div class="space-y-1">
                    <label class="block font-label-sm text-label-sm text-secondary" for="password">Şifre</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">lock</span>
                        <input class="w-full pl-12 pr-12 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" id="password" name="password" placeholder="••••••••" required type="password"/>
                        <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors" type="button" id="toggle-password">
                            <span class="material-symbols-outlined text-[20px]">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- Şifre Tekrar -->
                <div class="space-y-1">
                    <label class="block font-label-sm text-label-sm text-secondary" for="password_confirm">Şifre Tekrar</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">lock_reset</span>
                        <input class="w-full pl-12 pr-12 py-2.5 bg-white border border-outline-variant rounded-xl text-on-surface font-body-sm focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none" id="password_confirm" name="password_confirm" placeholder="••••••••" required type="password"/>
                        <button class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors" type="button" id="toggle-password-confirm">
                            <span class="material-symbols-outlined text-[20px]">visibility</span>
                        </button>
                    </div>
                </div>

                <!-- CTA Button -->
                <button class="register-cta group w-full py-3.5 rounded-xl font-headline-md text-headline-md flex items-center justify-center gap-2 shadow-lg active:scale-[0.98] mt-6" id="register-btn" type="submit" style="--mx:50%; --my:50%;">
                    <span class="relative z-10">Kayıt Ol</span>
                    <span class="material-symbols-outlined relative z-10 transition-transform group-hover:translate-x-1">arrow_forward</span>
                </button>
            </form>
            
            <!-- Footer Links -->
            <div class="mt-8 text-center">
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Zaten bir hesabınız var mı? <a class="text-primary font-bold hover:underline" href="login.php">Giriş Yap</a>
                </p>
            </div>
        </div>
    </section>
</main>

<!-- Success Feedback Overlay -->
<?php if ($success): ?>
    <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl p-8 max-w-[420px] w-full text-center shadow-2xl mx-4">
            <span class="material-symbols-outlined text-green-500 text-[64px] mb-4" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            <h3 class="text-2xl font-bold text-gray-900 mb-2">Kayıt Başarılı!</h3>
            <p class="text-gray-600 mb-6">Birim sorumlusu hesabınız başarıyla oluşturuldu. Yönlendiriliyorsunuz...</p>
            <div class="flex justify-center">
                <svg class="animate-spin h-8 w-8 text-secondary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = 'dashboard.php';
        }, 2200);
    </script>
<?php endif; ?>

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

    // Form Submission Interaction
    const form = document.getElementById('register-form');
    form.addEventListener('submit', (e) => {
        <?php if (!$success): ?>
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span><span>Kayıt Yapılıyor...</span>';
        btn.disabled = true;
        setTimeout(() => {
            form.submit();
        }, 500);
        <?php endif; ?>
    });

    // Password visibility toggle (Şifre)
    const toggleBtn = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');
    toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        toggleBtn.querySelector('span').textContent = isPassword ? 'visibility_off' : 'visibility';
    });

    // Password visibility toggle (Şifre Tekrar)
    const toggleConfirmBtn = document.getElementById('toggle-password-confirm');
    const passwordConfirmInput = document.getElementById('password_confirm');
    toggleConfirmBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const isPassword = passwordConfirmInput.type === 'password';
        passwordConfirmInput.type = isPassword ? 'text' : 'password';
        toggleConfirmBtn.querySelector('span').textContent = isPassword ? 'visibility_off' : 'visibility';
    });

    // Kayıt Ol butonu: spot ışığını mouse konumuna göre güncelle
    const registerBtn = document.getElementById('register-btn');
    registerBtn.addEventListener('pointermove', (e) => {
        const r = registerBtn.getBoundingClientRect();
        registerBtn.style.setProperty('--mx', (e.clientX - r.left) + 'px');
        registerBtn.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
</script>
</body>
</html>
