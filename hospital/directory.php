<?php
session_start();
require_once '../config/database.php';
$conn = getDbConnection();

$stats = ['total_hospitals'=>0,'with_blood_bank'=>0,'active_emergencies'=>0,'total_blood_units'=>0];
$hospitals = [];

try {
    $stmt = $conn->query("
        SELECT h.*,
               (SELECT SUM(available_units) FROM hospital_blood_inventory WHERE hospital_id = h.id) as total_units,
               (SELECT COUNT(id) FROM hospital_emergency_requests WHERE hospital_id = h.id AND status = 'open') as open_emergencies
        FROM hospitals h
        WHERE h.status = 'active'
    ");
    if($stmt) {
        $hospitals = $stmt->fetch_all(MYSQLI_ASSOC);
        foreach($hospitals as $h) {
            $stats['total_hospitals']++;
            if($h['has_blood_bank']) $stats['with_blood_bank']++;
            if($h['open_emergencies'] > 0) $stats['active_emergencies'] += $h['open_emergencies'];
            if($h['total_units'] > 0) $stats['total_blood_units'] += $h['total_units'];
        }
    }
} catch (Exception $e) {}

// Load blood inventory per hospital for modal
$inv_by_hospital = [];
try {
    $r = $conn->query("SELECT hospital_id, blood_group, available_units, minimum_units FROM hospital_blood_inventory");
    if($r) {
        while($row = $r->fetch_assoc()) {
            $inv_by_hospital[$row['hospital_id']][$row['blood_group']] = $row;
        }
    }
} catch(Exception $e) {}

// Load emergencies per hospital
$emer_by_hospital = [];
try {
    $r = $conn->query("SELECT * FROM hospital_emergency_requests WHERE status='open' ORDER BY priority DESC");
    if($r) {
        while($row = $r->fetch_assoc()) {
            $emer_by_hospital[$row['hospital_id']][] = $row;
        }
    }
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hospital Directory — LifeFlow</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
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
  --yellow: #f59e0b;
  --yellow-soft: #fffbeb;
  --blue: #1d4ed8;
  --blue-soft: #eff6ff;
  --line: #ecdfda;
  --shadow: 0 18px 40px rgba(143,18,32,.09), 0 6px 14px rgba(23,27,34,.04);
  --shadow-hover: 0 28px 55px rgba(143,18,32,.13), 0 8px 20px rgba(23,27,34,.06);
  --radius-lg: 26px;
  --radius-md: 18px;
  --radius-sm: 12px;
  --font-main: 'DM Sans', sans-serif;
  --font-heading: 'Space Grotesk', sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font-main); background: var(--paper); color: var(--ink); min-height: 100vh; overflow-x: hidden; }
h1,h2,h3,h4 { font-family: var(--font-heading); }

/* Canvases */
#bgCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: -2; pointer-events: none; }
#cursorCanvas { position: fixed; inset: 0; width: 100%; height: 100%; z-index: 30; pointer-events: none; }
.grain { position: fixed; inset: 0; z-index: -1; pointer-events: none; opacity: .035;
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='90' height='90'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>"); }
::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: var(--cream); } ::-webkit-scrollbar-thumb { background: linear-gradient(var(--red), var(--red-deep)); border-radius: 3px; }

/* ECG Topbar */
.ecg-topbar { display: flex; justify-content: space-between; align-items: center; padding: 7px max(24px, calc((100% - 1180px)/2)); background: #fffdfbe8; backdrop-filter: blur(16px); border-bottom: 1px solid var(--line); font-size: .77rem; color: var(--muted); z-index: 6; position: relative; }
.ecg-live { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--ink); }
.pulse-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--red); box-shadow: 0 0 10px rgba(214,40,57,.7); animation: pulseDot 1.6s ease infinite; flex: none; }
@keyframes pulseDot { 50% { transform: scale(1.5); opacity: .6; } }

