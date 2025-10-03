<?php

/**
 * Migration: Dokumente-Tabelle erstellen
 * 
 * @package Database\Migrations
 * @author GenSpark AI Developer
 */

return [
    'up' => "
        CREATE TABLE dokumente (
            id INT AUTO_INCREMENT PRIMARY KEY,
            
            -- Zuordnung (optional)
            korrespondent_id INT NULL,
            vertrag_id INT NULL,
            
            -- Datei-Informationen
            dateiname_original VARCHAR(255) NOT NULL,
            pfad VARCHAR(500) NOT NULL,
            mime VARCHAR(100) NOT NULL,
            groesse INT NOT NULL DEFAULT 0,
            
            -- Upload-Informationen
            hochgeladen_von INT NULL,
            hochgeladen_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (korrespondent_id) REFERENCES korrespondenten(id) ON DELETE CASCADE,
            FOREIGN KEY (vertrag_id) REFERENCES vertraege(id) ON DELETE CASCADE,
            FOREIGN KEY (hochgeladen_von) REFERENCES benutzer(id) ON DELETE SET NULL,
            
            INDEX idx_dokumente_korrespondent (korrespondent_id),
            INDEX idx_dokumente_vertrag (vertrag_id),
            INDEX idx_dokumente_hochgeladen_von (hochgeladen_von),
            INDEX idx_dokumente_hochgeladen_am (hochgeladen_am),
            INDEX idx_dokumente_mime (mime),
            FULLTEXT idx_dokumente_fulltext (dateiname_original)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS dokumente;
    "
];