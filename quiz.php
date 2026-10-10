<?php
/**
 * quiz.php — Full Quiz Interface
 * Timer, question navigation, answer submission, Low/Med/High result screen with confetti.
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

$bookStmt = $conn->prepare('SELECT id, title, author, category, page_count, low_points, medium_points, high_points FROM library_books WHERE id = ? AND published_status = 1');
$bookStmt->bind_param('i', $bookId);
$bookStmt->execute();
$book = $bookStmt->get_result()->fetch_assoc();
$bookStmt->close();

if (!$book) {
    header('Location: library.php');
    exit;
}

// Fetch quiz questions directly during page load (eliminates secondary AJAX round-trip)
$qStmt = $conn->prepare('SELECT id, question_text, option_a, option_b, option_c, option_d FROM quiz_questions WHERE book_id = ? ORDER BY id ASC');
$qStmt->bind_param('i', $bookId);
$qStmt->execute();
$questions = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qStmt->close();
$questionCount = count($questions);

if ($questionCount === 0) {
    header('Location: reader.php?book_id=' . $bookId);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quiz — <?= htmlspecialchars($book['title']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --red:#d62839; --deep:#8f1220; --ink:#171b22; --paper:#f8f9fa;
    --muted:#66707c; --line:#e5e7eb; --green:#059669; --blue:#2563eb;
    --yellow:#d97706; --card:#fff;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--ink); color: #fff; min-height: 100dvh; }

/* ── LOADING STATE ── */
.loading-screen {
    min-height: 100dvh; display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 16px;
}
.loading-spinner { font-size: 3rem; animation: spin 1s linear infinite; color: var(--red); }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── QUIZ CONTAINER ── */
.quiz-wrap { max-width: 740px; margin: 0 auto; padding: 24px 16px 60px; }

