<?php
require_once __DIR__ . '/BaseProvider.php';

/**
 * TwilioProvider
 *
 * Implements click-to-call using Twilio's REST API.
 *
 * HOW IT WORKS:
 * 1. We call the caller's phone via Twilio REST API.
 * 2. When caller picks up, Twilio executes TwiML that dials the receiver.
 * 3. Both parties speak through Twilio — neither sees the other's real number.
 * 4. Twilio posts status callbacks to /api/call/webhook.php.
 *
 * REQUIREMENTS:
 * - Twilio account with a verified/purchased number
 * - Both caller and receiver numbers must be verified (Trial account)
 *   OR you must upgrade to a paid account for unrestricted calling
 * - For Indian numbers (+91): Twilio supports India but may require
 *   A2P approval for transactional calling use cases
 *
 * DOCS: https://www.twilio.com/docs/voice/api/call-resource
 */
class TwilioProvider extends BaseProvider
{
    private const API_BASE = 'https://api.twilio.com/2010-04-01';

    /**
     * Initiate a call from caller → receiver via Twilio.
     *
     * Twilio calls the RECEIVER first, then connects to the caller.
     * The caller sees Twilio's number, not the receiver's real number.
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

        $accountSid  = $this->cfg['account_sid'];
        $authToken   = $this->cfg['auth_token'];
        $fromNumber  = $this->cfg['from_number'];

        // TwiML: when receiver picks up, dial the caller back
        // We encode the TwiML as a URL parameter (Twilio accepts inline TwiML via 'Twiml' param)
        $twiml = '<Response><Dial callerId="' . htmlspecialchars($fromNumber, ENT_XML1) . '">'
               . '<Number>' . htmlspecialchars($callerPhone, ENT_XML1) . '</Number>'
               . '</Dial></Response>';

        $apiUrl  = self::API_BASE . "/Accounts/{$accountSid}/Calls.json";
        $payload = [
            'To'                  => $receiverPhone,
            'From'                => $fromNumber,
            'Twiml'               => $twiml,
            'StatusCallback'      => $webhookUrl . '?call_id=' . urlencode($callId),
            'StatusCallbackEvent' => 'initiated ringing answered completed',
            'StatusCallbackMethod'=> 'POST',
            'Timeout'             => '30',
            'MachineDetection'    => 'Disable',
        ];

        $response = $this->httpRequest(
            $apiUrl,
            'POST',
            $payload,
            ['Content-Type: application/x-www-form-urlencoded'],
            $accountSid,
            $authToken
        );

        $data = json_decode($response['body'], true);

        if ($response['code'] === 201 && !empty($data['sid'])) {
            return [
                'success'          => true,
                'provider_call_id' => $data['sid'],
                'status'           => 'initiated',
                'error'            => null,
            ];
        }

        $errorMsg = $data['message'] ?? ('Twilio API error. HTTP ' . $response['code']);
        error_log("[TwilioProvider] initiateCall failed: " . $errorMsg);

        return [
            'success'          => false,
            'provider_call_id' => null,
            'status'           => 'failed',
            'error'            => $errorMsg,
        ];
    }

    /**
     * End an in-progress call by updating its status to 'completed'.
     */
    public function endCall(string $providerCallId): bool
    {
        $accountSid = $this->cfg['account_sid'];
        $authToken  = $this->cfg['auth_token'];
        $apiUrl     = self::API_BASE . "/Accounts/{$accountSid}/Calls/{$providerCallId}.json";

        $response = $this->httpRequest(
            $apiUrl,
            'POST',
            ['Status' => 'completed'],
            [],
            $accountSid,
            $authToken
        );

        return $response['code'] === 200;
    }

    /**
     * Verify Twilio webhook signature.
     * Twilio signs with HMAC-SHA1 of (URL + sorted POST params).
     */
    public function verifyWebhookSignature(
        string $rawBody,
        string $signature,
        string $requestUrl
    ): bool {
        if (empty($this->cfg['auth_token'])) {
            return false;
        }

        parse_str($rawBody, $params);
        ksort($params);
        $data = $requestUrl;
        foreach ($params as $key => $val) {
            $data .= $key . $val;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $this->cfg['auth_token'], true));
        return hash_equals($expected, $signature);
    }

    /**
     * Map Twilio call status → BloodConnect unified status.
     */
    public function mapStatus(string $providerStatus): string
    {
        return match (strtolower($providerStatus)) {
            'queued', 'initiated' => 'initiated',
            'ringing'             => 'ringing',
            'in-progress'         => 'answered',
            'completed'           => 'completed',
            'busy'                => 'busy',
            'failed'              => 'failed',
            'no-answer'           => 'no_answer',
            'canceled'            => 'cancelled',
            default               => 'failed',
        };
    }
}

