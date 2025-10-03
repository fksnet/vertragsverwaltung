<?php

/**
 * Migration: Audit-Log-Tabelle erstellen
 * 
 * @package Database\Migrations
 * @author GenSpark AI Developer
 */

return [
    'up' => "
        CREATE TABLE audit_log (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            
            -- Event-Details
            user_id INT NULL,
            event VARCHAR(100) NOT NULL,
            resource_type VARCHAR(50),
            resource_id INT NULL,
            
            -- Event-Daten
            details JSON NULL,
            
            -- Request-Informationen
            ip_address VARCHAR(45),
            user_agent TEXT,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (user_id) REFERENCES benutzer(id) ON DELETE SET NULL,
            
            INDEX idx_audit_log_user (user_id),
            INDEX idx_audit_log_event (event),
            INDEX idx_audit_log_resource (resource_type, resource_id),
            INDEX idx_audit_log_created_at (created_at),
            INDEX idx_audit_log_ip (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",
    
    'down' => "
        DROP TABLE IF EXISTS audit_log;
    "
];