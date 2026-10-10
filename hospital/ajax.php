<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
$conn = getDbConnection();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'hospital' || !isset($_SESSION['hospital_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$hospital_id = $_SESSION['hospital_id'];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_inventory':
        $blood_group = $_POST['blood_group'] ?? '';
        $available_units = (int)($_POST['available_units'] ?? 0);
        $minimum_units = (int)($_POST['minimum_units'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE hospital_blood_inventory SET available_units = ?, minimum_units = ?, last_updated = CURRENT_TIMESTAMP WHERE hospital_id = ? AND blood_group = ?");
        $stmt->bind_param("iiis", $available_units, $minimum_units, $hospital_id, $blood_group);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update inventory']);
        }
        break;

    case 'update_component':
        $component = $_POST['component'] ?? '';
        $available_units = (int)($_POST['available_units'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE hospital_blood_components SET available_units = ?, last_updated = CURRENT_TIMESTAMP WHERE hospital_id = ? AND component = ?");
        $stmt->bind_param("iis", $available_units, $hospital_id, $component);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update component']);
        }
        break;

    case 'add_batch':
        $blood_group = $_POST['blood_group'] ?? '';
        $component = $_POST['component'] ?? '';
        $units = (int)($_POST['units'] ?? 0);
        $collection_date = $_POST['collection_date'] ?? '';
        $expiry_date = $_POST['expiry_date'] ?? '';
        $storage_location = $_POST['storage_location'] ?? '';
        $batch_uid = $_POST['batch_uid'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO hospital_blood_batches (hospital_id, blood_group, component, units, collection_date, expiry_date, storage_location, batch_uid, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississsss", $hospital_id, $blood_group, $component, $units, $collection_date, $expiry_date, $storage_location, $batch_uid, $notes);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add batch']);
        }
        break;

    case 'delete_batch':
        $id = (int)($_POST['id'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE hospital_blood_batches SET is_used = 1 WHERE id = ? AND hospital_id = ?");
        $stmt->bind_param("ii", $id, $hospital_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark batch as used']);
        }
        break;

    case 'add_emergency':
        $blood_group = $_POST['blood_group'] ?? '';
        $required_units = (int)($_POST['required_units'] ?? 0);
        $required_by = $_POST['required_by'] ?? '';
        $priority = $_POST['priority'] ?? 'normal';
        $reason = $_POST['reason'] ?? '';
        $category = $_POST['category'] ?? 'other';
        $notes = $_POST['notes'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO hospital_emergency_requests (hospital_id, blood_group, required_units, required_by, priority, reason, category, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')");
        $stmt->bind_param("isssssss", $hospital_id, $blood_group, $required_units, $required_by, $priority, $reason, $category, $notes);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add emergency request']);
        }
        break;

    case 'close_emergency':
        $id = (int)($_POST['id'] ?? 0);
        
        $stmt = $conn->prepare("UPDATE hospital_emergency_requests SET status = 'fulfilled' WHERE id = ? AND hospital_id = ?");
        $stmt->bind_param("ii", $id, $hospital_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to close emergency request']);
        }
        break;

    case 'get_stats':
        $stats = [
            'total_units' => 0,
            'critical_groups' => 0,
            'open_emergencies' => 0
        ];
        
        // Total units
        $stmt = $conn->prepare("SELECT SUM(available_units) as total FROM hospital_blood_inventory WHERE hospital_id = ?");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $stats['total_units'] = (int)$row['total'];
        }
        
        // Critical groups (available < minimum)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM hospital_blood_inventory WHERE hospital_id = ? AND available_units < minimum_units");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $stats['critical_groups'] = (int)$row['count'];
        }
        
        // Open emergencies
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM hospital_emergency_requests WHERE hospital_id = ? AND status = 'open'");
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $stats['open_emergencies'] = (int)$row['count'];
        }
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
