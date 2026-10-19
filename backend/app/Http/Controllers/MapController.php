<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class MapController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:120'],
        ]);
        $userAgent = (string) config('services.maps.user_agent');
        if (! preg_match('/contact:\s*([^\s)]+)/i', $userAgent, $contact)
            || filter_var($contact[1], FILTER_VALIDATE_EMAIL) === false) {
            return response()->json([
                'message' => 'Map search requires an application User-Agent with a valid contact email.',
                'code' => 'map_search_not_configured',
            ], 503);
        }

        if (! RateLimiter::attempt('nominatim:global', 1, fn (): bool => true, 1)) {
            return response()->json([
                'message' => 'Map search is busy. Please wait a moment before trying again.',
                'code' => 'map_rate_limited',
            ], 429)->header('Retry-After', (string) max(1, RateLimiter::availableIn('nominatim:global')));
        }

        try {
            $response = Http::acceptJson()
                ->withUserAgent($userAgent)
                ->connectTimeout(3)
                ->timeout(8)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => trim($validated['query']),
                    'format' => 'jsonv2',
                    'limit' => 5,
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'The map search provider could not be reached. Please try again later.',
                'code' => 'map_search_unavailable',
            ], 502);
        }

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload)) {
            return response()->json([
                'message' => 'The map search provider returned an unavailable response.',
                'code' => 'map_search_unavailable',
            ], 502);
        }

        return response()->json([
            'places' => array_map(
                fn (array $place): array => [
                    'name' => (string) ($place['display_name'] ?? ''),
                    'lat' => (float) $place['lat'],
                    'lon' => (float) $place['lon'],
                ],
                array_filter(
                    $payload,
                    fn (mixed $place): bool => is_array($place)
                        && isset($place['display_name'], $place['lat'], $place['lon'])
                        && is_string($place['display_name'])
                        && is_numeric($place['lat'])
                        && is_numeric($place['lon'])
                        && (float) $place['lat'] >= -90
                        && (float) $place['lat'] <= 90
                        && (float) $place['lon'] >= -180
                        && (float) $place['lon'] <= 180,
                ),
            ),
            'provider' => 'OpenStreetMap Nominatim',
        ]);
    }
}
