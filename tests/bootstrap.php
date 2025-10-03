<?php

/**
 * PHPUnit Bootstrap für Vertragsverwaltung
 * 
 * @package Tests
 * @author GenSpark AI Developer
 */

// Error Reporting für Tests aktivieren
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Composer Autoloader laden
require_once __DIR__ . '/../vendor/autoload.php';

// Dotenv für Test-Environment laden
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Test-spezifische Umgebungsvariablen setzen
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_DATABASE'] = $_ENV['DB_DATABASE'] ?? 'vertragsverwaltung_test';

// Session für Tests vorbereiten
if (!session_id()) {
    session_start();
}

// Helper für Tests
if (!function_exists('createTestUser')) {
    function createTestUser(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'passwort_hash' => password_hash('password123', PASSWORD_ARGON2ID),
            'rolle_id' => 1,
            'aktiv' => true,
            'angelegt_am' => date('Y-m-d H:i:s'),
        ], $overrides);
    }
}

if (!function_exists('createTestVertrag')) {
    function createTestVertrag(array $overrides = []): array
    {
        return array_merge([
            'korrespondent_id' => 1,
            'beginn' => '2024-01-01',
            'ende' => '2025-01-01',
            'status' => 'laufend',
            'laufzeit_monate' => 12,
            'kosten_cent' => 2999,
            'kosten_zeitraum' => 'monat',
            'zahlungszyklus' => 'monat',
            'zahlungsweise' => 'sepa',
            'verlängerungsmodus' => 'automatisch',
            'kuendigungsfrist_tage' => 30,
        ], $overrides);
    }
}

// Database für Tests vorbereiten
function setupTestDatabase(): void
{
    try {
        // Test-Datenbank erstellen falls nicht vorhanden
        $config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'database' => $_ENV['DB_DATABASE'] ?? 'vertragsverwaltung_test',
        ];
        
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Test-Datenbank erstellen
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        echo "Test database '{$config['database']}' ready.\n";
        
    } catch (PDOException $e) {
        echo "Warning: Could not setup test database: " . $e->getMessage() . "\n";
    }
}

// Test-Datenbank bei Bedarf einrichten
if (getenv('SETUP_TEST_DB') !== false) {
    setupTestDatabase();
}