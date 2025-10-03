<?php
/**
 * Installation Step 1: Welcome
 */
?>

<h2>Willkommen zur Installation</h2>

<p>Herzlich willkommen beim Installations-Assistenten für die <strong>Vertragsverwaltung</strong>!</p>

<div class="alert alert-warning">
    <strong>Wichtige Hinweise vor der Installation:</strong>
    <ul style="margin-top: 10px; padding-left: 20px;">
        <li>Stellen Sie sicher, dass Sie <strong>PHP 8.1+</strong> und <strong>MySQL/MariaDB</strong> verfügbar haben</li>
        <li>Die Datenbankdaten sollten bereitliegen</li>
        <li>Sie benötigen Schreibrechte im Webverzeichnis</li>
        <li>Die Installation dauert ca. 2-3 Minuten</li>
    </ul>
</div>

<h3>Was wird installiert?</h3>
<ul style="margin: 20px 0; padding-left: 20px;">
    <li>📋 <strong>Vertragsverwaltung</strong> - Verwaltung aller Vertragsarten</li>
    <li>🔐 <strong>Zugangsdatenverwaltung</strong> - Sichere Speicherung von Anmeldedaten</li>
    <li>📄 <strong>Dokumentenverwaltung</strong> - Upload und Verwaltung von PDF-Dokumenten</li>
    <li>💰 <strong>Liquiditätsplanung</strong> - Finanzplanung und Cashflow-Prognosen</li>
    <li>👥 <strong>Freigabesystem</strong> - Sicheres Teilen mit Familie und Freunden</li>
    <li>🚨 <strong>Notfallsystem</strong> - Notfallzugriff für Vertrauenspersonen</li>
    <li>🔒 <strong>2FA-System</strong> - Zwei-Faktor-Authentifizierung</li>
    <li>⚙️ <strong>Admin-Panel</strong> - Vollständige Systemverwaltung</li>
</ul>

<h3>Systemanforderungen</h3>
<div class="requirements-grid">
    <div>PHP Version</div>
    <div class="<?= version_compare(PHP_VERSION, '8.1.0', '>=') ? 'status-ok' : 'status-error' ?>">
        <?= PHP_VERSION ?> <?= version_compare(PHP_VERSION, '8.1.0', '>=') ? '✓' : '✗' ?>
    </div>
    
    <div>MySQL/MariaDB</div>
    <div class="<?= extension_loaded('pdo_mysql') ? 'status-ok' : 'status-error' ?>">
        <?= extension_loaded('pdo_mysql') ? 'Verfügbar ✓' : 'Nicht verfügbar ✗' ?>
    </div>
    
    <div>Schreibrechte</div>
    <div class="<?= is_writable(ROOT_PATH) ? 'status-ok' : 'status-error' ?>">
        <?= is_writable(ROOT_PATH) ? 'OK ✓' : 'Fehlend ✗' ?>
    </div>
</div>

<?php if (version_compare(PHP_VERSION, '8.1.0', '<') || !extension_loaded('pdo_mysql') || !is_writable(ROOT_PATH)): ?>
    <div class="alert alert-error">
        <strong>Installation nicht möglich!</strong><br>
        Bitte erfüllen Sie zunächst alle Systemanforderungen.
    </div>
<?php else: ?>
    <div class="alert alert-success">
        <strong>Alle Systemanforderungen erfüllt!</strong><br>
        Sie können mit der Installation fortfahren.
    </div>
<?php endif; ?>

<div class="navigation">
    <div></div>
    <?php if (version_compare(PHP_VERSION, '8.1.0', '>=') && extension_loaded('pdo_mysql') && is_writable(ROOT_PATH)): ?>
        <a href="?step=requirements" class="btn">Installation starten →</a>
    <?php endif; ?>
</div>