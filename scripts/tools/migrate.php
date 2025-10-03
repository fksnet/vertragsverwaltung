<?php

/**
 * Database Migration Tool
 * 
 * @package Scripts\Tools
 * @author GenSpark AI Developer
 */

// CLI-Only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../../vendor/autoload.php';

// Environment laden
if (file_exists(__DIR__ . '/../../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
    $dotenv->load();
}

use App\Core\Database;

class MigrationRunner
{
    private PDO $pdo;
    private string $migrationPath;
    
    public function __construct()
    {
        $this->pdo = Database::connection();
        $this->migrationPath = __DIR__ . '/../../database/migrations';
        $this->setupMigrationTable();
    }
    
    /**
     * Migration-Tracking-Tabelle erstellen
     */
    private function setupMigrationTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Alle Migrationen ausführen
     */
    public function migrate(): void
    {
        echo "🚀 Starte Datenbankmigrationen...\n\n";
        
        $migrations = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();
        
        $pending = array_diff($migrations, $executed);
        
        if (empty($pending)) {
            echo "✅ Alle Migrationen sind bereits ausgeführt.\n";
            return;
        }
        
        echo "📋 Gefundene Migrationen: " . count($migrations) . "\n";
        echo "✅ Bereits ausgeführt: " . count($executed) . "\n";
        echo "⏳ Ausstehend: " . count($pending) . "\n\n";
        
        foreach ($pending as $migration) {
            $this->executeMigration($migration);
        }
        
        echo "\n🎉 Alle Migrationen erfolgreich ausgeführt!\n";
    }
    
    /**
     * Migration rückgängig machen
     */
    public function rollback(int $steps = 1): void
    {
        echo "🔄 Starte Migration-Rollback ($steps Schritte)...\n\n";
        
        $executed = $this->getExecutedMigrations();
        $toRollback = array_slice(array_reverse($executed), 0, $steps);
        
        if (empty($toRollback)) {
            echo "❌ Keine Migrationen zum Rückgängigmachen gefunden.\n";
            return;
        }
        
        foreach ($toRollback as $migration) {
            $this->rollbackMigration($migration);
        }
        
        echo "\n✅ Rollback erfolgreich abgeschlossen!\n";
    }
    
    /**
     * Migration-Status anzeigen
     */
    public function status(): void
    {
        echo "📊 Migration-Status:\n\n";
        
        $migrations = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();
        
        foreach ($migrations as $migration) {
            $status = in_array($migration, $executed) ? '✅ Ausgeführt' : '⏳ Ausstehend';
            echo "  $status  $migration\n";
        }
        
        echo "\nGesamt: " . count($migrations) . " | Ausgeführt: " . count($executed) . " | Ausstehend: " . (count($migrations) - count($executed)) . "\n";
    }
    
    /**
     * Einzelne Migration ausführen
     */
    private function executeMigration(string $filename): void
    {
        echo "⚡ Führe Migration aus: $filename\n";
        
        $filePath = $this->migrationPath . '/' . $filename;
        
        if (!file_exists($filePath)) {
            throw new Exception("Migration-Datei nicht gefunden: $filePath");
        }
        
        $migration = require $filePath;
        
        if (!isset($migration['up'])) {
            throw new Exception("Migration '$filename' hat keine 'up' Definition");
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Migration SQL ausführen
            $this->pdo->exec($migration['up']);
            
            // Migration als ausgeführt markieren
            $stmt = $this->pdo->prepare("INSERT INTO migrations (filename) VALUES (?)");
            $stmt->execute([$filename]);
            
            $this->pdo->commit();
            
            echo "  ✅ Erfolgreich\n";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "  ❌ Fehler: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Migration rückgängig machen
     */
    private function rollbackMigration(string $filename): void
    {
        echo "🔄 Rollback Migration: $filename\n";
        
        $filePath = $this->migrationPath . '/' . $filename;
        
        if (!file_exists($filePath)) {
            throw new Exception("Migration-Datei nicht gefunden: $filePath");
        }
        
        $migration = require $filePath;
        
        if (!isset($migration['down'])) {
            throw new Exception("Migration '$filename' hat keine 'down' Definition");
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Rollback SQL ausführen
            $this->pdo->exec($migration['down']);
            
            // Migration aus Historie entfernen
            $stmt = $this->pdo->prepare("DELETE FROM migrations WHERE filename = ?");
            $stmt->execute([$filename]);
            
            $this->pdo->commit();
            
            echo "  ✅ Erfolgreich\n";
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            echo "  ❌ Fehler: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Verfügbare Migration-Dateien abrufen
     */
    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationPath . '/*.php');
        $migrations = [];
        
        foreach ($files as $file) {
            $migrations[] = basename($file);
        }
        
        sort($migrations);
        return $migrations;
    }
    
    /**
     * Bereits ausgeführte Migrationen abrufen
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT filename FROM migrations ORDER BY executed_at");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// CLI-Interface
function showUsage(): void
{
    echo "Database Migration Tool\n\n";
    echo "Usage: php migrate.php [command]\n\n";
    echo "Commands:\n";
    echo "  migrate     Alle ausstehenden Migrationen ausführen (Standard)\n";
    echo "  rollback    Letzte Migration(en) rückgängig machen\n";
    echo "  status      Migration-Status anzeigen\n";
    echo "  help        Diese Hilfe anzeigen\n\n";
    echo "Options für rollback:\n";
    echo "  --steps=N   Anzahl der Schritte rückgängig zu machen (Standard: 1)\n\n";
}

try {
    $command = $argv[1] ?? 'migrate';
    $migrator = new MigrationRunner();
    
    switch ($command) {
        case 'migrate':
            $migrator->migrate();
            break;
            
        case 'rollback':
            $steps = 1;
            // Parse --steps parameter
            foreach ($argv as $arg) {
                if (str_starts_with($arg, '--steps=')) {
                    $steps = (int) str_replace('--steps=', '', $arg);
                    break;
                }
            }
            $migrator->rollback($steps);
            break;
            
        case 'status':
            $migrator->status();
            break;
            
        case 'help':
        default:
            showUsage();
            break;
    }
    
} catch (Exception $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
    exit(1);
}