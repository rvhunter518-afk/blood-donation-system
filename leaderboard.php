<?php
/**
 * leaderboard.php — Top Learners Leaderboard
 */
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.html?error=auth');
    exit;
}

require_once __DIR__ . '/config/database.php';
$conn   = getDbConnection();
$userId = (int)$_SESSION['user_id'];

$sql = "SELECT
    u.id,
    CONCAT(
        SUBSTRING_INDEX(u.full_name, ' ', 1),
        ' ',
        LEFT(SUBSTRING_INDEX(u.full_name, ' ', -1), 1),
        '.'
    ) AS display_name,
    gp.xp, gp.points, gp.tier, gp.current_streak,
    COALESCE(bp.books_completed, 0) AS books_completed,
    COALESCE(ub.badges_count, 0)    AS badges_count
FROM gamification_profiles gp
JOIN users u ON u.id = gp.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS books_completed FROM book_progress WHERE status = 'completed' GROUP BY user_id
) bp ON bp.user_id = gp.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS badges_count FROM user_badges GROUP BY user_id
) ub ON ub.user_id = gp.user_id
ORDER BY gp.xp DESC LIMIT 20";

$result = $conn->query($sql);
$leaders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Current user's rank
$myRank = 1;
$rankRes = $conn->query("SELECT COUNT(*)+1 AS r FROM gamification_profiles WHERE xp > COALESCE((SELECT xp FROM gamification_profiles WHERE user_id = $userId),0)");
if ($rankRes) $myRank = (int)$rankRes->fetch_assoc()['r'];

// Current user name (using full_name)
$meRes  = $conn->query("SELECT full_name FROM users WHERE id = $userId");
$meRow  = $meRes ? $meRes->fetch_assoc() : null;
$meName = $meRow ? (explode(' ', trim($meRow['full_name']))[0] ?? 'You') : 'You';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leaderboard — BloodConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --red:#d62839; --deep:#8f1220; --ink:#171b22; --paper:#f8f9fa;
    --card:#fff; --muted:#66707c; --line:#e5e7eb;
    --gold:#fbbf24; --silver:#94a3b8; --bronze:#b45309;
    --font-body:'DM Sans',sans-serif; --font-display:'Space Grotesk',sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
body { font-family:var(--font-body); background:var(--paper); color:var(--ink); min-height:100dvh; }
a { color:inherit; text-decoration:none; }
h1,h2,h3 { font-family:var(--font-display); }

