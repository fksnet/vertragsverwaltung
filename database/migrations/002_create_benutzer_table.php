<?php

/**
 * Migration: Benutzer-Tabelle erstellen
 * 
 * @package Database\Migrations
 * @author GenSpark AI Developer
 */

return [
    'up' => "
        CREATE TABLE benutzer (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            passwort_hash VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            rolle_id INT NOT NULL,
            aktiv BOOLEAN DEFAULT TRUE,
            
            -- 2FA Fields
            2fa_secret_enc TEXT NULL,
            2fa_enabled BOOLEAN DEFAULT FALSE,
            recovery_codes_enc TEXT NULL,
            
            -- Session/Security Fields
            remember_token VARCHAR(100) NULL,
            remember_token_expires TIMESTAMP NULL,
            reset_token VARCHAR(100) NULL,
            reset_token_expires TIMESTAMP NULL,
            last_activity TIMESTAMP NULL,
            
            -- Timestamps
            angelegt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (rolle_id) REFERENCES rollen(id) ON DELETE RESTRICT,
            
            INDEX idx_benutzer_email (email),
            INDEX idx_benutzer_rolle (rolle_id),
            INDEX idx_benutzer_aktiv (aktiv),
            INDEX idx_benutzer_remember_token (remember_token),
            INDEX idx_benutzer_reset_token (reset_token),
            INDEX idx_benutzer_last_activity (last_activity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS benutzer;
    "
];