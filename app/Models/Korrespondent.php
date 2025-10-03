<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\RBAC;

/**
 * Korrespondent-Model für Vertragspartner und Unternehmen
 * 
 * @package App\Models
 * @author GenSpark AI Developer
 */
class Korrespondent
{
    public int $id;
    public string $name;
    public string $adresse;
    public ?string $webseite;
    public ?string $hotline;
    public array $kontaktdaten;
    public \DateTime $created_at;
    public \DateTime $updated_at;
    
    private array $originalData;
    
    public function __construct(array $data)
    {
        $this->originalData = $data;
        
        $this->id = (int) $data['id'];
        $this->name = $data['name'];
        $this->adresse = $data['adresse'];
        $this->webseite = $data['webseite'] ?? null;
        $this->hotline = $data['hotline'] ?? null;
        
        // JSON-Kontaktdaten dekodieren
        $this->kontaktdaten = !empty($data['kontaktdaten_json']) 
            ? json_decode($data['kontaktdaten_json'], true) ?? []
            : [];
            
        $this->created_at = new \DateTime($data['created_at'] ?? 'now');
        $this->updated_at = new \DateTime($data['updated_at'] ?? 'now');
    }
    
    /**
     * Korrespondent aus Datenbank laden
     */
    public static function find(int $id): ?self
    {
        $data = db()->query(
            "SELECT * FROM korrespondenten WHERE id = ? LIMIT 1",
            [$id]
        )->fetch();
        
        if (!$data) {
            return null;
        }
        
        // RBAC-Check
        if (!rbac_check('read', 'korrespondent', ['id' => $id])) {
            return null;
        }
        
        return new self($data);
    }
    
