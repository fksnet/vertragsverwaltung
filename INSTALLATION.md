# Vertragsverwaltung - Webhosting Installation

## 🚀 Schnelle Installation

Diese Vertragsverwaltung-Anwendung kann einfach auf jedem Standard-Webhosting mit PHP und MySQL/MariaDB installiert werden, ähnlich wie WordPress.

## 📋 Systemanforderungen

### Mindestvoraussetzungen
- **PHP 8.1** oder höher
- **MySQL 8.0** oder **MariaDB 10.5** oder höher
- **Apache-Webserver** mit mod_rewrite aktiviert
- Mindestens **64 MB** PHP Memory Limit
- **512 MB** freier Speicherplatz

### Erforderliche PHP-Extensions
- `pdo_mysql` - Datenbankverbindung
- `sodium` - Verschlüsselung (normalerweise standardmäßig aktiviert)
- `intl` - Internationalisierung
- `mbstring` - Multibyte-Strings
- `fileinfo` - Dateityp-Erkennung
- `json` - JSON-Verarbeitung
- `openssl` - SSL-Funktionen

### Empfohlene PHP-Extensions
- `gd` oder `imagick` - Bildverarbeitung
- `zip` - Archiv-Funktionen
- `curl` - HTTP-Client
- `xml` - XML-Verarbeitung

## 🏗️ Installationsschritte

### 1. Dateien hochladen
1. **ZIP-Datei herunterladen** und lokal entpacken
2. **Alle Dateien via FTP/SFTP** in das Webroot-Verzeichnis Ihres Hostings hochladen
   - Bei den meisten Hostern ist das: `public_html/`, `www/`, oder `htdocs/`
3. **Dateiberechtigungen prüfen**:
   - Ordner: 755 (rwxr-xr-x)
   - Dateien: 644 (rw-r--r--)
   - Writable-Ordner: 775 (rwxrwxr-x)

### 2. Datenbank erstellen
1. **Datenbank anlegen** über das Control Panel Ihres Hosters
   - Wählen Sie UTF8MB4 als Zeichensatz
   - Notieren Sie sich: Datenbankname, Benutzername, Passwort, Host
2. **Datenbankbenutzer erstellen** (falls noch nicht vorhanden)
   - Vollzugriff auf die erstellte Datenbank gewähren

### 3. Installation über Web-Interface
1. **Ihre Domain im Browser öffnen** (`https://ihre-domain.de/`)
2. **Automatische Weiterleitung** zum Installations-Wizard
3. **6-Schritt-Installation durchlaufen**:

#### Schritt 1: Willkommen
- Übersicht über die Installation
- System-Check wird durchgeführt

#### Schritt 2: Systemanforderungen
- Prüfung aller PHP-Extensions
- Berechtigungen werden getestet
- Probleme werden mit Lösungsvorschlägen angezeigt

#### Schritt 3: Datenbank-Konfiguration
- Datenbankverbindung konfigurieren
- Verbindung wird automatisch getestet
- Datenbank wird automatisch erstellt (falls möglich)

#### Schritt 4: Administrator-Account
- Admin-Benutzerdaten festlegen
- Starkes Passwort wählen (wird geprüft)
- E-Mail-Adresse für wichtige Benachrichtigungen

#### Schritt 5: Grundeinstellungen
- App-Name und URL konfigurieren
- Zeitzone und Währung einstellen
- Erweiterte Optionen (Registrierung, API, Debug)

#### Schritt 6: Installation abschließen
- Alle Einstellungen werden gespeichert
- Datenbank-Tabellen werden erstellt
- Konfigurationsdateien werden generiert
- **Installation erfolgreich!** 🎉

### 4. Nach der Installation
1. **Login durchführen** mit den erstellten Admin-Daten
2. **Grundeinstellungen prüfen** im Admin-Bereich
3. **Erste Verträge anlegen** und das System testen
4. **Backup einrichten** (siehe Sicherheitshinweise)

## 🔒 Sicherheitshinweise

### Sofort nach Installation
- [ ] **Admin-Passwort ändern** (falls Standard verwendet)
- [ ] **`.env` Datei schützen** (bereits durch .htaccess geschützt)
- [ ] **Installation-Ordner löschen** (wird automatisch deaktiviert)
- [ ] **Datenbank-Zugangsdaten sichern**

