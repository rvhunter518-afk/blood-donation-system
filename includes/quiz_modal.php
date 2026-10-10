<?php
/**
 * includes/quiz_modal.php — Instant In-Page Quiz Modal
 * Enables instantaneous quiz launching without full page reloads.
 * Used in library.php, reader.php, and any learning page.
 */
?>
<link rel="stylesheet" href="assets/css/instant_quiz.css?v=<?= filemtime(__DIR__ . '/../assets/css/instant_quiz.css') ?>">

<!-- Canvas for Celebration Confetti -->
<canvas id="iqConfettiCanvas" style="display:none;"></canvas>

<!-- Instant Quiz Modal -->
<div id="instantQuizModal" class="instant-quiz-modal" role="dialog" aria-modal="true" aria-label="Book Knowledge Quiz" style="display:none;">
    <div class="instant-quiz-dialog">
        <!-- Close Button -->
        <button type="button" class="btn-quiz-close" id="btnCloseInstantQuiz" title="Exit Quiz">&times;</button>

        <!-- Quiz Screen -->
        <div id="instantQuizActiveScreen">
            <div class="iq-header">
                <div class="iq-book-info">
                    <span class="iq-book-badge"><i class="fas fa-droplet"></i> Quiz Challenge</span>
                    <h3 class="iq-book-title" id="iqBookTitle">Book Knowledge Quiz</h3>
                </div>
                <div class="iq-progress-bar">
                    <div class="iq-progress-fill" id="iqProgressFill" style="width: 0%;"></div>
                </div>
                <div class="iq-meta">
                    <span id="iqQuestionCounter">Question 1 of 10</span>
                    <span class="iq-timer" id="iqTimer"><i class="fas fa-clock"></i> 10:00</span>
                </div>
            </div>

            <!-- Question Pills Navigation (Min 10 Questions) -->
            <div class="iq-nav" id="iqQuestionNav"></div>

            <!-- Animated Question Card -->
            <div class="iq-card" id="iqQuestionCard">
                <div class="iq-qnum" id="iqQNum"><i class="fas fa-circle-question"></i> Question 1</div>
                <div class="iq-qtext" id="iqQText">Loading question...</div>
                <div class="iq-options" id="iqOptionsGrid"></div>
            </div>

            <!-- Controls -->
            <div class="iq-footer">
                <button type="button" class="iq-btn iq-btn-secondary" id="iqPrevBtn" disabled>
                    <i class="fas fa-arrow-left"></i> Previous
                </button>
                <button type="button" class="iq-btn iq-btn-primary" id="iqNextBtn">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
                <button type="button" class="iq-btn iq-btn-submit" id="iqSubmitBtn" style="display:none;">
                    <i class="fas fa-paper-plane"></i> Submit Quiz
                </button>
            </div>
        </div>

        <!-- Result Screen -->
        <div id="instantQuizResultScreen" style="display:none;">
            <div class="iq-result-header">
                <div class="iq-result-icon" id="iqResultIcon">🎉</div>
                <h2 class="iq-result-title" id="iqResultTitle">Quiz Completed!</h2>
                <p class="iq-result-sub" id="iqResultSub">Here is your verified performance & badge rewards:</p>
            </div>

            <div class="iq-score-card">
                <div class="iq-score-circle">
                    <span class="iq-score-pct" id="iqScorePct">100%</span>
                    <span class="iq-score-label" id="iqScoreFraction">10 / 10 correct</span>
                </div>
                <div class="iq-level-pill" id="iqLevelPill">High Mastery</div>
            </div>

            <div class="iq-rewards-grid">
                <div class="iq-reward-box">
                    <span class="iq-reward-val" id="iqRewardPoints">+100</span>
                    <span class="iq-reward-lbl">Points Earned</span>
                </div>
                <div class="iq-reward-box">
                    <span class="iq-reward-val" id="iqRewardXP">+75</span>
                    <span class="iq-reward-lbl">XP Gained</span>
                </div>
            </div>

            <!-- Badges Earned Alert -->
            <div class="iq-badges-earned" id="iqBadgesEarned"></div>

            <!-- Question Review & Explanations -->
            <div class="iq-review-section">
                <h4><i class="fas fa-clipboard-check"></i> Answer Explanations</h4>
                <div class="iq-review-list" id="iqReviewList"></div>
            </div>

            <div class="iq-result-actions">
                <button type="button" class="iq-btn iq-btn-secondary" id="iqRetakeBtn">
                    <i class="fas fa-rotate-right"></i> Retake Quiz
                </button>
                <button type="button" class="iq-btn iq-btn-primary" id="iqFinishBtn">
                    <i class="fas fa-check"></i> Done
                </button>
            </div>
        </div>
    </div>
</div>