/* ── QUIZ HEADER ── */
.quiz-header { margin-bottom: 28px; }
.quiz-book-info { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.quiz-book-info a { color: #94a3b8; text-decoration: none; font-size: .85rem; display: flex; align-items: center; gap: 6px; transition: color .15s; }
.quiz-book-info a:hover { color: #fff; }
.quiz-book-title { font-family: 'Space Grotesk', sans-serif; font-size: 1.1rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.quiz-progress-bar { height: 6px; background: #ffffff15; border-radius: 6px; overflow: hidden; margin-bottom: 12px; }
.quiz-progress-fill { height: 100%; background: linear-gradient(90deg, var(--red), #ff6b6b); border-radius: 6px; transition: width .4s ease; }
.quiz-meta { display: flex; justify-content: space-between; align-items: center; font-size: .82rem; color: #94a3b8; }
.quiz-timer { font-family: 'Space Grotesk', sans-serif; font-weight: 600; font-size: .95rem; color: #fff; }
.quiz-timer.warning { color: #f59e0b; }
.quiz-timer.danger { color: #f87171; animation: pulse 1s ease-in-out infinite; }
@keyframes pulse { 50% { opacity: .5; } }

/* ── QUESTION NAVIGATION ── */
.question-nav { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; }
.q-nav-btn {
    width: 36px; height: 36px; border-radius: 8px; border: 2px solid #ffffff20;
    background: transparent; color: #94a3b8; font: 700 .8rem 'DM Sans'; cursor: pointer;
    transition: all .15s;
}
.q-nav-btn.answered { border-color: var(--blue); background: #2563eb30; color: #93c5fd; }
.q-nav-btn.current { border-color: var(--red); background: var(--red); color: #fff; }

/* ── QUESTION CARD ── */
.question-card { background: #1e2530; border: 1px solid #ffffff15; border-radius: 20px; padding: 28px; margin-bottom: 24px; }
.q-num { color: #94a3b8; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 10px; }
.q-text { font-family: 'Space Grotesk', sans-serif; font-size: 1.15rem; line-height: 1.5; font-weight: 600; margin-bottom: 24px; }
.options-grid { display: flex; flex-direction: column; gap: 10px; }
.option-btn {
    width: 100%; text-align: left; padding: 14px 18px; border-radius: 12px;
    border: 2px solid #ffffff15; background: #ffffff08; color: #fff;
    font: 500 .95rem 'DM Sans'; cursor: pointer; transition: all .15s;
    display: flex; align-items: center; gap: 12px;
}
.option-btn:hover:not(:disabled) { border-color: #ffffff40; background: #ffffff15; transform: translateX(4px); }
.option-btn.selected { border-color: var(--blue); background: #2563eb25; }
.option-label { width: 28px; height: 28px; border-radius: 8px; background: #ffffff15; display: flex; align-items: center; justify-content: center; font: 700 .8rem 'Space Grotesk'; flex-shrink: 0; }
.option-btn.selected .option-label { background: var(--blue); }

/* ── QUIZ FOOTER ── */
.quiz-footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.btn-nav-q {
    border: 2px solid #ffffff25; background: transparent; color: #fff;
    border-radius: 12px; padding: 12px 20px; font: 700 .9rem 'DM Sans'; cursor: pointer; transition: all .15s;
    display: flex; align-items: center; gap: 8px;
}
.btn-nav-q:hover:not(:disabled) { border-color: #ffffff60; background: #ffffff15; }
.btn-nav-q:disabled { opacity: .4; cursor: default; }
.btn-submit-quiz {
    border: 0; background: var(--red); color: #fff;
    border-radius: 12px; padding: 14px 28px; font: 700 1rem 'DM Sans'; cursor: pointer;
    display: flex; align-items: center; gap: 8px; transition: background .15s;
}
.btn-submit-quiz:hover { background: var(--deep); }
.btn-submit-quiz:disabled { opacity: .6; cursor: default; }

/* ── RESULT SCREEN ── */
.result-screen { max-width: 600px; margin: 0 auto; padding: 32px 16px 60px; text-align: center; }
.result-icon { font-size: 5rem; margin-bottom: 16px; display: block; }
.result-title { font-family: 'Space Grotesk', sans-serif; font-size: 2rem; font-weight: 700; margin-bottom: 8px; }
.result-subtitle { color: #94a3b8; font-size: 1rem; margin-bottom: 28px; }
.result-score-ring { width: 160px; height: 160px; margin: 0 auto 28px; position: relative; }
.result-score-ring svg { transform: rotate(-90deg); }
.result-score-ring .score-text { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.score-percent { font-family: 'Space Grotesk', sans-serif; font-size: 2.2rem; font-weight: 700; }
.score-label { font-size: .75rem; color: #94a3b8; }
.result-stats { display: flex; justify-content: center; gap: 24px; margin-bottom: 28px; flex-wrap: wrap; }
.result-stat { background: #1e2530; border: 1px solid #ffffff15; border-radius: 14px; padding: 16px 20px; text-align: center; min-width: 110px; }
.result-stat .stat-num { font-family: 'Space Grotesk', sans-serif; font-size: 1.6rem; font-weight: 700; display: block; }
.result-stat .stat-lbl { font-size: .72rem; color: #94a3b8; }
.result-level { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; border-radius: 50px; font: 700 1rem 'DM Sans'; margin-bottom: 20px; }
.level-low { background: #7f1d1d; color: #fca5a5; }
.level-medium { background: #78350f; color: #fcd34d; }
.level-high { background: #064e3b; color: #6ee7b7; }

/* ── BADGES EARNED ── */
.badges-earned { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-bottom: 24px; }
.badge-pill { background: #1e2530; border: 1px solid #ffffff20; border-radius: 12px; padding: 10px 16px; display: flex; align-items: center; gap: 8px; font-size: .85rem; }
.badge-icon { font-size: 1.5rem; }

/* ── EXPLANATIONS ── */
.explanations { text-align: left; background: #1e2530; border-radius: 16px; padding: 20px; margin-bottom: 28px; max-height: 400px; overflow-y: auto; }
.explanations h3 { font-family: 'Space Grotesk', sans-serif; font-size: 1rem; margin-bottom: 16px; color: #94a3b8; }
.exp-item { padding: 12px 0; border-bottom: 1px solid #ffffff10; }
.exp-item:last-child { border-bottom: 0; }
.exp-q { font-size: .85rem; margin-bottom: 6px; font-weight: 500; }
.exp-result { display: flex; align-items: center; gap: 8px; font-size: .8rem; margin-bottom: 4px; }
.exp-correct { color: #6ee7b7; } .exp-wrong { color: #f87171; }
.exp-note { font-size: .78rem; color: #94a3b8; font-style: italic; }

/* ── RESULT ACTIONS ── */
.result-actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
.btn-result {
    border-radius: 12px; padding: 14px 24px; font: 700 .95rem 'DM Sans'; cursor: pointer;
    display: flex; align-items: center; gap: 8px; transition: all .15s; text-decoration: none;
}
.btn-result.primary { background: var(--red); color: #fff; border: 0; }
.btn-result.primary:hover { background: var(--deep); }
.btn-result.secondary { background: transparent; color: #fff; border: 2px solid #ffffff30; }
.btn-result.secondary:hover { border-color: #fff; }

/* ── CONFETTI CANVAS ── */
#confettiCanvas { position: fixed; inset: 0; pointer-events: none; z-index: 999; }

@media (max-width: 500px) {
    .question-card { padding: 18px; }
    .q-text { font-size: 1rem; }
    .result-stats { gap: 10px; }
    .result-stat { min-width: 90px; padding: 12px 14px; }
}
</style>
</head>
<body>

<canvas id="confettiCanvas" hidden></canvas>

<!-- Loading -->
<div class="loading-screen" id="loadingScreen" <?= !empty($questions) ? 'hidden' : '' ?>>
    <i class="fas fa-spinner loading-spinner"></i>
    <p>Loading quiz questions…</p>
</div>

<!-- Quiz UI (shown immediately if questions were passed by PHP) -->
<div class="quiz-wrap" id="quizWrap" <?= empty($questions) ? 'hidden' : '' ?>>
    <div class="quiz-header">
        <div class="quiz-book-info">
            <a href="reader.php?book_id=<?= $bookId ?>"><i class="fas fa-arrow-left"></i> Back to Book</a>
            <span class="quiz-book-title" id="quizBookTitle"><?= htmlspecialchars($book['title']) ?></span>
        </div>
        <div class="quiz-progress-bar"><div class="quiz-progress-fill" id="quizProgressFill" style="width:0%"></div></div>
        <div class="quiz-meta">
            <span id="questionCounter">Question 1 of <?= $questionCount ?></span>
            <span class="quiz-timer" id="quizTimer"><i class="fas fa-clock"></i> 10:00</span>
        </div>
    </div>

    <!-- Question Nav -->
    <div class="question-nav" id="questionNav"></div>

    <!-- Question Card -->
    <div class="question-card">
        <div class="q-num" id="questionNum">Question 1</div>
        <div class="q-text" id="questionText">Loading…</div>
        <div class="options-grid" id="optionsGrid"></div>
    </div>

    <!-- Footer -->
    <div class="quiz-footer">
        <button class="btn-nav-q" id="prevBtn" disabled><i class="fas fa-chevron-left"></i> Previous</button>
        <button class="btn-nav-q" id="nextBtn"><i class="fas fa-chevron-right"></i> Next</button>
        <button class="btn-submit-quiz" id="submitBtn" hidden><i class="fas fa-paper-plane"></i> Submit Quiz</button>
    </div>
</div>

<!-- Result Screen (hidden until submitted) -->
<div class="result-screen" id="resultScreen" hidden>
    <span class="result-icon" id="resultIcon">🎯</span>
    <h1 class="result-title" id="resultTitle">Quiz Complete!</h1>
    <p class="result-subtitle" id="resultSubtitle">Great effort!</p>

    <div class="result-level" id="resultLevel"></div>

    <div class="result-score-ring">
        <svg width="160" height="160" viewBox="0 0 160 160">
            <circle cx="80" cy="80" r="70" fill="none" stroke="#ffffff10" stroke-width="12"/>
            <circle cx="80" cy="80" r="70" fill="none" id="scoreCircle"
                    stroke="var(--red)" stroke-width="12" stroke-linecap="round"
                    stroke-dasharray="439.8" stroke-dashoffset="439.8"/>
        </svg>
        <div class="score-text">
            <span class="score-percent" id="scorePercent">0%</span>
            <span class="score-label" id="scoreCorrect">0 / 0</span>
        </div>
    </div>

    <div class="result-stats">
        <div class="result-stat"><span class="stat-num" id="statPoints">+0</span><span class="stat-lbl">Points</span></div>
        <div class="result-stat"><span class="stat-num" id="statXP">+0</span><span class="stat-lbl">XP</span></div>
        <div class="result-stat"><span class="stat-num" id="statLevel">—</span><span class="stat-lbl">Score Level</span></div>
    </div>

    <div class="badges-earned" id="badgesEarned"></div>

    <div class="explanations" id="explanations"></div>

    <div class="result-actions">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-result secondary"><i class="fas <?= htmlspecialchars($backIcon) ?>"></i> <?= htmlspecialchars($backLabel) ?></a>
        <a href="library.php" class="btn-result secondary"><i class="fas fa-book-open"></i> Library</a>
        <button type="button" class="btn-result secondary" id="retakeBtn"><i class="fas fa-rotate-right"></i> Retake</button>
        <a href="learning_dashboard.php" class="btn-result primary"><i class="fas fa-chart-line"></i> My Progress</a>
    </div>
</div>

<script>
window.QUIZ_CONFIG = {
    bookId: <?= $bookId ?>,
    bookTitle: <?= json_encode($book['title']) ?>,
    timerSeconds: <?= $questionCount * 60 /* 1 min per question */ ?>
};
window.INITIAL_QUIZ_DATA = <?= json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

(function() {
    const cfg      = window.QUIZ_CONFIG;
    const wrap     = document.getElementById('quizWrap');
    const loading  = document.getElementById('loadingScreen');
    const resultSc = document.getElementById('resultScreen');

    let questions  = [];
    let answers    = {};   // {question_id: 'A'|'B'|'C'|'D'}
    let current    = 0;
    let timerLeft  = cfg.timerSeconds;
    let timerInt   = null;
    let submitted  = false;

    // ── Load questions ──
    async function loadQuiz() {
        try {
            const res = await fetch('api/get_book_quiz.php?book_id=' + cfg.bookId);
            const data = await res.json();
            if (!data.success || !data.questions.length) throw new Error(data.error || 'No questions');
            questions = data.questions;
            loading.hidden = true;
            wrap.hidden    = false;
            buildNav();
            renderQuestion(0);
            startTimer();
        } catch(e) {
            loading.innerHTML = `<i class="fas fa-circle-exclamation" style="font-size:3rem;color:#f87171;"></i><p style="color:#f87171">Failed to load quiz: ${e.message}</p><p><a href="library.php" style="color:var(--red)">← Back to Library</a></p>`;
        }
    }

    // ── Build nav pills ──
    function buildNav() {
        const nav = document.getElementById('questionNav');
        nav.innerHTML = '';
        questions.forEach((_, i) => {
            const btn = document.createElement('button');
            btn.className = 'q-nav-btn' + (i === 0 ? ' current' : '');
            btn.textContent = i + 1;
            btn.type = 'button';
            btn.onclick = () => goTo(i);
            nav.appendChild(btn);
        });
    }

    function updateNav() {
        document.querySelectorAll('.q-nav-btn').forEach((btn, i) => {
            btn.className = 'q-nav-btn' + (answers[questions[i].id] ? ' answered' : '') + (i === current ? ' current' : '');
        });
    }

    // ── Render question ──
    function renderQuestion(idx) {
        current = idx;
        const q = questions[idx];
        document.getElementById('questionNum').textContent = `Question ${idx + 1}`;
        document.getElementById('questionText').textContent = q.question_text;
        document.getElementById('questionCounter').textContent = `Question ${idx + 1} of ${questions.length}`;
        document.getElementById('quizProgressFill').style.width = ((idx / questions.length) * 100) + '%';

        const opts = document.getElementById('optionsGrid');
        opts.innerHTML = '';
        [['A', q.option_a], ['B', q.option_b], ['C', q.option_c], ['D', q.option_d]].forEach(([letter, text]) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'option-btn' + (answers[q.id] === letter ? ' selected' : '');
            btn.innerHTML = `<span class="option-label">${letter}</span>${text}`;
            btn.onclick = () => selectAnswer(letter);
            opts.appendChild(btn);
        });

        document.getElementById('prevBtn').disabled = (idx === 0);
        document.getElementById('nextBtn').hidden   = (idx === questions.length - 1);
        document.getElementById('submitBtn').hidden = (idx !== questions.length - 1);
        updateNav();
    }

    function selectAnswer(letter) {
        answers[questions[current].id] = letter;
        renderQuestion(current);
    }

    function goTo(idx) { renderQuestion(idx); }

    document.getElementById('prevBtn').onclick = () => { if (current > 0) goTo(current - 1); };
    document.getElementById('nextBtn').onclick = () => { if (current < questions.length - 1) goTo(current + 1); };

    // ── Timer ──
    function startTimer() {
        const el = document.getElementById('quizTimer');
        timerInt = setInterval(() => {
            timerLeft--;
            const m = String(Math.floor(timerLeft / 60)).padStart(2, '0');
            const s = String(timerLeft % 60).padStart(2, '0');
            el.innerHTML = `<i class="fas fa-clock"></i> ${m}:${s}`;
            el.className = 'quiz-timer' + (timerLeft <= 60 ? ' danger' : timerLeft <= 180 ? ' warning' : '');
            if (timerLeft <= 0) { clearInterval(timerInt); submitQuiz(); }
        }, 1000);
    }

    // ── Submit ──
    document.getElementById('submitBtn').onclick = () => {
        const unanswered = questions.filter(q => !answers[q.id]).length;
        if (unanswered > 0) {
            const msg = `You have ${unanswered} unanswered question${unanswered > 1 ? 's' : ''}. Submit anyway?`;
            if (!confirm(msg)) return;
        }
        submitQuiz();
    };

    document.getElementById('retakeBtn').onclick = () => {
        if (timerInt) clearInterval(timerInt);
        answers   = {};
        current   = 0;
        submitted = false;
        timerLeft = cfg.timerSeconds;
        resultSc.hidden = true;
        wrap.hidden     = false;
        buildNav();
        renderQuestion(0);
        startTimer();
    };

    async function submitQuiz() {
        if (submitted) return;
        submitted = true;
        clearInterval(timerInt);
        wrap.hidden = true;
        loading.hidden = false;
        loading.innerHTML = '<i class="fas fa-spinner loading-spinner"></i><p>Grading your answers…</p>';

        const payload = {
            book_id: cfg.bookId,
            answers: questions.map(q => ({ question_id: q.id, selected_answer: answers[q.id] || '' }))
        };

        try {
            const res  = await fetch('api/submit_quiz.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Submission failed');
            loading.hidden = true;
            showResult(data);
        } catch(e) {
            loading.innerHTML = `<i class="fas fa-circle-exclamation" style="font-size:3rem;color:#f87171;"></i><p style="color:#f87171">Error: ${e.message}</p><p><a href="library.php" style="color:var(--red)">← Library</a></p>`;
        }
    }

    // ── Show result ──
    function showResult(data) {
        resultSc.hidden = false;
        const pct = data.percentage;
        const level = data.score_level;

        // Icon & message
        const icons = { low: '😅', medium: '🎯', high: '🏆' };
        const titles = { low: 'Keep Learning!', medium: 'Good Job!', high: data.is_perfect ? '🌟 Perfect Score!' : 'Excellent!' };
        const subs = { low: `You scored ${pct}%. Review the material and try again!`, medium: `You scored ${pct}%. Solid understanding!`, high: `You scored ${pct}%. Outstanding performance!` };

        document.getElementById('resultIcon').textContent = icons[level];
        document.getElementById('resultTitle').textContent = titles[level];
        document.getElementById('resultSubtitle').textContent = subs[level];
        document.getElementById('scorePercent').textContent = pct + '%';
        document.getElementById('scoreCorrect').textContent = `${data.score} / ${data.total}`;
        document.getElementById('statPoints').textContent = '+' + data.points_awarded;
        document.getElementById('statXP').textContent = '+' + data.xp_awarded;
        document.getElementById('statLevel').textContent = level.charAt(0).toUpperCase() + level.slice(1);
        document.getElementById('quizProgressFill').style.width = '100%';

        // Level badge
        const lvlEl = document.getElementById('resultLevel');
        lvlEl.className = 'result-level level-' + level;
        const lvlIcons = { low: '📚', medium: '⚡', high: '🔥' };
        lvlEl.textContent = lvlIcons[level] + ' ' + level.charAt(0).toUpperCase() + level.slice(1) + ' Score';

        // Animated circle
        const circle = document.getElementById('scoreCircle');
        const strokeColors = { low: '#ef4444', medium: '#f59e0b', high: '#10b981' };
        circle.style.stroke = strokeColors[level];
        setTimeout(() => {
            const offset = 439.8 * (1 - pct / 100);
            circle.style.transition = 'stroke-dashoffset 1.2s ease';
            circle.style.strokeDashoffset = offset;
        }, 100);

        // Badges
        const badgesEl = document.getElementById('badgesEarned');
        if (data.badges_earned && data.badges_earned.length) {
            badgesEl.innerHTML = '<h3 style="width:100%; font-family:Space Grotesk; font-size:1rem; margin-bottom:4px; color:#fbbf24">🏅 New Badges Earned!</h3>' +
                data.badges_earned.map(b => `<div class="badge-pill"><span class="badge-icon">${b.icon}</span><div><div style="font-weight:700">${b.name}</div><div style="font-size:.75rem;color:#94a3b8">${b.description}</div></div></div>`).join('');
        }

        // Explanations
        const expEl = document.getElementById('explanations');
        if (data.explanations && data.explanations.length) {
            expEl.innerHTML = '<h3>Answer Review</h3>' + data.explanations.map(e => `
                <div class="exp-item">
                    <div class="exp-q">${e.question_text}</div>
                    <div class="exp-result ${e.is_correct ? 'exp-correct' : 'exp-wrong'}">
                        <i class="fas ${e.is_correct ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                        ${e.is_correct ? 'Correct' : `Your answer: ${e.selected || '—'} · Correct: ${e.correct}`}
                    </div>
                    ${e.explanation ? `<div class="exp-note"><i class="fas fa-lightbulb"></i> ${e.explanation}</div>` : ''}
                </div>`).join('');
        }

        // Confetti for high/perfect
        if (level === 'high') launchConfetti();
    }

    // ── Confetti ──
    function launchConfetti() {
        const canvas = document.getElementById('confettiCanvas');
        canvas.hidden = false;
        canvas.width  = window.innerWidth;
        canvas.height = window.innerHeight;
        const ctx = canvas.getContext('2d');
        const pieces = Array.from({ length: 120 }, () => ({
            x: Math.random() * canvas.width, y: Math.random() * -canvas.height,
            w: 8 + Math.random() * 8, h: 14 + Math.random() * 10,
            color: ['#d62839','#fbbf24','#10b981','#3b82f6','#f59e0b','#8b5cf6'][Math.floor(Math.random()*6)],
            rotation: Math.random() * Math.PI * 2, rotSpeed: (Math.random() - 0.5) * 0.2,
            vx: (Math.random() - 0.5) * 4, vy: 2 + Math.random() * 4
        }));
        let frame;
        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let alive = false;
            pieces.forEach(p => {
                if (p.y < canvas.height + 20) { alive = true; p.x += p.vx; p.y += p.vy; p.vy += 0.08; p.rotation += p.rotSpeed; }
                ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rotation);
                ctx.fillStyle = p.color; ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h); ctx.restore();
            });
            if (alive) frame = requestAnimationFrame(draw);
            else { canvas.hidden = true; cancelAnimationFrame(frame); }
        }
        draw();
        setTimeout(() => { canvas.hidden = true; cancelAnimationFrame(frame); }, 5000);
    }

    // ── Instant Initialization from embedded data ──
    if (window.INITIAL_QUIZ_DATA && Array.isArray(window.INITIAL_QUIZ_DATA) && window.INITIAL_QUIZ_DATA.length > 0) {
        questions = window.INITIAL_QUIZ_DATA;
        if (loading) loading.hidden = true;
        if (wrap) wrap.hidden = false;
        buildNav();
        renderQuestion(0);
        startTimer();
    } else {
        loadQuiz();
    }

    // ── Wire Retake Button (Instant restart with zero network round-trip) ──
    document.getElementById('retakeBtn')?.addEventListener('click', () => {
        answers = {};
        current = 0;
        timerLeft = cfg.timerSeconds;
        submitted = false;
        resultSc.hidden = true;
        wrap.hidden = false;
        buildNav();
        renderQuestion(0);
        startTimer();
    });
})();
</script>
</body>
</html>

