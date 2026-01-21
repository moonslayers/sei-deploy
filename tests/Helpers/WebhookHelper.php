<?php

namespace Tests\Helpers;

class WebhookHelper
{
    private static string $privateKeyPath;

    public static function setPrivateKeyPath(string $path): void
    {
        self::$privateKeyPath = $path;
    }

    public static function createSignedPayload(array $data): array
    {
        $keyPath = self::$privateKeyPath ?? base_path('tests/keys/private_test.pem');

        if (! file_exists($keyPath)) {
            throw new \Exception('Private key file not found: '.$keyPath);
        }

        $privateKey = file_get_contents($keyPath);

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

        $privateKeyResource = openssl_pkey_get_private($privateKey);

        if ($privateKeyResource === false) {
            throw new \Exception('Failed to load private key: '.openssl_error_string());
        }

        $result = openssl_sign($payload, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);

        openssl_free_key($privateKeyResource);

        if (! $result) {
            throw new \Exception('Failed to sign payload');
        }

        $encodedSignature = base64_encode($signature);

        return [
            'payload' => $payload,
            'signature' => $encodedSignature,
        ];
    }

    public static function createValidWebhookData(array $extra = []): array
    {
        return array_merge([
            'repository' => 'sei-compilados',
            'branch' => 'main',
            'timestamp' => time(),
        ], $extra);
    }
}
