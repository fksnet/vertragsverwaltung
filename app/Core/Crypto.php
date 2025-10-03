<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Kryptografie-Klasse mit XChaCha20-Poly1305 für sensitive Daten
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class Crypto
{
    private const NONCE_LENGTH = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
    private const KEY_LENGTH = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;
    
    /**
     * Daten verschlüsseln
     */
    public static function encrypt(string $plaintext, ?string $additionalData = null): string
    {
        $key = self::getEncryptionKey();
        $nonce = random_bytes(self::NONCE_LENGTH);
        
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $additionalData,
            $nonce,
            $key
        );
        
        // Nonce + Ciphertext als Base64
        return base64_encode($nonce . $ciphertext);
    }
    
    /**
     * Daten entschlüsseln
     */
    public static function decrypt(string $encrypted, ?string $additionalData = null): ?string
    {
        try {
            $data = base64_decode($encrypted, true);
            
            if ($data === false || strlen($data) < self::NONCE_LENGTH) {
                return null;
            }
            
            $nonce = substr($data, 0, self::NONCE_LENGTH);
            $ciphertext = substr($data, self::NONCE_LENGTH);
            
            $key = self::getEncryptionKey();
            
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                $additionalData,
                $nonce,
                $key
            );
            
            return $plaintext !== false ? $plaintext : null;
            
        } catch (\SodiumException $e) {
            return null;
        }
    }
    
    /**
     * Passwort sicher hashen mit Argon2ID
     */
    public static function hashPassword(string $password): string
    {
        return password_hash(
            $password,
            config('security.password_algo', PASSWORD_ARGON2ID),
            config('security.password_options', [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ])
        );
    }
    
    /**
     * Passwort verifizieren
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Sicheren Random-String generieren
     */
    public static function randomString(int $length = 32, bool $urlSafe = false): string
    {
        $bytes = random_bytes($length);
        
        if ($urlSafe) {
            return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        }
        
        return bin2hex($bytes);
    }
    
    /**
     * Sicheren numerischen Code generieren
     */
    public static function randomCode(int $digits = 6): string
    {
        $min = pow(10, $digits - 1);
        $max = pow(10, $digits) - 1;
        
        return (string) random_int($min, $max);
    }
    
    /**
     * HMAC-Signatur erstellen
     */
    public static function sign(string $data, ?string $key = null): string
    {
        $key = $key ?? self::getAppKey();
        return hash_hmac('sha256', $data, $key);
    }
    
    /**
     * HMAC-Signatur verifizieren
     */
    public static function verifySignature(string $data, string $signature, ?string $key = null): bool
    {
        $expectedSignature = self::sign($data, $key);
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Signierte Daten erstellen (JSON mit Signatur)
     */
    public static function signData(array $data, ?int $expires = null): string
    {
        if ($expires) {
            $data['expires'] = time() + $expires;
        }
        
        $payload = base64_encode(json_encode($data));
        $signature = self::sign($payload);
        
        return $payload . '.' . $signature;
    }
    
    /**
     * Signierte Daten verifizieren und entpacken
     */
    public static function verifySignedData(string $signedData): ?array
    {
        $parts = explode('.', $signedData);
        
        if (count($parts) !== 2) {
            return null;
        }
        
        [$payload, $signature] = $parts;
        
        if (!self::verifySignature($payload, $signature)) {
            return null;
        }
        
        $data = json_decode(base64_decode($payload), true);
        
        if (!$data) {
            return null;
        }
        
        // Ablauf prüfen
        if (isset($data['expires']) && $data['expires'] < time()) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * JWT-ähnlichen Token erstellen
     */
    public static function createToken(array $payload, ?int $expiresInSeconds = null): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        
        if ($expiresInSeconds) {
            $payload['exp'] = time() + $expiresInSeconds;
        }
        
        $headerEncoded = base64url_encode(json_encode($header));
        $payloadEncoded = base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, self::getAppKey(), true);
        $signatureEncoded = base64url_encode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * JWT-Token verifizieren und Payload extrahieren
     */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }
        
        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        
        // Signatur prüfen
        $expectedSignature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, self::getAppKey(), true);
        $signature = base64url_decode($signatureEncoded);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        
        $payload = json_decode(base64url_decode($payloadEncoded), true);
        
        if (!$payload) {
            return null;
        }
        
        // Token-Ablauf prüfen
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Verschlüsselungsschlüssel abrufen
     */
    private static function getEncryptionKey(): string
    {
        $key = env('ENCRYPTION_KEY');
        
        if (!$key) {
            throw new \RuntimeException('ENCRYPTION_KEY not set in environment');
        }
        
        $decoded = base64_decode($key, true);
        
        if ($decoded === false || strlen($decoded) !== self::KEY_LENGTH) {
            throw new \RuntimeException('Invalid ENCRYPTION_KEY format or length');
        }
        
        return $decoded;
    }
    
    /**
     * App-Schlüssel abrufen
     */
    private static function getAppKey(): string
    {
        $key = env('APP_KEY');
        
        if (!$key) {
            throw new \RuntimeException('APP_KEY not set in environment');
        }
        
        return $key;
    }
    
    /**
     * Verschlüsselungsschlüssel generieren
     */
    public static function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(self::KEY_LENGTH));
    }
    
    /**
     * App-Schlüssel generieren
     */
    public static function generateAppKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}

/**
 * Base64 URL-safe Encoding/Decoding
 */
if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode')) {
    function base64url_decode(string $data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}