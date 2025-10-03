<?php
/**
 * Vertragsverwaltung - Entry Point
 * 
 * Haupteinstiegspunkt für die Vertragsverwaltung-Anwendung.
 * Kompatibel mit Standard-PHP-Webhosting-Umgebungen.
 * 
 * @package Vertragsverwaltung
 * @version 1.0.0
 * @author Vertragsverwaltung System
 */

// Fehlerberichterstattung für Entwicklung (wird in Produktion automatisch deaktiviert)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug-Modus für Installationsprobleme
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug) {
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
    echo '<h3>Debug-Info</h3>';
    echo '<p>ROOT_PATH: ' . __DIR__ . '</p>';
    echo '<p>Config exists: ' . (file_exists(__DIR__ . '/config/app.php') ? 'YES' : 'NO') . '</p>';
    echo '<p>Bootstrap exists: ' . (file_exists(__DIR__ . '/bootstrap/app.php') ? 'YES' : 'NO') . '</p>';
    echo '<p>Application exists: ' . (file_exists(__DIR__ . '/app/Core/Application.php') ? 'YES' : 'NO') . '</p>';
    echo '</div>';
}

// Basis-Pfade definieren
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('DATABASE_PATH', ROOT_PATH . '/database');

// Sicherheitsheader setzen
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Session-Sicherheit
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
ini_set('session.use_strict_mode', 1);

try {
    // Prüfen ob Installation erforderlich ist
    $configFile = CONFIG_PATH . '/app.php';
    
    if (!file_exists($configFile) || !is_readable($configFile)) {
        // Installation erforderlich - Weiterleitung zum Installations-Wizard
        if (!isset($_GET['install']) && strpos($_SERVER['REQUEST_URI'], '/install') === false) {
            header('Location: /install/');
            exit;
        }
    }
    
    // Autoloader laden
    if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
        require_once ROOT_PATH . '/vendor/autoload.php';
    }
    
    // Bootstrap-Dateien laden
    require_once ROOT_PATH . '/bootstrap/app.php';
    
    // Anwendung starten
    $app = new \App\Core\Application(ROOT_PATH);
    $app->run();
    
} catch (Exception $e) {
    // Fehlerbehandlung
    http_response_code(500);
    
    // In Produktionsumgebung keine Details preisgeben
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo '<h1>Application Error</h1>';
        echo '<pre>' . $e->getMessage() . '</pre>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
    } else {
        echo '<h1>Service Temporarily Unavailable</h1>';
        echo '<p>We are currently experiencing technical difficulties. Please try again later.</p>';
    }
    
    // Fehler protokollieren
    error_log('Vertragsverwaltung Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}