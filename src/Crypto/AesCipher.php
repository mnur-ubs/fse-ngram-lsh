<?php

declare(strict_types=1);

namespace Fkse\Crypto;

final class AesCipher
{
    private const CIPHER_METHOD = 'aes-256-cbc';
    private const IV_LENGTH = 16;

    /**
     * Encrypts plaintext using AES-256-CBC with a cryptographically secure random IV.
     * Payload format: Base64(16-byte IV || raw ciphertext)
     */
    public static function encrypt(string $plaintext, string $key): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $binaryKey = hash('sha256', $key, true);

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER_METHOD,
            $binaryKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('AES encryption failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $ciphertext);
    }

    /**
     * Decrypts Base64(IV || Ciphertext) using AES-256-CBC.
     */
    public static function decrypt(string $payload, string $key): ?string
    {
        $data = base64_decode($payload, true);
        if ($data === false || strlen($data) <= self::IV_LENGTH) {
            return null;
        }

        $iv = substr($data, 0, self::IV_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH);
        $binaryKey = hash('sha256', $key, true);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER_METHOD,
            $binaryKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted !== false ? $decrypted : null;
    }
}
