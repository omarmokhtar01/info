<?php
namespace App\Http\Middleware;

use Illuminate\Session\Middleware\StartSession as BaseStartSession;

class StartSessionMiddleware extends BaseStartSession
{
    public function handle($request, \Closure $next)
    {
        if (!$request->hasSession()) {
            $request->setLaravelSession(app('session.store'));
        }

        return parent::handle($request, $next);
    }
}
