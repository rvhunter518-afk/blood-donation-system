/**
 * assets/js/library.js
 * Library catalog: custom cursor, background video, AJAX filter/search, sorting, book cards.
 */
(function () {
    'use strict';

    // ── CUSTOM CURSOR ──
    const dot  = document.getElementById('cursorDot');
    const ring = document.getElementById('cursorRing');
    if (dot && ring) {
        let rx = 0, ry = 0, dx = 0, dy = 0;
        document.addEventListener('mousemove', e => {
            dx = e.clientX; dy = e.clientY;
            dot.style.cssText  = `left:${dx}px;top:${dy}px;opacity:1`;
        });
        (function animateRing() {
            rx += (dx - rx) * 0.12;
            ry += (dy - ry) * 0.12;
            ring.style.cssText = `left:${rx}px;top:${ry}px;opacity:1`;
            requestAnimationFrame(animateRing);
        })();
        document.addEventListener('mousedown', () => { dot.classList.add('clicked'); ring.classList.add('clicked'); });
        document.addEventListener('mouseup',   () => { dot.classList.remove('clicked'); ring.classList.remove('clicked'); });
        document.querySelectorAll('a,button,.book-card').forEach(el => {
            el.addEventListener('mouseenter', () => ring.classList.add('hover'));
            el.addEventListener('mouseleave', () => ring.classList.remove('hover'));
        });
    }

    // ── BACKGROUND VIDEO FALLBACK ──
    const bgVideo = document.getElementById('bgVideo');
    if (bgVideo) {
        bgVideo.addEventListener('error', () => {
            const wrap = document.getElementById('bgVideoWrap');
            if (wrap) wrap.style.display = 'none';
        });
    }

    // ── BADGES FLYOUT ──
    const badgesToggle = document.getElementById('badgesToggle');
    const badgesFlyout = document.getElementById('badgesFlyout');
    if (badgesToggle && badgesFlyout) {
        badgesToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            badgesFlyout.hidden = !badgesFlyout.hidden;
        });
        document.addEventListener('click', () => { if (badgesFlyout) badgesFlyout.hidden = true; });
    }

    // ── HAMBURGER MENU ──
    const hamburger = document.getElementById('hamburgerBtn');
    const libNav    = document.querySelector('.lib-nav');
    if (hamburger && libNav) {
        hamburger.addEventListener('click', () => libNav.classList.toggle('open'));
    }

    // ── BOOK CARDS & FILTERING ──
    const searchInput   = document.getElementById('bookSearch');
    const clearSearch   = document.getElementById('clearSearch');
    const filterBtns    = document.querySelectorAll('.filter-btn');
    const diffBtns      = document.querySelectorAll('.diff-btn');
    const sortSelect    = document.getElementById('sortSelect');
    const bookGrid      = document.getElementById('bookGrid');
    const booksCount    = document.getElementById('booksCount');
    const noBooksMsg    = document.getElementById('noBooksMsg');

    let activeFilter = 'all';
    let activeDiff   = 'all';
    let searchTerm   = '';

    function getCards() {
        return Array.from(bookGrid.querySelectorAll('.book-card'));
    }

    function filterAndSort() {
        const cards     = getCards();
        const sortVal   = sortSelect ? sortSelect.value : 'level';

        let visible = cards.filter(card => {
            const cat    = card.dataset.category || '';
            const title  = card.dataset.title || '';
            const author = card.dataset.author || '';
            const diff   = card.dataset.difficulty || '';

            const matchCat  = (activeFilter === 'all') || (cat === activeFilter);
            const matchDiff = (activeDiff === 'all')   || (diff === activeDiff);
            const matchQ    = !searchTerm || title.includes(searchTerm) || author.includes(searchTerm);
            return matchCat && matchDiff && matchQ;
        });

        // Sort
        visible.sort((a, b) => {
            if (sortVal === 'title')    return (a.dataset.title || '').localeCompare(b.dataset.title || '');
            if (sortVal === 'progress') return (parseInt(b.dataset.progress) || 0) - (parseInt(a.dataset.progress) || 0);
            if (sortVal === 'difficulty') {
                const order = { 'beginner': 0, 'intermediate': 1, 'advanced': 2 };
                return (order[a.dataset.difficulty?.toLowerCase()] ?? 0) - (order[b.dataset.difficulty?.toLowerCase()] ?? 0);
            }
            return (parseInt(a.dataset.level) || 0) - (parseInt(b.dataset.level) || 0);
        });

        // Reorder cards in DOM
        cards.forEach(c => { c.style.display = 'none'; });
        visible.forEach(c => {
            c.style.display = '';
            bookGrid.appendChild(c);
        });

        const count = visible.length;
        if (booksCount) booksCount.textContent = `Showing ${count} book${count !== 1 ? 's' : ''}`;
        if (noBooksMsg) noBooksMsg.hidden = (count > 0);
    }

    // Search
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            searchTerm = searchInput.value.toLowerCase().trim();
            if (clearSearch) clearSearch.hidden = !searchTerm;
            filterAndSort();
        });
    }
    if (clearSearch) {
        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            searchTerm = '';
            clearSearch.hidden = true;
            filterAndSort();
        });
    }

    // Category filters
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.dataset.filter || 'all';
            filterAndSort();
        });
    });

    // Difficulty filters
    diffBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            diffBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeDiff = btn.dataset.diff || 'all';
            filterAndSort();
        });
    });

    // Sort
    if (sortSelect) {
        sortSelect.addEventListener('change', filterAndSort);
    }

    // ── QUIZ PREFETCH ON HOVER ──
    document.addEventListener('mouseover', e => {
        const card = e.target.closest('.book-card');
        if (!card || card.dataset.prefetched) return;
        const readBtn = card.querySelector('.btn-read');
        const bookId = readBtn ? readBtn.dataset.bookId : null;
        if (bookId && window.preloadQuiz) {
            card.dataset.prefetched = '1';
            window.preloadQuiz(bookId);
        }
    }, { passive: true });

    // ── READ / QUIZ BUTTONS ──
    document.addEventListener('click', e => {
        const btn = e.target.closest('.btn-read');
        if (!btn) return;
        const bookId = btn.dataset.bookId;
        const action = btn.dataset.action;
        if (!bookId) return;

        if (action === 'quiz') {
            e.preventDefault();
            if (typeof window.openInstantQuiz === 'function') {
                window.openInstantQuiz(bookId);
            } else {
                window.location.href = `quiz.php?book_id=${bookId}`;
            }
            return;
        }

        window.location.href = `reader.php?book_id=${bookId}`;
    });

    // Expose for inline onclick
    window.clearAllFilters = function () {
        searchTerm = ''; activeFilter = 'all'; activeDiff = 'all';
        if (searchInput) searchInput.value = '';
        if (clearSearch) clearSearch.hidden = true;
        filterBtns.forEach((b, i) => b.classList.toggle('active', i === 0));
        diffBtns.forEach((b, i)   => b.classList.toggle('active', i === 0));
        filterAndSort();
    };

    // ── TOAST ──
    window.showToast = function (msg, type) {
        const wrap = document.getElementById('toastWrap');
        if (!wrap) return;
        const t = document.createElement('div');
        t.className = 'toast toast-' + (type || 'info');
        t.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'circle-exclamation' : 'circle-info'}"></i> ${msg}`;
        wrap.appendChild(t);
        setTimeout(() => { t.classList.add('hide'); setTimeout(() => t.remove(), 400); }, 3500);
    };

    // Initial render
    filterAndSort();
})();