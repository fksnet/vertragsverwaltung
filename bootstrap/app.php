<?php
/**
 * Application Bootstrap
 * 
 * Initialisiert die Vertragsverwaltung-Anwendung für Webhosting-Umgebungen.
 * 
 * @package Vertragsverwaltung
 * @version 1.0.0
 */

// Sicherstellen, dass dieser Code nur über den Haupteinstiegspunkt ausgeführt wird
if (!defined('ROOT_PATH')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Fehlerbehandlung für Produktionsumgebung
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// Pfade definieren
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', ROOT_PATH . '/storage');
}

// Storage-Verzeichnisse erstellen falls sie nicht existieren
$storageDirs = [
    STORAGE_PATH,
    STORAGE_PATH . '/logs',
    STORAGE_PATH . '/cache',
    STORAGE_PATH . '/sessions',
    STORAGE_PATH . '/uploads',
    STORAGE_PATH . '/backups',
    STORAGE_PATH . '/temp'
];

foreach ($storageDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        
        // .htaccess für Sicherheit hinzufügen
        if (!file_exists($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Order allow,deny\nDeny from all\n");
        }
    }
}

// Session-Konfiguration
ini_set('session.save_path', STORAGE_PATH . '/sessions');
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 7200); // 2 Stunden

// Autoloader für eigene Klassen (falls Composer nicht verfügbar)
spl_autoload_register(function ($className) {
    // Namespace App\ zu app/ Verzeichnis
    if (strpos($className, 'App\\') === 0) {
        $file = APP_PATH . '/' . str_replace(['App\\', '\\'], ['', '/'], $className) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        } else {
            // Debug-Info für fehlende Klassen
            if (defined('APP_DEBUG') && APP_DEBUG) {
                error_log("Autoloader: Class not found: $className (looked for: $file)");
            }
        }
    }
    return false;
});

// Konfiguration laden
$configFiles = [
    'app.php',
    'database.php', 
    'security.php',
    'mail.php'
];

$config = [];
foreach ($configFiles as $configFile) {
    $configPath = CONFIG_PATH . '/' . $configFile;
    if (file_exists($configPath)) {
        $fileConfig = include $configPath;
        if (is_array($fileConfig)) {
            $config = array_merge($config, $fileConfig);
        }
    }
}

// Konfiguration als Konstanten definieren
if (!empty($config)) {
    foreach ($config as $key => $value) {
        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            $constantName = 'APP_' . strtoupper($key);
            if (!defined($constantName)) {
                define($constantName, $value);
            }
        }
    }
}

// Error Handler für Produktionsumgebung
if (!APP_DEBUG) {
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $logMessage = date('Y-m-d H:i:s') . " ERROR: $message in $file:$line" . PHP_EOL;
        @error_log($logMessage, 3, STORAGE_PATH . '/logs/error.log');
        
        return true;
    });
    
    set_exception_handler(function($exception) {
        $logMessage = date('Y-m-d H:i:s') . " EXCEPTION: " . $exception->getMessage() . 
                     " in " . $exception->getFile() . ":" . $exception->getLine() . PHP_EOL;
        @error_log($logMessage, 3, STORAGE_PATH . '/logs/error.log');
        
        http_response_code(500);
        
        // Prüfen ob Fehlerseite existiert, bevor wir sie laden
        $errorPage = ROOT_PATH . '/public/errors/500.html';
        if (file_exists($errorPage)) {
            include $errorPage;
        } else {
            echo '<h1>Service Temporarily Unavailable</h1>';
            echo '<p>We are currently experiencing technical difficulties. Please try again later.</p>';
        }
        exit;
    });
}