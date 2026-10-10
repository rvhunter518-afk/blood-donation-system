<?php
/**
 * learning_dashboard.php — Personal Learning Stats Dashboard
 */
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.html?error=auth');
    exit;
}

require_once __DIR__ . '/config/database.php';
$conn   = getDbConnection();
$userId = (int)$_SESSION['user_id'];

// User info — users table uses full_name (not first_name/last_name)
$uStmt = $conn->prepare('SELECT full_name FROM users WHERE id = ?');
$user = ['full_name' => 'Learner'];
if ($uStmt) {
    $uStmt->bind_param('i', $userId);
    $uStmt->execute();
    $user = $uStmt->get_result()->fetch_assoc() ?? $user;
    $uStmt->close();
}
$user['first_name'] = explode(' ', trim($user['full_name']))[0] ?? 'Learner';


// Gamification profile
$profStmt = $conn->prepare('SELECT points, tier, xp, current_level, current_streak, longest_streak, last_activity_date FROM gamification_profiles WHERE user_id = ?');
$profStmt->bind_param('i', $userId);
$profStmt->execute();
$profile = $profStmt->get_result()->fetch_assoc() ?? ['points'=>0,'tier'=>'Bronze','xp'=>0,'current_level'=>1,'current_streak'=>0,'longest_streak'=>0,'last_activity_date'=>null];
$profStmt->close();

// Books completed
$booksCompleted = (int)($conn->query("SELECT COUNT(*) as c FROM book_progress WHERE user_id = $userId AND status = 'completed'")->fetch_assoc()['c'] ?? 0);

// Total quizzes
$totalQuizzes = (int)($conn->query("SELECT COUNT(*) as c FROM quiz_attempts WHERE user_id = $userId")->fetch_assoc()['c'] ?? 0);

// Best quiz percentage
$bestPct = (int)($conn->query("SELECT MAX(percentage) as m FROM quiz_attempts WHERE user_id = $userId")->fetch_assoc()['m'] ?? 0);

// Recent quiz attempts (last 5)
$recentSQL = "SELECT qa.percentage, qa.score_level, qa.points_earned AS points_awarded, qa.xp_earned AS xp_awarded, qa.attempted_at AS completed_at, lb.title, lb.category
              FROM quiz_attempts qa
              JOIN library_books lb ON lb.id = qa.book_id
              WHERE qa.user_id = $userId
              ORDER BY qa.attempted_at DESC LIMIT 5";
$recentRes = $conn->query($recentSQL);
$recentAttempts = $recentRes ? $recentRes->fetch_all(MYSQLI_ASSOC) : [];

// Badges earned
$badgeSQL = "SELECT b.name, b.description, b.icon, b.points_reward AS xp_reward, ub.unlocked_at AS earned_at
             FROM user_badges ub JOIN badges b ON b.badge_id = ub.badge_id
             WHERE ub.user_id = $userId ORDER BY ub.unlocked_at DESC";
$badgeRes = $conn->query($badgeSQL);
$badges = $badgeRes ? $badgeRes->fetch_all(MYSQLI_ASSOC) : [];

// Category progress
$catSQL = "SELECT lb.category,
                  COUNT(*) as total,
                  SUM(CASE WHEN bp.status = 'completed' THEN 1 ELSE 0 END) as completed
           FROM library_books lb
           LEFT JOIN book_progress bp ON bp.book_id = lb.id AND bp.user_id = $userId
           WHERE lb.published_status = 1
           GROUP BY lb.category
           ORDER BY completed DESC, total DESC";
$catRes = $conn->query($catSQL);
$catProgress = $catRes ? $catRes->fetch_all(MYSQLI_ASSOC) : [];

// Streak calendar (last 7 days)
$streakDays = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $hasActivity = (int)($conn->query("SELECT COUNT(*) as c FROM quiz_attempts WHERE user_id = $userId AND DATE(attempted_at) = '$date'")->fetch_assoc()['c'] ?? 0);
    $streakDays[] = ['date' => $date, 'label' => date('D', strtotime($date)), 'active' => $hasActivity > 0];
}

