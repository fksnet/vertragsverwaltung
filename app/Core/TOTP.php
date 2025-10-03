<?php

declare(strict_types=1);

namespace App\Core;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * TOTP (Time-based One-Time Password) für 2FA
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class TOTP
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    
    /**
     * TOTP-Secret generieren
     */
    public static function generateSecret(int $length = 32): string
    {
        $secret = '';
        $max = strlen(self::BASE32_CHARS) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $max)];
        }
        
        return $secret;
    }
    
    /**
     * TOTP-Code für aktuellen Zeitstempel generieren
     */
    public static function generate(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeWindow = intval($timestamp / 30); // 30-Sekunden-Fenster
        
        return self::generateCode($secret, $timeWindow);
    }
    
    /**
     * TOTP-Code verifizieren
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $timestamp = time();
        $currentWindow = intval($timestamp / 30);
        
        // Aktuelles + vorheriges/nächstes Zeitfenster prüfen
        for ($i = -$window; $i <= $window; $i++) {
            $testCode = self::generateCode($secret, $currentWindow + $i);
            
            if (hash_equals($testCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * QR-Code-URI für Authenticator-Apps generieren
     */
    public static function getQrCodeUri(string $secret, string $label, ?string $issuer = null): string
    {
        $issuer = $issuer ?? config('totp.issuer', config('app.name'));
        
        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => config('totp.algorithm', 'SHA1'),
            'digits' => config('totp.digits', 6),
            'period' => 30,
        ];
        
        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($label),
            http_build_query($params)
        );
    }
    
    /**
     * QR-Code als PNG generieren
     */
    public static function generateQrCode(string $secret, string $label, ?string $issuer = null): string
    {
        $uri = self::getQrCodeUri($secret, $label, $issuer);
        
        $qrCode = new QrCode($uri);
        $qrCode->setSize(200);
        $qrCode->setMargin(10);
        
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        
        return $result->getDataUri();
    }
    
    /**
     * Backup-Recovery-Codes generieren
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        
        for ($i = 0; $i < $count; $i++) {
            $codes[] = self::generateRecoveryCode();
        }
        
        return $codes;
    }
    
    /**
     * Einzelnen Recovery-Code generieren
     */
    public static function generateRecoveryCode(): string
    {
        // 8-stelliger alphanumerischer Code
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Mit Bindestrich für bessere Lesbarkeit
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }
    
    /**
     * Recovery-Code verifizieren
     */
    public static function verifyRecoveryCode(string $code, array $validCodes): bool
    {
        $code = strtoupper(str_replace('-', '', $code));
        
        foreach ($validCodes as $validCode) {
            $cleanValidCode = strtoupper(str_replace('-', '', $validCode));
            
            if (hash_equals($cleanValidCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * HOTP-Code generieren (basis für TOTP)
     */
    private static function generateCode(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        
        // Counter als 8-Byte big-endian
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        
        // HMAC-SHA1
        $hash = hash_hmac('sha1', $counterBytes, $key, true);
        
        // Dynamic truncation
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );
        
        $digits = config('totp.digits', 6);
        return str_pad((string) ($code % pow(10, $digits)), $digits, '0', STR_PAD_LEFT);
    }
    
    /**
     * Base32-String dekodieren
     */
    private static function base32Decode(string $input): string
    {
        $input = strtoupper($input);
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0, $j = strlen($input); $i < $j; $i++) {
            $c = $input[$i];
            
            if ($c >= 'A' && $c <= 'Z') {
                $v = ($v << 5) | (ord($c) - ord('A'));
            } elseif ($c >= '2' && $c <= '7') {
                $v = ($v << 5) | (ord($c) - ord('2') + 26);
            } elseif ($c === '=') {
                break;
            } else {
                continue; // Ungültige Zeichen ignorieren
            }
            
            $vbits += 5;
            
            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr($v >> $vbits);
                $v &= (1 << $vbits) - 1;
            }
        }
        
        return $output;
    }
    
    /**
     * 2FA für Benutzer aktivieren
     */
    public static function enableFor(int $userId, string $email): array
    {
        $secret = self::generateSecret();
        $recoveryCodes = self::generateRecoveryCodes();
        
        // QR-Code-URI generieren
        $qrUri = self::getQrCodeUri($secret, $email);
        
        // Daten verschlüsseln und in Datenbank speichern
        $encryptedSecret = Crypto::encrypt($secret);
        $encryptedCodes = Crypto::encrypt(json_encode($recoveryCodes));
        
        Database::table('benutzer')
            ->where('id', $userId)
            ->update([
                '2fa_secret_enc' => $encryptedSecret,
                'recovery_codes_enc' => $encryptedCodes,
                '2fa_enabled' => true,
            ]);
        
        AuditLog::log2FA('enable', $userId);
        
        return [
            'secret' => $secret,
            'qr_uri' => $qrUri,
            'recovery_codes' => $recoveryCodes,
        ];
    }
    
    /**
     * 2FA für Benutzer deaktivieren
     */
    public static function disableFor(int $userId): void
    {
        Database::table('benutzer')
            ->where('id', $userId)
            ->update([
                '2fa_secret_enc' => null,
                'recovery_codes_enc' => null,
                '2fa_enabled' => false,
            ]);
        
        AuditLog::log2FA('disable', $userId);
    }
    
    /**
     * Recovery-Code verwenden
     */
    public static function useRecoveryCode(int $userId, string $code): bool
    {
        $userData = Database::table('benutzer')->find($userId);
        
        if (!$userData || !$userData['recovery_codes_enc']) {
            return false;
        }
        
        $recoveryCodes = json_decode(Crypto::decrypt($userData['recovery_codes_enc']), true);
        
        if (!self::verifyRecoveryCode($code, $recoveryCodes)) {
            return false;
        }
        
        // Verwendeten Code entfernen
        $codeNormalized = strtoupper(str_replace('-', '', $code));
        $recoveryCodes = array_filter($recoveryCodes, function($validCode) use ($codeNormalized) {
            return strtoupper(str_replace('-', '', $validCode)) !== $codeNormalized;
        });
        
        // Aktualisierte Codes speichern
        Database::table('benutzer')
            ->where('id', $userId)
            ->update([
                'recovery_codes_enc' => Crypto::encrypt(json_encode(array_values($recoveryCodes))),
            ]);
        
        AuditLog::log('2fa_recovery_code_used', 'user', $userId);
        
        return true;
    }
    
    /**
     * Neue Recovery-Codes generieren
     */
    public static function regenerateRecoveryCodes(int $userId): array
    {
        $recoveryCodes = self::generateRecoveryCodes();
        
        Database::table('benutzer')
            ->where('id', $userId)
            ->update([
                'recovery_codes_enc' => Crypto::encrypt(json_encode($recoveryCodes)),
            ]);
        
        AuditLog::log2FA('backup_codes_generated', $userId);
        
        return $recoveryCodes;
    }
}