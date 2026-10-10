<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'donor') {
    header('Location: ../login.html?error=role');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$donorRequests = [];
$databaseMessage = '';
$hasCompletedScreening = false;
mysqli_report(MYSQLI_REPORT_OFF);
$donorDb = new mysqli('localhost', 'root', '', 'blood_connect');
if ($donorDb->connect_error) {
    $databaseMessage = 'Database connection failed. Check that MySQL is running and the blood_connect database exists.';
} else {
    $donorDb->set_charset('utf8mb4');
    $donorStmt = $donorDb->prepare('SELECT r.id, r.requester_id, r.patient_name, r.blood_type, r.units, r.hospital, r.city, r.urgency, r.created_at FROM blood_requests r JOIN users u ON u.id = ? WHERE r.status = "open" AND r.blood_type = u.blood_type ORDER BY FIELD(r.urgency, "critical", "urgent", "routine"), r.created_at DESC LIMIT 30');
    if (!$donorStmt) {
        $databaseMessage = 'Blood request tables are not installed. Import database/setup.sql in phpMyAdmin, then reload this page.';
    } else {
        $donorStmt->bind_param('i', $_SESSION['user_id']);
        if ($donorStmt->execute()) {
            $result = $donorStmt->get_result();
            if ($result) {
                $donorRequests = $result->fetch_all(MYSQLI_ASSOC);
            }
        } else {
            $databaseMessage = 'Unable to load blood requests. Import database/setup.sql and reload this page.';
        }
        $donorStmt->close();
    }

    // Check if donor has already completed preliminary screening
    $checkScreening = $donorDb->prepare('SELECT preliminary_result FROM donors WHERE user_id = ? LIMIT 1');
    if ($checkScreening) {
        $checkScreening->bind_param('i', $_SESSION['user_id']);
        if ($checkScreening->execute()) {
            $csRes = $checkScreening->get_result();
            if ($csRow = $csRes->fetch_assoc()) {
                if (!empty($csRow['preliminary_result'])) {
                    $hasCompletedScreening = true;
                }
            }
        }
        $checkScreening->close();
    }
    if (!$hasCompletedScreening) {
        $checkScr2 = $donorDb->prepare('SELECT id FROM donor_screenings WHERE user_id = ? LIMIT 1');
        if ($checkScr2) {
            $checkScr2->bind_param('i', $_SESSION['user_id']);
            if ($checkScr2->execute()) {
                $sRes2 = $checkScr2->get_result();
                if ($sRes2 && $sRes2->fetch_assoc()) {
                    $hasCompletedScreening = true;
                }
            }
            $checkScr2->close();
        }
    }

    $donorDb->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>" />
    <title>LifeFlow — Next-Gen Blood Donation Network</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="../call_directory.css" />
    <link rel="stylesheet" href="../assets/css/call.css" />
    <link rel="stylesheet" href="../assets/css/header_menu.css?v=<?= filemtime(__DIR__ . '/../assets/css/header_menu.css') ?>" />
    <!-- Chart.js for Hospital Blood Reserves Graph -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    <style>
        /* ---------- ROOT VARIABLES & PALETTE ---------- */
        :root {
            --bg-dark: #080b11;
            --bg-card: rgba(18, 24, 38, 0.75);
            --bg-glass: rgba(255, 255, 255, 0.04);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-glow: rgba(239, 68, 68, 0.35);
            --crimson: #e63946;
            --crimson-glow: #ff2a44;
            --blood-dark: #8b0000;
            --blood-light: #ff4d6d;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --accent-cyan: #06d6a0;
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-heading: 'Outfit', sans-serif;
        }

        /* ---------- RESET & BASE ---------- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-main);
            background-color: var(--bg-dark);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
        }
        a { text-decoration: none; color: inherit; }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #080b11; }
        ::-webkit-scrollbar-thumb { background: #dc2626; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #ef4444; }

        .container {
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
            position: relative;
            z-index: 2;
        }

        /* ---------- GLOBAL BACKGROUND VIDEO & CANVAS LAYER ---------- */
        .video-bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .video-bg {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translate(-50%, -50%);
            object-fit: cover;
            opacity: 0.35;
            filter: saturate(1.8) contrast(1.2) hue-rotate(-5deg);
            transition: opacity 1s ease;
        }
        .blood-canvas-layer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            pointer-events: auto;
        }
        .bg-gradient-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 50% 30%, rgba(220, 38, 38, 0.15) 0%, rgba(8, 11, 17, 0.85) 60%, rgba(5, 7, 10, 0.98) 100%);
            z-index: 1;
            pointer-events: none;
        }

        /* ---------- LIVE ECG HEARTBEAT TOP BAR ---------- */
        .ecg-topbar {
            background: rgba(10, 14, 23, 0.85);
            border-bottom: 1px solid var(--border-glow);
            padding: 6px 0;
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
        }
        .ecg-live-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-cyan);
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
        .video-control-btn {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #ff8595;
            padding: 3px 12px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .video-control-btn:hover {
            background: var(--crimson);
            color: #fff;
            box-shadow: 0 0 12px rgba(239, 68, 68, 0.6);
        }

        /* ---------- HEADER / NAVBAR ---------- */
        header {
            background: rgba(10, 14, 23, 0.7);
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
            background: rgba(8, 11, 17, 0.92);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            border-bottom-color: rgba(220, 38, 38, 0.25);
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
            color: #fff;
            letter-spacing: -0.5px;
        }
        .logo-icon-wrap {
            position: relative;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ff2a44, #8b0000);
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(255, 42, 68, 0.5);
            animation: floatSlow 3s ease-in-out infinite alternate;
        }
        @keyframes floatSlow {
            0% { transform: translateY(0px) rotate(0deg); }
            100% { transform: translateY(-4px) rotate(4deg); }
        }
        .logo-icon-wrap i { color: #fff; font-size: 1.3rem; }
        .logo span {
            background: linear-gradient(90deg, #ff4d6d, #ff8595);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        nav {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        nav a {
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.3s;
            font-size: 0.95rem;
            position: relative;
            padding: 4px 0;
        }
        nav a:hover, nav a.active {
            color: #fff;
        }
        nav a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 2px;
            background: var(--crimson-glow);
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 8px var(--crimson-glow);
        }
        nav a:hover::after, nav a.active::after {
            width: 100%;
        }
        .user-greeting {
            display: flex;
            align-items: center;
            gap: 14px;
            background: rgba(255, 255, 255, 0.05);
            padding: 6px 16px;
            border-radius: 50px;
            border: 1px solid var(--border-glass);
        }
        .user-greeting .user-name {
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .user-greeting .user-name i {
            color: var(--crimson-glow);
        }
        .btn-logout {
            background: rgba(220, 38, 38, 0.2);
            color: #ff8595;
            padding: 6px 14px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1px solid rgba(220, 38, 38, 0.4);
            transition: all 0.3s;
        }
        .btn-logout:hover {
            background: var(--crimson);
            color: #fff;
            box-shadow: 0 0 14px rgba(220, 38, 38, 0.6);
            transform: translateY(-1px);
        }

        /* ---------- BUTTONS ---------- */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: linear-gradient(135deg, #ff2a44 0%, #b91c1c 100%);
            color: #fff;
            padding: 15px 34px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.05rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 25px rgba(255, 42, 68, 0.45);
            position: relative;
            overflow: hidden;
        }
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        .btn-primary:hover::before {
            left: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 10px 35px rgba(255, 42, 68, 0.65);
        }
        .btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            padding: 14px 30px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.05rem;
            border: 2px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-outline:hover {
            border-color: var(--crimson-glow);
            background: rgba(239, 68, 68, 0.15);
            transform: translateY(-2px);
            box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
        }

        /* ---------- HERO SECTION ---------- */
        .hero {
            padding: 85px 0 70px;
            position: relative;
            min-height: 88vh;
            display: flex;
            align-items: center;
        }
        .hero .container {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 50px;
            align-items: center;
        }
        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #ff8595;
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: 0.5px;
            animation: pulseBorder 2s infinite;
        }
        @keyframes pulseBorder {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .hero-content h1 {
            font-family: var(--font-heading);
            font-size: 4rem;
            font-weight: 900;
            line-height: 1.1;
            color: #fff;
            margin-bottom: 20px;
            letter-spacing: -1px;
        }
        .hero-content h1 .blood-gradient {
            background: linear-gradient(135deg, #ff4d6d 0%, #ff0033 50%, #ff8595 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(255, 42, 68, 0.4);
        }
        .hero-content p {
            font-size: 1.2rem;
            color: var(--text-muted);
            margin-bottom: 34px;
            max-width: 540px;
            font-weight: 400;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }

        /* Hero Stats Glass Counter */
        .hero-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 48px;
            background: rgba(18, 24, 38, 0.6);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 20px 24px;
        }
        .hero-stats .stat {
            position: relative;
        }
        .hero-stats .stat:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -9px;
            top: 15%;
            height: 70%;
            width: 1px;
            background: var(--border-glass);
        }
        .hero-stats .stat .number {
            font-family: var(--font-heading);
            font-size: 2.3rem;
            font-weight: 900;
            color: #fff;
            background: linear-gradient(135deg, #ffffff 0%, #ff8595 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-stats .stat .label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Hero 3D Interactive Visualizer Container */
        .hero-visual-card {
            position: relative;
            background: radial-gradient(circle at 50% 50%, rgba(220, 38, 38, 0.25) 0%, rgba(18, 24, 38, 0.85) 75%);
            border: 1px solid rgba(255, 42, 68, 0.3);
            border-radius: 36px;
            padding: 40px 30px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6), inset 0 0 40px rgba(220, 38, 38, 0.2);
            backdrop-filter: blur(20px);
            text-align: center;
            overflow: hidden;
            transform-style: preserve-3d;
            transition: transform 0.3s ease;
        }
        .hero-visual-card:hover {
            border-color: rgba(255, 42, 68, 0.6);
            box-shadow: 0 30px 70px rgba(255, 42, 68, 0.25);
        }

        /* 3D Pulsing Blood Heart & Bag Illustration */
        .blood-heart-visual {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            user-select: none;
        }
        .blood-heart-visual:focus-visible { outline: 3px solid rgba(230, 57, 70, .35); outline-offset: 8px; border-radius: 50%; }
        .heart-glow-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 42, 68, 0.4) 0%, transparent 70%);
            animation: pulseGlowRing 2s infinite ease-out;
        }
        @keyframes pulseGlowRing {
            0% { transform: scale(0.8); opacity: 0.9; }
            100% { transform: scale(1.6); opacity: 0; }
        }
        .main-heart-icon {
            font-size: 5.5rem;
            color: #ff2a44;
            filter: drop-shadow(0 0 25px rgba(255, 42, 68, 0.8));
            animation: heartbeatBeat 1.4s infinite cubic-bezier(0.215, 0.61, 0.355, 1);
        }
        @keyframes heartbeatBeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.2); }
            28% { transform: scale(1); }
            42% { transform: scale(1.15); }
            70% { transform: scale(1); }
        }
        .hero-visual-card h3 {
            font-family: var(--font-heading);
            font-size: 1.85rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 8px;
        }
        .hero-visual-card p {
            color: var(--text-muted);
            font-size: 0.98rem;
            margin-bottom: 22px;
        }

        /* Live Blood Level Tube */
        .blood-meter-wrapper {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-glass);
            border-radius: 50px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 14px;
        }
        .blood-meter-track {
            flex: 1;
            height: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .blood-meter-fill {
            height: 100%;
            width: 78%;
            background: linear-gradient(90deg, #b91c1c, #ff2a44);
            border-radius: 10px;
            box-shadow: 0 0 10px var(--crimson-glow);
            position: relative;
            animation: fillPulse 3s infinite alternate ease-in-out;
        }
        @keyframes fillPulse {
            0% { width: 75%; }
            100% { width: 84%; }
        }

        /* ---------- BLOOD COMPATIBILITY MATRIX INTERACTIVE SECTION ---------- */
        .compatibility-section {
            padding: 80px 0;
            position: relative;
        }
        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }
        .section-header .section-tag {
            color: var(--crimson-glow);
            font-weight: 700;
            font-size: 0.9rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        .section-header h2 {
            font-family: var(--font-heading);
            font-size: 2.6rem;
            font-weight: 800;
            color: #fff;
        }
        .section-header h2 span {
            background: linear-gradient(135deg, #ff4d6d, #ff8595);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .section-header p {
            color: var(--text-muted);
            max-width: 620px;
            margin: 10px auto 0;
            font-size: 1.05rem;
        }

        .matrix-box {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 28px;
            padding: 40px;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
        }
        .blood-type-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 12px;
            margin-bottom: 30px;
        }
        .type-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 16px 8px;
            border-radius: 16px;
            font-family: var(--font-heading);
            font-weight: 800;
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
        }
        .type-btn:hover {
            border-color: var(--crimson-glow);
            background: rgba(239, 68, 68, 0.2);
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.35);
        }
        .type-btn.active {
            background: linear-gradient(135deg, #ff2a44, #b91c1c);
            border-color: #ff8595;
            box-shadow: 0 0 25px rgba(255, 42, 68, 0.6);
            transform: scale(1.06);
        }
        .compat-results {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 20px;
        }
        .compat-card {
            background: rgba(10, 14, 23, 0.6);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        .compat-card h4 {
            font-family: var(--font-heading);
            font-size: 1.25rem;
            color: #fff;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .compat-card.give h4 i { color: #06d6a0; }
        .compat-card.receive h4 i { color: #ff2a44; }
        .compat-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .compat-pill {
            background: rgba(255, 255, 255, 0.08);
            padding: 8px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1rem;
            color: #fff;
            border: 1px solid var(--border-glass);
            transition: all 0.3s;
        }
        .compat-pill.highlight-give {
            background: rgba(6, 214, 160, 0.2);
            border-color: #06d6a0;
            color: #06d6a0;
            box-shadow: 0 0 12px rgba(6, 214, 160, 0.3);
        }
        .compat-pill.highlight-receive {
            background: rgba(255, 42, 68, 0.2);
            border-color: #ff2a44;
            color: #ff8595;
            box-shadow: 0 0 12px rgba(255, 42, 68, 0.3);
        }

        /* ---------- FEATURES SECTION WITH 3D TILT ---------- */
        .features-section {
            padding: 80px 0;
            position: relative;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 36px 28px;
            backdrop-filter: blur(16px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--crimson-glow), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(239, 68, 68, 0.4);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.5), 0 0 20px rgba(239, 68, 68, 0.15);
        }
        .feature-card:hover::before { opacity: 1; }
        .feature-icon-wrap {
            width: 64px;
            height: 64px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            color: var(--crimson-glow);
            margin-bottom: 22px;
            transition: all 0.3s;
        }
        .feature-card:hover .feature-icon-wrap {
            background: var(--crimson-glow);
            color: #fff;
            box-shadow: 0 0 20px rgba(255, 42, 68, 0.6);
            transform: scale(1.1) rotate(5deg);
        }
        .feature-card h3 {
            font-family: var(--font-heading);
            font-size: 1.35rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
        }
        .feature-card p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* ---------- DONOR REGISTRATION FORM SECTION ---------- */
        .donor-section {
            padding: 80px 0;
            position: relative;
        }
        .form-glass-container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--bg-card);
            border: 1px solid var(--border-glow);
            border-radius: 32px;
            padding: 50px 45px;
            backdrop-filter: blur(25px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5), inset 0 0 30px rgba(220, 38, 38, 0.1);
            position: relative;
            overflow: hidden;
        }
        .form-glass-container h3 {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            text-align: center;
            margin-bottom: 6px;
        }
        .form-glass-container .form-sub {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 34px;
            font-size: 1rem;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 22px;
        }
        .form-group.full {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.9rem;
            color: #e5e7eb;
            margin-bottom: 8px;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            transition: color 0.3s;
        }
        .input-wrap input,
        .input-wrap select,
        .input-wrap textarea {
            width: 100%;
            padding: 15px 18px 15px 48px;
            background: rgba(10, 14, 23, 0.7);
            border: 1.5px solid var(--border-glass);
            border-radius: 14px;
            color: #fff;
            font-family: var(--font-main);
            font-size: 1rem;
            transition: all 0.3s;
        }
        .input-wrap select option {
            background: #0d121d;
            color: #fff;
        }
        .input-wrap textarea {
            padding: 15px 18px;
            min-height: 100px;
            resize: vertical;
        }
        .input-wrap input:focus,
        .input-wrap select:focus,
        .input-wrap textarea:focus {
            outline: none;
            border-color: var(--crimson-glow);
            box-shadow: 0 0 0 4px rgba(255, 42, 68, 0.2);
            background: rgba(15, 21, 35, 0.9);
        }
        .input-wrap input:focus + i {
            color: var(--crimson-glow);
        }

        /* Animated Success Card */
        #donationSuccess {
            display: none;
            margin-top: 24px;
            padding: 28px;
            background: rgba(6, 214, 160, 0.15);
            border: 1px solid rgba(6, 214, 160, 0.4);
            border-radius: 20px;
            text-align: center;
            animation: slideUp 0.6s ease;
        }
        #donationSuccess i {
            font-size: 3rem;
            color: #06d6a0;
            margin-bottom: 12px;
        }
        #donationSuccess h4 {
            font-size: 1.4rem;
            color: #fff;
            font-family: var(--font-heading);
            margin-bottom: 6px;
        }
        #donationSuccess p {
            color: #d1fae5;
            font-size: 0.95rem;
        }

        /* ---------- FOOTER ---------- */
        footer {
            background: #05070a;
            border-top: 1px solid var(--border-glass);
            padding: 60px 0 30px;
            position: relative;
            z-index: 2;
        }
        footer .container {
            display: grid;
            grid-template-columns: 1.8fr 1fr 1fr 1.2fr;
            gap: 40px;
        }
        footer h4 {
            font-family: var(--font-heading);
            color: #fff;
            font-size: 1.15rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        footer p {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.7;
        }
        footer .socials {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        footer .socials a {
            width: 42px;
            height: 42px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            transition: all 0.3s;
        }
        footer .socials a:hover {
            background: var(--crimson-glow);
            color: #fff;
            box-shadow: 0 0 15px rgba(255, 42, 68, 0.5);
            transform: translateY(-3px);
        }
        footer ul { list-style: none; }
        footer ul li { margin-bottom: 12px; }
        footer ul li a {
            color: var(--text-muted);
            transition: color 0.3s;
            font-size: 0.95rem;
        }
        footer ul li a:hover { color: var(--crimson-glow); }
        .footer-bottom {
            border-top: 1px solid var(--border-glass);
            margin-top: 50px;
            padding-top: 24px;
            text-align: center;
            font-size: 0.9rem;
            color: #6b7280;
            grid-column: 1 / -1;
        }

        /* ---------- RESPONSIVE DESIGN ---------- */
        @media (max-width: 1024px) {
            .hero-content h1 { font-size: 3.2rem; }
            .blood-type-grid { grid-template-columns: repeat(4, 1fr); }
            footer .container { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .hero .container { grid-template-columns: 1fr; text-align: center; }
            .hero-content p { margin-left: auto; margin-right: auto; }
            .hero-actions { justify-content: center; }
            .hero-stats { grid-template-columns: 1fr; gap: 20px; }
            .hero-stats .stat:not(:last-child)::after { display: none; }
            .form-grid { grid-template-columns: 1fr; }
            .compat-results { grid-template-columns: 1fr; }
            .form-glass-container { padding: 32px 20px; }
            nav { display: none; }
            footer .container { grid-template-columns: 1fr; }
        }
        /* ---------- LOGIN-INSPIRED LIGHT GLASS THEME ---------- */
        :root {
            --bg-dark: #f6f9fc;
            --bg-card: rgba(255, 255, 255, .88);
            --bg-glass: rgba(255, 255, 255, .7);
            --border-glass: rgba(220, 38, 38, .13);
            --border-glow: rgba(239, 68, 68, .35);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --accent-cyan: #0284c7;
        }
        body { background: #f6f9fc; color: var(--text-main); }
        .video-bg { opacity: .12; filter: saturate(1.5) contrast(1.05) brightness(1.2); }
        .bg-gradient-overlay {
            background:
                radial-gradient(circle at 12% 18%, rgba(255, 77, 109, .14), transparent 40%),
                radial-gradient(circle at 86% 72%, rgba(220, 38, 38, .11), transparent 44%),
                linear-gradient(180deg, rgba(246, 249, 252, .78), rgba(255, 255, 255, .9));
        }
        .ecg-topbar, header { background: rgba(255, 255, 255, .82); border-color: var(--border-glass); }
        header.scrolled { background: rgba(255, 255, 255, .96); box-shadow: 0 14px 40px rgba(220, 38, 38, .1); }
        /* Fix: profile pill username must be dark/visible on the light header background */
        .header-profile-pill {
            background: rgba(255, 255, 255, 0.9) !important;
            color: #0f172a !important;
            border: 1px solid rgba(220, 38, 38, 0.22) !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .header-profile-pill .profile-name { color: #0f172a !important; }
        .header-profile-pill i { color: #e63946 !important; }
        .header-profile-pill .role-tag { background: #fee2e2 !important; color: #b91c1c !important; border-color: #fca5a5 !important; }
        nav a, .user-greeting, .hero-content p, .hero-stats .stat .label, footer p, footer li a { color: var(--text-muted); }
        .logo { color: #0f172a; }
        nav a:hover, nav a.active { color: #0f172a; }
        .hero-content h1 { color: #0f172a; text-shadow: none; }
        .hero-stats .stat .number { background: linear-gradient(135deg, #b91c1c 0%, #ff4d6d 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-visual-card { background: radial-gradient(circle at 50% 35%, rgba(255, 77, 109, .2), rgba(255, 255, 255, .9) 72%); }
        .hero-visual-card h3, .section-header h2, .feature-card h3, .form-glass-container h3, footer h4 { color: #0f172a; }
        .audio-beat { animation: audioHeartbeat .36s ease-out !important; }
        @keyframes audioHeartbeat {
            0% { transform: scale(1); }
            28% { transform: scale(1.22); }
            52% { transform: scale(1.08); }
            100% { transform: scale(1); }
        }
        .section-header p, .feature-card p, .form-glass-container .form-sub { color: var(--text-muted); }
        .hero-stats { background: rgba(255, 255, 255, .72); }
        .blood-meter-wrapper { background: rgba(255, 255, 255, .72); }
        .blood-meter-track { background: rgba(100, 116, 139, .16); }
        .matrix-box, .form-glass-container { box-shadow: 0 24px 55px rgba(220, 38, 38, .1); }
        .type-btn { background: rgba(255, 255, 255, .75); border-color: rgba(220, 38, 38, .14); color: #0f172a; }
        .type-btn.active { color: #fff; }
        .compat-card { background: rgba(248, 250, 252, .88); }
        .compat-card h4, .compat-pill { color: #0f172a; }
        .compat-pill { background: rgba(255, 255, 255, .82); }
        .form-group label { color: #334155; }
        .input-wrap input, .input-wrap select, .input-wrap textarea {
            background: rgba(248, 250, 252, .94);
            color: #0f172a;
            border-color: rgba(220, 38, 38, .14);
        }
        .input-wrap select option { background: #fff; color: #0f172a; }
        .input-wrap input:focus, .input-wrap select:focus, .input-wrap textarea:focus { background: #fff; }
        #donationSuccess h4 { color: #166534; }
        #donationSuccess p { color: #166534; }
        footer .socials a { background: rgba(255, 255, 255, .72); color: #475569; }
        .request-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(245px,1fr));gap:16px}.request-card{padding:20px;border:1px solid rgba(220,38,38,.12);border-radius:20px;background:rgba(255,255,255,.82);box-shadow:0 14px 30px rgba(220,38,38,.07);transition:transform .35s,box-shadow .35s}.request-card:hover{transform:translateY(-5px);box-shadow:0 20px 40px rgba(220,38,38,.14)}.request-card h3{color:#0f172a;margin:16px 0 9px;font-family:var(--font-heading)}.request-card p{color:var(--text-muted);font-size:.88rem;margin:7px 0}.request-card p i{color:var(--crimson);width:16px}.request-card .btn-primary{width:100%;padding:11px 15px;margin-top:15px;font-size:.88rem}.request-card-top{display:flex;justify-content:space-between;gap:8px}.request-blood{padding:6px 11px;border-radius:50px;background:#fff0f1;color:var(--crimson);font-weight:800}.request-urgency{padding:6px 10px;border-radius:50px;font-size:.72rem;font-weight:800}.request-urgency.critical{background:#fee2e2;color:#b91c1c}.request-urgency.urgent{background:#fff7ed;color:#c2410c}.request-urgency.routine{background:#ecfdf5;color:#047857}.empty-request{display:grid;place-items:center;gap:8px;min-height:130px;text-align:center;color:var(--text-muted);grid-column:1/-1}.empty-request i{font-size:2rem;color:#10b981}.empty-request strong{color:#0f172a}
        .hero-stats, .feature-card, .compatibility-card, .donate-card, .contact-card {
            background: var(--bg-card);
            border-color: var(--border-glass);
            box-shadow: 0 24px 55px rgba(220, 38, 38, .08), 0 8px 18px rgba(15, 23, 42, .04);
            backdrop-filter: blur(22px);
        }
        .hero-visual-card:hover, .feature-card:hover, .compatibility-card:hover, .donate-card:hover { box-shadow: 0 28px 65px rgba(220, 38, 38, .15); }
        footer { background: rgba(255, 255, 255, .9); color: var(--text-main); border-top-color: var(--border-glass); }
        .video-control-btn { background: rgba(239, 68, 68, .08); color: var(--crimson); }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(#ff4d6d,#b91c1c); }
        #cursorCanvas { position: fixed; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 99; }
        /* ---------- LIVE MEDICAL NEWS TICKER (DONOR THEME) ---------- */
        .live-medical-ticker-wrap {
            background: rgba(15, 23, 42, 0.85);
            border-bottom: 1.5px solid rgba(220, 38, 38, 0.3);
            border-top: 1.5px solid rgba(220, 38, 38, 0.3);
            padding: 8px 24px;
            position: relative;
            z-index: 20;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
        }
        .ticker-inner {
            max-width: 1240px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: nowrap;
        }
        .ticker-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .ticker-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #d62839 !important;
            color: #ffffff !important;
            padding: 6px 14px;
            border-radius: 50px;
            font-family: var(--font-body);
            font-weight: 800;
            font-size: 0.76rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 2px 10px rgba(214, 40, 57, 0.45);
            white-space: nowrap;
            text-decoration: none;
        }
        .ticker-badge i {
            color: #ffffff !important;
            animation: pulse 1.8s infinite;
        }
        .ticker-source-tag {
            background: rgba(34, 197, 94, 0.15);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 4px 10px;
            border-radius: 20px;
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 0.74rem;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .ticker-time-tag {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 4px 10px;
            border-radius: 20px;
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 0.74rem;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .ticker-content {
            flex: 1;
            min-width: 0;
            overflow: hidden;
        }
        .ticker-text-box {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            cursor: pointer;
            transition: opacity 0.22s ease;
            overflow: hidden;
            white-space: nowrap;
        }
        .ticker-icon {
            flex-shrink: 0;
            color: #ff4d6d;
            font-size: 0.95rem;
            margin-right: 4px;
        }
        .ticker-title {
            color: #f8fafc;
            font-weight: 800;
            font-size: 0.88rem;
            flex-shrink: 0;
        }
        .ticker-text-box:hover .ticker-title {
            color: #ff4d6d;
        }
        .ticker-summary {
            color: #94a3b8;
            font-weight: 500;
            font-size: 0.86rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ticker-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        .btn-ticker {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.72rem;
            transition: all 0.2s;
        }
        .btn-ticker:hover {
            background: #d62839;
            color: #ffffff;
            border-color: #d62839;
        }
        .btn-ticker-viewall {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: #f1f5f9;
            font-weight: 700;
            font-size: 0.78rem;
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-ticker-viewall:hover {
            background: #d62839;
            color: #ffffff;
            border-color: #d62839;
        }
        @media (max-width: 992px) {
            .ticker-summary { display: none; }
        }
        @media (max-width: 768px) {
            .ticker-inner { flex-wrap: wrap; gap: 8px; }
            .ticker-meta { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body class="donor-page" data-theme="light">

    <!-- ========== GLOBAL BACKGROUND VIDEO & CANVAS LAYER ========== -->
    <div class="video-bg-container">
        <!-- High-res smooth streaming red blood vessel loop video -->
        <video class="video-bg" id="bgVideo" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1615461066841-6116e61058f4?auto=format&fit=crop&w=1920&q=80">
            <source src="https://assets.mixkit.co/videos/preview/mixkit-red-blood-cells-moving-under-a-microscope-41378-large.mp4" type="video/mp4">
            <source src="https://assets.mixkit.co/videos/preview/mixkit-abstract-red-particles-moving-in-fluid-motion-41804-large.mp4" type="video/mp4">
        </video>
    </div>
    
    <!-- 3D Interactive Erythrocyte & Bloodstream Canvas -->
    <canvas id="bloodCanvas" class="blood-canvas-layer"></canvas>
    <canvas id="cursorCanvas" aria-hidden="true"></canvas>
    
    <!-- Deep Atmospheric Dark/Red Overlay -->
    <div class="bg-gradient-overlay"></div>

    <!-- ========== LIVE ECG TOP BAR ========== -->
    <div class="ecg-topbar">
        <div class="container">
            <div class="ecg-live-badge">
                <span class="pulse-dot"></span>
                <span>VITAL LINK ACTIVE:</span>
                <canvas id="ecgMini" class="ecg-canvas-mini" width="140" height="24"></canvas>
                <span id="bpmDisplay" style="color:var(--crimson); font-weight:800;">72 BPM</span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="video-control-btn" id="toggleSoundBtn" title="Toggle realistic cardiac sound">
                    <i class="fas fa-volume-mute" id="soundIcon"></i> Heartbeat Sound
                </button>
                <button class="video-control-btn" id="toggleVideoBtn" title="Toggle background video playback">
                    <i class="fas fa-pause" id="videoIcon"></i> Pause Video
                </button>
            </div>
        </div>
    </div>

    <!-- ========== HEADER ========== -->
    <header id="navbar">
        <div class="container">
            <div class="logo">
                <div class="logo-icon-wrap">
                    <i class="fas fa-droplet"></i>
                </div>
                <div>Life<span>Flow</span></div>
            </div>
            <div class="header-action-bar">
                <button type="button" class="header-menu-btn" id="headerMenuBtn" title="Open Navigation Menu">
                    <i class="fas fa-bars"></i>
                    <span>Menu</span>
                </button>
                <div class="header-profile-pill" id="headerUserProfile" title="Logged in as <?php echo htmlspecialchars($userName); ?>" style="cursor:pointer;" role="button" tabindex="0">
                    <i class="fas fa-heart-pulse"></i>
                    <span class="profile-name" id="headerUserName"><?php echo htmlspecialchars($userName !== '' ? $userName : 'Donor Profile'); ?></span>
                    <span class="role-tag">Donor</span>
                </div>
                <button type="button" class="header-call-btn" id="openCallDirectoryBtn" title="Direct Phone Dial Registered Contacts">
                    <span class="call-pulse-dot"></span>
                    <i class="fas fa-phone-volume"></i>
                    <span class="call-btn-text">Call Directory</span>
                </button>
                <a href="../medical_news.php" title="Live Medical News" style="display:inline-flex;align-items:center;gap:7px;padding:8px 15px;border:1px solid rgba(220,38,38,0.25);border-radius:50px;background:rgba(220,38,38,0.07);color:#dc2626;font-weight:700;font-size:0.83rem;text-decoration:none;transition:all 0.25s;white-space:nowrap;"><i class="fas fa-newspaper"></i> Medical News</a>
                <a href="../hospital/directory.php" title="Hospital Directory" style="display:inline-flex;align-items:center;gap:7px;padding:8px 15px;border:1px solid rgba(37,99,235,0.25);border-radius:50px;background:rgba(37,99,235,0.07);color:#1d4ed8;font-weight:700;font-size:0.83rem;text-decoration:none;transition:all 0.25s;white-space:nowrap;"><i class="fas fa-hospital"></i> Hospitals</a>
                <?php if ($userName !== ''): ?>
                <a href="../logout.php" class="header-logout-btn" title="Logout"><i class="fas fa-arrow-right-from-bracket"></i> <span>Logout</span></a>
                <?php else: ?>
                <a href="../login.html" class="header-logout-btn" title="Sign In"><i class="fas fa-arrow-right-to-bracket"></i> <span>Sign In</span></a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ========== LIVE VERIFIED MEDICAL NEWS TICKER ========== -->
    <div class="live-medical-ticker-wrap" id="donorLiveTickerWrap">
        <div class="ticker-inner">
            <div class="ticker-meta">
                <a href="../medical_news.php" class="ticker-badge" title="Open Medical News Portal">
                    <i class="fas fa-tower-broadcast"></i> LIVE MEDICAL NEWS
                </a>
                <span class="ticker-source-tag" id="donorTickerSource"><i class="fas fa-circle-check"></i> WHO</span>
                <span class="ticker-time-tag" id="donorTickerTime"><i class="fas fa-clock"></i> Just now</span>
            </div>

            <div class="ticker-content">
                <a href="../medical_news.php" class="ticker-text-box" id="donorTickerTextBox" title="Click to view full medical report">
                    <i class="fas fa-newspaper ticker-icon" id="donorTickerIcon"></i>
                    <span class="ticker-title" id="donorTickerTitle">Loading verified clinical updates...</span>
                    <span class="ticker-summary" id="donorTickerSummary">— Real-time alerts directly from national hematology networks.</span>
                </a>
            </div>

            <div class="ticker-controls">
                <button type="button" class="btn-ticker" id="donorTickerPrev" title="Previous news"><i class="fas fa-chevron-left"></i></button>
                <button type="button" class="btn-ticker" id="donorTickerPause" title="Pause ticker"><i class="fas fa-pause"></i></button>
                <button type="button" class="btn-ticker" id="donorTickerNext" title="Next news"><i class="fas fa-chevron-right"></i></button>
                <a href="../medical_news.php" class="btn-ticker-viewall" title="View all medical news articles"><i class="fas fa-newspaper"></i> View All</a>
            </div>
        </div>
    </div>

    <!-- ========== HERO SECTION ========== -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <div class="hero-tag">
                    <i class="fas fa-shield-heart"></i> LIFEFLOW · DONOR DASHBOARD
                </div>
                <h1>
                    Hello, <span class="blood-gradient"><?php echo htmlspecialchars($userName); ?></span>
                </h1>
                <p>
                    Welcome to your LifeFlow verified donor dashboard. Here is your real-time status, matched emergency requests, and bio-compatibility profile.
                </p>
                <div class="hero-actions">
                    <a href="eligibility.php" class="btn-primary">
                        <i class="fas fa-clipboard-check"></i> <?php echo !empty($hasCompletedScreening) ? 'Retake Screening' : 'Start Eligibility Screening'; ?>
                    </a>
                    <a href="../map.php?view=requests" class="btn-primary" style="background:#0f172a;border:1.5px solid #ff4d6d;color:#ff4d6d;" title="View all users/patients needing blood on interactive map">
                        <i class="fas fa-map-location-dot"></i> Users / Requests Map
                    </a>
                    <a href="profile.php" class="btn-outline">
                        <i class="fas fa-id-card"></i> View Donor Profile Card
                    </a>
                </div>

                <div class="hero-stats">
                    <div class="stat">
                        <div class="number" id="statDonors">0</div>
                        <div class="label">Registered Donors</div>
                    </div>
                    <div class="stat">
                        <div class="number" id="statLives">0</div>
                        <div class="label">Lives Saved</div>
                    </div>
                    <div class="stat">
                        <div class="number" id="statCenters">0</div>
                        <div class="label">Active Hubs</div>
                    </div>
                </div>
            </div>

            <!-- Hero Interactive 3D Visualizer -->
            <div class="hero-image">
                <div class="hero-visual-card" id="heroCard">
                    <div class="blood-heart-visual" id="heartSoundTrigger" role="button" tabindex="0" aria-label="Toggle heartbeat sound">
                        <div class="heart-glow-ring"></div>
                        <i class="fas fa-heart-pulse main-heart-icon" id="mainHeartIcon"></i>
                    </div>
                    <h3>LifeFlow Pulse</h3>
                    <p>Your single donation can rescue up to 3 individual patients.</p>

                    <div class="blood-meter-wrapper">
                        <i class="fas fa-tint" style="color:#ff2a44;"></i>
                        <div class="blood-meter-track">
                            <div class="blood-meter-fill" id="heroBloodFill"></div>
                        </div>
                        <span style="color:#fff; font-weight:800; font-size:0.85rem;" id="bloodFillPct">84% Capacity</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="compatibility-section" id="requests">
        <div class="container">
            <div class="section-header">
                <span class="section-tag"><i class="fas fa-bell"></i> Donor Dispatch</span>
                <h2>Requests matching your <span>blood group</span></h2>
                <p>These open requests are loaded from the database and sorted by urgency.</p>
            </div>
            <div class="matrix-box request-grid">
                <?php if ($databaseMessage): ?>
                    <div class="empty-request"><i class="fas fa-database"></i><strong><?php echo htmlspecialchars($databaseMessage); ?></strong><span>Open phpMyAdmin, select the blood_connect database, import <code>database/setup.sql</code>, and reload.</span></div>
                <?php elseif (!$donorRequests): ?>
                    <div class="empty-request"><i class="fas fa-circle-check"></i><strong>No matching requests right now</strong><span>We will show new requests here when your blood group is needed.</span></div>
                <?php else: ?>
                    <?php foreach ($donorRequests as $request): ?>
                        <article class="request-card">
                            <div class="request-card-top"><span class="request-blood"><?php echo htmlspecialchars($request['blood_type']); ?></span><span class="request-urgency <?php echo htmlspecialchars($request['urgency']); ?>"><?php echo htmlspecialchars(ucfirst($request['urgency'])); ?></span></div>
                            <h3><?php echo htmlspecialchars($request['patient_name']); ?></h3>
                            <p><i class="fas fa-hospital"></i> <?php echo htmlspecialchars($request['hospital']); ?></p>
                            <p><i class="fas fa-location-dot"></i> <?php echo htmlspecialchars($request['city']); ?> · <?php echo (int) $request['units']; ?> unit(s)</p>
                            <form method="post" action="../claim_request.php"><input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>"><button class="btn-primary" type="submit"><i class="fas fa-hand-holding-heart"></i> I can help</button></form>
                            <div style="display:flex; gap:8px; margin-top:10px;">
                                <button type="button" class="btn-webrtc-call" style="flex:1; justify-content:center; padding:9px 12px; font-size:0.82rem;"
                                        data-webrtc-call
                                        data-receiver-id="<?php echo (int)($request['requester_id'] ?? 0); ?>"
                                        data-receiver-name="<?php echo htmlspecialchars($request['patient_name'], ENT_QUOTES); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['blood_type'], ENT_QUOTES); ?>"
                                        data-role="Patient"
                                        data-call-type="audio"
                                        data-context-type="blood_request"
                                        data-context-id="<?php echo (int)$request['id']; ?>"
                                        title="Direct WebRTC Audio Call">
                                    <i class="fas fa-phone"></i> Call
                                </button>
                                <button type="button" class="btn-webrtc-call btn-video" style="padding:9px 14px; font-size:0.82rem;"
                                        data-webrtc-call
                                        data-receiver-id="<?php echo (int)($request['requester_id'] ?? 0); ?>"
                                        data-receiver-name="<?php echo htmlspecialchars($request['patient_name'], ENT_QUOTES); ?>"
                                        data-blood-type="<?php echo htmlspecialchars($request['blood_type'], ENT_QUOTES); ?>"
                                        data-role="Patient"
                                        data-call-type="video"
                                        data-context-type="blood_request"
                                        data-context-id="<?php echo (int)$request['id']; ?>"
                                        title="Direct WebRTC Video Call">
                                    <i class="fas fa-video"></i>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ========== HOSPITAL BLOOD RESERVES & DEFICIT GRAPH ========== -->
    <section class="compatibility-section" id="hospital-stocks" style="padding-top:20px;">
        <div class="container">
            <div class="section-header">
                <span class="section-tag" style="background:rgba(230,57,70,0.12);color:#ff4d6d;border-color:rgba(230,57,70,0.3);">
                    <i class="fas fa-hospital"></i> Regional Hospital Reserves
                </span>
                <h2>Hospital Blood Stocks &amp; <span>Deficit Graph</span></h2>
                <p>Live inventory reported across regional hospital blood banks. See which blood groups face critical shortages where your donation is urgently required.</p>
            </div>

            <div style="background:#ffffff;border:1px solid #ecdfda;border-radius:24px;padding:32px;box-shadow:0 16px 40px rgba(143,18,32,0.06);">
                <!-- Top Summary Counters -->
                <div id="donorHospSummary" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;align-items:center;">
                    <!-- Filled dynamically from API -->
                </div>

                <!-- Dual Chart Layout -->
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:28px;" id="donorChartsGrid">
                    <div style="background:#ffffff;border:1px solid #ecdfda;border-radius:18px;padding:22px;box-shadow:0 4px 14px rgba(0,0,0,0.03);">
                        <div style="font:700 1rem 'Space Grotesk',sans-serif;color:#0f172a;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                            <span><i class="fas fa-chart-column" style="color:#d62839;margin-right:8px;"></i> Hospital Stock vs Minimum Reserve Threshold</span>
                            <span style="font-size:0.78rem;color:#64748b;font-weight:600;">Units per Blood Type</span>
                        </div>
                        <div style="position:relative;height:300px;">
                            <canvas id="donorHospitalBarChart"></canvas>
                        </div>
                    </div>
                    <div style="background:#ffffff;border:1px solid #ecdfda;border-radius:18px;padding:22px;box-shadow:0 4px 14px rgba(0,0,0,0.03);">
                        <div style="font:700 1rem 'Space Grotesk',sans-serif;color:#0f172a;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                            <span><i class="fas fa-chart-pie" style="color:#10b981;margin-right:8px;"></i> Stock Distribution</span>
                            <span style="font-size:0.78rem;color:#64748b;font-weight:600;">Reserve Share</span>
                        </div>
                        <div style="position:relative;height:300px;">
                            <canvas id="donorHospitalDonutChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Blood Groups Live Grid -->
                <div id="donorBloodGroupGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:14px;">
                    <!-- Filled dynamically from API -->
                </div>
            </div>
        </div>
    </section>

    <!-- ========== BLOOD COMPATIBILITY MATRIX ========== -->
    <section class="compatibility-section" id="compatibility">
        <div class="container">
            <div class="section-header">
                <span class="section-tag"><i class="fas fa-dna"></i> Bio-Compatibility</span>
                <h2>Interactive <span>Blood Matrix</span></h2>
                <p>Select your blood type below to instantly inspect which groups you can donate to and receive from.</p>
            </div>

            <div class="matrix-box">
                <div class="blood-type-grid" id="typeGrid">
                    <button class="type-btn active" data-type="O+">O+</button>
                    <button class="type-btn" data-type="O-">O-</button>
                    <button class="type-btn" data-type="A+">A+</button>
                    <button class="type-btn" data-type="A-">A-</button>
                    <button class="type-btn" data-type="B+">B+</button>
                    <button class="type-btn" data-type="B-">B-</button>
                    <button class="type-btn" data-type="AB+">AB+</button>
                    <button class="type-btn" data-type="AB-">AB-</button>
                </div>

                <div class="compat-results">
                    <div class="compat-card give">
                        <h4><i class="fas fa-arrow-up-from-bracket"></i> You Can Give To:</h4>
                        <div class="compat-tags" id="giveTags">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                    <div class="compat-card receive">
                        <h4><i class="fas fa-arrow-down-to-bracket"></i> You Can Receive From:</h4>
                        <div class="compat-tags" id="receiveTags">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="features-section" id="donor-guide">
        <div class="container">
            <div class="section-header">
                <span class="section-tag"><i class="fas fa-heart-circle-check"></i> Donor Guide</span>
                <h2>Donate with <span>confidence</span></h2>
                <p>Simple preparation steps help every donor have a safe and comfortable experience.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card"><div class="feature-icon-wrap"><i class="fas fa-clipboard-check"></i></div><h3>Before you arrive</h3><p>Eat a balanced meal, drink water, carry valid identification, and tell the medical team about medications or health changes.</p></div>
                <div class="feature-card"><div class="feature-icon-wrap"><i class="fas fa-stethoscope"></i></div><h3>Eligibility check</h3><p>Donation eligibility depends on age, weight, health, travel, and local medical rules. Staff make the final decision.</p></div>
                <div class="feature-card"><div class="feature-icon-wrap"><i class="fas fa-glass-water"></i></div><h3>After donation</h3><p>Rest briefly, hydrate, avoid strenuous activity for the recommended period, and contact the center if you feel unwell.</p></div>
                <div class="feature-card"><div class="feature-icon-wrap"><i class="fas fa-chart-line"></i></div><h3>Track your impact</h3><p>Keep your donor profile current so matching requests show the right city, blood group, phone, and availability.</p></div>
            </div>
        </div>
    </section>

    <!-- ========== FEATURES ========== -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header">
                <span class="section-tag"><i class="fas fa-microscope"></i> Medical Excellence</span>
                <h2>Why Choose <span>LifeFlow</span></h2>
                <p>Designed with medical-grade precision and unmatched compassionate care.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon-wrap"><i class="fas fa-shield-virus"></i></div>
                    <h3>Ultra-Sterile Safety</h3>
                    <p>Every kit is 100% single-use and laboratory tested against rigorous global clinical benchmarks.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrap"><i class="fas fa-bolt"></i></div>
                    <h3>Fast 20-Min Process</h3>
                    <p>Advanced digital pre-screening allows you to walk in, donate comfortably, and continue your day.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrap"><i class="fas fa-truck-medical"></i></div>
                    <h3>Live Hospital Grid</h3>
                    <p>Direct sync with intensive care units ensures urgent blood requests are matched in real-time.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrap"><i class="fas fa-award"></i></div>
                    <h3>Hero Rewards &amp; Badges</h3>
                    <p>Track your lifetime impact, earn verified donor milestones, and see hospitals you helped.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== DONOR REGISTRATION FORM ========== -->
    <section class="donor-section" id="donate">
        <div class="container">
            <div class="form-glass-container">
                <h3><i class="fas fa-hand-holding-droplet" style="color:var(--crimson-glow); margin-right: 10px;"></i> Register as a Donor</h3>
                <p class="form-sub">Fill out the quick intake form below. Our emergency coordinator will confirm your preferred slot.</p>

                <form id="donorForm" onsubmit="handleDonorSubmit(event)">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <div class="input-wrap">
                                <input type="text" id="fullName" value="<?php echo htmlspecialchars($userName); ?>" placeholder="e.g. John Doe" required />
                                <i class="fas fa-user"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-wrap">
                                <input type="email" id="email" placeholder="john@example.com" required />
                                <i class="fas fa-envelope"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <div class="input-wrap">
                                <input type="tel" id="phone" placeholder="+1 (555) 019-2834" required />
                                <i class="fas fa-phone"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bloodType">Blood Group</label>
                            <div class="input-wrap">
                                <select id="bloodType" required>
                                    <option value="">Select your type</option>
                                    <option value="O+">O+ (Universal RBC Recipient)</option>
                                    <option value="O-">O- (Universal Donor)</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+ (Universal Plasma Donor)</option>
                                    <option value="AB-">AB-</option>
                                    <option value="Unknown">I don't know yet</option>
                                </select>
                                <i class="fas fa-droplet"></i>
                            </div>
                        </div>

                        <div class="form-group full">
                            <label for="city">City / District Location</label>
                            <div class="input-wrap">
                                <input type="text" id="city" placeholder="e.g. Manhattan, New York" required />
                                <i class="fas fa-location-dot"></i>
                            </div>
                        </div>

                        <div class="form-group full">
                            <label for="notes">Medical Notes / Preferred Time (Optional)</label>
                            <div class="input-wrap">
                                <textarea id="notes" placeholder="Tell us if you have preferred morning/evening hours or previous donation history..."></textarea>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px; padding: 18px;">
                        <i class="fas fa-heart-pulse"></i> Submit Registration &amp; Dispatch Alert
                    </button>
                </form>

                <div id="donationSuccess">
                    <i class="fas fa-circle-check"></i>
                    <h4>Donor Application Confirmed!</h4>
                    <p>Thank you, hero! Your registration is recorded and our medical dispatch unit will contact you within 2 hours.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer id="contact">
        <div class="container">
            <div>
                <div class="logo" style="margin-bottom: 16px;">
                    <div class="logo-icon-wrap">
                        <i class="fas fa-droplet"></i>
                    </div>
                    <div>Life<span>Flow</span></div>
                </div>
                <p>
                    The global cloud blood collection and emergency transfusion network. 
                    Empowering everyday heroes to save precious human lives every minute.
                </p>
                <div class="socials">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>

            <div>
                <h4>Explore</h4>
                <ul>
                    <li><a href="#home">Home Base</a></li>
                    <li><a href="#compatibility">Compatibility Guide</a></li>
                    <li><a href="#features">Safety Protocols</a></li>
                    <li><a href="#donate">Volunteer as Donor</a></li>
                </ul>
            </div>

            <div>
                <h4>Medical Hubs</h4>
                <ul>
                    <li><a href="#">Emergency Transfusion</a></li>
                    <li><a href="#">Platelet Apheresis</a></li>
                    <li><a href="#">Plasma Fractionation</a></li>
                    <li><a href="#">Rare Blood Bank Registry</a></li>
                </ul>
            </div>

            <div>
                <h4>Direct Dispatch</h4>
                <ul>
                    <li><i class="fas fa-phone" style="color:var(--crimson-glow); margin-right:8px;"></i> 24/7 Hotline: +1 (800) 555-0199</li>
                    <li><i class="fas fa-envelope" style="color:var(--crimson-glow); margin-right:8px;"></i> emergency@lifeflow.org</li>
                    <li><i class="fas fa-hospital" style="color:var(--crimson-glow); margin-right:8px;"></i> Central Medical Command, NY</li>
                </ul>
            </div>

            <div class="footer-bottom">
                &copy; 2026 <strong>LifeFlow Network</strong>. Built for real-time life preservation. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- ========== ULTRA-ANIMATION JAVASCRIPT ENGINE ========== -->
    <script>
        /* -------------------------------------------------------------
           1. HIGH-PERFORMANCE 3D INTERACTIVE BLOODSTREAM CANVAS
           ------------------------------------------------------------- */
        const canvas = document.getElementById('bloodCanvas');
        const ctx = canvas.getContext('2d');
        let width, height;
        let cells = [];
        let mouse = { x: -1000, y: -1000, radius: 140 };

        function resizeCanvas() {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        window.addEventListener('mousemove', (e) => {
            mouse.x = e.clientX;
            mouse.y = e.clientY;
        });

        // Match the login page's liquid blood-droplet cursor interaction.
        const cursorCanvas = document.getElementById('cursorCanvas');
        const cursorCtx = cursorCanvas.getContext('2d');
        let cursorParticles = [];
        function resizeCursorCanvas() {
            cursorCanvas.width = window.innerWidth;
            cursorCanvas.height = window.innerHeight;
        }
        class DropletParticle {
            constructor(x, y) {
                this.x = x;
                this.y = y;
                this.size = Math.random() * 6 + 3;
                this.speedX = (Math.random() - 0.5) * 2;
                this.speedY = Math.random() * 2 + 1;
                this.opacity = 0.8;
            }
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                this.size *= 0.95;
                this.opacity -= 0.025;
            }
            draw() {
                cursorCtx.fillStyle = 'rgba(230, 57, 70, ' + Math.max(0, this.opacity) + ')';
                cursorCtx.beginPath();
                cursorCtx.arc(this.x, this.y, Math.max(0.1, this.size), 0, Math.PI * 2);
                cursorCtx.fill();
            }
        }
        function animateCursorParticles() {
            cursorCtx.clearRect(0, 0, cursorCanvas.width, cursorCanvas.height);
            for (let i = cursorParticles.length - 1; i >= 0; i--) {
                const particle = cursorParticles[i];
                particle.update();
                particle.draw();
                if (particle.opacity <= 0 || particle.size <= 0.5) cursorParticles.splice(i, 1);
            }
            requestAnimationFrame(animateCursorParticles);
        }
        window.addEventListener('resize', resizeCursorCanvas);
        window.addEventListener('mousemove', event => {
            if (Math.random() > 0.4) cursorParticles.push(new DropletParticle(event.clientX, event.clientY));
        });
        resizeCursorCanvas();
        animateCursorParticles();

        // Erythrocyte (Red Blood Cell) 3D model simulation
        class RedBloodCell {
            constructor() {
                this.reset();
            }

            reset() {
                this.x = Math.random() * width;
                this.y = height + Math.random() * 100;
                this.z = Math.random() * 0.8 + 0.2; // Depth factor
                this.baseRadius = (12 + Math.random() * 22) * this.z;
                this.radiusX = this.baseRadius;
                this.radiusY = this.baseRadius * (0.6 + Math.random() * 0.35); // Biconcave tilt
                this.speedY = (0.8 + Math.random() * 1.8) * this.z;
                this.speedX = (Math.random() - 0.5) * 0.6;
                this.angle = Math.random() * Math.PI * 2;
                this.rotSpeed = (Math.random() - 0.5) * 0.02;
                this.opacity = (0.25 + Math.random() * 0.45) * this.z;
                this.pulseOffset = Math.random() * Math.PI * 2;
            }

            update(heartbeatScale) {
                this.y -= this.speedY * heartbeatScale;
                this.x += this.speedX + Math.sin(this.y * 0.01 + this.pulseOffset) * 0.4;
                this.angle += this.rotSpeed;

                // Mouse interaction physics (deflection)
                const dx = this.x - mouse.x;
                const dy = this.y - mouse.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < mouse.radius) {
                    const force = (mouse.radius - dist) / mouse.radius;
                    this.x += (dx / dist) * force * 5;
                    this.y += (dy / dist) * force * 5;
                }

                // Recycle to bottom when off top
                if (this.y < -50) {
                    this.reset();
                }
            }

            draw() {
                ctx.save();
                ctx.translate(this.x, this.y);
                ctx.rotate(this.angle);

                // 3D Biconcave Erythrocyte shading
                const grad = ctx.createRadialGradient(
                    -this.radiusX * 0.2, -this.radiusY * 0.2, this.radiusX * 0.1,
                    0, 0, this.radiusX
                );
                grad.addColorStop(0, `rgba(255, 90, 110, ${this.opacity})`);
                grad.addColorStop(0.4, `rgba(220, 30, 50, ${this.opacity * 0.9})`);
                grad.addColorStop(0.8, `rgba(140, 0, 20, ${this.opacity * 0.8})`);
                grad.addColorStop(1, `rgba(60, 0, 10, ${this.opacity * 0.4})`);

                ctx.fillStyle = grad;
                ctx.beginPath();
                ctx.ellipse(0, 0, this.radiusX, this.radiusY, 0, 0, Math.PI * 2);
                ctx.fill();

                // Inner dimple of Red Blood Cell (concave reflection)
                ctx.beginPath();
                ctx.ellipse(0, 0, this.radiusX * 0.4, this.radiusY * 0.35, 0, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(80, 0, 15, ${this.opacity * 0.6})`;
                ctx.fill();

                ctx.restore();
            }
        }

        // Initialize blood cell pool
        const CELL_COUNT = Math.min(65, Math.floor(window.innerWidth / 20));
        for (let i = 0; i < CELL_COUNT; i++) {
            const cell = new RedBloodCell();
            cell.y = Math.random() * height; // initial spread
            cells.push(cell);
        }

        // Cardiac pulse state
        let heartbeatTime = 0;
        function animateBloodStream() {
            ctx.clearRect(0, 0, width, height);

            heartbeatTime += 0.035;
            // Rhythmic heart systole/diastole surge
            const heartbeatSurge = 1 + 0.35 * Math.pow(Math.sin(heartbeatTime * 2.5), 8);

            for (let i = 0; i < cells.length; i++) {
                cells[i].update(heartbeatSurge);
                cells[i].draw();
            }

            requestAnimationFrame(animateBloodStream);
        }
        animateBloodStream();

        /* -------------------------------------------------------------
           2. LIVE MINI ECG OSCILLOSCOPE & CARDIAC AUDIO ENGINE
           ------------------------------------------------------------- */
        const ecgCanvas = document.getElementById('ecgMini');
        const ecgCtx = ecgCanvas.getContext('2d');
        let ecgOffset = 0;

        function drawECG() {
            const w = ecgCanvas.width;
            const h = ecgCanvas.height;
            ecgCtx.clearRect(0, 0, w, h);

            ecgCtx.strokeStyle = '#06d6a0';
            ecgCtx.lineWidth = 2;
            ecgCtx.shadowColor = '#06d6a0';
            ecgCtx.shadowBlur = 8;
            ecgCtx.beginPath();

            const mid = h / 2;
            for (let x = 0; x < w; x++) {
                const pos = (x + ecgOffset) % 120;
                let y = mid;

                // Typical P-Q-R-S-T cardiac wave pattern
                if (pos > 20 && pos < 30) {
                    y -= Math.sin((pos - 20) / 10 * Math.PI) * 4; // P wave
                } else if (pos >= 40 && pos < 44) {
                    y += 3; // Q dip
                } else if (pos >= 44 && pos < 50) {
                    y -= 14; // R peak
                } else if (pos >= 50 && pos < 55) {
                    y += 6; // S dip
                } else if (pos > 65 && pos < 82) {
                    y -= Math.sin((pos - 65) / 17 * Math.PI) * 6; // T wave
                }

                if (x === 0) ecgCtx.moveTo(x, y);
                else ecgCtx.lineTo(x, y);
            }
            ecgCtx.stroke();
            ecgOffset += 1.8;

            requestAnimationFrame(drawECG);
        }
        drawECG();

        // Web Audio heartbeat: layered low thumps plus filtered breath noise.
        let audioCtx = null;
        let isAudioPlaying = false;
        let heartbeatInterval = null;
        let masterGain = null;
        const mainHeartIcon = document.getElementById('mainHeartIcon');
        const heartSoundTrigger = document.getElementById('heartSoundTrigger');

        function playHeartbeatBeat() {
            if (!audioCtx || !isAudioPlaying) return;
            playThump(58, 0.16, 0.42);
            playNoise(0.16, 0.08);
            setTimeout(() => {
                if (isAudioPlaying) {
                    playThump(46, 0.2, 0.34);
                    playNoise(0.2, 0.06);
                }
            }, 145);
            if (mainHeartIcon) {
                mainHeartIcon.classList.remove('audio-beat');
                void mainHeartIcon.offsetWidth;
                mainHeartIcon.classList.add('audio-beat');
            }
        }

        function playThump(startFreq, duration, gainValue) {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            const now = audioCtx.currentTime;
            osc.type = 'sine';
            osc.frequency.setValueAtTime(startFreq, now);
            osc.frequency.exponentialRampToValueAtTime(startFreq * 0.55, now + duration);
            gain.gain.setValueAtTime(0.001, now);
            gain.gain.exponentialRampToValueAtTime(gainValue, now + 0.012);
            gain.gain.exponentialRampToValueAtTime(0.001, now + duration);
            osc.connect(gain);
            gain.connect(masterGain);
            osc.start(now);
            osc.stop(now + duration + 0.02);
        }

        function playNoise(duration, gainValue) {
            const buffer = audioCtx.createBuffer(1, audioCtx.sampleRate * duration, audioCtx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
            const source = audioCtx.createBufferSource();
            const filter = audioCtx.createBiquadFilter();
            const gain = audioCtx.createGain();
            const now = audioCtx.currentTime;
            filter.type = 'lowpass';
            filter.frequency.setValueAtTime(115, now);
            filter.Q.setValueAtTime(0.7, now);
            gain.gain.setValueAtTime(gainValue, now);
            gain.gain.exponentialRampToValueAtTime(0.001, now + duration);
            source.buffer = buffer;
            source.connect(filter);
            filter.connect(gain);
            gain.connect(masterGain);
            source.start(now);
            source.stop(now + duration);
        }

        const toggleSoundBtn = document.getElementById('toggleSoundBtn');
        const soundIcon = document.getElementById('soundIcon');
        function toggleHeartbeatSound() {
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                masterGain = audioCtx.createGain();
                const compressor = audioCtx.createDynamicsCompressor();
                masterGain.gain.value = 1.8;
                compressor.threshold.value = -9;
                compressor.knee.value = 12;
                compressor.ratio.value = 10;
                compressor.attack.value = 0.003;
                compressor.release.value = 0.18;
                masterGain.connect(compressor);
                compressor.connect(audioCtx.destination);
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            isAudioPlaying = !isAudioPlaying;
            if (isAudioPlaying) {
                soundIcon.className = 'fas fa-volume-high';
                toggleSoundBtn.style.background = 'rgba(6, 214, 160, 0.25)';
                toggleSoundBtn.style.borderColor = '#06d6a0';
                toggleSoundBtn.style.color = '#06d6a0';
                playHeartbeatBeat();
                heartbeatInterval = setInterval(playHeartbeatBeat, 830);
            } else {
                soundIcon.className = 'fas fa-volume-mute';
                toggleSoundBtn.style.background = '';
                toggleSoundBtn.style.borderColor = '';
                toggleSoundBtn.style.color = '';
                clearInterval(heartbeatInterval);
                if (mainHeartIcon) mainHeartIcon.classList.remove('audio-beat');
            }
        }
        toggleSoundBtn.addEventListener('click', toggleHeartbeatSound);
        heartSoundTrigger.addEventListener('click', toggleHeartbeatSound);
        heartSoundTrigger.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleHeartbeatSound();
            }
        });

        /* -------------------------------------------------------------
           3. BACKGROUND VIDEO CONTROLS
           ------------------------------------------------------------- */
        const bgVideo = document.getElementById('bgVideo');
        const toggleVideoBtn = document.getElementById('toggleVideoBtn');
        const videoIcon = document.getElementById('videoIcon');

        toggleVideoBtn.addEventListener('click', () => {
            if (bgVideo.paused) {
                bgVideo.play();
                videoIcon.className = 'fas fa-pause';
                toggleVideoBtn.innerHTML = '<i class="fas fa-pause" id="videoIcon"></i> Pause Video';
            } else {
                bgVideo.pause();
                videoIcon.className = 'fas fa-play';
                toggleVideoBtn.innerHTML = '<i class="fas fa-play" id="videoIcon"></i> Play Video';
            }
        });

        /* -------------------------------------------------------------
           4. BLOOD COMPATIBILITY MATRIX CALCULATOR
           ------------------------------------------------------------- */
        const compatData = {
            'O-': {
                give: ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+ (Universal Donor)'],
                receive: ['O-']
            },
            'O+': {
                give: ['O+', 'A+', 'B+', 'AB+'],
                receive: ['O+', 'O-']
            },
            'A-': {
                give: ['A-', 'A+', 'AB-', 'AB+'],
                receive: ['A-', 'O-']
            },
            'A+': {
                give: ['A+', 'AB+'],
                receive: ['A+', 'A-', 'O+', 'O-']
            },
            'B-': {
                give: ['B-', 'B+', 'AB-', 'AB+'],
                receive: ['B-', 'O-']
            },
            'B+': {
                give: ['B+', 'AB+'],
                receive: ['B+', 'B-', 'O+', 'O-']
            },
            'AB-': {
                give: ['AB-', 'AB+'],
                receive: ['AB-', 'A-', 'B-', 'O-']
            },
            'AB+': {
                give: ['AB+ only'],
                receive: ['Everyone (Universal Recipient — O±, A±, B±, AB±)']
            }
        };

        const typeButtons = document.querySelectorAll('.type-btn');
        const giveTags = document.getElementById('giveTags');
        const receiveTags = document.getElementById('receiveTags');

        function updateCompatibility(type) {
            giveTags.innerHTML = '';
            receiveTags.innerHTML = '';

            const data = compatData[type] || { give: [], receive: [] };

            data.give.forEach(item => {
                const pill = document.createElement('span');
                pill.className = 'compat-pill highlight-give';
                pill.textContent = item;
                giveTags.appendChild(pill);
            });

            data.receive.forEach(item => {
                const pill = document.createElement('span');
                pill.className = 'compat-pill highlight-receive';
                pill.textContent = item;
                receiveTags.appendChild(pill);
            });
        }

        typeButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                typeButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                updateCompatibility(this.getAttribute('data-type'));
            });
        });
        updateCompatibility('O+');

        /* -------------------------------------------------------------
           5. ANIMATED NUMERICAL COUNTERS
           ------------------------------------------------------------- */
        function runCounter(id, target, duration = 2200) {
            const el = document.getElementById(id);
            if (!el) return;
            let start = 0;
            const startTime = performance.now();
            function step(curr) {
                const elapsed = curr - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOut = 1 - Math.pow(1 - progress, 4);
                const val = Math.floor(easeOut * target);
                el.textContent = val.toLocaleString();
                if (progress < 1) requestAnimationFrame(step);
                else el.textContent = target.toLocaleString();
            }
            requestAnimationFrame(step);
        }

        window.addEventListener('load', () => {
            runCounter('statDonors', 14280);
            runCounter('statLives', 42950);
            runCounter('statCenters', 36);
        });

        /* -------------------------------------------------------------
           6. 3D CARD PERSPECTIVE TILT
           ------------------------------------------------------------- */
        const heroCard = document.getElementById('heroCard');
        if (heroCard) {
            heroCard.addEventListener('mousemove', (e) => {
                const rect = heroCard.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                heroCard.style.transform = `perspective(1000px) rotateX(${-y * 0.05}deg) rotateY(${x * 0.05}deg) scale3d(1.02, 1.02, 1.02)`;
            });
            heroCard.addEventListener('mouseleave', () => {
                heroCard.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
            });
        }

        /* -------------------------------------------------------------
           7. DONOR SUBMISSION HANDLER WITH ANIMATED CONFIRMATION
           ------------------------------------------------------------- */
        function handleDonorSubmit(e) {
            e.preventDefault();
            const fullName = document.getElementById('fullName').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const bloodType = document.getElementById('bloodType').value;

            if (!fullName || !email || !phone || !bloodType) {
                alert('Please complete all essential fields.');
                return;
            }

            const successBox = document.getElementById('donationSuccess');
            successBox.style.display = 'block';
            document.getElementById('donorForm').reset();
            successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Increase fill level meter visually
            const meterFill = document.getElementById('heroBloodFill');
            const pctLabel = document.getElementById('bloodFillPct');
            if (meterFill) {
                meterFill.style.width = '95%';
                if (pctLabel) pctLabel.textContent = '95% Capacity (Surge Level)';
            }
        }

        /* -------------------------------------------------------------
           8. NAVBAR SCROLL EFFECT
           ------------------------------------------------------------- */
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 40) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });
    </script>
    <!-- Call Directory & Shared WebRTC Call UI -->
    <script src="../call_directory.js"></script>
    <?php include __DIR__ . '/../includes/call_ui.php'; ?>
    <script src="../assets/js/call.js"></script>

    <!-- Slide-out Menu Controller -->
    <script src="../assets/js/header_menu.js"></script>
    <script>
        async function loadDonorHospitalBloodGraph() {
            try {
                const res = await fetch('../api/get_hospital_inventory.php');
                const data = await res.json();
                if (!data.success) return;

                // 1. Populate summary chips
                const summaryContainer = document.getElementById('donorHospSummary');
                if (summaryContainer) {
                    summaryContainer.innerHTML = `
                        <span style="background:#fff1f2;color:#b91c1c;font-weight:700;font-size:0.84rem;padding:7px 16px;border-radius:20px;border:1px solid #fecdd3;">
                            <i class="fas fa-droplet"></i> ${data.summary.total_units} Total Units in Reserve
                        </span>
                        <span style="background:#fee2e2;color:#b91c1c;font-weight:700;font-size:0.84rem;padding:7px 16px;border-radius:20px;border:1px solid #fca5a5;">
                            <i class="fas fa-circle-exclamation"></i> ${data.summary.critical_count} Critical Shortages
                        </span>
                        <span style="background:#fef3c7;color:#b45309;font-weight:700;font-size:0.84rem;padding:7px 16px;border-radius:20px;border:1px solid #fde68a;">
                            <i class="fas fa-triangle-exclamation"></i> ${data.summary.low_count} Low Stock
                        </span>
                        <span style="background:#ecfdf5;color:#047857;font-weight:700;font-size:0.84rem;padding:7px 16px;border-radius:20px;border:1px solid #a7f3d0;">
                            <i class="fas fa-circle-check"></i> ${data.summary.safe_count} Safe Reserves
                        </span>
                    `;
                }

                // 2. Bar Chart
                const barCanvas = document.getElementById('donorHospitalBarChart');
                if (barCanvas) {
                    const labels = data.groups.map(g => g.blood_group);
                    const availData = data.groups.map(g => g.available);
                    const minData = data.groups.map(g => g.minimum);
                    const colors = data.groups.map(g => g.color);

                    new Chart(barCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Current Hospital Reserve (Units)',
                                    data: availData,
                                    backgroundColor: colors,
                                    borderRadius: 6,
                                    barPercentage: 0.6
                                },
                                {
                                    label: 'Safety Reserve Threshold',
                                    data: minData,
                                    backgroundColor: 'rgba(100, 116, 139, 0.15)',
                                    borderColor: '#94a3b8',
                                    borderWidth: 2,
                                    borderDash: [5, 5],
                                    borderRadius: 6,
                                    barPercentage: 0.6
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        color: '#0f172a',
                                        font: { family: 'Space Grotesk, DM Sans, sans-serif', weight: '700', size: 12 }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#0f172a',
                                    titleColor: '#ffffff',
                                    bodyColor: '#e2e8f0',
                                    cornerRadius: 8
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(0, 0, 0, 0.06)' },
                                    ticks: { color: '#475569', font: { weight: '600' } }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { color: '#0f172a', font: { weight: '800', size: 13 } }
                                }
                            }
                        }
                    });
                }

                // 3. Donut Chart
                const donutCanvas = document.getElementById('donorHospitalDonutChart');
                if (donutCanvas) {
                    new Chart(donutCanvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: data.groups.map(g => g.blood_group),
                            datasets: [{
                                data: data.groups.map(g => g.available),
                                backgroundColor: [
                                    '#ef4444', '#f87171', '#f59e0b', '#fbbf24',
                                    '#10b981', '#34d399', '#3b82f6', '#60a5fa'
                                ],
                                borderColor: '#ffffff',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        boxWidth: 12,
                                        color: '#0f172a',
                                        font: { family: 'DM Sans, sans-serif', weight: '700', size: 12 }
                                    }
                                },
                                tooltip: {
                                    backgroundColor: '#0f172a',
                                    titleColor: '#ffffff',
                                    bodyColor: '#e2e8f0',
                                    cornerRadius: 8
                                }
                            },
                            cutout: '65%'
                        }
                    });
                }

                // 4. Blood Groups Grid
                const gridContainer = document.getElementById('donorBloodGroupGrid');
                if (gridContainer) {
                    gridContainer.innerHTML = data.groups.map(g => {
                        const bgStatusClass = (g.status === 'critical') 
                            ? 'background:#fee2e2;color:#b91c1c;border-color:#fca5a5;' 
                            : ((g.status === 'low') 
                                ? 'background:#fef3c7;color:#b45309;border-color:#fde68a;' 
                                : 'background:#ecfdf5;color:#047857;border-color:#a7f3d0;');
                        return `
                            <div onclick="targetDonorDonation('${g.blood_group}')" style="background:#ffffff;border:1.5px solid #e2e8f0;border-radius:14px;padding:14px 10px;text-align:center;cursor:pointer;transition:all 0.2s;box-shadow:0 2px 8px rgba(0,0,0,0.04);" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='#d62839'" onmouseout="this.style.transform='none';this.style.borderColor='#e2e8f0'" title="Donate for ${g.blood_group}">
                                <div style="font:800 1.25rem 'Space Grotesk',sans-serif;color:#d62839;">${g.blood_group}</div>
                                <div style="font:700 1.35rem 'DM Sans',sans-serif;color:#0f172a;margin:3px 0;">${g.available} <span style="font-size:0.75rem;color:#64748b;font-weight:600;">units</span></div>
                                <div style="display:inline-block;font-size:0.72rem;font-weight:800;padding:3px 8px;border-radius:12px;border:1px solid;margin-top:6px;${bgStatusClass}">${g.badge}</div>
                            </div>
                        `;
                    }).join('');
                }
            } catch (err) {
                console.error("Failed to load hospital inventory graph in donor dashboard:", err);
            }
        }

        function targetDonorDonation(bg) {
            const sel = document.getElementById('bloodType');
            if (sel) sel.value = bg;
            const formSection = document.getElementById('donate');
            if (formSection) formSection.scrollIntoView({ behavior: 'smooth' });
        }

        document.addEventListener('DOMContentLoaded', function() {
            BloodConnectHeader.init({
                role: 'donor',
                userName: <?php echo json_encode($userName); ?>,
                rootPath: '../'
            });
            loadDonorHospitalBloodGraph();

            /* ---------- LIVE VERIFIED MEDICAL NEWS TICKER CONTROLLER (DONOR) ---------- */
            let donorNewsList = [];
            let donorCurrentIndex = 0;
            let donorTickerTimer = null;
            let isDonorTickerPaused = false;

            async function loadDonorMedicalNews() {
                try {
                    const res = await fetch('../live.api.php', { cache: 'no-store' });
                    const data = await res.json();
                    if (data.success && Array.isArray(data.news) && data.news.length > 0) {
                        donorNewsList = data.news;
                        renderDonorTicker();
                        startDonorTicker();
                    }
                } catch (e) {
                    console.error('Error fetching medical news in donor dashboard:', e);
                }
            }

            function renderDonorTicker() {
                if (!donorNewsList.length) return;
                const item = donorNewsList[donorCurrentIndex];
                const textBox = document.getElementById('donorTickerTextBox');
                const titleEl = document.getElementById('donorTickerTitle');
                const summaryEl = document.getElementById('donorTickerSummary');
                const sourceEl = document.getElementById('donorTickerSource');
                const timeEl = document.getElementById('donorTickerTime');
                const iconEl = document.getElementById('donorTickerIcon');

                if (textBox) textBox.style.opacity = '0';
                setTimeout(() => {
                    if (titleEl) titleEl.textContent = item.title;
                    if (summaryEl) summaryEl.textContent = ' — ' + item.summary;
                    if (sourceEl) sourceEl.innerHTML = `<i class="fas fa-circle-check"></i> ${item.source}`;
                    if (timeEl) timeEl.innerHTML = `<i class="fas fa-clock"></i> ${item.time_ago}`;
                    if (iconEl) iconEl.className = 'fas ' + (item.icon || 'fa-newspaper') + ' ticker-icon';
                    if (textBox) textBox.style.opacity = '1';
                }, 180);
            }

            function nextDonorNews() {
                if (!donorNewsList.length) return;
                donorCurrentIndex = (donorCurrentIndex + 1) % donorNewsList.length;
                renderDonorTicker();
            }

            function prevDonorNews() {
                if (!donorNewsList.length) return;
                donorCurrentIndex = (donorCurrentIndex - 1 + donorNewsList.length) % donorNewsList.length;
                renderDonorTicker();
            }

            function startDonorTicker() {
                stopDonorTicker();
                donorTickerTimer = setInterval(() => {
                    if (!isDonorTickerPaused) {
                        nextDonorNews();
                    }
                }, 5000);
            }

            function stopDonorTicker() {
                if (donorTickerTimer) {
                    clearInterval(donorTickerTimer);
                    donorTickerTimer = null;
                }
            }

            const prevBtn = document.getElementById('donorTickerPrev');
            const nextBtn = document.getElementById('donorTickerNext');
            const pauseBtn = document.getElementById('donorTickerPause');
            const tickerWrap = document.getElementById('donorLiveTickerWrap');

            if (prevBtn) prevBtn.addEventListener('click', () => { prevDonorNews(); startDonorTicker(); });
            if (nextBtn) nextBtn.addEventListener('click', () => { nextDonorNews(); startDonorTicker(); });
            if (pauseBtn) {
                pauseBtn.addEventListener('click', () => {
                    isDonorTickerPaused = !isDonorTickerPaused;
                    pauseBtn.innerHTML = isDonorTickerPaused ? '<i class="fas fa-play"></i>' : '<i class="fas fa-pause"></i>';
                    pauseBtn.title = isDonorTickerPaused ? 'Resume ticker' : 'Pause ticker';
                });
            }

            if (tickerWrap) {
                tickerWrap.addEventListener('mouseenter', () => { isDonorTickerPaused = true; });
                tickerWrap.addEventListener('mouseleave', () => {
                    if (pauseBtn && !pauseBtn.innerHTML.includes('fa-play')) {
                        isDonorTickerPaused = false;
                    }
                });
            }

            loadDonorMedicalNews();
        });
    </script>
</body>
</html>