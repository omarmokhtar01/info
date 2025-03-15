<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LanguageTranslation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request        $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language', 'en');

        // Optional: Validate the locale
        $availableLocales = ['en', 'ar', 'fr', 'es']; // Add your supported locales here
        if (!in_array($locale, $availableLocales)) {
            $locale = 'en'; // Default locale
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
