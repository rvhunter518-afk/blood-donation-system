<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? (int)$_SESSION['user_id'] : 0;
$userName = $_SESSION['user_name'] ?? 'Donor';

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli('localhost', 'root', '', 'blood_connect');

$userData = [
    'full_name' => $userName,
    'email' => '',
    'phone' => '',
    'blood_type' => 'O+',
    'city' => 'Chennai',
    'area' => ''
];
$donor = [];

if (!$conn->connect_error && $userId > 0) {
    $conn->set_charset('utf8mb4');
    $uStmt = $conn->prepare("SELECT full_name, email, phone, blood_type, city, area FROM users WHERE id = ?");
    if ($uStmt) {
        $uStmt->bind_param("i", $userId);
        $uStmt->execute();
        $uRes = $uStmt->get_result();
        if ($row = $uRes->fetch_assoc()) {
            $userData = $row;
        }
        $uStmt->close();
    }

    $dStmt = $conn->prepare("SELECT * FROM donors WHERE user_id = ?");
    if ($dStmt) {
        $dStmt->bind_param("i", $userId);
        $dStmt->execute();
        $dRes = $dStmt->get_result();
        if ($dRow = $dRes->fetch_assoc()) {
            $donor = $dRow;
        }
        $dStmt->close();
    }
    $conn->close();
}

