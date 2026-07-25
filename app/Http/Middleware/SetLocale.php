<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('locale')) {
            app()->setLocale(session()->get('locale'));
        } elseif (auth()->check() && auth()->user()->locale) {
            app()->setLocale(auth()->user()->locale);
            session(['locale' => auth()->user()->locale]);
        }

        return $next($request);
    }
}
