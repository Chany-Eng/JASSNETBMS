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
    'username' => 'jassnet012',
    'password' => 'p4_sm661',
    'api_url' => 'http://mshastra.com/sendsms_api.json.aspx'
];

// SMS Sender Class
class JASSnetSender {
    private $api_url;
    private $user;
    private $pass;

    public function __construct($url, $user, $pass) {
        $this->api_url = $url;
        $this->user = $user;
        $this->pass = $pass;
    }

    public function sendSMS($phone, $message, $sender = 'JASSNET') {
        $data = [
            'user' => $this->user,
            'pwd' => $this->pass,
            'senderid' => $sender,
            'mobileno' => $phone,
            'msgtext' => $message,
            'smstype' => '0'
        ];
        return $this->callAPI($data);
    }

    private function callAPI($data) {
        $ch = curl_init($this->api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}

// Instantiate sender globally for reuse
$smsSender = new JASSnetSender(
    $credentials['api_url'],
    $credentials['username'],
    $credentials['password']
);
