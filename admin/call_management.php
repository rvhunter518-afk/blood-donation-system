<?php
/**
 * Admin Call Management Dashboard
 * /admin/call_management.php
 *
 * NOTE: This project does not yet have an admin login system.
 * For now, access is restricted to logged-in users with role 'donor'
 * as a placeholder. You should add a proper admin role/table later.
 *
 * To enable this, add an 'admin' role to your users table:
 *   ALTER TABLE users MODIFY role ENUM('user','donor','admin') NOT NULL DEFAULT 'user';
 * Then log in as admin and update this guard.
 */
session_start();

// ── Temporary admin guard: change this to check for 'admin' role ─
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}
// Uncomment when admin role exists:
// if (($_SESSION['user_role'] ?? '') !== 'admin') {
//     header('Location: ../login.html?error=role'); exit;
// }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) die('Database error.');
$conn->set_charset('utf8mb4');

// ── Stats ─────────────────────────────────────────────────
$stats   = ['total' => 0, 'active' => 0, 'completed' => 0, 'failed' => 0, 'avg_duration' => 0, 'today' => 0];
$dbError = '';

$statsResult = $conn->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status IN ('initiated','ringing','answered')) AS active,
        SUM(status = 'completed') AS completed,
        SUM(status IN ('failed','no_answer','busy','rejected')) AS failed,
        ROUND(AVG(CASE WHEN duration_seconds > 0 THEN duration_seconds END)) AS avg_duration,
        SUM(DATE(started_at) = CURDATE()) AS today
     FROM call_logs"
);
if (!$statsResult) {
    $dbError = 'Call logs table not found. Please import database/call_logs.sql in phpMyAdmin.';
} else {
    $stats = array_merge($stats, $statsResult->fetch_assoc());
}

// ── Paginated call list ───────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$total   = (int)($stats['total'] ?? 0);
$pages   = max(1, (int)ceil($total / $perPage));

$filterStatus = in_array($_GET['status'] ?? '', ['','initiated','ringing','answered','completed','failed','cancelled','no_answer','busy','rejected'], true)
    ? ($_GET['status'] ?? '')
    : '';

