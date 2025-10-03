<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Simple Test Controller
 * 
 * @package App\Controllers
 */
class TestController
{
    /**
     * Simple test method
     */
    public function index(): void
    {
        echo '<h1>🎉 Vertragsverwaltung - Installation erfolgreich!</h1>';
        echo '<p>Die Anwendung läuft korrekt.</p>';
        echo '<p>Zeitstempel: ' . date('Y-m-d H:i:s') . '</p>';
        echo '<p>Session-ID: ' . session_id() . '</p>';
        
        // Konfiguration testen
        if (defined('APP_NAME')) {
            echo '<p>Konfiguration geladen: ' . APP_NAME . '</p>';
        } else {
            echo '<p><strong>Warnung:</strong> Konfiguration nicht geladen</p>';
        }
        
        // Datenbank-Verbindung testen
        try {
            $configPath = ROOT_PATH . '/config/database.php';
            if (file_exists($configPath)) {
                $dbConfig = include $configPath;
                $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
                $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
                echo '<p>✅ Datenbankverbindung: OK</p>';
            } else {
                echo '<p>❌ Datenbank-Konfiguration nicht gefunden</p>';
            }
        } catch (\Exception $e) {
            echo '<p>❌ Datenbankverbindung: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}