<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class GoogleIdentityVerifier
{
    public const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    /** @return array<string, mixed> */
    public function verify(#[\SensitiveParameter] string $credential, string $nonce): array
    {
        $certs = Cache::remember('google-signin:jwks', 3600, fn () => Http::connectTimeout(3)
            ->timeout(8)
            ->get(self::CERTS_URL)
            ->throw()
            ->json());

        if (! is_array($certs)) {
            throw new UnexpectedValueException('Invalid Google key set.');
        }

        $certs['keys'] = array_values(array_filter($certs['keys'] ?? [], fn ($key): bool => is_array($key)
            && ($key['kty'] ?? null) === 'RSA'
            && ($key['alg'] ?? null) === 'RS256'));

        $claims = (array) JWT::decode($credential, JWK::parseKeySet($certs, 'RS256'));

        if (($claims['aud'] ?? null) !== config('services.google.client_id')
            || ! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255
            || ! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce'])
            || ($claims['email_verified'] ?? false) !== true
            || ! is_string($claims['email'] ?? null) || strlen($claims['email']) > 255
            || ! filter_var($claims['email'], FILTER_VALIDATE_EMAIL)) {
            throw new UnexpectedValueException('Invalid Google identity claims.');
        }

        $claims['email'] = mb_strtolower($claims['email']);

        return $claims;
    }
}
