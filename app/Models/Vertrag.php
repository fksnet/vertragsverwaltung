<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\RBAC;

/**
 * Vertrag-Model für Vertragsmanagement
 * 
 * @package App\Models
 * @author GenSpark AI Developer
 */
class Vertrag
{
    public const STATUS_LAUFEND = 'laufend';
    public const STATUS_GEKUENDIGT = 'gekündigt';
    public const STATUS_BEENDET = 'beendet';
    public const STATUS_STORNIERT = 'storniert';
    
    public const KOSTEN_ZEITRAUM_MONAT = 'monat';
    public const KOSTEN_ZEITRAUM_QUARTAL = 'quartal';
    public const KOSTEN_ZEITRAUM_JAHR = 'jahr';
    
    public const ZAHLUNGSZYKLUS_MONAT = 'monat';
    public const ZAHLUNGSZYKLUS_QUARTAL = 'quartal';
    public const ZAHLUNGSZYKLUS_JAHR = 'jahr';
    
    public const ZAHLUNGSWEISE_SEPA = 'sepa';
    public const ZAHLUNGSWEISE_KREDITKARTE = 'kreditkarte';
    public const ZAHLUNGSWEISE_UEBERWEISUNG = 'ueberweisung';
    public const ZAHLUNGSWEISE_PAYPAL = 'paypal';
    public const ZAHLUNGSWEISE_BAR = 'bar';
    public const ZAHLUNGSWEISE_SONSTIGES = 'sonstiges';
    
    public const VERLAENGERUNG_AUTOMATISCH = 'automatisch';
    public const VERLAENGERUNG_MANUELL = 'manuell';
    public const VERLAENGERUNG_KUENDIGUNGSFRIST = 'kuendigungsfrist';
    
    public int $id;
    public int $korrespondent_id;
    public \DateTime $beginn;
    public ?\DateTime $ende;
    public string $status;
    public ?int $laufzeit_monate;
    public int $kosten_cent;
    public string $kosten_zeitraum;
    public string $zahlungszyklus;
    public string $zahlungsweise;
    public string $verlängerungsmodus;
    public int $kuendigungsfrist_tage;
    public ?string $notizen;
    public ?string $kuendigungsablauf;
    public \DateTime $created_at;
    public \DateTime $updated_at;
    
    // Relations
    public ?Korrespondent $korrespondent = null;
    
    private array $originalData;
    
    public function __construct(array $data)
    {
        $this->originalData = $data;
        
        $this->id = (int) $data['id'];
        $this->korrespondent_id = (int) $data['korrespondent_id'];
        $this->beginn = new \DateTime($data['beginn']);
        $this->ende = $data['ende'] ? new \DateTime($data['ende']) : null;
        $this->status = $data['status'];
        $this->laufzeit_monate = $data['laufzeit_monate'] ? (int) $data['laufzeit_monate'] : null;
        $this->kosten_cent = (int) $data['kosten_cent'];
        $this->kosten_zeitraum = $data['kosten_zeitraum'];
        $this->zahlungszyklus = $data['zahlungszyklus'];
        $this->zahlungsweise = $data['zahlungsweise'];
        $this->verlängerungsmodus = $data['verlängerungsmodus'];
        $this->kuendigungsfrist_tage = (int) $data['kuendigungsfrist_tage'];
        $this->notizen = $data['notizen'] ?? null;
        $this->kuendigungsablauf = $data['kuendigungsablauf'] ?? null;
        $this->created_at = new \DateTime($data['created_at'] ?? 'now');
        $this->updated_at = new \DateTime($data['updated_at'] ?? 'now');
    }
    
    /**
     * Vertrag aus Datenbank laden
     */
    public static function find(int $id): ?self
    {
        $data = db()->query(
            "SELECT * FROM vertraege WHERE id = ? LIMIT 1",
            [$id]
        )->fetch();
        
        if (!$data) {
            return null;
        }
        
        // RBAC-Check
        if (!rbac_check('read', 'vertrag', ['id' => $id])) {
            return null;
        }
        
        return new self($data);
    }
    
