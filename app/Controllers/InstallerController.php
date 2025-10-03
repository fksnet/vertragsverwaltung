<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\User;

/**
 * Web-Installer für die Vertragsverwaltung
 * 
 * Führt durch die 6-stufige Installation:
 * 1. Willkommen
 * 2. Systemvoraussetzungen
 * 3. Datenbank-Konfiguration
 * 4. App-Konfiguration
 * 5. Admin-Account
 * 6. Abschluss
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class InstallerController extends BaseController
{
    private const STEPS = [
        '/' => 'Willkommen',
        '/requirements' => 'Systemvoraussetzungen',
        '/database' => 'Datenbank',
        '/config' => 'Konfiguration',
        '/admin' => 'Administrator',
        '/finish' => 'Abschluss'
    ];
    
    public function __construct()
    {
        parent::__construct();
        
        // Prüfen ob Installation bereits abgeschlossen
        if (file_exists(app_path('../.env')) && env('DB_DATABASE')) {
            // Redirect zur Hauptseite wenn bereits installiert
            if ($_SERVER['REQUEST_URI'] !== '/installer/finish') {
                $this->redirect('/');
            }
        }
    }
    
    /**
     * Willkommensseite
     */
    public function welcome(): string
    {
        return $this->view('pages/installer/welcome', [
            'steps' => self::STEPS,
            'current_step' => '/',
        ]);
    }
    
    /**
     * Systemvoraussetzungen prüfen
     */
    public function requirements(): string
    {
        $requirements = [
            'PHP Version >= 8.3' => [
                'status' => version_compare(PHP_VERSION, '8.3.0', '>='),
                'current' => PHP_VERSION
            ],
            'PDO MySQL Extension' => [
                'status' => extension_loaded('pdo_mysql'),
                'current' => extension_loaded('pdo_mysql') ? 'Installiert' : 'Nicht gefunden'
            ],
            'Sodium Extension' => [
                'status' => extension_loaded('sodium'),
                'current' => extension_loaded('sodium') ? 'Installiert' : 'Nicht gefunden'
            ],
            'Intl Extension' => [
                'status' => extension_loaded('intl'),
                'current' => extension_loaded('intl') ? 'Installiert' : 'Nicht gefunden'
            ],
            'Mbstring Extension' => [
                'status' => extension_loaded('mbstring'),
                'current' => extension_loaded('mbstring') ? 'Installiert' : 'Nicht gefunden'
            ],
            'Fileinfo Extension' => [
                'status' => extension_loaded('fileinfo'),
                'current' => extension_loaded('fileinfo') ? 'Installiert' : 'Nicht gefunden'
            ],
            'JSON Extension' => [
                'status' => extension_loaded('json'),
                'current' => extension_loaded('json') ? 'Installiert' : 'Nicht gefunden'
            ],
            'Config-Verzeichnis beschreibbar' => [
                'status' => is_writable(app_path('../config')),
                'current' => is_writable(app_path('../config')) ? 'Beschreibbar' : 'Nicht beschreibbar'
            ],
            'Root-Verzeichnis beschreibbar (.env)' => [
                'status' => is_writable(app_path('../')),
                'current' => is_writable(app_path('../')) ? 'Beschreibbar' : 'Nicht beschreibbar'
            ],
            'Uploads-Verzeichnis beschreibbar' => [
                'status' => is_writable(app_path('../public/uploads')),
                'current' => is_writable(app_path('../public/uploads')) ? 'Beschreibbar' : 'Nicht beschreibbar'
            ]
        ];
        
        $allOk = array_reduce($requirements, fn($carry, $req) => $carry && $req['status'], true);
        
        return $this->view('pages/installer/requirements', [
            'steps' => self::STEPS,
            'current_step' => '/requirements',
            'requirements' => $requirements,
            'all_ok' => $allOk
        ]);
    }
    
    /**
     * Datenbank-Konfiguration
     */
    public function database(): string
    {
        $data = [
            'db_host' => $_SESSION['installer']['db_host'] ?? 'localhost',
            'db_port' => $_SESSION['installer']['db_port'] ?? '3306',
            'db_database' => $_SESSION['installer']['db_database'] ?? 'vertragsverwaltung',
            'db_username' => $_SESSION['installer']['db_username'] ?? '',
            'db_password' => $_SESSION['installer']['db_password'] ?? ''
        ];
        
        return $this->view('pages/installer/database', [
            'steps' => self::STEPS,
            'current_step' => '/database',
            'data' => $data
        ]);
    }
    
    /**
     * Datenbank-Verbindung testen
     */
    public function testDatabase(): void
    {
        try {
            $data = $this->validate([
                'db_host' => 'required',
                'db_port' => ['required', 'integer'],
                'db_database' => 'required',
                'db_username' => 'required',
                'db_password' => '' // Optional
            ]);
            
            // Verbindung testen
            $dsn = "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']};charset=utf8mb4";
            
            $pdo = new \PDO($dsn, $data['db_username'], $data['db_password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            
            // Test-Query ausführen
            $pdo->query('SELECT 1')->fetchColumn();
            
            // Daten in Session speichern
            $_SESSION['installer'] = array_merge($_SESSION['installer'] ?? [], $data);
            
            $this->json([
                'success' => true,
                'message' => 'Datenbankverbindung erfolgreich!',
                'redirect' => '/installer/config'
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * App-Konfiguration
     */
    public function config(): string
    {
        if (!isset($_SESSION['installer']['db_host'])) {
            $this->redirect('/installer/database');
        }
        
        $data = [
            'app_name' => $_SESSION['installer']['app_name'] ?? 'Vertragsverwaltung',
            'app_url' => $_SESSION['installer']['app_url'] ?? $this->guessAppUrl(),
            'timezone' => $_SESSION['installer']['timezone'] ?? 'Europe/Berlin',
            'currency' => $_SESSION['installer']['currency'] ?? 'EUR',
            'theme' => $_SESSION['installer']['theme'] ?? 'light',
            'mail_host' => $_SESSION['installer']['mail_host'] ?? '',
            'mail_port' => $_SESSION['installer']['mail_port'] ?? '587',
            'mail_username' => $_SESSION['installer']['mail_username'] ?? '',
            'mail_password' => $_SESSION['installer']['mail_password'] ?? '',
            'mail_encryption' => $_SESSION['installer']['mail_encryption'] ?? 'tls',
            'mail_from_address' => $_SESSION['installer']['mail_from_address'] ?? '',
            'mail_from_name' => $_SESSION['installer']['mail_from_name'] ?? 'Vertragsverwaltung'
        ];
        
        return $this->view('pages/installer/config', [
            'steps' => self::STEPS,
            'current_step' => '/config',
            'data' => $data,
            'timezones' => timezone_identifiers_list(),
        ]);
    }
    
    /**
     * Konfiguration speichern
     */
    public function saveConfig(): void
    {
        try {
            $data = $this->validate([
                'app_name' => ['required', 'max:100'],
                'app_url' => ['required'],
                'timezone' => 'required',
                'currency' => ['required', 'max:3'],
                'theme' => 'required',
                'mail_host' => '',
                'mail_port' => 'integer',
                'mail_username' => '',
                'mail_password' => '',
                'mail_encryption' => '',
                'mail_from_address' => 'email',
                'mail_from_name' => ''
            ]);
            
            // Daten in Session speichern
            $_SESSION['installer'] = array_merge($_SESSION['installer'] ?? [], $data);
            
            $this->json([
                'success' => true,
                'message' => 'Konfiguration gespeichert!',
                'redirect' => '/installer/admin'
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Administrator-Account erstellen
     */
    public function admin(): string
    {
        if (!isset($_SESSION['installer']['app_name'])) {
            $this->redirect('/installer/config');
        }
        
        return $this->view('pages/installer/admin', [
            'steps' => self::STEPS,
            'current_step' => '/admin'
        ]);
    }
    
    /**
     * Administrator-Account erstellen
     */
    public function createAdmin(): void
    {
        try {
            $data = $this->validate([
                'admin_name' => ['required', 'min:2', 'max:100'],
                'admin_email' => ['required', 'email'],
                'admin_password' => ['required', 'min:8'],
                'admin_password_confirmation' => 'required',
                'enable_2fa' => ''
            ]);
            
            if ($data['admin_password'] !== $data['admin_password_confirmation']) {
                throw new \Exception('Passwörter stimmen nicht überein');
            }
            
            // .env-Datei erstellen
            $this->createEnvFile();
            
            // Datenbank-Verbindung mit neuer .env
            $this->initializeDatabase();
            
            // Migrationen ausführen
            $this->runMigrations();
            
            // Admin-Account erstellen
            $adminId = $this->createAdminAccount($data);
            
            // Seeds laden (optional Demo-Daten)
            if (isset($_POST['load_demo_data'])) {
                $this->runSeeds();
            }
            
            $_SESSION['installer']['admin_created'] = true;
            
            $this->json([
                'success' => true,
                'message' => 'Installation abgeschlossen!',
                'redirect' => '/installer/finish'
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Installation abgeschlossen
     */
    public function finish(): string
    {
        if (!isset($_SESSION['installer']['admin_created'])) {
            $this->redirect('/installer/admin');
        }
        
        // Installer-Session löschen
        unset($_SESSION['installer']);
        
        return $this->view('pages/installer/finish', [
            'steps' => self::STEPS,
            'current_step' => '/finish'
        ]);
    }
    
    /**
     * .env-Datei erstellen
     */
    private function createEnvFile(): void
    {
        $installer = $_SESSION['installer'];
        
        $appKey = bin2hex(random_bytes(32));
        $encryptionKey = base64_encode(random_bytes(32));
        
        $envContent = <<<ENV
# Application Configuration
APP_NAME="{$installer['app_name']}"
APP_URL="{$installer['app_url']}"
APP_ENV=production
APP_DEBUG=false
APP_KEY={$appKey}

# Database Configuration
DB_CONNECTION=mysql
DB_HOST={$installer['db_host']}
DB_PORT={$installer['db_port']}
DB_DATABASE={$installer['db_database']}
DB_USERNAME={$installer['db_username']}
DB_PASSWORD={$installer['db_password']}

# Encryption
ENCRYPTION_KEY={$encryptionKey}

# Application Settings
TIMEZONE={$installer['timezone']}
CURRENCY={$installer['currency']}
DEFAULT_THEME={$installer['theme']}

# Mail Configuration
MAIL_HOST={$installer['mail_host']}
MAIL_PORT={$installer['mail_port']}
MAIL_USERNAME={$installer['mail_username']}
MAIL_PASSWORD={$installer['mail_password']}
MAIL_ENCRYPTION={$installer['mail_encryption']}
MAIL_FROM_ADDRESS={$installer['mail_from_address']}
MAIL_FROM_NAME="{$installer['mail_from_name']}"

# Session Configuration
SESSION_LIFETIME=7200
SESSION_SECURE=false
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=Lax

# Security
CSRF_EXPIRE=3600

# Authentication
AUTH_ALLOW_REGISTRATION=false
AUTH_PASSWORD_RESET_EXPIRE=3600

# Rate Limiting
RATE_LIMIT_LOGIN_ATTEMPTS=5
RATE_LIMIT_LOGIN_DECAY=300
ENV;

        if (file_put_contents(app_path('../.env'), $envContent) === false) {
            throw new \Exception('Konnte .env-Datei nicht erstellen');
        }
    }
    
    /**
     * Datenbank initialisieren
     */
    private function initializeDatabase(): void
    {
        // .env laden
        $dotenv = \Dotenv\Dotenv::createImmutable(app_path('..'));
        $dotenv->load();
        
        // Datenbank-Verbindung testen
        Database::init();
        $db = db();
        $db->query('SELECT 1')->fetchColumn();
    }
    
    /**
     * Migrationen ausführen
     */
    private function runMigrations(): void
    {
        $migrationPath = app_path('../database/migrations');
        $files = glob($migrationPath . '/*.php');
        sort($files);
        
        foreach ($files as $file) {
            require_once $file;
            
            $className = 'Migration_' . basename($file, '.php');
            if (class_exists($className)) {
                $migration = new $className();
                if (method_exists($migration, 'up')) {
                    $migration->up();
                }
            }
        }
    }
    
    /**
     * Seeds ausführen
     */
    private function runSeeds(): void
    {
        $seedPath = app_path('../database/seeds');
        $files = glob($seedPath . '/*.php');
        sort($files);
        
        foreach ($files as $file) {
            require_once $file;
            
            $className = 'Seed_' . basename($file, '.php');
            if (class_exists($className)) {
                $seed = new $className();
                if (method_exists($seed, 'run')) {
                    $seed->run();
                }
            }
        }
    }
    
    /**
     * Admin-Account erstellen
     */
    private function createAdminAccount(array $data): int
    {
        // Admin-Rolle finden/erstellen
        $adminRoleId = db()->query(
            "SELECT id FROM rollen WHERE name = 'administrator' LIMIT 1"
        )->fetchColumn();
        
        if (!$adminRoleId) {
            throw new \Exception('Administrator-Rolle nicht gefunden. Migrationen korrekt ausgeführt?');
        }
        
        // Admin-User erstellen
        $userId = User::create([
            'name' => $data['admin_name'],
            'email' => $data['admin_email'],
            'passwort_hash' => password_hash($data['admin_password'], PASSWORD_ARGON2ID),
            'rolle_id' => $adminRoleId,
            'aktiv' => true,
            'angelegt_am' => date('Y-m-d H:i:s')
        ]);
        
        return $userId;
    }
    
    /**
     * App-URL erraten
     */
    private function guessAppUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        // /installer von Pfad entfernen
        $path = str_replace('/installer', '', $path);
        
        return $protocol . '://' . $host . $path;
    }
}