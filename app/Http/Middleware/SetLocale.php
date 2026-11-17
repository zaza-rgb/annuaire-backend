<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $lang = $request->getPreferredLanguage(['fr', 'en', 'pt']) ?? 'fr';
        app()->setLocale($lang);

        return $next($request);
    }
}