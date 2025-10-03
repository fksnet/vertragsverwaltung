<?php

/**
 * Migration: RBAC-Policies-Tabelle erstellen
 * 
 * @package Database\Migrations
 * @author GenSpark AI Developer
 */

return [
    'up' => "
        CREATE TABLE rollen_objekt_policies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rolle_id INT NOT NULL,
            objekt ENUM('korrespondent', 'vertrag', 'zugangsdaten', 'dokument', 'benutzer', 'rolle') NOT NULL,
            can_read BOOLEAN DEFAULT FALSE,
            can_create BOOLEAN DEFAULT FALSE,
            can_update BOOLEAN DEFAULT FALSE,
            can_delete BOOLEAN DEFAULT FALSE,
            readonly BOOLEAN DEFAULT FALSE,
            field_value_regex_json JSON NULL,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            FOREIGN KEY (rolle_id) REFERENCES rollen(id) ON DELETE CASCADE,
            UNIQUE KEY unique_rolle_objekt (rolle_id, objekt),
            
            INDEX idx_policies_rolle (rolle_id),
            INDEX idx_policies_objekt (objekt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        -- Standard-Policies für Administrator
        INSERT INTO rollen_objekt_policies (rolle_id, objekt, can_read, can_create, can_update, can_delete, readonly) VALUES
        (1, 'korrespondent', 1, 1, 1, 1, 0),
        (1, 'vertrag', 1, 1, 1, 1, 0),
        (1, 'zugangsdaten', 1, 1, 1, 1, 0),
        (1, 'dokument', 1, 1, 1, 1, 0),
        (1, 'benutzer', 1, 1, 1, 1, 0),
        (1, 'rolle', 1, 1, 1, 1, 0);
        
        -- Standard-Policies für Benutzer
        INSERT INTO rollen_objekt_policies (rolle_id, objekt, can_read, can_create, can_update, can_delete, readonly) VALUES
        (2, 'korrespondent', 1, 1, 1, 1, 0),
        (2, 'vertrag', 1, 1, 1, 1, 0),
        (2, 'zugangsdaten', 1, 1, 1, 1, 0),
        (2, 'dokument', 1, 1, 1, 1, 0),
        (2, 'benutzer', 0, 0, 0, 0, 1),
        (2, 'rolle', 0, 0, 0, 0, 1);
        
        -- Standard-Policies für Freund
        INSERT INTO rollen_objekt_policies (rolle_id, objekt, can_read, can_create, can_update, can_delete, readonly) VALUES
        (3, 'korrespondent', 1, 0, 0, 0, 1),
        (3, 'vertrag', 1, 0, 0, 0, 1),
        (3, 'zugangsdaten', 0, 0, 0, 0, 1),
        (3, 'dokument', 1, 0, 0, 0, 1),
        (3, 'benutzer', 0, 0, 0, 0, 1),
        (3, 'rolle', 0, 0, 0, 0, 1);
    ",
    
    'down' => "
        DROP TABLE IF EXISTS rollen_objekt_policies;
    "
];