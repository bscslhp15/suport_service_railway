<?php require_once __DIR__ . '/includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PASS College - Service Support System</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,600;1,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        /* ========================================
           DESIGN SYSTEM - Semantic Tokens
           ======================================== */
        :root {
            /* Primary Colors */
            --primary-dark: #5C1F23;
            --primary: #800000;
            --primary-accent: #8B2E2F;
            --accent-warm: #A84A38;
            
            /* Secondary - Gold */
            --secondary: #D4AF37;
            --secondary-light: #E0B250;
            --secondary-dark: #B07A2B;
            
            /* Background & Text */
            --bg-cream: #FAF7F0;
            --bg-cream-warm: #FBF4E4;
            --text-cream: #FAF7F0;
            --text-dark: #2a2a2a;
            
            /* Semantic Tokens */
            --gradient-hero: linear-gradient(135deg, #5C1F23 0%, #8B2E2F 50%, #A84A38 100%);
            --gradient-gold: linear-gradient(135deg, #E0B250 0%, #B07A2B 100%);
            --gradient-body: radial-gradient(circle at 20% 10%, rgba(212, 175, 55, 0.08) 0%, transparent 50%),
                            radial-gradient(circle at 80% 90%, rgba(92, 31, 35, 0.05) 0%, transparent 50%);
            
            /* Shadows */
            --shadow-elegant: 0 10px 40px rgba(0, 0, 0, 0.1);
            --shadow-card: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-gold: 0 0 30px rgba(212, 175, 55, 0.2);
            --shadow-hover: 0 20px 50px rgba(0, 0, 0, 0.15);
            
            /* Border Radius */
            --radius-md: 1rem;
            --radius-lg: 2rem;
            --radius-xl: 3rem;
            
            /* Typography */
            --font-serif: 'Cormorant Garamond', serif;
            --font-sans: 'Inter', sans-serif;
            
            --letter-spacing-tight: -0.02em;
            --letter-spacing-normal: 0;
            --letter-spacing-wide: 0.05em;
            
            --line-height-tight: 1.2;
            --line-height-normal: 1.6;
            --line-height-relaxed: 1.8;

            /* Motion */
            --ease-elegant: cubic-bezier(0.16, 1, 0.3, 1);
            --shadow-ring: 0 0 0 1px rgba(212, 175, 55, 0.25);
        }

        /* ========================================
           RESET & BASE STYLES
           ======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-sans);
            font-size: 16px;
            color: var(--text-dark);
            background: var(--bg-cream);
            background-image: var(--gradient-body);
            background-attachment: fixed;
            line-height: var(--line-height-normal);
            overflow-x: hidden;
        }

        /* ========================================
           TYPOGRAPHY
           ======================================== */
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-serif);
            font-weight: 700;
            letter-spacing: var(--letter-spacing-tight);
            line-height: var(--line-height-tight);
        }

        h1 {
            font-size: 4rem;
            font-weight: 700;
        }

        h2 {
            font-size: 2.5rem;
        }

        h3 {
            font-size: 1.75rem;
        }

        p {
            line-height: var(--line-height-relaxed);
        }

        a {
            color: inherit;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        :focus-visible {
            outline: 2px solid var(--secondary);
            outline-offset: 3px;
            border-radius: 4px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
                scroll-behavior: auto !important;
            }
        }

        /* Scroll progress indicator */
        .scroll-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            background: var(--gradient-gold);
            z-index: 1200;
            transition: width 0.1s linear;
        }

        /* Generic scroll-reveal utility */
        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.8s var(--ease-elegant), transform 0.8s var(--ease-elegant);
        }

        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ========================================
           NAVIGATION BAR
           ======================================== */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(92, 31, 35, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            padding: 1.25rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: padding 0.4s var(--ease-elegant), box-shadow 0.4s var(--ease-elegant), background 0.4s ease;
        }

        nav.is-scrolled {
            padding: 0.75rem 2rem;
            background: rgba(92, 31, 35, 0.98);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
        }

        nav.is-scrolled .nav-logo {
            width: 46px;
            height: 46px;
        }

        nav.is-scrolled .nav-logo img {
            width: 40px;
            height: 40px;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            transition: width 0.4s var(--ease-elegant), height 0.4s var(--ease-elegant);
        }

        .nav-logo img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            transition: width 0.4s var(--ease-elegant), height 0.4s var(--ease-elegant);
        }

        .nav-branding {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .nav-title {
            font-family: 'ElephantLocal', var(--font-serif);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-cream);
            letter-spacing: 0.5px;
        }

        .nav-subtitle {
            font-size: 0.65rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 2.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-cream);
            font-size: 0.95rem;
            font-weight: 500;
            position: relative;
            transition: color 0.3s ease;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--secondary);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* ========================================
           HERO SECTION
           ======================================== */
        .hero {
            background: var(--gradient-hero);
            position: relative;
            overflow: hidden;
            padding: 8rem 2rem 6rem;
            margin-top: 3.75rem;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 1px 1px, rgba(212, 175, 55, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
        }

        .hero-container {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-left {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(212, 175, 55, 0.15);
            border: 1px solid rgba(212, 175, 55, 0.3);
            padding: 0.75rem 1.5rem;
            border-radius: 2rem;
            width: fit-content;
            color: var(--secondary);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .hero-badge i {
            font-size: 1rem;
        }

        .hero-headline {
            font-family: var(--font-serif);
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--text-cream);
            line-height: 1.15;
            letter-spacing: var(--letter-spacing-tight);
        }

        .hero-headline-italic {
            font-style: italic;
            color: var(--secondary-light);
        }

        .hero-subheading {
            font-size: 1.1rem;
            color: rgba(250, 247, 240, 0.85);
            max-width: 500px;
            line-height: 1.8;
        }

        .hero-cta-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .cta-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--gradient-gold);
            color: var(--primary-dark);
            padding: 1rem 2rem;
            border-radius: 2rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-card), 0 0 20px rgba(212, 175, 55, 0.2);
            font-size: 1rem;
            max-width: fit-content;
            position: relative;
            overflow: hidden;
            isolation: isolate;
        }

        .cta-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -60%;
            width: 40%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.45), transparent);
            transform: skewX(-20deg);
            transition: left 0.7s ease;
            z-index: -1;
        }

        .cta-primary:hover::before {
            left: 130%;
        }

        .cta-primary:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover), 0 0 40px rgba(212, 175, 55, 0.35);
        }

        .cta-primary:active {
            transform: translateY(-2px);
        }

        .cta-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: transparent;
            color: var(--text-cream);
            padding: 1rem 2rem;
            border-radius: 2rem;
            font-weight: 600;
            border: 2px solid rgba(250, 247, 240, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            max-width: fit-content;
        }

        .cta-secondary:hover {
            border-color: var(--secondary);
            color: var(--secondary);
            background: rgba(212, 175, 55, 0.05);
            transform: translateY(-4px);
        }

        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(212, 175, 55, 0.2);
        }

        .stat {
            flex: 1;
            text-align: center;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-family: var(--font-serif);
            font-size: 1.2rem;
            color: var(--text-cream);
            font-weight: 600;
        }

        /* SEAL CARD */
        .hero-right {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            perspective: 1000px;
        }

        .seal-glow {
            position: absolute;
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.3) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(40px);
            animation: float-glow 6s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes float-glow {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        /* Signature motif: a slow ceremonial ring orbiting the seal, like a wax-seal border */
        .seal-ring {
            position: absolute;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            border: 1px dashed rgba(212, 175, 55, 0.35);
            z-index: 0;
            animation: rotate-ring 40s linear infinite;
        }

        .seal-ring::before,
        .seal-ring::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--secondary);
            box-shadow: 0 0 10px 2px rgba(212, 175, 55, 0.5);
        }

        .seal-ring::before {
            top: -4px;
            left: 50%;
            transform: translateX(-50%);
        }

        .seal-ring::after {
            bottom: -4px;
            left: 50%;
            transform: translateX(-50%);
        }

        @keyframes rotate-ring {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .seal-card {
            transform-style: preserve-3d;
            will-change: transform;
        }

        .seal-card {
            background: var(--bg-cream);
            border-radius: var(--radius-lg);
            padding: 3rem 2.5rem;
            box-shadow: var(--shadow-elegant), 0 0 40px rgba(212, 175, 55, 0.15);
            border-top: 3px solid var(--secondary);
            position: relative;
            z-index: 1;
            max-width: 300px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .seal-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover), 0 0 50px rgba(212, 175, 55, 0.25);
        }

        .seal-image {
            width: 180px;
            height: 180px;
            margin: 0 auto 2rem;
            background: transparent;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .seal-image img {
            width: 160px;
            height: 160px;
            object-fit: contain;
        }

        .seal-quote {
            font-family: var(--font-serif);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            font-style: italic;
            line-height: 1.4;
        }

        .seal-description {
            font-size: 0.95rem;
            color: rgba(92, 31, 35, 0.7);
            margin-bottom: 1.5rem;
            line-height: 1.7;
        }

        .seal-accent {
            font-size: 0.75rem;
            color: var(--secondary-dark);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 700;
        }

        /* CURVE DIVIDER */
        .curve-divider {
            width: 100%;
            height: 100px;
            margin-top: -1px;
            background: linear-gradient(to bottom, var(--primary-accent), var(--bg-cream));
        }

        /* ========================================
           ABOUT STRIP
           ======================================== */
        .about {
            background: var(--bg-cream);
            padding: 5rem 2rem;
        }

        .about-container {
            max-width: 1300px;
            margin: 0 auto;
            background: var(--gradient-hero);
            border-radius: var(--radius-lg);
            border: 2px solid var(--secondary);
            padding: 4rem;
            box-shadow: var(--shadow-elegant);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .about-left h2 {
            color: var(--text-cream);
            margin-bottom: 1.5rem;
            line-height: 1.3;
        }

        .about-left p {
            color: rgba(250, 247, 240, 0.9);
            font-size: 1.05rem;
            line-height: 1.8;
        }

        .about-right {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .audience-card {
            background: rgba(92, 31, 35, 0.4);
            border: 2px solid rgba(212, 175, 55, 0.3);
            border-radius: var(--radius-md);
            padding: 2rem 1.5rem;
            text-align: center;
            backdrop-filter: blur(10px);
            transition: all 0.4s var(--ease-elegant);
            position: relative;
            overflow: hidden;
        }

        .audience-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-gold);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s var(--ease-elegant);
        }

        .audience-card:hover::before {
            transform: scaleX(1);
        }

        .audience-card:hover {
            border-color: var(--secondary);
            background: rgba(212, 175, 55, 0.1);
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
        }

        .audience-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--secondary);
            transition: transform 0.4s var(--ease-elegant);
            display: inline-block;
        }

        .audience-card:hover .audience-icon {
            transform: scale(1.15) translateY(-2px);
        }

        .audience-label {
            font-family: var(--font-serif);
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 0.75rem;
        }

        .audience-description {
            font-size: 0.9rem;
            color: rgba(250, 247, 240, 0.85);
            line-height: 1.6;
        }

        /* ========================================
           FOOTER
           ======================================== */
        footer {
            background: var(--bg-cream);
            border-top: 1px solid rgba(92, 31, 35, 0.1);
            padding: 3rem 2rem;
        }

        .footer-container {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .footer-left {
            font-size: 0.9rem;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .footer-right {
            text-align: right;
        }

        .footer-tagline {
            font-family: var(--font-serif);
            font-size: 0.85rem;
            color: var(--secondary-dark);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
        }

        /* ========================================
           RESPONSIVE DESIGN
           ======================================== */
        @media (max-width: 768px) {
            nav {
                padding: 1rem;
            }

            .nav-right {
                gap: 1rem;
            }

            .nav-links {
                gap: 1rem;
            }

            .nav-links a {
                font-size: 0.85rem;
            }

            .hero {
                padding: 6rem 1.5rem 4rem;
                margin-top: 3.5rem;
            }

            .hero-container {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .hero-headline {
                font-size: 2.5rem;
            }

            .hero-badge {
                font-size: 0.75rem;
            }

            .cta-primary,
            .cta-secondary {
                width: 100%;
                text-align: center;
                justify-content: center;
            }

            .hero-stats {
                flex-direction: column;
                gap: 1.5rem;
            }

            .about-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 2.5rem;
            }

            .about-right {
                grid-template-columns: 1fr 1fr;
            }

            .seal-card {
                max-width: 100%;
            }

            .footer-container {
                flex-direction: column;
                gap: 1.5rem;
                text-align: center;
            }

            .footer-right {
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 2.5rem;
            }

            h2 {
                font-size: 1.75rem;
            }

            .hero-headline {
                font-size: 2rem;
            }

            .nav-title {
                font-size: 0.95rem;
            }

            .nav-subtitle {
                font-size: 0.6rem;
            }

            .hero-badge {
                flex-direction: column;
                gap: 0.5rem;
            }

            .about-right {
                grid-template-columns: 1fr;
            }

            .hero-stats {
                gap: 1rem;
            }
        }

        /* ========================================
           ANIMATIONS & TRANSITIONS
           ======================================== */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-left {
            animation: slideIn 0.8s ease-out;
        }

        .seal-card {
            animation: slideIn 0.8s ease-out 0.2s both;
        }
    </style>
</head>
<body>
    <div class="scroll-progress" id="scrollProgress"></div>

    <!-- NAVIGATION -->
    <nav>
        <div class="nav-left">
            <div class="nav-logo">
                <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS College">
            </div>
            <div class="nav-branding">
                <div class="nav-title">PASS COLLEGE</div>
                <div class="nav-subtitle">Service Support System</div>
            </div>
        </div>
        <div class="nav-right">
            <ul class="nav-links">
                <li><a href="#about">About</a></li>
                <li><a href="#support">Support</a></li>
            </ul>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="hero-container">
            <!-- LEFT COLUMN -->
            <div class="hero-left">
                <div class="hero-badge">
                    <i class="fas fa-sparkles"></i>
                    Service Support System
                </div>

                <h1 class="hero-headline">
                    Your <span class="hero-headline-italic">Passport</span><br>
                    to <span class="hero-headline-italic">Success.</span>
                </h1>

                <p class="hero-subheading">
                    A unified platform for PASS College students to access services, resources, and connect with faculty. Built for excellence, designed for you.
                </p>

                <div class="hero-cta-group">
                    <button class="cta-primary" onclick="window.location.href='auth/student_login.php'">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign in to Student Portal
                    </button>
                    <button class="cta-secondary" onclick="document.getElementById('about').scrollIntoView({ behavior: 'smooth' })">
                        <span>Learn more</span>
                        <i class="fas fa-arrow-down"></i>
                    </button>
                </div>

                <div class="hero-stats">
                    <div class="stat">
                        <div class="stat-label">Unified Home</div>
                        <div class="stat-value">For All Services</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">24/7 Access</div>
                        <div class="stat-value">Always Available</div>
                    </div>
                    <div class="stat">
                        <div class="stat-label">Est. Excellence</div>
                        <div class="stat-value">Since Foundation</div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN - SEAL CARD -->
            <div class="hero-right">
                <div class="seal-glow"></div>
                <div class="seal-ring"></div>
                <div class="seal-card" id="sealCard">
                    <div class="seal-image">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS College Official Seal">
                    </div>
                    <p class="seal-quote">
                        "Pass College,<br>Your Passport<br>to Success."
                    </p>
                    <p class="seal-description">
                        Philippine Accountancy and Science School — A tradition of academic excellence and student support.
                    </p>
                    <div class="seal-accent">
                        — Est. Excellence —
                    </div>
                </div>
            </div>
        </div>

        <div class="curve-divider"></div>
    </section>

    <!-- ABOUT SECTION -->
    <section class="about" id="about">
        <div class="about-container">
            <!-- LEFT COLUMN -->
            <div class="about-left reveal">
                <h2>A modern home for a tradition of excellence.</h2>
                <p>
                    PASS College's Service Support System brings together all student resources, academic guidance, and administrative services in one elegant, intuitive platform. Whether you're seeking academic support, scheduling consultations, or accessing library resources, everything is designed around your success.
                </p>
            </div>

            <!-- RIGHT COLUMN - AUDIENCE GRID -->
            <div class="about-right">
                <div class="audience-card reveal" style="transition-delay: 0.05s;">
                    <div class="audience-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="audience-label">Students</div>
                    <p class="audience-description">Access all your academic resources and support services in one unified platform.</p>
                </div>

                <div class="audience-card reveal" style="transition-delay: 0.15s;">
                    <div class="audience-icon">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                    <div class="audience-label">Faculty</div>
                    <p class="audience-description">Manage appointments and coordinate with students efficiently.</p>
                </div>

                <div class="audience-card reveal" style="transition-delay: 0.25s;">
                    <div class="audience-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <div class="audience-label">Service Heads</div>
                    <p class="audience-description">Oversee and manage your department's support services.</p>
                </div>

                <div class="audience-card reveal" style="transition-delay: 0.35s;">
                    <div class="audience-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="audience-label">Administrators</div>
                    <p class="audience-description">Full system control and institutional oversight.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="support">
        <div class="footer-container">
            <div class="footer-left">
                <strong>© 2026 PASS College</strong><br>
                Philippine Accountancy and Science School
            </div>
            <div class="footer-right">
                <div class="footer-tagline">Your Passport to Success</div>
            </div>
        </div>
    </footer>

    <script>
        function scrollToBottom() {
            // Navigate to login
            window.location.href = 'auth/login.php?type=student';
        }

        // Smooth scroll for all anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#') {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        });

        // Active nav link on scroll
        const navEl = document.querySelector('nav');
        const scrollProgressEl = document.getElementById('scrollProgress');

        window.addEventListener('scroll', function() {
            const hero = document.querySelector('.hero');
            const about = document.getElementById('about');
            const navLinks = document.querySelectorAll('.nav-links a');

            let currentSection = '';
            if (window.scrollY < about.offsetTop - 200) {
                currentSection = '';
            } else if (window.scrollY < document.querySelector('footer').offsetTop) {
                currentSection = 'about';
            }

            navLinks.forEach(link => {
                link.style.color = link.getAttribute('href') === '#' + currentSection ? 'var(--secondary)' : 'var(--text-cream)';
            });

            // Nav shrink/shadow state
            navEl.classList.toggle('is-scrolled', window.scrollY > 40);

            // Scroll progress bar
            const scrollable = document.documentElement.scrollHeight - window.innerHeight;
            const progress = scrollable > 0 ? (window.scrollY / scrollable) * 100 : 0;
            scrollProgressEl.style.width = progress + '%';
        });

        // Scroll-reveal animations
        const revealTargets = document.querySelectorAll('.reveal');
        if ('IntersectionObserver' in window && revealTargets.length) {
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });

            revealTargets.forEach(target => revealObserver.observe(target));
        } else {
            revealTargets.forEach(target => target.classList.add('is-visible'));
        }

        // Subtle seal card tilt on pointer move (skipped for touch devices and reduced-motion preference)
        const sealCard = document.getElementById('sealCard');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const isTouchDevice = window.matchMedia('(hover: none)').matches;

        if (sealCard && !prefersReducedMotion && !isTouchDevice) {
            const sealWrapper = sealCard.parentElement;

            sealWrapper.addEventListener('mousemove', function(e) {
                const rect = sealCard.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width - 0.5;
                const y = (e.clientY - rect.top) / rect.height - 0.5;
                sealCard.style.transform = `translateY(-8px) rotateY(${x * 8}deg) rotateX(${y * -8}deg)`;
            });

            sealWrapper.addEventListener('mouseleave', function() {
                sealCard.style.transform = '';
            });
        }
    </script>
</body>
</html>
