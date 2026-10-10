/**
 * assets/js/pdf_reader.js — High-Performance Book Reader
 * Features:
 * - Direct Binary ArrayBuffer Streaming (Eliminates Base64 memory expansion & CPU blocking loops)
 * - In-Memory Offscreen Page Canvas Cache (Instant 0ms flipping between viewed pages)
 * - Background Next-Page Preloading (Pre-renders page N+1 while the user reads page N)
 * - Instant Keyboard (ArrowLeft / ArrowRight) and Touch Navigation
 * - Non-blocking Progress Sync with server
 * - Integrated Instant Quiz integration without full page reloads
 */
(function () {
    'use strict';

    const cfg = window.READER_CONFIG;
    if (!cfg) return;

    // DOM references
    const canvas         = document.getElementById('pdfCanvas');
    const ctx            = canvas ? canvas.getContext('2d', { alpha: false }) : null;
    const loadingEl      = document.getElementById('pageLoading');
    const errorEl        = document.getElementById('errorState');
    const pageInput      = document.getElementById('pageInput');
    const totalPagesDisp = document.getElementById('totalPagesDisplay');
    const prevBtn        = document.getElementById('prevPage');
    const nextBtn        = document.getElementById('nextPage');
    const zoomInBtn      = document.getElementById('zoomIn');
    const zoomOutBtn     = document.getElementById('zoomOut');
    const zoomDisplay    = document.getElementById('zoomDisplay');
    const progressMini   = document.getElementById('progressMini');
    const fsBtn          = document.getElementById('fullscreenBtn');

    let pdfDoc          = null;
    let currentPage     = 1;
    let totalPages      = cfg.pageCount || 1;
    let scale           = 1.4;
    let renderTask      = null;
    let saveTimeout     = null;
    let completionShown = false;
    let isPreloading    = false;

    // ── High-Speed In-Memory Page Cache ──
    // Maps pageNum -> { canvas: HTMLCanvasElement, width: number, height: number, scale: number }
    const pageCache = new Map();

    // ── Load PDF with Instant Binary Stream & Resilient Extension Fallback ──
    async function loadPdf() {
        try {
            if (loadingEl) loadingEl.hidden = false;
            if (canvas) canvas.hidden = true;

            let arrayBuffer = null;

            // Step 1: Attempt direct binary stream (fastest)
            try {
                const resp = await fetch(cfg.pdfUrl, {
                    credentials: 'include',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/pdf, */*'
                    }
                });
                if (resp.ok) {
                    const buf = await resp.arrayBuffer();
                    // Verify that the buffer starts with PDF header '%PDF'
                    if (buf && buf.byteLength > 10) {
                        const check = new Uint8Array(buf.slice(0, 5));
                        const magic = String.fromCharCode.apply(null, check);
                        if (magic.startsWith('%PDF')) {
                            arrayBuffer = buf;
                        }
                    }
                }
            } catch (netErr) {
                console.warn('[Reader] Direct stream interrupted or intercepted, engaging JSON fallback:', netErr);
            }

            // Step 2: If direct stream was intercepted (e.g. by download manager extension), fetch via JSON payload
            if (!arrayBuffer) {
                const sep = cfg.pdfUrl.includes('?') ? '&' : '?';
                const jsonResp = await fetch(cfg.pdfUrl + sep + 'format=json', {
                    credentials: 'include',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!jsonResp.ok) throw new Error('HTTP ' + jsonResp.status);
                const jsonData = await jsonResp.json();
                if (!jsonData || !jsonData.pdf_base64) throw new Error(jsonData?.error || 'Unable to read PDF data');

                try {
                    // Fast native C++ base64 decoding via data URI
                    const dataUriResp = await fetch('data:application/pdf;base64,' + jsonData.pdf_base64);
                    arrayBuffer = await dataUriResp.arrayBuffer();
                } catch (_) {
                    // Fallback to typed array
                    const bin = atob(jsonData.pdf_base64);
                    const bytes = Uint8Array.from(bin, c => c.charCodeAt(0));
                    arrayBuffer = bytes.buffer;
                }
            }

            const loadingTask = pdfjsLib.getDocument({
                data:       arrayBuffer,
                cMapUrl:    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
                cMapPacked: true
            });

            pdfDoc = await loadingTask.promise;
            totalPages = pdfDoc.numPages;

            if (pageInput) pageInput.max = totalPages;
            if (totalPagesDisp) totalPagesDisp.textContent = totalPages;

            const startPage = cfg.savedPage || 1;

            // Render start page directly without any blocking modal
            await renderPage(startPage);

            if (loadingEl) loadingEl.hidden = true;
            if (canvas) canvas.hidden = false;

            // Preload next page in the background
            preloadAdjacentPages(startPage);

            // Preload quiz questions in background
            if (window.preloadQuiz && cfg.bookId) {
                window.preloadQuiz(cfg.bookId);
            }
        } catch (err) {
            console.error('[Reader] PDF load error:', err);
            if (loadingEl) loadingEl.hidden = true;
            if (errorEl) errorEl.hidden = false;
        }
    }

    // ── Render Page with Cache & Offscreen Capture ──
    async function renderPage(pageNum) {
        if (!pdfDoc) return;
        pageNum = Math.max(1, Math.min(pageNum, totalPages));
        currentPage = pageNum;

        // 1. Instant Cache Hit: 0ms paint from offscreen snapshot!
        if (pageCache.has(pageNum)) {
            const cached = pageCache.get(pageNum);
            if (cached && cached.scale === scale) {
                if (renderTask) {
                    try { await renderTask.cancel(); } catch (_) {}
                    renderTask = null;
                }
                canvas.width  = cached.width;
                canvas.height = cached.height;
                ctx.drawImage(cached.canvas, 0, 0);
                updateUI();
                scheduleProgressSave();
                preloadAdjacentPages(pageNum);
                return;
            }
        }

        // 2. Cache Miss: Rasterize from PDF.js
        if (renderTask) {
            try { await renderTask.cancel(); } catch (_) {}
            renderTask = null;
        }

        try {
            const page     = await pdfDoc.getPage(pageNum);
            const viewport = page.getViewport({ scale });

            canvas.width  = viewport.width;
            canvas.height = viewport.height;

            renderTask = page.render({ canvasContext: ctx, viewport });
            await renderTask.promise;
            renderTask = null;

            // Cache rendered snapshot into an offscreen canvas
            cachePageSnapshot(pageNum, canvas, viewport.width, viewport.height, scale);

            updateUI();
            scheduleProgressSave();

            // 3. Preload adjacent pages in the background
            preloadAdjacentPages(pageNum);
        } catch (e) {
            if (e?.name !== 'RenderingCancelledException') {
                console.warn('[Reader] Render error on page', pageNum, e);
            }
        }
    }

    function cachePageSnapshot(pageNum, srcCanvas, width, height, currentScale) {
        try {
            const offscreen = document.createElement('canvas');
            offscreen.width  = width;
            offscreen.height = height;
            const offCtx = offscreen.getContext('2d', { alpha: false });
            offCtx.drawImage(srcCanvas, 0, 0);

            pageCache.set(pageNum, {
                canvas: offscreen,
                width:  width,
                height: height,
                scale:  currentScale
            });

            // Keep cache bounded to 8 adjacent pages to conserve memory
            if (pageCache.size > 8) {
                const oldest = Array.from(pageCache.keys()).find(p => Math.abs(p - currentPage) > 3);
                if (oldest) pageCache.delete(oldest);
            }
        } catch (_) {}
    }

    // ── Preload Next and Previous Pages in Background ──
    function preloadAdjacentPages(pageNum) {
        const nextP = pageNum + 1;
        const prevP = pageNum - 1;

        const schedulePreload = (p) => {
            if (p < 1 || p > totalPages || pageCache.has(p)) return;
            if (window.requestIdleCallback) {
                window.requestIdleCallback(() => renderOffscreenPage(p), { timeout: 800 });
            } else {
                setTimeout(() => renderOffscreenPage(p), 100);
            }
        };

        schedulePreload(nextP);
        if (prevP >= 1) schedulePreload(prevP);
    }

    async function renderOffscreenPage(pageNum) {
        if (!pdfDoc || pageCache.has(pageNum) || isPreloading) return;
        isPreloading = true;
        try {
            const page     = await pdfDoc.getPage(pageNum);
            const viewport = page.getViewport({ scale });

            const offscreen = document.createElement('canvas');
            offscreen.width  = viewport.width;
            offscreen.height = viewport.height;
            const offCtx     = offscreen.getContext('2d', { alpha: false });

            const task = page.render({ canvasContext: offCtx, viewport });
            await task.promise;

            pageCache.set(pageNum, {
                canvas: offscreen,
                width:  viewport.width,
                height: viewport.height,
                scale:  scale
            });
        } catch (_) {
        } finally {
            isPreloading = false;
        }
    }

    function goToPage(p) {
        p = parseInt(p, 10);
        if (isNaN(p) || p < 1) p = 1;
        if (p > totalPages) p = totalPages;
        renderPage(p);
    }

    function updateUI() {
        const pct = Math.round((currentPage / totalPages) * 100);
        if (pageInput) pageInput.value = currentPage;
        if (prevBtn) prevBtn.disabled = (currentPage <= 1);
        if (nextBtn) nextBtn.disabled = (currentPage >= totalPages);
        if (progressMini) progressMini.textContent = `${pct}% read`;
        if (zoomDisplay) zoomDisplay.textContent = `${Math.round((scale / 1.4) * 100)}%`;

        // Completion panel when last page is reached
        if (currentPage >= totalPages && !completionShown && !cfg.alreadyCompleted) {
            completionShown = true;
            showCompletionPanel();
        }
    }

    function showCompletionPanel() {
        saveProgress(currentPage, totalPages);

        if (document.getElementById('completionPanel')) return;
        const panel = document.createElement('div');
        panel.id = 'completionPanel';
        panel.className = 'completion-panel';
        panel.innerHTML = `
            <span class="comp-icon">🎉</span>
            <div class="comp-text">
                <h3>You finished the book!</h3>
                <p>${cfg.quizDone ? "You've already completed the quiz. Retake it anytime to boost your score!" : "Test your understanding now to earn points and XP!"}</p>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0">
                <button type="button" class="btn-quiz" id="btnLaunchQuizFromPanel">
                    <i class="fas fa-clipboard-question"></i> ${cfg.quizDone ? 'Retake Quiz' : 'Take Quiz'}
                </button>
                <button type="button" class="btn-quiz-skip" onclick="this.closest('.completion-panel').remove()">Later</button>
            </div>`;
        document.body.appendChild(panel);

        const btnLaunch = document.getElementById('btnLaunchQuizFromPanel');
        if (btnLaunch) {
            btnLaunch.onclick = () => {
                panel.remove();
                if (window.openInstantQuiz) {
                    window.openInstantQuiz(cfg.bookId, cfg.quizDone);
                } else {
                    window.location.href = cfg.quizUrl;
                }
            };
        }
    }

    // Wire top toolbar quiz button to open instant quiz modal directly
    const toolbarQuizBtn = document.querySelector('.reader-toolbar [href*="quiz.php"]');
    if (toolbarQuizBtn) {
        toolbarQuizBtn.onclick = (e) => {
            e.preventDefault();
            if (window.openInstantQuiz) {
                window.openInstantQuiz(cfg.bookId, cfg.quizDone);
            } else {
                window.location.href = cfg.quizUrl;
            }
        };
    }

    // ── Non-blocking Progress Save (Debounced) ──
    function scheduleProgressSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            saveProgress(currentPage, totalPages);
        }, 1200);
    }

    function saveProgress(cur, total) {
        const payload = JSON.stringify({
            book_id:      cfg.bookId,
            current_page: cur,
            total_pages:  total
        });

        // Use sendBeacon if available for zero-overhead background transport
        if (navigator.sendBeacon) {
            const blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon('api/save_reading_progress.php', blob);
        } else {
            fetch('api/save_reading_progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: payload,
                keepalive: true
            }).catch(() => {});
        }
    }

    // ── Button & Keyboard Bindings ──
    if (prevBtn) prevBtn.onclick = () => goToPage(currentPage - 1);
    if (nextBtn) nextBtn.onclick = () => goToPage(currentPage + 1);

    if (pageInput) {
        pageInput.onchange = () => goToPage(pageInput.value);
        pageInput.onkeydown = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                goToPage(pageInput.value);
            }
        };
    }

    if (zoomInBtn) {
        zoomInBtn.onclick = () => {
            if (scale < 3.0) {
                scale = Math.min(3.0, +(scale + 0.2).toFixed(1));
                pageCache.clear(); // clear cache on zoom change
                renderPage(currentPage);
            }
        };
    }

    if (zoomOutBtn) {
        zoomOutBtn.onclick = () => {
            if (scale > 0.8) {
                scale = Math.max(0.8, +(scale - 0.2).toFixed(1));
                pageCache.clear();
                renderPage(currentPage);
            }
        };
    }

    if (fsBtn) {
        fsBtn.onclick = () => {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                document.exitFullscreen().catch(() => {});
            }
        };
    }

    // Keyboard shortcuts: Left/Right arrow keys for instant flipping
    window.addEventListener('keydown', (e) => {
        if (['input', 'textarea', 'select'].includes(document.activeElement?.tagName?.toLowerCase())) return;
        if (e.key === 'ArrowRight' || e.key === 'PageDown') {
            e.preventDefault();
            goToPage(currentPage + 1);
        } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
            e.preventDefault();
            goToPage(currentPage - 1);
        }
    });

    // Touch swipe gestures for mobile
    let touchStartX = 0;
    let touchStartY = 0;
    const bodyEl = document.getElementById('readerBody');
    if (bodyEl) {
        bodyEl.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        bodyEl.addEventListener('touchend', (e) => {
            const diffX = e.changedTouches[0].screenX - touchStartX;
            const diffY = e.changedTouches[0].screenY - touchStartY;
            if (Math.abs(diffX) > 60 && Math.abs(diffX) > Math.abs(diffY) * 1.5) {
                if (diffX < 0) goToPage(currentPage + 1);
                else goToPage(currentPage - 1);
            }
        }, { passive: true });
    }

    // Connect top Quiz button to Instant Quiz modal
    document.addEventListener('DOMContentLoaded', () => {
        const quizBtn = document.querySelector('.reader-toolbar [href*="quiz.php"]');
        if (quizBtn) {
            quizBtn.addEventListener('click', (e) => {
                if (window.openInstantQuiz) {
                    e.preventDefault();
                    window.openInstantQuiz(cfg.bookId, cfg.quizDone);
                }
            });
            // Preload on mouse hover
            quizBtn.addEventListener('mouseenter', () => {
                if (window.preloadQuiz) window.preloadQuiz(cfg.bookId);
            });
        }
    });

    // Start loading
    loadPdf();
})();
