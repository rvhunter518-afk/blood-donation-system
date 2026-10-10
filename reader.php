<?php
/**
 * reader.php — Full PDF.js Book Reader
 * Loads PDF from api/serve_pdf.php, tracks progress, triggers quiz on completion.
 */
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.html?error=auth');
    exit;
}
require_once __DIR__ . '/includes/back_url.php';

require_once __DIR__ . '/config/database.php';
$conn   = getDbConnection();
$userId = (int)$_SESSION['user_id'];
$bookId = (int)($_GET['book_id'] ?? 0);


if ($bookId < 1) {
    header('Location: library.php');
    exit;
}

// Consolidated fast query for book metadata, user progress, and quiz status in 1 round trip
$stmt = $conn->prepare('
    SELECT lb.id, lb.title, lb.author, lb.category, lb.page_count, lb.difficulty,
           bp.current_page, bp.status AS progress_status,
           EXISTS(SELECT 1 FROM quiz_attempts qa WHERE qa.user_id = ? AND qa.book_id = lb.id) AS quiz_done
    FROM library_books lb
    LEFT JOIN book_progress bp ON bp.book_id = lb.id AND bp.user_id = ?
    WHERE lb.id = ? AND lb.published_status = 1
    LIMIT 1
');
$stmt->bind_param('iii', $userId, $userId, $bookId);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    header('Location: library.php');
    exit;
}

