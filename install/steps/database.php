<?php
/**
 * Installation Step 3: Database Configuration
 */

$errors = [];
$success = [];

// Formular verarbeiten
if ($_POST && isset($_POST['test_connection'])) {
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    
    // Validierung
    if (empty($dbHost)) $errors[] = 'Datenbank-Host ist erforderlich';
    if (empty($dbName)) $errors[] = 'Datenbankname ist erforderlich';
    if (empty($dbUser)) $errors[] = 'Benutzername ist erforderlich';
    
    if (empty($errors)) {
        try {
            // Verbindung testen
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            
            // Datenbank erstellen falls sie nicht existiert
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Verbindung zur spezifischen Datenbank
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // Datenbank-Konfiguration speichern
            $_SESSION['db_config'] = [
                'host' => $dbHost,
                'port' => $dbPort,
                'name' => $dbName,
                'username' => $dbUser,
                'password' => $dbPass
            ];
            
            $success[] = 'Datenbankverbindung erfolgreich getestet!';
            
        } catch (PDOException $e) {
            $errors[] = 'Datenbankverbindung fehlgeschlagen: ' . $e->getMessage();
        }
    }
}

// Weiter zum nächsten Schritt
if ($_POST && isset($_POST['continue']) && isset($_SESSION['db_config'])) {
    header('Location: ?step=admin');
    exit;
}

// Standardwerte
$dbHost = $_POST['db_host'] ?? $_SESSION['db_config']['host'] ?? 'localhost';
$dbPort = $_POST['db_port'] ?? $_SESSION['db_config']['port'] ?? '3306';
$dbName = $_POST['db_name'] ?? $_SESSION['db_config']['name'] ?? '';
$dbUser = $_POST['db_user'] ?? $_SESSION['db_config']['username'] ?? '';
$dbPass = $_POST['db_pass'] ?? $_SESSION['db_config']['password'] ?? '';

?>

<h2>Datenbank konfigurieren</h2>

<p>Bitte geben Sie die Verbindungsdaten für Ihre MySQL/MariaDB-Datenbank ein.</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <strong>Fehler:</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?php foreach ($success as $msg): ?>
            <div><?= htmlspecialchars($msg) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="alert alert-warning">
    <strong>Hinweis:</strong> Die Datenbank wird automatisch erstellt, falls sie noch nicht existiert. 
    Stellen Sie sicher, dass der Benutzer die erforderlichen Rechte besitzt.
</div>

<form method="post">
    <div class="form-group">
        <label for="db_host">Datenbank-Host:</label>
        <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($dbHost) ?>" 
               placeholder="localhost oder IP-Adresse" required>
    </div>
    
    <div class="form-group">
        <label for="db_port">Port:</label>
        <input type="number" id="db_port" name="db_port" value="<?= htmlspecialchars($dbPort) ?>" 
               placeholder="3306" min="1" max="65535">
    </div>
    
    <div class="form-group">
        <label for="db_name">Datenbankname:</label>
        <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($dbName) ?>" 
               placeholder="vertragsverwaltung" required>
        <small style="color: #6b7280;">Wird automatisch erstellt, falls nicht vorhanden</small>
    </div>
    
    <div class="form-group">
        <label for="db_user">Benutzername:</label>
        <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($dbUser) ?>" 
               placeholder="Datenbank-Benutzername" required>
    </div>
    
    <div class="form-group">
        <label for="db_pass">Passwort:</label>
        <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($dbPass) ?>" 
               placeholder="Datenbank-Passwort">
    </div>
    
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
        <button type="submit" name="test_connection" class="btn btn-secondary">
            🔍 Verbindung testen
        </button>
        
        <?php if (!empty($success)): ?>
            <button type="submit" name="continue" class="btn">
                ✓ Weiter mit dieser Konfiguration
            </button>
        <?php endif; ?>
    </div>
</form>

<h3>Typische Hosting-Konfigurationen</h3>
<div style="background: #f9fafb; padding: 15px; border-radius: 6px; margin: 20px 0;">
    <strong>Shared Hosting:</strong>
    <ul style="margin: 10px 0; padding-left: 20px;">
        <li>Host: meist <code>localhost</code> oder <code>127.0.0.1</code></li>
        <li>Port: Standard <code>3306</code></li>
        <li>Datenbankname: oft vom Provider vorgegeben</li>
        <li>Benutzer & Passwort: vom Hosting-Provider bereitgestellt</li>
    </ul>
</div>

<h3>Erforderliche Datenbankrechte</h3>
<div style="background: #f9fafb; padding: 15px; border-radius: 6px;">
    <ul style="margin: 0; padding-left: 20px;">
        <li><strong>CREATE:</strong> Zum Erstellen der Datenbank</li>
        <li><strong>SELECT, INSERT, UPDATE, DELETE:</strong> Für normale Operationen</li>
        <li><strong>CREATE, ALTER, DROP:</strong> Für Tabellen-Management</li>
        <li><strong>INDEX:</strong> Für Performance-Optimierungen</li>
    </ul>
</div>

<div class="navigation">
    <a href="?step=requirements" class="btn btn-secondary">← Zurück</a>
    <?php if (isset($_SESSION['db_config'])): ?>
        <a href="?step=admin" class="btn">Weiter →</a>
    <?php endif; ?>
</div>