<?php
/**
 * Installation Step 2: System Requirements Check
 */

$errors = [];
$success = [];

// Weiter zum nächsten Schritt
if ($_POST && isset($_POST['continue'])) {
    // Alle erforderlichen Checks nochmals durchführen
    $allPassed = true;
    
    // Kurzer Re-Check der wichtigsten Anforderungen
    if (version_compare(PHP_VERSION, '8.1.0', '<')) $allPassed = false;
    if (!extension_loaded('pdo_mysql')) $allPassed = false;
    if (!is_writable(ROOT_PATH)) $allPassed = false;
    
    if ($allPassed) {
        $_SESSION['requirements_passed'] = true;
        header('Location: ?step=database');
        exit;
    } else {
        $errors[] = 'Nicht alle Systemanforderungen sind erfüllt.';
    }
}

// System-Checks durchführen
$checks = [
    'PHP Version (≥ 8.1)' => [
        'status' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'value' => PHP_VERSION,
        'required' => true
    ],
    'PDO MySQL Extension' => [
        'status' => extension_loaded('pdo_mysql'),
        'value' => extension_loaded('pdo_mysql') ? 'Installiert' : 'Nicht verfügbar',
        'required' => true
    ],
    'OpenSSL Extension' => [
        'status' => extension_loaded('openssl'),
        'value' => extension_loaded('openssl') ? 'Installiert' : 'Nicht verfügbar',
        'required' => true
    ],
    'Mbstring Extension' => [
        'status' => extension_loaded('mbstring'),
        'value' => extension_loaded('mbstring') ? 'Installiert' : 'Nicht verfügbar',
        'required' => true
    ],
    'JSON Extension' => [
        'status' => extension_loaded('json'),
        'value' => extension_loaded('json') ? 'Installiert' : 'Nicht verfügbar',
        'required' => true
    ],
    'GD Extension' => [
        'status' => extension_loaded('gd'),
        'value' => extension_loaded('gd') ? 'Installiert' : 'Nicht verfügbar',
        'required' => false,
        'note' => 'Für Thumbnail-Generierung'
    ],
    'cURL Extension' => [
        'status' => extension_loaded('curl'),
        'value' => extension_loaded('curl') ? 'Installiert' : 'Nicht verfügbar',
        'required' => false,
        'note' => 'Für externe API-Calls'
    ]
];

// Verzeichnis-Checks
$directories = [
    'Root Directory' => ROOT_PATH,
    'Config Directory' => CONFIG_PATH,
    'Storage Directory' => ROOT_PATH . '/storage',
    'Cache Directory' => ROOT_PATH . '/storage/cache',
    'Logs Directory' => ROOT_PATH . '/storage/logs',
    'Uploads Directory' => ROOT_PATH . '/storage/uploads'
];

foreach ($directories as $name => $path) {
    $checks[$name . ' (Schreibbar)'] = [
        'status' => is_dir($path) ? is_writable($path) : (mkdir($path, 0755, true) && is_writable($path)),
        'value' => is_dir($path) ? (is_writable($path) ? 'Schreibbar' : 'Nicht schreibbar') : 'Erstellt',
        'required' => true
    ];
}

// PHP-Konfiguration prüfen
$phpSettings = [
    'Memory Limit' => [
        'current' => ini_get('memory_limit'),
        'recommended' => '256M',
        'status' => (int)ini_get('memory_limit') >= 256 || ini_get('memory_limit') === '-1'
    ],
    'Max Execution Time' => [
        'current' => ini_get('max_execution_time') . 's',
        'recommended' => '≥ 60s',
        'status' => (int)ini_get('max_execution_time') >= 60 || ini_get('max_execution_time') == 0
    ],
    'Upload Max Filesize' => [
        'current' => ini_get('upload_max_filesize'),
        'recommended' => '≥ 32M',
        'status' => parseSize(ini_get('upload_max_filesize')) >= parseSize('32M')
    ],
    'Post Max Size' => [
        'current' => ini_get('post_max_size'),
        'recommended' => '≥ 32M',
        'status' => parseSize(ini_get('post_max_size')) >= parseSize('32M')
    ]
];

// Hilfsfunktion für Größenkonvertierung
function parseSize($size) {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);
    if ($unit) {
        return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    }
    return round($size);
}

$allRequired = true;
foreach ($checks as $check) {
    if ($check['required'] && !$check['status']) {
        $allRequired = false;
        break;
    }
}

?>

<h2>Systemanforderungen prüfen</h2>

<p>Überprüfung der Systemvoraussetzungen für die Vertragsverwaltung...</p>

<h3>PHP-Erweiterungen</h3>
<div class="requirements-grid">
    <?php foreach ($checks as $name => $check): ?>
        <div class="req-item">
            <div>
                <?= htmlspecialchars($name) ?>
                <?php if (isset($check['note'])): ?>
                    <small style="color: #6b7280;"> (<?= htmlspecialchars($check['note']) ?>)</small>
                <?php endif; ?>
            </div>
            <div class="<?= $check['status'] ? 'status-ok' : 'status-error' ?>">
                <?= htmlspecialchars($check['value']) ?>
                <?= $check['status'] ? ' ✓' : ' ✗' ?>
                <?php if ($check['required'] && !$check['status']): ?>
                    <strong> (Erforderlich)</strong>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h3>PHP-Konfiguration</h3>
<div class="requirements-grid">
    <?php foreach ($phpSettings as $name => $setting): ?>
        <div class="req-item">
            <div>
                <?= htmlspecialchars($name) ?>
                <small style="color: #6b7280;"> (Empfohlen: <?= htmlspecialchars($setting['recommended']) ?>)</small>
            </div>
            <div class="<?= $setting['status'] ? 'status-ok' : 'status-error' ?>">
                <?= htmlspecialchars($setting['current']) ?>
                <?= $setting['status'] ? ' ✓' : ' ⚠' ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($allRequired): ?>
    <div class="alert alert-success">
        <strong>Alle erforderlichen Voraussetzungen erfüllt!</strong><br>
        Die Installation kann fortgesetzt werden.
    </div>
<?php else: ?>
    <div class="alert alert-error">
        <strong>Nicht alle Voraussetzungen erfüllt!</strong><br>
        Bitte beheben Sie die markierten Probleme vor der Fortsetzung der Installation.
        
        <h4>Häufige Lösungsansätze:</h4>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <li><strong>PHP-Erweiterungen:</strong> Kontaktieren Sie Ihren Hosting-Provider oder installieren Sie die fehlenden Erweiterungen</li>
            <li><strong>Schreibrechte:</strong> Setzen Sie die Ordnerrechte auf 755 oder 777</li>
            <li><strong>PHP-Limits:</strong> Erhöhen Sie die Werte in der php.ini oder via .htaccess</li>
        </ul>
    </div>
<?php endif; ?>

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

<form method="post" style="margin-top: 20px;">
    <div class="navigation">
        <a href="?step=welcome" class="btn btn-secondary">← Zurück</a>
        <?php if ($allRequired): ?>
            <button type="submit" name="continue" class="btn">Weiter →</button>
        <?php endif; ?>
    </div>
</form>