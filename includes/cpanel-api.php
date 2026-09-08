<?php
/**
 * includes/cpanel-api.php
 * کلاینت سبک برای صحبت با cPanel UAPI (Email::*) با استفاده از API Token.
 * این کار روی هاست اشتراکی معمولی هم جواب می‌دهد — نیازی به WHM/ریسلر نیست.
 * توکن را از داخل cPanel، بخش Security → Manage API Tokens بساز.
 */

if (!defined('RESA_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

class ResaCpanelApi
{
    private string $host;
    private int $port;
    private string $username;
    private string $token;

    public function __construct(string $host, int $port, string $username, string $token)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->token = $token;
    }

    public static function fromConfig(): self
    {
        return new self(CPANEL_HOST, CPANEL_PORT, CPANEL_USERNAME, CPANEL_API_TOKEN);
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->username !== '' && $this->token !== '';
    }

    /**
     * ساخت یک mailbox جدید روی دامنه‌ی مورد نظر.
     * @return array{ok:bool, message:string}
     */
    public function createMailbox(string $localPart, string $domain, string $password, int $quotaMb = 1024): array
    {
        return $this->call('Email', 'add_pop', [
            'email'    => $localPart,
            'domain'   => $domain,
            'password' => $password,
            'quota'    => $quotaMb, // مگابایت؛ 0 یعنی نامحدود
        ]);
    }

    /**
     * تغییر رمز عبور یک mailbox موجود.
     */
    public function changeMailboxPassword(string $localPart, string $domain, string $newPassword): array
    {
        return $this->call('Email', 'passwd_pop', [
            'email'    => $localPart,
            'domain'   => $domain,
            'password' => $newPassword,
        ]);
    }

    /**
     * حذف کامل یک mailbox (وقتی ادمین حذف می‌شود).
     */
    public function deleteMailbox(string $localPart, string $domain): array
    {
        return $this->call('Email', 'delete_pop', [
            'email'  => $localPart,
            'domain' => $domain,
        ]);
    }

    /**
     * فراخوانی خام UAPI با cURL + احراز هویت با API Token.
     * مستندات: https://api.docs.cpanel.net/cpanel/introduction/
     */
    private function call(string $module, string $function, array $params): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'تنظیمات اتصال به cPanel کامل نیست (فایل config.php را بررسی کنید).'];
        }

        $url = 'https://' . $this->host . ':' . $this->port
            . '/execute/' . $module . '/' . $function
            . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: cpanel ' . $this->username . ':' . $this->token],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'message' => 'خطا در اتصال به cPanel: ' . $curlErr];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از cPanel دریافت شد (کد HTTP: ' . $httpCode . ').'];
        }

        $status = $data['status'] ?? $data['result']['status'] ?? null;
        if ($status === 1 || $status === true) {
            return ['ok' => true, 'message' => 'انجام شد.'];
        }

        $errors = $data['errors'] ?? $data['result']['errors'] ?? null;
        $msg = is_array($errors) && count($errors) > 0 ? implode(' | ', $errors) : 'عملیات روی cPanel ناموفق بود.';
        return ['ok' => false, 'message' => $msg];
    }
}
