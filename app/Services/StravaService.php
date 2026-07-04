<?php

namespace App\Services;

use App\Models\StravaToken;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class StravaService
{
    private const AUTH_BASE_URL = 'https://www.strava.com/oauth';
    private const API_BASE_URL  = 'https://www.strava.com/api/v3';

    public function getAuthorizeUrl(): string
    {
        $query = http_build_query([
            'client_id'        => config('services.strava.client_id'),
            'redirect_uri'     => config('services.strava.redirect_uri'),
            'response_type'    => 'code',
            'approval_prompt'  => 'auto',
            'scope'            => 'activity:read_all',
        ]);

        return self::AUTH_BASE_URL . '/authorize?' . $query;
    }

    public function exchangeCode(string $code): void
    {
        $response = Http::post(self::AUTH_BASE_URL . '/token', [
            'client_id'     => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
            'code'          => $code,
            'grant_type'    => 'authorization_code',
        ]);

        $response->throw();

        $this->storeToken($response->json());
    }

    private function storeToken(array $data): void
    {
        $athlete = $data['athlete'] ?? [];

        StravaToken::query()->delete();

        StravaToken::create([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at'    => date('Y-m-d H:i:s', $data['expires_at']),
            'athlete_id'    => $athlete['id'] ?? null,
            'athlete_name'  => trim(($athlete['firstname'] ?? '') . ' ' . ($athlete['lastname'] ?? '')) ?: null,
        ]);
    }

    public function ensureFreshAccessToken(): string
    {
        $token = StravaToken::first();

        if (! $token) {
            throw new RuntimeException('Strava : aucun compte connecté');
        }

        if ($token->expires_at->isFuture()) {
            return $token->access_token;
        }

        $response = Http::post(self::AUTH_BASE_URL . '/token', [
            'client_id'     => config('services.strava.client_id'),
            'client_secret' => config('services.strava.client_secret'),
            'refresh_token' => $token->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        $response->throw();

        $data = $response->json();

        $token->update([
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at'    => date('Y-m-d H:i:s', $data['expires_at']),
        ]);

        return $token->access_token;
    }

    public function disconnect(): void
    {
        $token = StravaToken::first();

        if (! $token) {
            return;
        }

        try {
            Http::asForm()->post(self::AUTH_BASE_URL . '/deauthorize', [
                'access_token' => $token->access_token,
            ]);
        } catch (Throwable $e) {
            // best-effort : la révocation côté Strava peut échouer (token déjà expiré,
            // API injoignable...), on supprime quand même la connexion locale
        }

        StravaToken::query()->delete();
    }

    public function fetchActivities(?int $after = null): array
    {
        $accessToken = $this->ensureFreshAccessToken();

        $all     = [];
        $page    = 1;
        $perPage = 100;

        do {
            $response = Http::withToken($accessToken)
                ->get(self::API_BASE_URL . '/athlete/activities', array_filter([
                    'after'    => $after,
                    'page'     => $page,
                    'per_page' => $perPage,
                ], fn($value) => $value !== null));

            $response->throw();

            $batch = $response->json();
            $all   = array_merge($all, $batch);
            $page++;
        } while (count($batch) === $perPage);

        return $all;
    }
}
