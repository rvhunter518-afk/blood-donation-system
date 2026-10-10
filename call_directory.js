/**
 * LifeFlow — Emergency Call Directory & Interactive In-App Dialer
 * Handles fetching registered members, card rendering, filtering, searching,
 * device phone dialing (tel: protocol), and live in-app calling simulation.
 */
(function () {
  'use strict';

  // Determine correct relative path to get_contacts.php based on URL depth
  const isSubfolder = window.location.pathname.includes('/user/') || window.location.pathname.includes('/donor/');
  const apiEndpoint = isSubfolder ? '../get_contacts.php' : 'get_contacts.php';

  let contactsData = [];
  let currentFilter = 'all';
  let searchTerm = '';
  let activeCallTimer = null;
  let callSeconds = 0;
  let audioContext = null;
  let ringToneInterval = null;
  let isMuted = false;

  // DOM Elements (created and injected dynamically)
  function injectCallDirectoryDOM() {
    if (document.getElementById('callDirectoryModal')) return;

    const modalHTML = `
      <!-- Emergency Call Directory Modal -->
      <div class="call-modal-backdrop" id="callDirectoryModal" role="dialog" aria-modal="true" aria-labelledby="callModalTitle">
        <div class="call-modal-dialog">
          <div class="call-modal-header">
            <div class="call-modal-title-wrap">
              <h3 id="callModalTitle"><i class="fas fa-phone-volume"></i> Registered Call Directory</h3>
              <p>Direct dial verified registered donors and blood requesters in LifeFlow</p>
            </div>
            <button type="button" class="call-modal-close-btn" id="closeCallModalBtn" aria-label="Close Directory">&times;</button>
          </div>

          <div class="call-modal-controls">
            <div class="directory-search-box">
              <i class="fas fa-magnifying-glass"></i>
              <input type="text" id="dirSearchInput" class="directory-search-input" placeholder="Search registered names, blood group (e.g. A+), city, or phone...">
            </div>
            <div class="directory-filter-tabs">
              <button type="button" class="dir-tab-btn active" data-filter="all">
                <i class="fas fa-users"></i> All Registered (<span id="countAll">0</span>)
              </button>
              <button type="button" class="dir-tab-btn" data-filter="donor">
                <i class="fas fa-shield-heart"></i> Donors (<span id="countDonors">0</span>)
              </button>
              <button type="button" class="dir-tab-btn" data-filter="user">
                <i class="fas fa-hand-holding-droplet"></i> Requesters (<span id="countUsers">0</span>)
              </button>
            </div>
          </div>

          <div class="call-modal-body">
            <div class="directory-cards-grid" id="directoryCardsContainer">
              <div class="directory-empty-state">
                <i class="fas fa-circle-notch fa-spin"></i>
                <strong>Loading verified contacts...</strong>
                <span>Fetching registered members from the LifeFlow network.</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- In-App Interactive Phone Calling Screen -->
      <div class="active-call-overlay" id="activeCallScreen" role="dialog" aria-modal="true">
        <div class="call-screen-card">
          <div class="calling-avatar-container">
            <div class="soundwave-ring"></div>
            <div class="soundwave-ring"></div>
            <div class="soundwave-ring"></div>
            <div class="call-center-avatar" id="callAvatarInitial">NR</div>
          </div>

          <span class="call-status-badge" id="callStatusBadge">Connecting dialer...</span>
          <h3 id="callTargetName">Contact Name</h3>
          <div class="call-phone-text" id="callTargetPhone">+91 00000 00000</div>
          <div class="call-timer-display" id="callDurationTimer">00:00</div>

          <div class="call-controls-row">
            <button type="button" class="call-control-circle" id="callMuteBtn" title="Toggle Mute">
              <i class="fas fa-microphone"></i>
            </button>
            <button type="button" class="call-control-circle end-call-btn" id="callEndBtn" title="End Call">
              <i class="fas fa-phone-slash"></i>
            </button>
            <button type="button" class="call-control-circle" id="callSpeakerBtn" title="Speaker">
              <i class="fas fa-volume-high"></i>
            </button>
          </div>

          <div>
            <a href="#" class="direct-tel-fallback-link" id="directTelLink">
              <i class="fas fa-mobile-screen"></i> Tap to open in phone dialer app
            </a>
          </div>
        </div>
      </div>

      <!-- Toast Feedback -->
      <div class="call-toast-notification" id="callToast">Phone number copied to clipboard!</div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHTML);
    bindModalEvents();
  }

  // Bind modal and control buttons
  function bindModalEvents() {
    const modal = document.getElementById('callDirectoryModal');
    const closeBtn = document.getElementById('closeCallModalBtn');
    const searchInput = document.getElementById('dirSearchInput');
    const filterTabs = document.querySelectorAll('.dir-tab-btn');

    closeBtn?.addEventListener('click', closeDirectoryModal);
    modal?.addEventListener('click', (e) => {
      if (e.target === modal) closeDirectoryModal();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeDirectoryModal();
        terminateActiveCall();
      }
    });

    searchInput?.addEventListener('input', (e) => {
      searchTerm = e.target.value.toLowerCase().trim();
      renderContactCards();
    });

    filterTabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        filterTabs.forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');
        currentFilter = tab.dataset.filter;
        renderContactCards();
      });
    });

    // In-Call Controls
    document.getElementById('callEndBtn')?.addEventListener('click', terminateActiveCall);
    document.getElementById('callMuteBtn')?.addEventListener('click', function () {
      isMuted = !isMuted;
      this.classList.toggle('active', isMuted);
      this.innerHTML = isMuted ? '<i class="fas fa-microphone-slash"></i>' : '<i class="fas fa-microphone"></i>';
      showToast(isMuted ? 'Microphone muted' : 'Microphone unmuted');
    });

    document.getElementById('callSpeakerBtn')?.addEventListener('click', function () {
      this.classList.toggle('active');
      showToast('Audio output toggled');
    });
  }

  // Open Directory Modal and load contacts
  function openDirectoryModal() {
    injectCallDirectoryDOM();
    const modal = document.getElementById('callDirectoryModal');
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    loadContactsFromAPI();
  }

  function closeDirectoryModal() {
    const modal = document.getElementById('callDirectoryModal');
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Fetch registered contacts from backend API
  async function loadContactsFromAPI() {
    const container = document.getElementById('directoryCardsContainer');
    if (!container) return;

    try {
      const response = await fetch(apiEndpoint, {
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Failed to load contacts directory.');
      }

      contactsData = data.contacts || [];

      // Update counters
      const countAll = document.getElementById('countAll');
      const countDonors = document.getElementById('countDonors');
      const countUsers = document.getElementById('countUsers');

      if (countAll) countAll.textContent = data.stats.total;
      if (countDonors) countDonors.textContent = data.stats.donors_count;
      if (countUsers) countUsers.textContent = data.stats.users_count;

      renderContactCards();
    } catch (err) {
      container.innerHTML = `
        <div class="directory-empty-state">
          <i class="fas fa-triangle-exclamation" style="color:#ef4444;"></i>
          <strong>Unable to load directory</strong>
          <span>${escapeHtml(err.message)}</span>
        </div>
      `;
    }
  }

  // Filter and render contact cards
  function renderContactCards() {
    const container = document.getElementById('directoryCardsContainer');
    if (!container) return;

    let filtered = contactsData;

    // Filter by role
    if (currentFilter === 'donor') {
      filtered = filtered.filter((c) => c.role === 'donor');
    } else if (currentFilter === 'user') {
      filtered = filtered.filter((c) => c.role === 'user');
    }

    // Filter by search query
    if (searchTerm) {
      filtered = filtered.filter((c) => {
        const nameMatch = (c.name || '').toLowerCase().includes(searchTerm);
        const bloodMatch = (c.blood_type || '').toLowerCase().includes(searchTerm);
        const cityMatch = (c.city || '').toLowerCase().includes(searchTerm);
        const phoneMatch = (c.phone || '').includes(searchTerm);
        return nameMatch || bloodMatch || cityMatch || phoneMatch;
      });
    }

    if (filtered.length === 0) {
      container.innerHTML = `
        <div class="directory-empty-state">
          <i class="fas fa-user-slash"></i>
          <strong>No registered contacts found</strong>
          <span>Try adjusting your search query or filter tab.</span>
        </div>
      `;
      return;
    }

    container.innerHTML = filtered
      .map((contact) => {
        const initials = getInitials(contact.name);
        const isDonor = contact.role === 'donor';
        const cleanPhone = (contact.phone || '').replace(/[^0-9+]/g, '');

        let urgentAlert = '';
        if (contact.has_active_need && contact.active_requests && contact.active_requests.length > 0) {
          const req = contact.active_requests[0];
          urgentAlert = `
            <div class="card-urgent-box">
              <i class="fas fa-triangle-exclamation"></i>
              <span>Urgent Need: <strong>${req.units} unit(s) ${escapeHtml(req.blood_type)}</strong> at ${escapeHtml(req.hospital)}</span>
            </div>
          `;
        }

        return `
          <div class="contact-card ${contact.is_self ? 'is-self' : ''}">
            <div>
              <div class="card-header-row">
                <div class="card-avatar-wrap">
                  <div class="card-avatar ${isDonor ? 'donor' : ''}">
                    ${initials}
                    <span class="status-dot" title="Registered & Active"></span>
                  </div>
                  <div class="card-user-info">
                    <h4>${escapeHtml(contact.name)} ${contact.is_self ? '<small style="color:#0284c7;">(You)</small>' : ''}</h4>
                    <span class="verified-tag"><i class="fas fa-circle-check"></i> Registered LifeFlow Member</span>
                  </div>
                </div>
                <span class="blood-pill">${escapeHtml(contact.blood_type)}</span>
              </div>

              <div style="margin: 6px 0;">
                <span class="role-badge ${isDonor ? 'donor' : 'user'}">
                  <i class="fas ${isDonor ? 'fa-shield-heart' : 'fa-hand-holding-droplet'}"></i>
                  ${contact.role_label}
                </span>
              </div>

              ${urgentAlert}

              <div class="card-meta-list">
                <div class="card-meta-item">
                  <i class="fas fa-location-dot"></i>
                  <span>${escapeHtml(contact.city)}</span>
                </div>
                <div class="card-meta-item">
                  <i class="fas fa-calendar-check"></i>
                  <span>Joined ${escapeHtml(contact.registered_date)}</span>
                </div>
              </div>

              <div class="card-phone-display">
                <span><i class="fas fa-phone"></i> ${escapeHtml(contact.phone)}</span>
                <small style="color:#64748b; font-weight:600;">Direct Dial</small>
              </div>
            </div>

            <div class="card-actions-row">
              ${contact.is_self ? '<span style="color:#64748b; font-size:0.82rem;">Your Profile</span>' : `
              <button type="button" class="btn-webrtc-call" style="padding:7px 14px; font-size:0.82rem;"
                      data-webrtc-call
                      data-receiver-id="${contact.id}"
                      data-receiver-name="${escapeHtml(contact.name)}"
                      data-blood-type="${escapeHtml(contact.blood_type)}"
                      data-role="${escapeHtml(contact.role_label)}"
                      data-call-type="audio"
                      title="Direct Website Audio Call">
                <i class="fas fa-phone"></i> Audio Call
              </button>
              <button type="button" class="btn-webrtc-call btn-video" style="padding:7px 14px; font-size:0.82rem;"
                      data-webrtc-call
                      data-receiver-id="${contact.id}"
                      data-receiver-name="${escapeHtml(contact.name)}"
                      data-blood-type="${escapeHtml(contact.blood_type)}"
                      data-role="${escapeHtml(contact.role_label)}"
                      data-call-type="video"
                      title="Direct Website Video Call">
                <i class="fas fa-video"></i> Video Call
              </button>
              `}
            </div>
          </div>
        `;
      })
      .join('');

    // Close directory dialog when starting a WebRTC call
    container.querySelectorAll('[data-webrtc-call]').forEach((btn) => {
      btn.addEventListener('click', function () {
        const dirModal = document.getElementById('callDirectoryModal');
        if (dirModal) dirModal.classList.remove('open');
      });
    });

    container.querySelectorAll('.btn-copy-phone').forEach((btn) => {
      btn.addEventListener('click', function () {
        const phone = this.dataset.phone;
        navigator.clipboard.writeText(phone).then(() => {
          showToast(`Copied ${phone} to clipboard!`);
        });
      });
    });
  }

  // Launch In-App Dialing Screen + Trigger System Phone Dial
  function initiateLiveCall(target) {
    const screen = document.getElementById('activeCallScreen');
    if (!screen) return;

    // Populate call details
    document.getElementById('callAvatarInitial').textContent = target.initials || 'LF';
    document.getElementById('callTargetName').textContent = target.name || 'LifeFlow Contact';
    document.getElementById('callTargetPhone').textContent = target.phone || '';
    document.getElementById('callStatusBadge').textContent = 'Dialing registered line...';
    document.getElementById('directTelLink').href = 'tel:' + target.phone;

    screen.classList.add('active');

    // Synthesize gentle telephone ringback sound (using Web Audio API)
    startSynthesizedDialTone();

    // Reset and start call timer
    callSeconds = 0;
    updateCallTimerDisplay();
    clearInterval(activeCallTimer);

    // After 2.5 seconds, simulate connected status
    setTimeout(() => {
      if (screen.classList.contains('active')) {
        document.getElementById('callStatusBadge').textContent = 'Connected • Call in progress';
        stopSynthesizedDialTone();
        activeCallTimer = setInterval(() => {
          callSeconds++;
          updateCallTimerDisplay();
        }, 1000);
      }
    }, 2400);

    // Also invoke standard tel: dialer protocol for mobile/device softphones
    window.location.href = 'tel:' + target.phone;
  }

  function terminateActiveCall() {
    const screen = document.getElementById('activeCallScreen');
    if (!screen || !screen.classList.contains('active')) return;

    clearInterval(activeCallTimer);
    stopSynthesizedDialTone();

    document.getElementById('callStatusBadge').textContent = `Call ended (${formatDuration(callSeconds)})`;

    // Play subtle hangup click
    playHangupTone();

    setTimeout(() => {
      screen.classList.remove('active');
    }, 900);
  }

  function updateCallTimerDisplay() {
    const timerEl = document.getElementById('callDurationTimer');
    if (timerEl) timerEl.textContent = formatDuration(callSeconds);
  }

  function formatDuration(sec) {
    const m = Math.floor(sec / 60)
      .toString()
      .padStart(2, '0');
    const s = (sec % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
  }

  // Web Audio API Ringback Synthesizer
  function startSynthesizedDialTone() {
    try {
      if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
      }
      if (audioContext.state === 'suspended') {
        audioContext.resume();
      }

      function ring() {
        if (!audioContext || isMuted) return;
        const osc1 = audioContext.createOscillator();
        const osc2 = audioContext.createOscillator();
        const gain = audioContext.createGain();

        // Standard US/international ringing tones: 440Hz + 480Hz
        osc1.frequency.value = 440;
        osc2.frequency.value = 480;

        gain.gain.setValueAtTime(0.08, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 1.2);

        osc1.connect(gain);
        osc2.connect(gain);
        gain.connect(audioContext.destination);

        osc1.start();
        osc2.start();
        osc1.stop(audioContext.currentTime + 1.2);
        osc2.stop(audioContext.currentTime + 1.2);
      }

      ring();
      clearInterval(ringToneInterval);
      ringToneInterval = setInterval(ring, 2600);
    } catch (e) {
      console.log('Audio synthesize notice:', e);
    }
  }

  function stopSynthesizedDialTone() {
    clearInterval(ringToneInterval);
  }

  function playHangupTone() {
    try {
      if (!audioContext) return;
      const osc = audioContext.createOscillator();
      const gain = audioContext.createGain();
      osc.frequency.value = 350;
      gain.gain.setValueAtTime(0.12, audioContext.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.15);
      osc.connect(gain);
      gain.connect(audioContext.destination);
      osc.start();
      osc.stop(audioContext.currentTime + 0.15);
    } catch (e) {}
  }

  function showToast(msg) {
    const toast = document.getElementById('callToast');
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.add('active');
    setTimeout(() => {
      toast.classList.remove('active');
    }, 2800);
  }

  function getInitials(name) {
    if (!name) return 'LF';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
  }

  // Initialize and attach to any existing header call buttons
  function init() {
    injectCallDirectoryDOM();

    // Attach to all elements with id="openCallDirectoryBtn" or class="header-call-btn"
    document.querySelectorAll('#openCallDirectoryBtn, .header-call-btn').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        openDirectoryModal();
      });
    });
  }

  // Public API
  window.LifeFlowCallDirectory = {
    open: openDirectoryModal,
    close: closeDirectoryModal,
    call: initiateLiveCall,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

