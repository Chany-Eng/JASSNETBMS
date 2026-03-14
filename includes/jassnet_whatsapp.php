<?php

if (!defined('WHATSAPP_API_VERSION')) {
    define('WHATSAPP_API_VERSION', 'v22.0');
}

if (!defined('WHATSAPP_API_BASE_URL')) {
    define('WHATSAPP_API_BASE_URL', 'https://graph.facebook.com');
}

if (!defined('WHATSAPP_ENABLED')) {
    define('WHATSAPP_ENABLED', false);
}

if (!defined('WHATSAPP_MESSAGE_MODE')) {
    define('WHATSAPP_MESSAGE_MODE', 'template');
}

if (!defined('WHATSAPP_DEFAULT_TEMPLATE')) {
    define('WHATSAPP_DEFAULT_TEMPLATE', 'hello_world');
}

if (!defined('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE')) {
    define('WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE', 'en_US');
}

if (!defined('WHATSAPP_OTP_TEMPLATE')) {
    define('WHATSAPP_OTP_TEMPLATE', '');
}

if (!defined('WHATSAPP_OTP_TEMPLATE_LANGUAGE')) {
    define('WHATSAPP_OTP_TEMPLATE_LANGUAGE', 'en_US');
}

class JASSnetWhatsAppSender
{
    private string $baseUrl;
    private string $version;
    private string $token;
    private string $phoneNumberId;
    private int $timeout;

    public function __construct(string $baseUrl, string $version, string $token, string $phoneNumberId, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->version = trim($version) !== '' ? trim($version) : 'v22.0';
        $this->token = trim($token);
        $this->phoneNumberId = trim($phoneNumberId);
        $this->timeout = max(5, $timeout);
    }

    public function isConfigured(): bool
    {
        if (!WHATSAPP_ENABLED) {
            return false;
        }

        if ($this->token === '' || $this->phoneNumberId === '') {
            return false;
        }

        return stripos($this->token, 'ACCESS_TOKEN_HERE') === false;
    }

    public function sendTextMessage(string $phone, string $message): array
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return [
                'success' => false,
                'error' => 'invalid_phone',
                'phone' => $phone,
            ];
        }

        $message = trim($message);
        if ($message === '') {
            return [
                'success' => false,
                'error' => 'empty_message',
                'phone' => $phone,
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'whatsapp_not_configured',
                'phone' => $phone,
            ];
        }

        if (strtolower((string) WHATSAPP_MESSAGE_MODE) === 'template') {
            return $this->sendTemplateMessage($phone, WHATSAPP_DEFAULT_TEMPLATE, WHATSAPP_DEFAULT_TEMPLATE_LANGUAGE);
        }

        return $this->sendPayload($phone, [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ]);
    }

    public function sendTemplateMessage(string $phone, string $templateName, string $languageCode = 'en_US', array $components = []): array
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return [
                'success' => false,
                'error' => 'invalid_phone',
                'phone' => $phone,
            ];
        }

        $templateName = trim($templateName);
        if ($templateName === '') {
            return [
                'success' => false,
                'error' => 'empty_template_name',
                'phone' => $phone,
            ];
        }

        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'whatsapp_not_configured',
                'phone' => $phone,
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => trim($languageCode) !== '' ? trim($languageCode) : 'en_US',
                ],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        return $this->sendPayload($phone, $payload);
    }

    public function sendOtpTemplateMessage(string $phone, string $otpCode, int $expiryMinutes): array
    {
        $templateName = trim((string) WHATSAPP_OTP_TEMPLATE);
        if ($templateName === '') {
            return [
                'success' => false,
                'error' => 'otp_template_not_configured',
                'phone' => $this->normalizePhone($phone),
            ];
        }

        return $this->sendTemplateMessage(
            $phone,
            $templateName,
            WHATSAPP_OTP_TEMPLATE_LANGUAGE,
            [
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'text' => $otpCode,
                        ],
                        [
                            'type' => 'text',
                            'text' => (string) $expiryMinutes,
                        ],
                    ],
                ],
            ]
        );
    }

    private function sendPayload(string $phone, array $payload): array
    {
        $url = $this->baseUrl . '/' . $this->version . '/' . rawurlencode($this->phoneNumberId) . '/messages';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token,
            'Accept: application/json',
        ]);

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
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'raw_response' => is_string($response) ? $response : '',
            'data' => $decoded,
            'phone' => $phone,
            'mode' => (string) ($payload['type'] ?? ''),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', trim($phone));
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

$whatsAppSender = new JASSnetWhatsAppSender(
    defined('WHATSAPP_API_BASE_URL') ? WHATSAPP_API_BASE_URL : 'https://graph.facebook.com',
    defined('WHATSAPP_API_VERSION') ? WHATSAPP_API_VERSION : 'v22.0',
    defined('WHATSAPP_API_TOKEN') ? WHATSAPP_API_TOKEN : '',
    defined('WHATSAPP_PHONE_NUMBER_ID') ? WHATSAPP_PHONE_NUMBER_ID : ''
);