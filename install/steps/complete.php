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
        try {
            // 1. Konfigurationsdateien erstellen
            $configCreated = createConfigFiles();
            
            // 2. Datenbank initialisieren
            $dbInitialized = initializeDatabase();
            
            // 3. Administrator-Benutzer erstellen
            $adminCreated = createAdminUser();
            
            // 4. Standard-Daten einfügen
            $defaultDataCreated = createDefaultData();
            
            if ($configCreated && $dbInitialized && $adminCreated && $defaultDataCreated) {
                // Installation als abgeschlossen markieren
                updateConfigFile(['installed' => true, 'installed_at' => date('Y-m-d H:i:s')]);
                
                // Session-Daten löschen
                unset($_SESSION['db_config'], $_SESSION['admin_user'], $_SESSION['app_settings']);
                
                $success[] = 'Installation erfolgreich abgeschlossen!';
                
                // Installer-Dateien löschen (optional, zur Sicherheit)
                // $this->removeInstallerFiles();
                
            } else {
                $errors[] = 'Installation konnte nicht vollständig abgeschlossen werden.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Installationsfehler: ' . $e->getMessage();
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
        
        // SQL-Migrations-Datei laden
        $sqlFile = DATABASE_PATH . '/schema.sql';
        if (!file_exists($sqlFile)) {
            // Basis-Tabellen erstellen
            $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
            if (!$sql) {
                throw new Exception('Schema-Datei nicht gefunden');
            }
        } else {
            $sql = file_get_contents($sqlFile);
        }
        
        // SQL ausführen
        $pdo->exec($sql);
        
        return true;
    } catch (Exception $e) {
        error_log('Database initialization error: ' . $e->getMessage());
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
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        
        // Admin-Benutzer einfügen
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, is_active, email_verified_at, created_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $admin['name'],
            $admin['email'],
            $admin['password']
        ]);
        
        if ($result) {
            $userId = $pdo->lastInsertId();
            
            // Admin-Rolle zuweisen (falls Rollen-System vorhanden)
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO user_roles (user_id, role_id)
                SELECT ?, id FROM roles WHERE name = 'admin' LIMIT 1
            ");
            $stmt->execute([$userId]);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log('Admin user creation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Erstellt Standard-Daten
 */
function createDefaultData(): bool {
    try {
        $config = $_SESSION['db_config'];
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        
        // Standard-Rollen erstellen
        $roles = [
            ['name' => 'admin', 'display_name' => 'Administrator', 'description' => 'Vollzugriff auf alle Funktionen'],
            ['name' => 'user', 'display_name' => 'Benutzer', 'description' => 'Standard-Benutzerrolle'],
        ];
        
        foreach ($roles as $role) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO roles (name, display_name, description, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$role['name'], $role['display_name'], $role['description']]);
        }
        
        // Standard-Einstellungen erstellen
        $settings = [
            ['key' => 'app_name', 'value' => $_SESSION['app_settings']['name'], 'category' => 'general'],
            ['key' => 'app_timezone', 'value' => $_SESSION['app_settings']['timezone'], 'category' => 'general'],
            ['key' => 'app_language', 'value' => $_SESSION['app_settings']['language'], 'category' => 'general'],
            ['key' => 'app_currency', 'value' => $_SESSION['app_settings']['currency'], 'category' => 'general'],
        ];
        
        foreach ($settings as $setting) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO settings (`key`, `value`, category, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$setting['key'], $setting['value'], $setting['category']]);
        }
        
        return true;
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