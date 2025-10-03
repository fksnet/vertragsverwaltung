<?php
/**
 * Installation Step 6: Complete Installation
 */

$errors = [];
$success = [];

// Installation durchführen
if (isset($_GET['install']) && $_GET['install'] === 'true') {
    if (!isset($_SESSION['db_config'], $_SESSION['admin_user'], $_SESSION['app_settings'])) {
        $errors[] = 'Unvollständige Konfiguration. Bitte beginnen Sie die Installation erneut.';
    } else {
        $installationSteps = [];
        
        try {
            // 1. Konfigurationsdateien erstellen
            $installationSteps[] = 'Erstelle Konfigurationsdateien...';
            $configCreated = createConfigFiles();
            if (!$configCreated) {
                throw new Exception('Konfigurationsdateien konnten nicht erstellt werden');
            }
            $installationSteps[] = '✅ Konfigurationsdateien erstellt';
            
            // 2. Datenbank initialisieren
            $installationSteps[] = 'Initialisiere Datenbank...';
            $dbInitialized = initializeDatabase();
            if (!$dbInitialized) {
                // Fallback: Versuche Reset und erneute Initialisierung
                $installationSteps[] = '⚠️ Erste Initialisierung fehlgeschlagen, versuche Reset...';
                if (resetDatabase() && initializeDatabase()) {
                    $installationSteps[] = '✅ Datenbank nach Reset initialisiert';
                } else {
                    throw new Exception('Datenbank konnte auch nach Reset nicht initialisiert werden');
                }
            } else {
                $installationSteps[] = '✅ Datenbank initialisiert';
            }
            
            // 3. Administrator-Benutzer erstellen
            $installationSteps[] = 'Erstelle Administrator-Benutzer...';
            $adminCreated = createAdminUser();
            if (!$adminCreated) {
                throw new Exception('Administrator-Benutzer konnte nicht erstellt werden');
            }
            $installationSteps[] = '✅ Administrator-Benutzer erstellt';
            
            // 4. Standard-Daten einfügen
            $installationSteps[] = 'Erstelle Standard-Daten...';
            $defaultDataCreated = createDefaultData();
            if (!$defaultDataCreated) {
                // Standard-Daten sind optional, weitermachen
                $installationSteps[] = '⚠️ Standard-Daten übersprungen (nicht kritisch)';
            } else {
                $installationSteps[] = '✅ Standard-Daten erstellt';
            }
            
            // 5. Installation als abgeschlossen markieren
            $installationSteps[] = 'Schließe Installation ab...';
            updateConfigFile(['installed' => true, 'installed_at' => date('Y-m-d H:i:s')]);
            
            // Session-Daten löschen
            unset($_SESSION['db_config'], $_SESSION['admin_user'], $_SESSION['app_settings']);
            
            $success[] = 'Installation erfolgreich abgeschlossen!';
            $installationSteps[] = '🎉 Installation komplett!';
            
        } catch (Exception $e) {
            $errors[] = 'Installationsfehler: ' . $e->getMessage();
            $errors[] = 'Debug-Info: ' . implode('<br>', $installationSteps);
            error_log('Installation Error: ' . $e->getMessage());
        }
    }
}

/**
 * Erstellt die Konfigurationsdateien
 */
