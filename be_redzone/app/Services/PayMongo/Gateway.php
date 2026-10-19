<?php

namespace App\Services\PayMongo;

use Illuminate\Support\Facades\Http;

class Gateway
{
    public function mode(): string { return (string) config('paymongo.mode'); }

    public function configured(): bool
    {
        $url = (string) config('paymongo.public_url');
        return (bool) config('paymongo.enabled')
            && in_array($this->mode(), ['test', 'live'], true)
            && str_starts_with((string) config('paymongo.secret_key'), 'sk_'.$this->mode().'_')
            && filled(config('paymongo.webhook_secret'))
            && filter_var($url, FILTER_VALIDATE_URL)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && !parse_url($url, PHP_URL_FRAGMENT)
            && !parse_url($url, PHP_URL_QUERY)
            && !(app()->environment('production') && $this->mode() !== 'live');
    }

    public function request(string $method, string $path, array $body = [], ?string $key = null): array
    {
        abort_unless($this->configured(), 503, 'Online payments are not configured. Contact Redzone.');
        $client = Http::withBasicAuth(config('paymongo.secret_key'), '')
            ->acceptJson()->connectTimeout(5)->timeout(20);
        if ($key) $client = $client->withHeaders(['Idempotency-Key' => $key]);
        // Never automatically retry a mutating call after an ambiguous provider response.
        try {
            $response = $method === 'GET'
                ? $client->get('https://api.paymongo.com'.$path)
                : $client->post('https://api.paymongo.com'.$path, $body);
            if (!$response->successful() || !is_array($response->json('data'))) {
                throw new \RuntimeException('Unexpected provider response');
            }
            return $response->json('data');
        } catch (\Throwable $e) {
            abort(502, 'PayMongo could not confirm this request. Check its status before retrying.');
        }
    }

    public function retrieve(string $id): array
    {
        abort_unless(preg_match('/^cs_[a-zA-Z0-9]+$/D', $id), 422, 'Invalid checkout identifier.');
        return $this->request('GET', '/v1/checkout_sessions/'.rawurlencode($id));
    }
}
