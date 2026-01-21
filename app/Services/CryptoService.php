<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class CryptoService
{
    private ?string $publicKey = null;

    private int $timestampWindow;

    public function __construct()
    {
        $this->publicKey = env('PUBLIC_KEY');
        $this->timestampWindow = 300;
    }

    public function verifySignature(string $payload, string $signature): bool
    {
        if (! $this->publicKey) {
            throw new Exception('Public key not configured');
        }

        $decodedKey = $this->decodePublicKey($this->publicKey);
        $decodedSignature = base64_decode($signature);

        if ($decodedSignature === false) {
            return false;
        }

        $publicKeyResource = openssl_pkey_get_public($decodedKey);

        if ($publicKeyResource === false) {
            Log::error('Failed to load public key', ['error' => openssl_error_string()]);

            return false;
        }

        $result = openssl_verify($payload, $decodedSignature, $publicKeyResource, OPENSSL_ALGO_SHA256);

        openssl_free_key($publicKeyResource);

        return $result === 1;
    }

    public function decodePublicKey(string $encodedKey): string
    {
        $decoded = base64_decode($encodedKey);

        if ($decoded === false) {
            throw new Exception('Failed to decode public key');
        }

        return $decoded;
    }

    public function validateTimestamp(int $timestamp): bool
    {
        $currentTime = time();
        $minTime = $currentTime - $this->timestampWindow;
        $maxTime = $currentTime + $this->timestampWindow;

        return $timestamp >= $minTime && $timestamp <= $maxTime;
    }

    public function createPayload(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    public function parsePayload(string $payload): ?array
    {
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }
}
