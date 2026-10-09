<?php

namespace ValidityKit\Middleware;

use Closure;
use Illuminate\Http\Request;
use ValidityKit\LicenseValidator;
use Symfony\Component\HttpFoundation\Response;

class ValidateLicense
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $except = config(
            'validity-kit.middleware.except',
            []
        );

        foreach ($except as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        $validator = app(LicenseValidator::class);

        if (!$validator->checkTokenVerifyTokenRecreation()) {
            if (!$request->expectsJson()) {
                abort(403, 'License validation required.');
            }

            return response()->json([
                'success' => false,
                'message' => 'License validation required.',
            ], 403);
        }

        return $next($request);
    }
}