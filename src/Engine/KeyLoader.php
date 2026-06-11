<?php

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;

final class KeyLoader
{
    public static function load(string $path, ?string $passphrase): \OpenSSLAsymmetricKey
    {
        $resolved = self::resolvePath($path);

        if (!is_file($resolved) || !is_readable($resolved)) {
            throw new FiscalConfigurationException("Private key not found or not readable: {$resolved}");
        }

        $contents = file_get_contents($resolved);
        if ($contents === false || trim($contents) === '') {
            throw new FiscalConfigurationException("Private key is empty: {$resolved}");
        }

        $key = openssl_pkey_get_private($contents, $passphrase ?? '');
        if ($key === false) {
            $errors = self::opensslErrors();
            throw new FiscalConfigurationException(
                'Cannot load private key. OpenSSL: ' . implode('; ', $errors)
            );
        }

        $details = openssl_pkey_get_details($key);
        if (($details['type'] ?? -1) !== OPENSSL_KEYTYPE_EC) {
            throw new FiscalConfigurationException(
                'Private key must be ECDSA (EC type). RSA keys are not accepted.'
            );
        }
        if (($details['ec']['curve_name'] ?? '') !== 'prime256v1') {
            $found = $details['ec']['curve_name'] ?? 'unknown';
            throw new FiscalConfigurationException(
                "Private key must use P-256 curve (prime256v1). Found: {$found}"
            );
        }

        return $key;
    }

    private static function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1) {
            return $path;
        }
        return storage_path('app/' . ltrim($path, '/'));
    }

    private static function opensslErrors(): array
    {
        $errors = [];
        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }
        return $errors;
    }
}
