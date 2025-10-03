<?php

/**
 * Migration: Zugangsdaten-Tabelle erstellen
 * 
 * @package Database\Migrations
 * @author GenSpark AI Developer
 */

return [
    'up' => "
        CREATE TABLE zugangsdaten (
            id INT AUTO_INCREMENT PRIMARY KEY,
            
            -- Zuordnung (optional)
            korrespondent_id INT NULL,
            vertrag_id INT NULL,
            
            -- Zugangsdaten
            webseite VARCHAR(255),
            benutzername VARCHAR(255),
            passwort_enc TEXT,
            
            -- 2FA-Daten (verschlüsselt)
            totp_qr_uri_enc TEXT NULL,
            recovery_codes_enc TEXT NULL,
            
            -- Zusätzliche Informationen
            scriptsnippet_enc TEXT NULL,
            notizen TEXT,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (korrespondent_id) REFERENCES korrespondenten(id) ON DELETE CASCADE,
            FOREIGN KEY (vertrag_id) REFERENCES vertraege(id) ON DELETE CASCADE,
            
            INDEX idx_zugangsdaten_korrespondent (korrespondent_id),
            INDEX idx_zugangsdaten_vertrag (vertrag_id),
            INDEX idx_zugangsdaten_webseite (webseite)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS zugangsdaten;
    "
];