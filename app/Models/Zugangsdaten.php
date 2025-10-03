<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\RBAC;
use App\Core\Crypto;

/**
 * Zugangsdaten-Model für sichere Credential-Verwaltung
 * 
 * @package App\Models
 * @author GenSpark AI Developer
 */
class Zugangsdaten
{
    public int $id;
    public ?int $korrespondent_id;
    public ?int $vertrag_id;
    public string $webseite;
    public string $benutzername;
    public string $passwort_enc; // Verschlüsselt
    public ?string $totp_qr_uri_enc; // TOTP Secret verschlüsselt
    public ?string $recovery_codes_enc; // Recovery Codes verschlüsselt
    public ?string $scriptsnippet_enc; // Login-Scripts verschlüsselt
    public \DateTime $created_at;
    public \DateTime $updated_at;
    
    // Relations
    public ?Korrespondent $korrespondent = null;
    public ?Vertrag $vertrag = null;
    
    private array $originalData;
    
    public function __construct(array $data)
    {
        $this->originalData = $data;
        
        $this->id = (int) $data['id'];
        $this->korrespondent_id = $data['korrespondent_id'] ? (int) $data['korrespondent_id'] : null;
        $this->vertrag_id = $data['vertrag_id'] ? (int) $data['vertrag_id'] : null;
        $this->webseite = $data['webseite'];
        $this->benutzername = $data['benutzername'];
        $this->passwort_enc = $data['passwort_enc'];
        $this->totp_qr_uri_enc = $data['totp_qr_uri_enc'] ?? null;
        $this->recovery_codes_enc = $data['recovery_codes_enc'] ?? null;
        $this->scriptsnippet_enc = $data['scriptsnippet_enc'] ?? null;
        $this->created_at = new \DateTime($data['created_at'] ?? 'now');
        $this->updated_at = new \DateTime($data['updated_at'] ?? 'now');
    }
    
    /**
     * Zugangsdaten aus Datenbank laden
     */
    public static function find(int $id): ?self
    {
        $data = db()->query(
            "SELECT * FROM zugangsdaten WHERE id = ? LIMIT 1",
            [$id]
        )->fetch();
        
        if (!$data) {
            return null;
        }
        
        // RBAC-Check
        if (!rbac_check('read', 'zugangsdaten', ['id' => $id])) {
            return null;
        }
        
        return new self($data);
    }
    
