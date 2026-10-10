<?php
/**
 * CallService — Core WebRTC in-website audio and video call orchestration service.
 * BloodConnect — Life-Saving Blood Connection System
 * services/CallService.php
 *
 * Handles:
 * - Session validation and participant authentication
 * - Call lifecycle: initiate, ring, accept, reject, busy, end, missed, timeout
 * - Signaling queue: offer, answer, ice-candidate
 * - Real-time presence tracking (online, in_call, offline)
 * - Auto-expiration of stale/abandoned calls
 * - Call duration and call history logging
 * - Zero phone numbers exposed
 */

require_once __DIR__ . '/../config/database.php';

class CallService
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = getDbConnection();
    }

    /**
     * Initiate a real-time WebRTC audio or video call between website users.
     */
    public function initiateCall(
        int $callerUserId,
        int $receiverUserId,
        string $callType = 'audio',
        string $contextType = 'blood_request',
        ?int $contextId = null
    ): array {
        if ($callerUserId <= 0 || $receiverUserId <= 0) {
            return ['success' => false, 'error' => 'Invalid participant identifiers.'];
        }

        if ($callerUserId === $receiverUserId) {
            return ['success' => false, 'error' => 'You cannot call yourself.'];
        }

        // 1. Validate caller
        $caller = $this->getUserDetails($callerUserId);
        if (!$caller) {
            return ['success' => false, 'error' => 'Caller account not found. Please log in again.'];
        }

        // 2. Validate receiver
        $receiver = $this->getUserDetails($receiverUserId);
        if (!$receiver) {
            return ['success' => false, 'error' => 'Receiver account not found or deactivated.'];
        }

        // 3. Check if caller already has an active ongoing call
        if ($this->isUserInCall($callerUserId)) {
            return ['success' => false, 'error' => 'You already have an active call session in progress.'];
        }

        // 4. Check if receiver is already busy in another call
        if ($this->isUserInCall($receiverUserId)) {
            return [
                'success'  => false,
                'is_busy'  => true,
                'error'    => htmlspecialchars($receiver['full_name'], ENT_QUOTES, 'UTF-8') . ' is currently in another call.',
                'receiver' => $receiver,
            ];
        }

        $callType = ($callType === 'video') ? 'video' : 'audio';
        $callId   = 'CALL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $callerRole   = $caller['role'] ?? 'user';
        $receiverRole = $receiver['role'] ?? 'donor';

        // 5. Insert call log record
        $stmt = $this->db->prepare(
            "INSERT INTO call_logs 
                (call_id, caller_user_id, receiver_user_id, caller_role, receiver_role, 
                 call_type, status, context_type, context_id, provider, started_at)
             VALUES (?, ?, ?, ?, ?, ?, 'calling', ?, ?, 'webrtc', NOW())"
        );
        if (!$stmt) {
            return ['success' => false, 'error' => 'Database error: ' . $this->db->error];
        }

        $stmt->bind_param(
            'siissssi',
            $callId,
            $callerUserId,
            $receiverUserId,
            $callerRole,
            $receiverRole,
            $callType,
            $contextType,
            $contextId
        );

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            return ['success' => false, 'error' => 'Failed to initialize call session: ' . $err];
        }
        $stmt->close();

        // 6. Set caller presence to in_call
        $this->setPresence($callerUserId, 'in_call', $callId);

        // 7. Push initial ring signal to receiver
        $this->pushSignal($callId, $callerUserId, $receiverUserId, 'ring', json_encode([
            'call_id'      => $callId,
            'caller_id'    => $callerUserId,
            'caller_name'  => $caller['full_name'],
            'blood_type'   => $caller['blood_type'],
            'role'         => $caller['role'],
            'call_type'    => $callType,
            'context_type' => $contextType,
            'context_id'   => $contextId,
        ]));

        // 8. Add notification to notifications table if present
        $this->createCallNotification($receiverUserId, $callerUserId, $callId, $callType, $caller['full_name']);

        return [
            'success'   => true,
            'call_id'   => $callId,
            'call_type' => $callType,
            'status'    => 'calling',
            'receiver'  => [
                'id'         => (int)$receiver['id'],
                'name'       => $receiver['full_name'],
                'blood_type' => $receiver['blood_type'],
                'role'       => $receiver['role'],
                'city'       => $receiver['city'],
            ],
        ];
    }

    /**
     * Receiver accepts the incoming call.
     */
    public function acceptCall(string $callId, int $userId): array
    {
        $call = $this->getCallById($callId);
        if (!$call) {
            return ['success' => false, 'error' => 'Call not found.'];
        }

        if ((int)$call['receiver_user_id'] !== $userId) {
            return ['success' => false, 'error' => 'Unauthorized to accept this call.'];
        }

        if (!in_array($call['status'], ['calling', 'ringing'], true)) {
            return ['success' => false, 'error' => 'Call is no longer active (' . $call['status'] . ').'];
        }

        // Update call status to accepted
        $stmt = $this->db->prepare(
            "UPDATE call_logs SET status = 'accepted', answered_at = NOW() WHERE call_id = ?"
        );
        $stmt->bind_param('s', $callId);
        $stmt->execute();
        $stmt->close();

        // Mark receiver as in_call
        $this->setPresence($userId, 'in_call', $callId);

        // Notify caller that call was accepted
        $this->pushSignal($callId, $userId, (int)$call['caller_user_id'], 'accept', json_encode([
            'call_id'  => $callId,
            'accepted' => true,
        ]));

        return [
            'success'   => true,
            'call_id'   => $callId,
            'status'    => 'accepted',
            'call_type' => $call['call_type'],
            'peer_id'   => (int)$call['caller_user_id'],
        ];
    }

    /**
     * Receiver rejects the incoming call or is busy.
     */
    public function rejectCall(string $callId, int $userId, string $reason = 'rejected'): array
    {
        $call = $this->getCallById($callId);
        if (!$call) {
            return ['success' => false, 'error' => 'Call not found.'];
        }

        if ((int)$call['receiver_user_id'] !== $userId) {
            return ['success' => false, 'error' => 'Unauthorized.'];
        }

        $newStatus = ($reason === 'busy') ? 'busy' : 'rejected';
        $stmt = $this->db->prepare(
            "UPDATE call_logs SET status = ?, ended_at = NOW(), end_reason = ? WHERE call_id = ?"
        );
        $stmt->bind_param('sss', $newStatus, $reason, $callId);
        $stmt->execute();
        $stmt->close();

        // Reset presence for caller
        $this->setPresence((int)$call['caller_user_id'], 'online');
        $this->setPresence($userId, 'online');

        // Notify caller of rejection/busy
        $this->pushSignal($callId, $userId, (int)$call['caller_user_id'], $newStatus, json_encode([
            'call_id' => $callId,
            'reason'  => $reason,
        ]));

        return ['success' => true, 'status' => $newStatus];
    }

    /**
     * Either participant ends the call.
     */
    public function endCall(string $callId, int $userId, string $reason = 'normal'): array
    {
        $call = $this->getCallById($callId);
        if (!$call) {
            return ['success' => false, 'error' => 'Call not found.'];
        }

        $callerId   = (int)$call['caller_user_id'];
        $receiverId = (int)$call['receiver_user_id'];

        if ($userId !== $callerId && $userId !== $receiverId) {
            return ['success' => false, 'error' => 'Unauthorized to end this call.'];
        }

        $peerId = ($userId === $callerId) ? $receiverId : $callerId;

        // Determine final status and duration
        $duration = 0;
        if (!empty($call['answered_at'])) {
            $answeredTs = strtotime($call['answered_at']);
            $duration   = max(0, time() - $answeredTs);
            $finalStatus = 'ended';
        } else {
            // Never answered: if caller hangs up, it's missed; if receiver declines, it's rejected
            $finalStatus = ($userId === $callerId) ? 'missed' : 'rejected';
        }

        $stmt = $this->db->prepare(
            "UPDATE call_logs 
             SET status = ?, ended_at = NOW(), duration_seconds = ?, end_reason = ? 
             WHERE call_id = ?"
        );
        $stmt->bind_param('siss', $finalStatus, $duration, $reason, $callId);
        $stmt->execute();
        $stmt->close();

        // Reset presence for both users
        $this->setPresence($callerId, 'online');
        $this->setPresence($receiverId, 'online');

        // Notify peer that call has ended
        $this->pushSignal($callId, $userId, $peerId, 'end', json_encode([
            'call_id'  => $callId,
            'duration' => $duration,
            'reason'   => $reason,
        ]));

        return [
            'success'          => true,
            'status'           => $finalStatus,
            'duration_seconds' => $duration,
        ];
    }

    /**
     * Push WebRTC signaling message (SDP offer, answer, ICE candidate).
     */
    public function sendSignal(string $callId, int $senderUserId, string $type, string $payload): array
    {
        $call = $this->getCallById($callId);
        if (!$call) {
            return ['success' => false, 'error' => 'Call not found.'];
        }

        $callerId   = (int)$call['caller_user_id'];
        $receiverId = (int)$call['receiver_user_id'];

        if ($senderUserId !== $callerId && $senderUserId !== $receiverId) {
            return ['success' => false, 'error' => 'Unauthorized sender.'];
        }

        $peerId = ($senderUserId === $callerId) ? $receiverId : $callerId;

        // If offer is received and call is still 'calling', update status to 'ringing'
        if ($type === 'offer' && $call['status'] === 'calling') {
            $safeCallId = $this->db->real_escape_string($callId);
            $this->db->query("UPDATE call_logs SET status = 'ringing' WHERE call_id = '{$safeCallId}'");
        }

        // If answer is received, mark status as 'connected'
        if ($type === 'answer') {
            $safeCallId = $this->db->real_escape_string($callId);
            $this->db->query("UPDATE call_logs SET status = 'connected' WHERE call_id = '{$safeCallId}'");
        }

        $this->pushSignal($callId, $senderUserId, $peerId, $type, $payload);

        return ['success' => true];
    }

    /**
     * Retrieve unread signals for the requesting user and mark them read.
     */
    public function getSignals(string $callId, int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, type, payload, sender_user_id, created_at 
             FROM call_signals 
             WHERE call_id = ? AND receiver_user_id = ? AND is_read = 0
             ORDER BY id ASC"
        );
        if (!$stmt) {
            return ['success' => false, 'error' => $this->db->error];
        }

        $stmt->bind_param('si', $callId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $signals = [];
        $idsToMark = [];

        while ($row = $res->fetch_assoc()) {
            $payloadParsed = json_decode($row['payload'], true);
            $signals[] = [
                'id'             => (int)$row['id'],
                'type'           => $row['type'],
                'payload'        => $payloadParsed !== null ? $payloadParsed : $row['payload'],
                'sender_user_id' => (int)$row['sender_user_id'],
                'created_at'     => $row['created_at'],
            ];
            $idsToMark[] = (int)$row['id'];
        }
        $stmt->close();

        // Mark retrieved signals as read
        if (!empty($idsToMark)) {
            $idList = implode(',', $idsToMark);
            $this->db->query("UPDATE call_signals SET is_read = 1 WHERE id IN ($idList)");
        }

        return ['success' => true, 'signals' => $signals];
    }

    /**
     * Heartbeat ping to update user presence and check for incoming calls.
     */
    public function heartbeat(int $userId): array
    {
        // 1. Update user presence
        $this->db->query(
            "INSERT INTO user_presence (user_id, status, last_seen) 
             VALUES ($userId, 'online', NOW())
             ON DUPLICATE KEY UPDATE 
                 last_seen = NOW(),
                 status = CASE WHEN status = 'in_call' THEN 'in_call' ELSE 'online' END"
        );

        // 2. Auto-expire unanswered calls older than 45 seconds
        $this->db->query(
            "UPDATE call_logs 
             SET status = 'missed', ended_at = NOW(), end_reason = 'no_answer' 
             WHERE status IN ('calling', 'ringing') 
               AND started_at < NOW() - INTERVAL 45 SECOND"
        );

        // 3. Reset presence for users whose calls were marked missed/ended
        $this->db->query(
            "UPDATE user_presence up
             LEFT JOIN call_logs cl ON cl.call_id = up.current_call_id
             SET up.status = 'online', up.current_call_id = NULL
             WHERE up.status = 'in_call' 
               AND (cl.status IS NULL OR cl.status IN ('ended', 'missed', 'rejected', 'busy', 'failed'))"
        );

        // 4. Check for an active incoming call directed to this user
        $stmt = $this->db->prepare(
            "SELECT cl.call_id, cl.call_type, cl.context_type, cl.context_id, cl.started_at,
                    u.id AS caller_id, u.full_name AS caller_name, u.blood_type, u.role, u.city
             FROM call_logs cl
             JOIN users u ON u.id = cl.caller_user_id
             WHERE cl.receiver_user_id = ? 
               AND cl.status IN ('calling', 'ringing')
               AND cl.started_at >= NOW() - INTERVAL 45 SECOND
             ORDER BY cl.id DESC LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $incoming = $res->fetch_assoc();
        $stmt->close();

        return [
            'success'       => true,
            'status'        => 'online',
            'has_incoming'  => (bool)$incoming,
            'incoming_call' => $incoming ? [
                'call_id'      => $incoming['call_id'],
                'call_type'    => $incoming['call_type'],
                'context_type' => $incoming['context_type'],
                'context_id'   => $incoming['context_id'] ? (int)$incoming['context_id'] : null,
                'caller'       => [
                    'id'         => (int)$incoming['caller_id'],
                    'name'       => $incoming['caller_name'],
                    'blood_type' => $incoming['blood_type'] ?? 'O+',
                    'role'       => $incoming['role'] ?? 'User',
                    'city'       => $incoming['city'] ?? '',
                ],
            ] : null,
        ];
    }

    /**
     * Get current status and duration of a call session.
     */
    public function getCallStatus(string $callId, int $userId): array
    {
        $call = $this->getCallById($callId);
        if (!$call) {
            return ['success' => false, 'error' => 'Call not found.'];
        }

        $callerId   = (int)$call['caller_user_id'];
        $receiverId = (int)$call['receiver_user_id'];

        if ($userId !== $callerId && $userId !== $receiverId) {
            return ['success' => false, 'error' => 'Unauthorized.'];
        }

        $duration = (int)$call['duration_seconds'];
        if ($call['status'] === 'connected' && !empty($call['answered_at'])) {
            $duration = max(0, time() - strtotime($call['answered_at']));
        }

        return [
            'success'          => true,
            'call_id'          => $call['call_id'],
            'status'           => $call['status'],
            'call_type'        => $call['call_type'],
            'duration_seconds' => $duration,
            'started_at'       => $call['started_at'],
            'answered_at'      => $call['answered_at'],
            'ended_at'         => $call['ended_at'],
            'end_reason'       => $call['end_reason'],
        ];
    }

    /**
     * Check presence for an array of user IDs.
     */
    public function getUsersPresence(array $userIds): array
    {
        if (empty($userIds)) return [];

        $sanitized = array_filter(array_map('intval', $userIds));
        if (empty($sanitized)) return [];

        $inList = implode(',', $sanitized);
        $res = $this->db->query(
            "SELECT user_id, status, last_seen,
                    CASE 
                        WHEN last_seen < NOW() - INTERVAL 45 SECOND THEN 'offline'
                        ELSE status
                    END AS current_presence
             FROM user_presence
             WHERE user_id IN ($inList)"
        );

        $presenceMap = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $presenceMap[(int)$row['user_id']] = $row['current_presence'];
            }
        }

        $result = [];
        foreach ($sanitized as $uid) {
            $result[$uid] = $presenceMap[$uid] ?? 'offline';
        }
        return $result;
    }

    /**
     * Fetch call history for a user.
     */
    public function getUserCallHistory(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT cl.id, cl.call_id, cl.call_type, cl.status, cl.started_at, cl.answered_at,
                    cl.ended_at, cl.duration_seconds, cl.end_reason, cl.context_type, cl.context_id,
                    cl.caller_user_id, cl.receiver_user_id,
                    u1.full_name AS caller_name, u1.blood_type AS caller_blood, u1.role AS caller_role,
                    u2.full_name AS receiver_name, u2.blood_type AS receiver_blood, u2.role AS receiver_role
             FROM call_logs cl
             JOIN users u1 ON u1.id = cl.caller_user_id
             JOIN users u2 ON u2.id = cl.receiver_user_id
             WHERE cl.caller_user_id = ? OR cl.receiver_user_id = ?
             ORDER BY cl.started_at DESC
             LIMIT ?"
        );
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('iii', $userId, $userId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $history = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $history;
    }

    // ==========================================================
    // Internal Helpers
    // ==========================================================

    private function getUserDetails(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, full_name, blood_type, city, role FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }

    private function getCallById(string $callId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM call_logs WHERE call_id = ? LIMIT 1"
        );
        $stmt->bind_param('s', $callId);
        $stmt->execute();
        $res = $stmt->get_result();
        $call = $res->fetch_assoc();
        $stmt->close();
        return $call ?: null;
    }

    private function isUserInCall(int $userId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM call_logs 
             WHERE (caller_user_id = ? OR receiver_user_id = ?)
               AND status IN ('calling', 'ringing', 'accepted', 'connected')
               AND started_at >= NOW() - INTERVAL 30 MINUTE
             LIMIT 1"
        );
        $stmt->bind_param('ii', $userId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $inCall = (bool)$res->fetch_assoc();
        $stmt->close();
        return $inCall;
    }

    private function setPresence(int $userId, string $status, ?string $callId = null): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO user_presence (user_id, status, current_call_id, last_seen) 
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
                 status = VALUES(status), 
                 current_call_id = VALUES(current_call_id), 
                 last_seen = NOW()"
        );
        $stmt->bind_param('iss', $userId, $status, $callId);
        $stmt->execute();
        $stmt->close();
    }

    private function pushSignal(string $callId, int $senderId, int $receiverId, string $type, string $payload): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO call_signals (call_id, sender_user_id, receiver_user_id, type, payload, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW())"
        );
        if ($stmt) {
            $stmt->bind_param('siiss', $callId, $senderId, $receiverId, $type, $payload);
            $stmt->execute();
            $stmt->close();
        }
    }

    private function createCallNotification(int $receiverUserId, int $callerUserId, string $callId, string $callType, string $callerName): void
    {
        $icon = ($callType === 'video') ? 'fa-video' : 'fa-phone';
        $title = "Incoming " . ucfirst($callType) . " Call";
        $message = "{$callerName} is calling you via BloodConnect in-website {$callType} call.";
        $stmt = $this->db->prepare(
            "INSERT INTO notifications (user_id, related_user_id, title, type, priority, icon, message, is_read, created_at)
             VALUES (?, ?, ?, 'call', 'high', ?, ?, 0, NOW())"
        );
        if ($stmt) {
            $stmt->bind_param('iisss', $receiverUserId, $callerUserId, $title, $icon, $message);
            $stmt->execute();
            $stmt->close();
        }
    }
}
