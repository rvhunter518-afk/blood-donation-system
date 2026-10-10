/**
 * assets/js/header_menu.js
 * Universal Slide-out Navigation Drawer & Header Controller for LifeFlow
 * Connects Menu button, Profile, Call Directory, and Logout for Donor & User views
 */

(function () {
    'use strict';

    window.BloodConnectHeader = {
        init: function (options) {
            options = options || {};
            const role = options.role || 'user'; // 'donor' | 'user'
            const userName = options.userName || '';
            const rootPath = options.rootPath || '../'; // relative path to root

            // Inject or bind drawer
            let backdrop = document.getElementById('navDrawerBackdrop');
            if (!backdrop) {
                backdrop = this.createDrawerDOM(role, userName, rootPath);
                document.body.appendChild(backdrop);
            }

            // Bind toggle buttons
            const menuBtns = document.querySelectorAll('.header-menu-btn, #headerMenuBtn, [data-action="open-nav-drawer"]');
            menuBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.openDrawer();
                });
            });

            // Bind close button
            const closeBtn = backdrop.querySelector('#drawerCloseBtn');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.closeDrawer());
            }

            // Click backdrop to close
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    this.closeDrawer();
                }
            });

            // ESC key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && backdrop.classList.contains('active')) {
                    this.closeDrawer();
                }
            });

            // Smooth scroll and auto-close on in-page anchor clicks
            backdrop.querySelectorAll('a[href^="#"]').forEach(link => {
                link.addEventListener('click', (e) => {
                    const targetId = link.getAttribute('href');
                    if (targetId && targetId !== '#') {
                        const targetEl = document.querySelector(targetId);
                        if (targetEl) {
                            e.preventDefault();
                            this.closeDrawer();
                            setTimeout(() => {
                                targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }, 250);
                        }
                    }
                });
            });

            // If user page or userName is empty, fetch profile asynchronously
            if (!userName || role === 'user') {
                this.loadUserProfile(rootPath);
            }

            // ── Profile Pill Dropdown ──
            this.initProfileDropdown(rootPath, role, userName);
        },

        openDrawer: function () {
            const backdrop = document.getElementById('navDrawerBackdrop');
            if (backdrop) {
                backdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        },

        closeDrawer: function () {
            const backdrop = document.getElementById('navDrawerBackdrop');
            if (backdrop) {
                backdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
        },

        loadUserProfile: async function (rootPath) {
            try {
                const resp = await fetch(rootPath + 'api/me.php');
                if (!resp.ok) return;
                const data = await resp.json();
                if (data && data.authenticated && data.user_name) {
                    // Update header profile pill if exists
                    const nameEls = document.querySelectorAll('.header-profile-pill .profile-name, #headerUserName');
                    nameEls.forEach(el => {
                        el.textContent = data.user_name;
                    });

                    // Update drawer user info
                    const drawerName = document.getElementById('drawerUserName');
                    if (drawerName) drawerName.textContent = data.user_name;

                    const drawerRole = document.getElementById('drawerUserRole');
                    if (drawerRole) {
                        drawerRole.textContent = (data.user_role === 'donor') ? 'Verified Donor' : 'Requester / Patient';
                    }

                    const drawerBlood = document.getElementById('drawerUserBlood');
                    if (drawerBlood && data.blood_type) {
                        drawerBlood.textContent = data.blood_type;
                        drawerBlood.style.display = 'inline-block';
                    }
                }
            } catch (err) {
                console.warn('Could not fetch current session user info:', err);
            }
        },

        createDrawerDOM: function (role, userName, rootPath) {
            const backdrop = document.createElement('div');
            backdrop.id = 'navDrawerBackdrop';
            backdrop.className = 'nav-drawer-backdrop';

            const isDonor = (role === 'donor');
            const displayName = userName || (isDonor ? 'Verified Donor' : 'Blood Requester');
            const roleLabel = isDonor ? 'Active Blood Donor' : 'Blood Requester';

            backdrop.innerHTML = `
                <aside class="nav-drawer-panel" role="dialog" aria-modal="true" aria-label="Navigation Menu">
                    <div class="drawer-header">
                        <div class="drawer-brand">
                            <div class="drawer-brand-icon"><i class="fas fa-droplet"></i></div>
                            <div class="drawer-brand-text">Life<span>Flow</span></div>
                        </div>
                        <button type="button" class="drawer-close-btn" id="drawerCloseBtn" title="Close Menu (Esc)">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>

                    <div class="drawer-body">
                        <!-- User Profile Card -->
                        <div class="drawer-user-card" onclick="window.location.href='${rootPath}menu.html'" style="cursor:pointer;" title="View & Edit Profile">
                            <div class="drawer-user-avatar">
                                <i class="${isDonor ? 'fas fa-hand-holding-heart' : 'fas fa-user-circle'}"></i>
                            </div>
                            <div class="drawer-user-info">
                                <div class="drawer-user-name" id="drawerUserName">${displayName}</div>
                                <div class="drawer-user-sub">
                                    <span class="drawer-user-badge" id="drawerUserRole">${roleLabel}</span>
                                    <span class="drawer-badge-pill green" id="drawerUserBlood" style="display:none;"></span>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right drawer-arrow" style="margin-left:auto;color:#d62839;"></i>
                        </div>

                        <!-- 1. Real-Time Communications -->
                        <div>
                            <div class="drawer-section-label"><i class="fas fa-phone-volume"></i> Real-time Calling</div>
                            <div class="drawer-menu-grid">
                                <a href="call_history.php" class="drawer-nav-item">
                                    <div class="drawer-item-icon"><i class="fas fa-phone-volume"></i></div>
                                    <div class="drawer-item-content">
                                        <div class="drawer-item-title">
                                            <span>Call History & Logs</span>
                                            <span class="drawer-badge-pill">WebRTC</span>
                                        </div>
                                        <div class="drawer-item-desc">Website-to-website audio & video call records</div>
                                    </div>
                                    <i class="fas fa-chevron-right drawer-arrow"></i>
                                </a>
                                <a href="javascript:void(0)" onclick="BloodConnectHeader.closeDrawer(); const d=document.getElementById('openCallDirectoryBtn'); if(d) d.click();" class="drawer-nav-item">
                                    <div class="drawer-item-icon"><i class="fas fa-address-book"></i></div>
                                    <div class="drawer-item-content">
                                        <div class="drawer-item-title">
                                            <span>Call Directory</span>
                                            <span class="drawer-badge-pill green">Instant</span>
                                        </div>
                                        <div class="drawer-item-desc">Speed dial online verified donors & users</div>
                                    </div>
                                    <i class="fas fa-chevron-right drawer-arrow"></i>
                                </a>
                            </div>
                        </div>

                        <!-- 2. Academic Library & Quizzes (15-20 Page Books) -->
                        <div>
                            <div class="drawer-section-label"><i class="fas fa-book-open"></i> Learning & Academy</div>
                            <div class="drawer-menu-grid">
                                <a href="${rootPath}library.php" class="drawer-nav-item">
                                    <div class="drawer-item-icon"><i class="fas fa-book-bookmark"></i></div>
                                    <div class="drawer-item-content">
                                        <div class="drawer-item-title">
                                            <span>Book Library</span>
                                            <span class="drawer-badge-pill">105 Books</span>
                                        </div>
                                        <div class="drawer-item-desc">Hematology & tech catalog (16 pages each, instant reader)</div>
                                    </div>
                                    <i class="fas fa-chevron-right drawer-arrow"></i>
                                </a>
                                <a href="${rootPath}learning_dashboard.php" class="drawer-nav-item">
                                    <div class="drawer-item-icon"><i class="fas fa-graduation-cap"></i></div>
                                    <div class="drawer-item-content">
                                        <div class="drawer-item-title">
                                            <span>Learning Dashboard</span>
                                            <span class="drawer-badge-pill green">Streak</span>
                                        </div>
                                        <div class="drawer-item-desc">Study streak, XP badges unlocked & evaluation stats</div>
                                    </div>
                                    <i class="fas fa-chevron-right drawer-arrow"></i>
                                </a>
                                <a href="${rootPath}leaderboard.php" class="drawer-nav-item">
                                    <div class="drawer-item-icon"><i class="fas fa-trophy"></i></div>
                                    <div class="drawer-item-content">
                                        <div class="drawer-item-title">
                                            <span>Global Leaderboard</span>
                                            <span class="drawer-badge-pill">XP Rank</span>
                                        </div>
                                        <div class="drawer-item-desc">Top LifeFlow donors and certified learners</div>
                                    </div>
                                    <i class="fas fa-chevron-right drawer-arrow"></i>
                                </a>
                                <a href="${rootPath}schemes.php" class="drawer-nav-item">
                                    <div class="drawer-item-icon"><i class="fas fa-landmark"></i></div>
                                    <div class="drawer-item-content">
                                        <div class="drawer-item-title">
                                            <span>Government Schemes</span>
                                            <span class="drawer-badge-pill">Benefits</span>
                                        </div>
                                        <div class="drawer-item-desc">Health insurance, medical aid & public benefits</div>
                                    </div>
                                    <i class="fas fa-chevron-right drawer-arrow"></i>
                                </a>
                            </div>
                        </div>

                                <!-- 3. Page-Specific Network Operations -->
                        <div>
                            <div class="drawer-section-label"><i class="fas fa-shield-heart"></i> Blood Network Operations</div>
                            <div class="drawer-menu-grid">
                                ${isDonor ? `
                                    <a href="${rootPath}map.php?view=requests" class="drawer-nav-item" style="border-left: 3px solid #e63946;">
                                        <div class="drawer-item-icon" style="color: #e63946;"><i class="fas fa-map-location-dot"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">
                                                <span>Live Requests Map</span>
                                                <span class="drawer-badge-pill" style="background:#fee2e2;color:#dc2626;">Live GPS</span>
                                            </div>
                                            <div class="drawer-item-desc">See patient blood requests & hospitals near you</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                    <a href="#donate" class="drawer-nav-item">
                                        <div class="drawer-item-icon"><i class="fas fa-hand-holding-droplet"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">Donate Blood</div>
                                            <div class="drawer-item-desc">Record a donation or schedule appointment</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                    <a href="#compatibility" class="drawer-nav-item">
                                        <div class="drawer-item-icon"><i class="fas fa-dna"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">Compatibility Matrix</div>
                                            <div class="drawer-item-desc">Check ABO & Rh recipient matching rules</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                    <a href="#features" class="drawer-nav-item">
                                        <div class="drawer-item-icon"><i class="fas fa-bolt"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">Platform Features</div>
                                            <div class="drawer-item-desc">Rapid dispatch & real-time telemetry</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                    <a href="#contact" class="drawer-nav-item">
                                        <div class="drawer-item-icon"><i class="fas fa-headset"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">Emergency Contact</div>
                                            <div class="drawer-item-desc">24/7 LifeFlow medical emergency desk</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                ` : `
                                    <a href="${rootPath}map.php?view=donors" class="drawer-nav-item" style="border-left: 3px solid #10b981;">
                                        <div class="drawer-item-icon" style="color: #10b981;"><i class="fas fa-map-location-dot"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">
                                                <span>Find Donors Map</span>
                                                <span class="drawer-badge-pill green">Nearby</span>
                                            </div>
                                            <div class="drawer-item-desc">Interactive live map of active blood donors</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                    <a href="#request-title" class="drawer-nav-item">
                                        <div class="drawer-item-icon"><i class="fas fa-paper-plane"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">New Blood Request</div>
                                            <div class="drawer-item-desc">Broadcast emergency blood request to donors</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                    <a href="#sidePanel" class="drawer-nav-item">
                                        <div class="drawer-item-icon"><i class="fas fa-magnifying-glass"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">Search Donors</div>
                                            <div class="drawer-item-desc">Find active verified donors by blood type & city</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                    <a href="#sidePanel" class="drawer-nav-item">
                                        <div class="drawer-item-icon"><i class="fas fa-clock-rotate-left"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">Your Activity</div>
                                            <div class="drawer-item-desc">Track status of past blood requests</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                    <a href="#sidePanel" class="drawer-nav-item">
                                        <div class="drawer-item-icon"><i class="fas fa-circle-question"></i></div>
                                        <div class="drawer-item-content">
                                            <div class="drawer-item-title">How It Works</div>
                                            <div class="drawer-item-desc">Safe verified matching & safety protocols</div>
                                        </div>
                                        <i class="fas fa-chevron-right drawer-arrow"></i>
                                    </a>
                                `}
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Footer with Logout -->
                    <div class="drawer-footer">
                        <a href="${rootPath}logout.php" class="drawer-footer-btn drawer-btn-logout">
                            <i class="fas fa-arrow-right-from-bracket"></i>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </aside>
            `;

            return backdrop;
        },

        /* ── Profile Pill Dropdown ── */
        initProfileDropdown: function (rootPath, role, userName) {
            const pills = document.querySelectorAll('#headerUserProfile, .header-profile-pill');
            if (!pills || pills.length === 0) return;

            // Build dropdown DOM once
            let drop = document.getElementById('bloodProfileDrop');
            if (!drop) {
                drop = document.createElement('div');
                drop.id = 'bloodProfileDrop';
                drop.className = 'profile-dropdown';
                const defaultMapView = (role === 'donor') ? 'requests' : 'donors';
                drop.innerHTML = `
                    <div class="profile-dropdown-hdr" onclick="window.location.href='${rootPath}menu.html'" style="cursor:pointer;" title="Open My Profile">
                        <div class="profile-drop-av" id="dropAv">?</div>
                        <div class="profile-drop-info">
                            <div class="pdi-name" id="dropName">${userName || 'Loading…'}</div>
                            <div class="pdi-role" id="dropRole">${role === 'donor' ? 'Verified Donor' : 'Blood Requester'}</div>
                        </div>
                        <i class="fas fa-chevron-right" style="margin-left:auto;color:#d62839;font-size:0.8rem;"></i>
                    </div>
                    <a class="profile-drop-item" href="${rootPath}menu.html">
                        <i class="fas fa-id-card"></i> My Profile
                    </a>
                    <a class="profile-drop-item" href="${rootPath}map.php?view=${defaultMapView}">
                        <i class="fas fa-map-location-dot"></i> Live Map
                    </a>
                    <a class="profile-drop-item" href="${rootPath}message/message.html">
                        <i class="fas fa-comments"></i> Messages
                    </a>
                    <a class="profile-drop-item" href="${rootPath}donor/call_history.php">
                        <i class="fas fa-phone-volume"></i> Call History
                    </a>
                    <a class="profile-drop-item logout" href="${rootPath}logout.php">
                        <i class="fas fa-arrow-right-from-bracket"></i> Sign Out
                    </a>
                `;
                document.body.appendChild(drop);
            }

            if (userName) {
                const initials = userName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
                const av = document.getElementById('dropAv');
                if (av) av.textContent = initials || 'D';
            }

            // Bind click to each pill
            pills.forEach(pill => {
                pill.style.cursor = 'pointer';
                pill.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const rect = pill.getBoundingClientRect();
                    drop.style.top = (rect.bottom + 8) + 'px';
                    drop.style.right = Math.max(16, (window.innerWidth - rect.right)) + 'px';
                    drop.style.left = 'auto';
                    drop.classList.toggle('open');
                });
            });

            // Close on outside click
            document.addEventListener('click', (e) => {
                let clickedInsidePill = false;
                pills.forEach(pill => {
                    if (pill.contains(e.target)) clickedInsidePill = true;
                });
                if (!clickedInsidePill && !drop.contains(e.target)) {
                    drop.classList.remove('open');
                }
            });

            // Close on ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') drop.classList.remove('open');
            });

            // Populate with current user info from API
            fetch(rootPath + 'api/me.php').then(r => r.json()).then(data => {
                if (!data || !data.authenticated) return;
                const initials = (data.user_name || 'U').split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();
                const roleLabel = data.user_role === 'donor' ? 'Verified Donor' : data.user_role === 'hospital' ? 'Hospital' : 'Blood Requester';
                const av = document.getElementById('dropAv');
                const nm = document.getElementById('dropName');
                const rl = document.getElementById('dropRole');
                if (av) av.textContent = initials;
                if (nm) nm.textContent = data.user_name;
                if (rl) rl.textContent = roleLabel;

                const profileNames = document.querySelectorAll('.header-profile-pill .profile-name, #headerUserName');
                profileNames.forEach(pn => {
                    pn.textContent = data.user_name;
                });
            }).catch(() => {});
        }
    };
})();

