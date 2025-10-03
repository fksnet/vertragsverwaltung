<?php

/**
 * Demo-Verträge Seeder
 * 
 * @package Database\Seeds
 * @author GenSpark AI Developer
 */

use App\Core\Database;

return function() {
    echo "📋 Erstelle Demo-Verträge...\n";
    
    $vertraege = [
        [
            'korrespondent_id' => 1, // Stadtwerke München
            'beginn' => '2024-01-01',
            'ende' => '2024-12-31',
            'status' => 'laufend',
            'laufzeit_monate' => 12,
            'kosten_cent' => 8500, // 85.00 €
            'kosten_zeitraum' => 'monat',
            'zahlungszyklus' => 'monat',
            'zahlungsweise' => 'sepa',
            'verlängerungsmodus' => 'automatisch',
            'kuendigungsfrist_tage' => 30,
            'notizen' => 'Strom- und Gasversorgung für die Wohnung',
            'kuendigungsablauf' => 'Kündigung schriftlich per E-Mail oder Brief mit 30 Tagen Vorlauf'
        ],
        [
            'korrespondent_id' => 2, // Telekom
            'beginn' => '2023-06-15',
            'ende' => '2025-06-14',
            'status' => 'laufend',
            'laufzeit_monate' => 24,
            'kosten_cent' => 4999, // 49.99 €
            'kosten_zeitraum' => 'monat',
            'zahlungszyklus' => 'monat',
            'zahlungsweise' => 'sepa',
            'verlängerungsmodus' => 'automatisch',
            'kuendigungsfrist_tage' => 90,
            'notizen' => 'DSL 100 MBit/s mit Festnetz-Flatrate',
            'kuendigungsablauf' => 'Kündigung nur schriftlich per Brief, 3 Monate vor Vertragsende'
        ],
        [
            'korrespondent_id' => 3, // Netflix
            'beginn' => '2024-03-01',
            'ende' => null,
            'status' => 'laufend',
            'laufzeit_monate' => null,
            'kosten_cent' => 1299, // 12.99 €
            'kosten_zeitraum' => 'monat',
            'zahlungszyklus' => 'monat',
            'zahlungsweise' => 'kreditkarte',
            'verlängerungsmodus' => 'automatisch',
            'kuendigungsfrist_tage' => 0,
            'notizen' => 'Standard-Abo, jederzeit kündbar',
            'kuendigungsablauf' => 'Kündigung jederzeit online im Benutzerkonto möglich'
        ],
        [
            'korrespondent_id' => 4, // ADAC
            'beginn' => '2023-01-01',
            'ende' => '2023-12-31',
            'status' => 'beendet',
            'laufzeit_monate' => 12,
            'kosten_cent' => 8400, // 84.00 €
            'kosten_zeitraum' => 'jahr',
            'zahlungszyklus' => 'jahr',
            'zahlungsweise' => 'sepa',
            'verlängerungsmodus' => 'automatisch',
            'kuendigungsfrist_tage' => 60,
            'notizen' => 'ADAC Plus Mitgliedschaft - nicht verlängert',
            'kuendigungsablauf' => 'Kündigung schriftlich, 60 Tage vor Ablauf'
        ],
        [
            'korrespondent_id' => 5, // Allianz
            'beginn' => '2022-04-01',
            'ende' => null,
            'status' => 'laufend',
            'laufzeit_monate' => null,
            'kosten_cent' => 12800, // 128.00 €
            'kosten_zeitraum' => 'monat',
            'zahlungszyklus' => 'monat',
            'zahlungsweise' => 'sepa',
            'verlängerungsmodus' => 'manuell',
            'kuendigungsfrist_tage' => 90,
            'notizen' => 'Haftpflichtversicherung für Familie',
            'kuendigungsablauf' => 'Kündigung zum Jahresende mit 3 Monaten Vorlauf'
        ],
        [
            'korrespondent_id' => 6, // Spotify
            'beginn' => '2024-01-15',
            'ende' => null,
            'status' => 'laufend',
            'laufzeit_monate' => null,
            'kosten_cent' => 999, // 9.99 €
            'kosten_zeitraum' => 'monat',
            'zahlungszyklus' => 'monat',
            'zahlungsweise' => 'paypal',
            'verlängerungsmodus' => 'automatisch',
            'kuendigungsfrist_tage' => 0,
            'notizen' => 'Premium-Abo für Musik-Streaming',
            'kuendigungsablauf' => 'Kündigung jederzeit online möglich'
        ],
        [
            'korrespondent_id' => 2, // Telekom (zweiter Vertrag)
            'beginn' => '2021-01-01',
            'ende' => '2023-01-01',
            'status' => 'gekündigt',
            'laufzeit_monate' => 24,
            'kosten_cent' => 2999, // 29.99 €
            'kosten_zeitraum' => 'monat',
            'zahlungszyklus' => 'monat',
            'zahlungsweise' => 'sepa',
            'verlängerungsmodus' => 'automatisch',
            'kuendigungsfrist_tage' => 90,
            'notizen' => 'Alter Handy-Vertrag, durch besseren ersetzt',
            'kuendigungsablauf' => 'Gekündigt zum Vertragsende'
        ]
    ];
    
    foreach ($vertraege as $vertrag) {
        Database::table('vertraege')->insert(array_merge($vertrag, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }
    
    echo "  ✅ " . count($vertraege) . " Demo-Verträge erstellt\n";
};