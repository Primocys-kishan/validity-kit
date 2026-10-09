<?php

namespace ValidityKit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseValidator
{
    /**
     * Minimum seconds between verification attempts while the license
     * server is unreachable, so requests don't each wait on a timeout.
     */
    protected const RETRY_AFTER_SECONDS = 300;

    protected string $tokenPath;

    protected string $verifyUrl;

    protected string $validateUrl;

    protected int $verifyAfterHours;

    protected int $graceHours;

    protected int $timeout;

    protected ?string $macAddress = null;

    protected ?string $serverIp = null;

    protected bool $deviceResolved = false;

    public function __construct()
    {
        $this->tokenPath = (string) config('validity-kit.token_file');
        $this->verifyUrl = (string) config('validity-kit.verify_url');
        $this->validateUrl = (string) config('validity-kit.validate_url');
        $this->verifyAfterHours = (int) config('validity-kit.verify_after_hours', 2);
        $this->graceHours = (int) config('validity-kit.grace_hours', 24);
        $this->timeout = (int) config('validity-kit.timeout', 10);
    }

    /**
     * Get the server MAC address.
     */
    public function getMacAddress(): ?string
    {
        $this->resolveDevice();

        return $this->macAddress;
    }

    /**
     * Get the server IP address.
     */
    public function getServerIP(): ?string
    {
        $this->resolveDevice();

        return $this->serverIp;
    }

    /**
     * Verify the saved token with the license server.
     */
    public function verifyToken(): bool
    {
        return $this->requestVerification() === true;
    }

    /**
     * Check whether the token needs to be verified again.
     *
     * Returns true when the token is valid.
     */
    public function checkTokenVerifyTokenRecreation(): bool
    {
        $age = $this->tokenAge();

        if ($age === null) {
            return false;
        }

        if ($age < $this->verifyAfterHours * 3600) {
            return true;
        }

        $lock = @fopen($this->tokenPath . '.lock', 'c+');

        if ($lock === false) {
            return $this->withinGrace($age);
        }

        try {
            // Another request is already verifying; let this one through.
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return true;
            }

            // Re-check: the token may have been refreshed or removed while we waited.
            $age = $this->tokenAge();

            if ($age === null) {
                return false;
            }

            if ($age < $this->verifyAfterHours * 3600) {
                return true;
            }

            // The lock file holds the timestamp of the last verification attempt.
            $lastAttempt = (int) stream_get_contents($lock);

            if (time() - $lastAttempt < static::RETRY_AFTER_SECONDS) {
                return $this->withinGrace($age);
            }

            ftruncate($lock, 0);
            rewind($lock);
            fwrite($lock, (string) time());
            fflush($lock);

            $result = $this->requestVerification();

            if ($result === true) {
                touch($this->tokenPath);

                return true;
            }

            if ($result === false) {
                $this->deleteToken();

                return false;
            }

            // License server unreachable: keep the token, allow within the grace period.
            return $this->withinGrace($age);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Validate purchase information with the license server.
     */
    public function validatePurchase(array $data): array
    {
        $purchaseCode = $data['purchase_code'] ?? null;
        $username = $data['username'] ?? null;

        if (!$purchaseCode) {
            return [
                'success' => false,
                'message' => 'Purchase code is required.',
            ];
        }

        if (!$username) {
            return [
                'success' => false,
                'message' => 'Username is required.',
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'User-Agent' => 'primocys/validity-kit',
                    'X-MAC-Address' => $this->getMacAddress() ?? '',
                    'X-Device-IP' => $this->getServerIP() ?? '',
                ])
                ->post($this->validateUrl, [
                    'purchase_code' => $purchaseCode,
                    'username' => $username,
                ]);

            $result = $response->json();

            if (!is_array($result)) {
                $result = [];
            }

            $token = $result['token'] ?? null;

            if (
                !$response->successful()
                || in_array($result['status'] ?? null, ['used', 'error', 'invalid'], true)
                || !is_string($token)
                || $token === ''
            ) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Validation failed!',
                ];
            }

            if (!$this->saveToken($token)) {
                return [
                    'success' => false,
                    'message' => 'Could not save license token.',
                ];
            }

            return [
                'success' => true,
                'token' => $token,
            ];
        } catch (\Throwable $e) {
            Log::error('Validation error:', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Validation failed!',
            ];
        }
    }

    /**
     * Save token to the configured token file.
     */
    public function saveToken(string $token): bool
    {
        $directory = dirname($this->tokenPath);

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        return file_put_contents($this->tokenPath, $token, LOCK_EX) !== false;
    }

    /**
     * Delete the saved token.
     */
    public function deleteToken(): bool
    {
        if (!file_exists($this->tokenPath)) {
            return true;
        }

        return @unlink($this->tokenPath);
    }

    /**
     * Get the currently saved token.
     */
    public function getToken(): ?string
    {
        if (!is_file($this->tokenPath)) {
            return null;
        }

        $token = trim((string) @file_get_contents($this->tokenPath));

        return $token !== '' ? $token : null;
    }

    /**
     * Ask the license server whether the saved token is valid.
     *
     * Returns null when the server could not be reached or answered with a
     * server error, so an outage is not mistaken for a revoked license.
     */
    protected function requestVerification(): ?bool
    {
        $token = $this->getToken();

        if ($token === null) {
            return false;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($this->verifyUrl, [
                    'server_ip' => $this->getServerIP(),
                    'mac_address' => $this->getMacAddress(),
                    'token' => $token,
                ]);
        } catch (\Throwable $e) {
            Log::warning('License server unreachable:', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->serverError()) {
            return null;
        }

        return $response->successful()
            && (bool) $response->json('success', false);
    }

    /**
     * Seconds since the token was last verified, or null if there is no token.
     */
    protected function tokenAge(): ?int
    {
        clearstatcache(true, $this->tokenPath);

        // A single stat call: filemtime() fails when the file is missing.
        $lastModified = @filemtime($this->tokenPath);

        return $lastModified === false ? null : time() - $lastModified;
    }

    protected function withinGrace(int $age): bool
    {
        return $age < ($this->verifyAfterHours + $this->graceHours) * 3600;
    }

    protected function resolveDevice(): void
    {
        if ($this->deviceResolved) {
            return;
        }

        $this->deviceResolved = true;
        $this->macAddress = $this->detectMacAddress();
        $this->serverIp = $this->detectServerIp();
    }

    protected function detectMacAddress(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            if (!function_exists('exec')) {
                return null;
            }

            $output = [];
            @exec('getmac /fo csv /nh', $output);

            foreach ($output as $line) {
                if (preg_match('/"([0-9A-Fa-f]{2}(?:[-:][0-9A-Fa-f]{2}){5})"/', $line, $matches)) {
                    return $matches[1];
                }
            }

            return null;
        }

        // Read sysfs directly instead of shelling out: exec() is often disabled on shared hosting.
        foreach (glob('/sys/class/net/*/address') ?: [] as $file) {
            $mac = trim((string) @file_get_contents($file));

            if (
                $mac !== '00:00:00:00:00:00'
                && preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/i', $mac)
            ) {
                return $mac;
            }
        }

        return null;
    }

    protected function detectServerIp(): ?string
    {
        $hostname = gethostname();

        if (!$hostname) {
            return null;
        }

        $ip = gethostbyname($hostname);

        return $ip !== $hostname && filter_var($ip, FILTER_VALIDATE_IP)
            ? $ip
            : null;
    }
}
