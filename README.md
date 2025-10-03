# Vertragsverwaltung

[![CI/CD Pipeline](https://github.com/fksnet/vertragsverwaltung/actions/workflows/ci.yml/badge.svg)](https://github.com/fksnet/vertragsverwaltung/actions/workflows/ci.yml)

Eine moderne, wunderschöne und nutzerfreundliche Web-Applikation zur Familien-Vertragsverwaltung.

## 🚀 Technologie-Stack

- **Backend:** PHP 8.3+ (ohne Framework)
- **Datenbank:** MariaDB 10.5+
- **Frontend:** htmx, Alpine.js, TailwindCSS + daisyUI
- **Build-Tools:** Node.js, PostCSS, Tailwind CLI
- **Testing:** PHPUnit, PHPStan
- **CI/CD:** GitHub Actions

## 📋 Funktionen

### Kernfunktionen
- 📊 **Dashboard** mit KPIs und Übersichtskarten
- 📝 **Vertragsverwaltung** (CRUD) mit umfassenden Feldern
- 🏢 **Korrespondenten-Management**
- 🔐 **Sichere Zugangsdaten** (verschlüsselt gespeichert)
- 📄 **Dokument-Upload** (PDF) mit sicherer Verwaltung
- 💰 **Liquiditätsplanung** mit Zeitstrahl-Ansicht
- ⏰ **Automatische Erinnerungen** für Kündigungsfristen
- 👥 **Freigaben-System** für Familienmitglieder
- 🚨 **Notfall-Modus** für Notfallkontakte

### Sicherheitsfeatures
- 🔒 **Rollenbasierte Zugriffskontrolle (RBAC)**
- 🛡️ **CSRF-Schutz**
- 🔑 **Argon2id Passwort-Hashing**
- 🔐 **XChaCha20-Poly1305 Verschlüsselung** für sensitive Daten
- 📱 **Zwei-Faktor-Authentifizierung (2FA)** mit TOTP
- 📊 **Audit-Logging**
- 🚦 **Rate Limiting**
- 🛡️ **Content Security Policy (CSP)**

## 🔧 Installation

### Voraussetzungen

- **PHP 8.3+** mit Extensions:
  - `pdo_mysql`
  - `sodium`
  - `intl`
  - `mbstring`
  - `fileinfo`
  - `json`
- **MariaDB 10.5+** oder **MySQL 8.0+**
- **Node.js 18+** mit npm
- **Composer 2.0+**

### Setup-Schritte

1. **Repository klonen**
   ```bash
   git clone https://github.com/fksnet/vertragsverwaltung.git
   cd vertragsverwaltung
   ```

2. **Dependencies installieren**
   ```bash
   composer install
   npm install
   ```

3. **Assets bauen**
   ```bash
   npm run build:prod
   ```

4. **Datenbank erstellen**
   ```sql
   CREATE DATABASE vertragsverwaltung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

5. **Web-Installer ausführen**
   - Navigiere zu `http://your-domain.com/installer/`
   - Folge dem 6-stufigen Setup-Wizard
   - Konfiguriere Datenbank, App-Einstellungen und Admin-Account

### Alternative: Manuelle Konfiguration

1. **Umgebungsdatei kopieren**
   ```bash
   cp .env.example .env
   ```

2. **Konfiguration anpassen**
   ```bash
   # App-Keys generieren
   php -r "echo 'APP_KEY=' . bin2hex(random_bytes(32)) . PHP_EOL;"
   php -r "echo 'ENCRYPTION_KEY=' . base64_encode(random_bytes(32)) . PHP_EOL;"
   ```

3. **Datenbank-Migrationen ausführen**
   ```bash
   php scripts/tools/migrate.php
   ```

4. **Demo-Daten laden (optional)**
   ```bash
   php scripts/tools/seed.php
   ```

## 🔄 Entwicklung

### Development Server starten
```bash
# PHP Built-in Server
php -S localhost:8000 -t public/

# Assets im Watch-Modus bauen
npm run watch
```

### Code Quality
```bash
# Tests ausführen
composer test

# Static Analysis
composer analyze

# Code Style Check
composer cs-check

# Code Style Fix
composer cs-fix
```

### Frontend-Entwicklung
```bash
# Development Build (mit Watch)
npm run dev

# Production Build
npm run build:prod
```

## 📁 Projektstruktur

```
/app/
  /Core/          # Framework-Kern (Router, DB, Auth, etc.)
  /Controllers/   # HTTP Controllers
  /Models/        # Datenmodelle
  /Views/         # PHP-Templates
    /layouts/     # Layout-Templates
    /pages/       # Seiten-Templates
    /partials/    # Wiederverwendbare Komponenten
/config/          # Konfigurationsdateien
/database/
  /migrations/    # Datenbank-Migrationen
  /seeds/         # Test- und Demo-Daten
/public/          # Öffentlich zugängliche Dateien
  /assets/        # CSS/JS-Assets (generiert)
  /uploads/       # Upload-Verzeichnis
  /installer/     # Web-Installer
/scripts/
  /cron/          # Cron-Jobs
  /tools/         # CLI-Tools
/tests/           # PHPUnit Tests
/build/           # Build-Konfiguration
```

## 📱 Benutzerrollen

- **Administrator:** Vollzugriff auf alle Funktionen und Benutzerverwaltung
- **Benutzer:** Vollzugriff auf Verträge, keine Benutzerverwaltung
- **Freund:** Nur Lesezugriff auf freigegebene Verträge

## 🕒 Cron-Jobs einrichten

Für automatische Erinnerungen folgende Cron-Jobs einrichten:

```crontab
# Tägliche Überprüfung der Kündigungsfristen (9:00 Uhr)
0 9 * * * /usr/bin/php /path/to/vertragsverwaltung/scripts/cron/notify.php

# Monatliche Liquiditätsberechnung (1. des Monats, 6:00 Uhr)
0 6 1 * * /usr/bin/php /path/to/vertragsverwaltung/scripts/cron/liquidity.php
```

## 🛡️ Sicherheitshinweise

1. **Umgebungsvariablen schützen:** `.env` Datei niemals in Version Control einchecken
2. **Regelmäßige Updates:** Dependencies regelmäßig aktualisieren
3. **Backup-Strategie:** Regelmäßige Datenbank- und Datei-Backups
4. **HTTPS verwenden:** SSL/TLS-Verschlüsselung in Production
5. **Upload-Verzeichnis sichern:** `public/uploads/` vor direktem Zugriff schützen

## 📈 Performance-Empfehlungen

- **PHP OPcache aktivieren**
- **Gzip-Kompression einschalten**
- **Static Assets cachen**
- **CDN für Assets verwenden**
- **Datenbank-Indizes optimieren**

## 🤝 Contributing

1. Fork das Repository
2. Erstelle einen Feature-Branch (`git checkout -b feature/amazing-feature`)
3. Committe deine Änderungen (`git commit -m 'Add amazing feature'`)
4. Pushe den Branch (`git push origin feature/amazing-feature`)
5. Öffne einen Pull Request

## 📄 Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert. Siehe [LICENSE](LICENSE) für Details.

## 🏆 Acknowledgments

- **TailwindCSS** für das hervorragende CSS-Framework
- **daisyUI** für die wunderschönen UI-Komponenten
- **htmx** für die moderne HTML-basierte Interaktivität
- **Alpine.js** für leichtgewichtige JavaScript-Funktionalität