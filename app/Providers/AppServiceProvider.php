<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Anti-spam de la messagerie (ligne 79) : 20 messages par minute et par utilisateur.
        RateLimiter::for('messages', function (Request $request) {
            return Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json(
                        ['message' => 'Trop de messages. Réessayez dans quelques instants.'],
                        429,
                        $headers
                    );
                });
        });

        // Message aux inscrits (ligne 69) : 5 envois par minute et 20 par heure et par organisateur.
        RateLimiter::for('broadcast', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            $tooMany = function (Request $request, array $headers) {
                return response()->json(
                    ['message' => 'Trop de messages envoyés aux inscrits. Réessayez plus tard.'],
                    429,
                    $headers
                );
            };

            // Deux clés différentes, sinon les deux limites partageraient le même compteur.
            return [
                Limit::perMinute(5)->by($key . '-min')->response($tooMany),
                Limit::perHour(20)->by($key . '-hour')->response($tooMany),
            ];
        });
    }
}