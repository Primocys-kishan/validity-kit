<?php

namespace ValidityKit\Middleware;

use Closure;
use Illuminate\Http\Request;
use ValidityKit\LicenseValidator;
use Symfony\Component\HttpFoundation\Response;

class ValidateLicense
{
    /**
     * Paths that must stay reachable without an existing token.
     */
    protected const ALWAYS_EXCEPT = [
        'license/validate',
        'api/license/validate',
    ];

    public function __construct(
        protected LicenseValidator $validator
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $except = array_merge(
            static::ALWAYS_EXCEPT,
            (array) config('validity-kit.middleware.except', [])
        );

        if ($request->is(...$except)) {
            return $next($request);
        }

        if (!$this->validator->checkTokenVerifyTokenRecreation()) {
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
