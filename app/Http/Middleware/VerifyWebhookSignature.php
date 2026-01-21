<?php

namespace App\Http\Middleware;

use App\Services\CryptoService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    private CryptoService $cryptoService;

    public function __construct(CryptoService $cryptoService)
    {
        $this->cryptoService = $cryptoService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $payload = $request->getContent();

        if (empty($payload)) {
            Log::warning('Webhook received with empty payload');

            return response()->json([
                'error' => 'Empty payload',
                'message' => 'Request body cannot be empty',
            ], 400);
        }

        $signature = $request->header('X-Webhook-Signature');

        if (empty($signature)) {
            $data = $request->json()->all();
            $signature = $data['signature'] ?? null;
        }

        if (empty($signature)) {
            Log::warning('Webhook received without signature');

            return response()->json([
                'error' => 'Missing signature',
                'message' => 'Signature is required in header X-Webhook-Signature or body',
            ], 401);
        }

        try {
            $isValid = $this->cryptoService->verifySignature($payload, $signature);

            if (! $isValid) {
                Log::warning('Invalid webhook signature', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'error' => 'Invalid signature',
                    'message' => 'Could not verify webhook signature',
                ], 401);
            }

            $data = $this->cryptoService->parsePayload($payload);

            if ($data === null) {
                Log::warning('Invalid JSON payload');

                return response()->json([
                    'error' => 'Invalid JSON',
                    'message' => 'Request body must be valid JSON',
                ], 400);
            }

            if (! isset($data['timestamp'])) {
                Log::warning('Missing timestamp in payload');

                return response()->json([
                    'error' => 'Missing timestamp',
                    'message' => 'Payload must include timestamp',
                ], 400);
            }

            $timestampValid = $this->cryptoService->validateTimestamp($data['timestamp']);

            if (! $timestampValid) {
                Log::warning('Invalid timestamp in payload', ['timestamp' => $data['timestamp']]);

                return response()->json([
                    'error' => 'Invalid timestamp',
                    'message' => 'Timestamp is too old or too new',
                ], 401);
            }

            $request->merge(['webhook_data' => $data]);

        } catch (\Exception $e) {
            Log::error('Error verifying webhook signature', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Verification error',
                'message' => 'Failed to verify webhook signature',
            ], 500);
        }

        return $next($request);
    }
}