$savedPage = (int)($book['current_page'] ?? 1);
$alreadyCompleted = ($book['progress_status'] ?? '') === 'completed';
$quizDone = (bool)($book['quiz_done'] ?? false);
$pageCount = (int)($book['page_count'] ?: 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($book['title']) ?> — BloodConnect Reader</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/library.css?v=<?= filemtime(__DIR__ . '/assets/css/library.css') ?>">
<link rel="stylesheet" href="assets/css/instant_quiz.css?v=<?= filemtime(__DIR__ . '/assets/css/instant_quiz.css') ?>">
<style>
:root {
    --red: #d62839; --deep: #8f1220; --ink: #171b22; --paper: #f8f9fa;
    --muted: #66707c; --line: #e5e7eb; --green: #059669;
    --shadow: 0 4px 24px rgba(0,0,0,0.12);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--ink); color: #fff; height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }

/* ── READER TOOLBAR ── */
.reader-toolbar {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 16px; background: #1e2530;
    border-bottom: 1px solid #ffffff18; flex-shrink: 0;
    flex-wrap: wrap;
}
.toolbar-title { font-family: 'Space Grotesk', sans-serif; font-size: .9rem; font-weight: 600; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.toolbar-title span { color: #94a3b8; font-size: .75rem; font-weight: 400; }
.tb-btn {
    border: 0; border-radius: 8px; background: #ffffff15; color: #fff;
    width: 36px; height: 36px; cursor: pointer; display: grid; place-items: center;
    font-size: .95rem; transition: background .15s;
}
.tb-btn:hover { background: #ffffff30; }
.tb-btn:disabled { opacity: .35; cursor: default; }
.page-control { display: flex; align-items: center; gap: 6px; font-size: .85rem; }
.page-input {
    width: 54px; background: #ffffff15; border: 1px solid #ffffff20;
    color: #fff; border-radius: 8px; padding: 5px 8px; text-align: center;
    font: inherit; font-size: .85rem;
}
.page-input:focus { outline: none; border-color: var(--red); }
.zoom-control { display: flex; align-items: center; gap: 6px; font-size: .8rem; color: #94a3b8; }
.progress-mini { font-size: .75rem; color: #94a3b8; white-space: nowrap; }

/* ── CANVAS AREA ── */
.reader-body { flex: 1; overflow-y: auto; background: #262b35; display: flex; flex-direction: column; align-items: center; padding: 16px 16px 24px; gap: 12px; }
#pdfCanvas { display: block; border-radius: 4px; box-shadow: var(--shadow); max-width: 100%; }
.page-loading { display: flex; align-items: center; justify-content: center; gap: 10px; color: #94a3b8; padding: 80px; }
.page-loading i { font-size: 2rem; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.error-state { text-align: center; padding: 60px; color: #f87171; }
.error-state i { font-size: 3rem; margin-bottom: 16px; display: block; }
.error-state a { color: var(--red); font-weight: 700; }

/* ── COMPLETION PANEL ── */
.completion-panel {
    position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
    background: #1e2530; border: 1px solid #ffffff20; border-radius: 16px;
    padding: 16px 24px; display: flex; align-items: center; gap: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,.5); z-index: 50; max-width: 560px; width: calc(100% - 32px);
    animation: slideUp .4s ease;
}
@keyframes slideUp { from { transform: translateX(-50%) translateY(80px); opacity: 0; } }
.comp-icon { font-size: 2rem; flex-shrink: 0; }
.comp-text { flex: 1; }
.comp-text h3 { font-family: 'Space Grotesk', sans-serif; font-size: 1rem; margin-bottom: 2px; }
.comp-text p { color: #94a3b8; font-size: .8rem; }
.btn-quiz {
    background: var(--red); color: #fff; border: 0; border-radius: 10px;
    padding: 10px 18px; font: 700 .85rem 'DM Sans'; cursor: pointer; white-space: nowrap;
    transition: background .15s;
}
.btn-quiz:hover { background: var(--deep); }
.btn-quiz-skip { background: transparent; border: 1px solid #ffffff30; color: #94a3b8; border-radius: 10px; padding: 10px 14px; font: .85rem 'DM Sans'; cursor: pointer; }
.btn-quiz-skip:hover { border-color: #fff; color: #fff; }

/* ── RESUME MODAL ── */
.resume-overlay { position: fixed; inset: 0; background: #000a; backdrop-filter: blur(6px); z-index: 60; display: flex; align-items: center; justify-content: center; }
.resume-card { background: #1e2530; border-radius: 18px; padding: 32px; max-width: 400px; width: calc(100% - 32px); text-align: center; border: 1px solid #ffffff18; }
.resume-card h2 { font-family: 'Space Grotesk', sans-serif; font-size: 1.3rem; margin-bottom: 8px; }
.resume-card p { color: #94a3b8; margin-bottom: 24px; font-size: .9rem; }
.resume-btns { display: flex; gap: 10px; justify-content: center; }
.btn-primary { background: var(--red); color: #fff; border: 0; border-radius: 10px; padding: 12px 24px; font: 700 .9rem 'DM Sans'; cursor: pointer; }
.btn-primary:hover { background: var(--deep); }
.btn-secondary { background: #ffffff15; color: #fff; border: 1px solid #ffffff20; border-radius: 10px; padding: 12px 24px; font: .9rem 'DM Sans'; cursor: pointer; }
.btn-secondary:hover { background: #ffffff25; }

/* ── BACK BAR ── */
.back-bar { display: flex; align-items: center; gap: 8px; padding: 0 16px 0; }
.back-bar a { color: #94a3b8; text-decoration: none; font-size: .8rem; display: flex; align-items: center; gap: 6px; }
.back-bar a:hover { color: #fff; }

@media (max-width: 600px) {
    .toolbar-title span { display: none; }
    .zoom-control { display: none; }
    .completion-panel { flex-direction: column; text-align: center; }
}
</style>
</head>
<body>

<!-- Reader Toolbar -->
<div class="reader-toolbar">
    <a href="<?= htmlspecialchars($backUrl) ?>" class="tb-btn" title="Back to <?= htmlspecialchars($backLabel) ?>" style="gap:5px;display:flex;align-items:center;font-size:.78rem;">
        <i class="fas fa-house"></i>
    </a>
    <a href="library.php" class="tb-btn" title="Back to Library" style="gap:5px;display:flex;align-items:center;font-size:.78rem;">
        <i class="fas fa-arrow-left"></i> Library
    </a>
    <div class="toolbar-title">
        <?= htmlspecialchars($book['title']) ?>
        <span> — <?= htmlspecialchars($book['author']) ?></span>
    </div>
    <div class="page-control">
        <button class="tb-btn" id="prevPage" title="Previous page" disabled><i class="fas fa-chevron-left"></i></button>
        <input type="number" id="pageInput" class="page-input" value="1" min="1" max="<?= $pageCount ?>">
        <span style="color:#94a3b8; font-size:.85rem">/ <span id="totalPagesDisplay"><?= $pageCount ?></span></span>
        <button class="tb-btn" id="nextPage" title="Next page"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div class="zoom-control">
        <button class="tb-btn" id="zoomOut" title="Zoom out"><i class="fas fa-magnifying-glass-minus"></i></button>
        <span id="zoomDisplay">100%</span>
        <button class="tb-btn" id="zoomIn" title="Zoom in"><i class="fas fa-magnifying-glass-plus"></i></button>
    </div>
    <span class="progress-mini" id="progressMini">0% read</span>
    <button class="tb-btn" id="fullscreenBtn" title="Fullscreen"><i class="fas fa-expand"></i></button>
    <?php if (!$quizDone): ?>
    <a href="quiz.php?book_id=<?= $bookId ?>" class="tb-btn" style="background:var(--red); width:auto; padding:0 12px; font-size:.75rem; font-weight:700; gap:6px; text-decoration:none; color:#fff;" title="Take Quiz">
        <i class="fas fa-clipboard-question"></i> Quiz
    </a>
    <?php else: ?>
    <a href="quiz.php?book_id=<?= $bookId ?>" class="tb-btn" style="background:#059669; width:auto; padding:0 12px; font-size:.75rem; font-weight:700; gap:6px; text-decoration:none; color:#fff;" title="Retake Quiz">
        <i class="fas fa-rotate-right"></i> Retake
    </a>
    <?php endif; ?>
</div>

<!-- Canvas Area -->
<div class="reader-body" id="readerBody">
    <div class="page-loading" id="pageLoading">
        <i class="fas fa-spinner"></i>
        <span>Loading PDF…</span>
    </div>
    <canvas id="pdfCanvas" hidden></canvas>
    <div class="error-state" id="errorState" hidden>
        <i class="fas fa-file-circle-exclamation"></i>
        <h3>Could not load the PDF</h3>
        <p>The file may be missing or unavailable.</p>
        <p><a href="library.php">← Back to Library</a></p>
    </div>
</div>



<!-- Config -->
<script>
window.READER_CONFIG = {
    bookId:        <?= $bookId ?>,
    pdfUrl:        'api/serve_pdf.php?book_id=<?= $bookId ?>',
    savedPage:     <?= $savedPage ?>,
    pageCount:     <?= $pageCount ?>,
    alreadyCompleted: <?= $alreadyCompleted ? 'true' : 'false' ?>,
    quizDone:      <?= $quizDone ? 'true' : 'false' ?>,
    quizUrl:       'quiz.php?book_id=<?= $bookId ?>'
};
</script>

<!-- PDF.js CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
if (window.pdfjsLib) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}
</script>
<?php include __DIR__ . '/includes/quiz_modal.php'; ?>
<script src="assets/js/instant_quiz.js?v=<?= filemtime(__DIR__ . '/assets/js/instant_quiz.js') ?>"></script>
<script src="assets/js/pdf_reader.js?v=<?= filemtime(__DIR__ . '/assets/js/pdf_reader.js') ?>"></script>
</body>
</html>