    /**
     * Alle Korrespondenten abrufen (mit RBAC-Filterung)
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];
        
        // Such-Filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(name LIKE ? OR adresse LIKE ? OR webseite LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        // Status-Filter (aktive Verträge)
        if (isset($filters['has_active_contracts'])) {
            if ($filters['has_active_contracts']) {
                $where[] = "EXISTS (SELECT 1 FROM vertraege v WHERE v.korrespondent_id = korrespondenten.id AND v.status = 'laufend')";
            } else {
                $where[] = "NOT EXISTS (SELECT 1 FROM vertraege v WHERE v.korrespondent_id = korrespondenten.id AND v.status = 'laufend')";
            }
        }
        
        $sql = "SELECT * FROM korrespondenten";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY name ASC";
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = ($filters['page'] ?? 1 - 1) * $filters['limit'];
            $sql .= " LIMIT {$filters['limit']} OFFSET $offset";
        }
        
        $results = db()->query($sql, $params)->fetchAll();
        
        // RBAC-Filterung auf Ergebnis anwenden
        $filtered = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'korrespondent', ['id' => $data['id']])) {
                $filtered[] = new self($data);
            }
        }
        
        return $filtered;
    }
    
    /**
     * Korrespondent für Autocomplete/Search
     */
    public static function search(string $query, int $limit = 10): array
    {
        $searchTerm = '%' . $query . '%';
        
        $results = db()->query(
            "SELECT id, name, adresse FROM korrespondenten 
             WHERE name LIKE ? OR adresse LIKE ? 
             ORDER BY name ASC LIMIT ?",
            [$searchTerm, $searchTerm, $limit]
        )->fetchAll();
        
        // RBAC-Filterung
        $filtered = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'korrespondent', ['id' => $data['id']])) {
                $filtered[] = [
                    'id' => $data['id'],
                    'name' => $data['name'],
                    'adresse' => $data['adresse'],
                    'display_name' => $data['name'] . ' (' . $data['adresse'] . ')'
                ];
            }
        }
        
        return $filtered;
    }
    
    /**
     * Neuen Korrespondenten erstellen
     */
    public static function create(array $data): self
    {
        // RBAC-Check
        if (!rbac_check('create', 'korrespondent')) {
            throw new \Exception('Keine Berechtigung zum Erstellen von Korrespondenten');
        }
        
        // Validierung
        $errors = self::validate($data);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
        
        // Kontaktdaten als JSON kodieren
        $kontaktdatenJson = !empty($data['kontaktdaten']) 
            ? json_encode($data['kontaktdaten'], JSON_UNESCAPED_UNICODE)
            : null;
        
        $now = date('Y-m-d H:i:s');
        
        $id = db()->query(
            "INSERT INTO korrespondenten (name, adresse, webseite, hotline, kontaktdaten_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['adresse'],
                $data['webseite'] ?? null,
                $data['hotline'] ?? null,
                $kontaktdatenJson,
                $now,
                $now
            ]
        )->lastInsertId();
        
        return self::find($id);
    }
    
    /**
     * Korrespondent aktualisieren
     */
    public function update(array $data): bool
    {
        // RBAC-Check
        if (!rbac_check('update', 'korrespondent', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Bearbeiten dieses Korrespondenten');
        }
        
        // Validierung
        $errors = self::validate($data, $this->id);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
        
        $kontaktdatenJson = !empty($data['kontaktdaten']) 
            ? json_encode($data['kontaktdaten'], JSON_UNESCAPED_UNICODE)
            : null;
        
        $result = db()->query(
            "UPDATE korrespondenten 
             SET name = ?, adresse = ?, webseite = ?, hotline = ?, kontaktdaten_json = ?, updated_at = ?
             WHERE id = ?",
            [
                $data['name'],
                $data['adresse'],
                $data['webseite'] ?? null,
                $data['hotline'] ?? null,
                $kontaktdatenJson,
                date('Y-m-d H:i:s'),
                $this->id
            ]
        );
        
        return $result->rowCount() > 0;
    }
    
    /**
     * Korrespondent löschen (nur wenn keine aktiven Verträge)
     */
    public function delete(): bool
    {
        // RBAC-Check
        if (!rbac_check('delete', 'korrespondent', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Löschen dieses Korrespondenten');
        }
        
        // Prüfen ob noch Verträge existieren
        $contractCount = db()->query(
            "SELECT COUNT(*) FROM vertraege WHERE korrespondent_id = ?",
            [$this->id]
        )->fetchColumn();
        
        if ($contractCount > 0) {
            throw new \Exception('Korrespondent kann nicht gelöscht werden - es existieren noch Verträge');
        }
        
        // Zugangsdaten löschen
        db()->query("DELETE FROM zugangsdaten WHERE korrespondent_id = ?", [$this->id]);
        
        // Dokumente löschen
        db()->query("DELETE FROM dokumente WHERE korrespondent_id = ?", [$this->id]);
        
        // Korrespondent löschen
        $result = db()->query("DELETE FROM korrespondenten WHERE id = ?", [$this->id]);
        
        return $result->rowCount() > 0;
    }
    
    /**
     * Verträge dieses Korrespondenten
     */
    public function getVertraege(): array
    {
        return Vertrag::findByKorrespondent($this->id);
    }
    
    /**
     * Aktive Verträge
     */
    public function getActiveVertraege(): array
    {
        $results = db()->query(
            "SELECT * FROM vertraege WHERE korrespondent_id = ? AND status = 'laufend' ORDER BY beginn DESC",
            [$this->id]
        )->fetchAll();
        
        $vertraege = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'vertrag', ['id' => $data['id']])) {
                $vertraege[] = new Vertrag($data);
            }
        }
        
        return $vertraege;
    }
    
    /**
     * Zugangsdaten für diesen Korrespondenten
     */
    public function getZugangsdaten(): array
    {
        $results = db()->query(
            "SELECT * FROM zugangsdaten WHERE korrespondent_id = ? ORDER BY webseite",
            [$this->id]
        )->fetchAll();
        
        $zugangsdaten = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'zugangsdaten', ['id' => $data['id']])) {
                $zugangsdaten[] = new Zugangsdaten($data);
            }
        }
        
        return $zugangsdaten;
    }
    
    /**
     * Dokumente für diesen Korrespondenten
     */
    public function getDokumente(): array
    {
        $results = db()->query(
            "SELECT * FROM dokumente WHERE korrespondent_id = ? ORDER BY hochgeladen_am DESC",
            [$this->id]
        )->fetchAll();
        
        $dokumente = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'dokument', ['id' => $data['id']])) {
                $dokumente[] = new Dokument($data);
            }
        }
        
        return $dokumente;
    }
    
    /**
     * Statistiken für diesen Korrespondenten
     */
    public function getStats(): array
    {
        return [
            'total_vertraege' => db()->query(
                "SELECT COUNT(*) FROM vertraege WHERE korrespondent_id = ?",
                [$this->id]
            )->fetchColumn(),
            
            'active_vertraege' => db()->query(
                "SELECT COUNT(*) FROM vertraege WHERE korrespondent_id = ? AND status = 'laufend'",
                [$this->id]
            )->fetchColumn(),
            
            'total_kosten_pro_monat' => db()->query(
                "SELECT COALESCE(SUM(
                    CASE 
                        WHEN kosten_zeitraum = 'monat' THEN kosten_cent
                        WHEN kosten_zeitraum = 'quartal' THEN kosten_cent / 3
                        WHEN kosten_zeitraum = 'jahr' THEN kosten_cent / 12
                        ELSE 0
                    END
                ), 0) FROM vertraege WHERE korrespondent_id = ? AND status = 'laufend'",
                [$this->id]
            )->fetchColumn(),
            
            'dokumente_count' => db()->query(
                "SELECT COUNT(*) FROM dokumente WHERE korrespondent_id = ?",
                [$this->id]
            )->fetchColumn(),
            
            'zugangsdaten_count' => db()->query(
                "SELECT COUNT(*) FROM zugangsdaten WHERE korrespondent_id = ?",
                [$this->id]
            )->fetchColumn()
        ];
    }
    
    /**
     * Hauptkontaktperson ermitteln
     */
    public function getMainContact(): ?array
    {
        if (empty($this->kontaktdaten)) {
            return null;
        }
        
        // Erste Kontaktperson oder die mit Rolle "Hauptkontakt"
        foreach ($this->kontaktdaten as $kontakt) {
            if (($kontakt['rolle'] ?? '') === 'Hauptkontakt') {
                return $kontakt;
            }
        }
        
        return $this->kontaktdaten[0] ?? null;
    }
    
    /**
     * Validierung für Korrespondent-Daten
     */
    public static function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        
        // Name erforderlich
        if (empty($data['name'])) {
            $errors[] = 'Name ist erforderlich';
        } else {
            // Name-Eindeutigkeit prüfen
            $query = "SELECT id FROM korrespondenten WHERE name = ?";
            $params = [$data['name']];
            
            if ($excludeId) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            if (db()->query($query, $params)->fetch()) {
                $errors[] = 'Ein Korrespondent mit diesem Namen existiert bereits';
            }
        }
        
        // Adresse erforderlich
        if (empty($data['adresse'])) {
            $errors[] = 'Adresse ist erforderlich';
        }
        
        // Webseite URL-Format prüfen
        if (!empty($data['webseite']) && !filter_var($data['webseite'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Webseite muss eine gültige URL sein';
        }
        
        // Kontaktdaten validieren
        if (!empty($data['kontaktdaten'])) {
            if (!is_array($data['kontaktdaten'])) {
                $errors[] = 'Kontaktdaten müssen als Array übergeben werden';
            } else {
                foreach ($data['kontaktdaten'] as $index => $kontakt) {
                    if (empty($kontakt['name'])) {
                        $errors[] = "Name für Kontaktperson #" . ($index + 1) . " ist erforderlich";
                    }
                    
                    if (!empty($kontakt['email']) && !filter_var($kontakt['email'], FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Ungültige E-Mail für Kontaktperson #" . ($index + 1);
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Array-Darstellung für JSON-Response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'adresse' => $this->adresse,
            'webseite' => $this->webseite,
            'hotline' => $this->hotline,
            'kontaktdaten' => $this->kontaktdaten,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'main_contact' => $this->getMainContact()
        ];
    }
}