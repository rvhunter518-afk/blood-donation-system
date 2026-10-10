<?php
/**
 * BloodConnect — Call System Configuration
 *
 * INSTRUCTIONS:
 * 1. Set 'mode' => 'test'        for local XAMPP development (no real calls, no cost).
 * 2. Set 'mode' => 'production'  when you have valid provider credentials.
 * 3. Fill in the credentials for your chosen provider before going to production.
 * 4. NEVER commit real credentials to version control.
 *
 * SUPPORTED PROVIDERS:
 *   twilio  — https://www.twilio.com  (global, India supported)
 *   exotel  — https://exotel.com      (India-first, DLT required)
 *   plivo   — https://www.plivo.com   (global, India supported)
 */
return [

    // -------------------------------------------------------
    // OPERATIONAL MODE
    // 'test'       → Simulate calls locally (no API calls, no cost)
    // 'production' → Use real telephony provider
    // -------------------------------------------------------
    'mode'    => 'test',

    // -------------------------------------------------------
    // ACTIVE PROVIDER  (only used when mode = 'production')
    // Options: 'twilio' | 'exotel' | 'plivo'
    // -------------------------------------------------------
    'provider' => 'twilio',

    // -------------------------------------------------------
    // MASTER SWITCH
    // -------------------------------------------------------
    'enabled' => true,

    // -------------------------------------------------------
    // TWILIO CREDENTIALS
    // Get from: https://console.twilio.com
    // -------------------------------------------------------
    'twilio' => [
        'account_sid'  => '',   // e.g. ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
        'auth_token'   => '',   // e.g. your_auth_token_here
        'from_number'  => '',   // e.g. +1XXXXXXXXXX (your Twilio number)
        // Webhook URL that Twilio will POST call status updates to.
        // Must be publicly accessible (use ngrok for local testing).
        'webhook_url'  => 'https://yourdomain.com/api/call/webhook.php',
    ],

    // -------------------------------------------------------
    // EXOTEL CREDENTIALS (India)
    // Get from: https://exotel.com/dashboard
    // Note: Requires DLT registration + KYC for Indian numbers.
    // -------------------------------------------------------
    'exotel' => [
        'api_key'      => '',   // Exotel API Key (SID)
        'api_token'    => '',   // Exotel API Token
        'subdomain'    => 'api.exotel.com',  // or api.in.exotel.com for India
        'from_number'  => '',   // Your Exotel ExoPhone/virtual number
        'app_id'       => '',   // Exotel App ID (callerId flow app)
        'webhook_url'  => 'https://yourdomain.com/api/call/webhook.php',
    ],

    // -------------------------------------------------------
    // PLIVO CREDENTIALS
    // Get from: https://console.plivo.com
    // -------------------------------------------------------
    'plivo' => [
        'auth_id'      => '',   // Plivo Auth ID
        'auth_token'   => '',   // Plivo Auth Token
        'from_number'  => '',   // Your Plivo number (e.g. +91XXXXXXXXXX)
        'webhook_url'  => 'https://yourdomain.com/api/call/webhook.php',
    ],

    // -------------------------------------------------------
    // RATE LIMITING
    // Max call attempts per user within the time window.
    // -------------------------------------------------------
    'rate_limit' => [
        'max_calls'      => 5,   // max attempts
        'window_minutes' => 10,  // within N minutes
    ],

    // -------------------------------------------------------
    // CALL COOLDOWN
    // Minimum seconds between successive calls to the same receiver.
    // -------------------------------------------------------
    'cooldown_seconds' => 60,

    // -------------------------------------------------------
    // STATUS POLLING INTERVAL (seconds) — used by frontend JS
    // -------------------------------------------------------
    'poll_interval_seconds' => 3,

    // -------------------------------------------------------
    // DB CONNECTION (mirrors existing project config)
    // -------------------------------------------------------
    'db' => [
        'host'    => 'localhost',
        'dbname'  => 'blood_connect',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
];

