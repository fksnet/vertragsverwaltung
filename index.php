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
ini_set('log_errors', 1);

// Debug-Modus für detaillierte Fehlerdiagnose
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$step_debug = isset($_GET['step_debug']) && $_GET['step_debug'] === '1';

if ($debug || $step_debug) {
    echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;">';
    echo '<h3>🔍 Detaillierte Debug-Info</h3>';
    echo '<p><strong>ROOT_PATH:</strong> ' . __DIR__ . '</p>';
    echo '<p><strong>PHP Version:</strong> ' . PHP_VERSION . '</p>';
    echo '<p><strong>Server:</strong> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '</p>';
    echo '<p><strong>Config app.php exists:</strong> ' . (file_exists(__DIR__ . '/config/app.php') ? 'YES' : 'NO') . '</p>';
    echo '<p><strong>Config database.php exists:</strong> ' . (file_exists(__DIR__ . '/config/database.php') ? 'YES' : 'NO') . '</p>';
    echo '<p><strong>Config security.php exists:</strong> ' . (file_exists(__DIR__ . '/config/security.php') ? 'YES' : 'NO') . '</p>';
    echo '<p><strong>Bootstrap exists:</strong> ' . (file_exists(__DIR__ . '/bootstrap/app.php') ? 'YES' : 'NO') . '</p>';
    echo '<p><strong>Application exists:</strong> ' . (file_exists(__DIR__ . '/app/Core/Application.php') ? 'YES' : 'NO') . '</p>';
    echo '<p><strong>Storage writable:</strong> ' . (is_writable(__DIR__ . '/storage') ? 'YES' : 'NO') . '</p>';
    echo '<p><strong>Config readable:</strong> ' . (is_readable(__DIR__ . '/config/app.php') ? 'YES' : 'NO') . '</p>';
    
    // Test config loading
    try {
        if (file_exists(__DIR__ . '/config/app.php')) {
            $testConfig = include __DIR__ . '/config/app.php';
            echo '<p><strong>Config loadable:</strong> ' . (is_array($testConfig) ? 'YES' : 'NO - Not an array') . '</p>';
        }
    } catch (Exception $e) {
        echo '<p><strong>Config load error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    
    echo '</div>';
    
    if ($step_debug) {
        echo '<p><strong>Stopping at debug point. Add ?debug=1 to continue with execution.</strong></p>';
        exit;
    }
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
    if ($debug) echo '<p>🔄 <strong>Step 1:</strong> Checking installation status...</p>';
    
    // Prüfen ob Installation erforderlich ist
    $configFile = CONFIG_PATH . '/app.php';
    
    if (!file_exists($configFile) || !is_readable($configFile)) {
        if ($debug) echo '<p>❌ Installation required - redirecting to installer</p>';
        // Installation erforderlich - Weiterleitung zum Installations-Wizard
        if (!isset($_GET['install']) && strpos($_SERVER['REQUEST_URI'], '/install') === false) {
            header('Location: /install/');
            exit;
        }
    } else {
        if ($debug) echo '<p>✅ Installation check passed</p>';
    }
    
    if ($debug) echo '<p>🔄 <strong>Step 2:</strong> Loading autoloader...</p>';
    
    // Autoloader laden
    if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
        require_once ROOT_PATH . '/vendor/autoload.php';
        if ($debug) echo '<p>✅ Composer autoloader loaded</p>';
    } else {
        if ($debug) echo '<p>ℹ️ No Composer autoloader found (using custom autoloader)</p>';
    }
    
    if ($debug) echo '<p>🔄 <strong>Step 3:</strong> Loading bootstrap...</p>';
    
    // Bootstrap-Dateien laden
    require_once ROOT_PATH . '/bootstrap/app.php';
    if ($debug) echo '<p>✅ Bootstrap loaded successfully</p>';
    
    if ($debug) echo '<p>🔄 <strong>Step 4:</strong> Creating application instance...</p>';
    
    // Anwendung starten
    $app = new \App\Core\Application(ROOT_PATH);
    if ($debug) echo '<p>✅ Application instance created</p>';
    
    if ($debug) echo '<p>🔄 <strong>Step 5:</strong> Running application...</p>';
    
    $app->run();
    
    if ($debug) echo '<p>✅ Application completed successfully</p>';
    
} catch (Exception $e) {
    // Detaillierte Fehlerbehandlung
    http_response_code(500);
    
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Immer Fehler protokollieren
    $logMessage = sprintf(
        "[%s] Vertragsverwaltung Fatal Error: %s in %s:%d\nStack trace:\n%s\n",
        $errorDetails['timestamp'],
        $errorDetails['message'],
        $errorDetails['file'],
        $errorDetails['line'],
        $errorDetails['trace']
    );
    
    // In Datei und System-Log schreiben
    @error_log($logMessage);
    @file_put_contents(__DIR__ . '/storage/logs/fatal_error.log', $logMessage, FILE_APPEND | LOCK_EX);
    
    // Debug-Modus oder Produktionsumgebung?
    $showDetails = $debug || (defined('APP_DEBUG') && APP_DEBUG);
    
    if ($showDetails) {
        echo '<div style="background: #fee; border: 2px solid #f00; padding: 20px; margin: 10px; border-radius: 5px;">';
        echo '<h1>🚨 Application Fatal Error</h1>';
        echo '<h3>Error Details:</h3>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($errorDetails['message']) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($errorDetails['file']) . '</p>';
        echo '<p><strong>Line:</strong> ' . $errorDetails['line'] . '</p>';
        echo '<p><strong>Time:</strong> ' . $errorDetails['timestamp'] . '</p>';
        echo '<h3>Stack Trace:</h3>';
        echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">' . htmlspecialchars($errorDetails['trace']) . '</pre>';
        
        echo '<h3>Debug Actions:</h3>';
        echo '<ul>';
        echo '<li><a href="?step_debug=1">🔍 Step-by-Step Debug Mode</a></li>';
        echo '<li><a href="?route=test">🧪 Test Controller</a></li>';
        echo '<li><strong>Check log file:</strong> /storage/logs/fatal_error.log</li>';
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 10px; border-radius: 5px;">';
        echo '<h1>🔧 Service Temporarily Unavailable</h1>';
        echo '<p>We are currently experiencing technical difficulties. Please try again later.</p>';
        echo '<p><small>Error logged at: ' . $errorDetails['timestamp'] . '</small></p>';
        echo '<p><a href="?debug=1">🔍 Enable Debug Mode</a> (for developers)</p>';
        echo '</div>';
    }
} catch (Error $e) {
    // PHP Fatal Errors (7.x+)
    http_response_code(500);
    $errorMsg = sprintf(
        "[%s] PHP Fatal Error: %s in %s:%d",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    
    @error_log($errorMsg);
    @file_put_contents(__DIR__ . '/storage/logs/fatal_error.log', $errorMsg . "\n", FILE_APPEND | LOCK_EX);
    
    if ($debug) {
        echo '<div style="background: #fee; border: 2px solid #f00; padding: 20px; margin: 10px;">';
        echo '<h1>🚨 PHP Fatal Error</h1>';
        echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
        echo '</div>';
    } else {
        echo '<h1>Critical System Error</h1>';
        echo '<p>A critical system error occurred. Please contact the administrator.</p>';
    }
}