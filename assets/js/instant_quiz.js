/**
 * assets/js/instant_quiz.js — Instant In-Page Quiz Engine
 * Features:
 * - 0ms Launch: Preloads and caches quiz questions in memory.
 * - Zero Full-Page Reload: Opens directly in a smooth modal overlay.
 * - Instant Navigation: Client-side in-memory question switching.
 * - Secure Server Evaluation: Sends answers to api/submit_quiz.php for DB grading.
 * - Real-time Progress & Gamification Sync: Updates UI and scores dynamically.
 */
(function () {
    'use strict';

    // ── In-Memory Question Cache ──
    const quizCache = new Map(); // bookId -> { book_id, book_title, questions: [...] }

    let activeBookId   = null;
    let quizData       = null;
    let currentQIdx    = 0;
    let selectedAnswers = {}; // question_id -> 'A'|'B'|'C'|'D'
    let timerSeconds   = 300;
    let timerInterval  = null;
    let isSubmitting   = false;

    // ── Helper: Preload Quiz Questions in Background ──
    window.preloadQuiz = async function (bookId) {
        bookId = parseInt(bookId, 10);
        if (!bookId || quizCache.has(bookId)) return quizCache.get(bookId);

        try {
            const resp = await fetch(`api/get_book_quiz.php?book_id=${bookId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!resp.ok) return null;
            const data = await resp.json();
            if (data && data.success && Array.isArray(data.questions)) {
                quizCache.set(bookId, data);
                return data;
            }
        } catch (_) {}
        return null;
    };

    // ── Launch Instant Quiz Modal ──
    window.openInstantQuiz = async function (bookId, isRetake = false) {
        bookId = parseInt(bookId, 10);
        if (!bookId) return;

        const modal = document.getElementById('instantQuizModal');
        if (!modal) {
            // If modal DOM missing, fallback to quiz.php
            window.location.href = `quiz.php?book_id=${bookId}`;
            return;
        }

        activeBookId = bookId;
        selectedAnswers = {};
        currentQIdx = 0;
        isSubmitting = false;

        // Fetch or get from memory cache
        let data = quizCache.get(bookId);
        if (!data) {
            data = await window.preloadQuiz(bookId);
        }

        if (!data || !data.questions || !data.questions.length) {
            // Direct network fallback
            try {
                const resp = await fetch(`api/get_book_quiz.php?book_id=${bookId}`);
                data = await resp.json();
                if (data && data.success) {
                    quizCache.set(bookId, data);
                } else {
                    alert(data?.error || 'No quiz questions available for this book.');
                    return;
                }
            } catch (e) {
                alert('Unable to load quiz. Please check your connection.');
                return;
            }
        }

        quizData = data;
        timerSeconds = data.questions.length * 60; // 1 min per question

        // Reset views
        document.getElementById('instantQuizActiveScreen').style.display = 'block';
        document.getElementById('instantQuizResultScreen').style.display = 'none';

        // Set header info
        const titleEl = document.getElementById('iqBookTitle');
        if (titleEl) titleEl.textContent = data.book_title || 'Knowledge Quiz';

        // Build navigation pills
        buildPillNav(data.questions);

        // Render first question instantly
        renderQuestion(0);

        // Start timer
        startQuizTimer();

        // Show modal
        modal.style.display = 'flex';
        requestAnimationFrame(() => modal.classList.add('show'));
    };

    // ── Build Question Pills ──
    function buildPillNav(questions) {
        const nav = document.getElementById('iqQuestionNav');
        if (!nav) return;
        nav.innerHTML = '';

        questions.forEach((_, idx) => {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = `iq-nav-pill ${idx === 0 ? 'active' : ''}`;
            pill.textContent = idx + 1;
            pill.onclick = () => renderQuestion(idx);
            nav.appendChild(pill);
        });
    }

    function updatePillNav() {
        const pills = document.querySelectorAll('.iq-nav-pill');
        pills.forEach((pill, idx) => {
            const qId = quizData.questions[idx].id;
            const isAnswered = !!selectedAnswers[qId];
            pill.className = `iq-nav-pill ${idx === currentQIdx ? 'active' : ''} ${isAnswered ? 'answered' : ''}`;
        });
    }

    // ── Render Question (Instant in-memory switch with 3D animation) ──
    function renderQuestion(idx) {
        if (!quizData || !quizData.questions[idx]) return;
        currentQIdx = idx;
        const q = quizData.questions[idx];
        const total = quizData.questions.length;

        // Reset and trigger card entrance animation
        const card = document.getElementById('iqQuestionCard');
        if (card) {
            card.style.animation = 'none';
            void card.offsetWidth; // trigger reflow
            card.style.animation = 'cardSlideFade 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards';
        }

        document.getElementById('iqQNum').innerHTML = `<i class="fas fa-circle-question"></i> Question ${idx + 1} of ${total}`;
        document.getElementById('iqQText').textContent = q.question_text;
        document.getElementById('iqQuestionCounter').textContent = `Question ${idx + 1} of ${total}`;
        document.getElementById('iqProgressFill').style.width = `${((idx + 1) / total) * 100}%`;

        // Render options
        const optsGrid = document.getElementById('iqOptionsGrid');
        optsGrid.innerHTML = '';

        const options = [
            ['A', q.option_a],
            ['B', q.option_b],
            ['C', q.option_c],
            ['D', q.option_d]
        ];

        const selectedLetter = selectedAnswers[q.id];

        options.forEach(([letter, text], optIdx) => {
            if (!text) return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `iq-option-btn ${selectedLetter === letter ? 'selected' : ''}`;
            btn.style.animation = 'cardSlideFade 0.28s cubic-bezier(0.16, 1, 0.3, 1) forwards';
            btn.style.animationDelay = `${optIdx * 0.05}s`;
            btn.innerHTML = `<span class="iq-opt-letter">${letter}</span><span class="iq-opt-text">${escapeHtml(text)}</span>`;
            btn.onclick = () => {
                selectedAnswers[q.id] = letter;
                renderQuestion(currentQIdx);
            };
            optsGrid.appendChild(btn);
        });

        // Update footer buttons
        const prevBtn   = document.getElementById('iqPrevBtn');
        const nextBtn   = document.getElementById('iqNextBtn');
        const submitBtn = document.getElementById('iqSubmitBtn');

        if (prevBtn) prevBtn.disabled = (idx === 0);
        if (nextBtn) nextBtn.style.display = (idx === total - 1) ? 'none' : 'inline-flex';
        if (submitBtn) submitBtn.style.display = (idx === total - 1) ? 'inline-flex' : 'none';

        updatePillNav();
    }

    // ── Timer Loop ──
    function startQuizTimer() {
        stopQuizTimer();
        const timerEl = document.getElementById('iqTimer');

        const updateDisplay = () => {
            if (!timerEl) return;
            const m = String(Math.floor(timerSeconds / 60)).padStart(2, '0');
            const s = String(timerSeconds % 60).padStart(2, '0');
            timerEl.innerHTML = `<i class="fas fa-clock"></i> ${m}:${s}`;
            timerEl.className = `iq-timer ${timerSeconds <= 60 ? 'urgent' : ''}`;
        };

        updateDisplay();
        timerInterval = setInterval(() => {
            timerSeconds--;
            updateDisplay();
            if (timerSeconds <= 0) {
                stopQuizTimer();
                submitQuiz();
            }
        }, 1000);
    }

    function stopQuizTimer() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    // ── Submit Quiz to Server ──
    async function submitQuiz() {
        if (isSubmitting || !quizData) return;
        isSubmitting = true;
        stopQuizTimer();

        // Convert answers object to array
        const answersArray = Object.entries(selectedAnswers).map(([qid, ans]) => ({
            question_id: parseInt(qid, 10),
            selected_answer: ans
        }));

        const submitBtn = document.getElementById('iqSubmitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        }

        try {
            const resp = await fetch('api/submit_quiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    book_id: activeBookId,
                    answers: answersArray
                })
            });

            const result = await resp.json();
            if (!result.success) {
                alert(result.error || 'Failed to submit quiz.');
                isSubmitting = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Submit Quiz';
                }
                return;
            }

            // Render result screen
            renderResultScreen(result);

            // Update caller page state dynamically
            updatePageAfterQuizCompletion(activeBookId, result);

        } catch (e) {
            console.error('[Quiz] Submit error:', e);
            alert('Network error while evaluating quiz.');
            isSubmitting = false;
        }
    }

    // ── Render Result Screen ──
    function renderResultScreen(res) {
        document.getElementById('instantQuizActiveScreen').style.display = 'none';
        const resScreen = document.getElementById('instantQuizResultScreen');
        resScreen.style.display = 'block';

        const pct = res.percentage || 0;
        document.getElementById('iqScorePct').textContent = `${pct}%`;
        document.getElementById('iqScoreFraction').textContent = `${res.score} / ${res.total} correct`;
        document.getElementById('iqRewardPoints').textContent = `+${res.points_awarded || 0}`;
        document.getElementById('iqRewardXP').textContent = `+${res.xp_awarded || 0}`;

        const levelPill = document.getElementById('iqLevelPill');
        if (levelPill) {
            levelPill.className = `iq-level-pill level-${res.score_level || 'low'}`;
            levelPill.textContent = (res.score_level === 'high') ? 'High Mastery' :
                                   (res.score_level === 'medium') ? 'Intermediate' : 'Needs Practice';
        }

        const iconEl = document.getElementById('iqResultIcon');
        if (iconEl) {
            iconEl.textContent = (pct >= 80) ? '🏆' : (pct >= 50) ? '⭐' : '📖';
        }

        // Badges display (Luxury Cards with Shimmer)
        const badgesBox = document.getElementById('iqBadgesEarned');
        if (badgesBox) {
            if (res.badges_earned && res.badges_earned.length) {
                badgesBox.style.display = 'block';
                badgesBox.innerHTML = `
                    <div class="iq-badges-title"><i class="fas fa-medal"></i> New Badges Unlocked!</div>
                    <div class="iq-badges-cards">
                        ${res.badges_earned.map(b => `
                            <div class="iq-badge-card shimmer">
                                <div class="iq-badge-icon-wrap">
                                    <i class="fas ${b.icon ? (b.icon.startsWith('fa-') ? b.icon : b.icon) : 'fa-award'}"></i>
                                </div>
                                <div class="iq-badge-info">
                                    <span class="iq-badge-rarity">${escapeHtml(b.rarity || 'UNLOCKED')}</span>
                                    <strong class="iq-badge-name">${escapeHtml(b.name)}</strong>
                                    <small class="iq-badge-desc">${escapeHtml(b.description)}</small>
                                </div>
                                ${b.points ? `<span class="iq-badge-bonus">+${b.points} pts</span>` : ''}
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                badgesBox.style.display = 'block';
                badgesBox.innerHTML = `
                    <div class="iq-badge-milestone-card">
                        <div class="iq-milestone-icon"><i class="fas fa-award"></i></div>
                        <div class="iq-milestone-text">
                            <strong>Keep Reading to Unlock Badges!</strong>
                            <p>Scores over 80% earn Quiz Master & Gold Champion badges. XP and Points added to profile!</p>
                        </div>
                    </div>
                `;
            }
        }

        // Launch celebratory confetti if score >= 50%
        if (pct >= 50) {
            launchQuizConfetti();
        }

        // Explanations list
        const reviewList = document.getElementById('iqReviewList');
        if (reviewList && Array.isArray(res.explanations)) {
            reviewList.innerHTML = res.explanations.map((exp, i) => `
                <div class="iq-review-item ${exp.is_correct ? 'correct' : 'incorrect'}">
                    <div class="iq-review-q">
                        <span class="iq-review-badge"><i class="fas ${exp.is_correct ? 'fa-check' : 'fa-xmark'}"></i> Q${i + 1}</span>
                        <span>${escapeHtml(exp.question_text)}</span>
                    </div>
                    <div class="iq-review-answers">
                        <span>Your answer: <strong>${exp.selected}</strong></span>
                        ${!exp.is_correct ? `<span>Correct: <strong style="color:#10b981;">${exp.correct}</strong></span>` : ''}
                    </div>
                    ${exp.explanation ? `<p class="iq-review-exp">${escapeHtml(exp.explanation)}</p>` : ''}
                </div>
            `).join('');
        }
    }

    // ── Update Host Page State (Library / Reader) ──
    function updatePageAfterQuizCompletion(bookId, res) {
        // 1. Update library.php book card if visible
        const card = document.querySelector(`.book-card [data-book-id="${bookId}"]`)?.closest('.book-card');
        if (card) {
            card.classList.remove('state-unread', 'state-reading', 'state-completed');
            card.classList.add('state-quiz-done');
            const btn = card.querySelector('.btn-read');
            if (btn) {
                btn.innerHTML = '<i class="fas fa-rotate-right"></i> Retake Quiz';
            }
        }

        // 2. Update reader.php toolbar button if visible
        const readerQuizBtn = document.querySelector('.reader-toolbar [href*="quiz.php"], .reader-toolbar button#btnLaunchQuiz');
        if (readerQuizBtn) {
            readerQuizBtn.innerHTML = '<i class="fas fa-rotate-right"></i> Retake';
            readerQuizBtn.style.background = '#059669';
        }

        // 3. Update XP in header if present
        const xpText = document.querySelector('.xp-level, .xp-bar-wrap span');
        if (xpText && res.xp_awarded) {
            // Non-blocking header refresh
            try {
                const curXP = parseInt(xpText.textContent.replace(/[^0-9]/g, ''), 10) || 0;
                xpText.textContent = `${curXP + res.xp_awarded} XP`;
            } catch (_) {}
        }
    }

    // ── Close Modal ──
    function closeInstantQuiz() {
        stopQuizTimer();
        const modal = document.getElementById('instantQuizModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 250);
        }
    }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])
        );
    }

    // ── Celebration Confetti Explosion ──
    function launchQuizConfetti() {
        const canvas = document.getElementById('iqConfettiCanvas');
        if (!canvas) return;
        canvas.style.display = 'block';
        canvas.width  = window.innerWidth;
        canvas.height = window.innerHeight;
        const ctx = canvas.getContext('2d');
        const pieces = Array.from({ length: 130 }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * -canvas.height * 0.5,
            w: 8 + Math.random() * 8,
            h: 12 + Math.random() * 10,
            color: ['#dc2626', '#ef4444', '#f59e0b', '#fbbf24', '#10b981', '#3b82f6', '#ec4899'][Math.floor(Math.random() * 7)],
            rotation: Math.random() * Math.PI * 2,
            rotSpeed: (Math.random() - 0.5) * 0.25,
            vx: (Math.random() - 0.5) * 5,
            vy: 2.5 + Math.random() * 4.5
        }));
        let frame;
        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            let alive = false;
            pieces.forEach(p => {
                if (p.y < canvas.height + 30) {
                    alive = true;
                    p.x += p.vx;
                    p.y += p.vy;
                    p.vy += 0.08;
                    p.rotation += p.rotSpeed;
                }
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rotation);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            });
            if (alive) {
                frame = requestAnimationFrame(draw);
            } else {
                canvas.style.display = 'none';
                cancelAnimationFrame(frame);
            }
        }
        draw();
        setTimeout(() => { canvas.style.display = 'none'; cancelAnimationFrame(frame); }, 5000);
    }

    // ── DOM Event Listeners ──
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('instantQuizModal');
        if (!modal) return;

        // Close handlers
        document.getElementById('btnCloseInstantQuiz')?.addEventListener('click', closeInstantQuiz);
        document.getElementById('iqFinishBtn')?.addEventListener('click', closeInstantQuiz);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeInstantQuiz();
        });

        // Navigation
        document.getElementById('iqPrevBtn')?.addEventListener('click', () => {
            if (currentQIdx > 0) renderQuestion(currentQIdx - 1);
        });
        document.getElementById('iqNextBtn')?.addEventListener('click', () => {
            if (quizData && currentQIdx < quizData.questions.length - 1) {
                renderQuestion(currentQIdx + 1);
            }
        });

        // Submit
        document.getElementById('iqSubmitBtn')?.addEventListener('click', () => {
            if (!quizData) return;
            const unanswered = quizData.questions.filter(q => !selectedAnswers[q.id]).length;
            if (unanswered > 0) {
                if (!confirm(`You have ${unanswered} unanswered question${unanswered > 1 ? 's' : ''}. Submit anyway?`)) {
                    return;
                }
            }
            submitQuiz();
        });

        // Retake
        document.getElementById('iqRetakeBtn')?.addEventListener('click', () => {
            if (activeBookId) {
                window.openInstantQuiz(activeBookId, true);
            }
        });

        // Keyboard navigation inside quiz
        window.addEventListener('keydown', (e) => {
            if (modal.style.display === 'none' || !modal.classList.contains('show')) return;
            if (e.key === 'Escape') {
                closeInstantQuiz();
            } else if (e.key === 'ArrowRight' && currentQIdx < (quizData?.questions.length || 0) - 1) {
                renderQuestion(currentQIdx + 1);
            } else if (e.key === 'ArrowLeft' && currentQIdx > 0) {
                renderQuestion(currentQIdx - 1);
            } else if (['1', '2', '3', '4'].includes(e.key) || ['a', 'b', 'c', 'd'].includes(e.key.toLowerCase())) {
                const map = { '1':'A', '2':'B', '3':'C', '4':'D', 'a':'A', 'b':'B', 'c':'C', 'd':'D' };
                const letter = map[e.key.toLowerCase()];
                if (letter && quizData && quizData.questions[currentQIdx]) {
                    selectedAnswers[quizData.questions[currentQIdx].id] = letter;
                    renderQuestion(currentQIdx);
                }
            }
        });
    });
})();

