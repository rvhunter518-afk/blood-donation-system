<?php
require_once __DIR__ . '/BaseProvider.php';

/**
 * PlaceholderProvider (Test / Simulation Mode)
 *
 * Used when config 'mode' => 'test'.
 * Simulates a complete call lifecycle locally without any real API calls.
 * Safe to use on local XAMPP with no credentials or costs.
 *
 * Simulation behavior:
 * - Returns a fake provider_call_id (TEST-XXXXXXXX)
 * - The call lifecycle progresses automatically via status polling:
 *   initiated → ringing (5s) → answered (10s) → completed (60s)
 * - No webhook is sent (status is polled from DB timestamps instead)
 */
class PlaceholderProvider extends BaseProvider
{
    public function initiateCall(
        string $callerPhone,
        string $receiverPhone,
        string $callId,
        string $webhookUrl
    ): array {
        // Generate a fake provider call ID for tracking
        $fakeCallSid = 'TEST-' . strtoupper(bin2hex(random_bytes(6)));

        error_log("[TestMode] Simulated call initiated: {$callId} | SID: {$fakeCallSid}");
        error_log("[TestMode] Caller: {$callerPhone} → Receiver: {$receiverPhone}");

        return [
            'success'          => true,
            'provider_call_id' => $fakeCallSid,
            'status'           => 'initiated',
            'error'            => null,
        ];
    }

    /**
     * In test mode, "ending" a call just returns true.
     * The API endpoint updates the DB directly.
     */
    public function endCall(string $providerCallId): bool
    {
        error_log("[TestMode] Simulated endCall: {$providerCallId}");
        return true;
    }

    /**
     * Test mode does not use real webhooks.
     * The status API simulates progression based on time elapsed.
     * Signature verification always passes in test mode.
     */
    public function verifyWebhookSignature(
        string $rawBody,
        string $signature,
        string $requestUrl
    ): bool {
        // In test mode, we allow simulated webhook posts (from status.php time logic)
        return true;
    }

    /**
     * Map test status strings (same as unified enum).
     */
    public function mapStatus(string $providerStatus): string
    {
        // Test mode uses our own status strings directly
        $valid = ['initiated','ringing','answered','busy','rejected',
                  'no_answer','failed','completed','cancelled'];
        return in_array($providerStatus, $valid, true) ? $providerStatus : 'failed';
    }

    /**
     * Simulate status progression based on seconds elapsed since call started.
     * Called by status.php to advance test calls automatically.
     *
     * Timeline:
     *   0–4s   → initiated
     *   5–14s  → ringing
     *   15–89s → answered
     *   90s+   → completed
     */
    public static function simulateStatus(string $startedAt): string
    {
        $elapsed = time() - strtotime($startedAt);

        if ($elapsed < 5)  return 'initiated';
        if ($elapsed < 15) return 'ringing';
        if ($elapsed < 90) return 'answered';
        return 'completed';
    }
}

