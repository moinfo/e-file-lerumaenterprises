<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <base href="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>" />
    <title>File Bridge</title>
    <meta name="description" content="TheBridge File Manager">
    <meta name="author" content="TheBridge TechCreative">

    <!-- CSS Libraries -->
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="node_modules/bootstrap-datepicker/dist/css/bootstrap-datepicker.css">
    <link rel="stylesheet" href="node_modules/sweetalert2/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="node_modules/select2/dist/css/select2.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/version3.css">
    <link rel="stylesheet" href="assets/css/uploadfile.css">
    <link rel="stylesheet" href="assets/css/uploadfile.custom.css">
    <link href="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://common.olemiss.edu/_js/sweet-alert/sweet-alert.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- JavaScript Libraries -->
    <script src="node_modules/jquery/dist/jquery.min.js"></script>
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="node_modules/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="node_modules/sweetalert2/dist/sweetalert2.all.min.js"></script>
    <script src="assets/js/jquery.uploadfile.js"></script>
    <script src="assets/js/jquery.uploadfile.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="node_modules/select2/dist/js/select2.full.min.js"></script>
    <script src="node_modules/pdfobject/pdfobject.min.js"></script>
    <script src="./assets/js/main.js"></script>
    <script src="https://unpkg.com/bootstrap-table@1.22.1/dist/bootstrap-table.min.js"></script>
    <script src="https://unpkg.com/bootstrap-table@1.22.1/dist/extensions/custom-view/bootstrap-table-custom-view.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <!-- ===== Visual language refinement (matches login.php) ===== -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Warm-ink refinement of the existing v3 palette — variables cascade app-wide */
        :root {
            --bg-dark: #17130f;
            --bg-darker: #0b0907;
            --card-bg: #1f1a15;
            --light-orange: #ffb24d;
            --dark-orange: #d97706;
            --border-color: rgba(240, 140, 0, 0.16);
            --text-muted: #9c9389;
            --amber-300: #ffc97a;
            --font-display: 'Bricolage Grotesque', 'Segoe UI', sans-serif;
            --font-body: 'Hanken Grotesk', 'Segoe UI', sans-serif;
        }

        body {
            font-family: var(--font-body) !important;
            background:
                radial-gradient(900px 600px at 12% -8%, rgba(240, 140, 0, 0.13), transparent 60%),
                radial-gradient(720px 520px at 100% 112%, rgba(217, 119, 6, 0.10), transparent 55%),
                linear-gradient(160deg, #0b0907 0%, #110e0b 55%, #16120e 100%) !important;
            color: #f6efe5;
        }
        /* Fine grain overlay for depth (matches login) */
        body::after {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none; opacity: 0.04;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        h1, h2, h3, h4, h5, h6,
        .card-header h5, .card-header h4, .login-title {
            font-family: var(--font-display) !important;
            letter-spacing: -0.01em;
        }

        /* ----- Navbar ----- */
        .navbar {
            background: rgba(11, 9, 7, 0.72) !important;
            backdrop-filter: blur(16px) !important;
            border-bottom: 1px solid var(--border-color) !important;
            box-shadow: 0 12px 34px -16px rgba(0, 0, 0, 0.7) !important;
        }
        .navbar-brand { display: inline-flex !important; align-items: center; gap: 0.7rem; }
        .navbar-brand .wordmark {
            font-family: var(--font-display);
            font-weight: 800; font-size: 1.18rem; line-height: 1;
            letter-spacing: -0.01em; color: #fff;
        }
        .navbar-brand .wordmark b { color: var(--primary-orange); font-weight: 800; }
        .navbar-nav .nav-link {
            color: var(--text-muted) !important;
            font-weight: 600;
            border-radius: 10px;
            padding: 0.5rem 0.9rem !important;
            transition: color 0.2s ease, background 0.2s ease;
        }
        .navbar-nav .nav-link:hover { color: #fff !important; background: rgba(240, 140, 0, 0.10); }
        .navbar-nav .nav-link.active,
        .navbar-nav .active > .nav-link,
        .navbar-nav li.active a {
            color: #241400 !important;
            background: linear-gradient(135deg, var(--light-orange), var(--dark-orange)) !important;
            box-shadow: 0 8px 18px -8px rgba(240, 140, 0, 0.6);
        }
        .navbar-text a { color: var(--text-muted) !important; font-weight: 600; transition: color 0.2s ease; }
        .navbar-text a:hover { color: var(--primary-orange) !important; }

        /* ----- Footer (matches login) ----- */
        .footer {
            background: rgba(11, 9, 7, 0.7) !important;
            backdrop-filter: blur(12px);
            border-top: 1px solid var(--border-color) !important;
            color: var(--text-muted) !important;
        }
        .footer a { color: var(--light-orange) !important; font-weight: 600; }
        .footer a:hover { color: var(--amber-300) !important; }

        /* ----- Primary buttons → amber gradient (on-brand) ----- */
        .btn-primary {
            background: linear-gradient(135deg, var(--light-orange), var(--dark-orange)) !important;
            border: none !important;
            color: #241400 !important;
            font-weight: 700 !important;
        }
        .btn-primary:hover { filter: brightness(1.05); }

        /* Warm the cards (version3 hardcodes a cool gray) so they match the ink palette */
        .card {
            background: linear-gradient(180deg, rgba(31, 26, 21, 0.92), rgba(20, 17, 13, 0.95)) !important;
            border-color: var(--border-color) !important;
        }
        .card-header {
            background: linear-gradient(135deg, rgba(11, 9, 7, 0.92), rgba(23, 19, 15, 0.92)) !important;
            border-bottom-color: var(--border-color) !important;
        }

        /* ----- Mobile responsiveness (shell-wide) ----- */
        @media (max-width: 768px) {
            .navbar { padding: 0.4rem 0; }
            .navbar-brand .wordmark { display: none; }   /* prevent brand overflow on small screens */
            .navbar-brand img { height: 34px; }
            .navbar-collapse {
                background: rgba(11, 9, 7, 0.96); border: 1px solid var(--border-color);
                border-radius: 14px; margin-top: 0.6rem; padding: 0.5rem;
            }
            .navbar-nav { gap: 0.2rem; }
            .navbar-nav .nav-link { width: 100%; }
            .navbar .navbar-text { display: block; padding-top: 0.6rem; margin-top: 0.4rem; border-top: 1px solid var(--border-color); }
            .container, .container-fluid { padding-top: 1.2rem !important; padding-left: 1rem !important; padding-right: 1rem !important; }
            .table-responsive { -webkit-overflow-scrolling: touch; }
        }
        @media (max-width: 480px) {
            .footer { font-size: 0.78rem; padding: 0.8rem 1rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; }
        }
    </style>
    <!-- ===== /Visual language refinement ===== -->
</head>

<body>
<header>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarText" aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand mr-5 pt-0" href="?p=dashboard">
                <img src="assets/img/bridge_logo.png" class="img-responsive" height="40"/>
                <span class="wordmark">File<b>Bridge</b></span>
            </a>
            <div class="collapse navbar-collapse" id="navbarText">
                <ul class="navbar-nav mr-auto">
                    <?php
                    echo Menu::getUserMenu($user_id, $active);
                    ?>
                </ul>
                <span class="navbar-text">
                    <a class="mr-3" href="./login.php?logout"><i class="fa fa fa-sign-out">&nbsp;</i>Logout</a>
                </span>
            </div>
        </div>
    </nav>
</header>

<div class="content main-page">
    <?php
    // Load the page content
    if (isset($page_content_file) && file_exists($page_content_file)) {
        include $page_content_file;
    } else {
        echo '<div class="container"><div class="alert alert-danger">Page content not found.</div></div>';
    }
    ?>
</div>

<footer class="footer">
    <div class="footer-left">
        <span>Version 3.0</span>
    </div>
    <div class="footer-center">
        <span>&copy; <?php echo date('Y'); ?> File Bridge System. All rights reserved.</span><br>
        <span>Powered By <a href="https://moinfo.co.tz" target="_blank">MoinfoTech Company Limited</a></span>
        <div class="footer-icons">
            <a href="#" title="Documentation"><i class="fas fa-book"></i></a>
            <a href="https://moinfo.co.tz" target="_blank" title="Support"><i class="fas fa-headset"></i></a>
            <a href="#" title="Settings"><i class="fas fa-cog"></i></a>
        </div>
    </div>
    <div class="footer-right">
        <a href="#">Terms of Service</a> | <a href="#">Privacy Policy</a>
    </div>
</footer>

<script>
    $(document).ready(function() {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
        });

        // Ensure mobile menu toggle works properly
        $('.navbar-toggler').on('click', function() {
            $('#navbarText').toggleClass('show');
        });

        // Close mobile menu when clicking a link
        $('.navbar-nav a').on('click', function() {
            if ($(window).width() < 768) {
                $('#navbarText').removeClass('show');
            }
        });

        // Close mobile menu when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.navbar').length) {
                $('#navbarText').removeClass('show');
            }
        });
    });
</script>
</body>
</html>
