# Installation Troubleshooting Guide

## 🐛 Häufige Probleme und Lösungen

### Problem: Wizard-Schritt 2 hängt / "Es passiert nichts"

**Symptome:** 
- Schritt 1 (Willkommen) zeigt grüne Systemanforderungen
- Klick auf "Installation starten" führt zu Schritt 2
- Schritt 2 wird angezeigt, aber Navigation funktioniert nicht

**Mögliche Ursachen & Lösungen:**

#### 1. Session-Probleme
```
Lösung: PHP-Sessions prüfen
- Stellen Sie sicher, dass session.save_path beschreibbar ist
- Überprüfen Sie: <?php var_dump(session_start()); ?>
```

#### 2. .htaccess URL-Rewriting
```
Test: Temporär .htaccess deaktivieren
- Benennen Sie .htaccess zu .htaccess_backup um
- Navigieren Sie direkt: /install/index.php?step=database
- Falls funktioniert: .htaccess schrittweise aktivieren
```

#### 3. PHP-Fehlermeldungen versteckt
```
Debug-Modus aktivieren:
- URL: /install/?step=requirements&debug=1
- Zeigt detaillierte Fehlerinformationen
- Session-Daten und POST-Parameter werden angezeigt
```

#### 4. Fehlende PHP-Extensions
```
Prüfen Sie genau:
- Alle grünen Häkchen in Schritt 1?
- Insbesondere: pdo_mysql, openssl, mbstring
- Bei Rot: Hosting-Provider kontaktieren
```

### Problem: "Internal Server Error" nach Wizard-Start

**Lösung:**
1. **Error-Logs prüfen** (Hosting Control Panel)
2. **PHP-Version prüfen** (mindestens 8.1 erforderlich)
3. **.htaccess minimieren**:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^install/?$ install/index.php [L]
    RewriteRule ^install/(.+)$ install/index.php?step=$1 [L,QSA]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php?route=$1 [L,QSA]
</IfModule>
```

### Problem: Datenbankverbindung schlägt fehl

**Häufige Ursachen:**
- **Host falsch**: Meist `localhost` bei Shared Hosting
- **Port falsch**: Standard ist `3306`
- **Credentials falsch**: Vom Hosting-Provider bereitgestellt
- **Datenbank existiert nicht**: Meist muss diese vorher angelegt werden

**Test-Snippet:**
```php
<?php
try {
    $pdo = new PDO("mysql:host=DEIN_HOST;port=3306", "USER", "PASS");
    echo "Verbindung erfolgreich!";
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage();
}
?>
```

### Problem: Wizard zeigt weiße Seite

**Schritte:**
1. **PHP-Fehler aktivieren**:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

2. **Debug-URL verwenden**: `?debug=1`

3. **Direkte Datei-Zugriffe testen**:
   - `/install/index.php` 
   - `/install/steps/welcome.php`

### Problem: 500 Fehler nach erfolgreicher Installation

**Symptome:**
- Wizard läuft erfolgreich durch
- Nach Installation: "Internal Server Error 500"
- Error-Logs können SSL-Warnungen enthalten (nicht relevant)

**Diagnose-Schritte:**

#### 1. Debug-Modus aktivieren
```
URL: https://ihre-domain.de/?debug=1
Zeigt: Dateipfade, Konfigurationsstatus
```

#### 2. Test-Controller verwenden
```
URL: https://ihre-domain.de/?route=test
Sollte zeigen: Installations-Status-Seite
```

#### 3. Konfigurationsdateien prüfen
```
Dateien müssen existieren:
- /config/app.php
- /config/database.php
- /config/security.php
```

#### 4. Berechtigungen prüfen
```bash
chmod 755 storage/ config/ app/
chmod 644 config/*.php
chmod 775 storage/logs/ storage/cache/
```

**Häufige Ursachen:**

1. **Fehlende Konfigurationsdateien**
   - Installation nicht vollständig abgeschlossen
   - Lösung: Installation wiederholen

2. **Autoloader-Probleme**
   - PHP-Klassen können nicht geladen werden  
   - Lösung: PHP-Version prüfen (≥ 8.1)

3. **Datenbankverbindung**
   - Verbindungsdaten inkorrekt
   - Lösung: `/config/database.php` manuell prüfen

### Problem: Installation bricht bei Schritt 6 ab

**Häufig:** Datenbankrechte unzureichend

**Erforderliche MySQL-Rechte:**
- CREATE DATABASE
- CREATE TABLE
- ALTER TABLE  
- SELECT, INSERT, UPDATE, DELETE
- INDEX

**Test:** Führen Sie diese MySQL-Befehle manuell aus:
```sql
CREATE DATABASE test_db;
USE test_db;
CREATE TABLE test (id INT PRIMARY KEY);
DROP DATABASE test_db;
```

## 🔧 Erweiterte Debugging-Techniken

### 1. PHP-Info prüfen
Erstellen Sie: `phpinfo.php`
```php
<?php phpinfo(); ?>
```
Überprüfen Sie:
- PHP-Version ≥ 8.1
- Aktivierte Extensions
- Session-Konfiguration

### 2. Minimale Installation testen
Erstellen Sie: `minimal_test.php`
```php
<?php
session_start();
echo "PHP: " . PHP_VERSION . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'OK' : 'FEHLT') . "<br>";
echo "Schreibrechte: " . (is_writable('.') ? 'OK' : 'FEHLT') . "<br>";
?>
```

### 3. Manuelle Schritt-Navigation
Falls der Wizard nicht funktioniert:
```
Direkte URLs:
/install/index.php?step=welcome
/install/index.php?step=requirements  
/install/index.php?step=database
/install/index.php?step=admin
/install/index.php?step=settings
/install/index.php?step=complete
```

## 📞 Support-Kontakt

Falls die Probleme bestehen bleiben:

1. **Error-Logs sammeln** (vom Hosting-Provider)
2. **PHP-Info bereitstellen** (`phpinfo()`)
3. **Debug-URL testen** (`?debug=1`)
4. **GitHub Issue erstellen**: 
   - Repository: https://github.com/fksnet/vertragsverwaltung
   - Template: Bug Report verwenden
   - Logs und Konfiguration anhängen

## 🚑 Notfall-Installation

Falls der Wizard gar nicht funktioniert:

1. **Manuelle Konfiguration** (siehe `INSTALLATION.md`)
2. **Datenbank manuell importieren** (`/install/database/schema.sql`)
3. **Config-Dateien manuell erstellen**
4. **Nach Installation:** Wizard-Ordner löschen

---

**💡 Tipp:** Die meisten Probleme entstehen durch .htaccess-Inkompatibilität oder fehlende PHP-Extensions. Debug-Modus (`?debug=1`) zeigt meist die genaue Ursache!