<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $coordinates = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $apiKey = config('services.openweathermap.key');

        if (! is_string($apiKey) || $apiKey === '' || str_starts_with($apiKey, 'replace-with-')) {
            return response()->json([
                'message' => 'Weather service is not configured.',
                'code' => 'weather_not_configured',
            ], 503);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(8)
                ->get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $coordinates['lat'],
                    'lon' => $coordinates['lon'],
                    'appid' => $apiKey,
                    'units' => 'metric',
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'The weather provider could not be reached. Please try again later.',
                'code' => 'weather_unavailable',
            ], 502);
        }

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)
            || ! isset($payload['name'], $payload['main']['temp'], $payload['weather'][0]['description'])) {
            return response()->json([
                'message' => 'The weather provider returned an unavailable response.',
                'code' => 'weather_unavailable',
            ], 502);
        }

        return response()->json([
            'location' => $payload['name'],
            'temperature_c' => (float) $payload['main']['temp'],
            'feels_like_c' => isset($payload['main']['feels_like']) ? (float) $payload['main']['feels_like'] : null,
            'description' => (string) $payload['weather'][0]['description'],
            'humidity_percent' => isset($payload['main']['humidity']) ? (int) $payload['main']['humidity'] : null,
            'wind_speed_mps' => isset($payload['wind']['speed']) ? (float) $payload['wind']['speed'] : null,
            'provider' => 'OpenWeather',
        ]);
    }
}
