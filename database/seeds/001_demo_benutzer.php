<?php

/**
 * Demo-Benutzer Seeder
 * 
 * @package Database\Seeds
 * @author GenSpark AI Developer
 */

use App\Core\Database;
use App\Core\Crypto;

return function() {
    echo "👤 Erstelle Demo-Benutzer...\n";
    
    // Admin-Benutzer
    Database::table('benutzer')->insert([
        'email' => 'admin@vertragsverwaltung.local',
        'passwort_hash' => Crypto::hashPassword('admin123'),
        'name' => 'Administrator',
        'rolle_id' => 1, // Administrator
        'aktiv' => true,
        'angelegt_am' => date('Y-m-d H:i:s'),
    ]);
    
    // Standard-Benutzer
    Database::table('benutzer')->insert([
        'email' => 'benutzer@vertragsverwaltung.local',
        'passwort_hash' => Crypto::hashPassword('benutzer123'),
        'name' => 'Max Mustermann',
        'rolle_id' => 2, // Benutzer
        'aktiv' => true,
        'angelegt_am' => date('Y-m-d H:i:s'),
    ]);
    
    // Freund-Benutzer
    Database::table('benutzer')->insert([
        'email' => 'freund@vertragsverwaltung.local',
        'passwort_hash' => Crypto::hashPassword('freund123'),
        'name' => 'Anna Beispiel',
        'rolle_id' => 3, // Freund
        'aktiv' => true,
        'angelegt_am' => date('Y-m-d H:i:s'),
    ]);
    
    echo "  ✅ 3 Demo-Benutzer erstellt\n";
};