function createConfigFiles(): bool {
    try {
        // Verzeichnisse erstellen
        if (!is_dir(CONFIG_PATH)) {
            mkdir(CONFIG_PATH, 0755, true);
        }
        
        $dbConfig = $_SESSION['db_config'];
        $appSettings = $_SESSION['app_settings'];
        
        // app.php erstellen
        $appConfig = "<?php\nreturn [\n";
        $appConfig .= "    'name' => " . var_export($appSettings['name'], true) . ",\n";
        $appConfig .= "    'url' => " . var_export($appSettings['url'], true) . ",\n";
        $appConfig .= "    'timezone' => " . var_export($appSettings['timezone'], true) . ",\n";
        $appConfig .= "    'language' => " . var_export($appSettings['language'], true) . ",\n";
        $appConfig .= "    'currency' => " . var_export($appSettings['currency'], true) . ",\n";
        $appConfig .= "    'debug' => " . var_export($appSettings['debug'], true) . ",\n";
        $appConfig .= "    'enable_registration' => " . var_export($appSettings['enable_registration'], true) . ",\n";
        $appConfig .= "    'enable_api' => " . var_export($appSettings['enable_api'], true) . ",\n";
        $appConfig .= "    'version' => '1.0.0',\n";
        $appConfig .= "    'installed' => false,\n";
        $appConfig .= "];";
        
        file_put_contents(CONFIG_PATH . '/app.php', $appConfig);
        
        // database.php erstellen
        $dbConfigContent = "<?php\nreturn [\n";
        $dbConfigContent .= "    'host' => " . var_export($dbConfig['host'], true) . ",\n";
        $dbConfigContent .= "    'port' => " . var_export($dbConfig['port'], true) . ",\n";
        $dbConfigContent .= "    'name' => " . var_export($dbConfig['name'], true) . ",\n";
        $dbConfigContent .= "    'username' => " . var_export($dbConfig['username'], true) . ",\n";
        $dbConfigContent .= "    'password' => " . var_export($dbConfig['password'], true) . ",\n";
        $dbConfigContent .= "    'charset' => 'utf8mb4',\n";
        $dbConfigContent .= "    'options' => [\n";
        $dbConfigContent .= "        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
        $dbConfigContent .= "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
        $dbConfigContent .= "        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'\n";
        $dbConfigContent .= "    ]\n";
        $dbConfigContent .= "];";
        
        file_put_contents(CONFIG_PATH . '/database.php', $dbConfigContent);
        
        // Sicherheits-Konfiguration
        $securityKey = bin2hex(random_bytes(32));
        $encryptionKey = bin2hex(random_bytes(32));
        
        $securityConfig = "<?php\nreturn [\n";
        $securityConfig .= "    'key' => " . var_export($securityKey, true) . ",\n";
        $securityConfig .= "    'encryption_key' => " . var_export($encryptionKey, true) . ",\n";
        $securityConfig .= "    'session_lifetime' => 7200,\n";
        $securityConfig .= "    'password_hash_algo' => PASSWORD_ARGON2ID,\n";
        $securityConfig .= "    'csrf_protection' => true,\n";
        $securityConfig .= "];";
        
        file_put_contents(CONFIG_PATH . '/security.php', $securityConfig);
        
        return true;
    } catch (Exception $e) {
        error_log('Config creation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Initialisiert die Datenbank
 */
function initializeDatabase(): bool {
    try {
        $config = $_SESSION['db_config'];
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Try safe schema first (without foreign keys)
        $possiblePaths = [
            __DIR__ . '/../database/schema_safe.sql',  // Neue sichere Variante
            __DIR__ . '/../database/schema.sql',       // Original mit Foreign Keys
            ROOT_PATH . '/install/database/schema_safe.sql',
            ROOT_PATH . '/install/database/schema.sql',
            ROOT_PATH . '/database/schema.sql'
        ];
        
        $sql = null;
        $usedPath = null;
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $sql = file_get_contents($path);
                $usedPath = $path;
                break;
            }
        }
        
        if (!$sql) {
            throw new Exception('Schema-Datei nicht gefunden in: ' . implode(', ', $possiblePaths));
        }
        
        // Bei wiederholter Installation: Nur Tabellen erstellen, Foreign Keys überspringen
        $isReinstall = isReinstallation($pdo);
        if ($isReinstall) {
            error_log("Detected reinstallation - skipping foreign key constraints");
        }
        
        // SQL in einzelne Statements aufteilen
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $executedStatements = 0;
        $skippedStatements = 0;
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                // Bei Reinstallation: Foreign Key Constraints überspringen
                if ($isReinstall && (
                    strpos($statement, 'ADD CONSTRAINT') !== false || 
                    strpos($statement, 'FOREIGN KEY') !== false
                )) {
                    $skippedStatements++;
                    continue;
                }
                
                try {
                    $pdo->exec($statement);
                    $executedStatements++;
                } catch (PDOException $e) {
                    $errorMsg = $e->getMessage();
                    error_log("SQL Statement failed: " . substr($statement, 0, 100) . "... - Error: " . $errorMsg);
                    
                    // Bei diesen Fehlern weitermachen (nicht kritisch)
                    if (
                        strpos($errorMsg, 'already exists') !== false ||
                        strpos($errorMsg, 'Duplicate') !== false ||
                        strpos($errorMsg, 'CREATE TABLE') !== false ||
                        strpos($errorMsg, 'CONSTRAINT') !== false
                    ) {
                        $skippedStatements++;
                        continue;
                    }
                    
                    // Andere Fehler sind kritisch
                    throw $e;
                }
            }
        }
        
        error_log("Database initialized: $executedStatements statements executed, $skippedStatements skipped from $usedPath");
        return true;
        
    } catch (Exception $e) {
        error_log('Database initialization error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Prüft ob es sich um eine Reinstallation handelt
 */
function isReinstallation(PDO $pdo): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Reset der Datenbank (nur als letzter Ausweg)
 */
function resetDatabase(): bool {
    try {
        $config = $_SESSION['db_config'];
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Alle Foreign Key Constraints entfernen
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Alle Tabellen der Anwendung löschen
        $tables = [
            'settings', 'audit_logs', 'two_factor_backup_codes', 'notfall_zugriffe',
            'notfall_kontakte', 'freigaben', 'dokumente', 'zugangsdaten', 'vertraege',
            'korrespondenten', 'role_permissions', 'user_roles', 'permissions', 'roles', 'users'
        ];
        
        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            } catch (Exception $e) {
                // Ignorieren falls Tabelle nicht existiert
            }
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        error_log("Database reset completed");
        return true;
        
    } catch (Exception $e) {
        error_log('Database reset error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Erstellt den Administrator-Benutzer
 */
function createAdminUser(): bool {
    try {
        $config = $_SESSION['db_config'];
        $admin = $_SESSION['admin_user'];
        
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Prüfen ob users-Tabelle existiert
        $tablesQuery = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($tablesQuery->rowCount() == 0) {
            throw new Exception('users-Tabelle existiert nicht');
        }
        
        // Prüfen ob Benutzer bereits existiert
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $checkStmt->execute([$admin['email']]);
        if ($checkStmt->fetchColumn() > 0) {
            error_log("Admin user already exists: " . $admin['email']);
            return true; // Bereits vorhanden, als Erfolg werten
        }
        
        // Admin-Benutzer einfügen
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, is_active, email_verified_at, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $admin['name'],
            $admin['email'],
            $admin['password']
        ]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            error_log("Admin user created with ID: $userId");
            
            // Admin-Rolle zuweisen (optional - nur wenn Rollen-System vorhanden)
            try {
                $rolesQuery = $pdo->query("SHOW TABLES LIKE 'roles'");
                if ($rolesQuery->rowCount() > 0) {
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO user_roles (user_id, role_id)
                        SELECT ?, id FROM roles WHERE name = 'admin' LIMIT 1
                    ");
                    $stmt->execute([$userId]);
                }
            } catch (Exception $e) {
                error_log('Role assignment failed (not critical): ' . $e->getMessage());
                // Nicht kritisch, weitermachen
            }
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log('Admin user creation error: ' . $e->getMessage());
        throw $e; // Fehler weiterleiten für bessere Diagnose
    }
}

/**
 * Erstellt Standard-Daten
 */
function createDefaultData(): bool {
    try {
        $config = $_SESSION['db_config'];
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Standard-Daten sind optional - erstmal nur Basis-Rollen erstellen
        try {
            // Rollen erstellen (falls Tabelle existiert)
            $rolesQuery = $pdo->query("SHOW TABLES LIKE 'roles'");
            if ($rolesQuery->rowCount() > 0) {
                $roles = [
                    ['admin', 'Administrator'],
                    ['user', 'Standard Benutzer'],
                    ['guest', 'Gast']
                ];
                
                $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                foreach ($roles as $role) {
                    $stmt->execute($role);
                }
                error_log('Default roles created');
            }
        } catch (Exception $e) {
            error_log('Default data creation failed (not critical): ' . $e->getMessage());
        }
        
        return true; // Immer erfolgreich, da optional
    } catch (Exception $e) {
        error_log('Default data creation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Aktualisiert Konfigurationsdatei
 */
function updateConfigFile(array $updates): bool {
    try {
        $configFile = CONFIG_PATH . '/app.php';
        $config = include $configFile;
        
        foreach ($updates as $key => $value) {
            $config[$key] = $value;
        }
        
        $content = "<?php\nreturn " . var_export($config, true) . ";";
        return file_put_contents($configFile, $content) !== false;
    } catch (Exception $e) {
        return false;
    }
}

?>

<h2><?= !empty($success) ? '🎉 Installation abgeschlossen!' : '⚙️ Installation wird durchgeführt...' ?></h2>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Installationsfehler:</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    
    <div class="navigation">
        <a href="?step=settings" class="btn btn-secondary">← Zur Konfiguration zurück</a>
        <a href="?step=welcome" class="btn btn-secondary">🔄 Installation neu starten</a>
    </div>

<?php elseif (!empty($success)): ?>
    <div class="alert alert-success">
        <strong>Herzlichen Glückwunsch!</strong><br>
        Die Vertragsverwaltung wurde erfolgreich installiert und ist einsatzbereit.
    </div>
    
    <h3>Nächste Schritte</h3>
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <ol style="margin: 0; padding-left: 20px;">
            <li><strong>Anmelden:</strong> Loggen Sie sich mit Ihren Administrator-Daten ein</li>
            <li><strong>Sicherheit:</strong> Aktivieren Sie die Zwei-Faktor-Authentifizierung</li>
            <li><strong>E-Mail:</strong> Konfigurieren Sie die E-Mail-Einstellungen im Admin-Bereich</li>
            <li><strong>Backup:</strong> Richten Sie automatische Backups ein</li>
            <li><strong>SSL:</strong> Stellen Sie sicher, dass HTTPS aktiviert ist</li>
        </ol>
    </div>
    
    <h3>Wichtige Informationen</h3>
    <div style="background: #fffbeb; border: 1px solid #fed7aa; padding: 15px; border-radius: 6px; margin: 20px 0;">
        <ul style="margin: 0; padding-left: 20px;">
            <li><strong>Administrator-E-Mail:</strong> <?= htmlspecialchars($_SESSION['admin_user']['email'] ?? 'Nicht verfügbar') ?></li>
            <li><strong>Installations-Datum:</strong> <?= date('d.m.Y H:i:s') ?></li>
            <li><strong>Version:</strong> Vertragsverwaltung 1.0.0</li>
            <li><strong>PHP-Version:</strong> <?= PHP_VERSION ?></li>
        </ul>
    </div>
    
    <h3>Sicherheitsempfehlungen</h3>
    <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 6px; margin: 20px 0;">
        <ul style="margin: 0; padding-left: 20px;">
            <li>Löschen Sie das <code>/install</code>-Verzeichnis zur Sicherheit</li>
            <li>Stellen Sie sicher, dass die <code>/config</code>-Dateien nicht öffentlich zugänglich sind</li>
            <li>Aktivieren Sie regelmäßige Sicherheits-Updates</li>
            <li>Verwenden Sie starke Passwörter für alle Benutzerkonten</li>
        </ul>
    </div>
    
    <div class="navigation">
        <div></div>
        <a href="/" class="btn" style="background: #059669; font-size: 18px; padding: 15px 30px;">
            🏠 Zur Anwendung
        </a>
    </div>

<?php else: ?>
    <!-- Installation läuft -->
    <p>Die Installation wird durchgeführt. Bitte warten Sie einen Moment...</p>
    
    <div style="margin: 20px 0;">
        <div style="background: #e5e7eb; height: 20px; border-radius: 10px; overflow: hidden;">
            <div style="background: #2563eb; height: 100%; width: 100%; animation: pulse 1.5s infinite;"></div>
        </div>
    </div>
    
    <script>
        // Automatische Weiterleitung nach kurzer Verzögerung
        setTimeout(() => {
            window.location.href = '?step=complete&install=true';
        }, 2000);
    </script>

<?php endif; ?>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>