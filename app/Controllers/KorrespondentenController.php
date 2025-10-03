<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Korrespondent;
use App\Models\Vertrag;
use App\Models\Zugangsdaten;
use App\Models\Dokument;

/**
 * Korrespondenten Controller für CRUD-Operationen
 * 
 * @package App\Controllers
 * @author GenSpark AI Developer
 */
class KorrespondentenController extends BaseController
{
    /**
     * Korrespondenten-Liste anzeigen
     */
    public function index(): string
    {
        $this->authorize('read', 'korrespondent');
        
        // Filter aus Request
        $filters = [
            'search' => $_GET['q'] ?? '',
            'has_active_contracts' => isset($_GET['active_only']) ? (bool) $_GET['active_only'] : null,
            'page' => (int) ($_GET['page'] ?? 1),
            'limit' => 20
        ];
        
        // Korrespondenten laden
        $korrespondenten = Korrespondent::all($filters);
        $paginatedData = $this->paginate($korrespondenten, $filters['limit']);
        
        // Statistiken für jeden Korrespondenten
        foreach ($paginatedData['data'] as $korrespondent) {
            $korrespondent->stats = $korrespondent->getStats();
        }
        
        // Für htmx-Requests nur die Tabelle zurückgeben
        if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
            return $this->view('partials/korrespondenten/table', [
                'korrespondenten' => $paginatedData['data'],
                'pagination' => $paginatedData['pagination']
            ]);
        }
        
