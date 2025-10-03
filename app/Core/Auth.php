<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Authentication System mit Session-Management
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class Auth
{
    private const SESSION_KEY = '_auth_user_id';
    private const LOGIN_ATTEMPTS_KEY = '_login_attempts';
    private const RATE_LIMIT_KEY = '_rate_limit_timestamp';
    
    private static ?User $user = null;

    /**
     * Benutzer einloggen
     */
    public static function login(string $email, string $password, bool $remember = false): bool
    {
        // Rate Limiting prüfen
        if (!self::checkRateLimit($email)) {
            return false;
        }

        // Benutzer suchen
        $userData = Database::table('benutzer')
            ->where('email', $email)
            ->where('aktiv', true)
            ->first();

        if (!$userData) {
            self::recordFailedAttempt($email);
            return false;
        }

        // Passwort prüfen
        if (!password_verify($password, $userData['passwort_hash'])) {
            self::recordFailedAttempt($email);
            return false;
        }

        // Passwort-Upgrade bei Bedarf
        $passwordConfig = config('security.password_options');
        if (password_needs_rehash($userData['passwort_hash'], config('security.password_algo'), $passwordConfig)) {
            $newHash = password_hash($password, config('security.password_algo'), $passwordConfig);
            Database::table('benutzer')
                ->where('id', $userData['id'])
                ->update(['passwort_hash' => $newHash]);
        }

        // Session setzen
        self::setUserSession($userData);
        
        // Session regenerieren für Sicherheit
        session_regenerate_id(true);
        
        // Erfolgreiches Login loggen
        AuditLog::log('login', 'user', $userData['id'], [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Failed Attempts zurücksetzen
        self::clearFailedAttempts($email);
        
        // Remember Me Cookie (optional)
        if ($remember) {
            self::setRememberToken($userData['id']);
        }

        return true;
    }

    /**
     * 2FA-Login durchführen
     */
    public static function loginWith2FA(int $userId, string $totpCode): bool
    {
        $userData = Database::table('benutzer')->find($userId);
        
        if (!$userData || !$userData['2fa_secret_enc']) {
            return false;
        }

        // 2FA-Secret entschlüsseln und TOTP validieren
        $secret = Crypto::decrypt($userData['2fa_secret_enc']);
        
        if (!TOTP::verify($secret, $totpCode)) {
            return false;
        }

        // Benutzer einloggen
        self::setUserSession($userData);
        session_regenerate_id(true);
        
        AuditLog::log('login_2fa', 'user', $userData['id']);
        
        return true;
    }

    /**
     * Benutzer ausloggen
     */
    public static function logout(): void
    {
        $userId = self::id();
        
        // Logout loggen
        if ($userId) {
            AuditLog::log('logout', 'user', $userId);
        }
        
        // Session-Daten löschen
        unset($_SESSION[self::SESSION_KEY]);
        self::$user = null;
        
        // Remember Token löschen
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
            
            // Token aus Datenbank löschen
            if ($userId) {
                Database::table('benutzer')
                    ->where('id', $userId)
                    ->update(['remember_token' => null]);
            }
        }
        
        // Session zerstören
        session_destroy();
    }

    /**
     * Aktuell eingeloggten Benutzer abrufen
     */
    public static function user(): ?User
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $userId = self::id();
        
        if (!$userId) {
            return null;
        }

        // Benutzer mit Rolle laden
        $userData = Database::table('benutzer as b')
            ->select(['b.*', 'r.name as rolle_name'])
            ->leftJoin('rollen as r', 'b.rolle_id', '=', 'r.id')
            ->where('b.id', $userId)
            ->where('b.aktiv', true)
            ->first();

        if ($userData) {
            self::$user = new User($userData);
        }

        return self::$user;
    }

    /**
     * Benutzer-ID abrufen
     */
    public static function id(): ?int
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Eingeloggt-Status prüfen
     */
    public static function check(): bool
    {
        return self::id() !== null && self::user() !== null;
    }

    /**
     * Guest-Status prüfen
     */
    public static function guest(): bool
    {
        return !self::check();
    }

    /**
     * Auth-Middleware
     */
    public static function middleware($route, $next)
    {
        // Remember Token prüfen falls Session abgelaufen
        if (!self::check()) {
            self::checkRememberToken();
        }

        if (!self::check()) {
            // Redirect zu Login
            if (self::isAjaxRequest()) {
                json_response(['error' => 'Authentication required', 'redirect' => '/login'], 401);
                return;
            }
            
            // Current URL für Redirect nach Login speichern
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            redirect('/login');
            return;
        }

        return $next();
    }

    /**
     * User-Session setzen
     */
    private static function setUserSession(array $userData): void
    {
        $_SESSION[self::SESSION_KEY] = (int) $userData['id'];
        $_SESSION['login_time'] = time();
        
        // User-Objekt cachen
        self::$user = new User($userData);
    }

    /**
     * Rate Limiting prüfen
     */
    private static function checkRateLimit(string $email): bool
    {
        $attempts = $_SESSION[self::LOGIN_ATTEMPTS_KEY][$email] ?? 0;
        $lastAttempt = $_SESSION[self::RATE_LIMIT_KEY][$email] ?? 0;
        
        $maxAttempts = config('security.login_rate_limit', 5);
        $window = config('security.login_rate_window', 900); // 15 Minuten
        
        // Zeitfenster abgelaufen -> Reset
        if ((time() - $lastAttempt) > $window) {
            unset($_SESSION[self::LOGIN_ATTEMPTS_KEY][$email]);
            unset($_SESSION[self::RATE_LIMIT_KEY][$email]);
            return true;
        }
        
        return $attempts < $maxAttempts;
    }

    /**
     * Fehlgeschlagenen Login-Versuch aufzeichnen
     */
    private static function recordFailedAttempt(string $email): void
    {
        $_SESSION[self::LOGIN_ATTEMPTS_KEY][$email] = 
            ($_SESSION[self::LOGIN_ATTEMPTS_KEY][$email] ?? 0) + 1;
        $_SESSION[self::RATE_LIMIT_KEY][$email] = time();
        
        // Audit Log
        AuditLog::log('login_failed', 'auth', null, [
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }

    /**
     * Failed Attempts für Benutzer löschen
     */
    private static function clearFailedAttempts(string $email): void
    {
        unset($_SESSION[self::LOGIN_ATTEMPTS_KEY][$email]);
        unset($_SESSION[self::RATE_LIMIT_KEY][$email]);
    }

    /**
     * Remember Token setzen
     */
    private static function setRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        
        // Token in Datenbank speichern
        Database::table('benutzer')
            ->where('id', $userId)
            ->update([
                'remember_token' => $hashedToken,
                'remember_token_expires' => date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)) // 30 Tage
            ]);
        
        // Cookie setzen
        setcookie(
            'remember_token',
            $token,
            [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'secure' => config('session.secure', false),
                'httponly' => true,
                'samesite' => config('session.samesite', 'Lax')
            ]
        );
    }

    /**
     * Remember Token prüfen
     */
    private static function checkRememberToken(): void
    {
        $token = $_COOKIE['remember_token'] ?? null;
        
        if (!$token) {
            return;
        }
        
        $hashedToken = hash('sha256', $token);
        
        $userData = Database::table('benutzer')
            ->where('remember_token', $hashedToken)
            ->where('remember_token_expires', '>', date('Y-m-d H:i:s'))
            ->where('aktiv', true)
            ->first();
        
        if ($userData) {
            self::setUserSession($userData);
            session_regenerate_id(true);
            
            // Token erneuern
            self::setRememberToken((int) $userData['id']);
            
            AuditLog::log('login_remember', 'user', $userData['id']);
        } else {
            // Invalid/expired token -> Cookie löschen
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }

    /**
     * AJAX-Request erkennen
     */
    private static function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Passwort-Reset-Token generieren
     */
    public static function generatePasswordResetToken(string $email): ?string
    {
        $userData = Database::table('benutzer')
            ->where('email', $email)
            ->where('aktiv', true)
            ->first();
        
        if (!$userData) {
            return null;
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 Stunde
        
        Database::table('benutzer')
            ->where('id', $userData['id'])
            ->update([
                'reset_token' => hash('sha256', $token),
                'reset_token_expires' => $expires
            ]);
        
        return $token;
    }

    /**
     * Passwort mit Reset-Token ändern
     */
    public static function resetPassword(string $token, string $password): bool
    {
        $hashedToken = hash('sha256', $token);
        
        $userData = Database::table('benutzer')
            ->where('reset_token', $hashedToken)
            ->where('reset_token_expires', '>', date('Y-m-d H:i:s'))
            ->first();
        
        if (!$userData) {
            return false;
        }
        
        $passwordHash = password_hash(
            $password, 
            config('security.password_algo'), 
            config('security.password_options')
        );
        
        Database::table('benutzer')
            ->where('id', $userData['id'])
            ->update([
                'passwort_hash' => $passwordHash,
                'reset_token' => null,
                'reset_token_expires' => null
            ]);
        
        AuditLog::log('password_reset', 'user', $userData['id']);
        
        return true;
    }
}