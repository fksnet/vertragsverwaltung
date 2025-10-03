<?php

/**
 * Application Entry Point für Vertragsverwaltung
 * 
 * @package Public
 * @author GenSpark AI Developer
 */

declare(strict_types=1);

// Performance Monitoring starten
$start_time = microtime(true);

// Error Reporting für Development
if (file_exists(__DIR__ . '/../.env')) {
    $envContent = file_get_contents(__DIR__ . '/../.env');
    if (str_contains($envContent, 'APP_DEBUG=true') || str_contains($envContent, 'APP_ENV=development')) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }
}

// Composer Autoloader
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Environment laden
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }

    // Session-Konfiguration
    $sessionConfig = config('session');
    if ($sessionConfig) {
        session_set_cookie_params([
            'lifetime' => $sessionConfig['lifetime'],
            'path' => $sessionConfig['path'],
            'domain' => $sessionConfig['domain'] ?? '',
            'secure' => $sessionConfig['secure'],
            'httponly' => $sessionConfig['httponly'],
            'samesite' => $sessionConfig['samesite']
        ]);
    }

    // Session starten
    if (!session_id()) {
        session_start();
    }

    // Installer Check - wenn .env fehlt oder DB-Config fehlt
    if (!file_exists(__DIR__ . '/../.env') || !env('DB_DATABASE')) {
        // Redirect zum Installer
        if (!str_contains($_SERVER['REQUEST_URI'], '/installer/')) {
            header('Location: /installer/');
            exit;
        }
    }

    // Security Headers setzen
    $csp = config('csp');
    if ($csp) {
        $cspDirectives = [];
        foreach ($csp as $directive => $sources) {
            $cspDirectives[] = str_replace('_', '-', $directive) . ' ' . implode(' ', $sources);
        }
        header('Content-Security-Policy: ' . implode('; ', $cspDirectives));
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Shared View Data
    \App\Core\View::share([
        'app_name' => config('app.name'),
        'user' => auth(),
        'csrf_token' => csrf_token(),
    ]);

    // Router initialisieren
    $router = new \App\Core\Router();

    // Standard-Middlewares registrieren
    $router->middleware('csrf', [\App\Core\CSRF::class, 'middleware']);
    $router->middleware('auth', [\App\Core\Auth::class, 'middleware']);
    $router->middleware('rbac', [\App\Core\RBAC::class, 'middleware']);

    // Routes laden
    if (file_exists(__DIR__ . '/../routes/web.php')) {
        require __DIR__ . '/../routes/web.php';
    }

    // Request dispatchen
    $response = $router->dispatch();

    // Response ausgeben
    if (is_string($response)) {
        echo $response;
    } elseif (is_array($response)) {
        json_response($response);
    }

} catch (\Throwable $e) {
    // Error Handling
    if (env('APP_DEBUG', false)) {
        // Debug-Modus: Detaillierte Fehleranzeige
        echo '<h1>Application Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        echo '<h2>Stack Trace:</h2>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        // Production: Generische Fehlermeldung
        http_response_code(500);
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            // AJAX Request
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Internal Server Error']);
        } else {
            // Normal Request
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Fehler</title>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; }
        .error { color: #e74c3c; }
    </style>
</head>
<body>
    <h1 class="error">Entschuldigung!</h1>
    <p>Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.</p>
    <p><a href="/">← Zur Startseite</a></p>
</body>
</html>';
        }
    }
    
    // Fehler loggen (falls Logging verfügbar)
    if (function_exists('error_log')) {
        error_log(sprintf(
            '[%s] %s in %s:%d',
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));
    }
}

// Performance Info für Debug
if (env('APP_DEBUG', false)) {
    $execution_time = microtime(true) - $start_time;
    $memory_usage = memory_get_peak_usage(true);
    
    echo sprintf(
        '<!-- Execution Time: %.3fs | Memory Usage: %s -->',
        $execution_time,
        number_format($memory_usage / 1024 / 1024, 2) . ' MB'
    );
}

// Session Cleanup (alte Input-Daten nach Request löschen)
if (isset($_SESSION['_old_input'])) {
    unset($_SESSION['_old_input']);
}

if (isset($_SESSION['_errors'])) {
    unset($_SESSION['_errors']);
}