/* ── HEADER ── */
.header { display:flex; align-items:center; justify-content:space-between; padding:14px max(20px,calc((100% - 1100px)/2)); background:#fff; border-bottom:1px solid var(--line); position:sticky; top:0; z-index:100; }
.brand { display:flex; align-items:center; gap:10px; font:700 1.3rem var(--font-display); color:var(--red); }
.brand i { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,var(--red),var(--deep)); color:#fff; display:grid; place-items:center; }
nav { display:flex; gap:6px; flex-wrap:wrap; }
nav a { padding:7px 13px; border-radius:10px; font:600 .82rem var(--font-body); color:var(--muted); transition:all .15s; display:flex; align-items:center; gap:6px; }
nav a:hover { color:var(--red); background:#d628390d; }
.btn-signout { border:1px solid var(--line); }

/* ── HERO ── */
.lb-hero { background:linear-gradient(135deg,var(--ink),#2d3748); color:#fff; padding:48px max(20px,calc((100% - 1100px)/2)) 28px; text-align:center; }
.lb-hero h1 { font-size:clamp(1.8rem,4vw,3rem); margin-bottom:8px; }
.lb-hero p { color:rgba(255,255,255,.65); font-size:.95rem; margin-bottom:20px; }
.my-rank-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.2); padding:10px 20px; border-radius:50px; font:700 .9rem var(--font-display); }

/* ── PAGE WRAP ── */
.page-wrap { max-width:1100px; margin:0 auto; padding:32px 20px 60px; }

/* ── PODIUM ── */
.podium { display:flex; justify-content:center; align-items:flex-end; gap:16px; margin-bottom:36px; flex-wrap:wrap; }
.podium-card { text-align:center; padding:20px 18px 24px; border-radius:18px; position:relative; min-width:160px; max-width:220px; flex:1; }
.podium-card.gold   { background:linear-gradient(135deg,#fef3c7,#fde68a); border:2px solid var(--gold); order:2; }
.podium-card.silver { background:linear-gradient(135deg,#f1f5f9,#e2e8f0); border:2px solid var(--silver); order:1; }
.podium-card.bronze { background:linear-gradient(135deg,#fef7ee,#fed7aa); border:2px solid var(--bronze); order:3; }
.podium-medal { font-size:2.8rem; margin-bottom:8px; display:block; }
.podium-rank { position:absolute; top:-14px; left:50%; transform:translateX(-50%); background:var(--ink); color:#fff; width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font:700 .8rem var(--font-display); }
.podium-rank.gold   { background:var(--gold); color:#78350f; }
.podium-rank.silver { background:var(--silver); color:#0f172a; }
.podium-rank.bronze { background:var(--bronze); color:#fff; }
.podium-name { font:700 .95rem var(--font-display); margin-bottom:4px; }
.podium-xp { font-size:1.3rem; font-weight:700; margin-bottom:2px; }
.podium-meta { font-size:.72rem; color:var(--muted); }

/* ── LEADERBOARD TABLE ── */
.lb-table-wrap { background:var(--card); border:1px solid var(--line); border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.06); }
.lb-table { width:100%; border-collapse:collapse; }
.lb-table th { padding:12px 16px; background:#f8fafc; font:600 .75rem var(--font-display); color:var(--muted); text-transform:uppercase; letter-spacing:.05em; text-align:left; border-bottom:1px solid var(--line); }
.lb-table td { padding:14px 16px; border-bottom:1px solid var(--line); font-size:.88rem; }
.lb-table tr:last-child td { border-bottom:0; }
.lb-table tr.is-me td { background:#fff5f5; }
.rank-cell { font:700 1rem var(--font-display); width:48px; text-align:center; }
.rank-4-10 { color:var(--muted); }
.name-cell { font-weight:600; }
.name-cell .me-tag { display:inline-flex; align-items:center; gap:4px; margin-left:6px; background:#fee2e2; color:var(--red); font:700 .65rem var(--font-body); padding:2px 7px; border-radius:5px; text-transform:uppercase; }
.xp-cell { font:700 .95rem var(--font-display); }
.tier-tag { display:inline-block; padding:3px 9px; border-radius:6px; font:700 .68rem var(--font-body); text-transform:uppercase; }
.tier-Bronze     { background:#fef3c7; color:#92400e; }
.tier-Silver     { background:#f1f5f9; color:#334155; }
.tier-Gold       { background:#fef3c7; color:#78350f; }
.tier-Life\ Saver { background:#dcfce7; color:#166534; }

/* ── CTA ROW ── */
.cta-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:24px; }
.btn-cta { display:inline-flex; align-items:center; gap:8px; padding:12px 22px; border-radius:12px; font:700 .9rem var(--font-body); border:0; cursor:pointer; transition:all .15s; }
.btn-cta.primary { background:var(--red); color:#fff; }
.btn-cta.primary:hover { background:var(--deep); }
.btn-cta.secondary { background:transparent; border:2px solid var(--line); color:var(--ink); }
.btn-cta.secondary:hover { border-color:var(--red); color:var(--red); }
.empty-state { text-align:center; padding:48px; color:var(--muted); }

@media (max-width:640px) { .lb-table th:nth-child(4), .lb-table td:nth-child(4), .lb-table th:nth-child(5), .lb-table td:nth-child(5) { display:none; } .podium { gap:8px; } .podium-card { min-width:100px; padding:14px 10px 18px; } }
</style>
</head>
<body>

<header class="header">
    <a class="brand" href="user/user.html"><i class="fas fa-droplet"></i> BloodConnect</a>
    <nav>
        <a href="library.php"><i class="fas fa-book-open"></i> Library</a>
        <a href="learning_dashboard.php"><i class="fas fa-chart-line"></i> My Progress</a>
        <a href="logout.php" class="btn-signout"><i class="fas fa-right-from-bracket"></i> Sign out</a>
    </nav>
</header>

<!-- Hero -->
<div class="lb-hero">
    <h1>🏆 Top Learners</h1>
    <p>Earn XP by reading books and acing quizzes. Climb the ranks!</p>
    <div class="my-rank-badge">
        <i class="fas fa-user"></i>
        <?= htmlspecialchars($meName) ?>, you are ranked <strong>#<?= $myRank ?></strong>
    </div>
</div>

<div class="page-wrap">

    <?php if (empty($leaders)): ?>
    <div class="empty-state">
        <i class="fas fa-trophy" style="font-size:3rem;color:var(--line);display:block;margin-bottom:12px"></i>
        <p>No learners yet! <a href="library.php" style="color:var(--red);font-weight:700">Start reading</a> to claim your spot.</p>
    </div>
    <?php else: ?>

    <!-- Podium (Top 3) -->
    <?php if (count($leaders) >= 1): ?>
    <div class="podium">
        <?php
        $medals = [1 => ['gold','🥇'], 2 => ['silver','🥈'], 3 => ['bronze','🥉']];
        $podiumOrder = array_slice($leaders, 0, min(3, count($leaders)));
        foreach ($podiumOrder as $i => $p):
            $rank = $i + 1;
            $cls  = $medals[$rank][0] ?? 'bronze';
            $med  = $medals[$rank][1] ?? '🎖️';
        ?>
        <div class="podium-card <?= $cls ?>">
            <div class="podium-rank <?= $cls ?>"><?= $rank ?></div>
            <span class="podium-medal"><?= $med ?></span>
            <div class="podium-name"><?= htmlspecialchars($p['display_name']) ?></div>
            <div class="podium-xp"><?= number_format((int)$p['xp']) ?> XP</div>
            <div class="podium-meta">
                📚 <?= (int)$p['books_completed'] ?> books ·
                🏅 <?= (int)$p['badges_count'] ?> badges
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Full Table -->
    <?php if (count($leaders) > 3): ?>
    <div class="lb-table-wrap">
        <table class="lb-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Learner</th>
                    <th>XP</th>
                    <th>Books</th>
                    <th>Badges</th>
                    <th>Tier</th>
                    <th>Streak</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($leaders, 3) as $j => $r):
                    $rank = $j + 4;
                    $isMe = ($r['id'] == $userId);
                ?>
                <tr class="<?= $isMe ? 'is-me' : '' ?>">
                    <td class="rank-cell"><span class="rank-4-10">#<?= $rank ?></span></td>
                    <td class="name-cell">
                        <?= htmlspecialchars($r['display_name']) ?>
                        <?php if ($isMe): ?><span class="me-tag"><i class="fas fa-user"></i> You</span><?php endif; ?>
                    </td>
                    <td class="xp-cell"><?= number_format((int)$r['xp']) ?></td>
                    <td><?= (int)$r['books_completed'] ?></td>
                    <td><?= (int)$r['badges_count'] ?></td>
                    <td><span class="tier-tag tier-<?= htmlspecialchars($r['tier']) ?>"><?= htmlspecialchars($r['tier']) ?></span></td>
                    <td><i class="fas fa-fire" style="color:#f97316"></i> <?= (int)$r['current_streak'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <div class="cta-row">
        <a href="library.php" class="btn-cta primary"><i class="fas fa-book-open"></i> Read Books & Earn XP</a>
        <a href="learning_dashboard.php" class="btn-cta secondary"><i class="fas fa-chart-line"></i> My Dashboard</a>
    </div>
</div>

</body>
</html>