$xp = (int)$profile['xp'];
$xpPct = min(100, (int)round(($xp % 500) / 500 * 100));
$nextLevelXp = ($profile['current_level'] + 1) * 500;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Learning Dashboard — BloodConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --red:#d62839; --deep:#8f1220; --ink:#171b22; --paper:#f8f9fa;
    --card:#fff; --muted:#66707c; --line:#e5e7eb; --green:#059669; --blue:#2563eb;
    --yellow:#d97706; --shadow:0 4px 24px rgba(0,0,0,.08);
    --font-body:'DM Sans',sans-serif; --font-display:'Space Grotesk',sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin:0; padding:0; }
body { font-family:var(--font-body); background:var(--paper); color:var(--ink); min-height:100dvh; }
a { color:inherit; text-decoration:none; }
h1,h2,h3 { font-family:var(--font-display); }

/* ── HEADER ── */
.header { display:flex; align-items:center; justify-content:space-between; padding:14px max(20px,calc((100% - 1200px)/2)); background:#fff; border-bottom:1px solid var(--line); position:sticky; top:0; z-index:100; }
.brand { display:flex; align-items:center; gap:10px; font:700 1.3rem var(--font-display); color:var(--red); }
.brand i { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,var(--red),var(--deep)); color:#fff; display:grid; place-items:center; }
nav { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
nav a { padding:7px 13px; border-radius:10px; font:600 .82rem var(--font-body); color:var(--muted); transition:all .15s; display:flex; align-items:center; gap:6px; }
nav a:hover { color:var(--red); background:#d628390d; }
.btn-signout { border:1px solid var(--line); }

/* ── PAGE SHELL ── */
.page-wrap { max-width:1200px; margin:0 auto; padding:32px 20px 60px; }
.page-hero { margin-bottom:28px; }
.page-hero h1 { font-size:clamp(1.6rem,3vw,2.4rem); margin-bottom:4px; }
.page-hero p { color:var(--muted); font-size:.95rem; }

/* ── STATS GRID ── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-bottom:28px; }
.stat-card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:20px 18px; display:flex; flex-direction:column; gap:6px; box-shadow:var(--shadow); }
.stat-icon { font-size:1.8rem; }
.stat-num { font:700 2rem var(--font-display); line-height:1; }
.stat-label { font-size:.78rem; color:var(--muted); font-weight:600; }
.stat-card.red-card { background:linear-gradient(135deg,var(--red),var(--deep)); color:#fff; border-color:transparent; }
.stat-card.red-card .stat-label { color:rgba(255,255,255,.7); }
.stat-card.green-card { background:linear-gradient(135deg,#059669,#065f46); color:#fff; border-color:transparent; }
.stat-card.green-card .stat-label { color:rgba(255,255,255,.7); }
.stat-card.blue-card { background:linear-gradient(135deg,#2563eb,#1e3a8a); color:#fff; border-color:transparent; }
.stat-card.blue-card .stat-label { color:rgba(255,255,255,.7); }

/* ── TWO-COL LAYOUT ── */
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; }

/* ── SECTION CARD ── */
.sec-card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:22px; box-shadow:var(--shadow); }
.sec-title { font:700 1rem var(--font-display); margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.sec-title i { color:var(--red); }

/* ── XP BAR ── */
.xp-section { margin-bottom:28px; }
.xp-bar-wrap { display:flex; align-items:center; gap:12px; margin-bottom:6px; }
.xp-bar-label { font:700 .82rem var(--font-display); white-space:nowrap; }
.xp-bar-outer { flex:1; height:12px; background:var(--line); border-radius:12px; overflow:hidden; }
.xp-bar-inner { height:100%; background:linear-gradient(90deg,var(--red),#ff6b6b); border-radius:12px; transition:width 1.2s ease; }
.xp-bar-pct { font-size:.75rem; color:var(--muted); white-space:nowrap; }
.xp-details { font-size:.78rem; color:var(--muted); }

/* ── STREAK CALENDAR ── */
.streak-cal { display:flex; gap:6px; }
.streak-day { display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; }
.streak-dot { width:36px; height:36px; border-radius:10px; background:var(--line); display:flex; align-items:center; justify-content:center; font:700 .7rem var(--font-display); color:var(--muted); }
.streak-dot.active { background:linear-gradient(135deg,var(--red),var(--deep)); color:#fff; }
.streak-day-label { font-size:.65rem; color:var(--muted); }

/* ── RECENT QUIZZES ── */
.quiz-row { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--line); }
.quiz-row:last-child { border-bottom:0; }
.quiz-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; }
.icon-low { background:#fee2e2; color:#991b1b; }
.icon-medium { background:#fef3c7; color:#92400e; }
.icon-high { background:#dcfce7; color:#166534; }
.quiz-info { flex:1; min-width:0; }
.quiz-title { font:600 .85rem var(--font-body); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.quiz-meta-sm { font-size:.72rem; color:var(--muted); }
.quiz-pct { font:700 1rem var(--font-display); white-space:nowrap; }

/* ── BADGES ── */
.badges-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; }
.badge-card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px; text-align:center; }
.badge-emoji { font-size:2.2rem; margin-bottom:6px; display:block; }
.badge-name { font:700 .78rem var(--font-display); margin-bottom:2px; }
.badge-desc { font-size:.68rem; color:var(--muted); }
.no-badges { color:var(--muted); font-size:.85rem; text-align:center; padding:20px; }

/* ── CATEGORY PROGRESS ── */
.cat-row { margin-bottom:14px; }
.cat-label-row { display:flex; justify-content:space-between; font-size:.82rem; margin-bottom:4px; }
.cat-name { font-weight:600; }
.cat-count { color:var(--muted); }
.cat-bar-outer { height:8px; background:var(--line); border-radius:8px; overflow:hidden; }
.cat-bar-inner { height:100%; background:linear-gradient(90deg,var(--green),#34d399); border-radius:8px; }

/* ── LINKS ── */
.cta-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:24px; }
.btn-cta { display:inline-flex; align-items:center; gap:8px; padding:12px 22px; border-radius:12px; font:700 .9rem var(--font-body); border:0; cursor:pointer; text-decoration:none; transition:all .15s; }
.btn-cta.primary { background:var(--red); color:#fff; }
.btn-cta.primary:hover { background:var(--deep); }
.btn-cta.secondary { background:transparent; border:2px solid var(--line); color:var(--ink); }
.btn-cta.secondary:hover { border-color:var(--red); color:var(--red); }

@media (max-width:768px) { .two-col { grid-template-columns:1fr; } .stats-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:480px) { .stats-grid { grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>

<header class="header">
    <a class="brand" href="user/user.html"><i class="fas fa-droplet"></i> BloodConnect</a>
    <nav>
        <a href="library.php"><i class="fas fa-book-open"></i> Library</a>
        <a href="leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="logout.php" class="btn-signout"><i class="fas fa-right-from-bracket"></i> Sign out</a>
    </nav>
</header>

<div class="page-wrap">
    <div class="page-hero">
        <h1>📚 My Learning Dashboard</h1>
        <p>Welcome, <?= htmlspecialchars($user['first_name']) ?>! Here's your learning progress.</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card red-card">
            <span class="stat-icon">📖</span>
            <span class="stat-num"><?= $booksCompleted ?></span>
            <span class="stat-label">Books Completed</span>
        </div>
        <div class="stat-card green-card">
            <span class="stat-icon">✅</span>
            <span class="stat-num"><?= $totalQuizzes ?></span>
            <span class="stat-label">Quizzes Taken</span>
        </div>
        <div class="stat-card blue-card">
            <span class="stat-icon">🏅</span>
            <span class="stat-num"><?= count($badges) ?></span>
            <span class="stat-label">Badges Earned</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🔥</span>
            <span class="stat-num"><?= (int)$profile['current_streak'] ?></span>
            <span class="stat-label">Day Streak</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">⭐</span>
            <span class="stat-num"><?= number_format($xp) ?></span>
            <span class="stat-label">Total XP</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🪙</span>
            <span class="stat-num"><?= number_format((int)$profile['points']) ?></span>
            <span class="stat-label">Points</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🏆</span>
            <span class="stat-num"><?= $bestPct ?>%</span>
            <span class="stat-label">Best Quiz Score</span>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <span class="stat-num"><?= (int)$profile['longest_streak'] ?></span>
            <span class="stat-label">Longest Streak</span>
        </div>
    </div>

    <!-- XP Progress Bar -->
    <div class="sec-card xp-section">
        <div class="sec-title"><i class="fas fa-star"></i> XP Progress — Level <?= (int)$profile['current_level'] ?> (<?= htmlspecialchars($profile['tier']) ?>)</div>
        <div class="xp-bar-wrap">
            <span class="xp-bar-label">Level <?= (int)$profile['current_level'] ?></span>
            <div class="xp-bar-outer"><div class="xp-bar-inner" style="width:<?= $xpPct ?>%"></div></div>
            <span class="xp-bar-label">Level <?= (int)$profile['current_level'] + 1 ?></span>
            <span class="xp-bar-pct"><?= $xpPct ?>%</span>
        </div>
        <div class="xp-details"><?= number_format($xp) ?> XP total · <?= number_format($xp % 500) ?> / 500 XP to next level</div>
    </div>

    <!-- Two Column: Streak + Recent Quizzes -->
    <div class="two-col">
        <div class="sec-card">
            <div class="sec-title"><i class="fas fa-fire"></i> 7-Day Activity Streak</div>
            <div class="streak-cal">
                <?php foreach ($streakDays as $day): ?>
                <div class="streak-day">
                    <div class="streak-dot <?= $day['active'] ? 'active' : '' ?>">
                        <?= $day['active'] ? '🔥' : '—' ?>
                    </div>
                    <span class="streak-day-label"><?= htmlspecialchars($day['label']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:12px; font-size:.78rem; color:var(--muted)">
                Current streak: <strong style="color:var(--ink)"><?= (int)$profile['current_streak'] ?> day<?= $profile['current_streak'] != 1 ? 's' : '' ?></strong> ·
                Longest: <strong style="color:var(--ink)"><?= (int)$profile['longest_streak'] ?> days</strong>
            </div>
        </div>

        <div class="sec-card">
            <div class="sec-title"><i class="fas fa-clipboard-check"></i> Recent Quizzes</div>
            <?php if (empty($recentAttempts)): ?>
                <p style="color:var(--muted); font-size:.85rem">No quizzes taken yet. <a href="library.php" style="color:var(--red); font-weight:700">Start reading →</a></p>
            <?php else: foreach ($recentAttempts as $attempt): ?>
                <div class="quiz-row">
                    <div class="quiz-icon icon-<?= htmlspecialchars($attempt['score_level']) ?>">
                        <?php $icons = ['low'=>'😅','medium'=>'🎯','high'=>'🏆']; echo $icons[$attempt['score_level']] ?? '📝'; ?>
                    </div>
                    <div class="quiz-info">
                        <div class="quiz-title"><?= htmlspecialchars($attempt['title']) ?></div>
                        <div class="quiz-meta-sm"><?= htmlspecialchars($attempt['category']) ?> · <?= date('M j', strtotime($attempt['completed_at'])) ?> · +<?= (int)$attempt['points_awarded'] ?> pts · +<?= (int)$attempt['xp_awarded'] ?> XP</div>
                    </div>
                    <div class="quiz-pct"><?= (int)$attempt['percentage'] ?>%</div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Category Progress -->
    <?php if (!empty($catProgress)): ?>
    <div class="sec-card" style="margin-bottom:24px">
        <div class="sec-title"><i class="fas fa-chart-bar"></i> Progress by Category</div>
        <?php foreach ($catProgress as $cat):
            $pct = $cat['total'] > 0 ? round(($cat['completed'] / $cat['total']) * 100) : 0;
        ?>
        <div class="cat-row">
            <div class="cat-label-row">
                <span class="cat-name"><?= htmlspecialchars($cat['category']) ?></span>
                <span class="cat-count"><?= (int)$cat['completed'] ?> / <?= (int)$cat['total'] ?> · <?= $pct ?>%</span>
            </div>
            <div class="cat-bar-outer">
                <div class="cat-bar-inner" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Badges -->
    <div class="sec-card" style="margin-bottom:24px">
        <div class="sec-title"><i class="fas fa-award"></i> Badges Earned (<?= count($badges) ?>)</div>
        <?php if (empty($badges)): ?>
            <div class="no-badges">
                <p>🎖️ No badges yet! Complete books and take quizzes to earn them.</p>
            </div>
        <?php else: ?>
            <div class="badges-grid">
                <?php foreach ($badges as $b): ?>
                <div class="badge-card">
                    <span class="badge-emoji"><?= htmlspecialchars($b['icon']) ?></span>
                    <div class="badge-name"><?= htmlspecialchars($b['name']) ?></div>
                    <div class="badge-desc"><?= htmlspecialchars($b['description']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- CTAs -->
    <div class="cta-row">
        <a href="library.php" class="btn-cta primary"><i class="fas fa-book-open"></i> Browse Library</a>
        <a href="leaderboard.php" class="btn-cta secondary"><i class="fas fa-trophy"></i> View Leaderboard</a>
        <a href="user/user.html" class="btn-cta secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
</div>

</body>
</html>
