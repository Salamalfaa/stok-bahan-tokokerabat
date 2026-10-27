<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('voice-recordings', function (Request $request): Limit {
            return Limit::perMinute(10)
                ->by($request->user()->getAuthIdentifier().'|'.$request->ip())
                ->after(static fn (Response $response): bool => $response->getStatusCode() !== 422)
                ->response(static fn (Request $request, array $headers): JsonResponse => response()->json([
                    'message' => 'Batas penyimpanan rekaman tercapai. Tunggu sekitar satu menit sebelum mencoba lagi.',
                ], 429, $headers));
        });
    }
}
