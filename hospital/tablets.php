<?php
session_start();
require_once '../config/database.php';
$conn = getDbConnection();

// Check hospital auth
if(!isset($_SESSION['hospital_id']) || $_SESSION['user_role'] !== 'hospital') {
    header("Location: ../login.html");
    exit();
}

$hospital_id = $_SESSION['hospital_id'];

// Fetch all requests
$requests = [];
$req_res = $conn->query("
    SELECT r.*, u.full_name as requester_name 
    FROM blood_requests r 
    LEFT JOIN users u ON r.requester_id = u.id 
    ORDER BY r.created_at DESC
");
if($req_res) $requests = $req_res->fetch_all(MYSQLI_ASSOC);

// Fetch all users
$users = [];
$users_res = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
if($users_res) $users = $users_res->fetch_all(MYSQLI_ASSOC);

// Fetch all donors
$donors = [];
$donors_res = $conn->query("
    SELECT d.*, u.full_name, u.email 
    FROM donors d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.created_at DESC
");
if($donors_res) $donors = $donors_res->fetch_all(MYSQLI_ASSOC);

// Fetch all hospitals
$hospitals = [];
$hosp_res = $conn->query("SELECT * FROM hospitals ORDER BY created_at DESC");
if($hosp_res) $hospitals = $hosp_res->fetch_all(MYSQLI_ASSOC);

// Fetch inventory summary (pivot view via PHP)
$inventories = [];
$inv_res = $conn->query("
    SELECT i.hospital_id, h.hospital_name, h.city, i.blood_group, i.available_units, i.minimum_units
    FROM hospital_blood_inventory i
    JOIN hospitals h ON i.hospital_id = h.id
");
$inventory_data = [];
if($inv_res) {
    while($row = $inv_res->fetch_assoc()) {
        $hid = $row['hospital_id'];
        if(!isset($inventory_data[$hid])) {
            $inventory_data[$hid] = [
                'name' => $row['hospital_name'],
                'city' => $row['city'],
                'A+'=>0, 'A-'=>0, 'B+'=>0, 'B-'=>0, 'O+'=>0, 'O-'=>0, 'AB+'=>0, 'AB-'=>0,
                'total' => 0
            ];
        }
        $bg = $row['blood_group'];
        $units = (int)$row['available_units'];
        $inventory_data[$hid][$bg] = $units;
        $inventory_data[$hid]['total'] += $units;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Data Tables - LifeFlow</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/header_menu.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #080b11;
            --bg-card: rgba(18,24,38,0.75);
            --crimson: #e63946;
            --crimson-glow: #ff2a44;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --accent-cyan: #06d6a0;
            --border-glass: rgba(255,255,255,0.08);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        h1, h2, h3 { font-family: 'Outfit', sans-serif; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 5%;
            background: rgba(8, 11, 17, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-glass);
        }
        .logo { color: var(--crimson); font-size: 24px; font-weight: bold; text-decoration: none; }
        .back-link { color: var(--text-muted); text-decoration: none; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        .back-link:hover { color: white; }

        .container {
            padding: 30px 5%;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            background: rgba(0,0,0,0.3);
            padding: 10px;
            border-radius: 12px;
            overflow-x: auto;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            white-space: nowrap;
            transition: 0.3s;
        }
        .tab-btn.active, .tab-btn:hover {
            background: var(--crimson);
            color: white;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .table-controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        .search-box input {
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            padding: 10px 15px 10px 40px;
            border-radius: 8px;
            color: white;
            box-sizing: border-box;
        }
        
        .btn-export {
            background: rgba(6, 214, 160, 0.1);
            color: var(--accent-cyan);
            border: 1px solid rgba(6, 214, 160, 0.3);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        .btn-export:hover {
            background: var(--accent-cyan);
            color: #000;
        }

        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 16px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        th {
            background: rgba(0,0,0,0.4);
            padding: 15px;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid var(--border-glass);
            cursor: pointer;
            user-select: none;
        }
        th:hover { color: white; }
        td {
            padding: 15px;
            border-bottom: 1px solid var(--border-glass);
            font-size: 14px;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: rgba(255,255,255,0.02); }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* Urgency badges */
        .urgency-critical { background: rgba(230,57,70,0.1); color: var(--crimson); }
        .urgency-urgent { background: rgba(255,193,7,0.1); color: #ffc107; }
        .urgency-routine { background: rgba(6,214,160,0.1); color: var(--accent-cyan); }
        
        /* Status badges */
        .status-open { background: rgba(255,255,255,0.1); color: white; }
        .status-fulfilled { background: rgba(6,214,160,0.1); color: var(--accent-cyan); }
        
        /* Role badges */
        .role-user { background: rgba(33,150,243,0.1); color: #2196f3; }
        .role-donor { background: rgba(230,57,70,0.1); color: var(--crimson); }
        .role-hospital { background: rgba(156,39,176,0.1); color: #9c27b0; }

        /* Inventory cell colors */
        .inv-safe { color: var(--accent-cyan); font-weight: 600; }
        .inv-low { color: #ffc107; font-weight: 600; }
        .inv-critical { color: var(--crimson); font-weight: 600; }

    </style>
</head>
<body>

    <header class="header">
        <a href="dashboard.php" class="logo"><i class="fa-solid fa-droplet"></i> LifeFlow</a>
        <a href="dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </header>

    <div class="container">
        <div class="page-header">
            <h1>📋 Advanced Data Tables</h1>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('requests')">Blood Requests</button>
            <button class="tab-btn" onclick="switchTab('users')">Users</button>
            <button class="tab-btn" onclick="switchTab('donors')">Donors</button>
            <button class="tab-btn" onclick="switchTab('hospitals')">Hospitals</button>
            <button class="tab-btn" onclick="switchTab('inventory')">Inventory Summary</button>
        </div>

        <!-- Requests Tab -->
        <div id="requests" class="tab-content active"><br>
            <div class="table-controls">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search requests..." onkeyup="filterTable('reqTable', this.value)">
                </div>
                <button class="btn-export" onclick="exportTableToCSV('reqTable', 'blood_requests.csv')"><i class="fa-solid fa-download"></i> Export CSV</button>
            </div>
            <div class="table-container">
                <table id="reqTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable('reqTable', 0)">ID <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('reqTable', 1)">Patient Name <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('reqTable', 2)">Blood Type <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('reqTable', 3)">Units <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('reqTable', 4)">City <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('reqTable', 5)">Urgency <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('reqTable', 6)">Status <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('reqTable', 7)">Date <i class="fa-solid fa-sort"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($requests as $r): ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['patient_name']) ?></td>
                            <td style="color: var(--crimson); font-weight: bold;"><?= htmlspecialchars($r['blood_type']) ?></td>
                            <td><?= $r['units'] ?></td>
                            <td><?= htmlspecialchars($r['city']) ?></td>
                            <td><span class="badge urgency-<?= strtolower($r['urgency']) ?>"><?= ucfirst($r['urgency']) ?></span></td>
                            <td><span class="badge status-<?= strtolower($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Users Tab -->
        <div id="users" class="tab-content"><br>
            <div class="table-controls">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search users..." onkeyup="filterTable('userTable', this.value)">
                </div>
                <button class="btn-export" onclick="exportTableToCSV('userTable', 'users.csv')"><i class="fa-solid fa-download"></i> Export CSV</button>
            </div>
            <div class="table-container">
                <table id="userTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable('userTable', 0)">ID <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('userTable', 1)">Name <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('userTable', 2)">Email <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('userTable', 3)">Blood Type <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('userTable', 4)">Phone <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('userTable', 5)">City <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('userTable', 6)">Role <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('userTable', 7)">Registered <i class="fa-solid fa-sort"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td>#<?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['blood_type']) ?></td>
                            <td><?= htmlspecialchars($u['phone']) ?></td>
                            <td><?= htmlspecialchars($u['city']) ?></td>
                            <td><span class="badge role-<?= strtolower($u['role']) ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Donors Tab -->
        <div id="donors" class="tab-content"><br>
            <div class="table-controls">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search donors..." onkeyup="filterTable('donorTable', this.value)">
                </div>
                <button class="btn-export" onclick="exportTableToCSV('donorTable', 'donors.csv')"><i class="fa-solid fa-download"></i> Export CSV</button>
            </div>
            <div class="table-container">
                <table id="donorTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable('donorTable', 0)">ID <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('donorTable', 1)">Name <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('donorTable', 2)">Blood Type <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('donorTable', 3)">City <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('donorTable', 4)">Phone <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('donorTable', 5)">Availability <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('donorTable', 6)">Total Donations <i class="fa-solid fa-sort"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($donors as $d): ?>
                        <tr>
                            <td>#<?= $d['id'] ?></td>
                            <td><?= htmlspecialchars($d['full_name']) ?></td>
                            <td style="color: var(--crimson); font-weight: bold;"><?= htmlspecialchars($d['blood_type']) ?></td>
                            <td><?= htmlspecialchars($d['city']) ?></td>
                            <td><?= htmlspecialchars($d['phone']) ?></td>
                            <td>
                                <?php if($d['availability'] == 'available'): ?>
                                    <span style="color: var(--accent-cyan);"><i class="fa-solid fa-circle-check"></i> Available</span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);"><i class="fa-solid fa-circle-xmark"></i> Unavailable</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $d['total_donations'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Hospitals Tab -->
        <div id="hospitals" class="tab-content"><br>
            <div class="table-controls">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search hospitals..." onkeyup="filterTable('hospTable', this.value)">
                </div>
                <button class="btn-export" onclick="exportTableToCSV('hospTable', 'hospitals.csv')"><i class="fa-solid fa-download"></i> Export CSV</button>
            </div>
            <div class="table-container">
                <table id="hospTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable('hospTable', 0)">UID <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('hospTable', 1)">Name <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('hospTable', 2)">Type <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('hospTable', 3)">City <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('hospTable', 4)">Phone <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('hospTable', 5)">Blood Bank <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('hospTable', 6)">Status <i class="fa-solid fa-sort"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($hospitals as $h): ?>
                        <tr>
                            <td><?= htmlspecialchars($h['hospital_uid']) ?></td>
                            <td><?= htmlspecialchars($h['hospital_name']) ?></td>
                            <td><?= htmlspecialchars($h['hospital_type']) ?></td>
                            <td><?= htmlspecialchars($h['city']) ?></td>
                            <td><?= htmlspecialchars($h['contact_phone']) ?></td>
                            <td><?= $h['has_blood_bank'] ? 'Yes' : 'No' ?></td>
                            <td><?= ucfirst($h['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Inventory Tab -->
        <div id="inventory" class="tab-content"><br>
            <div class="table-controls">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" placeholder="Search inventory..." onkeyup="filterTable('invTable', this.value)">
                </div>
                <button class="btn-export" onclick="exportTableToCSV('invTable', 'inventory_summary.csv')"><i class="fa-solid fa-download"></i> Export CSV</button>
            </div>
            <div class="table-container">
                <table id="invTable">
                    <thead>
                        <tr>
                            <th onclick="sortTable('invTable', 0)">Hospital <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 1)">City <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 2)">A+ <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 3)">A- <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 4)">B+ <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 5)">B- <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 6)">O+ <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 7)">O- <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 8)">AB+ <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 9)">AB- <i class="fa-solid fa-sort"></i></th>
                            <th onclick="sortTable('invTable', 10)">Total <i class="fa-solid fa-sort"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($inventory_data as $inv): ?>
                        <tr>
                            <td><?= htmlspecialchars($inv['name']) ?></td>
                            <td><?= htmlspecialchars($inv['city']) ?></td>
                            <td><?= $inv['A+'] ?></td>
                            <td><?= $inv['A-'] ?></td>
                            <td><?= $inv['B+'] ?></td>
                            <td><?= $inv['B-'] ?></td>
                            <td><?= $inv['O+'] ?></td>
                            <td><?= $inv['O-'] ?></td>
                            <td><?= $inv['AB+'] ?></td>
                            <td><?= $inv['AB-'] ?></td>
                            <td style="font-weight:bold; color:var(--accent-cyan);"><?= $inv['total'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }

        function filterTable(tableId, query) {
            const trs = document.querySelectorAll('#' + tableId + ' tbody tr');
            query = query.toLowerCase();
            trs.forEach(tr => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function sortTable(tableId, n) {
            var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
            table = document.getElementById(tableId);
            switching = true;
            dir = "asc"; 
            while (switching) {
                switching = false;
                rows = table.rows;
                for (i = 1; i < (rows.length - 1); i++) {
                    shouldSwitch = false;
                    x = rows[i].getElementsByTagName("TD")[n];
                    y = rows[i + 1].getElementsByTagName("TD")[n];
                    let xContent = x.innerHTML.toLowerCase();
                    let yContent = y.innerHTML.toLowerCase();
                    
                    if (dir == "asc") {
                        if (xContent > yContent) { shouldSwitch = true; break; }
                    } else if (dir == "desc") {
                        if (xContent < yContent) { shouldSwitch = true; break; }
                    }
                }
                if (shouldSwitch) {
                    rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
                    switching = true;
                    switchcount ++;
                } else {
                    if (switchcount == 0 && dir == "asc") {
                        dir = "desc";
                        switching = true;
                    }
                }
            }
        }

        function downloadCSV(csv, filename) {
            let csvFile;
            let downloadLink;
            csvFile = new Blob([csv], {type: "text/csv"});
            downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
        }

        function exportTableToCSV(tableId, filename) {
            var csv = [];
            var rows = document.querySelectorAll('#' + tableId + ' tr');
            for (var i = 0; i < rows.length; i++) {
                if(rows[i].style.display !== 'none') {
                    var row = [], cols = rows[i].querySelectorAll("td, th");
                    for (var j = 0; j < cols.length; j++) 
                        row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
                    csv.push(row.join(","));
                }
            }
            downloadCSV(csv.join("\\n"), filename);
        }
    </script>
</body>
</html>