$calls = [];
if (!$dbError) {
    $whereClause = $filterStatus ? "WHERE cl.status = '{$conn->real_escape_string($filterStatus)}'" : '';
    $result = $conn->query(
        "SELECT
            cl.call_id, cl.status, cl.started_at, cl.ended_at,
            cl.duration_seconds, cl.context_type, cl.provider,
            cl.caller_role, cl.receiver_role,
            caller.full_name  AS caller_name,
            receiver.full_name AS receiver_name
         FROM call_logs cl
         JOIN users caller   ON caller.id   = cl.caller_user_id
         JOIN users receiver ON receiver.id = cl.receiver_user_id
         {$whereClause}
         ORDER BY cl.started_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    if ($result) {
        $calls = $result->fetch_all(MYSQLI_ASSOC);
    }
}
$conn->close();

function statusBadge(string $s): string {
    $cfg = [
        'initiated' => ['#eff6ff','#1d4ed8','fa-phone'],
        'ringing'   => ['#eff6ff','#1d4ed8','fa-phone-volume'],
        'answered'  => ['#d1fae5','#059669','fa-phone-volume'],
        'completed' => ['#d1fae5','#059669','fa-phone'],
        'failed'    => ['#fee2e2','#dc2626','fa-phone-slash'],
        'no_answer' => ['#fef3c7','#d97706','fa-clock'],
        'busy'      => ['#fef3c7','#d97706','fa-phone-slash'],
        'rejected'  => ['#fef3c7','#d97706','fa-phone-slash'],
        'cancelled' => ['#f3f4f6','#6b7280','fa-phone-slash'],
    ];
    [$bg, $color, $icon] = $cfg[$s] ?? ['#f3f4f6','#6b7280','fa-phone'];
    $label = ucfirst(str_replace('_',' ',$s));
    return "<span class=\"call-status-pill\" style=\"background:{$bg};color:{$color}\"><i class=\"fas {$icon}\"></i> {$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) ?>">
<title>Call Management | LifeFlow Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Space+Grotesk:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/call.css">
<style>
*{box-sizing:border-box}
body{margin:0;font:15px/1.5 'DM Sans',sans-serif;background:#f9fafb;color:#111827;min-height:100vh}
header{background:#fff;border-bottom:1px solid #e5e7eb;padding:16px 32px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10;box-shadow:0 1px 4px rgba(0,0,0,.06)}
.logo{font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:800;color:#111827;display:flex;align-items:center;gap:8px}
.logo i{color:#dc2626}
nav a{color:#6b7280;text-decoration:none;font-weight:600;font-size:.88rem;margin-left:20px}
nav a:hover{color:#111827}
.main-wrap{max-width:1200px;margin:0 auto;padding:36px 24px 80px}
.page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;flex-wrap:wrap;gap:12px}
.page-header h1{font-family:'Space Grotesk',sans-serif;font-size:1.8rem;font-weight:800;margin:0 0 4px}
.page-header p{color:#6b7280;margin:0;font-size:.9rem}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:32px}
.call-admin-stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.call-admin-stat-card .stat-num{font-family:'Space Grotesk',sans-serif;font-size:2rem;font-weight:800;display:block}
.stat-total    .stat-num{color:#1a56db}
.stat-active   .stat-num{color:#059669}
.stat-completed .stat-num{color:#059669}
.stat-failed   .stat-num{color:#dc2626}
.stat-avg      .stat-num{color:#d97706}
.stat-today    .stat-num{color:#7c3aed}
.call-admin-stat-card .stat-label{font-size:.78rem;color:#6b7280;font-weight:600;margin-top:4px;display:block}
.filter-bar{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.filter-bar label{font-size:.85rem;font-weight:700;color:#374151}
.filter-bar select{padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px;font:inherit;color:#374151;background:#f9fafb;cursor:pointer}
.filter-bar button{padding:8px 18px;background:#1a56db;color:#fff;border:none;border-radius:8px;font:700 .85rem inherit;cursor:pointer}
.filter-bar button:hover{background:#1240b5}
.table-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.table-card-header{padding:16px 20px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center}
.table-card-header h3{font-family:'Space Grotesk',sans-serif;font-size:1rem;font-weight:700;margin:0}
.table-card-header span{font-size:.82rem;color:#6b7280}
.call-table-wrap{overflow-x:auto}
.call-table{width:100%;border-collapse:collapse;font-size:.85rem}
.call-table thead th{background:#f9fafb;border-bottom:2px solid #e5e7eb;padding:12px 14px;text-align:left;font-weight:700;color:#374151;white-space:nowrap}
.call-table tbody td{padding:11px 14px;border-bottom:1px solid #f3f4f6;vertical-align:middle}
.call-table tbody tr:last-child td{border-bottom:none}
.call-table tbody tr:hover{background:#f9fafb}
.call-id-cell{font-family:monospace;font-size:.75rem;color:#9ca3af}
.user-cell{font-weight:600;color:#111827}
.role-chip{font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:50px;background:#eff6ff;color:#1d4ed8;margin-left:4px}
.duration-cell{font-variant-numeric:tabular-nums;color:#374151}
.no-data{padding:48px;text-align:center;color:#9ca3af}
.no-data i{font-size:2rem;margin-bottom:12px;display:block}
.pagination{display:flex;justify-content:center;gap:8px;margin-top:24px;flex-wrap:wrap}
.pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-weight:700;font-size:.85rem;text-decoration:none;border:1px solid #e5e7eb;color:#374151;background:#fff;transition:.2s}
.pagination a:hover{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.pagination .current{background:#1a56db;border-color:#1a56db;color:#fff}
.alert-db{padding:16px 20px;background:#fef3c7;border:1px solid #fde68a;border-radius:12px;color:#854d0e;margin-bottom:24px;font-size:.9rem}
@media(max-width:640px){.stats-grid{grid-template-columns:1fr 1fr}.page-header{flex-direction:column}}
</style>
</head>
<body>
<header>
  <div class="logo"><i class="fas fa-phone-volume"></i> Call Management</div>
  <nav>
    <a href="../user/user.html"><i class="fas fa-home"></i> Dashboard</a>
    <a href="../logout.php"><i class="fas fa-right-from-bracket"></i> Sign out</a>
  </nav>
</header>

<div class="main-wrap">
  <div class="page-header">
    <div>
      <h1><i class="fas fa-phone-volume" style="color:#1a56db;margin-right:10px"></i>Call Management</h1>
      <p>Monitor all call activity across the LifeFlow platform.</p>
    </div>
  </div>

  <?php if ($dbError): ?>
  <div class="alert-db"><i class="fas fa-triangle-exclamation"></i> <?= htmlspecialchars($dbError) ?></div>
  <?php else: ?>

  <!-- ── Stats ──────────────────────────────────────────── -->
  <div class="stats-grid">
    <div class="call-admin-stat-card stat-total">
      <span class="stat-num"><?= number_format((int)$stats['total']) ?></span>
      <span class="stat-label"><i class="fas fa-phone"></i> Total Calls</span>
    </div>
    <div class="call-admin-stat-card stat-active">
      <span class="stat-num"><?= (int)$stats['active'] ?></span>
      <span class="stat-label"><i class="fas fa-phone-volume"></i> Active Now</span>
    </div>
    <div class="call-admin-stat-card stat-completed">
      <span class="stat-num"><?= number_format((int)$stats['completed']) ?></span>
      <span class="stat-label"><i class="fas fa-circle-check"></i> Completed</span>
    </div>
    <div class="call-admin-stat-card stat-failed">
      <span class="stat-num"><?= number_format((int)$stats['failed']) ?></span>
      <span class="stat-label"><i class="fas fa-phone-slash"></i> Failed / Missed</span>
    </div>
    <div class="call-admin-stat-card stat-avg">
      <span class="stat-num">
        <?php
          $avg = (int)$stats['avg_duration'];
          echo $avg ? floor($avg/60).'m '.($avg%60).'s' : '—';
        ?>
      </span>
      <span class="stat-label"><i class="fas fa-stopwatch"></i> Avg Duration</span>
    </div>
    <div class="call-admin-stat-card stat-today">
      <span class="stat-num"><?= (int)$stats['today'] ?></span>
      <span class="stat-label"><i class="fas fa-calendar-day"></i> Today</span>
    </div>
  </div>

  <!-- ── Filter bar ─────────────────────────────────────── -->
  <div class="filter-bar">
    <label for="filterStatus"><i class="fas fa-filter"></i> Filter by status:</label>
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <select name="status" id="filterStatus">
        <option value="">All statuses</option>
        <?php foreach (['initiated','ringing','answered','completed','failed','no_answer','busy','rejected','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit">Apply</button>
      <?php if ($filterStatus): ?><a href="?" style="font-size:.82rem;color:#6b7280;text-decoration:none">Clear</a><?php endif; ?>
    </form>
  </div>

  <!-- ── Call table ─────────────────────────────────────── -->
  <div class="table-card">
    <div class="table-card-header">
      <h3>Call Log</h3>
      <span>Showing <?= count($calls) ?> of <?= number_format($total) ?> records <?= $filterStatus ? '· filtered by: '.ucfirst($filterStatus) : '' ?></span>
    </div>
    <div class="call-table-wrap">
      <table class="call-table">
        <thead>
          <tr>
            <th>Call ID</th>
            <th>Caller</th>
            <th>Receiver</th>
            <th>Context</th>
            <th>Status</th>
            <th>Duration</th>
            <th>Started</th>
            <th>Provider</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($calls)): ?>
          <tr><td colspan="8" class="no-data"><i class="fas fa-phone-slash"></i> No calls found.</td></tr>
          <?php else: ?>
          <?php foreach ($calls as $call): ?>
          <tr>
            <td class="call-id-cell"><?= htmlspecialchars(substr($call['call_id'], 0, 22)) ?>…</td>
            <td class="user-cell">
              <?= htmlspecialchars($call['caller_name']) ?>
              <span class="role-chip"><?= htmlspecialchars($call['caller_role']) ?></span>
            </td>
            <td class="user-cell">
              <?= htmlspecialchars($call['receiver_name']) ?>
              <span class="role-chip"><?= htmlspecialchars($call['receiver_role']) ?></span>
            </td>
            <td><?= htmlspecialchars(str_replace('_', ' ', $call['context_type'])) ?></td>
            <td><?= statusBadge($call['status']) ?></td>
            <td class="duration-cell">
              <?php if ($call['duration_seconds']): ?>
                <?= floor($call['duration_seconds']/60).':'.str_pad($call['duration_seconds']%60,2,'0',STR_PAD_LEFT) ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td style="white-space:nowrap;color:#6b7280;font-size:.82rem">
              <?= htmlspecialchars(date('d M, H:i', strtotime($call['started_at']))) ?>
            </td>
            <td>
              <span style="font-size:.78rem;font-family:monospace;color:<?= $call['provider']==='test'?'#d97706':'#059669' ?>">
                <?= htmlspecialchars($call['provider']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Pagination ─────────────────────────────────────── -->
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page-1 ?>&status=<?= urlencode($filterStatus) ?>"><i class="fas fa-chevron-left"></i></a>
    <?php endif; ?>
    <?php for ($i = max(1,$page-3); $i <= min($pages,$page+3); $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?page=<?= $i ?>&status=<?= urlencode($filterStatus) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $pages): ?>
      <a href="?page=<?= $page+1 ?>&status=<?= urlencode($filterStatus) ?>"><i class="fas fa-chevron-right"></i></a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php endif; // end dbError check ?>
</div>

<div id="callToastContainer"></div>
<!-- No call panel/modal needed on admin page, just the toast container -->
</body>
</html>

