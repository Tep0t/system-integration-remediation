<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\WeatherController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/diagnostics/database', function (Request $request) {
    $configuredToken = (string) config('services.database_diagnostic.token');
    $providedToken = (string) $request->header('X-Database-Diagnostic-Token', '');

    if ($configuredToken === '' || ! hash_equals($configuredToken, $providedToken)) {
        abort(404);
    }

    try {
        DB::select('SELECT 1');
    } catch (Throwable) {
        return response()->json(['message' => 'Database connection failed.'], 503);
    }

    return response()->json(['message' => 'Database connection successful.']);
})->withoutMiddleware([
    PreventRequestForgery::class,
    ShareErrorsFromSession::class,
    StartSession::class,
]);

Route::prefix('api')->group(function (): void {
    Route::get('/csrf-cookie', [AuthController::class, 'csrfCookie']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::post('/mfa/challenge', [MfaController::class, 'verifyLogin'])->middleware('throttle:mfa-challenge');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-reset-password');

    Route::middleware('auth')->group(function (): void {
        Route::get('/user', function (Request $request) {
            $user = $request->user();

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        });
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/mfa/status', [MfaController::class, 'status']);
        Route::post('/mfa/setup', [MfaController::class, 'setup'])->middleware('throttle:mfa-setup');
        Route::post('/mfa/enable', [MfaController::class, 'enable'])->middleware('throttle:mfa-enable');
        Route::post('/mfa/disable', [MfaController::class, 'disable'])->middleware('throttle:mfa-disable');
        Route::get('/weather', [WeatherController::class, 'show'])->middleware('throttle:weather');
        Route::post('/maps/search', [MapController::class, 'search'])->middleware('throttle:maps-search');
    });
});
