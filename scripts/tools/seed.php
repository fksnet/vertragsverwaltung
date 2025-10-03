<?php

/**
 * Database Seeder Tool
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

class SeederRunner
{
    private string $seedPath;
    
    public function __construct()
    {
        $this->seedPath = __DIR__ . '/../../database/seeds';
    }
    
    /**
     * Alle Seeds ausführen
     */
    public function run(): void
    {
        echo "🌱 Starte Datenbank-Seeding...\n\n";
        
        $seeds = $this->getSeedFiles();
        
        if (empty($seeds)) {
            echo "❌ Keine Seed-Dateien gefunden.\n";
            return;
        }
        
        echo "📋 Gefundene Seeds: " . count($seeds) . "\n\n";
        
        foreach ($seeds as $seed) {
            $this->executeSeed($seed);
        }
        
        echo "\n🎉 Seeding erfolgreich abgeschlossen!\n";
    }
    
    /**
     * Einzelnen Seed ausführen
     */
    public function runSingle(string $seedName): void
    {
        echo "🌱 Führe einzelnen Seed aus: $seedName\n\n";
        
        $seedFile = $seedName . '.php';
        $this->executeSeed($seedFile);
        
        echo "\n✅ Seed '$seedName' erfolgreich ausgeführt!\n";
    }
    
    /**
     * Seeds zurücksetzen (Tabellen leeren)
     */
    public function reset(): void
    {
        echo "🗑️ Setze Datenbank zurück...\n\n";
        
        // Referential Integrity temporär deaktivieren
        Database::connection()->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        $tables = [
            'freigaben',
            'dokumente', 
            'zugangsdaten',
            'vertraege',
            'korrespondenten',
            'audit_log',
            'benutzer'
        ];
        
        foreach ($tables as $table) {
            try {
                Database::connection()->exec("TRUNCATE TABLE $table");
                echo "  🗑️ Tabelle '$table' geleert\n";
            } catch (Exception $e) {
                echo "  ⚠️ Warnung bei '$table': " . $e->getMessage() . "\n";
            }
        }
        
        // Referential Integrity wieder aktivieren
        Database::connection()->exec('SET FOREIGN_KEY_CHECKS = 1');
        
        echo "\n✅ Datenbank-Reset abgeschlossen!\n";
    }
    
    /**
     * Einzelnen Seed ausführen
     */
    private function executeSeed(string $filename): void
    {
        $displayName = str_replace('.php', '', $filename);
        echo "🌱 Führe Seed aus: $displayName\n";
        
        $filePath = $this->seedPath . '/' . $filename;
        
        if (!file_exists($filePath)) {
            throw new Exception("Seed-Datei nicht gefunden: $filePath");
        }
        
        try {
            Database::connection()->beginTransaction();
            
            // Seed-Funktion ausführen
            $seedFunction = require $filePath;
            
            if (!is_callable($seedFunction)) {
                throw new Exception("Seed '$filename' muss eine aufrufbare Funktion zurückgeben");
            }
            
            $seedFunction();
            
            Database::connection()->commit();
            
        } catch (Exception $e) {
            Database::connection()->rollBack();
            echo "  ❌ Fehler: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Verfügbare Seed-Dateien abrufen
     */
    private function getSeedFiles(): array
    {
        $files = glob($this->seedPath . '/*.php');
        $seeds = [];
        
        foreach ($files as $file) {
            $seeds[] = basename($file);
        }
        
        sort($seeds);
        return $seeds;
    }
    
    /**
     * Seed-Status anzeigen
     */
    public function status(): void
    {
        echo "📊 Seed-Status:\n\n";
        
        $seeds = $this->getSeedFiles();
        
        foreach ($seeds as $seed) {
            echo "  📄 $seed\n";
        }
        
        echo "\nGesamt: " . count($seeds) . " verfügbare Seeds\n";
    }
}

// CLI-Interface
function showUsage(): void
{
    echo "Database Seeder Tool\n\n";
    echo "Usage: php seed.php [command] [options]\n\n";
    echo "Commands:\n";
    echo "  run         Alle Seeds ausführen (Standard)\n";
    echo "  seed NAME   Einzelnen Seed ausführen\n";
    echo "  reset       Alle Demo-Daten löschen\n";
    echo "  status      Verfügbare Seeds anzeigen\n";
    echo "  help        Diese Hilfe anzeigen\n\n";
    echo "Beispiele:\n";
    echo "  php seed.php                     # Alle Seeds ausführen\n";
    echo "  php seed.php seed demo_benutzer  # Nur Benutzer-Seeds\n";
    echo "  php seed.php reset               # Datenbank zurücksetzen\n\n";
}

try {
    $command = $argv[1] ?? 'run';
    $seeder = new SeederRunner();
    
    switch ($command) {
        case 'run':
            $seeder->run();
            break;
            
        case 'seed':
            if (!isset($argv[2])) {
                echo "❌ Seed-Name erforderlich. Beispiel: php seed.php seed demo_benutzer\n";
                exit(1);
            }
            $seeder->runSingle($argv[2]);
            break;
            
        case 'reset':
            echo "⚠️ Achtung: Alle Demo-Daten werden gelöscht!\n";
            echo "Fortfahren? (y/N): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);
            
            if (trim(strtolower($line)) === 'y') {
                $seeder->reset();
            } else {
                echo "❌ Abgebrochen.\n";
            }
            break;
            
        case 'status':
            $seeder->status();
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