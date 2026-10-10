<?php
/**
 * schemes.php — Government Health Schemes & Medical Benefits
 * Unified for both User and Donor dashboards.
 * Features:
 * - Direct integration with api/get_schemes.php for live search, filtering, and smart matching
 * - Atmospheric animation and design identical to Donor and User pages (Interactive ECG, Blood Canvas, Cursor particle trail)
 * - Standardized unified header bar (Menu, Profile, Call Directory, Logout)
 * - Transparent light-red slide-out navigation drawer
 */

session_start();
$userId    = $_SESSION['user_id'] ?? null;
$userRole  = $_SESSION['user_role'] ?? 'user';
$userName  = $_SESSION['user_name'] ?? ($userId ? 'Verified User' : 'Guest');
$isDonor   = ($userRole === 'donor');
$roleTitle = $isDonor ? 'Donor' : 'Requester';
$backUrl   = $isDonor ? 'donor/index.php' : 'user/user.html';
$backLabel = $isDonor ? 'Donor Dashboard' : 'My Dashboard';
$backIcon  = $isDonor ? 'fa-heart-pulse' : 'fa-house';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Government Health Schemes & Medical Benefits — LifeFlow</title>
    <meta name="description" content="Discover verified central and state government healthcare schemes, medical assistance programs, financial support, insurance, and blood-related benefits available in India." />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="call_directory.css" />
    <link rel="stylesheet" href="assets/css/call.css" />
    <link rel="stylesheet" href="assets/css/header_menu.css?v=2.1" />

    <style>
        /* ============================================================
           ROOT VARIABLES & PALETTE — Unified Light Healthcare Theme
           ============================================================ */
        :root {
            --bg-dark: #f6f9fc;
            --bg-card: rgba(255, 255, 255, .88);
            --bg-glass: rgba(255, 255, 255, .7);
            --border-glass: rgba(220, 38, 38, .13);
            --border-glow: rgba(239, 68, 68, .35);
            --crimson: #e63946;
            --crimson-glow: #ff2a44;
            --blood-dark: #8b0000;
            --blood-light: #ff4d6d;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent-cyan: #0284c7;
            --accent-green: #06d6a0;
            --accent-amber: #d97706;
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-heading: 'Outfit', sans-serif;
            --shadow-card: 0 4px 24px rgba(15, 23, 42, .06), 0 1px 3px rgba(15, 23, 42, .04);
            --shadow-card-hover: 0 14px 40px rgba(220, 38, 38, .12), 0 4px 12px rgba(239, 68, 68, .08);
            --radius-xl: 24px;
            --radius-lg: 18px;
            --radius-md: 14px;
            --radius-pill: 50px;
        }

        /* RESET & BASE */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-main);
            background-color: var(--bg-dark);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #eef2f7; }
        ::-webkit-scrollbar-thumb { background: #fca5a5; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--crimson); }

        .container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 2;
        }

        /* BACKGROUND CANVAS LAYERS (Matching User & Donor animation) */
        #bgCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; }
        #cursorCanvas { position: fixed; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 99; }
        .bg-gradient-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 15% 20%, rgba(230, 57, 70, .07) 0%, transparent 50%),
                radial-gradient(circle at 85% 75%, rgba(2, 132, 199, .05) 0%, transparent 50%),
                linear-gradient(180deg, #f6f9fc 0%, #eef2f7 100%);
            z-index: 0;
            pointer-events: none;
        }

        /* LIVE ECG TOP BAR */
        .ecg-topbar {
            background: rgba(255, 255, 255, .88);
            border-bottom: 1px solid var(--border-glass);
            padding: 7px 0;
            font-size: 0.82rem;
            color: var(--text-muted);
            backdrop-filter: blur(12px);
            position: relative;
            z-index: 101;
        }
        .ecg-topbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .ecg-live-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-green);
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .pulse-dot {
            width: 9px;
            height: 9px;
            background: var(--crimson-glow);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--crimson-glow);
            animation: pulseGlow 1.2s infinite ease-in-out;
        }
        @keyframes pulseGlow {
            0% { transform: scale(0.9); box-shadow: 0 0 4px var(--crimson-glow); }
            50% { transform: scale(1.4); box-shadow: 0 0 16px var(--crimson-glow); }
            100% { transform: scale(0.9); box-shadow: 0 0 4px var(--crimson-glow); }
        }
        .ecg-canvas-mini {
            width: 140px;
            height: 24px;
            vertical-align: middle;
        }
        .topbar-links a {
            color: var(--text-muted);
            font-weight: 600;
            transition: color .3s;
            margin-left: 16px;
        }
        .topbar-links a:hover { color: var(--crimson); }

        /* STANDARDIZED HEADER */
        header {
            background: rgba(255, 255, 255, .82);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-glass);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 14px 0;
            transition: all 0.4s ease;
        }
        header.scrolled {
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 10px 30px rgba(15, 23, 42, .07);
            border-bottom-color: rgba(220, 38, 38, .18);
        }
        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: var(--font-heading);
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }
        .logo-icon-wrap {
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ff2a44, #8b0000);
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(255, 42, 68, .35);
            animation: floatSlow 3s ease-in-out infinite alternate;
        }
        @keyframes floatSlow {
            0% { transform: translateY(0px) rotate(0deg); }
            100% { transform: translateY(-4px) rotate(4deg); }
        }
        .logo-icon-wrap i { color: #fff; font-size: 1.3rem; }
        .logo span {
            background: linear-gradient(90deg, #e63946, #ff4d6d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* HERO SECTION */
        .hero {
            padding: 60px 0 40px;
            position: relative;
            z-index: 2;
        }
        .hero-content { max-width: 820px; }
        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(239, 68, 68, .08);
            border: 1px solid rgba(239, 68, 68, .2);
            color: var(--crimson);
            padding: 6px 18px;
            border-radius: var(--radius-pill);
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 18px;
            letter-spacing: 0.5px;
        }
        .hero-content h1 {
            font-family: var(--font-heading);
            font-size: 3.2rem;
            font-weight: 900;
            line-height: 1.15;
            color: var(--text-main);
            margin-bottom: 16px;
            letter-spacing: -1px;
        }
        .hero-content h1 .blood-gradient {
            background: linear-gradient(135deg, #e63946 0%, #ff2a44 50%, #ff4d6d 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-content p {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 28px;
            max-width: 640px;
            font-weight: 400;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, #ff2a44 0%, #b91c1c 100%);
            color: #fff;
            padding: 13px 30px;
            border-radius: var(--radius-pill);
            font-weight: 700;
            font-size: 0.98rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 22px rgba(255, 42, 68, .35);
        }
        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 30px rgba(255, 42, 68, .5);
        }
        .btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(255, 255, 255, .85);
            color: var(--text-main);
            padding: 12px 26px;
            border-radius: var(--radius-pill);
            font-weight: 700;
            font-size: 0.98rem;
            border: 1.5px solid rgba(15, 23, 42, .12);
            backdrop-filter: blur(10px);
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-outline:hover {
            border-color: var(--crimson-glow);
            background: rgba(239, 68, 68, .06);
            color: var(--crimson);
            transform: translateY(-2px);
        }

        /* SEARCH & FILTER */
        .search-filter-section {
            padding: 0 0 32px;
            position: relative;
            z-index: 3;
        }
        .search-bar-wrap {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-xl);
            padding: 18px 20px;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-card);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .search-input-wrap {
            flex: 1 1 280px;
            position: relative;
        }
        .search-input-wrap i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .95rem;
        }
        .search-input-wrap input {
            width: 100%;
            padding: 13px 18px 13px 46px;
            background: rgba(255, 255, 255, .95);
            border: 1.5px solid rgba(15, 23, 42, .08);
            border-radius: var(--radius-md);
            color: var(--text-main);
            font-family: var(--font-main);
            font-size: .95rem;
            transition: all .3s;
        }
        .search-input-wrap input:focus {
            outline: none;
            border-color: var(--crimson-glow);
            box-shadow: 0 0 0 4px rgba(255, 42, 68, .1);
        }
        .filter-select {
            padding: 13px 18px;
            background: rgba(255, 255, 255, .95);
            border: 1.5px solid rgba(15, 23, 42, .08);
            border-radius: var(--radius-md);
            color: var(--text-main);
            font-family: var(--font-main);
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s;
            min-width: 160px;
        }
        .filter-select:focus {
            outline: none;
            border-color: var(--crimson-glow);
            box-shadow: 0 0 0 4px rgba(255, 42, 68, .1);
        }

        /* CATEGORY CHIPS */
        .category-scroll {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .cat-chip {
            padding: 8px 18px;
            border-radius: var(--radius-pill);
            font-size: .85rem;
            font-weight: 700;
            background: rgba(255, 255, 255, .75);
            border: 1.5px solid rgba(15, 23, 42, .08);
            color: var(--text-muted);
            cursor: pointer;
            transition: all .25s;
            white-space: nowrap;
        }
        .cat-chip:hover {
            border-color: var(--crimson-glow);
            color: var(--crimson);
            background: rgba(239, 68, 68, .05);
        }
        .cat-chip.active {
            background: linear-gradient(135deg, #ff2a44, #b91c1c);
            color: #fff;
            border-color: transparent;
            box-shadow: 0 4px 14px rgba(255, 42, 68, .3);
        }
        .results-count {
            font-size: .88rem;
            color: var(--text-muted);
            margin-top: 14px;
            font-weight: 600;
        }
        .results-count strong { color: var(--text-main); }

        /* SCHEMES GRID */
        .schemes-section {
            padding: 10px 0 60px;
            position: relative;
            z-index: 2;
        }
        .schemes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 22px;
        }
        .scheme-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-xl);
            padding: 26px 24px;
            backdrop-filter: blur(16px);
            box-shadow: var(--shadow-card);
            transition: all .35s cubic-bezier(.4, 0, .2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .scheme-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--crimson-glow), transparent);
            opacity: 0;
            transition: opacity .3s;
        }
        .scheme-card:hover {
            transform: translateY(-6px);
            border-color: rgba(239, 68, 68, .3);
            box-shadow: var(--shadow-card-hover);
        }
        .scheme-card:hover::before { opacity: 1; }

        .scheme-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
            gap: 10px;
        }
        .scheme-icon-wrap {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, rgba(230, 57, 70, .12), rgba(255, 42, 68, .06));
            border: 1px solid rgba(239, 68, 68, .18);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            color: var(--crimson);
            flex-shrink: 0;
            transition: all .3s;
        }
        .scheme-card:hover .scheme-icon-wrap {
            background: linear-gradient(135deg, #ff2a44, #b91c1c);
            color: #fff;
            box-shadow: 0 0 18px rgba(255, 42, 68, .35);
            transform: scale(1.06) rotate(4deg);
        }
        .scheme-labels {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
        }
        .label-pill {
            font-size: .66rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: var(--radius-pill);
            text-transform: uppercase;
            letter-spacing: .3px;
            white-space: nowrap;
        }
        .label-pill.central { background: rgba(2, 132, 199, .1); color: #0284c7; border: 1px solid rgba(2, 132, 199, .2); }
        .label-pill.state { background: rgba(6, 214, 160, .1); color: #059669; border: 1px solid rgba(6, 214, 160, .2); }
        .label-pill.financial { background: rgba(245, 158, 11, .1); color: #d97706; border: 1px solid rgba(245, 158, 11, .2); }
        .label-pill.emergency { background: rgba(239, 68, 68, .1); color: #dc2626; border: 1px solid rgba(239, 68, 68, .2); }
        .label-pill.insurance { background: rgba(139, 92, 246, .1); color: #7c3aed; border: 1px solid rgba(139, 92, 246, .2); }
        .label-pill.free { background: rgba(6, 214, 160, .1); color: #059669; border: 1px solid rgba(6, 214, 160, .2); }
        .label-pill.maternal { background: rgba(236, 72, 153, .1); color: #db2777; border: 1px solid rgba(236, 72, 153, .2); }
        .label-pill.senior { background: rgba(99, 102, 241, .1); color: #4f46e5; border: 1px solid rgba(99, 102, 241, .2); }
        .label-pill.critical { background: rgba(220, 38, 38, .1); color: #b91c1c; border: 1px solid rgba(220, 38, 38, .2); }

        .scheme-card h3 {
            font-family: var(--font-heading);
            font-size: 1.22rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 4px;
            line-height: 1.3;
        }
        .scheme-dept {
            font-size: .8rem;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 10px;
            line-height: 1.4;
        }
        .scheme-desc {
            font-size: .9rem;
            color: var(--text-muted);
            line-height: 1.55;
            margin-bottom: 14px;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .scheme-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
            font-size: .82rem;
        }
        .scheme-meta-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            color: var(--text-muted);
        }
        .scheme-meta-row i {
            color: var(--crimson);
            width: 16px;
            text-align: center;
            margin-top: 3px;
            font-size: .78rem;
            flex-shrink: 0;
        }
        .scheme-meta-row strong { color: var(--text-main); font-weight: 700; }

        .scheme-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid rgba(15, 23, 42, .06);
        }
        .scheme-actions .btn-apply {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: linear-gradient(135deg, #ff2a44, #b91c1c);
            color: #fff;
            padding: 11px 16px;
            border-radius: var(--radius-pill);
            font-weight: 700;
            font-size: .85rem;
            border: none;
            cursor: pointer;
            transition: all .3s;
            text-align: center;
        }
        .scheme-actions .btn-apply:hover {
            box-shadow: 0 6px 20px rgba(255, 42, 68, .4);
            transform: translateY(-2px);
        }
        .scheme-actions .btn-details {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(255, 255, 255, .85);
            color: var(--text-main);
            padding: 11px 16px;
            border-radius: var(--radius-pill);
            font-weight: 700;
            font-size: .85rem;
            border: 1.5px solid rgba(15, 23, 42, .1);
            cursor: pointer;
            transition: all .3s;
        }
        .scheme-actions .btn-details:hover {
            border-color: var(--crimson-glow);
            color: var(--crimson);
            background: rgba(239, 68, 68, .04);
        }
        .scheme-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            font-size: .78rem;
            color: var(--text-muted);
        }
        .scheme-card-footer a {
            color: var(--accent-cyan);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            max-width: 220px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .scheme-card-footer a:hover { text-decoration: underline; }
        .bookmark-btn {
            background: none;
            border: none;
            color: #cbd5e1;
            font-size: 1rem;
            cursor: pointer;
            transition: all .3s;
            padding: 4px;
        }
        .bookmark-btn:hover, .bookmark-btn.saved { color: var(--crimson); }

        /* FINDER SECTION */
        .finder-section {
            padding: 60px 0;
            position: relative;
            z-index: 2;
        }
        .finder-box {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 28px;
            padding: 44px 40px;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-card);
            max-width: 900px;
            margin: 0 auto;
        }
        .finder-header { text-align: center; margin-bottom: 32px; }
        .finder-header .section-tag {
            color: var(--crimson-glow);
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        .finder-header h2 {
            font-family: var(--font-heading);
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        .finder-header h2 span {
            background: linear-gradient(135deg, #e63946, #ff4d6d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .finder-header p {
            color: var(--text-muted);
            font-size: 1rem;
            max-width: 560px;
            margin: 0 auto;
        }
        .finder-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .finder-group label {
            display: block;
            font-weight: 700;
            font-size: .88rem;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        .finder-group select {
            width: 100%;
            padding: 13px 16px;
            background: rgba(255, 255, 255, .95);
            border: 1.5px solid rgba(15, 23, 42, .08);
            border-radius: var(--radius-md);
            color: var(--text-main);
            font-family: var(--font-main);
            font-size: .92rem;
            font-weight: 500;
            cursor: pointer;
            transition: all .3s;
        }
        .finder-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .finder-result {
            margin-top: 28px;
            padding: 24px;
            background: rgba(6, 214, 160, .06);
            border: 1px solid rgba(6, 214, 160, .2);
            border-radius: var(--radius-lg);
            display: none;
            animation: slideUp .5s ease;
        }
        .finder-result.show { display: block; }
        .finder-result h4 {
            font-family: var(--font-heading);
            font-size: 1.15rem;
            color: var(--text-main);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .finder-result h4 i { color: #06d6a0; }
        .finder-result-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        .finder-result-tags .compat-pill {
            background: rgba(255, 255, 255, .9);
            padding: 8px 18px;
            border-radius: var(--radius-pill);
            font-weight: 700;
            font-size: .88rem;
            color: var(--text-main);
            border: 1px solid rgba(15, 23, 42, .08);
            transition: all .3s;
            cursor: pointer;
        }
        .finder-result-tags .compat-pill:hover {
            border-color: var(--crimson-glow);
            box-shadow: 0 4px 12px rgba(239, 68, 68, .15);
            transform: translateY(-2px);
        }

        /* EMERGENCY BANNER */
        .emergency-banner {
            padding: 40px 0;
            position: relative;
            z-index: 2;
        }
        .emergency-inner {
            background: linear-gradient(135deg, #1e1b2e 0%, #2d1b2e 50%, #1a1f2e 100%);
            border-radius: 28px;
            padding: 44px 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 42, 68, .25);
            box-shadow: 0 20px 50px rgba(0, 0, 0, .2);
        }
        .emergency-text h3 {
            font-family: var(--font-heading);
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 6px;
        }
        .emergency-text p {
            color: rgba(255, 255, 255, .65);
            font-size: 1rem;
        }
        .emergency-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        .btn-em {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 24px;
            border-radius: var(--radius-pill);
            font-weight: 700;
            font-size: .92rem;
            border: none;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
        }
        .btn-em.blood {
            background: linear-gradient(135deg, #ff2a44, #b91c1c);
            color: #fff;
            box-shadow: 0 6px 20px rgba(255, 42, 68, .4);
        }
        .btn-em.blood:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(255, 42, 68, .55); }
        .btn-em.find {
            background: rgba(255, 255, 255, .12);
            color: #fff;
            border: 1.5px solid rgba(255, 255, 255, .25);
            backdrop-filter: blur(10px);
        }
        .btn-em.find:hover { background: rgba(255, 255, 255, .2); transform: translateY(-3px); }

        /* DISCLAIMER */
        .disclaimer-banner {
            padding: 0 0 40px;
            position: relative;
            z-index: 2;
        }
        .disclaimer-inner {
            background: rgba(245, 158, 11, .08);
            border: 1px solid rgba(245, 158, 11, .25);
            border-radius: var(--radius-lg);
            padding: 18px 24px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            font-size: .88rem;
            color: #92400e;
            line-height: 1.6;
        }
        .disclaimer-inner i {
            color: #d97706;
            font-size: 1.1rem;
            margin-top: 3px;
            flex-shrink: 0;
        }

        /* MODAL */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(8px);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-overlay.show { display: flex; }
        .modal-content {
            background: #fff;
            border-radius: 28px;
            max-width: 720px;
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            padding: 36px 34px;
            position: relative;
            box-shadow: 0 30px 80px rgba(15, 23, 42, .2);
            animation: modalIn .35s cubic-bezier(.4, 0, .2, 1);
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-close {
            position: absolute;
            top: 18px;
            right: 18px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(15, 23, 42, .05);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 1rem;
            transition: all .3s;
        }
        .modal-close:hover { background: rgba(230, 57, 70, .1); color: var(--crimson); }
        .modal-header {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-right: 40px;
        }
        .modal-header .scheme-icon-wrap { width: 56px; height: 56px; font-size: 1.5rem; }
        .modal-header h3 {
            font-family: var(--font-heading);
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.25;
        }
        .modal-body-section {
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid rgba(15, 23, 42, .06);
        }
        .modal-body-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .modal-body-section h4 {
            font-family: var(--font-heading);
            font-size: .98rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .modal-body-section h4 i { color: var(--crimson); font-size: .88rem; }
        .modal-body-section p, .modal-body-section li {
            font-size: .9rem;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .modal-body-section .official-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-cyan);
            font-weight: 700;
            font-size: .88rem;
            margin-top: 4px;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 22px;
            flex-wrap: wrap;
        }
        .modal-actions .btn-primary, .modal-actions .btn-outline { flex: 1; min-width: 160px; }

        /* RECENTLY VIEWED */
        .recent-section {
            padding: 0 0 50px;
            position: relative;
            z-index: 2;
        }
        .recent-section .section-label {
            font-family: var(--font-heading);
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .recent-section .section-label i { color: var(--crimson); }
        .recent-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .recent-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, .85);
            border: 1.5px solid rgba(15, 23, 42, .08);
            border-radius: var(--radius-pill);
            font-size: .84rem;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            transition: all .3s;
        }
        .recent-chip:hover {
            border-color: var(--crimson-glow);
            box-shadow: 0 4px 12px rgba(239, 68, 68, .12);
            transform: translateY(-2px);
        }
        .recent-chip i { color: var(--crimson); font-size: .72rem; }

        /* FOOTER */
        footer {
            background: #0f172a;
            border-top: 1px solid rgba(255, 255, 255, .06);
            padding: 50px 0 26px;
            position: relative;
            z-index: 2;
        }
        footer .container {
            display: grid;
            grid-template-columns: 1.8fr 1fr 1fr 1.2fr;
            gap: 36px;
        }
        footer h4 {
            font-family: var(--font-heading);
            color: #fff;
            font-size: 1.1rem;
            margin-bottom: 16px;
            font-weight: 700;
        }
        footer p {
            color: rgba(255, 255, 255, .55);
            font-size: .9rem;
            line-height: 1.7;
        }
        footer .socials { display: flex; gap: 12px; margin-top: 16px; }
        footer .socials a {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, .06);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            transition: all .3s;
            font-size: .9rem;
        }
        footer .socials a:hover {
            background: var(--crimson-glow);
            box-shadow: 0 0 15px rgba(255, 42, 68, .5);
            transform: translateY(-3px);
        }
        footer ul { list-style: none; }
        footer ul li { margin-bottom: 9px; }
        footer ul li a {
            color: rgba(255, 255, 255, .55);
            transition: color .3s;
            font-size: .9rem;
        }
        footer ul li a:hover { color: var(--crimson-glow); }
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, .06);
            margin-top: 36px;
            padding-top: 20px;
            text-align: center;
            font-size: .85rem;
            color: rgba(255, 255, 255, .35);
            grid-column: 1 / -1;
        }

        .section-header { text-align: center; margin-bottom: 36px; }
        .section-header .section-tag {
            color: var(--crimson-glow);
            font-weight: 700;
            font-size: 0.85rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        .section-header h2 {
            font-family: var(--font-heading);
            font-size: 2.3rem;
            font-weight: 800;
            color: var(--text-main);
        }
        .section-header h2 span {
            background: linear-gradient(135deg, #e63946, #ff4d6d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .section-header p {
            color: var(--text-muted);
            max-width: 640px;
            margin: 10px auto 0;
            font-size: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            grid-column: 1 / -1;
        }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        .empty-state h4 { font-family: var(--font-heading); font-size: 1.2rem; color: var(--text-main); margin-bottom: 6px; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up { animation: slideUp .5s ease both; }

        @media (max-width: 900px) {
            footer .container { grid-template-columns: 1fr 1fr; }
            .finder-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .hero-content h1 { font-size: 2.2rem; }
            .search-bar-wrap { flex-direction: column; }
            .search-input-wrap { width: 100%; }
            .filter-select { width: 100%; }
            footer .container { grid-template-columns: 1fr; }
            .schemes-grid { grid-template-columns: 1fr; }
            .emergency-inner { padding: 30px 20px; }
            .emergency-actions { width: 100%; }
            .emergency-actions .btn-em { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body class="user-page" data-theme="light">

<!-- Background Animation Layers -->
<canvas id="bgCanvas" aria-hidden="true"></canvas>
<canvas id="cursorCanvas" aria-hidden="true"></canvas>
<div class="bg-gradient-overlay"></div>

<!-- ============================================================
     LIVE ECG TOP BAR
     ============================================================ -->
<div class="ecg-topbar">
    <div class="container">
        <div class="ecg-live-badge">
            <span class="pulse-dot"></span>
            <span>LIFEFLOW LIVE MEDICAL AID LINK</span>
            <canvas class="ecg-canvas-mini" id="ecgMini" width="140" height="24"></canvas>
        </div>
       
    </div>
</div>

<!-- ============================================================
     STANDARDIZED HEADER (Unified Header System)
     ============================================================ -->


<!-- ============================================================
     HERO SECTION
     ============================================================ -->
<section class="hero">
    <div class="container">
        <div class="hero-content fade-in-up">
            <div class="hero-tag">
                <i class="fas fa-landmark"></i> Verified Government Healthcare Directory
            </div>
            <h1>
                Government <span class="blood-gradient">Health Schemes</span><br />
                &amp; Medical Benefits
            </h1>
            <p>
                Discover verified central and state government healthcare schemes, free hospital treatment,
                critical illness assistance, health insurance, and emergency blood support across India.
            </p>
            <div class="hero-actions">
                <a href="#schemes" class="btn-primary">
                    <i class="fas fa-search"></i> Browse Schemes
                </a>
                <a href="#finder" class="btn-outline">
                    <i class="fas fa-clipboard-check"></i> Find the Right Scheme
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     SEARCH & FILTER (Live API Driven)
     ============================================================ -->
<section class="search-filter-section">
    <div class="container">
        <div class="search-bar-wrap">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Live search by scheme name, department, or medical condition..." aria-label="Search schemes" />
            </div>
            <select class="filter-select" id="stateFilter" aria-label="Filter by state">
                <option value="">All States / Central</option>
                <option value="central">Central Government</option>
                <option value="andhra-pradesh">Andhra Pradesh</option>
                <option value="tamil-nadu">Tamil Nadu</option>
                <option value="maharashtra">Maharashtra</option>
                <option value="gujarat">Gujarat</option>
                <option value="west-bengal">West Bengal</option>
                <option value="rajasthan">Rajasthan</option>
                <option value="karnataka">Karnataka</option>
                <option value="kerala">Kerala</option>
                <option value="punjab">Punjab</option>
                <option value="assam">Assam</option>
                <option value="haryana">Haryana</option>
                <option value="himachal-pradesh">Himachal Pradesh</option>
            </select>
            <select class="filter-select" id="categoryFilter" aria-label="Filter by category">
                <option value="">All Categories</option>
                <option value="insurance">Health Insurance</option>
                <option value="free-treatment">Free / Subsidized Treatment</option>
                <option value="financial">Financial Assistance</option>
                <option value="emergency">Emergency Medical</option>
                <option value="maternal">Maternal &amp; Child</option>
                <option value="senior">Senior Citizen</option>
                <option value="critical">Critical Illness</option>
                <option value="infrastructure">Healthcare Infrastructure</option>
            </select>
            <select class="filter-select" id="sortFilter" aria-label="Sort schemes">
                <option value="relevance">Most Relevant</option>
                <option value="updated">Recently Updated</option>
                <option value="name-asc">Name (A–Z)</option>
                <option value="name-desc">Name (Z–A)</option>
            </select>
        </div>
        <div class="category-scroll" id="categoryChips">
            <button class="cat-chip active" data-cat="">All Schemes</button>
            <button class="cat-chip" data-cat="insurance">Insurance</button>
            <button class="cat-chip" data-cat="free-treatment">Free Treatment</button>
            <button class="cat-chip" data-cat="financial">Financial Assistance</button>
            <button class="cat-chip" data-cat="emergency">Emergency Support</button>
            <button class="cat-chip" data-cat="maternal">Maternal &amp; Child</button>
            <button class="cat-chip" data-cat="senior">Senior Citizen</button>
            <button class="cat-chip" data-cat="critical">Critical Illness</button>
            <button class="cat-chip" data-cat="infrastructure">Infrastructure</button>
        </div>
        <div class="results-count" id="resultsCount"></div>
    </div>
</section>

<!-- ============================================================
     SCHEMES GRID
     ============================================================ -->
<section class="schemes-section" id="schemes">
    <div class="container">
        <div class="section-header">
            <span class="section-tag"><i class="fas fa-file-medical-alt"></i> Scheme Directory</span>
            <h2>Browse <span>Government Schemes</span></h2>
            <p>Live verified directory powered by central and state healthcare portals. Click "View Details" to see complete criteria, benefits, and required documents.</p>
        </div>
        <div class="schemes-grid" id="schemesGrid">
            <!-- Cards populated dynamically via API -->
        </div>
    </div>
</section>

<!-- ============================================================
     FIND THE RIGHT SCHEME (Smart Matcher)
     ============================================================ -->
<section class="finder-section" id="finder">
    <div class="container">
        <div class="finder-box">
            <div class="finder-header">
                <span class="section-tag"><i class="fas fa-magic"></i> Smart Matcher</span>
                <h2>Find the <span>Right Scheme</span></h2>
                <p>Answer a few questions to find customized healthcare schemes and medical subsidies for your family.</p>
            </div>
            <div class="finder-grid">
                <div class="finder-group">
                    <label for="finderSupport"><i class="fas fa-notes-medical" style="color:var(--crimson);margin-right:6px;"></i> Medical support needed?</label>
                    <select id="finderSupport">
                        <option value="">Select support type</option>
                        <option value="insurance">Health Insurance</option>
                        <option value="free-treatment">Free / Subsidized Treatment</option>
                        <option value="financial">Financial Assistance</option>
                        <option value="emergency">Emergency Medical Support</option>
                        <option value="maternal">Maternal &amp; Child Healthcare</option>
                        <option value="senior">Senior Citizen Healthcare</option>
                        <option value="critical">Critical Illness Assistance</option>
                    </select>
                </div>
                <div class="finder-group">
                    <label for="finderState"><i class="fas fa-map-marker-alt" style="color:var(--crimson);margin-right:6px;"></i> State of residence?</label>
                    <select id="finderState">
                        <option value="">Select state</option>
                        <option value="central">Central Government</option>
                        <option value="andhra-pradesh">Andhra Pradesh</option>
                        <option value="tamil-nadu">Tamil Nadu</option>
                        <option value="maharashtra">Maharashtra</option>
                        <option value="gujarat">Gujarat</option>
                        <option value="west-bengal">West Bengal</option>
                        <option value="rajasthan">Rajasthan</option>
                        <option value="karnataka">Karnataka</option>
                        <option value="kerala">Kerala</option>
                        <option value="punjab">Punjab</option>
                        <option value="assam">Assam</option>
                        <option value="haryana">Haryana</option>
                        <option value="himachal-pradesh">Himachal Pradesh</option>
                    </select>
                </div>
                <div class="finder-group">
                    <label for="finderAge"><i class="fas fa-user" style="color:var(--crimson);margin-right:6px;"></i> Patient age group?</label>
                    <select id="finderAge">
                        <option value="">Select age group</option>
                        <option value="child">0–18 years (Child / Infant)</option>
                        <option value="adult">19–59 years (Adult)</option>
                        <option value="senior">60+ years (Senior Citizen)</option>
                    </select>
                </div>
                <div class="finder-group">
                    <label for="finderType"><i class="fas fa-hand-holding-usd" style="color:var(--crimson);margin-right:6px;"></i> Looking for?</label>
                    <select id="finderType">
                        <option value="">Select assistance type</option>
                        <option value="insurance">Cashless Insurance Card</option>
                        <option value="free-treatment">Free Treatment &amp; Surgery</option>
                        <option value="financial">Direct Financial Aid (DBT)</option>
                        <option value="emergency">Emergency Ambulance &amp; Care</option>
                    </select>
                </div>
            </div>
            <div class="finder-actions">
                <button class="btn-primary" id="findSchemesBtn"><i class="fas fa-search"></i> Match Schemes</button>
                <button class="btn-outline" id="resetFinderBtn"><i class="fas fa-redo"></i> Reset</button>
            </div>
            <div class="finder-result" id="finderResult">
                <h4><i class="fas fa-check-circle"></i> Potentially Relevant Schemes</h4>
                <div class="finder-result-tags" id="finderResultTags"></div>
                <p class="finder-disclaimer">
                    <i class="fas fa-info-circle"></i> Sourced from official state and central health registries. Click on any scheme tag above to open full coverage details and official application links.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     EMERGENCY BANNER
     ============================================================ -->
<section class="emergency-banner">
    <div class="container">
        <div class="emergency-inner">
            <div class="emergency-text">
                <h3><i class="fas fa-ambulance" style="color:#ff4d6d;margin-right:8px;"></i> Need urgent blood or emergency care?</h3>
                <p>Access emergency blood donors, WebRTC direct calling, and active hospital requests immediately.</p>
            </div>
            <div class="emergency-actions">
                <a href="<?= $isDonor ? 'donor/index.php#donate' : 'user/user.html#request-title' ?>" class="btn-em blood">
                    <i class="fas fa-droplet"></i> Emergency Blood Request
                </a>
                <a href="<?= $isDonor ? 'donor/index.php#compatibility' : 'user/user.html#sidePanel' ?>" class="btn-em find">
                    <i class="fas fa-search-location"></i> Find Donors
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     DISCLAIMER
     ============================================================ -->
<section class="disclaimer-banner">
    <div class="container">
        <div class="disclaimer-inner">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Official Health Awareness Advisory:</strong> All health scheme descriptions, coverage amounts, and application methods are sourced directly from authorized government portals including the National Health Authority (NHA) and Ministry of Health and Family Welfare (MoHFW). LifeFlow provides verified awareness and does not charge any fee for accessing government schemes.
            </div>
        </div>
    </div>
</section>

<!-- ============================================================
     RECENTLY VIEWED
     ============================================================ -->
<section class="recent-section" id="recentSection" style="display:none;">
    <div class="container">
        <div class="section-label"><i class="fas fa-history"></i> Recently Viewed Schemes</div>
        <div class="recent-chips" id="recentChips"></div>
    </div>
</section>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer>
    <div class="container">
        <div>
            <a href="#" class="logo" style="margin-bottom:16px;display:inline-flex;color:#fff;">
                <div class="logo-icon-wrap"><i class="fas fa-droplet"></i></div>
                Life<span>Flow</span>
            </a>
            <p>LifeFlow connects blood donors and patients across India with real-time matching, instant WebRTC calling, and comprehensive healthcare resources.</p>
            <div class="socials">
                <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
        </div>
        <div>
            <h4>Quick Links</h4>
            <ul>
                <li><a href="<?= $isDonor ? 'donor/index.php' : 'user/user.html' ?>">Dashboard</a></li>
                <li><a href="library.php">Book Library (105 Books)</a></li>
                <li><a href="learning_dashboard.php">Learning Academy</a></li>
                <li><a href="leaderboard.php">Global Leaderboard</a></li>
                <li><a href="schemes.php">Government Schemes</a></li>
            </ul>
        </div>
        <div>
            <h4>Official Resources</h4>
            <ul>
                <li><a href="https://pmjay.gov.in" target="_blank" rel="noopener">PM-JAY Portal</a></li>
                <li><a href="https://nha.gov.in" target="_blank" rel="noopener">National Health Authority</a></li>
                <li><a href="https://cghs.mohfw.gov.in" target="_blank" rel="noopener">CGHS Portal</a></li>
                <li><a href="https://nhm.gov.in" target="_blank" rel="noopener">National Health Mission</a></li>
                <li><a href="https://mohfw.gov.in" target="_blank" rel="noopener">Ministry of Health (MoHFW)</a></li>
            </ul>
        </div>
        <div>
            <h4>Emergency Contact</h4>
            <ul>
                <li><a href="tel:1800110770"><i class="fas fa-phone-alt" style="margin-right:8px;color:var(--crimson-glow);"></i> 1800-110-770 (Ayushman)</a></li>
                <li><a href="tel:108"><i class="fas fa-ambulance" style="margin-right:8px;color:var(--crimson-glow);"></i> 108 (National Emergency)</a></li>
                <li><a href="mailto:support@lifeflow.in"><i class="fas fa-envelope" style="margin-right:8px;color:var(--crimson-glow);"></i> support@lifeflow.in</a></li>
            </ul>
        </div>
        <div class="footer-bottom">
            &copy; 2026 LifeFlow Blood Connection &amp; Health Network. All rights reserved. | Sourced from official government portals.
        </div>
    </div>
</footer>

<!-- SCHEME DETAIL MODAL -->
<div class="modal-overlay" id="schemeModal" role="dialog" aria-modal="true">
    <div class="modal-content" id="modalContent"></div>
</div>

<!-- WebRTC Call Directory UI Components -->
<script src="call_directory.js"></script>
<?php include __DIR__ . '/includes/call_ui.php'; ?>
<script src="assets/js/call.js"></script>

<!-- Universal Header & Slide-out Menu Controller -->
<script src="assets/js/header_menu.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    BloodConnectHeader.init({
        role: <?= json_encode($userRole) ?>,
        userName: <?= json_encode($userName) ?>,
        rootPath: ''
    });
});
</script>

<script>
/* ============================================================
   CLIENT-SIDE SCRIPT DRIVEN BY STRONG API (api/get_schemes.php)
   ============================================================ */
let activeSchemes = [];
let currentCategory = '';
let currentSearch = '';
let currentState = '';
let currentSort = 'relevance';
let searchDebounceTimer = null;
let recentlyViewed = JSON.parse(localStorage.getItem('lifeflow_recent_schemes') || '[]');

const grid = document.getElementById('schemesGrid');
const searchInput = document.getElementById('searchInput');
const stateFilter = document.getElementById('stateFilter');
const categoryFilter = document.getElementById('categoryFilter');
const sortFilter = document.getElementById('sortFilter');
const categoryChips = document.getElementById('categoryChips');
const resultsCount = document.getElementById('resultsCount');

function getIcon(cat) {
    const icons = {
        insurance: 'fa-shield-alt',
        'free-treatment': 'fa-hand-holding-heart',
        financial: 'fa-hand-holding-usd',
        emergency: 'fa-ambulance',
        maternal: 'fa-baby',
        senior: 'fa-user-clock',
        critical: 'fa-heartbeat',
        infrastructure: 'fa-hospital'
    };
    return icons[cat] || 'fa-file-medical-alt';
}

function getLabelClass(label) {
    if (label.includes('Central')) return 'central';
    if (label.includes('State')) return 'state';
    if (label.includes('Financial')) return 'financial';
    if (label.includes('Emergency')) return 'emergency';
    if (label.includes('Insurance')) return 'insurance';
    if (label.includes('Free')) return 'free';
    if (label.includes('Maternal')) return 'maternal';
    if (label.includes('Senior')) return 'senior';
    if (label.includes('Critical')) return 'critical';
    return 'central';
}

/* FETCH FROM STRONG BACKEND API */
async function fetchSchemes(extraParams = '') {
    try {
        const queryParams = new URLSearchParams();
        if (currentSearch) queryParams.set('q', currentSearch);
        if (currentState) queryParams.set('state', currentState);
        if (currentCategory) queryParams.set('category', currentCategory);
        if (currentSort) queryParams.set('sort', currentSort);

        let url = 'api/get_schemes.php?' + queryParams.toString();
        if (extraParams) url += (url.includes('?') ? '&' : '?') + extraParams;

        const res = await fetch(url);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        activeSchemes = data.schemes || [];
        renderSchemes(activeSchemes, data.total || activeSchemes.length);
    } catch (err) {
        console.error('Failed to load schemes via API:', err);
    }
}

function renderSchemes(list, totalCount) {
    resultsCount.innerHTML = `Showing <strong>${list.length}</strong> of <strong>${totalCount}</strong> verified government schemes`;

    if (!list || list.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-search-minus"></i>
                <h4>No government schemes found</h4>
                <p>Try refining your search keyword or clearing the filters.</p>
            </div>`;
        return;
    }

    grid.innerHTML = list.map((s, i) => {
        const isSaved = recentlyViewed.some(r => r.id === s.id);
        const shortElig = s.eligibility.length > 130 ? s.eligibility.substring(0, 130) + '…' : s.eligibility;
        const shortBen = s.benefits.length > 120 ? s.benefits.substring(0, 120) + '…' : s.benefits;
        return `
        <div class="scheme-card fade-in-up" style="animation-delay:${Math.min(i * 0.03, 0.3)}s" data-id="${s.id}">
            <div class="scheme-card-top">
                <div class="scheme-icon-wrap">
                    <i class="fas ${getIcon(s.category)}"></i>
                </div>
                <div class="scheme-labels">
                    ${(s.labels || []).map(l => `<span class="label-pill ${getLabelClass(l)}">${l}</span>`).join('')}
                </div>
            </div>
            <h3>${s.name}</h3>
            <div class="scheme-dept"><i class="fas fa-building" style="margin-right:5px;font-size:.72rem;"></i>${s.department}</div>
            <p class="scheme-desc">${s.description}</p>
            <div class="scheme-meta">
                <div class="scheme-meta-row">
                    <i class="fas fa-user-check"></i>
                    <span><strong>Who can apply:</strong> ${shortElig}</span>
                </div>
                <div class="scheme-meta-row">
                    <i class="fas fa-gift"></i>
                    <span><strong>Benefits:</strong> ${shortBen}</span>
                </div>
            </div>
            <div class="scheme-actions">
                <button class="btn-details" onclick="openModal('${s.id}')">
                    <i class="fas fa-info-circle"></i> View Details
                </button>
                <a class="btn-apply" href="${s.website}" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt"></i> Official Portal
                </a>
            </div>
            <div class="scheme-card-footer">
                <a href="${s.website}" target="_blank" rel="noopener" title="${s.website}">
                    <i class="fas fa-globe"></i> ${s.website.replace('https://', '').replace('http://', '').split('/')[0]}
                </a>
                <button class="bookmark-btn ${isSaved ? 'saved' : ''}" onclick="toggleBookmark('${s.id}', this)" title="${isSaved ? 'Saved' : 'Save scheme'}" aria-label="Save scheme">
                    <i class="fas fa-bookmark"></i>
                </button>
            </div>
        </div>`;
    }).join('');
}

/* MODAL */
function openModal(id) {
    const s = activeSchemes.find(x => x.id === id);
    if (!s) return;
    const modal = document.getElementById('schemeModal');
    const content = document.getElementById('modalContent');

    recentlyViewed = recentlyViewed.filter(r => r.id !== id);
    recentlyViewed.unshift({ id: s.id, name: s.name });
    recentlyViewed = recentlyViewed.slice(0, 6);
    localStorage.setItem('lifeflow_recent_schemes', JSON.stringify(recentlyViewed));
    renderRecentlyViewed();

    content.innerHTML = `
        <button class="modal-close" onclick="closeModal()" aria-label="Close"><i class="fas fa-times"></i></button>
        <div class="modal-header">
            <div class="scheme-icon-wrap"><i class="fas ${getIcon(s.category)}"></i></div>
            <div>
                <h3>${s.name}</h3>
                <div class="scheme-dept"><i class="fas fa-building" style="margin-right:5px;font-size:.72rem;"></i>${s.department}</div>
            </div>
        </div>
        <div class="modal-body-section">
            <h4><i class="fas fa-align-left"></i> Description</h4>
            <p>${s.description}</p>
        </div>
        <div class="modal-body-section">
            <h4><i class="fas fa-user-check"></i> Who Can Apply</h4>
            <p>${s.eligibility}</p>
        </div>
        <div class="modal-body-section">
            <h4><i class="fas fa-gift"></i> Main Benefits</h4>
            <p>${s.benefits}</p>
        </div>
        <div class="modal-body-section">
            <h4><i class="fas fa-file-alt"></i> Required Documents</h4>
            <p>${s.documents}</p>
        </div>
        <div class="modal-body-section">
            <h4><i class="fas fa-tasks"></i> Application Method</h4>
            <p>${s.applyMethod}</p>
        </div>
        <div class="modal-body-section">
            <h4><i class="fas fa-link"></i> Official Portal</h4>
            <a class="official-link" href="${s.website}" target="_blank" rel="noopener">
                <i class="fas fa-external-link-alt"></i> ${s.website}
            </a>
        </div>
        <div class="modal-actions">
            <a class="btn-primary" href="${s.website}" target="_blank" rel="noopener">
                <i class="fas fa-external-link-alt"></i> Apply / Official Portal
            </a>
            <button class="btn-outline" onclick="window.print()">
                <i class="fas fa-print"></i> Print Details
            </button>
        </div>
    `;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('schemeModal').classList.remove('show');
    document.body.style.overflow = '';
}

document.getElementById('schemeModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

function toggleBookmark(id, btn) {
    const s = activeSchemes.find(x => x.id === id);
    if (!s) return;
    const idx = recentlyViewed.findIndex(r => r.id === id);
    if (idx > -1) {
        recentlyViewed.splice(idx, 1);
        btn.classList.remove('saved');
    } else {
        recentlyViewed.unshift({ id: s.id, name: s.name });
        recentlyViewed = recentlyViewed.slice(0, 6);
        btn.classList.add('saved');
    }
    localStorage.setItem('lifeflow_recent_schemes', JSON.stringify(recentlyViewed));
    renderRecentlyViewed();
}

function renderRecentlyViewed() {
    const section = document.getElementById('recentSection');
    const chips = document.getElementById('recentChips');
    if (recentlyViewed.length === 0) {
        section.style.display = 'none';
        return;
    }
    section.style.display = 'block';
    chips.innerHTML = recentlyViewed.map(r => `
        <button class="recent-chip" onclick="openModal('${r.id}')">
            <i class="fas fa-clock"></i> ${r.name.length > 45 ? r.name.substring(0, 45) + '…' : r.name}
        </button>
    `).join('');
}

/* EVENT LISTENERS */
searchInput.addEventListener('input', function() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        currentSearch = searchInput.value.trim();
        fetchSchemes();
    }, 250);
});

stateFilter.addEventListener('change', function() {
    currentState = this.value;
    fetchSchemes();
});

categoryFilter.addEventListener('change', function() {
    currentCategory = this.value;
    updateChips();
    fetchSchemes();
});

sortFilter.addEventListener('change', function() {
    currentSort = this.value;
    fetchSchemes();
});

categoryChips.addEventListener('click', function(e) {
    const chip = e.target.closest('.cat-chip');
    if (!chip) return;
    categoryChips.querySelectorAll('.cat-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    currentCategory = chip.dataset.cat;
    categoryFilter.value = currentCategory;
    fetchSchemes();
});

function updateChips() {
    categoryChips.querySelectorAll('.cat-chip').forEach(c => {
        c.classList.toggle('active', c.dataset.cat === currentCategory);
    });
}

/* SMART FINDER VIA API */
document.getElementById('findSchemesBtn').addEventListener('click', async function() {
    const support = document.getElementById('finderSupport').value;
    const state = document.getElementById('finderState').value;
    const age = document.getElementById('finderAge').value;
    const type = document.getElementById('finderType').value;
    const result = document.getElementById('finderResult');
    const tags = document.getElementById('finderResultTags');

    if (!support && !state && !age && !type) {
        tags.innerHTML = '<span style="color:var(--text-muted);font-size:.9rem;">Please choose at least one option to find relevant schemes.</span>';
        result.classList.add('show');
        return;
    }

    try {
        const p = new URLSearchParams({ support, state, age, type });
        const res = await fetch('api/get_schemes.php?' + p.toString());
        const data = await res.json();
        const matches = data.schemes || [];

        if (matches.length === 0) {
            tags.innerHTML = '<span style="color:var(--text-muted);font-size:.9rem;">No matching schemes found for this criteria. Try selecting Central or removing age filters.</span>';
            result.classList.add('show');
            return;
        }

        tags.innerHTML = matches.slice(0, 9).map(s => `
            <button class="compat-pill" onclick="openModal('${s.id}')">
                <i class="fas fa-file-medical-alt" style="color:var(--crimson);margin-right:6px;"></i>${s.name.length > 55 ? s.name.substring(0, 55) + '…' : s.name}
            </button>
        `).join('');
        result.classList.add('show');
    } catch (err) {
        console.error('Finder error:', err);
    }
});

document.getElementById('resetFinderBtn').addEventListener('click', function() {
    document.getElementById('finderSupport').value = '';
    document.getElementById('finderState').value = '';
    document.getElementById('finderAge').value = '';
    document.getElementById('finderType').value = '';
    document.getElementById('finderResult').classList.remove('show');
});

/* HEADER SCROLL */
window.addEventListener('scroll', function() {
    const header = document.getElementById('mainHeader');
    if (header) header.classList.toggle('scrolled', window.scrollY > 30);
});

/* ============================================================
   BACKGROUND ANIMATION & ECG ENGINE (Identical to User & Donor)
   ============================================================ */
// 1. ECG Canvas Pulse
function drawECG() {
    const canvas = document.getElementById('ecgMini');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width, h = canvas.height;
    ctx.clearRect(0, 0, w, h);
    ctx.strokeStyle = '#e63946';
    ctx.lineWidth = 1.8;
    ctx.beginPath();
    const mid = h / 2;
    const points = 70;
    for (let i = 0; i <= points; i++) {
        const x = (i / points) * w;
        let y = mid;
        const t = i / points;
        if (t > 0.35 && t < 0.38) y = mid - 2;
        else if (t > 0.38 && t < 0.42) y = mid + 4;
        else if (t > 0.42 && t < 0.46) y = mid - 9;
        else if (t > 0.46 && t < 0.50) y = mid + 3;
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    }
    ctx.stroke();
}
drawECG();
setInterval(drawECG, 1400);

// 2. Interactive Fluid Blood Cell Particles
(function initAmbientBg() {
    const canvas = document.getElementById('bgCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let w, h;
    function resize() {
        w = canvas.width = window.innerWidth;
        h = canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    const particles = Array.from({ length: 24 }, () => ({
        x: Math.random() * w,
        y: Math.random() * h,
        r: Math.random() * 80 + 35,
        vx: (Math.random() - 0.5) * 0.4,
        vy: (Math.random() - 0.5) * 0.4,
        hue: Math.random() > 0.5 ? 'rgba(230, 57, 70, ' : 'rgba(255, 77, 109, '
    }));

    function animate() {
        ctx.clearRect(0, 0, w, h);
        particles.forEach(p => {
            p.x += p.vx;
            p.y += p.vy;
            if (p.x < -p.r) p.x = w + p.r;
            if (p.x > w + p.r) p.x = -p.r;
            if (p.y < -p.r) p.y = h + p.r;
            if (p.y > h + p.r) p.y = -p.r;

            const grad = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.r);
            grad.addColorStop(0, p.hue + '0.08)');
            grad.addColorStop(1, p.hue + '0)');
            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fill();
        });
        requestAnimationFrame(animate);
    }
    animate();
})();

// 3. Interactive Cursor Glow
(function initCursorTrail() {
    const canvas = document.getElementById('cursorCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let w = canvas.width = window.innerWidth;
    let h = canvas.height = window.innerHeight;
    window.addEventListener('resize', () => { w = canvas.width = window.innerWidth; h = canvas.height = window.innerHeight; });

    let mouse = { x: -100, y: -100 };
    window.addEventListener('mousemove', e => { mouse.x = e.clientX; mouse.y = e.clientY; });

    function drawCursor() {
        ctx.clearRect(0, 0, w, h);
        if (mouse.x > 0 && mouse.y > 0) {
            const rad = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 140);
            rad.addColorStop(0, 'rgba(230, 57, 70, 0.09)');
            rad.addColorStop(1, 'rgba(230, 57, 70, 0)');
            ctx.fillStyle = rad;
            ctx.beginPath();
            ctx.arc(mouse.x, mouse.y, 140, 0, Math.PI * 2);
            ctx.fill();
        }
        requestAnimationFrame(drawCursor);
    }
    drawCursor();
})();

/* Initial Load */
fetchSchemes();
renderRecentlyViewed();
</script>
</body>
</html>

