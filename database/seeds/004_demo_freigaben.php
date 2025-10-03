<?php

/**
 * Demo-Freigaben Seeder
 * 
 * @package Database\Seeds
 * @author GenSpark AI Developer
 */

use App\Core\Database;

return function() {
    echo "🤝 Erstelle Demo-Freigaben...\n";
    
    // Freigaben für Freund-Benutzer (ID 3)
    $freigaben = [
        [
            'vertrag_id' => 1, // Stadtwerke
            'freund_user_id' => 3, // Anna Beispiel
            'include_docs' => true,
            'include_credentials' => false,
            'erstellt_von' => 2, // Max Mustermann
            'erstellt_am' => date('Y-m-d H:i:s'),
        ],
        [
            'vertrag_id' => 3, // Netflix
            'freund_user_id' => 3, // Anna Beispiel
            'include_docs' => true,
            'include_credentials' => true, // Netflix-Zugangsdaten teilen
            'erstellt_von' => 2, // Max Mustermann
            'erstellt_am' => date('Y-m-d H:i:s'),
        ],
        [
            'vertrag_id' => 6, // Spotify
            'freund_user_id' => 3, // Anna Beispiel
            'include_docs' => false,
            'include_credentials' => true, // Family-Account
            'erstellt_von' => 2, // Max Mustermann
            'erstellt_am' => date('Y-m-d H:i:s'),
        ]
    ];
    
    foreach ($freigaben as $freigabe) {
        Database::table('freigaben')->insert(array_merge($freigabe, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }
    
    echo "  ✅ " . count($freigaben) . " Demo-Freigaben erstellt\n";
};