<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class WeatherMapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_weather_is_loaded_from_the_provider_for_an_authenticated_user(): void
    {
        config(['services.openweathermap.key' => 'test-weather-key']);
        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'name' => 'Seattle',
                'main' => ['temp' => 12.4, 'feels_like' => 10.1, 'humidity' => 72],
                'weather' => [['description' => 'light rain']],
                'wind' => ['speed' => 3.2],
            ]),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/weather?lat=47.6&lon=-122.3')
            ->assertOk()
            ->assertJsonPath('location', 'Seattle')
            ->assertJsonPath('temperature_c', 12.4)
            ->assertJsonPath('description', 'light rain')
            ->assertJsonPath('provider', 'OpenWeather');

        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.openweathermap.org/data/2.5/weather')
            && $request['appid'] === 'test-weather-key'
            && $request['units'] === 'metric');
    }

    public function test_weather_reports_missing_configuration_and_invalid_coordinates(): void
    {
        config(['services.openweathermap.key' => 'replace-with-openweathermap-api-key']);
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/weather?lat=47.6&lon=-122.3')
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'weather_not_configured');
        $this->getJson('/api/weather?lat=91&lon=-122.3')->assertUnprocessable();
    }

    public function test_weather_provider_errors_are_not_returned_as_success(): void
    {
        config(['services.openweathermap.key' => 'test-weather-key']);
        Http::fake(['api.openweathermap.org/*' => Http::response(['message' => 'provider failure'], 500)]);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/weather?lat=47.6&lon=-122.3')
            ->assertStatus(502)
            ->assertJsonPath('code', 'weather_unavailable');
    }

    public function test_map_search_returns_provider_places_and_empty_results(): void
    {
        config(['services.maps.user_agent' => 'SystemIntegrationRemediation/1.0 (contact: test@example.com)']);
        RateLimiter::clear('nominatim:global');
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push([[
                    'display_name' => 'Seattle, King County, Washington, United States',
                    'lat' => '47.6062',
                    'lon' => '-122.3321',
                ], [
                    'display_name' => 'Invalid provider result',
                    'lat' => '1000',
                    'lon' => '500',
                ]])
                ->push([]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/maps/search', ['query' => 'Seattle'])
            ->assertOk()
            ->assertJsonCount(1, 'places')
            ->assertJsonPath('places.0.name', 'Seattle, King County, Washington, United States')
            ->assertJsonPath('places.0.lat', 47.6062)
            ->assertJsonPath('provider', 'OpenStreetMap Nominatim');

        RateLimiter::clear('nominatim:global');
        $this->postJson('/api/maps/search', ['query' => 'an unlisted place'])
            ->assertOk()
            ->assertExactJson(['places' => [], 'provider' => 'OpenStreetMap Nominatim']);

        Http::assertSent(fn ($request): bool => $request->hasHeader('User-Agent')
            && str_contains($request->url(), 'q=Seattle'));
    }

    public function test_map_search_validates_input_and_reports_provider_failures(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/maps/search', ['query' => 'x'])->assertUnprocessable();

        config(['services.maps.user_agent' => 'SystemIntegrationRemediation/1.0 (contact: test@example.com)']);
        RateLimiter::clear('nominatim:global');
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['error' => 'unavailable'], 503)]);
        $this->postJson('/api/maps/search', ['query' => 'Seattle'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'map_search_unavailable');
    }

    public function test_map_search_requires_a_contact_user_agent(): void
    {
        config(['services.maps.user_agent' => 'SystemIntegrationRemediation/1.0']);
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/maps/search', ['query' => 'Seattle'])
            ->assertServiceUnavailable()
            ->assertJsonPath('code', 'map_search_not_configured');

        Http::assertNothingSent();
    }
}
