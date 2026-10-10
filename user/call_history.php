<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    header('Location: ../login.html?error=role');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../services/CallService.php';

$userId = (int)$_SESSION['user_id'];
$callService = new CallService();
$calls = $callService->getUserCallHistory($userId, 50);

function formatDuration(int $sec): string {
    if ($sec <= 0) return '00:00';
    $m = floor($sec / 60);
    $s = $sec % 60;
    return sprintf('%02d:%02d', $m, $s);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <title>Call History — BloodConnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/call.css">
    <style>
        :root {
            --bg-page: #f8fafc;
            --surface: #ffffff;
            --surface-hover: #f1f5f9;
            --border-line: #e2e8f0;
            --crimson: #e63946;
            --text-dark: #0f172a;
            --text-muted: #64748b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-page);
            color: var(--text-dark);
            min-height: 100vh;
            padding-bottom: 60px;
        }
        header {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-line);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            text-decoration: none;
        }
        .brand i { color: var(--crimson); }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: color 0.2s;
        }
        .nav-links a:hover { color: var(--crimson); }
        .container {
            max-width: 960px;
            margin: 40px auto 0;
            padding: 0 20px;
        }
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .page-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .history-card-light {
            background: var(--surface);
            border: 1px solid var(--border-line);
            border-radius: 18px;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .history-card-light:hover {
            background: var(--surface-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }
        .card-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }
        .call-icon-wrap {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        .call-outgoing {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            border: 1px solid rgba(59, 130, 246, 0.25);
        }
        .call-incoming {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .call-missed {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }
        .peer-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .blood-pill {
            background: rgba(230, 57, 70, 0.12);
            color: var(--crimson);
            font-size: 0.72rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
        }
        .card-meta {
            font-size: 0.82rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .empty-history {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            background: var(--surface);
            border-radius: 18px;
            border: 1px solid var(--border-line);
        }
        .empty-history i {
            font-size: 3rem;
            color: var(--crimson);
            opacity: 0.4;
            margin-bottom: 14px;
        }
    </style>
</head>
<body>

<header>
    <a href="user.html" class="brand"><i class="fas fa-droplet"></i> LifeFlow</a>
    <nav class="nav-links">
        <a href="user.html"><i class="fas fa-arrow-left"></i> Back to Portal</a>
        <a href="../logout.php" style="color: #ef4444;"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </nav>
</header>

<main class="container">
    <div class="page-header">
        <h1><i class="fas fa-phone-volume" style="color: var(--crimson); margin-right: 12px;"></i>Your Call History</h1>
        <p>Your confidential in-website WebRTC calls with donors and coordinators.</p>
    </div>

    <div class="history-list">
        <?php if (empty($calls)): ?>
            <div class="empty-history">
                <i class="fas fa-phone-slash"></i>
                <h3>No call history yet</h3>
                <p>When you call a donor or receive an in-website call, records will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($calls as $c): 
                $isCaller = ((int)$c['caller_user_id'] === $userId);
                $peerId = $isCaller ? (int)$c['receiver_user_id'] : (int)$c['caller_user_id'];
                $peerName = $isCaller ? $c['receiver_name'] : $c['caller_name'];
                $peerBlood = $isCaller ? $c['receiver_blood'] : $c['caller_blood'];
                $peerRole = $isCaller ? $c['receiver_role'] : $c['caller_role'];
                
                $isMissed = in_array($c['status'], ['missed', 'rejected', 'no_answer']);
                $iconClass = $isMissed ? 'call-missed' : ($isCaller ? 'call-outgoing' : 'call-incoming');
                $icon = $isMissed ? 'fa-phone-slash' : ($isCaller ? 'fa-phone-arrow-up-right' : 'fa-phone-arrow-down-left');
                if ($c['call_type'] === 'video') $icon = 'fa-video';
            ?>
                <div class="history-card-light">
                    <div class="card-left">
                        <div class="call-icon-wrap <?= $iconClass ?>">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div>
                            <div class="peer-name">
                                <?= htmlspecialchars($peerName, ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($peerBlood): ?>
                                    <span class="blood-pill"><?= htmlspecialchars($peerBlood, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="card-meta">
                                <span><i class="fas <?= $isCaller ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i> <?= $isCaller ? 'Outgoing' : 'Incoming' ?></span>
                                <span>•</span>
                                <span><i class="fas fa-clock"></i> <?= date('d M Y, h:i A', strtotime($c['started_at'])) ?></span>
                                <span>•</span>
                                <span><i class="fas fa-stopwatch"></i> <?= formatDuration((int)$c['duration_seconds']) ?></span>
                                <span>•</span>
                                <span style="text-transform: capitalize; font-weight: 700; color: <?= ($c['status'] === 'ended' || $c['status'] === 'connected') ? '#059669' : '#dc2626' ?>">
                                    <?= htmlspecialchars($c['status'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn-webrtc-call"
                                data-webrtc-call
                                data-receiver-id="<?= $peerId ?>"
                                data-receiver-name="<?= htmlspecialchars($peerName, ENT_QUOTES, 'UTF-8') ?>"
                                data-blood-type="<?= htmlspecialchars($peerBlood ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-role="<?= htmlspecialchars($peerRole, ENT_QUOTES, 'UTF-8') ?>"
                                data-call-type="audio"
                                title="Call Again (Audio)">
                            <i class="fas fa-phone"></i> Call
                        </button>
                        <button type="button" class="btn-webrtc-call btn-video"
                                data-webrtc-call
                                data-receiver-id="<?= $peerId ?>"
                                data-receiver-name="<?= htmlspecialchars($peerName, ENT_QUOTES, 'UTF-8') ?>"
                                data-blood-type="<?= htmlspecialchars($peerBlood ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                data-role="<?= htmlspecialchars($peerRole, ENT_QUOTES, 'UTF-8') ?>"
                                data-call-type="video"
                                title="Call Again (Video)">
                            <i class="fas fa-video"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Shared WebRTC Call Markup -->
<?php include __DIR__ . '/../includes/call_ui.php'; ?>
<script src="../assets/js/call.js"></script>
</body>
</html>
