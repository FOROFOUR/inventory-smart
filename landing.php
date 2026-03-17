<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IBIS – Imbak-Bantay Inventory System</title>
    <link rel="stylesheet" href="css/landing.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- ═══════════════════════════════════════════════════
         IBIS ADDITIONS — inline styles only for new elements
         All existing landing.css rules remain untouched
    ════════════════════════════════════════════════════ -->
    <style>
        /* ── FIX: hero dark background so white text is visible ── */
        .hero {
            background: linear-gradient(135deg, #0a1628 0%, #0d2144 55%, #0a58ca 100%) !important;
        }

        /* ── IBIS brand pill (eyebrow above title) ── */
        .ibis-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(8px);
            color: #fff;
            padding: .4rem 1rem;
            border-radius: 99px;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 1.25rem;
        }
        .ibis-eyebrow i { font-size: .8rem; opacity: .85; }

        /* ── Big IBIS title ── */
        .ibis-main-title {
            font-family: 'Sora', sans-serif;
            font-size: clamp(3.5rem, 10vw, 7rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -.03em;
            color: #ffffff;
            margin-bottom: .15em;
        }
        .ibis-main-title span {
            background: linear-gradient(135deg, #7dd3fc, #a5f3fc, #ffffff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Tagline under IBIS ── */
        .ibis-tagline {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            color: rgba(255,255,255,.7);
            font-weight: 400;
            margin-bottom: 1.75rem;
            line-height: 1.5;
        }
        .ibis-tagline strong { color: #fff; font-weight: 600; }

        /* ── Single IBIS meaning box ── */
        .ibis-meaning-box {
            display: inline-flex;
            align-items: stretch;
            background: rgba(255,255,255,.09);
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 1.75rem;
            backdrop-filter: blur(12px);
        }
        .ibis-meaning-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px 24px;
            border-right: 1px solid rgba(255,255,255,.1);
            transition: background .2s;
            cursor: default;
        }
        .ibis-meaning-item:last-child { border-right: none; }
        .ibis-meaning-item:hover { background: rgba(255,255,255,.12); }
        .ibis-meaning-letter {
            width: 38px; height: 38px;
            border-radius: 9px;
            display: grid;
            place-items: center;
            font-family: 'Sora', sans-serif;
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
        }
        .ml-i1 { background: linear-gradient(135deg, #3b82f6, #06b6d4); }
        .ml-b  { background: linear-gradient(135deg, #6366f1, #8b5cf6); }
        .ml-i2 { background: linear-gradient(135deg, #ec4899, #f43f5e); }
        .ml-s  { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .ibis-meaning-ph { font-size: .82rem; font-weight: 700; color: #fff; line-height: 1.2; }
        .ibis-meaning-en { font-size: .63rem; color: rgba(255,255,255,.45); line-height: 1.3; }

        /* ── Typewriter text ── */
        .ibis-typewriter-line {
            font-size: clamp(1.1rem, 2.2vw, 1.35rem);
            color: rgba(255,255,255,.85);
            margin-bottom: 2rem;
            min-height: 2em;
            display: flex;
            align-items: center;
            gap: .3rem;
        }
        .ibis-typewriter-line i { color: rgba(255,255,255,.5); font-size: .9rem; }
        #ibis-typed { font-weight: 600; color: #fff; }
        .ibis-cursor {
            display: inline-block;
            color: #7dd3fc;
            animation: ibis-blink .7s step-end infinite;
        }
        @keyframes ibis-blink { 0%,100%{opacity:1} 50%{opacity:0} }

        /* ── "Bakit IBIS?" Section ── */
        .bakit-ibis {
            padding: 6rem 0;
            background: linear-gradient(135deg, #f0f7ff 0%, #f8faff 50%, #eef4ff 100%);
        }
        .bakit-ibis .section-header { text-align: center; margin-bottom: 3.5rem; }
        .bakit-ibis .section-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: rgba(59,130,246,.1);
            border: 1px solid rgba(59,130,246,.2);
            color: #2563eb;
            padding: .35rem .9rem;
            border-radius: 99px;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        .bakit-ibis .section-title {
            font-family: 'Sora', sans-serif;
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 800;
            color: #0f172a;
            margin-bottom: .75rem;
        }
        .bakit-ibis .section-title span {
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .bakit-ibis .section-desc {
            color: #64748b;
            max-width: 560px;
            margin-inline: auto;
            font-size: .95rem;
            line-height: 1.7;
        }

        /* big acronym display cards */
        .ibis-big-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 3.5rem;
        }
        @media (max-width: 900px) { .ibis-big-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .ibis-big-row { grid-template-columns: 1fr; } }

        .ibis-big-card {
            background: #ffffff;
            border: 1.5px solid rgba(59,130,246,.1);
            border-radius: 20px;
            padding: 2rem 1.5rem;
            text-align: center;
            box-shadow: 0 4px 24px rgba(59,130,246,.07);
            transition: transform .3s, box-shadow .3s;
            position: relative;
            overflow: hidden;
        }
        .ibis-big-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }
        .ibis-big-card.card-i1::before { background: linear-gradient(90deg, #3b82f6, #06b6d4); }
        .ibis-big-card.card-b::before  { background: linear-gradient(90deg, #6366f1, #8b5cf6); }
        .ibis-big-card.card-i2::before { background: linear-gradient(90deg, #ec4899, #f43f5e); }
        .ibis-big-card.card-s::before  { background: linear-gradient(90deg, #f59e0b, #f97316); }
        .ibis-big-card:hover { transform: translateY(-6px); box-shadow: 0 16px 48px rgba(59,130,246,.15); }

        .ibis-big-letter {
            font-family: 'Sora', sans-serif;
            font-size: 4rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: .5rem;
        }
        .card-i1 .ibis-big-letter { background: linear-gradient(135deg, #3b82f6, #06b6d4); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .card-b  .ibis-big-letter { background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .card-i2 .ibis-big-letter { background: linear-gradient(135deg, #ec4899, #f43f5e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .card-s  .ibis-big-letter { background: linear-gradient(135deg, #f59e0b, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

        .ibis-big-word-ph {
            font-family: 'Sora', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .2rem;
        }
        .ibis-big-word-en {
            font-size: .8rem;
            color: #94a3b8;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .ibis-big-icon {
            margin-top: 1.1rem;
            font-size: 1.5rem;
            opacity: .25;
        }
        .card-i1 .ibis-big-icon { color: #3b82f6; }
        .card-b  .ibis-big-icon { color: #6366f1; }
        .card-i2 .ibis-big-icon { color: #ec4899; }
        .card-s  .ibis-big-icon { color: #f59e0b; }

        /* bird story card */
        .ibis-story-card {
            background: #ffffff;
            border: 1.5px solid rgba(59,130,246,.1);
            border-radius: 20px;
            padding: 2.5rem;
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2.5rem;
            align-items: start;
            box-shadow: 0 4px 24px rgba(59,130,246,.07);
            position: relative;
            overflow: hidden;
        }
        .ibis-story-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6, #ec4899, #f59e0b);
        }
        @media (max-width: 700px) {
            .ibis-story-card { grid-template-columns: 1fr; gap: 1.5rem; padding: 1.75rem; }
        }
        .ibis-bird-icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1.5px solid rgba(59,130,246,.15);
            border-radius: 20px;
            display: grid;
            place-items: center;
            font-size: 2rem;
            flex-shrink: 0;
        }
        .ibis-story-text h3 {
            font-family: 'Sora', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: .75rem;
        }
        .ibis-story-text h3 span {
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .ibis-story-text p {
            color: #64748b;
            font-size: .93rem;
            line-height: 1.75;
            margin-bottom: .75rem;
        }
        .ibis-story-text p:last-child { margin-bottom: 0; }
        .ibis-story-text strong { color: #2563eb; }

        /* ── responsive tweaks for hero new elements ── */
        @media (max-width: 768px) {
            .ibis-meaning-box { flex-wrap: wrap; justify-content: center; }
            .ibis-meaning-item { padding: 12px 16px; }
            .ibis-typewriter-line { justify-content: center; }
        }
    </style>
</head>
<body>
    <nav class="navbar" id="navbar">
        <div class="container nav-container">
            <div class="logo">
                <i class="fas fa-feather-alt"></i>
                <span><strong>IBIS</strong></span>
            </div>
            <ul class="nav-menu" id="navMenu">
                <li><a href="#home" class="nav-link active">Home</a></li>
                <li><a href="#about" class="nav-link">About</a></li>
                <li><a href="#bakit-ibis" class="nav-link">Bakit IBIS?</a></li>
                <li><a href="#features" class="nav-link">Features</a></li>
                <li><a href="#contact" class="nav-link">Contact</a></li>
                <li><a href="pages/login.php" class="btn-login">Login</a></li>
            </ul>
            <div class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- ══════════════ HERO ══════════════ -->
    <section id="home" class="hero">
        <div class="hero-background">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="grid-pattern"></div>
        </div>
        <div class="container hero-container">
            <div class="hero-content">

                <!-- eyebrow badge -->
                <div class="ibis-eyebrow">
                    <i class="fas fa-feather-alt"></i>
                    Imbak-Bantay Inventory System
                </div>

                <!-- Big IBIS title -->
                <div class="ibis-main-title"><span>IBIS</span></div>

                <!-- tagline -->
                <p class="ibis-tagline">
                    <strong>I</strong>mbak · <strong>B</strong>antay · <strong>I</strong>nventory · <strong>S</strong>ystem
                </p>

                <!-- Single IBIS meaning box -->
                <div class="ibis-meaning-box">
                    <div class="ibis-meaning-item">
                        <div class="ibis-meaning-letter ml-i1">I</div>
                        <div class="ibis-meaning-ph">Imbak</div>
                        <div class="ibis-meaning-en">Storage</div>
                    </div>
                    <div class="ibis-meaning-item">
                        <div class="ibis-meaning-letter ml-b">B</div>
                        <div class="ibis-meaning-ph">Bantay</div>
                        <div class="ibis-meaning-en">Monitor</div>
                    </div>
                    <div class="ibis-meaning-item">
                        <div class="ibis-meaning-letter ml-i2">I</div>
                        <div class="ibis-meaning-ph">Inventory</div>
                        <div class="ibis-meaning-en">Talaan</div>
                    </div>
                    <div class="ibis-meaning-item">
                        <div class="ibis-meaning-letter ml-s">S</div>
                        <div class="ibis-meaning-ph">System</div>
                        <div class="ibis-meaning-en">Plataporma</div>
                    </div>
                </div>

                <!-- typewriter line -->
                <div class="ibis-typewriter-line">
                    <i class="fas fa-circle-dot"></i>
                    <span id="ibis-typed"></span><span class="ibis-cursor">|</span>
                </div>

                <div class="hero-buttons">
                    <a href="pages/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Mag-login sa IBIS
                    </a>
                    <a href="#bakit-ibis" class="btn btn-secondary">
                        <i class="fas fa-info-circle"></i>
                        Bakit IBIS?
                    </a>
                </div>

                <!-- keep the original empty stat placeholders so landing.css still works -->
                <div class="hero-stats">
                    <div class="stat-item"></div>
                    <div class="stat-item"></div>
                    <div class="stat-item"></div>
                </div>
            </div>

            <!-- dashboard mockup — completely untouched -->
            <div class="hero-visual">
                <div class="dashboard-mockup">
                    <div class="mockup-header">
                        <div class="mockup-dots">
                            <span></span><span></span><span></span>
                        </div>
                        <div class="mockup-title">IBIS Dashboard</div>
                    </div>
                    <div class="mockup-content">
                        <div class="mockup-sidebar">
                            <div class="sidebar-item active"></div>
                            <div class="sidebar-item"></div>
                            <div class="sidebar-item"></div>
                            <div class="sidebar-item"></div>
                        </div>
                        <div class="mockup-main">
                            <div class="chart-area">
                                <div class="chart-bar" style="height: 60%"></div>
                                <div class="chart-bar" style="height: 85%"></div>
                                <div class="chart-bar" style="height: 45%"></div>
                                <div class="chart-bar" style="height: 90%"></div>
                                <div class="chart-bar" style="height: 70%"></div>
                            </div>
                            <div class="data-cards">
                                <div class="data-card"></div>
                                <div class="data-card"></div>
                                <div class="data-card"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="floating-card card-1">
                    <i class="fas fa-laptop"></i>
                    <span>Real-time Tracking</span>
                </div>
                <div class="floating-card card-2">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </div>
                <div class="floating-card card-3">
                    <i class="fas fa-shield-alt"></i>
                    <span>Secure</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════ ABOUT (untouched) ══════════════ -->
    <section id="about" class="about">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">About Us</span>
                <h2 class="section-title">Revolutionizing Asset Management</h2>
                <p class="section-description">
                    IBIS is designed to simplify the complexity of inventory management 
                    with cutting-edge technology and intuitive design.
                </p>
            </div>
            
            <div class="about-content">
                <div class="about-image">
                    <div class="image-wrapper">
                        <div class="floating-element elem-1"></div>
                        <div class="floating-element elem-2"></div>
                        <div class="image-placeholder">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                </div>
                
                <div class="about-text">
                    <h3>Smart Solutions for Modern Businesses</h3>
                    <p>
                        In today's fast-paced business environment, keeping track of your IT assets 
                        shouldn't be complicated. IBIS provides a comprehensive solution that 
                        combines powerful features with an intuitive interface.
                    </p>
                    <p>
                        Our platform enables organizations to efficiently track laptops, desktops, 
                        peripherals, and other IT equipment throughout their lifecycle — from procurement 
                        to retirement.
                    </p>
                    
                    <div class="about-features">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Complete History</h4>
                                <p>Track every movement and change with detailed location history</p>
                            </div>
                        </div>
                        
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Advanced Analytics</h4>
                                <p>Make data-driven decisions with comprehensive reports</p>
                            </div>
                        </div>
                        
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Lightning Fast</h4>
                                <p>Built for speed with optimized performance</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════ BAKIT IBIS? (new section) ══════════════ -->
    <section id="bakit-ibis" class="bakit-ibis">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">
                    <i class="fas fa-feather-alt"></i>
                    Ang Pangalan
                </span>
                <h2 class="section-title">Bakit <span>IBIS</span>?</h2>
                <p class="section-desc">
                    Hindi lang ito acronym — ito ay isang pangalan na may malalim na kahulugan 
                    sa Filipino at sa misyon ng sistemang ito.
                </p>
            </div>

            <!-- 4 letter cards -->
            <div class="ibis-big-row">
                <div class="ibis-big-card card-i1">
                    <div class="ibis-big-letter">I</div>
                    <div class="ibis-big-word-ph">Imbak</div>
                    <div class="ibis-big-word-en">Storage</div>
                    <div class="ibis-big-icon"><i class="fas fa-box-archive"></i></div>
                </div>
                <div class="ibis-big-card card-b">
                    <div class="ibis-big-letter">B</div>
                    <div class="ibis-big-word-ph">Bantay</div>
                    <div class="ibis-big-word-en">Monitor</div>
                    <div class="ibis-big-icon"><i class="fas fa-eye"></i></div>
                </div>
                <div class="ibis-big-card card-i2">
                    <div class="ibis-big-letter">I</div>
                    <div class="ibis-big-word-ph">Inventory</div>
                    <div class="ibis-big-word-en">Talaan</div>
                    <div class="ibis-big-icon"><i class="fas fa-list-check"></i></div>
                </div>
                <div class="ibis-big-card card-s">
                    <div class="ibis-big-letter">S</div>
                    <div class="ibis-big-word-ph">System</div>
                    <div class="ibis-big-word-en">Plataporma</div>
                    <div class="ibis-big-icon"><i class="fas fa-server"></i></div>
                </div>
            </div>

            <!-- bird story -->
            <div class="ibis-story-card">
                <div class="ibis-bird-icon">
                    <i class="fas fa-feather-alt" style="color:#2563eb"></i>
                </div>
                <div class="ibis-story-text">
                    <h3>Ang <span>Ibis</span> — Simbolo ng Katalinuhan at Katumpakan</h3>
                    <p>
                        Ang <strong>ibis</strong> ay isang ibon na kilala sa buong mundo dahil sa kahusayan nito — 
                        matalas ang paningin, maingat sa bawat galaw, at palaging tumpak sa layunin. 
                        Ito ang ugali na nais naming katawanin ng aming sistema: <strong>matalino, matumpak, at mapagkakatiwalaan.</strong>
                    </p>
                    <p>
                        Ang mga salitang <strong>"Imbak"</strong> at <strong>"Bantay"</strong> ay dalawang pangunahing konsepto sa Filipino — 
                        ang <em>imbak</em> ay tumutukoy sa tamang pag-iingat at pagtatala ng mga bagay, 
                        habang ang <em>bantay</em> naman ay nagpapahiwatig ng patuloy na pagmamatyag at pangangalaga.
                    </p>
                    <p>
                        Ang pangalang IBIS ay nagbibigay ng <strong>Filipino identity</strong> sa isang modernong sistema — 
                        isang patunay na ang teknolohiya ay maaaring maging sariling atin, hindi lamang kopya ng ibang bansa.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════ FEATURES (untouched) ══════════════ -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Features</span>
                <h2 class="section-title">Everything You Need</h2>
                <p class="section-description">
                    Powerful features designed to make inventory management effortless
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-card-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3>Asset Tracking</h3>
                    <p>Monitor all your assets in real-time with unique serial numbers and barcodes</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-card-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h3>Location Management</h3>
                    <p>Track asset locations and transfers with detailed movement history</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-card-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3>Maintenance Tracking</h3>
                    <p>Schedule and monitor maintenance activities to extend asset lifespan</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-card-icon">
                        <i class="fas fa-file-export"></i>
                    </div>
                    <h3>Export Reports</h3>
                    <p>Generate detailed reports in multiple formats for audits and analysis</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-card-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Advanced Search</h3>
                    <p>Find assets quickly with powerful search and filtering capabilities</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-card-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3>Secure & Reliable</h3>
                    <p>Enterprise-grade security with role-based access control</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════ CONTACT (untouched) ══════════════ -->
    <section id="contact" class="contact">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Get In Touch</span>
                <h2 class="section-title">Contact Us</h2>
                <p class="section-description">
                    Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.
                </p>
            </div>
            
            <div class="contact-content">
                <div class="contact-info">
                    <h3>Let's Connect</h3>
                    <p>Whether you have questions, need support, or want to learn more about IBIS, we're here to help.</p>
                    
                    <div class="info-items">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <h4>Email</h4>
                                <p>support@ibis.ph</p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-content">
                                <h4>Phone</h4>
                                <p>+63 (2) 8123-4567</p>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <h4>Office</h4>
                                <p>Unit 201, Aspire Tower, Calle Industria,
                                    Bagumbayan, Quezon City, Philippines, 1110</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                
                <div class="contact-form-wrapper">
                    <form class="contact-form" id="contactForm">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">
                            Send Message
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════ FOOTER ══════════════ -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="logo">
                        <i class="fas fa-feather-alt"></i>
                        <span><strong>IBIS</strong></span>
                    </div>
                    <p>Imbak-Bantay Inventory System — intelihenteng pamamahala ng IT assets para sa modernong organisasyon.</p>
                </div>
                
                <div class="footer-links">
                    <div class="footer-column">
                        <h4>Product</h4>
                        <ul>
                            <li><a href="#features">Features</a></li>
                            <li><a href="#about">About</a></li>
                            <li><a href="pages/login.php">Login</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h4>Company</h4>
                        <ul>
                            <li><a href="#about">About Us</a></li>
                            <li><a href="#contact">Contact</a></li>
                            <li><a href="#">Careers</a></li>
                        </ul>
                    </div>
                    
                    <div class="footer-column">
                        <h4>Legal</h4>
                        <ul>
                            <li><a href="#">Privacy Policy</a></li>
                            <li><a href="#">Terms of Service</a></li>
                            <li><a href="#">Cookie Policy</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2026 IBIS – Imbak-Bantay Inventory System. Lahat ng karapatan ay nakalaan. 🇵🇭</p>
            </div>
        </div>
    </footer>

    <!-- Original landing.js reference removed — lahat ng JS ay nandito na -->
    <script>
    (function () {
        'use strict';

        /* ══════════════════════════════════════════
           1. NAVBAR — scroll shadow + active link
        ══════════════════════════════════════════ */
        var navbar  = document.getElementById('navbar');
        var navLinks = document.querySelectorAll('.nav-link');
        var sections = document.querySelectorAll('section[id]');

        function onScroll() {
            /* shadow when scrolled */
            if (window.scrollY > 20) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            /* active nav link based on scroll position */
            var current = '';
            sections.forEach(function (sec) {
                if (window.scrollY >= sec.offsetTop - 120) {
                    current = sec.getAttribute('id');
                }
            });
            navLinks.forEach(function (link) {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll(); /* run once on load */

        /* ══════════════════════════════════════════
           2. HAMBURGER MENU
        ══════════════════════════════════════════ */
        var hamburger = document.getElementById('hamburger');
        var navMenu   = document.getElementById('navMenu');

        hamburger.addEventListener('click', function () {
            hamburger.classList.toggle('open');
            navMenu.classList.toggle('active');
        });

        /* close menu when any nav link is clicked */
        navMenu.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                hamburger.classList.remove('open');
                navMenu.classList.remove('active');
            });
        });

        /* close menu when clicking outside */
        document.addEventListener('click', function (e) {
            if (!navbar.contains(e.target)) {
                hamburger.classList.remove('open');
                navMenu.classList.remove('active');
            }
        });

        /* ══════════════════════════════════════════
           3. SMOOTH SCROLL for all anchor links
        ══════════════════════════════════════════ */
        document.querySelectorAll('a[href^="#"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var target = document.querySelector(a.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    var offset = navbar.offsetHeight + 8;
                    var top = target.getBoundingClientRect().top + window.scrollY - offset;
                    window.scrollTo({ top: top, behavior: 'smooth' });
                }
            });
        });

        /* ══════════════════════════════════════════
           4. SCROLL REVEAL — fade-in on scroll
        ══════════════════════════════════════════ */
        var revealStyle = document.createElement('style');
        revealStyle.textContent = [
            '.reveal-on-scroll {',
            '  opacity: 0;',
            '  transform: translateY(28px);',
            '  transition: opacity 0.65s cubic-bezier(.4,0,.2,1), transform 0.65s cubic-bezier(.4,0,.2,1);',
            '}',
            '.reveal-on-scroll.is-visible {',
            '  opacity: 1;',
            '  transform: translateY(0);',
            '}'
        ].join('');
        document.head.appendChild(revealStyle);

        /* add class to elements we want to animate */
        var revealTargets = [
            '.section-header',
            '.about-image',
            '.about-text',
            '.feature-card',
            '.contact-info',
            '.contact-form-wrapper',
            '.ibis-big-card',
            '.ibis-story-card',
            '.bakit-ibis .section-header'
        ].join(', ');

        document.querySelectorAll(revealTargets).forEach(function (el) {
            el.classList.add('reveal-on-scroll');
        });

        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        document.querySelectorAll('.reveal-on-scroll').forEach(function (el) {
            revealObserver.observe(el);
        });

        /* stagger feature cards and ibis cards */
        document.querySelectorAll('.feature-card, .ibis-big-card').forEach(function (el, i) {
            el.style.transitionDelay = (i % 3) * 0.1 + 's';
        });

        /* ══════════════════════════════════════════
           5. CONTACT FORM — prevent default + feedback
        ══════════════════════════════════════════ */
        var contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = contactForm.querySelector('.btn-primary');
                var original = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Nagpapadala...';
                btn.disabled = true;

                setTimeout(function () {
                    btn.innerHTML = '<i class="fas fa-check"></i> Naipadala na!';
                    btn.style.background = '#198754';
                    setTimeout(function () {
                        btn.innerHTML = original;
                        btn.style.background = '';
                        btn.disabled = false;
                        contactForm.reset();
                    }, 2500);
                }, 1400);
            });
        }

        /* ══════════════════════════════════════════
           6. TYPEWRITER — IBIS hero text
        ══════════════════════════════════════════ */
        var typedEl = document.getElementById('ibis-typed');
        if (typedEl) {
            var phrases = [
                'Nagbabantay ng iyong Assets',
                'Nag-iimbak ng Talaan nang Tumpak',
                
                'Mabilis, Maaasahan, at Ligtas',
                
            ];
            var pi = 0, ci = 0, deleting = false;

            function tick() {
                var phrase = phrases[pi];
                if (!deleting) {
                    typedEl.textContent = phrase.slice(0, ci + 1);
                    ci++;
                    if (ci === phrase.length) {
                        deleting = true;
                        setTimeout(tick, 2000);
                        return;
                    }
                    setTimeout(tick, 60);
                } else {
                    typedEl.textContent = phrase.slice(0, ci - 1);
                    ci--;
                    if (ci === 0) {
                        deleting = false;
                        pi = (pi + 1) % phrases.length;
                        setTimeout(tick, 350);
                        return;
                    }
                    setTimeout(tick, 32);
                }
            }
            setTimeout(tick, 900);
        }

        /* ══════════════════════════════════════════
           7. IBIS ACRONYM CARDS — staggered entrance
        ══════════════════════════════════════════ */
        var ibisCards = document.querySelectorAll('.ibis-big-card');
        if (ibisCards.length) {
            var cardObserver = new IntersectionObserver(function (entries) {
                if (entries[0].isIntersecting) {
                    ibisCards.forEach(function (card, i) {
                        setTimeout(function () {
                            card.style.opacity    = '1';
                            card.style.transform  = 'translateY(0)';
                        }, i * 100);
                    });
                    cardObserver.disconnect();
                }
            }, { threshold: 0.2 });

            ibisCards.forEach(function (card) {
                card.style.opacity   = '0';
                card.style.transform = 'translateY(24px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            });
            cardObserver.observe(ibisCards[0]);
        }

    })();
    </script>
</body>
</html>