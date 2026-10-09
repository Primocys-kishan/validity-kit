<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Verify URL
    |--------------------------------------------------------------------------
    */

    'verify_url' => env(
        'LICENSE_VERIFY_URL',
        'http://62.72.36.245:1142/verify_new'
    ),

    /*
    |--------------------------------------------------------------------------
    | License Validate URL
    |--------------------------------------------------------------------------
    */

    'validate_url' => env(
        'LICENSE_VALIDATE_URL',
        'http://62.72.36.245:1142/validate'
    ),

    /*
    |--------------------------------------------------------------------------
    | Token File
    |--------------------------------------------------------------------------
    */

    'token_file' => env(
        'LICENSE_TOKEN_FILE',
        storage_path('app/validatedToken.txt')
    ),

    /*
    |--------------------------------------------------------------------------
    | Verify Token After
    |--------------------------------------------------------------------------
    */

    'verify_after_hours' => env(
        'LICENSE_VERIFY_AFTER_HOURS',
        2
    ),

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | Hours the app keeps working when the license server cannot be reached.
    | A token is only deleted when the server explicitly rejects it.
    |
    */

    'grace_hours' => env(
        'LICENSE_GRACE_HOURS',
        24
    ),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout (seconds)
    |--------------------------------------------------------------------------
    */

    'timeout' => env(
        'LICENSE_HTTP_TIMEOUT',
        10
    ),

    'middleware' => [

        'enabled' => env(
            'LICENSE_MIDDLEWARE_ENABLED',
            true
        ),

        'except' => [
            'api/license/validate',
        ],

    ],

];