### Regelmäßige Wartung
- [ ] **Regelmäßige Backups** von Datenbank und Dateien
- [ ] **Updates installieren** wenn verfügbar
- [ ] **Starke Passwörter verwenden**
- [ ] **Zwei-Faktor-Authentifizierung aktivieren**

### Empfohlene Maßnahmen
```apache
# Zusätzliche .htaccess-Regeln (bereits enthalten)
# IP-Whitelist für Admin-Bereich
# Rate-Limiting
# Security Headers
```

## 🛠️ Fehlerbehebung

### Häufige Probleme

#### 1. "Internal Server Error"
**Ursachen:**
- .htaccess-Regeln nicht unterstützt
- PHP-Fehler
- Falsche Dateiberechtigungen

**Lösung:**
- Error-Log des Hosters prüfen
- .htaccess temporär umbenennen
- PHP-Version prüfen (min. 8.1)

#### 2. "Database Connection Error"
**Ursachen:**
- Falsche Datenbankdaten
- MySQL-Server nicht erreichbar
- Benutzer hat keine Berechtigung

**Lösung:**
- Datenbank-Credentials prüfen
- Host-Adresse korrekt? (oft `localhost`)
- Datenbankbenutzer hat Vollzugriff?

#### 3. "PHP Extensions Missing"
**Ursachen:**
- Benötigte Extensions nicht installiert
- Falsche PHP-Version ausgewählt

**Lösung:**
- Hosting Control Panel prüfen
- PHP-Version auf mindestens 8.1 einstellen
- Extensions über Control Panel aktivieren

#### 4. "File Permissions Error"
**Ursachen:**
- Upload-Verzeichnisse nicht beschreibbar
- Config-Verzeichnis nicht beschreibbar

**Lösung:**
```bash
# Korrekte Berechtigungen setzen
chmod 755 storage/ uploads/ config/
chmod 644 *.php
```

### Support-Kontakt
Bei technischen Problemen:
1. **Error-Logs prüfen** (im Hosting Control Panel)
2. **PHP-Info anzeigen** (`<?php phpinfo(); ?>`)
3. **Minimale Systemanforderungen erfüllt?**

## 📁 Verzeichnisstruktur nach Installation

```
ihre-domain.de/
├── .htaccess              # Apache-Konfiguration
├── index.php              # Haupt-Einstiegspunkt
├── bootstrap/             # App-Initialisierung
├── app/                   # Anwendungslogik
│   ├── Controllers/       # HTTP-Controller
│   ├── Models/           # Datenmodelle
│   ├── Views/            # Templates
│   └── Core/             # Framework-Kern
├── config/               # Konfigurationsdateien
│   ├── app.php          # Haupt-Konfiguration
│   └── database.php     # Datenbank-Konfiguration
├── public/              # Öffentliche Assets
│   ├── assets/         # CSS/JS-Dateien
│   └── uploads/        # Upload-Verzeichnis
├── database/           # Schema und Migrationen
├── storage/            # Cache und Logs (writable)
└── install/           # Installations-Wizard (nach Installation inaktiv)
```

## 🎯 Erste Schritte nach Installation

1. **📱 Dashboard erkunden** - Übersicht über alle Funktionen
2. **📄 Ersten Vertrag anlegen** - System mit echten Daten testen
3. **🏢 Korrespondenten hinzufügen** - Vertragspartner verwalten
4. **🔐 Benutzer einladen** - Familienmitglieder hinzufügen
5. **💰 Liquiditätsplanung nutzen** - Finanzübersicht erstellen
6. **⏰ Erinnerungen aktivieren** - Automatische Benachrichtigungen

## 📞 Support & Community

- **GitHub Repository:** https://github.com/fksnet/vertragsverwaltung
- **Dokumentation:** Vollständige Docs im Repository
- **Issues:** Bug-Reports und Feature-Requests via GitHub
- **Updates:** Neue Versionen werden über GitHub veröffentlicht

---

**🎉 Herzlichen Glückwunsch!** Sie haben erfolgreich Ihre eigene Vertragsverwaltung installiert.