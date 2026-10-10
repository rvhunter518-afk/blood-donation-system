/**
 * BloodConnect — In-Website WebRTC Audio & Video Calling Engine
 * assets/js/call.js
 *
 * Provides real-time browser-to-browser audio and video calling
 * using WebRTC RTCPeerConnection, STUN, and AJAX signaling.
 * Modeled after WhatsApp Web & Instagram Call experience.
 */

(function () {
    'use strict';

    // ── Relative API base path helper ───────────────────────────
    function getApiBase() {
        const path = window.location.pathname;
        if (path.includes('/user/') || path.includes('/donor/') || path.includes('/admin/')) {
            return '../api/calls/';
        }
        return 'api/calls/';
    }

    // ── CSRF token helper (with dynamic token.php fallback) ───
    let cachedCsrfToken = '';
    async function getCsrfToken() {
        if (cachedCsrfToken) return cachedCsrfToken;
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.getAttribute('content')) {
            cachedCsrfToken = meta.getAttribute('content');
            return cachedCsrfToken;
        }
        try {
            const resp = await fetch(getApiBase() + 'token.php');
            const data = await resp.json();
            if (data && data.token) {
                cachedCsrfToken = data.token;
                return cachedCsrfToken;
            }
        } catch (_) {}
        return '';
    }

    // ── HTML escape helper ──────────────────────────────────────
    function esc(str) {
        return String(str || '').replace(/[&<>"']/g, c =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c])
        );
    }

    // ── STUN Configuration (Google Public STUN) ─────────────────
    const RTC_CONFIG = {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' }
        ]
    };

    // ── Web Audio Dual-Tone Ringtone & Dialtone Generator ───────
    class AudioToneGenerator {
        constructor() {
            this.ctx = null;
            this.interval = null;
            this.isPlaying = false;
        }

        initContext() {
            try {
                if (!this.ctx || this.ctx.state === 'closed') {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (AudioCtx) this.ctx = new AudioCtx();
                }
                if (this.ctx && this.ctx.state === 'suspended') {
                    this.ctx.resume().catch(() => {});
                }
            } catch (_) {}
        }

        // Realistic incoming phone ring (440Hz + 480Hz dual tone)
        playIncomingRing() {
            this.stop();
            this.initContext();
            if (!this.ctx) return;
            this.isPlaying = true;

            const ringBurst = () => {
                if (!this.isPlaying || !this.ctx) return;
                try {
                    const now = this.ctx.currentTime;
                    const osc1 = this.ctx.createOscillator();
                    const osc2 = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();

                    osc1.type = 'sine';
                    osc2.type = 'sine';
                    osc1.frequency.value = 440;
                    osc2.frequency.value = 480;

                    gain.gain.setValueAtTime(0.001, now);
                    gain.gain.exponentialRampToValueAtTime(0.18, now + 0.05);
                    gain.gain.setValueAtTime(0.18, now + 1.8);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 1.95);

                    osc1.connect(gain);
                    osc2.connect(gain);
                    gain.connect(this.ctx.destination);

                    osc1.start(now);
                    osc2.start(now);
                    osc1.stop(now + 2.0);
                    osc2.stop(now + 2.0);
                } catch (_) {}
            };

            ringBurst();
            this.interval = setInterval(ringBurst, 3500);
        }

        // Outgoing dialtone (440Hz + 480Hz: 1.2s on, 2.8s off)
        playOutgoingDialtone() {
            this.stop();
            this.initContext();
            if (!this.ctx) return;
            this.isPlaying = true;

            const dialBurst = () => {
                if (!this.isPlaying || !this.ctx) return;
                try {
                    const now = this.ctx.currentTime;
                    const osc1 = this.ctx.createOscillator();
                    const osc2 = this.ctx.createOscillator();
                    const gain = this.ctx.createGain();

                    osc1.type = 'sine';
                    osc2.type = 'sine';
                    osc1.frequency.value = 440;
                    osc2.frequency.value = 480;

                    gain.gain.setValueAtTime(0.001, now);
                    gain.gain.exponentialRampToValueAtTime(0.10, now + 0.04);
                    gain.gain.setValueAtTime(0.10, now + 1.1);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + 1.2);

                    osc1.connect(gain);
                    osc2.connect(gain);
                    gain.connect(this.ctx.destination);

                    osc1.start(now);
                    osc2.start(now);
                    osc1.stop(now + 1.25);
                    osc2.stop(now + 1.25);
                } catch (_) {}
            };

            dialBurst();
            this.interval = setInterval(dialBurst, 3200);
        }

        stop() {
            this.isPlaying = false;
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        }
    }

    // ── Toast Notification Helper ───────────────────────────────
    function showToast(message, type = 'info') {
        let container = document.getElementById('webrtcToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'webrtcToastContainer';
            document.body.appendChild(container);
        }

        const icons = {
            success: 'fa-circle-check',
            danger:  'fa-circle-xmark',
            error:   'fa-circle-xmark',
            warning: 'fa-triangle-exclamation',
            info:    'fa-circle-info'
        };

        const toast = document.createElement('div');
        toast.className = `webrtc-toast toast-${type}`;
        toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i> <span>${esc(message)}</span>`;
        container.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('show'));
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 350);
        }, 4000);
    }

    // ── Time Formatter (MM:SS) ──────────────────────────────────
    function formatTime(seconds) {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    }

    // ── WebRTC Calling Engine ───────────────────────────────────
    const WebRTCCallManager = {
        // Active Connection & Media
        peerConnection: null,
        localStream: null,
        remoteStream: null,
        tones: new AudioToneGenerator(),

        // Call State
        activeCallId: null,
        activePeerId: null,
        activePeerName: 'User',
        activeCallType: 'audio',
        isCaller: false,
        isMuted: false,
        isVideoActive: false,
        callDurationSec: 0,
        callTimerInterval: null,
        signalingInterval: null,
        heartbeatInterval: null,
        incomingCallData: null,
        processedSignalIds: new Set(),

        // Cached DOM Elements
        elements: {},

        init() {
            this.cacheElements();
            this.bindEvents();
            this.startPresenceHeartbeat();

            // Request browser notification permission if not yet decided
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().catch(() => {});
            }

            console.log('[WebRTC] BloodConnect WebRTC Call System ready.');
        },

        cacheElements() {
            this.elements = {
                incomingModal:         document.getElementById('webrtcIncomingModal'),
                incomingCallerName:    document.getElementById('incomingCallerName'),
                incomingCallerRole:    document.getElementById('incomingCallerRole'),
                incomingBloodType:     document.getElementById('incomingBloodType'),
                incomingCallTypeBadge: document.getElementById('incomingCallTypeBadge'),
                btnAcceptCall:         document.getElementById('btnAcceptCall'),
                btnDeclineCall:        document.getElementById('btnDeclineCall'),

                activeModal:           document.getElementById('webrtcActiveCallModal'),
                activeCallTypeLabel:   document.getElementById('activeCallTypeLabel'),
                activePeerName:        document.getElementById('activePeerName'),
                activePeerMeta:        document.getElementById('activePeerMeta'),
                activeCallTimer:       document.getElementById('activeCallTimer'),
                activeCallStatusText:  document.getElementById('activeCallStatusText'),
                activeAudioAvatar:     document.getElementById('activeAudioAvatar'),
                audioCallMain:         document.getElementById('audioCallMain'),

                videoGridContainer:    document.getElementById('videoGridContainer'),
                remoteVideo:           document.getElementById('remoteVideo'),
                localVideo:            document.getElementById('localVideo'),
                remoteAudio:           document.getElementById('remoteAudio'),

                btnToggleMic:          document.getElementById('btnToggleMic'),
                btnToggleVideo:        document.getElementById('btnToggleVideo'),
                btnEndActiveCall:      document.getElementById('btnEndActiveCall'),
                btnMinimizeCall:       document.getElementById('btnMinimizeCall'),
                toastContainer:        document.getElementById('webrtcToastContainer')
            };

            // Force hide modals on initialization
            if (this.elements.incomingModal) {
                this.elements.incomingModal.style.display = 'none';
                this.elements.incomingModal.classList.remove('show');
            }
            if (this.elements.activeModal) {
                this.elements.activeModal.style.display = 'none';
                this.elements.activeModal.classList.remove('show');
            }
        },

        bindEvents() {
            // Incoming Call Action Handlers
            this.elements.btnAcceptCall?.addEventListener('click', () => this.acceptIncomingCall());
            this.elements.btnDeclineCall?.addEventListener('click', () => this.declineIncomingCall());

            // Active Call Controls
            this.elements.btnToggleMic?.addEventListener('click', () => this.toggleMicrophone());
            this.elements.btnToggleVideo?.addEventListener('click', () => this.toggleVideo());
            this.elements.btnEndActiveCall?.addEventListener('click', () => this.endCurrentCall('user_hangup'));
            this.elements.btnMinimizeCall?.addEventListener('click', () => {
                this.elements.activeModal?.classList.toggle('minimized');
            });

            // Delegate click for [data-webrtc-call] buttons anywhere on the document
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-webrtc-call]');
                if (!btn) return;
                e.preventDefault();

                const receiverId   = parseInt(btn.dataset.receiverId, 10);
                const receiverName = btn.dataset.receiverName || 'Member';
                const bloodType    = btn.dataset.bloodType || '';
                const role         = btn.dataset.role || 'Donor';
                const callType     = btn.dataset.callType || 'audio';
                const contextType  = btn.dataset.contextType || 'blood_request';
                const contextId    = btn.dataset.contextId ? parseInt(btn.dataset.contextId, 10) : null;

                if (receiverId) {
                    this.startCall(receiverId, receiverName, bloodType, role, callType, contextType, contextId);
                }
            });
        },

        // ── 1. Acquire Local Media Streams ──────────────────────────
        async acquireMedia(withVideo = false) {
            this.stopLocalMedia();
            try {
                const constraints = {
                    audio: {
                        echoCancellation: true,
                        noiseSuppression: true,
                        autoGainControl: true
                    },
                    video: withVideo ? { width: { ideal: 1280 }, height: { ideal: 720 } } : false
                };

                this.localStream = await navigator.mediaDevices.getUserMedia(constraints);

                if (withVideo && this.elements.localVideo) {
                    this.elements.localVideo.srcObject = this.localStream;
                    this.elements.localVideo.play().catch(() => {});
                }
                return this.localStream;
            } catch (err) {
                console.error('[WebRTC] getUserMedia error:', err);
                if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
                    showToast(withVideo ? 'Camera/Microphone permission denied.' : 'Microphone permission is required for audio call.', 'danger');
                } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
                    showToast(withVideo ? 'Camera or microphone not found.' : 'Microphone device not found.', 'danger');
                } else {
                    showToast('Media access error: ' + err.message, 'danger');
                }
                throw err;
            }
        },

        stopLocalMedia() {
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    try { track.stop(); } catch (_) {}
                });
                this.localStream = null;
            }
            if (this.elements.localVideo) this.elements.localVideo.srcObject = null;
            if (this.elements.remoteVideo) this.elements.remoteVideo.srcObject = null;
            if (this.elements.remoteAudio) this.elements.remoteAudio.srcObject = null;
        },

        // ── 2. Create RTCPeerConnection ─────────────────────────────
        createPeerConnection() {
            if (this.peerConnection) {
                try { this.peerConnection.close(); } catch (_) {}
                this.peerConnection = null;
            }

            const pc = new RTCPeerConnection(RTC_CONFIG);
            this.peerConnection = pc;

            // Add local tracks to peer connection
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    pc.addTrack(track, this.localStream);
                });
            }

            // Handle incoming remote media stream tracks
            pc.ontrack = (event) => {
                console.log('[WebRTC] Remote track received:', event.track.kind);
                if (!this.remoteStream) {
                    this.remoteStream = new MediaStream();
                }
                this.remoteStream.addTrack(event.track);

                if (this.elements.remoteAudio) {
                    this.elements.remoteAudio.srcObject = this.remoteStream;
                    this.elements.remoteAudio.play().catch(() => {});
                }

                if (this.elements.remoteVideo && event.track.kind === 'video') {
                    this.elements.remoteVideo.srcObject = this.remoteStream;
                    this.elements.remoteVideo.play().catch(() => {});
                }
            };

            // Handle local ICE candidates
            pc.onicecandidate = (event) => {
                if (event.candidate && this.activeCallId) {
                    this.sendSignal('candidate', {
                        candidate: event.candidate.candidate,
                        sdpMid: event.candidate.sdpMid,
                        sdpMLineIndex: event.candidate.sdpMLineIndex
                    });
                }
            };

            // Handle connection state changes
            pc.onconnectionstatechange = () => {
                const state = pc.connectionState;
                console.log('[WebRTC] PeerConnection state:', state);

                if (state === 'connected') {
                    this.onCallConnected();
                } else if (state === 'disconnected' || state === 'failed') {
                    showToast('Connection interrupted.', 'warning');
                    this.endCurrentCall('connection_lost');
                }
            };

            return pc;
        },

        // ── 3. Start Outbound Call (Caller Flow) ────────────────────
        async startCall(receiverId, receiverName, bloodType, role, callType = 'audio', contextType = 'blood_request', contextId = null) {
            if (this.activeCallId) {
                showToast('You already have a call in progress.', 'warning');
                return;
            }

            this.isCaller = true;
            this.activePeerId = receiverId;
            this.activePeerName = receiverName;
            this.activeCallType = (callType === 'video') ? 'video' : 'audio';
            this.isVideoActive = (callType === 'video');
            this.isMuted = false;
            this.processedSignalIds.clear();

            showToast(`Calling ${receiverName}...`, 'info');

            // 1. Request microphone / camera
            try {
                await this.acquireMedia(this.isVideoActive);
            } catch (err) {
                return;
            }

            // 2. Open Active Call UI in "Calling..." state
            this.openActiveCallModal(
                receiverName,
                `${role} • ${bloodType || 'Compatible'}`,
                this.activeCallType,
                'Calling...'
            );
            this.tones.playOutgoingDialtone();

            // 3. Initiate call session with backend
            try {
                const apiBase = getApiBase();
                const csrf = await getCsrfToken();
                const resp = await fetch(apiBase + 'initiate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({
                        receiver_user_id: receiverId,
                        call_type: this.activeCallType,
                        context_type: contextType,
                        context_id: contextId,
                        csrf_token: csrf
                    })
                });

                const data = await resp.json();
                if (!data.success) {
                    this.tones.stop();
                    showToast(data.error || 'Unable to place call.', 'danger');
                    this.endCurrentCall('initiate_failed');
                    return;
                }

                this.activeCallId = data.call_id;

                // 4. Create PeerConnection & send offer
                this.createPeerConnection();
                await this.createAndSendOffer();

                // 5. Start polling for signals and call status
                this.startSignalingLoop();

            } catch (err) {
                console.error('[WebRTC] Initiate error:', err);
                this.tones.stop();
                showToast('Network error while placing call.', 'danger');
                this.endCurrentCall('error');
            }
        },

        // ── 4. Create and Send SDP Offer ────────────────────────────
        async createAndSendOffer() {
            if (!this.peerConnection) return;
            try {
                const offer = await this.peerConnection.createOffer({
                    offerToReceiveAudio: true,
                    offerToReceiveVideo: this.isVideoActive
                });
                await this.peerConnection.setLocalDescription(offer);
                console.log('[WebRTC] SDP Offer created.');

                await this.sendSignal('offer', {
                    type: offer.type,
                    sdp: offer.sdp
                });
            } catch (err) {
                console.error('[WebRTC] Error creating offer:', err);
            }
        },

        // ── 5. Accept Incoming Call (Receiver Flow) ─────────────────
        async acceptIncomingCall() {
            if (!this.incomingCallData) return;
            const call = this.incomingCallData;

            this.tones.stop();
            if (this.elements.incomingModal) {
                this.elements.incomingModal.classList.remove('show');
                this.elements.incomingModal.style.display = 'none';
            }

            this.activeCallId = call.call_id;
            this.activePeerId = call.caller.id;
            this.activePeerName = call.caller.name;
            this.activeCallType = call.call_type;
            this.isCaller = false;
            this.isVideoActive = (call.call_type === 'video');
            this.isMuted = false;
            this.processedSignalIds.clear();

            // 1. Acquire media
            try {
                await this.acquireMedia(this.isVideoActive);
            } catch (err) {
                this.declineIncomingCall();
                return;
            }

            // 2. Open active call modal in "Connecting..." state
            this.openActiveCallModal(
                call.caller.name,
                `${call.caller.role} • ${call.caller.blood_type}`,
                call.call_type,
                'Connecting...'
            );

            // 3. Notify backend of acceptance
            try {
                const apiBase = getApiBase();
                const csrf = await getCsrfToken();
                const resp = await fetch(apiBase + 'accept.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({
                        call_id: call.call_id,
                        csrf_token: csrf
                    })
                });
                const res = await resp.json();
                if (!res.success) {
                    showToast(res.error || 'Could not accept call.', 'danger');
                    this.endCurrentCall('accept_failed');
                    return;
                }

                // 4. Setup PeerConnection
                this.createPeerConnection();

                // 5. Start signaling loop to receive offer and exchange candidates
                this.startSignalingLoop();

            } catch (err) {
                console.error('[WebRTC] Accept error:', err);
                showToast('Failed to accept call: ' + err.message, 'danger');
                this.endCurrentCall('error');
            }
        },

        // ── 6. Decline Incoming Call ────────────────────────────────
        async declineIncomingCall() {
            this.tones.stop();
            if (this.elements.incomingModal) {
                this.elements.incomingModal.classList.remove('show');
                this.elements.incomingModal.style.display = 'none';
            }

            if (this.incomingCallData) {
                const callId = this.incomingCallData.call_id;
                this.incomingCallData = null;
                try {
                    const apiBase = getApiBase();
                    const csrf = await getCsrfToken();
                    await fetch(apiBase + 'reject.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({ call_id: callId, reason: 'rejected', csrf_token: csrf })
                    });
                } catch (_) {}
            }
        },

        // ── 7. Send Signal Packet to Backend ────────────────────────
        async sendSignal(type, payload) {
            if (!this.activeCallId) return;
            try {
                const apiBase = getApiBase();
                const csrf = await getCsrfToken();
                await fetch(apiBase + 'signal.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({
                        call_id: this.activeCallId,
                        type: type,
                        payload: payload,
                        csrf_token: csrf
                    })
                });
            } catch (e) {
                console.warn('[WebRTC] sendSignal error:', e);
            }
        },

        // ── 8. Signaling Loop (Polls Signals and Status) ────────────
        startSignalingLoop() {
            this.stopSignalingLoop();

            const pollIntervalMs = 1000;
            this.signalingInterval = setInterval(async () => {
                if (!this.activeCallId) return;

                const apiBase = getApiBase();
                try {
                    // Poll pending signals
                    const resp = await fetch(`${apiBase}signal.php?call_id=${encodeURIComponent(this.activeCallId)}`);
                    const data = await resp.json();

                    if (data.success && Array.isArray(data.signals)) {
                        for (const sig of data.signals) {
                            if (!this.processedSignalIds.has(sig.id)) {
                                this.processedSignalIds.add(sig.id);
                                await this.handleIncomingSignal(sig);
                            }
                        }
                    }

                    // Poll status for termination checks
                    const stResp = await fetch(`${apiBase}status.php?call_id=${encodeURIComponent(this.activeCallId)}`);
                    const stData = await stResp.json();
                    if (stData.success) {
                        const terminal = ['ended', 'rejected', 'busy', 'missed', 'failed'];
                        if (terminal.includes(stData.status)) {
                            this.tones.stop();
                            showToast(`Call ended (${stData.status}).`, 'info');
                            this.endCurrentCall('remote_ended');
                        } else if (stData.status === 'accepted' && this.isCaller) {
                            this.tones.stop();
                            if (this.elements.activeCallStatusText && this.elements.activeCallStatusText.textContent === 'Calling...') {
                                this.elements.activeCallStatusText.textContent = 'Connecting...';
                            }
                        }
                    }
                } catch (_) {}
            }, pollIntervalMs);
        },

        stopSignalingLoop() {
            if (this.signalingInterval) {
                clearInterval(this.signalingInterval);
                this.signalingInterval = null;
            }
        },

        // ── 9. Handle Incoming WebRTC Signal ────────────────────────
        async handleIncomingSignal(sig) {
            const type = sig.type;
            const payload = sig.payload;

            console.log('[WebRTC] Received signal:', type);

            if (type === 'offer') {
                try {
                    if (!this.peerConnection) {
                        this.createPeerConnection();
                    }
                    await this.peerConnection.setRemoteDescription(new RTCSessionDescription(payload));
                    const answer = await this.peerConnection.createAnswer();
                    await this.peerConnection.setLocalDescription(answer);

                    await this.sendSignal('answer', {
                        type: answer.type,
                        sdp: answer.sdp
                    });
                    console.log('[WebRTC] SDP Answer sent.');
                } catch (e) {
                    console.error('[WebRTC] Error processing offer:', e);
                }
            } else if (type === 'answer') {
                try {
                    if (this.peerConnection && this.peerConnection.signalingState === 'have-local-offer') {
                        await this.peerConnection.setRemoteDescription(new RTCSessionDescription(payload));
                        console.log('[WebRTC] SDP Answer applied.');
                    }
                } catch (e) {
                    console.error('[WebRTC] Error processing answer:', e);
                }
            } else if (type === 'candidate') {
                try {
                    if (this.peerConnection && payload && payload.candidate) {
                        const candidate = new RTCIceCandidate({
                            candidate: payload.candidate,
                            sdpMid: payload.sdpMid,
                            sdpMLineIndex: payload.sdpMLineIndex
                        });
                        await this.peerConnection.addIceCandidate(candidate);
                    }
                } catch (e) {
                    console.warn('[WebRTC] Error adding ICE candidate:', e);
                }
            } else if (type === 'end' || type === 'reject' || type === 'busy') {
                this.tones.stop();
                showToast(`Call ${type}.`, 'info');
                this.endCurrentCall(type);
            }
        },

        // ── 10. Direct WebRTC Peer Connection Established ───────────
        onCallConnected() {
            this.tones.stop();
            console.log('[WebRTC] Connected! Live browser-to-browser voice/video established.');

            if (this.elements.activeCallStatusText) {
                this.elements.activeCallStatusText.textContent = 'Connected (Live)';
            }
            if (this.elements.activeAudioAvatar) {
                this.elements.activeAudioAvatar.classList.add('speaking');
            }

            this.startCallTimer();
            showToast('Call connected. Audio stream live.', 'success');
        },

        // ── 11. Call Timer Management ───────────────────────────────
        startCallTimer() {
            this.stopCallTimer();
            this.callDurationSec = 0;
            if (this.elements.activeCallTimer) {
                this.elements.activeCallTimer.textContent = '00:00';
            }

            this.callTimerInterval = setInterval(() => {
                this.callDurationSec++;
                if (this.elements.activeCallTimer) {
                    this.elements.activeCallTimer.textContent = formatTime(this.callDurationSec);
                }
            }, 1000);
        },

        stopCallTimer() {
            if (this.callTimerInterval) {
                clearInterval(this.callTimerInterval);
                this.callTimerInterval = null;
            }
        },

        // ── 12. Active Call UI Controls (Mute & Video Toggle) ───────
        toggleMicrophone() {
            if (!this.localStream) return;
            const audioTracks = this.localStream.getAudioTracks();
            if (!audioTracks.length) return;

            this.isMuted = !this.isMuted;
            audioTracks[0].enabled = !this.isMuted;

            const btn = this.elements.btnToggleMic;
            if (btn) {
                btn.classList.toggle('active-toggled', this.isMuted);
                btn.innerHTML = `<i class="fas fa-microphone${this.isMuted ? '-slash' : ''}"></i>`;
                btn.title = this.isMuted ? 'Unmute Microphone' : 'Mute Microphone';
            }
            showToast(this.isMuted ? 'Microphone muted' : 'Microphone unmuted', 'info');
        },

        async toggleVideo() {
            this.isVideoActive = !this.isVideoActive;
            const btn = this.elements.btnToggleVideo;
            const grid = this.elements.videoGridContainer;

            if (this.isVideoActive) {
                try {
                    if (!this.localStream.getVideoTracks().length) {
                        const vStream = await navigator.mediaDevices.getUserMedia({ video: true });
                        const vTrack = vStream.getVideoTracks()[0];
                        this.localStream.addTrack(vTrack);

                        if (this.peerConnection) {
                            this.peerConnection.addTrack(vTrack, this.localStream);
                            this.createAndSendOffer();
                        }
                    } else {
                        this.localStream.getVideoTracks()[0].enabled = true;
                    }

                    if (this.elements.localVideo) {
                        this.elements.localVideo.srcObject = this.localStream;
                        this.elements.localVideo.play().catch(() => {});
                    }
                    grid?.classList.add('active');
                    btn?.classList.add('active-toggled');
                    showToast('Camera enabled', 'info');
                } catch (e) {
                    this.isVideoActive = false;
                    showToast('Could not enable camera: ' + e.message, 'danger');
                }
            } else {
                if (this.localStream && this.localStream.getVideoTracks().length) {
                    this.localStream.getVideoTracks()[0].enabled = false;
                }
                grid?.classList.remove('active');
                btn?.classList.remove('active-toggled');
                showToast('Camera disabled', 'info');
            }
        },

        // ── 13. End Call ────────────────────────────────────────────
        async endCurrentCall(reason = 'ended') {
            this.tones.stop();
            this.stopCallTimer();
            this.stopSignalingLoop();

            const callId = this.activeCallId;
            this.activeCallId = null;

            if (this.peerConnection) {
                try { this.peerConnection.close(); } catch (_) {}
                this.peerConnection = null;
            }

            this.stopLocalMedia();

            if (this.elements.activeModal) {
                this.elements.activeModal.classList.remove('show');
                this.elements.activeModal.style.display = 'none';
            }
            if (this.elements.incomingModal) {
                this.elements.incomingModal.classList.remove('show');
                this.elements.incomingModal.style.display = 'none';
            }

            if (callId) {
                try {
                    const apiBase = getApiBase();
                    const csrf = await getCsrfToken();
                    await fetch(apiBase + 'end.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({ call_id: callId, reason: reason, csrf_token: csrf })
                    });
                } catch (_) {}
            }
        },

        // ── 14. Open Active Call Modal ──────────────────────────────
        openActiveCallModal(name, meta, callType, statusText) {
            const m = this.elements;
            if (!m.activeModal) return;

            if (m.activePeerName) m.activePeerName.textContent = name;
            if (m.activePeerMeta) m.activePeerMeta.textContent = meta;
            if (m.activeCallStatusText) m.activeCallStatusText.textContent = statusText;
            if (m.activeCallTypeLabel) {
                m.activeCallTypeLabel.textContent = (callType === 'video') ? 'Live Video' : 'Live Audio';
            }
            if (m.activeCallTimer) m.activeCallTimer.textContent = '00:00';

            // Reset video container visibility
            if (callType === 'video' && m.videoGridContainer) {
                m.videoGridContainer.classList.add('active');
            } else if (m.videoGridContainer) {
                m.videoGridContainer.classList.remove('active');
            }

            m.activeModal.style.display = 'flex';
            m.activeModal.classList.add('show');
        },

        // ── 15. Presence Heartbeat & Incoming Call Detection ────────
        startPresenceHeartbeat() {
            this.stopPresenceHeartbeat();

            const ping = async () => {
                try {
                    const donorCards = document.querySelectorAll('[data-user-id]');
                    const userIds = Array.from(donorCards).map(c => c.dataset.userId).filter(Boolean);
                    const checkParam = userIds.length ? `?check_users=${userIds.join(',')}` : '';

                    const apiBase = getApiBase();
                    const resp = await fetch(`${apiBase}heartbeat.php${checkParam}`);
                    if (!resp.ok) return;
                    const data = await resp.json();

                    // Update live presence indicators on donor cards
                    if (data.presence_map) {
                        this.updatePagePresenceBadges(data.presence_map);
                    }

                    // Check for incoming call
                    if (data.has_incoming && data.incoming_call && !this.activeCallId) {
                        this.handleDetectedIncomingCall(data.incoming_call);
                    }
                } catch (_) {}
            };

            ping();
            this.heartbeatInterval = setInterval(ping, 3000);
        },

        stopPresenceHeartbeat() {
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
                this.heartbeatInterval = null;
            }
        },

        handleDetectedIncomingCall(call) {
            if (this.incomingCallData && this.incomingCallData.call_id === call.call_id) return;
            this.incomingCallData = call;

            const m = this.elements;
            if (m.incomingCallerName) m.incomingCallerName.textContent = call.caller.name;
            if (m.incomingCallerRole) m.incomingCallerRole.textContent = call.caller.role || 'User';
            if (m.incomingBloodType)  m.incomingBloodType.textContent  = call.caller.blood_type || 'O+';
            if (m.incomingCallTypeBadge) {
                m.incomingCallTypeBadge.innerHTML = (call.call_type === 'video')
                    ? '<i class="fas fa-video"></i> Video'
                    : '<i class="fas fa-phone"></i> Audio';
            }

            if (m.incomingModal) {
                m.incomingModal.style.display = 'flex';
                m.incomingModal.classList.add('show');
            }

            this.tones.playIncomingRing();

            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    new Notification('Incoming BloodConnect Call', {
                        body: `${call.caller.name} is calling you on BloodConnect.`,
                        icon: '/assets/images/logo.png'
                    });
                } catch (_) {}
            }
        },

        updatePagePresenceBadges(map) {
            for (const [userId, status] of Object.entries(map)) {
                const badges = document.querySelectorAll(`.user-presence-${userId}`);
                badges.forEach(badge => {
                    badge.className = `presence-badge user-presence-${userId} presence-${status}`;
                    const labels = {
                        online: 'Online',
                        offline: 'Offline',
                        away: 'Away',
                        in_call: 'In Call'
                    };
                    badge.innerHTML = `<span class="presence-dot"></span> <span>${labels[status] || status}</span>`;
                });

                const callBtns = document.querySelectorAll(`[data-webrtc-call][data-receiver-id="${userId}"]`);
                callBtns.forEach(btn => {
                    if (status === 'in_call') {
                        btn.classList.add('disabled');
                        btn.title = 'User is currently in another call';
                    } else {
                        btn.classList.remove('disabled');
                        btn.title = 'Call user directly on website';
                    }
                });
            }
        }
    };

    // Auto-init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => WebRTCCallManager.init());
    } else {
        WebRTCCallManager.init();
    }

    // Expose globally
    window.WebRTCCallManager = WebRTCCallManager;
    window.CallManager = {
        call: (receiverId, name, blood, role, type, ctxType, ctxId) =>
            WebRTCCallManager.startCall(receiverId, name, blood, role, type, ctxType, ctxId),
        end: () => WebRTCCallManager.endCurrentCall('user_hangup'),
        accept: () => WebRTCCallManager.acceptIncomingCall(),
        decline: () => WebRTCCallManager.declineIncomingCall()
    };
})();
