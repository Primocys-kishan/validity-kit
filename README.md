# Validity Kit

Laravel application authorization toolkit: purchase-code validation and periodic license verification for Laravel 10, 11 and 12.

## Installation

```bash
composer require primocys/validity-kit
```

The service provider is auto-discovered. To customise settings, publish the config:

```bash
php artisan vendor:publish --tag=validity-kit-config
```

## Configuration

| Env variable | Default | Description |
| --- | --- | --- |
| `LICENSE_VERIFY_URL` | — | Endpoint that re-verifies a saved token |
| `LICENSE_VALIDATE_URL` | — | Endpoint that exchanges a purchase code for a token |
| `LICENSE_TOKEN_FILE` | `storage/app/validatedToken.txt` | Where the token is stored |
| `LICENSE_VERIFY_AFTER_HOURS` | `2` | How often the token is re-verified |
| `LICENSE_GRACE_HOURS` | `24` | How long the app keeps working while the license server is unreachable |
| `LICENSE_HTTP_TIMEOUT` | `10` | HTTP timeout in seconds |
| `LICENSE_MIDDLEWARE_ENABLED` | `true` | Apply the license check to every request |

## Usage

Validate a purchase code (for example from an install/activation route):

```php
use ValidityKit\LicenseValidator;

Route::post('api/license/validate', function (Request $request, LicenseValidator $validator) {
    return $validator->validatePurchase($request->only('purchase_code', 'username'));
});
```

Paths listed in `middleware.except` skip the check, so keep the activation route there.

### Middleware

By default the check runs globally. To protect only specific routes, set
`LICENSE_MIDDLEWARE_ENABLED=false` and use the `license` alias:

```php
Route::middleware('license')->group(function () {
    // ...
});
```

## License

MIT
