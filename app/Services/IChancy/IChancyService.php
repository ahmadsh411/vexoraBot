<?php

namespace App\Services\IChancy;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\ErrorMessages;

class IChancyService
{
    private string $baseUrl;
    private string $appKey;
    private string $username;
    private string $password;

    private const CACHE_KEY = 'ichancy_session_cookie';
    private const CACHE_TTL = 1800; // 30 دقيقة

    // ⚙️ إعدادات Retry و Timeout
    private const HTTP_TIMEOUT         = 30;  // ثانية
    private const HTTP_MAX_ATTEMPTS    = 3;   // عدد المحاولات
    private const HTTP_BACKOFF_BASE    = 1;   // ثواني (1s, 2s, 4s)

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('services.ichancy.base_url'), '/');
        $this->appKey   = config('services.ichancy.app_key');
        $this->username = config('services.ichancy.agent_username');
        $this->password = config('services.ichancy.agent_password');
    }

    // ============================================================
    //  🎭 Headers موحّدة
    // ============================================================
    private function headers(array $extra = []): array
    {
        $base = [
            'accept'         => 'application/json',
            'content-type'   => 'application/json',
            'cf-app-content' => $this->appKey,
            'origin'         => $this->baseUrl,
            'referer'        => $this->baseUrl . '/',
            'user-agent'     => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];

        return array_merge($base, $extra);
    }

    // ============================================================
    //  🔄 HTTP with Retry — يحل مشكلة timeout
    // ============================================================
    private function httpWithRetry(
        string $method,
        string $url,
        array $data,
        array $headers,
        ?int $maxAttempts = null,
    ): ?\Illuminate\Http\Client\Response {
        $maxAttempts = $maxAttempts ?? self::HTTP_MAX_ATTEMPTS;
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $method === 'GET'
                    ? Http::timeout(self::HTTP_TIMEOUT)->withHeaders($headers)->get($url, $data)
                    : Http::timeout(self::HTTP_TIMEOUT)->withHeaders($headers)->post($url, $data);

                // ✅ إذا وصل رد (حتى لو 4xx/5xx)، لا نعيد المحاولة
                //    لأن هذه مسألة منطقية، ليست شبكية
                return $response;
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // ❌ فشل شبكي (timeout, DNS, connection refused)
                $lastError = $e;

                Log::warning("IChancy HTTP attempt {$attempt}/{$maxAttempts} failed", [
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxAttempts) {
                    // Backoff تصاعدي: 1s, 2s, 4s
                    $sleepSeconds = self::HTTP_BACKOFF_BASE * pow(2, $attempt - 1);
                    sleep((int) $sleepSeconds);
                }
            } catch (\Throwable $e) {
                // ❌ خطأ آخر (bug, type error, إلخ)
                $lastError = $e;

                Log::error("IChancy HTTP attempt {$attempt}/{$maxAttempts} exception", [
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);

                break; // لا نعيد المحاولة لأخطاء غير شبكية
            }
        }

        Log::error('IChancy HTTP all attempts failed', [
            'url'   => $url,
            'error' => $lastError?->getMessage(),
        ]);

        return null;
    }

    // ============================================================
    //  🔐 تسجيل الدخول
    // ============================================================
    public function signIn(): bool
    {
        try {
            $response = $this->httpWithRetry(
                'POST',
                $this->baseUrl . '/global/api/User/signIn',
                [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
                $this->headers(),
            );

            if (! $response) {
                Log::error('IChancy signIn: no response after retries');
                return false;
            }

            if (! $response->successful()) {
                Log::error('IChancy signIn failed', [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 300),
                ]);
                return false;
            }

            $data = $response->json();

            if (! ($data['status'] ?? false)) {
                Log::error('IChancy signIn: status=false', ['data' => $data]);
                return false;
            }

            $cookies = $response->cookies();
            $cookieString = $this->buildCookieString($cookies);

            Cache::put(self::CACHE_KEY, $cookieString, self::CACHE_TTL);

            Log::info('IChancy signIn: success', [
                'cookie_length' => strlen($cookieString),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('IChancy signIn exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ============================================================
    //  🍪 بناء Cookie String
    // ============================================================
    private function buildCookieString($cookieJar): string
    {
        $parts = [];

        foreach ($cookieJar as $cookie) {
            $name  = $cookie->getName();
            $value = $cookie->getValue();
            if ($name && $value) {
                $parts[] = $name . '=' . $value;
            }
        }

        $parts[] = 'languageCode=ar_IQ';
        $parts[] = 'language=Arabic';

        return implode('; ', $parts);
    }

    // ============================================================
    //  📡 GET / POST
    // ============================================================
    public function get(string $endpoint, array $params = []): ?array
    {
        return $this->request('GET', $endpoint, $params);
    }

    public function post(string $endpoint, array $data = []): ?array
    {
        return $this->request('POST', $endpoint, $data);
    }

    // ============================================================
    //  🔧 Request — مع Rate Limit + Retry + Timeout
    // ============================================================
    private function request(string $method, string $endpoint, array $data = []): ?array
    {
        // ✅ 1. تأخير بسيط بين الطلبات (يمنع Rate Limit)
        $lastRequest = Cache::get('ichancy_last_request_at', 0);
        $timeSince   = microtime(true) - $lastRequest;

        if ($timeSince < 0.3) {
            usleep((int) ((0.3 - $timeSince) * 1_000_000));
        }

        Cache::put('ichancy_last_request_at', microtime(true), 60);

        // ✅ 2. تأكد من الـ session
        if (! Cache::has(self::CACHE_KEY)) {
            if (! $this->signIn()) {
                return null;
            }
        }

        $cookie = Cache::get(self::CACHE_KEY);
        $url = $this->baseUrl . '/global/api/' . ltrim($endpoint, '/');

        try {
            $headers = $this->headers(['cookie' => $cookie]);

            // ✅ 3. الطلب مع Retry
            $response = $this->httpWithRetry($method, $url, $data, $headers);

            if (! $response) {
                return null;
            }

            // ✅ 4. إذا الجلسة انتهت أو Cloudflare حجب
            if (in_array($response->status(), [401, 403], true)) {
                Log::info('IChancy session expired or blocked, retrying', [
                    'status' => $response->status(),
                    'url'    => $url,
                ]);

                Cache::forget(self::CACHE_KEY);

                sleep(1);

                if (! $this->signIn()) {
                    return null;
                }

                $cookie = Cache::get(self::CACHE_KEY);
                $headers['cookie'] = $cookie;

                $response = $this->httpWithRetry($method, $url, $data, $headers);

                if (! $response) {
                    return null;
                }
            }

            if (! $response->successful()) {
                Log::warning('IChancy request failed', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 300),
                ]);
                return null;
            }

            // ✅ 5. تأكد إنه JSON (Cloudflare يرجّع HTML أحياناً)
            $contentType = $response->header('Content-Type');

            if ($contentType && ! str_contains($contentType, 'json')) {
                Log::warning('IChancy returned non-JSON (Cloudflare block?)', [
                    'url'          => $url,
                    'content_type' => $contentType,
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error('IChancy request exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ============================================================
    //  💰 رصيد الوكيل
    // ============================================================
    public function getAgentWallet(): ?array
    {
        return $this->post('Agent/getAgentWallet');
    }

    // ============================================================
    //  💰 كل محافظ الوكيل
    // ============================================================
    public function getAgentAllWallets(): ?array
    {
        return $this->post('Agent/getAgentAllWallets');
    }

    // ============================================================
    //  👥 قائمة اللاعبين
    // ============================================================
    public function getPlayers(int $start = 0, int $limit = 25, array $filter = []): ?array
    {
        return $this->post('Player/getPlayersForCurrentAgent', [
            'start'  => $start,
            'limit'  => $limit,
            'filter' => empty($filter) ? new \stdClass() : $filter,
        ]);
    }

    // ============================================================
    //  🔍 بحث عن لاعب بالاسم
    //  (مع Pagination — يبحث في كل الصفحات حتى 5000 لاعب)
    // ============================================================
    public function findPlayerByUsername(string $username): ?array
    {
        $targetLower = strtolower($username);
        $pageSize    = 100;   // ✅ قللنا من 500 إلى 100 لتسريع الطلب
        $maxPages    = 50;    // حد أقصى: 50 صفحة × 100 = 5000 لاعب
        $start       = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $result = $this->post('Player/getPlayersForCurrentAgent', [
                'start'  => $start,
                'limit'  => $pageSize,
                'filter' => new \stdClass(),
            ]);

            if (! $result || ! ($result['status'] ?? false)) {
                return null;
            }

            $records = $result['result']['records'] ?? [];

            // لا توجد سجلات → انتهت القائمة
            if (empty($records)) {
                return null;
            }

            // ✅ ابحث في هذه الصفحة
            foreach ($records as $p) {
                if (
                    isset($p['username'])
                    && strtolower($p['username']) === $targetLower
                ) {
                    return $p;
                }
            }

            // إذا عدد السجلات < pageSize → هذه آخر صفحة
            if (count($records) < $pageSize) {
                return null;
            }

            $start += $pageSize;
        }

        return null;
    }

    // ============================================================
    //  💰 رصيد لاعب
    // ============================================================
    public function getPlayerBalance(string $playerId): ?float
    {
        $result = $this->post('Player/getPlayerBalanceById', [
            'playerId' => $playerId,
        ]);

        if (! $result || ! ($result['status'] ?? false)) {
            return null;
        }

        $balances = $result['result'] ?? [];

        // ✅ إذا ما فيه أرصدة → 0 (مو null)
        if (empty($balances)) {
            return 0.0;
        }

        foreach ($balances as $b) {
            if (($b['main'] ?? false) === true) {
                return (float) $b['balance'];
            }
        }

        // ✅ إذا ما فيه "main" → خذ أول واحد
        return (float) ($balances[0]['balance'] ?? 0);
    }

    // ============================================================
    //  ➕ تسجيل لاعب جديد
    // ============================================================
    public function registerPlayer(
        string $login,
        string $password,
        ?string $email = null,
        string $currency = 'NSP',
    ): ?array {
        $affiliateId = $this->getAffiliateId();

        if (! $affiliateId) {
            Log::error('IChancy registerPlayer: cannot determine affiliateId');
            return null;
        }

        if (! $email) {
            $email = $this->generatePlayerEmail($login);
        }

        return $this->post('Player/registerPlayer', [
            'player' => [
                'login'    => $login,
                'password' => $password,
                'email'    => $email,
                'parentId' => (string) $affiliateId,
            ],
        ]);
    }

    // ============================================================
    //  ➕ تسجيل لاعب + جلب بياناته (مع Retry)
    // ============================================================
    public function registerAndGetPlayer(
        string $login,
        string $password,
        ?string $email = null,
    ): ?array {
        // ✅ 1. سجّل اللاعب
        $registerResult = $this->registerPlayer($login, $password, $email);

        if (! $registerResult || ! ($registerResult['status'] ?? false)) {
            Log::warning('IChancy registerAndGetPlayer: registration failed', [
                'login'  => $login,
                'result' => $registerResult,
            ]);
            return null;
        }

        // ✅ 2. ابحث عن اللاعب (مع محاولات متعددة — لأن السيرفر قد يتأخر)
        $maxFindAttempts = 3;
        $player = null;

        for ($attempt = 1; $attempt <= $maxFindAttempts; $attempt++) {
            // انتظر قبل كل محاولة: 2s, 4s, 6s
            sleep(2 * $attempt);

            $player = $this->findPlayerByUsername($login);

            if ($player) {
                Log::info("IChancy registerAndGetPlayer: found on attempt {$attempt}");
                break;
            }

            Log::warning("IChancy registerAndGetPlayer: not found, attempt {$attempt}/{$maxFindAttempts}", [
                'login' => $login,
            ]);
        }

        if (! $player) {
            Log::warning('IChancy registerAndGetPlayer: player not found after all attempts', [
                'login' => $login,
            ]);
            return null;
        }

        Log::info('IChancy registerAndGetPlayer: success', [
            'login'     => $login,
            'player_id' => $player['playerId'] ?? null,
        ]);

        return $player;
    }

    // ============================================================
    //  📧 توليد إيميل تلقائي
    // ============================================================
    private function generatePlayerEmail(string $login): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9._-]/', '', $login);
        $clean = strtolower($clean);

        $domain = config('services.ichancy.email_domain', 'vexora.bot');

        return $clean . '@' . $domain;
    }

    // ============================================================
    //  🆔 جلب affiliateId
    // ============================================================
    private function getAffiliateId(): ?string
    {
        $cached = Cache::get('ichancy_affiliate_id');
        if ($cached) {
            return $cached;
        }

        $result = $this->post('Affiliate/getUsersList', [
            'start'  => 0,
            'limit'  => 1,
            'filter' => new \stdClass(),
        ]);

        if (! $result || ! ($result['status'] ?? false)) {
            return null;
        }

        $records = $result['result']['records'] ?? [];
        $affiliateId = $records[0]['affiliateId'] ?? null;

        if ($affiliateId) {
            Cache::put('ichancy_affiliate_id', $affiliateId, now()->addHours(6));
        }

        return $affiliateId ? (string) $affiliateId : null;
    }

    // ============================================================
    //  📥 إيداع للاعب (شحن)
    // ============================================================
    public function depositToPlayer(
        string $playerId,
        float $amount,
        string $currency = 'NSP',
        ?string $note = null,
    ): ?array {
        return $this->post('Player/depositToPlayer', [
            'playerId' => $playerId,
            'amount'   => $amount,
            'currency' => $currency,
            'note'     => $note ?? 'شحن من البوت',
        ]);
    }

    // ============================================================
    //  📤 سحب من اللاعب
    // ============================================================
    public function withdrawFromPlayer(
        string $playerId,
        float $amount,
        string $currency = 'NSP',
        ?string $note = null,
    ): ?array {
        return $this->post('Player/withdrawFromPlayer', [
            'playerId' => $playerId,
            'amount'   => $amount,
            'currency' => $currency,
            'note'     => $note ?? 'سحب من البوت',
        ]);
    }

    // ============================================================
    //  🔑 تغيير كلمة مرور لاعب
    // ============================================================
    public function changePlayerPassword(string $playerId, string $newPassword): ?array
    {
        return $this->post('Player/changePlayerPassword', [
            'playerId' => $playerId,
            'password' => $newPassword,
        ]);
    }

    // ============================================================
    //  🗑 حذف لاعب
    // ============================================================
    public function deletePlayer(string $playerId): ?array
    {
        return $this->post('Player/deletePlayer', [
            'playerId' => $playerId,
        ]);
    }

    // ============================================================
    //  ♻️ استرجاع لاعب
    // ============================================================
    public function restorePlayer(string $playerId): ?array
    {
        return $this->post('Player/restorePlayer', [
            'playerId' => $playerId,
        ]);
    }

    // ============================================================
    //  🧪 اختبار endpoints
    // ============================================================
    public function testEndpoints(): array
    {
        $endpoints = [
            'Player/getPlayersForCurrentAgent',
            'Agent/getAgentWallet',
            'Agent/getAgentAllWallets',
            'UserNotification/getAllUserNotifications',
        ];

        $results = [];

        foreach ($endpoints as $ep) {
            $r = $this->post($ep, ['start' => 0, 'limit' => 5, 'filter' => new \stdClass()]);
            $results[$ep] = [
                'status'         => $r['status'] ?? null,
                'result_preview' => is_array($r['result'] ?? null)
                    ? substr(json_encode($r['result'], JSON_UNESCAPED_UNICODE), 0, 200)
                    : ($r['result'] ?? null),
            ];
        }

        return $results;
    }
}