/* Header */
header { display: flex; justify-content: space-between; align-items: center; padding: 18px max(24px, calc((100% - 1180px)/2)); border-bottom: 1px solid var(--line); background: #fffdfbdd; backdrop-filter: blur(20px); position: sticky; top: 0; z-index: 6; transition: box-shadow .25s; }
header.scrolled { box-shadow: 0 8px 24px rgba(143,18,32,.07); }
.brand { display: flex; align-items: center; gap: 10px; font: 700 1.5rem var(--font-heading); color: var(--ink); text-decoration: none; }
.brand i { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 11px; background: linear-gradient(135deg, var(--red), var(--red-deep)); color: #fff; box-shadow: 0 8px 18px rgba(143,18,32,.28); font-size: .9rem; }
.brand span { color: var(--red); }
.header-actions { display: flex; align-items: center; gap: 10px; }
.btn { display: inline-flex; align-items: center; gap: 7px; padding: 10px 22px; border-radius: 50px; font: 700 .9rem var(--font-main); text-decoration: none; cursor: pointer; border: none; transition: .25s; }
.btn-primary { background: linear-gradient(135deg, var(--red), var(--red-deep)); color: #fff; box-shadow: 0 6px 16px rgba(143,18,32,.25); }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(143,18,32,.32); }
.btn-outline { background: var(--paper); border: 1.5px solid var(--line); color: var(--ink); }
.btn-outline:hover { border-color: var(--red); color: var(--red); }
<?php if(isset($_SESSION['hospital_id'])): ?>
.hospital-login-badge { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; border-radius: 50px; background: var(--red-soft); border: 1px solid rgba(214,40,57,.2); color: var(--red-deep); font: 700 .85rem var(--font-main); }
<?php endif; ?>

/* Hero Section */
.hero { text-align: center; padding: 70px max(24px, calc((100% - 1180px)/2)) 50px; position: relative; }
.hero-badge { display: inline-flex; align-items: center; gap: 7px; background: var(--red-soft); border: 1px solid rgba(214,40,57,.2); color: var(--red-deep); border-radius: 20px; padding: 6px 16px; font: 700 .82rem var(--font-main); margin-bottom: 20px; }
.hero h1 { font: 700 3rem var(--font-heading); color: var(--ink); margin-bottom: 16px; line-height: 1.15; }
.hero h1 span { color: var(--red); }
.hero p { color: var(--muted); font-size: 1.05rem; margin-bottom: 32px; max-width: 560px; margin-left: auto; margin-right: auto; }

/* Search */
.search-container { max-width: 580px; margin: 0 auto 0; position: relative; }
.search-input { width: 100%; padding: 16px 22px 16px 52px; background: #fff; border: 2px solid var(--line); border-radius: 50px; color: var(--ink); font: inherit; font-size: .98rem; outline: none; box-shadow: 0 6px 24px rgba(143,18,32,.07); transition: .2s; }
.search-input:focus { border-color: var(--red); box-shadow: 0 0 0 4px rgba(214,40,57,.1), 0 6px 24px rgba(143,18,32,.07); }
.search-icon { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .95rem; }

/* Stats Bar */
.stats-section { padding: 30px max(24px, calc((100% - 1180px)/2)); }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.stat-card { background: #ffffffcc; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 24px 20px; text-align: center; box-shadow: var(--shadow); transition: transform .2s, box-shadow .2s; position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--red), var(--red-deep)); }
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-hover); }
.stat-card .stat-icon { width: 46px; height: 46px; background: linear-gradient(135deg, var(--red), var(--red-deep)); border-radius: 13px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff; margin: 0 auto 12px; box-shadow: 0 5px 14px rgba(143,18,32,.22); }
.stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, var(--green), #0a5e44); }
.stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, var(--yellow), #d97706); }
.stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, var(--blue), #1e40af); }
.stat-card::before, .stat-card:nth-child(2)::before { background: linear-gradient(90deg, var(--green), #0a5e44); }
.stat-card:nth-child(1)::before { background: linear-gradient(90deg, var(--red), var(--red-deep)); }
.stat-card:nth-child(3)::before { background: linear-gradient(90deg, var(--yellow), #d97706); }
.stat-card:nth-child(4)::before { background: linear-gradient(90deg, var(--blue), #1e40af); }
.stat-value { font: 700 2.2rem var(--font-heading); color: var(--ink); margin-bottom: 4px; }
.stat-label { font-size: .82rem; font-weight: 700; color: var(--muted); }

/* Filters */
.filters-section { padding: 0 max(24px, calc((100% - 1180px)/2)) 24px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.filters-label { font: 700 .85rem var(--font-main); color: var(--muted); margin-right: 4px; }
.filter-btn { padding: 9px 20px; border-radius: 50px; font: 700 .85rem var(--font-main); border: 1.5px solid var(--line); background: var(--paper); color: var(--muted); cursor: pointer; transition: .2s; }
.filter-btn:hover { border-color: var(--red); color: var(--red); }
.filter-btn.active { background: linear-gradient(135deg, var(--red), var(--red-deep)); color: #fff; border-color: transparent; box-shadow: 0 4px 14px rgba(143,18,32,.22); }

/* Directory Grid */
.directory-section { padding: 0 max(24px, calc((100% - 1180px)/2)) 60px; }
.directory-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }

/* Hospital Card */
.hospital-card { background: #ffffffcc; border: 1.5px solid var(--line); border-radius: var(--radius-lg); padding: 26px; box-shadow: var(--shadow); transition: transform .25s, box-shadow .25s, border-color .25s; cursor: pointer; position: relative; overflow: hidden; }
.hospital-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--red), var(--red-deep)); }
.hospital-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); border-color: rgba(214,40,57,.2); }
.emergency-badge { position: absolute; top: 14px; right: 14px; background: linear-gradient(135deg, var(--red), var(--red-deep)); color: #fff; border-radius: 20px; padding: 4px 11px; font: 700 .72rem var(--font-main); display: flex; align-items: center; gap: 5px; animation: pulseDot 1.6s ease infinite; box-shadow: 0 4px 12px rgba(143,18,32,.3); }
.card-top { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px; }
.hosp-avatar { width: 48px; height: 48px; background: linear-gradient(135deg, var(--red), var(--red-deep)); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: #fff; flex: none; box-shadow: 0 5px 14px rgba(143,18,32,.22); }
.hosp-meta h3 { font: 700 1.05rem var(--font-heading); color: var(--ink); margin-bottom: 4px; }
.uid-pill { display: inline-flex; align-items: center; gap: 4px; background: var(--red-soft); color: var(--red-deep); border-radius: 10px; padding: 2px 9px; font: 700 .72rem var(--font-main); }
.type-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font: 700 .75rem var(--font-main); margin-bottom: 12px; }
.type-government { background: var(--blue-soft); color: var(--blue); }
.type-private { background: var(--yellow-soft); color: #92400e; }
.type-trust { background: var(--green-soft); color: var(--green); }
.type-medical_college { background: #f3e8ff; color: #7c3aed; }
.info-row { display: flex; align-items: center; gap: 9px; color: var(--muted); font-size: .88rem; margin-bottom: 7px; }
.info-row i { color: var(--red); width: 16px; text-align: center; font-size: .8rem; }
.facilities { display: flex; gap: 7px; flex-wrap: wrap; margin: 12px 0; }
.facility { display: inline-flex; align-items: center; gap: 5px; padding: 4px 11px; border-radius: 20px; font: 700 .73rem var(--font-main); }
.facility.active { background: var(--green-soft); color: var(--green); }
.facility.inactive { background: var(--cream); color: var(--muted); }
.blood-bar { display: flex; justify-content: space-between; align-items: center; background: var(--cream); border-radius: var(--radius-sm); padding: 10px 14px; margin-bottom: 14px; font-size: .85rem; }
.blood-bar .units { font: 800 .9rem var(--font-heading); color: var(--red); }
.card-footer { padding-top: 14px; border-top: 1px solid var(--line); }
.btn-view { width: 100%; background: linear-gradient(135deg, var(--red), var(--red-deep)); color: #fff; border: none; border-radius: 50px; padding: 11px; font: 700 .9rem var(--font-main); cursor: pointer; transition: .25s; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 5px 14px rgba(143,18,32,.2); }
.btn-view:hover { box-shadow: 0 10px 24px rgba(143,18,32,.3); transform: translateY(-1px); }

/* Empty State */
.empty-state { grid-column: 1/-1; text-align: center; padding: 70px 20px; background: #ffffffcc; border: 2px dashed var(--line); border-radius: var(--radius-lg); }
.empty-state i { font-size: 3rem; color: var(--muted); margin-bottom: 16px; display: block; }
.empty-state h2 { font: 700 1.5rem var(--font-heading); color: var(--ink); margin-bottom: 10px; }
.empty-state p { color: var(--muted); margin-bottom: 22px; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); backdrop-filter: blur(8px); z-index: 200; display: none; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.active { display: flex; animation: fadeIn .25s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: none; } }
.modal-content { background: var(--paper); border-radius: var(--radius-lg); max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 30px 80px rgba(143,18,32,.2); animation: slideUp .3s ease; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 22px 28px; border-bottom: 1px solid var(--line); background: var(--paper); position: sticky; top: 0; z-index: 2; }
.modal-header h2 { font: 700 1.3rem var(--font-heading); color: var(--ink); display: flex; align-items: center; gap: 10px; }
.modal-header h2 i { color: var(--red); }
.modal-close { background: var(--cream); border: none; color: var(--muted); width: 34px; height: 34px; border-radius: 10px; cursor: pointer; font-size: 1rem; transition: .2s; display: flex; align-items: center; justify-content: center; }
.modal-close:hover { background: var(--red-soft); color: var(--red); }
.modal-body { padding: 26px 28px; }

/* Modal sections */
.m-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 22px; }
.m-info-item label { display: block; font-size: .72rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 3px; }
.m-info-item span { font-size: .92rem; font-weight: 600; color: var(--ink); }
.m-section-title { font: 700 1rem var(--font-heading); color: var(--ink); display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
.m-section-title i { color: var(--red); }

/* Blood inventory grid in modal */
.inv-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 22px; }
.inv-cell { background: var(--cream); border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 14px 10px; text-align: center; border-top: 3px solid var(--line); transition: .2s; }
.inv-cell.status-safe { border-top-color: var(--green); }
.inv-cell.status-low { border-top-color: var(--yellow); }
.inv-cell.status-critical { border-top-color: var(--red); }
.inv-bg { font: 800 1.1rem var(--font-heading); color: var(--ink); margin-bottom: 4px; }
.inv-units { font: 700 .78rem var(--font-main); color: var(--muted); }
.inv-status { font-size: .65rem; font-weight: 800; margin-top: 4px; padding: 2px 7px; border-radius: 10px; display: inline-block; }
.inv-cell.status-safe .inv-status { background: var(--green-soft); color: var(--green); }
.inv-cell.status-low .inv-status { background: var(--yellow-soft); color: #92400e; }
.inv-cell.status-critical .inv-status { background: var(--red-soft); color: var(--red-deep); }

/* Emergency table in modal */
.emer-table { width: 100%; border-collapse: collapse; }
.emer-table thead th { background: var(--red-soft); color: var(--red-deep); padding: 10px 13px; font: 800 .78rem var(--font-main); text-align: left; }
.emer-table tbody td { padding: 10px 13px; border-bottom: 1px solid var(--line); font-size: .87rem; }
.emer-table tbody tr:last-child td { border-bottom: none; }
.emer-table tbody tr:hover { background: var(--cream); }
.prio-critical { background: var(--red-soft); color: var(--red-deep); padding: 3px 9px; border-radius: 20px; font: 800 .72rem var(--font-main); }
.prio-urgent { background: var(--yellow-soft); color: #92400e; padding: 3px 9px; border-radius: 20px; font: 800 .72rem var(--font-main); }
.prio-normal { background: var(--blue-soft); color: var(--blue); padding: 3px 9px; border-radius: 20px; font: 800 .72rem var(--font-main); }

/* Mobile */
@media(max-width: 640px) {
  .hero h1 { font-size: 2rem; }
  .inv-grid { grid-template-columns: repeat(2, 1fr); }
  .m-info-grid { grid-template-columns: 1fr; }
}

/* Grain texture */
</style>
</head>
<body>
<canvas id="bgCanvas" aria-hidden="true"></canvas>
<div class="grain" aria-hidden="true"></div>
<canvas id="cursorCanvas" aria-hidden="true"></canvas>

<!-- ECG Topbar -->
<div class="ecg-topbar">
  <div class="ecg-live"><span class="pulse-dot"></span>LifeFlow Hospital Network</div>
  <div><?= count($hospitals) ?> hospitals registered &bull; <?php echo date('d M Y, H:i'); ?></div>
</div>

<!-- Header -->
<header id="mainHeader">
  <a href="../index.php" class="brand"><i class="fas fa-droplet"></i><span>LifeFlow</span></a>
  <div class="header-actions">
    <?php if(isset($_SESSION['hospital_id'])): ?>
      <span class="hospital-login-badge"><i class="fas fa-hospital"></i> <?= htmlspecialchars($_SESSION['hospital_uid']??'') ?></span>
      <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="logout.php" class="btn btn-outline"><i class="fas fa-sign-out-alt"></i> Logout</a>
    <?php else: ?>
      <a href="../login.html" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i> Hospital Login</a>
      <a href="register.php" class="btn btn-primary"><i class="fas fa-plus"></i> Register Your Hospital</a>
    <?php endif; ?>
  </div>
</header>

<!-- Hero -->
<section class="hero">
  <div class="hero-badge"><i class="fas fa-hospital"></i> Verified Hospital Directory</div>
  <h1>Find <span>Verified Hospitals</span><br>Near You</h1>
  <p>Discover hospitals with blood banks, real-time inventory, and emergency services across the LifeFlow network.</p>
  <div class="search-container">
    <i class="fas fa-search search-icon"></i>
    <input type="text" id="searchInput" class="search-input" placeholder="Search by city, district or hospital name...">
  </div>
</section>

<!-- Stats -->
<div class="stats-section">
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-hospital"></i></div>
      <div class="stat-value" id="sc-hospitals" data-count="<?= $stats['total_hospitals'] ?>"><?= $stats['total_hospitals'] ?></div>
      <div class="stat-label">Verified Hospitals</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-building-user"></i></div>
      <div class="stat-value" id="sc-banks" data-count="<?= $stats['with_blood_bank'] ?>"><?= $stats['with_blood_bank'] ?></div>
      <div class="stat-label">Blood Banks</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-truck-medical"></i></div>
      <div class="stat-value" id="sc-emer" data-count="<?= $stats['active_emergencies'] ?>"><?= $stats['active_emergencies'] ?></div>
      <div class="stat-label">Active Emergencies</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon"><i class="fas fa-droplet"></i></div>
      <div class="stat-value" id="sc-units" data-count="<?= $stats['total_blood_units'] ?>"><?= $stats['total_blood_units'] ?></div>
      <div class="stat-label">Total Blood Units</div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="filters-section">
  <span class="filters-label"><i class="fas fa-filter" style="color:var(--red);margin-right:4px"></i>Filter:</span>
  <button class="filter-btn active" data-filter="all">All Hospitals</button>
  <button class="filter-btn" data-filter="government"><i class="fas fa-landmark"></i> Government</button>
  <button class="filter-btn" data-filter="private"><i class="fas fa-building"></i> Private</button>
  <button class="filter-btn" data-filter="trust"><i class="fas fa-hands-helping"></i> Trust</button>
  <button class="filter-btn" data-filter="medical_college"><i class="fas fa-graduation-cap"></i> Medical College</button>
</div>

<!-- Directory -->
<div class="directory-section">
  <div class="directory-grid" id="directoryGrid">
    <?php if(empty($hospitals)): ?>
    <div class="empty-state">
      <i class="fas fa-hospital-user"></i>
      <h2>No Hospitals Registered Yet</h2>
      <p>Be the first to register your hospital on the LifeFlow network.</p>
      <a href="register.php" class="btn btn-primary"><i class="fas fa-plus"></i> Register Your Hospital</a>
    </div>
    <?php else: foreach($hospitals as $h):
      $typeKey = strtolower(str_replace(' ','_',$h['hospital_type']));
    ?>
    <div class="hospital-card" 
         data-type="<?= $typeKey ?>" 
         data-search="<?= strtolower($h['hospital_name'].' '.$h['city'].' '.$h['district']) ?>"
         onclick="openHospModal(<?= $h['id'] ?>)">
      <?php if($h['open_emergencies'] > 0): ?>
      <div class="emergency-badge"><i class="fas fa-bell"></i> <?= $h['open_emergencies'] ?> Emergency</div>
      <?php endif; ?>
      <div class="card-top">
        <div class="hosp-avatar"><i class="fas fa-hospital"></i></div>
        <div class="hosp-meta">
          <h3><?= htmlspecialchars($h['hospital_name']) ?></h3>
          <span class="uid-pill"><i class="fas fa-id-badge"></i> <?= htmlspecialchars($h['hospital_uid']) ?></span>
        </div>
      </div>
      <span class="type-badge type-<?= $typeKey ?>"><?= ucwords(str_replace('_',' ',$h['hospital_type'])) ?></span>
      <div class="info-row"><i class="fas fa-location-dot"></i><span><?= htmlspecialchars($h['city']) ?>, <?= htmlspecialchars($h['district']) ?></span></div>
      <div class="info-row"><i class="fas fa-phone"></i><span><?= htmlspecialchars($h['contact_phone']) ?></span></div>
      <?php if($h['official_email']): ?>
      <div class="info-row"><i class="fas fa-envelope"></i><span><?= htmlspecialchars($h['official_email']) ?></span></div>
      <?php endif; ?>
      <div class="facilities">
        <span class="facility <?= $h['has_blood_bank']?'active':'inactive' ?>"><i class="fas fa-<?= $h['has_blood_bank']?'check':'times' ?>"></i> Blood Bank</span>
        <span class="facility <?= $h['is_24_7']?'active':'inactive' ?>"><i class="fas fa-<?= $h['is_24_7']?'check':'times' ?>"></i> 24/7</span>
        <span class="facility <?= $h['has_blood_storage']?'active':'inactive' ?>"><i class="fas fa-<?= $h['has_blood_storage']?'check':'times' ?>"></i> Storage</span>
      </div>
      <?php if($h['total_units']): ?>
      <div class="blood-bar">
        <span><i class="fas fa-droplet" style="color:var(--red);margin-right:6px"></i>Total Blood Units</span>
        <span class="units"><?= $h['total_units'] ?> Units</span>
      </div>
      <?php endif; ?>
      <div class="card-footer">
        <button class="btn-view"><i class="fas fa-eye"></i> View Details & Inventory</button>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Detail Modal -->
<div class="modal-overlay" id="hospModal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="m-name"><i class="fas fa-hospital"></i> Hospital Details</h2>
      <button class="modal-close" onclick="closeHospModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body" id="modalBody">
      <div style="text-align:center;padding:40px"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--red)"></i></div>
    </div>
  </div>
</div>

<!-- Embed hospital data as JS -->
<script>
const HOSP_DATA = <?php echo json_encode($hospitals, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const INV_DATA = <?php echo json_encode($inv_by_hospital, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const EMER_DATA = <?php echo json_encode($emer_by_hospital, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const BLOOD_GROUPS = ['A+','A-','B+','B-','O+','O-','AB+','AB-'];

/* ——— Search & Filter ——— */
const searchInput = document.getElementById('searchInput');
const filterBtns = document.querySelectorAll('.filter-btn');
const cards = document.querySelectorAll('.hospital-card');

function filterHospitals() {
  const q = searchInput.value.toLowerCase();
  const af = document.querySelector('.filter-btn.active').dataset.filter;
  cards.forEach(card => {
    const sm = card.dataset.search.includes(q);
    const tm = af === 'all' || card.dataset.type.includes(af);
    card.style.display = (sm && tm) ? 'block' : 'none';
  });
}
searchInput.addEventListener('input', filterHospitals);
filterBtns.forEach(btn => btn.addEventListener('click', () => {
  filterBtns.forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  filterHospitals();
}));

/* ——— Modal ——— */
const modal = document.getElementById('hospModal');
const modalBody = document.getElementById('modalBody');
const mName = document.getElementById('m-name');

function openHospModal(id) {
  const h = HOSP_DATA.find(x => x.id == id);
  if (!h) return;
  mName.innerHTML = '<i class="fas fa-hospital" style="color:var(--red)"></i> ' + escH(h.hospital_name);
  const inv = INV_DATA[id] || {};
  const emers = EMER_DATA[id] || [];
  const typeKey = (h.hospital_type||'').toLowerCase().replace(/\s+/g,'_');
  const typeBadge = `<span class="type-badge type-${typeKey}">${ucwords(h.hospital_type)}</span>`;
  const f = (v, yes, no) => v ? `<span class="facility active"><i class="fas fa-check"></i> ${yes}</span>` : `<span class="facility inactive"><i class="fas fa-times"></i> ${no}</span>`;

  let invHtml = '';
  BLOOD_GROUPS.forEach(bg => {
    const u = inv[bg] ? inv[bg].available_units : 0;
    const m = inv[bg] ? inv[bg].minimum_units : 10;
    let cls = u < m ? 'status-critical' : (u < m*2 ? 'status-low' : 'status-safe');
    let slabel = u < m ? 'Critical' : (u < m*2 ? 'Low' : 'Safe');
    invHtml += `<div class="inv-cell ${cls}"><div class="inv-bg">${bg}</div><div class="inv-units">${u} units</div><span class="inv-status">${slabel}</span></div>`;
  });

  let emerHtml = '';
  if (emers.length) {
    emerHtml = `<div class="m-section-title"><i class="fas fa-bell"></i> Active Emergency Requests</div>
    <div style="overflow-x:auto;border-radius:var(--radius-sm);border:1px solid var(--line)">
    <table class="emer-table"><thead><tr><th>Blood Group</th><th>Units</th><th>Priority</th><th>Category</th><th>Required By</th></tr></thead><tbody>`;
    emers.forEach(e => {
      const pc = e.priority==='critical'?'prio-critical':(e.priority==='urgent'?'prio-urgent':'prio-normal');
      const rby = e.required_by ? new Date(e.required_by).toLocaleDateString('en-IN',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}) : '-';
      emerHtml += `<tr><td><strong>${escH(e.blood_group)}</strong></td><td>${e.required_units}</td><td><span class="${pc}">${ucwords(e.priority)}</span></td><td>${ucwords(e.category)}</td><td>${rby}</td></tr>`;
    });
    emerHtml += `</tbody></table></div><br>`;
  }

  modalBody.innerHTML = `
    ${typeBadge}
    <div class="m-info-grid" style="margin-top:14px">
      <div class="m-info-item"><label>Address</label><span>${escH(h.address)}</span></div>
      <div class="m-info-item"><label>City / District</label><span>${escH(h.city)}, ${escH(h.district||'-')}</span></div>
      <div class="m-info-item"><label>PIN Code</label><span>${escH(h.pin_code||'-')}</span></div>
      <div class="m-info-item"><label>License No.</label><span>${escH(h.license_number||'-')}</span></div>
      <div class="m-info-item"><label>Contact Phone</label><span><a href="tel:${escH(h.contact_phone)}" style="color:var(--red)">${escH(h.contact_phone)}</a></span></div>
      <div class="m-info-item"><label>Email</label><span>${escH(h.official_email||'-')}</span></div>
      ${h.emergency_phone ? `<div class="m-info-item"><label>Emergency Line</label><span><a href="tel:${escH(h.emergency_phone)}" style="color:var(--red)">${escH(h.emergency_phone)}</a></span></div>` : ''}
    </div>
    <div class="facilities" style="margin-bottom:22px">
      ${f(h.has_blood_bank,'Blood Bank Available','No Blood Bank')}
      ${f(h.is_24_7,'24/7 Service','No 24/7')}
      ${f(h.has_blood_storage,'Storage Facility','No Storage')}
    </div>
    <div class="m-section-title"><i class="fas fa-tint"></i> Blood Inventory</div>
    <div class="inv-grid">${invHtml}</div>
    ${emerHtml}
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      ${h.contact_phone ? `<a href="tel:${escH(h.contact_phone)}" class="btn btn-primary" style="flex:1;justify-content:center"><i class="fas fa-phone"></i> Call Hospital</a>` : ''}
      <button class="btn btn-outline" onclick="closeHospModal()" style="flex:1;justify-content:center"><i class="fas fa-times"></i> Close</button>
    </div>
  `;
  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeHospModal() {
  modal.classList.remove('active');
  document.body.style.overflow = '';
}
modal.addEventListener('click', e => { if(e.target === modal) closeHospModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeHospModal(); });

function escH(str) { return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function ucwords(str) { return String(str||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase()); }

/* ——— Animated Counters ——— */
function animateCounter(el, target, dur=1600) {
  let t0 = performance.now();
  function step(t) {
    const p = Math.min((t-t0)/dur, 1);
    el.textContent = Math.floor((1-Math.pow(1-p,3))*target);
    if(p<1) requestAnimationFrame(step); else el.textContent=target;
  }
  requestAnimationFrame(step);
}
window.addEventListener('load', () => {
  document.querySelectorAll('[data-count]').forEach(el => animateCounter(el, parseInt(el.dataset.count)));
});

/* ——— Header scroll ——— */
window.addEventListener('scroll', () => document.getElementById('mainHeader').classList.toggle('scrolled', window.scrollY > 10));

/* ——— BG Canvas: ambient blobs + heartbeat line ——— */
const bgCanvas = document.getElementById('bgCanvas'), bgCtx = bgCanvas.getContext('2d');
let bgW, bgH;
function resizeBg() { bgW = bgCanvas.width = window.innerWidth; bgH = bgCanvas.height = window.innerHeight; }
resizeBg(); window.addEventListener('resize', resizeBg);
const bgCells = Array.from({length:7}, () => ({
  x: Math.random(), y: Math.random(), r: 90 + Math.random()*160,
  vx: (Math.random()-.5)*.00012, vy: (Math.random()-.5)*.00012,
  hue: Math.random()>.5 ? 'rgba(214,40,57,' : 'rgba(15,122,92,'
}));
let waveT = 0;
function drawHBLine() {
  bgCtx.save(); bgCtx.globalAlpha = .14; bgCtx.strokeStyle = '#d62839'; bgCtx.lineWidth = 1.4;
  bgCtx.beginPath();
  for(let x=0; x<=bgW; x+=6) {
    const phase=(x+waveT)*.02; let y2=46+Math.sin(phase*.6)*3;
    const spike=(x+waveT)%260;
    if(spike>40&&spike<70) y2-=(spike-40)*1.7;
    else if(spike>=70&&spike<100) y2+=(spike-70)*1.7-51;
    if(x===0) bgCtx.moveTo(x,y2); else bgCtx.lineTo(x,y2);
  }
  bgCtx.stroke(); bgCtx.restore();
}
function animateBg() {
  bgCtx.clearRect(0,0,bgW,bgH);
  bgCells.forEach(c => {
    c.x+=c.vx; c.y+=c.vy;
    if(c.x<-.2||c.x>1.2) c.vx*=-1;
    if(c.y<-.2||c.y>1.2) c.vy*=-1;
    const cx=c.x*bgW, cy=c.y*bgH;
    const g=bgCtx.createRadialGradient(cx,cy,0,cx,cy,c.r);
    g.addColorStop(0,c.hue+'.09)'); g.addColorStop(1,c.hue+'0)');
    bgCtx.fillStyle=g; bgCtx.beginPath(); bgCtx.arc(cx,cy,c.r,0,Math.PI*2); bgCtx.fill();
  });
  drawHBLine(); waveT+=1.1;
  requestAnimationFrame(animateBg);
}
animateBg();

/* ——— Cursor Blood Drop Trail ——— */
const cursorCanvas = document.getElementById('cursorCanvas'), cursorCtx = cursorCanvas.getContext('2d'), drops = [];
function resizeCursor() { cursorCanvas.width=window.innerWidth; cursorCanvas.height=window.innerHeight; }
function animateCursor() {
  cursorCtx.clearRect(0,0,cursorCanvas.width,cursorCanvas.height);
  for(let i=drops.length-1;i>=0;i--) {
    const p=drops[i]; p.x+=p.vx; p.y+=p.vy; p.size*=.95; p.opacity-=.025;
    cursorCtx.fillStyle='rgba(214,40,57,'+Math.max(0,p.opacity)+')';
    cursorCtx.beginPath(); cursorCtx.arc(p.x,p.y,Math.max(.1,p.size),0,Math.PI*2); cursorCtx.fill();
    if(p.opacity<=0||p.size<=.5) drops.splice(i,1);
  }
  requestAnimationFrame(animateCursor);
}
window.addEventListener('resize', resizeCursor);
window.addEventListener('mousemove', e => { if(Math.random()>.4) drops.push({x:e.clientX,y:e.clientY,size:Math.random()*6+3,vx:(Math.random()-.5)*2,vy:Math.random()*2+1,opacity:.8}); });
resizeCursor(); animateCursor();
</script>
</body>
</html>
