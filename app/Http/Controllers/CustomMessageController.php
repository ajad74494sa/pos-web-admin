<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CustomMessageController extends Controller
{
    /**
     * Send a WhatsApp text message via OpenWA.
     */
    public function send(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string',
        ]);

        $baseUrl = rtrim(config('services.openwa.base_url', env('OPENWA_BASE_URL', '')), '/');
        $sessionId = config('services.openwa.session_id', env('OPENWA_SESSION_ID', ''));
        $apiKey = config('services.openwa.api_key', env('OPENWA_API_KEY', ''));

        if (empty($baseUrl) || empty($sessionId)) {
            return response()->json([
                'success' => false,
                'message' => 'OpenWA configuration is missing.',
            ], 500);
        }

        $url = $baseUrl.'/api/sessions/'.$sessionId.'/messages/send-text';

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if (! empty($apiKey)) {
            // include both header casings to match different OpenWA builds
            // $headers['X-Api-Key'] = $apiKey;
            $headers['X-API-Key'] = $apiKey;
        }

        try {
            // OpenWA expects payload with `chatId` and `text` (and optional `mentions`).
            // If caller sent `chatId` use it, otherwise build it from `to`.
            $chatId = $request->input('chatId');
            $to = $request->input('to');

            if (empty($chatId) && ! empty($to)) {
                // normalize: remove spaces and plus
                $num = preg_replace('/[^0-9]/', '', $to);
                if ($num !== '') {
                    $chatId = $num.'@c.us';
                }
            }

            $payload = [
                'text' => $request->input('message'),
            ];

            if (! empty($chatId)) {
                $payload['chatId'] = $chatId;
            }

            if ($request->has('mentions') && is_array($request->input('mentions'))) {
                $payload['mentions'] = $request->input('mentions');
            }

            // Log payload for debugging
            Log::info('OpenWA request payload', ['url' => $url, 'headers' => $headers, 'payload' => $payload]);

            $response = Http::withHeaders($headers)->post($url, $payload);

            // Log raw response for debugging (status, headers, body)
            Log::info('OpenWA response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
            ]);

            $respJson = $response->json();
            $success = $response->successful();

            return response()->json([
                'success' => $success,
                'message' => $respJson['message'] ?? ($success ? 'Message sent successfully.' : 'Provider error'),
                'data' => $respJson,
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send message.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
