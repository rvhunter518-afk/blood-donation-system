<?php
/**
 * POST /api/call/webhook.php
 *
 * Receives call status callbacks from telephony providers.
 * Updates the call_logs table with real-time call status.
 *
 * IMPORTANT:
 * - This endpoint must be publicly accessible (not behind localhost-only firewall)
 * - For local XAMPP testing, use ngrok: ngrok http 80
 *   Then update webhook_url in config/call.php with the ngrok URL
 *
 * Supported providers:
 *   Twilio  — Signs with X-Twilio-Signature header (HMAC-SHA1)
 *   Exotel  — Signs with X-Exotel-Signature header (HMAC-SHA256)
 *   Plivo   — Signs with X-Plivo-Signature header (HMAC-SHA256)
 *   Test    — No signature required (test mode only)
 *
 * Status field per provider:
 *   Twilio: CallStatus (queued|ringing|in-progress|completed|busy|failed|no-answer)
 *   Exotel: Status     (queued|ringing|in-progress|completed|busy|failed|no-answer)
 *   Plivo:  CallStatus (initiated|ringing|answered|completed|busy|failed|no-answer)
 */

// Webhooks MUST NOT require session — providers don't have our session cookie.
// Verification is done via cryptographic signature instead.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Read raw body (needed for signature verification before parsing)
$rawBody = file_get_contents('php://input');

// Parse POST data (providers send form-encoded or JSON)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $postData = json_decode($rawBody, true) ?? [];
} else {
    parse_str($rawBody, $postData);
    // Also include $_POST for providers that use multipart
    $postData = array_merge($postData, $_POST);
}

// Extract signature headers (varies by provider)
$twilioSig = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '';
$exotelSig = $_SERVER['HTTP_X_EXOTEL_SIGNATURE'] ?? '';
$plivoSig  = $_SERVER['HTTP_X_PLIVO_SIGNATURE']  ?? '';

// Determine signature to use
$signature = $twilioSig ?: $exotelSig ?: $plivoSig;

// Build the full request URL for signature verification
$scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$requestUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $_SERVER['REQUEST_URI'];

require_once __DIR__ . '/../../services/CallService.php';

try {
    $service = new CallService();
    $result  = $service->processWebhook($rawBody, $signature, $requestUrl, $postData);
} catch (Throwable $e) {
    error_log('[webhook.php] Exception: ' . $e->getMessage());
    // Return 200 to prevent provider from retrying (log the error internally)
    echo json_encode(['success' => false, 'error' => 'Internal error.']);
    exit;
}

// Providers expect a 200 OK even on soft errors
http_response_code(200);
echo json_encode($result);