$donorCode = !empty($donor['donor_code']) ? $donor['donor_code'] : ('DON-' . strtoupper(substr(md5((string)$userId), 0, 6)));
$bloodGroup = !empty($donor['blood_type']) ? $donor['blood_type'] : ($userData['blood_type'] ?? 'O+');
$resultStatus = !empty($donor['preliminary_result']) ? $donor['preliminary_result'] : 'green';
$city = !empty($donor['city']) ? $donor['city'] : ($userData['city'] ?? 'Chennai');
$area = !empty($donor['area']) ? $donor['area'] : ($userData['area'] ?? '');
$phone = !empty($donor['phone']) ? $donor['phone'] : ($userData['phone'] ?? '');
$emergencyAvail = !empty($donor['emergency_availability']) ? $donor['emergency_availability'] : 'Available';
$totalDonations = (int)($donor['total_donations'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LifeFlow Donor Profile & ID Card</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
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
            --blue: #1d4ed8;
            --blue-soft: #eff6ff;
            --line: #ecdfda;
            --shadow: 0 18px 40px rgba(143,18,32,.09), 0 6px 14px rgba(23,27,34,.04);
            --radius-lg: 24px;
            --radius-md: 16px;
            --radius-sm: 10px;
        }

        body {
            background-color: var(--paper);
            color: var(--ink);
            font-family: 'DM Sans', sans-serif;
            margin: 0;
            padding: 30px 20px 60px;
        }

        .profile-container {
            max-width: 820px;
            margin: 0 auto;
        }

        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .btn-nav-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 50px;
            color: var(--ink);
            font-weight: 700;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-nav-back:hover {
            background: var(--red);
            color: #ffffff;
            border-color: var(--red);
        }

        .donor-id-card {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
            margin-bottom: 30px;
        }

        .card-header-band {
            background: linear-gradient(135deg, var(--red), var(--red-deep));
            color: #ffffff;
            padding: 24px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .card-brand-icon {
            font-size: 2rem;
            color: #ffffff;
        }
        .card-brand-title {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .card-brand-sub {
            font-size: 0.78rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.9;
        }

        .donor-code-pill {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 6px 14px;
            border-radius: 50px;
            font-family: 'Space Grotesk', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            font-size: 0.95rem;
        }

        .card-body {
            padding: 30px;
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 28px;
            align-items: center;
        }
        @media (max-width: 600px) {
            .card-body {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }

        .blood-avatar-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .blood-badge-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--red-soft);
            border: 3px solid var(--red);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(214,40,57,0.2);
        }
        .blood-badge-large .blood-text {
            font-size: 2.2rem;
            font-weight: 900;
            color: var(--red);
            line-height: 1;
            font-family: 'Space Grotesk', sans-serif;
        }
        .blood-badge-large .type-label {
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--red-deep);
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        .donor-info-col h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--ink);
            margin: 0 0 8px;
        }
        .info-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }
        .badge-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .badge-verified {
            background: var(--green-soft);
            color: var(--green);
            border: 1px solid #bbf7d0;
        }
        .badge-status-green {
            background: var(--green-soft);
            color: var(--green);
            border: 1px solid #bbf7d0;
        }
        .badge-status-yellow {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .badge-status-red {
            background: var(--red-soft);
            color: var(--red-deep);
            border: 1px solid #fecaca;
        }

        .donor-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            background: var(--cream);
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            padding: 16px;
        }
        @media (max-width: 600px) {
            .donor-details-grid {
                grid-template-columns: 1fr;
                text-align: left;
            }
        }
        .detail-item strong {
            display: block;
            font-size: 0.76rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .detail-item span {
            font-size: 0.94rem;
            font-weight: 700;
            color: var(--ink);
        }

        .card-footer {
            background: #fdfaf8;
            border-top: 1px solid var(--line);
            padding: 14px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--muted);
        }

        .actions-bar {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary-action {
            background: var(--red);
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 14px rgba(214,40,57,0.3);
        }
        .btn-primary-action:hover {
            background: var(--red-deep);
            color: #ffffff;
        }
        .btn-outline-action {
            background: #ffffff;
            color: var(--ink);
            border: 1px solid var(--line);
        }
        .btn-outline-action:hover {
            background: var(--cream);
            color: var(--ink);
        }

        @media print {
            body { padding: 0; background: #fff; }
            .top-nav, .actions-bar { display: none !important; }
            .donor-id-card { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

    <div class="profile-container">
        <div class="top-nav">
            <a href="index.php" class="btn-nav-back"><i class="fas fa-arrow-left"></i> Donor Dashboard</a>
            <a href="eligibility.php" class="btn-nav-back"><i class="fas fa-clipboard-check"></i> Retake Screening</a>
        </div>

        <div class="donor-id-card">
            <div class="card-header-band">
                <div class="card-brand">
                    <i class="fas fa-droplet card-brand-icon"></i>
                    <div>
                        <h1 class="card-brand-title">LifeFlow</h1>
                        <span class="card-brand-sub">National Blood Donor Registry</span>
                    </div>
                </div>
                <div class="donor-code-pill">
                    <i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($donorCode); ?>
                </div>
            </div>

            <div class="card-body">
                <div class="blood-avatar-wrap">
                    <div class="blood-badge-large">
                        <span class="blood-text"><?php echo htmlspecialchars($bloodGroup); ?></span>
                        <span class="type-label">Blood Group</span>
                    </div>
                </div>

                <div class="donor-info-col">
                    <h2><?php echo htmlspecialchars($userData['full_name'] ?? $userName); ?></h2>
                    
                    <div class="info-badges">
                        <span class="badge-chip badge-verified"><i class="fas fa-certificate"></i> Registered Donor</span>
                        <?php if ($resultStatus === 'green'): ?>
                        <span class="badge-chip badge-status-green"><i class="fas fa-circle-check"></i> Likely Eligible</span>
                        <?php elseif ($resultStatus === 'yellow'): ?>
                        <span class="badge-chip badge-status-yellow"><i class="fas fa-triangle-exclamation"></i> Medical Review Required</span>
                        <?php else: ?>
                        <span class="badge-chip badge-status-red"><i class="fas fa-clock-rotate-left"></i> Temporarily Deferred</span>
                        <?php endif; ?>
                    </div>

                    <div class="donor-details-grid">
                        <div class="detail-item">
                            <strong>Location</strong>
                            <span><?php echo htmlspecialchars(($area !== '' ? $area . ', ' : '') . $city); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Emergency Status</strong>
                            <span><?php echo htmlspecialchars($emergencyAvail); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Contact</strong>
                            <span><?php echo htmlspecialchars($phone !== '' ? $phone : 'Registered Account'); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Donations Made</strong>
                            <span><?php echo $totalDonations; ?> donation<?php echo $totalDonations === 1 ? '' : 's'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <div>
                    <i class="fas fa-shield-heart me-1" style="color:var(--red);"></i> Verified LifeFlow Digital Blood Donor Card
                </div>
                <div>
                    Present this card at participating blood banks
                </div>
            </div>
        </div>

        <div class="actions-bar">
            <button type="button" onclick="window.print()" class="btn-action btn-primary-action">
                <i class="fas fa-print"></i> Print / Save Donor Card
            </button>
            <a href="index.php" class="btn-action btn-outline-action">
                <i class="fas fa-house"></i> Go to Dashboard
            </a>
            <a href="eligibility.php" class="btn-action btn-outline-action">
                <i class="fas fa-pen-to-square"></i> Update Health Answers
            </a>
        </div>
    </div>

</body>
</html>