<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\RBAC;

/**
 * User-Model mit RBAC-Integration
 * 
 * @package App\Models
 * @author GenSpark AI Developer
 */
class User
{
    public int $id;
    public string $email;
    public string $name;
    public int $rolle_id;
    public ?string $rolle_name;
    public bool $aktiv;
    public ?string $angelegt_am;
    public bool $twofa_enabled = false;
    
    private array $originalData;
    
    public function __construct(array $data)
    {
        $this->originalData = $data;
        
        $this->id = (int) $data['id'];
        $this->email = $data['email'];
        $this->name = $data['name'];
        $this->rolle_id = (int) $data['rolle_id'];
        $this->rolle_name = $data['rolle_name'] ?? null;
        $this->aktiv = (bool) $data['aktiv'];
        $this->angelegt_am = $data['angelegt_am'] ?? null;
        $this->twofa_enabled = !empty($data['2fa_secret_enc']);
    }
    
    /**
     * Administrator-Check
     */
    public function isAdmin(): bool
    {
        return $this->rolle_name === 'administrator';
    }
    
    /**
     * Super-Admin-Check (für RBAC-Bypass)
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && $this->id === 1; // Erster User ist Super-Admin
    }
    
    /**
     * Freund-Check
     */
    public function isFreund(): bool
    {
        return $this->rolle_name === 'freund';
    }
    
    /**
     * Berechtigung prüfen
     */
    public function can(string $action, string $resource, ?array $context = null): bool
    {
        return RBAC::can($action, $resource, $context);
    }
    
    /**
     * Benutzer aus Datenbank laden
     */
    public static function find(int $id): ?self
    {
        $data = Database::table('benutzer as b')
            ->select(['b.*', 'r.name as rolle_name'])
            ->leftJoin('rollen as r', 'b.rolle_id', '=', 'r.id')
            ->where('b.id', $id)
            ->first();
            
        return $data ? new self($data) : null;
    }
    
    /**
     * Benutzer nach E-Mail suchen
     */
    public static function findByEmail(string $email): ?self
    {
        $data = Database::table('benutzer as b')
            ->select(['b.*', 'r.name as rolle_name'])
            ->leftJoin('rollen as r', 'b.rolle_id', '=', 'r.id')
            ->where('b.email', $email)
            ->first();
            
        return $data ? new self($data) : null;
    }
    
