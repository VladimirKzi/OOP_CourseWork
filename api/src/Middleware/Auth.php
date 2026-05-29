<?php
namespace App\Middleware;
use App\{Database, JWT, Response};

class Auth
{
    private static ?array $cache = null;

    /**
     * Зчитує Bearer-токен з заголовку Authorization.
     *
     * Apache часто не передає Authorization до $_SERVER['HTTP_AUTHORIZATION'].
     * Тому перевіряємо кілька можливих місць у правильному порядку:
     *  1. $_SERVER['HTTP_AUTHORIZATION']        — стандарт (після RewriteRule в .htaccess)
     *  2. $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] — після Apache redirect
     *  3. apache_request_headers()['Authorization'] — прямий доступ до заголовків Apache
     *  4. getallheaders()['Authorization']      — псевдонім попереднього
     */
    private static function getBearerToken(): ?string
    {
        // 1. Стандартний спосіб (працює після RewriteRule .* - [E=HTTP_AUTHORIZATION:...])
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // 2. Після Apache internal redirect
        if (!$header) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        // 3. Через apache_request_headers() — найнадійніший на WampServer
        if (!$header && function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            // Заголовки можуть бути в різному регістрі
            $header = $apacheHeaders['Authorization']
                   ?? $apacheHeaders['authorization']
                   ?? '';
        }

        // 4. getallheaders() — альтернатива
        if (!$header && function_exists('getallheaders')) {
            $all    = getallheaders();
            $header = $all['Authorization'] ?? $all['authorization'] ?? '';
        }

        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        return substr($header, 7);
    }

    public static function user(): ?array
    {
        if (self::$cache !== null) return self::$cache;

        $token = self::getBearerToken();
        if (!$token) return null;

        try {
            $data   = JWT::decode($token, JWT_SECRET);
            $userId = $data['userId'] ?? null;
            if (!$userId) return null;

            $user = Database::one(
                'SELECT id, name, email, role, avatar_url, is_blocked, is_active, created_at
                 FROM users WHERE id = ?',
                [$userId]
            );

            if (!$user || $user['is_blocked'] || !$user['is_active']) return null;

            self::$cache = $user;
            return $user;
        } catch (\Exception) {
            return null;
        }
    }

    public static function require(): array
    {
        $u = self::user();
        if (!$u) Response::error('Не авторизований', 401);
        return $u;
    }

    public static function requireModerator(): array
    {
        $u = self::require();
        if (!in_array($u['role'], ['admin', 'moderator'], true))
            Response::error('Недостатньо прав', 403);
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::require();
        if ($u['role'] !== 'admin') Response::error('Потрібен адміністратор', 403);
        return $u;
    }

    public static function createToken(int $userId): string
    {
        return JWT::encode(
            ['userId' => $userId, 'iat' => time(), 'exp' => time() + JWT_EXPIRES_IN],
            JWT_SECRET
        );
    }
}
