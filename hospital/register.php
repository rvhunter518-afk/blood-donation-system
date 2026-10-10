<?php
session_start();
if (isset($_SESSION['hospital_id']) || (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'hospital')) {
    header("Location: dashboard.php");
    exit;
}

require_once '../config/database.php';
$conn = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    try {
        $name = trim($_POST['hospital_name'] ?? '');
        $type = $_POST['hospital_type'] ?? '';
        $license = trim($_POST['license_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $pin = trim($_POST['pin_code'] ?? '');
        $phone = trim($_POST['contact_phone'] ?? '');
        $email = trim($_POST['official_email'] ?? '');
        $emergency_phone = trim($_POST['emergency_phone'] ?? '');
        $is_24_7 = isset($_POST['is_24_7']) && $_POST['is_24_7'] == '1' ? 1 : 0;
        $has_bank = isset($_POST['has_blood_bank']) && $_POST['has_blood_bank'] == '1' ? 1 : 0;
        $has_storage = isset($_POST['has_blood_storage']) && $_POST['has_blood_storage'] == '1' ? 1 : 0;
        $password = $_POST['password'] ?? '';

        if (empty($name) || empty($city) || empty($phone) || empty($email) || empty($password)) {
            throw new Exception("Please fill all required fields.");
        }

        $stmt = $conn->prepare("SELECT id FROM hospitals WHERE official_email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("Email already registered.");
        }
        $stmt->close();

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO hospitals (hospital_name, hospital_type, license_number, address, city, district, pin_code, contact_phone, official_email, emergency_phone, is_24_7, has_blood_bank, has_blood_storage, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
        $stmt->bind_param("ssssssssssiiis", $name, $type, $license, $address, $city, $district, $pin, $phone, $email, $emergency_phone, $is_24_7, $has_bank, $has_storage, $password_hash);
        
        if (!$stmt->execute()) {
            throw new Exception("Registration failed: " . $stmt->error);
        }
        $hospital_id = $stmt->insert_id;
        $stmt->close();

        $hospital_uid = 'HOS-' . str_pad($hospital_id, 2, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("UPDATE hospitals SET hospital_uid = ? WHERE id = ?");
        $stmt->bind_param("si", $hospital_uid, $hospital_id);
        $stmt->execute();
        $stmt->close();

        $blood_groups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
        foreach ($blood_groups as $bg) {
            $key_safe = str_replace('+', '_pos', str_replace('-', '_neg', $bg));
            $available = isset($_POST['inv_' . $key_safe . '_current']) ? (int)$_POST['inv_' . $key_safe . '_current'] : 0;
            $min = isset($_POST['inv_' . $key_safe . '_min']) ? (int)$_POST['inv_' . $key_safe . '_min'] : 0;
            $stmt = $conn->prepare("INSERT INTO hospital_blood_inventory (hospital_id, blood_group, available_units, minimum_units, last_updated) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("isii", $hospital_id, $bg, $available, $min);
            $stmt->execute();
            $stmt->close();
        }

        $components = ['packed_rbc', 'platelets', 'ffp', 'cryoprecipitate'];
        foreach ($components as $comp) {
            $available = isset($_POST['comp_' . $comp]) ? (int)$_POST['comp_' . $comp] : 0;
            $stmt = $conn->prepare("INSERT INTO hospital_blood_components (hospital_id, component, available_units, last_updated) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("isi", $hospital_id, $comp, $available);
            $stmt->execute();
            $stmt->close();
        }

        $batch_groups = $_POST['batch_blood_group'] ?? [];
        if (is_array($batch_groups)) {
            foreach ($batch_groups as $i => $bg) {
                $b_comp = $_POST['batch_component'][$i] ?? 'whole_blood';
                $b_units = (int)($_POST['batch_units'][$i] ?? 0);
                $b_collect = $_POST['batch_collection'][$i] ?? null;
                $b_expiry = $_POST['batch_expiry'][$i] ?? null;
                $b_storage = $_POST['batch_storage'][$i] ?? '';
                $b_uid = $_POST['batch_uid'][$i] ?? 'BATCH-' . uniqid();
                $b_notes = $_POST['batch_notes'][$i] ?? '';
                if (!empty($bg) && $b_units > 0 && $b_expiry) {
                    $stmt = $conn->prepare("INSERT INTO hospital_blood_batches (hospital_id, blood_group, component, units, collection_date, expiry_date, storage_location, batch_uid, notes, is_used, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
                    $stmt->bind_param("issiissss", $hospital_id, $bg, $b_comp, $b_units, $b_collect, $b_expiry, $b_storage, $b_uid, $b_notes);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        $needs_urgent = isset($_POST['needs_urgent']) && $_POST['needs_urgent'] == '1';
        if ($needs_urgent) {
            $e_bg = $_POST['emer_blood_group'] ?? '';
            $e_units = (int)($_POST['emer_units'] ?? 0);
            $e_by = !empty($_POST['emer_required_by']) ? $_POST['emer_required_by'] : null;
            $e_priority = $_POST['emer_priority'] ?? 'urgent';
            $e_cat = $_POST['emer_category'] ?? 'other';
            $e_reason = $_POST['emer_reason'] ?? '';
            if (!empty($e_bg) && $e_units > 0) {
                $stmt = $conn->prepare("INSERT INTO hospital_emergency_requests (hospital_id, blood_group, required_units, required_by, priority, category, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'open', NOW())");
                $stmt->bind_param("isissss", $hospital_id, $e_bg, $e_units, $e_by, $e_priority, $e_cat, $e_reason);
                $stmt->execute();
                $stmt->close();
            }
        }

        $_SESSION['hospital_id'] = $hospital_id;
        $_SESSION['hospital_name'] = $name;
        $_SESSION['hospital_uid'] = $hospital_uid;
        $_SESSION['user_role'] = 'hospital';

        $response['success'] = true;
        $response['hospital_uid'] = $hospital_uid;
        $response['redirect'] = 'dashboard.php';
        $response['message'] = 'Registration successful!';

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hospital Registration — LifeFlow</title>
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
  --line: #ecdfda;
  --gold: #946116;
  --gold-soft: #fff4dc;
  --shadow: 0 18px 40px rgba(143,18,32,.09), 0 6px 14px rgba(23,27,34,.04);
  --radius-lg: 26px;
  --radius-md: 18px;
  --radius-sm: 12px;
  --font-main: 'DM Sans', sans-serif;
  --font-heading: 'Space Grotesk', sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-main);background:var(--paper);color:var(--ink);min-height:100vh;overflow-x:hidden}

/* Canvases */
#bgCanvas{position:fixed;inset:0;width:100%;height:100%;z-index:-2;pointer-events:none}
#cursorCanvas{position:fixed;inset:0;width:100%;height:100%;z-index:30;pointer-events:none}
.grain{position:fixed;inset:0;z-index:-1;pointer-events:none;opacity:.035;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='90' height='90'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>")}

/* ECG Top Bar */
.ecg-topbar{display:flex;justify-content:space-between;align-items:center;padding:7px max(24px,calc((100% - 1100px)/2));background:#fffdfbe6;backdrop-filter:blur(16px);border-bottom:1px solid var(--line);font-size:.77rem;color:var(--muted);position:relative;z-index:6}
.ecg-live{display:flex;align-items:center;gap:8px;font-weight:700;color:var(--ink)}
.pulse-dot{width:8px;height:8px;border-radius:50%;background:var(--red);box-shadow:0 0 10px rgba(214,40,57,.7);animation:pulseDot 1.6s ease infinite;flex:none}
@keyframes pulseDot{50%{transform:scale(1.5);opacity:.6}}

/* Header */
header{display:flex;justify-content:space-between;align-items:center;padding:16px max(24px,calc((100% - 1100px)/2));border-bottom:1px solid var(--line);background:#fffdfbdd;backdrop-filter:blur(20px);position:sticky;top:0;z-index:6;transition:box-shadow .25s}
header.scrolled{box-shadow:0 8px 24px rgba(143,18,32,.07)}
.brand{display:flex;align-items:center;gap:10px;font:700 1.4rem var(--font-heading);color:var(--ink);text-decoration:none}
.brand i{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;box-shadow:0 6px 16px rgba(143,18,32,.28);font-size:.9rem}
.brand span{color:var(--red)}
.header-links{display:flex;gap:12px;align-items:center}
.header-links a{color:var(--muted);text-decoration:none;font-weight:700;font-size:.88rem;padding:8px 16px;border-radius:50px;border:1px solid var(--line);transition:.2s}
.header-links a:hover{color:var(--red);border-color:var(--red)}
.header-links .btn-reg{background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;border:none;box-shadow:0 6px 16px rgba(143,18,32,.22)}
.header-links .btn-reg:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(143,18,32,.3)}

/* Main */
main{padding:40px max(20px,calc((100% - 860px)/2)) 60px}
.page-title{text-align:center;margin-bottom:36px}
.page-title h1{font:700 2.4rem var(--font-heading);color:var(--ink);margin-bottom:8px}
.page-title p{color:var(--muted);font-size:1rem}
.page-title .hosp-badge{display:inline-flex;align-items:center;gap:7px;background:var(--red-soft);color:var(--red-deep);border:1px solid rgba(214,40,57,.2);border-radius:20px;padding:5px 14px;font-size:.82rem;font-weight:800;margin-bottom:14px}

/* Step Progress Bar */
.step-bar{display:flex;align-items:center;margin-bottom:36px;gap:0}
.step-item{display:flex;flex-direction:column;align-items:center;gap:6px;flex:1;position:relative}
.step-num{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font:800 .9rem var(--font-heading);background:var(--line);color:var(--muted);transition:.3s;border:2px solid var(--line);z-index:1;position:relative}
.step-label{font-size:.72rem;font-weight:700;color:var(--muted);text-align:center;white-space:nowrap;transition:.3s}
.step-item.active .step-num{background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;border-color:var(--red);box-shadow:0 4px 14px rgba(143,18,32,.3)}
.step-item.active .step-label{color:var(--red-deep)}
.step-item.done .step-num{background:var(--green-soft);color:var(--green);border-color:var(--green)}
.step-item.done .step-label{color:var(--green)}
.step-line{flex:1;height:2px;background:var(--line);margin-bottom:22px;transition:.3s;position:relative}
.step-line.done{background:linear-gradient(90deg,var(--green),var(--red))}

/* Card */
.wizard-card{background:#ffffffcc;border:1px solid var(--line);border-radius:var(--radius-lg);padding:36px 40px;box-shadow:var(--shadow)}
.step-content{display:none}
.step-content.active{display:block;animation:fadeIn .35s ease}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-8px)}40%,80%{transform:translateX(8px)}}
@keyframes pulseGlow{0%{box-shadow:0 8px 20px rgba(143,18,32,.3)}50%{box-shadow:0 0 32px rgba(214,40,57,.55)}100%{box-shadow:0 8px 20px rgba(143,18,32,.3)}}

/* Step headers */
.step-header{margin-bottom:28px}
.step-header h2{font:700 1.5rem var(--font-heading);color:var(--ink);display:flex;align-items:center;gap:10px}
.step-header h2 i{color:var(--red)}
.step-header p{color:var(--muted);font-size:.9rem;margin-top:6px}

/* Form grid */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.form-grid.one-col{grid-template-columns:1fr}
.form-grid.three-col{grid-template-columns:1fr 1fr 1fr}
.field{display:flex;flex-direction:column;gap:6px}
.field.full{grid-column:1/-1}
.field label{font:700 .88rem var(--font-main);color:var(--ink)}
.field label .req{color:var(--red)}
.field input,.field select,.field textarea{width:100%;border:1.5px solid var(--line);border-radius:var(--radius-sm);padding:12px 14px;font:inherit;background:#fffffff2;color:var(--ink);transition:border-color .2s,box-shadow .2s;font-size:.95rem}
.field input:focus,.field select:focus,.field textarea:focus{outline:0;border-color:var(--red);box-shadow:0 0 0 4px rgba(214,40,57,.1)}
.field input.error{border-color:var(--red);box-shadow:0 0 0 3px rgba(214,40,57,.12)}
.field textarea{resize:vertical;min-height:80px}

/* Toggle switches */
.toggles-section{margin-top:4px;display:flex;flex-direction:column;gap:10px}
.toggle-wrap{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;background:var(--cream);border:1px solid var(--line);border-radius:var(--radius-sm);cursor:pointer;transition:border-color .2s}
.toggle-wrap:hover{border-color:var(--red)}
.toggle-label{display:flex;align-items:center;gap:10px;font-weight:700;font-size:.9rem}
.toggle-label i{color:var(--red);width:18px;text-align:center}
.toggle-switch{width:48px;height:26px;background:var(--line);border-radius:13px;position:relative;transition:.3s;flex:none;cursor:pointer;border:none;outline:none}
.toggle-switch.on{background:linear-gradient(135deg,var(--red),var(--red-deep))}
.toggle-switch::after{content:'';position:absolute;width:20px;height:20px;background:#fff;border-radius:50%;top:3px;left:3px;transition:.3s;box-shadow:0 2px 6px rgba(0,0,0,.15)}
.toggle-switch.on::after{left:25px}

/* Blood Inventory Table */
.inv-table-wrap{overflow-x:auto;border-radius:var(--radius-md);border:1px solid var(--line)}
.inv-table{width:100%;border-collapse:collapse}
.inv-table thead th{background:var(--red-soft);color:var(--red-deep);padding:13px 16px;font:800 .82rem var(--font-main);text-align:left}
.inv-table tbody td{padding:12px 16px;border-bottom:1px solid var(--line);font-size:.9rem}
.inv-table tbody tr:last-child td{border-bottom:none}
.inv-table tbody tr:hover{background:var(--cream)}
.bg-pill{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;border-radius:10px;font:800 .88rem var(--font-heading)}
.inv-table input[type=number]{width:90px;border:1.5px solid var(--line);border-radius:8px;padding:8px 10px;font:inherit;text-align:center;background:#fff;color:var(--ink)}
.inv-table input[type=number]:focus{outline:0;border-color:var(--red);box-shadow:0 0 0 3px rgba(214,40,57,.1)}

/* Component Cards */
.comp-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.comp-card{background:var(--cream);border:1.5px solid var(--line);border-radius:var(--radius-md);padding:22px;text-align:center;transition:border-color .2s,box-shadow .2s;border-top:3px solid var(--red)}
.comp-card:hover{border-color:var(--red);box-shadow:0 8px 24px rgba(143,18,32,.08)}
.comp-icon{width:50px;height:50px;background:linear-gradient(135deg,var(--red),var(--red-deep));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;color:#fff;margin:0 auto 12px;box-shadow:0 6px 16px rgba(143,18,32,.25)}
.comp-card h4{font:700 1rem var(--font-heading);color:var(--ink);margin-bottom:4px}
.comp-card small{color:var(--muted);font-size:.78rem;display:block;margin-bottom:14px}
.comp-card input[type=number]{width:100%;border:1.5px solid var(--line);border-radius:10px;padding:12px;font:700 1.5rem var(--font-heading);text-align:center;background:#fff;color:var(--ink)}
.comp-card input[type=number]:focus{outline:0;border-color:var(--red);box-shadow:0 0 0 3px rgba(214,40,57,.1)}

/* Batch rows */
.batch-row{background:var(--cream);border:1px solid var(--line);border-radius:var(--radius-md);padding:20px;margin-bottom:14px;position:relative}
.batch-row-grid{display:grid;grid-template-columns:1fr 1fr 80px;gap:12px}
.batch-row-grid2{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:12px}
.remove-batch{position:absolute;top:12px;right:14px;background:var(--red-soft);color:var(--red);border:none;border-radius:8px;width:28px;height:28px;font-size:.85rem;cursor:pointer;display:flex;align-items:center;justify-content:center}
.remove-batch:hover{background:var(--red);color:#fff}
.add-batch-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--cream);border:1.5px dashed var(--red);border-radius:50px;color:var(--red);font:700 .9rem var(--font-main);cursor:pointer;transition:.2s;margin-top:8px}
.add-batch-btn:hover{background:var(--red-soft)}

/* Emergency */
.emer-radios{display:flex;gap:14px;margin-bottom:20px}
.emer-radio{display:flex;align-items:center;gap:8px;padding:12px 20px;border:1.5px solid var(--line);border-radius:50px;cursor:pointer;font-weight:700;font-size:.9rem;transition:.2s}
.emer-radio input{display:none}
.emer-radio.sel-yes{border-color:var(--red);background:var(--red-soft);color:var(--red-deep)}
.emer-radio.sel-no{border-color:var(--green);background:var(--green-soft);color:var(--green)}

/* Navigation buttons */
.step-nav{display:flex;justify-content:space-between;align-items:center;margin-top:28px;padding-top:24px;border-top:1px solid var(--line)}
.btn-prev{background:var(--paper);border:1.5px solid var(--line);color:var(--ink);border-radius:50px;padding:12px 26px;font:700 .95rem var(--font-main);cursor:pointer;display:flex;align-items:center;gap:8px;transition:.2s}
.btn-prev:hover{border-color:var(--red);color:var(--red)}
.btn-next,.btn-submit{background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;border:none;border-radius:50px;padding:13px 30px;font:700 .95rem var(--font-main);cursor:pointer;display:flex;align-items:center;gap:8px;box-shadow:0 6px 18px rgba(143,18,32,.25);transition:.25s}
.btn-next:hover,.btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 26px rgba(143,18,32,.32)}
.btn-submit{padding:14px 36px;font-size:1rem}

/* Skip note */
.skip-note{background:var(--gold-soft);border:1px solid rgba(148,97,22,.2);border-radius:var(--radius-sm);padding:12px 16px;color:var(--gold);font-size:.85rem;font-weight:600;margin-top:16px;display:flex;align-items:center;gap:9px}

/* Success Modal */
#successModal{display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.55);backdrop-filter:blur(8px);align-items:center;justify-content:center;padding:20px}
.success-box{background:var(--paper);border-radius:var(--radius-lg);padding:48px 40px;max-width:480px;width:100%;text-align:center;box-shadow:0 30px 80px rgba(143,18,32,.22)}
.success-icon{width:80px;height:80px;background:linear-gradient(135deg,var(--red),var(--red-deep));border-radius:22px;display:flex;align-items:center;justify-content:center;font-size:2.2rem;color:#fff;margin:0 auto 24px;animation:pulseGlow 2s ease infinite}
.success-box h2{font:700 2rem var(--font-heading);color:var(--ink);margin-bottom:8px}
.success-box > p{color:var(--muted);margin-bottom:22px}
.uid-card{background:var(--red-soft);border:2px solid var(--red);border-radius:16px;padding:20px;margin-bottom:22px}
.uid-label{color:var(--muted);font-size:.82rem;font-weight:600;margin-bottom:6px}
.uid-value{font:800 2.6rem var(--font-heading);color:var(--red)}
.btn-copy{background:linear-gradient(135deg,var(--red),var(--red-deep));color:#fff;border:none;border-radius:50px;padding:12px 28px;font:700 .95rem var(--font-main);cursor:pointer;box-shadow:0 6px 16px rgba(143,18,32,.25);transition:.25s;display:inline-flex;align-items:center;gap:8px;margin-bottom:10px;width:100%;justify-content:center}
.btn-copy:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(143,18,32,.32)}
.countdown-text{color:var(--muted);font-size:.82rem;margin:10px 0 16px}
.btn-go-dash{display:flex;align-items:center;justify-content:center;gap:10px;padding:14px;background:var(--ink);color:#fff;text-decoration:none;border-radius:50px;font:700 .95rem var(--font-main);transition:.25s}
.btn-go-dash:hover{background:var(--red-deep)}

/* Error banner */
.error-banner{background:var(--red-soft);border:1.5px solid rgba(214,40,57,.3);border-radius:var(--radius-sm);padding:12px 16px;color:var(--red-deep);font-weight:700;font-size:.88rem;margin-bottom:20px;display:none;align-items:center;gap:9px}
.error-banner.show{display:flex}

/* Responsive */
@media(max-width:660px){
  .form-grid{grid-template-columns:1fr}
  .form-grid.three-col{grid-template-columns:1fr}
  .comp-grid{grid-template-columns:1fr}
  .wizard-card{padding:24px 20px}
  .batch-row-grid,.batch-row-grid2{grid-template-columns:1fr}
}
</style>
</head>
<body>
<canvas id="bgCanvas" aria-hidden="true"></canvas>
<div class="grain" aria-hidden="true"></div>
<canvas id="cursorCanvas" aria-hidden="true"></canvas>

<!-- ECG Top Bar -->
<div class="ecg-topbar">
  <div class="ecg-live"><span class="pulse-dot"></span>LifeFlow Hospital Network</div>
  <div>🏥 Hospital Registration Portal</div>
</div>

<!-- Header -->
<header id="mainHeader">
  <a href="../login.html" class="brand"><i class="fas fa-droplet"></i><span>LifeFlow</span></a>
  <div class="header-links">
    <a href="directory.php"><i class="fas fa-hospital"></i> Directory</a>
    <a href="../login.html"><i class="fas fa-arrow-left"></i> Back to Login</a>
  </div>
</header>

<!-- Main Content -->
<main>
  <div class="page-title">
    <div class="hosp-badge"><i class="fas fa-hospital"></i> Hospital Portal Registration</div>
    <h1>Register Your Hospital</h1>
    <p>Complete all 5 steps to join the LifeFlow Blood Network. You'll receive a unique Hospital ID.</p>
  </div>

  <!-- Step Bar -->
  <div class="step-bar" id="stepBar">
    <div class="step-item active" data-s="1"><div class="step-num">1</div><div class="step-label">Basic Info</div></div>
    <div class="step-line" id="line1"></div>
    <div class="step-item" data-s="2"><div class="step-num">2</div><div class="step-label">Inventory</div></div>
    <div class="step-line" id="line2"></div>
    <div class="step-item" data-s="3"><div class="step-num">3</div><div class="step-label">Components</div></div>
    <div class="step-line" id="line3"></div>
    <div class="step-item" data-s="4"><div class="step-num">4</div><div class="step-label">Batches</div></div>
    <div class="step-line" id="line4"></div>
    <div class="step-item" data-s="5"><div class="step-num">5</div><div class="step-label">Emergency</div></div>
  </div>

  <!-- Wizard Card -->
  <div class="wizard-card">
    <div class="error-banner" id="errorBanner"><i class="fas fa-exclamation-circle"></i> <span id="errorText"></span></div>
    <form id="regForm">

      <!-- STEP 1: Basic Info -->
      <div class="step-content active" id="sc-1">
        <div class="step-header">
          <h2><i class="fas fa-hospital"></i> Hospital Basic Details</h2>
          <p>Enter the primary information about your hospital or blood bank.</p>
        </div>
        <div class="form-grid">
          <div class="field full">
            <label>Hospital Name <span class="req">*</span></label>
            <input type="text" name="hospital_name" placeholder="e.g. City General Hospital" required>
          </div>
          <div class="field">
            <label>Hospital Type <span class="req">*</span></label>
            <select name="hospital_type" required>
              <option value="">Select type...</option>
              <option value="government">Government</option>
              <option value="private">Private</option>
              <option value="trust">Trust</option>
              <option value="medical_college">Medical College</option>
            </select>
          </div>
          <div class="field">
            <label>License / Registration Number</label>
            <input type="text" name="license_number" placeholder="e.g. MH-2024-HOSp-001">
          </div>
          <div class="field full">
            <label>Full Address <span class="req">*</span></label>
            <textarea name="address" placeholder="Street address, landmark, area..." required></textarea>
          </div>
          <div class="field">
            <label>City <span class="req">*</span></label>
            <input type="text" name="city" placeholder="e.g. Mumbai" required>
          </div>
          <div class="field">
            <label>District</label>
            <input type="text" name="district" placeholder="e.g. Thane">
          </div>
          <div class="field">
            <label>PIN Code</label>
            <input type="text" name="pin_code" placeholder="e.g. 400001" maxlength="10">
          </div>
          <div class="field">
            <label>Contact Phone <span class="req">*</span></label>
            <input type="tel" name="contact_phone" placeholder="+91 99999 00000" required>
          </div>
          <div class="field">
            <label>Official Email <span class="req">*</span></label>
            <input type="email" name="official_email" placeholder="admin@hospital.com" required>
          </div>
          <div class="field">
            <label>Emergency Contact Number</label>
            <input type="tel" name="emergency_phone" placeholder="24/7 Emergency line">
          </div>
          <div class="field full">
            <label style="margin-bottom:12px">Facilities Available</label>
            <div class="toggles-section">
              <div class="toggle-wrap">
                <div class="toggle-label"><i class="fas fa-clock"></i> 24/7 Emergency Service</div>
                <button type="button" class="toggle-switch" id="tog24" data-target="is_24_7"></button>
              </div>
              <div class="toggle-wrap">
                <div class="toggle-label"><i class="fas fa-droplet"></i> Blood Bank Available</div>
                <button type="button" class="toggle-switch" id="togBank" data-target="has_blood_bank"></button>
              </div>
              <div class="toggle-wrap">
                <div class="toggle-label"><i class="fas fa-snowflake"></i> Blood Storage Facility</div>
                <button type="button" class="toggle-switch" id="togStorage" data-target="has_blood_storage"></button>
              </div>
            </div>
            <input type="hidden" name="is_24_7" id="is_24_7" value="0">
            <input type="hidden" name="has_blood_bank" id="has_blood_bank" value="0">
            <input type="hidden" name="has_blood_storage" id="has_blood_storage" value="0">
          </div>
          <div class="field">
            <label>Password <span class="req">*</span></label>
            <input type="password" name="password" id="password" placeholder="Min 6 characters" required minlength="6">
          </div>
          <div class="field">
            <label>Confirm Password <span class="req">*</span></label>
            <input type="password" id="confirm_password" placeholder="Re-enter password" required>
          </div>
        </div>
      </div>

      <!-- STEP 2: Blood Inventory -->
      <div class="step-content" id="sc-2">
        <div class="step-header">
          <h2><i class="fas fa-tint"></i> Blood Inventory</h2>
          <p>Enter current stock levels and minimum emergency thresholds for each blood group.</p>
        </div>
        <div class="inv-table-wrap">
          <table class="inv-table">
            <thead>
              <tr>
                <th>Blood Group</th>
                <th>Current Units Available</th>
                <th>Minimum Emergency Stock</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $groups = [
                ['A+','A_pos',10],['A-','A_neg',5],['B+','B_pos',10],['B-','B_neg',5],
                ['O+','O_pos',15],['O-','O_neg',15],['AB+','AB_pos',5],['AB-','AB_neg',3]
              ];
              foreach($groups as [$label,$key,$defMin]): ?>
              <tr>
                <td><span class="bg-pill"><?= $label ?></span></td>
                <td><input type="number" name="inv_<?= $key ?>_current" min="0" value="0" placeholder="0"></td>
                <td><input type="number" name="inv_<?= $key ?>_min" min="0" value="<?= $defMin ?>" placeholder="<?= $defMin ?>"></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- STEP 3: Blood Components -->
      <div class="step-content" id="sc-3">
        <div class="step-header">
          <h2><i class="fas fa-vials"></i> Blood Components</h2>
          <p>Enter available units for each blood component type in your facility.</p>
        </div>
        <div class="comp-grid">
          <div class="comp-card">
            <div class="comp-icon"><i class="fas fa-syringe"></i></div>
            <h4>Packed RBC</h4>
            <small>Red Blood Cell Concentrate</small>
            <input type="number" name="comp_packed_rbc" min="0" value="0" placeholder="0">
          </div>
          <div class="comp-card">
            <div class="comp-icon"><i class="fas fa-microscope"></i></div>
            <h4>Platelets</h4>
            <small>Platelet Concentrate</small>
            <input type="number" name="comp_platelets" min="0" value="0" placeholder="0">
          </div>
          <div class="comp-card">
            <div class="comp-icon"><i class="fas fa-flask"></i></div>
            <h4>Fresh Frozen Plasma</h4>
            <small>FFP Units</small>
            <input type="number" name="comp_ffp" min="0" value="0" placeholder="0">
          </div>
          <div class="comp-card">
            <div class="comp-icon"><i class="fas fa-snowflake"></i></div>
            <h4>Cryoprecipitate</h4>
            <small>Cryo Units</small>
            <input type="number" name="comp_cryoprecipitate" min="0" value="0" placeholder="0">
          </div>
        </div>
      </div>

      <!-- STEP 4: Expiry Batches -->
      <div class="step-content" id="sc-4">
        <div class="step-header">
          <h2><i class="fas fa-calendar-check"></i> Blood Batch Tracking <span style="font-size:.75rem;color:var(--muted);font-weight:500">(Optional)</span></h2>
          <p>Add blood batches with collection and expiry dates for tracking. You can skip this step and add later from the dashboard.</p>
        </div>
        <div id="batchRows"></div>
        <button type="button" class="add-batch-btn" id="addBatchBtn"><i class="fas fa-plus"></i> Add Blood Batch</button>
        <div class="skip-note"><i class="fas fa-lightbulb"></i> You can skip this step — batches can be added anytime from the dashboard.</div>
      </div>

      <!-- STEP 5: Emergency -->
      <div class="step-content" id="sc-5">
        <div class="step-header">
          <h2><i class="fas fa-ambulance"></i> Emergency Requirements</h2>
          <p>Do you urgently need blood at this time? If yes, we'll alert the network immediately.</p>
        </div>
        <div class="emer-radios">
          <label class="emer-radio" id="radioYes">
            <input type="radio" name="needs_urgent" value="1"> <i class="fas fa-exclamation-triangle" style="color:var(--red)"></i> Yes, We Need Blood Urgently
          </label>
          <label class="emer-radio sel-no" id="radioNo">
            <input type="radio" name="needs_urgent" value="0" checked> <i class="fas fa-check-circle" style="color:var(--green)"></i> No, We're Fine Now
          </label>
        </div>
        <div id="emergencyFields" style="display:none">
          <div class="form-grid">
            <div class="field">
              <label>Blood Group Needed <span class="req">*</span></label>
              <select name="emer_blood_group">
                <option value="">Select...</option>
                <?php foreach(['A+','A-','B+','B-','O+','O-','AB+','AB-'] as $g): ?>
                <option><?= $g ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label>Units Required</label>
              <input type="number" name="emer_units" min="1" placeholder="e.g. 3">
            </div>
            <div class="field">
              <label>Required By (Date & Time)</label>
              <input type="datetime-local" name="emer_required_by">
            </div>
            <div class="field">
              <label>Priority</label>
              <select name="emer_priority">
                <option value="urgent">Urgent</option>
                <option value="critical">Critical</option>
                <option value="normal">Normal</option>
              </select>
            </div>
            <div class="field">
              <label>Category</label>
              <select name="emer_category">
                <option value="surgery">Surgery</option>
                <option value="accident">Accident</option>
                <option value="maternity">Maternity</option>
                <option value="cancer">Cancer</option>
                <option value="thalassemia">Thalassemia</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="field">
              <label>Reason / Notes</label>
              <input type="text" name="emer_reason" placeholder="Brief reason for emergency">
            </div>
          </div>
        </div>
        <div class="step-nav">
          <button type="button" class="btn-prev" onclick="goStep(4)"><i class="fas fa-arrow-left"></i> Previous</button>
          <button type="submit" class="btn-submit" id="submitBtn"><i class="fas fa-hospital"></i> Complete Registration</button>
        </div>
      </div>

      <!-- Navigation (steps 1-4) -->
      <div class="step-nav" id="mainNav">
        <button type="button" class="btn-prev" id="prevBtn" onclick="prevStep()" style="display:none"><i class="fas fa-arrow-left"></i> Previous</button>
        <button type="button" class="btn-next" id="nextBtn" onclick="nextStep()">Next <i class="fas fa-arrow-right"></i></button>
      </div>
    </form>
  </div>
</main>

<!-- Success Modal -->
<div id="successModal">
  <div class="success-box">
    <div class="success-icon"><i class="fas fa-hospital"></i></div>
    <h2>Registration Successful!</h2>
    <p>Your hospital is now live on the LifeFlow Network.</p>
    <div class="uid-card">
      <div class="uid-label">Your Hospital ID</div>
      <div class="uid-value" id="generatedUid">HOS-01</div>
    </div>
    <button class="btn-copy" id="copyBtn"><i class="fas fa-copy"></i> Copy Hospital ID</button>
    <p style="color:var(--muted);font-size:.82rem">Use this ID to login at the Hospital Portal</p>
    <div class="countdown-text">Redirecting to dashboard in <strong id="countNum">5</strong>s...</div>
    <a href="dashboard.php" id="goDashBtn" class="btn-go-dash"><i class="fas fa-tachometer-alt"></i> Go to Dashboard Now</a>
  </div>
</div>

<script>
/* === WIZARD === */
let currentStep = 1;

function updateStepBar(step) {
  document.querySelectorAll('.step-item').forEach((el, i) => {
    el.classList.remove('active','done');
    if (i + 1 === step) el.classList.add('active');
    else if (i + 1 < step) el.classList.add('done');
  });
  document.querySelectorAll('.step-line').forEach((line, i) => {
    line.classList.toggle('done', i + 1 < step);
  });
}

function showStep(step) {
  document.querySelectorAll('.step-content').forEach(sc => sc.classList.remove('active'));
  document.getElementById('sc-' + step).classList.add('active');
  const nav = document.getElementById('mainNav');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  if (step === 5) { nav.style.display = 'none'; }
  else { nav.style.display = 'flex'; prevBtn.style.display = step > 1 ? 'inline-flex' : 'none'; }
  updateStepBar(step);
  currentStep = step;
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function validateStep(step) {
  const sc = document.getElementById('sc-' + step);
  const fields = sc.querySelectorAll('[required]');
  let ok = true;
  fields.forEach(f => {
    if (!f.value.trim()) { f.classList.add('error'); ok = false; }
    else f.classList.remove('error');
  });
  if (step === 1) {
    const pw = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;
    if (pw && cpw && pw !== cpw) {
      document.getElementById('confirm_password').classList.add('error');
      showError('Passwords do not match.');
      return false;
    }
  }
  if (!ok) {
    sc.style.animation = 'shake .4s ease';
    setTimeout(() => sc.style.animation = '', 400);
    showError('Please fill all required fields.');
  }
  return ok;
}

function showError(msg) {
  const b = document.getElementById('errorBanner');
  document.getElementById('errorText').textContent = msg;
  b.classList.add('show');
  setTimeout(() => b.classList.remove('show'), 4000);
}

function nextStep() {
  if (!validateStep(currentStep)) return;
  if (currentStep < 5) showStep(currentStep + 1);
}
function prevStep() { if (currentStep > 1) showStep(currentStep - 1); }
function goStep(s) { showStep(s); }

/* Toggle switches */
document.querySelectorAll('.toggle-switch').forEach(btn => {
  btn.addEventListener('click', function() {
    this.classList.toggle('on');
    const hidden = document.getElementById(this.dataset.target);
    if (hidden) hidden.value = this.classList.contains('on') ? '1' : '0';
  });
});

/* Emergency toggle */
document.querySelectorAll('input[name="needs_urgent"]').forEach(r => {
  r.addEventListener('change', function() {
    const isYes = this.value === '1';
    document.getElementById('emergencyFields').style.display = isYes ? 'block' : 'none';
    document.getElementById('radioYes').className = 'emer-radio' + (isYes ? ' sel-yes' : '');
    document.getElementById('radioNo').className = 'emer-radio' + (!isYes ? ' sel-no' : '');
  });
});

/* Add batch row */
let batchCount = 0;
document.getElementById('addBatchBtn').addEventListener('click', addBatchRow);
function addBatchRow() {
  const i = batchCount++;
  const div = document.createElement('div');
  div.className = 'batch-row';
  div.id = 'batch-' + i;
  div.innerHTML = `
    <button type="button" class="remove-batch" onclick="document.getElementById('batch-${i}').remove()"><i class="fas fa-times"></i></button>
    <div class="batch-row-grid">
      <div class="field">
        <label>Blood Group</label>
        <select name="batch_blood_group[]">
          <option value="">Select...</option>
          ${['A+','A-','B+','B-','O+','O-','AB+','AB-'].map(g=>`<option>${g}</option>`).join('')}
        </select>
      </div>
      <div class="field">
        <label>Component</label>
        <select name="batch_component[]">
          <option value="whole_blood">Whole Blood</option>
          <option value="packed_rbc">Packed RBC</option>
          <option value="platelets">Platelets</option>
          <option value="ffp">FFP</option>
          <option value="cryoprecipitate">Cryoprecipitate</option>
        </select>
      </div>
      <div class="field">
        <label>Units</label>
        <input type="number" name="batch_units[]" min="1" placeholder="1">
      </div>
    </div>
    <div class="batch-row-grid2">
      <div class="field">
        <label>Collection Date</label>
        <input type="date" name="batch_collection[]">
      </div>
      <div class="field">
        <label>Expiry Date</label>
        <input type="date" name="batch_expiry[]">
      </div>
      <div class="field">
        <label>Storage Location</label>
        <input type="text" name="batch_storage[]" placeholder="e.g. Fridge A-1">
      </div>
      <div class="field">
        <label>Batch / Unit ID</label>
        <input type="text" name="batch_uid[]" placeholder="e.g. BLD-2024-001">
      </div>
      <div class="field full">
        <label>Notes</label>
        <input type="text" name="batch_notes[]" placeholder="Optional notes">
      </div>
    </div>
  `;
  document.getElementById('batchRows').appendChild(div);
}

/* Form submit */
document.getElementById('regForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
  const fd = new FormData(this);
  fd.append('action', 'register');
  try {
    const resp = await fetch('', { method: 'POST', body: fd });
    const data = await resp.json();
    if (data.success) {
      document.getElementById('generatedUid').textContent = data.hospital_uid;
      document.getElementById('goDashBtn').href = data.redirect || 'dashboard.php';
      const modal = document.getElementById('successModal');
      modal.style.display = 'flex';
      let count = 5;
      const iv = setInterval(() => {
        count--;
        document.getElementById('countNum').textContent = count;
        if (count <= 0) { clearInterval(iv); window.location.href = data.redirect || 'dashboard.php'; }
      }, 1000);
    } else {
      showError(data.message || 'Registration failed.');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-hospital"></i> Complete Registration';
    }
  } catch(err) {
    showError('Network error. Please try again.');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-hospital"></i> Complete Registration';
  }
});

/* Copy UID */
document.getElementById('copyBtn').addEventListener('click', function() {
  const uid = document.getElementById('generatedUid').textContent;
  navigator.clipboard.writeText(uid).then(() => {
    this.innerHTML = '<i class="fas fa-check"></i> Copied!';
    this.style.background = 'linear-gradient(135deg,var(--green),#0a5e44)';
    setTimeout(() => {
      this.innerHTML = '<i class="fas fa-copy"></i> Copy Hospital ID';
      this.style.background = '';
    }, 2500);
  });
});

/* Header scroll */
window.addEventListener('scroll', () =>
  document.getElementById('mainHeader').classList.toggle('scrolled', window.scrollY > 10)
);

/* === BG CANVAS (ambient blobs + heartbeat line) === */
const bgCanvas = document.getElementById('bgCanvas');
const bgCtx = bgCanvas.getContext('2d');
let bgW, bgH;
function resizeBg() { bgW = bgCanvas.width = window.innerWidth; bgH = bgCanvas.height = window.innerHeight; }
resizeBg(); window.addEventListener('resize', resizeBg);
const bgCells = Array.from({length: 7}, () => ({
  x: Math.random(), y: Math.random(),
  r: 90 + Math.random() * 160,
  vx: (Math.random() - .5) * 0.00012,
  vy: (Math.random() - .5) * 0.00012,
  hue: Math.random() > .5 ? 'rgba(214,40,57,' : 'rgba(15,122,92,'
}));
let waveT = 0;
function drawHBLine() {
  bgCtx.save(); bgCtx.globalAlpha = .16; bgCtx.strokeStyle = '#d62839'; bgCtx.lineWidth = 1.4;
  bgCtx.beginPath();
  for (let x = 0; x <= bgW; x += 6) {
    const phase = (x + waveT) * 0.02;
    let y2 = 46 + Math.sin(phase * 0.6) * 3;
    const spike = (x + waveT) % 260;
    if (spike > 40 && spike < 70) y2 -= (spike - 40) * 1.7;
    else if (spike >= 70 && spike < 100) y2 += (spike - 70) * 1.7 - 51;
    if (x === 0) bgCtx.moveTo(x, y2); else bgCtx.lineTo(x, y2);
  }
  bgCtx.stroke(); bgCtx.restore();
}
function animateBg() {
  bgCtx.clearRect(0, 0, bgW, bgH);
  bgCells.forEach(c => {
    c.x += c.vx; c.y += c.vy;
    if (c.x < -0.2 || c.x > 1.2) c.vx *= -1;
    if (c.y < -0.2 || c.y > 1.2) c.vy *= -1;
    const cx = c.x * bgW, cy = c.y * bgH;
    const grad = bgCtx.createRadialGradient(cx, cy, 0, cx, cy, c.r);
    grad.addColorStop(0, c.hue + '.10)'); grad.addColorStop(1, c.hue + '0)');
    bgCtx.fillStyle = grad; bgCtx.beginPath(); bgCtx.arc(cx, cy, c.r, 0, Math.PI * 2); bgCtx.fill();
  });
  drawHBLine(); waveT += 1.1;
  requestAnimationFrame(animateBg);
}
animateBg();

/* === CURSOR TRAIL === */
const cursorCanvas = document.getElementById('cursorCanvas');
const cursorCtx = cursorCanvas.getContext('2d');
const drops = [];
function resizeCursor() { cursorCanvas.width = window.innerWidth; cursorCanvas.height = window.innerHeight; }
function animateCursor() {
  cursorCtx.clearRect(0, 0, cursorCanvas.width, cursorCanvas.height);
  for (let i = drops.length - 1; i >= 0; i--) {
    const p = drops[i]; p.x += p.vx; p.y += p.vy; p.size *= .95; p.opacity -= .025;
    cursorCtx.fillStyle = 'rgba(214,40,57,' + Math.max(0, p.opacity) + ')';
    cursorCtx.beginPath(); cursorCtx.arc(p.x, p.y, Math.max(.1, p.size), 0, Math.PI * 2); cursorCtx.fill();
    if (p.opacity <= 0 || p.size <= .5) drops.splice(i, 1);
  }
  requestAnimationFrame(animateCursor);
}
window.addEventListener('resize', resizeCursor);
window.addEventListener('mousemove', e => {
  if (Math.random() > .4) drops.push({x: e.clientX, y: e.clientY, size: Math.random()*6+3, vx:(Math.random()-.5)*2, vy:Math.random()*2+1, opacity:.8});
});
resizeCursor(); animateCursor();
</script>
</body>
</html>
