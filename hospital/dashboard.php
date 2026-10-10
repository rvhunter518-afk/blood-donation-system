<?php
session_start();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'hospital') {
    header("Location: ../login.html?error=role");
    exit();
}

require_once '../config/database.php';
$conn = getDbConnection();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$hospital_id = $_SESSION['hospital_id'];

$stmt = $conn->prepare("SELECT * FROM hospitals WHERE id = ?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$hospital_info = $stmt->get_result()->fetch_assoc();
$stmt->close();

$inventory = [];
$blood_groups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$stmt = $conn->prepare("SELECT * FROM hospital_blood_inventory WHERE hospital_id = ?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $inventory[$row['blood_group']] = $row; }
$stmt->close();
foreach ($blood_groups as $bg) {
    if (!isset($inventory[$bg])) $inventory[$bg] = ['blood_group'=>$bg,'available_units'=>0,'minimum_units'=>10];
}

$components = [];
$comp_types = ['packed_rbc','platelets','ffp','cryoprecipitate'];
$stmt = $conn->prepare("SELECT * FROM hospital_blood_components WHERE hospital_id = ?");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $components[$row['component']] = $row; }
$stmt->close();
foreach ($comp_types as $ct) {
    if (!isset($components[$ct])) $components[$ct] = ['component'=>$ct,'available_units'=>0];
}

$expiring_batches = [];
$stmt = $conn->prepare("SELECT * FROM hospital_blood_batches WHERE hospital_id = ? AND is_used = 0 AND expiry_date <= DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY expiry_date ASC");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $expiring_batches[] = $row; }
$stmt->close();

$all_batches = [];
$stmt = $conn->prepare("SELECT * FROM hospital_blood_batches WHERE hospital_id = ? AND is_used = 0 ORDER BY expiry_date ASC");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $all_batches[] = $row; }
$stmt->close();

$emergencies = [];
$stmt = $conn->prepare("SELECT * FROM hospital_emergency_requests WHERE hospital_id = ? AND status = 'open' ORDER BY priority DESC, created_at ASC");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $emergencies[] = $row; }
$stmt->close();

