<?php
// app/helpers/Jwt.php — minimal HS256 JWT for the mobile API

class Jwt {
    private static function key(): string {
        $key = (string) JWT_SECRET;
        if ($key === '' || $key === 'dev-jwt-secret-change-me') {
            // Fall back to a value derived from other secrets so tokens are still
            // signed even if JWT_SECRET was left blank in config.
            $key = hash('sha256', DB_PASS . '|' . APP_NAME . '|fataknews-mobile');
        }
        return $key;
    }

    private static function b64(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64d(string $data): string {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4)) ?: '';
    }

    public static function issue(array $claims, int $ttlSeconds = 0): string {
        $ttlSeconds = $ttlSeconds > 0 ? $ttlSeconds : (int) JWT_EXPIRE;
        $now = time();
        $payload = array_merge([
            'iss' => APP_NAME,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttlSeconds,
        ], $claims);

        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body   = self::b64(json_encode($payload));
        $sig    = self::b64(hash_hmac('sha256', $header . '.' . $body, self::key(), true));

        return $header . '.' . $body . '.' . $sig;
    }

    /** @return array<string,mixed>|null decoded claims, or null when invalid/expired */
    public static function verify(?string $token): ?array {
        $token = trim((string) $token);
        if ($token === '' || substr_count($token, '.') !== 2) {
            return null;
        }

        [$header, $body, $sig] = explode('.', $token);
        $expected = self::b64(hash_hmac('sha256', $header . '.' . $body, self::key(), true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $claims = json_decode(self::b64d($body), true);
        if (!is_array($claims)) {
            return null;
        }

        $now = time();
        if (isset($claims['nbf']) && $now < (int) $claims['nbf'] - 5) {
            return null;
        }
        if (isset($claims['exp']) && $now >= (int) $claims['exp']) {
            return null;
        }

        return $claims;
    }

    public static function bearerToken(): ?string {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = $value;
                    break;
                }
            }
        }

        if (preg_match('/Bearer\s+(.+)/i', (string) $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
