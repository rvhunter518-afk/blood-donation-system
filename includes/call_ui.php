<?php
/**
 * BloodConnect — Shared WebRTC In-Website Call UI Components
 * includes/call_ui.php
 */
?>
<!-- WebRTC Incoming Call Modal -->
<div id="webrtcIncomingModal" role="dialog" aria-modal="true" aria-label="Incoming Call" style="display: none;">
    <div class="incoming-call-box">
        <div class="incoming-radar-wrap">
            <div class="incoming-radar-wave"></div>
            <div class="incoming-radar-wave"></div>
            <div class="incoming-radar-wave"></div>
            <div class="incoming-avatar">
                <i class="fas fa-user"></i>
            </div>
        </div>
        <h3 id="incomingCallerName">Caller</h3>
        <div class="incoming-meta-pill">
            <span id="incomingCallerRole">Donor</span>
            <span>•</span>
            <span class="blood-type" id="incomingBloodType">O+</span>
            <span id="incomingCallTypeBadge" style="margin-left:4px;"><i class="fas fa-phone"></i> Audio</span>
        </div>
        <div class="incoming-status-text">
            <i class="fas fa-phone-volume"></i> Incoming BloodConnect Call...
        </div>
        <div class="incoming-actions">
            <button type="button" class="incoming-btn btn-decline" id="btnDeclineCall" title="Decline Call">
                <div class="incoming-btn-icon"><i class="fas fa-phone-slash"></i></div>
                <span>Decline</span>
            </button>
            <button type="button" class="incoming-btn btn-accept" id="btnAcceptCall" title="Accept Call">
                <div class="incoming-btn-icon"><i class="fas fa-phone"></i></div>
                <span>Accept</span>
            </button>
        </div>
    </div>
</div>

<!-- WebRTC Active Call Modal -->
<div id="webrtcActiveCallModal" role="dialog" aria-modal="true" aria-label="Active Call" style="display: none;">
    <div class="active-call-container">
        <div class="active-call-topbar">
            <div class="call-badge-live">
                <span class="live-indicator-dot"></span>
                <span id="activeCallTypeLabel">Live Audio</span>
            </div>
            <button type="button" class="call-btn-ctrl" id="btnMinimizeCall" style="width:34px;height:34px;font-size:0.85rem;" title="Minimize Call">
                <i class="fas fa-minus"></i>
            </button>
        </div>
        <div class="video-grid-container" id="videoGridContainer">
            <video id="remoteVideo" class="remote-video-el" autoplay playsinline></video>
            <div class="local-pip-container" id="localPipContainer">
                <video id="localVideo" class="local-video-el" autoplay playsinline muted></video>
            </div>
        </div>
        <div class="active-call-main" id="audioCallMain">
            <div class="audio-call-visual">
                <div class="audio-avatar-wrap">
                    <div class="audio-sound-ring"></div>
                    <div class="audio-avatar" id="activeAudioAvatar"><i class="fas fa-user"></i></div>
                </div>
                <h2 class="active-peer-name" id="activePeerName">User</h2>
                <div class="active-peer-meta" id="activePeerMeta">Donor • O+</div>
                <div class="active-call-timer" id="activeCallTimer">00:00</div>
                <div class="active-call-status" id="activeCallStatusText">Connecting...</div>
            </div>
        </div>
        <div class="active-call-controls">
            <button type="button" class="call-btn-ctrl" id="btnToggleMic" title="Mute Microphone">
                <i class="fas fa-microphone"></i>
            </button>
            <button type="button" class="call-btn-ctrl" id="btnToggleVideo" title="Switch to Video">
                <i class="fas fa-video"></i>
            </button>
            <button type="button" class="call-btn-ctrl btn-hangup" id="btnEndActiveCall" title="End Call">
                <i class="fas fa-phone-slash"></i>
            </button>
        </div>
    </div>
</div>

<!-- Audio stream output -->
<audio id="remoteAudio" autoplay playsinline></audio>
<!-- Call toasts -->
<div id="webrtcToastContainer"></div>

