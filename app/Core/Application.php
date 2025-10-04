<?php
// Entfernt strict_types für bessere Webhosting-Kompatibilität
// declare(strict_types=1);

namespace App\Core;

/**
 * Main Application Class
 * 
 * Zentrale Anwendungsklasse für Webhosting-Umgebungen.
 * Vereinfachte Version ohne Framework-Abhängigkeiten.
 * 
 * @package App\Core
 * @version 1.0.1
 */
class Application
{
    private string $rootPath;
    private array $routes = [];
    
    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        $this->loadRoutes();
    }
    
    /**
     * Startet die Anwendung
     */
    public function run(): void
    {
        try {
            // Session starten
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Route ermitteln
            $route = $this->getRoute();
            
            // Spezielle Routen behandeln
            if ($this->handleSpecialRoutes($route)) {
                return;
            }
            
            // Controller und Action ermitteln
            list($controller, $action, $params) = $this->parseRoute($route);
            
            // Controller laden und ausführen
            $this->executeController($controller, $action, $params);
            
        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Ermittelt die aktuelle Route
     */
    private function getRoute(): string
    {
        $route = $_GET['route'] ?? '';
        
        // URL bereinigen
        $route = trim($route, '/');
        $route = filter_var($route, FILTER_SANITIZE_URL);
        
        return $route;
    }
    
    /**
     * Behandelt spezielle Routen
     */
    private function handleSpecialRoutes(string $route): bool
    {
        // Installation
        if (empty($route) || $route === 'install') {
            if (!$this->isInstalled()) {
                $this->redirectToInstaller();
                return true;
            } else if (empty($route)) {
                // Nach der Installation: Willkommensseite zeigen
                $welcomePage = $this->rootPath . '/public/welcome.html';
                if (file_exists($welcomePage)) {
                    include $welcomePage;
                    return true;
                }
            }
        }
        
        // Assets
        if (strpos($route, 'assets/') === 0 || strpos($route, 'css/') === 0 || strpos($route, 'js/') === 0) {
            $this->serveAsset($route);
            return true;
        }
        
        // API-Routen
        if (strpos($route, 'api/') === 0) {
            $this->handleApiRoute(substr($route, 4));
            return true;
        }
        
        return false;
    }
    
    /**
     * Parst die Route in Controller, Action und Parameter
     */
    private function parseRoute(string $route): array
    {
        if (empty($route)) {
            return ['Test', 'index', []];
        }
        
        $segments = explode('/', $route);
        
        $controller = ucfirst($segments[0] ?? 'Dashboard');
        $action = $segments[1] ?? 'index';
        $params = array_slice($segments, 2);
        
        // Admin-Routen
        if ($controller === 'Admin' && isset($segments[1])) {
            $controller = 'Admin\\' . ucfirst($segments[1]);
            $action = $segments[2] ?? 'index';
            $params = array_slice($segments, 3);
        }
        
        return [$controller, $action, $params];
    }
    
    /**
     * Führt den Controller aus
     */
    private function executeController(string $controller, string $action, array $params): void
    {
        $controllerClass = "App\\Controllers\\{$controller}Controller";
        
        if (!class_exists($controllerClass)) {
            throw new \Exception("Controller not found: {$controllerClass}");
        }
        
        $controllerInstance = new $controllerClass();
        
        if (!method_exists($controllerInstance, $action)) {
            throw new \Exception("Action not found: {$controllerClass}::{$action}");
        }
        
        // Parameter an Methode übergeben
        call_user_func_array([$controllerInstance, $action], $params);
    }
    
    /**
     * Behandelt API-Routen
     */
    private function handleApiRoute(string $route): void
    {
        header('Content-Type: application/json');
        
        try {
            // Authentifizierung prüfen
            if (!$this->isAuthenticated() && !$this->isPublicApiRoute($route)) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }
            
            // API-Controller ausführen
            list($controller, $action, $params) = $this->parseRoute($route);
            $this->executeController('Api\\' . $controller, $action, $params);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Behandelt Asset-Dateien
     */
    private function serveAsset(string $route): void
    {
        $assetPath = $this->rootPath . '/public/' . $route;
        
        if (!file_exists($assetPath) || !is_file($assetPath)) {
            http_response_code(404);
            return;
        }
        
        // MIME-Type ermitteln
        $mimeType = $this->getMimeType($assetPath);
        header('Content-Type: ' . $mimeType);
        
        // Cache-Header für Assets
        header('Cache-Control: public, max-age=2592000'); // 30 Tage
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 2592000) . ' GMT');
        
        readfile($assetPath);
    }
    
    /**
     * Prüft ob die Anwendung installiert ist
     */
    private function isInstalled(): bool
    {
        $configFile = $this->rootPath . '/config/app.php';
        return file_exists($configFile) && is_readable($configFile);
    }
    
    /**
     * Leitet zum Installer weiter
     */
    private function redirectToInstaller(): void
    {
        $installerPath = $this->rootPath . '/install/index.php';
        
        if (file_exists($installerPath)) {
            include $installerPath;
        } else {
            echo '<h1>Installation Required</h1>';
            echo '<p>Please run the installation process first.</p>';
        }
    }
    
    /**
     * Prüft Authentifizierung
     */
    private function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Prüft ob API-Route öffentlich ist
     */
    private function isPublicApiRoute(string $route): bool
    {
        $publicRoutes = ['auth/login', 'auth/register', 'install', 'health'];
        return in_array($route, $publicRoutes);
    }
    
    /**
     * Ermittelt MIME-Type
     */
    private function getMimeType(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',
            'htm' => 'text/html'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Lädt Routen-Konfiguration
     */
    private function loadRoutes(): void
    {
        $routeFile = $this->rootPath . '/routes/web.php';
        if (file_exists($routeFile)) {
            $routes = include $routeFile;
            if (is_array($routes)) {
                $this->routes = $routes;
            }
        }
    }
    
    /**
     * Behandelt Fehler
     */
    private function handleError(\Exception $e): void
    {
        // Fehler protokollieren
        $logMessage = date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . 
                     " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
        @error_log($logMessage, 3, STORAGE_PATH . '/logs/application.log');
        
        // Fehlerseite anzeigen
        http_response_code(500);
        
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo '<h1>Application Error</h1>';
            echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        } else {
            $errorPage = $this->rootPath . '/public/errors/500.html';
            if (file_exists($errorPage)) {
                include $errorPage;
            } else {
                echo '<h1>Service Temporarily Unavailable</h1>';
                echo '<p>We are currently experiencing technical difficulties.</p>';
            }
        }
    }
}