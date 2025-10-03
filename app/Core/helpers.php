<?php

/**
 * Global Helper Functions für Vertragsverwaltung
 * 
 * @package App\Core
 * @author GenSpark AI Developer
 */

if (!function_exists('env')) {
    /**
     * Umgebungsvariable abrufen mit Fallback
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value
        };
    }
}

if (!function_exists('config')) {
    /**
     * Konfigurationswert abrufen
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        
        if ($config === null) {
            $configPath = __DIR__ . '/../../config/config.php';
            $config = file_exists($configPath) ? require $configPath : [];
        }
        
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
}

if (!function_exists('view')) {
    /**
     * View rendern
     */
    function view(string $name, array $data = []): string
    {
        return \App\Core\View::render($name, $data);
    }
}

if (!function_exists('redirect')) {
    /**
     * HTTP-Redirect durchführen
     */
    function redirect(string $url, int $code = 302): void
    {
        header("Location: $url", true, $code);
        exit;
    }
}

if (!function_exists('url')) {
    /**
     * URL generieren
     */
    function url(string $path = ''): string
    {
        $baseUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Asset-URL generieren
     */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('e')) {
    /**
     * HTML-Escaping für Sicherheit
     */
    function e(mixed $value, bool $doubleEncode = true): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8', $doubleEncode);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * CSRF-Token generieren/abrufen
     */
    function csrf_token(): string
    {
        return \App\Core\CSRF::token();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * CSRF-Hidden-Field für Formulare
     */
    function csrf_field(): string
    {
        $token = csrf_token();
        return '<input type="hidden" name="_token" value="' . e($token) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Alten Input-Wert nach Fehler wiederherstellen
     */
    function old(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Flash-Message setzen
     */
    function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }
}

if (!function_exists('errors')) {
    /**
     * Validierungsfehler abrufen
     */
    function errors(?string $key = null): mixed
    {
        $errors = $_SESSION['_errors'] ?? [];
        
        if ($key === null) {
            return $errors;
        }
        
        return $errors[$key] ?? null;
    }
}

if (!function_exists('auth')) {
    /**
     * Authentifizierten Benutzer abrufen
     */
    function auth(): ?\App\Models\User
    {
        return \App\Core\Auth::user();
    }
}

if (!function_exists('can')) {
    /**
     * Berechtigung prüfen
     */
    function can(string $action, string $resource, ?array $context = null): bool
    {
        return \App\Core\RBAC::can($action, $resource, $context);
    }
}

if (!function_exists('json_response')) {
    /**
     * JSON-Response senden
     */
    function json_response(mixed $data, int $status = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and Die - für Debugging
     */
    function dd(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        exit;
    }
}

if (!function_exists('format_currency')) {
    /**
     * Geld-Betrag formatieren (Cent zu Euro)
     */
    function format_currency(int $cents): string
    {
        $euros = $cents / 100;
        return number_format($euros, 2, ',', '.') . ' €';
    }
}

if (!function_exists('format_date')) {
    /**
     * Datum formatieren
     */
    function format_date(?string $date, string $format = 'd.m.Y'): string
    {
        if (!$date) {
            return '';
        }
        
        $dateTime = new DateTime($date);
        return $dateTime->format($format);
    }
}

if (!function_exists('app_path')) {
    /**
     * App-Pfad generieren
     */
    function app_path(string $path = ''): string
    {
        return __DIR__ . '/../' . ltrim($path, '/');
    }
}

if (!function_exists('storage_path')) {
    /**
     * Storage-Pfad generieren  
     */
    function storage_path(string $path = ''): string
    {
        return __DIR__ . '/../../public/uploads/' . ltrim($path, '/');
    }
}

if (!function_exists('validate_required')) {
    /**
     * Einfache Required-Validierung
     */
    function validate_required(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (str_contains($rule, 'required') && empty($data[$field])) {
                $errors[$field] = "Das Feld $field ist erforderlich.";
            }
        }
        
        return $errors;
    }
}