    /**
     * Alle Benutzer abrufen (mit RBAC-Filterung)
     */
    public static function all(array $filters = []): array
    {
        $query = Database::table('benutzer as b')
            ->select(['b.*', 'r.name as rolle_name'])
            ->leftJoin('rollen as r', 'b.rolle_id', '=', 'r.id')
            ->orderBy('b.name');
            
        // RBAC-Filterung anwenden
        $query = RBAC::filterQuery('benutzer', $query);
        
        if ($query === null) {
            return []; // Kein Zugriff
        }
        
        // Zusätzliche Filter
        if (!empty($filters['rolle'])) {
            $query->where('r.name', $filters['rolle']);
        }
        
        if (isset($filters['aktiv'])) {
            $query->where('b.aktiv', (bool) $filters['aktiv']);
        }
        
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function($q) use ($search) {
                $q->where('b.name', 'LIKE', $search)
                  ->orWhere('b.email', 'LIKE', $search);
            });
        }
        
        $results = $query->get();
        
        return array_map(fn($data) => new self($data), $results);
    }
    
    /**
     * Neuen Benutzer erstellen
     */
    public static function create(array $data): self
    {
        // Validierung
        $errors = self::validate($data);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
        
        // Passwort hashen
        if (!empty($data['passwort'])) {
            $data['passwort_hash'] = \App\Core\Crypto::hashPassword($data['passwort']);
            unset($data['passwort']);
        }
        
        $data['angelegt_am'] = date('Y-m-d H:i:s');
        $data['aktiv'] = $data['aktiv'] ?? true;
        
        $id = Database::table('benutzer')->insertGetId($data);
        
        return self::find($id);
    }
    
    /**
     * Benutzer aktualisieren
     */
    public function update(array $data): bool
    {
        // Sensitive Felder filtern
        $allowedFields = ['name', 'email', 'rolle_id', 'aktiv'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));
        
        // Passwort-Update falls übergeben
        if (!empty($data['passwort'])) {
            $updateData['passwort_hash'] = \App\Core\Crypto::hashPassword($data['passwort']);
        }
        
        if (empty($updateData)) {
            return true; // Nichts zu aktualisieren
        }
        
        return Database::table('benutzer')
            ->where('id', $this->id)
            ->update($updateData);
    }
    
    /**
     * Benutzer löschen (Soft Delete)
     */
    public function delete(): bool
    {
        return Database::table('benutzer')
            ->where('id', $this->id)
            ->update(['aktiv' => false]);
    }
    
    /**
     * 2FA-Status prüfen
     */
    public function has2FA(): bool
    {
        return $this->twofa_enabled;
    }
    
    /**
     * Validierung für Benutzer-Daten
     */
    public static function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        
        // Name erforderlich
        if (empty($data['name'])) {
            $errors[] = 'Name ist erforderlich';
        }
        
        // E-Mail erforderlich und gültig
        if (empty($data['email'])) {
            $errors[] = 'E-Mail ist erforderlich';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-Mail-Format ist ungültig';
        } else {
            // E-Mail-Eindeutigkeit prüfen
            $query = Database::table('benutzer')->where('email', $data['email']);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            
            if ($query->first()) {
                $errors[] = 'E-Mail-Adresse ist bereits vergeben';
            }
        }
        
        // Rolle erforderlich
        if (empty($data['rolle_id'])) {
            $errors[] = 'Rolle ist erforderlich';
        } else {
            // Rolle existiert?
            $rolle = Database::table('rollen')->find($data['rolle_id']);
            if (!$rolle) {
                $errors[] = 'Ungültige Rolle';
            }
        }
        
        // Passwort bei Neuanlage erforderlich
        if ($excludeId === null && empty($data['passwort'])) {
            $errors[] = 'Passwort ist erforderlich';
        }
        
        // Passwort-Stärke prüfen
        if (!empty($data['passwort'])) {
            $password = $data['passwort'];
            
            if (strlen($password) < 8) {
                $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein';
            }
            
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = 'Passwort muss mindestens einen Großbuchstaben enthalten';
            }
            
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = 'Passwort muss mindestens einen Kleinbuchstaben enthalten';
            }
            
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = 'Passwort muss mindestens eine Ziffer enthalten';
            }
        }
        
        return $errors;
    }
    
    /**
     * Freigaben für diesen Freund-Benutzer
     */
    public function getFreigaben(): array
    {
        if (!$this->isFreund()) {
            return [];
        }
        
        return Database::table('freigaben as f')
            ->select(['f.*', 'v.beginn', 'v.ende', 'v.status', 'k.name as korrespondent_name'])
            ->join('vertraege as v', 'f.vertrag_id', '=', 'v.id')
            ->join('korrespondenten as k', 'v.korrespondent_id', '=', 'k.id')
            ->where('f.freund_user_id', $this->id)
            ->orderBy('v.beginn', 'DESC')
            ->get();
    }
    
    /**
     * Zuletzt aktiv-Zeitstempel aktualisieren
     */
    public function updateLastActivity(): void
    {
        Database::table('benutzer')
            ->where('id', $this->id)
            ->update(['last_activity' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Avatar-URL generieren (Gravatar oder Initialen)
     */
    public function getAvatar(int $size = 40): string
    {
        // Gravatar-URL
        $hash = md5(strtolower(trim($this->email)));
        $gravatarUrl = "https://www.gravatar.com/avatar/$hash?s=$size&d=404";
        
        // Prüfen ob Gravatar existiert (hier vereinfacht)
        // In der Praxis könnte man das cachen
        
        // Fallback: Initialen-Avatar
        return $this->generateInitialsAvatar($size);
    }
    
    /**
     * Initialen-Avatar generieren
     */
    private function generateInitialsAvatar(int $size): string
    {
        $initials = '';
        $words = explode(' ', trim($this->name));
        
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
            if (strlen($initials) >= 2) break;
        }
        
        return "data:image/svg+xml;base64," . base64_encode(
            "<svg width='$size' height='$size' xmlns='http://www.w3.org/2000/svg'>
                <rect width='100%' height='100%' fill='#" . substr(md5($this->email), 0, 6) . "'/>
                <text x='50%' y='50%' font-family='Arial' font-size='" . ($size/2) . "' fill='white' 
                      text-anchor='middle' dominant-baseline='middle'>$initials</text>
            </svg>"
        );
    }
    
    /**
     * Array-Darstellung für JSON-Response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'rolle_id' => $this->rolle_id,
            'rolle_name' => $this->rolle_name,
            'aktiv' => $this->aktiv,
            'angelegt_am' => $this->angelegt_am,
            'twofa_enabled' => $this->twofa_enabled,
        ];
    }
}