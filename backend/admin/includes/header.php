<?php
$pageTitle = isset($pageTitle) ? $pageTitle : '1wellness Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="icon" type="image/png" sizes="32x32" href="/images/brand/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/brand/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/brand/favicon-180.png">
    <link rel="shortcut icon" href="/images/brand/favicon.ico">

    <!-- Restore collapsed state before render to avoid width flash -->
    <script>
        try {
            if (localStorage.getItem('1w_sb') === '1') {
                document.documentElement.setAttribute('data-sb', 'c');
            }
        } catch (e) {}
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        :root {
            --sb-w:   240px;
            --sb-c:    68px;
            --tb-h:    56px;
            --moss:  #2C3E35;
            --clay:  #D97757;
            --sage:  #E3E8E1;
            --cream: #FDFCF8;
            --mute:  #6B7C70;
            --edge:  #EAEAE5;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--moss);
            overflow-x: hidden;
        }

        h1, h2, h3, h4, h5, h6 { font-family: 'Playfair Display', serif; }

        /* ─── Sidebar ────────────────────────────────────── */
        #sb {
            position: fixed; left: 0; top: 0;
            height: 100dvh; width: var(--sb-w);
            transform: translateX(0);
            transition: width .22s cubic-bezier(.4,0,.2,1),
                        transform .26s cubic-bezier(.4,0,.2,1);
            z-index: 40;
        }
        /* CSS pre-hydration: collapsed desktop */
        [data-sb="c"] #sb { width: var(--sb-c); }

        /* Mobile: start off-screen */
        @media (max-width: 767px) {
            #sb { width: 282px !important; transform: translateX(-100%); }
        }

        /* ─── Sidebar collapsed styles ───────────────────── */
        #sb.sbc .sb-label,
        #sb.sbc .sb-grp,
        #sb.sbc .sb-user-info,
        #sb.sbc .sb-spacer  { display: none !important; }

        #sb.sbc .sb-item    { justify-content: center; padding-left: 0; padding-right: 0; }
        #sb.sbc .sb-usr     { justify-content: center; padding-left: 8px; padding-right: 8px; }
        #sb.sbc .sb-sep     { margin-left: 10px; margin-right: 10px; }

        /* ─── Sidebar tooltip ────────────────────────────── */
        .sb-tip {
            position: absolute; left: calc(100% + 10px); top: 50%;
            transform: translateY(-50%);
            background: #111c17; color: #fff;
            font-size: 11.5px; font-weight: 500;
            padding: 5px 10px; border-radius: 8px;
            white-space: nowrap; z-index: 200;
            pointer-events: none; opacity: 0;
            transition: opacity .12s;
        }
        .sb-tip::after {
            content: ''; position: absolute;
            right: 100%; top: 50%; transform: translateY(-50%);
            border: 5px solid transparent; border-right-color: #111c17;
        }
        #sb.sbc .sb-item:hover .sb-tip,
        #sb.sbc .sb-usr:hover .sb-tip { opacity: 1; }

        /* ─── Sidebar scrollbar ──────────────────────────── */
        .sb-scroll { scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.08) transparent; }
        .sb-scroll::-webkit-scrollbar { width: 3px; }
        .sb-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 3px; }

        /* ─── Top bar ─────────────────────────────────────── */
        #tb {
            position: fixed; top: 0; right: 0;
            left: var(--sb-w); height: var(--tb-h);
            transition: left .22s cubic-bezier(.4,0,.2,1);
            z-index: 20;
        }
        [data-sb="c"] #tb { left: var(--sb-c); }
        @media (max-width: 767px) { #tb { left: 0 !important; } }

        /* ─── Main content wrapper ───────────────────────── */
        #mw {
            margin-left: var(--sb-w);
            padding-top: var(--tb-h);
            transition: margin-left .22s cubic-bezier(.4,0,.2,1);
            min-height: 100dvh;
        }
        [data-sb="c"] #mw { margin-left: var(--sb-c); }
        @media (max-width: 767px) {
            #mw { margin-left: 0 !important; padding-bottom: 80px; }
        }

        /* ─── Luxury components ──────────────────────────── */
        .luxury-card {
            background: #fff; border: 1px solid var(--edge);
            border-radius: 1.5rem;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .luxury-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px -10px rgba(44,62,53,.08);
            border-color: #d4d4d0;
        }

        .luxury-input {
            background: #fff; border: 1px solid var(--edge);
            border-radius: .5rem; padding: .5rem 1rem;
            font-size: .875rem; color: var(--moss);
            transition: border-color .15s; width: 100%;
        }
        .luxury-input:focus { outline: none; border-color: var(--moss); }

        select.luxury-input { appearance: auto; }

        .action-button { transition: transform .2s; }
        .action-button:hover { transform: scale(1.02); }

        /* ─── Background noise ───────────────────────────── */
        .bg-noise {
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            opacity: .025; pointer-events: none;
        }

        /* ─── Global scrollbar ───────────────────────────── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--edge); border-radius: 4px; }

        /* ─── Alpine x-cloak ────────────────────────────── */
        [x-cloak] { display: none !important; }
    </style>

    <script>
        function adminNav() {
            const mob = () => window.innerWidth < 768;
            return {
                sidebarOpen:      false,
                sidebarCollapsed: localStorage.getItem('1w_sb') === '1',
                isMobile:         mob(),
                userMenuOpen:     false,

                init() {
                    window.addEventListener('resize', () => {
                        this.isMobile = mob();
                        if (!this.isMobile && this.sidebarOpen) {
                            this.sidebarOpen = false;
                            document.body.style.overflow = '';
                        }
                    });
                    // Keep CSS attribute in sync after hydration
                    this.$watch('sidebarCollapsed', v => {
                        document.documentElement.setAttribute('data-sb', v ? 'c' : '');
                    });
                },

                sidebarStyle() {
                    if (this.isMobile) {
                        return { transform: this.sidebarOpen ? 'translateX(0)' : 'translateX(-100%)' };
                    }
                    return {
                        width:     this.sidebarCollapsed ? '68px' : '240px',
                        transform: 'translateX(0)'
                    };
                },

                sidebarClass() {
                    return (this.sidebarCollapsed && !this.isMobile) ? 'sbc' : '';
                },

                topbarStyle() {
                    const l = this.isMobile ? '0px'
                            : (this.sidebarCollapsed ? '68px' : '240px');
                    return { left: l };
                },

                mainStyle() {
                    const ml = this.isMobile ? '0px'
                             : (this.sidebarCollapsed ? '68px' : '240px');
                    return { marginLeft: ml };
                },

                /* Desktop sidebar toggle */
                toggleDesktop() {
                    if (this.isMobile) return;
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    localStorage.setItem('1w_sb', this.sidebarCollapsed ? '1' : '0');
                },

                /* Mobile sidebar open */
                openMobile() {
                    this.sidebarOpen = true;
                    document.body.style.overflow = 'hidden';
                },

                /* Mobile sidebar close */
                closeMobile() {
                    if (!this.isMobile) return;
                    this.sidebarOpen = false;
                    document.body.style.overflow = '';
                },

                /* Top-bar hamburger: toggle on both */
                toggleSidebar() {
                    if (this.isMobile) {
                        this.sidebarOpen ? this.closeMobile() : this.openMobile();
                    } else {
                        this.toggleDesktop();
                    }
                }
            };
        }
    </script>
</head>

<body x-data="adminNav()" @keydown.escape.window="closeMobile()">

    <!-- Background texture & blobs -->
    <div class="fixed inset-0 bg-noise z-0 pointer-events-none"></div>
    <div class="fixed pointer-events-none z-0" style="top:-10%;left:-10%;width:500px;height:500px;background:#E3E8E1;border-radius:50%;filter:blur(100px);opacity:.45;"></div>
    <div class="fixed pointer-events-none z-0" style="bottom:-10%;right:-10%;width:600px;height:600px;background:#F2E6D8;border-radius:50%;filter:blur(100px);opacity:.45;"></div>

    <!-- Sidebar + top bar + bottom nav (nav.php) -->
    <?php include __DIR__ . '/nav.php'; ?>

    <!-- Main content -->
    <div id="mw" :style="mainStyle()">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
