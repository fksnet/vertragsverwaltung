<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Dokument;
use App\Models\Korrespondent;
use App\Models\Vertrag;

/**
 * Dokumente Controller für PDF-Upload und -Verwaltung
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class DokumenteController extends BaseController
{
    /**
     * Dokumente-Liste anzeigen
     */
    public function index(): string
    {
        $this->authorize('read', 'dokument');
        
        // Filter aus Request
        $filters = [
            'search' => $_GET['q'] ?? '',
            'korrespondent_id' => $_GET['korrespondent_id'] ?? '',
            'vertrag_id' => $_GET['vertrag_id'] ?? '',
            'mime_type' => $_GET['mime_type'] ?? '',
            'from_date' => $_GET['from_date'] ?? '',
            'to_date' => $_GET['to_date'] ?? '',
            'page' => (int) ($_GET['page'] ?? 1),
            'limit' => 20
        ];
        
        // Dokumente laden
        $dokumente = Dokument::all($filters);
        $paginatedData = $this->paginate($dokumente, $filters['limit']);
        
        // Filter-Optionen
        $korrespondenten = Korrespondent::all(['limit' => 100]);
        $vertraege = Vertrag::all(['limit' => 100, 'status' => 'laufend']);
        
        $mimeTypeOptions = [
            '' => 'Alle Dateitypen',
            'application/pdf' => 'PDF Dokumente',
            'image/' => 'Bilder',
            'application/msword' => 'Word Dokumente'
        ];
        
        // Statistiken
        $stats = Dokument::getStats();
        
        // Für htmx-Requests nur die Tabelle zurückgeben
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            return $this->view('partials/dokumente/table', [
                'dokumente' => $paginatedData['data'],
                'pagination' => $paginatedData['pagination']
            ]);
        }
        
        return $this->view('pages/dokumente/index', [
            'page_title' => 'Dokumente',
            'dokumente' => $paginatedData['data'],
            'pagination' => $paginatedData['pagination'],
            'filters' => $filters,
            'korrespondenten' => $korrespondenten,
            'vertraege' => $vertraege,
            'mime_type_options' => $mimeTypeOptions,
            'stats' => $stats,
            'can_upload' => rbac_check('create', 'dokument')
        ]);
    }
    
    /**
     * Dokument-Details anzeigen oder inline anzeigen
     */
    public function show(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $dokument = Dokument::find($id);
        
        if (!$dokument) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        // Korrespondent und Vertrag laden
        $korrespondent = $dokument->getKorrespondent();
        $vertrag = $dokument->getVertrag();
        
        // Für PDF/Bilder: Inline-Viewer
        if ($dokument->isViewable()) {
            return $this->view('pages/dokumente/viewer', [
                'page_title' => 'Dokument: ' . $dokument->dateiname_original,
                'dokument' => $dokument,
                'korrespondent' => $korrespondent,
                'vertrag' => $vertrag,
                'can_delete' => rbac_check('delete', 'dokument', ['id' => $id])
            ]);
        }
        
        // Für andere Dateitypen: Download-Seite
        return $this->view('pages/dokumente/show', [
            'page_title' => 'Dokument: ' . $dokument->dateiname_original,
            'dokument' => $dokument,
            'korrespondent' => $korrespondent,
            'vertrag' => $vertrag,
            'can_delete' => rbac_check('delete', 'dokument', ['id' => $id])
        ]);
    }
    
    /**
     * Dokument-Upload-Formular
     */
    public function uploadForm(): string
    {
        $this->authorize('create', 'dokument');
        
        // Korrespondenten und Verträge für Dropdowns
        $korrespondenten = Korrespondent::all();
        $vertraege = Vertrag::all(['status' => 'laufend']);
        
        // Vorgefüllte Werte
        $preselected = [
            'korrespondent_id' => $_GET['korrespondent_id'] ?? null,
            'vertrag_id' => $_GET['vertrag_id'] ?? null
        ];
        
        return $this->view('pages/dokumente/upload', [
            'page_title' => 'Dokument hochladen',
            'korrespondenten' => $korrespondenten,
            'vertraege' => $vertraege,
            'preselected' => $preselected,
            'max_file_size_mb' => round(Dokument::MAX_FILE_SIZE / 1024 / 1024),
            'allowed_types' => implode(', ', array_map(fn($mime) => explode('/', $mime)[1] ?? $mime, Dokument::ALLOWED_MIMES))
        ]);
    }
    
    /**
     * Dokument hochladen
     */
    public function upload(): void
    {
        $this->authorize('create', 'dokument');
        
        try {
            // File-Upload validieren
            if (!isset($_FILES['dokument']) || $_FILES['dokument']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Keine Datei hochgeladen oder Upload-Fehler');
            }
            
            $korrespondentId = !empty($_POST['korrespondent_id']) ? (int) $_POST['korrespondent_id'] : null;
            $vertragId = !empty($_POST['vertrag_id']) ? (int) $_POST['vertrag_id'] : null;
            
            if (!$korrespondentId && !$vertragId) {
                throw new \Exception('Korrespondent oder Vertrag muss ausgewählt werden');
            }
            
            $dokument = Dokument::upload($_FILES['dokument'], $korrespondentId, $vertragId);
            
            // Für htmx-Requests
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => 'Dokument erfolgreich hochgeladen',
                    'redirect' => "/dokumente/{$dokument->id}"
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                "/dokumente/{$dokument->id}",
                'Dokument erfolgreich hochgeladen',
                'success'
            );
            
        } catch (\Exception $e) {
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
                return;
            }
            
            $this->redirectWithMessage('/dokumente/upload', $e->getMessage(), 'error');
        }
    }
    
    /**
     * Mehrere Dateien gleichzeitig hochladen (Drag & Drop)
     */
    public function multiUpload(): void
    {
        $this->authorize('create', 'dokument');
        
        try {
            $korrespondentId = !empty($_POST['korrespondent_id']) ? (int) $_POST['korrespondent_id'] : null;
            $vertragId = !empty($_POST['vertrag_id']) ? (int) $_POST['vertrag_id'] : null;
            
            if (!$korrespondentId && !$vertragId) {
                throw new \Exception('Korrespondent oder Vertrag muss ausgewählt werden');
            }
            
            $uploadedFiles = [];
            $errors = [];
            
            if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'])) {
                throw new \Exception('Keine Dateien zum Upload gefunden');
            }
            
            $fileCount = count($_FILES['files']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                try {
                    $fileData = [
                        'name' => $_FILES['files']['name'][$i],
                        'type' => $_FILES['files']['type'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                        'error' => $_FILES['files']['error'][$i],
                        'size' => $_FILES['files']['size'][$i]
                    ];
                    
                    if ($fileData['error'] === UPLOAD_ERR_OK) {
                        $dokument = Dokument::upload($fileData, $korrespondentId, $vertragId);
                        $uploadedFiles[] = $dokument->toArray();
                    } else {
                        $errors[] = "Fehler bei {$fileData['name']}: Upload-Fehler {$fileData['error']}";
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Fehler bei {$_FILES['files']['name'][$i]}: " . $e->getMessage();
                }
            }
            
            $successCount = count($uploadedFiles);
            $errorCount = count($errors);
            
            $this->json([
                'success' => $successCount > 0,
                'message' => "$successCount Datei(en) erfolgreich hochgeladen" . ($errorCount > 0 ? ", $errorCount Fehler" : ""),
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors,
                'stats' => [
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'total_count' => $fileCount
                ]
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Dokument herunterladen
     */
    public function download(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $dokument = Dokument::find($id);
        
        if (!$dokument) {
            http_response_code(404);
            echo 'Dokument nicht gefunden';
            return;
        }
        
        // Prüfen ob Datei existiert
        if (!file_exists($dokument->pfad)) {
            http_response_code(404);
            echo 'Datei nicht gefunden';
            return;
        }
        
        // Download-Header setzen
        header('Content-Type: ' . $dokument->mime);
        header('Content-Length: ' . $dokument->groesse);
        header('Content-Disposition: ' . $dokument->getContentDisposition(false));
        
        // Cache-Header für bessere Performance
        $lastModified = filemtime($dokument->pfad);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('ETag: "' . md5($lastModified . $dokument->groesse) . '"');
        
        // Conditional GET für Caching
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
            $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
            
            if (strtotime($ifModifiedSince) >= $lastModified || $ifNoneMatch === '"' . md5($lastModified . $dokument->groesse) . '"') {
                http_response_code(304);
                return;
            }
        }
        
        // Datei ausgeben
        readfile($dokument->pfad);
    }
    
    /**
     * Dokument inline anzeigen (für PDF-Viewer)
     */
    public function inline(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $dokument = Dokument::find($id);
        
        if (!$dokument || !$dokument->isViewable()) {
            http_response_code(404);
            echo 'Dokument nicht gefunden oder nicht anzeigbar';
            return;
        }
        
        if (!file_exists($dokument->pfad)) {
            http_response_code(404);
            echo 'Datei nicht gefunden';
            return;
        }
        
        // Inline-Display-Header
        header('Content-Type: ' . $dokument->mime);
        header('Content-Length: ' . $dokument->groesse);
        header('Content-Disposition: ' . $dokument->getContentDisposition(true));
        
        // Cache-Header
        $lastModified = filemtime($dokument->pfad);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('Cache-Control: public, max-age=3600');
        
        readfile($dokument->pfad);
    }
    
    /**
     * Dokument löschen
     */
    public function delete(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $dokument = Dokument::find($id);
        
        if (!$dokument) {
            http_response_code(404);
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json(['success' => false, 'message' => 'Dokument nicht gefunden'], 404);
                return;
            }
            $this->redirect('/dokumente');
            return;
        }
        
        $this->authorize('delete', 'dokument', ['id' => $id]);
        
        try {
            $filename = $dokument->dateiname_original;
            $dokument->delete();
            
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => "Dokument '$filename' erfolgreich gelöscht",
                    'redirect' => '/dokumente'
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                '/dokumente',
                "Dokument '$filename' erfolgreich gelöscht",
                'success'
            );
            
        } catch (\Exception $e) {
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
                return;
            }
            
            $this->redirectWithMessage("/dokumente/{$id}", $e->getMessage(), 'error');
        }
    }
    
    /**
     * Mehrere Dokumente löschen (Bulk-Operation)
     */
    public function bulkDelete(): void
    {
        $this->authorize('delete', 'dokument');
        
        try {
            $ids = $_POST['ids'] ?? [];
            
            if (empty($ids) || !is_array($ids)) {
                throw new \Exception('Keine Dokumente ausgewählt');
            }
            
            $deleted = 0;
            $errors = [];
            
            foreach ($ids as $id) {
                try {
                    $dokument = Dokument::find((int) $id);
                    if ($dokument && rbac_check('delete', 'dokument', ['id' => $id])) {
                        $dokument->delete();
                        $deleted++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "ID $id: " . $e->getMessage();
                }
            }
            
            $this->json([
                'success' => $deleted > 0,
                'message' => "$deleted Dokument(e) gelöscht" . (!empty($errors) ? ', ' . count($errors) . ' Fehler' : ''),
                'deleted_count' => $deleted,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Dokument-Thumbnail generieren (für Bilder)
     */
    public function thumbnail(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $size = (int) ($_GET['size'] ?? 150);
        
        $dokument = Dokument::find($id);
        
        if (!$dokument || !str_starts_with($dokument->mime, 'image/')) {
            http_response_code(404);
            return;
        }
        
        if (!file_exists($dokument->pfad)) {
            http_response_code(404);
            return;
        }
        
        // Thumbnail-Cache-Pfad
        $cacheDir = app_path('../public/uploads/thumbnails');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . '/' . $dokument->id . '_' . $size . '.jpg';
        
        // Cache prüfen
        if (file_exists($cacheFile) && filemtime($cacheFile) >= filemtime($dokument->pfad)) {
            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=86400');
            readfile($cacheFile);
            return;
        }
        
        try {
            // Thumbnail generieren
            $sourceImage = match ($dokument->mime) {
                'image/jpeg' => imagecreatefromjpeg($dokument->pfad),
                'image/png' => imagecreatefrompng($dokument->pfad),
                default => null
            };
            
            if (!$sourceImage) {
                throw new \Exception('Bild konnte nicht geladen werden');
            }
            
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            
            // Proportionen berechnen
            $ratio = min($size / $sourceWidth, $size / $sourceHeight);
            $thumbWidth = (int) ($sourceWidth * $ratio);
            $thumbHeight = (int) ($sourceHeight * $ratio);
            
            // Thumbnail erstellen
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagecopyresampled(
                $thumbnail, $sourceImage,
                0, 0, 0, 0,
                $thumbWidth, $thumbHeight,
                $sourceWidth, $sourceHeight
            );
            
            // Speichern und ausgeben
            imagejpeg($thumbnail, $cacheFile, 85);
            
            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=86400');
            readfile($cacheFile);
            
            // Memory cleanup
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo 'Thumbnail-Generierung fehlgeschlagen';
        }
    }
    
    /**
     * Cleanup alte temporäre Dateien
     */
    public function cleanup(): void
    {
        $this->authorize('admin', 'system');
        
        try {
            $cleaned = Dokument::cleanupTempFiles();
            
            $this->json([
                'success' => true,
                'message' => "$cleaned temporäre Dateien bereinigt",
                'cleaned_count' => $cleaned
            ]);
            
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    
    /**
     * Dokument-Statistiken
     */
    public function stats(): void
    {
        $this->authorize('read', 'dokument');
        
        try {
            $stats = Dokument::getStats();
            
            // Zusätzliche berechnete Werte
            $stats['total_size_mb'] = round($stats['total_size_bytes'] / 1024 / 1024, 2);
            $stats['avg_size_mb'] = $stats['total_count'] > 0 ? 
                round($stats['total_size_bytes'] / $stats['total_count'] / 1024 / 1024, 2) : 0;
            
            $this->json($stats);
            
        } catch (\Exception $e) {
            $this->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}