        return $this->view('pages/korrespondenten/index', [
            'page_title' => 'Korrespondenten',
            'korrespondenten' => $paginatedData['data'],
            'pagination' => $paginatedData['pagination'],
            'filters' => $filters,
            'can_create' => rbac_check('create', 'korrespondent')
        ]);
    }
    
    /**
     * Korrespondent-Details anzeigen
     */
    public function show(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $korrespondent = Korrespondent::find($id);
        
        if (!$korrespondent) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        // Verträge laden
        $vertraege = $korrespondent->getVertraege();
        $activeVertraege = $korrespondent->getActiveVertraege();
        
        // Zugangsdaten laden
        $zugangsdaten = $korrespondent->getZugangsdaten();
        
        // Dokumente laden
        $dokumente = $korrespondent->getDokumente();
        
        // Statistiken
        $stats = $korrespondent->getStats();
        
        return $this->view('pages/korrespondenten/show', [
            'page_title' => $korrespondent->name,
            'korrespondent' => $korrespondent,
            'vertraege' => $vertraege,
            'active_vertraege' => $activeVertraege,
            'zugangsdaten' => $zugangsdaten,
            'dokumente' => $dokumente,
            'stats' => $stats,
            'can_edit' => rbac_check('update', 'korrespondent', ['id' => $id]),
            'can_delete' => rbac_check('delete', 'korrespondent', ['id' => $id])
        ]);
    }
    
    /**
     * Neuen Korrespondent erstellen - Formular anzeigen
     */
    public function create(): string
    {
        $this->authorize('create', 'korrespondent');
        
        return $this->view('pages/korrespondenten/create', [
            'page_title' => 'Neuer Korrespondent',
            'korrespondent' => null
        ]);
    }
    
    /**
     * Neuen Korrespondent speichern
     */
    public function store(): void
    {
        $this->authorize('create', 'korrespondent');
        
        try {
            $data = $this->validate([
                'name' => ['required', 'max:200'],
                'adresse' => ['required', 'max:500'],
                'webseite' => '',
                'hotline' => ''
            ]);
            
            // Kontaktdaten verarbeiten (falls vorhanden)
            $kontaktdaten = [];
            if (!empty($_POST['kontakte'])) {
                foreach ($_POST['kontakte'] as $index => $kontakt) {
                    if (!empty($kontakt['name'])) {
                        $kontaktdaten[] = [
                            'name' => $kontakt['name'],
                            'rolle' => $kontakt['rolle'] ?? '',
                            'email' => $kontakt['email'] ?? '',
                            'telefon' => $kontakt['telefon'] ?? ''
                        ];
                    }
                }
            }
            
            $data['kontaktdaten'] = $kontaktdaten;
            
            $korrespondent = Korrespondent::create($data);
            
            // Für htmx-Requests
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => 'Korrespondent erfolgreich erstellt',
                    'redirect' => "/korrespondenten/{$korrespondent->id}"
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                "/korrespondenten/{$korrespondent->id}",
                'Korrespondent erfolgreich erstellt',
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
            
            $this->redirectWithMessage('/korrespondenten/create', $e->getMessage(), 'error');
        }
    }
    
    /**
     * Korrespondent bearbeiten - Formular anzeigen
     */
    public function edit(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $korrespondent = Korrespondent::find($id);
        
        if (!$korrespondent) {
            http_response_code(404);
            return $this->view('errors/404');
        }
        
        $this->authorize('update', 'korrespondent', ['id' => $id]);
        
        return $this->view('pages/korrespondenten/edit', [
            'page_title' => 'Korrespondent bearbeiten',
            'korrespondent' => $korrespondent
        ]);
    }
    
    /**
     * Korrespondent aktualisieren
     */
    public function update(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $korrespondent = Korrespondent::find($id);
        
        if (!$korrespondent) {
            http_response_code(404);
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json(['success' => false, 'message' => 'Korrespondent nicht gefunden'], 404);
                return;
            }
            $this->redirect('/korrespondenten');
            return;
        }
        
        $this->authorize('update', 'korrespondent', ['id' => $id]);
        
        try {
            $data = $this->validate([
                'name' => ['required', 'max:200'],
                'adresse' => ['required', 'max:500'],
                'webseite' => '',
                'hotline' => ''
            ]);
            
            // Kontaktdaten verarbeiten
            $kontaktdaten = [];
            if (!empty($_POST['kontakte'])) {
                foreach ($_POST['kontakte'] as $index => $kontakt) {
                    if (!empty($kontakt['name'])) {
                        $kontaktdaten[] = [
                            'name' => $kontakt['name'],
                            'rolle' => $kontakt['rolle'] ?? '',
                            'email' => $kontakt['email'] ?? '',
                            'telefon' => $kontakt['telefon'] ?? ''
                        ];
                    }
                }
            }
            
            $data['kontaktdaten'] = $kontaktdaten;
            
            $korrespondent->update($data);
            
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => 'Korrespondent erfolgreich aktualisiert',
                    'redirect' => "/korrespondenten/{$korrespondent->id}"
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                "/korrespondenten/{$korrespondent->id}",
                'Korrespondent erfolgreich aktualisiert',
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
            
            $this->redirectWithMessage("/korrespondenten/{$id}/edit", $e->getMessage(), 'error');
        }
    }
    
    /**
     * Korrespondent löschen
     */
    public function delete(): void
    {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $korrespondent = Korrespondent::find($id);
        
        if (!$korrespondent) {
            http_response_code(404);
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json(['success' => false, 'message' => 'Korrespondent nicht gefunden'], 404);
                return;
            }
            $this->redirect('/korrespondenten');
            return;
        }
        
        $this->authorize('delete', 'korrespondent', ['id' => $id]);
        
        try {
            $name = $korrespondent->name;
            $korrespondent->delete();
            
            if (!empty($_SERVER['HTTP_HX_REQUEST'])) {
                $this->json([
                    'success' => true,
                    'message' => "Korrespondent '$name' erfolgreich gelöscht",
                    'redirect' => '/korrespondenten'
                ]);
                return;
            }
            
            $this->redirectWithMessage(
                '/korrespondenten',
                "Korrespondent '$name' erfolgreich gelöscht",
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
            
            $this->redirectWithMessage("/korrespondenten/{$id}", $e->getMessage(), 'error');
        }
    }
    
    /**
     * Korrespondent-Verträge als Fragment laden
     */
    public function vertraege(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $korrespondent = Korrespondent::find($id);
        
        if (!$korrespondent) {
            return '<div class="alert alert-error">Korrespondent nicht gefunden</div>';
        }
        
        $vertraege = $korrespondent->getVertraege();
        
        return $this->view('partials/korrespondenten/vertraege-list', [
            'korrespondent' => $korrespondent,
            'vertraege' => $vertraege
        ]);
    }
    
    /**
     * Korrespondent-Dokumente als Fragment laden
     */
    public function dokumente(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $korrespondent = Korrespondent::find($id);
        
        if (!$korrespondent) {
            return '<div class="alert alert-error">Korrespondent nicht gefunden</div>';
        }
        
        $dokumente = $korrespondent->getDokumente();
        
        return $this->view('partials/korrespondenten/dokumente-list', [
            'korrespondent' => $korrespondent,
            'dokumente' => $dokumente
        ]);
    }
    
    /**
     * Korrespondent-Zugangsdaten als Fragment laden
     */
    public function zugangsdaten(): string
    {
        $id = (int) ($_GET['id'] ?? 0);
        $korrespondent = Korrespondent::find($id);
        
        if (!$korrespondent) {
            return '<div class="alert alert-error">Korrespondent nicht gefunden</div>';
        }
        
        $zugangsdaten = $korrespondent->getZugangsdaten();
        
        return $this->view('partials/korrespondenten/zugangsdaten-list', [
            'korrespondent' => $korrespondent,
            'zugangsdaten' => $zugangsdaten
        ]);
    }
    
    /**
     * Korrespondent-Statistiken aktualisieren
     */
    public function stats(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $korrespondent = Korrespondent::find($id);
        
        if (!$korrespondent) {
            $this->json(['error' => 'Korrespondent nicht gefunden'], 404);
            return;
        }
        
        $stats = $korrespondent->getStats();
        
        $this->json([
            'stats' => $stats,
            'formatted_monthly_costs' => number_format($stats['total_kosten_pro_monat'] / 100, 2, ',', '.') . ' €'
        ]);
    }
    
    /**
     * Autocomplete für Korrespondenten-Suche
     */
    public function search(): void
    {
        $query = $_GET['q'] ?? '';
        $limit = (int) ($_GET['limit'] ?? 10);
        
        if (strlen($query) < 2) {
            $this->json(['results' => []]);
            return;
        }
        
        try {
            $results = Korrespondent::search($query, $limit);
            $this->json(['results' => $results]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Bulk-Aktionen für Korrespondenten
     */
    public function bulk(): void
    {
        $action = $_POST['action'] ?? '';
        $ids = $_POST['ids'] ?? [];
        
        if (empty($action) || empty($ids)) {
            $this->json(['success' => false, 'message' => 'Aktion und IDs erforderlich'], 400);
            return;
        }
        
        try {
            $count = 0;
            
            foreach ($ids as $id) {
                $korrespondent = Korrespondent::find((int) $id);
                if (!$korrespondent) continue;
                
                switch ($action) {
                    case 'delete':
                        if (rbac_check('delete', 'korrespondent', ['id' => $id])) {
                            $korrespondent->delete();
                            $count++;
                        }
                        break;
                        
                    // Weitere Bulk-Aktionen können hier hinzugefügt werden
                }
            }
            
            $this->json([
                'success' => true,
                'message' => "$count Korrespondenten erfolgreich bearbeitet",
                'count' => $count
            ]);
            
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Export Korrespondenten als CSV
     */
    public function export(): void
    {
        $this->authorize('export', 'korrespondent');
        
        try {
            $korrespondenten = Korrespondent::all();
            
            $filename = 'korrespondenten_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"$filename\"");
            
            $output = fopen('php://output', 'w');
            
            // UTF-8 BOM für Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Header
            fputcsv($output, [
                'ID',
                'Name', 
                'Adresse',
                'Webseite',
                'Hotline',
                'Verträge aktiv',
                'Verträge gesamt',
                'Monatliche Kosten',
                'Dokumente',
                'Zugangsdaten',
                'Erstellt am'
            ], ';');
            
            // Daten
            foreach ($korrespondenten as $korrespondent) {
                $stats = $korrespondent->getStats();
                
                fputcsv($output, [
                    $korrespondent->id,
                    $korrespondent->name,
                    $korrespondent->adresse,
                    $korrespondent->webseite,
                    $korrespondent->hotline,
                    $stats['active_vertraege'],
                    $stats['total_vertraege'],
                    number_format($stats['total_kosten_pro_monat'] / 100, 2, ',', '.') . ' €',
                    $stats['dokumente_count'],
                    $stats['zugangsdaten_count'],
                    $korrespondent->created_at->format('d.m.Y H:i')
                ], ';');
            }
            
            fclose($output);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo 'Fehler beim Erstellen des Exports: ' . $e->getMessage();
        }
    }
}