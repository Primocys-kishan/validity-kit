<?php

namespace Primocys\LicenseValidator\Middleware;

use Closure;
use Illuminate\Http\Request;
use Primocys\LicenseValidator\LicenseValidator;
use Symfony\Component\HttpFoundation\Response;

class ValidateLicense
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $except = config(
            'license-validator.middleware.except',
            []
        );

        foreach ($except as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        $validator = app(LicenseValidator::class);

        if (!$validator->checkTokenVerifyTokenRecreation()) {
            return response()->json([
                'success' => false,
                'message' => 'License validation required.',
            ], 403);
        }

        return $next($request);
    }
}