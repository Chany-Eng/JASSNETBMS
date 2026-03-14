<?php
// JASSNET SMS Gateway - Professional Interface
// File: jassnet_sms.php

// Configuration
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
    'api_url' => defined('SMS_API_URL') && is_string(SMS_API_URL) && trim(SMS_API_URL) !== '' ? trim(SMS_API_URL) : 'http://mshastra.com/sendsms_api.json.aspx'
];

// SMS Sender Class
class JASSnetSender {
    private $api_url;
    private $user;
    private $pass;
    private $timeout;

    public function __construct($url, $user, $pass, $timeout = 30) {
        $this->api_url = $url;
        $this->user = $user;
        $this->pass = $pass;
        $this->timeout = max(5, (int) $timeout);
    }

    public function sendSMS($phone, $message, $sender = 'JASSNET') {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return [
                'success' => false,
                'error' => 'invalid_phone',
                'phone' => $phone,
            ];
        }

        $message = trim((string) $message);
        if ($message === '') {
            return [
                'success' => false,
                'error' => 'empty_message',
                'phone' => $phone,
            ];
        }

        $data = [
            'user' => $this->user,
            'pwd' => $this->pass,
            'senderid' => $sender,
            'mobileno' => $phone,
            'msgtext' => $message,
            'smstype' => '0'
        ];

        $postResponse = $this->callAPI($data, 'POST');
        if ($postResponse['success']) {
            return $postResponse;
        }

        if (($postResponse['http_code'] ?? 0) === 200 && trim((string) ($postResponse['raw_response'] ?? '')) === '') {
            $getResponse = $this->callAPI($data, 'GET');
            $getResponse['fallback_from'] = 'POST';
            return $getResponse;
        }

        return $postResponse;
    }

    private function callAPI($data, $method = 'POST') {
        $query = http_build_query($data);
        $url = $method === 'GET' ? $this->api_url . '?' . $query : $this->api_url;
        $ch = curl_init($url);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ERMS-SMS/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json, text/plain, */*']);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = null;
        if (is_string($response) && trim($response) !== '') {
            $decoded = json_decode($response, true);
        }

        return [
            'success' => $curlError === '' && $httpCode >= 200 && $httpCode < 300,
            'method' => $method,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'raw_response' => is_string($response) ? $response : '',
            'data' => $decoded,
        ];
    }

    private function normalizePhone($phone) {
        $digits = preg_replace('/\D+/', '', trim((string) $phone));
        if ($digits === null || $digits === '') {
            return '';
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255' . substr($digits, 1);
        }

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
