<?php
namespace App;

class JWT
{
    private static function b64u(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
    private static function b64d(string $d): string { return base64_decode(strtr($d, '-_', '+/')); }

    public static function encode(array $payload, string $secret): string
    {
        $h = self::b64u(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = self::b64u(json_encode($payload));
        return "$h.$p." . self::b64u(hash_hmac('sha256', "$h.$p", $secret, true));
    }

    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new \RuntimeException('Invalid token');
        [$h, $p, $sig] = $parts;
        if (!hash_equals(self::b64u(hash_hmac('sha256', "$h.$p", $secret, true)), $sig))
            throw new \RuntimeException('Bad signature');
        $data = json_decode(self::b64d($p), true);
        if (!$data) throw new \RuntimeException('Bad payload');
        if (isset($data['exp']) && $data['exp'] < time()) throw new \RuntimeException('Token expired');
        return $data;
    }
}
