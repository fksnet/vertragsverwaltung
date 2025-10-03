<?php

/**
 * Migration: Verträge-Tabelle erstellen
 * 
 * @package Database\Migrations
 * @author GenSpark AI Developer
 */

return [
    'up' => "
        CREATE TABLE vertraege (
            id INT AUTO_INCREMENT PRIMARY KEY,
            korrespondent_id INT NOT NULL,
            
            -- Vertragszeitraum
            beginn DATE NOT NULL,
            ende DATE,
            status ENUM('laufend', 'gekündigt', 'beendet', 'storniert') NOT NULL DEFAULT 'laufend',
            laufzeit_monate INT,
            
            -- Kosten
            kosten_cent INT NOT NULL DEFAULT 0,
            kosten_zeitraum ENUM('monat', 'quartal', 'jahr') NOT NULL DEFAULT 'monat',
            zahlungszyklus ENUM('monat', 'quartal', 'jahr') NOT NULL DEFAULT 'monat',
            zahlungsweise ENUM('sepa', 'kreditkarte', 'ueberweisung', 'paypal', 'bar', 'sonstiges') NOT NULL DEFAULT 'sepa',
            
            -- Verlängerung & Kündigung
            verlängerungsmodus ENUM('automatisch', 'manuell', 'kuendigungsfrist') NOT NULL DEFAULT 'automatisch',
            kuendigungsfrist_tage INT DEFAULT 30,
            
            -- Zusätzliche Informationen
            notizen TEXT,
            kuendigungsablauf TEXT,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (korrespondent_id) REFERENCES korrespondenten(id) ON DELETE RESTRICT,
            
            INDEX idx_vertraege_korrespondent (korrespondent_id),
            INDEX idx_vertraege_status (status),
            INDEX idx_vertraege_beginn (beginn),
            INDEX idx_vertraege_ende (ende),
            INDEX idx_vertraege_zahlungszyklus (zahlungszyklus),
            INDEX idx_vertraege_verlängerungsmodus (verlängerungsmodus),
            INDEX idx_vertraege_kuendigungsfrist (kuendigungsfrist_tage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS vertraege;
    "
];