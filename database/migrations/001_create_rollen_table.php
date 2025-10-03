<?php

/**
 * Migration: Rollen-Tabelle erstellen
 * 
 * @package Database\Migrations
 * @author GenSpark AI Developer
 */

return [
    'up' => "
        CREATE TABLE rollen (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            beschreibung TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_rollen_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- Standard-Rollen einfügen
        INSERT INTO rollen (name, beschreibung) VALUES
        ('administrator', 'Vollzugriff auf alle Funktionen und Benutzerverwaltung'),
        ('benutzer', 'Vollzugriff auf Verträge, keine Benutzerverwaltung'),
        ('freund', 'Nur Lesezugriff auf freigegebene Verträge');
    ",
    
    'down' => "
        DROP TABLE IF EXISTS rollen;
    "
];