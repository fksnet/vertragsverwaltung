# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

## [1.0.1] - 2024-10-04

### 🐛 Bugfixes
- **KRITISCH:** Behoben 500 Internal Server Error nach Installation
- Repariert `routes/web.php` - entfernte inkompatible Router-Klassen-Aufrufe
- Konvertiert Route-Definitionen zu einfachem Array-Format
- Verbesserte Webhosting-Kompatibilität durch Entfernung von `strict_types=1`
- Robuste Fehlerbehandlung mit `@` Operator für Dateioperationen
- Erweiterte Debug-Funktionalität in `index.php`

### ✨ Verbesserungen
- Detaillierte Fehlerprotokollierung in `/storage/logs/fatal_error.log`
- Step-by-Step Debug-Modus (`?step_debug=1`) hinzugefügt
- Verbesserte Debug-URLs: `?debug=1` und `?route=test`
- Bootstrap-Kompatibilität für Standard-Webhosting optimiert

### 🔧 Technische Änderungen
- Application.php: Entfernt `declare(strict_types=1)` für PHP-Kompatibilität
- routes/web.php: Vollständig überarbeitet für Application-Klassen-Kompatibilität
- Erweiterte Exception- und Error-Behandlung implementiert

## [1.0.0] - 2024-10-03

### 🎉 Erstes Release
- Vollständige Vertragsverwaltung-Anwendung
- WordPress-style Installation-Wizard
- PHP 8.1+ und MySQL/MariaDB Unterstützung
- Moderne Web-Technologien (Tailwind CSS, Alpine.js, HTMX)
- Umfassende Sicherheitsfeatures
- Benutzer- und Rollenverwaltung
- Dokumentenmanagement
- Liquiditätsplanung