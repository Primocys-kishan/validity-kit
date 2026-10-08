<?php

namespace Primocys\LicenseValidator;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class LicenseValidator
{
    protected string $tokenPath;

    protected string $verifyUrl;

    protected string $validateUrl;

    protected int $verifyAfterHours;

    public function __construct()
    {
        $this->tokenPath = config(
            'license-validator.token_file'
        );

        $this->verifyUrl = config(
            'license-validator.verify_url'
        );

        $this->validateUrl = config(
            'license-validator.validate_url'
        );

        $this->verifyAfterHours = (int) config(
            'license-validator.verify_after_hours',
            2
        );
    }

    /**
     * Get the server MAC address.
     */
    public function getMacAddress(): ?string
    {
        $output = [];

        if (PHP_OS_FAMILY === 'Windows') {
            exec('getmac /fo csv /nh', $output);

            foreach ($output as $line) {
                $line = trim($line);

                if (preg_match('/"([^"]+)"(?:,|$)/', $line, $matches)) {
                    $mac = trim($matches[1]);

                    if (
                        $mac !== ''
                        && strtoupper($mac) !== 'N/A'
                        && preg_match('/^[0-9A-Fa-f]{2}([-:][0-9A-Fa-f]{2}){5}$/', $mac)
                    ) {
                        return $mac;
                    }
                }
            }

            return null;
        }

        exec('cat /sys/class/net/*/address 2>/dev/null', $output);

        foreach ($output as $mac) {
            $mac = trim($mac);

            if (
                $mac !== ''
                && $mac !== '00:00:00:00:00:00'
                && preg_match('/^[0-9a-f]{2}(:[0-9a-f]{2}){5}$/i', $mac)
            ) {
                return $mac;
            }
        }

        return null;
    }

    /**
     * Get the server IP address.
     */
    public function getServerIP(): ?string
    {
        $hostname = gethostname();

        if (!$hostname) {
            return null;
        }

        $ip = gethostbyname($hostname);

        if ($ip === $hostname) {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP)
            ? $ip
            : null;
    }

    /**
     * Verify the saved token with the license server.
     */
    public function verifyToken(): bool
    {
        if (!file_exists($this->tokenPath)) {
            return false;
        }

        $token = trim(
            file_get_contents($this->tokenPath)
        );

        if ($token === '') {
            return false;
        }

        $serverIp = $this->getServerIP();
        $macAddress = $this->getMacAddress();

        try {
            $response = Http::timeout(15)->post(
                $this->verifyUrl,
                [
                    'server_ip' => $serverIp,
                    'mac_address' => $macAddress,
                    'token' => $token,
                ]
            );

            if (!$response->successful()) {
                return false;
            }

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check whether the token needs to be verified again.
     *
     * Returns true when the token is valid.
     */
    public function checkTokenVerifyTokenRecreation(): bool
    {
        if (!file_exists($this->tokenPath)) {
            return false;
        }

        $lastModified = filemtime($this->tokenPath);

        if ($lastModified === false) {
            return false;
        }

        $diffHours = (
            time() - $lastModified
        ) / 3600;

        if ($diffHours < $this->verifyAfterHours) {
            return true;
        }

        $isValid = $this->verifyToken();

        if (!$isValid) {
            $this->deleteToken();

            return false;
        }

        $token = trim(
            file_get_contents($this->tokenPath)
        );

        $this->deleteToken();

        $this->saveToken($token);

        return true;
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
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'Your User Agent',
                    'X-MAC-Address' => $this->getMacAddress() ?? '',
                    'X-Device-IP' => $this->getServerIP() ?? '',
                ])
                ->post(
                    $this->validateUrl,
                    [
                        'purchase_code' => $purchaseCode,
                        'username' => $username,
                    ]
                );

            $result = $response->json();

            if (
                is_array($result)
                && in_array(
                    $result['status'] ?? null,
                    ['used', 'error', 'invalid'],
                    true
                )
            ) {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? null,
                ];
            }

            if (
                is_array($result)
                && !empty($result['token'])
            ) {
                $this->saveToken($result['token']);
            }

            return [
                'success' => true,
                'token' => is_array($result)
                    ? ($result['token'] ?? null)
                    : null,
            ];
        } catch (\Throwable $e) {
            \Log::error('Validation error:', [
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

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0755,
                true
            );
        }

        return file_put_contents(
            $this->tokenPath,
            $token
        ) !== false;
    }

    /**
     * Delete the saved token.
     */
    public function deleteToken(): bool
    {
        if (!file_exists($this->tokenPath)) {
            return true;
        }

        return unlink($this->tokenPath);
    }

    /**
     * Get the currently saved token.
     */
    public function getToken(): ?string
    {
        if (!file_exists($this->tokenPath)) {
            return null;
        }

        $token = trim(
            file_get_contents($this->tokenPath)
        );

        return $token !== ''
            ? $token
            : null;
    }
}
