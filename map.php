<?php
/**
 * map.php — LifeFlow Live Interactive Geo Map
 * Provides role-tailored map views:
 * - Donor View: Shows all users / blood requests in need across locations.
 * - User View: Shows all available blood donors across locations.
 * - Hospital View: Shows both donors and blood requests (users) across locations.
 */
session_start();

$userRole = $_SESSION['user_role'] ?? 'user';
$userName = $_SESSION['user_name'] ?? 'User';
$userId   = $_SESSION['user_id'] ?? null;

// Determine role-based view and back navigation
$reqView = trim($_GET['view'] ?? '');
if (empty($reqView)) {
    if ($userRole === 'donor') {
        $defaultView = 'requests'; // Donor sees users needing blood
    } elseif ($userRole === 'hospital') {
        $defaultView = 'all';      // Hospital sees both
    } else {
        $defaultView = 'donors';    // User sees available donors
    }
} else {
    $defaultView = $reqView;
}

if ($userRole === 'donor') {
    $backUrl   = 'donor/index.php';
    $backLabel = 'Donor Dashboard';
    $backIcon  = 'fa-heart-pulse';
} elseif ($userRole === 'hospital') {
    $backUrl   = 'hospital/dashboard.php';
    $backLabel = 'Hospital Dashboard';
    $backIcon  = 'fa-hospital';
} else {
    $backUrl   = 'user/user.html';
    $backLabel = 'My Dashboard';
    $backIcon  = 'fa-house';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Live Blood Network Map — LifeFlow</title>
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

<style>
:root {
    --primary-red: #e63946;
    --primary-red-hover: #d62839;
    --dark-slate: #0f172a;
    --card-bg: #ffffff;
    --border-color: #e2e8f0;
    --text-muted: #64748b;
    --green-donor: #10b981;
    --red-request: #ef4444;
    --blue-hospital: #2563eb;
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #f8fafc;
    color: var(--dark-slate);
    margin: 0;
    padding: 0;
    height: 100vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* ── Top Bar ── */
.map-header {
    background: #ffffff;
    border-bottom: 1px solid var(--border-color);
    padding: 10px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}

.brand-title {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    font-size: 1.35rem;
    color: var(--dark-slate);
    text-decoration: none;
}

.brand-title i {
    color: var(--primary-red);
    font-size: 1.5rem;
}

.btn-back-link {
    background: #f1f5f9;
    color: var(--dark-slate);
    border: 1px solid var(--border-color);
    border-radius: 50px;
    padding: 7px 16px;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-back-link:hover {
    background: #e2e8f0;
    color: var(--primary-red);
}

/* ── Control Bar ── */
.map-filter-bar {
    background: #ffffff;
    border-bottom: 1px solid var(--border-color);
    padding: 8px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    z-index: 999;
}

.filter-group-pill {
    display: inline-flex;
    background: #f1f5f9;
    border-radius: 50px;
    padding: 3px;
    gap: 3px;
}

.btn-filter-toggle {
    border: none;
    background: transparent;
    color: var(--text-muted);
    font-size: 0.82rem;
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-filter-toggle.active {
    background: #ffffff;
    color: var(--dark-slate);
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.btn-filter-toggle.active.req-active {
    color: #ef4444;
}

.btn-filter-toggle.active.dnr-active {
    color: #10b981;
}

.btn-filter-toggle.active.hosp-active {
    color: #2563eb;
}

.filter-select {
    border: 1.5px solid var(--border-color);
    border-radius: 50px;
    padding: 6px 14px;
    font-size: 0.84rem;
    font-weight: 600;
    color: var(--dark-slate);
    outline: none;
    background: #ffffff;
}

.filter-select:focus {
    border-color: var(--primary-red);
}

/* ── Map Container ── */
#liveBloodMap {
    flex: 1;
    width: 100%;
    height: 100%;
    z-index: 10;
}

/* ── Custom Pins ── */
.custom-marker-pin {
    width: 38px;
    height: 38px;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    box-shadow: 0 6px 16px rgba(0,0,0,0.3);
    border: 2px solid #ffffff;
}

.custom-marker-pin i {
    transform: rotate(45deg);
    color: #ffffff;
    font-size: 1rem;
}

.pin-donor {
    background: linear-gradient(135deg, #10b981, #059669);
}

.pin-request {
    background: linear-gradient(135deg, #ef4444, #b91c1c);
    animation: pinPulse 2s infinite ease-in-out;
}

.pin-hospital {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

@keyframes pinPulse {
    0% { transform: rotate(-45deg) scale(1); }
    50% { transform: rotate(-45deg) scale(1.1); box-shadow: 0 8px 22px rgba(239,68,68,0.5); }
    100% { transform: rotate(-45deg) scale(1); }
}

/* ── Popup Card Styling ── */
.leaflet-popup-content-wrapper {
    border-radius: 16px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 12px 32px rgba(0,0,0,0.18);
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.leaflet-popup-content {
    margin: 0;
    line-height: 1.4;
}

.map-popup-card {
    padding: 18px 20px;
    min-width: 250px;
}

.popup-tag-badge {
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 8px;
    border-radius: 4px;
    display: inline-block;
    margin-bottom: 6px;
}

.badge-tag-donor {
    background: #ecfdf5;
    color: #059669;
    border: 1px solid #a7f3d0;
}

.badge-tag-request {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecdd3;
}

.badge-tag-hospital {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
}

.popup-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--dark-slate);
    margin: 0 0 6px;
}

.popup-blood-badge {
    font-family: 'Outfit', sans-serif;
    font-size: 1.25rem;
    font-weight: 900;
    color: var(--primary-red);
    display: inline-block;
    margin-bottom: 6px;
}

.popup-info-row {
    font-size: 0.82rem;
    color: var(--text-muted);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.popup-info-row i {
    width: 14px;
    color: var(--dark-slate);
}

.popup-btn-action {
    display: block;
    width: 100%;
    margin-top: 12px;
    text-align: center;
    background: var(--primary-red);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.82rem;
    padding: 8px 12px;
    border-radius: 8px;
    text-decoration: none;
    transition: background 0.2s;
}

.popup-btn-action:hover {
    background: var(--primary-red-hover);
    color: #ffffff;
}

.popup-btn-call {
    background: #10b981;
}

.popup-btn-call:hover {
    background: #059669;
}

/* ── Legend Floating Overlay ── */
.map-legend-overlay {
    position: absolute;
    bottom: 24px;
    left: 24px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 12px 18px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    z-index: 500;
    font-size: 0.8rem;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--dark-slate);
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.dot-donor { background: var(--green-donor); }
.dot-request { background: var(--red-request); }
.dot-hospital { background: var(--blue-hospital); }

/* Floating Stats Badge */
.map-stat-counter {
    position: absolute;
    top: 110px;
    right: 24px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 10px 18px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    z-index: 500;
    font-size: 0.82rem;
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-chip {
    display: flex;
    flex-direction: column;
    text-align: center;
}

.stat-chip-num {
    font-family: 'Outfit', sans-serif;
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1;
}

.stat-chip-label {
    font-size: 0.68rem;
    color: var(--text-muted);
    font-weight: 700;
    text-transform: uppercase;
}
</style>
</head>
<body>

<!-- Header -->
<header class="map-header">
    <a href="<?= htmlspecialchars($backUrl) ?>" class="brand-title">
        <i class="fas fa-droplet"></i>
        <span>LifeFlow · Live Network Map</span>
    </a>

    <div class="d-flex align-items-center gap-2">
        <div style="font-size:0.84rem;font-weight:700;color:var(--text-muted);">
            <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($userName) ?> 
            <span class="badge bg-secondary rounded-pill ms-1 text-uppercase" style="font-size:0.68rem;"><?= htmlspecialchars($userRole) ?></span>
        </div>
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-back-link">
            <i class="fas <?= htmlspecialchars($backIcon) ?>"></i> <?= htmlspecialchars($backLabel) ?>
        </a>
    </div>
</header>

<!-- Filter & Toggle Bar -->
<div class="map-filter-bar">
    <!-- View Switcher -->
    <div class="filter-group-pill">
        <button type="button" class="btn-filter-toggle <?= ($defaultView === 'all') ? 'active' : '' ?>" data-view="all">
            <i class="fas fa-globe"></i> Show All
        </button>
        <button type="button" class="btn-filter-toggle req-active <?= ($defaultView === 'requests') ? 'active req-active' : '' ?>" data-view="requests">
            <i class="fas fa-hand-holding-medical" style="color:#ef4444;"></i> Users / Blood Requests
        </button>
        <button type="button" class="btn-filter-toggle dnr-active <?= ($defaultView === 'donors') ? 'active dnr-active' : '' ?>" data-view="donors">
            <i class="fas fa-heart-pulse" style="color:#10b981;"></i> Donors
        </button>
        <button type="button" class="btn-filter-toggle hosp-active <?= ($defaultView === 'hospitals') ? 'active hosp-active' : '' ?>" data-view="hospitals">
            <i class="fas fa-hospital" style="color:#2563eb;"></i> Hospitals
        </button>
    </div>

    <!-- Dropdowns -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <select id="filterBloodType" class="filter-select">
            <option value="">All Blood Groups</option>
            <option value="O+">O+</option>
            <option value="O-">O-</option>
            <option value="A+">A+</option>
            <option value="A-">A-</option>
            <option value="B+">B+</option>
            <option value="B-">B-</option>
            <option value="AB+">AB+</option>
            <option value="AB-">AB-</option>
        </select>

        <select id="filterCity" class="filter-select">
            <option value="">All Cities</option>
            <option value="Coimbatore">Coimbatore</option>
            <option value="Avinashi">Avinashi</option>
            <option value="Tiruppur">Tiruppur</option>
            <option value="Chennai">Chennai</option>
            <option value="Salem">Salem</option>
            <option value="Erode">Erode</option>
        </select>

        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" id="btnLocateMe" title="Center map on my location">
            <i class="fas fa-crosshairs me-1"></i> Locate Me
        </button>
    </div>
</div>

<!-- Map Canvas -->
<div id="liveBloodMap"></div>

<!-- Floating Stats Counter -->
<div class="map-stat-counter">
    <div class="stat-chip">
        <span class="stat-chip-num text-danger" id="cntRequests">0</span>
        <span class="stat-chip-label">Requests</span>
    </div>
    <div class="stat-chip">
        <span class="stat-chip-num text-success" id="cntDonors">0</span>
        <span class="stat-chip-label">Donors</span>
    </div>
    <div class="stat-chip">
        <span class="stat-chip-num text-primary" id="cntHospitals">0</span>
        <span class="stat-chip-label">Hospitals</span>
    </div>
</div>

<!-- Floating Map Legend -->
<div class="map-legend-overlay">
    <div class="legend-item"><span class="legend-dot dot-request"></span> Users in Need / Blood Requests</div>
    <div class="legend-item"><span class="legend-dot dot-donor"></span> Verified Available Donors</div>
    <div class="legend-item"><span class="legend-dot dot-hospital"></span> Registered Hospitals &amp; Hubs</div>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
const USER_ROLE = '<?= htmlspecialchars($userRole) ?>';
let currentView = '<?= htmlspecialchars($defaultView) ?>';

// Initialize Leaflet Map centered on Western Tamil Nadu (Coimbatore Region)
const map = L.map('liveBloodMap', {
    zoomControl: true
}).setView([11.0168, 76.9558], 11);

// OpenStreetMap Standard Tiles
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors · LifeFlow'
}).addTo(map);

// Marker Layers
const markersLayer = L.layerGroup().addTo(map);

// Custom Leaflet Icons
function createCustomPin(type, iconClass) {
    return L.divIcon({
        className: 'custom-pin-wrapper',
        html: `<div class="custom-marker-pin pin-${type}"><i class="fas ${iconClass}"></i></div>`,
        iconSize: [38, 38],
        iconAnchor: [19, 38],
        popupAnchor: [0, -36]
    });
}

const donorPin   = createCustomPin('donor', 'fa-heart-pulse');
const requestPin = createCustomPin('request', 'fa-droplet');
const hospPin    = createCustomPin('hospital', 'fa-hospital');

async function loadMapData() {
    markersLayer.clearLayers();

    const blood = document.getElementById('filterBloodType').value;
    const city = document.getElementById('filterCity').value;

    const url = new URL('api/get_map_locations.php', window.location.href);
    url.searchParams.set('type', currentView);
    if (blood) url.searchParams.set('blood_type', blood);
    if (city)  url.searchParams.set('city', city);

    try {
        const res = await fetch(url.toString());
        const data = await res.json();

        if (!data.success) return;

        document.getElementById('cntRequests').textContent = data.counts.requests || 0;
        document.getElementById('cntDonors').textContent   = data.counts.donors || 0;
        document.getElementById('cntHospitals').textContent = data.counts.hospitals || 0;

        const bounds = [];

        // 1. Render Requests (Users needing blood)
        if (data.requests && (currentView === 'all' || currentView === 'requests')) {
            data.requests.forEach(req => {
                const marker = L.marker([req.latitude, req.longitude], { icon: requestPin });
                const popupHtml = `
                    <div class="map-popup-card">
                        <span class="popup-tag-badge badge-tag-request"><i class="fas fa-hand-holding-medical"></i> Blood Request (${req.urgency.toUpperCase()})</span>
                        <h4 class="popup-title">${escapeHtml(req.patient_name)}</h4>
                        <div class="popup-blood-badge">${escapeHtml(req.blood_type)} (${req.units} Units)</div>
                        <div class="popup-info-row"><i class="fas fa-hospital"></i> ${escapeHtml(req.hospital)}</div>
                        <div class="popup-info-row"><i class="fas fa-location-dot"></i> ${escapeHtml(req.city)}${req.area ? ', ' + escapeHtml(req.area) : ''}</div>
                        ${req.requester_phone ? `<div class="popup-info-row"><i class="fas fa-phone"></i> ${escapeHtml(req.requester_phone)}</div>` : ''}
                        <a href="tel:${req.requester_phone || ''}" class="popup-btn-action">
                            <i class="fas fa-phone me-1"></i> Respond to Donate
                        </a>
                    </div>
                `;
                marker.bindPopup(popupHtml);
                markersLayer.addLayer(marker);
                bounds.push([req.latitude, req.longitude]);
            });
        }

        // 2. Render Donors
        if (data.donors && (currentView === 'all' || currentView === 'donors')) {
            data.donors.forEach(dnr => {
                const marker = L.marker([dnr.latitude, dnr.longitude], { icon: donorPin });
                const popupHtml = `
                    <div class="map-popup-card">
                        <span class="popup-tag-badge badge-tag-donor"><i class="fas fa-heart-pulse"></i> Verified Donor</span>
                        <h4 class="popup-title">${escapeHtml(dnr.name)}</h4>
                        <div class="popup-blood-badge" style="color:#10b981;">${escapeHtml(dnr.blood_type)} (${escapeHtml(dnr.emergency_availability)})</div>
                        <div class="popup-info-row"><i class="fas fa-location-dot"></i> ${escapeHtml(dnr.city)}${dnr.area ? ', ' + escapeHtml(dnr.area) : ''}</div>
                        <div class="popup-info-row"><i class="fas fa-award"></i> ${dnr.total_donations} Lifetime Donations</div>
                        ${dnr.phone ? `<a href="tel:${dnr.phone}" class="popup-btn-action popup-btn-call"><i class="fas fa-phone me-1"></i> Call Donor (${dnr.phone})</a>` : ''}
                    </div>
                `;
                marker.bindPopup(popupHtml);
                markersLayer.addLayer(marker);
                bounds.push([dnr.latitude, dnr.longitude]);
            });
        }

        // 3. Render Hospitals
        if (data.hospitals && (currentView === 'all' || currentView === 'hospitals')) {
            data.hospitals.forEach(hosp => {
                const marker = L.marker([hosp.latitude, hosp.longitude], { icon: hospPin });
                const popupHtml = `
                    <div class="map-popup-card">
                        <span class="popup-tag-badge badge-tag-hospital"><i class="fas fa-hospital"></i> Hospital &amp; Blood Bank</span>
                        <h4 class="popup-title">${escapeHtml(hosp.name)}</h4>
                        <div class="popup-info-row"><i class="fas fa-location-dot"></i> ${escapeHtml(hosp.city)} - ${escapeHtml(hosp.address)}</div>
                        ${hosp.phone ? `<div class="popup-info-row"><i class="fas fa-phone"></i> ${escapeHtml(hosp.phone)}</div>` : ''}
                        <a href="tel:${hosp.phone || ''}" class="popup-btn-action" style="background:#2563eb;">
                            <i class="fas fa-phone me-1"></i> Contact Hospital
                        </a>
                    </div>
                `;
                marker.bindPopup(popupHtml);
                markersLayer.addLayer(marker);
                bounds.push([hosp.latitude, hosp.longitude]);
            });
        }

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
        }

    } catch (e) {
        console.error("Map load error:", e);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

// Bind view switches
document.querySelectorAll('.btn-filter-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-filter-toggle').forEach(b => b.classList.remove('active', 'req-active', 'dnr-active', 'hosp-active'));
        btn.classList.add('active');
        const v = btn.dataset.view;
        if (v === 'requests') btn.classList.add('req-active');
        if (v === 'donors')   btn.classList.add('dnr-active');
        if (v === 'hospitals')btn.classList.add('hosp-active');
        currentView = v;
        loadMapData();
    });
});

document.getElementById('filterBloodType').addEventListener('change', loadMapData);
document.getElementById('filterCity').addEventListener('change', loadMapData);

// Geolocation button
document.getElementById('btnLocateMe').addEventListener('click', () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            map.setView([lat, lng], 14);
            L.circleMarker([lat, lng], {
                radius: 8,
                fillColor: '#3b82f6',
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.9
            }).addTo(map).bindPopup('<strong>Your Current Location</strong>').openPopup();
        }, () => {
            alert('Unable to retrieve your location. Please check your browser location permission.');
        });
    } else {
        alert('Geolocation is not supported by your browser.');
    }
});

// Load on start
document.addEventListener('DOMContentLoaded', loadMapData);
</script>
</body>
</html>
