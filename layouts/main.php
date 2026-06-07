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
            background: rgba(11, 9, 7, 0.86) !important;
            backdrop-filter: blur(20px) saturate(1.4) !important;
            border-bottom: 1px solid rgba(240, 140, 0, 0.12) !important;
            box-shadow: 0 1px 0 rgba(240,140,0,0.07), 0 16px 40px -20px rgba(0,0,0,0.8) !important;
            padding: 0 1.5rem !important;
            min-height: 60px;
        }
        /* Wordmark */
        .navbar-brand {
            display: inline-flex !important; align-items: center; gap: 0;
            padding: 0 !important; margin-right: 2.5rem !important;
            text-decoration: none !important;
        }
        .navbar-brand .wordmark {
            font-family: var(--font-display);
            font-size: 1.22rem; font-weight: 800; line-height: 1;
            letter-spacing: -0.02em; color: #fff;
        }
        .navbar-brand .wordmark b { color: var(--light-orange); }
        /* Nav items */
        .navbar-nav .nav-link {
            color: rgba(246,239,229,0.55) !important;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            border-radius: 8px;
            padding: 0.45rem 0.85rem !important;
            transition: color 0.18s, background 0.18s;
            white-space: nowrap;
        }
        .navbar-nav .nav-link:hover {
            color: #fff !important;
            background: rgba(240,140,0,0.09);
        }
        .navbar-nav .nav-item.active .nav-link,
        .navbar-nav li.active a {
            color: #1a0d00 !important;
            background: linear-gradient(135deg, var(--light-orange) 0%, var(--dark-orange) 100%) !important;
            box-shadow: 0 4px 14px -6px rgba(240,140,0,0.55);
            border-radius: 8px;
        }
        /* Divider between nav links and profile */
        .nav-divider {
            width: 1px; height: 22px; margin: 0 1rem;
            background: rgba(240,140,0,0.15);
            align-self: center; flex-shrink: 0;
        }
        /* Profile dropdown */
        .profile-dropdown .dropdown-toggle {
            display: flex; align-items: center; gap: 0.55rem;
            background: none; border: none; padding: 0;
            cursor: pointer; text-decoration: none;
        }
        .profile-dropdown .dropdown-toggle::after { display: none; }
        .profile-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: linear-gradient(135deg, var(--light-orange), var(--dark-orange));
            color: #1a0d00; font-weight: 800; font-size: 0.78rem;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; letter-spacing: 0;
        }
        .profile-name {
            font-size: 0.82rem; font-weight: 600;
            color: rgba(246,239,229,0.75);
            max-width: 120px; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap;
        }
        .profile-chevron {
            color: rgba(246,239,229,0.35);
            font-size: 0.65rem;
            transition: transform 0.2s;
        }
        .profile-dropdown.show .profile-chevron { transform: rotate(180deg); }
        /* Dropdown menu */
        .profile-dropdown .dropdown-menu {
            background: rgba(20, 16, 11, 0.97) !important;
            border: 1px solid rgba(240,140,0,0.14) !important;
            border-radius: 12px !important;
            box-shadow: 0 20px 50px -12px rgba(0,0,0,0.85), 0 0 0 1px rgba(240,140,0,0.06) !important;
            padding: 0.4rem !important;
            min-width: 200px;
            margin-top: 0.6rem !important;
            right: 0; left: auto;
        }
        .profile-dropdown .dropdown-header {
            padding: 0.7rem 0.9rem 0.6rem;
            display: flex; align-items: center; gap: 0.6rem;
        }
        .profile-dropdown .dropdown-header .avatar-lg {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg, var(--light-orange), var(--dark-orange));
            color: #1a0d00; font-weight: 800; font-size: 0.92rem;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
        .profile-dropdown .dropdown-header .info { overflow: hidden; }
        .profile-dropdown .dropdown-header .info .dname {
            font-weight: 700; font-size: 0.88rem; color: #f6efe5;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .profile-dropdown .dropdown-header .info .drole {
            font-size: 0.72rem; color: var(--text-muted);
        }
        .dropdown-divider { border-color: rgba(240,140,0,0.10) !important; margin: 0.3rem 0 !important; }
        .profile-dropdown .dropdown-item {
            color: rgba(246,239,229,0.7) !important;
            font-size: 0.82rem; font-weight: 500;
            border-radius: 8px; padding: 0.55rem 0.9rem !important;
            display: flex; align-items: center; gap: 0.6rem;
            transition: background 0.15s, color 0.15s;
        }
        .profile-dropdown .dropdown-item:hover {
            background: rgba(240,140,0,0.09) !important;
            color: #fff !important;
        }
        .profile-dropdown .dropdown-item.text-danger { color: #e87070 !important; }
        .profile-dropdown .dropdown-item.text-danger:hover { background: rgba(232,112,112,0.09) !important; }
        .profile-dropdown .dropdown-item i { width: 14px; text-align: center; opacity: 0.7; }

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
            .navbar-brand .wordmark { display: none; }
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
<?php
$_nav_username = htmlspecialchars($user->username ?? 'User', ENT_QUOTES, 'UTF-8');
$_nav_display  = strpos($_nav_username, '@') !== false ? substr($_nav_username, 0, strpos($_nav_username, '@')) : $_nav_username;
$_nav_initial  = strtoupper(substr($_nav_display, 0, 1));
?>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="?p=dashboard">
                <span class="wordmark">File<b>Bridge</b></span>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarMain"
                    aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav mr-auto align-items-center">
                    <?php echo Menu::getUserMenu($user_id, $active); ?>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="nav-divider d-none d-lg-block"></div>
                    <!-- Profile dropdown -->
                    <div class="dropdown profile-dropdown">
                        <a href="#" class="dropdown-toggle" id="profileMenu"
                           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <div class="profile-avatar"><?= $_nav_initial ?></div>
                            <span class="profile-name d-none d-lg-block"><?= $_nav_display ?></span>
                            <i class="fas fa-chevron-down profile-chevron d-none d-lg-inline"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="profileMenu">
                            <!-- User info header -->
                            <div class="dropdown-header">
                                <div class="avatar-lg"><?= $_nav_initial ?></div>
                                <div class="info">
                                    <div class="dname"><?= $_nav_display ?></div>
                                    <div class="drole">Signed in</div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="?p=settings">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <a class="dropdown-item" href="?p=backup">
                                <i class="fas fa-database"></i> Backup
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="./login.php?logout">
                                <i class="fas fa-sign-out-alt"></i> Sign out
                            </a>
                        </div>
                    </div>
                </div>
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
