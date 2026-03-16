<?php
// JASSNET SMS Gateway
// File: jassnet_sms.php

if (!defined('JASSNET_VERSION')) {
    define('JASSNET_VERSION', '2.0');
}
if (!defined('SENDER_ID')) {
    define('SENDER_ID', 'JASSNET');
}

// API Credentials
$credentials = [
    'username' => defined('SMS_API_USERNAME') ? SMS_API_USERNAME : 'jassnet012',
    'password' => defined('SMS_API_PASSWORD') ? SMS_API_PASSWORD : 'p4_sm661',
    'api_url'  => defined('SMS_API_URL') && is_string(SMS_API_URL) && trim(SMS_API_URL) !== ''
                    ? trim(SMS_API_URL)
                    : 'http://mshastra.com/sendsms_api_json.aspx',
];

// SMS Sender Class
class JASSnetSender
{
    private $api_url;
    private $user;
    private $pass;
    private $timeout;

    public function __construct($url, $user, $pass, $timeout = 30)
    {
        $this->api_url = $url;
        $this->user    = $user;
        $this->pass    = $pass;
        $this->timeout = max(5, (int) $timeout);
    }

    public function sendSMS($phone, $message, $sender = 'JASSNET')
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return ['success' => false, 'error' => 'invalid_phone', 'phone' => $phone];
        }

        $message = trim((string) $message);
        if ($message === '') {
            return ['success' => false, 'error' => 'empty_message', 'phone' => $phone];
        }

        // Build JSON payload as array-of-one-object (mshastra JSON API format)
        $payload = json_encode([
            [
                'user'     => $this->user,
                'pwd'      => $this->pass,
                'number'   => $phone,
                'msg'      => $message,
                'sender'   => $sender,
                'language' => 'English',
            ]
        ]);

        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JASSNET-SMS/2.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $rawResponse = is_string($response) ? $response : '';
        $decoded     = ($rawResponse !== '') ? json_decode($rawResponse, true) : null;

        // mshastra returns success when HTTP 200 and no curl error
        $success = ($curlError === '' && $httpCode >= 200 && $httpCode < 300);

        return [
            'success'      => $success,
            'http_code'    => $httpCode,
            'curl_error'   => $curlError,
            'raw_response' => $rawResponse,
            'data'         => $decoded,
        ];
    }

    private function normalizePhone($phone)
    {
        $digits = preg_replace('/\D+/', '', trim((string) $phone));
        if ($digits === null || $digits === '') {
            return '';
        }

        // Already full international format (255XXXXXXXXX)
        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        // Local format starting with 0 (0XXXXXXXXX)
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255' . substr($digits, 1);
        }

        // 9-digit format without leading 0 or country code
        if (strlen($digits) === 9) {
            return '255' . $digits;
        }

        return '';
    }
}

// Instantiate sender globally for reuse
$smsSender = new JASSnetSender(
    $credentials['api_url'],
    $credentials['username'],
    $credentials['password']
);