    /**
     * Alle Zugangsdaten abrufen (mit RBAC-Filterung)
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];
        
        // Korrespondent-Filter
        if (!empty($filters['korrespondent_id'])) {
            $where[] = "z.korrespondent_id = ?";
            $params[] = $filters['korrespondent_id'];
        }
        
        // Vertrag-Filter
        if (!empty($filters['vertrag_id'])) {
            $where[] = "z.vertrag_id = ?";
            $params[] = $filters['vertrag_id'];
        }
        
        // Such-Filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(z.webseite LIKE ? OR z.benutzername LIKE ? OR k.name LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql = "SELECT z.*, k.name as korrespondent_name, v.status as vertrag_status
                FROM zugangsdaten z 
                LEFT JOIN korrespondenten k ON z.korrespondent_id = k.id
                LEFT JOIN vertraege v ON z.vertrag_id = v.id";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY z.webseite ASC";
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = ($filters['page'] ?? 1 - 1) * $filters['limit'];
            $sql .= " LIMIT {$filters['limit']} OFFSET $offset";
        }
        
        $results = db()->query($sql, $params)->fetchAll();
        
        // RBAC-Filterung
        $filtered = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'zugangsdaten', ['id' => $data['id']])) {
                $zugangsdaten = new self($data);
                $zugangsdaten->korrespondent_name = $data['korrespondent_name'];
                $zugangsdaten->vertrag_status = $data['vertrag_status'];
                $filtered[] = $zugangsdaten;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Zugangsdaten für Korrespondent
     */
    public static function findByKorrespondent(int $korrespondentId): array
    {
        $results = db()->query(
            "SELECT * FROM zugangsdaten WHERE korrespondent_id = ? ORDER BY webseite",
            [$korrespondentId]
        )->fetchAll();
        
        $zugangsdaten = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'zugangsdaten', ['id' => $data['id']])) {
                $zugangsdaten[] = new self($data);
            }
        }
        
        return $zugangsdaten;
    }
    
    /**
     * Zugangsdaten für Vertrag
     */
    public static function findByVertrag(int $vertragId): array
    {
        $results = db()->query(
            "SELECT * FROM zugangsdaten WHERE vertrag_id = ? ORDER BY webseite",
            [$vertragId]
        )->fetchAll();
        
        $zugangsdaten = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'zugangsdaten', ['id' => $data['id']])) {
                $zugangsdaten[] = new self($data);
            }
        }
        
        return $zugangsdaten;
    }
    
    /**
     * Neue Zugangsdaten erstellen
     */
    public static function create(array $data): self
    {
        // RBAC-Check
        if (!rbac_check('create', 'zugangsdaten')) {
            throw new \Exception('Keine Berechtigung zum Erstellen von Zugangsdaten');
        }
        
        // Validierung
        $errors = self::validate($data);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
        
        // Sensitive Daten verschlüsseln
        $passwortEnc = encrypt($data['passwort']);
        $totpQrUriEnc = !empty($data['totp_qr_uri']) ? encrypt($data['totp_qr_uri']) : null;
        $recoveryCodesEnc = !empty($data['recovery_codes']) ? encrypt(json_encode($data['recovery_codes'])) : null;
        $scriptsnippetEnc = !empty($data['scriptsnippet']) ? encrypt($data['scriptsnippet']) : null;
        
        $now = date('Y-m-d H:i:s');
        
        $id = db()->query(
            "INSERT INTO zugangsdaten (
                korrespondent_id, vertrag_id, webseite, benutzername, passwort_enc,
                totp_qr_uri_enc, recovery_codes_enc, scriptsnippet_enc, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['korrespondent_id'] ?? null,
                $data['vertrag_id'] ?? null,
                $data['webseite'],
                $data['benutzername'],
                $passwortEnc,
                $totpQrUriEnc,
                $recoveryCodesEnc,
                $scriptsnippetEnc,
                $now,
                $now
            ]
        )->lastInsertId();
        
        return self::find($id);
    }
    
    /**
     * Zugangsdaten aktualisieren
     */
    public function update(array $data): bool
    {
        // RBAC-Check
        if (!rbac_check('update', 'zugangsdaten', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Bearbeiten dieser Zugangsdaten');
        }
        
        // Validierung
        $errors = self::validate($data, $this->id);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
        
        // Sensitive Daten verschlüsseln (nur wenn geändert)
        $passwortEnc = isset($data['passwort']) ? encrypt($data['passwort']) : $this->passwort_enc;
        $totpQrUriEnc = isset($data['totp_qr_uri']) ? 
            (!empty($data['totp_qr_uri']) ? encrypt($data['totp_qr_uri']) : null) : 
            $this->totp_qr_uri_enc;
        $recoveryCodesEnc = isset($data['recovery_codes']) ? 
            (!empty($data['recovery_codes']) ? encrypt(json_encode($data['recovery_codes'])) : null) :
            $this->recovery_codes_enc;
        $scriptsnippetEnc = isset($data['scriptsnippet']) ? 
            (!empty($data['scriptsnippet']) ? encrypt($data['scriptsnippet']) : null) :
            $this->scriptsnippet_enc;
        
        $result = db()->query(
            "UPDATE zugangsdaten SET 
                korrespondent_id = ?, vertrag_id = ?, webseite = ?, benutzername = ?,
                passwort_enc = ?, totp_qr_uri_enc = ?, recovery_codes_enc = ?, 
                scriptsnippet_enc = ?, updated_at = ?
             WHERE id = ?",
            [
                $data['korrespondent_id'] ?? null,
                $data['vertrag_id'] ?? null,
                $data['webseite'],
                $data['benutzername'],
                $passwortEnc,
                $totpQrUriEnc,
                $recoveryCodesEnc,
                $scriptsnippetEnc,
                date('Y-m-d H:i:s'),
                $this->id
            ]
        );
        
        return $result->rowCount() > 0;
    }
    
    /**
     * Zugangsdaten löschen
     */
    public function delete(): bool
    {
        // RBAC-Check
        if (!rbac_check('delete', 'zugangsdaten', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Löschen dieser Zugangsdaten');
        }
        
        $result = db()->query("DELETE FROM zugangsdaten WHERE id = ?", [$this->id]);
        
        return $result->rowCount() > 0;
    }
    
    /**
     * Passwort entschlüsseln (nur mit Berechtigung)
     */
    public function revealPassword(): ?string
    {
        // Extra RBAC-Check für sensible Operation
        if (!rbac_check('reveal', 'zugangsdaten', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Anzeigen des Passworts');
        }
        
        try {
            return decrypt($this->passwort_enc);
        } catch (\Exception $e) {
            throw new \Exception('Passwort konnte nicht entschlüsselt werden');
        }
    }
    
    /**
     * TOTP URI entschlüsseln
     */
    public function revealTotpUri(): ?string
    {
        if (!$this->totp_qr_uri_enc) {
            return null;
        }
        
        if (!rbac_check('reveal', 'zugangsdaten', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Anzeigen der 2FA-Daten');
        }
        
        try {
            return decrypt($this->totp_qr_uri_enc);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Recovery Codes entschlüsseln
     */
    public function revealRecoveryCodes(): ?array
    {
        if (!$this->recovery_codes_enc) {
            return null;
        }
        
        if (!rbac_check('reveal', 'zugangsdaten', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Anzeigen der Recovery Codes');
        }
        
        try {
            $json = decrypt($this->recovery_codes_enc);
            return json_decode($json, true);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Script Snippet entschlüsseln
     */
    public function revealScriptSnippet(): ?string
    {
        if (!$this->scriptsnippet_enc) {
            return null;
        }
        
        if (!rbac_check('reveal', 'zugangsdaten', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Anzeigen des Script Snippets');
        }
        
        try {
            return decrypt($this->scriptsnippet_enc);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Korrespondent laden
     */
    public function getKorrespondent(): ?Korrespondent
    {
        if ($this->korrespondent === null && $this->korrespondent_id) {
            $this->korrespondent = Korrespondent::find($this->korrespondent_id);
        }
        return $this->korrespondent;
    }
    
    /**
     * Vertrag laden
     */
    public function getVertrag(): ?Vertrag
    {
        if ($this->vertrag === null && $this->vertrag_id) {
            $this->vertrag = Vertrag::find($this->vertrag_id);
        }
        return $this->vertrag;
    }
    
    /**
     * Prüfen ob 2FA konfiguriert ist
     */
    public function has2FA(): bool
    {
        return !empty($this->totp_qr_uri_enc);
    }
    
    /**
     * Prüfen ob Recovery Codes existieren
     */
    public function hasRecoveryCodes(): bool
    {
        return !empty($this->recovery_codes_enc);
    }
    
    /**
     * Prüfen ob Script Snippet existiert
     */
    public function hasScriptSnippet(): bool
    {
        return !empty($this->scriptsnippet_enc);
    }
    
    /**
     * Passwort-Stärke bewerten
     */
    public function getPasswordStrength(): array
    {
        try {
            $password = $this->revealPassword();
            if (!$password) {
                return ['score' => 0, 'label' => 'Unbekannt'];
            }
            
            $score = 0;
            $feedback = [];
            
            // Länge prüfen
            if (strlen($password) >= 8) $score += 2;
            elseif (strlen($password) >= 6) $score += 1;
            else $feedback[] = 'Zu kurz (min. 8 Zeichen)';
            
            // Zeichen-Arten prüfen
            if (preg_match('/[a-z]/', $password)) $score += 1;
            else $feedback[] = 'Kleinbuchstaben fehlen';
            
            if (preg_match('/[A-Z]/', $password)) $score += 1;
            else $feedback[] = 'Großbuchstaben fehlen';
            
            if (preg_match('/[0-9]/', $password)) $score += 1;
            else $feedback[] = 'Ziffern fehlen';
            
            if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 1;
            else $feedback[] = 'Sonderzeichen fehlen';
            
            // Bewertung
            $label = match (true) {
                $score >= 5 => 'Sehr stark',
                $score >= 4 => 'Stark',
                $score >= 3 => 'Mittel',
                $score >= 2 => 'Schwach',
                default => 'Sehr schwach'
            };
            
            return [
                'score' => $score,
                'max_score' => 6,
                'percentage' => round(($score / 6) * 100),
                'label' => $label,
                'feedback' => $feedback
            ];
            
        } catch (\Exception $e) {
            return ['score' => 0, 'label' => 'Fehler bei Bewertung'];
        }
    }
    
    /**
     * Validierung für Zugangsdaten
     */
    public static function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        
        // Webseite erforderlich
        if (empty($data['webseite'])) {
            $errors[] = 'Webseite ist erforderlich';
        }
        
        // Benutzername erforderlich
        if (empty($data['benutzername'])) {
            $errors[] = 'Benutzername ist erforderlich';
        }
        
        // Passwort erforderlich (bei Neuanlage)
        if ($excludeId === null && empty($data['passwort'])) {
            $errors[] = 'Passwort ist erforderlich';
        }
        
        // Korrespondent oder Vertrag muss angegeben werden
        if (empty($data['korrespondent_id']) && empty($data['vertrag_id'])) {
            $errors[] = 'Korrespondent oder Vertrag muss angegeben werden';
        }
        
        // Korrespondent existiert?
        if (!empty($data['korrespondent_id'])) {
            $korrespondent = Korrespondent::find($data['korrespondent_id']);
            if (!$korrespondent) {
                $errors[] = 'Ungültiger Korrespondent';
            }
        }
        
        // Vertrag existiert?
        if (!empty($data['vertrag_id'])) {
            $vertrag = Vertrag::find($data['vertrag_id']);
            if (!$vertrag) {
                $errors[] = 'Ungültiger Vertrag';
            }
        }
        
        // TOTP URI Format prüfen
        if (!empty($data['totp_qr_uri']) && !str_starts_with($data['totp_qr_uri'], 'otpauth://')) {
            $errors[] = 'TOTP URI muss im otpauth:// Format sein';
        }
        
        // Recovery Codes Format prüfen
        if (!empty($data['recovery_codes']) && !is_array($data['recovery_codes'])) {
            $errors[] = 'Recovery Codes müssen als Array übergeben werden';
        }
        
        return $errors;
    }
    
    /**
     * Array-Darstellung für JSON-Response (ohne sensitive Daten)
     */
    public function toArray(bool $includeSensitive = false): array
    {
        $data = [
            'id' => $this->id,
            'korrespondent_id' => $this->korrespondent_id,
            'vertrag_id' => $this->vertrag_id,
            'webseite' => $this->webseite,
            'benutzername' => $this->benutzername,
            'has_2fa' => $this->has2FA(),
            'has_recovery_codes' => $this->hasRecoveryCodes(),
            'has_script_snippet' => $this->hasScriptSnippet(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s')
        ];
        
        if ($includeSensitive && rbac_check('reveal', 'zugangsdaten', ['id' => $this->id])) {
            $data['password_strength'] = $this->getPasswordStrength();
        }
        
        return $data;
    }
    
    /**
     * Export-sichere Darstellung (für Notfall-Paket)
     */
    public function toExportArray(): array
    {
        if (!rbac_check('export', 'zugangsdaten', ['id' => $this->id])) {
            throw new \Exception('Keine Export-Berechtigung');
        }
        
        return [
            'webseite' => $this->webseite,
            'benutzername' => $this->benutzername,
            'passwort' => $this->revealPassword(),
            'totp_verfuegbar' => $this->has2FA(),
            'recovery_codes_verfuegbar' => $this->hasRecoveryCodes()
        ];
    }
}