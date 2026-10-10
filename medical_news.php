<?php
session_start();
$userName = $_SESSION['user_name'] ?? 'Guest';
$userRole = $_SESSION['user_role'] ?? 'user';
$backUrl = ($userRole === 'donor') ? 'donor/index.php' : 'user/user.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Medical & Transfusion News Network | LifeFlow</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ink: #171b22;
            --muted: #66707c;
            --paper: #fffdfb;
            --cream: #fff8f3;
            --red: #d62839;
            --red-deep: #8f1220;
            --red-soft: #fdeceb;
            --green: #0f7a5c;
            --green-soft: #e6f6ee;
            --blue: #1d4ed8;
            --blue-soft: #eff6ff;
            --line: #ecdfda;
            --shadow: 0 18px 40px rgba(143,18,32,.09), 0 6px 14px rgba(23,27,34,.04);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 10px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--paper);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            padding-bottom: 80px;
        }

        /* Top Header */
        header.news-header {
            background: #ffffff;
            border-bottom: 1px solid var(--line);
            padding: 16px 28px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .header-inner {
            max-width: 1240px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.45rem;
            font-weight: 800;
            color: var(--ink);
            text-decoration: none;
        }
        .brand i { color: var(--red); font-size: 1.6rem; }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-back {
            background: var(--cream);
            color: var(--ink);
            border: 1px solid var(--line);
        }
        .btn-back:hover {
            background: var(--line);
        }
        .btn-refresh {
            background: var(--red-soft);
            color: var(--red-deep);
            border: 1px solid rgba(214,40,57,0.25);
            cursor: pointer;
        }
        .btn-refresh:hover {
            background: var(--red);
            color: #ffffff;
        }

        /* Hero Banner */
        .news-hero {
            background: linear-gradient(180deg, var(--cream) 0%, var(--paper) 100%);
            padding: 40px 24px 30px;
            text-align: center;
            border-bottom: 1px solid var(--line);
        }
        .news-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--red-soft);
            color: var(--red-deep);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 14px;
            border: 1px solid rgba(214,40,57,0.2);
        }
        .news-hero h1 {
            font-size: 2.3rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 10px;
            color: var(--ink);
        }
        .news-hero p {
            font-size: 1.05rem;
            color: var(--muted);
            max-width: 680px;
            margin: 0 auto 24px;
            line-height: 1.6;
        }

        /* Category Filter Bar */
        .category-filter-wrap {
            max-width: 1240px;
            margin: 28px auto 0;
            padding: 0 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .filter-btn {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.86rem;
            font-weight: 700;
            color: var(--muted);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .filter-btn:hover {
            color: var(--ink);
            border-color: var(--ink);
        }
        .filter-btn.active {
            background: var(--red);
            color: #ffffff;
            border-color: var(--red);
            box-shadow: 0 4px 12px rgba(214,40,57,0.25);
        }

        /* News Cards Grid */
        .news-container {
            max-width: 1240px;
            margin: 36px auto 0;
            padding: 0 24px;
        }
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 28px;
        }
        @media (max-width: 768px) {
            .news-grid { grid-template-columns: 1fr; }
        }

        .news-card {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }
        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 24px 50px rgba(143,18,32,0.12);
        }

        .news-card-img-wrap {
            position: relative;
            width: 100%;
            height: 210px;
            overflow: hidden;
            background: var(--cream);
        }
        .news-card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease;
        }
        .news-card:hover .news-card-img {
            transform: scale(1.04);
        }

        .news-card-badges {
            position: absolute;
            top: 14px;
            left: 14px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .badge-source {
            background: rgba(23, 27, 34, 0.85);
            backdrop-filter: blur(8px);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-breaking {
            background: var(--red);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 8px rgba(214,40,57,0.4);
            animation: pulseBreaking 2s infinite ease-in-out;
        }
        @keyframes pulseBreaking {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .news-card-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        .news-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            color: var(--muted);
            margin-bottom: 12px;
            font-weight: 600;
        }
        .category-tag {
            background: var(--blue-soft);
            color: var(--blue);
            padding: 3px 10px;
            border-radius: 20px;
            font-weight: 700;
        }

        .news-title {
            font-size: 1.25rem;
            font-weight: 800;
            line-height: 1.35;
            color: var(--ink);
            margin-bottom: 12px;
            font-family: 'DM Sans', sans-serif;
        }
        .news-summary {
            font-size: 0.92rem;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 20px;
            flex: 1;
        }

        .news-card-footer {
            border-top: 1px solid var(--line);
            padding-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .verified-stamp {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--green);
        }
        .btn-read-more {
            background: none;
            border: none;
            color: var(--red);
            font-weight: 800;
            font-size: 0.86rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: gap 0.2s;
        }
        .btn-read-more:hover {
            gap: 10px;
            color: var(--red-deep);
        }

        /* Modal for reading news detail */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(23, 27, 34, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            border-radius: var(--radius-lg);
            max-width: 680px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(0,0,0,0.25);
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            animation: modalPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalPop {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .modal-img {
            width: 100%;
            height: 280px;
            object-fit: cover;
        }
        .modal-content {
            padding: 28px;
            overflow-y: auto;
        }
        .modal-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 36px;
            height: 36px;
            background: rgba(255,255,255,0.9);
            border: 1px solid var(--line);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            color: var(--ink);
            transition: all 0.2s;
        }
        .modal-close-btn:hover {
            background: var(--red);
            color: #ffffff;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="news-header">
        <div class="header-inner">
            <a href="<?php echo htmlspecialchars($backUrl); ?>" class="brand">
                <i class="fas fa-droplet"></i>
                <span>LifeFlow <span style="font-weight:400;color:var(--muted);font-size:1.05rem;">| Medical News</span></span>
            </a>
            <div class="header-actions">
                <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-header btn-back">
                    <i class="fas fa-arrow-left"></i> Back to <?php echo ($userRole === 'donor') ? 'Dashboard' : 'Portal'; ?>
                </a>
                <button type="button" class="btn-header btn-refresh" id="refreshNewsBtn" title="Fetch fresh clinical updates">
                    <i class="fas fa-arrows-rotate"></i> Refresh
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="news-hero">
        <div class="news-hero-badge">
            <i class="fas fa-tower-broadcast"></i> Verified Clinical Bulletin
        </div>
        <h1>Live Medical & Transfusion Science News</h1>
        <p>Continuous clinical updates, medical guidelines, and breakthrough hematology research verified by WHO, NBTC, ICMR, and global transfusion networks.</p>
    </section>

    <!-- Category Filters -->
    <div class="category-filter-wrap" id="categoryFilterWrap">
        <button class="filter-btn active" data-category="all">All News</button>
        <button class="filter-btn" data-category="Blood Safety & Protocols">Blood Safety</button>
        <button class="filter-btn" data-category="Clinical Research">Clinical Research</button>
        <button class="filter-btn" data-category="Emergency Network">Emergency Network</button>
        <button class="filter-btn" data-category="Platelet Preservation">Platelet Care</button>
        <button class="filter-btn" data-category="Donor Health">Donor Wellness</button>
        <button class="filter-btn" data-category="Screening Standards">Screening Standards</button>
    </div>

    <!-- News Grid Container -->
    <main class="news-container">
        <div class="news-grid" id="newsGrid">
            <!-- News Cards injected via JavaScript -->
        </div>
    </main>

    <!-- Modal for Detailed News -->
    <div class="modal-overlay" id="newsModal">
        <div class="modal-box" style="position:relative;">
            <button type="button" class="modal-close-btn" id="modalCloseBtn"><i class="fas fa-xmark"></i></button>
            <img src="" alt="News Image" class="modal-img" id="modalImg">
            <div class="modal-content">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <span class="category-tag" id="modalCategory">Category</span>
                    <span style="font-size:0.8rem;color:var(--muted);font-weight:600;" id="modalTime">Just now</span>
                </div>
                <h2 style="font-size:1.5rem;font-weight:800;color:var(--ink);margin-bottom:14px;line-height:1.35;" id="modalTitle"></h2>
                <div class="verified-stamp" style="margin-bottom:18px;" id="modalSource"><i class="fas fa-certificate"></i> Verified Source</div>
                <p style="font-size:1.02rem;color:var(--ink);line-height:1.7;" id="modalSummary"></p>
                <div style="margin-top:24px;padding-top:16px;border-top:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.82rem;color:var(--muted);"><i class="fas fa-shield-halved text-success me-1"></i> Verified by LifeFlow Clinical Council</span>
                    <button type="button" class="btn-header btn-back" onclick="document.getElementById('newsModal').classList.remove('active')">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-side News Controller -->
    <script>
        let allNews = [];
        let currentCategory = 'all';

        async function fetchNews() {
            const grid = document.getElementById('newsGrid');
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px 0;color:var(--muted);font-size:1.1rem;"><i class="fas fa-spinner fa-spin me-2 text-danger"></i> Fetching live medical updates...</div>';
            
            try {
                const res = await fetch('live.api.php', { cache: 'no-store' });
                const data = await res.json();
                if (data.success && Array.isArray(data.news)) {
                    allNews = data.news;
                    renderNews();
                } else {
                    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--red);padding:40px;">Unable to load medical news at this time.</div>';
                }
            } catch (err) {
                console.error("News fetch error:", err);
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--red);padding:40px;">Failed to reach medical news service.</div>';
            }
        }

        function renderNews() {
            const grid = document.getElementById('newsGrid');
            const filtered = (currentCategory === 'all') 
                ? allNews 
                : allNews.filter(n => n.category.toLowerCase().includes(currentCategory.toLowerCase()));

            if (!filtered.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted);">No news found in this category.</div>';
                return;
            }

            grid.innerHTML = filtered.map(item => `
                <article class="news-card">
                    <div class="news-card-img-wrap">
                        <img src="${item.image_url}" alt="${item.title}" class="news-card-img" loading="lazy">
                        <div class="news-card-badges">
                            <span class="badge-source"><i class="fas fa-circle-check text-success"></i> ${item.source}</span>
                            ${item.is_breaking ? '<span class="badge-breaking"><i class="fas fa-bolt"></i> BREAKING</span>' : ''}
                        </div>
                    </div>
                    <div class="news-card-body">
                        <div class="news-meta-row">
                            <span class="category-tag">${item.category}</span>
                            <span><i class="fas fa-clock me-1"></i> ${item.time_ago}</span>
                        </div>
                        <h3 class="news-title">${item.title}</h3>
                        <p class="news-summary">${item.summary}</p>
                        <div class="news-card-footer">
                            <div class="verified-stamp"><i class="fas fa-shield-virus"></i> Clinical Update</div>
                            <button type="button" class="btn-read-more" onclick="openNewsModal(${item.id})">
                                Read Report <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </article>
            `).join('');
        }

        function openNewsModal(id) {
            const item = allNews.find(n => n.id === id);
            if (!item) return;

            document.getElementById('modalImg').src = item.image_url;
            document.getElementById('modalCategory').textContent = item.category;
            document.getElementById('modalTime').textContent = item.time_ago;
            document.getElementById('modalTitle').textContent = item.title;
            document.getElementById('modalSource').innerHTML = `<i class="fas fa-certificate text-success me-1"></i> Source: <strong>${item.source}</strong> (${item.tag})`;
            document.getElementById('modalSummary').textContent = item.summary;

            document.getElementById('newsModal').classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchNews();

            // Refresh button
            document.getElementById('refreshNewsBtn').addEventListener('click', () => {
                fetchNews();
            });

            // Auto-refresh news every 5 minutes (300,000 ms)
            setInterval(fetchNews, 300000);

            // Filter button clicks
            const filterBtns = document.querySelectorAll('.filter-btn');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentCategory = this.getAttribute('data-category');
                    renderNews();
                });
            });

            // Modal close
            const modal = document.getElementById('newsModal');
            document.getElementById('modalCloseBtn').addEventListener('click', () => {
                modal.classList.remove('active');
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.remove('active');
            });
        });
    </script>
</body>
</html>