    /**
     * Verträge eines Korrespondenten
     */
    public static function findByKorrespondent(int $korrespondentId): array
    {
        $results = db()->query(
            "SELECT * FROM vertraege WHERE korrespondent_id = ? ORDER BY beginn DESC",
            [$korrespondentId]
        )->fetchAll();
        
        $vertraege = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'vertrag', ['id' => $data['id']])) {
                $vertraege[] = new self($data);
            }
        }
        
        return $vertraege;
    }
    
    /**
     * Alle Verträge abrufen (mit RBAC-Filterung)
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];
        
        // Status-Filter
        if (!empty($filters['status'])) {
            $where[] = "v.status = ?";
            $params[] = $filters['status'];
        }
        
        // Korrespondent-Filter
        if (!empty($filters['korrespondent_id'])) {
            $where[] = "v.korrespondent_id = ?";
            $params[] = $filters['korrespondent_id'];
        }
        
        // Such-Filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(k.name LIKE ? OR v.notizen LIKE ?)";
            $params[] = $search;
            $params[] = $search;
        }
        
        // Kündigungsfristen-Filter
        if (isset($filters['kuendigungsfrist_days'])) {
            $days = (int) $filters['kuendigungsfrist_days'];
            $where[] = "v.ende IS NOT NULL AND DATEDIFF(v.ende, NOW()) <= ? AND v.status = 'laufend'";
            $params[] = $days;
        }
        
        // Laufende Verträge ohne Ende-Datum aber mit Laufzeit
        if (isset($filters['needs_end_date'])) {
            $where[] = "v.ende IS NULL AND v.laufzeit_monate IS NOT NULL AND v.status = 'laufend'";
        }
        
        $sql = "SELECT v.*, k.name as korrespondent_name
                FROM vertraege v 
                LEFT JOIN korrespondenten k ON v.korrespondent_id = k.id";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY v.beginn DESC";
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = ($filters['page'] ?? 1 - 1) * $filters['limit'];
            $sql .= " LIMIT {$filters['limit']} OFFSET $offset";
        }
        
        $results = db()->query($sql, $params)->fetchAll();
        
        // RBAC-Filterung
        $filtered = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'vertrag', ['id' => $data['id']])) {
                $vertrag = new self($data);
                $vertrag->korrespondent_name = $data['korrespondent_name'];
                $filtered[] = $vertrag;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Verträge mit anstehenden Kündigungsfristen
     */
    public static function getUpcomingDeadlines(int $days = 30): array
    {
        $sql = "SELECT v.*, k.name as korrespondent_name,
                       DATEDIFF(
                           CASE 
                               WHEN v.ende IS NOT NULL THEN DATE_SUB(v.ende, INTERVAL v.kuendigungsfrist_tage DAY)
                               ELSE DATE_ADD(v.beginn, INTERVAL v.laufzeit_monate MONTH - INTERVAL v.kuendigungsfrist_tage DAY)
                           END, 
                           CURDATE()
                       ) as days_until_deadline
                FROM vertraege v 
                LEFT JOIN korrespondenten k ON v.korrespondent_id = k.id
                WHERE v.status = 'laufend' 
                AND (
                    (v.ende IS NOT NULL AND DATEDIFF(DATE_SUB(v.ende, INTERVAL v.kuendigungsfrist_tage DAY), CURDATE()) BETWEEN 0 AND ?)
                    OR 
                    (v.ende IS NULL AND v.laufzeit_monate IS NOT NULL AND DATEDIFF(DATE_ADD(v.beginn, INTERVAL v.laufzeit_monate MONTH - INTERVAL v.kuendigungsfrist_tage DAY), CURDATE()) BETWEEN 0 AND ?)
                )
                ORDER BY days_until_deadline ASC";
        
        $results = db()->query($sql, [$days, $days])->fetchAll();
        
        $vertraege = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'vertrag', ['id' => $data['id']])) {
                $vertrag = new self($data);
                $vertrag->korrespondent_name = $data['korrespondent_name'];
                $vertrag->days_until_deadline = (int) $data['days_until_deadline'];
                $vertraege[] = $vertrag;
            }
        }
        
        return $vertraege;
    }
    
    /**
     * Neuen Vertrag erstellen
     */
    public static function create(array $data): self
    {
        // RBAC-Check
        if (!rbac_check('create', 'vertrag')) {
            throw new \Exception('Keine Berechtigung zum Erstellen von Verträgen');
        }
        
        // Validierung
        $errors = self::validate($data);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
        
        $now = date('Y-m-d H:i:s');
        
        $id = db()->query(
            "INSERT INTO vertraege (
                korrespondent_id, beginn, ende, status, laufzeit_monate, kosten_cent, 
                kosten_zeitraum, zahlungszyklus, zahlungsweise, verlängerungsmodus, 
                kuendigungsfrist_tage, notizen, kuendigungsablauf, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['korrespondent_id'],
                $data['beginn'],
                $data['ende'] ?? null,
                $data['status'] ?? self::STATUS_LAUFEND,
                $data['laufzeit_monate'] ?? null,
                $data['kosten_cent'],
                $data['kosten_zeitraum'],
                $data['zahlungszyklus'],
                $data['zahlungsweise'],
                $data['verlängerungsmodus'],
                $data['kuendigungsfrist_tage'] ?? 30,
                $data['notizen'] ?? null,
                $data['kuendigungsablauf'] ?? null,
                $now,
                $now
            ]
        )->lastInsertId();
        
        return self::find($id);
    }
    
    /**
     * Vertrag aktualisieren
     */
    public function update(array $data): bool
    {
        // RBAC-Check
        if (!rbac_check('update', 'vertrag', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Bearbeiten dieses Vertrags');
        }
        
        // Validierung
        $errors = self::validate($data, $this->id);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $errors));
        }
        
        $result = db()->query(
            "UPDATE vertraege SET 
                korrespondent_id = ?, beginn = ?, ende = ?, status = ?, laufzeit_monate = ?,
                kosten_cent = ?, kosten_zeitraum = ?, zahlungszyklus = ?, zahlungsweise = ?,
                verlängerungsmodus = ?, kuendigungsfrist_tage = ?, notizen = ?, 
                kuendigungsablauf = ?, updated_at = ?
             WHERE id = ?",
            [
                $data['korrespondent_id'],
                $data['beginn'],
                $data['ende'] ?? null,
                $data['status'],
                $data['laufzeit_monate'] ?? null,
                $data['kosten_cent'],
                $data['kosten_zeitraum'],
                $data['zahlungszyklus'],
                $data['zahlungsweise'],
                $data['verlängerungsmodus'],
                $data['kuendigungsfrist_tage'],
                $data['notizen'] ?? null,
                $data['kuendigungsablauf'] ?? null,
                date('Y-m-d H:i:s'),
                $this->id
            ]
        );
        
        return $result->rowCount() > 0;
    }
    
    /**
     * Vertrag löschen
     */
    public function delete(): bool
    {
        // RBAC-Check
        if (!rbac_check('delete', 'vertrag', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Löschen dieses Vertrags');
        }
        
        // Freigaben löschen
        db()->query("DELETE FROM freigaben WHERE vertrag_id = ?", [$this->id]);
        
        // Zugehörige Dokumente löschen
        db()->query("DELETE FROM dokumente WHERE vertrag_id = ?", [$this->id]);
        
        // Zugehörige Zugangsdaten löschen
        db()->query("DELETE FROM zugangsdaten WHERE vertrag_id = ?", [$this->id]);
        
        // Vertrag löschen
        $result = db()->query("DELETE FROM vertraege WHERE id = ?", [$this->id]);
        
        return $result->rowCount() > 0;
    }
    
    /**
     * Korrespondent laden
     */
    public function getKorrespondent(): ?Korrespondent
    {
        if ($this->korrespondent === null) {
            $this->korrespondent = Korrespondent::find($this->korrespondent_id);
        }
        return $this->korrespondent;
    }
    
    /**
     * Monatliche Kosten berechnen
     */
    public function getMonthlyCoststCents(): int
    {
        switch ($this->kosten_zeitraum) {
            case self::KOSTEN_ZEITRAUM_MONAT:
                return $this->kosten_cent;
            case self::KOSTEN_ZEITRAUM_QUARTAL:
                return (int) round($this->kosten_cent / 3);
            case self::KOSTEN_ZEITRAUM_JAHR:
                return (int) round($this->kosten_cent / 12);
            default:
                return 0;
        }
    }
    
    /**
     * Kosten formatiert ausgeben
     */
    public function getFormattedCosts(): string
    {
        $euro = $this->kosten_cent / 100;
        $currency = config('app.currency', 'EUR');
        
        return number_format($euro, 2, ',', '.') . ' ' . $currency . ' / ' . $this->kosten_zeitraum;
    }
    
    /**
     * Nächste Kündigungsfrist berechnen
     */
    public function getKuendigungsfrist(): ?\DateTime
    {
        if ($this->status !== self::STATUS_LAUFEND) {
            return null;
        }
        
        if ($this->ende) {
            // Festes Ende-Datum
            $frist = clone $this->ende;
            $frist->sub(new \DateInterval('P' . $this->kuendigungsfrist_tage . 'D'));
            return $frist;
        } elseif ($this->laufzeit_monate) {
            // Laufzeit-basiert
            $ende = clone $this->beginn;
            $ende->add(new \DateInterval('P' . $this->laufzeit_monate . 'M'));
            $frist = clone $ende;
            $frist->sub(new \DateInterval('P' . $this->kuendigungsfrist_tage . 'D'));
            return $frist;
        }
        
        return null;
    }
    
    /**
     * Tage bis zur Kündigungsfrist
     */
    public function getDaysUntilDeadline(): ?int
    {
        $deadline = $this->getKuendigungsfrist();
        if (!$deadline) {
            return null;
        }
        
        $now = new \DateTime();
        $diff = $now->diff($deadline);
        
        if ($deadline < $now) {
            return -$diff->days; // Bereits überschritten
        }
        
        return $diff->days;
    }
    
    /**
     * Status-Badge CSS-Klasse
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_LAUFEND => 'badge-success',
            self::STATUS_GEKUENDIGT => 'badge-warning',
            self::STATUS_BEENDET => 'badge-neutral',
            self::STATUS_STORNIERT => 'badge-error',
            default => 'badge-ghost'
        };
    }
    
    /**
     * Zahlungsereignisse für Liquiditätsplanung generieren
     */
    public function generatePaymentEvents(\DateTime $from, \DateTime $to): array
    {
        $events = [];
        
        if ($this->status !== self::STATUS_LAUFEND) {
            return $events;
        }
        
        $interval = match ($this->zahlungszyklus) {
            self::ZAHLUNGSZYKLUS_MONAT => new \DateInterval('P1M'),
            self::ZAHLUNGSZYKLUS_QUARTAL => new \DateInterval('P3M'),
            self::ZAHLUNGSZYKLUS_JAHR => new \DateInterval('P1Y'),
            default => new \DateInterval('P1M')
        };
        
        $current = clone $this->beginn;
        
        // Ersten Zahlungstermin finden der >= $from ist
        while ($current < $from && $current < $to) {
            $current->add($interval);
        }
        
        // Events bis Ende-Datum oder $to generieren
        while ($current <= $to) {
            // Prüfen ob Vertrag zu diesem Zeitpunkt noch läuft
            if ($this->ende && $current > $this->ende) {
                break;
            }
            
            $events[] = [
                'date' => clone $current,
                'amount_cents' => $this->kosten_cent,
                'vertrag_id' => $this->id,
                'korrespondent_id' => $this->korrespondent_id,
                'description' => $this->getKorrespondent()?->name ?? 'Unbekannt'
            ];
            
            $current->add($interval);
        }
        
        return $events;
    }
    
    /**
     * Validierung für Vertrag-Daten
     */
    public static function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        
        // Korrespondent erforderlich
        if (empty($data['korrespondent_id'])) {
            $errors[] = 'Korrespondent ist erforderlich';
        } else {
            $korrespondent = Korrespondent::find($data['korrespondent_id']);
            if (!$korrespondent) {
                $errors[] = 'Ungültiger Korrespondent';
            }
        }
        
        // Beginn erforderlich
        if (empty($data['beginn'])) {
            $errors[] = 'Vertragsbeginn ist erforderlich';
        }
        
        // Ende-Datum muss nach Beginn liegen
        if (!empty($data['ende']) && !empty($data['beginn'])) {
            $beginn = new \DateTime($data['beginn']);
            $ende = new \DateTime($data['ende']);
            
            if ($ende <= $beginn) {
                $errors[] = 'Vertragsende muss nach Vertragsbeginn liegen';
            }
        }
        
        // Status validieren
        $validStatus = [self::STATUS_LAUFEND, self::STATUS_GEKUENDIGT, self::STATUS_BEENDET, self::STATUS_STORNIERT];
        if (!empty($data['status']) && !in_array($data['status'], $validStatus)) {
            $errors[] = 'Ungültiger Status';
        }
        
        // Kosten erforderlich
        if (!isset($data['kosten_cent']) || $data['kosten_cent'] < 0) {
            $errors[] = 'Kosten müssen angegeben werden und positiv sein';
        }
        
        // Kosten-Zeitraum validieren
        $validKostenZeitraum = [self::KOSTEN_ZEITRAUM_MONAT, self::KOSTEN_ZEITRAUM_QUARTAL, self::KOSTEN_ZEITRAUM_JAHR];
        if (empty($data['kosten_zeitraum']) || !in_array($data['kosten_zeitraum'], $validKostenZeitraum)) {
            $errors[] = 'Ungültiger Kosten-Zeitraum';
        }
        
        // Zahlungszyklus validieren
        $validZahlungszyklus = [self::ZAHLUNGSZYKLUS_MONAT, self::ZAHLUNGSZYKLUS_QUARTAL, self::ZAHLUNGSZYKLUS_JAHR];
        if (empty($data['zahlungszyklus']) || !in_array($data['zahlungszyklus'], $validZahlungszyklus)) {
            $errors[] = 'Ungültiger Zahlungszyklus';
        }
        
        // Zahlungsweise validieren
        $validZahlungsweise = [
            self::ZAHLUNGSWEISE_SEPA, self::ZAHLUNGSWEISE_KREDITKARTE, 
            self::ZAHLUNGSWEISE_UEBERWEISUNG, self::ZAHLUNGSWEISE_PAYPAL, 
            self::ZAHLUNGSWEISE_BAR, self::ZAHLUNGSWEISE_SONSTIGES
        ];
        if (empty($data['zahlungsweise']) || !in_array($data['zahlungsweise'], $validZahlungsweise)) {
            $errors[] = 'Ungültige Zahlungsweise';
        }
        
        // Verlängerungsmodus validieren
        $validVerlaengerung = [self::VERLAENGERUNG_AUTOMATISCH, self::VERLAENGERUNG_MANUELL, self::VERLAENGERUNG_KUENDIGUNGSFRIST];
        if (empty($data['verlängerungsmodus']) || !in_array($data['verlängerungsmodus'], $validVerlaengerung)) {
            $errors[] = 'Ungültiger Verlängerungsmodus';
        }
        
        // Kündigungsfrist muss positiv sein
        if (isset($data['kuendigungsfrist_tage']) && $data['kuendigungsfrist_tage'] < 0) {
            $errors[] = 'Kündigungsfrist muss positiv sein';
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
            'korrespondent_id' => $this->korrespondent_id,
            'beginn' => $this->beginn->format('Y-m-d'),
            'ende' => $this->ende?->format('Y-m-d'),
            'status' => $this->status,
            'laufzeit_monate' => $this->laufzeit_monate,
            'kosten_cent' => $this->kosten_cent,
            'kosten_zeitraum' => $this->kosten_zeitraum,
            'zahlungszyklus' => $this->zahlungszyklus,
            'zahlungsweise' => $this->zahlungsweise,
            'verlängerungsmodus' => $this->verlängerungsmodus,
            'kuendigungsfrist_tage' => $this->kuendigungsfrist_tage,
            'notizen' => $this->notizen,
            'kuendigungsablauf' => $this->kuendigungsablauf,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'monthly_costs_cents' => $this->getMonthlyCoststCents(),
            'formatted_costs' => $this->getFormattedCosts(),
            'kuendigungsfrist' => $this->getKuendigungsfrist()?->format('Y-m-d'),
            'days_until_deadline' => $this->getDaysUntilDeadline(),
            'status_badge_class' => $this->getStatusBadgeClass()
        ];
    }
}