<?php
/**
 * library.php — Advanced BloodConnect Learning Library
 * Full gamified book catalog with AJAX search, filters, progress tracking,
 * custom cursor, background video, XP banner, streak, badges button.
 */
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.html?error=auth');
    exit;
}

require_once __DIR__ . '/config/database.php';
$conn   = getDbConnection();
$userId = (int)$_SESSION['user_id'];


// -- Consolidated user & gamification profile query
$userStmt = $conn->prepare(
    'SELECT u.full_name,
            COALESCE(gp.points, 0) AS points,
            COALESCE(gp.tier, \'Bronze\') AS tier,
            COALESCE(gp.xp, 0) AS xp,
            COALESCE(gp.current_level, 1) AS current_level,
            COALESCE(gp.current_streak, 0) AS current_streak,
            COALESCE(gp.longest_streak, 0) AS longest_streak
     FROM users u
     LEFT JOIN gamification_profiles gp ON gp.user_id = u.id
     WHERE u.id = ?'
);
$profile = ['points'=>0,'tier'=>'Bronze','xp'=>0,'current_level'=>1,'current_streak'=>0,'longest_streak'=>0];
$user    = ['full_name' => 'Learner', 'first_name' => 'Learner'];
if ($userStmt) {
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userData = $userStmt->get_result()->fetch_assoc();
    if ($userData) {
        $user['full_name'] = $userData['full_name'] ?: 'Learner';
        $profile = [
            'points'         => (int)$userData['points'],
            'tier'           => $userData['tier'] ?: 'Bronze',
            'xp'             => (int)$userData['xp'],
            'current_level'  => (int)$userData['current_level'],
            'current_streak' => (int)$userData['current_streak'],
            'longest_streak' => (int)$userData['longest_streak']
        ];
    }
    $userStmt->close();
}
$user['first_name'] = explode(' ', trim($user['full_name']))[0] ?? 'Learner';

// -- Books with user progress (0 correlated subqueries, joined with aggregated attempts)
$booksSql = "SELECT lb.id, lb.title, lb.author, lb.category, lb.description,
                    lb.difficulty, lb.level, lb.estimated_reading_time, lb.page_count,
                    lb.low_points, lb.medium_points, lb.high_points, lb.pdf_path,
                    COALESCE(bp.progress_percentage, 0) AS progress_pct,
                    COALESCE(bp.status, 'unread') AS read_status,
                    IF(qa.book_id IS NOT NULL, 1, 0) AS quiz_done,
                    qa.best_level
             FROM library_books lb
             LEFT JOIN book_progress bp ON bp.book_id = lb.id AND bp.user_id = $userId
             LEFT JOIN (
                 SELECT book_id, MAX(score_level) AS best_level
                 FROM quiz_attempts
                 WHERE user_id = $userId
                 GROUP BY book_id
             ) qa ON qa.book_id = lb.id
             WHERE lb.published_status = 1
             ORDER BY lb.level ASC, lb.id ASC";
$booksResult = $conn->query($booksSql);
$books = $booksResult ? $booksResult->fetch_all(MYSQLI_ASSOC) : [];

// -- Category list
$categories = array_values(array_unique(array_column($books, 'category')));

// -- User badges
$badgeResult = $conn->query("SELECT b.name, b.icon FROM user_badges ub JOIN badges b ON b.badge_id = ub.badge_id WHERE ub.user_id = $userId ORDER BY ub.unlocked_at DESC LIMIT 5");
$userBadges  = $badgeResult ? $badgeResult->fetch_all(MYSQLI_ASSOC) : [];

// -- Consolidated Stats (Single query roundtrip)
$statsSql = "SELECT 
    (SELECT COUNT(*) FROM user_badges WHERE user_id = $userId) AS total_badges,
    (SELECT COUNT(*) FROM book_progress WHERE user_id = $userId AND status='completed') AS completed_books,
    (SELECT COUNT(*) FROM quiz_attempts WHERE user_id = $userId) AS total_quizzes";
$statsRow = $conn->query($statsSql)->fetch_assoc();
$totalBadges    = (int)($statsRow['total_badges'] ?? 0);
$completedBooks = (int)($statsRow['completed_books'] ?? 0);
$totalQuizzes   = (int)($statsRow['total_quizzes'] ?? 0);

$xp = (int)$profile['xp'];
$xpForNextLevel = ($profile['current_level'] + 1) * 500;
$xpPct = min(100, (int)round(($xp % 500) / 500 * 100));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BloodConnect | Learning Library</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/library.css?v=<?= filemtime(__DIR__ . '/assets/css/library.css') ?>">
<link rel="stylesheet" href="assets/css/instant_quiz.css?v=<?= filemtime(__DIR__ . '/assets/css/instant_quiz.css') ?>">
</head>
<body>

<!-- Custom Cursor -->
<div class="cursor-dot" id="cursorDot"></div>
<div class="cursor-ring" id="cursorRing"></div>

<!-- Background Video Layer -->
<div class="bg-video-wrap" id="bgVideoWrap">
    <video class="bg-video" autoplay muted loop playsinline id="bgVideo">
        <source src="assets/videos/library_bg.mp4" type="video/mp4">
    </video>
    <div class="bg-overlay"></div>
</div>

<!-- Header -->
<header class="lib-header">
    <a class="brand" href="user/user.html">
        <i class="fas fa-droplet"></i>
        <span>BloodConnect</span>
    </a>
    <nav class="lib-nav">
        <a href="learning_dashboard.php"><i class="fas fa-chart-line"></i><span>My Progress</span></a>
        <a href="leaderboard.php"><i class="fas fa-trophy"></i><span>Leaderboard</span></a>
        <a href="logout.php" class="btn-signout"><i class="fas fa-right-from-bracket"></i><span>Sign out</span></a>
    </nav>
    <button class="btn-hamburger" id="hamburgerBtn" aria-label="Open menu"><i class="fas fa-bars"></i></button>
</header>

<!-- XP Banner -->
<div class="xp-banner">
    <div class="xp-inner">
        <div class="xp-left">
            <span class="xp-greeting">Welcome back, <strong><?= htmlspecialchars($user['first_name']) ?>!</strong></span>
            <div class="xp-stats">
                <span class="stat-pill tier-<?= strtolower(str_replace(' ', '_', $profile['tier'])) ?>">
                    <i class="fas fa-medal"></i> <?= htmlspecialchars($profile['tier']) ?>
                </span>
                <span class="stat-pill streak">
                    <i class="fas fa-fire"></i> <?= (int)$profile['current_streak'] ?> day streak
                </span>
                <span class="stat-pill xp-pill">
                    <i class="fas fa-star"></i> <?= number_format($xp) ?> XP
                </span>
                <?php if ($totalBadges > 0): ?>
                <span class="stat-pill badges-pill" id="badgesToggle">
                    <i class="fas fa-award"></i> <?= $totalBadges ?> badge<?= $totalBadges !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="xp-right">
            <div class="xp-bar-wrap">
                <span class="xp-label">Level <?= (int)$profile['current_level'] ?></span>
                <div class="xp-bar"><div class="xp-fill" style="width:<?= $xpPct ?>%"></div></div>
                <span class="xp-label-right"><?= $xpPct ?>%</span>
            </div>
            <div class="quick-stats">
                <span><i class="fas fa-book-open"></i> <?= $completedBooks ?> completed</span>
                <span><i class="fas fa-clipboard-check"></i> <?= $totalQuizzes ?> quizzes</span>
                <span><i class="fas fa-coins"></i> <?= number_format((int)$profile['points']) ?> pts</span>
            </div>
        </div>
    </div>

    <!-- Badges flyout -->
    <?php if (!empty($userBadges)): ?>
    <div class="badges-flyout" id="badgesFlyout" hidden>
        <p class="flyout-title">Recent Badges</p>
        <div class="badges-list">
            <?php foreach ($userBadges as $b): ?>
            <span class="badge-item" title="<?= htmlspecialchars($b['name']) ?>"><?= htmlspecialchars($b['icon']) ?></span>
            <?php endforeach; ?>
            <?php if ($totalBadges > 5): ?>
            <a href="learning_dashboard.php" class="badge-more">+<?= $totalBadges - 5 ?> more</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Hero Section -->
<section class="lib-hero">
    <div class="hero-inner">
        <p class="eyebrow"><i class="fas fa-book-open"></i> Knowledge that saves lives</p>
        <h1>BloodConnect <span class="accent">Library</span></h1>
        <p class="hero-sub">Read books, take quizzes, earn XP and badges. <?= count($books) ?>+ books available.</p>
    </div>
</section>

<!-- Search & Filter Bar -->
<div class="lib-controls">
    <div class="search-wrap">
        <i class="fas fa-search search-icon"></i>
        <input type="search" id="bookSearch" placeholder="Search books, topics, authors…" class="search-input" autocomplete="off">
        <button class="btn-clear-search" id="clearSearch" hidden><i class="fas fa-xmark"></i></button>
    </div>
    <div class="filter-wrap">
        <button class="filter-btn active" data-filter="all" type="button">All Books</button>
        <?php foreach ($categories as $cat): ?>
        <button class="filter-btn" data-filter="<?= htmlspecialchars($cat) ?>" type="button"><?= htmlspecialchars($cat) ?></button>
        <?php endforeach; ?>
    </div>
    <div class="sort-wrap">
        <label for="sortSelect"><i class="fas fa-arrow-up-wide-short"></i></label>
        <select id="sortSelect" class="sort-select">
            <option value="level">By Level</option>
            <option value="title">A–Z Title</option>
            <option value="progress">By Progress</option>
            <option value="difficulty">By Difficulty</option>
        </select>
        <div class="difficulty-filters">
            <button class="diff-btn active" data-diff="all" type="button">All</button>
            <button class="diff-btn" data-diff="Beginner" type="button">Beginner</button>
            <button class="diff-btn" data-diff="Intermediate" type="button">Intermediate</button>
            <button class="diff-btn" data-diff="Advanced" type="button">Advanced</button>
        </div>
    </div>
</div>

<!-- Book Grid -->
<main class="lib-main">
    <div class="books-count" id="booksCount">Showing <?= count($books) ?> books</div>
    <div class="book-grid" id="bookGrid">
        <?php foreach ($books as $book):
            $pct      = (int)$book['progress_pct'];
            $status   = $book['read_status'];
            $quizDone = (bool)$book['quiz_done'];
            $bestLevel = $book['best_level'];
            $diff     = strtolower($book['difficulty'] ?? 'beginner');
            $catSlug  = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $book['category']));
            
            // Card state class
            $cardState = 'unread';
            if ($status === 'completed' && $quizDone) $cardState = 'quiz-done';
            elseif ($status === 'completed') $cardState = 'completed';
            elseif ($pct > 0) $cardState = 'reading';
            
            // Cover color by category
            $colors = [
                'Programming'   => '#2563eb,#1e40af',
                'Web Dev'       => '#7c3aed,#4c1d95',
                'AI'            => '#059669,#064e3b',
                'Cyber Security'=> '#dc2626,#7f1d1d',
                'Hematology'    => '#dc2626,#991b1b',
                'Tamil resources'=> '#b45309,#78350f',
                'Nutrition'     => '#0891b2,#0e7490',
            ];
            $grad = $colors[$book['category']] ?? '#d62839,#8f1220';
            $initial = mb_substr($book['title'], 0, 1);
        ?>
        <article class="book-card state-<?= $cardState ?>"
                 data-category="<?= htmlspecialchars($book['category']) ?>"
                 data-title="<?= htmlspecialchars(strtolower($book['title'])) ?>"
                 data-author="<?= htmlspecialchars(strtolower($book['author'])) ?>"
                 data-difficulty="<?= htmlspecialchars($book['difficulty']) ?>"
                 data-level="<?= (int)$book['level'] ?>"
                 data-progress="<?= $pct ?>">

            <!-- Cover -->
            <div class="book-cover" style="background: linear-gradient(135deg, <?= $grad ?>)">
                <span class="cover-initial"><?= htmlspecialchars($initial) ?></span>
                <div class="cover-badges">
                    <?php if ($cardState === 'quiz-done'): ?>
                        <span class="cover-badge badge-done"><i class="fas fa-check-double"></i></span>
                    <?php elseif ($cardState === 'completed'): ?>
                        <span class="cover-badge badge-read"><i class="fas fa-check"></i></span>
                    <?php elseif ($cardState === 'reading'): ?>
                        <span class="cover-badge badge-reading"><i class="fas fa-book-open"></i></span>
                    <?php endif; ?>
                    <span class="cover-badge badge-level">Lv<?= (int)$book['level'] ?></span>
                </div>
                <?php if ($pct > 0): ?>
                <div class="cover-progress-bar"><div style="width:<?= $pct ?>%"></div></div>
                <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="book-body">
                <div class="book-meta">
                    <span class="book-cat"><?= htmlspecialchars($book['category']) ?></span>
                    <span class="book-diff diff-<?= $diff ?>"><?= htmlspecialchars($book['difficulty']) ?></span>
                </div>
                <h2 class="book-title"><?= htmlspecialchars($book['title']) ?></h2>
                <p class="book-author"><i class="fas fa-user-pen"></i> <?= htmlspecialchars($book['author']) ?></p>
                <p class="book-desc"><?= htmlspecialchars(mb_substr($book['description'], 0, 100)) ?>…</p>

                <div class="book-footer">
                    <div class="book-info">
                        <?php if ($book['estimated_reading_time']): ?>
                        <span><i class="fas fa-clock"></i> <?= htmlspecialchars($book['estimated_reading_time']) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-star"></i> <?= (int)$book['high_points'] ?> pts</span>
                        <?php if ($pct > 0): ?>
                        <span class="progress-text"><i class="fas fa-circle-half-stroke"></i> <?= $pct ?>%</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($quizDone && $bestLevel): ?>
                    <span class="quiz-badge level-<?= $bestLevel ?>"><i class="fas fa-award"></i> <?= ucfirst($bestLevel) ?></span>
                    <?php endif; ?>
                    <div class="book-actions-row">
                        <?php if ($cardState === 'quiz-done' || $cardState === 'completed'): ?>
                        <a href="reader.php?book_id=<?= (int)$book['id'] ?>" class="btn-reread" title="Re-read Book" aria-label="Re-read Book">
                            <i class="fas fa-book-open"></i>
                        </a>
                        <?php endif; ?>
                        <button class="btn-read"
                                data-action="<?= ($cardState === 'quiz-done' || $cardState === 'completed') ? 'quiz' : 'read' ?>"
                                data-book-id="<?= (int)$book['id'] ?>"
                                data-book-title="<?= htmlspecialchars($book['title'], ENT_QUOTES) ?>"
                                data-page-count="<?= (int)($book['page_count'] ?: 10) ?>"
                                data-saved-page="<?= ($pct > 0 && $status !== 'completed') ? max(1, (int)round($pct / 100 * ($book['page_count'] ?: 10))) : 0 ?>">
                            <?php if ($cardState === 'quiz-done'): ?>
                                <i class="fas fa-rotate-right"></i> Retake Quiz
                            <?php elseif ($cardState === 'completed'): ?>
                                <i class="fas fa-clipboard-question"></i> Take Quiz
                            <?php elseif ($pct > 0): ?>
                                <i class="fas fa-play"></i> Continue (<?= $pct ?>%)
                            <?php else: ?>
                                <i class="fas fa-book-open"></i> Start Reading
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <p class="no-books-msg" id="noBooksMsg" hidden>
        <i class="fas fa-book-skull"></i><br>No books match your search.<br>
        <button type="button" onclick="clearAllFilters()">Clear filters</button>
    </p>
</main>

<!-- Toast -->
<div class="toast-wrap" id="toastWrap" aria-live="polite"></div>

<!-- Instant Quiz Modal -->
<?php include __DIR__ . '/includes/quiz_modal.php'; ?>

<!-- Pass PHP data to JS -->
<script>
window.LIB_CONFIG = {
    userId: <?= $userId ?>,
    totalBooks: <?= count($books) ?>
};
</script>
<script src="assets/js/instant_quiz.js?v=<?= filemtime(__DIR__ . '/assets/js/instant_quiz.js') ?>"></script>
<script src="assets/js/library.js?v=<?= filemtime(__DIR__ . '/assets/js/library.js') ?>"></script>
</body>
</html>