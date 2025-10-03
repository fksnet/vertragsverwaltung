<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\RBAC;

/**
 * Dokument-Model für PDF-Upload und -Verwaltung
 * 
 * @package App\Models
 * @author GenSpark AI Developer
 */
class Dokument
{
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    public const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25 MB
    
    public int $id;
    public ?int $korrespondent_id;
    public ?int $vertrag_id;
    public string $dateiname_original;
    public string $pfad;
    public string $mime;
    public int $groesse;
    public \DateTime $hochgeladen_am;
    
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
        $this->dateiname_original = $data['dateiname_original'];
        $this->pfad = $data['pfad'];
        $this->mime = $data['mime'];
        $this->groesse = (int) $data['groesse'];
        $this->hochgeladen_am = new \DateTime($data['hochgeladen_am']);
    }
    
    /**
     * Dokument aus Datenbank laden
     */
    public static function find(int $id): ?self
    {
        $data = db()->query(
            "SELECT * FROM dokumente WHERE id = ? LIMIT 1",
            [$id]
        )->fetch();
        
        if (!$data) {
            return null;
        }
        
        // RBAC-Check
        if (!rbac_check('read', 'dokument', ['id' => $id])) {
            return null;
        }
        
        return new self($data);
    }
    
    /**
     * Alle Dokumente abrufen (mit RBAC-Filterung)
     */
    public static function all(array $filters = []): array
    {
        $where = [];
        $params = [];
        
        // Korrespondent-Filter
        if (!empty($filters['korrespondent_id'])) {
            $where[] = "d.korrespondent_id = ?";
            $params[] = $filters['korrespondent_id'];
        }
        
        // Vertrag-Filter
        if (!empty($filters['vertrag_id'])) {
            $where[] = "d.vertrag_id = ?";
            $params[] = $filters['vertrag_id'];
        }
        
        // MIME-Type Filter
        if (!empty($filters['mime_type'])) {
            $where[] = "d.mime LIKE ?";
            $params[] = $filters['mime_type'] . '%';
        }
        
        // Such-Filter
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $where[] = "(d.dateiname_original LIKE ? OR k.name LIKE ?)";
            $params[] = $search;
            $params[] = $search;
        }
        
        // Zeitraum-Filter
        if (!empty($filters['from_date'])) {
            $where[] = "d.hochgeladen_am >= ?";
            $params[] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $where[] = "d.hochgeladen_am <= ?";
            $params[] = $filters['to_date'] . ' 23:59:59';
        }
        
        $sql = "SELECT d.*, k.name as korrespondent_name, v.status as vertrag_status
                FROM dokumente d 
                LEFT JOIN korrespondenten k ON d.korrespondent_id = k.id
                LEFT JOIN vertraege v ON d.vertrag_id = v.id";
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY d.hochgeladen_am DESC";
        
        // Pagination
        if (!empty($filters['limit'])) {
            $offset = ($filters['page'] ?? 1 - 1) * $filters['limit'];
            $sql .= " LIMIT {$filters['limit']} OFFSET $offset";
        }
        
        $results = db()->query($sql, $params)->fetchAll();
        
        // RBAC-Filterung
        $filtered = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'dokument', ['id' => $data['id']])) {
                $dokument = new self($data);
                $dokument->korrespondent_name = $data['korrespondent_name'];
                $dokument->vertrag_status = $data['vertrag_status'];
                $filtered[] = $dokument;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Dokumente für Korrespondent
     */
    public static function findByKorrespondent(int $korrespondentId): array
    {
        $results = db()->query(
            "SELECT * FROM dokumente WHERE korrespondent_id = ? ORDER BY hochgeladen_am DESC",
            [$korrespondentId]
        )->fetchAll();
        
        $dokumente = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'dokument', ['id' => $data['id']])) {
                $dokumente[] = new self($data);
            }
        }
        
        return $dokumente;
    }
    
    /**
     * Dokumente für Vertrag
     */
    public static function findByVertrag(int $vertragId): array
    {
        $results = db()->query(
            "SELECT * FROM dokumente WHERE vertrag_id = ? ORDER BY hochgeladen_am DESC",
            [$vertragId]
        )->fetchAll();
        
        $dokumente = [];
        foreach ($results as $data) {
            if (rbac_check('read', 'dokument', ['id' => $data['id']])) {
                $dokumente[] = new self($data);
            }
        }
        
        return $dokumente;
    }
    
    /**
     * Dokument-Upload
     */
    public static function upload(array $fileData, ?int $korrespondentId = null, ?int $vertragId = null): self
    {
        // RBAC-Check
        if (!rbac_check('create', 'dokument')) {
            throw new \Exception('Keine Berechtigung zum Hochladen von Dokumenten');
        }
        
        // File-Validierung
        $errors = self::validateFile($fileData);
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Upload failed: ' . implode(', ', $errors));
        }
        
        // Korrespondent oder Vertrag muss angegeben werden
        if (!$korrespondentId && !$vertragId) {
            throw new \InvalidArgumentException('Korrespondent oder Vertrag muss angegeben werden');
        }
        
        // Sicherer Dateiname generieren
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $safeFilename = self::generateSafeFilename($fileData['name'], $extension);
        
        // Upload-Pfad bestimmen
        $uploadDir = self::getUploadDir($korrespondentId, $vertragId);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filePath = $uploadDir . '/' . $safeFilename;
        
        // Datei verschieben
        if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
            throw new \Exception('Datei konnte nicht hochgeladen werden');
        }
        
        // Dateiberechtigungen setzen
        chmod($filePath, 0644);
        
        // MIME-Type nochmals prüfen
        $realMime = self::detectMimeType($filePath);
        if (!in_array($realMime, self::ALLOWED_MIMES)) {
            unlink($filePath);
            throw new \Exception('Ungültiger Dateityp: ' . $realMime);
        }
        
        // Datenbankeneintrag erstellen
        $id = db()->query(
            "INSERT INTO dokumente (
                korrespondent_id, vertrag_id, dateiname_original, pfad, mime, groesse, hochgeladen_am
            ) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $korrespondentId,
                $vertragId,
                $fileData['name'],
                $filePath,
                $realMime,
                filesize($filePath),
                date('Y-m-d H:i:s')
            ]
        )->lastInsertId();
        
        return self::find($id);
    }
    
    /**
     * Dokument löschen
     */
    public function delete(): bool
    {
        // RBAC-Check
        if (!rbac_check('delete', 'dokument', ['id' => $this->id])) {
            throw new \Exception('Keine Berechtigung zum Löschen dieses Dokuments');
        }
        
        // Datei löschen
        if (file_exists($this->pfad)) {
            unlink($this->pfad);
        }
        
        // Datenbankeintrag löschen
        $result = db()->query("DELETE FROM dokumente WHERE id = ?", [$this->id]);
        
        return $result->rowCount() > 0;
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
     * Dateierweiterung ermitteln
     */
    public function getExtension(): string
    {
        return strtolower(pathinfo($this->dateiname_original, PATHINFO_EXTENSION));
    }
    
    /**
     * Icon-Klasse für Dateitype
     */
    public function getIconClass(): string
    {
        return match ($this->mime) {
            'application/pdf' => 'fas fa-file-pdf text-red-500',
            'image/jpeg', 'image/png' => 'fas fa-file-image text-blue-500',
            'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fas fa-file-word text-blue-600',
            default => 'fas fa-file text-gray-500'
        };
    }
    
    /**
     * Dateigröße formatiert
     */
    public function getFormattedSize(): string
    {
        $bytes = $this->groesse;
        
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        
        return $bytes . ' Bytes';
    }
    
    /**
     * Prüfen ob Datei als PDF anzeigbar ist
     */
    public function isViewable(): bool
    {
        return $this->mime === 'application/pdf' || 
               str_starts_with($this->mime, 'image/');
    }
    
    /**
     * Download-URL generieren
     */
    public function getDownloadUrl(): string
    {
        return "/dokumente/{$this->id}/download";
    }
    
    /**
     * View-URL für PDFs generieren
     */
    public function getViewUrl(): string
    {
        if (!$this->isViewable()) {
            return $this->getDownloadUrl();
        }
        
        return "/dokumente/{$this->id}";
    }
    
    /**
     * Content-Disposition Header für Download
     */
    public function getContentDisposition(bool $inline = false): string
    {
        $type = $inline ? 'inline' : 'attachment';
        $filename = $this->dateiname_original;
        
        // RFC 5987 encoding für Dateinamen mit Umlauten
        $encodedFilename = rawurlencode($filename);
        
        return "$type; filename=\"$filename\"; filename*=UTF-8''$encodedFilename";
    }
    
    /**
     * Datei-Upload validieren
     */
    private static function validateFile(array $fileData): array
    {
        $errors = [];
        
        // Upload-Error prüfen
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload-Fehler: ' . self::getUploadErrorMessage($fileData['error']);
            return $errors;
        }
        
        // Dateigröße prüfen
        if ($fileData['size'] > self::MAX_FILE_SIZE) {
            $maxMb = round(self::MAX_FILE_SIZE / (1024 * 1024));
            $errors[] = "Datei zu groß (max. {$maxMb} MB)";
        }
        
        // MIME-Type prüfen
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($fileData['tmp_name']);
        
        if (!in_array($mime, self::ALLOWED_MIMES)) {
            $errors[] = 'Dateityp nicht erlaubt: ' . $mime;
        }
        
        // Dateiname prüfen
        if (empty($fileData['name'])) {
            $errors[] = 'Dateiname ist leer';
        } elseif (preg_match('/[<>:"|?*]/', $fileData['name'])) {
            $errors[] = 'Dateiname enthält ungültige Zeichen';
        }
        
        return $errors;
    }
    
    /**
     * Sicheren Dateinamen generieren
     */
    private static function generateSafeFilename(string $originalName, string $extension): string
    {
        // Timestamp und Random-String für Eindeutigkeit
        $timestamp = date('Y-m-d_H-i-s');
        $random = bin2hex(random_bytes(4));
        
        // Originaler Dateiname bereinigen
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $safeName = substr($safeName, 0, 50); // Maximale Länge begrenzen
        
        return "{$timestamp}_{$random}_{$safeName}.{$extension}";
    }
    
    /**
     * Upload-Verzeichnis ermitteln
     */
    private static function getUploadDir(?int $korrespondentId, ?int $vertragId): string
    {
        $baseDir = app_path('../public/uploads');
        
        if ($vertragId) {
            return $baseDir . '/vertraege/' . $vertragId;
        } elseif ($korrespondentId) {
            return $baseDir . '/korrespondenten/' . $korrespondentId;
        }
        
        return $baseDir . '/misc';
    }
    
    /**
     * MIME-Type der Datei ermitteln
     */
    private static function detectMimeType(string $filePath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($filePath);
    }
    
    /**
     * Upload-Error-Message
     */
    private static function getUploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE => 'Datei zu groß (php.ini upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'Datei zu groß (HTML MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'Datei nur teilweise hochgeladen',
            UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt',
            UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden',
            UPLOAD_ERR_EXTENSION => 'Upload durch PHP-Erweiterung gestoppt',
            default => 'Unbekannter Upload-Fehler'
        };
    }
    
    /**
     * Statistiken für Dokumente
     */
    public static function getStats(): array
    {
        return [
            'total_count' => db()->query("SELECT COUNT(*) FROM dokumente")->fetchColumn(),
            'total_size_bytes' => db()->query("SELECT COALESCE(SUM(groesse), 0) FROM dokumente")->fetchColumn(),
            'pdf_count' => db()->query("SELECT COUNT(*) FROM dokumente WHERE mime = 'application/pdf'")->fetchColumn(),
            'image_count' => db()->query("SELECT COUNT(*) FROM dokumente WHERE mime LIKE 'image/%'")->fetchColumn(),
            'recent_count' => db()->query("SELECT COUNT(*) FROM dokumente WHERE hochgeladen_am >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn()
        ];
    }
    
    /**
     * Cleanup alte temporäre Dateien
     */
    public static function cleanupTempFiles(): int
    {
        $tempDir = app_path('../public/uploads/temp');
        $count = 0;
        
        if (!is_dir($tempDir)) {
            return 0;
        }
        
        $files = glob($tempDir . '/*');
        $cutoff = time() - (24 * 60 * 60); // 24 Stunden
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Array-Darstellung für JSON-Response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'korrespondent_id' => $this->korrespondent_id,
            'vertrag_id' => $this->vertrag_id,
            'dateiname_original' => $this->dateiname_original,
            'mime' => $this->mime,
            'groesse' => $this->groesse,
            'formatted_size' => $this->getFormattedSize(),
            'extension' => $this->getExtension(),
            'icon_class' => $this->getIconClass(),
            'is_viewable' => $this->isViewable(),
            'download_url' => $this->getDownloadUrl(),
            'view_url' => $this->getViewUrl(),
            'hochgeladen_am' => $this->hochgeladen_am->format('Y-m-d H:i:s')
        ];
    }
}