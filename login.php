<?php
ob_start();
session_start();
require_once ("./config.php");
require_once ("./models/DB.php");
require_once ("./models/Entity.php");
require_once ("./models/File.php");
$active = isset($_GET['p']) ? $_GET['p'] : 'dashboard';
$menu = [
    ['name' => "dashboard", 'title' => 'Dashboard', 'link' => '.?p=dashboard', 'icon' => 'fa fa-cog'],
    ['name' => "editor", 'title' => 'Editor', 'link' => '.?p=editor', 'icon' => 'fa fa-cog'],
    ['name' => "sploads", 'title' => 'Uploads', 'link' => '.?p=uploads', 'icon' => 'fa fa-cog'],
    ['name' => "settings", 'title' => 'Settings', 'link' => '.?p=settings', 'icon' => 'fa fa-cog'],
];
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <base href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>" />
    <title>File Bridge — Sign in</title>
    <meta name="description" content="TheBridge File Manager">
    <meta name="author" content="TheBridge TechCreative">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --amber-300: #ffc97a;
            --amber-400: #ffb24d;
            --amber-500: #f08c00;
            --amber-600: #d97706;
            --ink-950: #0b0907;
            --ink-900: #110e0b;
            --ink-850: #17130f;
            --ink-800: #1f1a15;
            --ink-700: #2a241d;
            --line: rgba(240, 140, 0, 0.16);
            --text: #f6efe5;
            --muted: #9c9389;
            --font-display: 'Bricolage Grotesque', 'Segoe UI', sans-serif;
            --font-body: 'Hanken Grotesk', 'Segoe UI', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            font-family: var(--font-body);
            color: var(--text);
            background-color: var(--ink-950);
            background-image:
                radial-gradient(900px 600px at 12% -10%, rgba(240, 140, 0, 0.16), transparent 60%),
                radial-gradient(700px 500px at 100% 110%, rgba(217, 119, 6, 0.12), transparent 55%),
                radial-gradient(1200px 800px at 50% 50%, rgba(255, 178, 77, 0.04), transparent 70%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px 120px;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Fine grain overlay for depth */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            opacity: 0.04;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* Floating amber embers */
        .particles { position: fixed; inset: 0; overflow: hidden; z-index: 0; pointer-events: none; }
        .particle {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, var(--amber-300), transparent 70%);
            filter: blur(0.5px);
            opacity: 0;
            animation: drift linear infinite;
        }
        @keyframes drift {
            0%   { transform: translateY(20px) scale(0.6); opacity: 0; }
            15%  { opacity: 0.5; }
            85%  { opacity: 0.4; }
            100% { transform: translateY(-140px) scale(1); opacity: 0; }
        }

        /* ---------- Card ---------- */
        .login-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1040px;
            animation: cardIn 0.9s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(34px) scale(0.985); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-box {
            display: flex;
            border-radius: 28px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(31, 26, 21, 0.86), rgba(17, 14, 11, 0.92));
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.05) inset,
                0 40px 90px -30px rgba(0, 0, 0, 0.85),
                0 0 0 1px var(--line);
        }

        /* ---------- Brand panel ---------- */
        .login-side {
            position: relative;
            width: 45%;
            padding: 3.4em 3em;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #fff;
            background:
                linear-gradient(155deg, var(--amber-400) 0%, var(--amber-500) 48%, var(--amber-600) 100%);
            overflow: hidden;
            isolation: isolate;
        }
        /* Blueprint grid */
        .login-side::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.10) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.10) 1px, transparent 1px);
            background-size: 26px 26px;
            mask-image: radial-gradient(120% 90% at 70% 20%, #000 35%, transparent 80%);
            opacity: 0.6;
            z-index: -1;
        }
        /* Soft light bloom */
        .login-side::after {
            content: '';
            position: absolute;
            width: 360px; height: 360px;
            top: -130px; right: -120px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.35), transparent 65%);
            border-radius: 50%;
            z-index: -1;
            animation: bloom 7s ease-in-out infinite;
        }
        @keyframes bloom { 0%,100% { transform: scale(1); opacity: 0.7; } 50% { transform: scale(1.12); opacity: 0.95; } }

        .side-top { display: flex; justify-content: flex-end; }
        .version-badge {
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 0.72rem;
            letter-spacing: 0.12em;
            color: #fff;
            padding: 0.45em 0.9em;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            backdrop-filter: blur(6px);
        }

        .brand-block { animation: riseIn 0.8s ease-out 0.25s both; }
        .logo-ring {
            width: 92px; height: 92px;
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 18px 40px -12px rgba(0, 0, 0, 0.45);
            margin-bottom: 1.5rem;
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .logo-ring:hover { transform: translateY(-4px) rotate(-4deg); }
        .logo-ring img { width: 56px; height: auto; filter: brightness(0) invert(1); }

        .brand-name {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 2.7rem;
            line-height: 0.98;
            letter-spacing: -0.01em;
            text-shadow: 0 6px 22px rgba(120, 60, 0, 0.35);
        }
        .brand-name span { display: block; }
        .brand-tagline {
            margin-top: 0.7rem;
            font-size: 0.98rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.92);
            max-width: 16em;
        }

        .features { list-style: none; display: grid; gap: 0.85rem; }
        .features li {
            display: flex; align-items: center; gap: 0.8rem;
            font-size: 0.92rem; font-weight: 500;
            color: rgba(255, 255, 255, 0.95);
            animation: riseIn 0.7s ease-out both;
        }
        .features li:nth-child(1) { animation-delay: 0.45s; }
        .features li:nth-child(2) { animation-delay: 0.55s; }
        .features li:nth-child(3) { animation-delay: 0.65s; }
        .features .ico {
            flex: 0 0 auto;
            width: 34px; height: 34px;
            display: grid; place-items: center;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.25);
            font-size: 0.9rem;
        }
        @keyframes riseIn { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

        /* ---------- Form panel ---------- */
        .login-form { width: 55%; padding: 3.6em 3.4em; }

        .form-head { margin-bottom: 2.2rem; }
        .login-title {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 2.4rem;
            line-height: 1.05;
            letter-spacing: -0.02em;
            background: linear-gradient(100deg, #fff 30%, var(--amber-400));
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .login-subtitle { color: var(--muted); font-size: 0.97rem; margin-top: 0.5rem; }

        .form-group { margin-bottom: 1.4rem; animation: riseIn 0.7s ease-out both; }
        #loginForm .form-group:nth-of-type(1) { animation-delay: 0.30s; }
        #loginForm .form-group:nth-of-type(2) { animation-delay: 0.40s; }
        .form-options { animation: riseIn 0.7s ease-out 0.5s both; }
        .btn-row { animation: riseIn 0.7s ease-out 0.58s both; }
        .secure-note { animation: riseIn 0.7s ease-out 0.66s both; }

        .form-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.55rem;
        }

        .input-wrapper { position: relative; }
        .form-control {
            width: 100%;
            font-family: var(--font-body);
            font-size: 1rem;
            color: var(--text);
            background: var(--ink-850);
            border: 1.5px solid rgba(255, 255, 255, 0.07);
            border-radius: 14px;
            padding: 15px 48px 15px 48px;
            transition: border-color 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
        }
        .form-control::placeholder { color: #6f675e; }
        .form-control:focus {
            outline: none;
            background: var(--ink-800);
            border-color: var(--amber-500);
            box-shadow: 0 0 0 4px rgba(240, 140, 0, 0.14);
        }
        .input-icon {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%);
            color: #7d756b;
            font-size: 1rem;
            pointer-events: none;
            transition: color 0.25s ease;
        }
        .form-control:focus ~ .input-icon { color: var(--amber-400); }

        .password-toggle {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            color: #7d756b;
            cursor: pointer;
            font-size: 1rem;
            padding: 6px;
            border-radius: 8px;
            transition: color 0.2s ease, background 0.2s ease;
        }
        .password-toggle:hover { color: var(--amber-400); background: rgba(240, 140, 0, 0.08); }

        .form-options {
            display: flex; align-items: center; justify-content: space-between;
            margin: 0.3rem 0 1.8rem;
        }
        .remember-me { display: flex; align-items: center; gap: 0.6rem; cursor: pointer; color: var(--muted); font-size: 0.9rem; user-select: none; }
        .remember-me input { position: absolute; opacity: 0; width: 0; height: 0; }
        .checkmark {
            width: 19px; height: 19px;
            border-radius: 6px;
            border: 1.5px solid rgba(255, 255, 255, 0.18);
            background: var(--ink-850);
            display: grid; place-items: center;
            transition: all 0.2s ease;
        }
        .checkmark::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; font-size: 0.62rem; color: #fff; opacity: 0; transform: scale(0.5); transition: all 0.2s ease; }
        .remember-me input:checked ~ .checkmark { background: linear-gradient(135deg, var(--amber-400), var(--amber-600)); border-color: transparent; }
        .remember-me input:checked ~ .checkmark::after { opacity: 1; transform: scale(1); }
        .remember-me:hover .checkmark { border-color: var(--amber-500); }
        .remember-me input:focus-visible ~ .checkmark { box-shadow: 0 0 0 3px rgba(240, 140, 0, 0.25); }

        .forgot-password { color: var(--amber-400); text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.2s ease; }
        .forgot-password:hover { color: var(--amber-300); }

        .btn-custom {
            position: relative;
            width: 100%;
            border: none;
            cursor: pointer;
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.04em;
            color: #2a1500;
            padding: 16px 28px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--amber-300) 0%, var(--amber-500) 55%, var(--amber-600) 100%);
            box-shadow: 0 12px 30px -10px rgba(240, 140, 0, 0.6), 0 1px 0 rgba(255, 255, 255, 0.35) inset;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }
        .btn-custom span { position: relative; z-index: 2; display: inline-flex; align-items: center; gap: 0.55rem; }
        .btn-custom::before {
            content: ''; position: absolute; inset: 0; left: -120%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.55), transparent);
            transition: left 0.6s ease; z-index: 1;
        }
        .btn-custom:hover { transform: translateY(-2px); filter: brightness(1.04); box-shadow: 0 18px 38px -10px rgba(240, 140, 0, 0.7), 0 1px 0 rgba(255, 255, 255, 0.4) inset; }
        .btn-custom:hover::before { left: 120%; }
        .btn-custom:active { transform: translateY(0); }

        .secure-note {
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            margin-top: 1.6rem;
            color: #6f675e; font-size: 0.8rem;
        }
        .secure-note i { color: var(--amber-500); }

        /* Loading state */
        .btn-custom.loading { pointer-events: none; }
        .btn-custom.loading span { opacity: 0; }
        .btn-custom.loading::after {
            content: ''; position: absolute; width: 18px; height: 18px;
            top: 50%; left: 50%; margin: -9px 0 0 -9px;
            border: 2.5px solid rgba(42, 21, 0, 0.35); border-top-color: #2a1500;
            border-radius: 50%; animation: spin 0.6s linear infinite; z-index: 3;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ---------- Footer ---------- */
        .footer {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 50;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1rem 2rem;
            font-size: 0.82rem;
            color: var(--muted);
            background: rgba(11, 9, 7, 0.7);
            backdrop-filter: blur(12px);
            border-top: 1px solid var(--line);
        }
        .footer-left, .footer-right { flex: 1; }
        .footer-right { text-align: right; }
        .footer-center { flex: 2; text-align: center; line-height: 1.5; }
        .footer a { color: var(--amber-400); text-decoration: none; font-weight: 600; transition: color 0.2s ease; }
        .footer a:hover { color: var(--amber-300); }
        .footer-icons { margin-top: 0.3rem; }
        .footer-icons a { margin: 0 0.45em; font-size: 1rem; opacity: 0.75; display: inline-block; transition: all 0.2s ease; }
        .footer-icons a:hover { opacity: 1; transform: translateY(-2px); }

        /* ---------- Responsive ---------- */
        @media (max-width: 900px) {
            .login-box { flex-direction: column; }
            .login-side, .login-form { width: 100%; }
            .login-side { padding: 2.4em 2em; gap: 1.8rem; }
            .login-side::before { mask-image: radial-gradient(140% 120% at 70% 0%, #000 40%, transparent 85%); }
            .brand-name { font-size: 2.1rem; }
            .features { grid-template-columns: 1fr 1fr; }
            .login-form { padding: 2.6em 2em; }
        }
        @media (max-width: 560px) {
            body { padding: 18px 14px 150px; }
            .login-box { border-radius: 20px; }
            .login-side { padding: 2em 1.5em; }
            .features { grid-template-columns: 1fr; }
            .login-form { padding: 2em 1.5em; }
            .login-title { font-size: 1.9rem; }
            .footer { flex-direction: column; gap: 0.4rem; padding: 0.9em 1em; text-align: center; }
            .footer-left, .footer-right, .footer-center { flex: none; width: 100%; text-align: center; }
            .particles { display: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>

<body>
<?php
if(isset($_POST['login'])) {
    $username =  isset($_POST['username']) ? $_POST['username'] : null;
    $password =  isset($_POST['password']) ? $_POST['password'] : null;
    $password = md5($password);
    if(!is_null($username) && !is_null($username)) {
        $db = new DB();
        $login  = $db->query("SELECT * FROM users WHERE username = ? AND password = ?", "SELECT", true, [$username, $password]);
        if($login) {
            $_SESSION[SESSION_NAME]['user_id'] = $login['id'];
            $_SESSION[SESSION_NAME]['user'] = serialize($login);
            header("Location: ./?p=dashboard");
            echo "<script>toastr['success']('Login Successful !')</script>";
        } else {
            echo "<script>toastr['error']('Wrong Username or password !')</script>";
        }
    }
}

if(isset($_GET['logout'])) {
    session_destroy();
    header("Location ./login.php");
}
?>

<div class="particles" id="particles"></div>

<div class="login-container">
    <div class="login-box">
        <aside class="login-side">
            <div class="side-top">
                <span class="version-badge">V3.0</span>
            </div>

            <div class="brand-block">
                <div class="logo-ring">
                    <img src="assets/img/bridge_logo.png" alt="File Bridge Logo" />
                </div>
                <div class="brand-name"><span>File</span><span>Bridge</span></div>
                <p class="brand-tagline">Next-generation document management & secure digital archiving.</p>
            </div>

            <ul class="features">
                <li><span class="ico"><i class="fas fa-shield-alt"></i></span> Bank-grade access control</li>
                <li><span class="ico"><i class="fas fa-folder-open"></i></span> Centralized, organized archive</li>
                <li><span class="ico"><i class="fas fa-bolt"></i></span> Instant search & retrieval</li>
            </ul>
        </aside>

        <main class="login-form">
            <div class="form-head">
                <h1 class="login-title">Welcome back</h1>
                <p class="login-subtitle">Sign in to access your document workspace.</p>
            </div>

            <form name="loginForm" method="post" id="loginForm" novalidate>
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required autocomplete="username" />
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password" />
                        <i class="fas fa-lock input-icon"></i>
                        <i class="fas fa-eye password-toggle" id="togglePassword" role="button" tabindex="0" aria-label="Show password"></i>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" id="rememberMe" name="remember_me" />
                        <span class="checkmark"></span>
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>

                <div class="btn-row">
                    <button type="submit" name="login" class="btn-custom" id="loginBtn">
                        <span id="btnText">Sign in <i class="fas fa-arrow-right"></i></span>
                    </button>
                </div>

                <p class="secure-note"><i class="fas fa-lock"></i> Secured connection · Your session is encrypted</p>
            </form>
        </main>
    </div>
</div>

<footer class="footer">
    <div class="footer-left">
        <span>Version 3.0</span>
    </div>
    <div class="footer-center">
        <span>&copy; <?php echo date('Y'); ?> File Bridge System. All rights reserved.</span><br>
        <span>Powered By <a href="https://moinfo.co.tz" target="_blank" rel="noopener">MoinfoTech Company Limited</a></span>
        <div class="footer-icons">
            <a href="#" title="Documentation"><i class="fas fa-book"></i></a>
            <a href="https://moinfo.co.tz" target="_blank" rel="noopener" title="Support"><i class="fas fa-headset"></i></a>
            <a href="#" title="Settings"><i class="fas fa-cog"></i></a>
        </div>
    </div>
    <div class="footer-right">
        <a href="#">Terms of Service</a> | <a href="#">Privacy Policy</a>
    </div>
</footer>

<script src="node_modules/jquery/dist/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
    // Password visibility toggle
    (function () {
        const toggle = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        function flip() {
            const isPwd = password.getAttribute('type') === 'password';
            password.setAttribute('type', isPwd ? 'text' : 'password');
            toggle.classList.toggle('fa-eye');
            toggle.classList.toggle('fa-eye-slash');
            toggle.setAttribute('aria-label', isPwd ? 'Hide password' : 'Show password');
        }
        toggle.addEventListener('click', flip);
        toggle.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); flip(); } });
    })();

    // Loading state on submit
    document.getElementById('loginForm').addEventListener('submit', function () {
        document.getElementById('loginBtn').classList.add('loading');
    });

    // Floating amber embers
    (function () {
        const container = document.getElementById('particles');
        if (!container) return;
        const count = 16;
        for (let i = 0; i < count; i++) {
            const p = document.createElement('div');
            p.className = 'particle';
            const size = 2 + Math.random() * 5;
            p.style.width = size + 'px';
            p.style.height = size + 'px';
            p.style.left = Math.random() * 100 + '%';
            p.style.top = (60 + Math.random() * 40) + '%';
            p.style.animationDuration = (10 + Math.random() * 12) + 's';
            p.style.animationDelay = (Math.random() * 12) + 's';
            container.appendChild(p);
        }
    })();

    // Toastr config
    toastr.options = {
        "closeButton": true, "progressBar": true, "positionClass": "toast-top-right",
        "timeOut": "3000", "extendedTimeOut": "1000",
        "showMethod": "fadeIn", "hideMethod": "fadeOut"
    };

    // Auto-focus username
    window.addEventListener('load', function () {
        const u = document.getElementById('username');
        if (u && !u.value) u.focus();
    });

    // Remember-me: persist username locally
    (function () {
        const remember = document.getElementById('rememberMe');
        const username = document.getElementById('username');
        const saved = localStorage.getItem('rememberedUsername');
        if (saved) { username.value = saved; remember.checked = true; }
        document.getElementById('loginForm').addEventListener('submit', function () {
            if (remember.checked) localStorage.setItem('rememberedUsername', username.value);
            else localStorage.removeItem('rememberedUsername');
        });
    })();
</script>

</body>
</html>
