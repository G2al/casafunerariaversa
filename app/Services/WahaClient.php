<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class WahaClient
{
    public const RECIPIENT = '393357378138@c.us';

    public function ensureConfigured(): void
    {
        $url = (string) config('services.waha.url');
        if (! config('services.waha.enabled') || ! filter_var($url, FILTER_VALIDATE_URL)
            || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || blank(config('services.waha.api_key')) || blank(config('services.waha.session'))) {
            throw ValidationException::withMessages(['waha' => 'Configurare e attivare WAHA sul server (URL, API key e sessione). Nessun messaggio inviato.']);
        }
    }

    public function send(string $text): array
    {
        $this->ensureConfigured();
        // Nessun retry: un timeout può avvenire dopo l'accettazione del messaggio.
        $response = Http::acceptJson()->asJson()->withHeaders(['X-Api-Key' => config('services.waha.api_key')])
            ->connectTimeout(5)->timeout((int) config('services.waha.timeout', 25))->withoutRedirecting()
            ->post(rtrim(config('services.waha.url'), '/').'/api/sendText', [
                'session' => config('services.waha.session'), 'chatId' => self::RECIPIENT,
                'text' => $text, 'linkPreview' => false,
            ]);
        $id = $response->json('id');
        if (is_array($id)) {
            $id = $id['_serialized'] ?? null;
        }
        if ($response->successful() && is_string($id) && filled($id)) {
            return ['status' => 'sent', 'message_id' => mb_substr($id, 0, 255), 'error' => null];
        }

        return [
            'status' => $response->clientError() && $response->status() !== 408 ? 'failed' : 'uncertain',
            'message_id' => null,
            'error' => 'WAHA: risposta HTTP '.$response->status().'. Verificare sessione e messaggi prima di riprovare.',
        ];
    }
}
