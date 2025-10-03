<?php
/**
 * Installation Step 5: Basic Settings
 */

$errors = [];
$success = [];

// Grundeinstellungen speichern
if ($_POST && isset($_POST['save_settings'])) {
    $appName = trim($_POST['app_name'] ?? 'Vertragsverwaltung');
    $appUrl = trim($_POST['app_url'] ?? '');
    $timezone = $_POST['timezone'] ?? 'Europe/Berlin';
    $language = $_POST['language'] ?? 'de';
    $currency = $_POST['currency'] ?? 'EUR';
    $enableRegistration = isset($_POST['enable_registration']);
    $enableApi = isset($_POST['enable_api']);
    $debugMode = isset($_POST['debug_mode']);
    
    // Validierung
    if (empty($appName)) $errors[] = 'Anwendungsname ist erforderlich';
    if (!empty($appUrl) && !filter_var($appUrl, FILTER_VALIDATE_URL)) $errors[] = 'Ungültige URL';
    
    if (empty($errors)) {
        $_SESSION['app_settings'] = [
            'name' => $appName,
            'url' => $appUrl,
            'timezone' => $timezone,
            'language' => $language,
            'currency' => $currency,
            'enable_registration' => $enableRegistration,
            'enable_api' => $enableApi,
            'debug' => $debugMode
        ];
        
        $success[] = 'Grundeinstellungen gespeichert!';
    }
}

// Redirect-Verarbeitung wurde in den Hauptwizard verschoben

