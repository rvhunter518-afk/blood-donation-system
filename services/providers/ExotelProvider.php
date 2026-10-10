<?php
require_once __DIR__ . '/BaseProvider.php';

/**
 * ExotelProvider
 *
 * Implements click-to-call using Exotel's Connect API.
 *
 * HOW IT WORKS:
 * Exotel's "Connect" (click-to-call) API connects two phone numbers
 * through an ExoPhone (virtual number). Neither party sees the other's
 * real number — they see Exotel's virtual number.
 *
 * REQUIREMENTS:
 * - Exotel account (https://exotel.com)
 * - Purchased ExoPhone (virtual Indian number)
 * - DLT registration required for transactional voice (as per TRAI)
 * - KYC verification
 * - "Connect" app created in Exotel dashboard
 *
 * DOCS: https://developer.exotel.com/api/click-to-call
 */
class ExotelProvider extends BaseProvider
{
    /**
     * Initiate click-to-call: Exotel calls FROM first, then connects TO.
     * The receiver (To) gets the call from Exotel's virtual number.
     */
    public function initiateCall(
        string $callerPhone,
        string $receiverPhone,
        string $callId,
        string $webhookUrl
    ): array {
        $callerPhone   = $this->normalizePhone($callerPhone);
        $receiverPhone = $this->normalizePhone($receiverPhone);

        if (!$this->isValidE164($callerPhone) || !$this->isValidE164($receiverPhone)) {
            return [
                'success'          => false,
                'provider_call_id' => null,
                'status'           => 'failed',
                'error'            => 'Invalid phone number format.',
            ];
        }

        $apiKey    = $this->cfg['api_key'];
        $apiToken  = $this->cfg['api_token'];
        $subdomain = $this->cfg['subdomain'] ?? 'api.exotel.com';
        $fromPhone = $this->cfg['from_number'];
        $appId     = $this->cfg['app_id'] ?? '';

        // Remove leading + for Exotel (uses local format or 00 prefix)
        $callerLocal   = ltrim($callerPhone, '+');
        $receiverLocal = ltrim($receiverPhone, '+');
        $fromLocal     = ltrim($fromPhone, '+');

        $apiUrl = "https://{$subdomain}/v1/Accounts/{$apiKey}/Calls/connect.json";

        $payload = [
            'From'            => $callerLocal,    // Who gets called first
            'To'              => $receiverLocal,  // Who gets connected after
            'CallerId'        => $fromLocal,      // Exotel virtual number (masks both sides)
            'StatusCallback'  => $webhookUrl . '?call_id=' . urlencode($callId),
            'TimeLimit'       => '3600',          // Max call duration (seconds)
            'TimeOut'         => '30',            // Ring timeout
        ];

        if (!empty($appId)) {
            $payload['AppId'] = $appId;
        }

        $response = $this->httpRequest(
            $apiUrl,
            'POST',
            $payload,
            [],
            $apiKey,
            $apiToken
        );

        $data = json_decode($response['body'], true);

        if (
            $response['code'] === 200
            && isset($data['Call']['Sid'])
        ) {
            return [
                'success'          => true,
                'provider_call_id' => $data['Call']['Sid'],
                'status'           => 'initiated',
                'error'            => null,
            ];
        }

        $errorMsg = $data['RestException']['Message']
            ?? $data['message']
            ?? ('Exotel API error. HTTP ' . $response['code']);
        error_log("[ExotelProvider] initiateCall failed: " . $errorMsg);

        return [
            'success'          => false,
            'provider_call_id' => null,
            'status'           => 'failed',
            'error'            => $errorMsg,
        ];
    }

    /**
     * End a call via Exotel API.
     */
    public function endCall(string $providerCallId): bool
    {
        $apiKey    = $this->cfg['api_key'];
        $apiToken  = $this->cfg['api_token'];
        $subdomain = $this->cfg['subdomain'] ?? 'api.exotel.com';

        $apiUrl = "https://{$subdomain}/v1/Accounts/{$apiKey}/Calls/{$providerCallId}.json";

        $response = $this->httpRequest(
            $apiUrl,
            'POST',
            ['Status' => 'completed'],
            [],
            $apiKey,
            $apiToken
        );

        return $response['code'] === 200;
    }

    /**
     * Verify Exotel webhook signature.
     * Exotel sends an HMAC-SHA256 signature in X-Exotel-Signature header.
     */
    public function verifyWebhookSignature(
        string $rawBody,
        string $signature,
        string $requestUrl
    ): bool {
        if (empty($this->cfg['api_token'])) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->cfg['api_token']);
        return hash_equals($expected, strtolower($signature));
    }

    /**
     * Map Exotel call status → BloodConnect unified status.
     */
    public function mapStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'queued'      => 'initiated',
            'ringing'     => 'ringing',
            'in-progress' => 'answered',
            'completed'   => 'completed',
            'busy'        => 'busy',
            'failed'      => 'failed',
            'no-answer'   => 'no_answer',
            'canceled'    => 'cancelled',
            default       => 'failed',
        };
    }
}

