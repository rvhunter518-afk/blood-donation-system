<?php
/**
 * LifeFlow — Hospital Portal Database Installer
 * Visit this page ONCE via browser to create hospital tables.
 * DELETE or move this file after installation!
 */
header('Content-Type: text/html; charset=utf-8');
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) {
    die('<div style="font-family:sans-serif;color:red;padding:20px;">Connection failed: ' . htmlspecialchars($conn->connect_error) . '</div>');
}
$conn->set_charset('utf8mb4');

$sqls = [
    // hospitals
    "CREATE TABLE IF NOT EXISTS hospitals (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        hospital_uid VARCHAR(20) NOT NULL UNIQUE,
        hospital_name VARCHAR(200) NOT NULL,
        hospital_type ENUM('government','private','trust','medical_college') NOT NULL DEFAULT 'private',
        license_number VARCHAR(100) NULL,
        address TEXT NOT NULL,
        city VARCHAR(100) NOT NULL,
        district VARCHAR(100) NULL,
        pin_code VARCHAR(10) NULL,
        contact_phone VARCHAR(40) NOT NULL,
        official_email VARCHAR(200) NOT NULL UNIQUE,
        emergency_phone VARCHAR(40) NULL,
        is_24_7 TINYINT(1) NOT NULL DEFAULT 0,
        has_blood_bank TINYINT(1) NOT NULL DEFAULT 0,
        has_blood_storage TINYINT(1) NOT NULL DEFAULT 0,
        password_hash VARCHAR(255) NOT NULL,
        status ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (city, status),
        INDEX (hospital_type)
    ) ENGINE=InnoDB",

    // blood inventory
    "CREATE TABLE IF NOT EXISTS hospital_blood_inventory (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        hospital_id INT UNSIGNED NOT NULL,
        blood_group ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NOT NULL,
        available_units SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        minimum_units SMALLINT UNSIGNED NOT NULL DEFAULT 5,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_hospital_blood (hospital_id, blood_group),
        CONSTRAINT fk_hbi_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    // blood components
    "CREATE TABLE IF NOT EXISTS hospital_blood_components (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        hospital_id INT UNSIGNED NOT NULL,
        component ENUM('packed_rbc','platelets','ffp','cryoprecipitate') NOT NULL,
        available_units SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_hospital_component (hospital_id, component),
        CONSTRAINT fk_hbc_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    // blood batches
    "CREATE TABLE IF NOT EXISTS hospital_blood_batches (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        hospital_id INT UNSIGNED NOT NULL,
        blood_group ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NOT NULL,
        component ENUM('whole_blood','packed_rbc','platelets','ffp','cryoprecipitate') NOT NULL DEFAULT 'whole_blood',
        units SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        collection_date DATE NOT NULL,
        expiry_date DATE NOT NULL,
        storage_location VARCHAR(100) NULL,
        batch_uid VARCHAR(100) NOT NULL,
        notes TEXT NULL,
        is_used TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (hospital_id, expiry_date),
        INDEX (blood_group, expiry_date),
        CONSTRAINT fk_hbb_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",

    // emergency requests
    "CREATE TABLE IF NOT EXISTS hospital_emergency_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        hospital_id INT UNSIGNED NOT NULL,
        blood_group ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NOT NULL,
        required_units SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        required_by DATETIME NULL,
        priority ENUM('normal','urgent','critical') NOT NULL DEFAULT 'urgent',
        reason VARCHAR(200) NULL,
        category ENUM('surgery','accident','maternity','cancer','thalassemia','other') NOT NULL DEFAULT 'other',
        status ENUM('open','fulfilled','cancelled') NOT NULL DEFAULT 'open',
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (hospital_id, status),
        INDEX (blood_group, status),
        CONSTRAINT fk_her_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
];

$results = [];
foreach ($sqls as $sql) {
    $tableName = '';
    preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
    $tableName = $m[1] ?? 'unknown';
    if ($conn->query($sql)) {
        $results[] = ['table' => $tableName, 'ok' => true, 'msg' => 'Created / already exists'];
    } else {
        $results[] = ['table' => $tableName, 'ok' => false, 'msg' => $conn->error];
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hospital Portal — DB Installer</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{--crimson:#e63946;--blue:#2563eb;--green:#059669;--bg:#f6f9fc}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:24px;padding:40px;max-width:580px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.1);border:1px solid rgba(0,0,0,0.06)}
.card-header{text-align:center;margin-bottom:30px}
.icon{width:64px;height:64px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:#fff;margin:0 auto 16px;box-shadow:0 8px 20px rgba(37,99,235,0.35)}
h1{font-size:1.6rem;font-weight:800;color:#0f172a;margin-bottom:6px}
.sub{color:#64748b;font-size:0.92rem}
table{width:100%;border-collapse:collapse;margin-bottom:24px}
th{text-align:left;padding:10px 14px;background:#f1f5f9;font-size:0.82rem;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0}
td{padding:12px 14px;border-bottom:1px solid #f1f5f9;font-size:0.9rem}
.ok{color:var(--green);font-weight:700}
.err{color:var(--crimson);font-weight:700}
.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.75rem;font-weight:700}
.badge-ok{background:#d1fae5;color:#065f46}
.badge-err{background:#fee2e2;color:#991b1b}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:50px;font-weight:700;font-size:0.95rem;text-decoration:none;transition:all 0.25s}
.btn-primary{background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;box-shadow:0 6px 18px rgba(37,99,235,0.35)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 24px rgba(37,99,235,0.45)}
.btn-red{background:linear-gradient(135deg,#e63946,#b91c1c);color:#fff;box-shadow:0 6px 18px rgba(220,38,38,0.3)}
.btn-red:hover{transform:translateY(-2px)}
.warning{background:#fffbeb;border:1px solid #f59e0b;border-radius:12px;padding:12px 16px;margin-top:20px;color:#92400e;font-size:0.85rem;display:flex;gap:10px;align-items:flex-start}
</style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div class="icon"><i class="fas fa-hospital"></i></div>
        <h1>Hospital Portal — DB Setup</h1>
        <p class="sub">Installing hospital database tables into <strong>blood_connect</strong></p>
    </div>
    <table>
        <thead><tr><th>Table</th><th>Status</th><th>Message</th></tr></thead>
        <tbody>
        <?php foreach($results as $r): ?>
        <tr>
            <td><strong><?= htmlspecialchars($r['table']) ?></strong></td>
            <td>
                <?php if($r['ok']): ?>
                <span class="badge badge-ok"><i class="fas fa-check"></i> OK</span>
                <?php else: ?>
                <span class="badge badge-err"><i class="fas fa-times"></i> Error</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['msg']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="actions">
        <a href="hospital/register.php" class="btn btn-primary"><i class="fas fa-hospital"></i> Register Hospital</a>
        <a href="login.html" class="btn btn-red"><i class="fas fa-arrow-left"></i> Back to Login</a>
    </div>
    <div class="warning">
        <i class="fas fa-exclamation-triangle" style="margin-top:2px;flex:none;"></i>
        <span><strong>Security:</strong> Delete or rename <code>install_hospital.php</code> after installation to prevent unauthorized access.</span>
    </div>
</div>
</body>
</html>
