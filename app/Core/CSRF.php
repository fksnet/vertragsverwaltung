<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF-Schutz mit Token-basierter Validierung
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */
class CSRF
{
    private const TOKEN_NAME = '_token';
    private const SESSION_KEY = '_csrf_tokens';

    /**
     * CSRF-Token generieren oder aus Session abrufen
     */
    public static function token(): string
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        // Bestehenden Token verwenden oder neuen generieren
        $token = $_SESSION[self::SESSION_KEY]['current'] ?? null;
        
        if (!$token || self::isTokenExpired($token)) {
            $token = self::generateToken();
            $_SESSION[self::SESSION_KEY]['current'] = $token;
            $_SESSION[self::SESSION_KEY]['timestamp'] = time();
        }

        return $token;
    }

    /**
     * CSRF-Token validieren
     */
    public static function validate(?string $token = null): bool
    {
        // Token aus POST-Daten oder Parameter
        if ($token === null) {
            $token = $_POST[self::TOKEN_NAME] ?? $_GET[self::TOKEN_NAME] ?? null;
        }

        if (!$token) {
            return false;
        }

        // Session-Token prüfen
        $sessionToken = $_SESSION[self::SESSION_KEY]['current'] ?? null;
        
        if (!$sessionToken) {
            return false;
        }

        // Token-Vergleich mit timing-safe Vergleich
        if (!hash_equals($sessionToken, $token)) {
            return false;
        }

        // Token-Alter prüfen
        if (self::isTokenExpired($sessionToken)) {
            self::clearToken();
            return false;
        }

        return true;
    }

    /**
     * CSRF-Token rotieren (nach erfolgreichem Request)
     */
    public static function rotateToken(): void
    {
        $newToken = self::generateToken();
        $_SESSION[self::SESSION_KEY]['current'] = $newToken;
        $_SESSION[self::SESSION_KEY]['timestamp'] = time();
    }

    /**
     * CSRF-Token löschen
     */
    public static function clearToken(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * CSRF-Middleware
     */
    public static function middleware($route, $next)
    {
        // GET-Requests sind von CSRF ausgenommen
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return $next();
        }

        // CSRF-Token validieren
        if (!self::validate()) {
            self::handleCsrfFailure();
            return;
        }

        // Nach erfolgreicher Validierung Token rotieren
        self::rotateToken();

        return $next();
    }

    /**
     * Neuen Token generieren
     */
    private static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Token-Ablauf prüfen
     */
    private static function isTokenExpired(string $token): bool
    {
        $timestamp = $_SESSION[self::SESSION_KEY]['timestamp'] ?? 0;
        $lifetime = config('security.csrf_lifetime', 3600); // 1 Stunde Standard
        
        return (time() - $timestamp) > $lifetime;
    }

    /**
     * CSRF-Fehler behandeln
     */
    private static function handleCsrfFailure(): void
    {
        // AJAX-Request -> JSON-Response
        if (self::isAjaxRequest()) {
            http_response_code(419);
            json_response([
                'error' => 'CSRF token mismatch',
                'message' => 'Ihre Sitzung ist abgelaufen. Bitte laden Sie die Seite neu.',
                'code' => 419
            ], 419);
            return;
        }

        // Normal Request -> Flash-Message und Redirect
        flash('error', 'Sicherheitsfehler: Ihre Sitzung ist abgelaufen. Bitte versuchen Sie es erneut.');
        
        $referer = $_SERVER['HTTP_REFERER'] ?? url('/');
        redirect($referer);
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
     * Double Submit Cookie Pattern (zusätzliche Sicherheit)
     */
    public static function setDoubleCookie(): void
    {
        $token = self::token();
        
        setcookie(
            'csrf_token', 
            $token, 
            [
                'expires' => time() + config('security.csrf_lifetime', 3600),
                'path' => '/',
                'domain' => '',
                'secure' => config('session.secure', false),
                'httponly' => false, // JS muss darauf zugreifen können
                'samesite' => config('session.samesite', 'Lax')
            ]
        );
    }

    /**
     * Double Submit Cookie validieren
     */
    public static function validateDoubleCookie(): bool
    {
        $sessionToken = self::token();
        $cookieToken = $_COOKIE['csrf_token'] ?? null;
        
        if (!$cookieToken) {
            return false;
        }
        
        return hash_equals($sessionToken, $cookieToken);
    }

    /**
     * Meta-Tag für JavaScript generieren
     */
    public static function metaTag(): string
    {
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            e(self::token())
        );
    }

    /**
     * JavaScript für htmx-Integration
     */
    public static function htmxScript(): string
    {
        return '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // CSRF-Token für htmx-Requests
    document.body.addEventListener("htmx:configRequest", function(evt) {
        var token = document.querySelector("meta[name=csrf-token]");
        if (token) {
            evt.detail.headers["X-CSRF-Token"] = token.getAttribute("content");
        }
    });
    
    // CSRF-Fehler behandeln
    document.body.addEventListener("htmx:responseError", function(evt) {
        if (evt.detail.xhr.status === 419) {
            alert("Ihre Sitzung ist abgelaufen. Die Seite wird neu geladen.");
            window.location.reload();
        }
    });
});
</script>';
    }
}