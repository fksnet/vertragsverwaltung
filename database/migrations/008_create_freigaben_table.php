<?php

/**
 * Migration: Freigaben-Tabelle erstellen
 * 
 * @package Database\Migrations
 * @author GenSpark AI Developer
 */

return [
    'up' => "
        CREATE TABLE freigaben (
            id INT AUTO_INCREMENT PRIMARY KEY,
            
            -- Freigabe-Details
            vertrag_id INT NOT NULL,
            freund_user_id INT NOT NULL,
            
            -- Berechtigungen
            include_docs BOOLEAN DEFAULT FALSE,
            include_credentials BOOLEAN DEFAULT FALSE,
            
            -- Metadata
            erstellt_von INT NOT NULL,
            erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (vertrag_id) REFERENCES vertraege(id) ON DELETE CASCADE,
            FOREIGN KEY (freund_user_id) REFERENCES benutzer(id) ON DELETE CASCADE,
            FOREIGN KEY (erstellt_von) REFERENCES benutzer(id) ON DELETE CASCADE,
            
            UNIQUE KEY unique_vertrag_freund (vertrag_id, freund_user_id),
            
            INDEX idx_freigaben_vertrag (vertrag_id),
            INDEX idx_freigaben_freund (freund_user_id),
            INDEX idx_freigaben_erstellt_von (erstellt_von),
            INDEX idx_freigaben_erstellt_am (erstellt_am)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS freigaben;
    "
];