// Standardwerte
$appName = $_POST['app_name'] ?? $_SESSION['app_settings']['name'] ?? 'Vertragsverwaltung';
$appUrl = $_POST['app_url'] ?? $_SESSION['app_settings']['url'] ?? (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$timezone = $_POST['timezone'] ?? $_SESSION['app_settings']['timezone'] ?? 'Europe/Berlin';
$language = $_POST['language'] ?? $_SESSION['app_settings']['language'] ?? 'de';
$currency = $_POST['currency'] ?? $_SESSION['app_settings']['currency'] ?? 'EUR';
$enableRegistration = $_POST['enable_registration'] ?? $_SESSION['app_settings']['enable_registration'] ?? false;
$enableApi = $_POST['enable_api'] ?? $_SESSION['app_settings']['enable_api'] ?? true;
$debugMode = $_POST['debug_mode'] ?? $_SESSION['app_settings']['debug'] ?? false;

?>

<h2>Grundeinstellungen konfigurieren</h2>

<p>Konfigurieren Sie die grundlegenden Einstellungen für Ihre Vertragsverwaltung.</p>

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

<form method="post">
    <h3>Anwendungseinstellungen</h3>
    
    <div class="form-group">
        <label for="app_name">Anwendungsname:</label>
        <input type="text" id="app_name" name="app_name" value="<?= htmlspecialchars($appName) ?>" 
               placeholder="Vertragsverwaltung" required>
        <small style="color: #6b7280;">Wird in der Kopfzeile und E-Mails angezeigt</small>
    </div>
    
    <div class="form-group">
        <label for="app_url">Anwendungs-URL:</label>
        <input type="url" id="app_url" name="app_url" value="<?= htmlspecialchars($appUrl) ?>" 
               placeholder="https://example.com">
        <small style="color: #6b7280;">Vollständige URL für E-Mail-Links und API</small>
    </div>
    
    <h3>Regionalisierung</h3>
    
    <div class="form-group">
        <label for="timezone">Zeitzone:</label>
        <select id="timezone" name="timezone">
            <option value="Europe/Berlin" <?= $timezone === 'Europe/Berlin' ? 'selected' : '' ?>>Europa/Berlin (MEZ/MESZ)</option>
            <option value="Europe/Vienna" <?= $timezone === 'Europe/Vienna' ? 'selected' : '' ?>>Europa/Wien (MEZ/MESZ)</option>
            <option value="Europe/Zurich" <?= $timezone === 'Europe/Zurich' ? 'selected' : '' ?>>Europa/Zürich (MEZ/MESZ)</option>
            <option value="UTC" <?= $timezone === 'UTC' ? 'selected' : '' ?>>UTC (Koordinierte Weltzeit)</option>
        </select>
    </div>
    
    <div class="form-group">
        <label for="language">Sprache:</label>
        <select id="language" name="language">
            <option value="de" <?= $language === 'de' ? 'selected' : '' ?>>Deutsch</option>
            <option value="en" <?= $language === 'en' ? 'selected' : '' ?>>English</option>
        </select>
    </div>
    
    <div class="form-group">
        <label for="currency">Standardwährung:</label>
        <select id="currency" name="currency">
            <option value="EUR" <?= $currency === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
            <option value="CHF" <?= $currency === 'CHF' ? 'selected' : '' ?>>Schweizer Franken (CHF)</option>
            <option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>US-Dollar (USD)</option>
            <option value="GBP" <?= $currency === 'GBP' ? 'selected' : '' ?>>Britisches Pfund (GBP)</option>
        </select>
    </div>
    
    <h3>Funktionen</h3>
    
    <div style="margin-bottom: 15px;">
        <label style="display: flex; align-items: center; cursor: pointer;">
            <input type="checkbox" name="enable_registration" <?= $enableRegistration ? 'checked' : '' ?> style="margin-right: 10px;">
            <strong>Benutzerregistrierung aktivieren</strong>
        </label>
        <small style="color: #6b7280; margin-left: 25px;">Erlaubt neuen Benutzern die Selbstregistrierung</small>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: flex; align-items: center; cursor: pointer;">
            <input type="checkbox" name="enable_api" <?= $enableApi ? 'checked' : '' ?> style="margin-right: 10px;">
            <strong>API-Schnittstelle aktivieren</strong>
        </label>
        <small style="color: #6b7280; margin-left: 25px;">Ermöglicht Zugriff über REST-API</small>
    </div>
    
    <h3>Entwicklung</h3>
    
    <div style="margin-bottom: 20px;">
        <label style="display: flex; align-items: center; cursor: pointer;">
            <input type="checkbox" name="debug_mode" <?= $debugMode ? 'checked' : '' ?> style="margin-right: 10px;">
            <strong>Debug-Modus aktivieren</strong>
        </label>
        <small style="color: #6b7280; margin-left: 25px;">Nur für Entwicklung/Tests! Zeigt detaillierte Fehlermeldungen an.</small>
    </div>
    
    <div style="margin-bottom: 20px;">
        <button type="submit" name="save_settings" class="btn">
            💾 Einstellungen speichern
        </button>
        
        <?php if (!empty($success)): ?>
            <button type="submit" name="complete_installation" class="btn" style="margin-left: 10px; background: #059669;">
                🚀 Installation abschließen
            </button>
        <?php endif; ?>
    </div>
</form>

<h3>Konfigurationsübersicht</h3>
<div style="background: #f9fafb; padding: 15px; border-radius: 6px;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
        <div><strong>Datenbank:</strong></div>
        <div><?= isset($_SESSION['db_config']) ? '✓ Konfiguriert' : '✗ Nicht konfiguriert' ?></div>
        
        <div><strong>Administrator:</strong></div>
        <div><?= isset($_SESSION['admin_user']) ? '✓ Konfiguriert' : '✗ Nicht konfiguriert' ?></div>
        
        <div><strong>Einstellungen:</strong></div>
        <div><?= isset($_SESSION['app_settings']) ? '✓ Konfiguriert' : '✗ Nicht konfiguriert' ?></div>
    </div>
</div>

<div class="navigation">
    <a href="?step=admin" class="btn btn-secondary">← Zurück</a>
    <?php if (isset($_SESSION['db_config'], $_SESSION['admin_user'], $_SESSION['app_settings'])): ?>
        <a href="?step=complete&install=true" class="btn" style="background: #059669;">Installation abschließen →</a>
    <?php endif; ?>
</div>