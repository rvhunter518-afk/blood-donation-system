<?php
/**
 * BaseProvider — Abstract telephony provider interface.
 *
 * Every concrete provider (Twilio, Exotel, Plivo, Placeholder)
 * must extend this class and implement all abstract methods.
 */
abstract class BaseProvider
{
    /** @var array Provider-specific config slice */
    protected array $cfg;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    /**
     * Initiate an outbound call that bridges caller ↔ receiver.
     *
     * @param string $callerPhone   E.164 caller number (+91XXXXXXXXXX)
     * @param string $receiverPhone E.164 receiver number
     * @param string $callId        Internal call session ID (CALL-YYYYMMDD-XXXX)
     * @param string $webhookUrl    URL for provider to POST status updates
     *
     * @return array {
     *   'success'          => bool,
     *   'provider_call_id' => string|null,  // Provider's SID/UUID
     *   'status'           => string,        // 'initiated'|'failed'
     *   'error'            => string|null,
     * }
     */
    abstract public function initiateCall(
        string $callerPhone,
        string $receiverPhone,
        string $callId,
        string $webhookUrl
    ): array;

    /**
     * End / cancel an in-progress call.
     *
     * @param string $providerCallId Provider's SID/UUID
     * @return bool true on success
     */
    abstract public function endCall(string $providerCallId): bool;

    /**
     * Verify that an incoming webhook request is authentic
     * (came from the real provider, not a spoofed request).
     *
     * @param string $rawBody    Raw POST body
     * @param string $signature  Value from provider's signature header
     * @param string $requestUrl Full webhook URL
     * @return bool
     */
    abstract public function verifyWebhookSignature(
        string $rawBody,
        string $signature,
        string $requestUrl
    ): bool;

    /**
     * Map provider-specific call status strings to BloodConnect's
     * unified status enum values.
     *
     * @param string $providerStatus Raw status string from provider webhook
     * @return string One of: initiated|ringing|answered|busy|rejected|no_answer|failed|completed|cancelled
     */
    abstract public function mapStatus(string $providerStatus): string;

    // -------------------------------------------------------
    // Shared helpers available to all providers
    // -------------------------------------------------------

    /**
     * Normalise a phone number to E.164.
     * Adds +91 prefix for 10-digit Indian numbers if no country code given.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        if (strlen($phone) === 10 && $phone[0] !== '+') {
            $phone = '+91' . $phone;
        } elseif (strlen($phone) > 10 && $phone[0] !== '+') {
            $phone = '+' . $phone;
        }
        return $phone;
    }

    /**
     * Validate E.164 format: +[1-3 digit country code][6-12 digits]
     */
    protected function isValidE164(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $phone);
    }

    /**
     * Make a cURL HTTP request.
     *
     * @return array ['code' => int, 'body' => string]
     */
    protected function httpRequest(
        string $url,
        string $method = 'POST',
        array  $postFields = [],
        array  $headers    = [],
        ?string $basicUser = null,
        ?string $basicPass = null
    ): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($basicUser !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicUser . ':' . $basicPass);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("[CallProvider] cURL error: $err");
        }

        return ['code' => $code, 'body' => (string) $body];
    }
}

