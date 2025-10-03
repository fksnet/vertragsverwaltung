<?php

/**
 * Demo-Korrespondenten Seeder
 * 
 * @package Database\Seeds
 * @author GenSpark AI Developer
 */

use App\Core\Database;

return function() {
    echo "🏢 Erstelle Demo-Korrespondenten...\n";
    
    $korrespondenten = [
        [
            'name' => 'Stadtwerke München',
            'adresse' => 'Emmy-Noether-Straße 2, 80287 München',
            'webseite' => 'https://www.swm.de',
            'hotline' => '0800 796 796 0',
            'kontaktdaten_json' => json_encode([
                [
                    'name' => 'Kundenservice',
                    'rolle' => 'Support',
                    'email' => 'service@swm.de',
                    'telefon' => '089 2361-0'
                ]
            ])
        ],
        [
            'name' => 'Telekom Deutschland GmbH',
            'adresse' => 'Landgrabenweg 151, 53227 Bonn',
            'webseite' => 'https://www.telekom.de',
            'hotline' => '0800 33 01000',
            'kontaktdaten_json' => json_encode([
                [
                    'name' => 'Kundenbetreuung Privatkunden',
                    'rolle' => 'Support',
                    'email' => 'info@telekom.de',
                    'telefon' => '0800 33 01000'
                ]
            ])
        ],
        [
            'name' => 'Netflix International B.V.',
            'adresse' => 'Westertoren 2E, 1017 ZN Amsterdam, Niederlande',
            'webseite' => 'https://www.netflix.com',
            'hotline' => '+49 30 30807070',
            'kontaktdaten_json' => json_encode([
                [
                    'name' => 'Kundenservice',
                    'rolle' => 'Support',
                    'email' => 'help@netflix.com',
                    'telefon' => '+49 30 30807070'
                ]
            ])
        ],
        [
            'name' => 'ADAC e.V.',
            'adresse' => 'Am Westpark 8, 81373 München',
            'webseite' => 'https://www.adac.de',
            'hotline' => '089 7676 0',
            'kontaktdaten_json' => json_encode([
                [
                    'name' => 'Mitgliederservice',
                    'rolle' => 'Support',
                    'email' => 'kontakt@adac.de',
                    'telefon' => '089 7676 2610'
                ]
            ])
        ],
        [
            'name' => 'Allianz Versicherungs-AG',
            'adresse' => 'Königinstraße 28, 80802 München',
            'webseite' => 'https://www.allianz.de',
            'hotline' => '0800 4 124 124',
            'kontaktdaten_json' => json_encode([
                [
                    'name' => 'Kundenservice',
                    'rolle' => 'Support',
                    'email' => 'service@allianz.de',
                    'telefon' => '0800 4 124 124'
                ]
            ])
        ],
        [
            'name' => 'Spotify AB',
            'adresse' => 'Regeringsgatan 19, 111 53 Stockholm, Schweden',
            'webseite' => 'https://www.spotify.com',
            'hotline' => null,
            'kontaktdaten_json' => json_encode([
                [
                    'name' => 'Support-Team',
                    'rolle' => 'Online-Support',
                    'email' => 'support@spotify.com',
                    'telefon' => null
                ]
            ])
        ]
    ];
    
    foreach ($korrespondenten as $korrespondent) {
        Database::table('korrespondenten')->insert(array_merge($korrespondent, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }
    
    echo "  ✅ " . count($korrespondenten) . " Demo-Korrespondenten erstellt\n";
};