$all_emergencies = [];
$stmt = $conn->prepare("SELECT * FROM hospital_emergency_requests WHERE hospital_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $hospital_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $all_emergencies[] = $row; }
$stmt->close();

$recent_requests = [];
$stmt = $conn->prepare("SELECT * FROM blood_requests ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $recent_requests[] = $row; }
$stmt->close();

$users = [];
$stmt = $conn->prepare("SELECT id, full_name, email, blood_type, phone, city, role, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $users[] = $row; }
$stmt->close();

$donors = [];
$stmt = $conn->prepare("SELECT d.*, u.full_name, u.email FROM donors d JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $donors[] = $row; }
$stmt->close();

$hospitals_list = [];
$stmt = $conn->prepare("SELECT * FROM hospitals");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) { $hospitals_list[] = $row; }
$stmt->close();

$total_blood_units = 0;
$critical_groups = 0;
foreach ($inventory as $inv) {
    $total_blood_units += $inv['available_units'];
    if ($inv['available_units'] < $inv['minimum_units']) $critical_groups++;
}
$active_emergencies = count($emergencies);
$registered_users = count($users);

function getInventoryStatus($available, $minimum) {
    if ($available < $minimum) return ['badge'=>'🔴 Critical','class'=>'badge-critical'];
    if ($available >= $minimum && $available < $minimum * 2) return ['badge'=>'🟡 Low','class'=>'badge-low'];
    return ['badge'=>'🟢 Safe','class'=>'badge-safe'];
}
$csrf = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hospital Dashboard — LifeFlow</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
  --ink:#171b22;--muted:#66707c;--paper:#fffdfb;--cream:#fff8f3;
  --red:#d62839;--red-deep:#8f1220;--red-soft:#fdeceb;
  --green:#0f7a5c;--green-soft:#e6f6ee;
  --yellow:#f59e0b;--yellow-soft:#fffbeb;
  --blue:#1d4ed8;--blue-soft:#eff6ff;
  --line:#ecdfda;--shadow:0 18px 40px rgba(143,18,32,.09),0 6px 14px rgba(23,27,34,.04);
  --radius-lg:26px;--radius-md:18px;--radius-sm:12px;
  --font-main:'DM Sans',sans-serif;--font-heading:'Space Grotesk',sans-serif;
  --sidebar-w:260px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-main);background:var(--paper);color:var(--ink);min-height:100vh;overflow-x:hidden}
#bgCanvas{position:fixed;inset:0;width:100%;height:100%;z-index:-2;pointer-events:none}
#cursorCanvas{position:fixed;inset:0;width:100%;height:100%;z-index:30;pointer-events:none}
.grain{position:fixed;inset:0;z-index:-1;pointer-events:none;opacity:.03;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='90' height='90'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>")}
::-webkit-scrollbar{width:6px;height:6px}::-webkit-scrollbar-track{background:var(--cream)}::-webkit-scrollbar-thumb{background:linear-gradient(var(--red),var(--red-deep));border-radius:3px}

/* ECG Topbar */
.ecg-topbar{display:flex;justify-content:space-between;align-items:center;padding:7px 24px;background:#fffdfbe8;backdrop-filter:blur(16px);border-bottom:1px solid var(--line);font-size:.77rem;color:var(--muted);z-index:10;position:relative}
.pulse-dot{width:8px;height:8px;border-radius:50%;background:var(--red);box-shadow:0 0 10px rgba(214,40,57,.7);animation:pulseDot 1.6s ease infinite;display:inline-block;margin-right:7px}
@keyframes pulseDot{50%{transform:scale(1.5);opacity:.6}}

/* Header */
header{display:flex;justify-content:space-between;align-items:center;padding:12px 24px;border-bottom:1px solid var(--line);background:#fffdfbdd;backdrop-filter:blur(20px);position:sticky;top:0;z-index:9;transition:box-shadow .25s}
header.scrolled{box-shadow:0 8px 24px rgba(143,18,32,.07)}
.brand{display:flex;align-items:center;gap:10px;font:700 1.3rem var(--font-heading);color:var(--ink);text-decoration:none}
.brand i{display:grid;place-items:center;width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;font-size:.85rem}
.brand span{color:var(--red)}
.header-right{display:flex;align-items:center;gap:10px}
.hosp-badge{display:inline-flex;align-items:center;gap:6px;background:var(--red-soft);color:var(--red-deep);border-radius:20px;padding:5px 12px;font:700 .8rem var(--font-heading)}
.header-link{color:var(--muted);text-decoration:none;font-weight:700;font-size:.85rem;padding:7px 14px;border:1px solid var(--line);border-radius:50px;transition:.2s}
.header-link:hover{color:var(--red);border-color:var(--red)}
.header-link.logout{background:var(--red-soft);color:var(--red-deep);border-color:rgba(214,40,57,.2)}
.header-link.logout:hover{background:var(--red);color:#fff}

/* Layout */
.app-layout{display:flex;min-height:calc(100vh - 84px)}

/* Sidebar */
.sidebar{width:var(--sidebar-w);flex:none;background:#fffdfb;border-right:1px solid var(--line);position:sticky;top:84px;height:calc(100vh - 84px);overflow-y:auto;display:flex;flex-direction:column}
.sidebar-top{padding:20px 16px 14px;border-bottom:1px solid var(--line)}
.sidebar-hosp-name{font:700 .95rem var(--font-heading);color:var(--ink);margin-bottom:4px}
.sidebar-uid{font-size:.78rem;color:var(--muted)}
.side-nav{flex:1;padding:10px 0}
.side-nav-item{display:flex;align-items:center;gap:12px;padding:11px 18px;color:var(--muted);font:700 .88rem var(--font-main);cursor:pointer;border-left:3px solid transparent;transition:.18s;user-select:none}
.side-nav-item i{width:18px;text-align:center;color:var(--red);font-size:.9rem}
.side-nav-item:hover{color:var(--red-deep);background:var(--cream);border-left-color:rgba(214,40,57,.3)}
.side-nav-item.active{color:var(--red-deep);background:var(--red-soft);border-left-color:var(--red)}
.sidebar-footer{padding:14px 18px;border-top:1px solid var(--line)}
.sidebar-footer a{display:flex;align-items:center;gap:8px;color:var(--muted);text-decoration:none;font-weight:700;font-size:.85rem;transition:.2s}
.sidebar-footer a:hover{color:var(--red)}

/* Main Content */
.main-content{flex:1;padding:28px 30px;overflow-y:auto;min-width:0}
.tab-content{display:none;animation:fadeIn .3s ease}
.tab-content.active{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@keyframes slideUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}

/* Section headers */
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;flex-wrap:wrap;gap:12px}
.section-title{font:700 1.5rem var(--font-heading);color:var(--ink);display:flex;align-items:center;gap:10px}
.section-title i{color:var(--red)}
.section-sub{color:var(--muted);font-size:.88rem;margin-top:3px}

/* Stats grid */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-bottom:28px}
.stat-card{background:#ffffffcc;border:1px solid var(--line);border-radius:var(--radius-md);padding:22px 20px;box-shadow:var(--shadow);position:relative;overflow:hidden;cursor:default;transition:transform .2s,box-shadow .2s}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 24px 50px rgba(143,18,32,.12)}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--red),var(--red-deep))}
.stat-card.green::before{background:linear-gradient(90deg,var(--green),#0a5e44)}
.stat-card.yellow::before{background:linear-gradient(90deg,var(--yellow),#d97706)}
.stat-card.blue::before{background:linear-gradient(90deg,var(--blue),#1e40af)}
.stat-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:#fff;background:linear-gradient(135deg,var(--red),var(--red-deep));margin-bottom:12px;box-shadow:0 5px 14px rgba(143,18,32,.22)}
.stat-card.green .stat-icon{background:linear-gradient(135deg,var(--green),#0a5e44)}
.stat-card.yellow .stat-icon{background:linear-gradient(135deg,var(--yellow),#d97706)}
.stat-card.blue .stat-icon{background:linear-gradient(135deg,var(--blue),#1e40af)}
.stat-value{font:700 2rem var(--font-heading);color:var(--ink);margin-bottom:4px}
.stat-label{font-size:.8rem;color:var(--muted);font-weight:600}

/* Cards */
.card{background:#ffffffcc;border:1px solid var(--line);border-radius:var(--radius-md);padding:24px;box-shadow:var(--shadow);margin-bottom:22px}
.card-title{font:700 1rem var(--font-heading);color:var(--ink);margin-bottom:16px;display:flex;align-items:center;gap:8px}
.card-title i{color:var(--red)}

/* Hospital info grid */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.info-item label{font-size:.75rem;font-weight:700;color:var(--muted);display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.05em}
.info-item span{font-size:.92rem;color:var(--ink);font-weight:600}
.facility-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.f-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:.78rem;font-weight:700}
.f-pill.on{background:var(--green-soft);color:var(--green)}
.f-pill.off{background:var(--cream);color:var(--muted)}

/* Blood status bars (overview) */
.blood-status-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.blood-bar-card{background:var(--cream);border:1px solid var(--line);border-radius:var(--radius-sm);padding:14px}
.blood-bar-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.bg-pill{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:38px;background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;border-radius:10px;font:800 .85rem var(--font-heading);padding:0 8px}
.blood-bar-track{height:8px;background:var(--line);border-radius:4px;overflow:hidden}
.blood-bar-fill{height:100%;border-radius:4px;transition:width .8s ease}
.fill-safe{background:linear-gradient(90deg,var(--green),#059669)}
.fill-low{background:linear-gradient(90deg,var(--yellow),#d97706)}
.fill-critical{background:linear-gradient(90deg,var(--red),var(--red-deep))}
.blood-bar-units{font-size:.78rem;color:var(--muted);margin-top:6px;display:flex;justify-content:space-between}

/* Badges */
.badge-critical{background:var(--red-soft);color:var(--red-deep);padding:4px 10px;border-radius:20px;font:800 .75rem var(--font-main)}
.badge-low{background:var(--yellow-soft);color:#92400e;padding:4px 10px;border-radius:20px;font:800 .75rem var(--font-main)}
.badge-safe{background:var(--green-soft);color:var(--green);padding:4px 10px;border-radius:20px;font:800 .75rem var(--font-main)}
.badge-critical-prio{background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:4px 10px;border-radius:20px;font:800 .75rem var(--font-main)}
.badge-urgent-prio{background:var(--yellow-soft);border:1px solid #fcd34d;color:#92400e;padding:4px 10px;border-radius:20px;font:800 .75rem var(--font-main)}
.badge-normal-prio{background:var(--blue-soft);border:1px solid #bfdbfe;color:var(--blue);padding:4px 10px;border-radius:20px;font:800 .75rem var(--font-main)}
.role-badge-user{background:var(--blue-soft);color:var(--blue);padding:3px 10px;border-radius:20px;font:700 .75rem var(--font-main)}
.role-badge-donor{background:var(--red-soft);color:var(--red-deep);padding:3px 10px;border-radius:20px;font:700 .75rem var(--font-main)}

/* Tables */
.table-wrap{overflow-x:auto;border-radius:var(--radius-sm);border:1px solid var(--line)}
table{width:100%;border-collapse:collapse}
thead th{background:var(--red-soft);color:var(--red-deep);padding:11px 14px;font:800 .8rem var(--font-main);text-align:left;white-space:nowrap}
tbody td{padding:11px 14px;border-bottom:1px solid var(--line);font-size:.88rem;vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:var(--cream)}
table input[type=number]{width:80px;border:1px solid var(--line);border-radius:8px;padding:7px 9px;font:inherit;text-align:center;background:#fff;color:var(--ink);font-size:.88rem}
table input[type=number]:focus{outline:0;border-color:var(--red);box-shadow:0 0 0 3px rgba(214,40,57,.1)}

/* Expiry row colors */
tr.row-expired td{background:#fef2f2;color:#991b1b}
tr.row-warn td{background:var(--yellow-soft)}
tr.row-ok td{background:var(--green-soft)}

/* Buttons */
.btn-red{background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;border:none;border-radius:50px;padding:9px 20px;font:700 .88rem var(--font-main);cursor:pointer;box-shadow:0 5px 14px rgba(143,18,32,.22);transition:.22s;display:inline-flex;align-items:center;gap:7px}
.btn-red:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(143,18,32,.3)}
.btn-ghost{background:var(--paper);border:1.5px solid var(--line);color:var(--ink);border-radius:50px;padding:9px 20px;font:700 .88rem var(--font-main);cursor:pointer;transition:.22s;display:inline-flex;align-items:center;gap:7px}
.btn-ghost:hover{border-color:var(--red);color:var(--red)}
.btn-sm{padding:6px 14px;font-size:.78rem}
.btn-green{background:linear-gradient(135deg,var(--green),#0a5e44);color:#fff;border:none;border-radius:50px;padding:7px 14px;font:700 .78rem var(--font-main);cursor:pointer;transition:.2s;display:inline-flex;align-items:center;gap:5px}
.btn-green:hover{transform:translateY(-1px)}

/* Summary bar */
.summary-bar{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:22px}
.summary-pill{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:50px;font:700 .85rem var(--font-main)}
.summary-pill.total{background:var(--cream);border:1px solid var(--line);color:var(--ink)}
.summary-pill.critical{background:var(--red-soft);border:1px solid rgba(214,40,57,.2);color:var(--red-deep)}
.summary-pill.low{background:var(--yellow-soft);border:1px solid rgba(245,158,11,.2);color:#92400e}
.summary-pill.safe{background:var(--green-soft);border:1px solid rgba(15,122,92,.2);color:var(--green)}

/* Search bar */
.search-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;align-items:center}
.search-input{border:1.5px solid var(--line);border-radius:50px;padding:9px 16px 9px 38px;font:inherit;background:#fff;color:var(--ink);min-width:220px;position:relative;font-size:.88rem;transition:.2s}
.search-wrap{position:relative}
.search-wrap .si{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.82rem}
.search-input:focus{outline:0;border-color:var(--red);box-shadow:0 0 0 3px rgba(214,40,57,.1)}
.filter-sel{border:1.5px solid var(--line);border-radius:50px;padding:9px 14px;font:inherit;background:#fff;color:var(--ink);font-size:.88rem;cursor:pointer}
.filter-sel:focus{outline:0;border-color:var(--red)}

/* Emergency cards */
.emer-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px}
.emer-card{background:#fff;border:1.5px solid var(--line);border-radius:var(--radius-md);padding:20px;box-shadow:var(--shadow);position:relative;overflow:hidden}
.emer-card.priority-critical{border-color:var(--red)}
.emer-card.priority-urgent{border-color:var(--yellow)}
.emer-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.emer-card.priority-critical::before{background:linear-gradient(90deg,var(--red),var(--red-deep))}
.emer-card.priority-urgent::before{background:linear-gradient(90deg,var(--yellow),#d97706)}
.emer-card.priority-normal::before{background:linear-gradient(90deg,var(--blue),#1e40af)}
.emer-bg-badge{display:inline-flex;align-items:center;justify-content:center;min-width:44px;height:44px;background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;border-radius:12px;font:800 1rem var(--font-heading);padding:0 10px;margin-bottom:12px}
.emer-card h4{font:700 1rem var(--font-heading);color:var(--ink);margin-bottom:6px}
.emer-card .emer-meta{font-size:.82rem;color:var(--muted);margin-bottom:12px}
.emer-actions{display:flex;gap:8px;flex-wrap:wrap}

/* Component cards */
.comp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
.comp-card{background:#fff;border:1.5px solid var(--line);border-top:3px solid var(--red);border-radius:var(--radius-md);padding:20px;text-align:center;box-shadow:var(--shadow);transition:border-color .2s,box-shadow .2s}
.comp-card:hover{border-color:var(--red);box-shadow:0 12px 30px rgba(143,18,32,.1)}
.comp-icon{width:48px;height:48px;background:linear-gradient(135deg,var(--red),var(--red-deep));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;margin:0 auto 12px;box-shadow:0 5px 14px rgba(143,18,32,.22)}
.comp-card h4{font:700 .95rem var(--font-heading);color:var(--ink);margin-bottom:4px}
.comp-card small{color:var(--muted);font-size:.75rem;display:block;margin-bottom:14px}
.comp-units{font:700 2rem var(--font-heading);color:var(--red);margin-bottom:6px}
.comp-updated{font-size:.72rem;color:var(--muted);margin-bottom:12px}
.comp-input{width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px;font:700 1.4rem var(--font-heading);text-align:center;background:var(--cream);color:var(--ink);display:none}
.comp-input:focus{outline:0;border-color:var(--red);box-shadow:0 0 0 3px rgba(214,40,57,.1);background:#fff}

/* Toast */
#toastContainer{position:fixed;top:22px;right:22px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none}
.toast{background:#fff;border:1px solid var(--line);border-radius:14px;padding:13px 18px;box-shadow:var(--shadow);font:700 .88rem var(--font-main);transform:translateX(130%);transition:transform .3s ease;max-width:300px;display:flex;align-items:center;gap:9px;pointer-events:auto}
.toast.show{transform:translateX(0)}
.toast-success{border-left:4px solid var(--green);color:var(--green)}
.toast-error{border-left:4px solid var(--red);color:var(--red-deep)}
.toast-info{border-left:4px solid var(--blue);color:var(--blue)}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(6px);z-index:500;display:none;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal-box{background:var(--paper);border-radius:var(--radius-lg);padding:36px;max-width:520px;width:100%;box-shadow:0 30px 80px rgba(143,18,32,.2);animation:slideUp .3s ease;max-height:90vh;overflow-y:auto}
.modal-box h3{font:700 1.4rem var(--font-heading);color:var(--ink);margin-bottom:18px;display:flex;align-items:center;gap:10px}
.modal-box h3 i{color:var(--red)}
.modal-close{float:right;background:var(--cream);border:none;border-radius:8px;width:30px;height:30px;cursor:pointer;color:var(--muted);font-size:.95rem;transition:.2s}
.modal-close:hover{background:var(--red-soft);color:var(--red)}
.m-field{margin-bottom:16px}
.m-field label{display:block;font:700 .85rem var(--font-main);color:var(--ink);margin-bottom:6px}
.m-field input,.m-field select,.m-field textarea{width:100%;border:1.5px solid var(--line);border-radius:10px;padding:10px 13px;font:inherit;background:#fff;color:var(--ink);font-size:.92rem}
.m-field input:focus,.m-field select:focus,.m-field textarea:focus{outline:0;border-color:var(--red);box-shadow:0 0 0 3px rgba(214,40,57,.1)}
.m-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/* Sub-tabs */
.sub-tabs{display:flex;gap:2px;background:var(--cream);border-radius:50px;padding:4px;margin-bottom:20px;border:1px solid var(--line);overflow-x:auto}
.sub-tab-btn{flex:1;padding:9px 16px;border:none;background:none;border-radius:50px;font:700 .85rem var(--font-main);color:var(--muted);cursor:pointer;transition:.2s;white-space:nowrap}
.sub-tab-btn.active{background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;box-shadow:0 4px 12px rgba(143,18,32,.22)}

/* Mobile sidebar toggle */
.mob-menu-btn{display:none;background:var(--red-soft);border:none;border-radius:10px;width:36px;height:36px;align-items:center;justify-content:center;color:var(--red);cursor:pointer;font-size:.95rem}

@media(max-width:768px){
  .sidebar{position:fixed;left:-var(--sidebar-w);top:0;height:100vh;z-index:50;transform:translateX(-100%);transition:transform .3s}
  .sidebar.open{transform:translateX(0)}
  .mob-menu-btn{display:flex}
  .main-content{padding:20px 16px}
  .stats-grid{grid-template-columns:1fr 1fr}
  .comp-grid{grid-template-columns:1fr 1fr}
  .info-grid{grid-template-columns:1fr}
  .m-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<canvas id="bgCanvas" aria-hidden="true"></canvas>
<div class="grain" aria-hidden="true"></div>
<canvas id="cursorCanvas" aria-hidden="true"></canvas>
<div id="toastContainer"></div>

<!-- ECG Topbar -->
<div class="ecg-topbar">
  <div style="display:flex;align-items:center;gap:8px;font-weight:700;color:var(--ink)">
    <span class="pulse-dot"></span>Hospital Control Center
  </div>
  <div><?php echo htmlspecialchars($_SESSION['hospital_uid']??''); ?> &bull; <?php echo date('d M Y, H:i'); ?></div>
</div>

<!-- Header -->
<header id="mainHeader">
  <div style="display:flex;align-items:center;gap:12px">
    <button class="mob-menu-btn" id="mobMenuBtn"><i class="fas fa-bars"></i></button>
    <a href="../index.php" class="brand"><i class="fas fa-droplet"></i><span>LifeFlow</span></a>
  </div>
  <div class="header-right">
    <span class="hosp-badge"><i class="fas fa-hospital"></i> <?php echo htmlspecialchars($hospital_info['hospital_name']??'Hospital'); ?></span>
    <a href="../map.php?view=all" class="header-link" style="color:#2563eb;font-weight:700;"><i class="fas fa-map-location-dot"></i> Live Map</a>
    <a href="directory.php" class="header-link"><i class="fas fa-building-user"></i> Directory</a>
    <a href="tablets.php" class="header-link"><i class="fas fa-table"></i> Tables</a>
    <a href="logout.php" class="header-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</header>

<div class="app-layout">
  <!-- Sidebar -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-top">
      <div class="sidebar-hosp-name"><?php echo htmlspecialchars($hospital_info['hospital_name']??'Hospital'); ?></div>
      <div class="sidebar-uid"><i class="fas fa-id-badge" style="color:var(--red);margin-right:5px"></i><?php echo htmlspecialchars($_SESSION['hospital_uid']??''); ?></div>
    </div>
    <div class="side-nav">
      <?php $navItems = [
        ['overview','home','Overview'],
        ['map','map-location-dot','Network Map'],
        ['inventory','tint','Blood Inventory'],
        ['components','vials','Components'],
        ['expiry','calendar-check','Expiry Tracker'],
        ['emergency','ambulance','Emergency'],
        ['users','users','User Profiles'],
        ['donors','heart-pulse','Donors'],
        ['datatables','table','Data Tables'],
        ['settings','gear','Settings'],
      ]; foreach($navItems as [$id,$icon,$label]): ?>
      <div class="side-nav-item <?= $id==='overview'?'active':'' ?>" data-tab="<?= $id ?>" onclick="switchTab('<?= $id ?>')">
        <i class="fas fa-<?= $icon ?>"></i> <?= $label ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="sidebar-footer">
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </nav>

  <!-- Main -->
  <main class="main-content">

    <!-- OVERVIEW TAB -->
    <div class="tab-content active" id="tab-overview">
      <div class="section-header">
        <div><div class="section-title"><i class="fas fa-home"></i> Dashboard Overview</div>
        <div class="section-sub">Welcome back, <?php echo htmlspecialchars($hospital_info['hospital_name']??'Hospital'); ?></div></div>
      </div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon"><i class="fas fa-tint"></i></div>
          <div class="stat-value" data-count="<?= $total_blood_units ?>"><?= $total_blood_units ?></div>
          <div class="stat-label">Total Blood Units</div>
        </div>
        <div class="stat-card <?= $critical_groups>0?'':'green' ?>">
          <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="stat-value" data-count="<?= $critical_groups ?>"><?= $critical_groups ?></div>
          <div class="stat-label">Critical Groups</div>
        </div>
        <div class="stat-card yellow">
          <div class="stat-icon"><i class="fas fa-ambulance"></i></div>
          <div class="stat-value" data-count="<?= $active_emergencies ?>"><?= $active_emergencies ?></div>
          <div class="stat-label">Active Emergencies</div>
        </div>
        <div class="stat-card blue">
          <div class="stat-icon"><i class="fas fa-users"></i></div>
          <div class="stat-value" data-count="<?= $registered_users ?>"><?= $registered_users ?></div>
          <div class="stat-label">Registered Users</div>
        </div>
      </div>

      <?php if(!empty($expiring_batches)): ?>
      <div style="background:var(--yellow-soft);border:1.5px solid rgba(245,158,11,.3);border-radius:var(--radius-sm);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:700;color:#92400e">
        <i class="fas fa-exclamation-triangle"></i> <?= count($expiring_batches) ?> blood batch(es) expiring within 7 days!
        <button class="btn-ghost btn-sm" onclick="switchTab('expiry')" style="margin-left:auto">View Batches</button>
      </div>
      <?php endif; ?>

      <!-- Hospital Info -->
      <div class="card">
        <div class="card-title"><i class="fas fa-hospital"></i> Hospital Information</div>
        <div class="info-grid">
          <div class="info-item"><label>Hospital Name</label><span><?= htmlspecialchars($hospital_info['hospital_name']??'-') ?></span></div>
          <div class="info-item"><label>Type</label><span><?= ucfirst(str_replace('_',' ',$hospital_info['hospital_type']??'-')) ?></span></div>
          <div class="info-item"><label>License No.</label><span><?= htmlspecialchars($hospital_info['license_number']??'-') ?></span></div>
          <div class="info-item"><label>City / District</label><span><?= htmlspecialchars($hospital_info['city']??'-') ?>, <?= htmlspecialchars($hospital_info['district']??'-') ?></span></div>
          <div class="info-item"><label>Contact Phone</label><span><?= htmlspecialchars($hospital_info['contact_phone']??'-') ?></span></div>
          <div class="info-item"><label>Email</label><span><?= htmlspecialchars($hospital_info['official_email']??'-') ?></span></div>
        </div>
        <div class="facility-pills">
          <span class="f-pill <?= $hospital_info['is_24_7']?'on':'off' ?>"><i class="fas fa-<?= $hospital_info['is_24_7']?'check':'times' ?>"></i> 24/7 Service</span>
          <span class="f-pill <?= $hospital_info['has_blood_bank']?'on':'off' ?>"><i class="fas fa-<?= $hospital_info['has_blood_bank']?'check':'times' ?>"></i> Blood Bank</span>
          <span class="f-pill <?= $hospital_info['has_blood_storage']?'on':'off' ?>"><i class="fas fa-<?= $hospital_info['has_blood_storage']?'check':'times' ?>"></i> Storage Facility</span>
        </div>
      </div>

      <!-- Quick Blood Status -->
      <div class="card">
        <div class="card-title"><i class="fas fa-tint"></i> Quick Blood Status</div>
        <div class="blood-status-grid">
          <?php foreach($inventory as $bg => $inv):
            $s = getInventoryStatus($inv['available_units'], $inv['minimum_units']);
            $pct = $inv['minimum_units']>0 ? min(100, round(($inv['available_units']/$inv['minimum_units'])*50)) : 0;
            $fillClass = $s['class']==='badge-critical'?'fill-critical':($s['class']==='badge-low'?'fill-low':'fill-safe');
          ?>
          <div class="blood-bar-card">
            <div class="blood-bar-top">
              <span class="bg-pill"><?= $bg ?></span>
              <span class="<?= $s['class'] ?>"><?= $s['badge'] ?></span>
            </div>
            <div class="blood-bar-track"><div class="blood-bar-fill <?= $fillClass ?>" style="width:<?= $pct ?>%"></div></div>
            <div class="blood-bar-units"><span><?= $inv['available_units'] ?> avail</span><span>Min: <?= $inv['minimum_units'] ?></span></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Recent Blood Requests -->
      <div class="card">
        <div class="card-title"><i class="fas fa-list"></i> Recent Blood Requests</div>
        <?php if(empty($recent_requests)): ?>
        <p style="color:var(--muted);text-align:center;padding:20px">No recent blood requests.</p>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>#</th><th>Patient</th><th>Blood Type</th><th>Units</th><th>City</th><th>Urgency</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach($recent_requests as $i=>$r): ?>
              <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($r['patient_name']) ?></td>
                <td><span class="bg-pill" style="min-width:32px;height:32px;font-size:.78rem"><?= htmlspecialchars($r['blood_type']) ?></span></td>
                <td><?= $r['units'] ?></td>
                <td><?= htmlspecialchars($r['city']) ?></td>
                <td><?php $u=$r['urgency'];echo '<span class="'.($u==='critical'?'badge-critical-prio':($u==='urgent'?'badge-urgent-prio':'badge-normal-prio')).'">'.ucfirst($u).'</span>'; ?></td>
                <td><?= ucfirst($r['status']) ?></td>
                <td><?= date('d M',strtotime($r['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- NETWORK MAP TAB (DONORS & REQUESTS) -->
    <div class="tab-content" id="tab-map">
      <div class="section-header">
        <div>
          <div class="section-title"><i class="fas fa-map-location-dot" style="color:#2563eb"></i> Live Network Geo Map</div>
          <div class="section-sub">Real-time geographic visualization of all nearby blood requests (users) and available donors.</div>
        </div>
        <a href="../map.php?view=all" target="_blank" class="btn-red" style="text-decoration:none;">
          <i class="fas fa-up-right-from-square"></i> Open Fullscreen Map
        </a>
      </div>
      <div style="height:620px;border-radius:16px;overflow:hidden;border:1px solid var(--line);box-shadow:var(--shadow);">
        <iframe src="../map.php?view=all" style="width:100%;height:100%;border:none;"></iframe>
      </div>
    </div>

    <!-- BLOOD INVENTORY TAB -->
    <div class="tab-content" id="tab-inventory">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-tint"></i> Blood Inventory</div>
      </div>
      <?php
      $crit=0;$low=0;$safe=0;
      foreach($inventory as $inv){$s=getInventoryStatus($inv['available_units'],$inv['minimum_units']);if($s['class']==='badge-critical')$crit++;elseif($s['class']==='badge-low')$low++;else$safe++;}
      ?>
      <div class="summary-bar">
        <span class="summary-pill total"><i class="fas fa-tint"></i> <?= $total_blood_units ?> Total Units</span>
        <span class="summary-pill critical"><i class="fas fa-times-circle"></i> <?= $crit ?> Critical</span>
        <span class="summary-pill low"><i class="fas fa-exclamation-circle"></i> <?= $low ?> Low</span>
        <span class="summary-pill safe"><i class="fas fa-check-circle"></i> <?= $safe ?> Safe</span>
      </div>
      <div class="table-wrap">
        <table id="invTable">
          <thead><tr><th>Blood Group</th><th>Available Units</th><th>Minimum Stock</th><th>Status</th><th>Last Updated</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach($inventory as $bg=>$inv):
              $s=getInventoryStatus($inv['available_units'],$inv['minimum_units']);
            ?>
            <tr data-bg="<?= htmlspecialchars($bg) ?>">
              <td><span class="bg-pill"><?= $bg ?></span></td>
              <td><input type="number" class="inv-avail" value="<?= $inv['available_units'] ?>" min="0"></td>
              <td><input type="number" class="inv-min" value="<?= $inv['minimum_units'] ?>" min="0"></td>
              <td><span class="<?= $s['class'] ?>"><?= $s['badge'] ?></span></td>
              <td><?= $inv['last_updated']??'-' ?></td>
              <td><button class="btn-red btn-sm" onclick="saveInventory(this)"><i class="fas fa-save"></i> Save</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- COMPONENTS TAB -->
    <div class="tab-content" id="tab-components">
      <div class="section-title" style="margin-bottom:22px"><i class="fas fa-vials"></i> Blood Components</div>
      <?php $compInfo=['packed_rbc'=>['Packed RBC','syringe','Red Blood Cell Concentrate'],'platelets'=>['Platelets','microscope','Platelet Concentrate'],'ffp'=>['Fresh Frozen Plasma','flask','FFP Units'],'cryoprecipitate'=>['Cryoprecipitate','snowflake','Cryo Units']]; ?>
      <div class="comp-grid">
        <?php foreach($comp_types as $ct):
          $c=$components[$ct];
          [$cname,$cicon,$csub]=$compInfo[$ct];
        ?>
        <div class="comp-card" data-comp="<?= $ct ?>">
          <div class="comp-icon"><i class="fas fa-<?= $cicon ?>"></i></div>
          <h4><?= $cname ?></h4>
          <small><?= $csub ?></small>
          <div class="comp-units" id="cu-<?= $ct ?>"><?= $c['available_units'] ?> <span style="font-size:.9rem;color:var(--muted)">units</span></div>
          <div class="comp-updated">Last updated: <?= isset($c['last_updated'])?date('d M H:i',strtotime($c['last_updated'])):'-' ?></div>
          <input type="number" class="comp-input" id="ci-<?= $ct ?>" value="<?= $c['available_units'] ?>" min="0">
          <div style="display:flex;gap:8px;justify-content:center;margin-top:8px">
            <button class="btn-ghost btn-sm" onclick="editComp('<?= $ct ?>')"><i class="fas fa-edit"></i> Edit</button>
            <button class="btn-red btn-sm" id="csave-<?= $ct ?>" onclick="saveComp('<?= $ct ?>')" style="display:none"><i class="fas fa-save"></i> Save</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- EXPIRY TRACKER TAB -->
    <div class="tab-content" id="tab-expiry">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-calendar-check"></i> Expiry Tracker</div>
        <button class="btn-red" onclick="openModal('addBatchModal')"><i class="fas fa-plus"></i> Add Batch</button>
      </div>
      <?php if(!empty($expiring_batches)): ?>
      <div style="background:var(--red-soft);border:1.5px solid rgba(214,40,57,.3);border-radius:var(--radius-sm);padding:13px 16px;margin-bottom:18px;font-weight:700;color:var(--red-deep);display:flex;align-items:center;gap:9px">
        <i class="fas fa-exclamation-triangle"></i> <?= count($expiring_batches) ?> batch(es) expiring within 7 days!
      </div>
      <?php endif; ?>
      <?php if(empty($all_batches)): ?>
      <div class="card" style="text-align:center;padding:40px"><i class="fas fa-box-open" style="font-size:2rem;color:var(--muted);margin-bottom:12px;display:block"></i><p style="color:var(--muted)">No active batches. Add your first batch.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Batch UID</th><th>Blood Group</th><th>Component</th><th>Units</th><th>Collection</th><th>Expiry</th><th>Days Left</th><th>Storage</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach($all_batches as $b):
              $today=new DateTime();
              $exp=new DateTime($b['expiry_date']);
              $diff=(int)$today->diff($exp)->format('%r%a');
              $rowClass=$diff<0?'row-expired':($diff<3?'row-warn':($diff<7?'row-warn':'row-ok'));
              $daysText=$diff<0?abs($diff).' days ago':$diff.' days';
            ?>
            <tr class="<?= $rowClass ?>">
              <td><strong><?= htmlspecialchars($b['batch_uid']) ?></strong></td>
              <td><span class="bg-pill" style="min-width:32px;height:32px;font-size:.78rem"><?= $b['blood_group'] ?></span></td>
              <td><?= ucwords(str_replace('_',' ',$b['component'])) ?></td>
              <td><?= $b['units'] ?></td>
              <td><?= $b['collection_date']?date('d M Y',strtotime($b['collection_date'])):'-' ?></td>
              <td><?= date('d M Y',strtotime($b['expiry_date'])) ?></td>
              <td><strong><?= $daysText ?></strong></td>
              <td><?= htmlspecialchars($b['storage_location']??'-') ?></td>
              <td><button class="btn-green btn-sm" onclick="markBatchUsed(<?= $b['id'] ?>,this)"><i class="fas fa-check"></i> Used</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- EMERGENCY TAB -->
    <div class="tab-content" id="tab-emergency">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-ambulance"></i> Emergency Requirements</div>
        <button class="btn-red" onclick="openModal('addEmerModal')"><i class="fas fa-plus"></i> Create Emergency</button>
      </div>
      <?php if(!empty($emergencies)): ?>
      <div class="emer-grid">
        <?php foreach($emergencies as $e): $pc='priority-'.$e['priority']; ?>
        <div class="emer-card <?= $pc ?>">
          <div><span class="bg-pill" style="min-width:42px;height:42px"><?= $e['blood_group'] ?></span></div>
          <h4><?= $e['required_units'] ?> Units Needed</h4>
          <div class="emer-meta">
            <?php
            $pc2=$e['priority']==='critical'?'badge-critical-prio':($e['priority']==='urgent'?'badge-urgent-prio':'badge-normal-prio');
            echo '<span class="'.$pc2.'">'.ucfirst($e['priority']).'</span>';
            ?> &bull; <?= ucfirst($e['category']) ?><br>
            <?php if($e['required_by']): ?><i class="fas fa-clock"></i> By <?= date('d M H:i',strtotime($e['required_by'])) ?><?php endif; ?>
            <?php if($e['reason']): ?><br><?= htmlspecialchars($e['reason']) ?><?php endif; ?>
          </div>
          <div class="emer-actions">
            <button class="btn-green btn-sm" onclick="fulfillEmer(<?= $e['id'] ?>,this)"><i class="fas fa-check"></i> Fulfilled</button>
            <button class="btn-ghost btn-sm" onclick="cancelEmer(<?= $e['id'] ?>,this)"><i class="fas fa-times"></i> Cancel</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="card" style="text-align:center;padding:40px"><i class="fas fa-check-circle" style="font-size:2rem;color:var(--green);margin-bottom:12px;display:block"></i><p style="color:var(--muted)">No active emergencies. All clear!</p></div>
      <?php endif; ?>
      <?php if(!empty($all_emergencies)): ?>
      <div class="card"><div class="card-title"><i class="fas fa-history"></i> All Emergency History</div>
      <div class="table-wrap"><table>
        <thead><tr><th>Blood Group</th><th>Units</th><th>Priority</th><th>Category</th><th>Status</th><th>Required By</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach($all_emergencies as $e): ?>
          <tr>
            <td><span class="bg-pill" style="min-width:32px;height:32px;font-size:.78rem"><?= $e['blood_group'] ?></span></td>
            <td><?= $e['required_units'] ?></td>
            <td><?php $pc=$e['priority']==='critical'?'badge-critical-prio':($e['priority']==='urgent'?'badge-urgent-prio':'badge-normal-prio');echo '<span class="'.$pc.'">'.ucfirst($e['priority']).'</span>'; ?></td>
            <td><?= ucfirst($e['category']) ?></td>
            <td><?= ucfirst($e['status']) ?></td>
            <td><?= $e['required_by']?date('d M H:i',strtotime($e['required_by'])):'-' ?></td>
            <td><?= date('d M Y',strtotime($e['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div></div>
      <?php endif; ?>
    </div>

    <!-- USERS TAB -->
    <div class="tab-content" id="tab-users">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-users"></i> User Profiles</div>
        <button class="btn-ghost btn-sm" onclick="exportCSV('usersTable','users_export')"><i class="fas fa-download"></i> Export CSV</button>
      </div>
      <div class="search-bar">
        <div class="search-wrap"><i class="fas fa-search si"></i><input class="search-input" id="userSearch" oninput="filterTable('userSearch','usersTable')" placeholder="Search users..."></div>
        <select class="filter-sel" id="userRoleFilter" onchange="filterByCol('userRoleFilter','usersTable',6)">
          <option value="">All Roles</option><option>user</option><option>donor</option>
        </select>
        <select class="filter-sel" id="userBtFilter" onchange="filterByCol('userBtFilter','usersTable',3)">
          <option value="">All Blood Types</option>
          <?php foreach(['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $g): ?><option><?= $g ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="table-wrap"><table id="usersTable">
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Blood Type</th><th>Phone</th><th>City</th><th>Role</th><th>Registered</th></tr></thead>
        <tbody>
          <?php foreach($users as $i=>$u): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($u['full_name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="bg-pill" style="min-width:32px;height:32px;font-size:.75rem"><?= htmlspecialchars($u['blood_type']??'-') ?></span></td>
            <td><?= htmlspecialchars($u['phone']??'-') ?></td>
            <td><?= htmlspecialchars($u['city']??'-') ?></td>
            <td><?= $u['role']==='donor'?'<span class="role-badge-donor">Donor</span>':'<span class="role-badge-user">User</span>' ?></td>
            <td><?= date('d M Y',strtotime($u['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <!-- DONORS TAB -->
    <div class="tab-content" id="tab-donors">
      <div class="section-header">
        <div class="section-title"><i class="fas fa-heart-pulse"></i> Donor Profiles</div>
        <button class="btn-ghost btn-sm" onclick="exportCSV('donorsTable','donors_export')"><i class="fas fa-download"></i> Export CSV</button>
      </div>
      <div class="search-bar">
        <div class="search-wrap"><i class="fas fa-search si"></i><input class="search-input" id="donorSearch" oninput="filterTable('donorSearch','donorsTable')" placeholder="Search donors..."></div>
        <select class="filter-sel" onchange="filterByCol(this,'donorsTable',3)">
          <option value="">All Blood Types</option>
          <?php foreach(['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $g): ?><option><?= $g ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="table-wrap"><table id="donorsTable">
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Blood Type</th><th>City</th><th>Phone</th><th>Availability</th><th>Last Donation</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach($donors as $i=>$d): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($d['full_name']) ?></td>
            <td><?= htmlspecialchars($d['email']) ?></td>
            <td><span class="bg-pill" style="min-width:32px;height:32px;font-size:.75rem"><?= htmlspecialchars($d['blood_type']) ?></span></td>
            <td><?= htmlspecialchars($d['city']??'-') ?></td>
            <td><?= htmlspecialchars($d['phone']??'-') ?></td>
            <td><?php $a=$d['availability']??'unavailable';echo $a==='available'?'<span class="badge-safe">🟢 Available</span>':'<span style="background:var(--cream);color:var(--muted);padding:4px 10px;border-radius:20px;font:700 .75rem var(--font-main)">⚫ Unavailable</span>'; ?></td>
            <td><?= $d['last_donation_date']?date('d M Y',strtotime($d['last_donation_date'])):'-' ?></td>
            <td><?= $d['total_donations']??0 ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <!-- DATA TABLES TAB -->
    <div class="tab-content" id="tab-datatables">
      <div class="section-title" style="margin-bottom:20px"><i class="fas fa-table"></i> Data Tables</div>
      <div class="sub-tabs">
        <button class="sub-tab-btn active" onclick="switchSubTab('dt-requests',this)">Blood Requests</button>
        <button class="sub-tab-btn" onclick="switchSubTab('dt-hospitals',this)">All Hospitals</button>
        <button class="sub-tab-btn" onclick="switchSubTab('dt-invsummary',this)">Inventory Summary</button>
      </div>
      <div id="dt-requests">
        <div class="search-bar"><div class="search-wrap"><i class="fas fa-search si"></i><input class="search-input" oninput="filterTable(this,'reqTable')" placeholder="Search requests..."></div></div>
        <div class="table-wrap"><table id="reqTable">
          <thead><tr><th>#</th><th>Patient</th><th>Blood Type</th><th>Units</th><th>Hospital/City</th><th>Urgency</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach($recent_requests as $i=>$r): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($r['patient_name']) ?></td>
              <td><span class="bg-pill" style="min-width:30px;height:30px;font-size:.72rem"><?= htmlspecialchars($r['blood_type']) ?></span></td>
              <td><?= $r['units'] ?></td>
              <td><?= htmlspecialchars($r['hospital']??$r['city']??'-') ?></td>
              <td><?php $u=$r['urgency'];echo '<span class="'.($u==='critical'?'badge-critical-prio':($u==='urgent'?'badge-urgent-prio':'badge-normal-prio')).'">'.ucfirst($u).'</span>'; ?></td>
              <td><?= ucfirst($r['status']) ?></td>
              <td><?= date('d M Y',strtotime($r['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
      <div id="dt-hospitals" style="display:none">
        <div class="table-wrap"><table>
          <thead><tr><th>#</th><th>Hospital</th><th>UID</th><th>Type</th><th>City</th><th>Phone</th><th>Blood Bank</th><th>24/7</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach($hospitals_list as $i=>$h): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($h['hospital_name']) ?></td>
              <td><span class="hosp-badge"><?= htmlspecialchars($h['hospital_uid']) ?></span></td>
              <td><?= ucfirst(str_replace('_',' ',$h['hospital_type'])) ?></td>
              <td><?= htmlspecialchars($h['city']) ?></td>
              <td><?= htmlspecialchars($h['contact_phone']) ?></td>
              <td><?= $h['has_blood_bank']?'<span class="badge-safe">Yes</span>':'<span class="badge-critical">No</span>' ?></td>
              <td><?= $h['is_24_7']?'<span class="badge-safe">Yes</span>':'<span class="badge-critical">No</span>' ?></td>
              <td><?= ucfirst($h['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
      <div id="dt-invsummary" style="display:none">
        <p style="color:var(--muted);font-size:.88rem;margin-bottom:12px">Cross-hospital inventory overview. Color: 🔴 Critical / 🟡 Low / 🟢 Safe</p>
        <div class="table-wrap"><table>
          <thead><tr><th>Hospital</th><?php foreach($blood_groups as $bg): ?><th><?= $bg ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach($hospitals_list as $h):
              $stmt2=$conn->prepare("SELECT blood_group,available_units,minimum_units FROM hospital_blood_inventory WHERE hospital_id=?");
              $stmt2->bind_param("i",$h['id']);$stmt2->execute();$r2=$stmt2->get_result();$hinv=[];
              while($row2=$r2->fetch_assoc())$hinv[$row2['blood_group']]=$row2;
              $stmt2->close();
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($h['hospital_name']) ?></strong><br><small style="color:var(--muted)"><?= htmlspecialchars($h['hospital_uid']) ?></small></td>
              <?php foreach($blood_groups as $bg):
                $u=$hinv[$bg]['available_units']??0;
                $m=$hinv[$bg]['minimum_units']??10;
                $bg_s=getInventoryStatus($u,$m);
                $bgColor=$bg_s['class']==='badge-critical'?'#fef2f2':($bg_s['class']==='badge-low'?'var(--yellow-soft)':'var(--green-soft)');
              ?>
              <td style="background:<?= $bgColor ?>;font-weight:700;text-align:center"><?= $u ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
    </div>

    <!-- SETTINGS TAB -->
    <div class="tab-content" id="tab-settings">
      <div class="section-title" style="margin-bottom:22px"><i class="fas fa-gear"></i> Hospital Settings</div>
      <div class="card">
        <div class="card-title"><i class="fas fa-edit"></i> Hospital Information</div>
        <div class="info-grid">
          <div class="info-item"><label>Hospital Name</label><span><?= htmlspecialchars($hospital_info['hospital_name']??'-') ?></span></div>
          <div class="info-item"><label>Type</label><span><?= ucfirst(str_replace('_',' ',$hospital_info['hospital_type']??'-')) ?></span></div>
          <div class="info-item"><label>License</label><span><?= htmlspecialchars($hospital_info['license_number']??'-') ?></span></div>
          <div class="info-item"><label>Email</label><span><?= htmlspecialchars($hospital_info['official_email']??'-') ?></span></div>
          <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($hospital_info['contact_phone']??'-') ?></span></div>
          <div class="info-item"><label>City</label><span><?= htmlspecialchars($hospital_info['city']??'-') ?></span></div>
          <div class="info-item"><label>Hospital UID</label><span style="font-weight:800;color:var(--red)"><?= htmlspecialchars($_SESSION['hospital_uid']??'-') ?></span></div>
          <div class="info-item"><label>Status</label><span class="badge-safe"><?= ucfirst($hospital_info['status']??'-') ?></span></div>
        </div>
        <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--line)"><p style="color:var(--muted);font-size:.88rem"><i class="fas fa-info-circle" style="color:var(--red)"></i> To update hospital information, please contact the LifeFlow network administrator.</p></div>
      </div>
      <div class="card" style="border-color:rgba(214,40,57,.3)">
        <div class="card-title" style="color:var(--red-deep)"><i class="fas fa-triangle-exclamation"></i> Danger Zone</div>
        <p style="color:var(--muted);font-size:.88rem;margin-bottom:14px">To deactivate or delete your hospital account, contact the network administrator.</p>
        <a href="logout.php" class="btn-red"><i class="fas fa-sign-out-alt"></i> Logout Now</a>
      </div>
    </div>

  </main><!-- /main-content -->
</div><!-- /app-layout -->

<!-- Add Batch Modal -->
<div class="modal-overlay" id="addBatchModal">
  <div class="modal-box">
    <h3><i class="fas fa-plus-circle"></i> Add Blood Batch <button class="modal-close" onclick="closeModal('addBatchModal')"><i class="fas fa-times"></i></button></h3>
    <div class="m-grid">
      <div class="m-field"><label>Blood Group</label><select id="mb_bg"><option value="">Select...</option><?php foreach(['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $g): ?><option><?= $g ?></option><?php endforeach; ?></select></div>
      <div class="m-field"><label>Component</label><select id="mb_comp"><option value="whole_blood">Whole Blood</option><option value="packed_rbc">Packed RBC</option><option value="platelets">Platelets</option><option value="ffp">FFP</option><option value="cryoprecipitate">Cryoprecipitate</option></select></div>
      <div class="m-field"><label>Units</label><input type="number" id="mb_units" min="1" placeholder="e.g. 5"></div>
      <div class="m-field"><label>Batch UID</label><input type="text" id="mb_uid" placeholder="e.g. BLD-001"></div>
      <div class="m-field"><label>Collection Date</label><input type="date" id="mb_collect"></div>
      <div class="m-field"><label>Expiry Date</label><input type="date" id="mb_expiry"></div>
    </div>
    <div class="m-field"><label>Storage Location</label><input type="text" id="mb_storage" placeholder="e.g. Fridge A-1"></div>
    <div class="m-field"><label>Notes</label><textarea id="mb_notes" rows="2" placeholder="Optional notes"></textarea></div>
    <div style="display:flex;gap:10px;margin-top:4px">
      <button class="btn-ghost" onclick="closeModal('addBatchModal')">Cancel</button>
      <button class="btn-red" onclick="addBatch()"><i class="fas fa-plus"></i> Add Batch</button>
    </div>
  </div>
</div>

<!-- Add Emergency Modal -->
<div class="modal-overlay" id="addEmerModal">
  <div class="modal-box">
    <h3><i class="fas fa-ambulance"></i> Create Emergency <button class="modal-close" onclick="closeModal('addEmerModal')"><i class="fas fa-times"></i></button></h3>
    <div class="m-grid">
      <div class="m-field"><label>Blood Group <span style="color:var(--red)">*</span></label><select id="me_bg"><option value="">Select...</option><?php foreach(['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $g): ?><option><?= $g ?></option><?php endforeach; ?></select></div>
      <div class="m-field"><label>Units Required</label><input type="number" id="me_units" min="1" placeholder="e.g. 3"></div>
      <div class="m-field"><label>Required By</label><input type="datetime-local" id="me_by"></div>
      <div class="m-field"><label>Priority</label><select id="me_priority"><option value="urgent">Urgent</option><option value="critical">Critical</option><option value="normal">Normal</option></select></div>
      <div class="m-field"><label>Category</label><select id="me_cat"><option value="surgery">Surgery</option><option value="accident">Accident</option><option value="maternity">Maternity</option><option value="cancer">Cancer</option><option value="thalassemia">Thalassemia</option><option value="other">Other</option></select></div>
      <div class="m-field"><label>Reason</label><input type="text" id="me_reason" placeholder="Brief reason"></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:4px">
      <button class="btn-ghost" onclick="closeModal('addEmerModal')">Cancel</button>
      <button class="btn-red" onclick="addEmergency()"><i class="fas fa-ambulance"></i> Create Emergency</button>
    </div>
  </div>
</div>

<script>
const CSRF = '<?= $csrf ?>';

/* Toast */
function showToast(msg, type='success') {
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.innerHTML = '<i class="fas fa-'+(type==='success'?'check-circle':'exclamation-circle')+'"></i> ' + msg;
  document.getElementById('toastContainer').appendChild(t);
  setTimeout(()=>t.classList.add('show'),10);
  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),300);},3500);
}

/* Modal */
function openModal(id){document.getElementById(id).classList.add('open');}
function closeModal(id){document.getElementById(id).classList.remove('open');}
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('open');}));

/* Sidebar tab switch */
function switchTab(tabId) {
  document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.side-nav-item').forEach(i=>i.classList.remove('active'));
  const tab = document.getElementById('tab-'+tabId);
  if(tab){tab.classList.add('active');}
  const navItem = document.querySelector('.side-nav-item[data-tab="'+tabId+'"]');
  if(navItem){navItem.classList.add('active');}
  if(window.innerWidth<=768) document.getElementById('sidebar').classList.remove('open');
}

/* Sub-tabs */
function switchSubTab(id, btn) {
  ['dt-requests','dt-hospitals','dt-invsummary'].forEach(t=>{
    const el=document.getElementById(t);
    if(el)el.style.display=t===id?'block':'none';
  });
  document.querySelectorAll('.sub-tab-btn').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
}

/* Mobile sidebar */
document.getElementById('mobMenuBtn').addEventListener('click',()=>{
  document.getElementById('sidebar').classList.toggle('open');
});

/* Animated counters */
function animateCounter(el,target,dur=1600){
  let start=0;const t0=performance.now();
  function step(t){const e=t-t0,p=Math.min(e/dur,1),v=Math.floor((1-Math.pow(1-p,3))*target);
    el.textContent=v;if(p<1)requestAnimationFrame(step);else el.textContent=target;}
  requestAnimationFrame(step);
}
window.addEventListener('load',()=>{
  document.querySelectorAll('[data-count]').forEach(el=>animateCounter(el,parseInt(el.dataset.count)));
});

/* Table filter */
function filterTable(inputEl,tableId){
  const q=(typeof inputEl==='string'?document.getElementById(inputEl):inputEl).value.toLowerCase();
  document.querySelectorAll('#'+tableId+' tbody tr').forEach(r=>{
    r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';
  });
}
function filterByCol(selEl,tableId,colIdx){
  const v=(typeof selEl==='string'?document.getElementById(selEl):selEl).value.toLowerCase();
  document.querySelectorAll('#'+tableId+' tbody tr').forEach(r=>{
    const cell=r.cells[colIdx];
    r.style.display=(!v||cell&&cell.textContent.toLowerCase().includes(v))?'':'none';
  });
}

/* CSV Export */
function exportCSV(tableId,filename){
  const rows=document.querySelectorAll('#'+tableId+' tr');
  const csv=Array.from(rows).map(r=>Array.from(r.querySelectorAll('td,th')).map(c=>'"'+c.innerText.replace(/"/g,'""')+'"').join(',')).join('\n');
  const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download=filename+'.csv';a.click();
}

/* Inventory Save */
async function saveInventory(btn) {
  const row=btn.closest('tr');const bg=row.dataset.bg;
  const avail=row.querySelector('.inv-avail').value;
  const min=row.querySelector('.inv-min').value;
  btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>';
  const fd=new FormData();fd.append('action','update_inventory');fd.append('blood_group',bg);
  fd.append('available_units',avail);fd.append('minimum_units',min);fd.append('csrf_token',CSRF);
  try{const r=await fetch('ajax.php',{method:'POST',body:fd});const d=await r.json();
    if(d.success){showToast(bg+' inventory updated!');
      btn.innerHTML='<i class="fas fa-check"></i> Saved';
      setTimeout(()=>{btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> Save';},2000);
    }else{showToast(d.message||'Error','error');btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> Save';}}
  catch{showToast('Network error','error');btn.disabled=false;btn.innerHTML='<i class="fas fa-save"></i> Save';}
}

/* Component Edit/Save */
function editComp(ct){
  const inp=document.getElementById('ci-'+ct);
  const saveBtn=document.getElementById('csave-'+ct);
  inp.style.display='block';
  saveBtn.style.display='inline-flex';
  document.querySelectorAll('.comp-card[data-comp="'+ct+'"] .btn-ghost')[0].innerHTML='<i class="fas fa-times"></i> Cancel';
  document.querySelectorAll('.comp-card[data-comp="'+ct+'"] .btn-ghost')[0].onclick=()=>cancelEditComp(ct);
}
function cancelEditComp(ct){
  document.getElementById('ci-'+ct).style.display='none';
  document.getElementById('csave-'+ct).style.display='none';
  const eb=document.querySelectorAll('.comp-card[data-comp="'+ct+'"] .btn-ghost')[0];
  eb.innerHTML='<i class="fas fa-edit"></i> Edit';eb.onclick=()=>editComp(ct);
}
async function saveComp(ct){
  const units=document.getElementById('ci-'+ct).value;
  const fd=new FormData();fd.append('action','update_component');fd.append('component',ct);fd.append('available_units',units);fd.append('csrf_token',CSRF);
  try{const r=await fetch('ajax.php',{method:'POST',body:fd});const d=await r.json();
    if(d.success){document.getElementById('cu-'+ct).innerHTML=units+' <span style="font-size:.9rem;color:var(--muted)">units</span>';showToast('Component updated!');cancelEditComp(ct);}
    else showToast(d.message||'Error','error');}
  catch{showToast('Network error','error');}
}

/* Add Batch */
async function addBatch(){
  const fd=new FormData();fd.append('action','add_batch');
  fd.append('blood_group',document.getElementById('mb_bg').value);
  fd.append('component',document.getElementById('mb_comp').value);
  fd.append('units',document.getElementById('mb_units').value);
  fd.append('batch_uid',document.getElementById('mb_uid').value);
  fd.append('collection_date',document.getElementById('mb_collect').value);
  fd.append('expiry_date',document.getElementById('mb_expiry').value);
  fd.append('storage_location',document.getElementById('mb_storage').value);
  fd.append('notes',document.getElementById('mb_notes').value);
  fd.append('csrf_token',CSRF);
  try{const r=await fetch('ajax.php',{method:'POST',body:fd});const d=await r.json();
    if(d.success){showToast('Batch added! Refresh to see.');closeModal('addBatchModal');}
    else showToast(d.message||'Error','error');}
  catch{showToast('Network error','error');}
}

/* Mark batch used */
async function markBatchUsed(id,btn){
  const fd=new FormData();fd.append('action','delete_batch');fd.append('batch_id',id);fd.append('csrf_token',CSRF);
  btn.disabled=true;
  try{const r=await fetch('ajax.php',{method:'POST',body:fd});const d=await r.json();
    if(d.success){showToast('Batch marked as used!');btn.closest('tr').style.opacity='.4';}
    else showToast(d.message||'Error','error');}
  catch{showToast('Network error','error');}
}

/* Add Emergency */
async function addEmergency(){
  const fd=new FormData();fd.append('action','add_emergency');
  fd.append('blood_group',document.getElementById('me_bg').value);
  fd.append('required_units',document.getElementById('me_units').value);
  fd.append('required_by',document.getElementById('me_by').value);
  fd.append('priority',document.getElementById('me_priority').value);
  fd.append('category',document.getElementById('me_cat').value);
  fd.append('notes',document.getElementById('me_reason').value);
  fd.append('csrf_token',CSRF);
  try{const r=await fetch('ajax.php',{method:'POST',body:fd});const d=await r.json();
    if(d.success){showToast('Emergency created!');closeModal('addEmerModal');}
    else showToast(d.message||'Error','error');}
  catch{showToast('Network error','error');}
}

async function fulfillEmer(id,btn){
  const fd=new FormData();fd.append('action','close_emergency');fd.append('emergency_id',id);fd.append('csrf_token',CSRF);
  btn.disabled=true;
  try{const r=await fetch('ajax.php',{method:'POST',body:fd});const d=await r.json();
    if(d.success){showToast('Emergency fulfilled!');btn.closest('.emer-card').style.opacity='.4';}
    else showToast(d.message||'Error','error');}
  catch{showToast('Network error','error');}
}
async function cancelEmer(id,btn){
  const fd=new FormData();fd.append('action','cancel_emergency');fd.append('emergency_id',id);fd.append('csrf_token',CSRF);
  btn.disabled=true;
  try{const r=await fetch('ajax.php',{method:'POST',body:fd});const d=await r.json();
    if(d.success){showToast('Emergency cancelled.');btn.closest('.emer-card').style.opacity='.4';}
    else showToast(d.message||'Error','error');}
  catch{showToast('Network error','error');}
}

/* Scroll header shrink */
window.addEventListener('scroll',()=>document.getElementById('mainHeader').classList.toggle('scrolled',window.scrollY>10));

/* === BG CANVAS === */
const bgCanvas=document.getElementById('bgCanvas'),bgCtx=bgCanvas.getContext('2d');
let bgW,bgH;
function resizeBg(){bgW=bgCanvas.width=window.innerWidth;bgH=bgCanvas.height=window.innerHeight;}
resizeBg();window.addEventListener('resize',resizeBg);
const bgCells=Array.from({length:7},()=>({x:Math.random(),y:Math.random(),r:90+Math.random()*160,vx:(Math.random()-.5)*0.00012,vy:(Math.random()-.5)*0.00012,hue:Math.random()>.5?'rgba(214,40,57,':'rgba(15,122,92,'}));
let waveT=0;
function drawHBLine(){bgCtx.save();bgCtx.globalAlpha=.12;bgCtx.strokeStyle='#d62839';bgCtx.lineWidth=1.4;bgCtx.beginPath();
  for(let x=0;x<=bgW;x+=6){const phase=(x+waveT)*0.02;let y2=46+Math.sin(phase*0.6)*3;const spike=(x+waveT)%260;
    if(spike>40&&spike<70)y2-=(spike-40)*1.7;else if(spike>=70&&spike<100)y2+=(spike-70)*1.7-51;
    if(x===0)bgCtx.moveTo(x,y2);else bgCtx.lineTo(x,y2);}bgCtx.stroke();bgCtx.restore();}
function animateBg(){bgCtx.clearRect(0,0,bgW,bgH);bgCells.forEach(c=>{c.x+=c.vx;c.y+=c.vy;if(c.x<-0.2||c.x>1.2)c.vx*=-1;if(c.y<-0.2||c.y>1.2)c.vy*=-1;
  const cx=c.x*bgW,cy=c.y*bgH,grad=bgCtx.createRadialGradient(cx,cy,0,cx,cy,c.r);
  grad.addColorStop(0,c.hue+'.08)');grad.addColorStop(1,c.hue+'0)');bgCtx.fillStyle=grad;bgCtx.beginPath();bgCtx.arc(cx,cy,c.r,0,Math.PI*2);bgCtx.fill();});
  drawHBLine();waveT+=1.1;requestAnimationFrame(animateBg);}
animateBg();

/* === CURSOR TRAIL === */
const cursorCanvas=document.getElementById('cursorCanvas'),cursorCtx=cursorCanvas.getContext('2d'),drops=[];
function resizeCursor(){cursorCanvas.width=window.innerWidth;cursorCanvas.height=window.innerHeight;}
function animateCursor(){cursorCtx.clearRect(0,0,cursorCanvas.width,cursorCanvas.height);
  for(let i=drops.length-1;i>=0;i--){const p=drops[i];p.x+=p.vx;p.y+=p.vy;p.size*=.95;p.opacity-=.025;
    cursorCtx.fillStyle='rgba(214,40,57,'+Math.max(0,p.opacity)+')';
    cursorCtx.beginPath();cursorCtx.arc(p.x,p.y,Math.max(.1,p.size),0,Math.PI*2);cursorCtx.fill();
    if(p.opacity<=0||p.size<=.5)drops.splice(i,1);}requestAnimationFrame(animateCursor);}
window.addEventListener('resize',resizeCursor);
window.addEventListener('mousemove',e=>{if(Math.random()>.4)drops.push({x:e.clientX,y:e.clientY,size:Math.random()*6+3,vx:(Math.random()-.5)*2,vy:Math.random()*2+1,opacity:.8});});
resizeCursor();animateCursor();
</script>
</body>
</html>
