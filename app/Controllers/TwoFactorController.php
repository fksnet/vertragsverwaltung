<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Models\TwoFactorBackupCode;
use App\Core\Auth;
use App\Core\Logger;
use App\Core\Security;
use PragmaRX\Google2FA\Google2FA;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * TwoFactorController - Verwaltet Zwei-Faktor-Authentifizierung
 * 
 * Implementiert TOTP-basierte 2FA mit Google Authenticator Unterstützung.
 * Verwaltet Backup-Codes, QR-Code-Generierung und Verifizierung.
 * 
 * @package App\Controllers
 * @version 1.0.0
 * @author Vertragsverwaltung System
 */
class TwoFactorController extends BaseController
{
    private const BACKUP_CODES_COUNT = 8;
    private const BACKUP_CODE_LENGTH = 8;
    private const QR_CODE_SIZE = 200;
    private const TOTP_WINDOW = 2; // ±2 Zeitfenster (6 Minuten)
    
    private Google2FA $google2fa;

    public function __construct()
    {
        parent::__construct();
        $this->google2fa = new Google2FA();
    }

    /**
     * Zeigt die 2FA-Übersicht
     */
    public function index(): void
    {
        try {
            $this->authorize(['profile:view']);
            
            $user = Auth::user();
            
            // 2FA-Status ermitteln
            $twoFactorEnabled = !empty($user->two_factor_secret);
            
            // Backup-Codes zählen
            $unusedBackupCodes = 0;
            if ($twoFactorEnabled) {
                $unusedBackupCodes = TwoFactorBackupCode::where('user_id', $user->id)
                    ->where('used_at', null)
                    ->count();
            }
            
            // Letzte Verwendungen
            $recentUsage = [];
            if ($twoFactorEnabled) {
                $recentUsage = TwoFactorBackupCode::where('user_id', $user->id)
                    ->whereNotNull('used_at')
                    ->orderBy('used_at', 'desc')
                    ->limit(5)
                    ->get(['code_hash', 'used_at', 'used_ip']);
            }
            
            // Sicherheitseinstellungen
            $securitySettings = [
                'force_2fa' => $user->force_two_factor ?? false,
                'remember_2fa_device' => $user->remember_2fa_device ?? false,
                'backup_codes_regenerated_at' => $user->two_factor_backup_codes_generated_at
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'two_factor_enabled' => $twoFactorEnabled,
                    'backup_codes_count' => $unusedBackupCodes,
                    'recent_usage' => $recentUsage,
                    'security_settings' => $securitySettings,
                    'app_name' => config('app.name', 'Vertragsverwaltung')
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der 2FA-Übersicht', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der 2FA-Übersicht'], 500);
        }
    }

    /**
     * Startet die 2FA-Einrichtung
     */
    public function setup(): void
    {
        try {
            $this->authorize(['profile:edit']);
            
            $user = Auth::user();
            
            // Prüfen ob 2FA bereits aktiviert ist
            if (!empty($user->two_factor_secret)) {
                $this->jsonResponse(['error' => '2FA ist bereits aktiviert'], 400);
                return;
            }
            
            // Neuen Secret generieren
            $secret = $this->google2fa->generateSecretKey();
            
            // Temporär speichern (noch nicht aktiviert)
            $user->update(['two_factor_secret_temp' => $secret]);
            
            // QR-Code URL generieren
            $appName = config('app.name', 'Vertragsverwaltung');
            $qrCodeUrl = $this->google2fa->getQRCodeUrl(
                $appName,
                $user->email,
                $secret
            );
            
            // QR-Code als Data-URL generieren
            $qrCodeDataUrl = $this->generateQRCodeDataUrl($qrCodeUrl);
            
            Logger::info('2FA-Einrichtung gestartet', [
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'secret' => $secret,
                    'qr_code_url' => $qrCodeUrl,
                    'qr_code_data_url' => $qrCodeDataUrl,
                    'manual_entry_key' => chunk_split($secret, 4, ' '),
                    'app_name' => $appName,
                    'account_name' => $user->email
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei der 2FA-Einrichtung', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler bei der 2FA-Einrichtung'], 500);
        }
    }

    /**
     * Bestätigt und aktiviert 2FA
     */
    public function confirm(): void
    {
        try {
            $this->authorize(['profile:edit']);
            
            $user = Auth::user();
            
            // Validierung
            $rules = [
                'code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
                'password' => 'required|string'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Passwort verifizieren
            if (!password_verify($data['password'], $user->password)) {
                $this->jsonResponse(['error' => 'Ungültiges Passwort'], 400);
                return;
            }
            
            // Temporären Secret prüfen
            $tempSecret = $user->two_factor_secret_temp;
            if (empty($tempSecret)) {
                $this->jsonResponse(['error' => 'Keine 2FA-Einrichtung in Bearbeitung'], 400);
                return;
            }
            
            // TOTP-Code verifizieren
            $isValid = $this->google2fa->verifyKey($tempSecret, $data['code'], self::TOTP_WINDOW);
            
            if (!$isValid) {
                Logger::warning('2FA-Bestätigung fehlgeschlagen - ungültiger Code', [
                    'user_id' => $user->id,
                    'code' => $data['code']
                ]);
                
                $this->jsonResponse(['error' => 'Ungültiger Authentifizierungscode'], 400);
                return;
            }
            
            // 2FA aktivieren
            $backupCodes = $this->generateBackupCodes();
            
            $user->update([
                'two_factor_secret' => $tempSecret,
                'two_factor_secret_temp' => null,
                'two_factor_enabled_at' => date('Y-m-d H:i:s'),
                'two_factor_backup_codes_generated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Backup-Codes speichern
            $this->saveBackupCodes($user->id, $backupCodes);
            
            Logger::info('2FA erfolgreich aktiviert', [
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => '2FA erfolgreich aktiviert',
                'data' => [
                    'backup_codes' => $backupCodes,
                    'backup_codes_count' => count($backupCodes)
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei der 2FA-Bestätigung', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler bei der 2FA-Bestätigung'], 500);
        }
    }

    /**
     * Deaktiviert 2FA
     */
    public function disable(): void
    {
        try {
            $this->authorize(['profile:edit']);
            
            $user = Auth::user();
            
            // Prüfen ob 2FA aktiviert ist
            if (empty($user->two_factor_secret)) {
                $this->jsonResponse(['error' => '2FA ist nicht aktiviert'], 400);
                return;
            }
            
            // Validierung
            $rules = [
                'password' => 'required|string',
                'confirmation_method' => 'required|string|in:totp,backup_code',
                'code' => 'required|string'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Passwort verifizieren
            if (!password_verify($data['password'], $user->password)) {
                $this->jsonResponse(['error' => 'Ungültiges Passwort'], 400);
                return;
            }
            
            // 2FA-Code verifizieren
            $codeValid = false;
            
            if ($data['confirmation_method'] === 'totp') {
                // TOTP-Code prüfen
                if (preg_match('/^[0-9]{6}$/', $data['code'])) {
                    $codeValid = $this->google2fa->verifyKey(
                        $user->two_factor_secret, 
                        $data['code'], 
                        self::TOTP_WINDOW
                    );
                }
            } elseif ($data['confirmation_method'] === 'backup_code') {
                // Backup-Code prüfen
                $codeValid = $this->useBackupCode($user->id, $data['code']);
            }
            
            if (!$codeValid) {
                Logger::warning('2FA-Deaktivierung fehlgeschlagen - ungültiger Code', [
                    'user_id' => $user->id,
                    'method' => $data['confirmation_method']
                ]);
                
                $this->jsonResponse(['error' => 'Ungültiger Authentifizierungscode'], 400);
                return;
            }
            
            // 2FA deaktivieren
            $user->update([
                'two_factor_secret' => null,
                'two_factor_secret_temp' => null,
                'two_factor_enabled_at' => null,
                'two_factor_backup_codes_generated_at' => null,
                'remember_2fa_device' => false
            ]);
            
            // Alle Backup-Codes löschen
            TwoFactorBackupCode::where('user_id', $user->id)->delete();
            
            // Alle vertrauenswürdigen Geräte zurücksetzen
            $user->update(['trusted_devices' => null]);
            
            Logger::info('2FA deaktiviert', [
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => '2FA erfolgreich deaktiviert'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei der 2FA-Deaktivierung', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler bei der 2FA-Deaktivierung'], 500);
        }
    }

    /**
     * Verifiziert einen 2FA-Code beim Login
     */
    public function verify(): void
    {
        try {
            // Keine Auth erforderlich - Teil des Login-Prozesses
            
            $rules = [
                'user_id' => 'required|integer|exists:users,id',
                'code' => 'required|string',
                'remember_device' => 'boolean',
                'login_token' => 'required|string' // Temporärer Token vom Login
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $user = User::find($data['user_id']);
            if (!$user || empty($user->two_factor_secret)) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden oder 2FA nicht aktiviert'], 404);
                return;
            }
            
            // Login-Token verifizieren
            $sessionKey = "2fa_login_token_{$user->id}";
            $storedToken = $_SESSION[$sessionKey] ?? null;
            
            if (!$storedToken || !hash_equals($storedToken, $data['login_token'])) {
                $this->jsonResponse(['error' => 'Ungültiger Login-Token'], 400);
                return;
            }
            
            $codeValid = false;
            $usedBackupCode = false;
            
            // Code-Format bestimmen
            if (preg_match('/^[0-9]{6}$/', $data['code'])) {
                // TOTP-Code
                $codeValid = $this->google2fa->verifyKey(
                    $user->two_factor_secret, 
                    $data['code'], 
                    self::TOTP_WINDOW
                );
            } elseif (preg_match('/^[A-Z0-9]{8}$/', strtoupper($data['code']))) {
                // Backup-Code
                $codeValid = $this->useBackupCode($user->id, strtoupper($data['code']));
                $usedBackupCode = $codeValid;
            }
            
            if (!$codeValid) {
                Logger::warning('2FA-Verifizierung fehlgeschlagen', [
                    'user_id' => $user->id,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                
                $this->jsonResponse(['error' => 'Ungültiger Authentifizierungscode'], 400);
                return;
            }
            
            // Login-Token löschen
            unset($_SESSION[$sessionKey]);
            
            // Benutzer einloggen
            Auth::login($user);
            
            // Gerät als vertrauenswürdig speichern
            if ($data['remember_device'] ?? false) {
                $this->addTrustedDevice($user);
            }
            
            // Verbleibenende Backup-Codes zählen
            $remainingBackupCodes = TwoFactorBackupCode::where('user_id', $user->id)
                ->where('used_at', null)
                ->count();
            
            Logger::info('2FA-Verifizierung erfolgreich', [
                'user_id' => $user->id,
                'used_backup_code' => $usedBackupCode,
                'remaining_backup_codes' => $remainingBackupCodes
            ]);
            
            $response = [
                'success' => true,
                'message' => '2FA-Verifizierung erfolgreich',
                'data' => [
                    'user' => $user->toArray(),
                    'used_backup_code' => $usedBackupCode,
                    'remaining_backup_codes' => $remainingBackupCodes
                ]
            ];
            
            // Warnung bei wenigen Backup-Codes
            if ($usedBackupCode && $remainingBackupCodes <= 2) {
                $response['warning'] = "Nur noch {$remainingBackupCodes} Backup-Codes verfügbar. Generieren Sie neue Codes.";
            }
            
            $this->jsonResponse($response);
            
        } catch (\Exception $e) {
            Logger::error('Fehler bei der 2FA-Verifizierung', [
                'error' => $e->getMessage(),
                'user_id' => $data['user_id'] ?? null
            ]);
            $this->jsonResponse(['error' => 'Fehler bei der 2FA-Verifizierung'], 500);
        }
    }

    /**
     * Generiert neue Backup-Codes
     */
    public function regenerateBackupCodes(): void
    {
        try {
            $this->authorize(['profile:edit']);
            
            $user = Auth::user();
            
            // Prüfen ob 2FA aktiviert ist
            if (empty($user->two_factor_secret)) {
                $this->jsonResponse(['error' => '2FA ist nicht aktiviert'], 400);
                return;
            }
            
            // Validierung
            $rules = [
                'password' => 'required|string'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            // Passwort verifizieren
            if (!password_verify($data['password'], $user->password)) {
                $this->jsonResponse(['error' => 'Ungültiges Passwort'], 400);
                return;
            }
            
            // Alte Backup-Codes löschen
            TwoFactorBackupCode::where('user_id', $user->id)->delete();
            
            // Neue Backup-Codes generieren
            $backupCodes = $this->generateBackupCodes();
            $this->saveBackupCodes($user->id, $backupCodes);
            
            // Timestamp aktualisieren
            $user->update([
                'two_factor_backup_codes_generated_at' => date('Y-m-d H:i:s')
            ]);
            
            Logger::info('Backup-Codes regeneriert', [
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Neue Backup-Codes erfolgreich generiert',
                'data' => [
                    'backup_codes' => $backupCodes,
                    'backup_codes_count' => count($backupCodes)
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Regenerieren der Backup-Codes', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Generieren der Backup-Codes'], 500);
        }
    }

    /**
     * Zeigt die aktuellen Backup-Codes (einmalig nach Generierung)
     */
    public function showBackupCodes(): void
    {
        try {
            $this->authorize(['profile:view']);
            
            $user = Auth::user();
            
            // Prüfen ob 2FA aktiviert ist
            if (empty($user->two_factor_secret)) {
                $this->jsonResponse(['error' => '2FA ist nicht aktiviert'], 400);
                return;
            }
            
            // Backup-Codes laden (nur unbenutzte)
            $backupCodes = TwoFactorBackupCode::where('user_id', $user->id)
                ->where('used_at', null)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function($code) {
                    return [
                        'id' => $code->id,
                        'code' => $code->getDecryptedCode(),
                        'created_at' => $code->created_at
                    ];
                });
            
            // Verwendete Codes (für Info)
            $usedCodesCount = TwoFactorBackupCode::where('user_id', $user->id)
                ->whereNotNull('used_at')
                ->count();
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'backup_codes' => $backupCodes,
                    'unused_count' => $backupCodes->count(),
                    'used_count' => $usedCodesCount,
                    'total_generated' => $backupCodes->count() + $usedCodesCount,
                    'generated_at' => $user->two_factor_backup_codes_generated_at
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Anzeigen der Backup-Codes', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Backup-Codes'], 500);
        }
    }

    /**
     * Prüft ob ein Gerät vertrauenswürdig ist
     */
    public function checkTrustedDevice(): void
    {
        try {
            $userId = $_GET['user_id'] ?? null;
            
            if (!$userId) {
                $this->jsonResponse(['error' => 'Benutzer-ID erforderlich'], 400);
                return;
            }
            
            $user = User::find($userId);
            if (!$user) {
                $this->jsonResponse(['error' => 'Benutzer nicht gefunden'], 404);
                return;
            }
            
            $deviceFingerprint = $this->getDeviceFingerprint();
            $isTrusted = $this->isTrustedDevice($user, $deviceFingerprint);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'is_trusted' => $isTrusted,
                    'two_factor_required' => !$isTrusted && !empty($user->two_factor_secret)
                ]
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Prüfen des vertrauenswürdigen Geräts', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Prüfen des Geräts'], 500);
        }
    }

    /**
     * Entfernt alle vertrauenswürdigen Geräte
     */
    public function clearTrustedDevices(): void
    {
        try {
            $this->authorize(['profile:edit']);
            
            $user = Auth::user();
            
            // Alle vertrauenswürdigen Geräte entfernen
            $user->update(['trusted_devices' => null]);
            
            Logger::info('Alle vertrauenswürdigen Geräte entfernt', [
                'user_id' => $user->id
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Alle vertrauenswürdigen Geräte wurden entfernt'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Entfernen der vertrauenswürdigen Geräte', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Entfernen der Geräte'], 500);
        }
    }

    /**
     * Zeigt 2FA-Einstellungen
     */
    public function settings(): void
    {
        try {
            $this->authorize(['profile:view']);
            
            $user = Auth::user();
            
            // Vertrauenswürdige Geräte
            $trustedDevices = [];
            if ($user->trusted_devices) {
                $devices = json_decode($user->trusted_devices, true);
                foreach ($devices as $device) {
                    $trustedDevices[] = [
                        'fingerprint' => substr($device['fingerprint'], 0, 8) . '...',
                        'added_at' => $device['added_at'],
                        'last_used' => $device['last_used'] ?? null,
                        'user_agent' => $device['user_agent'] ?? 'Unbekannt'
                    ];
                }
            }
            
            $settings = [
                'two_factor_enabled' => !empty($user->two_factor_secret),
                'remember_device_enabled' => $user->remember_2fa_device ?? false,
                'trusted_devices_count' => count($trustedDevices),
                'trusted_devices' => $trustedDevices,
                'backup_codes_count' => TwoFactorBackupCode::where('user_id', $user->id)
                    ->where('used_at', null)
                    ->count(),
                'last_backup_codes_generated' => $user->two_factor_backup_codes_generated_at,
                'two_factor_enabled_at' => $user->two_factor_enabled_at
            ];
            
            $this->jsonResponse([
                'success' => true,
                'data' => $settings
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Laden der 2FA-Einstellungen', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Laden der Einstellungen'], 500);
        }
    }

    /**
     * Aktualisiert 2FA-Einstellungen
     */
    public function updateSettings(): void
    {
        try {
            $this->authorize(['profile:edit']);
            
            $user = Auth::user();
            
            $rules = [
                'remember_2fa_device' => 'boolean'
            ];
            
            $data = $this->validate($_POST, $rules);
            
            $user->update([
                'remember_2fa_device' => $data['remember_2fa_device'] ?? false
            ]);
            
            Logger::info('2FA-Einstellungen aktualisiert', [
                'user_id' => $user->id,
                'remember_device' => $data['remember_2fa_device'] ?? false
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'message' => '2FA-Einstellungen erfolgreich aktualisiert'
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Fehler beim Aktualisieren der 2FA-Einstellungen', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            $this->jsonResponse(['error' => 'Fehler beim Aktualisieren der Einstellungen'], 500);
        }
    }

    /**
     * Generiert Backup-Codes
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        
        for ($i = 0; $i < self::BACKUP_CODES_COUNT; $i++) {
            $codes[] = strtoupper(Security::generateToken(self::BACKUP_CODE_LENGTH, '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
        }
        
        return $codes;
    }

    /**
     * Speichert Backup-Codes verschlüsselt in der Datenbank
     */
    private function saveBackupCodes(int $userId, array $codes): void
    {
        foreach ($codes as $code) {
            TwoFactorBackupCode::create([
                'user_id' => $userId,
                'code_hash' => hash('sha256', $code),
                'encrypted_code' => Security::encrypt($code)
            ]);
        }
    }

    /**
     * Verwendet einen Backup-Code
     */
    private function useBackupCode(int $userId, string $code): bool
    {
        $codeHash = hash('sha256', $code);
        
        $backupCode = TwoFactorBackupCode::where('user_id', $userId)
            ->where('code_hash', $codeHash)
            ->where('used_at', null)
            ->first();
            
        if (!$backupCode) {
            return false;
        }
        
        // Code als verwendet markieren
        $backupCode->update([
            'used_at' => date('Y-m-d H:i:s'),
            'used_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        return true;
    }

    /**
     * Generiert QR-Code als Data-URL
     */
    private function generateQRCodeDataUrl(string $qrCodeUrl): string
    {
        try {
            $options = new QROptions([
                'version'    => 7,
                'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'   => QRCode::ECC_M,
                'scale'      => 8,
                'imageBase64' => true,
            ]);
            
            $qrcode = new QRCode($options);
            return $qrcode->render($qrCodeUrl);
            
        } catch (\Exception $e) {
            Logger::warning('QR-Code-Generierung fehlgeschlagen', [
                'error' => $e->getMessage()
            ]);
            
            return '';
        }
    }

    /**
     * Generiert Geräte-Fingerprint
     */
    private function getDeviceFingerprint(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        $fingerprint = hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding);
        
        return $fingerprint;
    }

    /**
     * Prüft ob das aktuelle Gerät vertrauenswürdig ist
     */
    private function isTrustedDevice(User $user, string $deviceFingerprint): bool
    {
        if (!$user->trusted_devices) {
            return false;
        }
        
        $devices = json_decode($user->trusted_devices, true);
        
        foreach ($devices as $device) {
            if ($device['fingerprint'] === $deviceFingerprint) {
                // Letzten Zugriff aktualisieren
                $device['last_used'] = date('Y-m-d H:i:s');
                
                // Gerät-Liste aktualisieren
                $updatedDevices = array_map(function($d) use ($device, $deviceFingerprint) {
                    return $d['fingerprint'] === $deviceFingerprint ? $device : $d;
                }, $devices);
                
                $user->update(['trusted_devices' => json_encode($updatedDevices)]);
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Fügt aktuelles Gerät zu vertrauenswürdigen Geräten hinzu
     */
    private function addTrustedDevice(User $user): void
    {
        if (!$user->remember_2fa_device) {
            return;
        }
        
        $deviceFingerprint = $this->getDeviceFingerprint();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $devices = [];
        if ($user->trusted_devices) {
            $devices = json_decode($user->trusted_devices, true);
        }
        
        // Prüfen ob Gerät bereits existiert
        foreach ($devices as &$device) {
            if ($device['fingerprint'] === $deviceFingerprint) {
                $device['last_used'] = date('Y-m-d H:i:s');
                $user->update(['trusted_devices' => json_encode($devices)]);
                return;
            }
        }
        
        // Neues Gerät hinzufügen
        $devices[] = [
            'fingerprint' => $deviceFingerprint,
            'user_agent' => $userAgent,
            'added_at' => date('Y-m-d H:i:s'),
            'last_used' => date('Y-m-d H:i:s')
        ];
        
        // Maximale Anzahl vertrauenswürdiger Geräte begrenzen (z.B. 5)
        if (count($devices) > 5) {
            // Älteste Geräte entfernen
            usort($devices, function($a, $b) {
                return strtotime($a['last_used'] ?? $a['added_at']) - strtotime($b['last_used'] ?? $b['added_at']);
            });
            $devices = array_slice($devices, -5);
        }
        
        $user->update(['trusted_devices' => json_encode($devices)]);
    }
}